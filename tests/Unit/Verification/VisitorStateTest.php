<?php

namespace ProofAge\PrestaShop\Tests\Unit\Verification;

use PHPUnit\Framework\TestCase;
use ProofAge\PrestaShop\Tests\Unit\Fakes\FixedClock;
use ProofAge\PrestaShop\Tests\Unit\Fakes\InMemoryCustomerVerificationRepository;
use ProofAge\PrestaShop\Tests\Unit\Fakes\InMemoryVerificationRepository;
use ProofAge\PrestaShop\Verification\CustomerVerification;
use ProofAge\PrestaShop\Verification\TtlPolicy;
use ProofAge\PrestaShop\Verification\VerificationRecord;
use ProofAge\PrestaShop\Verification\VisitorState;

final class VisitorStateTest extends TestCase
{
    private InMemoryVerificationRepository $verifications;
    private InMemoryCustomerVerificationRepository $customers;
    private FixedClock $clock;
    private VisitorState $state;

    protected function setUp(): void
    {
        $this->verifications = new InMemoryVerificationRepository();
        $this->customers = new InMemoryCustomerVerificationRepository();
        $this->clock = new FixedClock();
        $this->state = new VisitorState($this->verifications, $this->customers, $this->clock);
    }

    private function guestRecord(string $status = 'approved', int $ttl = 3600, ?int $idCart = null, int $idShop = 1): VerificationRecord
    {
        $r = new VerificationRecord();
        $r->verificationId = 'ver-g';
        $r->idShop = $idShop;
        $r->externalId = 'ps-1-gx';
        $r->sessionTokenHash = hash('sha256', 'tok');
        $r->status = $status;
        $r->method = 'wallet';
        $r->decidedAt = $this->clock->now - 10;
        $r->expiresAt = $status === 'approved' ? $this->clock->now + $ttl : null;
        $r->idCart = $idCart;
        $this->verifications->insert($r);

        return $r;
    }

    public function testApprovedGuestWithMatchingToken(): void
    {
        $this->guestRecord();
        $approval = $this->state->resolveApproval(0, 1, 0, 'ver-g', 'tok');

        self::assertNotNull($approval);
        self::assertSame('ver-g', $approval->verificationId);
        self::assertSame('wallet', $approval->method);
        self::assertTrue($this->state->isVerified(0, 1, 'ver-g', 'tok'));
    }

    public function testGuestWithWrongTokenIsNotVerified(): void
    {
        $this->guestRecord();
        self::assertFalse($this->state->isVerified(0, 1, 'ver-g', 'other'));
        self::assertFalse($this->state->isVerified(0, 1, 'ver-g', null));
    }

    public function testExpiredOrPendingGuestIsNotVerified(): void
    {
        $this->guestRecord('approved', -1);
        self::assertFalse($this->state->isVerified(0, 1, 'ver-g', 'tok'));

        $this->verifications->records = [];
        $this->guestRecord('review');
        self::assertFalse($this->state->isVerified(0, 1, 'ver-g', 'tok'));
    }

    public function testGuestRecordFromAnotherShopIsIgnored(): void
    {
        $this->guestRecord('approved', 3600, null, 2);
        self::assertFalse($this->state->isVerified(0, 1, 'ver-g', 'tok'));
    }

    public function testCustomerRecord(): void
    {
        $this->customers->save(new CustomerVerification(42, 1, 'ver-c', 100, null));
        self::assertTrue($this->state->isVerified(42, 1, null, null));
        self::assertFalse($this->state->isVerified(42, 2, null, null));

        $this->customers->save(new CustomerVerification(43, 1, 'ver-d', 100, $this->clock->now - 1));
        self::assertFalse($this->state->isVerified(43, 1, null, null));
    }

    public function testCartBoundApprovalForCookielessCallbacks(): void
    {
        $this->guestRecord('approved', 3600, 77);

        self::assertNull($this->state->resolveApproval(0, 1, 0, null, null));
        self::assertNotNull($this->state->resolveApproval(0, 1, 77, null, null));
        self::assertNull($this->state->resolveApproval(0, 1, 78, null, null));
        self::assertNull($this->state->resolveApproval(0, 2, 77, null, null));
    }

    public function testPromoteGuestCreatesCustomerRecord(): void
    {
        $record = $this->guestRecord();

        self::assertTrue($this->state->promoteGuest(42, 1, 'ver-g', 'tok', new TtlPolicy(24, 30)));

        $customer = $this->customers->find(42, 1);
        self::assertNotNull($customer);
        self::assertSame('ver-g', $customer->verificationId);
        self::assertSame($record->decidedAt + 30 * 86400, $customer->expiresAt);
        self::assertSame(42, $record->idCustomer);
    }

    public function testPromoteIgnoresUnapprovedGuest(): void
    {
        $this->guestRecord('review');
        self::assertFalse($this->state->promoteGuest(42, 1, 'ver-g', 'tok', new TtlPolicy(24, 30)));
        self::assertNull($this->customers->find(42, 1));
    }

    public function testPromoteRefusesGuestRecordOwnedByAnotherCustomer(): void
    {
        $record = $this->guestRecord();
        $record->idCustomer = 7;
        $this->verifications->update($record);

        self::assertFalse($this->state->promoteGuest(42, 1, 'ver-g', 'tok', new TtlPolicy(24, 30)));
        self::assertNull($this->customers->find(42, 1));
        self::assertSame(7, $this->verifications->findByVerificationId('ver-g')->idCustomer);
    }

    public function testPromoteAgainForTheSameCustomerIsAllowed(): void
    {
        $record = $this->guestRecord();
        $record->idCustomer = 42;
        $this->verifications->update($record);

        self::assertTrue($this->state->promoteGuest(42, 1, 'ver-g', 'tok', new TtlPolicy(24, 30)));
        self::assertNotNull($this->customers->find(42, 1));
    }

    public function testResetCustomerRevokesEveryPathToTheVerification(): void
    {
        $record = $this->guestRecord('approved', 3600, 77);
        $record->idCustomer = 42;
        $this->verifications->update($record);
        $this->customers->save(new CustomerVerification(42, 1, 'ver-g', $this->clock->now - 10, null));

        $this->state->resetCustomer(42, 1);

        self::assertNull($this->customers->find(42, 1));
        self::assertFalse($this->state->isVerified(0, 1, 'ver-g', 'tok'));
        self::assertNull($this->state->resolveApproval(0, 1, 77, null, null));
        self::assertFalse($this->state->promoteGuest(42, 1, 'ver-g', 'tok', new TtlPolicy(24, 30)));
        self::assertNull($this->customers->find(42, 1));
    }

    public function testResetCustomerLeavesOtherCustomersAlone(): void
    {
        $record = $this->guestRecord();
        $record->idCustomer = 7;
        $this->verifications->update($record);

        $this->state->resetCustomer(42, 1);

        self::assertTrue($this->state->isVerified(0, 1, 'ver-g', 'tok'));
    }
}

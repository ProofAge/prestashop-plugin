<?php

namespace ProofAge\PrestaShop\Tests\Unit\Verification;

use PHPUnit\Framework\TestCase;
use ProofAge\PrestaShop\Api\ApiException;
use ProofAge\PrestaShop\Tests\Unit\Fakes\FakeVerificationApi;
use ProofAge\PrestaShop\Tests\Unit\Fakes\FixedClock;
use ProofAge\PrestaShop\Tests\Unit\Fakes\InMemoryCustomerVerificationRepository;
use ProofAge\PrestaShop\Tests\Unit\Fakes\InMemoryOrderSnapshotRepository;
use ProofAge\PrestaShop\Tests\Unit\Fakes\InMemoryVerificationRepository;
use ProofAge\PrestaShop\Tests\Unit\Fakes\SpyLogger;
use ProofAge\PrestaShop\Verification\OrderSnapshot;
use ProofAge\PrestaShop\Verification\RateLimitedException;
use ProofAge\PrestaShop\Verification\StartRequest;
use ProofAge\PrestaShop\Verification\TtlPolicy;
use ProofAge\PrestaShop\Verification\VerificationService;

final class VerificationServiceTest extends TestCase
{
    private FakeVerificationApi $api;
    private InMemoryVerificationRepository $verifications;
    private InMemoryCustomerVerificationRepository $customers;
    private InMemoryOrderSnapshotRepository $orders;
    private FixedClock $clock;
    private SpyLogger $logger;
    private VerificationService $service;

    protected function setUp(): void
    {
        $this->api = new FakeVerificationApi();
        $this->verifications = new InMemoryVerificationRepository();
        $this->customers = new InMemoryCustomerVerificationRepository();
        $this->orders = new InMemoryOrderSnapshotRepository();
        $this->clock = new FixedClock();
        $this->logger = new SpyLogger();
        $this->service = new VerificationService(
            $this->api,
            $this->verifications,
            $this->customers,
            $this->orders,
            $this->clock,
            new TtlPolicy(24, 365),
            $this->logger
        );
    }

    private function request(?int $idCustomer = null, ?string $vid = null, ?string $token = null, string $ip = 'ip-1'): StartRequest
    {
        $r = new StartRequest();
        $r->idShop = 1;
        $r->idCustomer = $idCustomer;
        $r->guestKey = 'guest-uuid';
        $r->cookieVerificationId = $vid;
        $r->cookieToken = $token;
        $r->ipHash = $ip;
        $r->originUrl = 'https://shop.test/3-clothes';
        $r->language = 'fr';
        $r->metadata = ['integration' => 'prestashop-module'];
        $r->callbackUrlFactory = static fn (string $ref): string => 'https://shop.test/module/proofage/return?ref=' . $ref;

        return $r;
    }

    public function testStartCreatesGuestVerification(): void
    {
        $result = $this->service->start($this->request());

        self::assertFalse($result->reused);
        self::assertSame('ver-1', $result->verificationId);
        self::assertSame('https://idv.proofage.net/v/ver-1', $result->url);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $result->newToken);

        $payload = $this->api->created[0];
        self::assertSame('ps-1-gguest-uuid', $payload['external_id']);
        self::assertSame(['integration' => 'prestashop-module'], $payload['external_metadata']);
        self::assertSame(['sdk_preferences' => ['language' => 'fr']], $payload['metadata']);

        $record = $this->verifications->findByVerificationId('ver-1');
        self::assertNotNull($record);
        self::assertSame('https://shop.test/module/proofage/return?ref=' . $record->returnRef, $payload['callback_url']);
        self::assertTrue($record->matchesToken($result->newToken));
        self::assertSame('https://shop.test/3-clothes', $record->originUrl);
        self::assertSame('created', $record->status);
        self::assertNull($record->idCustomer);
        self::assertSame($this->clock->now, $record->createdAt);
    }

    public function testStartUsesCustomerExternalId(): void
    {
        $this->service->start($this->request(42));

        self::assertSame('ps-1-c42', $this->api->created[0]['external_id']);
        self::assertSame(42, $this->verifications->findByVerificationId('ver-1')->idCustomer);
    }

    public function testStartReusesUnfinishedVerificationForSameBrowser(): void
    {
        $first = $this->service->start($this->request());
        $this->verifications->findByVerificationId('ver-1')->status = 'review';

        $second = $this->service->start($this->request(null, 'ver-1', $first->newToken));

        self::assertTrue($second->reused);
        self::assertNull($second->newToken);
        self::assertSame('ver-1', $second->verificationId);
        self::assertCount(1, $this->api->created);
    }

    public function testGuestVerificationReusedAfterLoginIsBoundToCustomer(): void
    {
        $first = $this->service->start($this->request());

        $second = $this->service->start($this->request(42, 'ver-1', $first->newToken));

        self::assertTrue($second->reused);
        self::assertCount(1, $this->api->created);
        $record = $this->verifications->findByVerificationId('ver-1');
        self::assertSame(42, $record->idCustomer);
        self::assertSame('ps-1-gguest-uuid', $record->externalId);

        $this->service->applyStatus($record, 'approved', null, null);
        self::assertNotNull($this->customers->find(42, 1));
    }

    public function testStartDoesNotReuseRecordOwnedByAnotherCustomer(): void
    {
        $first = $this->service->start($this->request(42));

        $second = $this->service->start($this->request(43, 'ver-1', $first->newToken));

        self::assertFalse($second->reused);
        self::assertCount(2, $this->api->created);
        self::assertSame(42, $this->verifications->findByVerificationId('ver-1')->idCustomer);
    }

    public function testStartDoesNotReuseCustomerRecordForGuest(): void
    {
        $first = $this->service->start($this->request(42));

        $second = $this->service->start($this->request(null, 'ver-1', $first->newToken));

        self::assertFalse($second->reused);
        self::assertCount(2, $this->api->created);
    }

    public function testStartDoesNotReuseWithWrongToken(): void
    {
        $this->service->start($this->request());
        $second = $this->service->start($this->request(null, 'ver-1', str_repeat('0', 64)));

        self::assertFalse($second->reused);
        self::assertCount(2, $this->api->created);
    }

    public function testStartDoesNotReuseFinalVerification(): void
    {
        $first = $this->service->start($this->request());
        $this->verifications->findByVerificationId('ver-1')->status = 'declined';

        $second = $this->service->start($this->request(null, 'ver-1', $first->newToken));

        self::assertFalse($second->reused);
    }

    public function testStartDoesNotReuseAfterSevenDays(): void
    {
        $first = $this->service->start($this->request());
        $this->clock->advance(VerificationService::REUSE_WINDOW + 1);

        $second = $this->service->start($this->request(null, 'ver-1', $first->newToken));

        self::assertFalse($second->reused);
    }

    public function testRateLimitPerVisitor(): void
    {
        for ($i = 0; $i < VerificationService::LIMIT_PER_VISITOR; ++$i) {
            $this->service->start($this->request(null, null, null, 'ip-' . $i));
        }
        $this->expectException(RateLimitedException::class);
        $this->service->start($this->request(null, null, null, 'ip-new'));
    }

    public function testRateLimitPerIpAcrossVisitors(): void
    {
        for ($i = 0; $i < VerificationService::LIMIT_PER_IP; ++$i) {
            $this->service->start($this->request(1000 + $i));
        }
        $this->expectException(RateLimitedException::class);
        $this->service->start($this->request(5000));
    }

    public function testRateLimitWindowIsOneHour(): void
    {
        for ($i = 0; $i < VerificationService::LIMIT_PER_VISITOR; ++$i) {
            $this->service->start($this->request(null, null, null, 'ip-' . $i));
        }
        $this->clock->advance(3601);
        $result = $this->service->start($this->request(null, null, null, 'ip-x'));
        self::assertFalse($result->reused);
    }

    public function testStartRejectsApiResponseWithoutUrl(): void
    {
        $api = new class extends FakeVerificationApi {
            public function createVerification(array $payload): array
            {
                return ['id' => 'x'];
            }
        };
        $service = new VerificationService($api, $this->verifications, $this->customers, $this->orders, $this->clock, new TtlPolicy(24, 365), $this->logger);

        $this->expectException(ApiException::class);
        $service->start($this->request());
    }

    public function testApproveSetsGuestExpiryAndCustomerRecord(): void
    {
        $this->service->start($this->request(42));
        $record = $this->verifications->findByVerificationId('ver-1');

        self::assertTrue($this->service->applyStatus($record, 'approved', 'wallet', null));

        self::assertSame('approved', $record->status);
        self::assertSame('wallet', $record->method);
        self::assertSame($this->clock->now, $record->decidedAt);
        self::assertSame($this->clock->now + 24 * 3600, $record->expiresAt);
        $customer = $this->customers->find(42, 1);
        self::assertNotNull($customer);
        self::assertSame('ver-1', $customer->verificationId);
        self::assertSame($this->clock->now + 365 * 86400, $customer->expiresAt);
    }

    public function testRevocationRemovesCustomerAndMarksOrders(): void
    {
        $this->service->start($this->request(42));
        $record = $this->verifications->findByVerificationId('ver-1');
        $this->service->applyStatus($record, 'approved', null, null);
        $this->orders->save(new OrderSnapshot(7, true, 'ver-1', OrderSnapshot::STATUS_APPROVED, null, $this->clock->now));

        self::assertTrue($this->service->applyStatus($record, 'declined', null, 'verification.revoked'));

        self::assertSame('declined', $record->status);
        self::assertSame('verification.revoked', $record->reason);
        self::assertNull($record->expiresAt);
        self::assertNull($this->customers->find(42, 1));
        self::assertSame(OrderSnapshot::STATUS_REVOKED, $this->orders->find(7)->status);
    }

    public function testLateReviewAfterDecisionIsIgnored(): void
    {
        $this->service->start($this->request());
        $record = $this->verifications->findByVerificationId('ver-1');
        $this->service->applyStatus($record, 'declined', null, 'too_young');
        $updates = $this->verifications->updates;

        self::assertFalse($this->service->applyStatus($record, 'review', null, null));
        self::assertFalse($this->service->applyStatus($record, 'approved', null, null));
        self::assertSame('declined', $record->status);
        self::assertSame($updates, $this->verifications->updates);
    }

    public function testResubmissionKeepsVerificationOpen(): void
    {
        $this->service->start($this->request());
        $record = $this->verifications->findByVerificationId('ver-1');

        $this->service->applyStatus($record, 'resubmission_requested', null, 'blurry');

        self::assertSame('resubmission_requested', $record->status);
        self::assertNull($record->decidedAt);
    }

    public function testSyncAppliesRemoteStatus(): void
    {
        $this->service->start($this->request());
        $record = $this->verifications->findByVerificationId('ver-1');
        $this->api->remote['ver-1']['status'] = 'approved';
        $this->api->remote['ver-1']['method'] = 'wallet';

        $this->service->sync($record);

        self::assertSame('approved', $record->status);
        self::assertSame('wallet', $record->method);
        self::assertSame($this->clock->now, $record->lastSyncedAt);
    }

    public function testSyncIsThrottled(): void
    {
        $this->service->start($this->request());
        $record = $this->verifications->findByVerificationId('ver-1');

        $this->service->sync($record);
        $this->clock->advance(VerificationService::SYNC_INTERVAL - 1);
        $this->service->sync($record);
        self::assertSame(1, $this->api->getCalls);

        $this->clock->advance(1);
        $this->service->sync($record);
        self::assertSame(2, $this->api->getCalls);
    }

    public function testSyncSkipsFinalVerification(): void
    {
        $this->service->start($this->request());
        $record = $this->verifications->findByVerificationId('ver-1');
        $this->service->applyStatus($record, 'approved', null, null);

        $this->service->sync($record);

        self::assertSame(0, $this->api->getCalls);
    }

    public function testSyncIgnoresExternalIdMismatch(): void
    {
        $this->service->start($this->request());
        $record = $this->verifications->findByVerificationId('ver-1');
        $this->api->remote['ver-1'] = ['id' => 'ver-1', 'external_id' => 'someone-else', 'status' => 'approved'];

        $this->service->sync($record);

        self::assertSame('created', $record->status);
        self::assertNotEmpty($this->logger->warnings);
    }

    public function testSyncSwallowsApiErrors(): void
    {
        $this->service->start($this->request());
        $record = $this->verifications->findByVerificationId('ver-1');
        $this->api->failWith = new ApiException('down', 503, null);

        $this->service->sync($record);

        self::assertSame('created', $record->status);
        self::assertNotEmpty($this->logger->warnings);
    }
}

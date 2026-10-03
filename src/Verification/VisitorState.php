<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Verification;

use ProofAge\PrestaShop\Support\Clock;

/**
 * Answers "is this visitor verified?" from local data only (never calls the API).
 */
final class VisitorState
{
    /** @var VerificationRepository */
    private $verifications;
    /** @var CustomerVerificationRepository */
    private $customers;
    /** @var Clock */
    private $clock;

    public function __construct(VerificationRepository $verifications, CustomerVerificationRepository $customers, Clock $clock)
    {
        $this->verifications = $verifications;
        $this->customers = $customers;
        $this->clock = $clock;
    }

    /**
     * @return Approval|null
     */
    public function resolveApproval(int $idCustomer, int $idShop, int $idCart, ?string $cookieVerificationId, ?string $cookieToken)
    {
        $now = $this->clock->now();

        if ($idCustomer > 0) {
            $customer = $this->customers->find($idCustomer, $idShop);
            if ($customer !== null && $customer->isValidAt($now)) {
                $record = $this->verifications->findByVerificationId($customer->verificationId);

                return new Approval($customer->verificationId, $record !== null ? $record->method : null, $customer->verifiedAt);
            }
        }

        $guest = $this->approvedGuestRecord($cookieVerificationId, $cookieToken);
        if ($guest !== null && $guest->idShop === $idShop) {
            return new Approval($guest->verificationId, $guest->method, (int) $guest->decidedAt);
        }

        if ($idCart > 0) {
            $bound = $this->verifications->findApprovedForCart($idCart, $now);
            if ($bound !== null && $bound->idShop === $idShop) {
                return new Approval($bound->verificationId, $bound->method, (int) $bound->decidedAt);
            }
        }

        return null;
    }

    public function isVerified(int $idCustomer, int $idShop, ?string $cookieVerificationId, ?string $cookieToken): bool
    {
        return $this->resolveApproval($idCustomer, $idShop, 0, $cookieVerificationId, $cookieToken) !== null;
    }

    /**
     * @return VerificationRecord|null
     */
    public function approvedGuestRecord(?string $cookieVerificationId, ?string $cookieToken)
    {
        if (!$cookieVerificationId || !$cookieToken) {
            return null;
        }
        $record = $this->verifications->findByVerificationId($cookieVerificationId);
        if ($record === null || !$record->matchesToken($cookieToken) || !$record->isApprovedAt($this->clock->now())) {
            return null;
        }

        return $record;
    }

    /**
     * Back-office reset: the customer must verify again. Expiring the approved records too keeps the
     * browser cookie, a bound cart and a later login promotion from restoring the access.
     */
    public function resetCustomer(int $idCustomer, int $idShop): void
    {
        $this->customers->delete($idCustomer, $idShop);
        $this->verifications->expireApprovedForCustomer($idCustomer, $idShop, $this->clock->now());
    }

    public function promoteGuest(int $idCustomer, int $idShop, ?string $cookieVerificationId, ?string $cookieToken, TtlPolicy $ttl): bool
    {
        if ($idCustomer <= 0) {
            return false;
        }
        $record = $this->approvedGuestRecord($cookieVerificationId, $cookieToken);
        if ($record === null || $record->idShop !== $idShop) {
            return false;
        }
        // A shared browser must not hand one account's verification to another account.
        if ($record->idCustomer !== null && $record->idCustomer !== $idCustomer) {
            return false;
        }
        $verifiedAt = $record->decidedAt !== null ? $record->decidedAt : $this->clock->now();
        $this->customers->save(new CustomerVerification($idCustomer, $idShop, $record->verificationId, $verifiedAt, $ttl->customerExpiry($verifiedAt)));
        $record->idCustomer = $idCustomer;
        $record->updatedAt = $this->clock->now();
        $this->verifications->update($record);

        return true;
    }
}

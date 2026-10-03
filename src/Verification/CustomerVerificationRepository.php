<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Verification;

interface CustomerVerificationRepository
{
    /**
     * @return CustomerVerification|null
     */
    public function find(int $idCustomer, int $idShop): ?CustomerVerification;

    /**
     * Upsert on (id_customer, id_shop).
     */
    public function save(CustomerVerification $verification): void;

    public function deleteByVerificationId(string $verificationId): void;

    public function delete(int $idCustomer, int $idShop): void;
}

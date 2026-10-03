<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Verification;

interface VerificationRepository
{
    /**
     * @return VerificationRecord|null
     */
    public function findByVerificationId(string $verificationId): ?VerificationRecord;

    /**
     * @return VerificationRecord|null
     */
    public function findByReturnRef(string $returnRef): ?VerificationRecord;

    /**
     * @return VerificationRecord|null
     */
    public function findApprovedForCart(int $idCart, int $now): ?VerificationRecord;

    /**
     * @param string $column 'external_id' or 'ip_hash'
     */
    public function countCreatedSince(string $column, string $value, int $since): int;

    /**
     * Persists a new record and sets $record->id.
     */
    public function insert(VerificationRecord $record): void;

    public function update(VerificationRecord $record): void;

    /**
     * Ends every still-approved verification of a customer in a shop (expires_at = $now)
     * and unbinds it from its cart; the rows stay for audit.
     */
    public function expireApprovedForCustomer(int $idCustomer, int $idShop, int $now): void;
}

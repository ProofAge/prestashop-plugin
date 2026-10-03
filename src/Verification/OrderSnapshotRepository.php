<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Verification;

interface OrderSnapshotRepository
{
    public function save(OrderSnapshot $snapshot): void;

    /**
     * @return OrderSnapshot|null
     */
    public function find(int $idOrder): ?OrderSnapshot;

    public function markRevoked(string $verificationId): void;
}

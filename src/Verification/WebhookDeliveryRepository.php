<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Verification;

interface WebhookDeliveryRepository
{
    public function exists(string $deliveryId): bool;

    /**
     * Ignores duplicate delivery ids.
     */
    public function insert(string $deliveryId, string $verificationId, string $status, int $receivedAt): void;
}

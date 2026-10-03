<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Api;

interface VerificationApi
{
    /**
     * @param array<string,mixed> $payload
     *
     * @return array<string,mixed>
     */
    public function createVerification(array $payload): array;

    /**
     * @return array<string,mixed>
     */
    public function getVerification(string $verificationId): array;
}

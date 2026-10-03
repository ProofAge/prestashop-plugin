<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Api;

/**
 * Verifies ProofAge webhook deliveries: hex(hmac_sha256(timestamp + "." + rawBody, secret)).
 */
final class WebhookSignatureVerifier
{
    /** @var string */
    private $secretKey;

    /** @var int */
    private $toleranceSeconds;

    public function __construct(string $secretKey, int $toleranceSeconds = 300)
    {
        $this->secretKey = $secretKey;
        $this->toleranceSeconds = $toleranceSeconds;
    }

    public function verify(string $timestamp, string $signature, string $rawBody, int $now): bool
    {
        if ($timestamp === '' || !ctype_digit($timestamp) || $signature === '') {
            return false;
        }
        if (abs($now - (int) $timestamp) > $this->toleranceSeconds) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $this->secretKey);

        return hash_equals($expected, strtolower(trim($signature)));
    }
}

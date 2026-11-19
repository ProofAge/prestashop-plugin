<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Api;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Signs ProofAge API requests: hex(hmac_sha256(canonical, secret)).
 *
 * The legacy canonical string is METHOD + path[?query] + rawBody. When the request also carries
 * an X-Timestamp header (Unix seconds), the canonical string is "{timestamp}." + the legacy one.
 */
final class RequestSigner
{
    /** @var string */
    private $secretKey;

    public function __construct(string $secretKey)
    {
        $this->secretKey = $secretKey;
    }

    public function sign(string $method, string $pathWithQuery, string $body): string
    {
        return hash_hmac('sha256', $this->canonical($method, $pathWithQuery, $body), $this->secretKey);
    }

    /**
     * Signature for a request that sends the X-Timestamp header with the same $timestamp.
     */
    public function signWithTimestamp(string $method, string $pathWithQuery, string $body, int $timestamp): string
    {
        return hash_hmac('sha256', $this->canonicalWithTimestamp($method, $pathWithQuery, $body, $timestamp), $this->secretKey);
    }

    public function canonical(string $method, string $pathWithQuery, string $body): string
    {
        return strtoupper($method) . $pathWithQuery . $body;
    }

    public function canonicalWithTimestamp(string $method, string $pathWithQuery, string $body, int $timestamp): string
    {
        return $timestamp . '.' . $this->canonical($method, $pathWithQuery, $body);
    }
}

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
 * Signs ProofAge API requests: hex(hmac_sha256(METHOD + path[?query] + rawBody, secret)).
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
        return hash_hmac('sha256', strtoupper($method) . $pathWithQuery . $body, $this->secretKey);
    }
}

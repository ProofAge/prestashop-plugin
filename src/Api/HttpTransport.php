<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Api;

interface HttpTransport
{
    /**
     * @param array<string,string> $headers
     *
     * @throws ApiException when the request cannot be sent
     */
    public function send(string $method, string $url, array $headers, string $body): HttpResponse;
}

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

final class ApiClient implements VerificationApi
{
    /** @var HttpTransport */
    private $transport;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $basePath;

    /** @var string */
    private $publicKey;

    /** @var RequestSigner */
    private $signer;

    public function __construct(HttpTransport $transport, string $baseUrl, string $publicKey, RequestSigner $signer)
    {
        $this->transport = $transport;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->basePath = rtrim((string) parse_url($this->baseUrl, PHP_URL_PATH), '/');
        $this->publicKey = $publicKey;
        $this->signer = $signer;
    }

    public function createVerification(array $payload): array
    {
        return $this->request('POST', '/v1/verifications', $payload);
    }

    public function getVerification(string $verificationId): array
    {
        return $this->request('GET', '/v1/verifications/' . rawurlencode($verificationId), null);
    }

    /**
     * @return array<string,mixed>
     */
    public function getWorkspace(): array
    {
        return $this->request('GET', '/v1/workspace', null);
    }

    /**
     * @param array<string,mixed>|null $payload
     *
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, $payload): array
    {
        $body = '';
        if ($payload !== null) {
            $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                throw new ApiException('Could not encode the request body', 0, null);
            }
            $body = $encoded;
        }

        $headers = [
            'Accept' => 'application/json',
            'X-API-Key' => $this->publicKey,
            'X-HMAC-Signature' => $this->signer->sign($method, $this->basePath . $path, $body),
        ];
        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        $response = $this->transport->send($method, $this->baseUrl . $path, $headers, $body);
        $decoded = json_decode($response->body, true);

        if ($response->status < 200 || $response->status >= 300) {
            $code = is_array($decoded) && isset($decoded['code']) && is_string($decoded['code']) ? $decoded['code'] : null;
            $message = is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])
                ? $decoded['message']
                : 'ProofAge API returned HTTP ' . $response->status;
            throw new ApiException($message, $response->status, $code);
        }
        if (!is_array($decoded)) {
            throw new ApiException('ProofAge API returned an invalid response', $response->status, null);
        }
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            return $decoded['data'];
        }

        return $decoded;
    }
}

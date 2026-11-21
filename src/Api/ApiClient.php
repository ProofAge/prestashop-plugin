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

use ProofAge\PrestaShop\Support\Clock;
use ProofAge\PrestaShop\Support\ModuleInfo;
use ProofAge\PrestaShop\Support\SystemClock;
use ProofAge\PrestaShop\Support\Uuid;

final class ApiClient implements VerificationApi
{
    private const REPLAYED_REQUEST = 'REPLAYED_REQUEST';
    private const TIMESTAMP_OUT_OF_RANGE = 'TIMESTAMP_OUT_OF_RANGE';

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

    /** @var Clock */
    private $clock;

    /** @var ClockSkewStore */
    private $skew;

    /** @var callable(float): void */
    private $sleeper;

    /** @var string */
    private $sdkIdentifier;

    /**
     * @param callable(float): void|null $sleeper receives the number of seconds to wait; defaults to usleep
     */
    public function __construct(
        HttpTransport $transport,
        string $baseUrl,
        string $publicKey,
        RequestSigner $signer,
        ?Clock $clock = null,
        ?ClockSkewStore $skew = null,
        ?callable $sleeper = null
    ) {
        $this->transport = $transport;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->basePath = rtrim((string) parse_url($this->baseUrl, PHP_URL_PATH), '/');
        $this->publicKey = $publicKey;
        $this->signer = $signer;
        $this->clock = $clock ?: new SystemClock();
        $this->skew = $skew ?: new MemoryClockSkewStore();
        $this->sleeper = $sleeper ?: static function (float $seconds): void {
            usleep((int) round($seconds * 1000000));
        };
        $this->sdkIdentifier = 'prestashop/' . ModuleInfo::VERSION;
    }

    public function createVerification(array $payload, ?string $idempotencyKey = null): array
    {
        if ($idempotencyKey === null) {
            $idempotencyKey = Uuid::v4();
        } elseif (!preg_match('/^[\x21-\x7E]{1,255}$/D', $idempotencyKey)) {
            throw new \InvalidArgumentException('The idempotency key must be 1-255 printable ASCII characters');
        }

        return $this->request('POST', '/v1/verifications', $payload, $idempotencyKey);
    }

    public function getVerification(string $verificationId): array
    {
        return $this->request('GET', '/v1/verifications/' . rawurlencode($verificationId), null, null);
    }

    /**
     * @return array<string,mixed>
     */
    public function getWorkspace(): array
    {
        return $this->request('GET', '/v1/workspace', null, null);
    }

    /**
     * Sends a signed request. Every attempt gets its own X-Timestamp and signature, because the
     * server accepts a timestamped non-GET signature once.
     *
     * @param array<string,mixed>|null $payload
     *
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, $payload, ?string $idempotencyKey): array
    {
        $body = '';
        if ($payload !== null) {
            $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                throw new ApiException('Could not encode the request body', 0, null);
            }
            $body = $encoded;
        }

        $isRead = $method === 'GET' || $method === 'HEAD';
        $replayRetried = false;
        $skewRetried = false;

        while (true) {
            $timestamp = $this->clock->now() + $this->skew->offset();
            $headers = [
                'Accept' => 'application/json',
                'X-API-Key' => $this->publicKey,
                'X-Timestamp' => (string) $timestamp,
                'X-HMAC-Signature' => $this->signer->signWithTimestamp($method, $this->basePath . $path, $body, $timestamp),
                'X-ProofAge-Sdk' => $this->sdkIdentifier,
            ];
            if ($payload !== null) {
                $headers['Content-Type'] = 'application/json';
            }
            if ($idempotencyKey !== null) {
                $headers['Idempotency-Key'] = $idempotencyKey;
            }

            $response = $this->transport->send($method, $this->baseUrl . $path, $headers, $body);
            $decoded = json_decode($response->body, true);

            if ($response->status === 401) {
                $code = $this->errorCode($decoded);
                if ($code === self::REPLAYED_REQUEST && !$isRead && !$replayRetried) {
                    // The signature was already used (same body, same second); the server did not act on it.
                    $replayRetried = true;
                    $this->waitForNextSecond();
                    continue;
                }
                if ($code === self::TIMESTAMP_OUT_OF_RANGE && !$skewRetried) {
                    $serverTime = $this->serverTime($response);
                    if ($serverTime !== null) {
                        $skewRetried = true;
                        $this->skew->save($serverTime - $this->clock->now());
                        continue;
                    }
                }
            }

            return $this->decode($response, $decoded);
        }
    }

    /**
     * @param mixed $decoded
     *
     * @return array<string,mixed>
     */
    private function decode(HttpResponse $response, $decoded): array
    {
        if ($response->status < 200 || $response->status >= 300) {
            $message = $this->errorMessage($decoded);

            throw new ApiException($message !== null ? $message : 'ProofAge API returned HTTP ' . $response->status, $response->status, $this->errorCode($decoded));
        }
        if (!is_array($decoded)) {
            throw new ApiException('ProofAge API returned an invalid response', $response->status, null);
        }
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            return $decoded['data'];
        }

        return $decoded;
    }

    /**
     * The error code, whether the API sends it at the top level or inside an "error" object.
     *
     * @param mixed $decoded
     */
    private function errorCode($decoded): ?string
    {
        return $this->errorField($decoded, 'code');
    }

    /**
     * @param mixed $decoded
     */
    private function errorMessage($decoded): ?string
    {
        return $this->errorField($decoded, 'message');
    }

    /**
     * @param mixed $decoded
     */
    private function errorField($decoded, string $field): ?string
    {
        if (!is_array($decoded)) {
            return null;
        }
        if (isset($decoded['error']) && is_array($decoded['error']) && isset($decoded['error'][$field]) && is_string($decoded['error'][$field])) {
            return $decoded['error'][$field];
        }

        return isset($decoded[$field]) && is_string($decoded[$field]) ? $decoded[$field] : null;
    }

    private function serverTime(HttpResponse $response): ?int
    {
        $date = $response->header('Date');
        if ($date === null) {
            return null;
        }
        $parsed = strtotime($date);

        return $parsed === false ? null : $parsed;
    }

    /**
     * Waits until the next whole second (plus a margin), so the next X-Timestamp differs from the last one.
     */
    private function waitForNextSecond(): void
    {
        $now = microtime(true);
        call_user_func($this->sleeper, (floor($now) + 1) - $now + 0.05);
    }
}

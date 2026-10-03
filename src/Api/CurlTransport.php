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

final class CurlTransport implements HttpTransport
{
    /** @var int */
    private $timeoutSeconds;

    /** @var string */
    private $userAgent;

    public function __construct(int $timeoutSeconds = 10, string $userAgent = 'ProofAge-PrestaShop')
    {
        $this->timeoutSeconds = $timeoutSeconds;
        $this->userAgent = $userAgent;
    }

    public function send(string $method, string $url, array $headers, string $body): HttpResponse
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new ApiException('Could not initialise cURL', 0, null);
        }
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($body !== '') {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        if (PHP_VERSION_ID < 80000) {
            curl_close($handle);
        }
        if ($raw === false) {
            throw new ApiException('Could not reach the ProofAge API: ' . $error, 0, null);
        }

        return new HttpResponse($status, (string) $raw);
    }
}

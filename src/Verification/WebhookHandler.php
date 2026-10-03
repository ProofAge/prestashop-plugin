<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Verification;

use ProofAge\PrestaShop\Api\WebhookSignatureVerifier;
use ProofAge\PrestaShop\Support\Clock;
use ProofAge\PrestaShop\Support\Logger;

/**
 * Processes ProofAge webhook deliveries. ProofAge retries only 408/429/5xx,
 * so 401 is reserved for real signature failures and a missing secret answers 503.
 */
final class WebhookHandler
{
    /** @var WebhookSignatureVerifier|null */
    private $verifier;
    /** @var WebhookDeliveryRepository */
    private $deliveries;
    /** @var VerificationRepository */
    private $verifications;
    /** @var VerificationService */
    private $service;
    /** @var Clock */
    private $clock;
    /** @var Logger */
    private $logger;

    public function __construct(
        ?WebhookSignatureVerifier $verifier,
        WebhookDeliveryRepository $deliveries,
        VerificationRepository $verifications,
        VerificationService $service,
        Clock $clock,
        Logger $logger
    ) {
        $this->verifier = $verifier;
        $this->deliveries = $deliveries;
        $this->verifications = $verifications;
        $this->service = $service;
        $this->clock = $clock;
        $this->logger = $logger;
    }

    /**
     * @param array<string,string> $headers
     */
    public function handle(array $headers, string $rawBody): WebhookResult
    {
        $headers = array_change_key_case($headers, CASE_LOWER);

        if ($this->verifier === null) {
            $this->logger->warning('Webhook received but the secret key is not configured');

            return new WebhookResult(503, 'Secret key is not configured');
        }

        $timestamp = isset($headers['x-timestamp']) ? (string) $headers['x-timestamp'] : '';
        $signature = isset($headers['x-hmac-signature']) ? (string) $headers['x-hmac-signature'] : '';
        if (!$this->verifier->verify($timestamp, $signature, $rawBody, $this->clock->now())) {
            $this->logger->warning('Webhook rejected: invalid signature or timestamp');

            return new WebhookResult(401, 'Invalid signature');
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)
            || empty($payload['verification_id']) || !is_string($payload['verification_id'])
            || empty($payload['status']) || !is_string($payload['status'])) {
            $this->logger->warning('Webhook rejected: malformed payload');

            return new WebhookResult(400, 'Malformed payload');
        }

        $deliveryId = isset($headers['x-proofage-webhook-delivery-id']) ? (string) $headers['x-proofage-webhook-delivery-id'] : '';
        if ($deliveryId !== '' && $this->deliveries->exists($deliveryId)) {
            return new WebhookResult(200, 'Duplicate delivery');
        }

        $verificationId = $payload['verification_id'];
        $status = $payload['status'];
        $record = $this->verifications->findByVerificationId($verificationId);
        if ($record === null) {
            $this->logger->warning('Webhook for unknown verification ' . $verificationId);
        } else {
            $method = isset($payload['method']) && is_string($payload['method']) ? $payload['method'] : null;
            $reason = isset($payload['reason']) && is_string($payload['reason']) ? $payload['reason'] : null;
            $this->service->applyStatus($record, $status, $method, $reason);
        }

        if ($deliveryId !== '') {
            // Recorded after applying: a crash in between leads to a retry, and re-applying is a no-op.
            $this->deliveries->insert($deliveryId, $verificationId, $status, $this->clock->now());
        }

        return new WebhookResult(200, $record === null ? 'Unknown verification' : 'OK');
    }
}

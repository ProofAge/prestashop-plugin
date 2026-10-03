<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Verification;

use ProofAge\PrestaShop\Api\ApiException;
use ProofAge\PrestaShop\Api\VerificationApi;
use ProofAge\PrestaShop\Support\Clock;
use ProofAge\PrestaShop\Support\Logger;

final class VerificationService
{
    public const REUSE_WINDOW = 604800;
    public const SYNC_INTERVAL = 5;
    public const LIMIT_PER_VISITOR = 5;
    public const LIMIT_PER_IP = 20;

    /** @var VerificationApi */
    private $api;
    /** @var VerificationRepository */
    private $verifications;
    /** @var CustomerVerificationRepository */
    private $customers;
    /** @var OrderSnapshotRepository */
    private $orders;
    /** @var Clock */
    private $clock;
    /** @var TtlPolicy */
    private $ttl;
    /** @var Logger */
    private $logger;

    public function __construct(
        VerificationApi $api,
        VerificationRepository $verifications,
        CustomerVerificationRepository $customers,
        OrderSnapshotRepository $orders,
        Clock $clock,
        TtlPolicy $ttl,
        Logger $logger
    ) {
        $this->api = $api;
        $this->verifications = $verifications;
        $this->customers = $customers;
        $this->orders = $orders;
        $this->clock = $clock;
        $this->ttl = $ttl;
        $this->logger = $logger;
    }

    /**
     * @throws RateLimitedException
     * @throws ApiException
     */
    public function start(StartRequest $request): StartResult
    {
        $now = $this->clock->now();

        $existing = $this->reusable($request, $now);
        if ($existing !== null) {
            if ($existing->idCustomer === null && $request->idCustomer) {
                $existing->idCustomer = (int) $request->idCustomer;
            }
            $existing->originUrl = $request->originUrl;
            $existing->updatedAt = $now;
            $this->verifications->update($existing);

            return new StartResult($existing->verificationId, $existing->verificationUrl, null, true);
        }

        $externalId = $request->idCustomer
            ? ExternalId::forCustomer($request->idShop, (int) $request->idCustomer)
            : ExternalId::forGuest($request->idShop, $request->guestKey);

        $since = $now - 3600;
        if ($this->verifications->countCreatedSince('external_id', $externalId, $since) >= self::LIMIT_PER_VISITOR
            || $this->verifications->countCreatedSince('ip_hash', $request->ipHash, $since) >= self::LIMIT_PER_IP) {
            throw new RateLimitedException('Too many verification attempts');
        }

        $token = bin2hex(random_bytes(32));
        $returnRef = bin2hex(random_bytes(16));
        $response = $this->api->createVerification([
            'external_id' => $externalId,
            'callback_url' => call_user_func($request->callbackUrlFactory, $returnRef),
            'external_metadata' => $request->metadata,
            'metadata' => ['sdk_preferences' => ['language' => $request->language]],
        ]);
        if (empty($response['id']) || !is_string($response['id']) || empty($response['url']) || !is_string($response['url'])) {
            throw new ApiException('ProofAge API response is missing the verification id or url', 0, null);
        }

        $record = new VerificationRecord();
        $record->verificationId = $response['id'];
        $record->idShop = $request->idShop;
        $record->externalId = $externalId;
        $record->idCustomer = $request->idCustomer ? (int) $request->idCustomer : null;
        $record->sessionTokenHash = hash('sha256', $token);
        $record->ipHash = $request->ipHash;
        $record->returnRef = $returnRef;
        $record->verificationUrl = $response['url'];
        $record->status = isset($response['status']) && is_string($response['status']) ? $response['status'] : 'created';
        $record->originUrl = $request->originUrl;
        $record->createdAt = $now;
        $record->updatedAt = $now;
        $this->verifications->insert($record);

        return new StartResult($record->verificationId, $record->verificationUrl, $token, false);
    }

    public function applyStatus(VerificationRecord $record, string $status, ?string $method, ?string $reason): bool
    {
        if (!StatusTransition::isAllowed($record->status, $status)) {
            return false;
        }
        $now = $this->clock->now();
        $previous = $record->status;

        $record->status = $status;
        if ($method !== null) {
            $record->method = $method;
        }
        $record->reason = $reason;
        $record->updatedAt = $now;

        if ($status === 'approved') {
            $record->decidedAt = $now;
            $record->expiresAt = $this->ttl->guestExpiry($now);
            if ($record->idCustomer) {
                $this->customers->save(new CustomerVerification(
                    (int) $record->idCustomer,
                    $record->idShop,
                    $record->verificationId,
                    $now,
                    $this->ttl->customerExpiry($now)
                ));
            }
        } elseif (StatusMapper::isFinal($status)) {
            $record->decidedAt = $now;
            $record->expiresAt = null;
        }

        if ($previous === 'approved' && $status === 'declined') {
            $this->customers->deleteByVerificationId($record->verificationId);
            $this->orders->markRevoked($record->verificationId);
            $this->logger->warning('Verification ' . $record->verificationId . ' approval was revoked');
        }

        $this->verifications->update($record);

        return true;
    }

    public function sync(VerificationRecord $record): VerificationRecord
    {
        if (StatusMapper::isFinal($record->status)) {
            return $record;
        }
        $now = $this->clock->now();
        if ($record->lastSyncedAt !== null && $now - $record->lastSyncedAt < self::SYNC_INTERVAL) {
            return $record;
        }
        $record->lastSyncedAt = $now;

        try {
            $remote = $this->api->getVerification($record->verificationId);
        } catch (ApiException $e) {
            $this->logger->warning('Could not sync verification ' . $record->verificationId . ': ' . $e->getMessage());
            $this->verifications->update($record);

            return $record;
        }

        if (isset($remote['external_id']) && $remote['external_id'] !== $record->externalId) {
            $this->logger->warning('Verification ' . $record->verificationId . ' external_id mismatch; ignoring remote status');
            $this->verifications->update($record);

            return $record;
        }

        $status = isset($remote['status']) && is_string($remote['status']) ? $remote['status'] : $record->status;
        $method = isset($remote['method']) && is_string($remote['method']) ? $remote['method'] : null;
        $reason = isset($remote['reason']) && is_string($remote['reason']) ? $remote['reason'] : null;
        if (!$this->applyStatus($record, $status, $method, $reason)) {
            $this->verifications->update($record);
        }

        return $record;
    }

    /**
     * @return VerificationRecord|null
     */
    private function reusable(StartRequest $request, int $now)
    {
        if (!$request->cookieVerificationId) {
            return null;
        }
        $record = $this->verifications->findByVerificationId($request->cookieVerificationId);
        if ($record === null
            || $record->idShop !== $request->idShop
            || !$this->sameOwner($record, $request)
            || !$record->matchesToken($request->cookieToken)
            || !StatusMapper::isReusable($record->status)
            || $record->createdAt <= $now - self::REUSE_WINDOW
            || $record->verificationUrl === '') {
            return null;
        }

        return $record;
    }

    /**
     * A guest record may be claimed by a logged-in customer; a record owned by
     * a customer is reusable only by that same customer.
     */
    private function sameOwner(VerificationRecord $record, StartRequest $request): bool
    {
        return $record->idCustomer === null || (int) $record->idCustomer === (int) $request->idCustomer;
    }
}

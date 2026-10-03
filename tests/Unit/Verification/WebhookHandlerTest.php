<?php

namespace ProofAge\PrestaShop\Tests\Unit\Verification;

use PHPUnit\Framework\TestCase;
use ProofAge\PrestaShop\Api\WebhookSignatureVerifier;
use ProofAge\PrestaShop\Tests\Unit\Fakes\FakeVerificationApi;
use ProofAge\PrestaShop\Tests\Unit\Fakes\FixedClock;
use ProofAge\PrestaShop\Tests\Unit\Fakes\InMemoryCustomerVerificationRepository;
use ProofAge\PrestaShop\Tests\Unit\Fakes\InMemoryOrderSnapshotRepository;
use ProofAge\PrestaShop\Tests\Unit\Fakes\InMemoryVerificationRepository;
use ProofAge\PrestaShop\Tests\Unit\Fakes\InMemoryWebhookDeliveryRepository;
use ProofAge\PrestaShop\Tests\Unit\Fakes\SpyLogger;
use ProofAge\PrestaShop\Verification\TtlPolicy;
use ProofAge\PrestaShop\Verification\VerificationRecord;
use ProofAge\PrestaShop\Verification\VerificationService;
use ProofAge\PrestaShop\Verification\WebhookHandler;

final class WebhookHandlerTest extends TestCase
{
    private const SECRET = 'sk_test_abc';

    private InMemoryVerificationRepository $verifications;
    private InMemoryCustomerVerificationRepository $customers;
    private InMemoryWebhookDeliveryRepository $deliveries;
    private FixedClock $clock;
    private SpyLogger $logger;

    protected function setUp(): void
    {
        $this->verifications = new InMemoryVerificationRepository();
        $this->customers = new InMemoryCustomerVerificationRepository();
        $this->deliveries = new InMemoryWebhookDeliveryRepository();
        $this->clock = new FixedClock();
        $this->logger = new SpyLogger();

        $record = new VerificationRecord();
        $record->verificationId = 'ver-1';
        $record->idShop = 1;
        $record->externalId = 'ps-1-c42';
        $record->idCustomer = 42;
        $record->status = 'review';
        $this->verifications->insert($record);
    }

    private function handler(?string $secret = self::SECRET): WebhookHandler
    {
        $service = new VerificationService(
            new FakeVerificationApi(),
            $this->verifications,
            $this->customers,
            new InMemoryOrderSnapshotRepository(),
            $this->clock,
            new TtlPolicy(24, 365),
            $this->logger
        );

        return new WebhookHandler(
            $secret === null ? null : new WebhookSignatureVerifier($secret),
            $this->deliveries,
            $this->verifications,
            $service,
            $this->clock,
            $this->logger
        );
    }

    /** @return array{array<string,string>,string} */
    private function delivery(array $payload, string $deliveryId = 'd-1', ?int $ts = null, string $secret = self::SECRET): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $ts = (string) ($ts ?? $this->clock->now);

        return [[
            'X-Timestamp' => $ts,
            'X-HMAC-Signature' => hash_hmac('sha256', $ts . '.' . $body, $secret),
            'X-ProofAge-Webhook-Delivery-Id' => $deliveryId,
            'Content-Type' => 'application/json',
        ], $body];
    }

    public function testApprovedDeliveryIsApplied(): void
    {
        [$headers, $body] = $this->delivery(['verification_id' => 'ver-1', 'status' => 'approved', 'method' => 'wallet']);

        $result = $this->handler()->handle($headers, $body);

        self::assertSame(200, $result->status);
        self::assertSame('approved', $this->verifications->findByVerificationId('ver-1')->status);
        self::assertSame('wallet', $this->verifications->findByVerificationId('ver-1')->method);
        self::assertNotNull($this->customers->find(42, 1));
        self::assertTrue($this->deliveries->exists('d-1'));
    }

    public function testHeaderNamesAreCaseInsensitive(): void
    {
        [$headers, $body] = $this->delivery(['verification_id' => 'ver-1', 'status' => 'declined', 'reason' => 'too_young']);
        $lower = array_change_key_case($headers, CASE_LOWER);

        self::assertSame(200, $this->handler()->handle($lower, $body)->status);
        self::assertSame('too_young', $this->verifications->findByVerificationId('ver-1')->reason);
    }

    public function testMissingSecretReturns503(): void
    {
        [$headers, $body] = $this->delivery(['verification_id' => 'ver-1', 'status' => 'approved']);

        self::assertSame(503, $this->handler(null)->handle($headers, $body)->status);
        self::assertSame('review', $this->verifications->findByVerificationId('ver-1')->status);
    }

    public function testBadSignatureReturns401AndLogs(): void
    {
        [$headers, $body] = $this->delivery(['verification_id' => 'ver-1', 'status' => 'approved'], 'd-1', null, 'sk_wrong');

        self::assertSame(401, $this->handler()->handle($headers, $body)->status);
        self::assertSame('review', $this->verifications->findByVerificationId('ver-1')->status);
        self::assertNotEmpty($this->logger->warnings);
    }

    public function testStaleTimestampReturns401(): void
    {
        [$headers, $body] = $this->delivery(['verification_id' => 'ver-1', 'status' => 'approved'], 'd-1', $this->clock->now - 301);

        self::assertSame(401, $this->handler()->handle($headers, $body)->status);
    }

    public function testMalformedPayloadReturns400(): void
    {
        [$headers, $body] = $this->delivery(['status' => 'approved']);

        self::assertSame(400, $this->handler()->handle($headers, $body)->status);
    }

    public function testDuplicateDeliveryIsNotReapplied(): void
    {
        [$headers, $body] = $this->delivery(['verification_id' => 'ver-1', 'status' => 'approved']);
        $handler = $this->handler();
        $handler->handle($headers, $body);
        $updates = $this->verifications->updates;

        $result = $handler->handle($headers, $body);

        self::assertSame(200, $result->status);
        self::assertSame($updates, $this->verifications->updates);
    }

    public function testUnknownVerificationIsAcknowledged(): void
    {
        [$headers, $body] = $this->delivery(['verification_id' => 'ver-other', 'status' => 'approved']);

        self::assertSame(200, $this->handler()->handle($headers, $body)->status);
        self::assertNotEmpty($this->logger->warnings);
        self::assertTrue($this->deliveries->exists('d-1'));
    }

    public function testRevocationWebhookAfterApproval(): void
    {
        $handler = $this->handler();
        [$h1, $b1] = $this->delivery(['verification_id' => 'ver-1', 'status' => 'approved'], 'd-1');
        [$h2, $b2] = $this->delivery(['verification_id' => 'ver-1', 'status' => 'declined', 'reason' => 'revoked'], 'd-2');

        $handler->handle($h1, $b1);
        $handler->handle($h2, $b2);

        self::assertSame('declined', $this->verifications->findByVerificationId('ver-1')->status);
        self::assertNull($this->customers->find(42, 1));
    }

    public function testDeliveryWithoutIdIsStillProcessed(): void
    {
        [$headers, $body] = $this->delivery(['verification_id' => 'ver-1', 'status' => 'approved']);
        unset($headers['X-ProofAge-Webhook-Delivery-Id']);

        self::assertSame(200, $this->handler()->handle($headers, $body)->status);
        self::assertSame('approved', $this->verifications->findByVerificationId('ver-1')->status);
    }
}

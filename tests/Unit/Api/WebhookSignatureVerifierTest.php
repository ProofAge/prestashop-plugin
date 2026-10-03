<?php

namespace ProofAge\PrestaShop\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use ProofAge\PrestaShop\Api\WebhookSignatureVerifier;

final class WebhookSignatureVerifierTest extends TestCase
{
    private const BODY = '{"verification_id":"v1","status":"approved"}';
    private const SIG = '35069e1f1e9826d23b806c09b6044f13d307d07ee9a329bf61305ab126eb6b86';

    public function testAcceptsValidSignatureWithinTolerance(): void
    {
        $v = new WebhookSignatureVerifier('sk_test_abc');
        self::assertTrue($v->verify('1700000000', self::SIG, self::BODY, 1700000100));
    }

    public function testAcceptsUppercaseSignature(): void
    {
        $v = new WebhookSignatureVerifier('sk_test_abc');
        self::assertTrue($v->verify('1700000000', strtoupper(self::SIG), self::BODY, 1700000000));
    }

    public function testRejectsTamperedBody(): void
    {
        $v = new WebhookSignatureVerifier('sk_test_abc');
        self::assertFalse($v->verify('1700000000', self::SIG, str_replace('approved', 'declined', self::BODY), 1700000000));
    }

    public function testRejectsWrongSecret(): void
    {
        $v = new WebhookSignatureVerifier('sk_test_other');
        self::assertFalse($v->verify('1700000000', self::SIG, self::BODY, 1700000000));
    }

    public function testRejectsOldTimestamp(): void
    {
        $v = new WebhookSignatureVerifier('sk_test_abc');
        self::assertFalse($v->verify('1700000000', self::SIG, self::BODY, 1700000301));
    }

    public function testRejectsFutureTimestamp(): void
    {
        $v = new WebhookSignatureVerifier('sk_test_abc');
        self::assertFalse($v->verify('1700000000', self::SIG, self::BODY, 1699999699));
    }

    public function testRejectsNonNumericOrEmptyTimestamp(): void
    {
        $v = new WebhookSignatureVerifier('sk_test_abc');
        self::assertFalse($v->verify('', self::SIG, self::BODY, 1700000000));
        self::assertFalse($v->verify('17e8', self::SIG, self::BODY, 1700000000));
    }

    public function testRejectsEmptySignature(): void
    {
        $v = new WebhookSignatureVerifier('sk_test_abc');
        self::assertFalse($v->verify('1700000000', '', self::BODY, 1700000000));
    }
}

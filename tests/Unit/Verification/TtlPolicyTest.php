<?php

namespace ProofAge\PrestaShop\Tests\Unit\Verification;

use PHPUnit\Framework\TestCase;
use ProofAge\PrestaShop\Verification\TtlPolicy;

final class TtlPolicyTest extends TestCase
{
    public function testGuestExpiryIsClamped(): void
    {
        self::assertSame(1000 + 3600, (new TtlPolicy(0, 1))->guestExpiry(1000));
        self::assertSame(1000 + 720 * 3600, (new TtlPolicy(9999, 1))->guestExpiry(1000));
        self::assertSame(1000 + 24 * 3600, (new TtlPolicy(24, 1))->guestExpiry(1000));
    }

    public function testCustomerExpiryZeroMeansForever(): void
    {
        self::assertNull((new TtlPolicy(24, 0))->customerExpiry(1000));
        self::assertSame(1000 + 365 * 86400, (new TtlPolicy(24, 365))->customerExpiry(1000));
        self::assertNull((new TtlPolicy(24, -5))->customerExpiry(1000));
    }
}

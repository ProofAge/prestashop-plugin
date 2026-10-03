<?php

namespace ProofAge\PrestaShop\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use ProofAge\PrestaShop\Support\Uuid;

final class UuidTest extends TestCase
{
    public function testGeneratesUniqueV4(): void
    {
        $a = Uuid::v4();
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $a);
        self::assertNotSame($a, Uuid::v4());
    }
}

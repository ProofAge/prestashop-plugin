<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use ProofAge\PrestaShop\Support\DateFormat;

final class DateFormatTest extends TestCase
{
    public function testFormatsInUtc(): void
    {
        $this->assertSame('2026-10-02 12:30 UTC', DateFormat::utc(1790944200));
    }

    public function testNullIsEmpty(): void
    {
        $this->assertSame('', DateFormat::utc(null));
    }
}

<?php

namespace ProofAge\PrestaShop\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use ProofAge\PrestaShop\Support\Input;

final class InputTest extends TestCase
{
    public function testLines(): void
    {
        self::assertSame(['manufacturer', '/promo/*'], Input::lines(" manufacturer \r\n\n/promo/*\nmanufacturer\n"));
        self::assertSame([], Input::lines(''));
        self::assertCount(200, Input::lines(implode("\n", range(1, 500))));
    }

    public function testIds(): void
    {
        self::assertSame([3, 5], Input::ids(['3', '5', '0', 'x', '3']));
        self::assertSame([7, 9], Input::ids('[7,"9",-1]'));
        self::assertSame([], Input::ids('not json'));
        self::assertSame([], Input::ids(null));
    }

    public function testClampInt(): void
    {
        self::assertSame(24, Input::clampInt('abc', 1, 720, 24));
        self::assertSame(720, Input::clampInt('9999', 1, 720, 24));
        self::assertSame(1, Input::clampInt('0', 1, 720, 24));
        self::assertSame(48, Input::clampInt('48', 1, 720, 24));
    }
}

<?php

namespace ProofAge\PrestaShop\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use ProofAge\PrestaShop\Rules\UrlPattern;

final class UrlPatternTest extends TestCase
{
    public function testExactMatchIgnoresCaseAndSlashes(): void
    {
        self::assertTrue(UrlPattern::matches('/promo', '/PROMO/'));
        self::assertTrue(UrlPattern::matches('promo/', '/promo'));
        self::assertFalse(UrlPattern::matches('/promo', '/promo/x'));
    }

    public function testWildcard(): void
    {
        self::assertTrue(UrlPattern::matches('/promo/*', '/promo/summer'));
        self::assertTrue(UrlPattern::matches('/vina-*', '/vina-red'));
        self::assertFalse(UrlPattern::matches('/vina-*', '/beer'));
    }

    public function testRegexCharactersAreLiteral(): void
    {
        self::assertTrue(UrlPattern::matches('/a.b', '/a.b'));
        self::assertFalse(UrlPattern::matches('/a.b', '/axb'));
    }

    public function testRootOnlyMatchesRoot(): void
    {
        self::assertTrue(UrlPattern::matches('/', '/'));
        self::assertFalse(UrlPattern::matches('/', '/anything'));
    }

    public function testEmptyPatternNeverMatches(): void
    {
        self::assertFalse(UrlPattern::matches('  ', '/'));
    }
}

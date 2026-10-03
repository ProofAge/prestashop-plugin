<?php

namespace ProofAge\PrestaShop\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use ProofAge\PrestaShop\Support\Urls;

final class UrlsTest extends TestCase
{
    public function testSdkLoaderUrl(): void
    {
        self::assertSame('https://app.proofage.xyz/sdk-build/kyc-loader.js', Urls::sdkLoaderUrl('https://api.proofage.xyz'));
        self::assertSame('https://app.proofage.xyz/sdk-build/kyc-loader.js', Urls::sdkLoaderUrl('https://api.proofage.xyz/'));
        self::assertSame('https://app.staging.proofage.xyz/sdk-build/kyc-loader.js', Urls::sdkLoaderUrl('https://api.staging.proofage.xyz'));
        self::assertSame('https://app.proofage.xyz/sdk-build/kyc-loader.js', Urls::sdkLoaderUrl('http://127.0.0.1:8765'));
    }

    public function testSdkLoaderUrlRejectsMaliciousHosts(): void
    {
        // Reject hosts without proper dot boundary
        self::assertSame('https://app.proofage.xyz/sdk-build/kyc-loader.js', Urls::sdkLoaderUrl('https://api.evilproofage.xyz'));
        // Reject hosts with invalid prefix
        self::assertSame('https://app.proofage.xyz/sdk-build/kyc-loader.js', Urls::sdkLoaderUrl('https://api.x-proofage.xyz'));
    }

    public function testSafeRedirects(): void
    {
        self::assertTrue(Urls::isSafeRedirect('https://Shop.test/3-clothes?x=1', 'shop.test'));
        self::assertTrue(Urls::isSafeRedirect('http://shop.test/', 'shop.test'));
        self::assertTrue(Urls::isSafeRedirect('/cart?action=show', 'shop.test'));
    }

    public function testUnsafeRedirects(): void
    {
        self::assertFalse(Urls::isSafeRedirect('', 'shop.test'));
        self::assertFalse(Urls::isSafeRedirect('https://evil.test/', 'shop.test'));
        self::assertFalse(Urls::isSafeRedirect('https://shop.test.evil.test/', 'shop.test'));
        self::assertFalse(Urls::isSafeRedirect('//evil.test/x', 'shop.test'));
        self::assertFalse(Urls::isSafeRedirect('javascript:alert(1)', 'shop.test'));
        self::assertFalse(Urls::isSafeRedirect('ftp://shop.test/x', 'shop.test'));
        self::assertFalse(Urls::isSafeRedirect('/\\evil.test', 'shop.test'));
    }

    public function testUnsafeRedirectsWithControlCharacters(): void
    {
        // Control characters bypass the // guard since browsers strip them
        self::assertFalse(Urls::isSafeRedirect("/\t/evil.test", 'shop.test'));
        self::assertFalse(Urls::isSafeRedirect("/\n/evil.test", 'shop.test'));
        self::assertFalse(Urls::isSafeRedirect("/\r/evil.test", 'shop.test'));
        // Leading space is also a control character
        self::assertFalse(Urls::isSafeRedirect(' https://shop.test/', 'shop.test'));
    }
}

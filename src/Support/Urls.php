<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Support;

final class Urls
{
    public const DEFAULT_SDK_LOADER = 'https://app.proofage.xyz/sdk-build/kyc-loader.js';

    public static function sdkLoaderUrl(string $apiUrl): string
    {
        $scheme = (string) parse_url($apiUrl, PHP_URL_SCHEME);
        if ($scheme !== 'https') {
            return self::DEFAULT_SDK_LOADER;
        }

        $host = (string) parse_url($apiUrl, PHP_URL_HOST);
        if (!preg_match('/^api\.((?:[a-z0-9-]+\.)*proofage\.xyz)$/i', $host, $m)) {
            return self::DEFAULT_SDK_LOADER;
        }

        return 'https://app.' . strtolower($m[1]) . '/sdk-build/kyc-loader.js';
    }

    public static function isSafeRedirect(string $url, string $shopHost): bool
    {
        if ($url === '' || strpos($url, '\\') !== false || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return false;
        }
        if ($url[0] === '/') {
            return !isset($url[1]) || $url[1] !== '/';
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return ($scheme === 'http' || $scheme === 'https') && $host !== '' && $host === strtolower($shopHost);
    }
}

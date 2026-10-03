<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Rules;

/**
 * Case-insensitive path match where "*" matches any characters; slashes at both ends are ignored.
 */
final class UrlPattern
{
    public static function matches(string $pattern, string $path): bool
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return false;
        }
        $pattern = self::normalise($pattern);
        $path = self::normalise($path);
        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';

        return preg_match($regex, $path) === 1;
    }

    private static function normalise(string $value): string
    {
        $value = '/' . trim(strtolower($value), '/');

        return $value === '/' ? '/' : $value;
    }
}

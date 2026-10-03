<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Support;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class DateFormat
{
    /**
     * Formats a Unix timestamp for the back office, always in UTC; returns '' for null.
     */
    public static function utc(?int $timestamp): string
    {
        return $timestamp === null ? '' : gmdate('Y-m-d H:i', $timestamp) . ' UTC';
    }
}

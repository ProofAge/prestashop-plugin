<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Infrastructure;

if (!defined('_PS_VERSION_')) {
    exit;
}

use ProofAge\PrestaShop\Api\ClockSkewStore;

/**
 * Persists the learned clock offset in a global PrestaShop Configuration value, so the PHP requests
 * that follow the correcting one do not each pay the extra round trip.
 */
final class ConfigurationClockSkewStore implements ClockSkewStore
{
    public const KEY = 'PROOFAGE_CLOCK_SKEW';

    public function offset(): int
    {
        $value = \Configuration::getGlobalValue(self::KEY);

        return $value === false ? 0 : (int) $value;
    }

    public function save(int $seconds): void
    {
        if ($seconds !== $this->offset()) {
            \Configuration::updateGlobalValue(self::KEY, (string) $seconds);
        }
    }
}

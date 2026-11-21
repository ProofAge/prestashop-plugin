<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Api;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Keeps the offset (server time minus local time, in seconds) learned from a 401 TIMESTAMP_OUT_OF_RANGE answer.
 */
interface ClockSkewStore
{
    public function offset(): int;

    public function save(int $seconds): void;
}

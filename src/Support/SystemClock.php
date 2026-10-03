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

final class SystemClock implements Clock
{
    public function now(): int
    {
        return time();
    }
}

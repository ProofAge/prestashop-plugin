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

final class MemoryClockSkewStore implements ClockSkewStore
{
    /** @var int */
    private $offset = 0;

    public function offset(): int
    {
        return $this->offset;
    }

    public function save(int $seconds): void
    {
        $this->offset = $seconds;
    }
}

<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Verification;

final class TtlPolicy
{
    /** @var int */
    private $guestTtlHours;

    /** @var int */
    private $customerTtlDays;

    public function __construct(int $guestTtlHours, int $customerTtlDays)
    {
        $this->guestTtlHours = max(1, min(720, $guestTtlHours));
        $this->customerTtlDays = max(0, $customerTtlDays);
    }

    public function guestExpiry(int $from): int
    {
        return $from + $this->guestTtlHours * 3600;
    }

    /**
     * @return int|null null = never expires
     */
    public function customerExpiry(int $from)
    {
        return $this->customerTtlDays === 0 ? null : $from + $this->customerTtlDays * 86400;
    }
}

<?php

namespace ProofAge\PrestaShop\Tests\Unit\Fakes;

use ProofAge\PrestaShop\Support\Clock;

final class FixedClock implements Clock
{
    public function __construct(public int $now = 1_800_000_000)
    {
    }

    public function now(): int
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now += $seconds;
    }
}

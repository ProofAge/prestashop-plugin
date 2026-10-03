<?php

namespace ProofAge\PrestaShop\Tests\Unit\Fakes;

use ProofAge\PrestaShop\Support\Logger;

final class SpyLogger implements Logger
{
    /** @var list<string> */
    public array $warnings = [];
    /** @var list<string> */
    public array $infos = [];

    public function warning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function info(string $message): void
    {
        $this->infos[] = $message;
    }
}

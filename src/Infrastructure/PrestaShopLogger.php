<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Infrastructure;

use ProofAge\PrestaShop\Support\Logger;

final class PrestaShopLogger implements Logger
{
    public function warning(string $message): void
    {
        \PrestaShopLogger::addLog('[ProofAge] ' . $message, 2, null, 'Proofage', 0, true);
    }

    public function info(string $message): void
    {
        \PrestaShopLogger::addLog('[ProofAge] ' . $message, 1, null, 'Proofage', 0, true);
    }
}

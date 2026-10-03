<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Verification;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ExternalId
{
    public static function forCustomer(int $idShop, int $idCustomer): string
    {
        return 'ps-' . $idShop . '-c' . $idCustomer;
    }

    public static function forGuest(int $idShop, string $guestKey): string
    {
        return 'ps-' . $idShop . '-g' . $guestKey;
    }
}

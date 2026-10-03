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

final class StartRequest
{
    /** @var int */
    public $idShop = 0;
    /** @var int|null */
    public $idCustomer;
    /** @var string uuid kept in the guest's cookie */
    public $guestKey = '';
    /** @var string|null */
    public $cookieVerificationId;
    /** @var string|null */
    public $cookieToken;
    /** @var string */
    public $ipHash = '';
    /** @var string */
    public $originUrl = '';
    /** @var string ISO 639-1 */
    public $language = 'en';
    /** @var array<string,mixed> sent as external_metadata */
    public $metadata = [];
    /** @var callable(string): string builds callback_url from the return ref */
    public $callbackUrlFactory;
}

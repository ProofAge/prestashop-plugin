<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Verification;

final class Approval
{
    /** @var string */
    public $verificationId;
    /** @var string|null */
    public $method;
    /** @var int */
    public $verifiedAt;

    public function __construct(string $verificationId, ?string $method, int $verifiedAt)
    {
        $this->verificationId = $verificationId;
        $this->method = $method;
        $this->verifiedAt = $verifiedAt;
    }
}

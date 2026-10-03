<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Verification;

final class OrderSnapshot
{
    public const STATUS_APPROVED = 'approved';
    public const STATUS_NOT_REQUIRED = 'not_required';
    public const STATUS_NOT_VERIFIED = 'not_verified';
    public const STATUS_REVOKED = 'revoked';

    /** @var int */
    public $idOrder;
    /** @var bool */
    public $required;
    /** @var string|null */
    public $verificationId;
    /** @var string */
    public $status;
    /** @var string|null */
    public $method;
    /** @var int|null */
    public $verifiedAt;

    public function __construct(int $idOrder, bool $required, ?string $verificationId, string $status, ?string $method, ?int $verifiedAt)
    {
        $this->idOrder = $idOrder;
        $this->required = $required;
        $this->verificationId = $verificationId;
        $this->status = $status;
        $this->method = $method;
        $this->verifiedAt = $verifiedAt;
    }
}

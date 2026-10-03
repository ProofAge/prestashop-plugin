<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Verification;

final class CustomerVerification
{
    /** @var int */
    public $idCustomer;
    /** @var int */
    public $idShop;
    /** @var string */
    public $verificationId;
    /** @var int */
    public $verifiedAt;
    /** @var int|null null = never expires */
    public $expiresAt;

    public function __construct(int $idCustomer, int $idShop, string $verificationId, int $verifiedAt, ?int $expiresAt)
    {
        $this->idCustomer = $idCustomer;
        $this->idShop = $idShop;
        $this->verificationId = $verificationId;
        $this->verifiedAt = $verifiedAt;
        $this->expiresAt = $expiresAt;
    }

    public function isValidAt(int $now): bool
    {
        return $this->expiresAt === null || $this->expiresAt > $now;
    }
}

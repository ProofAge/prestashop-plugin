<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Verification;

final class VerificationRecord
{
    /** @var int */
    public $id = 0;
    /** @var string */
    public $verificationId = '';
    /** @var int */
    public $idShop = 0;
    /** @var string */
    public $externalId = '';
    /** @var int|null */
    public $idCustomer;
    /** @var int|null */
    public $idCart;
    /** @var string sha256 of the browser session token */
    public $sessionTokenHash = '';
    /** @var string */
    public $ipHash = '';
    /** @var string */
    public $returnRef = '';
    /** @var string */
    public $verificationUrl = '';
    /** @var string raw ProofAge status */
    public $status = 'created';
    /** @var string|null "wallet" or null */
    public $method;
    /** @var string|null */
    public $reason;
    /** @var string */
    public $originUrl = '';
    /** @var int|null */
    public $lastSyncedAt;
    /** @var int|null */
    public $decidedAt;
    /** @var int|null guest approval expiry */
    public $expiresAt;
    /** @var int */
    public $createdAt = 0;
    /** @var int */
    public $updatedAt = 0;

    public function isApprovedAt(int $now): bool
    {
        return $this->status === 'approved' && $this->expiresAt !== null && $this->expiresAt > $now;
    }

    public function matchesToken(?string $rawToken): bool
    {
        if ($rawToken === null || $rawToken === '' || $this->sessionTokenHash === '') {
            return false;
        }

        return hash_equals($this->sessionTokenHash, hash('sha256', $rawToken));
    }
}

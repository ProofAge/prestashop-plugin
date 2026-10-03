<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Verification;

final class StartResult
{
    /** @var string */
    public $verificationId;
    /** @var string */
    public $url;
    /** @var string|null raw token to store in the cookie; null when an existing verification was reused */
    public $newToken;
    /** @var bool */
    public $reused;

    public function __construct(string $verificationId, string $url, ?string $newToken, bool $reused)
    {
        $this->verificationId = $verificationId;
        $this->url = $url;
        $this->newToken = $newToken;
        $this->reused = $reused;
    }
}

<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Infrastructure;

use ProofAge\PrestaShop\Support\Uuid;

/**
 * Module state kept in PrestaShop's encrypted cookie.
 */
final class CookieState
{
    public const VERIFICATION_ID = 'proofage_vid';
    public const TOKEN = 'proofage_tok';
    public const GUEST_KEY = 'proofage_guest';

    /** @var \Cookie|null */
    private $cookie;

    public function __construct(?\Cookie $cookie)
    {
        $this->cookie = $cookie;
    }

    /**
     * @return string|null
     */
    public function verificationId()
    {
        return $this->read(self::VERIFICATION_ID);
    }

    /**
     * @return string|null
     */
    public function token()
    {
        return $this->read(self::TOKEN);
    }

    public function guestKey(): string
    {
        $key = $this->read(self::GUEST_KEY);
        if ($key === null) {
            $key = Uuid::v4();
            if ($this->cookie !== null) {
                $this->cookie->__set(self::GUEST_KEY, $key);
                $this->cookie->write();
            }
        }

        return $key;
    }

    public function remember(string $verificationId, string $token): void
    {
        if ($this->cookie === null) {
            return;
        }
        $this->cookie->__set(self::VERIFICATION_ID, $verificationId);
        $this->cookie->__set(self::TOKEN, $token);
        $this->cookie->write();
    }

    /**
     * @return string|null
     */
    private function read(string $name)
    {
        if ($this->cookie === null || !isset($this->cookie->{$name})) {
            return null;
        }
        $value = (string) $this->cookie->{$name};

        return $value === '' ? null : $value;
    }
}

<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Infrastructure;

if (!defined('_PS_VERSION_')) {
    exit;
}

use ProofAge\PrestaShop\Rules\RuleSet;
use ProofAge\PrestaShop\Support\GateTexts;
use ProofAge\PrestaShop\Support\Input;
use ProofAge\PrestaShop\Verification\TtlPolicy;

final class Settings
{
    public const PUBLIC_KEY = 'PROOFAGE_PUBLIC_KEY';
    public const SECRET_KEY = 'PROOFAGE_SECRET_KEY';
    public const API_URL = 'PROOFAGE_API_URL';
    public const SITE_WIDE = 'PROOFAGE_SITE_WIDE';
    public const PROTECT_PRODUCTS = 'PROOFAGE_PROTECT_PRODUCTS';
    public const EXCLUDE_PRODUCTS = 'PROOFAGE_EXCLUDE_PRODUCTS';
    public const PROTECT_CATEGORIES = 'PROOFAGE_PROTECT_CATEGORIES';
    public const EXCLUDE_CATEGORIES = 'PROOFAGE_EXCLUDE_CATEGORIES';
    public const INCLUDE_CHILDREN = 'PROOFAGE_INCLUDE_CHILDREN';
    public const PROTECT_CMS = 'PROOFAGE_PROTECT_CMS';
    public const EXCLUDE_CMS = 'PROOFAGE_EXCLUDE_CMS';
    public const PROTECT_CONTROLLERS = 'PROOFAGE_PROTECT_CONTROLLERS';
    public const EXCLUDE_CONTROLLERS = 'PROOFAGE_EXCLUDE_CONTROLLERS';
    public const PROTECT_URLS = 'PROOFAGE_PROTECT_URLS';
    public const EXCLUDE_URLS = 'PROOFAGE_EXCLUDE_URLS';
    public const DISPLAY_MODE = 'PROOFAGE_DISPLAY_MODE';
    public const LAUNCH_MODE = 'PROOFAGE_LAUNCH_MODE';
    public const GATE_TITLE = 'PROOFAGE_GATE_TITLE';
    public const GATE_DESCRIPTION = 'PROOFAGE_GATE_DESCRIPTION';
    public const GATE_BUTTON = 'PROOFAGE_GATE_BUTTON';
    public const GUEST_TTL_HOURS = 'PROOFAGE_GUEST_TTL_HOURS';
    public const CUSTOMER_TTL_DAYS = 'PROOFAGE_CUSTOMER_TTL_DAYS';

    public const DEFAULT_API_URL = 'https://api.proofage.net';

    public const LIST_KEYS = [
        self::PROTECT_PRODUCTS, self::EXCLUDE_PRODUCTS, self::PROTECT_CATEGORIES, self::EXCLUDE_CATEGORIES,
        self::PROTECT_CMS, self::EXCLUDE_CMS, self::PROTECT_CONTROLLERS, self::EXCLUDE_CONTROLLERS,
        self::PROTECT_URLS, self::EXCLUDE_URLS,
    ];
    public const LANG_KEYS = [self::GATE_TITLE, self::GATE_DESCRIPTION, self::GATE_BUTTON];
    public const ALL_KEYS = [
        self::PUBLIC_KEY, self::SECRET_KEY, self::API_URL, self::SITE_WIDE, self::INCLUDE_CHILDREN,
        self::DISPLAY_MODE, self::LAUNCH_MODE, self::GUEST_TTL_HOURS, self::CUSTOMER_TTL_DAYS,
        self::PROTECT_PRODUCTS, self::EXCLUDE_PRODUCTS, self::PROTECT_CATEGORIES, self::EXCLUDE_CATEGORIES,
        self::PROTECT_CMS, self::EXCLUDE_CMS, self::PROTECT_CONTROLLERS, self::EXCLUDE_CONTROLLERS,
        self::PROTECT_URLS, self::EXCLUDE_URLS, self::GATE_TITLE, self::GATE_DESCRIPTION, self::GATE_BUTTON,
    ];

    /** @var int */
    private $idShop;

    /** @var array<string,string> */
    private $cache = [];

    public function __construct(int $idShop)
    {
        $this->idShop = $idShop;
    }

    /**
     * @return array<string,mixed> non-multilang defaults
     */
    public static function defaults(): array
    {
        return [
            self::PUBLIC_KEY => '',
            self::SECRET_KEY => '',
            self::API_URL => self::DEFAULT_API_URL,
            self::SITE_WIDE => 0,
            self::INCLUDE_CHILDREN => 1,
            self::DISPLAY_MODE => 'gate',
            self::LAUNCH_MODE => 'modal',
            self::GUEST_TTL_HOURS => 24,
            self::CUSTOMER_TTL_DAYS => 365,
            self::PROTECT_PRODUCTS => '[]',
            self::EXCLUDE_PRODUCTS => '[]',
            self::PROTECT_CATEGORIES => '[]',
            self::EXCLUDE_CATEGORIES => '[]',
            self::PROTECT_CMS => '[]',
            self::EXCLUDE_CMS => '[]',
            self::PROTECT_CONTROLLERS => '[]',
            self::EXCLUDE_CONTROLLERS => '[]',
            self::PROTECT_URLS => '[]',
            self::EXCLUDE_URLS => '[]',
        ];
    }

    public function get(string $key): string
    {
        if (!array_key_exists($key, $this->cache)) {
            $value = \Configuration::get($key, null, null, $this->idShop);
            $this->cache[$key] = $value === false ? '' : (string) $value;
        }

        return $this->cache[$key];
    }

    /**
     * @return array<int,int|string>
     */
    public function getList(string $key): array
    {
        $decoded = json_decode($this->get($key), true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    public function publicKey(): string
    {
        return trim($this->get(self::PUBLIC_KEY));
    }

    public function secretKey(): string
    {
        return trim($this->get(self::SECRET_KEY));
    }

    public function apiUrl(): string
    {
        $url = trim($this->get(self::API_URL));

        return $url === '' ? self::DEFAULT_API_URL : $url;
    }

    public function isConfigured(): bool
    {
        return $this->publicKey() !== '' && $this->secretKey() !== '';
    }

    public function ruleSet(): RuleSet
    {
        return RuleSet::fromArray([
            'site_wide' => $this->get(self::SITE_WIDE) === '1',
            'protect_products' => $this->getList(self::PROTECT_PRODUCTS),
            'exclude_products' => $this->getList(self::EXCLUDE_PRODUCTS),
            'protect_categories' => $this->getList(self::PROTECT_CATEGORIES),
            'exclude_categories' => $this->getList(self::EXCLUDE_CATEGORIES),
            'include_children' => $this->get(self::INCLUDE_CHILDREN) !== '0',
            'protect_cms' => $this->getList(self::PROTECT_CMS),
            'exclude_cms' => $this->getList(self::EXCLUDE_CMS),
            'protect_controllers' => $this->getList(self::PROTECT_CONTROLLERS),
            'exclude_controllers' => $this->getList(self::EXCLUDE_CONTROLLERS),
            'protect_urls' => $this->getList(self::PROTECT_URLS),
            'exclude_urls' => $this->getList(self::EXCLUDE_URLS),
        ]);
    }

    public function displayMode(): string
    {
        return $this->get(self::DISPLAY_MODE) === 'overlay' ? 'overlay' : 'gate';
    }

    public function launchMode(): string
    {
        return $this->get(self::LAUNCH_MODE) === 'redirect' ? 'redirect' : 'modal';
    }

    public function ttlPolicy(): TtlPolicy
    {
        return new TtlPolicy(
            Input::clampInt($this->get(self::GUEST_TTL_HOURS), 1, 720, 24),
            Input::clampInt($this->get(self::CUSTOMER_TTL_DAYS), 0, 36500, 365)
        );
    }

    /**
     * @return array{title:string,description:string,button:string}
     */
    public function gateTexts(int $idLang): array
    {
        $language = new \Language($idLang);
        $defaults = GateTexts::defaultsFor((string) $language->iso_code);
        $title = (string) \Configuration::get(self::GATE_TITLE, $idLang, null, $this->idShop);
        $description = (string) \Configuration::get(self::GATE_DESCRIPTION, $idLang, null, $this->idShop);
        $button = (string) \Configuration::get(self::GATE_BUTTON, $idLang, null, $this->idShop);

        return [
            'title' => $title !== '' ? $title : $defaults['title'],
            'description' => $description !== '' ? $description : $defaults['description'],
            'button' => $button !== '' ? $button : $defaults['button'],
        ];
    }

    /**
     * @param mixed $value list arrays are JSON-encoded; multilang values are [id_lang => string]
     */
    public static function update(string $key, $value, int $idShop): void
    {
        if (in_array($key, self::LIST_KEYS, true) && is_array($value)) {
            $value = json_encode(array_values($value));
        }
        $isHtml = $key === self::GATE_DESCRIPTION;
        if (\Shop::isFeatureActive()) {
            \Configuration::updateValue($key, $value, $isHtml, null, $idShop);
        } else {
            \Configuration::updateGlobalValue($key, $value, $isHtml);
        }
    }
}

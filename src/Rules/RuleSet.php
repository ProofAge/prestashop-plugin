<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Rules;

final class RuleSet
{
    /** @var bool */
    public $siteWide = false;
    /** @var int[] */
    public $protectProducts = [];
    /** @var int[] */
    public $excludeProducts = [];
    /** @var int[] */
    public $protectCategories = [];
    /** @var int[] */
    public $excludeCategories = [];
    /** @var bool */
    public $includeChildren = true;
    /** @var int[] */
    public $protectCms = [];
    /** @var int[] */
    public $excludeCms = [];
    /** @var string[] */
    public $protectControllers = [];
    /** @var string[] */
    public $excludeControllers = [];
    /** @var string[] */
    public $protectUrls = [];
    /** @var string[] */
    public $excludeUrls = [];

    /**
     * @param array<string,mixed> $config
     */
    public static function fromArray(array $config): RuleSet
    {
        $rules = new self();
        $rules->siteWide = !empty($config['site_wide']);
        $rules->protectProducts = self::ids(isset($config['protect_products']) ? $config['protect_products'] : []);
        $rules->excludeProducts = self::ids(isset($config['exclude_products']) ? $config['exclude_products'] : []);
        $rules->protectCategories = self::ids(isset($config['protect_categories']) ? $config['protect_categories'] : []);
        $rules->excludeCategories = self::ids(isset($config['exclude_categories']) ? $config['exclude_categories'] : []);
        $rules->includeChildren = !array_key_exists('include_children', $config) || (bool) $config['include_children'];
        $rules->protectCms = self::ids(isset($config['protect_cms']) ? $config['protect_cms'] : []);
        $rules->excludeCms = self::ids(isset($config['exclude_cms']) ? $config['exclude_cms'] : []);
        $rules->protectControllers = self::strings(isset($config['protect_controllers']) ? $config['protect_controllers'] : []);
        $rules->excludeControllers = self::strings(isset($config['exclude_controllers']) ? $config['exclude_controllers'] : []);
        $rules->protectUrls = self::strings(isset($config['protect_urls']) ? $config['protect_urls'] : []);
        $rules->excludeUrls = self::strings(isset($config['exclude_urls']) ? $config['exclude_urls'] : []);

        return $rules;
    }

    public function isEmpty(): bool
    {
        return !$this->siteWide
            && $this->protectProducts === []
            && $this->protectCategories === []
            && $this->protectCms === []
            && $this->protectControllers === []
            && $this->protectUrls === [];
    }

    /**
     * @param mixed $value
     *
     * @return int[]
     */
    private static function ids($value)
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (is_int($item) || (is_string($item) && ctype_digit($item))) {
                $id = (int) $item;
                if ($id > 0 && !in_array($id, $out, true)) {
                    $out[] = $id;
                }
            }
        }

        return $out;
    }

    /**
     * @param mixed $value
     *
     * @return string[]
     */
    private static function strings($value)
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            $item = strtolower(trim((string) $item));
            if ($item !== '' && !in_array($item, $out, true)) {
                $out[] = $item;
            }
        }

        return $out;
    }
}

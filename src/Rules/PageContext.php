<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Rules;

final class PageContext
{
    public const TYPE_PRODUCT = 'product';
    public const TYPE_CATEGORY = 'category';
    public const TYPE_CMS = 'cms';
    public const TYPE_CART = 'cart';
    public const TYPE_CHECKOUT = 'checkout';
    public const TYPE_SYSTEM = 'system';
    public const TYPE_OTHER = 'other';

    /** @var string */
    public $type;

    /** @var int */
    public $id;

    /** @var string controller name, e.g. "product" or "module-foo-bar" */
    public $controller;

    /** @var string request path without base URI, language prefix and query */
    public $path;

    /** @var int[] product ids in the cart (cart and checkout pages only) */
    public $cartProductIds;

    /**
     * @param int[] $cartProductIds
     */
    public function __construct(string $type, int $id, string $controller, string $path, array $cartProductIds = [])
    {
        $this->type = $type;
        $this->id = $id;
        $this->controller = $controller;
        $this->path = $path;
        $this->cartProductIds = $cartProductIds;
    }
}

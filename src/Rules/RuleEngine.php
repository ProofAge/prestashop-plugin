<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Rules;

/**
 * Decides whether a product, cart or page needs a verified visitor.
 * The most specific rule wins; at the same level protection beats exclusion.
 */
final class RuleEngine
{
    /** @var RuleSet */
    private $rules;

    /** @var CategoryLookup */
    private $categories;

    /** @var array<int,bool|null> */
    private $productDecisions = [];

    public function __construct(RuleSet $rules, CategoryLookup $categories)
    {
        $this->rules = $rules;
        $this->categories = $categories;
    }

    public function productRequires(int $idProduct): bool
    {
        $decision = $this->productDecision($idProduct);

        return $decision === null ? $this->rules->siteWide : $decision;
    }

    /**
     * @param int[] $productIds
     */
    public function cartRequires(array $productIds): bool
    {
        foreach ($productIds as $idProduct) {
            if ($this->productRequires((int) $idProduct)) {
                return true;
            }
        }

        return false;
    }

    public function pageRequires(PageContext $page): bool
    {
        if ($page->type === PageContext::TYPE_SYSTEM) {
            return false;
        }

        $decision = null;
        if ($page->type === PageContext::TYPE_PRODUCT && $page->id > 0) {
            $decision = $this->productDecision($page->id);
        } elseif ($page->type === PageContext::TYPE_CATEGORY && $page->id > 0) {
            $decision = $this->categoryDecision($this->withAncestors([$page->id]));
        } elseif ($page->type === PageContext::TYPE_CMS && $page->id > 0) {
            $decision = $this->listDecision($page->id, $this->rules->protectCms, $this->rules->excludeCms);
        }
        if ($decision !== null) {
            return $decision;
        }

        $decision = $this->pathDecision($page);
        if ($decision !== null) {
            return $decision;
        }

        if ($page->type === PageContext::TYPE_CART || $page->type === PageContext::TYPE_CHECKOUT) {
            return $this->cartRequires($page->cartProductIds);
        }

        return $this->rules->siteWide;
    }

    /**
     * @return bool|null null when no product or category rule applies
     */
    private function productDecision(int $idProduct)
    {
        if (array_key_exists($idProduct, $this->productDecisions)) {
            return $this->productDecisions[$idProduct];
        }
        $decision = $this->listDecision($idProduct, $this->rules->protectProducts, $this->rules->excludeProducts);
        if ($decision === null && ($this->rules->protectCategories !== [] || $this->rules->excludeCategories !== [])) {
            $decision = $this->categoryDecision($this->withAncestors($this->categories->categoriesForProduct($idProduct)));
        }
        $this->productDecisions[$idProduct] = $decision;

        return $decision;
    }

    /**
     * @param int[] $categoryIds
     *
     * @return int[]
     */
    private function withAncestors(array $categoryIds)
    {
        $all = array_map('intval', $categoryIds);
        if ($this->rules->includeChildren) {
            foreach ($categoryIds as $idCategory) {
                foreach ($this->categories->ancestorsOf((int) $idCategory) as $ancestor) {
                    $all[] = (int) $ancestor;
                }
            }
        }

        return array_values(array_unique($all));
    }

    /**
     * @param int[] $categoryIds
     *
     * @return bool|null
     */
    private function categoryDecision(array $categoryIds)
    {
        if (array_intersect($categoryIds, $this->rules->protectCategories) !== []) {
            return true;
        }
        if (array_intersect($categoryIds, $this->rules->excludeCategories) !== []) {
            return false;
        }

        return null;
    }

    /**
     * @param int[] $protect
     * @param int[] $exclude
     *
     * @return bool|null
     */
    private function listDecision(int $id, array $protect, array $exclude)
    {
        if (in_array($id, $protect, true)) {
            return true;
        }
        if (in_array($id, $exclude, true)) {
            return false;
        }

        return null;
    }

    /**
     * @return bool|null
     */
    private function pathDecision(PageContext $page)
    {
        $controller = strtolower($page->controller);
        if (in_array($controller, $this->rules->protectControllers, true) || $this->anyUrl($this->rules->protectUrls, $page->path)) {
            return true;
        }
        if (in_array($controller, $this->rules->excludeControllers, true) || $this->anyUrl($this->rules->excludeUrls, $page->path)) {
            return false;
        }

        return null;
    }

    /**
     * @param string[] $patterns
     */
    private function anyUrl(array $patterns, string $path): bool
    {
        foreach ($patterns as $pattern) {
            if (UrlPattern::matches($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}

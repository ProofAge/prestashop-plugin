<?php

namespace ProofAge\PrestaShop\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use ProofAge\PrestaShop\Rules\PageContext;
use ProofAge\PrestaShop\Rules\RuleEngine;
use ProofAge\PrestaShop\Rules\RuleSet;
use ProofAge\PrestaShop\Tests\Unit\Fakes\FakeCategoryLookup;

final class RuleEngineTest extends TestCase
{
    private function engine(array $config, ?FakeCategoryLookup $lookup = null): RuleEngine
    {
        $lookup = $lookup ?? $this->lookup();

        return new RuleEngine(RuleSet::fromArray($config), $lookup);
    }

    private function lookup(): FakeCategoryLookup
    {
        return new FakeCategoryLookup(
            array(1 => array(2, 3, 4), 2 => array(2, 3, 5), 9 => array(2, 6, 8), 20 => array(2, 6, 8, 4)),
            array(3 => 2, 4 => 3, 5 => 3, 6 => 2, 8 => 6)
        );
    }

    private function page(string $type, int $id = 0, string $controller = '', string $path = '/', array $cart = []): PageContext
    {
        return new PageContext($type, $id, $controller, $path, $cart);
    }

    public function testNothingConfiguredRequiresNothing(): void
    {
        $engine = $this->engine(array());
        self::assertFalse($engine->productRequires(1));
        self::assertFalse($engine->pageRequires($this->page(PageContext::TYPE_OTHER, 0, 'index')));
    }

    public function testProtectedProduct(): void
    {
        $engine = $this->engine(array('protect_products' => array(1)));
        self::assertTrue($engine->productRequires(1));
        self::assertFalse($engine->productRequires(2));
        self::assertTrue($engine->pageRequires($this->page(PageContext::TYPE_PRODUCT, 1, 'product')));
    }

    public function testProductExclusionBeatsCategoryProtection(): void
    {
        $engine = $this->engine(array('protect_categories' => array(3), 'exclude_products' => array(1)));
        self::assertFalse($engine->productRequires(1));
        self::assertTrue($engine->productRequires(2));
    }

    public function testProductProtectionBeatsCategoryExclusion(): void
    {
        $engine = $this->engine(array('exclude_categories' => array(3), 'protect_products' => array(1)));
        self::assertTrue($engine->productRequires(1));
    }

    public function testProtectAndExcludeSameProductProtects(): void
    {
        $engine = $this->engine(array('protect_products' => array(1), 'exclude_products' => array(1)));
        self::assertTrue($engine->productRequires(1));
    }

    public function testCategoryProtectionCoversAnyAssociationNotOnlyDefault(): void
    {
        // Product 20's default could be 8, but it is also associated with 4 (Men).
        $engine = $this->engine(array('protect_categories' => array(4), 'include_children' => false));
        self::assertTrue($engine->productRequires(20));
        self::assertFalse($engine->productRequires(9));
    }

    public function testParentCategoryProtectionCoversChildrenByDefault(): void
    {
        $engine = $this->engine(array('protect_categories' => array(6)));
        self::assertTrue($engine->productRequires(9));
        self::assertTrue($engine->pageRequires($this->page(PageContext::TYPE_CATEGORY, 8, 'category')));
        self::assertFalse($engine->pageRequires($this->page(PageContext::TYPE_CATEGORY, 3, 'category')));
    }

    public function testParentCategoryProtectionCanSkipChildren(): void
    {
        $engine = $this->engine(array('protect_categories' => array(6), 'include_children' => false));
        self::assertTrue($engine->productRequires(9)); // directly associated with 6
        self::assertFalse($engine->pageRequires($this->page(PageContext::TYPE_CATEGORY, 8, 'category')));
        self::assertTrue($engine->pageRequires($this->page(PageContext::TYPE_CATEGORY, 6, 'category')));
    }

    public function testCategoryConflictProtects(): void
    {
        // Product 20 is in protected 4 and excluded 8.
        $engine = $this->engine(array('protect_categories' => array(4), 'exclude_categories' => array(8)));
        self::assertTrue($engine->productRequires(20));
    }

    public function testSiteWideWithCategoryExclusion(): void
    {
        $engine = $this->engine(array('site_wide' => true, 'exclude_categories' => array(6)));
        self::assertTrue($engine->productRequires(1));
        self::assertFalse($engine->productRequires(9));
        self::assertTrue($engine->pageRequires($this->page(PageContext::TYPE_OTHER, 0, 'index')));
        self::assertFalse($engine->pageRequires($this->page(PageContext::TYPE_CATEGORY, 8, 'category')));
    }

    public function testCmsRules(): void
    {
        $engine = $this->engine(array('protect_cms' => array(4)));
        self::assertTrue($engine->pageRequires($this->page(PageContext::TYPE_CMS, 4, 'cms')));
        self::assertFalse($engine->pageRequires($this->page(PageContext::TYPE_CMS, 1, 'cms')));

        $siteWide = $this->engine(array('site_wide' => true, 'exclude_cms' => array(1)));
        self::assertFalse($siteWide->pageRequires($this->page(PageContext::TYPE_CMS, 1, 'cms')));
        self::assertTrue($siteWide->pageRequires($this->page(PageContext::TYPE_CMS, 2, 'cms')));
    }

    public function testControllerAndUrlRules(): void
    {
        $engine = $this->engine(array('protect_controllers' => array('manufacturer'), 'protect_urls' => array('/promo/*')));
        self::assertTrue($engine->pageRequires($this->page(PageContext::TYPE_OTHER, 0, 'Manufacturer', '/brands')));
        self::assertTrue($engine->pageRequires($this->page(PageContext::TYPE_OTHER, 0, 'module-foo-bar', '/promo/summer')));
        self::assertFalse($engine->pageRequires($this->page(PageContext::TYPE_OTHER, 0, 'contact', '/contact-us')));
    }

    public function testUrlExclusionLiftsSiteWideOnProductPageWithoutExplicitRule(): void
    {
        $engine = $this->engine(array('site_wide' => true, 'exclude_urls' => array('/free-*')));
        self::assertFalse($engine->pageRequires($this->page(PageContext::TYPE_PRODUCT, 2, 'product', '/free-sample')));
        self::assertTrue($engine->pageRequires($this->page(PageContext::TYPE_PRODUCT, 2, 'product', '/2-shirt')));
    }

    public function testExplicitProductProtectionBeatsUrlExclusion(): void
    {
        $engine = $this->engine(array('protect_products' => array(2), 'exclude_urls' => array('/free-*')));
        self::assertTrue($engine->pageRequires($this->page(PageContext::TYPE_PRODUCT, 2, 'product', '/free-sample')));
    }

    public function testUrlProtectAndExcludeConflictProtects(): void
    {
        $engine = $this->engine(array('protect_urls' => array('/promo/*'), 'exclude_controllers' => array('module-foo-bar')));
        self::assertTrue($engine->pageRequires($this->page(PageContext::TYPE_OTHER, 0, 'module-foo-bar', '/promo/x')));
    }

    public function testCartRequiresWhenAnyItemRequires(): void
    {
        $engine = $this->engine(array('protect_products' => array(9)));
        self::assertTrue($engine->cartRequires(array(1, 9)));
        self::assertFalse($engine->cartRequires(array(1, 2)));
        self::assertFalse($engine->cartRequires(array()));
        self::assertTrue($engine->pageRequires($this->page(PageContext::TYPE_CART, 0, 'cart', '/cart', array(1, 9))));
        self::assertFalse($engine->pageRequires($this->page(PageContext::TYPE_CHECKOUT, 0, 'order', '/order', array(1))));
    }

    public function testSystemPagesNeverRequire(): void
    {
        $engine = $this->engine(array('site_wide' => true, 'protect_controllers' => array('authentication')));
        self::assertFalse($engine->pageRequires($this->page(PageContext::TYPE_SYSTEM, 0, 'authentication', '/login')));
    }

    public function testProductCategoriesAreLookedUpOncePerRequest(): void
    {
        $lookup = $this->lookup();
        $engine = $this->engine(array('protect_categories' => array(5)), $lookup);
        $engine->productRequires(1);
        $engine->productRequires(1);
        $engine->cartRequires(array(1, 1));
        self::assertSame(1, $lookup->productCalls);
    }
}

<?php

namespace ProofAge\PrestaShop\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use ProofAge\PrestaShop\Rules\RuleSet;

final class RuleSetTest extends TestCase
{
    public function testNormalisesInput(): void
    {
        $rules = RuleSet::fromArray(array(
            'site_wide' => '1',
            'protect_products' => array('3', 3, '0', -1, 'x', 7),
            'protect_controllers' => array(' Manufacturer ', '', 'manufacturer'),
            'protect_urls' => 'not-an-array',
        ));

        self::assertTrue($rules->siteWide);
        self::assertSame(array(3, 7), $rules->protectProducts);
        self::assertSame(array('manufacturer'), $rules->protectControllers);
        self::assertSame(array(), $rules->protectUrls);
        self::assertTrue($rules->includeChildren);
    }

    public function testIncludeChildrenCanBeDisabled(): void
    {
        self::assertFalse(RuleSet::fromArray(array('include_children' => 0))->includeChildren);
    }

    public function testIsEmpty(): void
    {
        self::assertTrue(RuleSet::fromArray(array('exclude_products' => array(1)))->isEmpty());
        self::assertFalse(RuleSet::fromArray(array('protect_cms' => array(1)))->isEmpty());
        self::assertFalse(RuleSet::fromArray(array('site_wide' => true))->isEmpty());
    }
}

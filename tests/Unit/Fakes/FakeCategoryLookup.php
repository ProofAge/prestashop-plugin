<?php

namespace ProofAge\PrestaShop\Tests\Unit\Fakes;

use ProofAge\PrestaShop\Rules\CategoryLookup;

final class FakeCategoryLookup implements CategoryLookup
{
    public $productCalls = 0;

    /**
     * @param array<int,list<int>> $productCategories id_product => categories
     * @param array<int,int>       $parents           id_category => id_parent (0 = none)
     */
    public function __construct(private $productCategories, private $parents)
    {
    }

    public function categoriesForProduct(int $idProduct): array
    {
        ++$this->productCalls;

        return $this->productCategories[$idProduct] ?? array();
    }

    public function ancestorsOf(int $idCategory): array
    {
        $out = array();
        $current = $this->parents[$idCategory] ?? 0;
        while ($current > 0) {
            $out[] = $current;
            $current = $this->parents[$current] ?? 0;
        }

        return $out;
    }
}

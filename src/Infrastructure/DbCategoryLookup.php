<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Infrastructure;

use ProofAge\PrestaShop\Rules\CategoryLookup;

final class DbCategoryLookup implements CategoryLookup
{
    /** @var array<int,int>|null id_category => id_parent */
    private $parents;

    public function categoriesForProduct(int $idProduct): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT `id_category` FROM `' . _DB_PREFIX_ . 'category_product` WHERE `id_product` = ' . (int) $idProduct
        );

        return array_map('intval', array_column(is_array($rows) ? $rows : [], 'id_category'));
    }

    public function ancestorsOf(int $idCategory): array
    {
        $parents = $this->parents();
        $out = [];
        $current = isset($parents[$idCategory]) ? $parents[$idCategory] : 0;
        while ($current > 0 && !in_array($current, $out, true)) {
            $out[] = $current;
            $current = isset($parents[$current]) ? $parents[$current] : 0;
        }

        return $out;
    }

    /**
     * @return array<int,int>
     */
    private function parents(): array
    {
        if ($this->parents === null) {
            $rows = \Db::getInstance()->executeS('SELECT `id_category`, `id_parent` FROM `' . _DB_PREFIX_ . 'category`');
            $this->parents = [];
            foreach (is_array($rows) ? $rows : [] as $row) {
                $this->parents[(int) $row['id_category']] = (int) $row['id_parent'];
            }
        }

        return $this->parents;
    }
}

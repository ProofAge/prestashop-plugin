<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Rules;

if (!defined('_PS_VERSION_')) {
    exit;
}

interface CategoryLookup
{
    /**
     * Every category the product is associated with (not only its default one).
     *
     * @return int[]
     */
    public function categoriesForProduct(int $idProduct): array;

    /**
     * Parent chain of a category, nearest first, without the category itself.
     *
     * @return int[]
     */
    public function ancestorsOf(int $idCategory): array;
}

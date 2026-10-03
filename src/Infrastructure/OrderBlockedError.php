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

use PrestaShop\PrestaShop\Core\Module\Exception\ModuleErrorInterface;

/**
 * Thrown from actionObjectOrderAddBefore to abort order creation.
 * Extends \Error (not Exception) so Hook::exec() cannot swallow it in production.
 */
final class OrderBlockedError extends \Error implements ModuleErrorInterface
{
}

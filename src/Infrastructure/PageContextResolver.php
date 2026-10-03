<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Infrastructure;

use ProofAge\PrestaShop\Rules\PageContext;

final class PageContextResolver
{
    public const SYSTEM_CONTROLLERS = ['authentication', 'registration', 'password', 'pagenotfound', 'maintenance'];

    public function resolve(\FrontController $controller, \Context $context): PageContext
    {
        $name = self::controllerName($controller);
        $path = $this->path($context);

        // AJAX exactly as core decides it (Controller::isAjax(): `ajax` parameter or Accept: application/json),
        // so a request is only exempt when core really skips the full page. X-Requested-With alone still gets the page.
        if (in_array($name, self::SYSTEM_CONTROLLERS, true) || strpos($name, 'module-proofage-') === 0 || $controller->ajax) {
            return new PageContext(PageContext::TYPE_SYSTEM, 0, $name, $path);
        }

        switch ($name) {
            case 'product':
                return new PageContext(PageContext::TYPE_PRODUCT, (int) \Tools::getValue('id_product'), $name, $path);
            case 'category':
                return new PageContext(PageContext::TYPE_CATEGORY, (int) \Tools::getValue('id_category'), $name, $path);
            case 'cms':
                $idCms = (int) \Tools::getValue('id_cms');

                return new PageContext($idCms > 0 ? PageContext::TYPE_CMS : PageContext::TYPE_OTHER, $idCms, $name, $path);
            case 'cart':
                return new PageContext(PageContext::TYPE_CART, 0, $name, $path, self::cartProductIds($context->cart));
            case 'order':
                return new PageContext(PageContext::TYPE_CHECKOUT, 0, $name, $path, self::cartProductIds($context->cart));
            default:
                // Payment providers POST cookieless callbacks to arbitrary module controllers: never gate those.
                $type = $this->isReadRequest() ? PageContext::TYPE_OTHER : PageContext::TYPE_SYSTEM;

                return new PageContext($type, 0, $name, $path);
        }
    }

    public static function controllerName(\FrontController $controller): string
    {
        if ($controller instanceof \ModuleFrontController) {
            return strtolower('module-' . $controller->module->name . '-' . (string) \Tools::getValue('controller'));
        }

        return strtolower((string) $controller->php_self);
    }

    /**
     * @param mixed $cart
     *
     * @return int[]
     */
    public static function cartProductIds($cart): array
    {
        if (!$cart instanceof \Cart || !$cart->id) {
            return [];
        }
        $ids = [];
        foreach ($cart->getProducts() as $product) {
            $ids[] = (int) $product['id_product'];
        }

        return array_values(array_unique($ids));
    }

    private function isReadRequest(): bool
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';

        return $method === 'GET' || $method === 'HEAD';
    }

    private function path(\Context $context): string
    {
        $uri = (string) parse_url(isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
        $base = rtrim((string) $context->shop->getBaseURI(), '/');
        if ($base !== '' && strpos($uri, $base) === 0) {
            $uri = (string) substr($uri, strlen($base));
        }
        $segments = explode('/', ltrim($uri, '/'));
        if (\Language::isMultiLanguageActivated() && isset($segments[0]) && strtolower($segments[0]) === strtolower((string) $context->language->iso_code)) {
            array_shift($segments);
        }

        return '/' . implode('/', $segments);
    }
}

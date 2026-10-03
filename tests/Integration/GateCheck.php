<?php

use ProofAge\PrestaShop\Infrastructure\ServiceFactory;
use ProofAge\PrestaShop\Infrastructure\Settings;
use ProofAge\PrestaShop\Verification\CustomerVerification;

return [
    'protected product redirects to the gate, gate answers 403 noindex' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);

        $page = proofage_http('GET', proofage_product_url($id));
        check($page['status'] === 302, 'expected 302, got ' . $page['status']);
        $location = proofage_location($page);
        check(str_contains($location, 'module/proofage/gate') && str_contains($location, 'back='), 'bad location ' . $location);

        $gate = proofage_http('GET', $location);
        check($gate['status'] === 403, 'gate status ' . $gate['status']);
        check(str_contains($gate['headers']['x-robots-tag'] ?? '', 'noindex'), 'missing noindex');
        check(str_contains($gate['body'], 'data-proofage-start'), 'gate button missing');
        check(str_contains($gate['body'], 'proofageGate'), 'gate JS config missing');
    },
    'X-Requested-With alone does not bypass the gate (core renders the full page)' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);

        // Core Controller::isAjax() ignores X-Requested-With, so this request gets the full HTML page: it must be gated.
        $page = proofage_http('GET', proofage_product_url($id), ['headers' => ['X-Requested-With: XMLHttpRequest']]);
        check($page['status'] === 302, 'expected 302, got ' . $page['status']);
        check(str_contains(proofage_location($page), 'module/proofage/gate'), 'bad location ' . proofage_location($page));
    },
    'Accept: application/json on a protected product never returns the product' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);
        $name = (string) Product::getProductName($id, null, (int) Configuration::get('PS_LANG_DEFAULT'));

        // Core Controller::isAjax() treats Accept: application/json as AJAX (no page is rendered), so the
        // product AJAX guard answers: 403 JSON with the gate URL, never the product page or its content.
        $page = proofage_http('GET', proofage_product_url($id), ['headers' => ['Accept: application/json']]);
        check($page['status'] === 403, 'expected 403, got ' . $page['status']);
        check(!str_contains($page['body'], $name) && !str_contains($page['body'], 'product-description'), 'product content leaked');
        $json = json_decode($page['body'], true);
        check(!empty($json['proofage']['required']), 'missing proofage.required: ' . substr($page['body'], 0, 200));
    },
    'product AJAX actions (quickview, refresh) of a protected product are refused with 403 JSON' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);
        $name = (string) Product::getProductName($id, null, (int) Configuration::get('PS_LANG_DEFAULT'));

        foreach (['quickview', 'refresh'] as $action) {
            $response = proofage_http('POST', '/index.php?controller=product', ['body' => ['ajax' => '1', 'action' => $action, 'id_product' => (string) $id]]);
            check($response['status'] === 403, $action . ': expected 403, got ' . $response['status']);
            check(!str_contains($response['body'], $name), $action . ': product content leaked');
            $json = json_decode($response['body'], true);
            check(($json['hasError'] ?? false) === true && !empty($json['errors'][0]), $action . ': missing error ' . substr($response['body'], 0, 200));
            check(!empty($json['proofage']['required']) && str_contains($json['proofage']['gateUrl'] ?? '', 'module/proofage/gate'), $action . ': missing proofage payload');
        }
    },
    'product AJAX actions work for a verified customer' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);
        $email = proofage_set_customer_password(2, 'Proofage123!');
        ServiceFactory::forShop(1)->customers()->save(new CustomerVerification(2, 1, 'ver-c', time(), null));
        $jar = proofage_new_jar();
        proofage_login($jar, $email, 'Proofage123!');

        foreach (['quickview', 'refresh'] as $action) {
            $response = proofage_http('POST', '/index.php?controller=product', ['jar' => $jar, 'body' => ['ajax' => '1', 'action' => $action, 'id_product' => (string) $id]]);
            check($response['status'] === 200, $action . ': verified customer got ' . $response['status']);
            check(!isset(json_decode($response['body'], true)['proofage']), $action . ': verified customer refused');
        }
    },
    'unprotected product is served' => function (): void {
        $products = proofage_demo_products();
        proofage_configure([Settings::PROTECT_PRODUCTS => [$products['simple']]]);

        check(proofage_http('GET', proofage_product_url($products['combinations']))['status'] === 200, 'unprotected product blocked');
    },
    'nothing happens without API keys' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id], Settings::SECRET_KEY => '']);

        check(proofage_http('GET', proofage_product_url($id))['status'] === 200, 'gated without keys');
    },
    'protected category page and its child redirect' => function (): void {
        $idParent = (int) Db::getInstance()->getValue('SELECT `id_category` FROM `' . _DB_PREFIX_ . 'category` WHERE `level_depth` = 2 AND `active` = 1 AND `id_category` IN (SELECT `id_parent` FROM `' . _DB_PREFIX_ . 'category` WHERE `active` = 1) ORDER BY `id_category`');
        $idChild = (int) Db::getInstance()->getValue('SELECT `id_category` FROM `' . _DB_PREFIX_ . 'category` WHERE `id_parent` = ' . $idParent . ' AND `active` = 1');
        proofage_configure([Settings::PROTECT_CATEGORIES => [$idParent]]);

        check(proofage_http('GET', proofage_category_url($idParent))['status'] === 302, 'parent not gated');
        check(proofage_http('GET', proofage_category_url($idChild))['status'] === 302, 'child not gated');

        proofage_configure([Settings::INCLUDE_CHILDREN => 0]);
        check(proofage_http('GET', proofage_category_url($idChild))['status'] === 200, 'child gated without include_children');
    },
    'protected CMS page and URL pattern redirect' => function (): void {
        proofage_configure([Settings::PROTECT_CMS => [1]]);
        check(proofage_http('GET', proofage_cms_url(1))['status'] === 302, 'cms not gated');
        check(proofage_http('GET', proofage_cms_url(2))['status'] === 200, 'other cms gated');

        // Patterns are written without the language prefix (/fr/...), which multi-language shops add to every URL.
        $path = (string) parse_url(proofage_cms_url(2), PHP_URL_PATH);
        if (Language::isMultiLanguageActivated()) {
            $path = (string) preg_replace('#^/' . preg_quote(Context::getContext()->language->iso_code, '#') . '(?=/)#', '', $path);
        }
        proofage_configure([Settings::PROTECT_CMS => [], Settings::PROTECT_URLS => [$path]]);
        check(proofage_http('GET', proofage_cms_url(2))['status'] === 302, 'url pattern not gated');
    },
    'site-wide gate keeps login reachable' => function (): void {
        proofage_configure([Settings::SITE_WIDE => 1]);

        check(proofage_http('GET', proofage_page_path('index'))['status'] === 302, 'home not gated');
        check(proofage_http('GET', proofage_page_path('authentication'))['status'] === 200, 'login gated');
    },
    'overlay mode renders the page with the overlay' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id], Settings::DISPLAY_MODE => 'overlay']);

        $page = proofage_http('GET', proofage_product_url($id));
        check($page['status'] === 200, 'overlay page status ' . $page['status']);
        check(str_contains($page['body'], 'class="proofage-overlay"'), 'overlay missing');
    },
    'verified customer sees protected product' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);
        $email = proofage_set_customer_password(2, 'Proofage123!');
        ServiceFactory::forShop(1)->customers()->save(new CustomerVerification(2, 1, 'ver-c', time(), null));

        $jar = proofage_new_jar();
        proofage_login($jar, $email, 'Proofage123!');
        check(proofage_http('GET', proofage_product_url($id), ['jar' => $jar])['status'] === 200, 'verified customer gated');
    },
    'gate rejects an off-site back URL' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);

        $gate = proofage_http('GET', '/module/proofage/gate?back=' . rawurlencode('https://evil.test/'));
        check(!str_contains($gate['body'], 'evil.test'), 'off-site back URL leaked into the page');
        check($gate['status'] === 302 && !str_contains(proofage_location($gate), 'evil.test'), 'off-site back URL not stripped');

        $clean = proofage_http('GET', proofage_location($gate));
        check($clean['status'] === 403 && !str_contains($clean['body'], 'evil.test'), 'sanitized gate page wrong');
    },
    'non-GET request to another module controller is never gated' => function (): void {
        proofage_configure([Settings::SITE_WIDE => 1]);

        $post = proofage_http('POST', '/module/gsitemap/cron', ['body' => ['token' => 'x']]);
        check(!str_contains(proofage_location($post), 'module/proofage/gate'), 'POST callback redirected to the gate: ' . proofage_location($post));
        check(proofage_http('GET', proofage_page_path('index'))['status'] === 302, 'home no longer gated');
    },
];

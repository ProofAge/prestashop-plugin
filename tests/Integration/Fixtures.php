<?php

use ProofAge\PrestaShop\Infrastructure\ServiceFactory;
use ProofAge\PrestaShop\Infrastructure\Settings;
use ProofAge\PrestaShop\Support\GateTexts;

const PROOFAGE_FAKE_API = 'http://127.0.0.1:8765';
const PROOFAGE_FAKE_PK = 'pk_test_fake';
const PROOFAGE_FAKE_SK = 'sk_test_fake';

function proofage_base_url(): string
{
    return rtrim(getenv('PS_BASE_URL') ?: 'https://proofage-prestashop.test', '/');
}

/** CA bundle for the local Herd/Valet TLS certificates, when the shop is served over https. */
function proofage_local_ca_file(): ?string
{
    $file = getenv('HOME') . '/Library/Application Support/Herd/config/valet/CA/LaravelValetCASelfSigned.pem';

    return is_file($file) ? $file : null;
}

/** Wipes module tables and restores default settings pointed at the fake API. */
function proofage_reset(): void
{
    foreach (['proofage_verification', 'proofage_customer', 'proofage_webhook_delivery', 'proofage_order'] as $table) {
        Db::getInstance()->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . $table . '`');
    }
    // Drop shop-level overrides (e.g. saved by the settings form) so global values apply.
    Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name` LIKE "PROOFAGE\\_%" AND (`id_shop` IS NOT NULL OR `id_shop_group` IS NOT NULL)');
    foreach (Settings::defaults() as $key => $value) {
        Configuration::updateGlobalValue($key, $value);
    }
    $texts = ['title' => [], 'description' => [], 'button' => []];
    foreach (Language::getLanguages(false) as $language) {
        $defaults = GateTexts::defaultsFor((string) $language['iso_code']);
        foreach (array_keys($texts) as $field) {
            $texts[$field][(int) $language['id_lang']] = $defaults[$field];
        }
    }
    Configuration::updateGlobalValue(Settings::GATE_TITLE, $texts['title']);
    Configuration::updateGlobalValue(Settings::GATE_DESCRIPTION, $texts['description'], true);
    Configuration::updateGlobalValue(Settings::GATE_BUTTON, $texts['button']);
    proofage_configure([
        Settings::PUBLIC_KEY => PROOFAGE_FAKE_PK,
        Settings::SECRET_KEY => PROOFAGE_FAKE_SK,
        Settings::API_URL => PROOFAGE_FAKE_API,
    ]);
    Configuration::updateGlobalValue('PS_SHOP_ENABLE', 1);
}

/** @param array<string,mixed> $values */
function proofage_configure(array $values): void
{
    foreach ($values as $key => $value) {
        if (is_array($value)) {
            $value = json_encode(array_values($value));
        }
        Configuration::updateGlobalValue($key, $value);
    }
    Configuration::clearConfigurationCacheForTesting();
    ServiceFactory::reset();
}

/**
 * @param array{jar?:string,body?:array<string,string>|string,headers?:list<string>,ajax?:bool} $options
 *
 * @return array{status:int,headers:array<string,string>,body:string}
 */
function proofage_http(string $method, string $path, array $options = []): array
{
    $url = str_starts_with($path, 'http') ? $path : proofage_base_url() . $path;
    $ch = curl_init($url);
    $headers = $options['headers'] ?? [];
    if (!empty($options['ajax'])) {
        $headers[] = 'X-Requested-With: XMLHttpRequest';
        $headers[] = 'Accept: application/json';
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);
    if (str_starts_with($url, 'https://') && proofage_local_ca_file() !== null) {
        curl_setopt($ch, CURLOPT_CAINFO, proofage_local_ca_file());
    }
    if (isset($options['jar'])) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $options['jar']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $options['jar']);
    }
    if (isset($options['body'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($options['body']) ? http_build_query($options['body']) : $options['body']);
    }
    $raw = (string) curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $parsed = [];
    foreach (explode("\r\n", substr($raw, 0, $headerSize)) as $line) {
        if (str_contains($line, ':')) {
            [$name, $value] = explode(':', $line, 2);
            $parsed[strtolower(trim($name))] = trim($value);
        }
    }

    return ['status' => $status, 'headers' => $parsed, 'body' => substr($raw, $headerSize)];
}

function proofage_new_jar(): string
{
    return tempnam(sys_get_temp_dir(), 'proofage-jar-');
}

/** Static front token for a cookie jar: read it from any storefront page's prestashop.static_token. */
function proofage_static_token(string $jar): string
{
    $page = proofage_http('GET', proofage_page_path('index'), ['jar' => $jar]);
    preg_match('/"static_token":"([0-9a-f]+)"/', $page['body'], $m);

    return $m[1] ?? '';
}

function proofage_fake_api_start(): void
{
    $socket = @fsockopen('127.0.0.1', 8765, $errno, $errstr, 0.2);
    if ($socket !== false) {
        fclose($socket);

        return;
    }
    $router = dirname(__DIR__) . '/Fixtures/fake-proofage-api.php';
    exec('FAKE_SK=' . PROOFAGE_FAKE_SK . ' php -S 127.0.0.1:8765 ' . escapeshellarg($router) . ' > /dev/null 2>&1 &');
    usleep(500000);
}

function proofage_fake_api_set(string $verificationId, string $status, ?string $method = null): void
{
    $query = http_build_query(array_filter(['status' => $status, 'method' => $method]));
    $ch = curl_init(PROOFAGE_FAKE_API . '/__set/' . $verificationId . '?' . $query);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_RETURNTRANSFER => true]);
    curl_exec($ch);
}

/** @return array{status:int,headers:array<string,string>,body:string} */
function proofage_send_webhook(array $payload, string $deliveryId, ?int $timestamp = null, string $secret = PROOFAGE_FAKE_SK): array
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ts = (string) ($timestamp ?? time());

    return proofage_http('POST', '/module/proofage/webhook', [
        'body' => $body,
        'headers' => [
            'Content-Type: application/json',
            'X-Timestamp: ' . $ts,
            'X-HMAC-Signature: ' . hash_hmac('sha256', $ts . '.' . $body, $secret),
            'X-ProofAge-Webhook-Delivery-Id: ' . $deliveryId,
        ],
    ]);
}

/** First product of the demo catalog with combinations, and one without. */
function proofage_demo_products(): array
{
    $withCombinations = (int) Db::getInstance()->getValue('SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product_attribute` ORDER BY `id_product`');
    $simple = (int) Db::getInstance()->getValue('SELECT p.`id_product` FROM `' . _DB_PREFIX_ . 'product` p LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON pa.`id_product` = p.`id_product` WHERE pa.`id_product_attribute` IS NULL AND p.`active` = 1 ORDER BY p.`id_product`');

    return ['combinations' => $withCombinations, 'simple' => $simple];
}

function proofage_product_url(int $idProduct): string
{
    return Context::getContext()->link->getProductLink(new Product($idProduct, false, (int) Configuration::get('PS_LANG_DEFAULT')));
}

/** Shop-relative path of a front page (friendly URLs differ per PrestaShop version and language). */
function proofage_page_path(string $controller, array $params = []): string
{
    $url = Context::getContext()->link->getPageLink($controller, null, (int) Configuration::get('PS_LANG_DEFAULT'), $params);
    $parts = parse_url($url);

    return ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
}

function proofage_category_url(int $idCategory): string
{
    return Context::getContext()->link->getCategoryLink($idCategory);
}

function proofage_cms_url(int $idCms): string
{
    return Context::getContext()->link->getCMSLink($idCms);
}

/** Sets a known password on a demo customer and returns its email. */
function proofage_set_customer_password(int $idCustomer, string $password): string
{
    $customer = new Customer($idCustomer);
    $hashing = new PrestaShop\PrestaShop\Core\Crypto\Hashing();
    $customer->passwd = $hashing->hash($password);
    $customer->update();

    return $customer->email;
}

function proofage_login(string $jar, string $email, string $password): void
{
    $response = proofage_http('POST', proofage_page_path('authentication', ['back' => 'my-account']), [
        'jar' => $jar,
        'body' => ['email' => $email, 'password' => $password, 'submitLogin' => '1'],
    ]);
    check($response['status'] === 302, 'login failed with HTTP ' . $response['status']);
}

function proofage_location(array $response): string
{
    return $response['headers']['location'] ?? '';
}

/** Starts a verification for a fresh browser and returns [jar, json]. */
function proofage_start_session(string $back = '/'): array
{
    $jar = proofage_new_jar();
    $token = proofage_static_token($jar);
    $response = proofage_http('POST', '/module/proofage/session', [
        'jar' => $jar,
        'ajax' => true,
        'body' => ['token' => $token, 'back' => proofage_base_url() . $back],
    ]);
    check($response['status'] === 200, 'session HTTP ' . $response['status'] . ' ' . $response['body']);

    return [$jar, json_decode($response['body'], true)];
}

function proofage_status(string $jar): array
{
    return json_decode(proofage_http('GET', '/module/proofage/status', ['jar' => $jar, 'ajax' => true])['body'], true);
}

function proofage_build_cart(int $idCustomer, int $idProduct, int $idProductAttribute = 0): Cart
{
    $context = Context::getContext();
    $customer = new Customer($idCustomer);
    $context->customer = $customer;
    $cart = new Cart();
    $cart->id_customer = $idCustomer;
    $cart->id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
    $cart->id_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
    $cart->id_shop = 1;
    $cart->id_shop_group = 1;
    $cart->id_address_delivery = $cart->id_address_invoice = (int) Address::getFirstCustomerAddressId($idCustomer);
    $cart->id_carrier = (int) Configuration::get('PS_CARRIER_DEFAULT');
    $cart->secure_key = $customer->secure_key;
    $cart->add();
    // Like a real front request: PrestaShop 8 price code dies without a context cart (or employee).
    $context->cart = $cart;
    $context->currency = new Currency($cart->id_currency);
    $cart->updateQty(1, $idProduct, $idProductAttribute ?: null);

    return $cart;
}

/** Places the order through ps_wirepayment, like a real checkout; returns the order id. */
function proofage_validate_order(Cart $cart): int
{
    /** @var PaymentModule $module */
    $module = Module::getInstanceByName('ps_wirepayment');
    $customer = new Customer($cart->id_customer);
    $module->validateOrder(
        (int) $cart->id,
        (int) Configuration::get('PS_OS_BANKWIRE'),
        (float) $cart->getOrderTotal(true, Cart::BOTH),
        'Bank wire',
        null,
        [],
        null,
        false,
        $customer->secure_key
    );

    return (int) $module->currentOrder;
}

/** The shop's (renamed) back-office directory: the one holding PrestaShop's admin init.php. */
function proofage_admin_dir(): string
{
    foreach (glob(_PS_ROOT_DIR_ . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        if (basename($dir) !== 'admin-api' && is_file($dir . '/init.php') && is_file($dir . '/index.php')) {
            return $dir;
        }
    }
    throw new RuntimeException('Back-office directory not found under ' . _PS_ROOT_DIR_);
}

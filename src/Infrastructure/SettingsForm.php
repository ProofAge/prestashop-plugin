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

use ProofAge\PrestaShop\Support\DateFormat;
use ProofAge\PrestaShop\Support\Input;
use ProofAge\PrestaShop\Verification\VerificationRecord;

final class SettingsForm
{
    /** @var \Module */
    private $module;
    /** @var \Context */
    private $context;
    /** @var int */
    private $idShop;

    public function __construct(\Module $module, \Context $context, int $idShop)
    {
        $this->module = $module;
        $this->context = $context;
        $this->idShop = $idShop;
    }

    public function t(string $text): string
    {
        return $this->context->getTranslator()->trans($text, [], 'Modules.Proofage.Admin');
    }

    /**
     * Rules without both API keys protect nothing (the gatekeeper stays inactive): say so on the settings page.
     *
     * @return string|null
     */
    public function inactiveWarning()
    {
        $settings = new Settings($this->idShop);
        if ($settings->isConfigured() || $settings->ruleSet()->isEmpty()) {
            return null;
        }

        return $this->t('Protection is inactive: enter your ProofAge public and secret keys.');
    }

    /**
     * @param array<string,mixed> $input usually Tools::getAllValues()
     *
     * @return string[] errors; nothing is saved when not empty
     */
    public function save(array $input): array
    {
        $errors = [];
        $publicKey = trim((string) (isset($input[Settings::PUBLIC_KEY]) ? $input[Settings::PUBLIC_KEY] : ''));
        $secretKey = trim((string) (isset($input[Settings::SECRET_KEY]) ? $input[Settings::SECRET_KEY] : ''));
        $apiUrl = trim((string) (isset($input[Settings::API_URL]) ? $input[Settings::API_URL] : ''));
        $apiUrl = $apiUrl === '' ? Settings::DEFAULT_API_URL : rtrim($apiUrl, '/');

        if ($publicKey !== '' && !preg_match('/^pk_(test|live)_[A-Za-z0-9_]+$/', $publicKey)) {
            $errors[] = $this->t('The public key must start with pk_test_ or pk_live_.');
        }
        if ($secretKey !== '' && !preg_match('/^sk_(test|live)_[A-Za-z0-9_]+$/', $secretKey)) {
            $errors[] = $this->t('The secret key must start with sk_test_ or sk_live_.');
        }
        $savedSecret = (new Settings($this->idShop))->secretKey();
        $effectiveSecret = $secretKey !== '' ? $secretKey : $savedSecret;
        if ($publicKey !== '' && $effectiveSecret !== '' && substr($publicKey, 3, 4) !== substr($effectiveSecret, 3, 4)) {
            $errors[] = $this->t('The public and secret keys must belong to the same mode (test or live).');
        }
        if (!filter_var($apiUrl, FILTER_VALIDATE_URL) || (string) parse_url($apiUrl, PHP_URL_SCHEME) !== 'https') {
            $errors[] = $this->t('The API URL must be an HTTPS address.');
        }
        if ($errors !== []) {
            return $errors;
        }

        $save = function ($key, $value) {
            Settings::update($key, $value, $this->idShop);
        };
        $save(Settings::PUBLIC_KEY, $publicKey);
        if ($secretKey !== '') {
            $save(Settings::SECRET_KEY, $secretKey);
        }
        $save(Settings::API_URL, $apiUrl);
        $save(Settings::SITE_WIDE, empty($input[Settings::SITE_WIDE]) ? 0 : 1);
        $save(Settings::INCLUDE_CHILDREN, empty($input[Settings::INCLUDE_CHILDREN]) ? 0 : 1);
        foreach ([Settings::PROTECT_PRODUCTS, Settings::EXCLUDE_PRODUCTS, Settings::PROTECT_CATEGORIES, Settings::EXCLUDE_CATEGORIES, Settings::PROTECT_CMS, Settings::EXCLUDE_CMS] as $key) {
            $save($key, Input::ids(isset($input[$key]) ? $input[$key] : []));
        }
        foreach ([Settings::PROTECT_CONTROLLERS, Settings::EXCLUDE_CONTROLLERS] as $key) {
            $save($key, array_values(array_unique(array_map('strtolower', Input::lines((string) (isset($input[$key]) ? $input[$key] : ''))))));
        }
        foreach ([Settings::PROTECT_URLS, Settings::EXCLUDE_URLS] as $key) {
            $save($key, Input::lines((string) (isset($input[$key]) ? $input[$key] : '')));
        }
        $display = isset($input[Settings::DISPLAY_MODE]) ? $input[Settings::DISPLAY_MODE] : '';
        $save(Settings::DISPLAY_MODE, $display === 'overlay' ? 'overlay' : 'gate');
        $launch = isset($input[Settings::LAUNCH_MODE]) ? $input[Settings::LAUNCH_MODE] : '';
        $save(Settings::LAUNCH_MODE, $launch === 'redirect' ? 'redirect' : 'modal');
        $save(Settings::GUEST_TTL_HOURS, Input::clampInt(isset($input[Settings::GUEST_TTL_HOURS]) ? $input[Settings::GUEST_TTL_HOURS] : null, 1, 720, 24));
        $save(Settings::CUSTOMER_TTL_DAYS, Input::clampInt(isset($input[Settings::CUSTOMER_TTL_DAYS]) ? $input[Settings::CUSTOMER_TTL_DAYS] : null, 0, 36500, 365));

        foreach (Settings::LANG_KEYS as $key) {
            $values = [];
            foreach (\Language::getLanguages(false) as $language) {
                $field = $key . '_' . (int) $language['id_lang'];
                $values[(int) $language['id_lang']] = trim(strip_tags((string) (isset($input[$field]) ? $input[$field] : '')));
            }
            $save($key, $values);
        }
        ServiceFactory::reset();

        return [];
    }

    public function render(): string
    {
        $helper = new \HelperForm();
        $helper->module = $this->module;
        $helper->name_controller = $this->module->name;
        $helper->identifier = 'proofage';
        $helper->token = \Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = \AdminController::$currentIndex . '&configure=' . $this->module->name;
        $helper->default_form_language = (int) \Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) \Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->submit_action = 'submitProofage';
        $helper->show_toolbar = false;
        $helper->tpl_vars = [
            'fields_value' => $this->values(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => (int) $this->context->language->id,
        ];

        return $helper->generateForm($this->forms());
    }

    /**
     * @param array<int,VerificationRecord> $records
     *
     * @return array<int,array<string,mixed>>
     */
    private function verificationRows(array $records): array
    {
        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                'verification_id' => $record->verificationId,
                'status' => $record->status,
                'method' => $record->method,
                'id_customer' => $record->idCustomer,
                'created_at' => DateFormat::utc((int) $record->createdAt),
                'decided_at' => $record->decidedAt ? DateFormat::utc((int) $record->decidedAt) : '',
            ];
        }

        return $rows;
    }

    public function renderLogs(): string
    {
        $factory = ServiceFactory::forShop($this->idShop);
        $this->context->smarty->assign([
            'proofage_verifications' => $this->verificationRows($factory->verifications()->latest($this->idShop, 50)),
            'proofage_deliveries' => $factory->deliveries()->latest(50),
        ]);

        return $this->context->smarty->fetch($this->module->getLocalPath() . 'views/templates/admin/logs.tpl');
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function forms(): array
    {
        $yesNo = [
            ['id' => 'on', 'value' => 1, 'label' => $this->t('Yes')],
            ['id' => 'off', 'value' => 0, 'label' => $this->t('No')],
        ];
        $settings = new Settings($this->idShop);
        $cms = \CMS::getCMSPages((int) $this->context->language->id, null, false, $this->idShop);
        $cmsOptions = ['query' => is_array($cms) ? $cms : [], 'id' => 'id_cms', 'name' => 'meta_title'];
        $submit = ['title' => $this->t('Save')];

        return [
            ['form' => [
                'legend' => ['title' => $this->t('Connection'), 'icon' => 'icon-link'],
                'input' => [
                    ['type' => 'text', 'label' => $this->t('Public key'), 'name' => Settings::PUBLIC_KEY, 'desc' => $this->t('Starts with pk_test_ or pk_live_. Find it in your ProofAge workspace.')],
                    ['type' => 'password', 'label' => $this->t('Secret key'), 'name' => Settings::SECRET_KEY, 'desc' => $this->t('Starts with sk_test_ or sk_live_. Leave empty to keep the saved key.')],
                    ['type' => 'text', 'label' => $this->t('API URL'), 'name' => Settings::API_URL, 'desc' => $this->t('Change only if ProofAge support asks you to.')],
                    ['type' => 'html', 'name' => 'proofage_connection', 'html_content' => $this->connectionBlock()],
                ],
                'submit' => $submit,
            ]],
            ['form' => [
                'legend' => ['title' => $this->t('Rules'), 'icon' => 'icon-shield'],
                'description' => $this->t('The most specific rule wins: product, then category, then page, then the whole site. When a protection and an exclusion apply at the same level, the protection wins.'),
                'input' => [
                    ['type' => 'switch', 'label' => $this->t('Protect the whole site'), 'name' => Settings::SITE_WIDE, 'is_bool' => true, 'values' => $yesNo],
                    ['type' => 'html', 'label' => $this->t('Protected products'), 'name' => 'proofage_protect_products', 'html_content' => $this->productPicker(Settings::PROTECT_PRODUCTS, $settings)],
                    ['type' => 'html', 'label' => $this->t('Excluded products'), 'name' => 'proofage_exclude_products', 'html_content' => $this->productPicker(Settings::EXCLUDE_PRODUCTS, $settings)],
                    ['type' => 'html', 'label' => $this->t('Protected categories'), 'name' => 'proofage_protect_categories', 'html_content' => $this->categoryTree('proofage-protect-categories', Settings::PROTECT_CATEGORIES, $settings)],
                    ['type' => 'html', 'label' => $this->t('Excluded categories'), 'name' => 'proofage_exclude_categories', 'html_content' => $this->categoryTree('proofage-exclude-categories', Settings::EXCLUDE_CATEGORIES, $settings)],
                    ['type' => 'switch', 'label' => $this->t('Apply category rules to subcategories'), 'name' => Settings::INCLUDE_CHILDREN, 'is_bool' => true, 'values' => $yesNo],
                    ['type' => 'select', 'label' => $this->t('Protected CMS pages'), 'name' => Settings::PROTECT_CMS . '[]', 'multiple' => true, 'class' => 'chosen', 'options' => $cmsOptions],
                    ['type' => 'select', 'label' => $this->t('Excluded CMS pages'), 'name' => Settings::EXCLUDE_CMS . '[]', 'multiple' => true, 'class' => 'chosen', 'options' => $cmsOptions],
                    ['type' => 'textarea', 'label' => $this->t('Protected controllers'), 'name' => Settings::PROTECT_CONTROLLERS, 'desc' => $this->t('One per line, e.g. manufacturer or module-mymodule-display.')],
                    ['type' => 'textarea', 'label' => $this->t('Excluded controllers'), 'name' => Settings::EXCLUDE_CONTROLLERS],
                    ['type' => 'textarea', 'label' => $this->t('Protected URLs'), 'name' => Settings::PROTECT_URLS, 'desc' => $this->t('One path per line, without the language prefix. Use * as a wildcard, e.g. /promo/*.')],
                    ['type' => 'textarea', 'label' => $this->t('Excluded URLs'), 'name' => Settings::EXCLUDE_URLS],
                ],
                'submit' => $submit,
            ]],
            ['form' => [
                'legend' => ['title' => $this->t('Display'), 'icon' => 'icon-eye'],
                'input' => [
                    ['type' => 'select', 'label' => $this->t('Protected pages show'), 'name' => Settings::DISPLAY_MODE, 'options' => ['query' => [
                        ['id' => 'gate', 'name' => $this->t('A verification page instead of the content (not indexed by search engines)')],
                        ['id' => 'overlay', 'name' => $this->t('The page blurred behind a verification window (content stays indexable)')],
                    ], 'id' => 'id', 'name' => 'name'], 'desc' => $this->t('The cart and checkout always use the verification page.')],
                    ['type' => 'select', 'label' => $this->t('Open the verification'), 'name' => Settings::LAUNCH_MODE, 'options' => ['query' => [
                        ['id' => 'modal', 'name' => $this->t('In a window on top of the shop')],
                        ['id' => 'redirect', 'name' => $this->t('On the ProofAge page, then come back')],
                    ], 'id' => 'id', 'name' => 'name']],
                    ['type' => 'text', 'label' => $this->t('Title'), 'name' => Settings::GATE_TITLE, 'lang' => true],
                    ['type' => 'textarea', 'label' => $this->t('Description'), 'name' => Settings::GATE_DESCRIPTION, 'lang' => true],
                    ['type' => 'text', 'label' => $this->t('Button'), 'name' => Settings::GATE_BUTTON, 'lang' => true],
                ],
                'submit' => $submit,
            ]],
            ['form' => [
                'legend' => ['title' => $this->t('Validity'), 'icon' => 'icon-time'],
                'input' => [
                    ['type' => 'text', 'label' => $this->t('Guests stay verified for'), 'name' => Settings::GUEST_TTL_HOURS, 'suffix' => $this->t('hours'), 'class' => 'fixed-width-sm', 'desc' => $this->t('From 1 to 720 hours.')],
                    ['type' => 'text', 'label' => $this->t('Customer accounts stay verified for'), 'name' => Settings::CUSTOMER_TTL_DAYS, 'suffix' => $this->t('days'), 'class' => 'fixed-width-sm', 'desc' => $this->t('0 means the verification never expires.')],
                ],
                'submit' => $submit,
            ]],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function values(): array
    {
        $settings = new Settings($this->idShop);
        $values = [
            Settings::PUBLIC_KEY => $settings->publicKey(),
            Settings::SECRET_KEY => '',
            Settings::API_URL => $settings->apiUrl(),
            Settings::SITE_WIDE => $settings->get(Settings::SITE_WIDE) === '1' ? 1 : 0,
            Settings::INCLUDE_CHILDREN => $settings->get(Settings::INCLUDE_CHILDREN) === '0' ? 0 : 1,
            Settings::PROTECT_CMS . '[]' => array_map('intval', $settings->getList(Settings::PROTECT_CMS)),
            Settings::EXCLUDE_CMS . '[]' => array_map('intval', $settings->getList(Settings::EXCLUDE_CMS)),
            Settings::PROTECT_CONTROLLERS => implode("\n", $settings->getList(Settings::PROTECT_CONTROLLERS)),
            Settings::EXCLUDE_CONTROLLERS => implode("\n", $settings->getList(Settings::EXCLUDE_CONTROLLERS)),
            Settings::PROTECT_URLS => implode("\n", $settings->getList(Settings::PROTECT_URLS)),
            Settings::EXCLUDE_URLS => implode("\n", $settings->getList(Settings::EXCLUDE_URLS)),
            Settings::DISPLAY_MODE => $settings->displayMode(),
            Settings::LAUNCH_MODE => $settings->launchMode(),
            Settings::GUEST_TTL_HOURS => $settings->get(Settings::GUEST_TTL_HOURS),
            Settings::CUSTOMER_TTL_DAYS => $settings->get(Settings::CUSTOMER_TTL_DAYS),
        ];
        foreach (Settings::LANG_KEYS as $key) {
            foreach (\Language::getLanguages(false) as $language) {
                $idLang = (int) $language['id_lang'];
                $values[$key][$idLang] = (string) \Configuration::get($key, $idLang, null, $this->idShop);
            }
        }

        return $values;
    }

    private function connectionBlock(): string
    {
        $this->context->smarty->assign([
            'proofage_webhook_url' => $this->context->link->getModuleLink('proofage', 'webhook', [], true),
            'proofage_test_url' => $this->context->link->getAdminLink('AdminProofageAjax') . '&ajax=1&action=TestConnection',
        ]);

        return $this->context->smarty->fetch($this->module->getLocalPath() . 'views/templates/admin/connection.tpl');
    }

    /**
     * HelperForm renders only one 'categories' input per form, so each tree is rendered here.
     * The tree posts its selection as KEY[].
     */
    private function categoryTree(string $id, string $key, Settings $settings): string
    {
        $tree = new \HelperTreeCategories($id);
        $tree->setInputName($key)
            ->setSelectedCategories(array_map('intval', $settings->getList($key)))
            ->setUseCheckBox(true)
            ->setUseSearch(true);

        return $tree->render();
    }

    private function productPicker(string $key, Settings $settings): string
    {
        $selected = [];
        foreach (array_map('intval', $settings->getList($key)) as $idProduct) {
            $name = \Product::getProductName($idProduct, null, (int) $this->context->language->id);
            $selected[] = ['id' => $idProduct, 'name' => $name !== false && $name !== '' ? $name : '#' . $idProduct];
        }
        $this->context->smarty->assign([
            'proofage_picker_name' => $key,
            'proofage_picker_value' => json_encode(array_column($selected, 'id')),
            'proofage_picker_selected' => $selected,
            'proofage_search_url' => $this->context->link->getAdminLink('AdminProofageAjax') . '&ajax=1&action=SearchProducts',
        ]);

        return $this->context->smarty->fetch($this->module->getLocalPath() . 'views/templates/admin/product_picker.tpl');
    }
}

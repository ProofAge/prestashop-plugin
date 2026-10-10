<?php

use ProofAge\PrestaShop\Infrastructure\Settings;
use ProofAge\PrestaShop\Infrastructure\SettingsForm;

function proofage_form(): SettingsForm
{
    return new SettingsForm(Module::getInstanceByName('proofage'), Context::getContext(), 1);
}

return [
    'settings form saves and normalises input' => function (): void {
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $errors = proofage_form()->save([
            Settings::PUBLIC_KEY => 'pk_test_abc',
            Settings::SECRET_KEY => 'sk_test_def',
            Settings::API_URL => 'https://api.proofage.net/',
            Settings::SITE_WIDE => '1',
            Settings::PROTECT_PRODUCTS => '[3,"5",0]',
            Settings::PROTECT_CATEGORIES => ['4', '6'],
            Settings::PROTECT_CMS => ['1'],
            Settings::PROTECT_CONTROLLERS => "Manufacturer\n\nmanufacturer",
            Settings::PROTECT_URLS => "/promo/*\n",
            Settings::DISPLAY_MODE => 'overlay',
            Settings::LAUNCH_MODE => 'nonsense',
            Settings::GUEST_TTL_HOURS => '9999',
            Settings::CUSTOMER_TTL_DAYS => '0',
            Settings::GATE_TITLE . '_' . $idLang => '<b>Adults only</b>',
        ]);
        check($errors === [], implode('; ', $errors));
        $s = new Settings(1);
        check($s->secretKey() === 'sk_test_def' && $s->apiUrl() === 'https://api.proofage.net', 'keys');
        check($s->getList(Settings::PROTECT_PRODUCTS) === [3, 5], 'products ' . json_encode($s->getList(Settings::PROTECT_PRODUCTS)));
        check($s->getList(Settings::PROTECT_CONTROLLERS) === ['manufacturer'], 'controllers');
        check($s->displayMode() === 'overlay' && $s->launchMode() === 'modal', 'modes');
        check($s->get(Settings::GUEST_TTL_HOURS) === '720' && $s->get(Settings::CUSTOMER_TTL_DAYS) === '0', 'ttl');
        check($s->gateTexts($idLang)['title'] === 'Adults only', 'title not stripped');
        check($s->ruleSet()->siteWide, 'site wide');
    },
    'blank secret keeps the saved one' => function (): void {
        proofage_form()->save([Settings::PUBLIC_KEY => 'pk_test_abc', Settings::SECRET_KEY => 'sk_test_keep']);
        proofage_form()->save([Settings::PUBLIC_KEY => 'pk_test_abc', Settings::SECRET_KEY => '']);
        check((new Settings(1))->secretKey() === 'sk_test_keep', 'secret overwritten');
    },
    'invalid input is rejected and nothing is saved' => function (): void {
        $before = Configuration::get(Settings::PUBLIC_KEY);
        $errors = proofage_form()->save([Settings::PUBLIC_KEY => 'pk_live_abc', Settings::SECRET_KEY => 'sk_test_def', Settings::API_URL => 'http://evil.test']);
        check(count($errors) === 2, 'expected mode mismatch and URL errors, got ' . json_encode($errors));
        check(Configuration::get(Settings::PUBLIC_KEY) === $before, 'saved despite errors');
    },
    'plain http API URLs are rejected, local ones included' => function (): void {
        foreach (['http://127.0.0.1:8765', 'http://localhost:8765'] as $url) {
            $errors = proofage_form()->save([Settings::PUBLIC_KEY => 'pk_test_abc', Settings::SECRET_KEY => 'sk_test_def', Settings::API_URL => $url]);
            check(count($errors) === 1, "expected the URL error for {$url}, got " . json_encode($errors));
            check(Configuration::get(Settings::API_URL) === PROOFAGE_FAKE_API, "{$url} saved despite the error");
        }
    },
    'hidden ajax tab exists' => function (): void {
        check(Tab::getIdFromClassName('AdminProofageAjax') > 0, 'AdminProofageAjax tab missing');
    },
    'ajax tab permissions are enforced per profile' => function (): void {
        require_once _PS_MODULE_DIR_ . 'proofage/controllers/admin/AdminProofageAjaxController.php';
        $profile = new Profile();
        foreach (Language::getIDs(false) as $idLang) {
            $profile->name[$idLang] = 'proofage-test-restricted';
        }
        check($profile->add(), 'could not create profile');
        try {
            check(!AdminProofageAjaxController::profileCan((int) $profile->id, 'view'), 'restricted profile can view');
            check(!AdminProofageAjaxController::profileCan((int) $profile->id, 'edit'), 'restricted profile can edit');
            check(AdminProofageAjaxController::profileCan(1, 'edit') && AdminProofageAjaxController::profileCan(1, 'view'), 'SuperAdmin denied');
        } finally {
            $profile->delete();
        }
    },
    'settings page warns when rules are set but keys are missing' => function (): void {
        $source = 'Protection is inactive: enter your ProofAge public and secret keys.';
        // In the context language (the 8.2 test shop is French).
        $warning = Context::getContext()->getTranslator()->trans($source, [], 'Modules.Proofage.Admin');
        check(proofage_form()->inactiveWarning() === null, 'warning without rules');

        proofage_configure([Settings::PROTECT_PRODUCTS => [3], Settings::SECRET_KEY => '']);
        check(proofage_form()->inactiveWarning() === $warning, 'missing secret key not reported');
        proofage_configure([Settings::SECRET_KEY => PROOFAGE_FAKE_SK, Settings::PUBLIC_KEY => '']);
        check(proofage_form()->inactiveWarning() === $warning, 'missing public key not reported');
        proofage_configure([Settings::PUBLIC_KEY => PROOFAGE_FAKE_PK]);
        check(proofage_form()->inactiveWarning() === null, 'warning with both keys');

        proofage_configure([Settings::PROTECT_PRODUCTS => [], Settings::SECRET_KEY => '']);
        check(proofage_form()->inactiveWarning() === null, 'warning with no rules');

        $fr = Context::getContext()->getTranslatorFromLocale('fr-FR')->trans($source, [], 'Modules.Proofage.Admin', 'fr-FR');
        check($fr !== $source, 'warning not translated to French');
    },
];

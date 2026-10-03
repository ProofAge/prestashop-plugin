<?php

use ProofAge\PrestaShop\Infrastructure\ServiceFactory;
use ProofAge\PrestaShop\Infrastructure\Settings;

return [
    'session rejects GET and bad token' => function (): void {
        check(proofage_http('GET', '/module/proofage/session')['status'] === 405, 'GET allowed');
        check(proofage_http('POST', '/module/proofage/session', ['body' => ['token' => 'nope']])['status'] === 403, 'bad token accepted');
    },
    'session creates a verification through the API' => function (): void {
        [, $data] = proofage_start_session('/3-clothes');
        check(str_starts_with($data['url'], 'http://127.0.0.1:8765/hosted/'), 'unexpected url ' . $data['url']);
        check($data['launch_mode'] === 'modal', 'launch mode');
        $record = ServiceFactory::forShop(1)->verifications()->findByVerificationId($data['verification_id']);
        check($record !== null && str_starts_with($record->externalId, 'ps-1-g'), 'record missing');
        check($record->originUrl === proofage_base_url() . '/3-clothes', 'origin ' . $record->originUrl);
    },
    'second session call reuses verification' => function (): void {
        [$jar, $first] = proofage_start_session();
        $second = json_decode(proofage_http('POST', '/module/proofage/session', [
            'jar' => $jar, 'ajax' => true, 'body' => ['token' => proofage_static_token($jar), 'back' => proofage_base_url() . '/'],
        ])['body'], true);
        check($second['verification_id'] === $first['verification_id'], 'created a second verification');
    },
    'status follows the API and unlocks the page' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);
        [$jar, $data] = proofage_start_session();

        check(proofage_status($jar)['state'] === 'pending', 'expected pending');
        proofage_fake_api_set($data['verification_id'], 'approved', 'wallet');
        sleep(6); // status sync is throttled to once per 5 s
        $status = proofage_status($jar);
        check($status['state'] === 'approved', 'expected approved, got ' . json_encode($status));
        check(proofage_http('GET', proofage_product_url($id), ['jar' => $jar])['status'] === 200, 'still gated after approval');
        $record = ServiceFactory::forShop(1)->verifications()->findByVerificationId($data['verification_id']);
        check($record->method === 'wallet', 'method not stored');
    },
    'declined status is reported and keeps the gate' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);
        [$jar, $data] = proofage_start_session();
        proofage_fake_api_set($data['verification_id'], 'declined');

        check(proofage_status($jar)['state'] === 'declined', 'expected declined');
        check(proofage_http('GET', proofage_product_url($id), ['jar' => $jar])['status'] === 302, 'declined visitor got through');
    },
    'return page polls when the cookie matches' => function (): void {
        [$jar, $data] = proofage_start_session();
        $record = ServiceFactory::forShop(1)->verifications()->findByVerificationId($data['verification_id']);
        $page = proofage_http('GET', '/module/proofage/return?ref=' . $record->returnRef, ['jar' => $jar]);
        check($page['status'] === 200 && str_contains($page['body'], '"returnMode":true'), 'return page not in polling mode');
    },
    'return without cookie shows cookie message' => function (): void {
        [, $data] = proofage_start_session();
        $record = ServiceFactory::forShop(1)->verifications()->findByVerificationId($data['verification_id']);
        $page = proofage_http('GET', '/module/proofage/return?ref=' . $record->returnRef);
        // The message is shown in the shop's default language (English on a shop without a module translation).
        $expected = Context::getContext()->getTranslatorFromLocale(Language::getLanguage((int) Configuration::get('PS_LANG_DEFAULT'))['locale'])
            ->trans('Please make sure cookies are enabled, then start the verification again from the same browser.', [], 'Modules.Proofage.Shop');
        check(str_contains($page['body'], htmlspecialchars($expected, ENT_NOQUOTES)), 'cookie message missing');
        check(str_contains($page['body'], '"returnMode":false'), 'return page polls without cookie');
    },
    'too many new verifications from one IP are refused' => function (): void {
        for ($i = 0; $i < 20; ++$i) {
            proofage_start_session();
        }
        $jar = proofage_new_jar();
        $response = proofage_http('POST', '/module/proofage/session', [
            'jar' => $jar, 'ajax' => true, 'body' => ['token' => proofage_static_token($jar), 'back' => '/'],
        ]);
        check($response['status'] === 429, 'expected 429, got ' . $response['status']);
    },
];

<?php

use ProofAge\PrestaShop\Infrastructure\ServiceFactory;
use ProofAge\PrestaShop\Infrastructure\Settings;

return [
    'signed approved webhook unlocks the browser' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);
        [$jar, $data] = proofage_start_session();

        $response = proofage_send_webhook(['verification_id' => $data['verification_id'], 'status' => 'approved', 'method' => 'wallet'], 'd-' . uniqid());
        check($response['status'] === 200, 'webhook HTTP ' . $response['status'] . ' ' . $response['body']);
        check(proofage_http('GET', proofage_product_url($id), ['jar' => $jar])['status'] === 200, 'still gated');
    },
    'duplicate delivery is acknowledged once' => function (): void {
        [, $data] = proofage_start_session();
        $delivery = 'd-' . uniqid();
        proofage_send_webhook(['verification_id' => $data['verification_id'], 'status' => 'review'], $delivery);
        $second = proofage_send_webhook(['verification_id' => $data['verification_id'], 'status' => 'review'], $delivery);
        check(str_contains($second['body'], 'Duplicate'), 'duplicate not detected: ' . $second['body']);
    },
    'bad signature is rejected with 401' => function (): void {
        [, $data] = proofage_start_session();
        $response = proofage_send_webhook(['verification_id' => $data['verification_id'], 'status' => 'approved'], 'd-' . uniqid(), null, 'sk_wrong');
        check($response['status'] === 401, 'expected 401, got ' . $response['status']);
        $record = ServiceFactory::forShop(1)->verifications()->findByVerificationId($data['verification_id']);
        check($record->status !== 'approved', 'forged webhook applied');
    },
    'GET is refused' => function (): void {
        check(proofage_http('GET', '/module/proofage/webhook')['status'] === 405, 'GET allowed');
    },
    'webhook works in maintenance mode' => function (): void {
        [, $data] = proofage_start_session();
        Configuration::updateGlobalValue('PS_SHOP_ENABLE', 0);
        Configuration::updateGlobalValue('PS_MAINTENANCE_IP', '');
        $response = proofage_send_webhook(['verification_id' => $data['verification_id'], 'status' => 'approved'], 'd-' . uniqid());
        Configuration::updateGlobalValue('PS_SHOP_ENABLE', 1);
        check($response['status'] === 200 && str_contains($response['body'], 'OK'), 'maintenance blocked webhook: ' . $response['status']);
    },
];

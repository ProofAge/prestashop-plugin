<?php

use ProofAge\PrestaShop\Infrastructure\ServiceFactory;
use ProofAge\PrestaShop\Infrastructure\Settings;
use ProofAge\PrestaShop\Verification\CustomerVerification;

function proofage_ajax_add(string $jar, int $idProduct, int $idProductAttribute = 0): array
{
    return proofage_http('POST', proofage_page_path('cart'), [
        'jar' => $jar,
        'ajax' => true,
        'body' => [
            'token' => proofage_static_token($jar),
            'id_product' => (string) $idProduct,
            'id_product_attribute' => (string) $idProductAttribute,
            'qty' => '1',
            'add' => '1',
            'action' => 'update',
            'ajax' => '1',
        ],
    ]);
}

return [
    'ajax add of protected product is blocked' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);
        $jar = proofage_new_jar();

        $data = json_decode(proofage_ajax_add($jar, $id)['body'], true);
        check(!empty($data['hasError']) && !empty($data['proofage']['required']), 'not blocked: ' . json_encode($data));
        check(str_contains($data['proofage']['gateUrl'], 'module/proofage/gate'), 'gate url missing');
    },
    'ajax add of protected combination is blocked' => function (): void {
        $id = proofage_demo_products()['combinations'];
        $idAttribute = (int) Product::getDefaultAttribute($id);
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);

        $data = json_decode(proofage_ajax_add(proofage_new_jar(), $id, $idAttribute)['body'], true);
        check(!empty($data['proofage']['required']), 'combination add not blocked');
    },
    'non-ajax add redirects to the gate' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);
        $jar = proofage_new_jar();

        $response = proofage_http('POST', proofage_page_path('cart'), ['jar' => $jar, 'body' => ['token' => proofage_static_token($jar), 'id_product' => (string) $id, 'qty' => '1', 'add' => '1']]);
        check($response['status'] === 302 && str_contains(proofage_location($response), 'module/proofage/gate'), 'no redirect to gate');
    },
    'unprotected product can be added' => function (): void {
        $products = proofage_demo_products();
        proofage_configure([Settings::PROTECT_PRODUCTS => [$products['combinations']]]);

        $data = json_decode(proofage_ajax_add(proofage_new_jar(), $products['simple'])['body'], true);
        check(!empty($data['success']), 'unprotected add failed: ' . json_encode($data));
    },
    'verified customer can add protected product' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);
        $email = proofage_set_customer_password(2, 'Proofage123!');
        ServiceFactory::forShop(1)->customers()->save(new CustomerVerification(2, 1, 'ver-c', time(), null));
        $jar = proofage_new_jar();
        proofage_login($jar, $email, 'Proofage123!');

        $data = json_decode(proofage_ajax_add($jar, $id)['body'], true);
        check(!empty($data['success']), 'verified add failed: ' . json_encode($data));
    },
    'cart with a protected product always shows the full gate' => function (): void {
        $id = proofage_demo_products()['simple'];
        $jar = proofage_new_jar();
        proofage_ajax_add($jar, $id); // added while unprotected
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id], Settings::DISPLAY_MODE => 'overlay']);

        $cart = proofage_http('GET', proofage_page_path('cart', ['action' => 'show']), ['jar' => $jar]);
        check($cart['status'] === 302 && str_contains(proofage_location($cart), 'module/proofage/gate'), 'cart not gated');
        check(proofage_http('GET', proofage_page_path('order'), ['jar' => $jar])['status'] === 302, 'checkout not gated');
    },
    'login promotes an approved guest' => function (): void {
        [$jar, $data] = proofage_start_session();
        proofage_send_webhook(['verification_id' => $data['verification_id'], 'status' => 'approved'], 'd-' . uniqid());
        $email = proofage_set_customer_password(2, 'Proofage123!');
        ServiceFactory::forShop(1)->customers()->delete(2, 1);

        proofage_login($jar, $email, 'Proofage123!');
        $row = ServiceFactory::forShop(1)->customers()->find(2, 1);
        check($row !== null && $row->verificationId === $data['verification_id'], 'guest approval not promoted');
    },
    'add combined with delete cannot bypass the guard' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);
        $jar = proofage_new_jar();

        $response = proofage_http('POST', proofage_page_path('cart'), ['jar' => $jar, 'ajax' => true, 'body' => [
            'token' => proofage_static_token($jar), 'id_product' => (string) $id, 'id_product_attribute' => '0',
            'qty' => '1', 'add' => '1', 'delete' => '1', 'action' => 'update', 'ajax' => '1',
        ]]);
        $data = json_decode($response['body'], true);
        check(!empty($data['proofage']['required']), 'add+delete bypassed the guard: ' . $response['body']);
    },
];

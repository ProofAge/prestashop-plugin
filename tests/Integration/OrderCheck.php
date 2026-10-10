<?php

use ProofAge\PrestaShop\Infrastructure\ServiceFactory;
use ProofAge\PrestaShop\Infrastructure\Settings;
use ProofAge\PrestaShop\Verification\CustomerVerification;
use ProofAge\PrestaShop\Verification\VerificationRecord;

check(!_PS_MODE_DEV_, 'run integration checks with _PS_MODE_DEV_ = false (production hook behaviour)');

function proofage_expect_order_blocked(Cart $cart): void
{
    $ordersBefore = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'orders`');
    try {
        proofage_validate_order($cart);
        throw new RuntimeException('order was created');
    } catch (ProofAge\PrestaShop\Infrastructure\OrderBlockedError $e) {
        // expected
    }
    check((int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'orders`') === $ordersBefore, 'order row written');
}

final class ProofageThrowingCookie extends Cookie
{
    /** @var bool */
    public $armed = false;

    public function __get($key)
    {
        if ($this->armed) {
            throw new RuntimeException('simulated internal failure');
        }

        return parent::__get($key);
    }

    public function __isset($key)
    {
        if ($this->armed) {
            throw new RuntimeException('simulated internal failure');
        }

        return parent::__isset($key);
    }
}

return [
    'order blocked when cart requires verification' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);
        ServiceFactory::forShop(1)->customers()->delete(2, 1);

        proofage_expect_order_blocked(proofage_build_cart(2, $id));
    },
    'order blocked when product protected after being added' => function (): void {
        $id = proofage_demo_products()['simple'];
        ServiceFactory::forShop(1)->customers()->delete(2, 1);
        $cart = proofage_build_cart(2, $id);
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);

        proofage_expect_order_blocked($cart);
    },
    'verified customer order succeeds with approved snapshot' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);
        ServiceFactory::forShop(1)->customers()->save(new CustomerVerification(2, 1, 'ver-c', time(), null));

        $idOrder = proofage_validate_order(proofage_build_cart(2, $id));
        $snapshot = ServiceFactory::forShop(1)->orders()->find($idOrder);
        check($snapshot !== null && $snapshot->required && $snapshot->status === 'approved' && $snapshot->verificationId === 'ver-c', 'bad snapshot');
    },
    'cookieless payment callback accepted through cart binding' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);
        ServiceFactory::forShop(1)->customers()->delete(2, 1);
        $cart = proofage_build_cart(2, $id);

        $r = new VerificationRecord();
        $r->verificationId = 'ver-bound-' . uniqid();
        $r->idShop = 1;
        $r->externalId = 'ps-1-gbound';
        $r->sessionTokenHash = hash('sha256', 'x');
        $r->ipHash = 'x';
        $r->returnRef = bin2hex(random_bytes(16));
        $r->verificationUrl = 'https://idv.proofage.net/v/x';
        $r->status = 'approved';
        $r->decidedAt = time();
        $r->expiresAt = time() + 3600;
        $r->idCart = (int) $cart->id;
        $r->createdAt = $r->updatedAt = time();
        ServiceFactory::forShop(1)->verifications()->insert($r);

        $idOrder = proofage_validate_order($cart);
        check($idOrder > 0, 'order not created');
        check(ServiceFactory::forShop(1)->orders()->find($idOrder)->verificationId === $r->verificationId, 'snapshot not linked to bound verification');
    },
    'order without protected products gets not_required snapshot' => function (): void {
        $products = proofage_demo_products();
        proofage_configure([Settings::PROTECT_PRODUCTS => [$products['combinations']]]);
        ServiceFactory::forShop(1)->customers()->delete(2, 1);

        $idOrder = proofage_validate_order(proofage_build_cart(2, $products['simple']));
        $snapshot = ServiceFactory::forShop(1)->orders()->find($idOrder);
        check($snapshot !== null && !$snapshot->required && $snapshot->status === 'not_required', 'bad snapshot');
    },
    'revocation webhook marks the order snapshot' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);
        [, $data] = proofage_start_session();
        $record = ServiceFactory::forShop(1)->verifications()->findByVerificationId($data['verification_id']);
        $record->idCustomer = 2;
        ServiceFactory::forShop(1)->verifications()->update($record);
        proofage_send_webhook(['verification_id' => $data['verification_id'], 'status' => 'approved'], 'd-' . uniqid());

        $idOrder = proofage_validate_order(proofage_build_cart(2, $id));
        proofage_send_webhook(['verification_id' => $data['verification_id'], 'status' => 'declined', 'reason' => 'revoked'], 'd-' . uniqid());

        check(ServiceFactory::forShop(1)->orders()->find($idOrder)->status === 'revoked', 'snapshot not revoked');
        check(ServiceFactory::forShop(1)->customers()->find(2, 1) === null, 'customer approval kept');
    },
    'order guard fails closed on an internal error' => function (): void {
        $id = proofage_demo_products()['simple'];
        proofage_configure([Settings::PROTECT_PRODUCTS => [$id]]);
        $cart = proofage_build_cart(2, $id);
        $order = new Order();
        $order->id_cart = (int) $cart->id;
        $order->id_customer = 2;
        $order->id_shop = 1;

        // A cookie that throws makes the approval lookup fail deterministically.
        $context = clone Context::getContext();
        $context->cookie = new ProofageThrowingCookie('proofage_test');
        $context->cookie->armed = true;
        $guard = new ProofAge\PrestaShop\Infrastructure\OrderGuard($context, ServiceFactory::forShop(1));
        try {
            $guard->assertOrderAllowed($order);
            throw new RuntimeException('internal failure let the order through');
        } catch (ProofAge\PrestaShop\Infrastructure\OrderBlockedError $e) {
            check($e->getPrevious() instanceof RuntimeException, 'original failure not chained');
        }
    },
];

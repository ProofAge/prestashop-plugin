<?php

use ProofAge\PrestaShop\Infrastructure\ServiceFactory;
use ProofAge\PrestaShop\Verification\CustomerVerification;
use ProofAge\PrestaShop\Verification\OrderSnapshot;
use ProofAge\PrestaShop\Verification\VerificationRecord;

return [
    'order block renders the snapshot' => function (): void {
        ServiceFactory::forShop(1)->orders()->save(new OrderSnapshot(999001, true, 'ver-x', 'approved', 'wallet', time()));
        $html = Module::getInstanceByName('proofage')->hookDisplayAdminOrderMain(['id_order' => 999001]);
        check(str_contains($html, 'ver-x') && str_contains($html, 'badge-success'), 'snapshot not rendered');
    },
    'customer block renders and links to reset' => function (): void {
        // Link::getAdminLink() returns '' outside the back office; the hook always runs there.
        if (!defined('_PS_ADMIN_DIR_')) {
            define('_PS_ADMIN_DIR_', proofage_admin_dir());
        }
        ServiceFactory::forShop(1)->customers()->save(new CustomerVerification(2, 1, 'ver-c', time(), null));
        $html = Module::getInstanceByName('proofage')->hookDisplayAdminCustomers(['id_customer' => 2]);
        check(str_contains($html, 'ver-c') && str_contains($html, 'proofage_action=reset_customer'), 'customer block wrong');
    },
    'GDPR export and delete' => function (): void {
        $insert = function (string $verificationId, int $idCustomer): void {
            $r = new VerificationRecord();
            $r->verificationId = $verificationId;
            $r->idShop = 1;
            $r->externalId = 'ps-1-c' . $idCustomer;
            $r->idCustomer = $idCustomer;
            $r->sessionTokenHash = hash('sha256', 'x');
            $r->ipHash = hash('sha256', 'ip-' . $idCustomer);
            $r->returnRef = bin2hex(random_bytes(16));
            $r->verificationUrl = 'https://idv.proofage.xyz/v/x';
            $r->createdAt = $r->updatedAt = time();
            ServiceFactory::forShop(1)->verifications()->insert($r);
        };
        $insert('ver-gdpr', 2);
        $insert('ver-other', 3);
        ServiceFactory::forShop(1)->customers()->save(new CustomerVerification(2, 1, 'ver-gdpr', time(), null));
        ServiceFactory::forShop(1)->customers()->save(new CustomerVerification(3, 1, 'ver-other', time(), null));
        $module = Module::getInstanceByName('proofage');

        $export = json_decode($module->hookActionExportGDPRData(['id' => 2]), true);
        check(count($export) === 1 && $export[0]['verification_id'] === 'ver-gdpr', 'export wrong');

        check($module->hookActionDeleteGDPRCustomer(['id' => 2]) === json_encode(true), 'delete hook must return json_encode(true)');
        check(ServiceFactory::forShop(1)->customers()->find(2, 1) === null, 'customer row kept');
        $deleted = ServiceFactory::forShop(1)->verifications()->findByVerificationId('ver-gdpr');
        check($deleted !== null, 'audit row removed');
        check($deleted->idCustomer === null, 'not anonymised');
        check($deleted->externalId === 'deleted-' . $deleted->id && !str_contains($deleted->externalId, 'c2'), 'external_id still identifies the customer: ' . $deleted->externalId);
        check($deleted->ipHash === '', 'ip_hash kept');

        $other = ServiceFactory::forShop(1)->verifications()->findByVerificationId('ver-other');
        check($other->idCustomer === 3 && $other->externalId === 'ps-1-c3' && $other->ipHash === hash('sha256', 'ip-3'), 'other customer row changed');
        check(ServiceFactory::forShop(1)->customers()->find(3, 1) !== null, 'other customer verification removed');
    },
    'back-office reset sticks: no cookie access, no re-promotion after login' => function (): void {
        $now = time();
        $r = new VerificationRecord();
        $r->verificationId = 'ver-reset';
        $r->idShop = 1;
        $r->externalId = 'ps-1-c2';
        $r->idCustomer = 2;
        $r->idCart = 555;
        $r->sessionTokenHash = hash('sha256', 'tok-reset');
        $r->ipHash = 'x';
        $r->returnRef = bin2hex(random_bytes(16));
        $r->verificationUrl = 'https://idv.proofage.xyz/v/x';
        $r->status = 'approved';
        $r->decidedAt = $now - 60;
        $r->expiresAt = $now + 86400;
        $r->createdAt = $r->updatedAt = $now - 120;
        $factory = ServiceFactory::forShop(1);
        $factory->verifications()->insert($r);
        $factory->customers()->save(new CustomerVerification(2, 1, 'ver-reset', $now - 60, null));
        check($factory->visitorState()->isVerified(0, 1, 'ver-reset', 'tok-reset'), 'precondition: cookie verified');

        $factory->visitorState()->resetCustomer(2, 1);

        check($factory->customers()->find(2, 1) === null, 'customer row kept');
        check(!$factory->visitorState()->isVerified(0, 1, 'ver-reset', 'tok-reset'), 'still verified through the browser cookie');
        check(!$factory->visitorState()->isVerified(2, 1, 'ver-reset', 'tok-reset'), 'still verified as the customer');
        check($factory->visitorState()->resolveApproval(0, 1, 555, null, null) === null, 'still verified through the bound cart');
        check(!$factory->visitorState()->promoteGuest(2, 1, 'ver-reset', 'tok-reset', $factory->settings()->ttlPolicy()), 'login promoted the reset verification');
        check($factory->customers()->find(2, 1) === null, 'login recreated the customer row');
        $kept = $factory->verifications()->findByVerificationId('ver-reset');
        check($kept !== null && $kept->status === 'approved' && $kept->idCart === null, 'audit row should stay approved with the cart unbound');
    },
];

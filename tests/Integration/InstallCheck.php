<?php

use ProofAge\PrestaShop\Infrastructure\Installer;
use ProofAge\PrestaShop\Infrastructure\ServiceFactory;
use ProofAge\PrestaShop\Infrastructure\Settings;
use ProofAge\PrestaShop\Verification\CustomerVerification;
use ProofAge\PrestaShop\Verification\VerificationRecord;

return [
    'module ships a 32x32 logo.png' => function (): void {
        $logo = _PS_MODULE_DIR_ . 'proofage/logo.png';
        check(is_file($logo), 'logo.png missing');
        $size = getimagesize($logo);
        check($size !== false && $size[0] === 32 && $size[1] === 32 && $size['mime'] === 'image/png', 'logo.png must be a 32x32 PNG');
    },
    'module is installed with tables, hooks and config' => function (): void {
        $module = Module::getInstanceByName('proofage');
        check($module instanceof Module && Module::isInstalled('proofage'), 'module not installed');
        foreach (Installer::TABLES as $table) {
            check((bool) Db::getInstance()->executeS('SHOW TABLES LIKE "' . _DB_PREFIX_ . $table . '"'), "missing table $table");
        }
        foreach (Installer::HOOKS as $hook) {
            check($module->isRegisteredInHook($hook), "hook $hook not registered");
        }
        check(Configuration::get(Settings::DISPLAY_MODE) !== false, 'defaults missing');
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        check(Configuration::get(Settings::GATE_BUTTON, $idLang) !== '', 'gate texts missing');
    },
    'verification repository round-trips a record' => function (): void {
        $repo = ServiceFactory::forShop(1)->verifications();
        $r = new VerificationRecord();
        $r->verificationId = 'ver-int-1';
        $r->idShop = 1;
        $r->externalId = 'ps-1-gtest';
        $r->sessionTokenHash = hash('sha256', 't');
        $r->ipHash = hash('sha256', 'ip');
        $r->returnRef = bin2hex(random_bytes(16));
        $r->verificationUrl = 'https://idv.proofage.net/v/x';
        $r->originUrl = '/';
        $r->createdAt = $r->updatedAt = time();
        $r->expiresAt = time() + 3600;
        $r->status = 'approved';
        $r->idCart = 99;
        $repo->insert($r);

        $loaded = $repo->findByVerificationId('ver-int-1');
        check($loaded !== null && $loaded->id > 0, 'not found');
        check($loaded->expiresAt === $r->expiresAt, 'timestamp drift: ' . $loaded->expiresAt . ' vs ' . $r->expiresAt);
        check($repo->findByReturnRef($r->returnRef) !== null, 'return ref lookup failed');
        check($repo->findApprovedForCart(99, time()) !== null, 'cart lookup failed');
        check($repo->countCreatedSince('external_id', 'ps-1-gtest', time() - 60) === 1, 'count failed');
    },
    'verification repository keeps empty strings and nulls distinct' => function (): void {
        $repo = ServiceFactory::forShop(1)->verifications();
        $r = new VerificationRecord();
        $r->verificationId = 'ver-int-nulls';
        $r->idShop = 1;
        $r->externalId = 'ps-1-gnulls';
        $r->idCustomer = 7;
        $r->returnRef = bin2hex(random_bytes(16));
        $r->originUrl = '';
        $r->verificationUrl = '';
        $r->sessionTokenHash = '';
        $r->ipHash = '';
        $r->status = 'created';
        $r->createdAt = $r->updatedAt = time();
        $repo->insert($r);

        $loaded = $repo->findByVerificationId('ver-int-nulls');
        check($loaded !== null, 'record with empty strings not inserted');
        check($loaded->originUrl === '' && $loaded->verificationUrl === '', 'empty strings not preserved');
        check($loaded->method === null && $loaded->reason === null && $loaded->idCart === null, 'nulls not preserved');
        check($loaded->lastSyncedAt === null && $loaded->decidedAt === null && $loaded->expiresAt === null, 'null dates not preserved');

        $loaded->status = 'approved';
        $loaded->method = 'estimation';
        $loaded->idCustomer = null;
        $loaded->updatedAt = time();
        $repo->update($loaded);
        $again = $repo->findByVerificationId('ver-int-nulls');
        check($again->status === 'approved' && $again->method === 'estimation' && $again->idCustomer === null && $again->originUrl === '', 'update round trip failed');

        $again->method = null;
        $repo->update($again);
        check($repo->findByVerificationId('ver-int-nulls')->method === null, 'update to null failed');

        $other = clone $r;
        $other->verificationId = 'ver-int-anon';
        $other->returnRef = bin2hex(random_bytes(16));
        $other->idCustomer = 8;
        $repo->insert($other);
        $repo->anonymiseCustomer(8);
        $anon = $repo->findByVerificationId('ver-int-anon');
        check($anon !== null && $anon->idCustomer === null, 'anonymiseCustomer did not set NULL');
    },
    'customer repository upserts' => function (): void {
        $repo = ServiceFactory::forShop(1)->customers();
        $repo->save(new CustomerVerification(2, 1, 'ver-a', time(), null));
        $repo->save(new CustomerVerification(2, 1, 'ver-b', time(), time() + 60));
        $row = $repo->find(2, 1);
        check($row !== null && $row->verificationId === 'ver-b' && $row->expiresAt !== null, 'upsert failed');
    },
    'uninstall then reinstall is clean' => function (): void {
        $module = Module::getInstanceByName('proofage');
        check($module->uninstall(), 'uninstall failed');
        check(!Db::getInstance()->executeS('SHOW TABLES LIKE "' . _DB_PREFIX_ . 'proofage_verification"'), 'table survived uninstall');
        check(Configuration::get(Settings::DISPLAY_MODE) === false, 'config survived uninstall');
        check($module->install(), 'reinstall failed');
    },
    'install clears the media cache so CCC bundles pick up module assets' => function (): void {
        $before = (int) Configuration::get('PS_CCCJS_VERSION');
        $beforeCss = (int) Configuration::get('PS_CCCCSS_VERSION');
        check((new Installer())->install(Module::getInstanceByName('proofage')), 'install failed');
        check((int) Configuration::get('PS_CCCJS_VERSION') === $before + 1, 'JS bundle version not bumped');
        check((int) Configuration::get('PS_CCCCSS_VERSION') === $beforeCss + 1, 'CSS bundle version not bumped');
    },
];

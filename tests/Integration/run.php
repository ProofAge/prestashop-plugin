<?php
// Boots the PrestaShop install this module lives in and runs every *Check.php.
// Usage: php tests/Integration/run.php [filter]
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$_SERVER['REQUEST_METHOD'] = 'GET';
$baseUrl = getenv('PS_BASE_URL') ?: 'https://proofage-prestashop.test';
$_SERVER['HTTP_HOST'] = parse_url($baseUrl, PHP_URL_HOST);
if (parse_url($baseUrl, PHP_URL_SCHEME) === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require dirname(__DIR__, 4) . '/config/config.inc.php';
// PrestaShop's module (un)installation needs the Symfony container, which plain scripts do not boot.
global $kernel;
// PrestaShop 9 ships FrontKernel; 8.x has the single AppKernel.
$kernelClass = class_exists('FrontKernel') ? 'FrontKernel' : 'AppKernel';
$kernel = new $kernelClass(_PS_ENV_, _PS_MODE_DEV_);
$kernel->boot();

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/Assert.php';
require __DIR__ . '/Fixtures.php';
require __DIR__ . '/ShopStateGuard.php';

// The checks overwrite the shop's ProofAge settings and module data; put the real ones back afterwards,
// also when a check throws or the script dies.
proofage_guard_snapshot();
$restoreShopState = function (): void {
    // After an out-of-memory fatal the restore needs room of its own.
    ini_set('memory_limit', '-1');
    if (proofage_guard_restore()) {
        echo "Shop configuration and module data restored.\n";
    }
};
register_shutdown_function($restoreShopState);

proofage_fake_api_start();

$filter = $argv[1] ?? '';
$failed = 0;
$ran = 0;
$files = glob(__DIR__ . '/*Check.php');
// AdminHooksCheck defines _PS_ADMIN_DIR_ (process-wide, and OrderGuard bypasses in the back office), so it runs last.
usort($files, function (string $a, string $b): int {
    return (basename($a) === 'AdminHooksCheck.php') <=> (basename($b) === 'AdminHooksCheck.php') ?: strcmp($a, $b);
});
try {
    foreach ($files as $file) {
        if ($filter !== '' && !str_contains(basename($file), $filter)) {
            continue;
        }
        $checks = require $file;
        foreach ($checks as $name => $check) {
            ++$ran;
            try {
                proofage_reset();
                $check();
                echo "PASS {$name}\n";
            } catch (Throwable $e) {
                ++$failed;
                echo "FAIL {$name}: {$e->getMessage()} ({$e->getFile()}:{$e->getLine()})\n";
            }
        }
    }
} finally {
    $restoreShopState();
}
echo "\n{$ran} checks, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);

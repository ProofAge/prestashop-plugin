<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

// Lists source strings that have no fr/es/it translation in translations/source/catalog.php.
$root = dirname(__DIR__);
$catalog = require $root . '/translations/source/catalog.php';
$string = "'((?:[^'\\\\]|\\\\.)*)'";
$patterns = [
    // ->trans('Text', [...], 'Modules.Proofage.X')
    ['/->trans\(\s*' . $string . '\s*,\s*\[[^\]]*\]\s*,\s*\'(Modules\.Proofage\.(?:Shop|Admin))\'/', null],
    // Gatekeeper::trans('Text') — storefront
    ['/\$this->trans\(\s*' . $string . '\s*\)/', 'Modules.Proofage.Shop'],
    // SettingsForm::t('Text') — back office
    ['/->t\(\s*' . $string . '\s*\)/', 'Modules.Proofage.Admin'],
    // OrderGuard::message(): $text = 'Text'; translated through trans($text, [], 'Modules.Proofage.Shop')
    ['/\$text = ' . $string . ';/', 'Modules.Proofage.Shop'],
    // Smarty {l s='Text' d='Modules.Proofage.X'}
    ['/\{l s=' . $string . ' d=\'(Modules\.Proofage\.(?:Shop|Admin))\'/', null],
];
$files = array_merge(
    [$root . '/proofage.php'],
    glob($root . '/controllers/*/*.php'),
    glob($root . '/src/*/*.php'),
    glob($root . '/views/templates/*/*.tpl')
);
$found = [];
foreach ($files as $file) {
    $code = file_get_contents($file);
    foreach ($patterns as [$regex, $domain]) {
        preg_match_all($regex, $code, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $text = stripcslashes($m[1]);
            $found[$domain ?? $m[2]][$text] = true;
        }
    }
}
$problems = 0;
foreach ($found as $domain => $texts) {
    foreach (array_keys($texts) as $text) {
        foreach (['fr', 'es', 'it'] as $iso) {
            if (empty($catalog[$domain][$text][$iso])) {
                echo "MISSING [$domain] [$iso] $text\n";
                ++$problems;
            }
        }
    }
}
foreach ($catalog as $domain => $texts) {
    foreach (array_keys($texts) as $text) {
        if (!isset($found[$domain][$text])) {
            echo "UNUSED  [$domain] $text\n";
            ++$problems;
        }
    }
}
echo $problems === 0 ? "All strings translated.\n" : "$problems problem(s).\n";
exit($problems === 0 ? 0 : 1);

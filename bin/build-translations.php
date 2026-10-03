<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
// Writes translations/<locale>/<Domain>.<locale>.xlf from translations/source/catalog.php.
$root = dirname(__DIR__);
$catalog = require $root . '/translations/source/catalog.php';
$locales = ['fr' => 'fr-FR', 'es' => 'es-ES', 'it' => 'it-IT'];

foreach ($locales as $iso => $locale) {
    $dir = $root . '/translations/' . $locale;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    foreach ($catalog as $domain => $messages) {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $xliff = $doc->createElementNS('urn:oasis:names:tc:xliff:document:1.2', 'xliff');
        $xliff->setAttribute('version', '1.2');
        $doc->appendChild($xliff);
        $file = $doc->createElement('file');
        $file->setAttribute('original', 'proofage');
        $file->setAttribute('source-language', 'en');
        $file->setAttribute('target-language', $iso);
        $file->setAttribute('datatype', 'plaintext');
        $xliff->appendChild($file);
        $body = $doc->createElement('body');
        $file->appendChild($body);
        foreach ($messages as $source => $targets) {
            $unit = $doc->createElement('trans-unit');
            $unit->setAttribute('id', md5($source));
            $unit->setAttribute('approved', 'yes');
            $sourceNode = $doc->createElement('source');
            $sourceNode->appendChild($doc->createTextNode($source));
            $targetNode = $doc->createElement('target');
            $targetNode->setAttribute('state', 'final');
            $targetNode->appendChild($doc->createTextNode($targets[$iso]));
            $unit->appendChild($sourceNode);
            $unit->appendChild($targetNode);
            $body->appendChild($unit);
        }
        $path = $dir . '/' . str_replace('.', '', $domain) . '.' . $locale . '.xlf';
        $doc->save($path);
        echo "wrote $path\n";
    }
    copy($root . '/index.php', $dir . '/index.php');
}

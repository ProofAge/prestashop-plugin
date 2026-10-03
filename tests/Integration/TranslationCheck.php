<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

return [
    'module strings are translated in fr, es and it' => function (): void {
        $cases = [
            ['fr-FR', 'Too many attempts. Please try again later.', 'Modules.Proofage.Shop', 'Trop de tentatives. Veuillez réessayer plus tard.'],
            ['es-ES', 'Continue shopping', 'Modules.Proofage.Shop', 'Seguir comprando'],
            ['it-IT', 'Rules', 'Modules.Proofage.Admin', 'Regole'],
            // Wording polish: agreement with "Expired" (la vérification), consistent terms per language.
            ['fr-FR', 'Verified', 'Modules.Proofage.Admin', 'Vérifiée'],
            ['es-ES', 'Verified', 'Modules.Proofage.Admin', 'Verificada'],
            ['it-IT', 'Verified', 'Modules.Proofage.Admin', 'Verificata'],
            ['it-IT', 'Test connection', 'Modules.Proofage.Admin', 'Prova la connessione'],
            ['es-ES', 'Guest', 'Modules.Proofage.Admin', 'Visitante'],
            ['fr-FR', 'Checking your verification…', 'Modules.Proofage.Shop', 'Vérification en cours…'],
            ['it-IT', 'We need you to retake a photo or document. Click the button to continue.', 'Modules.Proofage.Shop', 'Dobbiamo chiederti di rifare una foto o un documento. Fai clic sul pulsante per continuare.'],
            ['es-ES', 'Age verification is required to order the products in your cart.', 'Modules.Proofage.Shop', 'Debes verificar tu edad para realizar el pedido de los productos de tu carrito.'],
            ['fr-FR', 'The check type, minimum age and wallet option are set in your ProofAge workspace, not here.', 'Modules.Proofage.Admin', 'Le type de vérification, l\'âge minimum et l\'option portefeuille d\'identité numérique se configurent dans votre espace de travail ProofAge, pas ici.'],
            ['es-ES', 'The check type, minimum age and wallet option are set in your ProofAge workspace, not here.', 'Modules.Proofage.Admin', 'El tipo de verificación, la edad mínima y la opción de cartera de identidad digital se configuran en tu espacio de trabajo de ProofAge, no aquí.'],
            ['it-IT', 'The check type, minimum age and wallet option are set in your ProofAge workspace, not here.', 'Modules.Proofage.Admin', 'Il tipo di verifica, l\'età minima e l\'opzione portafoglio di identità digitale si impostano nel tuo workspace ProofAge, non qui.'],
        ];
        foreach ($cases as [$locale, $source, $domain, $expected]) {
            // The shared translator only holds the catalogue of the context language, so build one per locale.
            $translator = Context::getContext()->getTranslatorFromLocale($locale);
            $actual = $translator->trans($source, [], $domain, $locale);
            check($actual === $expected, "$locale: expected '$expected', got '$actual'");
        }
    },
    'every catalog entry is served by the translator' => function (): void {
        $catalog = require dirname(__DIR__, 2) . '/translations/source/catalog.php';
        foreach (['fr' => 'fr-FR', 'es' => 'es-ES', 'it' => 'it-IT'] as $iso => $locale) {
            $translator = Context::getContext()->getTranslatorFromLocale($locale);
            foreach ($catalog as $domain => $messages) {
                foreach ($messages as $source => $targets) {
                    $actual = $translator->trans($source, [], $domain, $locale);
                    check($actual === $targets[$iso], "$locale [$domain] '$source': expected '{$targets[$iso]}', got '$actual'");
                }
            }
        }
    },
];

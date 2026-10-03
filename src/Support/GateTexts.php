<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Support;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Default gate texts written into the configuration at install time (merchants can edit them).
 */
final class GateTexts
{
    public const DEFAULTS = [
        'en' => [
            'title' => 'Age verification required',
            'description' => 'This content is age-restricted. Please confirm your age with a quick verification to continue.',
            'button' => 'Verify my age',
        ],
        'fr' => [
            'title' => 'Vérification de l\'âge requise',
            'description' => 'Ce contenu est soumis à une restriction d\'âge. Veuillez confirmer votre âge grâce à une vérification rapide pour continuer.',
            'button' => 'Vérifier mon âge',
        ],
        'es' => [
            'title' => 'Verificación de edad obligatoria',
            'description' => 'Este contenido tiene restricción de edad. Confirma tu edad con una verificación rápida para continuar.',
            'button' => 'Verificar mi edad',
        ],
        'it' => [
            'title' => 'Verifica dell\'età richiesta',
            'description' => 'Questo contenuto è soggetto a limiti di età. Conferma la tua età con una verifica rapida per continuare.',
            'button' => 'Verifica la mia età',
        ],
    ];

    /**
     * @return array{title:string,description:string,button:string}
     */
    public static function defaultsFor(string $iso): array
    {
        $iso = strtolower($iso);

        return isset(self::DEFAULTS[$iso]) ? self::DEFAULTS[$iso] : self::DEFAULTS['en'];
    }
}

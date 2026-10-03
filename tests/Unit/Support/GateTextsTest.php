<?php

namespace ProofAge\PrestaShop\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use ProofAge\PrestaShop\Support\GateTexts;

final class GateTextsTest extends TestCase
{
    public function testKnownLanguages(): void
    {
        self::assertSame('Verify my age', GateTexts::defaultsFor('en')['button']);
        self::assertSame('Vérifier mon âge', GateTexts::defaultsFor('fr')['button']);
        self::assertSame('Verificar mi edad', GateTexts::defaultsFor('ES')['button']);
        self::assertSame('Verifica la mia età', GateTexts::defaultsFor('it')['button']);
    }

    public function testFallbackIsEnglish(): void
    {
        self::assertSame(GateTexts::defaultsFor('en'), GateTexts::defaultsFor('ru'));
    }

    public function testAllKeysPresent(): void
    {
        foreach (['en', 'fr', 'es', 'it'] as $iso) {
            self::assertSame(['title', 'description', 'button'], array_keys(GateTexts::defaultsFor($iso)), $iso);
        }
    }
}

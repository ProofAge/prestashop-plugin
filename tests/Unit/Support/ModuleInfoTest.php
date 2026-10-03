<?php

namespace ProofAge\PrestaShop\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use ProofAge\PrestaShop\Support\ModuleInfo;

final class ModuleInfoTest extends TestCase
{
    public function testVersionIsSemver(): void
    {
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', ModuleInfo::VERSION);
        self::assertSame('proofage', ModuleInfo::NAME);
    }

    /**
     * The PrestaShop Addons validator reads the module class statically and requires
     * string literals for name and version in the constructor.
     */
    public function testModuleClassDeclaresNameAndVersionAsLiteralsMatchingModuleInfo(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/proofage.php');

        self::assertMatchesRegularExpression('/\$this->name\s*=\s*\'' . preg_quote(ModuleInfo::NAME, '/') . '\';/', $source);
        self::assertMatchesRegularExpression('/\$this->version\s*=\s*\'' . preg_quote(ModuleInfo::VERSION, '/') . '\';/', $source);
    }
}

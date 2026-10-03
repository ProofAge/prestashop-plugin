<?php

namespace ProofAge\PrestaShop\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Rules the PrestaShop Addons validator enforces on every shipped PHP file.
 */
final class ShippedCodeTest extends TestCase
{
    /**
     * @return list<string>
     */
    private static function shippedPhpFiles(): array
    {
        $root = dirname(__DIR__, 2);
        $files = [$root . '/proofage.php'];
        foreach (['src', 'controllers'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->getExtension() === 'php' && $file->getFilename() !== 'index.php') {
                    $files[] = $file->getPathname();
                }
            }
        }
        sort($files);

        return $files;
    }

    public function testEveryShippedPhpFileExitsOutsidePrestaShop(): void
    {
        $missing = [];
        foreach (self::shippedPhpFiles() as $file) {
            // The statement itself, not just a mention of the constant elsewhere in the code.
            if (!preg_match('/^if \(!defined\(\'_PS_VERSION_\'\)\) \{\s*exit;\s*\}/m', (string) file_get_contents($file))) {
                $missing[] = $file;
            }
        }

        self::assertSame([], $missing, 'Missing the _PS_VERSION_ guard');
    }

    public function testLicenseDocblockDirectlyFollowsTheOpeningTag(): void
    {
        $bad = [];
        foreach (self::shippedPhpFiles() as $file) {
            if (strpos((string) file_get_contents($file), "<?php\n/**") !== 0) {
                $bad[] = $file;
            }
        }

        self::assertSame([], $bad, 'Blank line or missing docblock after <?php');
    }
}

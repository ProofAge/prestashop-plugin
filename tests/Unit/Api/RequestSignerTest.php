<?php

namespace ProofAge\PrestaShop\Tests\Unit\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProofAge\PrestaShop\Api\RequestSigner;

final class RequestSignerTest extends TestCase
{
    public function testSignsPostWithBody(): void
    {
        $signer = new RequestSigner('sk_test_abc');

        self::assertSame(
            '66daedb7b63981603120ab0eacc165845dc56621a8ab8133954b55c087a2c35b',
            $signer->sign('post', '/v1/verifications', '{"external_id":"ps-1-c42"}')
        );
    }

    public function testSignsGetWithEmptyBody(): void
    {
        $signer = new RequestSigner('sk_test_abc');

        self::assertSame(
            '095fc297ebc6e2981107bb9f963b4a6fdd99400d7a914aeb9097a638f4081790',
            $signer->sign('GET', '/v1/verifications/0b7c2c1e-8f1a-4b5e-9d1c-2f3a4b5c6d7e', '')
        );
    }

    public function testDifferentSecretGivesDifferentSignature(): void
    {
        $a = (new RequestSigner('sk_test_abc'))->sign('GET', '/v1/workspace', '');
        $b = (new RequestSigner('sk_test_xyz'))->sign('GET', '/v1/workspace', '');

        self::assertNotSame($a, $b);
    }

    public function testTimestampedCanonicalPrefixesTheLegacyOne(): void
    {
        $signer = new RequestSigner('sk_test_abc');

        self::assertSame('1795049078.POST/v1/verifications{}', $signer->canonicalWithTimestamp('post', '/v1/verifications', '{}', 1795049078));
        self::assertSame(
            hash_hmac('sha256', '1795049078.POST/v1/verifications{}', 'sk_test_abc'),
            $signer->signWithTimestamp('POST', '/v1/verifications', '{}', 1795049078)
        );
        self::assertSame(
            '66daedb7b63981603120ab0eacc165845dc56621a8ab8133954b55c087a2c35b',
            $signer->sign('post', '/v1/verifications', '{"external_id":"ps-1-c42"}'),
            'The legacy signature is unchanged'
        );
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:bool}>
     */
    public static function sharedVectors(): array
    {
        $file = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/Fixtures/hmac-vectors.json'), true);
        $cases = [];
        foreach ($file['json'] as $vector) {
            if (isset($vector['query'])) {
                continue; // The module never sends a query string, so the signer has no query normalisation.
            }
            $cases['legacy: ' . $vector['name']] = [$file['secret'], $vector, false];
        }
        // The module only sends JSON bodies, so the multipart vectors (form = multipart) are not run.
        foreach ($file['timestamped'] as $vector) {
            if ($vector['form'] === 'json' && !isset($vector['query'])) {
                $cases['timestamped: ' . $vector['name']] = [$file['secret'], $vector, true];
            }
        }

        return $cases;
    }

    /**
     * @param array<string,mixed> $vector
     */
    #[DataProvider('sharedVectors')]
    public function testSharedVector(string $secret, array $vector, bool $timestamped): void
    {
        $signer = new RequestSigner($secret);
        if ($timestamped) {
            $timestamp = (int) $vector['timestamp'];
            self::assertSame($vector['canonical'], $signer->canonicalWithTimestamp($vector['method'], $vector['path'], $vector['body'], $timestamp));
            self::assertSame($vector['expected'], $signer->signWithTimestamp($vector['method'], $vector['path'], $vector['body'], $timestamp));

            return;
        }
        self::assertSame($vector['canonical'], $signer->canonical($vector['method'], $vector['path'], $vector['body']));
        self::assertSame($vector['expected'], $signer->sign($vector['method'], $vector['path'], $vector['body']));
    }
}

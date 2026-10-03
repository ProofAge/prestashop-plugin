<?php

namespace ProofAge\PrestaShop\Tests\Unit\Api;

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
}

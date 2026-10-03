<?php

namespace ProofAge\PrestaShop\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use ProofAge\PrestaShop\Api\ApiClient;
use ProofAge\PrestaShop\Api\ApiException;
use ProofAge\PrestaShop\Api\HttpResponse;
use ProofAge\PrestaShop\Api\RequestSigner;
use ProofAge\PrestaShop\Tests\Unit\Fakes\RecordingTransport;

final class ApiClientTest extends TestCase
{
    public function testCreateVerificationSendsSignedJson(): void
    {
        $transport = new RecordingTransport(new HttpResponse(201, '{"id":"v-1","url":"https://idv.proofage.xyz/v/x","status":"created"}'));
        $client = new ApiClient($transport, 'https://api.proofage.xyz/', 'pk_test_1', new RequestSigner('sk_test_abc'));

        $result = $client->createVerification(['external_id' => 'ps-1-c42', 'callback_url' => 'https://shop.test/return?ref=a/b']);

        self::assertSame('v-1', $result['id']);
        $request = $transport->requests[0];
        self::assertSame('POST', $request['method']);
        self::assertSame('https://api.proofage.xyz/v1/verifications', $request['url']);
        self::assertSame('{"external_id":"ps-1-c42","callback_url":"https://shop.test/return?ref=a/b"}', $request['body']);
        self::assertSame('pk_test_1', $request['headers']['X-API-Key']);
        self::assertSame('application/json', $request['headers']['Content-Type']);
        self::assertSame(
            (new RequestSigner('sk_test_abc'))->sign('POST', '/v1/verifications', $request['body']),
            $request['headers']['X-HMAC-Signature']
        );
    }

    public function testUnwrapsDataEnvelope(): void
    {
        $transport = new RecordingTransport(new HttpResponse(200, '{"data":{"id":"v-2","status":"review"}}'));
        $client = new ApiClient($transport, 'https://api.proofage.xyz', 'pk', new RequestSigner('sk'));

        self::assertSame(['id' => 'v-2', 'status' => 'review'], $client->getVerification('v-2'));
    }

    public function testGetVerificationSignsPathWithEmptyBody(): void
    {
        $transport = new RecordingTransport(new HttpResponse(200, '{"id":"v 3"}'));
        $client = new ApiClient($transport, 'https://api.proofage.xyz', 'pk', new RequestSigner('sk'));

        $client->getVerification('v 3');

        $request = $transport->requests[0];
        self::assertSame('GET', $request['method']);
        self::assertSame('https://api.proofage.xyz/v1/verifications/v%203', $request['url']);
        self::assertSame('', $request['body']);
        self::assertArrayNotHasKey('Content-Type', $request['headers']);
        self::assertSame((new RequestSigner('sk'))->sign('GET', '/v1/verifications/v%203', ''), $request['headers']['X-HMAC-Signature']);
    }

    public function testBaseUrlPathPrefixIsSigned(): void
    {
        $transport = new RecordingTransport(new HttpResponse(200, '{"id":"ws"}'));
        $client = new ApiClient($transport, 'http://127.0.0.1:8765/proxy/', 'pk', new RequestSigner('sk'));

        $client->getWorkspace();

        self::assertSame('http://127.0.0.1:8765/proxy/v1/workspace', $transport->requests[0]['url']);
        self::assertSame((new RequestSigner('sk'))->sign('GET', '/proxy/v1/workspace', ''), $transport->requests[0]['headers']['X-HMAC-Signature']);
    }

    public function testErrorResponseThrowsWithStatusAndCode(): void
    {
        $transport = new RecordingTransport(new HttpResponse(402, '{"code":"PAYMENT_METHOD_REQUIRED","message":"Add a payment method"}'));
        $client = new ApiClient($transport, 'https://api.proofage.xyz', 'pk', new RequestSigner('sk'));

        try {
            $client->createVerification([]);
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(402, $e->getHttpStatus());
            self::assertSame('PAYMENT_METHOD_REQUIRED', $e->getErrorCode());
            self::assertSame('Add a payment method', $e->getMessage());
        }
    }

    public function testNonJsonSuccessThrows(): void
    {
        $transport = new RecordingTransport(new HttpResponse(200, '<html>'));
        $client = new ApiClient($transport, 'https://api.proofage.xyz', 'pk', new RequestSigner('sk'));

        $this->expectException(ApiException::class);
        $client->getWorkspace();
    }
}

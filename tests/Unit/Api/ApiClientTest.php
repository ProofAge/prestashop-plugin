<?php

namespace ProofAge\PrestaShop\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use ProofAge\PrestaShop\Api\ApiClient;
use ProofAge\PrestaShop\Api\ApiException;
use ProofAge\PrestaShop\Api\HttpResponse;
use ProofAge\PrestaShop\Api\MemoryClockSkewStore;
use ProofAge\PrestaShop\Api\RequestSigner;
use ProofAge\PrestaShop\Support\ModuleInfo;
use ProofAge\PrestaShop\Tests\Unit\Fakes\FixedClock;
use ProofAge\PrestaShop\Tests\Unit\Fakes\RecordingTransport;

final class ApiClientTest extends TestCase
{
    public function testCreateVerificationSendsSignedJson(): void
    {
        $transport = new RecordingTransport(new HttpResponse(201, '{"id":"v-1","url":"https://idv.proofage.net/v/x","status":"created"}'));
        $client = new ApiClient($transport, 'https://api.proofage.net/', 'pk_test_1', new RequestSigner('sk_test_abc'));

        $result = $client->createVerification(['external_id' => 'ps-1-c42', 'callback_url' => 'https://shop.test/return?ref=a/b']);

        self::assertSame('v-1', $result['id']);
        $request = $transport->requests[0];
        self::assertSame('POST', $request['method']);
        self::assertSame('https://api.proofage.net/v1/verifications', $request['url']);
        self::assertSame('{"external_id":"ps-1-c42","callback_url":"https://shop.test/return?ref=a/b"}', $request['body']);
        self::assertSame('pk_test_1', $request['headers']['X-API-Key']);
        self::assertSame('application/json', $request['headers']['Content-Type']);
        self::assertSame(
            (new RequestSigner('sk_test_abc'))->signWithTimestamp('POST', '/v1/verifications', $request['body'], (int) $request['headers']['X-Timestamp']),
            $request['headers']['X-HMAC-Signature']
        );
    }

    public function testUnwrapsDataEnvelope(): void
    {
        $transport = new RecordingTransport(new HttpResponse(200, '{"data":{"id":"v-2","status":"review"}}'));
        $client = new ApiClient($transport, 'https://api.proofage.net', 'pk', new RequestSigner('sk'));

        self::assertSame(['id' => 'v-2', 'status' => 'review'], $client->getVerification('v-2'));
    }

    public function testGetVerificationSignsPathWithEmptyBody(): void
    {
        $transport = new RecordingTransport(new HttpResponse(200, '{"id":"v 3"}'));
        $client = new ApiClient($transport, 'https://api.proofage.net', 'pk', new RequestSigner('sk'));

        $client->getVerification('v 3');

        $request = $transport->requests[0];
        self::assertSame('GET', $request['method']);
        self::assertSame('https://api.proofage.net/v1/verifications/v%203', $request['url']);
        self::assertSame('', $request['body']);
        self::assertArrayNotHasKey('Content-Type', $request['headers']);
        self::assertSame((new RequestSigner('sk'))->signWithTimestamp('GET', '/v1/verifications/v%203', '', (int) $request['headers']['X-Timestamp']), $request['headers']['X-HMAC-Signature']);
    }

    public function testBaseUrlPathPrefixIsSigned(): void
    {
        $transport = new RecordingTransport(new HttpResponse(200, '{"id":"ws"}'));
        $client = new ApiClient($transport, 'http://127.0.0.1:8765/proxy/', 'pk', new RequestSigner('sk'));

        $client->getWorkspace();

        self::assertSame('http://127.0.0.1:8765/proxy/v1/workspace', $transport->requests[0]['url']);
        self::assertSame((new RequestSigner('sk'))->signWithTimestamp('GET', '/proxy/v1/workspace', '', (int) $transport->requests[0]['headers']['X-Timestamp']), $transport->requests[0]['headers']['X-HMAC-Signature']);
    }

    public function testErrorResponseThrowsWithStatusAndCode(): void
    {
        $transport = new RecordingTransport(new HttpResponse(402, '{"code":"PAYMENT_METHOD_REQUIRED","message":"Add a payment method"}'));
        $client = new ApiClient($transport, 'https://api.proofage.net', 'pk', new RequestSigner('sk'));

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
        $client = new ApiClient($transport, 'https://api.proofage.net', 'pk', new RequestSigner('sk'));

        $this->expectException(ApiException::class);
        $client->getWorkspace();
    }

    /**
     * @return array{0:ApiClient,1:FixedClock,2:MemoryClockSkewStore,3:\ArrayObject<int,float>}
     */
    private function clientWith(RecordingTransport $transport): array
    {
        $clock = new FixedClock(1795049078);
        $skew = new MemoryClockSkewStore();
        $sleeps = new \ArrayObject();
        $client = new ApiClient($transport, 'https://api.proofage.net', 'pk', new RequestSigner('sk'), $clock, $skew, function (float $seconds) use ($clock, $sleeps): void {
            $sleeps[] = $seconds;
            $clock->advance(1);
        });

        return [$client, $clock, $skew, $sleeps];
    }

    public function testEveryRequestCarriesTimestampAndSdkHeaderAndMatchesTheSharedVector(): void
    {
        $transport = new RecordingTransport(new HttpResponse(200, '{"id":"ws"}'));
        [$client] = $this->clientWith($transport);

        $client->getWorkspace();

        $headers = $transport->requests[0]['headers'];
        self::assertSame('1795049078', $headers['X-Timestamp']);
        self::assertSame('86285373b76c47809ad4ab8629040076f01e9940e226a8c2f00344c13a6dcaf7', (new RequestSigner('vector-secret-2026'))->signWithTimestamp('GET', '/v1/workspace', '', 1795049078));
        self::assertSame(
            hash_hmac('sha256', '1795049078.GET/v1/workspace', 'sk'),
            $headers['X-HMAC-Signature']
        );
        self::assertSame('prestashop/' . ModuleInfo::VERSION, $headers['X-ProofAge-Sdk']);
        self::assertArrayNotHasKey('Idempotency-Key', $headers);
    }

    public function testCreateVerificationGeneratesAUuidIdempotencyKey(): void
    {
        $transport = new RecordingTransport(new HttpResponse(201, '{"id":"v"}'), new HttpResponse(201, '{"id":"w"}'));
        [$client] = $this->clientWith($transport);

        $client->createVerification(['external_id' => 'a']);
        $client->createVerification(['external_id' => 'a']);

        $first = $transport->requests[0]['headers']['Idempotency-Key'];
        $second = $transport->requests[1]['headers']['Idempotency-Key'];
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $first);
        self::assertNotSame($first, $second);
    }

    public function testCallerSuppliedIdempotencyKeyIsSentAndValidated(): void
    {
        $transport = new RecordingTransport(new HttpResponse(201, '{"id":"v"}'), new HttpResponse(201, '{"id":"w"}'));
        [$client] = $this->clientWith($transport);

        $client->createVerification([], 'order-1042');
        self::assertSame('order-1042', $transport->requests[0]['headers']['Idempotency-Key']);

        foreach (['', 'has space', "tab\t", 'caf' . "\xc3\xa9", str_repeat('k', 256), "ok\n"] as $bad) {
            try {
                $client->createVerification([], $bad);
                self::fail('Expected InvalidArgumentException for ' . json_encode($bad));
            } catch (\InvalidArgumentException $e) {
                self::assertCount(1, $transport->requests);
            }
        }
        $client->createVerification([], str_repeat('k', 255));
        self::assertCount(2, $transport->requests);
    }

    public function testReplayedPostWaitsForTheNextSecondAndResendsOnceWithTheSameKey(): void
    {
        $transport = new RecordingTransport(
            new HttpResponse(401, '{"error":{"code":"REPLAYED_REQUEST","message":"Replayed"}}'),
            new HttpResponse(201, '{"id":"v-9"}')
        );
        [$client, , , $sleeps] = $this->clientWith($transport);

        $result = $client->createVerification(['external_id' => 'a']);

        self::assertSame('v-9', $result['id']);
        self::assertCount(2, $transport->requests);
        self::assertCount(1, $sleeps);
        self::assertGreaterThan(0.04, $sleeps[0]);
        self::assertLessThanOrEqual(1.05, $sleeps[0]);
        [$one, $two] = $transport->requests;
        self::assertSame('1795049078', $one['headers']['X-Timestamp']);
        self::assertSame('1795049079', $two['headers']['X-Timestamp']);
        self::assertNotSame($one['headers']['X-HMAC-Signature'], $two['headers']['X-HMAC-Signature']);
        self::assertSame(hash_hmac('sha256', '1795049079.POST/v1/verifications{"external_id":"a"}', 'sk'), $two['headers']['X-HMAC-Signature']);
        self::assertSame($one['headers']['Idempotency-Key'], $two['headers']['Idempotency-Key']);
    }

    public function testSecondReplayedAnswerIsAnError(): void
    {
        $replayed = '{"error":{"code":"REPLAYED_REQUEST","message":"Replayed"}}';
        $transport = new RecordingTransport(new HttpResponse(401, $replayed), new HttpResponse(401, $replayed));
        [$client] = $this->clientWith($transport);

        try {
            $client->createVerification([]);
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(401, $e->getHttpStatus());
            self::assertSame('REPLAYED_REQUEST', $e->getErrorCode());
            self::assertCount(2, $transport->requests);
        }
    }

    public function testReplayedGetIsNotResent(): void
    {
        $transport = new RecordingTransport(new HttpResponse(401, '{"error":{"code":"REPLAYED_REQUEST","message":"Replayed"}}'));
        [$client, , , $sleeps] = $this->clientWith($transport);

        try {
            $client->getWorkspace();
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame('REPLAYED_REQUEST', $e->getErrorCode());
            self::assertCount(1, $transport->requests);
            self::assertCount(0, $sleeps);
        }
    }

    public function testClockSkewIsLearnedFromTheDateHeaderAndAppliedToLaterRequests(): void
    {
        // Local clock is 1795049078; the server says 1795049978 (15 minutes ahead).
        $date = gmdate('D, d M Y H:i:s', 1795049978) . ' GMT';
        $transport = new RecordingTransport(
            new HttpResponse(401, '{"error":{"code":"TIMESTAMP_OUT_OF_RANGE","message":"Skew"}}', ['Date' => $date]),
            new HttpResponse(201, '{"id":"v-1"}'),
            new HttpResponse(200, '{"id":"ws"}')
        );
        [$client, , $skew] = $this->clientWith($transport);

        $client->createVerification([]);

        self::assertSame(900, $skew->offset());
        self::assertCount(2, $transport->requests);
        self::assertSame('1795049078', $transport->requests[0]['headers']['X-Timestamp']);
        self::assertSame('1795049978', $transport->requests[1]['headers']['X-Timestamp']);
        self::assertSame(
            hash_hmac('sha256', '1795049978.POST/v1/verifications[]', 'sk'),
            $transport->requests[1]['headers']['X-HMAC-Signature']
        );
        self::assertSame($transport->requests[0]['headers']['Idempotency-Key'], $transport->requests[1]['headers']['Idempotency-Key']);

        $client->getWorkspace();
        self::assertSame('1795049978', $transport->requests[2]['headers']['X-Timestamp']);
    }

    public function testClockSkewWithoutDateHeaderIsAnErrorAndStoresNothing(): void
    {
        $transport = new RecordingTransport(new HttpResponse(401, '{"error":{"code":"TIMESTAMP_OUT_OF_RANGE","message":"Skew"}}'));
        [$client, , $skew] = $this->clientWith($transport);

        try {
            $client->getWorkspace();
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame('TIMESTAMP_OUT_OF_RANGE', $e->getErrorCode());
            self::assertCount(1, $transport->requests);
            self::assertSame(0, $skew->offset());
        }
    }

    public function testClockSkewIsRetriedOnlyOnce(): void
    {
        $date = gmdate('D, d M Y H:i:s', 1795049978) . ' GMT';
        $out = '{"error":{"code":"TIMESTAMP_OUT_OF_RANGE","message":"Skew"}}';
        $transport = new RecordingTransport(new HttpResponse(401, $out, ['Date' => $date]), new HttpResponse(401, $out, ['Date' => $date]));
        [$client] = $this->clientWith($transport);

        $this->expectException(ApiException::class);
        try {
            $client->getWorkspace();
        } finally {
            self::assertCount(2, $transport->requests);
        }
    }

    public function testHttpResponseHeadersAreCaseInsensitive(): void
    {
        $response = new HttpResponse(200, '', ['DATE' => 'x']);

        self::assertSame('x', $response->header('Date'));
        self::assertNull($response->header('Retry-After'));
    }
}

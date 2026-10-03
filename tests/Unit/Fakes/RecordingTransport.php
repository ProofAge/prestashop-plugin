<?php

namespace ProofAge\PrestaShop\Tests\Unit\Fakes;

use ProofAge\PrestaShop\Api\HttpResponse;
use ProofAge\PrestaShop\Api\HttpTransport;

final class RecordingTransport implements HttpTransport
{
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:string}> */
    public array $requests = [];

    /** @var list<HttpResponse> */
    private array $responses;

    public function __construct(HttpResponse ...$responses)
    {
        $this->responses = $responses;
    }

    public function send(string $method, string $url, array $headers, string $body): HttpResponse
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        return array_shift($this->responses) ?? new HttpResponse(500, '{}');
    }
}

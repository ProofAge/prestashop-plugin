<?php

namespace ProofAge\PrestaShop\Tests\Unit\Fakes;

use ProofAge\PrestaShop\Api\ApiException;
use ProofAge\PrestaShop\Api\VerificationApi;

class FakeVerificationApi implements VerificationApi
{
    /** @var list<array<string,mixed>> */
    public array $created = [];
    /** @var array<string,array<string,mixed>> */
    public array $remote = [];
    public ?ApiException $failWith = null;
    public int $getCalls = 0;
    private int $sequence = 0;

    public function createVerification(array $payload): array
    {
        if ($this->failWith) {
            throw $this->failWith;
        }
        $this->created[] = $payload;
        $id = 'ver-' . ++$this->sequence;
        $this->remote[$id] = ['id' => $id, 'external_id' => $payload['external_id'], 'status' => 'created'];

        return ['id' => $id, 'url' => 'https://idv.proofage.net/v/' . $id, 'status' => 'created'];
    }

    public function getVerification(string $verificationId): array
    {
        ++$this->getCalls;
        if ($this->failWith) {
            throw $this->failWith;
        }

        return $this->remote[$verificationId] ?? throw new ApiException('Not found', 404, null);
    }
}

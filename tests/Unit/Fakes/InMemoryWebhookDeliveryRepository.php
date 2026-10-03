<?php

namespace ProofAge\PrestaShop\Tests\Unit\Fakes;

use ProofAge\PrestaShop\Verification\WebhookDeliveryRepository;

final class InMemoryWebhookDeliveryRepository implements WebhookDeliveryRepository
{
    /** @var array<string,array{verification_id:string,status:string,received_at:int}> */
    public array $rows = [];

    public function exists(string $deliveryId): bool
    {
        return isset($this->rows[$deliveryId]);
    }

    public function insert(string $deliveryId, string $verificationId, string $status, int $receivedAt): void
    {
        $this->rows[$deliveryId] ??= ['verification_id' => $verificationId, 'status' => $status, 'received_at' => $receivedAt];
    }
}

<?php

namespace ProofAge\PrestaShop\Tests\Unit\Fakes;

use ProofAge\PrestaShop\Verification\OrderSnapshot;
use ProofAge\PrestaShop\Verification\OrderSnapshotRepository;

final class InMemoryOrderSnapshotRepository implements OrderSnapshotRepository
{
    /** @var array<int,OrderSnapshot> */
    public array $rows = [];

    public function save(OrderSnapshot $snapshot): void
    {
        $this->rows[$snapshot->idOrder] = $snapshot;
    }

    public function find(int $idOrder): ?OrderSnapshot
    {
        return $this->rows[$idOrder] ?? null;
    }

    public function markRevoked(string $verificationId): void
    {
        foreach ($this->rows as $row) {
            if ($row->verificationId === $verificationId) {
                $row->status = OrderSnapshot::STATUS_REVOKED;
            }
        }
    }
}

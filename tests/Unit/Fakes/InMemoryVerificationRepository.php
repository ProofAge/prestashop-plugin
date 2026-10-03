<?php

namespace ProofAge\PrestaShop\Tests\Unit\Fakes;

use ProofAge\PrestaShop\Verification\VerificationRecord;
use ProofAge\PrestaShop\Verification\VerificationRepository;

final class InMemoryVerificationRepository implements VerificationRepository
{
    /** @var array<string,VerificationRecord> */
    public array $records = [];
    public int $updates = 0;

    public function findByVerificationId(string $verificationId): ?VerificationRecord
    {
        return $this->records[$verificationId] ?? null;
    }

    public function findByReturnRef(string $returnRef): ?VerificationRecord
    {
        foreach ($this->records as $record) {
            if ($record->returnRef === $returnRef) {
                return $record;
            }
        }

        return null;
    }

    public function findApprovedForCart(int $idCart, int $now): ?VerificationRecord
    {
        foreach ($this->records as $record) {
            if ($record->idCart === $idCart && $record->isApprovedAt($now)) {
                return $record;
            }
        }

        return null;
    }

    public function countCreatedSince(string $column, string $value, int $since): int
    {
        $count = 0;
        foreach ($this->records as $record) {
            $field = $column === 'ip_hash' ? $record->ipHash : $record->externalId;
            if ($field === $value && $record->createdAt >= $since) {
                ++$count;
            }
        }

        return $count;
    }

    public function insert(VerificationRecord $record): void
    {
        $record->id = count($this->records) + 1;
        $this->records[$record->verificationId] = $record;
    }

    public function expireApprovedForCustomer(int $idCustomer, int $idShop, int $now): void
    {
        foreach ($this->records as $record) {
            if ($record->idCustomer === $idCustomer && $record->idShop === $idShop && $record->status === 'approved') {
                $record->expiresAt = $now;
                $record->idCart = null;
                $record->updatedAt = $now;
            }
        }
    }

    public function update(VerificationRecord $record): void
    {
        ++$this->updates;
        $this->records[$record->verificationId] = $record;
    }
}

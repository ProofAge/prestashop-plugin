<?php

namespace ProofAge\PrestaShop\Tests\Unit\Fakes;

use ProofAge\PrestaShop\Verification\CustomerVerification;
use ProofAge\PrestaShop\Verification\CustomerVerificationRepository;

final class InMemoryCustomerVerificationRepository implements CustomerVerificationRepository
{
    /** @var array<string,CustomerVerification> key "customer-shop" */
    public array $rows = [];

    public function find(int $idCustomer, int $idShop): ?CustomerVerification
    {
        return $this->rows[$idCustomer . '-' . $idShop] ?? null;
    }

    public function save(CustomerVerification $verification): void
    {
        $this->rows[$verification->idCustomer . '-' . $verification->idShop] = $verification;
    }

    public function deleteByVerificationId(string $verificationId): void
    {
        foreach ($this->rows as $key => $row) {
            if ($row->verificationId === $verificationId) {
                unset($this->rows[$key]);
            }
        }
    }

    public function delete(int $idCustomer, int $idShop): void
    {
        unset($this->rows[$idCustomer . '-' . $idShop]);
    }
}

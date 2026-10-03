<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Infrastructure;

use ProofAge\PrestaShop\Verification\CustomerVerification;
use ProofAge\PrestaShop\Verification\CustomerVerificationRepository;

final class DbCustomerVerificationRepository implements CustomerVerificationRepository
{
    public const TABLE = 'proofage_customer';

    public function find(int $idCustomer, int $idShop): ?CustomerVerification
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE `id_customer` = ' . (int) $idCustomer . ' AND `id_shop` = ' . (int) $idShop
        );
        if (!is_array($row)) {
            return null;
        }

        return new CustomerVerification(
            (int) $row['id_customer'],
            (int) $row['id_shop'],
            (string) $row['verification_id'],
            (int) DbVerificationRepository::fromDb($row['verified_at']),
            DbVerificationRepository::fromDb($row['expires_at'])
        );
    }

    public function save(CustomerVerification $v): void
    {
        $expires = $v->expiresAt === null ? 'NULL' : '\'' . pSQL(DbVerificationRepository::toDb($v->expiresAt)) . '\'';
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . self::TABLE . '` (`id_customer`, `id_shop`, `verification_id`, `verified_at`, `expires_at`) VALUES ('
            . (int) $v->idCustomer . ', ' . (int) $v->idShop . ', \'' . pSQL($v->verificationId) . '\', \'' . pSQL(DbVerificationRepository::toDb($v->verifiedAt)) . '\', ' . $expires . ')'
            . ' ON DUPLICATE KEY UPDATE `verification_id` = VALUES(`verification_id`), `verified_at` = VALUES(`verified_at`), `expires_at` = VALUES(`expires_at`)';
        if (!\Db::getInstance()->execute($sql)) {
            throw new \RuntimeException('Could not save customer verification');
        }
    }

    public function deleteByVerificationId(string $verificationId): void
    {
        \Db::getInstance()->delete(self::TABLE, '`verification_id` = \'' . pSQL($verificationId) . '\'');
    }

    public function delete(int $idCustomer, int $idShop): void
    {
        \Db::getInstance()->delete(self::TABLE, '`id_customer` = ' . (int) $idCustomer . ' AND `id_shop` = ' . (int) $idShop);
    }

    public function deleteCustomerEverywhere(int $idCustomer): void
    {
        \Db::getInstance()->delete(self::TABLE, '`id_customer` = ' . (int) $idCustomer);
    }
}

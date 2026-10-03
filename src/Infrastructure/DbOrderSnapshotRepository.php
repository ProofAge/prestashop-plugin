<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Infrastructure;

use ProofAge\PrestaShop\Verification\OrderSnapshot;
use ProofAge\PrestaShop\Verification\OrderSnapshotRepository;

final class DbOrderSnapshotRepository implements OrderSnapshotRepository
{
    public const TABLE = 'proofage_order';

    public function save(OrderSnapshot $s): void
    {
        $sql = 'REPLACE INTO `' . _DB_PREFIX_ . self::TABLE . '` (`id_order`, `required`, `verification_id`, `status`, `method`, `verified_at`) VALUES ('
            . (int) $s->idOrder . ', ' . ($s->required ? 1 : 0) . ', '
            . ($s->verificationId === null ? 'NULL' : '\'' . pSQL($s->verificationId) . '\'') . ', \''
            . pSQL($s->status) . '\', '
            . ($s->method === null ? 'NULL' : '\'' . pSQL($s->method) . '\'') . ', '
            . ($s->verifiedAt === null ? 'NULL' : '\'' . pSQL(DbVerificationRepository::toDb($s->verifiedAt)) . '\'') . ')';
        if (!\Db::getInstance()->execute($sql)) {
            throw new \RuntimeException('Could not save order verification snapshot');
        }
    }

    public function find(int $idOrder): ?OrderSnapshot
    {
        $row = \Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE `id_order` = ' . (int) $idOrder);
        if (!is_array($row)) {
            return null;
        }

        return new OrderSnapshot(
            (int) $row['id_order'],
            (bool) $row['required'],
            $row['verification_id'] === null ? null : (string) $row['verification_id'],
            (string) $row['status'],
            $row['method'] === null ? null : (string) $row['method'],
            DbVerificationRepository::fromDb($row['verified_at'])
        );
    }

    public function markRevoked(string $verificationId): void
    {
        \Db::getInstance()->update(self::TABLE, ['status' => OrderSnapshot::STATUS_REVOKED], '`verification_id` = \'' . pSQL($verificationId) . '\'');
    }
}

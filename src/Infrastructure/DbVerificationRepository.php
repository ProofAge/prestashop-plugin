<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Infrastructure;

use ProofAge\PrestaShop\Verification\VerificationRecord;
use ProofAge\PrestaShop\Verification\VerificationRepository;

final class DbVerificationRepository implements VerificationRepository
{
    public const TABLE = 'proofage_verification';

    public function findByVerificationId(string $verificationId): ?VerificationRecord
    {
        return $this->findOne('`verification_id` = \'' . pSQL($verificationId) . '\'');
    }

    public function findByReturnRef(string $returnRef): ?VerificationRecord
    {
        return $this->findOne('`return_ref` = \'' . pSQL($returnRef) . '\'');
    }

    public function findApprovedForCart(int $idCart, int $now): ?VerificationRecord
    {
        return $this->findOne('`id_cart` = ' . (int) $idCart . ' AND `status` = \'approved\' AND `expires_at` > \'' . pSQL(self::toDb($now)) . '\' ORDER BY `decided_at` DESC');
    }

    public function countCreatedSince(string $column, string $value, int $since): int
    {
        $column = $column === 'ip_hash' ? 'ip_hash' : 'external_id';

        return (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE `' . $column . '` = \'' . pSQL($value) . '\' AND `created_at` >= \'' . pSQL(self::toDb($since)) . '\''
        );
    }

    public function insert(VerificationRecord $record): void
    {
        $db = \Db::getInstance();
        if (!$db->insert(self::TABLE, $this->toRow($record))) {
            throw new \RuntimeException('Could not save verification: ' . $db->getMsgError());
        }
        $record->id = (int) $db->Insert_ID();
    }

    public function update(VerificationRecord $record): void
    {
        $db = \Db::getInstance();
        if (!$db->update(self::TABLE, $this->toRow($record), '`verification_id` = \'' . pSQL($record->verificationId) . '\'', 1)) {
            throw new \RuntimeException('Could not update verification: ' . $db->getMsgError());
        }
    }

    public function expireApprovedForCustomer(int $idCustomer, int $idShop, int $now): void
    {
        \Db::getInstance()->update(
            self::TABLE,
            ['expires_at' => self::toDb($now), 'id_cart' => self::sqlNull(), 'updated_at' => self::toDb($now)],
            '`id_customer` = ' . (int) $idCustomer . ' AND `id_shop` = ' . (int) $idShop . ' AND `status` = \'approved\''
        );
    }

    /**
     * @return VerificationRecord[]
     */
    public function latest(int $idShop, int $limit): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE `id_shop` = ' . (int) $idShop . ' ORDER BY `created_at` DESC LIMIT ' . (int) $limit
        );

        return array_map([$this, 'fromRow'], is_array($rows) ? $rows : []);
    }

    /**
     * Unlinks a deleted customer (GDPR) without losing the verification audit trail:
     * drops the customer id, the customer-derived external id and the IP hash.
     */
    public function anonymiseCustomer(int $idCustomer): void
    {
        \Db::getInstance()->update(self::TABLE, [
            'id_customer' => self::sqlNull(),
            'external_id' => ['type' => 'sql', 'value' => 'CONCAT(\'deleted-\', `id_proofage_verification`)'],
            'ip_hash' => '',
        ], '`id_customer` = ' . (int) $idCustomer);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function rowsForCustomer(int $idCustomer): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT `verification_id`, `status`, `method`, `created_at`, `decided_at` FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE `id_customer` = ' . (int) $idCustomer
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return VerificationRecord|null
     */
    private function findOne(string $where)
    {
        $row = \Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE ' . $where);

        return is_array($row) ? $this->fromRow($row) : null;
    }

    /**
     * @return array<string,mixed>
     */
    private function toRow(VerificationRecord $r): array
    {
        return [
            'verification_id' => pSQL($r->verificationId),
            'id_shop' => (int) $r->idShop,
            'external_id' => pSQL($r->externalId),
            'id_customer' => $r->idCustomer === null ? self::sqlNull() : (int) $r->idCustomer,
            'id_cart' => $r->idCart === null ? self::sqlNull() : (int) $r->idCart,
            'session_token_hash' => pSQL($r->sessionTokenHash),
            'ip_hash' => pSQL($r->ipHash),
            'return_ref' => pSQL($r->returnRef),
            'verification_url' => pSQL($r->verificationUrl),
            'status' => pSQL($r->status),
            'method' => $r->method === null ? self::sqlNull() : pSQL($r->method),
            'reason' => $r->reason === null ? self::sqlNull() : pSQL(substr($r->reason, 0, 255)),
            'origin_url' => pSQL($r->originUrl),
            'last_synced_at' => self::dateOrNull($r->lastSyncedAt),
            'decided_at' => self::dateOrNull($r->decidedAt),
            'expires_at' => self::dateOrNull($r->expiresAt),
            'created_at' => self::toDb($r->createdAt),
            'updated_at' => self::toDb($r->updatedAt),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function fromRow(array $row): VerificationRecord
    {
        $r = new VerificationRecord();
        $r->id = (int) $row['id_proofage_verification'];
        $r->verificationId = (string) $row['verification_id'];
        $r->idShop = (int) $row['id_shop'];
        $r->externalId = (string) $row['external_id'];
        $r->idCustomer = $row['id_customer'] === null ? null : (int) $row['id_customer'];
        $r->idCart = $row['id_cart'] === null ? null : (int) $row['id_cart'];
        $r->sessionTokenHash = (string) $row['session_token_hash'];
        $r->ipHash = (string) $row['ip_hash'];
        $r->returnRef = (string) $row['return_ref'];
        $r->verificationUrl = (string) $row['verification_url'];
        $r->status = (string) $row['status'];
        $r->method = $row['method'] === null ? null : (string) $row['method'];
        $r->reason = $row['reason'] === null ? null : (string) $row['reason'];
        $r->originUrl = (string) $row['origin_url'];
        $r->lastSyncedAt = self::fromDb($row['last_synced_at']);
        $r->decidedAt = self::fromDb($row['decided_at']);
        $r->expiresAt = self::fromDb($row['expires_at']);
        $r->createdAt = (int) self::fromDb($row['created_at']);
        $r->updatedAt = (int) self::fromDb($row['updated_at']);

        return $r;
    }

    /**
     * Explicit SQL NULL for nullable columns; Db::insert()/update() would otherwise
     * turn every empty string into NULL when asked to write NULLs.
     *
     * @return array{type:string,value:string}
     */
    private static function sqlNull(): array
    {
        return ['type' => 'sql', 'value' => 'NULL'];
    }

    /**
     * @return string|array{type:string,value:string}
     */
    private static function dateOrNull(?int $timestamp)
    {
        return $timestamp === null ? self::sqlNull() : self::toDb($timestamp);
    }

    public static function toDb(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @return string|null
     */
    public static function toDbNullable(?int $timestamp)
    {
        return $timestamp === null ? null : self::toDb($timestamp);
    }

    /**
     * @param mixed $value
     *
     * @return int|null
     */
    public static function fromDb($value)
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }
        $ts = strtotime($value . ' UTC');

        return $ts === false ? null : $ts;
    }
}

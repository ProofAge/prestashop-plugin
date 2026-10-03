<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Infrastructure;

use ProofAge\PrestaShop\Verification\WebhookDeliveryRepository;

final class DbWebhookDeliveryRepository implements WebhookDeliveryRepository
{
    public const TABLE = 'proofage_webhook_delivery';

    public function exists(string $deliveryId): bool
    {
        return (bool) \Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE `delivery_id` = \'' . pSQL($deliveryId) . '\''
        );
    }

    public function insert(string $deliveryId, string $verificationId, string $status, int $receivedAt): void
    {
        \Db::getInstance()->execute(
            'INSERT IGNORE INTO `' . _DB_PREFIX_ . self::TABLE . '` (`delivery_id`, `verification_id`, `status`, `received_at`) VALUES (\''
            . pSQL(substr($deliveryId, 0, 64)) . '\', \'' . pSQL($verificationId) . '\', \'' . pSQL($status) . '\', \'' . pSQL(DbVerificationRepository::toDb($receivedAt)) . '\')'
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function latest(int $limit): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '` ORDER BY `received_at` DESC LIMIT ' . (int) $limit
        );

        return is_array($rows) ? $rows : [];
    }
}

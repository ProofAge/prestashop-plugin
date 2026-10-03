<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Infrastructure;

if (!defined('_PS_VERSION_')) {
    exit;
}

use ProofAge\PrestaShop\Support\GateTexts;

final class Installer
{
    public const HOOKS = [
        'actionFrontControllerInitAfter',
        'actionFrontControllerSetMedia',
        'displayBeforeBodyClosingTag',
        'actionAuthentication',
        'actionCustomerAccountAdd',
        'actionObjectOrderAddBefore',
        'actionValidateOrder',
        'displayAdminOrderMain',
        'displayAdminCustomers',
        'registerGDPRConsent',
        'actionExportGDPRData',
        'actionDeleteGDPRCustomer',
    ];

    public const TABLES = ['proofage_verification', 'proofage_customer', 'proofage_webhook_delivery', 'proofage_order'];

    public function install(\Module $module): bool
    {
        if (!$this->createTables() || !$module->registerHook(self::HOOKS) || !$this->installConfiguration()) {
            return false;
        }
        // Combined (CCC) JS/CSS bundles would otherwise keep serving the pre-install asset set.
        \Media::clearCache();

        return true;
    }

    public function uninstall(): bool
    {
        foreach (self::TABLES as $table) {
            \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . $table . '`');
        }
        foreach (Settings::ALL_KEYS as $key) {
            \Configuration::deleteByName($key);
        }

        return true;
    }

    private function createTables(): bool
    {
        $sql = file_get_contents(__DIR__ . '/../../sql/install.sql');
        if ($sql === false) {
            return false;
        }
        $sql = str_replace(['PREFIX', 'ENGINE=ENGINE'], [_DB_PREFIX_, 'ENGINE=' . _MYSQL_ENGINE_], $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if (!\Db::getInstance()->execute($statement)) {
                return false;
            }
        }

        return true;
    }

    private function installConfiguration(): bool
    {
        foreach (Settings::defaults() as $key => $value) {
            if (\Configuration::get($key) === false) {
                \Configuration::updateGlobalValue($key, $value);
            }
        }
        $texts = ['title' => [], 'description' => [], 'button' => []];
        foreach (\Language::getLanguages(false) as $language) {
            $defaults = GateTexts::defaultsFor((string) $language['iso_code']);
            foreach ($texts as $field => $unused) {
                $texts[$field][(int) $language['id_lang']] = $defaults[$field];
            }
        }

        return \Configuration::updateGlobalValue(Settings::GATE_TITLE, $texts['title'])
            && \Configuration::updateGlobalValue(Settings::GATE_DESCRIPTION, $texts['description'], true)
            && \Configuration::updateGlobalValue(Settings::GATE_BUTTON, $texts['button']);
    }
}

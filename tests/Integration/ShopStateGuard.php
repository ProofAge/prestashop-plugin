<?php
// Keeps the shop's real ProofAge settings and module data intact across an integration run.
//
// The checks overwrite PROOFAGE_* configuration with fake keys, flip a few PS_* settings and
// truncate (or even drop, via uninstall) the module tables. Before the first check the guard
// copies all of that into *__bak tables; after the last check — also when a check throws or the
// script dies — it puts everything back exactly and drops the copies. Because the copies live in
// the database, a run that was killed outright is repaired at the start of the next run.

const PROOFAGE_GUARD_TABLES = ['proofage_verification', 'proofage_customer', 'proofage_webhook_delivery', 'proofage_order'];
const PROOFAGE_GUARD_PS_KEYS = ['PS_SSL_ENABLED', 'PS_SSL_ENABLED_EVERYWHERE', 'PS_SHOP_ENABLE', 'PS_MAINTENANCE_IP'];

function proofage_guard_table(string $name): string
{
    return '`' . _DB_PREFIX_ . $name . '`';
}

function proofage_guard_backup_table(string $name): string
{
    return '`' . _DB_PREFIX_ . $name . '__bak`';
}

function proofage_guard_table_exists(string $name): bool
{
    return (bool) Db::getInstance()->executeS('SHOW TABLES LIKE "' . _DB_PREFIX_ . str_replace('_', '\\_', $name) . '"');
}

/** WHERE clause selecting the configuration rows the guard owns. */
function proofage_guard_config_where(string $alias = ''): string
{
    $column = ($alias !== '' ? $alias . '.' : '') . '`name`';
    $keys = implode(',', array_map(fn (string $key): string => '"' . pSQL($key) . '"', PROOFAGE_GUARD_PS_KEYS));

    return '(' . $column . ' LIKE "PROOFAGE\\_%" OR ' . $column . ' IN (' . $keys . '))';
}

function proofage_guard_exec(string $sql): void
{
    if (!Db::getInstance()->execute($sql)) {
        throw new RuntimeException('Shop state guard query failed: ' . Db::getInstance()->getMsgError() . ' — ' . $sql);
    }
}

/** Copies the live configuration rows and module tables into *__bak tables. */
function proofage_guard_snapshot(): void
{
    if (proofage_guard_table_exists('proofage_configuration__bak')) {
        // A previous run died before restoring: its copies hold the real data, restore them first.
        echo "Found backups from an interrupted run, restoring them first.\n";
        proofage_guard_restore();
    }
    // Leftovers of a snapshot that died before completing; no check ran after it, so they are stale.
    foreach (array_merge(PROOFAGE_GUARD_TABLES, ['proofage_configuration_lang']) as $table) {
        proofage_guard_exec('DROP TABLE IF EXISTS ' . proofage_guard_backup_table($table));
    }
    $config = proofage_guard_backup_table('proofage_configuration');
    $configLang = proofage_guard_backup_table('proofage_configuration_lang');
    proofage_guard_exec('CREATE TABLE ' . $configLang . ' LIKE ' . proofage_guard_table('configuration_lang'));
    proofage_guard_exec('INSERT INTO ' . $configLang . ' SELECT cl.* FROM ' . proofage_guard_table('configuration_lang') . ' cl'
        . ' INNER JOIN ' . proofage_guard_table('configuration') . ' c ON c.`id_configuration` = cl.`id_configuration`'
        . ' WHERE ' . proofage_guard_config_where('c'));
    foreach (PROOFAGE_GUARD_TABLES as $table) {
        if (!proofage_guard_table_exists($table)) {
            continue;
        }
        proofage_guard_exec('CREATE TABLE ' . proofage_guard_backup_table($table) . ' LIKE ' . proofage_guard_table($table));
        proofage_guard_exec('INSERT INTO ' . proofage_guard_backup_table($table) . ' SELECT * FROM ' . proofage_guard_table($table));
    }
    // Created last: its presence marks a complete snapshot.
    proofage_guard_exec('CREATE TABLE ' . $config . ' LIKE ' . proofage_guard_table('configuration'));
    proofage_guard_exec('INSERT INTO ' . $config . ' SELECT * FROM ' . proofage_guard_table('configuration') . ' WHERE ' . proofage_guard_config_where());
}

/**
 * Puts the snapshot back exactly and drops the *__bak tables. Safe to call more than once.
 *
 * @return bool whether a snapshot was restored
 */
function proofage_guard_restore(): bool
{
    $config = proofage_guard_backup_table('proofage_configuration');
    $configLang = proofage_guard_backup_table('proofage_configuration_lang');
    $restored = proofage_guard_table_exists('proofage_configuration__bak');
    if ($restored) {
        proofage_guard_exec('DELETE cl FROM ' . proofage_guard_table('configuration_lang') . ' cl'
            . ' INNER JOIN ' . proofage_guard_table('configuration') . ' c ON c.`id_configuration` = cl.`id_configuration`'
            . ' WHERE ' . proofage_guard_config_where('c'));
        proofage_guard_exec('DELETE FROM ' . proofage_guard_table('configuration') . ' WHERE ' . proofage_guard_config_where());
        // Orphaned language rows of keys that were deleted and re-created with new ids.
        proofage_guard_exec('DELETE cl FROM ' . proofage_guard_table('configuration_lang') . ' cl'
            . ' INNER JOIN ' . $configLang . ' b ON b.`id_configuration` = cl.`id_configuration`');
        proofage_guard_exec('INSERT INTO ' . proofage_guard_table('configuration') . ' SELECT * FROM ' . $config);
        proofage_guard_exec('INSERT INTO ' . proofage_guard_table('configuration_lang') . ' SELECT * FROM ' . $configLang);
    }
    foreach (PROOFAGE_GUARD_TABLES as $table) {
        if (!proofage_guard_table_exists($table . '__bak')) {
            continue;
        }
        if (!proofage_guard_table_exists($table)) {
            proofage_guard_exec('CREATE TABLE ' . proofage_guard_table($table) . ' LIKE ' . proofage_guard_backup_table($table));
        }
        proofage_guard_exec('TRUNCATE TABLE ' . proofage_guard_table($table));
        proofage_guard_exec('INSERT INTO ' . proofage_guard_table($table) . ' SELECT * FROM ' . proofage_guard_backup_table($table));
        proofage_guard_exec('DROP TABLE ' . proofage_guard_backup_table($table));
    }
    proofage_guard_exec('DROP TABLE IF EXISTS ' . $configLang);
    // Dropped last, so an interrupted restore is retried by the next run.
    proofage_guard_exec('DROP TABLE IF EXISTS ' . $config);
    Configuration::clearConfigurationCacheForTesting();
    Cache::clean('Configuration*');

    return $restored;
}

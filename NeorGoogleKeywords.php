<?php
namespace Piwik\Plugins\NeorGoogleKeywords;

use Piwik\Common;
use Piwik\Db;

class NeorGoogleKeywords extends \Piwik\Plugin
{
    public function install()
    {
        // Migrate legacy tables if upgrading
        $legacyRows = Common::prefixTable('gsc_lite_rows');
        $newRows = Common::prefixTable('neor_gkeys_rows');
        $legacyDays = Common::prefixTable('gsc_lite_days');
        $newDays = Common::prefixTable('neor_gkeys_days');

        try {
            $existing = Db::fetchAll('SHOW TABLES LIKE ?', [Common::prefixTable('%')]);
            $flat = array_map(static fn($r) => array_values($r)[0] ?? '', $existing);
            if (in_array($legacyRows, $flat, true) && !in_array($newRows, $flat, true)) {
                Db::query('RENAME TABLE `' . $legacyRows . '` TO `' . $newRows . '`');
            }
            if (in_array($legacyDays, $flat, true) && !in_array($newDays, $flat, true)) {
                Db::query('RENAME TABLE `' . $legacyDays . '` TO `' . $newDays . '`');
            }
        } catch (\Throwable $e) {
            // Ignore migration query errors on restricted environments; CREATE TABLE will handle creation
        }

        Db::query('CREATE TABLE IF NOT EXISTS `' . Common::prefixTable('neor_gkeys_rows') . '` (
            idsite INT UNSIGNED NOT NULL,
            property_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            data_date DATE NOT NULL,
            kind TINYINT UNSIGNED NOT NULL,
            row_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            query_text TEXT NOT NULL,
            page_url TEXT NOT NULL,
            clicks DOUBLE NOT NULL,
            impressions DOUBLE NOT NULL,
            position_sum DOUBLE NOT NULL,
            PRIMARY KEY (idsite, property_hash, data_date, kind, row_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
        Db::query('CREATE TABLE IF NOT EXISTS `' . Common::prefixTable('neor_gkeys_days') . '` (
            idsite INT UNSIGNED NOT NULL,
            property_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            data_date DATE NOT NULL,
            clicks DOUBLE NOT NULL,
            impressions DOUBLE NOT NULL,
            position_sum DOUBLE NOT NULL,
            capped TINYINT UNSIGNED NOT NULL DEFAULT 0,
            imported_at DATETIME NOT NULL,
            PRIMARY KEY (idsite, property_hash, data_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
    }

    public function registerEvents()
    {
        return [
            'SitesManager.deleteSite.end' => 'deleteSite',
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'AssetManager.getJavaScriptFiles' => 'getJavaScriptFiles',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
        ];
    }

    public function getJavaScriptFiles(&$scripts)
    {
        $scripts[] = 'plugins/NeorGoogleKeywords/javascripts/date-filter.js';
        $scripts[] = 'plugins/NeorGoogleKeywords/javascripts/menu-nav.js';
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = 'NeorGoogleKeywords_ChooseDate';
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = 'plugins/NeorGoogleKeywords/stylesheets/report.css';
        // User-owned overrides are retained on upgrade; the release ZIP does not contain custom.css.
        if (is_file(__DIR__ . '/stylesheets/custom.css')) {
            $stylesheets[] = 'plugins/NeorGoogleKeywords/stylesheets/custom.css';
        }
    }

    public function deleteSite($idSite)
    {
        foreach (['neor_gkeys_rows', 'neor_gkeys_days'] as $table) {
            Db::query('DELETE FROM `' . Common::prefixTable($table) . '` WHERE idsite = ?', [(int) $idSite]);
        }
        \Piwik\Option::delete('NeorGoogleKeywords.log.' . (int) $idSite);
        \Piwik\Option::delete('NeorGoogleKeywords.connection.' . (int) $idSite);
    }

    public function uninstall()
    {
        foreach (['neor_gkeys_rows', 'neor_gkeys_days'] as $table) {
            Db::query('DROP TABLE IF EXISTS `' . Common::prefixTable($table) . '`');
        }
        \Piwik\Option::deleteLike('NeorGoogleKeywords.log.%');
        \Piwik\Option::deleteLike('NeorGoogleKeywords.connection.%');
    }
}

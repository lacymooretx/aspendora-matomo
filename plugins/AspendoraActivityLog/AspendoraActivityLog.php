<?php

namespace Piwik\Plugins\AspendoraActivityLog;

use Piwik\Common;
use Piwik\Db;
use Piwik\Piwik;

class AspendoraActivityLog extends \Piwik\Plugin
{
    public const TABLE = 'aspendora_activity_log';

    /** API modules whose mutating methods get logged. */
    private const WATCHED_MODULES = [
        'UsersManager', 'SitesManager', 'SegmentEditor', 'Goals', 'Annotations',
        'CorePluginsAdmin', 'CoreAdminHome', 'PrivacyManager', 'TwoFactorAuth',
        'ScheduledReports', 'MobileMessaging',
    ];

    /** Method prefixes considered mutations. */
    private const MUTATING_PREFIXES = ['add', 'update', 'delete', 'set', 'remove', 'create', 'change', 'invalidate', 'deactivate', 'activate', 'anonymize', 'regenerate'];

    public function install()
    {
        $table = Common::prefixTable(self::TABLE);
        Db::exec("CREATE TABLE IF NOT EXISTS `$table` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `ts` DATETIME NOT NULL,
            `login` VARCHAR(100) NOT NULL DEFAULT '',
            `event` VARCHAR(190) NOT NULL,
            `detail` TEXT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_ts` (`ts`)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    public function uninstall()
    {
        Db::dropTables([Common::prefixTable(self::TABLE)]);
    }

    public function registerEvents()
    {
        return [
            'Login.authenticate.successful' => 'onLoginSuccess',
            'API.Request.dispatch.end'      => 'onApiDispatchEnd',
        ];
    }

    public function onLoginSuccess($login)
    {
        self::record((string) $login, 'Login.success', 'Successful login');
    }

    public function onApiDispatchEnd(&$returnedValue, $extraInfo)
    {
        $module = $extraInfo['module'] ?? '';
        $method = $extraInfo['action'] ?? '';
        if (!in_array($module, self::WATCHED_MODULES, true)) {
            return;
        }
        $isMutation = false;
        foreach (self::MUTATING_PREFIXES as $prefix) {
            if (stripos($method, $prefix) === 0) {
                $isMutation = true;
                break;
            }
        }
        if (!$isMutation) {
            return;
        }
        $params = $extraInfo['parameters'] ?? [];
        // never persist credential material
        foreach (['password', 'passwordConfirmation', 'password_bis', 'token_auth', 'clientSecret'] as $secret) {
            if (isset($params[$secret])) {
                $params[$secret] = '***';
            }
        }
        $login = 'anonymous';
        try {
            $login = Piwik::getCurrentUserLogin();
        } catch (\Exception $e) {
            // pre-auth context
        }
        self::record($login, $module . '.' . $method, json_encode($params, JSON_UNESCAPED_SLASHES));
    }

    public static function record(string $login, string $event, ?string $detail): void
    {
        try {
            Db::query(
                'INSERT INTO `' . Common::prefixTable(self::TABLE) . '` (ts, login, event, detail) VALUES (NOW(), ?, ?, ?)',
                [mb_substr($login, 0, 100), mb_substr($event, 0, 190), $detail === null ? null : mb_substr($detail, 0, 60000)]
            );
        } catch (\Exception $e) {
            // audit logging must never break the action being audited
        }
    }
}

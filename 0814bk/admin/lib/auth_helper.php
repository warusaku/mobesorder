<?php
/**
 * auth_helper.php
 * 管理画面共通の認証ユーティリティ
 * adminsetting_registrer.php 経由でユーザー情報を取得する
 */

if (!defined('ADMIN_SETTING_INTERNAL_CALL')) {
    define('ADMIN_SETTING_INTERNAL_CALL', true);
}

$adminSettingPath = __DIR__ . '/../adminsetting_registrer.php';
if (file_exists($adminSettingPath)) {
    require_once $adminSettingPath;
} else {
    throw new RuntimeException('adminsetting_registrer.php が見つかりません');
}

if (!function_exists('loadSettings')) {
    throw new RuntimeException('loadSettings 関数が利用できません');
}

/**
 * admin_setting.user 配列を返す
 * @return array [username=>[password,token], ...]
 */
function getAdminUsers(): array {
    $settings = loadSettings();
    if (!$settings || !isset($settings['admin_setting']['user'])) {
        return [];
    }
    return $settings['admin_setting']['user'];
} 
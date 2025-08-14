<?php
/**
 * admin_auth.php
 * 管理画面共通のユーザー認証ユーティリティ。
 * adminsetting_registrer.php 経由で
 * admin/adminpagesetting/adminsetting.json の admin_setting.user を読み取る。
 * 既存ページでは `require_once __DIR__ . '/admin_auth.php';` の後に
 * `$users = getAdminUsers();` で取得できる。
 */

if (!function_exists('getAdminUsers')) {
    function getAdminUsers(): array
    {
        // デバッグ情報をエラーログに出力
        error_log('getAdminUsers() 関数が呼び出されました');
        
        // 他で定義済みでなければ内部呼び出しフラグを立てて読み込む
        if (!defined('ADMIN_SETTING_INTERNAL_CALL')) {
            define('ADMIN_SETTING_INTERNAL_CALL', true);
            error_log('ADMIN_SETTING_INTERNAL_CALL フラグを設定しました');
        }
        
        try {
            $adminSettingPath = __DIR__ . '/adminsetting_registrer.php';
            error_log('adminsetting_registrer.php を読み込みます: ' . $adminSettingPath);
            
            if (!file_exists($adminSettingPath)) {
                error_log('エラー: adminsetting_registrer.php が見つかりません');
                return [];
            }
            
            require_once $adminSettingPath;
            error_log('adminsetting_registrer.php の読み込みが完了しました');
            
            if (function_exists('loadSettings')) {
                error_log('loadSettings() 関数を呼び出します');
                // エラー抑制を削除してエラーを表示できるようにする
                $settings = loadSettings();
                
                if ($settings === false) {
                    error_log('エラー: loadSettings() が false を返しました');
                    // フォールバックへ
                } else if ($settings && isset($settings['admin_setting']['user']) && is_array($settings['admin_setting']['user'])) {
                    error_log('管理者ユーザー情報を正常に取得しました');
                    return $settings['admin_setting']['user']; // [username => [pass, token], ...]
                } else {
                    error_log('エラー: 管理者設定が見つかりません。settings: ' . json_encode($settings));
                    // フォールバックへ
                }
            } else {
                error_log('エラー: loadSettings() 関数が見つかりません');
                // フォールバックへ
            }
            
            // フォールバック処理
            error_log('フォールバック: JSONファイルを直接読み込みます');
            $jsonPath = __DIR__ . '/adminpagesetting/adminsetting.json';
            
            if (!file_exists($jsonPath)) {
                error_log('エラー: ' . $jsonPath . ' が見つかりません');
                // 試しに親ディレクトリも確認
                $altJsonPath = dirname(__DIR__) . '/admin/adminpagesetting/adminsetting.json';
                if (file_exists($altJsonPath)) {
                    error_log('代替パスでJSONファイルを発見: ' . $altJsonPath);
                    $jsonPath = $altJsonPath;
                } else {
                    error_log('エラー: 代替パスにもJSONファイルがありません');
                    return [];
                }
            }
            
            $json = file_get_contents($jsonPath);
            if ($json === false) {
                error_log('エラー: JSONファイルの読み込みに失敗しました');
                return [];
            }
            
            $data = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('エラー: JSON解析エラー: ' . json_last_error_msg());
                return [];
            }
            
            if ($data && isset($data['admin_setting']['user']) && is_array($data['admin_setting']['user'])) {
                error_log('JSONファイルから管理者ユーザー情報を取得しました');
                return $data['admin_setting']['user'];
            } else {
                error_log('エラー: JSONファイルに管理者設定がありません');
            }
        } catch (Exception $e) {
            error_log('例外エラー: ' . $e->getMessage());
        }
        
        error_log('空の配列を返します - 管理者ユーザー情報が取得できませんでした');
        return [];
    }
}
?> 
<?php
/**
 * 商品表示設定の保存処理
 * 
 * このスクリプトは、商品表示関連の設定を保存するためのAPIとして機能します。
 * 現在は直リンク設定の保存をサポートしています。
 * 
 * @version 1.0.0
 * @author FG Development Team
 */

// 設定ファイルを読み込み
$rootPath = realpath(__DIR__ . '/..');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Utils.php';

// セッション開始
session_start();

// ログ関数
function logMessage($message, $level = 'INFO') {
    Utils::log($message, $level, 'SaveDisplaySettings');
}

// ユーザー認証チェック
if (!isset($_SESSION['auth_user'])) {
    echo json_encode([
        'success' => false,
        'message' => '認証されていません。ログインしてください。'
    ]);
    exit;
}

// レスポンスヘッダー設定
header('Content-Type: application/json');

// リクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'save_direct_link':
            saveLinkSettings();
            break;
        default:
            echo json_encode([
                'success' => false,
                'message' => '無効なアクションです'
            ]);
            break;
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => '無効なリクエストメソッドです'
    ]);
}

/**
 * 直リンク設定を保存
 */
function saveLinkSettings() {
    $settingsJson = $_POST['settings'] ?? '';
    
    if (empty($settingsJson)) {
        echo json_encode([
            'success' => false,
            'message' => '設定データが提供されていません'
        ]);
        return;
    }
    
    try {
        $settings = json_decode($settingsJson, true);
        if (!$settings || !isset($settings['direct_link']) || !isset($settings['direct_link']['base_url'])) {
            throw new Exception('無効な設定データです');
        }
        
        // 設定ファイルのパス
        $settingsDir = __DIR__ . '/adminpagesetting';
        $settingsFile = $settingsDir . '/product_display_setting.json';
        
        // ディレクトリが存在しなければ作成
        if (!file_exists($settingsDir)) {
            if (!mkdir($settingsDir, 0755, true)) {
                throw new Exception('設定ディレクトリの作成に失敗しました');
            }
        }
        
        // 既存の設定をマージ
        $existingSettings = [];
        if (file_exists($settingsFile)) {
            $existingContent = file_get_contents($settingsFile);
            $existingSettings = json_decode($existingContent, true) ?: [];
        }
        
        // 新しい設定をマージ
        $mergedSettings = array_merge($existingSettings, $settings);
        
        // ファイルに保存
        if (file_put_contents($settingsFile, json_encode($mergedSettings, JSON_PRETTY_PRINT))) {
            logMessage("直リンク設定を保存しました: " . $settings['direct_link']['base_url']);
            echo json_encode([
                'success' => true,
                'message' => '設定を保存しました'
            ]);
        } else {
            throw new Exception('設定ファイルの書き込みに失敗しました');
        }
    } catch (Exception $e) {
        logMessage("設定保存エラー: " . $e->getMessage(), 'ERROR');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} 
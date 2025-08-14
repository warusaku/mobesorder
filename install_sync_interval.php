<?php
/**
 * product_sync_intervalの設定をシステム設定テーブルに登録するスクリプト
 */

// 初期化ファイルの読み込み
require_once __DIR__ . '/lib/init.php';

// 実行確認メッセージ
echo "product_sync_interval設定を登録または更新します。\n";
echo "間隔は900秒(15分)に設定されます。\n";
echo "続行するには「y」を入力してください: ";
$input = strtolower(trim(fgets(STDIN)));

if ($input !== 'y') {
    echo "処理を中止しました。\n";
    exit;
}

try {
    // データベース接続
    $db = Database::getInstance();
    
    // system_settingsテーブルの存在確認
    $tableExists = false;
    $tables = $db->select("SHOW TABLES LIKE 'system_settings'");
    $tableExists = !empty($tables);
    
    if (!$tableExists) {
        echo "system_settingsテーブルが存在しません。テーブルを作成します。\n";
        
        // テーブルの作成
        $db->execute("
            CREATE TABLE system_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        echo "system_settingsテーブルを作成しました。\n";
    }
    
    // 既存の設定を確認
    $existingSetting = $db->selectOne(
        "SELECT id, setting_value FROM system_settings WHERE setting_key = ?", 
        ['product_sync_interval']
    );
    
    if ($existingSetting) {
        // 既存の設定を更新
        $db->execute(
            "UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?",
            ['900', 'product_sync_interval']
        );
        
        echo "既存の設定を更新しました。'product_sync_interval' => '900'\n";
    } else {
        // 新規設定を追加
        $db->execute(
            "INSERT INTO system_settings (setting_key, setting_value, created_at, updated_at)
             VALUES (?, ?, NOW(), NOW())",
            ['product_sync_interval', '900']
        );
        
        echo "新規設定を追加しました。'product_sync_interval' => '900'\n";
    }
    
    echo "完了しました。設定が適用されるまでに時間がかかる場合があります。\n";
    
} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
    exit(1);
} 
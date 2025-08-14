<?php
/**
 * product_sync_intervalの設定をシステム設定テーブルに登録するシンプルなSQL実行スクリプト
 */

echo "以下のSQLを実行して商品同期間隔を設定します：\n\n";

echo "-- product_sync_intervalの設定を追加または更新\n";
echo "INSERT INTO system_settings (setting_key, setting_value, created_at, updated_at)\n";
echo "VALUES ('product_sync_interval', '900', NOW(), NOW())\n";
echo "ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),\n";
echo "                        updated_at = NOW();\n\n";

echo "このSQLをphpMyAdminで実行してください。\n";
echo "これにより、商品同期の間隔が15分（900秒）に設定されます。\n";
?> 
#!/bin/bash

# heartbeat.sh
# RTSPリーダーシステム用ハートビートクライアント実行スクリプト
# cronで定期的に実行する

# スクリプトの絶対パスを取得
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
LOG_FILE="$PROJECT_ROOT/logs/cron.log"

# ログディレクトリの存在確認
mkdir -p "$PROJECT_ROOT/logs"
mkdir -p "$PROJECT_ROOT/data"

# ログ関数
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# LacisIDの設定（実際の環境では設定ファイルから読み込む）
# 設定ファイルが存在する場合は、そこから読み込む
CONFIG_FILE="$PROJECT_ROOT/config/device.conf"
LACIS_ID="LACIS_DEFAULT"

if [ -f "$CONFIG_FILE" ]; then
    # 設定ファイルからLacisIDを読み込む
    LACIS_ID=$(grep -oP 'LACIS_ID=\K.*' "$CONFIG_FILE" 2>/dev/null || echo "LACIS_DEFAULT")
fi

log_message "ハートビートクライアント実行開始: LacisID=$LACIS_ID"

# PHPスクリプトのパス
PHP_SCRIPT="$PROJECT_ROOT/api/heartbeat_client.php"

# PHPの存在確認
if ! command -v php &> /dev/null; then
    log_message "エラー: PHPがインストールされていません"
    exit 1
fi

# PHPスクリプトの存在確認
if [ ! -f "$PHP_SCRIPT" ]; then
    log_message "エラー: ハートビートクライアントスクリプトが見つかりません: $PHP_SCRIPT"
    exit 1
fi

# PHPスクリプトを実行
result=$(php "$PHP_SCRIPT" "lacis_id=$LACIS_ID" 2>&1)
exit_code=$?

# 結果の確認
if [ $exit_code -eq 0 ]; then
    log_message "ハートビートクライアント実行成功"
    
    # JSON出力から設定変更通知があるかチェック
    if echo "$result" | grep -q "config_changes"; then
        log_message "設定変更通知が検出されました："
        echo "$result" | grep -o '"config_changes":[^}]*}' >> "$LOG_FILE"
        
        # 設定更新のトリガースクリプトがあれば実行
        UPDATE_SCRIPT="$PROJECT_ROOT/cron/update_config.sh"
        if [ -f "$UPDATE_SCRIPT" ] && [ -x "$UPDATE_SCRIPT" ]; then
            log_message "設定更新スクリプトを実行します: $UPDATE_SCRIPT"
            "$UPDATE_SCRIPT"
        fi
    fi
else
    log_message "ハートビートクライアント実行失敗: 終了コード $exit_code"
    log_message "エラー出力: $result"
fi

log_message "ハートビートクライアント実行完了"
exit $exit_code 
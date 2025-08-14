#!/bin/bash

# update_config.sh
# RTSPリーダーシステム用設定更新スクリプト
# クラウドサーバーから受け取った設定変更を適用する

# スクリプトの絶対パスを取得
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
LOG_FILE="$PROJECT_ROOT/logs/config_update.log"
CONFIG_DIR="$PROJECT_ROOT/config"
DATA_DIR="$PROJECT_ROOT/data"

# ログディレクトリの存在確認
mkdir -p "$PROJECT_ROOT/logs"
mkdir -p "$CONFIG_DIR"
mkdir -p "$DATA_DIR"

# ログ関数
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# LacisIDの設定（実際の環境では設定ファイルから読み込む）
CONFIG_FILE="$CONFIG_DIR/device.conf"
LACIS_ID="LACIS_DEFAULT"

if [ -f "$CONFIG_FILE" ]; then
    # 設定ファイルからLacisIDを読み込む
    LACIS_ID=$(grep -oP 'LACIS_ID=\K.*' "$CONFIG_FILE" 2>/dev/null || echo "LACIS_DEFAULT")
fi

log_message "設定更新プロセス開始: LacisID=$LACIS_ID"

# 設定更新用PHPスクリプトのパス
SYNC_SCRIPT="$PROJECT_ROOT/api/sync_client.php"

# PHPの存在確認
if ! command -v php &> /dev/null; then
    log_message "エラー: PHPがインストールされていません"
    exit 1
fi

# PHPスクリプトの存在確認
if [ ! -f "$SYNC_SCRIPT" ]; then
    log_message "エラー: 同期クライアントスクリプトが見つかりません: $SYNC_SCRIPT"
    # 存在しない場合は、簡易的な設定更新関数を使用
    log_message "簡易設定更新機能を使用します"
    
    # 設定APIエンドポイントのURL
    CONFIG_API_URL="https://example.com/rtsp_reader/api/config.php"
    CONFIG_SECURITY_KEY="rtsp_test"
    CONFIG_FILE_PATH="$CONFIG_DIR/device_config.json"
    
    # 設定ファイルをダウンロード
    log_message "設定ファイルを取得中: $CONFIG_API_URL?lacis_id=$LACIS_ID&key=$CONFIG_SECURITY_KEY"
    
    # curlがインストールされているか確認
    if command -v curl &> /dev/null; then
        # curlで設定をダウンロード
        curl -s "$CONFIG_API_URL?lacis_id=$LACIS_ID&key=$CONFIG_SECURITY_KEY" -o "$DATA_DIR/temp_config.json"
        curl_exit=$?
        
        if [ $curl_exit -eq 0 ] && [ -f "$DATA_DIR/temp_config.json" ]; then
            # JSONの構文チェック（jqがあれば）
            if command -v jq &> /dev/null; then
                jq . "$DATA_DIR/temp_config.json" > /dev/null 2>&1
                jq_exit=$?
                
                if [ $jq_exit -eq 0 ]; then
                    log_message "有効なJSON設定ファイルを受信しました"
                    # 設定ファイルをバックアップ
                    if [ -f "$CONFIG_FILE_PATH" ]; then
                        cp "$CONFIG_FILE_PATH" "$CONFIG_FILE_PATH.bak"
                        log_message "既存の設定ファイルをバックアップしました: $CONFIG_FILE_PATH.bak"
                    fi
                    
                    # 新しい設定ファイルを適用
                    mv "$DATA_DIR/temp_config.json" "$CONFIG_FILE_PATH"
                    log_message "新しい設定ファイルを適用しました: $CONFIG_FILE_PATH"
                    
                    # 設定適用完了フラグファイルを作成
                    echo "$(date '+%Y-%m-%d %H:%M:%S')" > "$DATA_DIR/config_updated.txt"
                    
                    # サービス再起動が必要な場合は、適切なコマンドを追加
                    log_message "設定更新が完了しました。サービス再起動が必要かもしれません。"
                else
                    log_message "エラー: 無効なJSON設定ファイルを受信しました"
                    rm "$DATA_DIR/temp_config.json"
                fi
            else
                # jqがなくても一応移動
                log_message "警告: jqがインストールされていないため、JSON検証をスキップします"
                
                # 設定ファイルをバックアップ
                if [ -f "$CONFIG_FILE_PATH" ]; then
                    cp "$CONFIG_FILE_PATH" "$CONFIG_FILE_PATH.bak"
                    log_message "既存の設定ファイルをバックアップしました: $CONFIG_FILE_PATH.bak"
                fi
                
                # 新しい設定ファイルを適用
                mv "$DATA_DIR/temp_config.json" "$CONFIG_FILE_PATH"
                log_message "新しい設定ファイルを適用しました: $CONFIG_FILE_PATH"
                
                # 設定適用完了フラグファイルを作成
                echo "$(date '+%Y-%m-%d %H:%M:%S')" > "$DATA_DIR/config_updated.txt"
            fi
        else
            log_message "エラー: 設定ファイルのダウンロードに失敗しました"
        fi
    else
        log_message "エラー: curlがインストールされていません"
        exit 1
    fi
else
    # 同期クライアントスクリプトを実行
    log_message "同期クライアントスクリプトを実行します: $SYNC_SCRIPT"
    result=$(php "$SYNC_SCRIPT" "lacis_id=$LACIS_ID" 2>&1)
    exit_code=$?
    
    # 結果の確認
    if [ $exit_code -eq 0 ]; then
        log_message "設定更新成功"
        log_message "応答: $result"
        
        # 設定適用完了フラグファイルを作成
        echo "$(date '+%Y-%m-%d %H:%M:%S')" > "$DATA_DIR/config_updated.txt"
    else
        log_message "設定更新失敗: 終了コード $exit_code"
        log_message "エラー出力: $result"
    fi
fi

log_message "設定更新プロセス完了"
exit 0 
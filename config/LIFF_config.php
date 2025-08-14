<?php
/**
 * LIFF モバイルオーダーシステム設定ファイル
 * LINE Front-end Framework (LIFF) の設定情報を管理します
 */

// LINE LIFF設定
define('LIFF_ID', '2007360690-Da3WzGrJ'); // LINE DevelopersコンソールのLIFFタブで取得したIDを設定
define('LIFF_CHANNEL_ID', '2007360690'); // LINE Developersコンソールで取得したチャネルID
define('LIFF_CHANNEL_SECRET', 'e595857416a47f74bc64cdc0d608aecc'); // LINE Developersコンソールで取得したチャネルシークレット

// LINE Messaging API設定 (通知機能用)
define('LINE_BOT_CHANNEL_TOKEN', '2007361239'); // LINE Developersコンソールで取得したチャネルアクセストークン
define('LINE_BOT_CHANNEL_SECRET', '8e238742291b39328af58c8546e89d94'); // LINE Developersコンソールで取得したチャネルシークレット

// LIFF URL設定
define('LIFF_ENDPOINT_URL', 'https://mobes.online/order/'); // 移行後 LIFF エンドポイント URL

// デバッグモード
define('LIFF_DEBUG_MODE', true); // 開発中はtrue、本番環境ではfalseに設定

// セキュリティ設定
define('LIFF_TOKEN_EXPIRY', 86400); // アクセストークンの有効期限（秒）、デフォルト24時間

// LINEユーザーと部屋番号の紐づけに関する設定
define('ROOM_LINK_EXPIRY', 259200); // 部屋紐づけの有効期限（秒）、デフォルト3日間
define('ROOM_LINK_AUTO_CLEANUP', true); // チェックアウト時に自動的に紐づけを解除するか

/**
 * 環境依存の設定
 * 本番環境と開発環境で異なる設定を環境変数またはファイルパスから読み込む
 */
$env_file = __DIR__ . '/LIFF_env.php';
if (file_exists($env_file)) {
    require_once $env_file;
}

/**
 * ヘルパー関数
 */

/**
 * LIFFのURLを生成する
 * @return string 完全なLIFF URL
 */
function getLiffUrl() {
    return 'https://liff.line.me/' . LIFF_ID;
}

/**
 * LIFFのステータスを文字列で返す
 * @return string 現在の環境（'development' または 'production'）
 */
function getLiffEnvironment() {
    return LIFF_DEBUG_MODE ? 'development' : 'production';
} 
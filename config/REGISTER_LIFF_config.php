<?php
/**
 * 部屋番号登録アプリ用のLIFF設定ファイル
 * LINE Front-end Framework (LIFF) の設定情報を管理します
 */

// LINE LIFF設定
define('REGISTER_LIFF_ID', '2007363986-nMAv6J8w'); // 【注意】実際のLIFF IDに置き換えてください
define('REGISTER_LIFF_CHANNEL_ID', '2007363986'); // 【注意】実際のチャネルIDに置き換えてください
define('REGISTER_LIFF_CHANNEL_SECRET', 'fe2cc992e5c47b69312f328d4675280a'); // 【注意】実際のチャネルシークレットに置き換えてください

// API基本URL
define('API_BASE_URL', 'https://mobes.online');

// LINE Messaging API設定 (通知機能用)
define('LINE_CHANNEL_ACCESS_TOKEN', ''); // 必要に応じて設定
define('LINE_CHANNEL_SECRET', ''); // 必要に応じて設定

// LIFF URL設定
define('REGISTER_LIFF_ENDPOINT_URL', 'https://mobes.online/register/'); // 登録アプリのエンドポイントURL

// デバッグモード
define('REGISTER_LIFF_DEBUG_MODE', true); // 開発中はtrue、本番環境ではfalseに設定

// セキュリティ設定
define('REGISTER_LIFF_TOKEN_EXPIRY', 86400); // アクセストークンの有効期限（秒）、デフォルト24時間

// LINEユーザーと部屋番号の紐づけに関する設定
define('ROOM_LINK_EXPIRY', 259200); // 部屋紐づけの有効期限（秒）、デフォルト3日間
define('ROOM_LINK_AUTO_CLEANUP', true); // チェックアウト時に自動的に紐づけを解除するか

/**
 * 環境依存の設定
 * 本番環境と開発環境で異なる設定を環境変数またはファイルパスから読み込む
 */
$env_file = __DIR__ . '/REGISTER_LIFF_env.php';
if (file_exists($env_file)) {
    require_once $env_file;
}

/**
 * ヘルパー関数
 */

/**
 * 登録アプリのLIFFのURLを生成する
 * @return string 完全なLIFF URL
 */
function getRegisterLiffUrl() {
    return 'https://liff.line.me/' . REGISTER_LIFF_ID;
}

/**
 * 登録アプリのLIFFのステータスを文字列で返す
 * @return string 現在の環境（'development' または 'production'）
 */
function getRegisterLiffEnvironment() {
    return REGISTER_LIFF_DEBUG_MODE ? 'development' : 'production';
}
// PHPの終了タグは省略 - PHPファイルの末尾に余分な空白や改行があると問題が起きることがあります 
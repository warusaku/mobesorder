<?php
/**
 * LacisMobileOrder システム設定ファイル
 * 
 * このファイルには、システム全体の設定が含まれています。
 * 本番環境では、機密情報は環境変数から読み込むことをお勧めします。
 */

/**
 * デバッグモード設定
 * 本番環境では必ずfalseにしてください
 */
define('DEBUG_MODE', false);

/**
 * 開発モード設定
 * 本番環境では必ずfalseにしてください
 * DEBUG_MODEとDEVELOPMENT_MODEの両方がtrueの場合のみ詳細なデバッグ出力が有効になります
 */
define('DEVELOPMENT_MODE', false);

// データベース設定
// DB_HOST: データベースサーバーのホスト名またはIPアドレス
//   - 通常は 'localhost' または '127.0.0.1'
//   - リモートデータベースの場合はそのホスト名
define('DB_HOST', 'mysql320.phy.lolipop.lan');

// DB_NAME: 使用するデータベースの名前
//   - 事前にMySQLで作成しておく必要があります
//   - 例: 'lacis_mobile_order'
define('DB_NAME', 'LAA1207717-fgsquare');

// DB_USER: データベースへの接続ユーザー名
//   - このユーザーには、データベースへの全権限が必要です
//   - 例: 'lacis_user'
define('DB_USER', 'LAA1207717');

// DB_PASS: データベース接続パスワード
//   - 強力なパスワードを使用してください
//   - 本番環境では環境変数から取得するなど安全な方法で管理
define('DB_PASS', 'fg12345');

// Square API設定
// SQUARE_ACCESS_TOKEN: Square APIへのアクセストークン
//   - Square Developer Dashboardで取得できます
//   - https://developer.squareup.com/apps
//   - 本番環境と開発環境で異なるトークンを使用します
define('SQUARE_ACCESS_TOKEN', 'EAAAl1GbmuKdDbydgBaPhPszRMhrdbFEfJ0dvljwgveuIvvPBkhEzme3a80-iq6h');

// SQUARE_LOCATION_ID: Square店舗のロケーションID
//   - Square Dashboardの「ロケーション」から確認できます
//   - 複数店舗がある場合は、対象店舗のIDを指定
define('SQUARE_LOCATION_ID', 'L7161EYN8GM9H');

// SQUARE_ENVIRONMENT: Square APIの環境設定
//   - 'sandbox': テスト環境（実際の決済は発生しません）
//   - 'production': 本番環境（実際の決済が発生します）
define('SQUARE_ENVIRONMENT', 'production');

// SQUARE_APP_ID: Square Web支払いSDK用のアプリケーションID
//   - Square Developer Dashboardで取得できます
//   - 本番環境と開発環境で異なるIDを使用します
define('SQUARE_APP_ID', 'sq0idp-HVcgP_GBfpKWY7xmgIZg1w');

// SQUARE_WEBHOOK_SIGNATURE_KEY: Square Webhookの署名検証キー
//   - Square Developer Dashboardで設定したWebhookの署名キー
//   - Webhookのセキュリティ検証に使用されます
define('SQUARE_WEBHOOK_SIGNATURE_KEY', 'CeWJJuQ7mPtN4aMka1y6RA');

// LINE Messaging API設定
// LINE_CHANNEL_SECRET: LINEチャネルシークレット
//   - LINE Developers Consoleで取得できます
//   - https://developers.line.biz/console/
//   - Webhookの署名検証に使用されます
define('LINE_CHANNEL_SECRET', '8e238742291b39328af58c8546e89d94');

// LINE_CHANNEL_ACCESS_TOKEN: LINEチャネルアクセストークン
//   - LINE Developers Consoleで取得できます
//   - メッセージ送信などのAPI操作に使用されます
define('LINE_CHANNEL_ACCESS_TOKEN', '2007361239');

// アプリケーション設定
// BASE_URL: APIのベースURL
//   - システムがデプロイされるURLのベースパス
//   - 例: 'https://your-domain.com/api'
//   - LINE通知のリンクURLなどに使用されます
define('BASE_URL', 'https://test-mijeos.but.jp/fgsquare/api');

// CORS_ALLOWED_ORIGINS: CORSで許可するオリジン（カンマ区切り）
//   - フロントエンドアプリケーションのドメインを指定
//   - 開発環境と本番環境の両方を含めることができます
define('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,https://test-mijeos.but.jp');

// TOKEN_LENGTH: 部屋トークンの長さ
//   - 生成される認証トークンの文字数
//   - 長すぎると入力が面倒、短すぎるとセキュリティリスクが高まります
//   - 推奨: 6〜8文字
define('TOKEN_LENGTH', 6);

// TOKEN_EXPIRY_DAYS: トークンの有効期限（日数）
//   - 生成されたトークンが無効になるまでの日数
//   - 通常は宿泊期間より少し長めに設定
//   - 例: 7日間
define('TOKEN_EXPIRY_DAYS', 7);

// ログ設定
// LOG_LEVEL: ログに記録するメッセージの最小レベル
//   - 'DEBUG': すべてのメッセージ（開発環境向け）
//   - 'INFO': 情報、警告、エラーメッセージ
//   - 'WARNING': 警告とエラーメッセージのみ
//   - 'ERROR': エラーメッセージのみ
define('LOG_LEVEL', 'DEBUG');

// LOG_FILE: ログファイルのパス
//   - ログが書き込まれるファイルの絶対パス
//   - このディレクトリには書き込み権限が必要です
define('LOG_FILE', __DIR__ . '/../logs/app.log');

// タイムゾーン設定
// 日本の場合は 'Asia/Tokyo' を指定
date_default_timezone_set('Asia/Tokyo');

// セッション設定（管理画面用）
// セキュリティ強化のためのセッション設定
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if (!DEBUG_MODE) {
    ini_set('session.cookie_secure', 1);
}

// エラー表示設定
// 開発環境と本番環境でエラー表示を切り替え
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
}

// ADMIN_KEY: 管理者APIアクセス用のキー
//   - 管理者専用APIエンドポイントへのアクセスに使用
//   - 強力なパスワードを使用してください安
define('ADMIN_KEY', 'fg12345'); // これは実装段階をスムーズに行うための仮のパスワードです

// SYNC_TOKEN: 同期スクリプト用のアクセストークン
//   - 商品・カテゴリ同期APIへのアクセスに使用
//   - ブラウザからのアクセス時の認証に使用されます
define('SYNC_TOKEN', 'fg12345@');

/**
 * 環境変数から設定を読み込む（本番環境用）
 * 
 * .envファイルまたはサーバー環境変数から設定を読み込みます。
 * 本番環境では、機密情報をソースコードに直接記述せず、
 * 環境変数として管理することをお勧めします。
 */
function loadEnvConfig() {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // 引用符を削除
                if (preg_match('/^"(.+)"$/', $value, $matches)) {
                    $value = $matches[1];
                } elseif (preg_match("/^'(.+)'$/", $value, $matches)) {
                    $value = $matches[1];
                }
                
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// 本番環境では環境変数から設定を読み込む
if (!DEBUG_MODE) {
    loadEnvConfig();
    
    // 環境変数から設定を上書き
    if (getenv('DB_HOST')) define('DB_HOST', getenv('DB_HOST'));
    if (getenv('DB_NAME')) define('DB_NAME', getenv('DB_NAME'));
    if (getenv('DB_USER')) define('DB_USER', getenv('DB_USER'));
    if (getenv('DB_PASS')) define('DB_PASS', getenv('DB_PASS'));
    if (getenv('SQUARE_ACCESS_TOKEN')) define('SQUARE_ACCESS_TOKEN', getenv('SQUARE_ACCESS_TOKEN'));
    if (getenv('SQUARE_LOCATION_ID')) define('SQUARE_LOCATION_ID', getenv('SQUARE_LOCATION_ID'));
    if (getenv('SQUARE_ENVIRONMENT')) define('SQUARE_ENVIRONMENT', getenv('SQUARE_ENVIRONMENT'));
    if (getenv('SQUARE_APP_ID')) define('SQUARE_APP_ID', getenv('SQUARE_APP_ID'));
    if (getenv('SQUARE_WEBHOOK_SIGNATURE_KEY')) define('SQUARE_WEBHOOK_SIGNATURE_KEY', getenv('SQUARE_WEBHOOK_SIGNATURE_KEY'));
    if (getenv('LINE_CHANNEL_SECRET')) define('LINE_CHANNEL_SECRET', getenv('LINE_CHANNEL_SECRET'));
    if (getenv('LINE_CHANNEL_ACCESS_TOKEN')) define('LINE_CHANNEL_ACCESS_TOKEN', getenv('LINE_CHANNEL_ACCESS_TOKEN'));
    if (getenv('BASE_URL')) define('BASE_URL', getenv('BASE_URL'));
    if (getenv('CORS_ALLOWED_ORIGINS')) define('CORS_ALLOWED_ORIGINS', getenv('CORS_ALLOWED_ORIGINS'));
} 
<?php
require_once '../config/REGISTER_LIFF_config.php';
require_once '../api/lib/Utils.php';

// アクセスログ
if (defined('REGISTER_LIFF_DEBUG_MODE') && REGISTER_LIFF_DEBUG_MODE) {
    $logDir = __DIR__ . '/../logs';
    $logFile = $logDir . '/register_access.log';
    
    // ログディレクトリ作成
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // ログファイルサイズチェック (200KB超過で削除)
    if (file_exists($logFile) && filesize($logFile) > 204800) {
        unlink($logFile);
    }
    
    // アクセスログ記録
    $logMessage = "部屋番号登録アクセス: " . date('Y-m-d H:i:s') . " - " . $_SERVER['REMOTE_ADDR'];
    
    if (function_exists('Utils::log')) {
        Utils::log($logMessage, 'INFO', 'RegisterAccess');
    } else {
        file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
    }
}

// QRコードからのアクセスフラグ
$isQrAccess = isset($_GET['qr']) ? true : false;

// LINEアプリ外ブラウザで開かれた場合は line://app スキームへリダイレクト
$liffId = defined('REGISTER_LIFF_ID') ? REGISTER_LIFF_ID : '2007363986-nMAv6J8w';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

// リダイレクトループ防止: LIFF SDKがリダイレクト後のパラメータを付与するため、それらの存在をチェック
$isLiffCallback = isset($_GET['liff_state']) || isset($_GET['liff.state']) || isset($_GET['code']) || isset($_GET['liff.linked']);

// セッション開始（リダイレクト制御のため）
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// リダイレクト履歴の確認
$redirectKey = 'liff_redirect_count_' . $liffId;
$lastRedirectTime = $_SESSION[$redirectKey . '_time'] ?? 0;
$currentTime = time();

// 5分経過していたらリセット
if ($currentTime - $lastRedirectTime > 300) {
    unset($_SESSION[$redirectKey]);
    unset($_SESSION[$redirectKey . '_time']);
}

$redirectCount = $_SESSION[$redirectKey] ?? 0;

// LINEアプリ内でない、かつLIFFコールバックでない、かつリダイレクト回数が2回未満の場合のみリダイレクト
if (strpos($ua, 'Line/') === false && !$isLiffCallback && $redirectCount < 2) {
    // リダイレクト回数を増加
    $_SESSION[$redirectKey] = $redirectCount + 1;
    $_SESSION[$redirectKey . '_time'] = $currentTime;
    
    // クエリストリングを維持して LINE アプリへ渡す
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    $lineUrl = 'https://liff.line.me/' . $liffId . ($qs ? '?' . $qs : '');
    
    // デバッグ情報をログに記録
    if (defined('REGISTER_LIFF_DEBUG_MODE') && REGISTER_LIFF_DEBUG_MODE) {
        $logMessage = "リダイレクト実行: " . date('Y-m-d H:i:s') . " - Count: " . ($redirectCount + 1) . " - URL: " . $lineUrl;
        file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
    }
    
    header('Location: ' . $lineUrl, true, 302);
    exit;
}

// リダイレクト上限に達した場合のメッセージを追加
$reachedRedirectLimit = ($redirectCount >= 2 && strpos($ua, 'Line/') === false && !$isLiffCallback);

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>部屋番号登録</title>
    <meta name="description" content="LINEアカウントと部屋番号を紐づけるための登録ページです">
    <meta name="theme-color" content="#4CAF50">
    
    <!-- キャッシュ制御 -->
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    
    <!-- LIFF SDK プリロード -->
    <link rel="preconnect" href="https://static.line-scdn.net">
    <link rel="dns-prefetch" href="https://static.line-scdn.net">
    
    <!-- LIFF SDK メイン -->
    <script charset="utf-8" src="https://static.line-scdn.net/liff/edge/2/sdk.js" 
            onload="window.liffSdkLoaded = true"></script>
    
    <!-- LIFF SDK フォールバック -->
    <script>
        window.liffLoadAttempts = 0;
        window.liffSdkLoaded = false;
        
        function handleLiffLoadError() {
            console.error('LIFF SDKの読み込みに失敗しました。再試行します...');
            window.liffLoadAttempts++;
            
            if (window.liffLoadAttempts < 3) {
                // 代替CDNから読み込みを試行
                const fallbackScript = document.createElement('script');
                fallbackScript.charset = 'utf-8';
                
                // バージョンを指定して読み込み
                const versions = ['2.22', '2.21', '2.20'];
                const version = versions[window.liffLoadAttempts - 1] || '2';
                
                fallbackScript.src = `https://static.line-scdn.net/liff/edge/${version}/sdk.js`;
                fallbackScript.onerror = handleLiffLoadError;
                fallbackScript.onload = function() {
                    window.liffSdkLoaded = true;
                    console.log('LIFF SDKの読み込みに成功しました（フォールバック）');
                };
                
                document.head.appendChild(fallbackScript);
            } else {
                // 全ての試行が失敗した場合
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('loading').style.display = 'none';
                    document.getElementById('error-container').style.display = 'flex';
                    document.getElementById('error-message').innerHTML = `
                        <div style="text-align: center;">
                            <p style="font-size: 18px; margin-bottom: 20px;">システムエラー</p>
                            <p style="margin-bottom: 20px;">LINEシステムとの接続に失敗しました。</p>
                            <p style="margin-bottom: 20px;">以下をお試しください：</p>
                            <ul style="text-align: left; display: inline-block; margin-bottom: 20px;">
                                <li>LINEアプリを最新版にアップデート</li>
                                <li>端末を再起動</li>
                                <li>Wi-Fi/モバイルデータを切り替え</li>
                                <li>LINEアプリのキャッシュをクリア</li>
                            </ul>
                            <button onclick="window.location.reload()" 
                                    style="background-color: #00B900; color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer;">
                                再試行
                            </button>
                        </div>
                    `;
                    document.getElementById('retry-button').style.display = 'none';
                });
            }
        }
        
        // SDKロード監視
        setTimeout(function() {
            // 実際にSDKが読み込まれていない場合のみエラー処理を実行
            if (!window.liffSdkLoaded && window.liffLoadAttempts === 0 && typeof liff === 'undefined') {
                handleLiffLoadError();
            }
        }, 3000);
    </script>
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/picker.css?v=<?php echo time(); ?>">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- Picker.js (ドラムロール用ライブラリ) -->
    <script src="js/picker.js?v=<?php echo time(); ?>"></script>
</head>
<body>
    <div id="app">
        <div id="loading">
            <div class="spinner"></div>
            <p>読み込み中...</p>
        </div>

        <div id="error-container" style="display: none;">
            <div class="error-content">
                <i class="fas fa-exclamation-circle"></i>
                <h2>エラーが発生しました</h2>
                <p id="error-message"></p>
                <button id="retry-button">再試行</button>
            </div>
        </div>

        <div id="content-container" style="display: none;">
            <!-- ヘッダー -->
            <header>
                <div class="header-content">
                    <div class="header-left">
                        <img src="../order/images/logo.svg" alt="FG Square Logo" class="header-logo">
                        <img src="../order/images/title.svg" alt="部屋番号登録" class="header-title">
                    </div>
                    <div class="user-info-badge">
                        <img id="profile-image-small" src="https://mobes.online/images/default-profile.png" alt="プロフィール画像">
                        <span id="user-name-small">...</span>
                    </div>
                </div>
            </header>

            <main>
                <div class="register-container">
                    <div class="register-card">
                        <div class="register-header">
                            <h2>部屋番号登録</h2>
                            <p>LINEでモバイルオーダーを利用するには部屋番号登録が必要です</p>
                        </div>
                        
                        <div class="user-info">
                            <div class="profile-image">
                                <img id="profile-image" src="https://mobes.online/images/default-profile.png" alt="プロフィール画像">
                            </div>
                            <div class="profile-name">
                                <span id="user-name">ユーザー名</span>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <form id="room-register-form">
                                <div class="form-group">
                                    <label for="room-select-button">部屋番号</label>
                                    <button type="button" id="room-select-button" class="room-select-button">
                                        部屋番号を選択してください
                                    </button>
                                    <input type="hidden" id="room-number" name="room-number" required>
                                    <div id="selected-room-display" class="selected-room-display">
                                        選択：<span id="selected-room">未選択</span>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="user-name-input">ご利用者様氏名(LINEIDでも可)</label>
                                    <input type="text" id="user-name-input" name="user-name" class="form-control" placeholder="例: 山田 太郎" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="check-in-date">チェックイン日</label>
                                    <input type="hidden" id="check-in-date" name="check-in-date">
                                    <div class="form-text">チェックイン日は本日の日付が自動入力されます</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="check-out-date">チェックアウト日時</label>
                                    <div class="checkout-time-container">
                                        <input type="date" id="check-out-date" name="check-out-date" class="form-control" required>
                                        <select id="checkout-time" name="checkout-time" class="form-control">
                                            <option value="10:00">10:00</option>
                                            <option value="11:00" selected>11:00</option>
                                            <option value="12:00">12:00</option>
                                            <option value="13:00">13:00</option>
                                            <option value="14:00">14:00</option>
                                            <option value="15:00">15:00</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" id="register-button" class="register-button">設定する</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>

            <!-- 登録完了モーダル -->
            <div id="register-complete-modal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>登録が完了しました</h2>
                    </div>
                    <div class="modal-body">
                        <div class="register-complete-message">
                            <i class="fas fa-check-circle"></i>
                            <p>部屋番号の登録が完了しました。</p>
                            <p>モバイルオーダーをご利用いただけます。</p>
                        </div>
                        <div class="room-info">
                            <p>部屋番号: <span id="registered-room-number"></span></p>
                            <p>ご利用者様: <span id="registered-user-name"></span></p>
                            <p>滞在期間: <span id="registered-stay-period"></span></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button id="go-to-order-button" class="checkout-button">モバイルオーダーを開く</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- メッセージ表示エリア -->
    <div id="message-container" style="display: none;"></div>

    <!-- デバッグ情報表示エリア -->
    <div id="debug-container" style="display: none; margin-top: 20px; padding: 10px; background-color: #f8f9fa; border-radius: 5px;">
        <h3>デバッグ情報</h3>
        <pre id="debug-output"></pre>
    </div>

    <!-- JavaScript -->
    <script>
        // QRコードからのアクセスフラグをJavaScriptに渡す
        window.isQrAccess = <?php echo $isQrAccess ? 'true' : 'false'; ?>;
        
        // リダイレクト上限に達しているかどうか
        window.reachedRedirectLimit = <?php echo $reachedRedirectLimit ? 'true' : 'false'; ?>;
        
        // リダイレクト上限に達している場合の処理
        if (window.reachedRedirectLimit) {
            document.addEventListener('DOMContentLoaded', function() {
                // エラーメッセージを表示
                document.getElementById('loading').style.display = 'none';
                document.getElementById('error-container').style.display = 'flex';
                document.getElementById('error-message').innerHTML = `
                    <div style="text-align: center;">
                        <p style="font-size: 18px; margin-bottom: 20px;">LINE認証に失敗しました</p>
                        <p style="margin-bottom: 20px;">このページはLINEアプリ内で開く必要があります。</p>
                        <p style="margin-bottom: 20px;">以下のボタンをタップしてLINEアプリで開いてください：</p>
                        <a href="https://liff.line.me/<?php echo $liffId; ?><?php echo !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>" 
                           style="display: inline-block; background-color: #00B900; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold;">
                            LINEアプリで開く
                        </a>
                        <p style="margin-top: 20px; font-size: 14px; color: #666;">
                            または、このURLをLINEのトークにコピーして送信し、<br>
                            LINE内でタップして開いてください。
                        </p>
                    </div>
                `;
                document.getElementById('retry-button').style.display = 'none';
            });
        }
    </script>
    <script src="js/liff-init.js?v=<?php echo time(); ?>"></script>
    <script src="js/api.js?v=<?php echo time(); ?>"></script>
    <script src="js/inform_Modal.js?v=<?php echo time(); ?>"></script>
    <script src="js/app.js?v=<?php echo time(); ?>"></script>
    
    <!-- 強制リロード用スクリプト -->
    <script>
        // キャッシュクリア用のハードリロード機能
        function forceReload() {
            // キャッシュをクリアして再読み込み
            const timestamp = new Date().getTime();
            window.location.href = window.location.pathname + '?nocache=' + timestamp;
        }
    </script>

    <script>
        // URLパラメータをコンソールに表示（デバッグ用）
        document.addEventListener('DOMContentLoaded', function() {
            const params = new URLSearchParams(window.location.search);
            console.log('URLパラメータ:', Object.fromEntries(params));
            
            // デバッグモード確認
            if (params.has('debug') && params.get('debug') === 'true') {
                document.getElementById('debug-container').style.display = 'block';
                window.debugMode = true;
                
                // デバッグ出力関数
                window.debugOutput = function(message) {
                    const debugElement = document.getElementById('debug-output');
                    const timestamp = new Date().toISOString().substr(11, 8);
                    debugElement.innerHTML += `[${timestamp}] ${message}\n`;
                };
                
                // コンソールログのオーバーライド
                const originalConsoleLog = console.log;
                console.log = function() {
                    originalConsoleLog.apply(console, arguments);
                    const message = Array.from(arguments).map(arg => 
                        typeof arg === 'object' ? JSON.stringify(arg) : arg
                    ).join(' ');
                    window.debugOutput(message);
                };
                
                console.log('デバッグモード有効');
            }
        });
    </script>
</body>
</html> 
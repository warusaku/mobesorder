index.php
<?php
require_once '../config/LIFF_config.php';
require_once '../api/lib/Utils.php';
require_once 'php/log_helper.php';
require_once 'api/lib/login_control.php'; // LOGIN_CONTROLクラスを読み込む

// キャッシュを無効化するヘッダーを追加
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// LINEログイン設定の取得
$loginControl = LOGIN_CONTROL::getInstance();
$lineLoginRequired = $loginControl->isLineLoginRequired();
$roomLinkRequired = $loginControl->isRoomLinkRequired();

// 自身のPHPファイル名からログファイル名を生成
$currentFile = basename(__FILE__);
$logFileName = LogHelper::getLogFileNameFromPhp($currentFile);

// アクセスログを直接出力
$ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
$accessLogMessage = "モバイルオーダーアクセス - IP: {$ipAddress}, UA: {$userAgent}";
LogHelper::info($accessLogMessage, $logFileName);

// アクセスログ（オプション）- 既存のログ機能も残す
if (defined('LIFF_DEBUG_MODE') && LIFF_DEBUG_MODE) {
    $logMessage = "モバイルオーダーアクセス: " . date('Y-m-d H:i:s') . " - " . $_SERVER['REMOTE_ADDR'];
    
    if (function_exists('Utils::log')) {
        Utils::log($logMessage, 'INFO', 'OrderAccess');
    } else {
        error_log($logMessage);
    }
}

// グローバル変数
$debug_level = 0;
$allow_test_mode = false;

// バージョン番号 - シンプルに日付ベースに変更
$script_version = date('YmdHis');

// 初期エラーハンドラーを設定
set_error_handler(function($errno, $errstr, $errfile, $errline) use ($logFileName) {
    $errorTypes = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'PARSE ERROR',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'CORE ERROR',
        E_CORE_WARNING => 'CORE WARNING',
        E_COMPILE_ERROR => 'COMPILE ERROR',
        E_COMPILE_WARNING => 'COMPILE WARNING',
        E_USER_ERROR => 'USER ERROR',
        E_USER_WARNING => 'USER WARNING',
        E_USER_NOTICE => 'USER NOTICE',
        E_STRICT => 'STRICT',
        E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
        E_DEPRECATED => 'DEPRECATED',
        E_USER_DEPRECATED => 'USER DEPRECATED',
    ];
    
    $type = isset($errorTypes[$errno]) ? $errorTypes[$errno] : 'UNKNOWN ERROR';
    $message = "{$type}: {$errstr} in {$errfile} on line {$errline}";
    
    // ログに出力
    LogHelper::error($message, $logFileName);
    
    // 本来のエラーハンドラーにも渡す
    return false;
});
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>注文アプリ</title>
    <!-- 唯一存在するCSSファイルのみ読み込み -->
    <link rel="stylesheet" href="css/style.css?v=<?php echo $script_version; ?>">
    <!-- WEB FONTS -->
    <link href="https://fonts.googleapis.com/css?family=M+PLUS+Rounded+1c:400,700&display=swap" rel="stylesheet">
    
    <!-- LIFF ID設定 -->
    <script>
        // グローバル変数の初期化（変更しない）
        window.SCRIPT_VERSION = "<?php echo $script_version; ?>";
        window.LIFF_ID = "<?php echo LIFF_ID; ?>"; // 設定ファイルから取得
        window.LINE_USER_ID = null;
        window.itemData = null;
        window.cart = null;
        window.roomInfo = null;
        
        // LINE設定
        window.LINE_LOGIN_REQUIRED = <?php echo $lineLoginRequired ? 'true' : 'false'; ?>;
        window.ROOM_LINK_REQUIRED = <?php echo $roomLinkRequired ? 'true' : 'false'; ?>;
        
        // LIFF IDログ出力
        console.log("LIFF ID: " + window.LIFF_ID);
        console.log("LINE設定: ログイン必須=" + window.LINE_LOGIN_REQUIRED + ", 部屋連携必須=" + window.ROOM_LINK_REQUIRED);
        
        // シンプルなES6チェック
        window.isES6Compatible = (function() {
            try {
                // 基本的な機能チェック
                eval("let a = 1; const b = 2; `${a + b}`;");
                return true;
            } catch (e) {
                console.warn("ES6非互換ブラウザが検出されました: ", e);
                return false;
            }
        })();
        
        // シンプルなES6ポリフィル - api.js用のみ
        if (!window.isES6Compatible) {
            console.log("レガシーブラウザ対応: 基本ポリフィルを適用します");
            
            // クラス構文のシンプルなポリフィル（API.jsのため）
            if (typeof Object.create !== 'function') {
                Object.create = function(proto) {
                    function F() {}
                    F.prototype = proto;
                    return new F();
                };
            }
            
            // テンプレートリテラルのフォールバック
            window._formatString = function(strings) {
                var values = Array.prototype.slice.call(arguments, 1);
                var result = strings[0] || '';
                for (var i = 0; i < values.length; i++) {
                    result += values[i] + (strings[i + 1] || '');
                }
                return result;
            };
        }
        
        // セッションストレージヘルパー
        window.sessionHelper = {
            get: function(key, defaultValue) {
                try {
                    var value = sessionStorage.getItem(key);
                    return value !== null ? value : defaultValue;
                } catch (e) {
                    console.warn("セッションストレージアクセスエラー:", e);
                    return defaultValue;
                }
            },
            set: function(key, value) {
                try {
                    sessionStorage.setItem(key, value);
                    return true;
                } catch (e) {
                    console.warn("セッションストレージ保存エラー:", e);
                    return false;
                }
            },
            remove: function(key) {
                try {
                    sessionStorage.removeItem(key);
                    return true;
                } catch (e) {
                    console.warn("セッションストレージ削除エラー:", e);
                    return false;
                }
        }
        };
    </script>
    
    <!-- Logger（開発用） -->
    <script>
        // ログ出力用の関数（デバッグレベルによって制御）
        window.log = function() {
            var debugLevel = <?php echo $debug_level; ?>;
            if (debugLevel > 0) {
                console.log.apply(console, arguments);
                
                // サーバーログにも同期（重要なメッセージの場合）
                try {
                    var message = Array.prototype.slice.call(arguments).join(' ');
                    fetch('../api/log_writer.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            file: '<?php echo $logFileName; ?>',
                            message: "Client Log: " + message,
                            type: 'DEBUG'
                        })
                    }).catch(function() {
                        // エラーは無視（ログ記録の失敗で処理を止めない）
                    });
                } catch (e) {
                    // 例外は無視
                }
            }
        };
        
        // エラー記録用の拡張
        window.onerror = function(message, source, lineno, colno, error) {
            var errorDetails = 'JavaScript Error: ' + message + ' at ' + source + ':' + lineno + ':' + colno;
            if (error && error.stack) {
                errorDetails += '\nStack: ' + error.stack;
        }
        
            // サーバーログに書き込み
            fetch('../api/log_writer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    file: '<?php echo $logFileName; ?>',
                    message: errorDetails,
                    type: 'ERROR'
                })
            }).catch(function() {
                // エラーは無視（ログ記録の失敗で処理を止めない）
            });
            
            // デフォルトのエラーハンドリングは継続
            return false;
        };
    </script>
    
    <!-- 初期化とLIFF SDK読み込み -->
    <script>
        // DOMヘルパー関数
        window.$ = function(selector) {
            return document.querySelector(selector);
        };
        
        window.$$ = function(selector) {
            return document.querySelectorAll(selector);
        };
        
        // アプリ初期化状態の追跡
        window.appState = {
            liffLoaded: false,
            liffInitialized: false,
            domLoaded: false,
            uiInitialized: false,
            apiInitialized: false,
            cartInitialized: false
        };
        
        // リダイレクトループ対策
        function checkForLineRedirect() {
            // URLパラメータをチェック
            var urlParams = new URLSearchParams(window.location.search);
            var liffState = urlParams.get('liff.state');
            var code = urlParams.get('code');
            var state = urlParams.get('state');
            var lineUserId = urlParams.get('line_user_id');
            var hasLineParam = lineUserId || liffState || (code && state);
            
            // リダイレクト後かどうかをチェック
            if (hasLineParam) {
                var redirectType = code && state ? 'auth_code' : 
                                  liffState ? 'liff_state' : 
                                  lineUserId ? 'line_user_id' : 'unknown';
                
                console.log("🔄 LINEリダイレクト後の状態を検出しました");
                console.log(`🔑 リダイレクト種別: ${redirectType}`);
                console.log(`🔍 詳細: code=${code || "なし"}, state=${state || "なし"}, liff.state=${liffState || "なし"}, line_user_id=${lineUserId || "なし"}`);
                
                // リダイレクト処理済みフラグをセット（ループ防止）
                window.sessionHelper.set('line_redirect_processed', 'true');
                window.sessionHelper.set('redirect_time', Date.now().toString());
                window.sessionHelper.set('redirect_type', redirectType);
                
                // サーバーログに記録
                fetch('../api/log_writer.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        file: '<?php echo $logFileName; ?>',
                        message: `LINEリダイレクト検出: タイプ=${redirectType}, URL=${window.location.href}`,
                        type: 'INFO'
                    })
                }).catch(function() {
                    // エラーは無視
                });
                
                return true;
            }
            
            return false;
        }
        
        // LIFF SDKの読み込み
        function loadLIFFScript() {
            return new Promise(function(resolve, reject) {
                var isRedirect = checkForLineRedirect();
                
                // リダイレクト検出状態をコンソールに出力
                console.log("LINEリダイレクト検出状態:", isRedirect);
                
                // すでにLIFFが読み込まれているか確認
                if (typeof liff !== 'undefined') {
                    console.log("LIFF SDKは既に読み込まれています");
                    window.appState.liffLoaded = true;
                    resolve();
                    return;
                }
                
                console.log("LIFF SDKを読み込みます");
                
                // ログに記録
                fetch('../api/log_writer.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        file: '<?php echo $logFileName; ?>',
                        message: "LIFF SDK読み込み開始",
                        type: 'INFO'
                    })
                }).catch(function() {
                    // エラーは無視
                });
                
                var script = document.createElement('script');
                script.src = "https://static.line-scdn.net/liff/edge/2/sdk.js";
                script.onload = function() {
                    console.log("LIFF SDK読み込み完了");
                    window.appState.liffLoaded = true;
                    
                    // ログに記録
                    fetch('../api/log_writer.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            file: '<?php echo $logFileName; ?>',
                            message: "LIFF SDK読み込み完了",
                            type: 'INFO'
                        })
                    }).catch(function() {
                        // エラーは無視
                    });
                    
                    resolve();
                };
                script.onerror = function() {
                    console.error("LIFF SDK読み込みエラー");
                    
                    // ログに記録
                    fetch('../api/log_writer.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            file: '<?php echo $logFileName; ?>',
                            message: "LIFF SDK読み込みエラー",
                            type: 'ERROR'
                        })
                    }).catch(function() {
                        // エラーは無視
                    });
                    
                    reject(new Error("Failed to load LIFF SDK"));
                };
                
                document.head.appendChild(script);
            });
        }
        
        // 初期化シーケンスの管理
        function initializeAppModules() {
            console.log("アプリモジュール初期化シーケンスを開始します");
            
            // カスタムイベントを監視
            document.addEventListener('liff-initialized', function() {
                console.log("✅ LIFF初期化完了イベントを受信");
                window.appState.liffInitialized = true;
                
                // すべてのモジュールを順次初期化
                initializeAllModules();
            });
            
            document.addEventListener('liff-error', function(event) {
                console.error("❌ LIFF初期化エラーイベントを受信:", event.detail && event.detail.message);
                showError(event.detail && event.detail.message || "LIFF初期化エラー");
            });
        }
        
        // すべてのモジュールを初期化
        function initializeAllModules() {
            console.log("モジュール初期化を開始します - LIFF初期化済み");
            
            // API初期化（cart.jsやui.jsより先に初期化する必要あり）
            if (typeof window.apiClient === 'undefined' && typeof API === 'function') {
                try {
                    console.log("APIクライアントを初期化します");
                    window.apiClient = new API('/fgsquare/api/v1');
                    window.api = window.apiClient; // エイリアス
                    window.appState.apiInitialized = true;
                    console.log("✅ APIクライアント初期化完了");
                } catch (error) {
                    console.error("APIクライアント初期化エラー:", error);
                }
            }
            
            // UI初期化
            if (typeof initUI === 'function' && !window.appState.uiInitialized) {
                try {
                    console.log("UI初期化を開始します");
                    initUI();
                    window.appState.uiInitialized = true;
                    console.log("✅ UI初期化完了");
                } catch (error) {
                    console.error("UI初期化エラー:", error);
                }
            }
            
            // カート初期化
            if (typeof initCart === 'function' && !window.appState.cartInitialized) {
                try {
                    console.log("カート初期化を開始します");
                    initCart();
                    window.appState.cartInitialized = true;
                    console.log("✅ カート初期化完了");
                } catch (error) {
                    console.error("カート初期化エラー:", error);
                }
            }
            
            // イベントを発火してアプリケーション全体の初期化完了を通知
            try {
                var appReadyEvent = new CustomEvent('app-ready', { detail: { state: window.appState } });
                document.dispatchEvent(appReadyEvent);
                console.log("🎉 アプリケーション初期化完了イベントを発行しました");
            } catch (error) {
                console.error("イベント発行エラー:", error);
            }
        }
        
        // ページロード時の処理
        window.addEventListener('DOMContentLoaded', function() {
            console.log("DOM読み込み完了 - 初期化シーケンスを開始します");
            window.appState.domLoaded = true;
            
            // 初期化シーケンスを開始
            initializeAppModules();
            
            // LIFF SDKの読み込みと初期化
            loadLIFFScript().then(function() {
                console.log("LIFFスクリプト読み込み完了 - 初期化を待機します");
                // この時点でliff-init.jsが初期化処理を行います
            }).catch(function(error) {
                console.error("LIFFスクリプト読み込み失敗:", error);
                showError("LINEアプリの読み込みに失敗しました。ページを再読み込みしてください。");
            });
        });
        
        // エラー表示関数
        function showError(message) {
            var errorContainer = document.getElementById('error-container');
            var errorMessage = document.getElementById('error-message');
            
            // ログに記録
            fetch('../api/log_writer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    file: '<?php echo $logFileName; ?>',
                    message: "UI Error: " + message,
                    type: 'ERROR'
                })
            }).catch(function() {
                // エラーは無視
            });
            
            if (errorContainer && errorMessage) {
                errorMessage.textContent = message;
                errorContainer.style.display = 'flex';
                
                // ローディングを非表示
                var loadingElement = document.getElementById('loading');
                if (loadingElement) {
                    loadingElement.style.display = 'none';
                }
            } else {
                alert(message);
            }
        }
    </script>
</head>
<body>
    <div id="loading">
        <div class="spinner"></div>
        <p>読み込んでいます...</p>
    </div>

    <div id="error-container" style="display: none;">
        <div class="error-content">
            <i class="fas fa-exclamation-triangle"></i>
            <h2>エラーが発生しました</h2>
            <p id="error-message-text">エラーメッセージ</p>
            <button id="retry-button">再試行</button>
        </div>
    </div>

    <div id="app">
        <header>
            <div class="header-content">
                <div class="header-left">
                    <img src="images/logo.svg" alt="ロゴ" class="header-logo" id="header-logo-img">
                    <img src="images/title.svg" alt="タイトル" class="header-title" id="header-title-img">
                </div>
                <div class="room-info">
                    <span id="room-number">----</span>
                    <button class="refresh-button" id="refresh-button" aria-label="情報を更新">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        </header>

        <main>
            <div class="category-sidebar" id="category-sidebar">
                <ul id="category-list">
                    <!-- カテゴリはここに動的に追加されます -->
                </ul>
                <div class="closed-message" id="category-closed-message" style="display: none;">
                    <i class="fas fa-store-slash"></i>
                    <h3>営業時間外です</h3>
                    <p id="category-closed-message-text">現在、全ての商品カテゴリが営業時間外です。</p>
                </div>
            </div>
            <div class="product-content">
                <div id="product-list">
                    <!-- 商品はここに動的に追加されます -->
                </div>
                <div class="closed-message" id="store-closed-message" style="display: none;">
                    <i class="fas fa-store-slash"></i>
                    <h3>営業時間外です</h3>
                    <p>現在、お店は営業しておりません。恐れ入りますが、営業時間内に再度お試しください。</p>
                </div>
            </div>
        </main>

        <footer>
            <button class="order-history-button" id="order-history-button" aria-label="注文履歴">
                <i class="fas fa-history"></i>
            </button>
            <div class="cart-summary">
                <i class="fas fa-shopping-cart"></i>
                合計 <span id="total-quantity">0</span>点 <span id="total-price">¥0</span>
            </div>
            <button class="order-button" id="view-cart-button"> <!-- このボタンはカートタブを開くトリガーか、直接カート確認モーダルを開くかJS依存 -->
                注文へ進む
                <span class="cart-badge" id="cart-badge" style="display: none;">0</span>
            </button>
        </footer>

        <!-- 商品詳細モーダル (IDを item-detail に変更) -->
        <div id="item-detail" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="productDetailName">商品名</h2>
                    <span class="close item-detail-close">&times;</span> <!-- クラス追加で特定しやすく -->
                </div>
                <div class="modal-body">
                    <div class="product-detail-content">
                        <div class="product-detail-image" id="productDetailImageContainer">
                            <img id="productDetailImage" src="" alt="商品画像">
                        </div>
                        <div class="product-detail-info">
                            <div class="product-detail-price-container">
                                <span class="price-value" id="productDetailPrice">¥0</span>
                                <div class="product-detail-labels" id="productDetailLabelsContainer">
                                    <!-- ラベルはここに動的に追加 -->
                                </div>
                            </div>
                            <p class="product-category" id="productDetailCategory">カテゴリ</p>
                            <p class="product-description" id="productDetailDescription">商品説明</p>
                            <div class="quantity-control">
                                <button class="quantity-button minus"><i class="fas fa-minus"></i></button>
                                <input type="number" class="quantity-input" id="detail-quantity" value="1" min="1">
                                <button class="quantity-button plus"><i class="fas fa-plus"></i></button>
                            </div>
                            <button class="add-to-cart-button" id="add-to-cart-detail-btn">カートに追加</button> <!-- IDをadd-to-cart-detail-btnに変更 -->
                            <p class="out-of-stock-message" id="detailOutOfStockMessage" style="display:none;">現在、在庫がありません。</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- カート確認モーダル (旧 ui.js が参照していた cartModal とは別。これは注文プロセスの一部と想定) -->
        <!-- 今回のエラーとは直接関係ないかもしれないが、構造として残す -->
        <div id="cartModal" class="modal"> 
            <div class="modal-content">
                <div class="modal-header">
                    <h2>ご注文内容の最終確認</h2>
                    <span class="close" id="closeCartModal">&times;</span>
                </div>
                <div class="modal-body" id="final-cart-items-container">
                    <!-- 最終確認用のカートアイテムはここにJSで描画される想定 -->
                    <div class="cart-total-section">
                        <div class="cart-total-row">
                            <span>小計</span>
                            <span id="finalCartSubtotal">¥0</span>
                        </div>
                        <div class="cart-total-row total">
                            <span>合計</span>
                            <span id="finalCartTotal">¥0</span>
                        </div>
                    </div>
                    <div class="notes-section">
                        <label>備考:</label>
                        <p id="finalOrderNotes"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="back-button" id="backToCartTabBtn">修正する</button> <!-- カートタブに戻る -->
                    <button class="checkout-button" id="finalCheckoutBtn">注文を確定する</button>
                </div>
            </div>
        </div>
        
        <!-- 注文確認モーダル (旧 ui.js の initOrderConfirmationModal が期待するID) -->
        <div id="order-confirmation" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>注文確認</h2>
                    <span class="close" id="cancel-order-button">&times;</span> <!-- 旧JSに合わせたID -->
                </div>
                <div class="modal-body">
                    <div class="order-confirmation-message">
                        以下の注文を確定しますか？
                    </div>
                    <div class="order-confirmation-list" id="order-confirmation-list-items">
                        <!-- 注文確認アイテム -->
                    </div>
                    <div class="order-confirmation-total">
                        合計: <span id="confirmation-total-amount">0</span>円
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="back-button" id="cancel-confirmation-action-btn">キャンセル</button> <!-- 新しいID -->
                    <button class="checkout-button" id="confirm-order-button">注文を確定する</button> <!-- 旧JSに合わせたID -->
                </div>
            </div>
        </div>

        <!-- 注文完了モーダル (IDを order-complete に変更) -->
        <div id="order-complete" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>ご注文完了</h2>
                    <span class="close" id="return-to-menu-button">&times;</span> <!-- 旧JSに合わせたID -->
                </div>
                <div class="modal-body">
                    <div class="order-complete-message">
                        <i class="fas fa-check-circle"></i>
                        <p>ご注文ありがとうございました。</p>
                        <p>お部屋へお届けしますので、しばらくお待ちください。</p>
                        <div class="order-number">
                            受付番号: <span id="receiptNumber"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 注文履歴モーダル -->
        <div id="orderHistoryModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>注文履歴</h2>
                    <span class="close" id="closeOrderHistoryModal">&times;</span>
                </div>
                <div class="modal-body" id="order-history-list">
                    <!-- 注文履歴はここに表示 -->
                    <div class="loading-indicator" style="display: none;">
                        <div class="spinner"></div>
                        <p>読み込み中...</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 営業時間外モーダル -->
        <div id="storeClosedModal" class="modal">
            <div class="modal-content closed-time-modal-content">
                 <div class="modal-header">
                    <h2>お知らせ</h2>
                    <span class="close" id="closeStoreClosedModal">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="closed-message-modal">
                        <i class="fas fa-store-slash"></i>
                        <p id="storeClosedModalText">ただいまの時間、ご注文の受付を停止しております。恐れ入りますが、営業時間内に再度お試しください。</p>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /#app -->

    <!-- Font Awesome -->
    <script src="https://use.fontawesome.com/releases/v5.15.4/js/all.js"></script> <!-- 一般的なCDNに一旦変更 -->
    
    <!-- LIFF SDK と アプリケーションのコアスクリプト -->
    <script charset="utf-8" src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
    <script src="js/liff-init.js?v=<?php echo $script_version; ?>"></script>
    <script src="js/api.js?v=<?php echo $script_version; ?>"></script>
    <script src="js/cart.js?v=<?php echo $script_version; ?>"></script>
    <script src="js/ui.js?v=<?php echo $script_version; ?>"></script>
    <script src="js/app.js?v=<?php echo $script_version; ?>"></script>

</body>
</html> 

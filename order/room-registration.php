<?php
require_once '../config/LIFF_config.php';
require_once '../api/lib/Utils.php';

// キャッシュを無効化するヘッダーを追加
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// アクセスログ
if (defined('LIFF_DEBUG_MODE') && LIFF_DEBUG_MODE) {
    $logMessage = "部屋登録画面アクセス: " . date('Y-m-d H:i:s') . " - " . $_SERVER['REMOTE_ADDR'];
    
    if (function_exists('Utils::log')) {
        Utils::log($logMessage, 'INFO', 'RoomRegAccess');
    } else {
        error_log($logMessage);
    }
}

// LINE UserIDを取得
$lineUserId = isset($_GET['line_user_id']) ? $_GET['line_user_id'] : '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>部屋番号登録 - FG Square</title>
    <meta name="description" content="ルームサービスをご利用いただくための部屋番号登録画面です">
    <meta name="theme-color" content="#4CAF50">
    
    <!-- キャッシュ制御 -->
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    
    <!-- エラーハンドラを読み込み -->
    <script src="js/error-handler.js?v=<?php echo date('Ymd'); ?>"></script>
    
    <!-- LIFF SDK -->
    <script charset="utf-8" src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo date('Ymd'); ?>">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- インラインスタイル -->
    <style>
        .registration-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        .btn {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            text-align: center;
        }
        
        .btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        
        .text-center {
            text-align: center;
        }
        
        .mb-10 {
            margin-bottom: 10px;
        }
        
        .mb-20 {
            margin-bottom: 20px;
        }
        
        .instructions {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .success-message {
            background-color: #e8f5e9;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: none;
        }
        
        .error-message {
            background-color: #ffebee;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: none;
        }
    </style>
    
    <!-- LIFF設定 -->
    <script>
        // LIFF設定をPHPから受け取る
        window.LIFF_ID = '<?php echo LIFF_ID; ?>';
        window.LIFF_CHANNEL_ID = '<?php echo LIFF_CHANNEL_ID; ?>';
        
        // ユーザープロファイル用変数
        window.lineProfile = null;
        
        // LINE UserID
        window.lineUserId = '<?php echo $lineUserId; ?>';
    </script>
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
                        <img src="images/logo.svg" alt="FG Square Logo" class="header-logo">
                        <img src="images/title.svg" alt="モバイルオーダー" class="header-title">
                    </div>
                </div>
            </header>

            <main>
                <div class="registration-container">
                    <div class="card">
                        <h2 class="text-center mb-20">部屋番号の登録</h2>
                        
                        <div class="instructions">
                            <p>モバイルオーダーをご利用いただくには、お客様の部屋番号を登録していただく必要があります。</p>
                            <p>下記フォームに部屋番号をご入力ください。</p>
                        </div>
                        
                        <div id="success-message" class="success-message">
                            <i class="fas fa-check-circle"></i>
                            <span>部屋番号の登録が完了しました。モバイルオーダーをご利用いただけます。</span>
                        </div>
                        
                        <div id="registration-error" class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <span id="error-text">エラーが発生しました。しばらく経ってから再度お試しください。</span>
                        </div>
                        
                        <form id="registration-form">
                            <div class="form-group">
                                <label for="room-number">部屋番号</label>
                                <input type="text" id="room-number" class="form-control" placeholder="例: 101" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="guest-name">お名前</label>
                                <input type="text" id="guest-name" class="form-control" placeholder="例: 山田 太郎" required>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" id="register-button" class="btn">登録する</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // グローバル変数
        let isRegistering = false;
        
        // DOMが読み込まれたら実行
        document.addEventListener('DOMContentLoaded', function() {
            // LIFFの初期化
            initializeLIFF();
            
            // フォームの送信イベント
            const form = document.getElementById('registration-form');
            if (form) {
                form.addEventListener('submit', handleFormSubmit);
            }
            
            // 再試行ボタン
            const retryButton = document.getElementById('retry-button');
            if (retryButton) {
                retryButton.addEventListener('click', function() {
                    location.reload();
                });
            }
        });
        
        // LIFFの初期化
        function initializeLIFF() {
            // ローディング表示
            showLoading(true);
            
            if (!window.LIFF_ID) {
                showError('LIFF IDが設定されていません');
                return;
            }
            
            if (typeof liff === 'undefined') {
                showError('LIFF SDKが読み込まれていません');
                return;
            }
            
            // LIFF初期化
            liff.init({
                liffId: window.LIFF_ID
            })
            .then(() => {
                console.log('LIFF初期化成功');
                
                // ログイン確認
                if (!liff.isLoggedIn()) {
                    console.log('ログインしていないため、ログイン画面にリダイレクト');
                    liff.login();
                    return;
                }
                
                // プロフィール取得
                return liff.getProfile();
            })
            .then(profile => {
                if (!profile) return;
                
                console.log('プロフィール取得成功', profile);
                window.lineProfile = profile;
                
                // URLからLINE UserIDが指定されていない場合はプロフィールから取得
                if (!window.lineUserId && profile.userId) {
                    window.lineUserId = profile.userId;
                }
                
                // 名前を自動入力
                const nameInput = document.getElementById('guest-name');
                if (nameInput && profile.displayName) {
                    nameInput.value = profile.displayName;
                }
                
                // コンテンツを表示
                showContent();
            })
            .catch(err => {
                console.error('LIFF初期化エラー', err);
                showError('LINEとの連携に失敗しました。ページを再読み込みしてください。');
            })
            .finally(() => {
                showLoading(false);
            });
        }
        
        // フォーム送信処理
        function handleFormSubmit(event) {
            event.preventDefault();
            
            if (isRegistering) return;
            isRegistering = true;
            
            // 入力値の取得
            const roomNumber = document.getElementById('room-number').value.trim();
            const guestName = document.getElementById('guest-name').value.trim();
            
            // バリデーション
            if (!roomNumber) {
                showRegistrationError('部屋番号を入力してください');
                isRegistering = false;
                return;
            }
            
            if (!guestName) {
                showRegistrationError('お名前を入力してください');
                isRegistering = false;
                return;
            }
            
            if (!window.lineUserId) {
                showRegistrationError('LINE情報が取得できませんでした。ページを再読み込みしてください。');
                isRegistering = false;
                return;
            }
            
            // ボタンの状態を更新
            const registerButton = document.getElementById('register-button');
            if (registerButton) {
                registerButton.disabled = true;
                registerButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 登録中...';
            }
            
            // 部屋情報をAPIに送信
            registerRoom(roomNumber, guestName, window.lineUserId)
                .then(success => {
                    if (success) {
                        showSuccessMessage();
                        // 登録フォームを非表示
                        const form = document.getElementById('registration-form');
                        if (form) form.style.display = 'none';
                        
                        // 3秒後にモバイルオーダーページに戻る
                        setTimeout(() => {
                            window.location.href = '/fgsquare/order/';
                        }, 3000);
                    } else {
                        showRegistrationError('部屋情報の登録に失敗しました。しばらく経ってから再度お試しください。');
                    }
                })
                .catch(error => {
                    console.error('登録エラー', error);
                    showRegistrationError('エラーが発生しました: ' + error.message);
                })
                .finally(() => {
                    isRegistering = false;
                    
                    // ボタンの状態を戻す
                    if (registerButton) {
                        registerButton.disabled = false;
                        registerButton.innerHTML = '登録する';
                    }
                });
        }
        
        // 部屋情報をAPIに登録
        function registerRoom(roomNumber, guestName, lineUserId) {
            const apiUrl = '/fgsquare/api/v1/register-room.php';
            
            const formData = new FormData();
            formData.append('room_number', roomNumber);
            formData.append('user_name', guestName);
            formData.append('line_user_id', lineUserId);
            
            return fetch(apiUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`サーバーエラー: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    return true;
                } else {
                    throw new Error(data.error || '不明なエラー');
                }
            });
        }
        
        // ローディング表示の制御
        function showLoading(show) {
            const loading = document.getElementById('loading');
            if (loading) {
                loading.style.display = show ? 'flex' : 'none';
            }
        }
        
        // コンテンツ表示
        function showContent() {
            const contentContainer = document.getElementById('content-container');
            if (contentContainer) {
                contentContainer.style.display = 'block';
            }
        }
        
        // エラーメッセージ表示
        function showError(message) {
            const errorContainer = document.getElementById('error-container');
            const errorMessage = document.getElementById('error-message');
            
            if (errorContainer && errorMessage) {
                errorMessage.textContent = message;
                errorContainer.style.display = 'flex';
                
                // ローディング表示を非表示
                showLoading(false);
            }
        }
        
        // 登録エラーメッセージ表示
        function showRegistrationError(message) {
            const errorElement = document.getElementById('registration-error');
            const errorTextElement = document.getElementById('error-text');
            
            if (errorElement && errorTextElement) {
                errorTextElement.textContent = message;
                errorElement.style.display = 'block';
                
                // 5秒後に非表示
                setTimeout(() => {
                    errorElement.style.display = 'none';
                }, 5000);
            }
        }
        
        // 成功メッセージ表示
        function showSuccessMessage() {
            const successElement = document.getElementById('success-message');
            
            if (successElement) {
                successElement.style.display = 'block';
            }
        }
    </script>
</body>
</html> 
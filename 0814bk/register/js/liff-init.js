/**
 * LIFF初期化処理
 * @version 2.2.0
 */

// LIFF ID
const LIFF_ID = '2007363986-nMAv6J8w';

// デバッグモード
const DEBUG = true;

// 最大再試行回数
const MAX_RETRY_COUNT = 2;

// リダイレクト制限
const MAX_REDIRECT_COUNT = 2;
const REDIRECT_COUNT_KEY = 'liff_redirect_count';
const LAST_REDIRECT_TIME_KEY = 'liff_last_redirect_time';

// グローバル変数
let liffId = '';
let lineUserId = '';
let lineDisplayName = '';
let lineProfileImage = '';
let initRetryCount = 0;
window.liffInfo = null; // アプリで利用するLIFF情報
window.liffInitialized = false; // LIFF初期化完了フラグ
window.liffInitializationPromise = null; // 初期化プロミス

/**
 * デバッグログ出力
 */
function debugLog(message, data = null) {
    if (DEBUG) {
        const timestamp = new Date().toISOString().substr(11, 8);
        const logMessage = `[LIFF ${timestamp}] ${message}`;
        
        if (data) {
            console.log(logMessage, data);
        } else {
            console.log(logMessage);
        }
        
        // エラーログをlocalStorageに保存（デバッグ用）
        try {
            const logs = JSON.parse(localStorage.getItem('liff_debug_logs') || '[]');
            logs.push({ time: timestamp, message, data });
            // 最新50件のみ保持
            if (logs.length > 50) {
                logs.splice(0, logs.length - 50);
            }
            localStorage.setItem('liff_debug_logs', JSON.stringify(logs));
        } catch (e) {
            // localStorageが使えない場合は無視
        }
    }
}

/**
 * プリフライトチェック
 * LIFF初期化前に環境をチェック
 */
async function preflightCheck() {
    debugLog('プリフライトチェック開始');
    
    const checks = {
        liffSdk: false,
        storage: false,
        network: false,
        userAgent: false
    };
    
    // 1. LIFF SDKの存在確認
    if (typeof liff !== 'undefined') {
        checks.liffSdk = true;
        debugLog('✓ LIFF SDK: 利用可能');
    } else {
        debugLog('✗ LIFF SDK: 未定義');
    }
    
    // 2. ストレージの利用可能性確認
    try {
        const testKey = 'liff_storage_test';
        sessionStorage.setItem(testKey, '1');
        sessionStorage.removeItem(testKey);
        localStorage.setItem(testKey, '1');
        localStorage.removeItem(testKey);
        checks.storage = true;
        debugLog('✓ ストレージ: 利用可能');
    } catch (e) {
        debugLog('✗ ストレージ: 利用不可', e.message);
    }
    
    // 3. ネットワーク接続確認（LINE APIへのアクセス）
    try {
        const response = await fetch('https://api.line.me/healthcheck', {
            method: 'HEAD',
            mode: 'no-cors'
        });
        checks.network = true;
        debugLog('✓ ネットワーク: LINE API接続可能');
    } catch (e) {
        debugLog('⚠ ネットワーク: LINE API接続確認失敗', e.message);
        // no-corsモードなので失敗しても問題ない場合がある
        checks.network = true;
    }
    
    // 4. UserAgent確認
    const ua = navigator.userAgent;
    const isLineApp = /Line\//i.test(ua);
    const isWebView = /wv|WebView/i.test(ua);
    
    checks.userAgent = true;
    debugLog(`✓ UserAgent: ${isLineApp ? 'LINEアプリ内' : 'ブラウザ'} ${isWebView ? '(WebView)' : ''}`);
    debugLog(`  詳細: ${ua}`);
    
    // 環境情報を収集
    const environment = {
        platform: navigator.platform,
        language: navigator.language,
        cookieEnabled: navigator.cookieEnabled,
        onLine: navigator.onLine,
        screenResolution: `${screen.width}x${screen.height}`,
        windowSize: `${window.innerWidth}x${window.innerHeight}`,
        pixelRatio: window.devicePixelRatio || 1
    };
    
    debugLog('環境情報:', environment);
    
    // チェック結果を返す
    const allPassed = Object.values(checks).every(v => v);
    debugLog(`プリフライトチェック結果: ${allPassed ? '全て合格' : '一部失敗'}`, checks);
    
    return { passed: allPassed, checks, environment };
}

/**
 * リダイレクト回数をチェックしてリセット
 */
function checkAndResetRedirectCount() {
    const lastRedirectTime = sessionStorage.getItem(LAST_REDIRECT_TIME_KEY);
    const currentTime = Date.now();
    
    // 最後のリダイレクトから5分以上経過していたらカウントをリセット
    if (lastRedirectTime && (currentTime - parseInt(lastRedirectTime)) > 300000) {
        sessionStorage.removeItem(REDIRECT_COUNT_KEY);
        sessionStorage.removeItem(LAST_REDIRECT_TIME_KEY);
        localStorage.removeItem(REDIRECT_COUNT_KEY); // localStorageも念のためクリア
        localStorage.removeItem(LAST_REDIRECT_TIME_KEY);
        debugLog('リダイレクトカウントをリセット（5分経過）');
    }
}

/**
 * リダイレクト回数を増加
 */
function incrementRedirectCount() {
    checkAndResetRedirectCount();
    
    // sessionStorageとlocalStorage両方に保存（より確実にするため）
    const currentCount = parseInt(sessionStorage.getItem(REDIRECT_COUNT_KEY) || localStorage.getItem(REDIRECT_COUNT_KEY) || '0');
    const newCount = currentCount + 1;
    
    sessionStorage.setItem(REDIRECT_COUNT_KEY, newCount.toString());
    sessionStorage.setItem(LAST_REDIRECT_TIME_KEY, Date.now().toString());
    localStorage.setItem(REDIRECT_COUNT_KEY, newCount.toString());
    localStorage.setItem(LAST_REDIRECT_TIME_KEY, Date.now().toString());
    
    debugLog(`リダイレクト回数: ${newCount}`);
    return newCount;
}

/**
 * リダイレクト可能かチェック
 */
function canRedirect() {
    checkAndResetRedirectCount();
    const currentCount = parseInt(sessionStorage.getItem(REDIRECT_COUNT_KEY) || localStorage.getItem(REDIRECT_COUNT_KEY) || '0');
    return currentCount < MAX_REDIRECT_COUNT;
}

/**
 * LIFF初期化関数
 * @return {Promise<Object>} LIFFの初期化結果
 */
async function liffInit() {
    try {
        debugLog('LIFF初期化プロセス開始');
        
        // プリフライトチェック実行
        const preflightResult = await preflightCheck();
        if (!preflightResult.passed) {
            // 必須チェックが失敗した場合
            if (!preflightResult.checks.liffSdk) {
                throw new Error('LIFF SDKが読み込まれていません。ページを再読み込みしてください。');
            }
            if (!preflightResult.checks.storage) {
                console.warn('ストレージが利用できません。プライベートブラウジングモードを無効にしてください。');
            }
        }
        
        // LIFFの初期化パラメータをログ出力
        debugLog('LIFF ID:', LIFF_ID);
        
        // すでに初期化されていたら既存の結果を返す
        if (window.liffInitialized && window.idToken) {
            debugLog('LIFF既に初期化済み - 既存のトークンを使用');
            return {
                profile: { 
                    userId: lineUserId, 
                    displayName: lineDisplayName, 
                    pictureUrl: lineProfileImage 
                },
                isLoggedIn: true,
                idToken: window.idToken
            };
        }
        
        // LIFFが定義されているか確認
        if (typeof liff === 'undefined') {
            debugLog('LIFFが未定義です、SDKの読み込みを確認してください');
            
            // SDKの再読み込みを試行
            if (window.liffLoadAttempts < 3) {
                debugLog('LIFF SDKの再読み込みを試行します...');
                await new Promise(resolve => setTimeout(resolve, 2000));
                return await liffInit(); // 再帰的に再試行
            }
            
            throw new Error('LIFF SDKが正しく読み込まれていません');
        }
        
        // LIFFの初期化（改良版）
        try {
            // 初期化前に少し待機（SDKの完全読み込みを待つ）
            await new Promise(resolve => setTimeout(resolve, 500));
            
            debugLog('liff.init()を実行します...');
            
            // タイムアウト付き初期化
            const initPromise = liff.init({ liffId: LIFF_ID });
            const timeoutPromise = new Promise((_, reject) => 
                setTimeout(() => reject(new Error('LIFF初期化タイムアウト')), 10000)
            );
            
            await Promise.race([initPromise, timeoutPromise]);
            
            debugLog('liff.init()成功');
            
        } catch (initError) {
            // 初期化エラーの詳細分析
            debugLog('LIFF初期化エラー:', initError);
            
            // エラーメッセージから原因を特定
            const errorMessage = initError.message || '';
            
            if (errorMessage.includes('network') || errorMessage.includes('fetch')) {
                throw new Error('ネットワークエラー: インターネット接続を確認してください');
            }
            
            if (errorMessage.includes('timeout')) {
                // タイムアウトの場合は再試行
                if (initRetryCount < MAX_RETRY_COUNT) {
                    initRetryCount++;
                    debugLog(`LIFF初期化タイムアウト - 再試行 (${initRetryCount}/${MAX_RETRY_COUNT})`);
                    const waitTime = 2000 * initRetryCount;
                    await new Promise(resolve => setTimeout(resolve, waitTime));
                    return await liffInit();
                }
                throw new Error('LINEサーバーへの接続がタイムアウトしました');
            }
            
            if (errorMessage.includes('invalid') || errorMessage.includes('liffId')) {
                throw new Error('LIFF設定エラー: 管理者にお問い合わせください');
            }
            
            // その他のエラー
            throw new Error('LIFF初期化エラー: ' + errorMessage);
        }
        
        debugLog('LIFF初期化成功');
        window.liffInitialized = true;
        
        // 初期化後の状態確認
        const context = liff.getContext();
        debugLog('LIFF Context:', context);
        debugLog('isInClient:', liff.isInClient());
        debugLog('isLoggedIn:', liff.isLoggedIn());
        
        // ブラウザからのアクセスの場合、ログインを要求
        if (!liff.isInClient() && !liff.isLoggedIn()) {
            debugLog('ブラウザアクセス - ログインが必要');
            
            // リダイレクト回数をチェック
            if (!canRedirect()) {
                debugLog('リダイレクト回数が上限に達しました');
                throw new Error('認証の再試行回数が上限に達しました。しばらく待ってから再度お試しください。');
            }
            
            // リダイレクト回数を増加
            incrementRedirectCount();
            
            // 現在のURLパラメータを保持してログイン
            const currentUrl = window.location.href;
            debugLog('現在のURL（パラメータ含む）:', currentUrl);
            
            // URLパラメータをsessionStorageに保存（復帰後に使用）
            const urlParams = new URLSearchParams(window.location.search);
            const roomParam = urlParams.get('room');
            if (roomParam) {
                try {
                    sessionStorage.setItem('qr_room_param', roomParam);
                    localStorage.setItem('qr_room_param', roomParam); // バックアップ
                    debugLog('部屋番号パラメータを保存:', roomParam);
                } catch (e) {
                    debugLog('パラメータ保存エラー:', e.message);
                }
            }
            
            // 少し待ってからリダイレクト（急激なリダイレクトを防ぐ）
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            // redirectUriに現在のURLを指定してログイン
            liff.login({ redirectUri: currentUrl });
            return { profile: null, isLoggedIn: false };
        }
        
        // ログイン成功後はリダイレクトカウントをリセット
        sessionStorage.removeItem(REDIRECT_COUNT_KEY);
        sessionStorage.removeItem(LAST_REDIRECT_TIME_KEY);
        localStorage.removeItem(REDIRECT_COUNT_KEY);
        localStorage.removeItem(LAST_REDIRECT_TIME_KEY);
        
        // IDトークンの取得と検証を試みる
        let idToken = null;
        let retryCount = 0;
        const maxRetries = 5;
        
        // IDトークン取得のリトライループ
        while (!idToken && retryCount < maxRetries) {
            try {
                idToken = liff.getIDToken();
                if (idToken) {
                    debugLog(`IDトークン取得成功（試行 ${retryCount + 1}回目）`);
                    break;
                }
            } catch (e) {
                debugLog(`IDトークン取得エラー（試行 ${retryCount + 1}回目）: ${e.message}`);
            }
            
            retryCount++;
            
            // 待機時間を段階的に増やす
            const waitTime = Math.min(500 * retryCount, 2000);
            debugLog(`${waitTime}ms 待機後に再試行します`);
            await new Promise(resolve => setTimeout(resolve, waitTime));
        }
        
        if (!idToken) {
            debugLog(`IDトークン取得失敗 - ${maxRetries}回試行後も失敗`);
            
            // ブラウザ環境であればログイン画面へリダイレクト
            if (!liff.isInClient()) {
                debugLog('ブラウザ環境でIDトークン取得失敗 - ログイン画面へリダイレクト');
                
                // リダイレクト可能かチェック
                if (canRedirect()) {
                    incrementRedirectCount();
                    liff.login();
                } else {
                    debugLog('リダイレクト上限に達しているため、ログイン画面への遷移を中止');
                    throw new Error('認証の再試行回数が上限に達しました。しばらく待ってから再度お試しください。');
                }
                
                return { profile: null, isLoggedIn: false, error: 'IDトークン取得失敗' };
            }
            
            throw new Error('認証情報の取得に失敗しました。ページをリロードしてください。');
        }
        
        // IDトークンをグローバル変数に保存
        window.idToken = idToken;
        
        // プロフィール情報の取得
        let profile = null;
        try {
            profile = await liff.getProfile();
            debugLog('プロフィール取得成功', profile);
        } catch (profileError) {
            console.error('プロフィール取得エラー:', profileError);
            debugLog('エラー詳細:', profileError.message);
            // プロフィール取得に失敗しても、続行する
        }
        
        return {
            profile,
            isLoggedIn: liff.isLoggedIn(),
            idToken: window.idToken
        };
    } catch (error) {
        console.error('LIFF初期化エラー:', error);
        debugLog('エラー詳細:', error.message);
        debugLog('エラースタック:', error.stack);
        throw new Error('LINE連携に失敗しました。再度お試しください。: ' + error.message);
    }
}

/**
 * IDトークンの形式が正しいかを簡易的に検証
 * @param {string} token - 検証するIDトークン
 * @return {boolean} 検証結果
 */
function validateIdToken(token) {
    if (!token || typeof token !== 'string') {
        return false;
    }
    
    // JWTの形式チェック (ヘッダー.ペイロード.署名)
    const parts = token.split('.');
    if (parts.length !== 3) {
        debugLog('トークンの形式が不正: パート数が3ではありません');
        return false;
    }
    
    try {
        // ヘッダーとペイロードをデコード
        const headerB64 = parts[0];
        const payloadB64 = parts[1];
        
        // Base64デコード
        const headerStr = atob(headerB64.replace(/-/g, '+').replace(/_/g, '/'));
        const payloadStr = atob(payloadB64.replace(/-/g, '+').replace(/_/g, '/'));
        
        // JSONパース
        const header = JSON.parse(headerStr);
        const payload = JSON.parse(payloadStr);
        
        // 最低限の内容チェック
        if (!header.alg || !header.typ) {
            debugLog('トークンヘッダーに必須フィールドがありません');
            return false;
        }
        
        if (!payload.sub || !payload.iss) {
            debugLog('トークンペイロードに必須フィールドがありません');
            return false;
        }
        
        return true;
    } catch (e) {
        debugLog('トークン検証中にエラー: ' + e.message);
        return false;
    }
}

/**
 * LIFFの設定情報をサーバーから取得
 */
async function fetchLiffInfo() {
    try {
        console.log('LIFF設定取得開始');
        const response = await fetch('api/liff-config.php');
        console.log('LIFF設定取得レスポンス:', response.status, response.statusText);
        
        if (!response.ok) {
            throw new Error(`HTTP エラー: ${response.status} ${response.statusText}`);
        }
        
        const data = await response.json();
        console.log('取得したLIFF設定:', data);
        
        if (data && data.liffId) {
            return data;
        } else {
            throw new Error('LIFF設定の取得に失敗しました: 必要なパラメータが不足しています');
        }
    } catch (error) {
        console.error('LIFF情報取得エラー:', error);
        console.error('エラー詳細:', error.message);
        console.error('エラースタック:', error.stack);
        showError('LIFF設定の取得に失敗しました。エラー: ' + error.message);
        throw error;
    }
}

/**
 * プロファイル情報をUIに反映
 */
function updateProfileUI(profile) {
    const profileImageElement = document.getElementById('profile-image');
    const userNameElement = document.getElementById('user-name');
    
    if (profile.pictureUrl) {
        profileImageElement.src = profile.pictureUrl;
    }
    
    userNameElement.textContent = profile.displayName;
}

/**
 * エラー表示
 */
function showError(message) {
    document.getElementById('loading').style.display = 'none';
    document.getElementById('error-message').textContent = message;
    document.getElementById('error-container').style.display = 'flex';
    
    console.error('エラー表示:', message);
    
    // 再試行ボタンのイベントリスナー
    document.getElementById('retry-button').addEventListener('click', function() {
        console.log('再試行ボタンがクリックされました。ページをリロードします。');
        window.location.reload();
    });
}

// ページ読み込み時にLIFF初期化を実行
window.addEventListener('DOMContentLoaded', initializeLiff);

/**
 * LIFF初期化関数
 * DOMContentLoaded時に呼び出される
 */
async function initializeLiff() {
    try {
        // 初期化プロミスを保存 (他の場所から参照できるように)
        if (!window.liffInitializationPromise) {
            window.liffInitializationPromise = liffInit();
        }
        
        // liffInit関数を呼び出し
        const result = await window.liffInitializationPromise;
        debugLog('LIFF初期化完了:', result);
        
        // グローバル変数に設定
        if (result.profile) {
            lineUserId = result.profile.userId;
            lineDisplayName = result.profile.displayName;
            lineProfileImage = result.profile.pictureUrl;
        }
        
        // プロフィール情報をデバッグ出力
        debugLog('ディスパッチするプロフィール情報:', {
            userId: lineUserId,
            displayName: lineDisplayName,
            pictureUrl: lineProfileImage,
            isLoggedIn: result.isLoggedIn
        });

        // 初期化完了イベントをディスパッチ
        const event = new CustomEvent('liffInitComplete', { 
            detail: { 
                userId: lineUserId,
                displayName: lineDisplayName,
                pictureUrl: lineProfileImage, // 重要: 画像URLを明示的に含める
                isLoggedIn: result.isLoggedIn,
                idToken: window.idToken,
                profile: result.profile // 完全なプロフィールオブジェクトも含める
            } 
        });
        window.dispatchEvent(event);
        debugLog('liffInitCompleteイベントをディスパッチしました');
    } catch (error) {
        console.error('LIFF初期化エラー:', error);
        debugLog('エラー詳細:', error.message);
        
        // 自動リカバリーを試みる
        if (!liff.isLoggedIn() && !liff.isInClient()) {
            debugLog('自動リカバリー: ログインページへリダイレクト');
            setTimeout(() => liff.login(), 1000);
            return;
        }
        
        // エラー表示
        showError('LINE連携に失敗しました: ' + error.message);
    }
}

/**
 * LIFF初期化が完了するのを待つ関数
 * APIリクエストの前に呼び出して、確実に認証完了後に実行させる
 */
window.waitForLiffInit = async function() {
    if (window.liffInitialized && window.idToken) {
        return true;
    }
    
    if (!window.liffInitializationPromise) {
        // 初期化がまだ開始されていない場合は開始
        debugLog('LIFF初期化を開始します');
        window.liffInitializationPromise = liffInit();
    }
    
    try {
        // 初期化の完了を待つ
        await window.liffInitializationPromise;
        return true;
    } catch (error) {
        debugLog('LIFF初期化完了待機中にエラー:', error);
        return false;
    }
}; 
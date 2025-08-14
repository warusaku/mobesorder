/**
 * グローバルエラー処理モジュール
 * 全てのJavaScriptエラーを捕捉し、詳細なログを提供します
 */

// エラーログのレベル設定（1: 最小限、2: 詳細、3: 全て）
const ERROR_LOG_LEVEL = 3;

// グローバルなデバッグログレベル - 各ファイルで共有
window.DEBUG_LEVEL = 3; // 1: エラーのみ, 2: 警告含む, 3: すべてのログ

// 無視すべきエラーパターン
const IGNORED_ERROR_PATTERNS = [
    /polyfills-/i,
    /modulepreload/i,
    /chunk-/i,
    /Failed to load resource/i,
    /Cannot read properties of null/i,
    /LIFF does not exist/i
];

// エラーのカウント
let errorCount = 0;
let lastErrorTimestamp = 0;

// エラーをキャプチャして処理する関数
function handleJavaScriptError(event) {
    // 無視すべきエラーパターンをチェック
    for (const pattern of IGNORED_ERROR_PATTERNS) {
        if (event.message && pattern.test(event.message)) {
            console.debug(`無視されたエラー: ${event.message}`);
            return;
        }
        
        if (event.filename && pattern.test(event.filename)) {
            console.debug(`無視されたエラー (ソース): ${event.filename}`);
            return;
        }
    }

    const now = Date.now();
    errorCount++;

    // タイムスタンプ
    const timestamp = new Date().toISOString();
    
    // エラー情報を構造化
    const errorInfo = {
        count: errorCount,
        timeSinceLastError: lastErrorTimestamp ? now - lastErrorTimestamp : 0,
        timestamp: timestamp,
        message: event.message || 'Unknown error',
        source: event.filename || 'Unknown source',
        line: event.lineno || 0,
        column: event.colno || 0,
        stack: event.error ? event.error.stack : null,
        type: event.error ? event.error.name : 'Unknown',
        userAgent: navigator.userAgent,
        url: window.location.href
    };
    
    // エラーをコンソールに詳細表示
    console.error(`[ERROR_HANDLER][${timestamp}] JavaScript Error #${errorCount}:`);
    console.error(`場所: ${errorInfo.source} (${errorInfo.line}:${errorInfo.column})`);
    console.error(`メッセージ: ${errorInfo.message}`);
    console.error(`タイプ: ${errorInfo.type}`);
    
    if (ERROR_LOG_LEVEL >= 2 && errorInfo.stack) {
        console.error('スタックトレース:');
        console.error(errorInfo.stack);
    }
    
    if (ERROR_LOG_LEVEL >= 3) {
        console.error('詳細情報:', errorInfo);
        
        // DOM構造の診断
        try {
            const domStatus = {
                readyState: document.readyState,
                scriptsCount: document.scripts.length,
                scripts: Array.from(document.scripts).map(s => s.src || 'inline'),
                bodyExists: !!document.body,
                headExists: !!document.head
            };
            console.error('DOM状態:', domStatus);
        } catch (e) {
            console.error('DOM状態の診断中にエラーが発生しました:', e);
        }
    }
    
    // DOM要素があれば画面にエラーを表示
    displayErrorOnScreen(errorInfo);
    
    // 最後のエラー時間を更新
    lastErrorTimestamp = now;
}

// Promise エラーを処理する関数
function handleUnhandledRejection(event) {
    const now = Date.now();
    errorCount++;
    
    const timestamp = new Date().toISOString();
    const reason = event.reason || {};
    
    const errorInfo = {
        count: errorCount,
        timeSinceLastError: lastErrorTimestamp ? now - lastErrorTimestamp : 0,
        timestamp: timestamp,
        message: reason.message || 'Unhandled Promise rejection',
        stack: reason.stack || null,
        type: 'UnhandledPromiseRejection',
        userAgent: navigator.userAgent,
        url: window.location.href
    };
    
    console.error(`[ERROR_HANDLER][${timestamp}] Unhandled Promise Rejection #${errorCount}:`);
    console.error(`メッセージ: ${errorInfo.message}`);
    
    if (ERROR_LOG_LEVEL >= 2 && errorInfo.stack) {
        console.error('スタックトレース:');
        console.error(errorInfo.stack);
    }
    
    if (ERROR_LOG_LEVEL >= 3) {
        console.error('詳細情報:', reason);
    }
    
    // DOM要素があれば画面にエラーを表示
    displayErrorOnScreen(errorInfo);
    
    // 最後のエラー時間を更新
    lastErrorTimestamp = now;
}

// 画面上にエラーを表示する関数
function displayErrorOnScreen(errorInfo) {
    // DOMが読み込まれていない場合は表示しない
    if (document.readyState === 'loading') {
        return;
    }
    
    try {
        // エラー表示用のコンテナが存在すれば使用、なければ作成
        let errorContainer = document.getElementById('js-error-container');
        
        if (!errorContainer) {
            errorContainer = document.createElement('div');
            errorContainer.id = 'js-error-container';
            errorContainer.style.cssText = `
                position: fixed;
                bottom: 10px;
                right: 10px;
                max-width: 80%;
                max-height: 200px;
                overflow-y: auto;
                background-color: rgba(255, 220, 220, 0.95);
                border: 1px solid #ff6b6b;
                border-radius: 5px;
                padding: 10px;
                font-family: monospace;
                font-size: 12px;
                color: #333;
                z-index: 10000;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            `;
            
            // クローズボタン
            const closeButton = document.createElement('button');
            closeButton.textContent = '×';
            closeButton.style.cssText = `
                position: absolute;
                top: 5px;
                right: 5px;
                background: none;
                border: none;
                font-size: 16px;
                cursor: pointer;
                color: #ff6b6b;
            `;
            closeButton.onclick = function() {
                errorContainer.style.display = 'none';
            };
            
            const title = document.createElement('div');
            title.textContent = 'JavaScript エラーログ';
            title.style.cssText = `
                font-weight: bold;
                margin-bottom: 5px;
                padding-bottom: 5px;
                border-bottom: 1px solid #ffb8b8;
            `;
            
            const errorList = document.createElement('div');
            errorList.id = 'js-error-list';
            
            errorContainer.appendChild(closeButton);
            errorContainer.appendChild(title);
            errorContainer.appendChild(errorList);
            
            // body要素に追加
            if (document.body) {
                document.body.appendChild(errorContainer);
            }
        }
        
        // エラーリストを取得
        const errorList = document.getElementById('js-error-list');
        if (errorList) {
            // エラー項目を作成
            const errorItem = document.createElement('div');
            errorItem.style.cssText = `
                margin-bottom: 10px;
                padding-bottom: 5px;
                border-bottom: 1px dotted #ffb8b8;
            `;
            
            // エラーの詳細情報
            const errorLocation = errorInfo.source ? 
                `${errorInfo.source.split('/').pop()} (${errorInfo.line}:${errorInfo.column})` : 
                '不明な場所';
            
            errorItem.innerHTML = `
                <div><strong>#${errorInfo.count}</strong> [${new Date().toLocaleTimeString()}]</div>
                <div>${errorInfo.message}</div>
                <div><small>${errorLocation}</small></div>
            `;
            
            // リストの先頭に追加（新しいエラーを上に表示）
            if (errorList.firstChild) {
                errorList.insertBefore(errorItem, errorList.firstChild);
            } else {
                errorList.appendChild(errorItem);
            }
            
            // 最大10件まで表示
            while (errorList.children.length > 10) {
                errorList.removeChild(errorList.lastChild);
            }
        }
    } catch (e) {
        // エラー表示中のエラーは無視（ループを防止）
        console.error('エラー表示中にエラーが発生しました:', e);
    }
}

// エラーとPromise拒否のイベントリスナーを登録
window.addEventListener('error', handleJavaScriptError);
window.addEventListener('unhandledrejection', handleUnhandledRejection);

// カスタムエラーログ関数
window.logError = function(message, source, additionalInfo) {
    try {
        const error = new Error(message);
        const errorEvent = {
            message: message,
            filename: source || 'Custom error',
            lineno: 0,
            colno: 0,
            error: error
        };
        
        if (additionalInfo) {
            console.error('追加情報:', additionalInfo);
        }
        
        handleJavaScriptError(errorEvent);
    } catch (e) {
        console.error('カスタムエラーログ中にエラーが発生しました:', e);
    }
};

// スクリプト実行順序の確認
window.scriptsLoaded = window.scriptsLoaded || {};
window.logScriptLoad = function(scriptName) {
    const timestamp = Date.now();
    window.scriptsLoaded[scriptName] = timestamp;
    console.log(`[SCRIPT_LOAD] ${scriptName} loaded at ${new Date(timestamp).toISOString()}`);
    
    // 最低限のスクリプト確認リスト
    const requiredScripts = ['error-handler.js', 'api.js', 'ui.js', 'cart.js', 'app.js'];
    const loadedRequired = requiredScripts.filter(script => window.scriptsLoaded[script]);
    
    if (loadedRequired.length === requiredScripts.length) {
        console.log(`[SCRIPT_LOAD] すべての必須スクリプトが読み込まれました (${loadedRequired.length} / ${requiredScripts.length})`);
    } else {
        console.log(`[SCRIPT_LOAD] 必須スクリプト読み込み状況: ${loadedRequired.length} / ${requiredScripts.length}`);
    }
};

// このスクリプト自体の読み込みを記録
window.logScriptLoad('error-handler.js');

// DOMの読み込み完了を監視
document.addEventListener('DOMContentLoaded', function() {
    console.log('[ERROR_HANDLER] DOM Content Loaded at ' + new Date().toISOString());
    
    // 既存のエラーがあれば表示
    if (errorCount > 0) {
        console.log(`[ERROR_HANDLER] ${errorCount} エラーが既に発生しています`);
    }
});

// ページの完全な読み込みを監視
window.addEventListener('load', function() {
    console.log('[ERROR_HANDLER] Window Loaded at ' + new Date().toISOString());
    console.log('[ERROR_HANDLER] スクリプト読み込み状況:', window.scriptsLoaded);
}); 
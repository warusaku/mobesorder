"use strict";

/**
 * モバイルオーダーアプリケーションのメインファイル
 * 各モジュールの連携や初期化処理を行う
 */

// アプリケーションの状態
const app = {
  initialized: false,
  loading: true,
  error: null
};

/**
 * アプリケーションの初期化
 * DOMコンテンツロード後に実行される
 */
async function initializeApp() {
  if (app.initialized) return;
  try {
    // APIインスタンスをグローバルに設定
    window.apiClient = new API();

    // LIFFの初期化が完了するまで待機
    await waitForLiffInitialization();

    // カートの初期化
    // loadCartFromStorage()はcart.jsで定義済み、DOMContentLoaded時に実行される

    // UI要素の初期化
    // initCartModal()とinitOrderCompleteModal()はui.jsで定義済み、DOMContentLoaded時に実行される

    // 画面サイズに応じたレイアウト調整
    adjustLayoutForScreenSize();

    // ウィンドウリサイズ時の処理
    window.addEventListener('resize', adjustLayoutForScreenSize);
    app.initialized = true;
    app.loading = false;
  } catch (error) {
    console.error('アプリケーション初期化エラー:', error);
    app.error = error.message || 'アプリケーションの初期化中にエラーが発生しました';
    app.loading = false;

    // エラー表示
    showError(app.error);
  }
}

/**
 * LIFFの初期化完了を待機する
 * @returns {Promise} LIFF初期化完了時に解決されるPromise
 */
function waitForLiffInitialization() {
  console.log('LIFF初期化待機開始...');

  // チェック用タイマーのID
  let checkIntervalId = null;
  // タイムアウト用タイマーのID
  let timeoutId = null;
  return new Promise((resolve, reject) => {
    // グローバル変数と実行タイミングを初期ログ
    console.log(`初期状態: userProfile=${typeof userProfile}, window.userProfile=${typeof window.userProfile}`, typeof userProfile !== 'undefined' ? `userProfile.userId存在=${userProfile && userProfile.userId ? 'あり' : 'なし'}` : '');

    // グローバルのwaitForLiffInit関数が利用可能ならそれを使う
    if (typeof window.waitForLiffInit === 'function') {
      console.log('グローバルwaitForLiffInit関数を使用します');
      window.waitForLiffInit().then(result => {
        console.log('LIFF初期化が正常に完了しました');
        resolve(result);
      }).catch(error => {
        console.error('LIFF初期化エラー:', error);
        reject(error);
      });
      return;
    }

    // 旧来のポーリング方式をフォールバックとして維持
    console.log('レガシーポーリング方式でLIFF初期化を待機します');

    // LIFFが初期化されるまで定期的にチェック
    checkIntervalId = setInterval(() => {
      console.log(`チェック: userProfile=${typeof userProfile}`, typeof userProfile !== 'undefined' ? `userProfile.userId存在=${userProfile && userProfile.userId ? 'あり' : 'なし'}` : '', `window.liffService=${typeof window.liffService}`, typeof window.liffService !== 'undefined' ? `初期化済み=${window.liffService.initialized ? 'はい' : 'いいえ'}` : '');

      // ユーザープロフィール取得済みかチェック
      if (typeof userProfile !== 'undefined' && userProfile) {
        console.log('userProfile が設定されています。LIFF初期化成功と判断します。');
        clearTimeout(timeoutId);
        clearInterval(checkIntervalId);
        resolve();
        return;
      }

      // LiffServiceが初期化済みかチェック
      if (typeof window.liffService !== 'undefined' && window.liffService && window.liffService.initialized) {
        console.log('window.liffService.initialized=true. LIFF初期化成功と判断します。');
        clearTimeout(timeoutId);
        clearInterval(checkIntervalId);
        resolve();
        return;
      }

      // LIFF SDKの状態確認を追加
      if (typeof window.liff !== 'undefined') {
        console.log('LIFF SDK状態:', 'isInClient=', typeof window.liff.isInClient === 'function' ? window.liff.isInClient() : '不明', 'isLoggedIn=', typeof window.liff.isLoggedIn === 'function' ? window.liff.isLoggedIn() : '不明', 'getIDToken=', typeof window.liff.getIDToken === 'function' ? window.liff.getIDToken() ? '取得済み' : 'なし' : '不明');
      }
    }, 1000); // チェック間隔を1秒に延長

    // タイムアウト処理
    timeoutId = setTimeout(() => {
      clearInterval(checkIntervalId);
      // タイムアウト時の詳細情報をログ
      console.error('LIFF初期化タイムアウト - 詳細情報:', `userProfile=${typeof userProfile}`, typeof userProfile !== 'undefined' ? `userProfile.userId存在=${userProfile && userProfile.userId ? 'あり' : 'なし'}` : '', `window.liffService=${typeof window.liffService}`, typeof window.liffService !== 'undefined' ? `初期化済み=${window.liffService.initialized ? 'はい' : 'いいえ'}` : '', `window.liff=${typeof window.liff}`, typeof window.liff !== 'undefined' ? `isLoggedIn=${window.liff.isLoggedIn ? window.liff.isLoggedIn() : '関数なし'}` : '');

      // LIFFサービスが存在しない場合は別のエラーメッセージ
      if (typeof window.liffService === 'undefined') {
        reject(new Error('LIFF初期化のタイムアウト: LiffServiceオブジェクトが存在しません'));
        return;
      }

      // 既存のLIFFサービスの状態を確認
      if (window.liffService && window.liffService._promise) {
        window.liffService._promise.then(() => {
          console.log('遅延LIFF初期化成功');
          resolve();
        }).catch(error => {
          console.error('遅延LIFF初期化エラー:', error);
          reject(new Error('LIFF初期化エラー: ' + (error.message || '不明なエラー')));
        });
      } else {
        reject(new Error('LIFF初期化のタイムアウト'));
      }
    }, 30000); // 30秒に延長
  });
}

/**
 * 画面サイズに応じたレイアウト調整
 */
function adjustLayoutForScreenSize() {
  const isMobile = window.innerWidth <= 768;
  const isSmallScreen = window.innerWidth <= 480;

  // モバイル表示の場合の調整
  if (isMobile) {
    // スマートフォン向けのレイアウト調整
    document.body.classList.add('mobile-view');
    if (isSmallScreen) {
      document.body.classList.add('small-screen');
    } else {
      document.body.classList.remove('small-screen');
    }
  } else {
    // デスクトップ向けのレイアウト調整
    document.body.classList.remove('mobile-view', 'small-screen');
  }
}

/**
 * エラーメッセージを表示
 * @param {string} message - 表示するエラーメッセージ
 */
function showError(message) {
  const errorContainer = document.getElementById('error-container');
  const errorMessage = document.getElementById('error-message');
  const loadingElement = document.getElementById('loading');
  if (errorMessage) {
    errorMessage.textContent = message;
  }
  if (errorContainer) {
    errorContainer.style.display = 'flex';
  }
  if (loadingElement) {
    loadingElement.style.display = 'none';
  }

  // 再試行ボタンのイベントリスナを設定
  const retryButton = document.getElementById('retry-button');
  if (retryButton) {
    retryButton.addEventListener('click', function () {
      location.reload();
    });
  }
}

/**
 * エラーメッセージをコンソールに記録
 * @param {string} message - エラーメッセージ
 * @param {Error} error - エラーオブジェクト
 */
function logError(message, error) {
  console.error(message, error);

  // 開発環境では詳細なエラー情報を表示
  if (isDevelopment()) {
    console.debug('エラー詳細:', {
      message: error.message,
      stack: error.stack,
      timestamp: new Date().toISOString()
    });
  }
}

/**
 * 開発環境かどうかを判定
 * @returns {boolean} 開発環境の場合はtrue
 */
function isDevelopment() {
  // URLから開発環境かどうかを判定
  return window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1' || window.location.hostname.includes('.local');
}

// ページ読み込み時にアプリケーションを初期化
document.addEventListener('DOMContentLoaded', initializeApp);

// アプリケーションのグローバルエラーハンドリング
window.addEventListener('error', event => {
  logError('グローバルエラー:', event.error || new Error(event.message));

  // エラー表示（UIが初期化されている場合）
  if (app.initialized) {
    showError('予期しないエラーが発生しました。ページを再読み込みしてください。');
  }
});
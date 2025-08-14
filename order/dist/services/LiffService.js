"use strict";

(function (global) {
  'use strict';

  const DEBUG = true;
  function log() {
    if (DEBUG) {
      for (var _len = arguments.length, a = new Array(_len), _key = 0; _key < _len; _key++) {
        a[_key] = arguments[_key];
      }
      console.log('[LiffService]', ...a);
    }
  }

  // 再試行回数の追加
  const MAX_RETRY_COUNT = 3;
  let initRetryCount = 0;
  function LiffService(liffId) {
    this.liffId = liffId;
    this.initialized = false;
    this.profile = null;
    this.idToken = null;
    this._promise = null;
    log('LiffService インスタンス作成: liffId=', liffId);

    // LIFF SDKが読み込まれているか確認
    if (typeof global.liff === 'undefined') {
      log('警告: LIFF SDKが読み込まれていません。スクリプトの読み込み順序を確認してください。');
    } else {
      log('LIFF SDK 検出: OK');
    }
  }
  LiffService.prototype.init = function () {
    if (this._promise) {
      log('すでに初期化中/済です。既存のPromiseを返します。');
      return this._promise;
    }
    const self = this;
    log('初期化開始 - LIFF ID:', self.liffId);
    this._promise = new Promise((resolve, reject) => {
      // 明示的なPromise構文を使用
      if (typeof global.liff === 'undefined') {
        log('エラー: LIFF SDK not loaded - グローバルliffオブジェクトがありません');
        reject(new Error('LIFF SDK not loaded'));
        return;
      }
      log('init start', self.liffId, 'SDKバージョン:', typeof global.liff.getVersion === 'function' ? global.liff.getVersion() : '不明');
      log('LocalStorage状態:', 'アクセス可能=', checkLocalStorageAccess());
      log('Cookieアクセス:', document.cookie ? 'Cookie存在' : 'Cookie空または制限');
      log('URL情報:', 'protocol=', location.protocol, 'hostname=', location.hostname, 'port=', location.port, 'pathname=', location.pathname, 'search=', location.search, 'hash=', location.hash);

      // URL情報の詳細ログ
      if (location.search) {
        const params = new URLSearchParams(location.search);
        log('URL パラメータ一覧:', Array.from(params.keys()).join(', '));
        if (params.has('code')) {
          log('codeパラメータ検出: 長さ=', params.get('code').length);
        }
      }

      // SDK の状態をログ
      if (typeof global.liff !== 'undefined') {
        try {
          log('SDK version:', global.liff.getVersion ? global.liff.getVersion() : 'unknown');
          log('OS:', global.liff.getOS ? global.liff.getOS() : 'unknown');
          log('Language:', global.liff.getLanguage ? global.liff.getLanguage() : 'unknown');

          // 使用可能な関数の確認
          log('LIFF関数確認:', 'init=', typeof global.liff.init === 'function' ? '関数' : '未定義', 'getProfile=', typeof global.liff.getProfile === 'function' ? '関数' : '未定義', 'getIDToken=', typeof global.liff.getIDToken === 'function' ? '関数' : '未定義', 'isLoggedIn=', typeof global.liff.isLoggedIn === 'function' ? '関数' : '未定義', 'login=', typeof global.liff.login === 'function' ? '関数' : '未定義');
        } catch (e) {
          log('SDK info error:', e.message);
        }
      }
      log('liff.init呼び出し開始...');

      // init呼び出しはasync/awaitではなくPromiseチェーンに変更
      global.liff.init({
        liffId: self.liffId
      }).then(() => {
        log('liff.init 完了');

        // Cookieの状態を確認
        const cookies = document.cookie.split(';').map(c => c.trim());
        log('LIFF初期化後のCookie数:', cookies.length);
        if (cookies.length > 0) {
          // Cookie名のみログ出力 (値は出力しない)
          log('Cookie名:', cookies.map(c => c.split('=')[0]).join(', '));
        }

        // 初期化後の状態をログ
        log('LIFF初期化後の状態:', 'isLoggedIn=', global.liff.isLoggedIn(), 'isInClient=', typeof global.liff.isInClient === 'function' ? global.liff.isInClient() : '不明', 'getOS=', typeof global.liff.getOS === 'function' ? global.liff.getOS() : '不明', 'getLanguage=', typeof global.liff.getLanguage === 'function' ? global.liff.getLanguage() : '不明');

        // ブラウザからのアクセスでログインしていない場合は自動ログイン
        if (!global.liff.isLoggedIn()) {
          log('未ログイン状態を検出。login()を呼び出す前にコードパラメータ確認');

          // URLにcodeパラメータがあるか確認
          const urlParams = new URLSearchParams(global.location.search);
          const hasCode = urlParams.has('code');
          log('URLにcodeパラメータ:', hasCode ? 'あり' : 'なし');
          const attempted = sessionStorage.getItem('LIFF_LOGIN_ATTEMPTED');
          if (!attempted) {
            log('First login attempt');

            // LocalStorageが使えるか確認
            try {
              sessionStorage.setItem('LIFF_TEST', 'test');
              const test = sessionStorage.getItem('LIFF_TEST');
              if (test !== 'test') {
                log('警告: sessionStorageに書き込めましたが、読み取り値が異なります');
              } else {
                sessionStorage.removeItem('LIFF_TEST');
                log('sessionStorage動作確認OK');
              }
            } catch (storageErr) {
              log('sessionStorage例外:', storageErr.message);
            }
            sessionStorage.setItem('LIFF_LOGIN_ATTEMPTED', '1');
            log('LIFF_LOGIN_ATTEMPTEDフラグ設定完了');

            // 現在のURLを保存
            const currentUrl = global.location.href;
            log('現在のURLへリダイレクト:', currentUrl);
            try {
              log('liff.login呼び出し直前');
              global.liff.login({
                redirectUri: global.location.href
              });
              log('liff.login呼び出し完了 - リダイレクト待機中');
              return; // リダイレクト後に処理が継続されるのでここで終了
            } catch (loginErr) {
              log('LOGIN ERROR:', loginErr.message, loginErr.stack);
              reject(new Error('LINEログイン呼び出しエラー: ' + loginErr.message));
              return;
            }
          }

          // Already attempted once and still not logged
          log('既にログイン試行済みだがまだログインしていない → 再リダイレクトせず中断');
          log('sessionStorage:', 'LIFF_LOGIN_ATTEMPTED=', attempted);
          sessionStorage.removeItem('LIFF_LOGIN_ATTEMPTED');
          reject(new Error('LINEログインに失敗しました。再度お試しください。'));
          return;
        }
        log('Login status: isLoggedIn=true');

        // IDトークンの状態確認
        try {
          const idToken = global.liff.getIDToken();
          log('IDトークン取得:', idToken ? '成功 (長さ:' + idToken.length + ')' : '失敗 (null/undefined)');
          if (idToken) {
            // トークンの先頭部分だけを表示（セキュリティのため）
            log('IDトークン先頭:', idToken.substring(0, 10) + '...');

            // JWTトークンの構造確認
            const parts = idToken.split('.');
            if (parts.length === 3) {
              log('JWTトークン構造OK: ヘッダ.ペイロード.署名');
              try {
                // ヘッダー部分をデコード (base64url → JSON)
                const header = JSON.parse(atob(parts[0].replace(/-/g, '+').replace(/_/g, '/')));
                log('JWTヘッダ:', 'alg=', header.alg, 'typ=', header.typ);
              } catch (jwtErr) {
                log('JWTヘッダデコードエラー:', jwtErr.message);
              }
            } else {
              log('警告: IDトークンがJWT形式ではありません (期待:3パート, 実際:' + parts.length + 'パート)');
            }
          } else {
            log('IDトークンが取得できません。認証に問題がある可能性があります。');
          }
        } catch (tokenErr) {
          log('IDトークン取得エラー:', tokenErr.message);
        }
        self.idToken = global.liff.getIDToken();

        // プロファイル取得処理も明示的なPromiseチェーンへ変更
        log('Getting profile - API呼び出し開始');
        // プロファイル取得前のLIFF状態
        log('LIFF状態:', 'isInClient=', global.liff.isInClient ? global.liff.isInClient() : 'undefined', 'isLoggedIn=', global.liff.isLoggedIn());
        log('liff.getProfile呼び出し直前');
        return global.liff.getProfile().then(profile => {
          log('liff.getProfile呼び出し完了');
          if (!profile) {
            log('プロファイル取得: 結果がnullまたはundefinedです');
            throw new Error('プロフィール取得に失敗しました: 結果が空です');
          }
          log('プロファイル取得成功:', 'userId=', profile.userId ? profile.userId.substring(0, 5) + '...' : 'undefined', 'displayName=', profile.displayName || 'undefined', 'pictureUrl=', profile.pictureUrl ? '存在' : 'なし');
          self.profile = profile;
          global.userProfile = profile;
          log('global.userProfile設定完了');
          self.initialized = true;
          log('init ok');

          // カスタムイベントを発火
          try {
            log('Dispatching liffReady event');
            global.dispatchEvent(new CustomEvent('liffReady', {
              detail: self
            }));
            log('liffReady dispatched successfully');
          } catch (eventErr) {
            log('Error dispatching liffReady:', eventErr);
          }
          resolve(self);
        });
      }).catch(err => {
        log('liff.init 失敗:', err.name, err.message);
        log('初期化エラー詳細:', err.stack || 'スタックトレースなし');

        // 再試行ロジック
        if (initRetryCount < MAX_RETRY_COUNT) {
          initRetryCount++;
          log(`LIFF初期化再試行 (${initRetryCount}/${MAX_RETRY_COUNT})`);

          // 既存のPromiseを無効化し、再初期化
          setTimeout(() => {
            self._promise = null; // 既存のPromiseを無効化
            const newPromise = self.init();
            newPromise.then(resolve).catch(reject);
          }, 1000);
        } else {
          reject(err);
        }
      });
    });
    return this._promise;
  };
  LiffService.prototype.wait = function () {
    if (!this._promise) {
      log('wait()が呼ばれましたが、初期化が開始されていません');
      return Promise.reject(new Error('call init first'));
    }
    log('wait()が呼ばれました、初期化完了を待機します');
    return this._promise;
  };
  global.LiffService = LiffService;

  // 自動初期化: index.php で LIFF_ID が設定済みの場合
  if (global.LIFF_ID) {
    log('Auto init with LIFF_ID', global.LIFF_ID);
    global.liffService = new LiffService(global.LIFF_ID);
    // 初期化を開始するが完了は待たない（必要な時にwait()を使用）
    global.liffService.init();
  } else {
    log('LIFF_ID not defined. Skip auto init.');
  }

  // 新しいユーティリティ関数を追加
  function checkLocalStorageAccess() {
    try {
      const testKey = '_liff_test_';
      localStorage.setItem(testKey, '1');
      const value = localStorage.getItem(testKey);
      localStorage.removeItem(testKey);
      return value === '1' ? true : 'アクセスできるが正しく動作していません';
    } catch (e) {
      return 'エラー: ' + e.message;
    }
  }

  // グローバル関数として登録
  global.waitForLiffInit = function () {
    log('waitForLiffInit()が呼ばれました');
    if (!global.liffService) {
      log('エラー: liffServiceが存在しません');
      return Promise.reject(new Error('LiffService not initialized'));
    }
    return global.liffService.wait();
  };
})(window);
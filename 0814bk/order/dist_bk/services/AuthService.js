"use strict";

(function (global) {
  'use strict';

  const ENDPOINT = 'https://test-mijeos.but.jp/fgsquare/api/v1/auth';
  function log() {
    for (var _len = arguments.length, a = new Array(_len), _key = 0; _key < _len; _key++) {
      a[_key] = arguments[_key];
    }
    console.log('[AuthService]', ...a);
  }
  function AuthService() {}
  AuthService.prototype.fetchRoomInfo = async function (lineUserId) {
    const url = `${ENDPOINT}?line_user_id=${encodeURIComponent(lineUserId)}`;
    log('GET', url);
    const res = await fetch(url);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    return data;
  };

  // listen liffReady
  global.addEventListener('liffReady', async e => {
    try {
      const svc = new AuthService();
      const data = await svc.fetchRoomInfo(e.detail.profile.userId);
      log('roomInfo', data);
      if (global.processRoomInfo) {
        global.processRoomInfo(data);
      }
      if (global.onInitialized) {
        global.onInitialized();
      }
    } catch (err) {
      console.error('AuthService error', err);
      if (global.showError) {
        global.showError('部屋情報取得に失敗しました', err.message);
      }
    }
  });
  global.AuthService = AuthService;
})(window);
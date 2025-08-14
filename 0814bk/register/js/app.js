/**
 * 部屋番号登録アプリケーション
 * @version 2.0.0
 */
document.addEventListener('DOMContentLoaded', function() {
    // DOM要素
    const loadingElement = document.getElementById('loading');
    const contentContainer = document.getElementById('content-container');
    const errorContainer = document.getElementById('error-container');
    const errorMessage = document.getElementById('error-message');
    const retryButton = document.getElementById('retry-button');
    const registerForm = document.getElementById('room-register-form');
    const roomSelectButton = document.getElementById('room-select-button');
    const roomNumberInput = document.getElementById('room-number');
    const selectedRoomSpan = document.getElementById('selected-room');
    const userNameInput = document.getElementById('user-name-input');
    const checkInDateInput = document.getElementById('check-in-date');
    const checkOutDateInput = document.getElementById('check-out-date');
    const checkoutTimeSelect = document.getElementById('checkout-time');
    const registerButton = document.getElementById('register-button');
    const registerCompleteModal = document.getElementById('register-complete-modal');
    const registeredRoomNumber = document.getElementById('registered-room-number');
    const registeredUserName = document.getElementById('registered-user-name');
    const registeredStayPeriod = document.getElementById('registered-stay-period');
    const goToOrderButton = document.getElementById('go-to-order-button');
    const profileImage = document.getElementById('profile-image');
    const profileImageSmall = document.getElementById('profile-image-small');
    const userNameElement = document.getElementById('user-name');
    const userNameSmallElement = document.getElementById('user-name-small');

    // アプリ状態
    let sentinelInfo = null;    // Sentinel 応答保持
    let registerSettings = null; // register_settings セクション保持
    let loginSettings = null;    // login_settings セクション保持

    let state = {
        profile: null,
        rooms: [],
        selectedRoom: null,
        isRegistering: false,
        isLoggedIn: false,
        qrRoomParam: null  // QRコードから渡された部屋番号
    };

    // URLからパラメータを取得
    function getUrlParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    }

    // 初期化
    initApp();

    /**
     * アプリの初期化
     */
    function initApp() {
        // QRコードからのアクセスで部屋番号が指定されている場合、保存しておく
        const roomParam = getUrlParam('room');
        if (roomParam) {
            // URLデコードしてパラメータを保存
            state.qrRoomParam = decodeURIComponent(roomParam);
            console.log('QRコードから部屋番号を取得:', state.qrRoomParam);
        } else {
            // URLパラメータがない場合、sessionStorageから復元を試みる
            const savedRoomParam = sessionStorage.getItem('qr_room_param');
            if (savedRoomParam) {
                state.qrRoomParam = decodeURIComponent(savedRoomParam);
                console.log('sessionStorageから部屋番号を復元:', state.qrRoomParam);
                // 使用後は削除
                sessionStorage.removeItem('qr_room_param');
            }
        }

        // 現在の日付をデフォルト値として設定
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(today.getDate() + 1);
        
        const formatDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };
        
        // チェックイン日のフィールドを非表示にして、テキスト表示に変更
        replaceCheckInDateWithText(formatDate(today));
        
        checkOutDateInput.value = formatDate(tomorrow);
        
        // チェックアウト日の最小値を設定
        checkOutDateInput.min = formatDate(tomorrow);

        // 初期画面としてローディングを表示
        toggleLoading(true, 'LINE認証を確認中...');

        // LIFF初期化完了イベントのリスナーを設定
        window.addEventListener('liffInitComplete', async (event) => {
            console.log('LIFF初期化完了イベントを受信:', event.detail);
            
            try {
                // プロフィール情報を更新
                state.profile = event.detail;
                state.isLoggedIn = event.detail.isLoggedIn;
                
                // ユーザープロフィール表示
                if (state.profile) {
                    // プロフィール画像の取得（構造が変わっている可能性があるため詳細にチェック）
                    let pictureUrl = null;
                    
                    // LINEプロフィール画像のURLを様々な構造から取得を試みる
                    if (state.profile.pictureUrl) {
                        pictureUrl = state.profile.pictureUrl;
                    } else if (state.profile.profile && state.profile.profile.pictureUrl) {
                        pictureUrl = state.profile.profile.pictureUrl;
                    } else if (lineProfileImage) {
                        pictureUrl = lineProfileImage;
                    }
                    
                    // デバッグログ
                    console.log('プロフィール画像URL:', pictureUrl);
                    
                    // 画像URLの設定
                    const defaultImg = 'https://mobes.online/images/default-profile.png';
                    profileImage.src = pictureUrl || defaultImg;
                    profileImageSmall.src = pictureUrl || defaultImg;
                    
                    // 名前の表示
                    const displayName = state.profile.displayName || 
                                      (state.profile.profile ? state.profile.profile.displayName : null) || 
                                      lineDisplayName ||
                                      'ゲスト';
                    
                    userNameElement.textContent = displayName;
                    userNameSmallElement.textContent = displayName;
                    userNameInput.value = displayName || '';
                }

                // 部屋情報取得は独立したプロセスとして実行
                initializeRoomData();

                // プロフィール情報をデバッグ出力
                debugLog('ディスパッチするプロフィール情報:', {
                    userId: lineUserId,
                    displayName: lineDisplayName,
                    pictureUrl: lineProfileImage,
                    isLoggedIn: event.detail.isLoggedIn
                });

                /* ---------------- ユーザー状態チェック ---------------- */
                (async function(){
                    try {
                        if(!lineUserId){ return; }
                        const statusRes = await fetch('api/register_Sentinel.php',{
                            method:'POST',
                            headers:{'Content-Type':'application/x-www-form-urlencoded'},
                            body: new URLSearchParams({line_user_id: lineUserId})
                        });
                        const statusJson = await statusRes.json();
                        console.log('[Sentinel] response', statusJson);

                        // 管理設定を取得
                        const cfgRes = await fetch('/admin/adminsetting_registrer.php?section=register_settings');
                        const cfgJson = await cfgRes.json();
                        registerSettings = (cfgJson && cfgJson.settings) ? cfgJson.settings : {};

                        // login_settings も取得
                        const loginRes = await fetch('/admin/adminsetting_registrer.php?section=login_settings');
                        const loginJson = await loginRes.json();
                        loginSettings = (loginJson && loginJson.settings) ? loginJson.settings : {};
                        console.log('[login_settings]', loginSettings);

                        // 未払いで部屋変更を禁止する設定がある場合
                        const unpaidChangeAllowed = !!(registerSettings && registerSettings["Unpaid room change"]);
                        if(statusJson.success && statusJson.has_active_room && statusJson.unpaid_total>0 && !unpaidChangeAllowed){
                            window.registerInformModal && window.registerInformModal.show('Unpaid room change_alert', {room_number: statusJson.room_number});
                            // 部屋番号変更不可: ボタンを無効化
                            const btn = document.getElementById('room-select-button');
                            if(btn){ btn.disabled = true; btn.classList.add('disabled'); }

                            // ドラムロールに現在の部屋番号を強制表示
                            if(statusJson.room_number){
                                autoSelectRoom(statusJson.room_number);
                            }

                            // 登録ボタンを『モバイルオーダーへ』に変更し、遷移のみ行う
                            registerButton.textContent = 'モバイルオーダーへ';
                            // フォーム送信を横取り（多重登録防止のため once オプションで一度だけ）
                            const newHandler = function(e){
                                e.preventDefault();
                                window.location.href = 'https://mobes.online/order/';
                            };
                            registerForm.addEventListener('submit', newHandler, { once: true });
                        }

                        sentinelInfo = statusJson;
                    } catch(err){ console.error('[Sentinel] error', err); }
                })();
            } catch (error) {
                console.error('プロフィール更新中にエラー:', error);
                // エラーがあっても部屋情報取得は試行
                initializeRoomData();
            }
        });

        // LIFF初期化エラー時の処理 (タイムアウト)
        setTimeout(() => {
            if (!window.liffInitialized && loadingElement.style.display !== 'none') {
                console.log('LIFF初期化タイムアウト');
                
                // リダイレクト回数をチェック（liff-init.jsと同じロジック）
                const redirectCount = parseInt(
                    sessionStorage.getItem('liff_redirect_count') || 
                    localStorage.getItem('liff_redirect_count') || 
                    '0'
                );
                
                if (redirectCount >= 2) {
                    // リダイレクト上限に達している場合は、エラーメッセージを表示
                    console.log('リダイレクト上限に達しています');
                    showError('LINE認証の再試行回数が上限に達しました。しばらく待ってから再度アクセスしてください。');
                    toggleLoading(false);
                    return;
                }
                
                // 上限に達していない場合のみ再読み込み
                toggleLoading(true, 'LINE認証がタイムアウトしました。再試行中...');
                
                // 自動的に再読み込み
                setTimeout(() => {
                    if (loadingElement.style.display !== 'none' && redirectCount < 2) {
                        window.location.reload();
                    }
                }, 3000);
            }
        }, 15000); // 15秒のタイムアウト（少し長めに設定）
        
        // イベントリスナー設定
        setupEventListeners();
    }

    /**
     * チェックイン日のフィールドをテキスト表示に置き換える
     * @param {string} dateStr - 表示する日付
     */
    function replaceCheckInDateWithText(dateStr) {
        // 日付をフォーマット (YYYY-MM-DD → YYYY/MM/DD)
        const dateParts = dateStr.split('-');
        const formattedDate = `${dateParts[0]}/${dateParts[1]}/${dateParts[2]}`;
        
        // 入力フィールドの親要素を取得
        const parentElement = checkInDateInput.parentElement;
        
        // 非表示の入力フィールドに値を保持
        checkInDateInput.type = 'hidden';
        checkInDateInput.value = dateStr;
        
        // テキスト表示用の要素を作成
        const displayElement = document.createElement('div');
        displayElement.className = 'checkin-display';
        displayElement.textContent = formattedDate;
        
        // フォームに挿入
        parentElement.appendChild(displayElement);
    }

    /**
     * ルーム情報を取得する
     */
    async function fetchRoomData() {
        try {
            // IDトークンの確認
            const idToken = liff.getIDToken();
            if (!idToken) {
                console.error('IDトークンの取得に失敗しました。再度ログインが必要です。');
                debugLog('IDトークン取得失敗 - fetchRoomData');
                
                // 未ログイン状態の場合、ログインを促す
                if (!liff.isLoggedIn()) {
                    debugLog('ログインが必要です');
                    
                    // ブラウザ環境でなければエラー表示
                    if (!liff.isInClient()) {
                        liff.login();
                        return;
                    }
                    
                    showError('LINEにログインしてください。');
                    return;
                }
                
                showError('認証情報の取得に失敗しました。ページをリロードしてください。');
                return;
            }
            
            debugLog('IDトークン取得成功: ' + idToken.substring(0, 20) + '...');
            
            // APIから部屋情報を取得
            console.log('部屋情報取得リクエスト開始');
            const roomsData = await fetchRooms();
            
            if (roomsData && roomsData.rooms && Array.isArray(roomsData.rooms)) {
                state.rooms = roomsData.rooms.map(room => ({
                    text: room,
                    value: room
                }));
                
                console.log('Room data loaded:', state.rooms);
            } else {
                console.error('部屋データの形式が不正です', roomsData);
                state.rooms = [];
            }
        } catch (error) {
            console.error('部屋データ取得エラー:', error);
            debugLog('部屋データ取得エラー: ' + error.message);
            showError('部屋情報の取得に失敗しました。ネットワーク接続を確認してください。');
        }
    }

    /**
     * イベントリスナーの設定
     */
    function setupEventListeners() {
        // 部屋選択ボタンがある場合のみイベントを追加
        if (roomSelectButton) {
            // 部屋選択ボタンクリック
            roomSelectButton.addEventListener('click', showRoomPicker);
            
            // 部屋選択ボタンを含む親要素を取得
            const roomNumberContainer = roomSelectButton.closest('.form-group');
            if (roomNumberContainer) {
                roomNumberContainer.classList.add('room-number-container');
            }
        }
        
        // 部屋登録フォーム送信
        registerForm.addEventListener('submit', handleRegistration);
        
        // 再試行ボタン
        retryButton.addEventListener('click', function() {
            window.location.reload();
        });
    }

    /**
     * URLパラメータで指定された部屋を自動選択
     * @param {string} roomNumber - 選択する部屋番号
     */
    function autoSelectRoom(roomNumber) {
        console.log('部屋番号を自動選択:', roomNumber);
        
        // 内部状態を更新
        state.selectedRoom = roomNumber;
        roomNumberInput.value = roomNumber;
        
        // 部屋番号選択ボタンの表示を強調
        roomSelectButton.textContent = roomNumber;
        roomSelectButton.classList.add('selected');
        
        // 部屋番号コンテナに選択済みクラスを追加
        const roomNumberContainer = roomSelectButton.closest('.room-number-container');
        if (roomNumberContainer) {
            roomNumberContainer.classList.add('has-selection');
        }
        
        // 部屋番号バナーを表示
        showRoomConfirmationBanner(roomNumber);
    }

    /**
     * 部屋選択用ピッカーを表示
     */
    function showRoomPicker() {
        if (state.rooms.length === 0) {
            showError('部屋情報が見つかりません。管理者にお問い合わせください。');
            return;
        }
        
        // ピッカーインスタンス作成
        const picker = new Picker({
            data: state.rooms,
            selectedIndex: state.selectedRoom ? state.rooms.findIndex(room => room.value === state.selectedRoom) : 0,
            title: '部屋番号を選択',
            onConfirm: function(value, text) {
                state.selectedRoom = value;
                roomNumberInput.value = value;
                
                // 部屋番号選択ボタンの表示を強調
                roomSelectButton.textContent = value;
                roomSelectButton.classList.add('selected');
                
                // 部屋番号コンテナに選択済みクラスを追加
                const roomNumberContainer = roomSelectButton.closest('.room-number-container');
                if (roomNumberContainer) {
                    roomNumberContainer.classList.add('has-selection');
                }
                
                // 部屋番号が選択されたら確認バナーを表示
                showRoomConfirmationBanner(value);
            },
            onChange: function(value, text) {
                console.log('Changed to:', value);
            },
            onCancel: function() {
                console.log('Picker cancelled');
            }
        });
        
        // ピッカー表示
        picker.show();
    }
    
    /**
     * 部屋番号選択確認バナーを表示
     * @param {string} roomNumber - 選択された部屋番号
     */
    function showRoomConfirmationBanner(roomNumber) {
        // 既存のバナーがあれば削除
        const existingBanner = document.querySelector('.room-confirmation-banner');
        if (existingBanner) {
            existingBanner.remove();
        }
        
        // バナー要素を作成
        const banner = document.createElement('div');
        banner.className = 'room-confirmation-banner';
        
        // URLパラメータ経由でアクセスした場合（QRコードなど）は編集アイコンを非表示
        if (state.qrRoomParam) {
            banner.classList.add('no-edit');
        }
        
        banner.innerHTML = `
            <div class="confirmation-text">選択された部屋番号</div>
            <div class="room-number-display">${roomNumber}</div>
            <div class="confirmation-text">部屋番号をご確認ください</div>
        `;
        
        // パラメータ経由のアクセスでなければクリックイベントを追加
        if (!state.qrRoomParam) {
            // バナーにクリックイベントを追加 (再選択用)
            banner.addEventListener('click', function() {
                showRoomPicker();
            });
        }
        
        // 部屋番号フォームグループに挿入
        const roomNumberContainer = roomSelectButton.closest('.room-number-container');
        if (roomNumberContainer) {
            roomNumberContainer.appendChild(banner);
        } else {
            // フォールバック：ユーザー名入力欄の前に挿入
            const userNameFormGroup = document.querySelector('.form-group:nth-child(2)');
            if (userNameFormGroup) {
                registerForm.insertBefore(banner, userNameFormGroup);
            } else {
                registerForm.appendChild(banner);
            }
        }
        
        // アニメーション表示
        setTimeout(() => {
            banner.classList.add('visible');
        }, 100);

        // --- 追加モーダル判定 --------------------------------------
        (async ()=>{
            try{
                // ② Notification of joining a room
                if(registerSettings && registerSettings["Notification of joining a room"]){
                    const occRes = await fetch('api/room_occupancy.php',{
                        method:'POST',
                        headers:{'Content-Type':'application/x-www-form-urlencoded'},
                        body:new URLSearchParams({room_number:roomNumber})
                    });
                    const occJson = await occRes.json();
                    if(occJson.success && occJson.number_of_people_in_room>0){
                        window.registerInformModal && window.registerInformModal.show('Notification of joining a room', {
                            room_number:roomNumber,
                            number_of_people_in_room: occJson.number_of_people_in_room,
                            usernames: occJson.usernames ? occJson.usernames.join('、') : ''
                        });
                    }
                }

                // ③ Notification of room change
                if(registerSettings && registerSettings["Notification of room change"] &&
                   sentinelInfo && sentinelInfo.has_active_room && sentinelInfo.room_number && sentinelInfo.room_number!==roomNumber){
                    window.registerInformModal && window.registerInformModal.show('Notification of room change', {room_number: sentinelInfo.room_number});
                }
            }catch(modalErr){ console.error('[Modal check] error', modalErr); }
        })();
    }

    /**
     * 部屋番号登録処理
     * @param {Event} event - フォームイベント
     */
    async function handleRegistration(event) {
        event.preventDefault();
        
        if (state.isRegistering) {
            return;
        }
        
        // 入力検証
        const roomNumber = roomNumberInput.value.trim();
        const userName = userNameInput.value.trim();
        const checkInDate = checkInDateInput.value;
        const checkOutDate = checkOutDateInput.value;
        const checkoutTime = checkoutTimeSelect.value;
        
        if (!roomNumber) {
            showError('部屋番号を選択してください');
            return;
        }
        
        // 登録中フラグをセット
        state.isRegistering = true;
        toggleLoading(true, '登録中...');
        
        try {
            // LIFFのIDトークン取得
            const idToken = liff.getIDToken();
            if (!idToken) {
                throw new Error('認証情報が取得できませんでした');
            }
            
            // 登録リクエスト
            const registrationResult = await registerRoom({
                token: idToken,
                roomNumber: roomNumber,
                userName: userName,
                checkInDate: checkInDate,
                checkOutDate: checkOutDate,
                checkOutTime: checkoutTime
            });
            
            if (registrationResult && registrationResult.success) {
                // 登録成功
                showRegistrationComplete(roomNumber, userName, checkInDate, checkOutDate, checkoutTime);
            } else {
                // エラーメッセージがあれば表示
                const message = registrationResult && registrationResult.message 
                    ? registrationResult.message 
                    : '登録処理に失敗しました。再度お試しください。';
                    
                showError(message);
            }
        } catch (error) {
            console.error('登録エラー:', error);
            showError('登録処理中にエラーが発生しました。ネットワーク接続を確認してください。');
        } finally {
            // 登録中フラグを解除
            state.isRegistering = false;
            toggleLoading(false);
        }
    }

    /**
     * 登録完了モーダルを表示
     */
    function showRegistrationComplete(roomNumber, userName, checkInDate, checkOutDate, checkoutTime) {
        registeredRoomNumber.textContent = roomNumber;
        registeredUserName.textContent = userName;
        
        // 滞在期間表示用のフォーマット
        const formatDateForDisplay = (dateString) => {
            const date = new Date(dateString);
            return `${date.getFullYear()}年${date.getMonth() + 1}月${date.getDate()}日`;
        };
        
        const checkInFormatted = formatDateForDisplay(checkInDate);
        const checkOutFormatted = formatDateForDisplay(checkOutDate);
        registeredStayPeriod.textContent = `${checkInFormatted} から ${checkOutFormatted} ${checkoutTime}`;
        
        // モーダル表示
        registerCompleteModal.style.display = 'block';
        
        // 5秒後に自動でモバイルオーダー画面へ遷移
        const countdownElement = document.createElement('p');
        countdownElement.className = 'auto-redirect-message';
        countdownElement.style.marginTop = '10px';
        countdownElement.style.fontSize = '14px';
        countdownElement.style.color = '#666';
        
        let countdown = 5;
        countdownElement.textContent = `${countdown}秒後に自動でモバイルオーダー画面へ移動します`;
        
        // モーダルフッターに追加
        const modalFooter = registerCompleteModal.querySelector('.modal-footer');
        modalFooter.appendChild(countdownElement);
        
        // カウントダウン処理
        const countdownInterval = setInterval(() => {
            countdown--;
            if (countdown > 0) {
                countdownElement.textContent = `${countdown}秒後に自動でモバイルオーダー画面へ移動します`;
            } else {
                clearInterval(countdownInterval);
                window.location.href = 'https://mobes.online/order/';
            }
        }, 1000);
        
        // 手動でボタンを押した場合はカウントダウンをクリア
        goToOrderButton.onclick = function() {
            clearInterval(countdownInterval);
            window.location.href = 'https://mobes.online/order/';
        };
    }

    /**
     * コンテンツを表示
     */
    function showContent() {
        loadingElement.style.display = 'none';
        contentContainer.style.display = 'block';
    }

    /**
     * エラーを表示（診断機能付き）
     * @param {string} message - エラーメッセージ
     * @param {boolean} runDiagnostics - 診断を実行するか
     */
    async function showError(message, runDiagnostics = true) {
        loadingElement.style.display = 'none';
        contentContainer.style.display = 'none';
        errorMessage.textContent = message;
        errorContainer.style.display = 'flex';
        
        // 診断機能を実行
        if (runDiagnostics && message.includes('認証') || message.includes('LINE') || message.includes('タイムアウト')) {
            try {
                // クライアント情報を収集
                const clientInfo = {
                    liffSdkAvailable: typeof liff !== 'undefined',
                    liffInitialized: window.liffInitialized || false,
                    hasIdToken: !!(window.idToken),
                    redirectCount: parseInt(
                        sessionStorage.getItem('liff_redirect_count') || 
                        localStorage.getItem('liff_redirect_count') || 
                        '0'
                    ),
                    errorLogs: JSON.parse(localStorage.getItem('liff_debug_logs') || '[]').slice(-10),
                    environment: {
                        userAgent: navigator.userAgent,
                        platform: navigator.platform,
                        cookieEnabled: navigator.cookieEnabled,
                        onLine: navigator.onLine,
                        language: navigator.language
                    }
                };
                
                // 診断APIを呼び出し
                const response = await fetch('api/liff_diagnostics.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        client: clientInfo,
                        errors: [message]
                    })
                });
                
                if (response.ok) {
                    const diagnostics = await response.json();
                    
                    if (diagnostics.recommendations && diagnostics.recommendations.length > 0) {
                        // 推奨事項を表示
                        const recommendationsHtml = `
                            <div style="margin-top: 20px; padding: 15px; background-color: #f0f0f0; border-radius: 8px; text-align: left;">
                                <h4 style="margin: 0 0 10px 0; color: #333;">解決方法：</h4>
                                <ul style="margin: 0; padding-left: 20px;">
                                    ${diagnostics.recommendations.map(rec => `<li style="margin: 5px 0;">${rec}</li>`).join('')}
                                </ul>
                                ${diagnostics.should_retry ? `
                                    <button onclick="window.location.reload()" 
                                            style="margin-top: 15px; background-color: #00B900; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer;">
                                        再試行する
                                    </button>
                                ` : ''}
                            </div>
                        `;
                        
                        errorMessage.innerHTML = message + recommendationsHtml;
                    }
                    
                    // 診断ログを出力（デバッグ用）
                    if (window.debugMode) {
                        console.log('LIFF診断結果:', diagnostics);
                    }
                }
            } catch (diagError) {
                console.error('診断エラー:', diagError);
                // 診断に失敗してもエラー表示は続行
            }
        }
    }

    /**
     * ローディング表示/非表示
     * @param {boolean} show - 表示するか
     * @param {string} message - メッセージ (オプション)
     */
    function toggleLoading(show, message = '読み込み中...') {
        if (show) {
            loadingElement.querySelector('p').textContent = message;
            loadingElement.style.display = 'flex';
        } else {
            loadingElement.style.display = 'none';
        }
    }

    /**
     * 部屋情報の初期化と表示
     */
    async function initializeRoomData() {
        try {
            toggleLoading(true, '部屋情報を取得中...');
            
            // room_link_required チェック
            if (loginSettings && loginSettings.room_link_required && !state.qrRoomParam) {
                console.warn('room_link_required が有効ですが、QRコードパラメータがありません');
                
                // room_register_alert モーダルを表示
                if (window.registerInformModal && typeof window.registerInformModal.show === 'function') {
                    window.registerInformModal.show('room_register_alert', {});
                } else {
                    showError('QRコードを読み取ってアクセスしてください。フロントまたは部屋に設置されたQRコードからご利用ください。');
                }
                return;
            }
            
            // 現在のルーム情報取得
            await fetchRoomData();
            
            // QRコードからの部屋番号が指定されていれば自動選択
            if (state.qrRoomParam && state.rooms.length > 0) {
                const roomIndex = state.rooms.findIndex(room => room.value === state.qrRoomParam);
                if (roomIndex >= 0) {
                    // 部屋番号が存在する場合、自動選択
                    autoSelectRoom(state.qrRoomParam);
                } else {
                    console.warn('指定された部屋番号が見つかりません:', state.qrRoomParam);
                }
            }
            
            // UI表示
            showContent();
        } catch (error) {
            console.error('部屋情報初期化エラー:', error);
            
            // 認証エラーの場合は再ログインを試みる
            if (error.message && (
                error.message.includes('認証') || 
                error.message.includes('ログイン') || 
                error.message.includes('auth')
            )) {
                if (!liff.isInClient()) {
                    showError('LINE認証に失敗しました。再ログインしてください。');
                    
                    // 3秒後に再ログイン
                    setTimeout(() => {
                        if (typeof liff !== 'undefined' && liff.isLoggedIn()) {
                            liff.logout();
                            setTimeout(() => liff.login(), 500);
                        } else {
                            window.location.reload();
                        }
                    }, 3000);
                } else {
                    showError('認証エラーが発生しました。ページをリロードしてください。');
                    // LINE内の場合はリロードだけ
                    setTimeout(() => window.location.reload(), 3000);
                }
            } else {
                // その他のエラー
                showError('部屋情報の取得に失敗しました。ネットワーク接続を確認してください。');
            }
        }
    }
}); 
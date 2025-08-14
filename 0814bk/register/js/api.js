/**
 * API通信モジュール
 */
class API {
    constructor() {
        this.baseUrl = ''; // 初期化時に設定されます
        this.debug = false; // デバッグモード
    }

    /**
     * APIのベースURLを設定
     * @param {string} url - APIのベースURL
     */
    setBaseUrl(url) {
        this.baseUrl = url;
    }

    /**
     * デバッグモードを設定
     * @param {boolean} enabled デバッグモードを有効にするかどうか
     */
    setDebug(enabled) {
        this.debug = enabled;
    }

    /**
     * API通信の共通処理
     * @param {string} endpoint - APIエンドポイント
     * @param {string} method - HTTPメソッド
     * @param {Object} data - リクエストデータ
     * @param {string} token - 認証トークン
     * @returns {Promise<Object>} APIレスポンス
     */
    async apiRequest(endpoint, method = 'GET', data = null, token = null) {
        try {
            const url = this.baseUrl + endpoint;
            
            if (this.debug) {
                console.log(`API Request: ${method} ${url}`);
                if (data) console.log('Request data:', data);
            }

            const options = {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };
            
            // 認証トークンがある場合はヘッダーに追加
            if (token) {
                options.headers['Authorization'] = `Bearer ${token}`;
            }
            
            // POSTリクエストの場合はボディにデータを追加
            if (data && (method === 'POST' || method === 'PUT')) {
                options.body = JSON.stringify(data);
            }
            
            // APIリクエスト実行
            const response = await fetch(url, options);
            
            if (this.debug) {
                console.log(`Response status: ${response.status}`);
                console.log(`Response headers:`, Object.fromEntries([...response.headers]));
            }

            // エラーレスポンスの処理
            if (!response.ok) {
                const errorText = await response.text();
                if (this.debug) {
                    console.error('API Error Response:', errorText);
                }
                try {
                    // 空のレスポンスチェック
                    if (!errorText || errorText.trim() === '') {
                        throw new Error(`APIエラー: ${response.status} - レスポンスが空です`);
                    }
                    
                    const errorJson = JSON.parse(errorText);
                    throw new Error(errorJson.error || errorJson.message || `APIエラー: ${response.status}`);
                } catch (e) {
                    if (e instanceof SyntaxError) {
                        // JSONパースエラーの場合はテキストをそのまま返す
                        console.error('JSONパースエラー:', e);
                        console.error('エラーレスポンステキスト:', errorText);
                        throw new Error(`APIエラー: ${response.status} - ${errorText || 'レスポンスが不正です'}`);
                    }
                    throw e;
                }
            }
            
            // 成功レスポンスの処理
            const responseText = await response.text();
            
            // 空のレスポンスチェック
            if (!responseText || responseText.trim() === '') {
                if (this.debug) {
                    console.warn('API returned empty response');
                }
                return {}; // 空のオブジェクトを返す
            }
            
            try {
                const responseData = JSON.parse(responseText);
                
                if (this.debug) {
                    console.log('API Response data:', responseData);
                }
                
                return responseData;
            } catch (e) {
                console.error('レスポンスJSONパースエラー:', e);
                console.error('レスポンステキスト:', responseText);
                throw new Error('APIレスポンスのJSONパースに失敗しました');
            }
        } catch (error) {
            if (this.debug) {
                console.error('API Request failed:', error);
            }
            throw error;
        }
    }

    /**
     * 部屋番号を登録する
     * @param {Object} registerData - 登録データ
     * @returns {Promise<Object>} 登録結果
     */
    async registerRoom(registerData) {
        try {
            const data = await this.apiRequest('/register/api/register.php', 'POST', {
                userId: lineUserId,
                userName: lineDisplayName,
                roomNumber: registerData.roomNumber,
                checkInDate: registerData.checkInDate,
                checkOutDate: registerData.checkOutDate
            });
            
            if (data && data.success) {
                return data;
            } else {
                throw new Error(data.message || '部屋番号の登録に失敗しました');
            }
        } catch (error) {
            console.error('部屋番号登録エラー:', error);
            throw error;
        }
    }

    /**
     * ユーザーIDに紐づく部屋情報を取得する
     * @returns {Promise<Object>} 部屋情報
     */
    async getRoomInfo() {
        try {
            const data = await this.apiRequest(`/register/api/room.php?userId=${lineUserId}`);
            
            if (data && data.success) {
                return data.roomInfo;
            } else {
                // 登録がない場合はnullを返す
                return null;
            }
        } catch (error) {
            console.error('部屋情報取得エラー:', error);
            return null;
        }
    }
}

// APIインスタンスの作成
const api = new API();

/**
 * 部屋番号登録API関数
 * @version 2.0.0
 */

// APIベースURL
const API_BASE_URL = 'https://test-mijeos.but.jp/fgsquare';

/**
 * 部屋一覧を取得する
 * @returns {Promise<Object>} 部屋一覧情報
 */
async function fetchRooms() {
    try {
        // LIFF初期化の完了を待つ
        if (window.waitForLiffInit) {
            console.log('fetchRooms: LIFF初期化の完了を待機中...');
            const initialized = await window.waitForLiffInit();
            if (!initialized) {
                console.error('fetchRooms: LIFF初期化に失敗しました');
                throw new Error('LINE認証の初期化に失敗しました。ページをリロードしてください。');
            }
            console.log('fetchRooms: LIFF初期化完了を確認');
        }
        
        // IDトークンの取得と再確認 (最大3回試行)
        let idToken = null;
        for (let attempt = 0; attempt < 3; attempt++) {
            idToken = window.idToken || liff.getIDToken();
            if (idToken) break;
            
            console.log(`fetchRooms: IDトークン取得再試行 (${attempt + 1}/3)`);
            // 少し待機
            await new Promise(resolve => setTimeout(resolve, 500));
        }
        
        if (!idToken) {
            console.error('fetchRooms: 認証情報が取得できませんでした');
            if (typeof debugLog === 'function') {
                debugLog('fetchRooms: IDトークンの取得に失敗');
            }
            
            // LINE認証のセッションクリア
            if (!liff.isInClient() && liff.isLoggedIn()) {
                console.log('fetchRooms: 認証セッションをクリアして再ログインを試みます');
                liff.logout();
                setTimeout(() => {
                    liff.login();
                }, 500);
            }
            
            throw new Error('認証情報が取得できませんでした。再ログインが必要です。');
        }

        console.log('fetchRooms: IDトークン取得成功 (長さ: ' + idToken.length + ')');
        
        // リクエスト準備
        const requestBody = {
            token: idToken,
            action: 'get_rooms',
            active_only: true
        };
        
        if (typeof debugLog === 'function') {
            debugLog('room.php APIリクエスト開始: ' + JSON.stringify(requestBody, (k, v) => k === 'token' ? v.substring(0, 15) + '...' : v));
        }

        // APIリクエスト
        const response = await fetch(`${API_BASE_URL}/register/api/room.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestBody)
        });

        console.log('room.php APIレスポンス:', response.status, response.statusText);
        
        // 認証エラーの特別処理
        if (response.status === 401 || response.status === 403) {
            console.error('fetchRooms: 認証エラー、再認証を試みます');
            
            // セッションをクリアして再ログイン
            if (!liff.isInClient()) {
                liff.logout();
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                window.location.reload();
            }
            
            throw new Error('認証エラーが発生しました。自動的に再認証を試みています...');
        }
        
        // エラーレスポンスの処理
        if (!response.ok) {
            const errorText = await response.text();
            console.error('room.php APIエラーレスポンス:', errorText);
            try {
                const errorData = JSON.parse(errorText);
                
                // 認証エラーの場合は特別処理
                if (errorData.message && (
                    errorData.message.includes('認証') || 
                    errorData.message.includes('auth') || 
                    errorData.message.includes('token')
                )) {
                    console.error('fetchRooms: 認証関連のエラーを検出、再ログインを試みます');
                    
                    // セッションをクリアして再ログイン
                    if (!liff.isInClient()) {
                        window.liffInitialized = false;
                        window.idToken = null;
                        liff.logout();
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else {
                        window.location.reload();
                    }
                }
                
                throw new Error(errorData.message || `部屋情報の取得に失敗しました (${response.status})`);
            } catch (e) {
                if (e instanceof SyntaxError) {
                    throw new Error(`部屋情報の取得に失敗しました (${response.status}): ${errorText}`);
                }
                throw e;
            }
        }
        
        // レスポンスのテキストを取得して内容を確認
        const responseText = await response.text();
        console.log('room.php APIレスポンス本文:', responseText.substring(0, 100) + '...');
        
        if (!responseText || responseText.trim() === '') {
            console.error('room.php API: 空のレスポンス');
            throw new Error('部屋情報の取得に失敗しました: サーバーから空のレスポンスが返されました');
        }
        
        // JSONとしてパース
        try {
            const data = JSON.parse(responseText);
            console.log('room.php APIレスポンスデータ:', data);
            return data;
        } catch (jsonError) {
            console.error('room.php API: JSONパースエラー', jsonError);
            console.error('パースに失敗したテキスト:', responseText);
            throw new Error('部屋情報の取得に失敗しました: サーバーレスポンスのパースに失敗');
        }
    } catch (error) {
        console.error('部屋一覧取得エラー:', error);
        throw error;
    }
}

/**
 * 部屋番号を登録する
 * @param {Object} params - 登録パラメータ
 * @param {string} params.token - LIFFのIDトークン
 * @param {string} params.roomNumber - 部屋番号
 * @param {string} params.userName - 利用者名
 * @param {string} params.checkInDate - チェックイン日
 * @param {string} params.checkOutDate - チェックアウト日
 * @param {string} params.checkOutTime - チェックアウト時間
 * @param {boolean} [params.force=false] - 強制登録フラグ（既存データの上書き）
 * @returns {Promise<Object>} 登録結果
 */
async function registerRoom(params, retryCount = 0) {
    try {
        console.log('registerRoom: リクエスト送信', {
            roomNumber: params.roomNumber,
            userName: params.userName,
            checkIn: params.checkInDate,
            checkOut: params.checkOutDate,
            force: params.force || false,
            retryCount: retryCount
        });

        // リクエストボディの作成
        const requestBody = {
            token: params.token,
            room_number: params.roomNumber,
            user_name: params.userName,
            check_in: params.checkInDate,
            check_out: params.checkOutDate,
            check_out_time: params.checkOutTime
        };
        
        // forceフラグが指定されている場合は追加
        if (params.force) {
            requestBody.force = true;
        }

        // APIリクエスト
        const response = await fetch(`${API_BASE_URL}/register/api/register.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestBody)
        });

        console.log('registerRoom: レスポンス受信', response.status, response.statusText);
        
        // エラーレスポンスの処理
        if (!response.ok) {
            const errorText = await response.text();
            console.error('register.php APIエラーレスポンス:', errorText);
            
            try {
                // 空のレスポンスチェック
                if (!errorText || errorText.trim() === '') {
                    throw new Error(`登録処理に失敗しました (${response.status})`);
                }
                
                const errorData = JSON.parse(errorText);
                const errorMessage = errorData.error || errorData.message || `登録処理に失敗しました (${response.status})`;
                
                // 「同じユーザーIDが既に登録されています」エラーの特別処理
                if (
                    (errorMessage.includes('同じユーザーID') || 
                    errorMessage.includes('already registered') || 
                    errorMessage.includes('already exists')) && 
                    retryCount < 1
                ) {
                    console.log('同じユーザーIDエラーを検出。forceフラグを付けて再試行します');
                    
                    // forceフラグを付けて再度登録を試みる
                    return await registerRoom({
                        ...params,
                        force: true  // 強制上書きフラグをセット
                    }, retryCount + 1);
                }
                
                throw new Error(errorMessage);
            } catch (e) {
                if (e instanceof SyntaxError) {
                    // JSONパースエラーの場合はテキストをそのまま返す
                    throw new Error(`登録処理に失敗しました (${response.status}): ${errorText}`);
                }
                throw e;
            }
        }
        
        // レスポンスのテキストを取得して内容を確認
        const responseText = await response.text();
        console.log('register.php APIレスポンス本文:', responseText.substring(0, 100) + '...');
        
        if (!responseText || responseText.trim() === '') {
            console.error('register.php API: 空のレスポンス');
            throw new Error('登録処理に失敗しました: サーバーから空のレスポンスが返されました');
        }
        
        // JSONとしてパース
        try {
            const data = JSON.parse(responseText);
            console.log('register.php APIレスポンスデータ:', data);
            return data;
        } catch (jsonError) {
            console.error('register.php API: JSONパースエラー', jsonError);
            console.error('パースに失敗したテキスト:', responseText);
            throw new Error('登録処理に失敗しました: サーバーレスポンスのパースに失敗');
        }
    } catch (error) {
        console.error('部屋番号登録エラー:', error);
        throw error;
    }
}

/**
 * 現在の部屋情報を取得する
 * @returns {Promise<Object>} 部屋情報
 */
async function getCurrentRoomInfo() {
    try {
        // IDトークンを取得
        const idToken = liff.getIDToken();
        if (!idToken) {
            throw new Error('認証情報が取得できませんでした');
        }

        // APIリクエスト
        const response = await fetch(`${API_BASE_URL}/register/api/room.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                token: idToken,
                action: 'get_current'
            })
        });

        // レスポンスをJSONとしてパース
        const data = await response.json();
        
        // レスポンスの検証
        if (!response.ok) {
            throw new Error(data.message || '部屋情報の取得に失敗しました');
        }
        
        return data.room_info || null;
    } catch (error) {
        console.error('現在の部屋情報取得エラー:', error);
        throw error;
    }
} 
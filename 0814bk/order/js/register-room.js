/**
 * 部屋登録画面のJavaScript
 * 部屋番号の登録とLINEユーザー情報の連携を行う
 */

// バージョン（キャッシュ対策）
const VERSION = new Date().getTime();

// グローバル変数
let lineUserId = '';
let initialized = false;
let loadingElement;

// DOMの準備完了後に実行
document.addEventListener('DOMContentLoaded', () => {
    // ローディング表示の初期化
    loadingElement = document.getElementById('loading');
    
    // フォーム要素の取得
    const registerForm = document.getElementById('room-register-form');
    
    if (registerForm) {
        // フォーム送信イベントの設定
        registerForm.addEventListener('submit', handleFormSubmit);
        
        // 入力フィールドのイベント設定
        const roomNumberInput = document.getElementById('room-number');
        if (roomNumberInput) {
            roomNumberInput.addEventListener('input', validateRoomNumber);
        }
    }
    
    // エラーメッセージ表示領域の初期化
    initializeErrorDisplay();
    
    // LIFF初期化
    initializeLiff();
});

/**
 * エラーメッセージ表示領域の初期化
 */
function initializeErrorDisplay() {
    const errorContainer = document.getElementById('error-container');
    if (errorContainer) {
        errorContainer.style.display = 'none';
    }
}

/**
 * エラーメッセージの表示
 * @param {string} message - 表示するエラーメッセージ
 */
function showError(message) {
    const errorContainer = document.getElementById('error-container');
    const errorMessage = document.getElementById('error-message');
    
    if (errorContainer && errorMessage) {
        errorMessage.textContent = message;
        errorContainer.style.display = 'block';
        
        // コンソールにもエラーを出力
        console.error(message);
    }
}

/**
 * ローディング表示の切り替え
 * @param {boolean} show - 表示するかどうか
 */
function toggleLoading(show) {
    if (loadingElement) {
        loadingElement.style.display = show ? 'flex' : 'none';
    }
}

/**
 * LIFF初期化
 */
function initializeLiff() {
    // ローディング表示
    toggleLoading(true);
    
    try {
        // LIFF初期化
        liff.init({
            liffId: LIFF_ID, // LIFF IDはHTML側で定義
            withLoginOnExternalBrowser: true
        })
        .then(() => {
            // ログインチェック
            if (!liff.isLoggedIn()) {
                console.log('LINEログインが必要です');
                liff.login();
                return;
            }
            
            // ユーザー情報取得
            return liff.getProfile();
        })
        .then((profile) => {
            if (!profile) return;
            
            // LINEユーザーIDを保存
            lineUserId = profile.userId;
            
            // フォームに値を設定
            const userIdInput = document.getElementById('line-user-id');
            if (userIdInput) {
                userIdInput.value = lineUserId;
            }
            
            // 初期化完了
            initialized = true;
            toggleLoading(false);
            
            console.log('LIFF初期化完了: ユーザーID = ' + lineUserId);
        })
        .catch((error) => {
            console.error('LIFF初期化エラー', error);
            showError('LINEとの連携中にエラーが発生しました。ページを再読み込みしてください。');
            toggleLoading(false);
        });
    } catch (error) {
        console.error('LIFF初期化例外', error);
        showError('LIFFの初期化中にエラーが発生しました。');
        toggleLoading(false);
    }
}

/**
 * 部屋番号のバリデーション
 */
function validateRoomNumber() {
    const roomNumberInput = document.getElementById('room-number');
    const errorElement = document.getElementById('room-number-error');
    
    if (!roomNumberInput || !errorElement) return;
    
    const value = roomNumberInput.value.trim();
    
    if (!value) {
        errorElement.textContent = '部屋番号を入力してください';
        return false;
    }
    
    // 追加のバリデーションルールがあれば実装（例：桁数、フォーマットなど）
    
    errorElement.textContent = '';
    return true;
}

/**
 * フォーム送信ハンドラ
 * @param {Event} event - イベントオブジェクト
 */
function handleFormSubmit(event) {
    event.preventDefault();
    
    // LIFFが初期化されているか確認
    if (!initialized || !lineUserId) {
        showError('LINEとの連携が完了していません。ページを再読み込みしてください。');
        return;
    }
    
    // 入力値の検証
    if (!validateRoomNumber()) {
        return;
    }
    
    // フォームデータの取得
    const roomNumber = document.getElementById('room-number').value.trim();
    const userName = document.getElementById('user-name').value.trim();
    
    // 送信データの作成
    const formData = new FormData();
    formData.append('line_user_id', lineUserId);
    formData.append('room_number', roomNumber);
    formData.append('user_name', userName);
    
    // ローディング表示
    toggleLoading(true);
    
    // API呼び出し
    fetch(`api/v1/register-room.php?v=${VERSION}`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        toggleLoading(false);
        
        if (data.success) {
            // 登録成功
            alert('部屋情報の登録が完了しました。モバイルオーダーをご利用いただけます。');
            
            // メニュー画面に遷移
            window.location.href = 'index.php';
        } else {
            // エラーメッセージ表示
            showError(data.error || '部屋情報の登録に失敗しました。しばらく経ってからお試しください。');
        }
    })
    .catch(error => {
        console.error('API呼び出しエラー', error);
        showError('サーバーとの通信中にエラーが発生しました。ネットワーク接続をご確認ください。');
        toggleLoading(false);
    });
} 
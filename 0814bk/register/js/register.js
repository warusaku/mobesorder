document.addEventListener('DOMContentLoaded', async () => {
    // APIインスタンスの設定
    const api = new API();
    api.setDebug(true); // デバッグモードを有効化
    
    // LIFFの初期化完了を待機
    window.addEventListener('liffInitComplete', async function(e) {
        try {
            console.log('LIFF初期化完了イベント受信:', e.detail);
            
            // フォームの取得
            const form = document.getElementById('registration-form');
            if (!form) {
                console.error('登録フォームが見つかりません');
                return;
            }
            
            // URLからルームパラメータを取得
            const urlParams = new URLSearchParams(window.location.search);
            const roomParam = urlParams.get('room');
            
            if (roomParam) {
                console.log('URLから部屋番号を取得:', roomParam);
                const roomInput = document.getElementById('room-number');
                if (roomInput) {
                    roomInput.value = roomParam;
                }
            }
            
            // 送信ボタンのイベントリスナー
            form.addEventListener('submit', async function(event) {
                event.preventDefault();
                
                try {
                    // フォームデータの取得
                    const roomNumber = document.getElementById('room-number').value;
                    const userName = document.getElementById('user-name').value;
                    const checkIn = document.getElementById('check-in').value;
                    const checkOut = document.getElementById('check-out').value;
                    
                    // バリデーション
                    if (!roomNumber || !userName || !checkIn || !checkOut) {
                        throw new Error('すべての項目を入力してください');
                    }
                    
                    // 送信データの作成
                    const data = {
                        token: window.idToken || 'dummy-token-for-testing', // IDトークンを使用
                        room_number: roomNumber,
                        user_name: userName,
                        check_in: checkIn,
                        check_out: checkOut
                    };
                    
                    console.log('送信データ:', data);
                    
                    // 登録APIの呼び出し
                    const result = await api.registerRoom(data);
                    
                    console.log('登録成功:', result);
                    
                    // 成功メッセージの表示
                    showMessage('登録が完了しました', 'success');
                    
                    // 3秒後にリダイレクト
                    setTimeout(() => {
                        window.location.href = result.redirect_url || '../index.html';
                    }, 3000);
                    
                } catch (error) {
                    console.error('登録エラー:', error);
                    showMessage(error.message || 'エラーが発生しました。もう一度お試しください。', 'error');
                }
            });
            
        } catch (error) {
            console.error('初期化エラー:', error);
            showMessage('初期化中にエラーが発生しました: ' + error.message, 'error');
        }
    });
});

/**
 * メッセージを表示する
 * @param {string} message メッセージ
 * @param {string} type メッセージの種類 (success, error)
 */
function showMessage(message, type = 'info') {
    const messageContainer = document.getElementById('message-container');
    if (!messageContainer) {
        console.error('メッセージコンテナが見つかりません');
        alert(message);
        return;
    }
    
    const messageElement = document.createElement('div');
    messageElement.className = `message ${type}`;
    messageElement.textContent = message;
    
    // 既存のメッセージをクリア
    messageContainer.innerHTML = '';
    messageContainer.appendChild(messageElement);
    messageContainer.style.display = 'block';
    
    // 成功メッセージは5秒後に消える
    if (type === 'success') {
        setTimeout(() => {
            messageContainer.style.display = 'none';
        }, 5000);
    }
} 
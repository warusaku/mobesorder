// 部屋データと変更追跡
let roomsData = [];
let changedRooms = [];
let currentQrImageUrl = null;
let authToken = '';
let usageData = []; // 利用状況データを格納

// 初期化処理
document.addEventListener('DOMContentLoaded', function() {
    console.log('部屋設定画面の初期化を開始します');
    
    // authTokenを取得（data属性を使用）
    authToken = document.body.getAttribute('data-auth-token') || '';
    
    loadRoomData();
    loadUsageData();
    
    // 部屋追加ボタン
    document.getElementById('add-room-btn').addEventListener('click', function() {
        document.getElementById('add-room-modal').style.display = 'block';
    });
    
    // モーダルを閉じるボタン
    document.querySelectorAll('.close-modal').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('add-room-modal').style.display = 'none';
        });
    });
    
    // メッセージモーダルを閉じるボタン
    document.querySelectorAll('.close-message-modal').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('message-modal').style.display = 'none';
        });
    });
    
    // 部屋追加保存ボタン
    document.getElementById('save-room-btn').addEventListener('click', saveNewRoom);
    
    // 一括更新ボタン
    document.getElementById('save-all-btn').addEventListener('click', saveAllChanges);
    
    // 利用状況更新ボタン
    document.getElementById('refresh-usage-btn').addEventListener('click', loadUsageData);
    
    // QRコードタイプ選択変更イベント
    document.getElementById('qr-type-select').addEventListener('change', function() {
        const roomSelectContainer = document.getElementById('room-select-container');
        if (this.value === 'room') {
            roomSelectContainer.style.display = 'block';
        } else {
            roomSelectContainer.style.display = 'none';
        }
    });
    
    // QRコード生成ボタン
    document.getElementById('generate-qrcode').addEventListener('click', function() {
        const qrType = document.getElementById('qr-type-select').value;
        const baseUrl = document.getElementById('qr-base-url').value;
        
        if (!baseUrl) {
            showMessage('エラー', 'ベースURLを入力してください');
            return;
        }
        
        // ボタンを無効化
        this.classList.add('disabled');
        this.setAttribute('disabled', 'disabled');
        // ボタンのテキストを変更
        this.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> 生成処理中...';
        
        if (qrType === 'common') {
            // 共通QRコード生成
            generateQRCode(baseUrl, '共通登録QR', qrType, '');
        } else {
            // 部屋別QRコード生成
            const roomNumber = document.getElementById('qr-room-select').value;
            if (!roomNumber) {
                showMessage('エラー', '部屋を選択してください');
                // ボタンを再有効化
                this.classList.remove('disabled');
                this.removeAttribute('disabled');
                return;
            }
            
            // LIFF対応のためのURLを構築
            // URLに直接roomパラメータを付加してQRコードを生成
            const roomParam = encodeURIComponent(roomNumber);
            // 既存URLの末尾に?または&でパラメータを追加
            const hasParams = baseUrl.includes('?');
            const separator = hasParams ? '&' : '?';
            const fullUrl = `${baseUrl}${separator}room=${roomParam}`;
            
            generateQRCode(fullUrl, `部屋番号: ${roomNumber}`, qrType, roomNumber);
        }
    });
    
    // QRコード印刷ボタン
    document.getElementById('print-qrcode').addEventListener('click', function() {
        const qrContainer = document.getElementById('qrcode-container');
        const qrImage = qrContainer.querySelector('img');
        
        if (!currentQrImageUrl || qrImage.src.includes('no-image.png')) {
            showMessage('エラー', '印刷するQRコードを先に生成してください');
            return;
        }
        
        // 印刷用ウィンドウを開く
        const printWindow = window.open('', '_blank');
        printWindow.document.write('<html><head><title>QRコード印刷</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('body { text-align: center; font-family: Arial, sans-serif; padding: 20px; }');
        printWindow.document.write('.qr-container { margin: 0 auto; max-width: 500px; }');
        printWindow.document.write('img { max-width: 100%; height: auto; }');
        printWindow.document.write('</style></head><body>');
        printWindow.document.write('<div class="qr-container">');
        printWindow.document.write('<img src="' + qrImage.src + '" alt="QRコード">');
        printWindow.document.write('</div>');
        printWindow.document.write('<script>');
        printWindow.document.write('window.onload = function() { setTimeout(function() { window.print(); window.close(); }, 500); };');
        printWindow.document.write('</script>');
        printWindow.document.write('</body></html>');
        printWindow.document.close();
    });
    
    // QRコード共有ボタン
    document.getElementById('share-qrcode').addEventListener('click', function() {
        const qrContainer = document.getElementById('qrcode-container');
        const qrImage = qrContainer.querySelector('img');
        
        if (!currentQrImageUrl || qrImage.src.includes('no-image.png')) {
            showMessage('エラー', '共有するQRコードを先に生成してください');
            return;
        }
        
        // ファイル名提案用に日付を取得
        const now = new Date();
        const dateStr = now.getFullYear() + 
                        ('0' + (now.getMonth() + 1)).slice(-2) + 
                        ('0' + now.getDate()).slice(-2);
        const filename = 'qrcode_' + dateStr + '.png';
        
        // 画像ダウンロード処理
        const link = document.createElement('a');
        link.href = qrImage.src;
        link.download = filename;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showMessage('成功', 'QRコード画像をダウンロードしました');
    });
});

// 部屋データ読み込み
function loadRoomData() {
    console.log('部屋データを読み込みます');
    fetch('../api/admin/roomdata.php?token=' + authToken)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('部屋データ取得成功:', data.rooms.length + '件');
                roomsData = data.rooms;
                renderRoomsTable();
                populateRoomSelect(); // 部屋選択を更新
            } else {
                console.error('部屋データ取得エラー:', data.message);
                showMessage('エラー', '部屋データの読み込みに失敗しました: ' + data.message);
            }
        })
        .catch(error => {
            console.error('部屋データ取得エラー:', error);
            showMessage('エラー', '部屋データの読み込み中にエラーが発生しました');
        });
}

// 部屋選択リストを更新
function populateRoomSelect() {
    const select = document.getElementById('qr-room-select');
    // 最初のオプションだけ残す
    select.innerHTML = '<option value="">部屋を選択してください</option>';
    
    // 有効な部屋のみをフィルタリングしてソート
    const activeRooms = roomsData
        .filter(room => room.is_active == 1)
        .sort((a, b) => a.room_number.localeCompare(b.room_number));
    
    activeRooms.forEach(room => {
        const option = document.createElement('option');
        option.value = room.room_number;
        option.textContent = `${room.room_number} - ${room.description || '説明なし'}`;
        select.appendChild(option);
    });
}

// QRコード生成
function generateQRCode(content, label, qrType, roomNumber) {
    const container = document.getElementById('qrcode-container');
    const urlDisplay = document.getElementById('qr-url-display');
    const loadingOverlay = container.querySelector('.qr-loading-overlay');
    const generateButton = document.getElementById('generate-qrcode');
    
    // 読み込み表示
    loadingOverlay.style.display = 'flex';
    
    // URLを表示
    urlDisplay.textContent = content;
    
    // フォームデータ作成
    const formData = new FormData();
    formData.append('token', authToken);
    formData.append('content', content);
    formData.append('label', label);
    formData.append('qr_type', qrType);
    formData.append('room_number', roomNumber);
    
    // QR生成APIを呼び出し
    fetch('QRgenerate.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        loadingOverlay.style.display = 'none';
        
        // ボタンを再有効化
        generateButton.classList.remove('disabled');
        generateButton.removeAttribute('disabled');
        // ボタンのテキストを元に戻す
        generateButton.innerHTML = '<i class="bi bi-qr-code"></i> 登録用QRコードを生成';
        
        if (data.success) {
            console.log('QRコード生成成功:', data.image_url);
            
            // 画像を表示
            const qrImage = container.querySelector('img');
            qrImage.src = data.image_url;
            qrImage.alt = label;
            
            // 現在のQR画像URLを保存
            currentQrImageUrl = data.image_url;
        } else {
            console.error('QRコード生成エラー:', data.message);
            showMessage('エラー', 'QRコードの生成に失敗しました: ' + data.message);
        }
    })
    .catch(error => {
        loadingOverlay.style.display = 'none';
        
        // ボタンを再有効化
        generateButton.classList.remove('disabled');
        generateButton.removeAttribute('disabled');
        // ボタンのテキストを元に戻す
        generateButton.innerHTML = '<i class="bi bi-qr-code"></i> 登録用QRコードを生成';
        
        console.error('QRコード生成エラー:', error);
        showMessage('エラー', 'QRコードの生成中にエラーが発生しました');
    });
}

// 利用状況データ読み込み
function loadUsageData() {
    console.log('利用状況データを読み込みます');
    fetch('../api/admin/roomdata.php?token=' + authToken + '&usage=1')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('利用状況データ取得成功');
                usageData = data.usage || []; // グローバル変数に保存
                renderUsageTable(usageData);
                // 部屋テーブルも更新して利用状況を反映
                renderRoomsTable();
            } else {
                console.error('利用状況データ取得エラー:', data.message);
                showMessage('エラー', '利用状況データの読み込みに失敗しました: ' + data.message);
            }
        })
        .catch(error => {
            console.error('利用状況データ取得エラー:', error);
            showMessage('エラー', '利用状況データの読み込み中にエラーが発生しました');
        });
}

// 部屋テーブル描画
function renderRoomsTable() {
    const tbody = document.getElementById('rooms-table-body');
    tbody.innerHTML = '';
    
    if (roomsData.length === 0) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="7" class="text-center">登録されている部屋はありません</td>';
        tbody.appendChild(tr);
        return;
    }
    
    roomsData.forEach(room => {
        const tr = document.createElement('tr');
        // 無効な部屋の場合はinactive-rowクラスを追加
        if (room.is_active != 1) {
            tr.classList.add('inactive-row');
        }
        
        // 利用中かどうかを確認
        const isInUse = usageData.some(usage => 
            usage.room_number === room.room_number && usage.is_active == 1
        );
        
        // 利用中の場合、in-use-rowクラスを追加
        if (isInUse) {
            tr.classList.add('in-use-row');
        }
        
        tr.innerHTML = `
            <td>${room.id}</td>
            <td>
                <input type="text" class="form-control room-number" value="${room.room_number}" data-id="${room.id}" maxlength="5">
            </td>
            <td>
                <input type="text" class="form-control room-description" value="${room.description || ''}" data-id="${room.id}">
            </td>
            <td class="text-center">
                ${isInUse ? '<span class="usage-status">利用中</span>' : '-'}
            </td>
            <td class="text-center">
                <label class="switch">
                    <input type="checkbox" class="room-active" ${room.is_active == 1 ? 'checked' : ''} data-id="${room.id}">
                    <span class="slider"></span>
                </label>
            </td>
            <td>${formatDateTime(room.last_update)}</td>
            <td class="text-center">
                <button type="button" class="trash-icon-btn delete-room-btn" data-id="${room.id}">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    
    // 部屋番号編集イベント
    document.querySelectorAll('.room-number').forEach(input => {
        input.addEventListener('change', function() {
            const roomId = this.getAttribute('data-id');
            markAsChanged(roomId, 'room_number', this.value);
        });
    });
    
    // 説明編集イベント
    document.querySelectorAll('.room-description').forEach(input => {
        input.addEventListener('change', function() {
            const roomId = this.getAttribute('data-id');
            markAsChanged(roomId, 'description', this.value);
        });
    });
    
    // 有効/無効チェックボックスイベント
    document.querySelectorAll('.room-active').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const roomId = this.getAttribute('data-id');
            markAsChanged(roomId, 'is_active', this.checked ? 1 : 0);
            
            // チェックボックスの状態に応じて親のtr要素のクラスを追加/削除
            const tr = this.closest('tr');
            if (this.checked) {
                tr.classList.remove('inactive-row');
            } else {
                tr.classList.add('inactive-row');
            }
        });
    });
    
    // 削除ボタン
    document.querySelectorAll('.delete-room-btn').forEach(button => {
        button.addEventListener('click', function() {
            const roomId = this.getAttribute('data-id');
            deleteRoom(roomId);
        });
    });
}

// 利用状況テーブル描画
function renderUsageTable(usageData) {
    const tbody = document.getElementById('usage-table-body');
    tbody.innerHTML = '';
    
    // 部屋番号があり、かつis_activeが1（利用中）の項目だけをフィルタリング
    const activeRooms = usageData.filter(usage => usage.room_number && usage.is_active == 1);
    
    if (activeRooms.length === 0) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="5" class="text-center">現在利用中の部屋はありません</td>';
        tbody.appendChild(tr);
        return;
    }
    
    activeRooms.forEach(usage => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${usage.room_number}</td>
            <td>${usage.user_name || '-'}</td>
            <td>${formatDate(usage.check_in_date) || '-'}</td>
            <td>${formatDate(usage.check_out_date) || '-'}</td>
            <td>
                <span class="status-badge success">利用中</span>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// 変更をマーク
function markAsChanged(roomId, field, value) {
    const index = changedRooms.findIndex(item => item.id == roomId);
    
    if (index === -1) {
        changedRooms.push({
            id: roomId,
            [field]: value
        });
    } else {
        changedRooms[index][field] = value;
    }
    
    console.log('変更をマーク:', roomId, field, value);
}

// 新規部屋の保存
function saveNewRoom() {
    const roomNumber = document.getElementById('room-number').value;
    const description = document.getElementById('room-description').value;
    const isActive = document.getElementById('room-active').checked ? 1 : 0;
    
    if (!roomNumber) {
        showMessage('エラー', '部屋番号を入力してください');
        return;
    }
    
    if (roomNumber.length > 5) {
        showMessage('エラー', '部屋番号は5文字以内で入力してください');
        return;
    }
    
    console.log('部屋を追加します:', roomNumber, description, isActive);
    
    const formData = new FormData();
    formData.append('token', authToken);
    formData.append('action', 'add');
    formData.append('room_number', roomNumber);
    formData.append('description', description);
    formData.append('is_active', isActive);
    
    fetch('../api/admin/saveroom.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('部屋追加成功');
            showMessage('成功', '部屋を追加しました');
            document.getElementById('add-room-modal').style.display = 'none';
            document.getElementById('add-room-form').reset();
            loadRoomData();
        } else {
            console.error('部屋追加エラー:', data.message);
            showMessage('エラー', '部屋の追加に失敗しました: ' + data.message);
        }
    })
    .catch(error => {
        console.error('部屋追加エラー:', error);
        showMessage('エラー', '部屋の追加中にエラーが発生しました');
    });
}

// 全ての変更を保存
function saveAllChanges() {
    if (changedRooms.length === 0) {
        showMessage('情報', '変更はありません');
        return;
    }
    
    console.log('部屋設定を更新します:', changedRooms);
    
    const formData = new FormData();
    formData.append('token', authToken);
    formData.append('action', 'update');
    formData.append('room_data', JSON.stringify(changedRooms));
    
    fetch('../api/admin/saveroom.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('部屋設定更新成功');
            showMessage('成功', '部屋設定を更新しました');
            changedRooms = [];
            loadRoomData();
        } else {
            console.error('部屋設定更新エラー:', data.message);
            showMessage('エラー', '部屋設定の更新に失敗しました: ' + data.message);
        }
    })
    .catch(error => {
        console.error('部屋設定更新エラー:', error);
        showMessage('エラー', '部屋設定の更新中にエラーが発生しました');
    });
}

// 部屋の削除
function deleteRoom(roomId) {
    if (!confirm('この部屋を削除してもよろしいですか？\n※利用中の部屋は削除できません。')) {
        return;
    }
    
    console.log('部屋を削除します:', roomId);
    
    const formData = new FormData();
    formData.append('token', authToken);
    formData.append('action', 'delete');
    formData.append('room_id', roomId);
    
    fetch('../api/admin/saveroom.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('部屋削除成功');
            showMessage('成功', '部屋を削除しました');
            loadRoomData();
            loadUsageData(); // 利用状況も更新
        } else {
            console.error('部屋削除エラー:', data.message);
            showMessage('エラー', '部屋の削除に失敗しました: ' + data.message);
        }
    })
    .catch(error => {
        console.error('部屋削除エラー:', error);
        showMessage('エラー', '部屋の削除中にエラーが発生しました');
    });
}

// メッセージの表示
function showMessage(title, message) {
    const modal = document.getElementById('message-modal');
    document.getElementById('message-title').textContent = title;
    document.getElementById('message-text').textContent = message;
    modal.style.display = 'block';
}

// 日時フォーマット (yyyy-mm-dd hh:mm:ss)
function formatDateTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    
    // 無効な日付の場合
    if (isNaN(date.getTime())) return dateString;
    
    return date.toLocaleString('ja-JP', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}

// 日付のみのフォーマット (yyyy-mm-dd)
function formatDate(dateString) {
    if (!dateString) return '-';
    
    // 時間部分がある場合は切り捨て
    if (dateString.includes(' ')) {
        dateString = dateString.split(' ')[0];
    }
    
    // YYYY-MM-DD形式をYYYY年MM月DD日に変換
    const parts = dateString.split('-');
    if (parts.length === 3) {
        return `${parts[0]}年${parts[1]}月${parts[2]}日`;
    }
    
    return dateString;
} 
<?php
/**
 * 商品ラベル編集機能
 * 
 * このスクリプトは、商品に付与するラベルの管理・編集機能を提供します。
 * 
 * @version 1.0.0
 * @author FG Development Team
 */

// 設定ファイルを読み込み
$rootPath = realpath(__DIR__ . '/..');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Database.php';
require_once $rootPath . '/api/lib/Utils.php';

// ログ関数
function labelEditorLogMessage($message, $level = 'INFO') {
    // ログファイルのパス設定
    $logFile = realpath(__DIR__ . '/../logs') . '/product_labelediter.log';
    
    // タイムスタンプ付きのログメッセージを作成
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    // ログファイルのローテーションチェック
    labelEditorCheckLogRotation($logFile);
    
    // ログを書き込み
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * ログファイルのローテーションをチェックし、必要に応じて実行する
 * 
 * @param string $logFile ログファイルのパス
 * @return void
 */
function labelEditorCheckLogRotation($logFile) {
    // ファイルが存在しない場合は何もしない
    if (!file_exists($logFile)) {
        return;
    }
    
    // ファイルサイズを取得（バイト）
    $fileSize = filesize($logFile);
    
    // 300KB（307200バイト）を超えていたらローテーション
    $maxSize = 307200;
    if ($fileSize > $maxSize) {
        // ファイルの内容を読み込み
        $content = file_get_contents($logFile);
        
        // 80%のサイズを計算（つまり20%残す）
        $removeSize = intval($fileSize * 0.8);
        
        // 新しい内容（古いログの80%を削除）
        $newContent = substr($content, $removeSize);
        
        // ファイルを上書き
        file_put_contents($logFile, $newContent);
        
        // ローテーション情報をログに追加
        $timestamp = date('Y-m-d H:i:s');
        $rotationInfo = "[{$timestamp}] [INFO] ログファイルのローテーションを実行しました。{$removeSize}バイト削除" . PHP_EOL;
        file_put_contents($logFile, $rotationInfo, FILE_APPEND);
    }
}

// 初期化時のログ
labelEditorLogMessage('ラベル編集機能が読み込まれました');

/**
 * ラベル管理モジュールのHTMLコードを取得
 * 
 * @return string モーダルウィンドウとフォームを含むHTML
 */
function getLabelEditorHTML() {
    // ラベル管理機能のHTMLコード
    $html = <<<HTML
    <!-- ラベル管理モーダル -->
    <div id="label-modal" class="copy-modal">
        <div class="label-modal-content">
            <span class="copy-modal-close">&times;</span>
            <h4>ラベル管理</h4>
            
            <div class="label-list mt-4 mb-4">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ラベル名</th>
                            <th>カラー</th>
                            <th>最終更新</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="label-list-body">
                        <!-- ラベルリストが動的に挿入されます -->
                    </tbody>
                </table>
            </div>
            
            <hr>
            
            <h5>新規ラベル追加</h5>
            <div class="new-label-form">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="new-label-text">ラベル名 (8文字以内)</label>
                        <input type="text" class="form-control" id="new-label-text" maxlength="8" placeholder="例: HOT!!">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="new-label-color">カラー</label>
                        <input type="color" class="form-control" id="new-label-color" value="#a9a9a9">
                    </div>
                    <div class="form-group col-md-2 align-self-end">
                        <button id="add-label-btn" class="btn btn-success">追加</button>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <button id="close-label-modal" class="btn btn-primary">閉じる</button>
            </div>
        </div>
    </div>
    
    <!-- ラベル編集モーダル -->
    <div id="edit-label-modal" class="copy-modal">
        <div class="edit-label-modal-content">
            <span class="copy-modal-close">&times;</span>
            <h4>ラベル編集</h4>
            
            <div class="edit-label-form mt-4">
                <input type="hidden" id="edit-label-id">
                <div class="form-group">
                    <label for="edit-label-text">ラベル名 (8文字以内)</label>
                    <input type="text" class="form-control" id="edit-label-text" maxlength="8">
                </div>
                <div class="form-group mt-3">
                    <label for="edit-label-color">カラー</label>
                    <input type="color" class="form-control" id="edit-label-color">
                </div>
                
                <div class="mt-4">
                    <button id="update-label-btn" class="btn btn-primary">更新</button>
                    <button id="delete-label-btn" class="btn btn-danger">削除</button>
                    <button id="cancel-edit-label" class="btn btn-secondary">キャンセル</button>
                </div>
            </div>
        </div>
    </div>
HTML;

    return $html;
}

/**
 * ラベル管理機能のJavaScriptコードを取得
 * 
 * @return string ラベル管理機能を実装するJavaScript
 */
function getLabelEditorJS() {
    // ラベル管理機能のJavaScriptコード
    $js = <<<JAVASCRIPT
// ラベル管理関連の変数
const manageLabelBtn = $('#manageLabelBtn');
const labelModal = $('#label-modal');
const closeLabelModalBtn = $('#close-label-modal');
const labelListBody = $('#label-list-body');
const addLabelBtn = $('#add-label-btn');
const newLabelText = $('#new-label-text');
const newLabelColor = $('#new-label-color');

// ラベル編集モーダル関連の変数
const editLabelModal = $('#edit-label-modal');
const editLabelId = $('#edit-label-id');
const editLabelText = $('#edit-label-text');
const editLabelColor = $('#edit-label-color');
const updateLabelBtn = $('#update-label-btn');
const deleteLabelBtn = $('#delete-label-btn');
const cancelEditLabelBtn = $('#cancel-edit-label');

// ラベル管理モーダルを開く
manageLabelBtn.on('click', function() {
    loadLabels();
    labelModal.addClass('active');
});

// ラベル管理モーダルを閉じる
closeLabelModalBtn.on('click', function() {
    labelModal.removeClass('active');
});

// ラベル追加ボタン
addLabelBtn.on('click', function() {
    const labelText = newLabelText.val().trim();
    const labelColor = newLabelColor.val().replace('#', '');
    
    if (!labelText) {
        showError('ラベル名を入力してください');
        return;
    }
    
    addNewLabel(labelText, labelColor);
});

// ラベル編集モーダルを閉じる
cancelEditLabelBtn.on('click', function() {
    editLabelModal.removeClass('active');
    labelModal.addClass('active');
});

// ラベル更新ボタン
updateLabelBtn.on('click', function() {
    const labelId = editLabelId.val();
    const labelText = editLabelText.val().trim();
    const labelColor = editLabelColor.val().replace('#', '');
    
    if (!labelText) {
        showError('ラベル名を入力してください');
        return;
    }
    
    updateLabel(labelId, labelText, labelColor);
});

// ラベル削除ボタン
deleteLabelBtn.on('click', function() {
    const labelId = editLabelId.val();
    if (confirm('このラベルを削除してもよろしいですか？\\n関連付けられた商品からもラベルが削除されます。')) {
        deleteLabel(labelId);
    }
});

// 編集モーダルの×ボタン特別処理
$('#edit-label-modal .copy-modal-close').on('click', function() {
    editLabelModal.removeClass('active');
    labelModal.addClass('active');
});

// 編集モーダルの外側クリック時にも閉じる
editLabelModal.on('click', function(e) {
    if (e.target === this) {
        editLabelModal.removeClass('active');
        labelModal.addClass('active');
    }
});

// ラベル一覧を読み込む
function loadLabels() {
    $.ajax({
        url: './api/label_management.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderLabelList(response.labels);
            } else {
                showError('ラベル一覧の取得に失敗しました: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            let errorMsg = 'サーバーとの通信に失敗しました: ' + error;
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg += ' - ' + xhr.responseJSON.message;
            }
            showError(errorMsg);
            
            console.error('ラベル一覧取得エラー:', {
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText
            });
        }
    });
}

// ラベル一覧を表示
function renderLabelList(labels) {
    labelListBody.empty();
    
    if (labels.length === 0) {
        labelListBody.html('<tr><td colspan="5" class="text-center">ラベルはありません</td></tr>');
        return;
    }
    
    labels.forEach(function(label) {
        const labelRow = `
            <tr>
                <td>\${label.label_id}</td>
                <td>
                    <span class="label-preview" style="background-color: #\${label.label_color}; color: white; padding: 2px 8px; border-radius: 4px;">
                        \${label.label_text}
                    </span>
                </td>
                <td>#\${label.label_color}</td>
                <td>\${label.last_update}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary edit-label-btn" data-id="\${label.label_id}" data-text="\${label.label_text}" data-color="\${label.label_color}">
                        編集
                    </button>
                </td>
            </tr>
        `;
        
        labelListBody.append(labelRow);
    });
    
    // 編集ボタンのイベント
    $('.edit-label-btn').on('click', function() {
        const id = $(this).data('id');
        const text = $(this).data('text');
        const color = $(this).data('color');
        
        editLabelId.val(id);
        editLabelText.val(text);
        editLabelColor.val('#' + color);
        
        // activeクラスの切り替えでモーダル表示を制御
        labelModal.removeClass('active');
        editLabelModal.addClass('active');
    });
}

// 新しいラベルを追加
function addNewLabel(text, color) {
    // リクエストデータを作成
    const requestData = {
        label_text: text,
        label_color: color
    };
    
    // ラベル追加前のローディング表示
    addLabelBtn.prop('disabled', true);
    addLabelBtn.html('<i class="spinner-border spinner-border-sm"></i> 処理中...');
    
    // ローカルパスを使用して、絶対URLではなく相対URLを指定
    $.ajax({
        url: './api/label_management.php?action=add_label',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(requestData),
        success: function(response) {
            if (response.success) {
                showSuccess('ラベルを追加しました');
                newLabelText.val('');
                loadLabels();
                // 商品一覧も再読み込み
                if (currentCategory) {
                    loadProductsForCategory(currentCategory);
                }
            } else {
                showError('ラベルの追加に失敗しました: ' + response.message);
            }
            
            // ボタンを元に戻す
            addLabelBtn.prop('disabled', false);
            addLabelBtn.html('追加');
        },
        error: function(xhr, status, error) {
            // より詳細なエラー情報を表示
            let errorMsg = 'サーバーとの通信に失敗しました: ' + error;
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg += ' - ' + xhr.responseJSON.message;
            }
            showError(errorMsg);
            
            console.error('ラベル追加エラー:', {
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText
            });
            
            // ボタンを元に戻す
            addLabelBtn.prop('disabled', false);
            addLabelBtn.html('追加');
        }
    });
}

// ラベルを更新
function updateLabel(id, text, color) {
    // ボタンを無効化して処理中表示
    updateLabelBtn.prop('disabled', true);
    updateLabelBtn.html('<i class="spinner-border spinner-border-sm"></i> 処理中...');
    
    $.ajax({
        url: './api/label_management.php',
        method: 'PUT',
        contentType: 'application/json',
        data: JSON.stringify({
            label_id: id,
            label_text: text,
            label_color: color
        }),
        success: function(response) {
            if (response.success) {
                showSuccess('ラベルを更新しました');
                editLabelModal.removeClass('active');
                loadLabels();
                labelModal.addClass('active');
                
                // 商品一覧も再読み込み
                if (currentCategory) {
                    loadProductsForCategory(currentCategory);
                }
            } else {
                showError('ラベルの更新に失敗しました: ' + response.message);
            }
            
            // ボタンを元に戻す
            updateLabelBtn.prop('disabled', false);
            updateLabelBtn.html('更新');
        },
        error: function(xhr, status, error) {
            let errorMsg = 'サーバーとの通信に失敗しました: ' + error;
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg += ' - ' + xhr.responseJSON.message;
            }
            showError(errorMsg);
            
            console.error('ラベル更新エラー:', {
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText
            });
            
            // ボタンを元に戻す
            updateLabelBtn.prop('disabled', false);
            updateLabelBtn.html('更新');
        }
    });
}

// ラベルを削除
function deleteLabel(id) {
    // 削除ボタンを無効化して処理中表示
    deleteLabelBtn.prop('disabled', true);
    deleteLabelBtn.html('<i class="spinner-border spinner-border-sm"></i> 処理中...');
    
    $.ajax({
        url: './api/label_management.php?id=' + id,
        method: 'DELETE',
        success: function(response) {
            if (response.success) {
                showSuccess('ラベルを削除しました');
                editLabelModal.removeClass('active');
                loadLabels();
                labelModal.addClass('active');
                
                // 商品一覧も再読み込み
                if (currentCategory) {
                    loadProductsForCategory(currentCategory);
                }
            } else {
                showError('ラベルの削除に失敗しました: ' + response.message);
            }
            
            // ボタンを元に戻す
            deleteLabelBtn.prop('disabled', false);
            deleteLabelBtn.html('削除');
        },
        error: function(xhr, status, error) {
            let errorMsg = 'サーバーとの通信に失敗しました: ' + error;
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg += ' - ' + xhr.responseJSON.message;
            }
            showError(errorMsg);
            
            console.error('ラベル削除エラー:', {
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText
            });
            
            // ボタンを元に戻す
            deleteLabelBtn.prop('disabled', false);
            deleteLabelBtn.html('削除');
        }
    });
}
JAVASCRIPT;

    return $js;
}

/**
 * ラベル管理のスタイルシートを取得
 * 
 * @return string ラベル管理機能用のCSS
 */
function getLabelEditorCSS() {
    $css = <<<CSS
/* ラベル管理関連のスタイル */
.label-preview {
    display: inline-block;
    min-width: 80px;
    text-align: center;
}

.label-select {
    width: 100%;
    max-width: 150px;
}

/* ラベル選択のスタイル */
.label-select option {
    padding: 5px;
}

/* ラベル管理モーダル */
.label-modal-content {
    background-color: white;
    padding: 20px;
    border-radius: 5px;
    width: 90%;
    max-width: 800px;
    position: relative;
    z-index: 1001;
}

/* ラベル編集モーダル */
.edit-label-modal-content {
    background-color: white;
    padding: 20px;
    border-radius: 5px;
    width: 90%;
    max-width: 500px;
    position: relative;
    z-index: 1001;
}
CSS;

    return $css;
} 
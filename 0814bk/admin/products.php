<!-- 管理操作ボタン -->
<div class="admin-actions">
    <a href="products_sync.php" class="btn btn-primary">
        <i class="bi bi-cloud-arrow-down"></i> Square商品同期
    </a>
    <a href="categories.php" class="btn btn-secondary">
        <i class="bi bi-folder"></i> カテゴリ管理
    </a>
    <button id="updateImagesBtn" class="btn btn-info">
        <i class="bi bi-images"></i> 画像URL更新
    </button>
</div>

<!-- 画像更新中のモーダル -->
<div class="modal fade" id="imageUpdateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">画像URL更新中</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="progress mb-3">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                </div>
                <p>Square画像IDから実際のURLへの変換処理を実行中です。この処理には時間がかかる場合があります。</p>
                <div id="updateResults" class="alert alert-info d-none">
                    処理結果がここに表示されます。
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript - 最後に配置 -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 画像URL更新ボタンの処理
    const updateImagesBtn = document.getElementById('updateImagesBtn');
    const imageUpdateModal = new bootstrap.Modal(document.getElementById('imageUpdateModal'));
    const updateResults = document.getElementById('updateResults');
    
    if (updateImagesBtn) {
        updateImagesBtn.addEventListener('click', function() {
            // モーダルを表示
            imageUpdateModal.show();
            updateResults.classList.add('d-none');
            
            // 画像URL更新APIを呼び出し
            fetch('../api/admin/update_image_urls.php')
                .then(response => response.json())
                .then(data => {
                    // 結果表示
                    let resultMessage = '';
                    if (data.success) {
                        resultMessage = `<strong>成功:</strong> ${data.message}<br>`;
                        resultMessage += `処理件数: ${data.stats.processed}件<br>`;
                        resultMessage += `更新件数: ${data.stats.updated}件<br>`;
                        resultMessage += `エラー件数: ${data.stats.errors}件`;
                        updateResults.classList.remove('alert-info', 'alert-danger');
                        updateResults.classList.add('alert-success');
                    } else {
                        resultMessage = `<strong>エラー:</strong> ${data.message}`;
                        updateResults.classList.remove('alert-info', 'alert-success');
                        updateResults.classList.add('alert-danger');
                    }
                    
                    updateResults.innerHTML = resultMessage;
                    updateResults.classList.remove('d-none');
                })
                .catch(error => {
                    // エラー表示
                    updateResults.innerHTML = `<strong>通信エラー:</strong> ${error.message}`;
                    updateResults.classList.remove('alert-info', 'alert-success');
                    updateResults.classList.add('alert-danger');
                    updateResults.classList.remove('d-none');
                });
        });
    }
});
</script>

<div class="row mb-3">
    <div class="col-md-12">
        <div class="d-flex justify-content-between">
            <h2>商品管理</h2>
            <div>
                <button id="syncProductsBtn" class="btn btn-primary">
                    <i class="fas fa-sync-alt"></i> 商品同期
                </button>
                <button id="updateImageUrlsBtn" class="btn btn-info ml-2">
                    <i class="fas fa-images"></i> 画像URL更新
                </button>
                <a href="product_edit.php" class="btn btn-success ml-2">
                    <i class="fas fa-plus"></i> 新規商品
                </a>
            </div>
        </div>
    </div>
</div>

<!-- 画像URL更新確認モーダル -->
<div class="modal fade" id="updateImageUrlsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">画像URL更新</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>商品の画像URLを一括で更新します。この処理にはしばらく時間がかかる場合があります。</p>
                <p>実行しますか？</p>
                <div id="updateImageUrlsProgress" class="d-none">
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                    </div>
                    <p class="text-center mt-2">処理中です。しばらくお待ちください...</p>
                </div>
                <div id="updateImageUrlsResult" class="d-none">
                    <div class="alert alert-info">
                        <p id="updateImageUrlsResultMessage"></p>
                        <ul>
                            <li>処理件数: <span id="processedCount">0</span>件</li>
                            <li>更新件数: <span id="updatedCount">0</span>件</li>
                            <li>エラー件数: <span id="errorCount">0</span>件</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">キャンセル</button>
                <button type="button" id="executeUpdateImageUrlsBtn" class="btn btn-primary">実行する</button>
            </div>
        </div>
    </div>
</div>

<script>
// 画像URL更新ボタンのイベント処理
$(document).ready(function() {
    // 画像URL更新ボタンクリック時
    $('#updateImageUrlsBtn').click(function() {
        $('#updateImageUrlsProgress').addClass('d-none');
        $('#updateImageUrlsResult').addClass('d-none');
        $('#executeUpdateImageUrlsBtn').show();
        $('#updateImageUrlsModal').modal('show');
    });
    
    // 実行ボタンクリック時
    $('#executeUpdateImageUrlsBtn').click(function() {
        // ボタンを無効化
        $(this).prop('disabled', true);
        
        // プログレスバーを表示
        $('#updateImageUrlsProgress').removeClass('d-none');
        
        // API呼び出し
        $.ajax({
            url: '../api/admin/update_image_urls.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                // 結果表示
                $('#updateImageUrlsProgress').addClass('d-none');
                $('#updateImageUrlsResult').removeClass('d-none');
                
                if (response.success) {
                    $('#updateImageUrlsResultMessage').text(response.message);
                    $('#processedCount').text(response.stats.processed);
                    $('#updatedCount').text(response.stats.updated);
                    $('#errorCount').text(response.stats.errors);
                } else {
                    $('#updateImageUrlsResultMessage').text('エラーが発生しました: ' + response.message);
                }
                
                // 実行ボタンを非表示
                $('#executeUpdateImageUrlsBtn').hide();
            },
            error: function(xhr, status, error) {
                // エラー表示
                $('#updateImageUrlsProgress').addClass('d-none');
                $('#updateImageUrlsResult').removeClass('d-none');
                $('#updateImageUrlsResultMessage').text('通信エラーが発生しました。');
                $('#executeUpdateImageUrlsBtn').prop('disabled', false);
            }
        });
    });
});
</script> 
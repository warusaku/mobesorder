<?php
/**
 * RTSP_Reader Test Framework - Logs Tab (Lolipop)
 * 
 * ログタブのコンテンツ
 */

// フィルタリング設定
$logLevel = isset($_GET['level']) ? (int)$_GET['level'] : null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

// 表示するログ行数の選択肢
$limitOptions = [
    10 => '10行',
    50 => '50行',
    100 => '100行',
    500 => '500行',
    1000 => '1000行',
    0 => 'すべて'
];

// ログレベルの選択肢
$levelOptions = [
    null => 'すべて',
    TestLogger::DEBUG => 'DEBUG以上',
    TestLogger::INFO => 'INFO以上',
    TestLogger::WARNING => 'WARNING以上',
    TestLogger::ERROR => 'ERROR以上',
    TestLogger::CRITICAL => 'CRITICAL'
];

// 現在のURLベース（フィルタリング用）
$baseUrl = '?tab=logs';

// ログメッセージを取得
$logs = $testRunner->getLogger()->getMessages($limit, $logLevel);
$totalLogs = count($testRunner->getLogger()->getMessages(0, $logLevel));

?>

<div class="card mb-4">
    <div class="card-header bg-secondary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-clipboard-list me-2"></i>ログ表示
            </div>
            <div>
                <button class="btn btn-sm btn-danger" id="clearLogsBtn">
                    <i class="fas fa-trash-alt me-1"></i>ログをクリア
                </button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <!-- フィルターコントロール -->
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text">表示行数</span>
                    <select class="form-select" id="limitSelect">
                        <?php foreach ($limitOptions as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo $limit === $val ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text">ログレベル</span>
                    <select class="form-select" id="levelSelect">
                        <?php foreach ($levelOptions as $val => $label): ?>
                        <option value="<?php echo $val === null ? '' : $val; ?>" <?php echo $logLevel === $val ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- ログ情報 -->
        <div class="alert alert-info">
            <?php if ($limit > 0 && $totalLogs > $limit): ?>
            <strong><?php echo $totalLogs; ?></strong> 件中 <strong><?php echo count($logs); ?></strong> 件表示
            <?php else: ?>
            <strong><?php echo count($logs); ?></strong> 件のログを表示
            <?php endif; ?>
        </div>
        
        <!-- ログ表示エリア -->
        <div class="log-container p-3 bg-light border rounded">
            <?php echo $testRunner->getLogger()->getHtmlLog($limit, $logLevel); ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 表示行数変更時の処理
    document.getElementById('limitSelect').addEventListener('change', function() {
        updateLogFilters();
    });
    
    // ログレベル変更時の処理
    document.getElementById('levelSelect').addEventListener('change', function() {
        updateLogFilters();
    });
    
    // フィルター更新関数
    function updateLogFilters() {
        const limit = document.getElementById('limitSelect').value;
        const level = document.getElementById('levelSelect').value;
        let url = '<?php echo $baseUrl; ?>';
        
        if (limit) {
            url += '&limit=' + limit;
        }
        
        if (level) {
            url += '&level=' + level;
        }
        
        window.location.href = url;
    }
    
    // ログクリアボタンの処理
    document.getElementById('clearLogsBtn').addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm('ログをクリアしますか？')) {
            fetch('api/clear_logs.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert('ログがクリアされました');
                        location.reload();
                    } else {
                        alert('エラー: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('エラーが発生しました: ' + error);
                });
        }
    });
});
</script>

<style>
.log-container {
    max-height: 600px;
    overflow-y: auto;
}
</style> 
 
 
 
 
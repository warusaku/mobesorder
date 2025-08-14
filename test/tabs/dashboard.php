<?php
/**
 * RTSP_Reader Test Framework - Dashboard Tab (Lolipop)
 * 
 * ダッシュボードタブのコンテンツ
 */

// システム情報の取得
$systemInfo = [
    'php_version' => PHP_VERSION,
    'os' => PHP_OS,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '不明',
    'server_name' => $_SERVER['SERVER_NAME'] ?? '不明',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '不明',
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time') . '秒',
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size')
];

// データベース概要情報の取得
$dbSummary = null;
if ($dbConnection) {
    $dbAnalyzer = new DatabaseAnalyzer($dbConnection, $logger);
    try {
        $dbSummary = $dbAnalyzer->getDatabaseSummary('LAA1207717-rtspreader');
    } catch (Exception $e) {
        $logger->error("データベース情報取得エラー", ['error' => $e->getMessage()]);
    }
}

// 利用可能なテストモジュールを取得
$availableTests = $testRunner->getAvailableTests();
$testCounts = [
    'modules' => count($availableTests),
    'tests' => 0
];

foreach ($availableTests as $moduleName => $tests) {
    $testCounts['tests'] += count($tests);
}

// 最近のログメッセージを取得
$recentLogs = $testRunner->getLogger()->getMessages(5);

?>

<div class="row">
    <!-- システム概要 -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-info-circle me-2"></i>システム情報
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tbody>
                        <?php foreach ($systemInfo as $key => $value): ?>
                        <tr>
                            <th><?php echo ucfirst(str_replace('_', ' ', $key)); ?></th>
                            <td><?php echo htmlspecialchars($value); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- テスト概要 -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <i class="fas fa-vial me-2"></i>テスト概要
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <div class="text-center mb-3">
                            <h2 class="mb-0"><?php echo $testCounts['modules']; ?></h2>
                            <p class="text-muted">テストモジュール</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center mb-3">
                            <h2 class="mb-0"><?php echo $testCounts['tests']; ?></h2>
                            <p class="text-muted">テスト数</p>
                        </div>
                    </div>
                </div>
                
                <h5>利用可能なモジュール</h5>
                <ul class="list-group">
                    <?php foreach ($availableTests as $moduleName => $tests): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?php echo htmlspecialchars($moduleName); ?>
                        <span class="badge bg-primary rounded-pill"><?php echo count($tests); ?> テスト</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                
                <?php if (empty($availableTests)): ?>
                <div class="alert alert-warning mt-3">
                    利用可能なテストモジュールがありません。テストモジュールを /modules ディレクトリに追加してください。
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- データベース情報 -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <i class="fas fa-database me-2"></i>データベース情報
            </div>
            <div class="card-body">
                <?php if ($dbConnection === null): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> データベース接続が確立されていないため、このページの機能は使用できません。
                </div>
                <?php elseif ($dbSummary !== null && $dbSummary['status'] === 'success'): ?>
                
                <ul class="nav nav-tabs" id="dbTestTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="db-overview-tab" data-bs-toggle="tab" data-bs-target="#db-overview" type="button" role="tab" aria-controls="db-overview" aria-selected="true">概要</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="db-tables-tab" data-bs-toggle="tab" data-bs-target="#db-tables" type="button" role="tab" aria-controls="db-tables" aria-selected="false">テーブル</button>
                    </li>
                </ul>
                
                <div class="tab-content mt-3" id="dbTestTabsContent">
                    <div class="tab-pane fade show active" id="db-overview" role="tabpanel" aria-labelledby="db-overview-tab">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body text-center">
                                        <h5>テーブル数</h5>
                                        <h2><?php echo $dbSummary['tables_count']; ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body text-center">
                                        <h5>総レコード数</h5>
                                        <h2><?php echo number_format($dbSummary['total_rows']); ?></h2>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-body text-center">
                                <h5>データベースサイズ</h5>
                                <h2><?php echo $dbSummary['total_size']; ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="db-tables" role="tabpanel" aria-labelledby="db-tables-tab">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>テーブル名</th>
                                    <th>行数</th>
                                    <th>サイズ</th>
                                    <th>エンジン</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dbSummary['tables_info'] as $table): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($table['name']); ?></td>
                                    <td><?php echo number_format($table['rows']); ?></td>
                                    <td><?php echo htmlspecialchars($table['total_size']); ?></td>
                                    <td><?php echo htmlspecialchars($table['engine']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle"></i> データベース情報の取得中にエラーが発生しました。
                    <?php if (isset($dbSummary['message'])): ?>
                    <p><?php echo htmlspecialchars($dbSummary['message']); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- 最近のログ -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">
                <i class="fas fa-clipboard-list me-2"></i>最近のログ
            </div>
            <div class="card-body">
                <?php echo $testRunner->getLogger()->getHtmlLog(5); ?>
                <div class="text-end mt-2">
                    <a href="?tab=logs" class="btn btn-sm btn-outline-secondary">すべてのログを表示</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- クイックアクション -->
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <i class="fas fa-bolt me-2"></i>クイックアクション
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <a href="?tab=api_tests" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-server me-2"></i>APIテストを実行
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="?tab=ui_tests" class="btn btn-info w-100 mb-2">
                            <i class="fas fa-window-restore me-2"></i>UIテストを実行
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="?tab=e2e_tests" class="btn btn-success w-100 mb-2">
                            <i class="fas fa-exchange-alt me-2"></i>E2Eテストを実行
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="#" class="btn btn-danger w-100 mb-2" id="clearLogsBtn">
                            <i class="fas fa-trash-alt me-2"></i>ログをクリア
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
 
 
 
 
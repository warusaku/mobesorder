<?php
/**
 * RTSP_Reader Test Framework - E2E Tests Tab (Lolipop)
 * 
 * E2Eテストタブのコンテンツ
 */

// 利用可能なE2Eテストを取得
$e2eTests = [];
foreach ($testRunner->getAvailableTests() as $moduleName => $tests) {
    if (strpos($moduleName, 'e2e_') === 0) {
        $e2eTests[$moduleName] = $tests;
    }
}
?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-exchange-alt me-2"></i>E2Eテスト
    </div>
    <div class="card-body">
        <?php if (empty($e2eTests)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-circle me-2"></i>E2Eテストモジュールが利用できません
        </div>
        
        <div class="mt-3">
            <p>E2Eテストモジュールが見つかりません。E2Eテストは以下のワークフローをテストします：</p>
            <ul>
                <li>OCR処理ワークフロー（カメラ接続 → 画像取得 → OCR処理 → 結果送信 → 判定 → 通知）</li>
                <li>設定同期ワークフロー（Lolipop側設定変更 → 同期 → ローカルサーバーでの反映）</li>
                <li>カメラ検出・追加ワークフロー（RTSPスキャン → カメラ検出 → 設定 → 登録）</li>
                <li>Webhook通知（アラート発生 → 通知処理 → 外部サービス連携）</li>
            </ul>
            
            <p>テストモジュールを実装するには、<code>/modules/e2e_tests.php</code>ファイルを作成してください。</p>
        </div>
        <?php else: ?>
        <div class="row mb-4">
            <div class="col">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>E2Eテストは実際のサーバー間通信とWebhook通知を実行します。注意して実行してください。
                </div>
            </div>
        </div>
        
        <?php foreach ($e2eTests as $moduleName => $tests): ?>
        <div class="mb-4">
            <h4><?php echo str_replace('_', ' ', ucfirst($moduleName)); ?></h4>
            
            <div class="mb-3">
                <button class="btn btn-primary run-module-btn" data-module="<?php echo $moduleName; ?>" id="run-all-<?php echo $moduleName; ?>">
                    <i class="fas fa-play"></i> すべてのテストを実行
                </button>
            </div>
            
            <div id="module-result-<?php echo $moduleName; ?>" class="mb-4 d-none">
                <!-- テスト結果がここに表示されます -->
            </div>
            
            <div class="accordion" id="<?php echo $moduleName; ?>Accordion">
                <?php foreach ($tests as $testName => $testInfo): ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-<?php echo $moduleName . '-' . $testName; ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $moduleName . '-' . $testName; ?>" aria-expanded="false" aria-controls="collapse-<?php echo $moduleName . '-' . $testName; ?>">
                            <span class="me-2"><?php echo $testName; ?></span>
                            <?php if (!empty($testInfo['description'])): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($testInfo['description']); ?></small>
                            <?php endif; ?>
                        </button>
                    </h2>
                    <div id="collapse-<?php echo $moduleName . '-' . $testName; ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo $moduleName . '-' . $testName; ?>" data-bs-parent="#<?php echo $moduleName; ?>Accordion">
                        <div class="accordion-body">
                            <div class="mb-3">
                                <button class="btn btn-sm btn-primary run-test-btn" data-module="<?php echo $moduleName; ?>" data-test="<?php echo $testName; ?>">
                                    <i class="fas fa-play"></i> 実行
                                </button>
                            </div>
                            
                            <div id="test-result-<?php echo $moduleName; ?>-<?php echo $testName; ?>" class="d-none">
                                <!-- 個別テスト結果がここに表示されます -->
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-secondary text-white">
        <i class="fas fa-info-circle me-2"></i>E2Eテストについて
    </div>
    <div class="card-body">
        <p>エンドツーエンド（E2E）テストは、RTSP_Readerシステム全体の統合フローをテストします。主なテスト項目：</p>
        
        <ul>
            <li><strong>OCR処理ワークフロー</strong> - カメラからの映像取得、OCR処理、結果送信、判定、通知までの一連の流れ</li>
            <li><strong>データベース同期</strong> - ローカルサーバーとLolipopサーバー間のデータ同期機能</li>
            <li><strong>Webhook通知</strong> - Discord、Slack等への外部サービス通知機能</li>
            <li><strong>エラー処理</strong> - 各処理でのエラー発生時の挙動確認</li>
        </ul>
        
        <div class="alert alert-info">
            <i class="fas fa-lightbulb me-2"></i>E2Eテストモジュールは<code>/modules/e2e_tests.php</code>に新しく作成してください。
        </div>
    </div>
</div>

<!-- API実行エンドポイント: api/run_test.php -->
<!-- モジュール全体実行エンドポイント: api/run_module_tests.php --> 
 
 
 
 
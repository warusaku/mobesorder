<?php
require_once 'test/views/header.php';
require_once 'api/lib/SquareService.php';
require_once 'api/lib/Utils.php';
?>

<div class="card">
    <h2>Square連携確認</h2>
    <p>Square APIとの連携状態を確認し、各種同期テストを実行できます。</p>

    <div class="card mb-4">
        <h3>現在のSquare API設定</h3>
        <table>
            <tr>
                <th>設定項目</th>
                <th>設定状態</th>
            </tr>
            <tr>
                <td>API環境</td>
                <td><?php echo defined('SQUARE_ENVIRONMENT') ? SQUARE_ENVIRONMENT : 'undefined'; ?></td>
            </tr>
            <tr>
                <td>アクセストークン</td>
                <td>
                    <?php 
                    if (defined('SQUARE_ACCESS_TOKEN') && !empty(SQUARE_ACCESS_TOKEN)) {
                        echo '<span style="color: green;">✓ 設定済み</span>';
                    } else {
                        echo '<span style="color: red;">✗ 未設定</span>';
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <td>ロケーションID</td>
                <td>
                    <?php 
                    if (defined('SQUARE_LOCATION_ID') && !empty(SQUARE_LOCATION_ID)) {
                        echo '<span style="color: green;">✓ 設定済み</span> (' . SQUARE_LOCATION_ID . ')';
                    } else {
                        echo '<span style="color: red;">✗ 未設定</span>';
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <td>Webhook署名キー</td>
                <td>
                    <?php 
                    if (defined('SQUARE_WEBHOOK_SIGNATURE_KEY') && !empty(SQUARE_WEBHOOK_SIGNATURE_KEY)) {
                        echo '<span style="color: green;">✓ 設定済み</span>';
                    } else {
                        echo '<span style="color: red;">✗ 未設定</span>';
                    }
                    ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="card mb-4">
        <h3>Square連携テスト</h3>
        <form action="/fgsquare/test_dashboard.php?action=square" method="post">
            <div class="form-group">
                <label>実行するテスト</label>
                <div>
                    <input type="checkbox" name="tests[]" value="api_connection" checked>
                    <span style="margin-left: 5px;">API接続テスト</span>
                </div>
                <div>
                    <input type="checkbox" name="tests[]" value="catalog_sync">
                    <span style="margin-left: 5px;">商品カタログ同期</span>
                </div>
                <div>
                    <input type="checkbox" name="tests[]" value="inventory_sync">
                    <span style="margin-left: 5px;">在庫同期</span>
                </div>
                <div>
                    <input type="checkbox" name="tests[]" value="payment_test">
                    <span style="margin-left: 5px;">決済テスト</span>
                </div>
                <div>
                    <input type="checkbox" name="tests[]" value="webhook_test">
                    <span style="margin-left: 5px;">Webhook検証</span>
                </div>
            </div>
            <div class="form-group">
                <label>デバッグレベル</label>
                <select name="debug_level">
                    <option value="normal">通常</option>
                    <option value="verbose">詳細</option>
                    <option value="full">完全（開発者向け）</option>
                </select>
            </div>
            <button type="submit" name="run_tests" class="button">テスト実行</button>
        </form>
    </div>

    <?php
    // 商品データをデータベースに同期
    if (isset($_POST['sync_to_db']) && $_POST['sync_to_db'] == '1') {
        try {
            require_once 'api/lib/ProductService.php';
            require_once 'api/lib/Utils.php';
            
            Utils::log("商品カタログ同期開始 - ダッシュボードから実行", 'INFO', 'SquareDebug');
            
            $productService = new ProductService();
            $startTime = microtime(true);
            
            // 詳細ログを有効化（チェックボックスに応じて）
            $enableDebug = isset($_POST['sync_debug']) && $_POST['sync_debug'] == '1';
            if ($enableDebug) {
                define('SYNC_DEBUG', true);
                Utils::log("詳細デバッグモードが有効化されました", 'DEBUG', 'SquareDebug');
            }
            
            $result = $productService->processProductSync();
            $executionTime = round(microtime(true) - $startTime, 2);
            
            Utils::log("商品カタログ同期完了 - 実行時間: {$executionTime}秒, 結果: " . json_encode($result), 'INFO', 'SquareDebug');
            
            echo '<div class="card mb-4">';
            if ($result['success']) {
                echo '<div class="test-result success">';
                echo '<h3>同期成功</h3>';
                echo '<p>' . htmlspecialchars($result['message']) . '</p>';
                echo '<p>追加: ' . $result['stats']['added'] . '件、更新: ' . $result['stats']['updated'] . '件、エラー: ' . $result['stats']['errors'] . '件</p>';
                echo '<p>実行時間: ' . $executionTime . '秒</p>';
                
                // ログファイルへのリンクを追加
                echo '<p>詳細ログは <code>' . htmlspecialchars(realpath('logs/app.log')) . '</code> に保存されています</p>';
                
                echo '</div>';
            } else {
                echo '<div class="test-result failure">';
                echo '<h3>同期失敗</h3>';
                echo '<p>' . htmlspecialchars($result['message']) . '</p>';
                echo '<p>詳細なエラーログは <code>' . htmlspecialchars(realpath('logs/app.log')) . '</code> を確認してください。</p>';
                echo '</div>';
            }
            echo '</div>';
        } catch (Exception $e) {
            Utils::log("商品カタログ同期エラー: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'ERROR', 'SquareDebug');
            
            echo '<div class="card mb-4">';
            echo '<div class="test-result failure">';
            echo '<h3>同期エラー</h3>';
            echo '<p>同期処理中に例外が発生しました: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p>スタックトレース:</p>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            echo '<p>詳細なエラーログは <code>' . htmlspecialchars(realpath('logs/app.log')) . '</code> を確認してください。</p>';
            echo '</div>';
            echo '</div>';
        }
    }
    
    // テスト実行処理
    if (isset($_POST['run_tests']) && isset($_POST['tests']) && is_array($_POST['tests'])) {
        $tests = $_POST['tests'];
        $debugLevel = isset($_POST['debug_level']) ? $_POST['debug_level'] : 'normal';
        $results = [];
        $squareService = null;
        
        try {
            // SquareServiceのインスタンス生成
            $squareService = new SquareService();
            
            echo '<div class="card mb-4"><h3>テスト結果</h3>';
            
            // 各テストの実行
            foreach ($tests as $test) {
                switch ($test) {
                    case 'api_connection':
                        // API接続テスト
                        echo '<div class="test-section"><h4>API接続テスト</h4>';
                        try {
                            $startTime = microtime(true);
                            $response = $squareService->testConnection();
                            $endTime = microtime(true);
                            $executionTime = round(($endTime - $startTime) * 1000, 2);
                            
                            echo '<div class="test-result success">';
                            echo '<p>✓ APIに正常に接続できました</p>';
                            echo '<p>応答時間: ' . $executionTime . 'ms</p>';
                            
                            if ($debugLevel == 'verbose' || $debugLevel == 'full') {
                                echo '<pre>' . htmlspecialchars(print_r($response, true)) . '</pre>';
                            }
                            echo '</div>';
                        } catch (Exception $e) {
                            echo '<div class="test-result failure">';
                            echo '<p>✗ API接続エラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
                            echo '</div>';
                        }
                        echo '</div>';
                        break;
                        
                    case 'catalog_sync':
                        // 商品カタログ同期テスト
                        echo '<div class="test-section"><h4>商品カタログ同期テスト</h4>';
                        try {
                            $items = $squareService->getItems();
                            
                            echo '<div class="test-result success">';
                            echo '<p>✓ 商品カタログを取得できました</p>';
                            echo '<p>取得商品数: ' . count($items) . '件</p>';
                            
                            // データベース同期ボタンを追加
                            echo '<form action="/fgsquare/test_dashboard.php?action=square" method="post" style="margin-top: 10px;">';
                            echo '<input type="hidden" name="sync_to_db" value="1">';
                            echo '<div class="form-group">';
                            echo '<label for="sync_debug">詳細なデバッグログを出力</label>';
                            echo '<input type="checkbox" id="sync_debug" name="sync_debug" value="1" checked>';
                            echo '</div>';
                            echo '<button type="submit" class="button" style="background-color: #4CAF50; color: white;">データベースに同期</button>';
                            echo '</form>';
                            
                            // デバッグ: $itemsの内容を確認
                            echo '<div style="background-color: #f5f5f5; padding: 10px; margin: 10px 0; overflow: auto; max-height: 300px;">';
                            echo '<p><strong>デバッグ情報 - 最初のアイテム構造:</strong></p>';
                            if (!empty($items)) {
                                echo '<pre>';
                                var_dump($items[0]);
                                echo '</pre>';
                            } else {
                                echo '<p>アイテムデータが空です</p>';
                            }
                            echo '</div>';
                            
                            if ($debugLevel == 'verbose' || $debugLevel == 'full') {
                                echo '<table class="data-table">';
                                echo '<tr><th>商品ID</th><th>商品名</th><th>価格</th></tr>';
                                foreach ($items as $item) {
                                    echo '<tr>';
                                    echo '<td>' . htmlspecialchars($item['id'] ?? 'N/A') . '</td>';
                                    echo '<td>' . htmlspecialchars($item['name'] ?? 'N/A') . '</td>';
                                    echo '<td>' . htmlspecialchars($item['price'] ?? 'N/A') . '</td>';
                                    echo '</tr>';
                                }
                                echo '</table>';
                            }
                            echo '</div>';
                        } catch (Exception $e) {
                            echo '<div class="test-result failure">';
                            echo '<p>✗ 商品カタログ取得エラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
                            echo '</div>';
                        }
                        echo '</div>';
                        break;
                        
                    case 'inventory_sync':
                        // 在庫同期テスト
                        echo '<div class="test-section"><h4>在庫同期テスト</h4>';
                        try {
                            // まず商品を取得してから在庫をテスト
                            $items = $squareService->getItems();
                            if (empty($items)) {
                                throw new Exception("商品が取得できないため、在庫同期テストを実行できません");
                            }
                            
                            // 最初の数件の商品でテスト
                            $testItems = array_slice($items, 0, 5);
                            $itemIds = [];
                            foreach ($testItems as $item) {
                                $itemIds[] = $item['id'];
                            }
                            
                            // 在庫取得テスト
                            $inventory = $squareService->getInventory($itemIds);
                            
                            echo '<div class="test-result success">';
                            echo '<p>✓ 在庫情報を取得できました</p>';
                            echo '<p>取得商品数: ' . count($inventory) . '件</p>';
                            
                            if ($debugLevel == 'verbose' || $debugLevel == 'full') {
                                echo '<table class="data-table">';
                                echo '<tr><th>商品ID</th><th>在庫数</th><th>最終更新</th></tr>';
                                foreach ($inventory as $itemId => $info) {
                                    echo '<tr>';
                                    echo '<td>' . htmlspecialchars($itemId) . '</td>';
                                    echo '<td>' . htmlspecialchars($info['quantity'] ?? 'N/A') . '</td>';
                                    echo '<td>' . htmlspecialchars($info['updated_at'] ?? 'N/A') . '</td>';
                                    echo '</tr>';
                                }
                                echo '</table>';
                            }
                            echo '</div>';
                        } catch (Exception $e) {
                            echo '<div class="test-result failure">';
                            echo '<p>✗ 在庫同期エラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
                            echo '</div>';
                        }
                        echo '</div>';
                        break;
                        
                    case 'payment_test':
                        // 決済テスト
                        echo '<div class="test-section"><h4>決済処理</h4>';
                        echo '<p>本番環境での決済処理を行います。クレジットカード情報を入力して決済をテストできます。</p>';
                        
                        // 決済フォームの表示
                        echo '<div id="payment-form">';
                        echo '<form id="square-payment-form">';
                        echo '<input type="hidden" id="order-id" value="order_' . time() . '_' . rand(1000, 9999) . '">';
                        echo '<input type="hidden" id="amount" value="100">';
                        
                        echo '<div class="form-group">';
                        echo '<p>テスト金額: 100円</p>';
                        echo '<div id="card-container"></div>';
                        echo '<div id="payment-status-container"></div>';
                        echo '</div>';
                        
                        echo '<button type="button" id="card-button" class="button">決済処理実行</button>';
                        echo '</form>';
                        echo '</div>';
                        
                        // Square Web Payment SDKの読み込みとJavaScriptコード
                        echo '<script type="text/javascript" src="https://web.squarecdn.com/v1/square.js"></script>';
                        echo '<script type="text/javascript">
                        document.addEventListener("DOMContentLoaded", async function() {
                            const appId = "' . (defined("SQUARE_APP_ID") ? SQUARE_APP_ID : "sandbox-sq0idb-yourappidhere") . '";
                            const locationId = "' . SQUARE_LOCATION_ID . '";
                            
                            let payments;
                            try {
                                payments = window.Square.payments(appId, locationId);
                                const card = await payments.card();
                                await card.attach("#card-container");
                                
                                const cardButton = document.getElementById("card-button");
                                cardButton.addEventListener("click", async function(event) {
                                    event.preventDefault();
                                    
                                    try {
                                        const statusContainer = document.getElementById("payment-status-container");
                                        statusContainer.innerHTML = "決済処理中...";
                                        cardButton.disabled = true;
                                        
                                        const result = await card.tokenize();
                                        if (result.status === "OK") {
                                            const sourceId = result.token;
                                            const orderId = document.getElementById("order-id").value;
                                            const amount = document.getElementById("amount").value;
                                            
                                            // サーバーに決済処理をリクエスト
                                            const response = await fetch("/fgsquare/api/process_payment.php", {
                                                method: "POST",
                                                headers: {
                                                    "Content-Type": "application/json"
                                                },
                                                body: JSON.stringify({
                                                    sourceId: sourceId,
                                                    orderId: orderId,
                                                    amount: amount
                                                })
                                            });
                                            
                                            const responseData = await response.json();
                                            if (responseData.success) {
                                                statusContainer.innerHTML = "<div class=\'test-result success\'>✓ 決済処理が成功しました：" + 
                                                    responseData.payment_id + "</div>";
                                            } else {
                                                statusContainer.innerHTML = "<div class=\'test-result failure\'>✗ 決済処理に失敗しました：" + 
                                                    responseData.error + "</div>";
                                            }
                                        } else {
                                            statusContainer.innerHTML = "<div class=\'test-result failure\'>✗ カード情報の処理に失敗しました：" + 
                                                result.errors[0].message + "</div>";
                                        }
                                    } catch (e) {
                                        console.error(e);
                                        const statusContainer = document.getElementById("payment-status-container");
                                        statusContainer.innerHTML = "<div class=\'test-result failure\'>✗ エラーが発生しました：" + e.message + "</div>";
                                    } finally {
                                        cardButton.disabled = false;
                                    }
                                });
                            } catch (e) {
                                console.error(e);
                                const container = document.getElementById("payment-form");
                                container.innerHTML = "<div class=\'test-result failure\'>✗ Square Payment SDKの初期化に失敗しました：" + e.message + "</div>";
                            }
                        });
                        </script>';
                        
                        echo '</div>';
                        break;
                        
                    case 'webhook_test':
                        // Webhook検証テスト
                        echo '<div class="test-section"><h4>Webhook検証テスト</h4>';
                        try {
                            // Webhook URLとエンドポイントの確認
                            $webhookUrl = BASE_URL . '/webhooks/square_webhook.php';
                            $webhookExists = Utils::checkUrlExists($webhookUrl);
                            
                            if ($webhookExists) {
                                echo '<div class="test-result success">';
                                echo '<p>✓ Webhookエンドポイントが存在します</p>';
                                echo '<p>URL: ' . htmlspecialchars($webhookUrl) . '</p>';
                                
                                // Webhook署名キーの検証
                                if (defined('SQUARE_WEBHOOK_SIGNATURE_KEY') && !empty(SQUARE_WEBHOOK_SIGNATURE_KEY)) {
                                    echo '<p>✓ Webhook署名キーが設定されています</p>';
                                } else {
                                    echo '<p>✗ Webhook署名キーが設定されていません</p>';
                                }
                                
                                echo '</div>';
                            } else {
                                echo '<div class="test-result failure">';
                                echo '<p>✗ Webhookエンドポイントが存在しません: ' . htmlspecialchars($webhookUrl) . '</p>';
                                echo '</div>';
                            }
                        } catch (Exception $e) {
                            echo '<div class="test-result failure">';
                            echo '<p>✗ Webhook検証エラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
                            echo '</div>';
                        }
                        echo '</div>';
                        break;
                }
            }
            
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="card mb-4"><h3>エラー</h3>';
            echo '<p style="color: red;">SquareServiceの初期化中にエラーが発生しました: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
    }
    ?>

    <div class="card mb-4">
        <h3>Square APIレート制限</h3>
        <p>Square APIには以下のレート制限があります：</p>
        <ul>
            <li>カタログAPI: 1日あたり5,000リクエスト</li>
            <li>在庫API: 1日あたり5,000リクエスト</li>
            <li>注文API: 1日あたり5,000リクエスト</li>
            <li>決済API: 1日あたり10,000リクエスト</li>
        </ul>
        <p>APIレート制限超過時は、エラーコード「RATE_LIMITED」が返されます。</p>
    </div>

    <div class="card">
        <h3>データベース同期ステータス</h3>
        <p>ローカルデータベースとSquareデータの同期状態を表示します。</p>
        
        <?php
        // データベース接続を取得
        try {
            $db = Database::getInstance();
            
            // 同期ステータスを表示（実装例）
            echo '<table class="data-table">';
            echo '<tr><th>テーブル</th><th>最終同期日時</th><th>同期状態</th></tr>';
            
            // 同期状態を取得するクエリ（実際のテーブル設計に合わせて調整が必要）
            $query = "SELECT * FROM sync_status WHERE provider = 'square' ORDER BY table_name ASC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $syncData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($syncData)) {
                echo '<tr><td colspan="3">同期データが見つかりません</td></tr>';
            } else {
                foreach ($syncData as $row) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['table_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['last_sync_time']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['status']) . '</td>';
                    echo '</tr>';
                }
            }
            
            echo '</table>';
        } catch (Exception $e) {
            echo '<p style="color: red;">データベース接続エラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </div>
</div>

<style>
.mb-4 {
    margin-bottom: 20px;
}
.test-section {
    margin-bottom: 15px;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.test-result {
    padding: 10px;
    border-radius: 4px;
}
.success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
}
.failure {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}
.data-table th, .data-table td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
}
.data-table th {
    background-color: #f2f2f2;
}
</style>

<?php
require_once 'test/views/footer.php';
?> 
<?php
/**
 * 商品データとカテゴリデータの同期スクリプト
 * 
 * このスクリプトは、Square APIから商品データおよびカテゴリデータを取得し、
 * データベースに同期します。CRON ジョブや手動実行向けに設計されています。
 * 
 * 実行方法:
 * 1. コマンドライン: php sync_products.php
 * 2. ブラウザから: https://example.com/api/sync/sync_products.php?token=<SYNC_TOKEN>
 * 
 * 注意: 本番環境で実行する場合は、SYNC_TOKENによる認証を必ず有効にしてください
 */

// 相対パスから必要なファイルをインクルード
$rootPath = realpath(__DIR__ . '/../../');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Database.php';
require_once $rootPath . '/api/lib/Utils.php';
require_once $rootPath . '/api/lib/SquareService.php';
require_once $rootPath . '/api/lib/ProductService.php';

// セキュリティチェック
if (php_sapi_name() !== 'cli') {
    // ブラウザからの実行の場合、トークン認証を行う
    $syncToken = isset($_GET['token']) ? $_GET['token'] : '';
    
    if (!defined('SYNC_TOKEN') || empty(SYNC_TOKEN) || $syncToken !== SYNC_TOKEN) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['error' => 'Invalid or missing sync token']);
        exit;
    }
}

/**
 * 商品同期と画像URL更新を実行する関数
 * 
 * @return array 処理結果
 */
function syncProducts() {
    try {
        Utils::log("商品同期処理を開始します", 'INFO', 'sync_products');
        
        $productService = new ProductService();
        
        // API呼び出し情報を記録
        $apiCallInfo = [
            'timestamp' => date('Y-m-d H:i:s'),
            'success' => false,
            'square_version' => defined('SQUARE_API_VERSION') ? SQUARE_API_VERSION : 'unknown'
        ];
        
        // ProductServiceにより商品同期を一元的に実行
        Utils::log("ProductService経由で商品同期を実行します", 'INFO', 'sync_products');
        $syncStartTime = date('Y-m-d H:i:s');
        Utils::log("同期開始時刻: {$syncStartTime}", 'INFO', 'sync_products');
        
        // ProductServiceによる統合同期処理の実行
        $syncResult = $productService->processProductSync();
        Utils::log("ProductService経由の商品同期が完了しました", 'INFO', 'sync_products');
        
        // 同期結果の詳細をログに出力
        if (isset($syncResult['stats'])) {
            Utils::log(
                "同期結果統計: 追加=" . ($syncResult['stats']['added'] ?? 0) . 
                ", 更新=" . ($syncResult['stats']['updated'] ?? 0) . 
                ", 無効化=" . ($syncResult['stats']['disabled'] ?? 0) . 
                ", エラー=" . ($syncResult['stats']['errors'] ?? 0),
                'INFO',
                'sync_products'
            );
        }
        
        // API呼び出し成功を記録
        $apiCallInfo['success'] = $syncResult['success'];
        
        // 同期処理が成功した場合、画像URL更新処理も実行
        if ($syncResult['success']) {
            Utils::log("商品同期が成功しました。画像URL更新処理を開始します", 'INFO', 'sync_products');
            
            try {
                // 最大100件の画像URLを更新
                $imageUpdateResult = $productService->updateImageUrls(100);
                
                // 結果をログに記録
                Utils::log(
                    "画像URL更新処理が完了しました: 更新=" . ($imageUpdateResult['updated'] ?? 0) . 
                    ", スキップ=" . ($imageUpdateResult['skipped'] ?? 0) . 
                    ", エラー=" . ($imageUpdateResult['errors'] ?? 0),
                    'INFO', 
                    'sync_products'
                );
                
                // 同期結果と画像更新結果を統合
                $result = [
                    'success' => true,
                    'message' => '商品同期と画像URL更新が完了しました',
                    'product_sync' => $syncResult,
                    'image_update' => $imageUpdateResult,
                    'api_call_success' => $apiCallInfo['success'],
                    'api_timestamp' => $apiCallInfo['timestamp'],
                    'square_version' => $apiCallInfo['square_version']
                ];
            } catch (Exception $e) {
                // 画像URL更新でエラーが発生しても商品同期は成功と判定
                Utils::log("画像URL更新処理でエラーが発生しました: " . $e->getMessage(), 'ERROR', 'sync_products');
                $result = [
                    'success' => true,
                    'message' => '商品同期は成功しましたが、画像URL更新でエラーが発生しました: ' . $e->getMessage(),
                    'product_sync' => $syncResult,
                    'image_update_error' => $e->getMessage(),
                    'api_call_success' => $apiCallInfo['success'],
                    'api_timestamp' => $apiCallInfo['timestamp'],
                    'square_version' => $apiCallInfo['square_version']
                ];
            }
        } else {
            // 商品同期が失敗した場合
            Utils::log("商品同期処理が失敗しました: " . $syncResult['message'], 'ERROR', 'sync_products');
            $result = array_merge($syncResult, [
                'api_call_success' => $apiCallInfo['success'],
                'api_timestamp' => $apiCallInfo['timestamp'],
                'square_version' => $apiCallInfo['square_version']
            ]);
        }
        
        Utils::log("同期処理の全てのステップが完了しました", 'INFO', 'sync_products');
        return $result;
    } catch (Exception $e) {
        Utils::log("予期せぬエラーが発生しました: " . $e->getMessage(), 'ERROR', 'sync_products');
        Utils::log("スタックトレース: " . $e->getTraceAsString(), 'ERROR', 'sync_products');
        return [
            'success' => false,
            'message' => '同期処理中に予期せぬエラーが発生しました: ' . $e->getMessage(),
            'error_message' => $e->getMessage(),
            'error_trace' => $e->getTraceAsString(),
            'api_call_success' => false,
            'api_timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * 同期処理を実行
 * 
 * @return array 処理結果
 */
function syncAll() {
    $results = [
        'products' => [
            'success' => false,
            'message' => '',
            'stats' => []
        ],
        'categories' => [
            'success' => false,
            'message' => '',
            'stats' => []
        ],
        'overall_success' => false,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    try {
        // ログ記録開始
        Utils::log("商品・カテゴリ同期処理を開始します", 'INFO', 'sync_all');
        
        // 1. 商品同期の実行
        Utils::log("商品同期を開始します", 'INFO', 'sync_all');
        $productResult = syncProducts();
        
        $results['products'] = $productResult;
        Utils::log("商品同期結果: " . ($productResult['success'] ? '成功' : '失敗'), 'INFO', 'sync_all');
        
        // 2. カテゴリ同期の実行
        Utils::log("カテゴリ同期を開始します", 'INFO', 'sync_all');
        
        // カテゴリ同期APIをトークン付きで呼び出し
        if (defined('SYNC_TOKEN') && !empty(SYNC_TOKEN)) {
            $categorySyncUrl = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . 
                              (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost') . 
                              '/fgsquare/api/sync/sync_categories.php?token=' . SYNC_TOKEN;
            
            Utils::log("カテゴリ同期API呼び出し: " . $categorySyncUrl, 'INFO', 'sync_all');
            
            $ch = curl_init($categorySyncUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30秒タイムアウト
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $categoryResult = json_decode($response, true);
                if (is_array($categoryResult) && isset($categoryResult['success'])) {
                    $results['categories'] = $categoryResult;
                    Utils::log("カテゴリ同期結果: " . ($categoryResult['success'] ? '成功' : '失敗'), 'INFO', 'sync_all');
                    
                    if ($categoryResult['success']) {
                        Utils::log("カテゴリ同期統計: 作成=" . ($categoryResult['stats']['created'] ?? 0) . 
                              ", 更新=" . ($categoryResult['stats']['updated'] ?? 0) . 
                              ", エラー=" . ($categoryResult['stats']['errors'] ?? 0), 'INFO', 'sync_all');
                    } else {
                        Utils::log("カテゴリ同期エラー: " . ($categoryResult['message'] ?? '不明なエラー'), 'ERROR', 'sync_all');
                    }
                } else {
                    $results['categories'] = [
                        'success' => false,
                        'message' => 'カテゴリ同期APIのレスポンスが無効です',
                        'stats' => ['errors' => 1]
                    ];
                    Utils::log("カテゴリ同期APIの応答が無効です: " . substr($response, 0, 200), 'ERROR', 'sync_all');
                }
            } else {
                $results['categories'] = [
                    'success' => false,
                    'message' => 'カテゴリ同期APIへのリクエストが失敗しました (HTTP ' . $httpCode . ')',
                    'stats' => ['errors' => 1]
                ];
                Utils::log("カテゴリ同期APIエラー: HTTP " . $httpCode . ", " . $curlError, 'ERROR', 'sync_all');
            }
        } else {
            // ダイレクトに同期関数を実行（fallback方式）
            Utils::log("SYNC_TOKENが未定義のため、ダイレクトにカテゴリ同期を実行します", 'WARNING', 'sync_all');
            
            $syncScriptPath = $rootPath . '/api/sync/sync_categories.php';
            if (file_exists($syncScriptPath)) {
                require_once $syncScriptPath;
                $categoryResult = syncCategories();
                $results['categories'] = $categoryResult;
                Utils::log("カテゴリ同期結果: " . ($categoryResult['success'] ? '成功' : '失敗'), 'INFO', 'sync_all');
            } else {
                $results['categories'] = [
                    'success' => false,
                    'message' => 'カテゴリ同期スクリプトが見つかりません',
                    'stats' => ['errors' => 1]
                ];
                Utils::log("カテゴリ同期スクリプトが見つかりません: " . $syncScriptPath, 'ERROR', 'sync_all');
            }
        }
        
        // 全体的な成功/失敗の判定
        $results['overall_success'] = $results['products']['success'] && $results['categories']['success'];
        
        return $results;
    } catch (Exception $e) {
        Utils::log("同期処理中に例外が発生しました: " . $e->getMessage(), 'ERROR', 'sync_all');
        Utils::log("スタックトレース: " . $e->getTraceAsString(), 'ERROR', 'sync_all');
        
        $results['products']['message'] = $e->getMessage();
        $results['products']['stats']['errors'] = 1;
        $results['overall_success'] = false;
        
        return $results;
    }
}

/**
 * 同期ステータスを更新
 * 
 * @param array $results 同期結果
 * @return bool 成功した場合はtrue
 */
function updateSyncStatus($results) {
    try {
        $db = Database::getInstance();
        
        // sync_statusテーブルが存在するか確認
        $tableExists = $db->select("SHOW TABLES LIKE 'sync_status'");
        
        if (empty($tableExists)) {
            Utils::log("sync_statusテーブルが存在しないため作成します", 'INFO', 'sync_all');
            
            $db->execute("
                CREATE TABLE IF NOT EXISTS sync_status (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    provider VARCHAR(50) NOT NULL,
                    table_name VARCHAR(50) NOT NULL,
                    last_sync_time DATETIME NOT NULL,
                    status VARCHAR(20) NOT NULL,
                    details TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY provider_table (provider, table_name)
                )
            ");
        }
        
        // 全体の同期ステータスを記録
        $overallStatus = $results['overall_success'] ? 'success' : 'error';
        
        // 既存のレコードを確認
        $existingRecord = $db->selectOne(
            "SELECT id FROM sync_status WHERE provider = ? AND table_name = ?",
            ['square', 'all']
        );
        
        $details = json_encode([
            'products' => isset($results['products']['stats']) ? $results['products']['stats'] : [],
            'categories' => isset($results['categories']['stats']) ? $results['categories']['stats'] : [],
            'timestamp' => $results['timestamp']
        ]);
        
        if ($existingRecord) {
            // 既存レコードを更新
            $result = $db->execute(
                "UPDATE sync_status SET 
                    last_sync_time = NOW(), 
                    status = ?, 
                    details = ? 
                WHERE provider = ? AND table_name = ?",
                [
                    $overallStatus,
                    $details,
                    'square',
                    'all'
                ]
            );
        } else {
            // 新規レコードを挿入
            $result = $db->execute(
                "INSERT INTO sync_status 
                 (provider, table_name, last_sync_time, status, details)
                 VALUES (?, ?, NOW(), ?, ?)",
                [
                    'square',
                    'all',
                    $overallStatus,
                    $details
                ]
            );
        }
        
        Utils::log("同期ステータスを更新しました: " . $overallStatus, 'INFO', 'sync_all');
        return true;
    } catch (Exception $e) {
        Utils::log("同期ステータスの更新中にエラーが発生しました: " . $e->getMessage(), 'ERROR', 'sync_all');
        return false;
    }
}

/**
 * 同期結果の整合性を確保するヘルパー関数
 * 
 * @param array $result 同期処理の結果
 * @return array 正規化された結果
 */
function normalizeResponse($result) {
    // デバッグログ
    Utils::log("正規化前のレスポンス構造: " . json_encode(array_keys($result)), 'DEBUG', 'sync_products');
    
    // レスポンスが既にトップレベルで'success'を持っている場合はそのまま返す
    if (isset($result['success'])) {
        // 画像更新情報がトップレベルの場合でも保持
        if (isset($result['product_sync']) && isset($result['image_update']) && !isset($result['products']['image_update'])) {
            $result['products']['image_update'] = $result['image_update'];
        }
        return $result;
    }
    
    // syncAll関数から返されるレスポンス構造を処理（overall_successを持つ）
    if (isset($result['overall_success']) && isset($result['products'])) {
        $normalizedResult = [
            'success' => $result['overall_success'],
            'message' => $result['products']['message'] ?? '同期処理が完了しました',
            'stats' => $result['products']['stats'] ?? ['added' => 0, 'updated' => 0, 'errors' => 0],
            'products' => $result['products']
        ];
        
        // 画像更新情報があれば追加
        if (isset($result['products']['product_sync']) && isset($result['products']['product_sync']['image_update'])) {
            $normalizedResult['image_update'] = $result['products']['product_sync']['image_update'];
        } elseif (isset($result['image_update'])) {
            $normalizedResult['image_update'] = $result['image_update'];
        }
        
        // カテゴリ情報も維持
        if (isset($result['categories'])) {
            $normalizedResult['categories'] = $result['categories'];
        }
        
        // 実行時間情報があれば追加
        if (isset($result['execution_time'])) {
            $normalizedResult['execution_time'] = $result['execution_time'];
        }
        
        return $normalizedResult;
    }
    
    // レスポンスが'product_sync'構造を持っている場合は正規化
    if (isset($result['product_sync'])) {
        $normalized = [
            'success' => $result['product_sync']['success'] ?? false,
            'message' => $result['message'] ?? ($result['product_sync']['message'] ?? ''),
            'stats' => $result['product_sync']['stats'] ?? ['added' => 0, 'updated' => 0, 'errors' => 0],
            'products' => [ 
                'success' => $result['product_sync']['success'] ?? false,
                'stats' => $result['product_sync']['stats'] ?? ['added' => 0, 'updated' => 0, 'errors' => 0]
            ]
        ];
        
        // 画像更新情報があれば追加
        if (isset($result['image_update'])) {
            $normalized['image_update'] = $result['image_update'];
        }
        
        return $normalized;
    }
    
    // productsキーだけを持つシンプルな構造
    if (isset($result['products']) && is_array($result['products'])) {
        $normalized = [
            'success' => $result['products']['success'] ?? false,
            'message' => $result['products']['message'] ?? '同期処理が完了しました',
            'stats' => $result['products']['stats'] ?? ['added' => 0, 'updated' => 0, 'errors' => 0],
            'products' => $result['products']
        ];
        
        // 画像更新情報があれば追加
        if (isset($result['image_update'])) {
            $normalized['image_update'] = $result['image_update'];
        }
        
        return $normalized;
    }
    
    // デバッグログ
    Utils::log("想定外のレスポンス構造: " . json_encode(array_keys($result)), 'WARNING', 'sync_products');
    
    // 標準レスポンスに変換
    return [
        'success' => false,
        'message' => '同期結果のフォーマットが不正です',
        'stats' => ['added' => 0, 'updated' => 0, 'errors' => 0],
        'products' => [
            'success' => false,
            'stats' => ['added' => 0, 'updated' => 0, 'errors' => 0]
        ]
    ];
}

// メイン処理実行
$startTime = microtime(true);
$results = syncAll();
$endTime = microtime(true);
$executionTime = round(($endTime - $startTime), 2);

// 同期ステータスを更新
updateSyncStatus($results);

// 実行時間をログに記録
Utils::log("同期処理完了 - 実行時間: " . $executionTime . "秒", 'INFO', 'sync_all');

// 出力形式を設定（CLIの場合はテキスト、ブラウザの場合はJSON）
if (php_sapi_name() === 'cli') {
    // コマンドライン実行の場合
    echo "商品・カテゴリ同期処理結果: " . ($results['overall_success'] ? "成功" : "失敗") . "\n";
    echo "実行時間: " . $executionTime . " 秒\n";
    echo "商品同期: " . $results['products']['message'] . "\n";
    echo "カテゴリ同期: " . $results['categories']['message'] . "\n";
    
    if (isset($results['products']['stats'])) {
        echo "商品統計:\n";
        foreach ($results['products']['stats'] as $key => $value) {
            echo "  - {$key}: {$value}\n";
        }
    }
    
    if (isset($results['categories']['stats'])) {
        echo "カテゴリ統計:\n";
        foreach ($results['categories']['stats'] as $key => $value) {
            echo "  - {$key}: {$value}\n";
        }
    }
} else {
    // ブラウザからの実行の場合
    header('Content-Type: application/json');
    $results['execution_time'] = $executionTime;
    // レスポンスを正規化してから出力
    echo json_encode(normalizeResponse($results));
} 
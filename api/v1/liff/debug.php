<?php
/**
 * デバッグ情報を提供するAPIエンドポイント
 * 開発・テスト環境専用
 */

// ヘッダー設定
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

/**
 * ファイルの最後のn行を読み込む関数
 * 
 * @param string $filepath 読み込むファイルのパス
 * @param int $n 読み込む行数
 * @return array 読み込んだ行の配列
 */
function readLastLines($filepath, $n = 100) {
    if (!file_exists($filepath) || !is_readable($filepath)) {
        return [];
    }
    
    // ファイルサイズが大きすぎる場合は、最後の一部だけ読み込む
    $filesize = filesize($filepath);
    $maxBytes = 1024 * 1024; // 最大1MB
    
    if ($filesize > $maxBytes) {
        $fp = fopen($filepath, 'r');
        fseek($fp, -$maxBytes, SEEK_END);
        $buffer = fread($fp, $maxBytes);
        fclose($fp);
    } else {
        $buffer = file_get_contents($filepath);
    }
    
    // 行に分割
    $lines = explode("\n", $buffer);
    
    // 最初の行が不完全な可能性があるため削除
    if ($filesize > $maxBytes) {
        array_shift($lines);
    }
    
    // 最後のn行を取得
    $lines = array_slice($lines, -$n);
    
    // 空行を削除
    $lines = array_filter($lines, function($line) {
        return trim($line) !== '';
    });
    
    // 配列のキーを振り直す
    return array_values($lines);
}

// 本番環境では無効化
if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
    echo json_encode([
        'success' => false,
        'error' => 'デバッグモードが無効です',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// 必要なライブラリを読み込み
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../lib/Utils.php';

try {
    // リクエストパラメータ
    $action = isset($_GET['action']) ? $_GET['action'] : 'info';
    
    // 基本システム情報
    $systemInfo = [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'database' => [
            'host' => DB_HOST,
            'name' => DB_NAME,
            'user' => DB_USER
        ],
        'debug_mode' => DEBUG_MODE,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // アクションによって処理を分岐
    switch ($action) {
        case 'db_tables':
            // データベーステーブル一覧を取得
            $db = Database::getInstance();
            $tables = $db->select('SHOW TABLES');
            
            // 結果をフラット化
            $tableNames = [];
            foreach ($tables as $table) {
                $tableNames[] = reset($table);
            }
            
            echo json_encode([
                'success' => true,
                'system_info' => $systemInfo,
                'tables' => $tableNames
            ]);
            break;
            
        case 'products_count':
            // 商品数を取得
            $db = Database::getInstance();
            $result = $db->selectOne('SELECT COUNT(*) as count FROM products');
            $activeProducts = $db->selectOne('SELECT COUNT(*) as count FROM products WHERE is_active = 1');
            
            echo json_encode([
                'success' => true,
                'system_info' => $systemInfo,
                'products' => [
                    'total' => $result['count'] ?? 0,
                    'active' => $activeProducts['count'] ?? 0
                ]
            ]);
            break;
            
        case 'categories':
            // カテゴリ情報を取得
            $db = Database::getInstance();
            $categories = $db->select('SELECT DISTINCT category, COUNT(*) as product_count FROM products GROUP BY category');
            
            echo json_encode([
                'success' => true,
                'system_info' => $systemInfo,
                'categories' => $categories
            ]);
            break;
            
        case 'logs':
            // 直近のシステムログを取得（存在する場合）
            $db = Database::getInstance();
            $logs = [];
            
            try {
                $logs = $db->select('SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 20');
            } catch (Exception $e) {
                $logs = ['error' => 'システムログテーブルが見つかりません'];
            }
            
            echo json_encode([
                'success' => true,
                'system_info' => $systemInfo,
                'logs' => $logs
            ]);
            break;
            
        case 'file_logs':
            // ログファイルの内容を取得
            $logType = isset($_GET['type']) ? $_GET['type'] : 'database';
            $lines = isset($_GET['lines']) ? intval($_GET['lines']) : 100;
            
            // ログファイルのパスを決定
            switch ($logType) {
                case 'database':
                    $logFile = __DIR__ . '/../../../logs/Database.log';
                    break;
                case 'product':
                    $logFile = __DIR__ . '/../../../logs/ProductService.log';
                    break;
                case 'category':
                    $logFile = __DIR__ . '/../../../logs/CategoryAPI.log';
                    break;
                default:
                    $logFile = __DIR__ . '/../../../logs/' . $logType . '.log';
            }
            
            // ファイルの存在確認
            $fileExists = file_exists($logFile);
            $fileContent = [];
            
            if ($fileExists) {
                // ファイルから最新のn行を取得
                $fileContent = readLastLines($logFile, $lines);
            }
            
            echo json_encode([
                'success' => true,
                'system_info' => $systemInfo,
                'log_type' => $logType,
                'file_exists' => $fileExists,
                'file_path' => $logFile,
                'logs' => $fileContent
            ]);
            break;
            
        case 'test_query':
            // カテゴリSELECTクエリをテスト
            $db = Database::getInstance();
            $query = "SELECT DISTINCT category as id, category as name FROM products WHERE is_active = 1 AND category IS NOT NULL AND category != '' ORDER BY category";
            $result = $db->select($query);
            
            echo json_encode([
                'success' => true,
                'system_info' => $systemInfo,
                'query' => $query,
                'result' => $result
            ]);
            break;
            
        case 'info':
        default:
            // 基本情報のみ
            echo json_encode([
                'success' => true,
                'system_info' => $systemInfo
            ]);
            break;
    }
} catch (Exception $e) {
    // エラーレスポンス
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'system_info' => $systemInfo ?? [
            'php_version' => PHP_VERSION,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
} 
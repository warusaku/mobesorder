<?php
// データベース接続情報の読み込み
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/lib/Database.php';
require_once __DIR__ . '/../api/lib/Utils.php';

// CORSヘッダー設定
header('Content-Type: application/json');

// アクセス制限（テスト環境か管理者のみアクセス可能）
if (!isset($_COOKIE['admin_session']) || $_COOKIE['admin_session'] !== hash('sha256', ADMIN_KEY)) {
    echo json_encode(['error' => '認証エラー: アクセス権限がありません']);
    exit;
}

// データベース接続
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
} catch (Exception $e) {
    echo json_encode(['error' => 'データベース接続エラー: ' . $e->getMessage()]);
    exit;
}

// アクションに応じて処理を分岐
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_table_data':
        getTableData($db);
        break;
        
    case 'execute_query':
        executeQuery($db);
        break;
        
    default:
        echo json_encode(['error' => '不明なアクション: ' . $action]);
        break;
}

/**
 * テーブルデータを取得する
 * @param Database $db データベース接続オブジェクト
 */
function getTableData($db) {
    $table = $_GET['table'] ?? '';
    
    if (empty($table)) {
        echo json_encode(['error' => 'テーブル名が指定されていません']);
        return;
    }
    
    // テーブル名のバリデーション（英数字、アンダースコアのみ許可）
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        echo json_encode(['error' => '無効なテーブル名: ' . $table]);
        return;
    }
    
    try {
        // テーブルが存在するか確認
        $tables = $db->select("SHOW TABLES");
        $tableExists = false;
        
        foreach ($tables as $t) {
            if (reset($t) === $table) {
                $tableExists = true;
                break;
            }
        }
        
        if (!$tableExists) {
            echo json_encode(['error' => 'テーブルが存在しません: ' . $table]);
            return;
        }
        
        // テーブルデータを取得（最大500行まで）
        $data = $db->select("SELECT * FROM `$table` LIMIT 500");
        
        echo json_encode($data);
    } catch (Exception $e) {
        echo json_encode(['error' => 'テーブルデータ取得エラー: ' . $e->getMessage()]);
    }
}

/**
 * SQLクエリを実行する
 * @param Database $db データベース接続オブジェクト
 */
function executeQuery($db) {
    $query = $_POST['query'] ?? '';
    
    if (empty($query)) {
        echo json_encode(['error' => 'クエリが指定されていません']);
        return;
    }
    
    // 危険なクエリをブロック
    $forbiddenPatterns = [
        '/DROP\s+TABLE/i',
        '/DROP\s+DATABASE/i',
        '/TRUNCATE\s+TABLE/i',
        '/DELETE\s+FROM\s+(?!WHERE)/i',  // WHERE句のないDELETE
        '/ALTER\s+TABLE/i',
        '/CREATE\s+TABLE/i',
        '/CREATE\s+DATABASE/i'
    ];
    
    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $query)) {
            echo json_encode(['error' => '安全上の理由により、このクエリは実行できません']);
            return;
        }
    }
    
    try {
        // クエリの種類を判断
        $queryType = '';
        if (preg_match('/^\s*SELECT/i', $query)) {
            $queryType = 'SELECT';
        } elseif (preg_match('/^\s*(INSERT|UPDATE|DELETE)/i', $query)) {
            $queryType = 'DML';
        } else {
            $queryType = 'OTHER';
        }
        
        // クエリ実行
        if ($queryType === 'SELECT') {
            // SELECT文は結果セットを返す
            $result = $db->select($query);
            echo json_encode($result);
        } else {
            // その他のクエリは影響を受けた行数を返す
            $stmt = $db->getConnection()->prepare($query);
            $stmt->execute();
            $affectedRows = $stmt->affected_rows;
            
            echo json_encode([
                ['result' => 'クエリが実行されました', 'affected_rows' => $affectedRows]
            ]);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'クエリ実行エラー: ' . $e->getMessage()]);
    }
} 
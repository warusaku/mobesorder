<?php
// 出力バッファリングを開始
ob_start();

// PHPのエラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 設定ファイル読み込み
require_once __DIR__ . '/../../api/config/config.php';

// CORSヘッダー設定
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// デバッグログ設定
$logDir = __DIR__ . '/../../logs';
$logFile = $logDir . '/register_api.log';

// ログディレクトリの確認と作成
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

/**
 * デバッグ情報をログファイルに記録
 */
function debugLog($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    error_log($logMessage, 3, $logFile);
}

// ログ開始
debugLog("--- テーブル作成処理開始 ---", 'INFO');

try {
    // データベース接続
    debugLog("データベース接続開始: ホスト=" . DB_HOST . ", データベース=" . DB_NAME, 'INFO');
    
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    debugLog("データベース接続成功", 'INFO');
    
    // 結果配列の初期化
    $result = [
        'success' => true,
        'message' => 'テーブル更新処理を完了しました',
        'tables' => []
    ];
    
    // line_room_linksテーブルのチェックと更新
    try {
        // テーブルが存在するか確認
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'line_room_links'");
        $stmt->execute();
        $tableExists = $stmt->fetch(PDO::FETCH_NUM);
        
        if ($tableExists) {
            // テーブル構造を確認
            $stmt = $pdo->prepare("DESCRIBE line_room_links");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $result['tables']['line_room_links']['before'] = $columns;
            
            // user_nameカラムの存在チェックと追加
            if (!in_array('user_name', $columns)) {
                $stmt = $pdo->prepare("ALTER TABLE line_room_links ADD COLUMN user_name VARCHAR(255) AFTER room_number");
                $stmt->execute();
                $result['tables']['line_room_links']['changes'][] = "user_nameカラムを追加しました";
            }
            
            // check_in_dateカラムの存在チェックと追加
            if (!in_array('check_in_date', $columns)) {
                $stmt = $pdo->prepare("ALTER TABLE line_room_links ADD COLUMN check_in_date DATE AFTER user_name");
                $stmt->execute();
                $result['tables']['line_room_links']['changes'][] = "check_in_dateカラムを追加しました";
            }
            
            // check_out_dateカラムの存在チェックと追加
            if (!in_array('check_out_date', $columns)) {
                $stmt = $pdo->prepare("ALTER TABLE line_room_links ADD COLUMN check_out_date DATE AFTER check_in_date");
                $stmt->execute();
                $result['tables']['line_room_links']['changes'][] = "check_out_dateカラムを追加しました";
            }
            
            // created_atカラムの存在チェックと追加
            if (!in_array('created_at', $columns)) {
                $stmt = $pdo->prepare("ALTER TABLE line_room_links ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
                $stmt->execute();
                $result['tables']['line_room_links']['changes'][] = "created_atカラムを追加しました";
            }
            
            // updated_atカラムの存在チェックと追加
            if (!in_array('updated_at', $columns)) {
                $stmt = $pdo->prepare("ALTER TABLE line_room_links ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                $stmt->execute();
                $result['tables']['line_room_links']['changes'][] = "updated_atカラムを追加しました";
            }
            
            // 最新の構造を取得
            $stmt = $pdo->prepare("DESCRIBE line_room_links");
            $stmt->execute();
            $result['tables']['line_room_links']['after'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
        } else {
            // テーブルが存在しない場合は作成
            $sql = "CREATE TABLE line_room_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                line_user_id VARCHAR(255) NOT NULL UNIQUE,
                room_number VARCHAR(50) NOT NULL,
                user_name VARCHAR(255),
                check_in_date DATE NOT NULL,
                check_out_date DATE NOT NULL,
                access_token VARCHAR(64),
                is_active BOOLEAN DEFAULT TRUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $pdo->exec($sql);
            $result['tables']['line_room_links']['created'] = true;
            
            // 作成したテーブルの構造を取得
            $stmt = $pdo->prepare("DESCRIBE line_room_links");
            $stmt->execute();
            $result['tables']['line_room_links']['structure'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $result['tables']['line_room_links']['error'] = $e->getMessage();
    }
    
    // room_tokensテーブルのチェックと更新
    try {
        // テーブルが存在するか確認
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'room_tokens'");
        $stmt->execute();
        $tableExists = $stmt->fetch(PDO::FETCH_NUM);
        
        if ($tableExists) {
            // テーブル構造を確認
            $stmt = $pdo->prepare("DESCRIBE room_tokens");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $result['tables']['room_tokens']['before'] = $columns;
            
            // tokenカラムの存在チェックと追加
            if (!in_array('token', $columns) && in_array('access_token', $columns)) {
                $stmt = $pdo->prepare("ALTER TABLE room_tokens ADD COLUMN token VARCHAR(255) AFTER room_number");
                $stmt->execute();
                $result['tables']['room_tokens']['changes'][] = "tokenカラムを追加しました";
            }
            
            // expires_atカラムの存在チェックと追加
            if (!in_array('expires_at', $columns)) {
                $stmt = $pdo->prepare("ALTER TABLE room_tokens ADD COLUMN expires_at DATETIME NOT NULL AFTER token");
                $stmt->execute();
                $result['tables']['room_tokens']['changes'][] = "expires_atカラムを追加しました";
            }
            
            // created_atカラムの存在チェックと追加
            if (!in_array('created_at', $columns)) {
                $stmt = $pdo->prepare("ALTER TABLE room_tokens ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
                $stmt->execute();
                $result['tables']['room_tokens']['changes'][] = "created_atカラムを追加しました";
            }
            
            // updated_atカラムの存在チェックと追加
            if (!in_array('updated_at', $columns)) {
                $stmt = $pdo->prepare("ALTER TABLE room_tokens ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                $stmt->execute();
                $result['tables']['room_tokens']['changes'][] = "updated_atカラムを追加しました";
            }
            
            // 最新の構造を取得
            $stmt = $pdo->prepare("DESCRIBE room_tokens");
            $stmt->execute();
            $result['tables']['room_tokens']['after'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
        } else {
            // テーブルが存在しない場合は作成
            $sql = "CREATE TABLE room_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                room_number VARCHAR(50) NOT NULL UNIQUE,
                token VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $pdo->exec($sql);
            $result['tables']['room_tokens']['created'] = true;
            
            // 作成したテーブルの構造を取得
            $stmt = $pdo->prepare("DESCRIBE room_tokens");
            $stmt->execute();
            $result['tables']['room_tokens']['structure'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $result['tables']['room_tokens']['error'] = $e->getMessage();
    }
    
    // room_registrationsテーブルのチェックと作成
    try {
        // テーブルが存在するか確認
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'room_registrations'");
        $stmt->execute();
        $tableExists = $stmt->fetch(PDO::FETCH_NUM);
        
        if ($tableExists) {
            // テーブル構造を確認
            $stmt = $pdo->prepare("DESCRIBE room_registrations");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $result['tables']['room_registrations']['before'] = $columns;
            
            // check_out_timeカラムの存在チェックと追加
            if (!in_array('check_out_time', $columns)) {
                $stmt = $pdo->prepare("ALTER TABLE room_registrations ADD COLUMN check_out_time VARCHAR(10) DEFAULT '11:00' AFTER check_out_date");
                $stmt->execute();
                $result['tables']['room_registrations']['changes'][] = "check_out_timeカラムを追加しました";
            }
            
            // 最新の構造を取得
            $stmt = $pdo->prepare("DESCRIBE room_registrations");
            $stmt->execute();
            $result['tables']['room_registrations']['after'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
        } else {
            // テーブルが存在しない場合は作成
            debugLog("room_registrationsテーブルが存在しないため、新規作成します", 'INFO');
            
            $sql = "CREATE TABLE room_registrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                line_user_id VARCHAR(255) NOT NULL,
                room_number VARCHAR(50) NOT NULL,
                user_name VARCHAR(255) NOT NULL,
                check_in_date DATE NOT NULL,
                check_out_date DATE NOT NULL,
                check_out_time VARCHAR(10) DEFAULT '11:00',
                is_active BOOLEAN DEFAULT TRUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_line_user_id (line_user_id),
                INDEX idx_room_number (room_number),
                INDEX idx_dates (check_in_date, check_out_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $pdo->exec($sql);
            $result['tables']['room_registrations']['created'] = true;
            debugLog("room_registrationsテーブルを作成しました", 'INFO');
            
            // 作成したテーブルの構造を取得
            $stmt = $pdo->prepare("DESCRIBE room_registrations");
            $stmt->execute();
            $result['tables']['room_registrations']['structure'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        debugLog("room_registrationsテーブル作成中にエラー: " . $e->getMessage(), 'ERROR');
        $result['tables']['room_registrations']['error'] = $e->getMessage();
    }
    
    // レスポンスを返す前にバッファをクリア
    ob_end_clean();
    
    // 結果を表示
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    // バッファをクリアしてJSONエラーレスポンスを返す
    ob_end_clean();
    
    // エラーレスポンス
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベース接続エラー: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    // バッファをクリアしてJSONエラーレスポンスを返す
    ob_end_clean();
    
    // エラーレスポンス
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'エラー: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
} 
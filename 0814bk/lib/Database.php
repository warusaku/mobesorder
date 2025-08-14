<?php
/**
 * データベース接続クラス
 */
class Database {
    private static $instance = null;
    private $conn;
    private static $logFile = null;
    private static $maxLogSize = 307200; // 300KB
    
    /**
     * コンストラクタ - データベース接続を確立
     */
    private function __construct() {
        self::initLogFile();
        self::logMessage("Database::__construct - データベース接続開始", 'INFO');
        
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            // デバッグ: 接続情報を出力
            // 重要: 本番環境では絶対に出力しない - DEBUG_MODEとDEVELOPMENT_MODEの両方が必要
            if (defined('DEBUG_MODE') && DEBUG_MODE && defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
                // ログファイルに記録するが、標準出力には出さない
                self::logMessage("デバッグモード: データベース接続情報: " . DB_HOST . ", " . DB_NAME);
                // 以下の行をコメントアウト - 標準出力はしない
                // echo "データベース接続情報: " . DB_HOST . ", " . DB_NAME . "<br>";
            }
            
            self::logMessage("データベース接続試行: DSN=" . $dsn);
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
            self::logMessage("データベース接続成功");
            
            // デバッグ: 接続成功メッセージ
            // 重要: 本番環境では絶対に出力しない - DEBUG_MODEとDEVELOPMENT_MODEの両方が必要
            if (defined('DEBUG_MODE') && DEBUG_MODE && defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
                // ログファイルに記録するが、標準出力には出さない
                self::logMessage("デバッグモード: データベース接続成功");
                self::logMessage("デバッグモード: テーブル一覧を取得");
                // 以下の行をコメントアウト - 標準出力はしない
                // echo "データベース接続成功<br>";
                // テーブル一覧を取得して表示
                $tables = $this->select("SHOW TABLES");
                self::logMessage("デバッグモード: テーブル一覧: " . json_encode($tables));
                // 以下の行をコメントアウト - 標準出力はしない
                // echo "存在するテーブル:<br>";
                // echo "<pre>";
                // print_r($tables);
                // echo "</pre>";
            }
        } catch (PDOException $e) {
            // デバッグ: エラー情報を詳細に出力
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                echo "データベース接続エラー: " . $e->getMessage() . "<br>";
                echo "DSN: " . $dsn . "<br>";
            }
            
            self::logMessage("データベース接続エラー: " . $e->getMessage(), 'ERROR');
            $this->logError("データベース接続エラー: " . $e->getMessage());
            throw new Exception("データベース接続に失敗しました。");
        }
    }
    
    /**
     * ログファイルの初期化
     * 
     * @return void
     */
    private static function initLogFile() {
        if (self::$logFile !== null) {
            return;
        }
        
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        self::$logFile = $logDir . '/Database.log';
        
        // ログローテーションのチェック
        self::checkLogRotation();
    }
    
    /**
     * ログファイルのローテーションチェック
     */
    private static function checkLogRotation() {
        if (!file_exists(self::$logFile)) {
            return;
        }
        
        // サイズベースのローテーション
        $fileSize = filesize(self::$logFile);
        if ($fileSize > self::$maxLogSize) {
            // ファイルを削除（バックアップなし）
            @unlink(self::$logFile);
            
            // 新しいログファイル作成の記録
            $timestamp = date('Y-m-d H:i:s');
            $message = "[$timestamp] [INFO] ログローテーション実行: ファイルサイズ " . round($fileSize / 1024, 2) . "KB が上限の " . round(self::$maxLogSize / 1024, 2) . "KB を超過\n";
            @file_put_contents(self::$logFile, $message);
        }
    }
    
    /**
     * ログメッセージをファイルに書き込む
     * 
     * @param string $message ログメッセージ
     * @param string $level ログレベル (INFO, WARNING, ERROR)
     */
    private static function logMessage($message, $level = 'INFO') {
        self::initLogFile();
        
        $timestamp = date('Y-m-d H:i:s');
        
        // 長すぎるメッセージは切り詰める
        if (strlen($message) > 1000) {
            $message = substr($message, 0, 997) . '...';
        }
        
        $logMessage = "[$timestamp] [$level] $message\n";
        
        // ログファイルへの書き込み
        @file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * シングルトンパターン - インスタンス取得
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * データベース接続を取得
     */
    public function getConnection() {
        self::logMessage("getConnection - データベース接続取得");
        return $this->conn;
    }
    
    /**
     * クエリを実行し、結果を取得
     * 
     * @param string $query SQL文
     * @param array $params バインドするパラメータ
     * @return array 結果の配列
     */
    public function select($query, $params = []) {
        try {
            $startTime = microtime(true);
            self::logMessage("SELECT実行開始: " . $query . " - パラメータ: " . json_encode($params));
            
            // 実行前にデバッグログに記録
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                Utils::log("SQL SELECT: " . $query . " - Params: " . json_encode($params), 'DEBUG', 'Database');
            }
            
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                $errorInfo = $this->conn->errorInfo();
                $errorMsg = "SQL準備エラー: " . ($errorInfo[2] ?? 'Unknown error');
                Utils::log($errorMsg, 'ERROR', 'Database');
                self::logMessage("SQL準備エラー: " . $errorMsg, 'ERROR');
                throw new Exception($errorMsg);
            }
            
            $result = $stmt->execute($params);
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                $errorMsg = "SQL実行エラー: " . ($errorInfo[2] ?? 'Unknown error');
                Utils::log($errorMsg, 'ERROR', 'Database');
                self::logMessage("SQL実行エラー: " . $errorMsg, 'ERROR');
                throw new Exception($errorMsg);
            }
            
            $data = $stmt->fetchAll();
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2); // ミリ秒に変換
            
            // 結果のログを記録
            self::logMessage("SELECT実行完了: 取得行数=" . count($data) . ", 実行時間=" . $executionTime . "ms");
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                Utils::log("SQL結果: " . count($data) . "件の行を取得", 'DEBUG', 'Database');
            }
            
            return $data;
        } catch (PDOException $e) {
            $errorMessage = "クエリ実行エラー: " . $e->getMessage() . " - クエリ: " . $query . " - パラメータ: " . json_encode($params);
            Utils::log($errorMessage, 'ERROR', 'Database');
            self::logMessage($errorMessage, 'ERROR');
            
            // スタックトレースも記録
            Utils::log("Stack trace: " . $e->getTraceAsString(), 'ERROR', 'Database');
            self::logMessage("Stack trace: " . $e->getTraceAsString(), 'ERROR');
            
            throw new Exception("データベースクエリの実行に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 単一行を取得するクエリを実行
     * 
     * @param string $query SQL文
     * @param array $params バインドするパラメータ
     * @return array|null 結果の1行、または結果がない場合はnull
     */
    public function selectOne($query, $params = []) {
        try {
            $startTime = microtime(true);
            self::logMessage("SELECTONE実行開始: " . $query . " - パラメータ: " . json_encode($params));
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2); // ミリ秒に変換
            
            self::logMessage("SELECTONE実行完了: 結果=" . ($result ? "データあり" : "データなし") . ", 実行時間=" . $executionTime . "ms");
            return $result !== false ? $result : null;
        } catch (PDOException $e) {
            $errorMessage = "クエリ実行エラー: " . $e->getMessage() . " - クエリ: " . $query . " - パラメータ: " . json_encode($params);
            self::logMessage($errorMessage, 'ERROR');
            $this->logError($errorMessage);
            throw new Exception("データベースクエリの実行に失敗しました。");
        }
    }
    
    /**
     * INSERT/UPDATE/DELETEクエリを実行
     * 
     * @param string $query SQL文
     * @param array $params バインドするパラメータ
     * @return int 影響を受けた行数
     */
    public function execute($query, $params = []) {
        try {
            $startTime = microtime(true);
            self::logMessage("EXECUTE実行開始: " . $query . " - パラメータ: " . json_encode($params));
            
            // 実行前にデバッグログに記録
            if (defined('DEBUG_MODE') && DEBUG_MODE || defined('SYNC_DEBUG') && SYNC_DEBUG) {
                $logPrefix = defined('SYNC_DEBUG') && SYNC_DEBUG ? "SQL実行:" : "Database execute:";
                $logMessage = $logPrefix . " " . $query . " - Params: " . json_encode($params);
                
                if (function_exists('error_log')) {
                    error_log($logMessage);
                }
                
                if (defined('SYNC_DEBUG') && SYNC_DEBUG && class_exists('Utils', false)) {
                    Utils::log($logMessage, 'DEBUG', 'Database');
                }
            }
            
            // クエリを準備
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                $errorInfo = $this->conn->errorInfo();
                $errorMsg = "SQL準備エラー: " . ($errorInfo[2] ?? 'Unknown error');
                
                if (defined('SYNC_DEBUG') && SYNC_DEBUG && class_exists('Utils', false)) {
                    Utils::log($errorMsg, 'ERROR', 'Database');
                }
                
                self::logMessage($errorMsg, 'ERROR');
                throw new PDOException($errorMsg);
            }
            
            // クエリを実行
            $result = $stmt->execute($params);
            $rowCount = $stmt->rowCount();
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2); // ミリ秒に変換
            
            self::logMessage("EXECUTE実行完了: 影響行数=" . $rowCount . ", 実行時間=" . $executionTime . "ms");
            
            // 結果をデバッグログに記録
            if (defined('DEBUG_MODE') && DEBUG_MODE || defined('SYNC_DEBUG') && SYNC_DEBUG) {
                $resultMsg = "SQL実行結果: " . ($result ? "成功" : "失敗") . " - 影響行数: " . $rowCount;
                
                if (function_exists('error_log')) {
                    error_log($resultMsg);
                }
                
                if (defined('SYNC_DEBUG') && SYNC_DEBUG && class_exists('Utils', false)) {
                    Utils::log($resultMsg, 'DEBUG', 'Database');
                }
            }
            
            // 実行結果が失敗の場合はエラー情報を収集
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                $errorCode = $errorInfo[0] ?? 'Unknown';
                $sqlState = $errorInfo[1] ?? 'Unknown';
                $errorMsg = $errorInfo[2] ?? 'Unknown error';
                
                $fullErrorMsg = "SQL実行失敗 - Code: $errorCode, State: $sqlState, Message: $errorMsg";
                
                if (defined('SYNC_DEBUG') && SYNC_DEBUG && class_exists('Utils', false)) {
                    Utils::log($fullErrorMsg, 'ERROR', 'Database');
                }
                
                self::logMessage($fullErrorMsg, 'ERROR');
                throw new PDOException($fullErrorMsg);
            }
            
            return $rowCount;
        } catch (PDOException $e) {
            $errorMessage = "クエリ実行エラー: " . $e->getMessage() . " - クエリ: " . $query . " - パラメータ: " . json_encode($params);
            self::logMessage($errorMessage, 'ERROR');
            
            // エラーログに記録
            if (function_exists('error_log')) {
                error_log($errorMessage);
                error_log("Stack trace: " . $e->getTraceAsString());
            }
            
            if (class_exists('Utils', false)) {
                Utils::log($errorMessage, 'ERROR', 'Database');
                // スタックトレースも記録
                Utils::log("Stack trace: " . $e->getTraceAsString(), 'ERROR', 'Database');
            }
            
            throw new Exception("データベースクエリの実行に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * テーブルにデータを挿入
     * 
     * @param string $table テーブル名
     * @param array $data 挿入するデータ（カラム名 => 値）
     * @return bool|int 挿入成功時は挿入ID、失敗時はfalse
     */
    public function insert($table, $data) {
        try {
            self::logMessage("INSERT実行開始: テーブル=" . $table . ", データ=" . json_encode($data));
            // カラム名とプレースホルダを作成
            $columns = array_keys($data);
            $placeholders = array_map(function($column) {
                return ":{$column}";
            }, $columns);
            
            // SQLクエリを構築
            $query = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            
            // パラメータを準備
            $params = [];
            foreach ($data as $column => $value) {
                $params[":{$column}"] = $value;
            }
            
            // 実行前にデバッグログに記録
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                Utils::log("SQL INSERT: " . $query . " - Params: " . json_encode($params), 'DEBUG', 'Database');
            }
            
            // トランザクション内で実行中かチェック
            $inTransaction = $this->conn->inTransaction();
            if (!$inTransaction) {
                self::logMessage("警告: トランザクション外でのINSERT操作", 'WARNING');
            }
            
            // SQL文の準備
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                $errorInfo = $this->conn->errorInfo();
                $errorMsg = "SQL準備エラー: " . ($errorInfo[2] ?? 'Unknown error');
                Utils::log($errorMsg, 'ERROR', 'Database');
                self::logMessage($errorMsg, 'ERROR');
                throw new Exception($errorMsg);
            }
            
            // パラメータをバインドしてクエリを実行
            $result = $stmt->execute($params);
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                $errorMsg = "INSERT実行エラー: " . ($errorInfo[2] ?? 'Unknown error');
                Utils::log($errorMsg, 'ERROR', 'Database');
                self::logMessage($errorMsg, 'ERROR');
                throw new Exception($errorMsg);
            }
            
            // 挿入されたIDを取得
            $lastId = $this->conn->lastInsertId();
            
            self::logMessage("INSERT実行成功: last_id=" . $lastId);
            return $lastId;
        } catch (PDOException $e) {
            $errorMessage = "INSERT実行エラー: " . $e->getMessage() . " - テーブル: " . $table . " - データ: " . json_encode($data);
            Utils::log($errorMessage, 'ERROR', 'Database');
            self::logMessage($errorMessage, 'ERROR');
            throw $e;
        }
    }
    
    /**
     * トランザクションを開始
     * 
     * @return bool 成功時はtrue
     */
    public function beginTransaction() {
        try {
            // 既にトランザクションが開始されていないか確認
            if ($this->conn->inTransaction()) {
                self::logMessage("トランザクション警告: 既にトランザクションが開始されています", 'WARNING');
                return true;
            }
            
            // トランザクション開始
            self::logMessage("トランザクション開始");
            $result = $this->conn->beginTransaction();
            
            if (!$result) {
                self::logMessage("トランザクション開始失敗", 'ERROR');
                return false;
            }
            
            return true;
        } catch (PDOException $e) {
            $errorMessage = "トランザクション開始エラー: " . $e->getMessage();
            Utils::log($errorMessage, 'ERROR', 'Database');
            self::logMessage($errorMessage, 'ERROR');
            return false;
        }
    }
    
    /**
     * トランザクションをコミット
     * 
     * @return bool 成功時はtrue
     */
    public function commit() {
        try {
            // トランザクションが開始されているか確認
            if (!$this->conn->inTransaction()) {
                self::logMessage("トランザクション警告: アクティブなトランザクションがないためコミットできません", 'WARNING');
                return false;
            }
            
            self::logMessage("トランザクションコミット");
            $result = $this->conn->commit();
            
            if (!$result) {
                self::logMessage("トランザクションコミット失敗", 'ERROR');
                return false;
            }
            
            return true;
        } catch (PDOException $e) {
            $errorMessage = "トランザクションコミットエラー: " . $e->getMessage();
            Utils::log($errorMessage, 'ERROR', 'Database');
            self::logMessage($errorMessage, 'ERROR');
            
            // コミット失敗時に自動的にロールバックを試みる
            try {
                if ($this->conn->inTransaction()) {
                    $this->conn->rollBack();
                    self::logMessage("コミット失敗後に自動ロールバック実行", 'WARNING');
                }
            } catch (Exception $rollbackEx) {
                self::logMessage("自動ロールバック失敗: " . $rollbackEx->getMessage(), 'ERROR');
            }
            
            return false;
        }
    }
    
    /**
     * トランザクションをロールバック
     * 
     * @return bool 成功時はtrue
     */
    public function rollback() {
        try {
            // トランザクションが開始されているか確認
            if (!$this->conn->inTransaction()) {
                self::logMessage("トランザクション警告: アクティブなトランザクションがないためロールバックできません", 'WARNING');
                return false;
            }
            
            self::logMessage("トランザクションロールバック");
            $result = $this->conn->rollBack();
            
            if (!$result) {
                self::logMessage("トランザクションロールバック失敗", 'ERROR');
                return false;
            }
            
            return true;
        } catch (PDOException $e) {
            $errorMessage = "トランザクションロールバックエラー: " . $e->getMessage();
            Utils::log($errorMessage, 'ERROR', 'Database');
            self::logMessage($errorMessage, 'ERROR');
            return false;
        }
    }
    
    /**
     * UPDATEクエリを実行するエイリアス（execute と同じ動作）
     * 
     * @param string $query SQL文
     * @param array $params バインドするパラメータ
     * @return int 影響を受けた行数
     */
    public function update($query, $params = []) {
        return $this->execute($query, $params);
    }
    
    /**
     * エラーログを記録
     */
    private function logError($message) {
        if (function_exists('error_log')) {
            error_log($message);
        }
        
        // $this->connがnullでない場合のみシステムログテーブルに記録を試みる
        if ($this->conn) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO system_logs (log_level, log_source, message)
                VALUES ('ERROR', 'Database', ?)
            ");
            $stmt->execute([$message]);
        } catch (Exception $e) {
            // ログテーブルへの書き込みに失敗した場合は無視
            self::logMessage("ログテーブル書き込み失敗: " . $e->getMessage(), 'ERROR');
            }
        }
    }
    
    /**
     * 最後のクエリで影響を受けた行数を取得
     * 
     * @return int 影響を受けた行数
     */
    public function getAffectedRows() {
        self::logMessage("最後のクエリで影響を受けた行数を取得", 'INFO');
        
        try {
            $stmt = $this->conn->query("SELECT ROW_COUNT() AS affected_rows");
            if ($stmt) {
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $count = isset($result['affected_rows']) ? (int)$result['affected_rows'] : 0;
                self::logMessage("影響を受けた行数: {$count}", 'INFO');
                return $count;
            }
            return 0;
        } catch (Exception $e) {
            self::logMessage("影響を受けた行数取得エラー: " . $e->getMessage(), 'ERROR');
            return 0;
        }
    }
} 
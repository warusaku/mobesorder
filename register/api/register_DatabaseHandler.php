<?php
/**
 * register_DatabaseHandler.php
 * Version: 1.0.0
 * 
 * データベース接続とトランザクション管理を担当するクラス
 * PDO接続、トランザクション管理、エラーハンドリングを実装
 */

require_once dirname(__DIR__, 2) . '/api/config/config.php';
require_once 'register_Logger.php';

class RegisterDatabaseHandler {
    private $pdo;
    private $logger;
    private $inTransaction = false;
    
    /**
     * コンストラクタ
     * 
     * @param RegisterLogger $logger ログインスタンス
     */
    public function __construct(RegisterLogger $logger) {
        $this->logger = $logger;
        $this->connect();
    }
    
    /**
     * データベースに接続
     * 
     * @throws Exception 接続失敗時
     */
    private function connect() {
        try {
            $this->logger->info('データベース接続を開始します');
            
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $this->pdo = new PDO(
                $dsn,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
                ]
            );
            
            $this->logger->info('データベース接続成功');
            
        } catch (PDOException $e) {
            $this->logger->error('データベース接続エラー: ' . $e->getMessage());
            throw new Exception('データベース接続に失敗しました: ' . $e->getMessage());
        }
    }
    
    /**
     * PDOインスタンスを取得
     * 
     * @return PDO
     */
    public function getPdo() {
        if (!$this->pdo) {
            $this->connect();
        }
        return $this->pdo;
    }
    
    /**
     * トランザクションを開始
     * 
     * @return bool
     */
    public function beginTransaction() {
        if ($this->inTransaction) {
            $this->logger->warning('既にトランザクション中です');
            return false;
        }
        
        try {
            $result = $this->pdo->beginTransaction();
            if ($result) {
                $this->inTransaction = true;
                $this->logger->info('トランザクション開始');
            }
            return $result;
            
        } catch (PDOException $e) {
            $this->logger->error('トランザクション開始エラー: ' . $e->getMessage());
            throw new Exception('トランザクションの開始に失敗しました');
        }
    }
    
    /**
     * トランザクションをコミット
     * 
     * @return bool
     */
    public function commit() {
        if (!$this->inTransaction) {
            $this->logger->warning('トランザクションが開始されていません');
            return false;
        }
        
        try {
            $result = $this->pdo->commit();
            if ($result) {
                $this->inTransaction = false;
                $this->logger->info('トランザクションコミット成功');
            }
            return $result;
            
        } catch (PDOException $e) {
            $this->logger->error('トランザクションコミットエラー: ' . $e->getMessage());
            throw new Exception('トランザクションのコミットに失敗しました');
        }
    }
    
    /**
     * トランザクションをロールバック
     * 
     * @return bool
     */
    public function rollback() {
        if (!$this->inTransaction) {
            $this->logger->warning('トランザクションが開始されていません');
            return false;
        }
        
        try {
            $result = $this->pdo->rollBack();
            if ($result) {
                $this->inTransaction = false;
                $this->logger->info('トランザクションロールバック完了');
            }
            return $result;
            
        } catch (PDOException $e) {
            $this->logger->error('トランザクションロールバックエラー: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * プリペアドステートメントを実行
     * 
     * @param string $sql SQL文
     * @param array $params パラメータ
     * @return PDOStatement
     * @throws Exception 実行失敗時
     */
    public function execute($sql, $params = []) {
        try {
            $this->logger->debug('SQL実行', ['sql' => $sql, 'params' => $params]);
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                $this->logger->error('SQL実行エラー', $errorInfo);
                throw new Exception('SQL実行エラー: ' . ($errorInfo[2] ?? 'Unknown error'));
            }
            
            return $stmt;
            
        } catch (PDOException $e) {
            $this->logger->error('PDO実行例外: ' . $e->getMessage());
            $this->handlePdoException($e);
            throw $e;
        }
    }
    
    /**
     * 最後に挿入されたIDを取得
     * 
     * @return string
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * PDO例外を処理
     * 
     * @param PDOException $e
     * @throws Exception
     */
    private function handlePdoException(PDOException $e) {
        $message = $e->getMessage();
        $code = $e->getCode();
        
        $this->logger->error('PDO例外', [
            'code' => $code,
            'message' => $message,
            'sqlState' => $e->errorInfo[0] ?? 'Unknown',
            'driverError' => $e->errorInfo[1] ?? 'Unknown'
        ]);
        
        // ユーザーフレンドリーなエラーメッセージに変換
        if (strpos($message, 'Duplicate entry') !== false) {
            throw new Exception('データが既に存在します');
        } else if (strpos($message, 'cannot be null') !== false) {
            throw new Exception('必須項目が不足しています');
        } else if (strpos($message, 'Data too long') !== false) {
            throw new Exception('入力されたデータが長すぎます');
        } else {
            throw new Exception('データベース操作中にエラーが発生しました');
        }
    }
    
    /**
     * テーブルの存在確認
     * 
     * @param string $tableName テーブル名
     * @return bool
     */
    public function tableExists($tableName) {
        try {
            $sql = "SHOW TABLES LIKE :tableName";
            $stmt = $this->execute($sql, [':tableName' => $tableName]);
            return $stmt->rowCount() > 0;
            
        } catch (Exception $e) {
            $this->logger->error('テーブル存在確認エラー: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * テーブル構造情報を取得
     * 
     * @param string $tableName テーブル名
     * @return array
     */
    public function getTableStructure($tableName) {
        try {
            $this->logger->debug("テーブル構造を確認: $tableName");
            
            // カラム情報
            $stmt = $this->execute("DESCRIBE $tableName");
            $columns = $stmt->fetchAll();
            
            // インデックス情報
            $stmt = $this->execute("SHOW INDEX FROM $tableName");
            $indexes = $stmt->fetchAll();
            
            return [
                'columns' => $columns,
                'indexes' => $indexes
            ];
            
        } catch (Exception $e) {
            $this->logger->error('テーブル構造取得エラー: ' . $e->getMessage());
            return ['columns' => [], 'indexes' => []];
        }
    }
    
    /**
     * デストラクタ
     */
    public function __destruct() {
        if ($this->inTransaction) {
            $this->logger->warning('トランザクションが未完了のまま終了します');
            $this->rollback();
        }
    }
} 
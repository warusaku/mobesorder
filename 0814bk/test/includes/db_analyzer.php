<?php
/**
 * RTSP_Reader Test Framework - DatabaseAnalyzer
 * 
 * データベース診断ツール
 */

require_once __DIR__ . '/test_logger.php';

class DatabaseAnalyzer {
    private $db;
    private $logger;
    
    /**
     * DatabaseAnalyzerコンストラクタ
     *
     * @param PDO $db データベース接続
     * @param TestLogger $logger ロガーインスタンス
     */
    public function __construct($db, $logger = null) {
        $this->db = $db;
        $this->logger = $logger ?: new TestLogger();
    }
    
    /**
     * 利用可能なデータベース一覧を取得
     *
     * @return array データベース情報
     */
    public function getDatabases() {
        $this->logger->info("データベース一覧取得");
        
        try {
            $query = "SHOW DATABASES";
            $stmt = $this->db->query($query);
            $databases = [];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $databases[] = $row['Database'];
            }
            
            $this->logger->info("データベース一覧取得完了", ['count' => count($databases)]);
            
            return [
                'status' => 'success',
                'count' => count($databases),
                'databases' => $databases
            ];
        } catch (PDOException $e) {
            $this->logger->error("データベース一覧取得エラー", ['error' => $e->getMessage()]);
            
            return [
                'status' => 'error',
                'message' => 'データベース一覧取得中にエラーが発生しました: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * 指定データベース内のテーブル一覧を取得
     *
     * @param string $database データベース名
     * @return array テーブル情報
     */
    public function getTables($database) {
        $this->logger->info("テーブル一覧取得", ['database' => $database]);
        
        try {
            $query = "SHOW TABLES FROM `{$database}`";
            $stmt = $this->db->query($query);
            $tables = [];
            
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
            
            $this->logger->info("テーブル一覧取得完了", ['database' => $database, 'count' => count($tables)]);
            
            return [
                'status' => 'success',
                'database' => $database,
                'count' => count($tables),
                'tables' => $tables
            ];
        } catch (PDOException $e) {
            $this->logger->error("テーブル一覧取得エラー", ['database' => $database, 'error' => $e->getMessage()]);
            
            return [
                'status' => 'error',
                'message' => "テーブル一覧取得中にエラーが発生しました: " . $e->getMessage(),
                'database' => $database,
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * テーブル構造を取得
     *
     * @param string $database データベース名
     * @param string $table テーブル名
     * @return array テーブル構造情報
     */
    public function getTableStructure($database, $table) {
        $this->logger->info("テーブル構造取得", ['database' => $database, 'table' => $table]);
        
        try {
            $this->db->exec("USE `{$database}`");
            
            // テーブル構造取得
            $query = "DESCRIBE `{$table}`";
            $stmt = $this->db->query($query);
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // インデックス情報取得
            $query = "SHOW INDEX FROM `{$table}`";
            $stmt = $this->db->query($query);
            $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // テーブル情報取得
            $query = "SHOW TABLE STATUS LIKE '{$table}'";
            $stmt = $this->db->query($query);
            $tableInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // コメント情報の取得（MariaDBとMySQLで互換性を保つ）
            $createTableQuery = "SHOW CREATE TABLE `{$table}`";
            $stmt = $this->db->query($createTableQuery);
            $createTableInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            $createTableSql = $createTableInfo['Create Table'] ?? '';
            
            $this->logger->info("テーブル構造取得完了", [
                'database' => $database, 
                'table' => $table, 
                'columns' => count($columns),
                'indexes' => count($indexes)
            ]);
            
            return [
                'status' => 'success',
                'database' => $database,
                'table' => $table,
                'columns' => $columns,
                'indexes' => $indexes,
                'table_info' => $tableInfo,
                'create_table_sql' => $createTableSql
            ];
        } catch (PDOException $e) {
            $this->logger->error("テーブル構造取得エラー", [
                'database' => $database, 
                'table' => $table, 
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'message' => "テーブル構造取得中にエラーが発生しました: " . $e->getMessage(),
                'database' => $database,
                'table' => $table,
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * テーブルデータを取得
     *
     * @param string $database データベース名
     * @param string $table テーブル名
     * @param int $limit 取得する行数上限
     * @param int $offset 取得開始位置
     * @param string $where 検索条件（SQLのWHERE句、NULLの場合は指定なし）
     * @param string $orderBy ソート順（SQLのORDER BY句、NULLの場合は指定なし）
     * @return array テーブルデータ
     */
    public function getTableData($database, $table, $limit = 100, $offset = 0, $where = null, $orderBy = null) {
        $this->logger->info("テーブルデータ取得", [
            'database' => $database, 
            'table' => $table, 
            'limit' => $limit,
            'offset' => $offset
        ]);
        
        try {
            $this->db->exec("USE `{$database}`");
            
            // データ件数取得
            $countQuery = "SELECT COUNT(*) AS total FROM `{$table}`";
            if ($where) {
                $countQuery .= " WHERE {$where}";
            }
            $stmt = $this->db->query($countQuery);
            $totalCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // データ取得
            $query = "SELECT * FROM `{$table}`";
            if ($where) {
                $query .= " WHERE {$where}";
            }
            if ($orderBy) {
                $query .= " ORDER BY {$orderBy}";
            }
            $query .= " LIMIT {$offset}, {$limit}";
            
            $stmt = $this->db->query($query);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->logger->info("テーブルデータ取得完了", [
                'database' => $database, 
                'table' => $table, 
                'total' => $totalCount,
                'fetched' => count($rows)
            ]);
            
            return [
                'status' => 'success',
                'database' => $database,
                'table' => $table,
                'total_count' => $totalCount,
                'returned_count' => count($rows),
                'offset' => $offset,
                'limit' => $limit,
                'data' => $rows
            ];
        } catch (PDOException $e) {
            $this->logger->error("テーブルデータ取得エラー", [
                'database' => $database, 
                'table' => $table, 
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'message' => "テーブルデータ取得中にエラーが発生しました: " . $e->getMessage(),
                'database' => $database,
                'table' => $table,
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * 外部キー整合性チェック
     *
     * @param string $database データベース名
     * @return array 整合性チェック結果
     */
    public function checkForeignKeyIntegrity($database) {
        $this->logger->info("外部キー整合性チェック", ['database' => $database]);
        
        try {
            $this->db->exec("USE `{$database}`");
            
            // 外部キー情報取得
            $query = "
                SELECT 
                    TABLE_NAME, 
                    COLUMN_NAME, 
                    CONSTRAINT_NAME, 
                    REFERENCED_TABLE_NAME, 
                    REFERENCED_COLUMN_NAME 
                FROM 
                    INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE 
                    REFERENCED_TABLE_SCHEMA = :database 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['database' => $database]);
            $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $integrityResults = [];
            
            foreach ($foreignKeys as $fk) {
                $checkQuery = "
                    SELECT 
                        a.{$fk['COLUMN_NAME']} as fk_value,
                        COUNT(b.{$fk['REFERENCED_COLUMN_NAME']}) as ref_count
                    FROM 
                        `{$fk['TABLE_NAME']}` a
                        LEFT JOIN `{$fk['REFERENCED_TABLE_NAME']}` b ON a.{$fk['COLUMN_NAME']} = b.{$fk['REFERENCED_COLUMN_NAME']}
                    WHERE 
                        a.{$fk['COLUMN_NAME']} IS NOT NULL
                    GROUP BY 
                        a.{$fk['COLUMN_NAME']}
                    HAVING 
                        ref_count = 0
                    LIMIT 10
                ";
                
                try {
                    $stmt = $this->db->query($checkQuery);
                    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $integrityResults[] = [
                        'constraint' => $fk['CONSTRAINT_NAME'],
                        'table' => $fk['TABLE_NAME'],
                        'column' => $fk['COLUMN_NAME'],
                        'referenced_table' => $fk['REFERENCED_TABLE_NAME'],
                        'referenced_column' => $fk['REFERENCED_COLUMN_NAME'],
                        'violations' => $violations,
                        'has_violations' => count($violations) > 0
                    ];
                    
                    if (count($violations) > 0) {
                        $this->logger->warning("外部キー整合性違反を検出", [
                            'constraint' => $fk['CONSTRAINT_NAME'],
                            'table' => $fk['TABLE_NAME'], 
                            'violations_count' => count($violations)
                        ]);
                    }
                } catch (PDOException $e) {
                    $this->logger->error("外部キー整合性チェックエラー", [
                        'constraint' => $fk['CONSTRAINT_NAME'],
                        'table' => $fk['TABLE_NAME'], 
                        'error' => $e->getMessage()
                    ]);
                    
                    $integrityResults[] = [
                        'constraint' => $fk['CONSTRAINT_NAME'],
                        'table' => $fk['TABLE_NAME'],
                        'column' => $fk['COLUMN_NAME'],
                        'referenced_table' => $fk['REFERENCED_TABLE_NAME'],
                        'referenced_column' => $fk['REFERENCED_COLUMN_NAME'],
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $this->logger->info("外部キー整合性チェック完了", [
                'database' => $database, 
                'foreign_keys' => count($foreignKeys)
            ]);
            
            return [
                'status' => 'success',
                'database' => $database,
                'foreign_key_count' => count($foreignKeys),
                'integrity_results' => $integrityResults
            ];
        } catch (PDOException $e) {
            $this->logger->error("外部キー整合性チェックエラー", [
                'database' => $database, 
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'message' => "外部キー整合性チェック中にエラーが発生しました: " . $e->getMessage(),
                'database' => $database,
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * データベース概要情報を取得
     *
     * @param string $database データベース名
     * @return array データベース概要情報
     */
    public function getDatabaseSummary($database) {
        $this->logger->info("データベース概要情報取得", ['database' => $database]);
        
        try {
            // テーブル一覧取得
            $tablesResult = $this->getTables($database);
            
            if ($tablesResult['status'] !== 'success') {
                return $tablesResult;
            }
            
            $tables = $tablesResult['tables'];
            $tablesInfo = [];
            $totalRows = 0;
            $totalSize = 0;
            
            // 各テーブルの情報を取得
            $this->db->exec("USE `{$database}`");
            
            foreach ($tables as $table) {
                $query = "SHOW TABLE STATUS LIKE '{$table}'";
                $stmt = $this->db->query($query);
                $tableStatus = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // 行数と大きさを計算
                $rows = $tableStatus['Rows'] ?? 0;
                $dataLength = $tableStatus['Data_length'] ?? 0;
                $indexLength = $tableStatus['Index_length'] ?? 0;
                $totalSize += ($dataLength + $indexLength);
                $totalRows += $rows;
                
                $tablesInfo[] = [
                    'name' => $table,
                    'rows' => $rows,
                    'data_size' => $this->formatBytes($dataLength),
                    'index_size' => $this->formatBytes($indexLength),
                    'total_size' => $this->formatBytes($dataLength + $indexLength),
                    'engine' => $tableStatus['Engine'] ?? 'Unknown',
                    'collation' => $tableStatus['Collation'] ?? 'Unknown'
                ];
            }
            
            // テーブル情報を行数でソート
            usort($tablesInfo, function($a, $b) {
                return $b['rows'] - $a['rows']; // 降順
            });
            
            $this->logger->info("データベース概要情報取得完了", [
                'database' => $database, 
                'tables' => count($tables),
                'total_rows' => $totalRows,
                'total_size' => $this->formatBytes($totalSize)
            ]);
            
            return [
                'status' => 'success',
                'database' => $database,
                'tables_count' => count($tables),
                'total_rows' => $totalRows,
                'total_size' => $this->formatBytes($totalSize),
                'tables_info' => $tablesInfo
            ];
        } catch (PDOException $e) {
            $this->logger->error("データベース概要情報取得エラー", [
                'database' => $database, 
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'message' => "データベース概要情報取得中にエラーが発生しました: " . $e->getMessage(),
                'database' => $database,
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * バイト数を人間が読みやすい形式に変換
     *
     * @param int $bytes バイト数
     * @param int $precision 小数点以下の精度
     * @return string フォーマットされたサイズ
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * SQLクエリを実行
     *
     * @param string $database データベース名
     * @param string $query SQLクエリ
     * @param array $params バインドパラメータ
     * @return array クエリ結果
     */
    public function executeQuery($database, $query, $params = []) {
        $this->logger->info("SQLクエリ実行", [
            'database' => $database, 
            'query' => $query,
            'params_count' => count($params)
        ]);
        
        try {
            $this->db->exec("USE `{$database}`");
            
            $startTime = microtime(true);
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // SELECTクエリの場合は結果を取得
            if (stripos(trim($query), 'SELECT') === 0) {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $rowCount = count($rows);
                
                $this->logger->info("SELECTクエリ実行完了", [
                    'database' => $database, 
                    'rows' => $rowCount,
                    'execution_time' => $executionTime
                ]);
                
                return [
                    'status' => 'success',
                    'database' => $database,
                    'query_type' => 'SELECT',
                    'row_count' => $rowCount,
                    'execution_time' => $executionTime,
                    'data' => $rows
                ];
            } else {
                // 非SELECTクエリの場合は影響を受けた行数を返す
                $rowCount = $stmt->rowCount();
                
                $this->logger->info("非SELECTクエリ実行完了", [
                    'database' => $database, 
                    'affected_rows' => $rowCount,
                    'execution_time' => $executionTime
                ]);
                
                return [
                    'status' => 'success',
                    'database' => $database,
                    'query_type' => $this->getQueryType($query),
                    'affected_rows' => $rowCount,
                    'execution_time' => $executionTime
                ];
            }
        } catch (PDOException $e) {
            $this->logger->error("SQLクエリ実行エラー", [
                'database' => $database, 
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'message' => "SQLクエリ実行中にエラーが発生しました: " . $e->getMessage(),
                'database' => $database,
                'query' => $query,
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * SQLクエリのタイプを判定
     *
     * @param string $query SQLクエリ
     * @return string クエリタイプ
     */
    private function getQueryType($query) {
        $query = trim($query);
        if (stripos($query, 'INSERT') === 0) {
            return 'INSERT';
        } elseif (stripos($query, 'UPDATE') === 0) {
            return 'UPDATE';
        } elseif (stripos($query, 'DELETE') === 0) {
            return 'DELETE';
        } elseif (stripos($query, 'CREATE') === 0) {
            return 'CREATE';
        } elseif (stripos($query, 'ALTER') === 0) {
            return 'ALTER';
        } elseif (stripos($query, 'DROP') === 0) {
            return 'DROP';
        } else {
            return 'OTHER';
        }
    }
} 
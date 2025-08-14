<?php
/**
 * 同期マネージャークラス
 * 
 * このクラスはデータベース同期処理を管理するための機能を提供します。
 * ローカルデータベースと遠隔データベース間でのデータ同期を処理します。
 * 
 * @author Hideaki Kurata
 * @created 2023-06-15
 */
class SyncManager {
    /**
     * @var mysqli データベース接続
     */
    private $db;
    
    /**
     * @var array 同期対象テーブル
     */
    private $sync_tables = [
        'camera_settings',
        'area_definitions',
        'ocr_settings',
        'notification_settings',
        'system_settings'
    ];
    
    /**
     * コンストラクタ
     * 
     * @param mysqli $db_connection データベース接続
     */
    public function __construct($db_connection) {
        $this->db = $db_connection;
    }
    
    /**
     * リモートからの変更を処理する
     * 
     * @param array $changes 変更データの配列
     * @return array 処理結果
     */
    public function processRemoteChanges($changes) {
        $processed = [];
        $errors = [];
        
        // 変更がない場合は空配列を返す
        if (empty($changes)) {
            return [];
        }
        
        // トランザクション開始
        $this->db->begin_transaction();
        
        try {
            foreach ($changes as $change) {
                // 必須パラメータの検証
                if (!isset($change['table_name']) || !isset($change['record_id']) || !isset($change['change_type'])) {
                    $errors[] = "無効な変更データ: " . json_encode($change);
                    continue;
                }
                
                $table_name = $change['table_name'];
                $record_id = $change['record_id'];
                $change_type = $change['change_type'];
                $data = isset($change['data']) ? $change['data'] : [];
                
                // 対象テーブルの検証
                if (!in_array($table_name, $this->sync_tables)) {
                    $errors[] = "未サポートのテーブル: " . $table_name;
                    continue;
                }
                
                // 変更タイプに基づいた処理
                switch ($change_type) {
                    case 'INSERT':
                        $result = $this->insertRecord($table_name, $record_id, $data);
                        break;
                        
                    case 'UPDATE':
                        $result = $this->updateRecord($table_name, $record_id, $data);
                        break;
                        
                    case 'DELETE':
                        $result = $this->deleteRecord($table_name, $record_id);
                        break;
                        
                    default:
                        $errors[] = "未サポートの変更タイプ: " . $change_type;
                        continue;
                }
                
                if ($result) {
                    // 処理成功した変更を記録
                    $processed[] = [
                        'table_name' => $table_name,
                        'record_id' => $record_id,
                        'change_type' => $change_type,
                        'status' => 'success'
                    ];
                    
                    // 同期変更レコードを追加（処理済みとしてマーク）
                    $this->logSyncChange($table_name, $record_id, $change_type, 'REMOTE', 'SYNCED');
                } else {
                    $errors[] = "変更適用エラー: テーブル=" . $table_name . ", ID=" . $record_id . ", タイプ=" . $change_type;
                }
            }
            
            // 全ての変更が成功したらコミット
            if (empty($errors)) {
                $this->db->commit();
            } else {
                $this->db->rollback();
                throw new Exception("変更の適用中にエラーが発生しました: " . implode(", ", $errors));
            }
            
        } catch (Exception $e) {
            // エラー発生時はロールバック
            $this->db->rollback();
            error_log("同期エラー: " . $e->getMessage());
            throw $e;
        }
        
        return $processed;
    }
    
    /**
     * ローカルの未同期変更を取得
     * 
     * @param string $device_id デバイスID
     * @param int $limit 最大件数
     * @return array 未同期の変更
     */
    public function getLocalPendingChanges($device_id, $limit = 10) {
        $changes = [];
        
        try {
            // 未同期の変更を取得（優先度順）
            $query = "SELECT id, table_name, record_id, change_type, change_timestamp, priority
                      FROM sync_changes 
                      WHERE sync_status = 'PENDING' 
                        AND origin = 'REMOTE'
                      ORDER BY priority DESC, change_timestamp ASC
                      LIMIT ?";
                      
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $change_id = $row['id'];
                $table_name = $row['table_name'];
                $record_id = $row['record_id'];
                $change_type = $row['change_type'];
                
                // レコードデータの取得（DELETE以外）
                $data = [];
                if ($change_type !== 'DELETE') {
                    $data = $this->getRecordData($table_name, $record_id);
                }
                
                $changes[] = [
                    'change_id' => $change_id,
                    'table_name' => $table_name,
                    'record_id' => $record_id,
                    'change_type' => $change_type,
                    'data' => $data,
                    'timestamp' => $row['change_timestamp']
                ];
                
                // 送信済みとしてマーク
                $this->updateSyncStatus($change_id, 'SYNCED');
            }
            
        } catch (Exception $e) {
            error_log("未同期変更取得エラー: " . $e->getMessage());
            throw $e;
        }
        
        return $changes;
    }
    
    /**
     * 完全同期を実行
     * 
     * @param string $device_id デバイスID
     * @param array $request_data リクエストデータ
     * @return array 同期結果
     */
    public function performFullSync($device_id, $request_data) {
        $results = [];
        
        try {
            // 各同期テーブルを処理
            foreach ($this->sync_tables as $table) {
                $results[$table] = $this->syncTable($table, $device_id, $request_data);
            }
            
            // 同期ログを記録
            $this->logSyncOperation('FULL', count($results), 0);
            
        } catch (Exception $e) {
            error_log("完全同期エラー: " . $e->getMessage());
            throw $e;
        }
        
        return $results;
    }
    
    /**
     * 特定のテーブルを同期
     * 
     * @param string $table_name テーブル名
     * @param string $device_id デバイスID
     * @param array $request_data リクエストデータ
     * @return array 同期結果
     */
    private function syncTable($table_name, $device_id, $request_data) {
        $result = [
            'added' => 0,
            'updated' => 0,
            'deleted' => 0,
            'errors' => []
        ];
        
        // リクエストから特定テーブルのデータを取得
        $remote_data = isset($request_data['tables'][$table_name]) 
                    ? $request_data['tables'][$table_name] 
                    : [];
                    
        // リモートデータがない場合はスキップ
        if (empty($remote_data)) {
            return $result;
        }
        
        // データベースからローカルデータを取得
        $local_data = $this->getTableData($table_name);
        
        // IDをキーにしたマップを作成
        $local_map = [];
        foreach ($local_data as $record) {
            $id = $record['id'];
            $local_map[$id] = $record;
        }
        
        $remote_map = [];
        foreach ($remote_data as $record) {
            $id = $record['id'];
            $remote_map[$id] = $record;
        }
        
        // 追加/更新処理
        foreach ($remote_map as $id => $remote_record) {
            // ローカルに存在しない場合は追加
            if (!isset($local_map[$id])) {
                if ($this->insertRecord($table_name, $id, $remote_record)) {
                    $result['added']++;
                } else {
                    $result['errors'][] = "追加失敗: " . $table_name . " ID=" . $id;
                }
                continue;
            }
            
            // 更新日時を比較し、リモートの方が新しい場合は更新
            $local_record = $local_map[$id];
            $remote_updated = isset($remote_record['updated_at']) ? strtotime($remote_record['updated_at']) : 0;
            $local_updated = isset($local_record['updated_at']) ? strtotime($local_record['updated_at']) : 0;
            
            if ($remote_updated > $local_updated) {
                if ($this->updateRecord($table_name, $id, $remote_record)) {
                    $result['updated']++;
                } else {
                    $result['errors'][] = "更新失敗: " . $table_name . " ID=" . $id;
                }
            }
        }
        
        // 削除処理（オプション、デフォルトでは無効）
        $allow_delete = isset($request_data['allow_delete']) ? $request_data['allow_delete'] : false;
        if ($allow_delete) {
            foreach ($local_map as $id => $local_record) {
                // リモートに存在しない場合は削除
                if (!isset($remote_map[$id])) {
                    if ($this->deleteRecord($table_name, $id)) {
                        $result['deleted']++;
                    } else {
                        $result['errors'][] = "削除失敗: " . $table_name . " ID=" . $id;
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * テーブルデータを取得
     * 
     * @param string $table_name テーブル名
     * @return array テーブルデータ
     */
    private function getTableData($table_name) {
        $data = [];
        
        try {
            $query = "SELECT * FROM " . $this->db->real_escape_string($table_name);
            $result = $this->db->query($query);
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
                $result->free();
            }
        } catch (Exception $e) {
            error_log("テーブルデータ取得エラー: " . $e->getMessage());
        }
        
        return $data;
    }
    
    /**
     * レコードデータを取得
     * 
     * @param string $table_name テーブル名
     * @param int $record_id レコードID
     * @return array レコードデータ
     */
    private function getRecordData($table_name, $record_id) {
        $data = [];
        
        try {
            $query = "SELECT * FROM " . $this->db->real_escape_string($table_name) . " WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $record_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $data = $row;
            }
            
        } catch (Exception $e) {
            error_log("レコードデータ取得エラー: " . $e->getMessage());
        }
        
        return $data;
    }
    
    /**
     * レコードを挿入
     * 
     * @param string $table_name テーブル名
     * @param int $record_id レコードID
     * @param array $data レコードデータ
     * @return bool 成功した場合はtrue
     */
    private function insertRecord($table_name, $record_id, $data) {
        // IDフィールドが含まれていない場合は追加
        if (!isset($data['id'])) {
            $data['id'] = $record_id;
        }
        
        // フィールドと値のリストを生成
        $fields = [];
        $values = [];
        $placeholders = [];
        $types = "";
        
        foreach ($data as $field => $value) {
            $fields[] = "`" . $this->db->real_escape_string($field) . "`";
            $values[] = $value;
            $placeholders[] = "?";
            
            // データ型を判定
            if (is_int($value)) {
                $types .= "i";
            } elseif (is_float($value)) {
                $types .= "d";
            } elseif (is_string($value)) {
                $types .= "s";
            } else {
                $types .= "s";
                $values[count($values) - 1] = json_encode($value);
            }
        }
        
        // SQLクエリ作成
        $query = "INSERT INTO " . $this->db->real_escape_string($table_name) . " 
                 (" . implode(", ", $fields) . ") 
                 VALUES (" . implode(", ", $placeholders) . ")";
                 
        try {
            $stmt = $this->db->prepare($query);
            
            // パラメータをバインド
            $params = array_merge([$types], $values);
            $tmp = [];
            foreach ($params as $key => $value) {
                $tmp[$key] = &$params[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $tmp);
            
            $result = $stmt->execute();
            return $result;
            
        } catch (Exception $e) {
            error_log("レコード挿入エラー: " . $e->getMessage() . " - クエリ: " . $query);
            return false;
        }
    }
    
    /**
     * レコードを更新
     * 
     * @param string $table_name テーブル名
     * @param int $record_id レコードID
     * @param array $data レコードデータ
     * @return bool 成功した場合はtrue
     */
    private function updateRecord($table_name, $record_id, $data) {
        // 更新内容がない場合はtrueを返す
        if (empty($data)) {
            return true;
        }
        
        // IDフィールドは更新しない
        if (isset($data['id'])) {
            unset($data['id']);
        }
        
        // 更新用のフィールド=値の構文を生成
        $set_parts = [];
        $values = [];
        $types = "";
        
        foreach ($data as $field => $value) {
            $set_parts[] = "`" . $this->db->real_escape_string($field) . "` = ?";
            $values[] = $value;
            
            // データ型を判定
            if (is_int($value)) {
                $types .= "i";
            } elseif (is_float($value)) {
                $types .= "d";
            } elseif (is_string($value)) {
                $types .= "s";
            } else {
                $types .= "s";
                $values[count($values) - 1] = json_encode($value);
            }
        }
        
        // レコードIDのデータ型を追加
        $types .= "i";
        $values[] = $record_id;
        
        // SQLクエリ作成
        $query = "UPDATE " . $this->db->real_escape_string($table_name) . " 
                 SET " . implode(", ", $set_parts) . " 
                 WHERE id = ?";
                 
        try {
            $stmt = $this->db->prepare($query);
            
            // パラメータをバインド
            $params = array_merge([$types], $values);
            $tmp = [];
            foreach ($params as $key => $value) {
                $tmp[$key] = &$params[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $tmp);
            
            $result = $stmt->execute();
            return $result;
            
        } catch (Exception $e) {
            error_log("レコード更新エラー: " . $e->getMessage() . " - クエリ: " . $query);
            return false;
        }
    }
    
    /**
     * レコードを削除
     * 
     * @param string $table_name テーブル名
     * @param int $record_id レコードID
     * @return bool 成功した場合はtrue
     */
    private function deleteRecord($table_name, $record_id) {
        $query = "DELETE FROM " . $this->db->real_escape_string($table_name) . " WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $record_id);
            $result = $stmt->execute();
            return $result;
            
        } catch (Exception $e) {
            error_log("レコード削除エラー: " . $e->getMessage() . " - クエリ: " . $query);
            return false;
        }
    }
    
    /**
     * 同期変更をログに記録
     * 
     * @param string $table_name テーブル名
     * @param int $record_id レコードID
     * @param string $change_type 変更タイプ
     * @param string $origin 変更元
     * @param string $status 初期同期状態
     * @return bool 成功した場合はtrue
     */
    private function logSyncChange($table_name, $record_id, $change_type, $origin, $status = 'PENDING') {
        $query = "INSERT INTO sync_changes 
                 (table_name, record_id, change_type, origin, sync_status) 
                 VALUES (?, ?, ?, ?, ?)";
                 
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("sisss", $table_name, $record_id, $change_type, $origin, $status);
            $result = $stmt->execute();
            return $result;
            
        } catch (Exception $e) {
            error_log("同期変更ログ記録エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 同期状態を更新
     * 
     * @param int $change_id 変更ID
     * @param string $status 新しい状態
     * @param string $message メッセージ
     * @return bool 成功した場合はtrue
     */
    private function updateSyncStatus($change_id, $status, $message = '') {
        $query = "UPDATE sync_changes 
                 SET sync_status = ?, 
                     last_sync_attempt = NOW(), 
                     sync_message = ? 
                 WHERE id = ?";
                 
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ssi", $status, $message, $change_id);
            $result = $stmt->execute();
            return $result;
            
        } catch (Exception $e) {
            error_log("同期状態更新エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 同期操作をログに記録
     * 
     * @param string $sync_type 同期タイプ
     * @param int $sent 送信レコード数
     * @param int $received 受信レコード数
     * @param bool $success 成功フラグ
     * @param string $error_message エラーメッセージ
     * @param int $duration_ms 実行時間（ミリ秒）
     * @return bool 成功した場合はtrue
     */
    private function logSyncOperation($sync_type, $sent, $received, $success = true, $error_message = '', $duration_ms = 0) {
        $query = "INSERT INTO sync_logs 
                 (sync_type, records_sent, records_received, success, error_message, duration_ms) 
                 VALUES (?, ?, ?, ?, ?, ?)";
                 
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("siiisi", $sync_type, $sent, $received, $success, $error_message, $duration_ms);
            $result = $stmt->execute();
            return $result;
            
        } catch (Exception $e) {
            error_log("同期操作ログ記録エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 同期状態を取得
     * 
     * @return array 同期状態情報
     */
    public function getSyncStatus() {
        $status = [
            'pending_changes' => 0,
            'error_changes' => 0,
            'last_successful_sync' => null,
            'sync_health' => 'unknown',
            'tables' => []
        ];
        
        try {
            // 未同期変更数を取得
            $query = "SELECT COUNT(*) AS count FROM sync_changes WHERE sync_status = 'PENDING'";
            $result = $this->db->query($query);
            if ($row = $result->fetch_assoc()) {
                $status['pending_changes'] = (int)$row['count'];
            }
            
            // エラー変更数を取得
            $query = "SELECT COUNT(*) AS count FROM sync_changes WHERE sync_status = 'ERROR'";
            $result = $this->db->query($query);
            if ($row = $result->fetch_assoc()) {
                $status['error_changes'] = (int)$row['count'];
            }
            
            // 最後の成功同期を取得
            $query = "SELECT log_timestamp FROM sync_logs WHERE success = 1 ORDER BY log_timestamp DESC LIMIT 1";
            $result = $this->db->query($query);
            if ($row = $result->fetch_assoc()) {
                $status['last_successful_sync'] = $row['log_timestamp'];
            }
            
            // 同期ヘルスステータスを決定
            if ($status['error_changes'] > 10) {
                $status['sync_health'] = 'critical';
            } elseif ($status['error_changes'] > 0) {
                $status['sync_health'] = 'warning';
            } elseif ($status['last_successful_sync']) {
                $status['sync_health'] = 'healthy';
            }
            
            // テーブルごとの状態を取得
            foreach ($this->sync_tables as $table) {
                $table_status = [
                    'name' => $table,
                    'record_count' => 0,
                    'pending_changes' => 0,
                    'error_changes' => 0
                ];
                
                // レコード数を取得
                $query = "SELECT COUNT(*) AS count FROM " . $this->db->real_escape_string($table);
                $result = $this->db->query($query);
                if ($row = $result->fetch_assoc()) {
                    $table_status['record_count'] = (int)$row['count'];
                }
                
                // 未同期変更数を取得
                $query = "SELECT COUNT(*) AS count FROM sync_changes 
                         WHERE table_name = ? AND sync_status = 'PENDING'";
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("s", $table);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $table_status['pending_changes'] = (int)$row['count'];
                }
                
                // エラー変更数を取得
                $query = "SELECT COUNT(*) AS count FROM sync_changes 
                         WHERE table_name = ? AND sync_status = 'ERROR'";
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("s", $table);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $table_status['error_changes'] = (int)$row['count'];
                }
                
                $status['tables'][] = $table_status;
            }
            
        } catch (Exception $e) {
            error_log("同期状態取得エラー: " . $e->getMessage());
        }
        
        return $status;
    }
    
    /**
     * 特定条件の変更データを取得
     * 
     * @param string $device_id デバイスID
     * @param string $last_sync 最後の同期タイムスタンプ
     * @param array $table_filter テーブルフィルタ
     * @return array 変更データ
     */
    public function getChanges($device_id, $last_sync, $table_filter = []) {
        $changes = [];
        
        try {
            // SQLクエリ構築の基本部分
            $query = "SELECT id, table_name, record_id, change_type, change_timestamp 
                      FROM sync_changes 
                      WHERE origin = 'REMOTE'";
            
            $params = [];
            $types = "";
            
            // タイムスタンプフィルタ追加
            if ($last_sync) {
                $query .= " AND change_timestamp > ?";
                $params[] = $last_sync;
                $types .= "s";
            }
            
            // テーブルフィルタ追加
            if (!empty($table_filter)) {
                $placeholders = implode(',', array_fill(0, count($table_filter), '?'));
                $query .= " AND table_name IN ($placeholders)";
                
                foreach ($table_filter as $table) {
                    $params[] = $table;
                    $types .= "s";
                }
            }
            
            // ORDER BY と LIMIT 追加
            $query .= " ORDER BY priority DESC, change_timestamp ASC LIMIT 1000";
            
            // クエリ実行
            $stmt = $this->db->prepare($query);
            
            // パラメータがある場合はバインド
            if (!empty($params)) {
                $bind_params = array_merge([$types], $params);
                $tmp = [];
                foreach ($bind_params as $key => $value) {
                    $tmp[$key] = &$bind_params[$key];
                }
                call_user_func_array([$stmt, 'bind_param'], $tmp);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            // 結果の処理
            while ($row = $result->fetch_assoc()) {
                $change_id = $row['id'];
                $table_name = $row['table_name'];
                $record_id = $row['record_id'];
                $change_type = $row['change_type'];
                
                // レコードデータの取得（DELETE以外）
                $data = [];
                if ($change_type !== 'DELETE') {
                    $data = $this->getRecordData($table_name, $record_id);
                }
                
                $changes[] = [
                    'change_id' => $change_id,
                    'table_name' => $table_name,
                    'record_id' => $record_id,
                    'change_type' => $change_type,
                    'data' => $data,
                    'timestamp' => $row['change_timestamp']
                ];
            }
            
        } catch (Exception $e) {
            error_log("変更データ取得エラー: " . $e->getMessage());
            throw $e;
        }
        
        return $changes;
    }
    
    /**
     * 変更を適用
     * 
     * @param array $changes 変更データ
     * @param string $device_id デバイスID
     * @return array 処理結果
     */
    public function applyChanges($changes, $device_id) {
        $result = [
            'applied_count' => 0,
            'errors' => []
        ];
        
        // 変更がない場合は結果を返す
        if (empty($changes)) {
            return $result;
        }
        
        // トランザクション開始
        $this->db->begin_transaction();
        
        try {
            foreach ($changes as $change) {
                // 必須パラメータの検証
                if (!isset($change['table_name']) || !isset($change['record_id']) || !isset($change['change_type'])) {
                    $result['errors'][] = "無効な変更データ: " . json_encode($change);
                    continue;
                }
                
                $table_name = $change['table_name'];
                $record_id = $change['record_id'];
                $change_type = $change['change_type'];
                $data = isset($change['data']) ? $change['data'] : [];
                
                // 対象テーブルの検証
                if (!in_array($table_name, $this->sync_tables)) {
                    $result['errors'][] = "未サポートのテーブル: " . $table_name;
                    continue;
                }
                
                // 変更タイプに基づいた処理
                $success = false;
                switch ($change_type) {
                    case 'INSERT':
                        $success = $this->insertRecord($table_name, $record_id, $data);
                        break;
                        
                    case 'UPDATE':
                        $success = $this->updateRecord($table_name, $record_id, $data);
                        break;
                        
                    case 'DELETE':
                        $success = $this->deleteRecord($table_name, $record_id);
                        break;
                        
                    default:
                        $result['errors'][] = "未サポートの変更タイプ: " . $change_type;
                        continue;
                }
                
                if ($success) {
                    $result['applied_count']++;
                    
                    // 同期変更レコードを追加（処理済みとしてマーク）
                    $this->logSyncChange($table_name, $record_id, $change_type, 'REMOTE', 'SYNCED');
                } else {
                    $result['errors'][] = "変更適用エラー: テーブル=" . $table_name . ", ID=" . $record_id . ", タイプ=" . $change_type;
                }
            }
            
            // 全ての変更が成功したらコミット
            if (empty($result['errors'])) {
                $this->db->commit();
            } else {
                $this->db->rollback();
                throw new Exception("変更の適用中にエラーが発生しました: " . implode(", ", $result['errors']));
            }
            
        } catch (Exception $e) {
            // エラー発生時はロールバック
            $this->db->rollback();
            error_log("変更適用エラー: " . $e->getMessage());
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }
} 
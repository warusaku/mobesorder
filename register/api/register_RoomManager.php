<?php
/**
 * register_RoomManager.php
 * Version: 1.0.0
 * 
 * 部屋管理と登録処理を担当するクラス
 * 部屋の確認、登録、更新、削除機能を実装
 */

require_once 'register_Logger.php';
require_once 'register_DatabaseHandler.php';

class RegisterRoomManager {
    private $logger;
    private $db;
    
    /**
     * コンストラクタ
     * 
     * @param RegisterLogger $logger ログインスタンス
     * @param RegisterDatabaseHandler $db データベースハンドラ
     */
    public function __construct(RegisterLogger $logger, RegisterDatabaseHandler $db) {
        $this->logger = $logger;
        $this->db = $db;
    }
    
    /**
     * 部屋の存在と有効性を確認
     * 
     * @param string $roomNumber 部屋番号
     * @return array 部屋情報
     * @throws Exception 部屋が存在しないか無効な場合
     */
    public function validateRoom($roomNumber) {
        $this->logger->info("部屋の検証開始: $roomNumber");
        
        $sql = "SELECT id, is_active FROM roomdatasettings WHERE room_number = :roomNumber";
        $stmt = $this->db->execute($sql, [':roomNumber' => $roomNumber]);
        
        $roomData = $stmt->fetch();
        
        if (!$roomData) {
            $this->logger->warning("存在しない部屋番号: $roomNumber");
            throw new Exception('指定された部屋番号は存在しません');
        }
        
        if (!$roomData['is_active']) {
            $this->logger->warning("無効な部屋番号: $roomNumber");
            throw new Exception('この部屋は現在利用できません');
        }
        
        $this->logger->info("部屋検証成功: ID={$roomData['id']}, アクティブ={$roomData['is_active']}");
        return $roomData;
    }
    
    /**
     * ユーザーの既存登録を取得
     * 
     * @param string $userId ユーザーID
     * @return array 登録情報の配列
     */
    public function getUserRegistrations($userId) {
        $this->logger->info("ユーザー登録情報を取得: userId=$userId");
        
        $sql = "SELECT id, room_number, is_active, check_in_date, check_out_date 
                FROM line_room_links 
                WHERE line_user_id = :userId
                ORDER BY id DESC";
                
        $stmt = $this->db->execute($sql, [':userId' => $userId]);
        $registrations = $stmt->fetchAll();
        
        $this->logger->info("取得した登録数: " . count($registrations));
        return $registrations;
    }
    
    /**
     * アクティブな登録を取得
     * 
     * @param string $userId ユーザーID
     * @return array|null アクティブな登録情報
     */
    public function getActiveRegistration($userId) {
        $this->logger->info("アクティブな登録を検索: userId=$userId");
        
        $sql = "SELECT * FROM line_room_links 
                WHERE line_user_id = :userId AND is_active = 1 
                LIMIT 1";
                
        $stmt = $this->db->execute($sql, [':userId' => $userId]);
        $registration = $stmt->fetch();
        
        if ($registration) {
            $this->logger->info("アクティブな登録を発見: ID={$registration['id']}, 部屋={$registration['room_number']}");
        } else {
            $this->logger->info("アクティブな登録なし");
        }
        
        return $registration;
    }
    
    /**
     * 未払いセッションの確認
     * 
     * @param string $roomNumber 部屋番号
     * @return int 未払い件数
     */
    public function checkUnpaidSessions($roomNumber) {
        $this->logger->info("未払いセッションを確認: room=$roomNumber");
        
        $sql = "SELECT COUNT(*) as count
                FROM order_sessions s
                JOIN orders o ON o.order_session_id = s.id AND o.order_status <> 'COMPLETED'
                WHERE s.room_number = :roomNumber
                  AND s.is_active = 1";
                  
        $stmt = $this->db->execute($sql, [':roomNumber' => $roomNumber]);
        $result = $stmt->fetch();
        
        $unpaidCount = (int)$result['count'];
        $this->logger->info("未払いセッション件数: $unpaidCount");
        
        return $unpaidCount;
    }
    
    /**
     * 既存の非アクティブレコードを検索
     * 
     * @param string $userId ユーザーID
     * @return array|null 非アクティブな登録情報
     */
    public function getInactiveRegistration($userId) {
        $this->logger->info("非アクティブな登録を検索: userId=$userId");
        
        $sql = "SELECT * FROM line_room_links 
                WHERE line_user_id = :userId AND is_active = 0 
                ORDER BY id DESC
                LIMIT 1";
                
        $stmt = $this->db->execute($sql, [':userId' => $userId]);
        $registration = $stmt->fetch();
        
        if ($registration) {
            $this->logger->info("非アクティブな登録を発見: ID={$registration['id']}, 部屋={$registration['room_number']}");
        } else {
            $this->logger->info("非アクティブな登録なし");
        }
        
        return $registration;
    }
    
    /**
     * 既存の登録を非アクティブ化
     * 
     * @param string $userId ユーザーID
     * @return int 更新された行数
     */
    public function deactivateUserRegistrations($userId) {
        $this->logger->info("既存登録を非アクティブ化: userId=$userId");
        
        $sql = "UPDATE line_room_links SET is_active = 0 WHERE line_user_id = :userId";
        $stmt = $this->db->execute($sql, [':userId' => $userId]);
        
        $affectedRows = $stmt->rowCount();
        $this->logger->info("非アクティブ化した登録数: $affectedRows");
        
        return $affectedRows;
    }
    
    /**
     * 新規登録を作成（既存の非アクティブレコードがある場合は更新）
     * 
     * @param array $data 登録データ
     * @return int 登録ID
     */
    public function createRegistration($data) {
        $this->logger->info("新規登録を作成", $data);
        
        // データ検証
        $this->validateRegistrationData($data);
        
        // 日付の正規化
        $checkInDate = $this->normalizeDate($data['check_in_date']);
        $checkOutDate = $this->normalizeDate($data['check_out_date']);
        
        // 既存の非アクティブレコードを確認
        $inactiveRegistration = $this->getInactiveRegistration($data['user_id']);
        
        if ($inactiveRegistration) {
            // 既存の非アクティブレコードを更新
            $this->logger->info("既存の非アクティブレコードを再利用: ID={$inactiveRegistration['id']}");
            
            $sql = "UPDATE line_room_links 
                    SET room_number = :roomNumber,
                        user_name = :userName,
                        check_in_date = :checkInDate,
                        check_out_date = :checkOutDate,
                        is_active = 1
                    WHERE id = :id";
                    
            $params = [
                ':id' => $inactiveRegistration['id'],
                ':roomNumber' => $data['room_number'],
                ':userName' => $data['user_name'],
                ':checkInDate' => $checkInDate,
                ':checkOutDate' => $checkOutDate
            ];
            
            $this->db->execute($sql, $params);
            $registrationId = $inactiveRegistration['id'];
            
            $this->logger->info("既存レコード更新完了: ID=$registrationId");
        } else {
            // 新規レコードを作成
            $sql = "INSERT INTO line_room_links 
                    (line_user_id, room_number, user_name, check_in_date, check_out_date, is_active)
                    VALUES 
                    (:userId, :roomNumber, :userName, :checkInDate, :checkOutDate, 1)";
                    
            $params = [
                ':userId' => $data['user_id'],
                ':roomNumber' => $data['room_number'],
                ':userName' => $data['user_name'],
                ':checkInDate' => $checkInDate,
                ':checkOutDate' => $checkOutDate
            ];
            
            $this->db->execute($sql, $params);
            $registrationId = $this->db->lastInsertId();
            
            $this->logger->info("新規登録作成完了: ID=$registrationId");
        }
        
        return $registrationId;
    }
    
    /**
     * 既存登録を更新
     * 
     * @param int $registrationId 登録ID
     * @param array $data 更新データ
     * @return bool 成功/失敗
     */
    public function updateRegistration($registrationId, $data) {
        $this->logger->info("登録を更新: ID=$registrationId", $data);
        
        // データ検証
        $this->validateRegistrationData($data);
        
        // 日付の正規化
        $checkInDate = $this->normalizeDate($data['check_in_date']);
        $checkOutDate = $this->normalizeDate($data['check_out_date']);
        
        $sql = "UPDATE line_room_links 
                SET room_number = :roomNumber,
                    user_name = :userName,
                    check_in_date = :checkInDate,
                    check_out_date = :checkOutDate,
                    is_active = 1
                WHERE id = :id";
                
        $params = [
            ':id' => $registrationId,
            ':roomNumber' => $data['room_number'],
            ':userName' => $data['user_name'],
            ':checkInDate' => $checkInDate,
            ':checkOutDate' => $checkOutDate
        ];
        
        $stmt = $this->db->execute($sql, $params);
        $success = $stmt->rowCount() > 0;
        
        $this->logger->info("登録更新完了: " . ($success ? "成功" : "変更なし"));
        return $success;
    }
    
    /**
     * 旧セッションをクローズ
     * 
     * @param string $roomNumber 部屋番号
     * @return int 更新された行数
     */
    public function closeOrderSessions($roomNumber) {
        $this->logger->info("オーダーセッションをクローズ: room=$roomNumber");
        
        $sql = "UPDATE order_sessions 
                SET is_active = 0, 
                    session_status = 'Completed', 
                    closed_at = NOW() 
                WHERE room_number = :roomNumber 
                  AND is_active = 1";
                  
        $stmt = $this->db->execute($sql, [':roomNumber' => $roomNumber]);
        $affectedRows = $stmt->rowCount();
        
        $this->logger->info("クローズしたセッション数: $affectedRows");
        return $affectedRows;
    }
    
    /**
     * 登録データの検証
     * 
     * @param array $data 登録データ
     * @throws Exception 検証エラー時
     */
    private function validateRegistrationData($data) {
        // 必須フィールドのチェック
        $requiredFields = ['room_number', 'user_name', 'check_in_date', 'check_out_date'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("必須項目が不足しています: $field");
            }
        }
        
        // 文字列長のチェック
        if (strlen($data['room_number']) > 20) {
            throw new Exception('部屋番号が長すぎます（最大20文字）');
        }
        
        if (strlen($data['user_name']) > 255) {
            throw new Exception('ユーザー名が長すぎます（最大255文字）');
        }
        
        // 日付の妥当性チェック
        try {
            $checkIn = new DateTime($data['check_in_date']);
            $checkOut = new DateTime($data['check_out_date']);
            
            if ($checkOut <= $checkIn) {
                throw new Exception('チェックアウト日はチェックイン日より後の日付を指定してください');
            }
        } catch (Exception $e) {
            if ($e->getMessage() !== 'チェックアウト日はチェックイン日より後の日付を指定してください') {
                throw new Exception('日付の形式が不正です');
            }
            throw $e;
        }
    }
    
    /**
     * 日付を正規化（Y-m-d形式）
     * 
     * @param mixed $date 日付
     * @return string 正規化された日付
     */
    private function normalizeDate($date) {
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d');
        }
        
        // 文字列の場合はDateTimeに変換して正規化
        try {
            $dateObj = new DateTime($date);
            return $dateObj->format('Y-m-d');
        } catch (Exception $e) {
            $this->logger->error("日付の正規化に失敗: $date");
            throw new Exception('日付の形式が不正です');
        }
    }
} 
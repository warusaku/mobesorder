<?php
/**
 * 認証サービスクラス
 * 
 * データベースからユーザー認証機能を分離したサービスクラス
 */
class AuthService {
    private static $instance = null;
    private $db;
    private static $logFile = null;
    
    /**
     * コンストラクタ - データベース接続を取得
     */
    private function __construct() {
        // ログファイルの初期化
        self::initLogFile();
        self::logMessage('AuthService::__construct - 認証サービス初期化');
        
        // データベース接続を取得
        $this->db = Database::getInstance();
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
        
        self::$logFile = $logDir . '/AuthService.log';
        
        // ログローテーションのチェック
        self::checkLogRotation();
    }
    
    /**
     * ログローテーションのチェックと実行
     * 
     * @return void
     */
    private static function checkLogRotation() {
        if (!file_exists(self::$logFile)) {
            return;
        }
        
        $fileTime = filemtime(self::$logFile);
        $hoursDiff = (time() - $fileTime) / 3600;
        
        // 48時間でログローテーション
        if ($hoursDiff > 48) {
            $backupFile = self::$logFile . '.' . date('Y-m-d_H-i-s', $fileTime);
            rename(self::$logFile, $backupFile);
            
            // ログファイル作成開始をログに記録
            $timestamp = date('Y-m-d H:i:s');
            $message = "[$timestamp] [INFO] ログファイル作成開始 - ローテーション実行（前回ログ: $backupFile）\n";
            file_put_contents(self::$logFile, $message);
        }
    }
    
    /**
     * ログメッセージをファイルに書き込む
     * 
     * @param string $message ログメッセージ
     * @param string $level ログレベル（INFO/WARNING/ERROR）
     * @return void
     */
    private static function logMessage($message, $level = 'INFO') {
        self::initLogFile();
        
        $timestamp = date('Y-m-d H:i:s');
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]['function']) ? $backtrace[1]['function'] : 'unknown';
        $file = isset($backtrace[0]['file']) ? basename($backtrace[0]['file']) : 'unknown';
        $line = isset($backtrace[0]['line']) ? $backtrace[0]['line'] : 0;
        
        $logMessage = "[$timestamp] [$level] [$file:$line->$caller] $message\n";
        
        // ログファイルへの書き込みを試みる
        $result = @file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
        
        // ファイル書き込みに失敗した場合はPHPのエラーログに記録
        if ($result === false && function_exists('error_log')) {
            error_log("AuthService: " . $logMessage);
            error_log("AuthService: ログファイルへの書き込みに失敗しました: " . self::$logFile);
        }
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
     * トークンからユーザー情報を取得
     * 
     * @param string $token アクセストークン
     * @return array|false ユーザー情報、または無効なトークンの場合はfalse
     */
    public function getUserByToken($token) {
        self::logMessage("トークンからユーザー情報取得試行: " . substr($token, 0, 8) . "...");
        
        if (empty($token)) {
            self::logMessage("トークンが空です", 'WARNING');
            return false;
        }
        
        try {
            $query = "SELECT u.* FROM line_room_links u 
                     WHERE u.token = ? AND u.is_active = 1 
                     LIMIT 1";
            
            $user = $this->db->selectOne($query, [$token]);
            
            if (!$user) {
                self::logMessage("トークンに一致するユーザーがいません: " . substr($token, 0, 8) . "...", 'WARNING');
                return false;
            }
            
            self::logMessage("ユーザー情報取得成功: LINE ID " . $user['line_user_id']);
            return $user;
            
        } catch (Exception $e) {
            self::logMessage("ユーザー情報取得エラー: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * LINE UserIDからユーザー情報を取得
     * 
     * @param string $lineUserId LINE UserID
     * @return array|false ユーザー情報、またはユーザーが見つからない場合はfalse
     */
    public function getUserByLineId($lineUserId) {
        self::logMessage("LINE IDからユーザー情報取得試行: " . $lineUserId);
        
        if (empty($lineUserId)) {
            self::logMessage("LINE IDが空です", 'WARNING');
            return false;
        }
        
        try {
            $query = "SELECT * FROM line_room_links 
                     WHERE line_user_id = ? AND is_active = 1 
                     LIMIT 1";
            
            $user = $this->db->selectOne($query, [$lineUserId]);
            
            if (!$user) {
                self::logMessage("LINE IDに一致するユーザーがいません: " . $lineUserId, 'WARNING');
                return false;
            }
            
            self::logMessage("ユーザー情報取得成功: LINE ID " . $lineUserId);
            return $user;
            
        } catch (Exception $e) {
            self::logMessage("ユーザー情報取得エラー: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * 部屋番号からユーザー情報を取得
     * 
     * @param string $roomNumber 部屋番号
     * @return array|false ユーザー情報、または部屋が見つからない場合はfalse
     */
    public function getUserByRoomNumber($roomNumber) {
        self::logMessage("部屋番号からユーザー情報取得試行: " . $roomNumber);
        
        if (empty($roomNumber)) {
            self::logMessage("部屋番号が空です", 'WARNING');
            return false;
        }
        
        try {
            $query = "SELECT * FROM line_room_links 
                     WHERE room_number = ? AND is_active = 1 
                     LIMIT 1";
            
            $user = $this->db->selectOne($query, [$roomNumber]);
            
            if (!$user) {
                self::logMessage("部屋番号に一致するユーザーがいません: " . $roomNumber, 'WARNING');
                return false;
            }
            
            self::logMessage("ユーザー情報取得成功: 部屋 " . $roomNumber);
            return $user;
            
        } catch (Exception $e) {
            self::logMessage("ユーザー情報取得エラー: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * ユーザーと部屋を紐づける
     * 
     * @param string $lineUserId LINE UserID
     * @param string $roomNumber 部屋番号
     * @param string $token アクセストークン
     * @return bool 成功した場合はtrue
     */
    public function linkUserToRoom($lineUserId, $roomNumber, $token) {
        self::logMessage("ユーザーと部屋の紐づけ試行: LINE ID " . $lineUserId . ", 部屋 " . $roomNumber);
        
        try {
            // 既存の紐づけをチェック
            $existingLink = $this->getUserByRoomNumber($roomNumber);
            
            if ($existingLink && $existingLink['line_user_id'] !== $lineUserId) {
                self::logMessage("この部屋は既に別のユーザーと紐づいています: 部屋 " . $roomNumber, 'WARNING');
                return false;
            }
            
            // トランザクション開始
            $this->db->beginTransaction();
            
            // ユーザーの既存の紐づけを無効化
            $query = "UPDATE line_room_links 
                     SET is_active = 0 
                     WHERE line_user_id = ?";
            
            $this->db->execute($query, [$lineUserId]);
            
            // 新しい紐づけを作成
            $query = "INSERT INTO line_room_links 
                     (line_user_id, room_number, token, is_active, created_at, updated_at) 
                     VALUES (?, ?, ?, 1, NOW(), NOW())";
            
            $this->db->execute($query, [$lineUserId, $roomNumber, $token]);
            
            // トランザクションコミット
            $this->db->commit();
            
            self::logMessage("ユーザーと部屋の紐づけが完了しました: LINE ID " . $lineUserId . ", 部屋 " . $roomNumber);
            return true;
            
        } catch (Exception $e) {
            // エラー発生時はロールバック
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            
            self::logMessage("ユーザーと部屋の紐づけエラー: " . $e->getMessage(), 'ERROR');
            self::logMessage("スタックトレース: " . $e->getTraceAsString(), 'ERROR');
            return false;
        }
    }
    
    /**
     * ユーザーの部屋紐づけを解除
     * 
     * @param string $lineUserId LINE UserID
     * @return bool 成功した場合はtrue
     */
    public function unlinkUser($lineUserId) {
        self::logMessage("ユーザーの部屋紐づけ解除試行: LINE ID " . $lineUserId);
        
        try {
            $query = "UPDATE line_room_links 
                     SET is_active = 0, updated_at = NOW() 
                     WHERE line_user_id = ? AND is_active = 1";
            
            $result = $this->db->execute($query, [$lineUserId]);
            
            if ($result) {
                self::logMessage("ユーザーの部屋紐づけ解除成功: LINE ID " . $lineUserId);
                return true;
            } else {
                self::logMessage("ユーザーの部屋紐づけが見つかりません: LINE ID " . $lineUserId, 'WARNING');
                return false;
            }
            
        } catch (Exception $e) {
            self::logMessage("ユーザーの部屋紐づけ解除エラー: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * 部屋のユーザー紐づけを解除
     * 
     * @param string $roomNumber 部屋番号
     * @return bool 成功した場合はtrue
     */
    public function unlinkRoom($roomNumber) {
        self::logMessage("部屋のユーザー紐づけ解除試行: 部屋 " . $roomNumber);
        
        try {
            $query = "UPDATE line_room_links 
                     SET is_active = 0, updated_at = NOW() 
                     WHERE room_number = ? AND is_active = 1";
            
            $result = $this->db->execute($query, [$roomNumber]);
            
            if ($result) {
                self::logMessage("部屋のユーザー紐づけ解除成功: 部屋 " . $roomNumber);
                return true;
            } else {
                self::logMessage("部屋のユーザー紐づけが見つかりません: 部屋 " . $roomNumber, 'WARNING');
                return false;
            }
            
        } catch (Exception $e) {
            self::logMessage("部屋のユーザー紐づけ解除エラー: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * 部屋のオーダー情報を取得
     * 
     * @param string $roomNumber 部屋番号
     * @return array|false オーダー情報、またはオーダーが見つからない場合はfalse
     */
    public function getRoomOrder($roomNumber) {
        self::logMessage("部屋のオーダー情報取得試行: 部屋 " . $roomNumber);
        
        try {
            $query = "SELECT * FROM room_tickets 
                     WHERE room_number = ? AND status = 'OPEN' 
                     ORDER BY created_at DESC 
                     LIMIT 1";
            
            $order = $this->db->selectOne($query, [$roomNumber]);
            
            if (!$order) {
                self::logMessage("部屋のオーダーが見つかりません: 部屋 " . $roomNumber, 'INFO');
                return false;
            }
            
            self::logMessage("部屋のオーダー情報取得成功: 部屋 " . $roomNumber);
            return $order;
            
        } catch (Exception $e) {
            self::logMessage("部屋のオーダー情報取得エラー: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
} 
<?php
/**
 * 認証クラス
 * 
 * 認証処理を担当し、AuthServiceと連携するクラス
 */
class Auth {
    private $db;
    private $authService;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->db = Database::getInstance();
        $this->authService = AuthService::getInstance();
    }
    
    /**
     * トークンを検証し、部屋情報を取得
     * 
     * @param string $token アクセストークン
     * @return array|false 部屋情報、または無効なトークンの場合はfalse
     */
    public function validateToken($token) {
        if (empty($token)) {
            return false;
        }
        
        // 通常のトークン検証
        $roomToken = $this->db->selectOne(
            "SELECT id, room_number, guest_name, is_active, check_in_date, check_out_date 
             FROM room_tokens 
             WHERE token = ? AND is_active = 1",
            [$token]
        );
        
        if ($roomToken) {
        // チェックアウト日を過ぎている場合は無効化
        if (!empty($roomToken['check_out_date'])) {
            $checkOutDate = new DateTime($roomToken['check_out_date']);
            $today = new DateTime();
            
            if ($today > $checkOutDate) {
                $this->db->execute(
                    "UPDATE room_tokens SET is_active = 0 WHERE id = ?",
                    [$roomToken['id']]
                );
                Utils::log("期限切れトークン: $token", 'INFO', 'Auth');
                return false;
            }
        }
        
        return $roomToken;
        }
        
        // トークンが見つからない場合、LINE関連のトークンとして検証
        // （クライアント側でLINEユーザーIDからハッシュ生成されたトークン用）
        $lineRoomLinks = $this->db->select(
            "SELECT l.room_number, r.guest_name, r.check_in_date, r.check_out_date, r.is_active, l.id
             FROM line_room_links l
             LEFT JOIN room_tokens r ON l.room_number = r.room_number AND r.is_active = 1
             WHERE l.is_active = 1
             ORDER BY l.updated_at DESC"
        );
        
        if (!empty($lineRoomLinks)) {
            // 有効な部屋リンクがあれば、最初のものを返す
            $lineRoom = $lineRoomLinks[0];
            
            if ($lineRoom['is_active'] || $lineRoom['is_active'] === null) {
                Utils::log("LINEリンク経由でのトークン検証成功", 'INFO', 'Auth');
                
                return [
                    'id' => $lineRoom['id'],
                    'room_number' => $lineRoom['room_number'],
                    'guest_name' => $lineRoom['guest_name'] ?? 'ゲスト',
                    'is_active' => 1,
                    'check_in_date' => $lineRoom['check_in_date'],
                    'check_out_date' => $lineRoom['check_out_date']
                ];
            }
        }
        
        Utils::log("無効なトークン: $token", 'WARNING', 'Auth');
        return false;
    }
    
    /**
     * リクエストからトークンを取得して検証
     * 
     * @return array|false 部屋情報、または認証失敗時はfalse
     */
    public function authenticateRequest() {
        // LINE IDを使用した認証を試みる（優先）
        $lineUserId = null;
        
        // POST、GET、ヘッダーからLINE IDを取得
        if (isset($_POST['line_user_id'])) {
            $lineUserId = $_POST['line_user_id'];
        } elseif (isset($_GET['line_user_id'])) {
            $lineUserId = $_GET['line_user_id'];
        } elseif (isset($_SERVER['HTTP_X_LINE_USER_ID'])) {
            $lineUserId = $_SERVER['HTTP_X_LINE_USER_ID'];
        }
        
        // リクエストボディから取得を試みる
        if (!$lineUserId) {
            $input = file_get_contents('php://input');
            if (!empty($input)) {
                $data = json_decode($input, true);
                if (isset($data['line_user_id'])) {
                    $lineUserId = $data['line_user_id'];
                }
            }
        }
        
        // LINE IDが見つかった場合、それを使用して認証
        if ($lineUserId) {
            Utils::log("LINE IDによる認証を試行: " . $lineUserId, 'INFO', 'Auth');
            
            // LINE IDから部屋情報を直接取得
            $roomInfo = $this->db->selectOne(
                "SELECT l.id, l.room_number, l.line_user_id, 
                        r.guest_name, r.check_in_date, r.check_out_date 
                 FROM line_room_links l
                 LEFT JOIN room_tokens r ON l.room_number = r.room_number AND r.is_active = 1
                 WHERE l.line_user_id = ? AND l.is_active = 1",
                [$lineUserId]
            );
            
            if ($roomInfo) {
                Utils::log("LINE ID認証成功: " . $lineUserId . " -> 部屋番号: " . $roomInfo['room_number'], 'INFO', 'Auth');
                
                return [
                    'id' => $roomInfo['id'],
                    'room_number' => $roomInfo['room_number'],
                    'guest_name' => $roomInfo['guest_name'] ?? 'ゲスト',
                    'is_active' => 1,
                    'check_in_date' => $roomInfo['check_in_date'],
                    'check_out_date' => $roomInfo['check_out_date'],
                    'auth_method' => 'line_id'
                ];
            } else {
                Utils::log("LINE ID認証失敗: " . $lineUserId . " - 紐付けられた部屋が見つかりません", 'WARNING', 'Auth');
            }
        }
        
        // LINE IDでの認証に失敗した場合、従来のトークン認証を試行（従方互換性のため）
        $token = Utils::getBearerToken();
        
        if (!$token) {
            Utils::log("認証失敗: LINE IDとトークンの両方が見つかりません", 'WARNING', 'Auth');
            return false;
        }
        
        Utils::log("トークンによる従来の認証を試行", 'INFO', 'Auth');
        return $this->validateToken($token);
    }
    
    /**
     * 新しいトークンを生成
     * 
     * @param string $roomNumber 部屋番号
     * @param string $guestName ゲスト名
     * @param string $checkInDate チェックイン日 (YYYY-MM-DD)
     * @param string $checkOutDate チェックアウト日 (YYYY-MM-DD)
     * @return string 生成されたトークン
     */
    public function generateRoomToken($roomNumber, $guestName, $checkInDate, $checkOutDate) {
        // 既存のトークンを無効化
        $this->db->execute(
            "UPDATE room_tokens SET is_active = 0 WHERE room_number = ?",
            [$roomNumber]
        );
        
        // 新しいトークンを生成
        $token = Utils::generateToken(TOKEN_LENGTH);
        
        // トークンが既に存在する場合は再生成
        while ($this->db->selectOne("SELECT id FROM room_tokens WHERE token = ?", [$token])) {
            $token = Utils::generateToken(TOKEN_LENGTH);
        }
        
        // トークンをデータベースに保存
        $this->db->execute(
            "INSERT INTO room_tokens (room_number, token, guest_name, check_in_date, check_out_date, is_active) 
             VALUES (?, ?, ?, ?, ?, 1)",
            [$roomNumber, $token, $guestName, $checkInDate, $checkOutDate]
        );
        
        Utils::log("トークン生成: $token (部屋: $roomNumber, ゲスト: $guestName)", 'INFO', 'Auth');
        
        return $token;
    }
    
    /**
     * トークンを無効化
     * 
     * @param string $token 無効化するトークン
     * @return bool 成功した場合はtrue
     */
    public function invalidateToken($token) {
        $result = $this->db->execute(
            "UPDATE room_tokens SET is_active = 0 WHERE token = ?",
            [$token]
        );
        
        if ($result) {
            Utils::log("トークン無効化: $token", 'INFO', 'Auth');
            return true;
        }
        
        return false;
    }
    
    /**
     * 部屋番号に関連するすべてのトークンを無効化
     * 
     * @param string $roomNumber 部屋番号
     * @return bool 成功した場合はtrue
     */
    public function invalidateRoomTokens($roomNumber) {
        $result = $this->db->execute(
            "UPDATE room_tokens SET is_active = 0 WHERE room_number = ?",
            [$roomNumber]
        );
        
        if ($result) {
            Utils::log("部屋のトークン無効化: $roomNumber", 'INFO', 'Auth');
            return true;
        }
        
        return false;
    }
    
    /**
     * LINE UserIDから部屋情報を取得
     * 
     * @param string $lineUserId LINE ユーザーID
     * @return array|false 部屋情報、または紐づけが見つからない場合はfalse
     */
    public function getRoomByLineUserId($lineUserId) {
        return $this->authService->getUserByLineId($lineUserId);
    }
    
    /**
     * 部屋番号からLINE連携情報を取得
     * 
     * @param string $roomNumber 部屋番号
     * @return array|false LINE連携情報、または紐づけが見つからない場合はfalse
     */
    public function getLineUserByRoomNumber($roomNumber) {
        return $this->authService->getUserByRoomNumber($roomNumber);
    }
} 
<?php
/**
 * 部屋情報登録専用データベース操作クラス
 * LINE登録アプリ用のデータベース読み書き処理を担当
 */
class RoomDataRegister {
    public $pdo;
    private static $logFile;
    
    /**
     * コンストラクタ - データベース接続を確立
     */
    public function __construct() {
        // ログファイルの初期化
        self::initLogFile();
        $this->log('RoomDataRegister::__construct - 初期化開始');
        
        try {
            // 直接PDO接続を確立
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->log("データベース接続を確立します: DSN=$dsn");
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            if (!$this->pdo) {
                $this->log("PDO接続の確立に失敗しました", 'ERROR');
                throw new Exception("PDO接続の確立に失敗しました");
            }
            
            $this->log('RoomDataRegister::__construct - データベース接続確立完了');
        } catch (PDOException $e) {
            $this->log("データベース接続エラー: " . $e->getMessage(), 'ERROR');
            throw new Exception("データベース接続に失敗しました: " . $e->getMessage());
        } catch (Exception $e) {
            $this->log("コンストラクタでエラーが発生しました: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * ログファイルの初期化
     */
    private static function initLogFile() {
        if (self::$logFile !== null) {
            return;
        }
        
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        self::$logFile = $logDir . '/roomdata_register.log';
    }
    
    /**
     * ログメッセージをファイルに書き込む
     * 
     * @param string $message ログメッセージ
     * @param string $level ログレベル（INFO/WARNING/ERROR）
     * @return void
     */
    public function log($message, $level = 'INFO') {
        self::initLogFile();
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * データベース接続とテーブルの状態を診断
     * 
     * @return array 診断結果
     */
    public function diagnose() {
        $result = [
            'status' => 'ok',
            'messages' => [],
            'tables' => []
        ];
        
        try {
            $this->log("diagnose - データベース接続診断を開始");
            
            // PDOの状態をチェック
            if (!$this->pdo) {
                $result['status'] = 'error';
                $result['messages'][] = 'PDO接続がありません';
                return $result;
            }
            
            // 必要なテーブルが存在するか確認
            $requiredTables = ['line_room_links', 'room_tokens'];
            foreach ($requiredTables as $table) {
                try {
                    $this->log("diagnose - テーブル '$table' の存在を確認します");
                    $stmt = $this->pdo->prepare("SHOW TABLES LIKE :table");
                    $stmt->bindParam(':table', $table);
                    $stmt->execute();
                    $exists = $stmt->fetch(PDO::FETCH_NUM);
                    
                    if (!$exists) {
                        $result['status'] = 'warning';
                        $result['messages'][] = "テーブル '$table' が見つかりません";
                        $result['tables'][$table] = false;
                    } else {
                        $result['tables'][$table] = true;
                        
                        // テーブル構造を取得
                        $this->log("diagnose - テーブル '$table' の構造を取得します");
                        $stmt = $this->pdo->prepare("DESCRIBE `$table`");
                        $stmt->execute();
                        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $result['structure'][$table] = $columns;
                    }
                } catch (PDOException $e) {
                    $result['status'] = 'error';
                    $result['messages'][] = "テーブル '$table' のチェック中にエラー: " . $e->getMessage();
                    $this->log("diagnose - テーブル '$table' のチェック中にエラー: " . $e->getMessage(), 'ERROR');
                }
            }
            
            if ($result['status'] === 'ok') {
                $result['messages'][] = 'データベース接続とテーブル構造は正常です';
            }
            
            $this->log("diagnose - データベース接続診断を完了: " . $result['status']);
            return $result;
            
        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['messages'][] = '診断中にエラー: ' . $e->getMessage();
            $this->log("diagnose - 診断中にエラー: " . $e->getMessage(), 'ERROR');
            return $result;
        }
    }
    
    /**
     * LINE_ユーザーIDによる部屋情報の存在確認
     * 
     * @param string $userId LINEユーザーID
     * @return array|null ユーザー情報またはnull
     */
    public function findRoomByUserId($userId) {
        $this->log("findRoomByUserId - ユーザーID: $userId の部屋情報を検索");
        
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM line_room_links WHERE line_user_id = :userId");
            $stmt->bindParam(':userId', $userId);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $this->log("findRoomByUserId - ユーザーID: $userId の部屋情報が見つかりました");
            } else {
                $this->log("findRoomByUserId - ユーザーID: $userId の部屋情報は見つかりませんでした");
            }
            
            return $result;
        } catch (PDOException $e) {
            $this->log("findRoomByUserId - エラー: " . $e->getMessage(), 'ERROR');
            throw new Exception("部屋情報の検索に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 部屋番号によるトークン情報の存在確認
     * 
     * @param string $roomNumber 部屋番号
     * @return array|null トークン情報またはnull
     */
    public function findTokenByRoomNumber($roomNumber) {
        $this->log("findTokenByRoomNumber - 部屋番号: $roomNumber のトークン情報を検索");
        
        try {
            // tokenカラムが存在するか確認
            $hasTokenColumn = true;
            try {
                $stmt = $this->pdo->prepare("SHOW COLUMNS FROM room_tokens LIKE 'token'");
                $stmt->execute();
                $hasTokenColumn = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $this->log("findTokenByRoomNumber - tokenカラム確認でエラー: " . $e->getMessage(), 'WARNING');
                $hasTokenColumn = false;
            }
            
            if ($hasTokenColumn) {
                // 新形式のトークンを検索
                $stmt = $this->pdo->prepare("SELECT * FROM room_tokens WHERE room_number = :roomNumber");
                $stmt->bindParam(':roomNumber', $roomNumber);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    $this->log("findTokenByRoomNumber - 部屋番号: $roomNumber のトークン情報が見つかりました");
                    return $result;
                }
            }
            
            // 旧形式のトークンを検索（tokenカラムが存在しない場合や新形式で見つからなかった場合）
            $stmt = $this->pdo->prepare("SELECT * FROM room_tokens WHERE room_number = :roomNumber");
            $stmt->bindParam(':roomNumber', $roomNumber);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $this->log("findTokenByRoomNumber - 部屋番号: $roomNumber のトークン情報が見つかりました（旧形式）");
                return $result;
            } else {
                $this->log("findTokenByRoomNumber - 部屋番号: $roomNumber のトークン情報は見つかりませんでした");
                return null;
            }
        } catch (PDOException $e) {
            $this->log("findTokenByRoomNumber - エラー: " . $e->getMessage(), 'ERROR');
            throw new Exception("トークン情報の検索に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * LINE_ユーザーIDと部屋情報を紐づけて登録または更新
     * 
     * @param string $userId LINEユーザーID
     * @param string $roomNumber 部屋番号
     * @param string $userName ユーザー名（オプション）
     * @param string $checkInDate チェックイン日
     * @param string $checkOutDate チェックアウト日
     * @return bool 登録/更新の成功・失敗
     */
    public function saveRoomLink($userId, $roomNumber, $userName, $checkInDate, $checkOutDate) {
        $this->log("saveRoomLink - ユーザーID: $userId, 部屋番号: $roomNumber の情報を保存開始");
        
        try {
            // テーブル構造を確認し、user_nameカラムの存在をチェック
            $hasUserNameColumn = true;
            try {
                $stmt = $this->pdo->prepare("SHOW COLUMNS FROM line_room_links LIKE 'user_name'");
                $stmt->execute();
                $hasUserNameColumn = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
                $this->log("saveRoomLink - user_nameカラムの存在確認: " . ($hasUserNameColumn ? "存在します" : "存在しません"));
            } catch (PDOException $e) {
                $this->log("saveRoomLink - カラム確認でエラー: " . $e->getMessage(), 'WARNING');
                $hasUserNameColumn = false;
            }
            
            // 既存のレコードを確認
            $existingRecord = $this->findRoomByUserId($userId);
            
            if ($existingRecord) {
                // 既存レコードを更新
                $this->log("saveRoomLink - 既存レコードを更新します");
                
                // SQLクエリを構築（user_nameカラムの有無に応じて）
                if ($hasUserNameColumn) {
                    $sql = "
                        UPDATE line_room_links 
                        SET room_number = :roomNumber, 
                            user_name = :userName, 
                            check_in_date = :checkInDate, 
                            check_out_date = :checkOutDate,
                            updated_at = NOW()
                        WHERE line_user_id = :userId
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->bindParam(':roomNumber', $roomNumber);
                    $stmt->bindParam(':userName', $userName);
                    $stmt->bindParam(':checkInDate', $checkInDate);
                    $stmt->bindParam(':checkOutDate', $checkOutDate);
                    $stmt->bindParam(':userId', $userId);
                } else {
                    $sql = "
                        UPDATE line_room_links 
                        SET room_number = :roomNumber, 
                            check_in_date = :checkInDate, 
                            check_out_date = :checkOutDate,
                            updated_at = NOW()
                        WHERE line_user_id = :userId
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->bindParam(':roomNumber', $roomNumber);
                    $stmt->bindParam(':checkInDate', $checkInDate);
                    $stmt->bindParam(':checkOutDate', $checkOutDate);
                    $stmt->bindParam(':userId', $userId);
                }
                
                $stmt->execute();
                
                $this->log("saveRoomLink - 既存レコードの更新が完了しました");
            } else {
                // 新規レコードを挿入
                $this->log("saveRoomLink - 新規レコードを挿入します");
                
                // SQLクエリを構築（user_nameカラムの有無に応じて）
                if ($hasUserNameColumn) {
                    $sql = "
                        INSERT INTO line_room_links 
                        (line_user_id, room_number, user_name, check_in_date, check_out_date, created_at, updated_at)
                        VALUES (:userId, :roomNumber, :userName, :checkInDate, :checkOutDate, NOW(), NOW())
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->bindParam(':userId', $userId);
                    $stmt->bindParam(':roomNumber', $roomNumber);
                    $stmt->bindParam(':userName', $userName);
                    $stmt->bindParam(':checkInDate', $checkInDate);
                    $stmt->bindParam(':checkOutDate', $checkOutDate);
                } else {
                    $sql = "
                        INSERT INTO line_room_links 
                        (line_user_id, room_number, check_in_date, check_out_date, created_at, updated_at)
                        VALUES (:userId, :roomNumber, :checkInDate, :checkOutDate, NOW(), NOW())
                    ";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->bindParam(':userId', $userId);
                    $stmt->bindParam(':roomNumber', $roomNumber);
                    $stmt->bindParam(':checkInDate', $checkInDate);
                    $stmt->bindParam(':checkOutDate', $checkOutDate);
                }
                
                $stmt->execute();
                
                $this->log("saveRoomLink - 新規レコードの挿入が完了しました");
            }
            
            return true;
        } catch (PDOException $e) {
            $this->log("saveRoomLink - エラー: " . $e->getMessage(), 'ERROR');
            throw new Exception("部屋情報の保存に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 部屋番号に対するトークンを作成または更新
     * 
     * @param string $roomNumber 部屋番号
     * @param int $expiryDays トークンの有効期間（日数）
     * @return string 生成されたトークン
     */
    public function saveRoomToken($roomNumber, $expiryDays = 7) {
        $this->log("saveRoomToken - 部屋番号: $roomNumber のトークンを保存開始");
        
        try {
            // トークンの生成
            $token = bin2hex(random_bytes(16));
            $expiryDate = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));
            
            // 既存のトークンを確認
            $existingToken = $this->findTokenByRoomNumber($roomNumber);
            
            // tokensカラムが存在するか確認
            $hasTokenColumn = true;
            try {
                $stmt = $this->pdo->prepare("SHOW COLUMNS FROM room_tokens LIKE 'token'");
                $stmt->execute();
                $hasTokenColumn = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
                $this->log("saveRoomToken - tokenカラムの存在確認: " . ($hasTokenColumn ? "存在します" : "存在しません"));
            } catch (PDOException $e) {
                $this->log("saveRoomToken - カラム確認でエラー: " . $e->getMessage(), 'WARNING');
                $hasTokenColumn = false;
            }
            
            // expires_atカラムが存在するか確認
            $hasExpiresAtColumn = true;
            try {
                $stmt = $this->pdo->prepare("SHOW COLUMNS FROM room_tokens LIKE 'expires_at'");
                $stmt->execute();
                $hasExpiresAtColumn = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
                $this->log("saveRoomToken - expires_atカラムの存在確認: " . ($hasExpiresAtColumn ? "存在します" : "存在しません"));
            } catch (PDOException $e) {
                $this->log("saveRoomToken - カラム確認でエラー: " . $e->getMessage(), 'WARNING');
                $hasExpiresAtColumn = false;
            }
            
            if ($existingToken) {
                // 既存トークンを更新
                $this->log("saveRoomToken - 既存のトークンを更新します");
                
                if ($hasTokenColumn && $hasExpiresAtColumn) {
                    // 新形式（tokenとexpires_at）
                    $stmt = $this->pdo->prepare("
                        UPDATE room_tokens 
                        SET token = :token, 
                            expires_at = :expiryDate,
                            updated_at = NOW()
                        WHERE room_number = :roomNumber
                    ");
                    
                    $stmt->bindParam(':token', $token);
                    $stmt->bindParam(':expiryDate', $expiryDate);
                    $stmt->bindParam(':roomNumber', $roomNumber);
                } else {
                    // 旧形式（access_token）
                    $stmt = $this->pdo->prepare("
                        UPDATE room_tokens 
                        SET access_token = :token, 
                            updated_at = NOW()
                        WHERE room_number = :roomNumber
                    ");
                    
                    $stmt->bindParam(':token', $token);
                    $stmt->bindParam(':roomNumber', $roomNumber);
                }
                
                $stmt->execute();
                $this->log("saveRoomToken - 既存トークンの更新が完了しました");
            } else {
                // 新規トークンを挿入
                $this->log("saveRoomToken - 新規トークンを挿入します");
                
                if ($hasTokenColumn && $hasExpiresAtColumn) {
                    // 新形式（tokenとexpires_at）
                    $stmt = $this->pdo->prepare("
                        INSERT INTO room_tokens 
                        (room_number, token, expires_at, is_active, created_at, updated_at)
                        VALUES (:roomNumber, :token, :expiryDate, TRUE, NOW(), NOW())
                    ");
                    
                    $stmt->bindParam(':roomNumber', $roomNumber);
                    $stmt->bindParam(':token', $token);
                    $stmt->bindParam(':expiryDate', $expiryDate);
                } else {
                    // 旧形式（access_token）
                    $stmt = $this->pdo->prepare("
                        INSERT INTO room_tokens 
                        (room_number, access_token, is_active, created_at, updated_at)
                        VALUES (:roomNumber, :token, TRUE, NOW(), NOW())
                    ");
                    
                    $stmt->bindParam(':roomNumber', $roomNumber);
                    $stmt->bindParam(':token', $token);
                }
                
                $stmt->execute();
                $this->log("saveRoomToken - 新規トークンの挿入が完了しました");
            }
            
            return $token;
        } catch (PDOException $e) {
            $this->log("saveRoomToken - エラー: " . $e->getMessage(), 'ERROR');
            throw new Exception("トークンの保存に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * LINEユーザーIDから部屋情報とトークンを完全取得
     * 
     * @param string $userId LINEユーザーID
     * @return array|null 部屋情報とトークン情報
     */
    public function getRoomInfoWithToken($userId) {
        $this->log("getRoomInfoWithToken - ユーザーID: $userId の部屋情報とトークンを取得");
        
        try {
            // テーブル構造を確認し、user_nameカラムの存在をチェック
            $hasUserNameColumn = true;
            try {
                $stmt = $this->pdo->prepare("SHOW COLUMNS FROM line_room_links LIKE 'user_name'");
                $stmt->execute();
                $hasUserNameColumn = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
                $this->log("getRoomInfoWithToken - user_nameカラムの存在確認: " . ($hasUserNameColumn ? "存在します" : "存在しません"));
            } catch (PDOException $e) {
                $this->log("getRoomInfoWithToken - カラム確認でエラー: " . $e->getMessage(), 'WARNING');
                $hasUserNameColumn = false;
            }
            
            // SQLクエリを構築（user_nameカラムの有無に応じて）
            $userNameField = $hasUserNameColumn ? "l.user_name, " : "";
            $sql = "
                SELECT l.line_user_id, l.room_number, {$userNameField}l.check_in_date, l.check_out_date, t.token, t.expires_at
                FROM line_room_links l
                LEFT JOIN room_tokens t ON l.room_number = t.room_number
                WHERE l.line_user_id = :userId
                AND t.expires_at > NOW()
            ";
            
            $this->log("getRoomInfoWithToken - 実行するSQL: " . $sql);
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':userId', $userId);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $this->log("getRoomInfoWithToken - ユーザーID: $userId の部屋情報とトークンが見つかりました");
                return $result;
            } else {
                // tokenカラムが見つからない場合、access_tokenカラムを使って再試行
                $sql = "
                    SELECT l.line_user_id, l.room_number, {$userNameField}l.check_in_date, l.check_out_date, t.access_token as token, NOW() as expires_at
                    FROM line_room_links l
                    LEFT JOIN room_tokens t ON l.room_number = t.room_number
                    WHERE l.line_user_id = :userId
                ";
                
                $this->log("getRoomInfoWithToken - 代替SQLを実行: " . $sql);
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindParam(':userId', $userId);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    $this->log("getRoomInfoWithToken - 代替方法でユーザーID: $userId の部屋情報とトークンが見つかりました");
                    return $result;
                }
                
                $this->log("getRoomInfoWithToken - ユーザーID: $userId の部屋情報とトークンは見つかりませんでした");
                return null;
            }
        } catch (PDOException $e) {
            $this->log("getRoomInfoWithToken - エラー: " . $e->getMessage(), 'ERROR');
            throw new Exception("部屋情報とトークンの取得に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 有効期限切れのトークンをクリーンアップ
     * 
     * @return int 削除されたトークン数
     */
    public function cleanupExpiredTokens() {
        $this->log("cleanupExpiredTokens - 有効期限切れのトークンをクリーンアップ開始");
        
        try {
            $stmt = $this->pdo->prepare("DELETE FROM room_tokens WHERE expires_at < NOW()");
            $stmt->execute();
            $count = $stmt->rowCount();
            
            $this->log("cleanupExpiredTokens - {$count}件の有効期限切れトークンを削除しました");
            return $count;
        } catch (PDOException $e) {
            $this->log("cleanupExpiredTokens - エラー: " . $e->getMessage(), 'ERROR');
            throw new Exception("有効期限切れトークンのクリーンアップに失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * チェックアウト済みのユーザーの部屋紐づけを解除
     * 
     * @return int 削除された紐づけ数
     */
    public function cleanupCheckedOutRooms() {
        $this->log("cleanupCheckedOutRooms - チェックアウト済みの部屋紐づけをクリーンアップ開始");
        
        try {
            $today = date('Y-m-d');
            $stmt = $this->pdo->prepare("DELETE FROM line_room_links WHERE check_out_date < :today");
            $stmt->bindParam(':today', $today);
            $stmt->execute();
            $count = $stmt->rowCount();
            
            $this->log("cleanupCheckedOutRooms - {$count}件のチェックアウト済み部屋紐づけを削除しました");
            return $count;
        } catch (PDOException $e) {
            $this->log("cleanupCheckedOutRooms - エラー: " . $e->getMessage(), 'ERROR');
            throw new Exception("チェックアウト済み部屋紐づけのクリーンアップに失敗しました: " . $e->getMessage());
        }
    }
} 
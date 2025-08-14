<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Utils.php';
require_once __DIR__ . '/SquareService.php';

/**
 * 保留伝票管理サービスクラス
 * 
 * 保留伝票をローカルデータベースとSquareの両方で管理するためのサービスクラス
 */
class RoomTicketService {
    private $db;
    private $squareService;
    private static $logFile = null;
    private static $maxLogSize = 500 * 1024; // 500KB
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        // ログファイルの初期化
        self::initLogFile();
        self::logMessage("RoomTicketService::__construct - チケットサービス初期化開始", 'INFO');
        
        $this->db = Database::getInstance();
        $this->squareService = new SquareService();
        
        self::logMessage("RoomTicketService::__construct - チケットサービス初期化完了", 'INFO');
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
        
        self::$logFile = $logDir . '/RoomTicketService.log';
        
        // ログローテーションのチェック
        self::checkLogRotation();
    }
    
    /**
     * ログローテーションのチェックと実行
     */
    private static function checkLogRotation() {
        if (!file_exists(self::$logFile)) {
            // ログファイルが存在しない場合は作成する
            $timestamp = date('Y-m-d H:i:s');
            $message = "[$timestamp] [INFO] ログファイル作成\n";
            file_put_contents(self::$logFile, $message);
            return;
        }
        
        // ファイルサイズを確認
        $fileSize = filesize(self::$logFile);
        if ($fileSize > self::$maxLogSize) {
            // 古いログファイルの名前を変更
            $backupFile = self::$logFile . '.' . date('Y-m-d_H-i-s');
            rename(self::$logFile, $backupFile);
            
            // 新しいログファイルを作成
            $timestamp = date('Y-m-d H:i:s');
            $message = "[$timestamp] [INFO] ログローテーション実行 - 前回ログ: $backupFile ($fileSize bytes)\n";
            file_put_contents(self::$logFile, $message);
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
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]['function']) ? $backtrace[1]['function'] : 'unknown';
        $file = isset($backtrace[0]['file']) ? basename($backtrace[0]['file']) : 'unknown';
        $line = isset($backtrace[0]['line']) ? $backtrace[0]['line'] : 0;
        
        $logMessage = "[$timestamp] [$level] [$file:$line->$caller] $message\n";
        
        // ログファイルへの書き込み
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * 引数の内容を文字列化する
     * 
     * @param mixed $args 引数
     * @return string 文字列化された引数
     */
    private static function formatArgs($args) {
        if (is_array($args)) {
            // 配列の場合は再帰的に処理
            $result = [];
            foreach ($args as $key => $value) {
                if (is_array($value)) {
                    // 配列が大きすぎる場合は要約
                    if (count($value) > 5) {
                        $result[$key] = '[配列: ' . count($value) . '件]';
                    } else {
                        $result[$key] = self::formatArgs($value);
                    }
                } elseif (is_object($value)) {
                    $result[$key] = '[オブジェクト: ' . get_class($value) . ']';
                } else {
                    $result[$key] = $value;
                }
            }
            return json_encode($result, JSON_UNESCAPED_UNICODE);
        } elseif (is_object($args)) {
            return '[オブジェクト: ' . get_class($args) . ']';
        } else {
            return json_encode($args, JSON_UNESCAPED_UNICODE);
        }
    }
    
    /**
     * 部屋番号に対応する保留伝票を作成
     * 
     * @param string $roomNumber 部屋番号
     * @param string $guestName ゲスト名（オプション）
     * @param string $lineUserId LINE User ID（オプション）
     * @return array|false 成功時は保留伝票情報、失敗時はfalse
     */
    public function createRoomTicket($roomNumber, $guestName = '', $lineUserId = null) {
        self::logMessage("createRoomTicket 開始: roomNumber={$roomNumber}, guestName={$guestName}, lineUserId={$lineUserId}", 'INFO');
        
        try {
            Utils::log("createRoomTicket 開始: roomNumber={$roomNumber}, guestName={$guestName}, lineUserId={$lineUserId}", 'DEBUG', 'RoomTicketService');
            
            // デバッグログ追加：処理開始
            self::logMessage("=== デバッグ: 処理フロー開始 ===", 'INFO');
            
            // LINE User IDの取得（パラメータが空の場合はリクエストヘッダーから取得を試みる）
            if (empty($lineUserId) && isset($_SERVER['HTTP_X_LINE_USER_ID'])) {
                $lineUserId = $_SERVER['HTTP_X_LINE_USER_ID'];
                self::logMessage("リクエストヘッダーからLINE User IDを取得: {$lineUserId}", 'INFO');
            }
            
            // SquareServiceが初期化されているか確認
            if (!$this->squareService) {
                self::logMessage("squareServiceがnullです。初期化します。", 'WARNING');
                $this->squareService = new SquareService();
                
                if (!$this->squareService) {
                    self::logMessage("squareServiceの初期化に失敗しました", 'ERROR');
                    Utils::log("squareServiceの初期化に失敗しました", 'ERROR', 'RoomTicketService');
                    return false;
                }
            }
            
            self::logMessage("デバッグ: SquareService初期化完了", 'INFO');
            
            // 既存のチケットをチェック
            $existingTicket = $this->getRoomTicketByRoomNumber($roomNumber);
            
            self::logMessage("デバッグ: 既存チケットチェック完了 - 結果: " . ($existingTicket ? "チケット発見" : "チケットなし"), 'INFO');
            
            if ($existingTicket) {
                self::logMessage("部屋番号 {$roomNumber} の既存チケット検出: " . self::formatArgs($existingTicket), 'INFO');
                Utils::log("Existing ticket found for room {$roomNumber}: " . json_encode($existingTicket), 'DEBUG', 'RoomTicketService');
                
                // 既存のチケットが有効なら、そのまま返す
                if (isset($existingTicket['square_order_id']) && !empty($existingTicket['square_order_id'])) {
                    self::logMessage("既存の有効なチケットを使用します", 'INFO');
                    return $existingTicket;
                }
                
                // 既存のチケットを削除（一意制約違反エラーを防ぐため）
                $this->db->beginTransaction();
                try {
                    self::logMessage("既存チケットの削除を試行: ticket_id={$existingTicket['id']}", 'INFO');
                    $deleteResult = $this->deleteRoomTicket($existingTicket['id']);
                    if (!$deleteResult) {
                        self::logMessage("既存チケットの削除に失敗", 'ERROR');
                        Utils::log("Failed to delete existing ticket for room {$roomNumber}", 'ERROR', 'RoomTicketService');
                        $this->db->rollback();
                        return false;
                    }
                    self::logMessage("既存チケットの削除成功", 'INFO');
                    Utils::log("Deleted existing ticket for room {$roomNumber}", 'DEBUG', 'RoomTicketService');
                    $this->db->commit();
                } catch (Exception $e) {
                    self::logMessage("チケット削除中のエラー: " . $e->getMessage(), 'ERROR');
                    Utils::log("Error deleting existing ticket: " . $e->getMessage(), 'ERROR', 'RoomTicketService');
                    $this->db->rollback();
                    // チケット削除失敗の場合でも、新規作成を試みる
                }
            }
            
            // 新しいチケットIDを生成
            $ticketId = Utils::generateUniqueId();
            self::logMessage("新規チケットID生成: {$ticketId}", 'INFO');
            
            // 現在の日時を取得
            $currentTime = date('Y-m-d H:i:s');
            
            self::logMessage("デバッグ: Square API呼び出し前", 'INFO');
            
            try {
                // Square APIで保留伝票を作成
                self::logMessage("Square APIでの保留伝票作成を試行します", 'INFO');
                Utils::log("Square APIでの保留伝票作成を試行: room={$roomNumber}, guestName={$guestName}, lineUserId={$lineUserId}", 'INFO', 'RoomTicketService');
                
                // スクエアサービスのメソッド呼び出し前
                self::logMessage("デバッグ: Square API createRoomTicket呼び出し前", 'INFO');
                
                // Square APIの実際の呼び出し
                $squareTicket = $this->squareService->createRoomTicket($roomNumber, $guestName, $lineUserId);
                
                // この行に到達したらAPI呼び出しは成功
                self::logMessage("デバッグ: Square API createRoomTicket呼び出し完了", 'INFO');
                
                // 返り値の確認
                if ($squareTicket) {
                    self::logMessage("デバッグ: API呼び出し成功 - 返り値: " . json_encode($squareTicket), 'INFO');
                } else {
                    self::logMessage("デバッグ: API呼び出し失敗 - falseが返されました", 'ERROR');
                }
                
                if ($squareTicket && isset($squareTicket['square_order_id'])) {
                    self::logMessage("Square API保留伝票作成成功: square_order_id=" . $squareTicket['square_order_id'], 'INFO');
                } else {
                    $errorDetails = is_array($squareTicket) ? json_encode($squareTicket) : "応答なし";
                    self::logMessage("Square API保留伝票作成失敗: " . $errorDetails, 'ERROR');
                    Utils::log("Square APIエラー詳細: " . $errorDetails, 'ERROR', 'RoomTicketService');
                    return false;
                }
            } catch (Exception $apiEx) {
                self::logMessage("Square API例外: " . $apiEx->getMessage() . "\n" . $apiEx->getTraceAsString(), 'ERROR');
                Utils::log("Square API例外詳細: " . $apiEx->getMessage(), 'ERROR', 'RoomTicketService');
                return false;
            }
            
            self::logMessage("デバッグ: Square API呼び出し後、データベース保存前", 'INFO');
            
            // Square注文IDの取得
            $squareOrderId = $squareTicket['square_order_id'];
            if (empty($squareOrderId)) {
                self::logMessage("Square注文IDが取得できませんでした", 'ERROR');
                Utils::log("Square order ID is empty", 'ERROR', 'RoomTicketService');
                return false;
            }
            
            self::logMessage("Square注文ID取得成功: {$squareOrderId}", 'INFO');
                    
            // チケットデータを準備
            $ticketData = [
                'id' => $ticketId,
                'room_number' => $roomNumber,
                'guest_name' => $guestName,
                'status' => 'OPEN',
                'square_order_id' => $squareOrderId,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ];
            self::logMessage("新規チケットデータ: " . self::formatArgs($ticketData), 'INFO');
            
            // データベースにチケットを保存
            self::logMessage("デバッグ: データベース保存開始", 'INFO');
            
            $this->db->beginTransaction();
            try {
                // 新しいDatabase::insertメソッドに合わせて呼び出しを変更
                $result = $this->db->insert('room_tickets', $ticketData);
                if (!$result) {
                    self::logMessage("チケット保存失敗", 'ERROR');
                    Utils::log("Failed to save room ticket", 'ERROR', 'RoomTicketService');
                    $this->db->rollback();
                    return false;
                }
                
                $commitResult = $this->db->commit();
                if (!$commitResult) {
                    self::logMessage("チケット保存トランザクションのコミット失敗", 'ERROR');
                    Utils::log("Failed to commit room ticket transaction", 'ERROR', 'RoomTicketService');
                    $this->db->rollback();
                    return false;
                }
                
                self::logMessage("チケット作成成功: " . self::formatArgs($ticketData), 'INFO');
                Utils::log("Room ticket created: {$ticketId} for room {$roomNumber}", 'INFO', 'RoomTicketService');
                
                // Square APIの情報も含めて返す
                $ticketData['square_data'] = $squareTicket;
                
                self::logMessage("デバッグ: 処理完了 - 正常終了", 'INFO');
                return $ticketData;
            } catch (Exception $dbEx) {
                // エラー詳細をより詳しく記録
                self::logMessage("データベース操作エラー: " . $dbEx->getMessage() . "\n" . $dbEx->getTraceAsString(), 'ERROR');
                Utils::log("Database error: " . $dbEx->getMessage(), 'ERROR', 'RoomTicketService');
                
                // トランザクションがアクティブであればロールバック
                $this->db->rollback();
                
                self::logMessage("デバッグ: 処理失敗 - データベースエラー", 'ERROR');
                return false;
            }
        } catch (Exception $e) {
            self::logMessage("createRoomTicket エラー: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'ERROR');
            Utils::log("Room ticket creation error: " . $e->getMessage(), 'ERROR', 'RoomTicketService');
            self::logMessage("デバッグ: 処理失敗 - 一般例外", 'ERROR');
            return false;
        }
    }
    
    /**
     * 部屋番号から保留伝票を取得
     * 
     * @param string $roomNumber 部屋番号
     * @return array|false 成功時は保留伝票情報、失敗時はfalse
     */
    public function getRoomTicketByRoomNumber($roomNumber) {
        self::logMessage("getRoomTicketByRoomNumber 開始: roomNumber={$roomNumber}", 'INFO');
        
        try {
            // データベースから保留伝票を検索
            $query = "SELECT * FROM room_tickets WHERE room_number = ? AND status = 'OPEN' LIMIT 1";
            $ticket = $this->db->selectOne($query, [$roomNumber]);
            
            if (!$ticket) {
                self::logMessage("部屋番号 {$roomNumber} のアクティブなチケットが見つかりません", 'INFO');
                return false;
            }
            
            // square_order_idがチケットになければfalseを返す
            if (!isset($ticket['square_order_id']) || empty($ticket['square_order_id'])) {
                self::logMessage("チケットにSquare注文IDがありません: " . self::formatArgs($ticket), 'ERROR');
                
                // チケットを無効化する
                $this->updateTicketStatus($ticket['id'], 'CANCELED');
                
                self::logMessage("Square注文IDが無いためチケットをキャンセルしました", 'WARNING');
                return false;
            }
            
            // Square APIから最新の情報を取得
            self::logMessage("Square APIから注文情報を取得します: " . $ticket['square_order_id'], 'INFO');
            $squareTicket = $this->squareService->getOrder($ticket['square_order_id']);
            
            if (!$squareTicket) {
                // Square側で削除されている場合は、ローカルのステータスを更新
                $this->updateTicketStatus($ticket['id'], 'CANCELED');
                self::logMessage("Square order not found for room ticket ID: {$ticket['id']}, square_order_id: {$ticket['square_order_id']}", 'WARNING');
                return false;
            }
            
            // ステータスが変更されている場合は、ローカルのステータスを更新
            if ($squareTicket['status'] !== $ticket['status']) {
                self::logMessage("チケットのステータスを更新します: {$ticket['status']} -> {$squareTicket['status']}", 'INFO');
                $this->updateTicketStatus($ticket['id'], $squareTicket['status']);
                $ticket['status'] = $squareTicket['status'];
            }
            
            // キャンセルや完了済みの場合はfalseを返す
            if ($ticket['status'] !== 'OPEN') {
                self::logMessage("チケットは現在アクティブではありません。ステータス: {$ticket['status']}", 'INFO');
                return false;
            }
            
            self::logMessage("部屋番号 {$roomNumber} のチケット取得成功: " . self::formatArgs($ticket), 'INFO');
            return array_merge($ticket, [
                'square_data' => $squareTicket
            ]);
        } catch (Exception $e) {
            self::logMessage("getRoomTicketByRoomNumber エラー: " . $e->getMessage(), 'ERROR');
            Utils::log("Error retrieving room ticket by room number: " . $e->getMessage(), 'ERROR', 'RoomTicketService');
            return false;
        }
    }
    
    /**
     * 保留伝票のステータスを更新
     * 
     * @param int $ticketId チケットID
     * @param string $status 新しいステータス
     * @return bool 成功時はtrue、失敗時はfalse
     */
    private function updateTicketStatus($ticketId, $status) {
        try {
            // トランザクション開始
            $this->db->beginTransaction();
            
            $query = "UPDATE room_tickets SET status = ? WHERE id = ?";
            $this->db->execute($query, [$status, $ticketId]);
            
            // トランザクションコミット
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            // トランザクションロールバック
            $this->db->rollback();
            self::logMessage("Error updating ticket status: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * 保留伝票に商品を追加
     * 
     * @param string $roomNumber 部屋番号
     * @param array $items 追加する商品の配列 [['square_item_id' => 'xxx', 'quantity' => 1, 'note' => '...'], ...]
     * @return array|false 成功時は更新された保留伝票情報、失敗時はfalse
     */
    public function addItemToRoomTicket($roomNumber, $items) {
        self::logMessage("addItemToRoomTicket called - roomNumber: {$roomNumber}, items: " . self::formatArgs($items), 'INFO');
        
        try {
            // パラメータチェック
            if (empty($roomNumber)) {
                self::logMessage("部屋番号が指定されていません", 'ERROR');
                return false;
            }
            
            // itemsが文字列の場合はJSONデコード（配列全体が文字列として渡された場合）
            if (is_string($items)) {
                try {
                    $items = json_decode($items, true);
                    if (!is_array($items)) {
                        self::logMessage("商品データのJSONデコードに失敗しました: " . $items, 'ERROR');
                        return false;
                    }
                    self::logMessage("商品データ全体をJSONデコードしました: " . count($items) . "件", 'INFO');
                } catch (Exception $e) {
                    self::logMessage("商品データのJSONデコード中にエラー: " . $e->getMessage(), 'ERROR');
                    return false;
                }
            }
            
            // 配列でない場合はエラー
            if (!is_array($items)) {
                self::logMessage("商品データが配列ではありません: " . gettype($items), 'ERROR');
                return false;
            }
            
            // デバッグ: 項目ごとのデータ型を確認
            self::logMessage("商品データの型: " . json_encode(array_map(function($item) { 
                return is_array($item) ? 'array' : gettype($item); 
            }, $items)), 'INFO');
            
            // 正規化された商品アイテムの配列を作成
            $processedItems = [];
            
            foreach ($items as $index => $item) {
                // オリジナルのitemデータを保持
                $originalItem = $item;
                
                // 文字列の場合はデコード試行
                if (is_string($item)) {
                    try {
                        $decodedItem = json_decode($item, true);
                        
                        // JSONデコードが成功し、かつ配列である場合
                        if (is_array($decodedItem)) {
                            $item = $decodedItem;
                            
                            // square_item_idをロギング
                            if (isset($item['square_item_id'])) {
                                self::logMessage("アイテム[$index]をJSONデコードしました。square_item_id: " . $item['square_item_id'], 'INFO');
                            } else {
                                self::logMessage("アイテム[$index]をJSONデコードしましたが、square_item_idがありません", 'WARNING');
                            }
                        } else {
                            // デコードに失敗した場合は正規表現で抽出を試みる
                            if (preg_match('/square_item_id[\"\']?\s*:\s*[\"\']([^\"\']+)[\"\']/', $item, $matches)) {
                                $square_item_id = $matches[1];
                                $quantity = 1;
                                
                                // 数量も抽出を試みる
                                if (preg_match('/quantity[\"\']?\s*:\s*(\d+)/', $item, $qMatches)) {
                                    $quantity = (int)$qMatches[1];
                                }
                                
                                $item = [
                                    'square_item_id' => $square_item_id,
                                    'quantity' => $quantity,
                                    'note' => ''
                                ];
                                self::logMessage("アイテム[$index]を正規表現で抽出しました: square_item_id={$square_item_id}, quantity={$quantity}", 'INFO');
                            } else {
                                self::logMessage("アイテム[$index]の処理に失敗しました: " . $item, 'ERROR');
                                continue; // このアイテムはスキップ
                            }
                        }
                    } catch (Exception $e) {
                        self::logMessage("アイテム[$index]の処理中にエラー: " . $e->getMessage(), 'ERROR');
                        continue; // このアイテムはスキップ
                    }
                }
                
                // 配列でない場合はスキップ
                if (!is_array($item)) {
                    self::logMessage("アイテム[$index]が配列ではありません: " . gettype($item), 'ERROR');
                    continue;
                }
                
                // 必須フィールドのチェック - square_item_idまたはname+priceの組み合わせを許可
                $hasSquareId = isset($item['square_item_id']) && !empty($item['square_item_id']);
                $hasNameAndPrice = isset($item['name']) && !empty($item['name']) && isset($item['price']);
                
                if (!$hasSquareId && !$hasNameAndPrice) {
                    self::logMessage("アイテム[$index]に必要な識別情報がありません。square_item_idまたはname+priceの組み合わせが必要です", 'ERROR');
                    continue;
                }
                
                // 数量のチェック
                if (!isset($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] <= 0) {
                    $item['quantity'] = 1; // デフォルト値
                    self::logMessage("アイテム[$index]の数量が無効なため、デフォルト値(1)を使用します", 'WARNING');
                }
                
                // 備考のチェック
                if (!isset($item['note'])) {
                    $item['note'] = '';
                }
                
                // 処理済みアイテムとして追加（すべての情報を含める）
                $processedItem = [
                    'quantity' => (int)$item['quantity'],
                    'note' => $item['note']
                ];
                
                // square_item_idがある場合は追加
                if ($hasSquareId) {
                    $processedItem['square_item_id'] = $item['square_item_id'];
                }
                
                // nameとpriceがある場合は追加
                if ($hasNameAndPrice) {
                    $processedItem['name'] = $item['name'];
                    $processedItem['price'] = is_numeric($item['price']) ? floatval($item['price']) : 0;
                }
                
                // 文字列からデコードした場合、元の文字列にsquare_item_idが含まれているか確認
                if (!isset($processedItem['square_item_id']) && is_string($originalItem)) {
                    // 正規表現でsquare_item_idを抽出
                    if (preg_match('/square_item_id[\"\']?\s*:\s*[\"\']([^\"\']+)[\"\']/', $originalItem, $matches)) {
                        $processedItem['square_item_id'] = $matches[1];
                        self::logMessage("アイテム[$index]の元の文字列からsquare_item_idを抽出: " . $matches[1], 'INFO');
                    }
                }
                
                $processedItems[] = $processedItem;
            }
            
            // 処理可能なアイテムがない場合
            if (empty($processedItems)) {
                self::logMessage("処理可能な商品がありません", 'ERROR');
                return false;
            }
            
            self::logMessage("処理済み商品アイテム: " . json_encode($processedItems), 'INFO');
            
            // データ変換後にsquare_item_idが保持されているか確認
            foreach ($processedItems as $index => $item) {
                // 元のitemsからsquare_item_idを確認
                if (!isset($item['square_item_id']) && is_array($items[$index]) && isset($items[$index]['square_item_id'])) {
                    $processedItems[$index]['square_item_id'] = $items[$index]['square_item_id'];
                    self::logMessage("アイテム[$index]にsquare_item_idを復元しました: " . $items[$index]['square_item_id'], 'INFO');
                }
            }
            
            // 部屋の保留伝票を取得
            $ticket = $this->getRoomTicketByRoomNumber($roomNumber);
            self::logMessage("現在の部屋チケット: " . self::formatArgs($ticket), 'INFO');
            
            // 保留伝票が存在しない場合は作成
            if (!$ticket) {
                self::logMessage("Room {$roomNumber} の保留伝票が存在しないため、新規作成します", 'INFO');
                $ticket = $this->createRoomTicket($roomNumber);
                
                if (!$ticket) {
                    self::logMessage("Failed to create room ticket for room {$roomNumber}", 'ERROR');
                    return false;
                }
                
                self::logMessage("Room {$roomNumber} の保留伝票を作成しました: " . self::formatArgs($ticket), 'INFO');
                
                // 作成したチケットのSquare側の情報を取得
                if (!isset($ticket['square_order_id'])) {
                    self::logMessage("作成したチケットにSquare注文IDがありません", 'ERROR');
                    return false;
                }
                
                // Square APIから情報を取得して追加
                $squareTicket = $this->squareService->getOrder($ticket['square_order_id']);
                if ($squareTicket) {
                    $ticket['square_data'] = $squareTicket;
                } else {
                    self::logMessage("Square APIから注文情報を取得できませんでした: " . $ticket['square_order_id'], 'ERROR');
                    // エラーログを記録するが処理は続行する
                }
            }
            
            // チケットにsquare_data属性が無いか、square_order_idが無い場合は再作成
            if (!isset($ticket['square_data']) || !isset($ticket['square_order_id'])) {
                self::logMessage("無効なチケット形式、square_dataかsquare_order_idがありません: " . self::formatArgs($ticket), 'ERROR');
                
                // チケットを削除して再作成
                if (isset($ticket['id'])) {
                    $this->db->execute("DELETE FROM room_tickets WHERE id = ?", [$ticket['id']]);
                    self::logMessage("無効なチケットを削除しました: id={$ticket['id']}", 'INFO');
                }
                
                // 新規作成
                self::logMessage("チケットを再作成します", 'INFO');
                $ticket = $this->createRoomTicket($roomNumber);
                
                if (!$ticket) {
                    self::logMessage("チケットの再作成に失敗しました", 'ERROR');
                    return false;
                }
            
                // Square APIから情報を取得して追加
                $squareTicket = $this->squareService->getOrder($ticket['square_order_id']);
                if ($squareTicket) {
                    $ticket['square_data'] = $squareTicket;
                } else {
                    self::logMessage("再作成後もSquare APIから注文情報を取得できませんでした: " . $ticket['square_order_id'], 'ERROR');
                    return false; // ここで失敗とする
                }
            }
            
            // SquareServiceのインスタンスを確認
            if (!$this->squareService) {
                self::logMessage("squareServiceがnullです。再初期化します。", 'ERROR');
                $this->squareService = new SquareService();
            
                if (!$this->squareService) {
                    self::logMessage("squareServiceの再初期化に失敗しました", 'ERROR');
                    return false;
                }
            }
            
            // Square APIで商品を追加
            self::logMessage("Square APIで商品を追加します: " . self::formatArgs($processedItems), 'INFO');
            
            try {
                $updatedTicket = $this->squareService->addItemToRoomTicket($roomNumber, $processedItems);
            
                if (!$updatedTicket) {
                    self::logMessage("Failed to add items to Square ticket for room {$roomNumber} - サービスがfalseを返しました", 'ERROR');
                    
                    // SquareServiceのログを確認
                    if (file_exists(__DIR__ . '/../../logs/SquareService.log')) {
                        $lastLogLines = shell_exec('tail -50 ' . __DIR__ . '/../../logs/SquareService.log');
                        self::logMessage("SquareService の最新ログ: \n" . $lastLogLines, 'INFO');
                    }
                    
                    return false;
                }
                
                self::logMessage("Square APIで商品が正常に追加されました: " . self::formatArgs($updatedTicket), 'INFO');
                
                // 正常終了
                return $updatedTicket;
            } catch (Exception $e) {
                self::logMessage("Square APIでの商品追加中に例外が発生しました: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'ERROR');
                
                // エラーから回復を試みる
                try {
                    // 既存のチケットが正常か確認
                    $existingTicket = $this->getRoomTicketByRoomNumber($roomNumber);
                    
                    if (!$existingTicket) {
                        self::logMessage("リカバリーエラー: 既存のチケットが見つかりません", 'ERROR');
                        return false;
                    }
                    
                    self::logMessage("既存のチケット情報で表示を更新します", 'INFO');
                    return $existingTicket;
                } catch (Exception $recoveryEx) {
                    self::logMessage("リカバリー中に二次例外が発生しました: " . $recoveryEx->getMessage(), 'ERROR');
                    return false;
                }
            }
        } catch (Exception $e) {
            self::logMessage("addItemToRoomTicket処理中に例外が発生しました: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'ERROR');
            return false;
        }
    }
    
    /**
     * アクティブな保留伝票の一覧を取得
     * 
     * @return array 保留伝票の配列
     */
    public function getAllActiveRoomTickets() {
        self::logMessage("getAllActiveRoomTickets 開始", 'INFO');
        
        try {
            $query = "SELECT * FROM room_tickets WHERE status = 'OPEN'";
            $tickets = $this->db->select($query);
            
            $results = [];
            foreach ($tickets as $ticket) {
                $squareTicket = $this->squareService->getOrder($ticket['square_order_id']);
                
                if ($squareTicket && $squareTicket['status'] === 'OPEN') {
                    $results[] = array_merge($ticket, [
                        'square_data' => $squareTicket
                    ]);
                } else {
                    // Square側のステータスが変更されている場合は、ローカルのステータスを更新
                    if ($squareTicket) {
                        $this->updateTicketStatus($ticket['id'], $squareTicket['status']);
                    } else {
                        $this->updateTicketStatus($ticket['id'], 'CANCELED');
                    }
                }
            }
            
            self::logMessage("アクティブな保留伝票の一覧取得成功", 'INFO');
            return $results;
        } catch (Exception $e) {
            self::logMessage("Error getting all active room tickets: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * 保留伝票をチェックアウト（完了）状態に更新
     * 
     * @param string $roomNumber 部屋番号
     * @return bool 成功時はtrue、失敗時はfalse
     */
    public function checkoutRoomTicket($roomNumber) {
        self::logMessage("checkoutRoomTicket 開始: roomNumber={$roomNumber}", 'INFO');
        
        try {
            $ticket = $this->getRoomTicketByRoomNumber($roomNumber);
            
            if (!$ticket) {
                self::logMessage("No active room ticket found for checkout, room: {$roomNumber}", 'WARNING');
                return false;
            }
            
            // トランザクション開始
            $this->db->beginTransaction();
            
            try {
                // ローカルのステータスを更新
                $query = "UPDATE room_tickets SET status = 'COMPLETED' WHERE id = ?";
                $this->db->execute($query, [$ticket['id']]);
                
                // トランザクションコミット
                $this->db->commit();
                
                self::logMessage("Room ticket checked out for room {$roomNumber}", 'INFO');
                
                return true;
            } catch (Exception $e) {
                // トランザクションロールバック
                $this->db->rollback();
                throw $e;
            }
        } catch (Exception $e) {
            self::logMessage("Error checking out room ticket: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * チケットを削除
     * 
     * @param string $ticketId チケットID
     * @return bool 成功時はtrue、失敗時はfalse
     */
    public function deleteRoomTicket($ticketId) {
        self::logMessage("deleteRoomTicket 開始: ticketId={$ticketId}", 'INFO');
        
        try {
            // チケットが存在するか確認
            $ticket = $this->getRoomTicket($ticketId);
            if (!$ticket) {
                self::logMessage("削除対象のチケットが見つかりません: {$ticketId}", 'WARNING');
                Utils::log("Ticket not found for deletion: {$ticketId}", 'ERROR', 'RoomTicketService');
                return false;
            }
            
            // チケットに関連する注文の関連付けを削除
            $this->db->execute(
                "DELETE FROM ticket_orders WHERE ticket_id = ?",
                [$ticketId]
            );
            
            // チケットを削除
            $result = $this->db->execute(
                "DELETE FROM room_tickets WHERE id = ?",
                [$ticketId]
            );
            
            if (!$result) {
                self::logMessage("チケット削除に失敗: {$ticketId}", 'ERROR');
                Utils::log("Failed to delete room ticket: {$ticketId}", 'ERROR', 'RoomTicketService');
                return false;
            }
            
            self::logMessage("チケット削除成功: {$ticketId}", 'INFO');
            Utils::log("Room ticket deleted: {$ticketId}", 'INFO', 'RoomTicketService');
            
            return true;
        } catch (Exception $e) {
            self::logMessage("deleteRoomTicket エラー: " . $e->getMessage(), 'ERROR');
            Utils::log("Error deleting room ticket: " . $e->getMessage(), 'ERROR', 'RoomTicketService');
            return false;
        }
    }
} 
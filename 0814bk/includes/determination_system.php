<?php
/**
 * DeterminationSystem クラス
 * 
 * OCR結果判定システムの中核クラス。
 * ローカルサーバーから送信されたOCR結果を機種ごとの表示定義に基づいて判定し、
 * 通知が必要な場合はWebhookManagerに通知リクエストを送信します。
 */
class DeterminationSystem {
    private $db;
    private $logger;
    
    /**
     * コンストラクタ
     * 
     * @param PDO $db データベース接続オブジェクト
     */
    public function __construct($db) {
        $this->db = $db;
        $this->logger = new Logger('determination_system');
    }
    
    /**
     * OCR結果を判定する
     * 
     * @param array $ocr_data ローカルサーバーから送信されたOCRデータ
     * @return array 判定結果
     */
    public function determineOcrResult($ocr_data) {
        $this->logger->info("判定開始: OCRテキスト='" . $ocr_data['ocr_text'] . "', カメラID=" . $ocr_data['camera_id'] . ", エリアID=" . $ocr_data['area_id']);
        
        // モデルIDの取得（ArUcoマーカーまたはエリアマッピングから）
        $model_id = $this->getModelId($ocr_data);
        if (!$model_id) {
            $this->logger->warning("モデルIDが見つかりません: カメラID=" . $ocr_data['camera_id'] . ", エリアID=" . $ocr_data['area_id']);
            return $this->handleUnknownModel($ocr_data);
        }
        
        // OCR状態に基づく特別処理
        if ($ocr_data['ocr_status'] === 'EMPTY') {
            return $this->handleEmptyOcrResult($ocr_data, $model_id);
        }
        
        if ($ocr_data['ocr_status'] === 'FAILURE') {
            return $this->handleFailedOcrResult($ocr_data, $model_id);
        }
        
        // カメラ接続エラー時の特別処理
        if ($ocr_data['camera_status'] !== 'CONNECTED') {
            return $this->handleCameraError($ocr_data, $model_id);
        }
        
        // 通常の判定処理
        try {
            // 数値パース（可能な場合）
            $numerical_value = null;
            $parsed_value = $ocr_data['ocr_text'];
            
            // 数値パターンチェック
            if (preg_match('/^-?\d+(\.\d+)?$/', $ocr_data['ocr_text'])) {
                $numerical_value = floatval($ocr_data['ocr_text']);
                $parsed_value = (string)$numerical_value;
            }
            
            // 表示定義を検索
            $definition = $this->findMatchingDefinition($ocr_data['ocr_text'], $model_id);
            
            // 判定結果の作成
            $determination_result = [
                'ocr_text' => $ocr_data['ocr_text'],
                'parsed_value' => $parsed_value,
                'numerical_value' => $numerical_value,
                'camera_id' => $ocr_data['camera_id'],
                'area_id' => $ocr_data['area_id'],
                'model_id' => $model_id,
                'aruco_id' => isset($ocr_data['aruco_id']) ? $ocr_data['aruco_id'] : null,
                'ocr_status' => $ocr_data['ocr_status'],
                'camera_status' => $ocr_data['camera_status'],
                'image_path' => isset($ocr_data['image_path']) ? $ocr_data['image_path'] : null,
                'processed_image_path' => isset($ocr_data['processed_image_path']) ? $ocr_data['processed_image_path'] : null,
                'capture_time' => isset($ocr_data['capture_time']) ? $ocr_data['capture_time'] : date('Y-m-d H:i:s')
            ];
            
            if ($definition) {
                $this->logger->info("パターンマッチ成功: 定義ID=" . $definition['definition_id'] . ", 表示種別=" . $definition['display_type']);
                
                // 閾値判定が必要な場合
                $threshold_result = null;
                $is_threshold_alert = false;
                
                if ($definition['display_type'] === 'THRESHOLD' && $numerical_value !== null) {
                    list($threshold_result, $is_threshold_alert) = $this->checkThreshold(
                        $numerical_value,
                        $definition['threshold_min'],
                        $definition['threshold_max'],
                        $definition['condition_logic']
                    );
                    
                    $this->logger->info("閾値判定結果: $threshold_result, アラート=" . ($is_threshold_alert ? 'true' : 'false'));
                }
                
                // 定義情報を追加
                $determination_result['display_type'] = $definition['display_type'];
                $determination_result['determination_type'] = $definition['determination_type'];
                $determination_result['display_level'] = $definition['display_level'];
                $determination_result['matched_definition_id'] = $definition['definition_id'];
                $determination_result['is_threshold_alert'] = $is_threshold_alert;
                $determination_result['threshold_min'] = $definition['threshold_min'];
                $determination_result['threshold_max'] = $definition['threshold_max'];
                $determination_result['condition_logic'] = $definition['condition_logic'];
                $determination_result['threshold_result'] = $threshold_result;
            } else {
                $this->logger->info("パターンマッチ失敗: 該当する定義なし");
                
                // 未知の表示内容の場合
                $determination_result['display_type'] = 'UNKNOWN';
                $determination_result['determination_type'] = null;
                $determination_result['display_level'] = 'UNKNOWN';
                $determination_result['matched_definition_id'] = null;
                $determination_result['is_threshold_alert'] = false;
            }
            
            // 判定結果をDBに保存
            $result_id = $this->saveDeterminationResult($determination_result);
            $determination_result['result_id'] = $result_id;
            
            // 通知が必要か判断し、必要ならWebhookManagerに通知リクエスト
            if ($this->shouldNotify($determination_result)) {
                $this->triggerNotification($determination_result);
            }
            
            return $determination_result;
            
        } catch (Exception $e) {
            $this->logger->error("判定処理中にエラー発生: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * モデルIDを取得する（ArUcoマーカーまたはエリアマッピングから）
     *
     * @param array $ocr_data OCRデータ
     * @return int|null モデルID、見つからなければnull
     */
    private function getModelId($ocr_data) {
        // ArUcoマーカーIDがある場合はそれを優先
        if (isset($ocr_data['aruco_id']) && $ocr_data['aruco_id']) {
            $stmt = $this->db->prepare("
                SELECT model_id FROM aruco_model_mapping 
                WHERE camera_id = ? AND aruco_id = ? AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$ocr_data['camera_id'], $ocr_data['aruco_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result['model_id'];
            }
        }
        
        // エリアIDから検索
        $stmt = $this->db->prepare("
            SELECT model_id FROM area_model_mapping 
            WHERE camera_id = ? AND area_id = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$ocr_data['camera_id'], $ocr_data['area_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['model_id'];
        }
        
        return null;
    }
    
    /**
     * OCRテキストにマッチする表示定義を検索する
     *
     * @param string $ocr_text OCRテキスト
     * @param int $model_id モデルID
     * @return array|null マッチした表示定義、見つからなければnull
     */
    private function findMatchingDefinition($ocr_text, $model_id) {
        try {
            // 表示パターンが具体的な（長い）ものから優先的にマッチング
            $stmt = $this->db->prepare("
                SELECT * FROM display_definitions 
                WHERE model_id = ? AND is_active = 1
                ORDER BY LENGTH(display_pattern) DESC
            ");
            $stmt->execute([$model_id]);
            $definitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($definitions as $definition) {
                $pattern = $definition['display_pattern'];
                
                // 完全一致の場合
                if ($pattern === $ocr_text) {
                    return $definition;
                }
                
                // 正規表現マッチングの場合
                try {
                    if (@preg_match('/' . $pattern . '/', $ocr_text)) {
                        return $definition;
                    }
                } catch (Exception $e) {
                    $this->logger->warning("正規表現エラー: パターン=" . $pattern);
                    continue;
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->logger->error("表示定義検索中にエラー発生: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 閾値判定を行う
     *
     * @param float $value 判定対象の数値
     * @param float|null $threshold_min 最小閾値
     * @param float|null $threshold_max 最大閾値
     * @param string|null $condition_logic 判定ロジック
     * @return array [判定結果メッセージ, 閾値超過フラグ]
     */
    private function checkThreshold($value, $threshold_min, $threshold_max, $condition_logic) {
        if ($condition_logic === null) {
            $condition_logic = 'OUTSIDE_RANGE';  // デフォルト値
        }
        
        if ($threshold_min === null && $threshold_max === null) {
            return ["閾値未設定", false];
        }
        
        switch ($condition_logic) {
            case 'OUTSIDE_RANGE':
                // 範囲外判定 (値 < 最小閾値 または 値 > 最大閾値)
                if ($threshold_min !== null && $value < $threshold_min) {
                    return ["値($value)が最小閾値($threshold_min)未満", true];
                } elseif ($threshold_max !== null && $value > $threshold_max) {
                    return ["値($value)が最大閾値($threshold_max)超過", true];
                } else {
                    return ["値($value)は正常範囲内", false];
                }
                
            case 'INSIDE_RANGE':
                // 範囲内判定 (最小閾値 <= 値 <= 最大閾値)
                if ($threshold_min !== null && $threshold_max !== null && $threshold_min <= $value && $value <= $threshold_max) {
                    return ["値($value)が指定範囲内($threshold_min～$threshold_max)", true];
                } else {
                    return ["値($value)は指定範囲外", false];
                }
                
            case 'ABOVE_ONLY':
                // 上限超過のみ判定 (値 > 最大閾値)
                if ($threshold_max !== null && $value > $threshold_max) {
                    return ["値($value)が最大閾値($threshold_max)超過", true];
                } else {
                    return ["値($value)は最大閾値以下", false];
                }
                
            case 'BELOW_ONLY':
                // 下限未満のみ判定 (値 < 最小閾値)
                if ($threshold_min !== null && $value < $threshold_min) {
                    return ["値($value)が最小閾値($threshold_min)未満", true];
                } else {
                    return ["値($value)は最小閾値以上", false];
                }
                
            default:
                $this->logger->warning("不明な判定ロジック: " . $condition_logic);
                return ["不明な判定ロジック", false];
        }
    }
    
    /**
     * 表示なしのOCR結果を処理する
     *
     * @param array $ocr_data OCRデータ
     * @param int $model_id モデルID
     * @return array 判定結果
     */
    private function handleEmptyOcrResult($ocr_data, $model_id) {
        $this->logger->info("表示なし状態の処理: 機種ID={$model_id}, カメラID={$ocr_data['camera_id']}, エリアID={$ocr_data['area_id']}");
        
        // 機種ごとの「表示なし」状態の定義を検索
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM display_definitions 
                WHERE model_id = ? AND display_pattern = '' AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$model_id]);
            $empty_definition = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 判定結果の作成
            $determination_result = [
                'ocr_text' => '',
                'parsed_value' => '',
                'numerical_value' => null,
                'camera_id' => $ocr_data['camera_id'],
                'area_id' => $ocr_data['area_id'],
                'model_id' => $model_id,
                'aruco_id' => isset($ocr_data['aruco_id']) ? $ocr_data['aruco_id'] : null,
                'ocr_status' => 'EMPTY',
                'camera_status' => $ocr_data['camera_status'],
                'image_path' => isset($ocr_data['image_path']) ? $ocr_data['image_path'] : null,
                'processed_image_path' => isset($ocr_data['processed_image_path']) ? $ocr_data['processed_image_path'] : null,
                'capture_time' => isset($ocr_data['capture_time']) ? $ocr_data['capture_time'] : date('Y-m-d H:i:s')
            ];
            
            if ($empty_definition) {
                // 表示なし状態の定義がある場合
                $determination_result['display_type'] = $empty_definition['display_type'];
                $determination_result['determination_type'] = $empty_definition['determination_type'];
                $determination_result['display_level'] = $empty_definition['display_level'];
                $determination_result['matched_definition_id'] = $empty_definition['definition_id'];
                $determination_result['is_threshold_alert'] = false;
            } else {
                // 表示なし状態の定義がない場合
                $determination_result['display_type'] = 'UNKNOWN';
                $determination_result['determination_type'] = null;
                $determination_result['display_level'] = 'UNKNOWN';
                $determination_result['matched_definition_id'] = null;
                $determination_result['is_threshold_alert'] = false;
            }
            
            // 判定結果をDBに保存
            $result_id = $this->saveDeterminationResult($determination_result);
            $determination_result['result_id'] = $result_id;
            
            // 通知が必要か判断し、必要ならWebhookManagerに通知リクエスト
            if ($this->shouldNotify($determination_result)) {
                $this->triggerNotification($determination_result);
            }
            
            return $determination_result;
            
        } catch (Exception $e) {
            $this->logger->error("表示なし処理中にエラー発生: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * OCR認識失敗を処理する
     *
     * @param array $ocr_data OCRデータ
     * @param int $model_id モデルID
     * @return array 判定結果
     */
    private function handleFailedOcrResult($ocr_data, $model_id) {
        $this->logger->info("OCR認識失敗の処理: 機種ID={$model_id}, カメラID={$ocr_data['camera_id']}, エリアID={$ocr_data['area_id']}");
        
        // OCR失敗結果を記録
        $determination_result = [
            'ocr_text' => 'OCR_FAILED',
            'parsed_value' => 'OCR_FAILED',
            'numerical_value' => null,
            'camera_id' => $ocr_data['camera_id'],
            'area_id' => $ocr_data['area_id'],
            'model_id' => $model_id,
            'aruco_id' => isset($ocr_data['aruco_id']) ? $ocr_data['aruco_id'] : null,
            'ocr_status' => 'FAILURE',
            'camera_status' => $ocr_data['camera_status'],
            'display_type' => 'UNKNOWN',
            'determination_type' => 'ocr_failure',
            'display_level' => 'INFO',  // 通常はINFOレベル、継続的に失敗する場合は別途対応
            'matched_definition_id' => null,
            'is_threshold_alert' => false,
            'image_path' => isset($ocr_data['image_path']) ? $ocr_data['image_path'] : null,
            'processed_image_path' => isset($ocr_data['processed_image_path']) ? $ocr_data['processed_image_path'] : null,
            'capture_time' => isset($ocr_data['capture_time']) ? $ocr_data['capture_time'] : date('Y-m-d H:i:s')
        ];
        
        // 判定結果をDBに保存
        $result_id = $this->saveDeterminationResult($determination_result);
        $determination_result['result_id'] = $result_id;
        
        // 通知が必要か判断し、必要ならWebhookManagerに通知リクエスト
        if ($this->shouldNotify($determination_result)) {
            $this->triggerNotification($determination_result);
        }
        
        return $determination_result;
    }
    
    /**
     * カメラエラーを処理する
     *
     * @param array $ocr_data OCRデータ
     * @param int $model_id モデルID
     * @return array 判定結果
     */
    private function handleCameraError($ocr_data, $model_id) {
        $this->logger->info("カメラエラーの処理: 機種ID={$model_id}, カメラID={$ocr_data['camera_id']}, エリアID={$ocr_data['area_id']}, 状態={$ocr_data['camera_status']}");
        
        // カメラエラー結果を記録
        $determination_result = [
            'ocr_text' => "CAMERA_{$ocr_data['camera_status']}",
            'parsed_value' => "CAMERA_{$ocr_data['camera_status']}",
            'numerical_value' => null,
            'camera_id' => $ocr_data['camera_id'],
            'area_id' => $ocr_data['area_id'],
            'model_id' => $model_id,
            'aruco_id' => isset($ocr_data['aruco_id']) ? $ocr_data['aruco_id'] : null,
            'ocr_status' => 'FAILURE',
            'camera_status' => $ocr_data['camera_status'],
            'display_type' => 'UNKNOWN',
            'determination_type' => 'camera_error',
            'display_level' => 'HIGH',  // カメラエラーは通常HIGH優先度
            'matched_definition_id' => null,
            'is_threshold_alert' => false,
            'image_path' => isset($ocr_data['image_path']) ? $ocr_data['image_path'] : null,
            'processed_image_path' => null,
            'capture_time' => isset($ocr_data['capture_time']) ? $ocr_data['capture_time'] : date('Y-m-d H:i:s')
        ];
        
        // 判定結果をDBに保存
        $result_id = $this->saveDeterminationResult($determination_result);
        $determination_result['result_id'] = $result_id;
        
        // 通知が必要か判断し、必要ならWebhookManagerに通知リクエスト
        if ($this->shouldNotify($determination_result)) {
            $this->triggerNotification($determination_result);
        }
        
        return $determination_result;
    }
    
    /**
     * モデルIDが見つからない場合の処理
     *
     * @param array $ocr_data OCRデータ
     * @return array 判定結果
     */
    private function handleUnknownModel($ocr_data) {
        $this->logger->warning("モデルIDが見つからない: カメラID={$ocr_data['camera_id']}, エリアID={$ocr_data['area_id']}");
        
        // モデル不明結果を記録
        $determination_result = [
            'ocr_text' => isset($ocr_data['ocr_text']) ? $ocr_data['ocr_text'] : 'UNKNOWN_MODEL',
            'parsed_value' => isset($ocr_data['ocr_text']) ? $ocr_data['ocr_text'] : 'UNKNOWN_MODEL',
            'numerical_value' => null,
            'camera_id' => $ocr_data['camera_id'],
            'area_id' => $ocr_data['area_id'],
            'model_id' => null,
            'aruco_id' => isset($ocr_data['aruco_id']) ? $ocr_data['aruco_id'] : null,
            'ocr_status' => isset($ocr_data['ocr_status']) ? $ocr_data['ocr_status'] : 'SUCCESS',
            'camera_status' => isset($ocr_data['camera_status']) ? $ocr_data['camera_status'] : 'CONNECTED',
            'display_type' => 'UNKNOWN',
            'determination_type' => 'unknown_model',
            'display_level' => 'WARNING',  // モデル不明はWARNING優先度
            'matched_definition_id' => null,
            'is_threshold_alert' => false,
            'image_path' => isset($ocr_data['image_path']) ? $ocr_data['image_path'] : null,
            'processed_image_path' => isset($ocr_data['processed_image_path']) ? $ocr_data['processed_image_path'] : null,
            'capture_time' => isset($ocr_data['capture_time']) ? $ocr_data['capture_time'] : date('Y-m-d H:i:s')
        ];
        
        // 判定結果をDBに保存（モデルIDがnullでもエラーにならないよう対処）
        try {
            $result_id = $this->saveDeterminationResult($determination_result);
            $determination_result['result_id'] = $result_id;
            
            // 通知が必要か判断し、必要ならWebhookManagerに通知リクエスト
            if ($this->shouldNotify($determination_result)) {
                $this->triggerNotification($determination_result);
            }
        } catch (Exception $e) {
            $this->logger->error("モデル不明結果の保存に失敗: " . $e->getMessage());
        }
        
        return $determination_result;
    }
    
    /**
     * 判定結果をデータベースに保存する
     *
     * @param array $result 判定結果
     * @return int 保存された結果のID
     */
    private function saveDeterminationResult($result) {
        try {
            // 必須フィールドと省略可能フィールドを定義
            $required_fields = [
                'camera_id', 'area_id', 'ocr_text', 'ocr_status', 'camera_status',
                'display_type', 'display_level', 'capture_time'
            ];
            
            $nullable_fields = [
                'model_id', 'aruco_id', 'parsed_value', 'numerical_value', 'determination_type',
                'matched_definition_id', 'threshold_min', 'threshold_max', 'condition_logic',
                'image_path', 'processed_image_path', 'is_threshold_alert'
            ];
            
            // フィールドと値の配列を構築
            $fields = [];
            $values = [];
            $placeholders = [];
            
            // 必須フィールドをチェック
            foreach ($required_fields as $field) {
                if (!isset($result[$field])) {
                    throw new Exception("必須フィールド '$field' が欠落しています");
                }
                $fields[] = $field;
                $values[] = $result[$field];
                $placeholders[] = '?';
            }
            
            // 省略可能フィールドをチェック
            foreach ($nullable_fields as $field) {
                if (isset($result[$field])) {
                    $fields[] = $field;
                    $values[] = $result[$field];
                    $placeholders[] = '?';
                }
            }
            
            // SQLクエリの構築
            $field_list = implode(', ', $fields);
            $placeholder_list = implode(', ', $placeholders);
            
            $query = "INSERT INTO determination_results ($field_list) VALUES ($placeholder_list)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($values);
            
            $result_id = $this->db->lastInsertId();
            $this->logger->info("判定結果を保存しました: ID={$result_id}");
            
            return $result_id;
            
        } catch (Exception $e) {
            $this->logger->error("判定結果保存中にエラー発生: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 判定結果が通知すべきものか判断する
     *
     * @param array $result 判定結果
     * @return bool 通知が必要な場合はtrue
     */
    private function shouldNotify($result) {
        // 重要度に基づく判断
        if (in_array($result['display_level'], ['CRITICAL', 'HIGH'])) {
            return true;
        }
        
        // 閾値アラートの場合
        if (isset($result['is_threshold_alert']) && $result['is_threshold_alert']) {
            return true;
        }
        
        // エラー状態の場合
        if ($result['display_type'] === 'ERROR') {
            return true;
        }
        
        // カメラエラーの場合
        if ($result['determination_type'] === 'camera_error') {
            return true;
        }
        
        // 通知ルールに基づく判断（より複雑な条件）
        $notify = $this->checkNotificationRules($result);
        
        return $notify;
    }
    
    /**
     * 通知ルールをチェックする（より詳細な判定）
     *
     * @param array $result 判定結果
     * @return bool 通知が必要な場合はtrue
     */
    private function checkNotificationRules($result) {
        try {
            // 通知ルールのチェック
            $stmt = $this->db->prepare("
                SELECT * FROM notification_rules 
                WHERE is_active = 1
                ORDER BY priority ASC
            ");
            $stmt->execute();
            $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($rules as $rule) {
                // 判定種別一致チェック
                if ($result['determination_type'] !== null) {
                    $determination_types = explode(',', $rule['determination_types']);
                    if (!in_array($result['determination_type'], $determination_types) && !in_array('*', $determination_types)) {
                        continue;
                    }
                }
                
                // 表示レベル一致チェック
                $display_levels = explode(',', $rule['display_levels']);
                if (!in_array($result['display_level'], $display_levels) && !in_array('*', $display_levels)) {
                    continue;
                }
                
                // 機種ID一致チェック（指定がある場合のみ）
                if ($rule['model_ids'] !== null && $rule['model_ids'] !== '') {
                    $model_ids = explode(',', $rule['model_ids']);
                    if (!in_array($result['model_id'], $model_ids)) {
                        continue;
                    }
                }
                
                // ルールに一致した場合は通知する
                return true;
            }
            
            // どのルールにも一致しなかった場合
            return false;
            
        } catch (Exception $e) {
            $this->logger->error("通知ルールチェック中にエラー発生: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 通知を発行する
     *
     * @param array $result 判定結果
     */
    private function triggerNotification($result) {
        try {
            // WebhookManagerクラスを利用して通知を発行
            $webhook_manager = new WebhookManager($this->db);
            $webhook_manager->processNotification($result);
            
            // 通知フラグを更新
            $stmt = $this->db->prepare("
                UPDATE determination_results
                SET notified = 1, notified_at = NOW()
                WHERE result_id = ?
            ");
            $stmt->execute([$result['result_id']]);
            
            $this->logger->info("通知リクエストを発行しました: 判定結果ID={$result['result_id']}");
            
        } catch (Exception $e) {
            $this->logger->error("通知発行中にエラー発生: " . $e->getMessage());
        }
    }
}

/**
 * シンプルなロガークラス (本来はちゃんとしたロギングライブラリを使用する)
 */
class Logger {
    private $name;
    
    public function __construct($name) {
        $this->name = $name;
    }
    
    public function info($message) {
        $this->log('INFO', $message);
    }
    
    public function warning($message) {
        $this->log('WARNING', $message);
    }
    
    public function error($message) {
        $this->log('ERROR', $message);
    }
    
    private function log($level, $message) {
        $date = date('Y-m-d H:i:s');
        $log_message = "[$date] [$level] [{$this->name}] $message" . PHP_EOL;
        error_log($log_message, 3, __DIR__ . '/../logs/determination_system.log');
    }
} 
 
 
 
 
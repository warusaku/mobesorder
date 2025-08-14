<?php
/**
 * RTSPカメラテンプレート管理クラス
 * 
 * このクラスはRTSPカメラのメーカー別テンプレートを管理します。
 * JSONファイルからテンプレートを読み込み、データベースと連携します。
 * 
 * @author RTSP_Reader Development Team
 * @created 2025-07-10
 */

class TemplateManager {
    /**
     * @var mysqli データベース接続
     */
    private $db;
    
    /**
     * @var string テンプレートのベースディレクトリ
     */
    private $template_dir;
    
    /**
     * @var array 読み込まれたテンプレートキャッシュ
     */
    private $templates = [];

    /**
     * コンストラクタ
     * 
     * @param mysqli $db_connection データベース接続
     * @param string $base_dir ベースディレクトリ（省略時はデフォルト）
     */
    public function __construct($db_connection, $base_dir = null) {
        $this->db = $db_connection;
        
        if ($base_dir === null) {
            $this->template_dir = dirname(__DIR__) . '/templates';
        } else {
            $this->template_dir = rtrim($base_dir, '/');
        }
    }

    /**
     * 使用可能なすべてのテンプレートを取得
     * 
     * @param bool $force_refresh キャッシュを無視して再読み込みするかどうか
     * @return array テンプレートの配列
     */
    public function getAllTemplates($force_refresh = false) {
        if (empty($this->templates) || $force_refresh) {
            $this->loadTemplatesFromDb();
        }
        
        return $this->templates;
    }
    
    /**
     * テンプレートIDによる取得
     * 
     * @param string $template_id テンプレートID
     * @return array|null テンプレートデータ、存在しない場合はnull
     */
    public function getTemplateById($template_id) {
        if (empty($this->templates)) {
            $this->loadTemplatesFromDb();
        }
        
        foreach ($this->templates as $template) {
            if ($template['template_id'] === $template_id) {
                // テンプレートJSONの読み込み
                $template['data'] = $this->loadTemplateJson($template['file_path']);
                return $template;
            }
        }
        
        return null;
    }
    
    /**
     * メーカー別のテンプレート取得
     * 
     * @param string $manufacturer メーカー名
     * @return array メーカーに一致するテンプレートの配列
     */
    public function getTemplatesByManufacturer($manufacturer) {
        if (empty($this->templates)) {
            $this->loadTemplatesFromDb();
        }
        
        $result = [];
        foreach ($this->templates as $template) {
            if ($template['manufacturer'] === $manufacturer) {
                $result[] = $template;
            }
        }
        
        return $result;
    }
    
    /**
     * モデル名に一致するテンプレートを取得
     * 
     * @param string $model_name モデル名
     * @return array|null 最適なテンプレート、一致するものがない場合はnull
     */
    public function findBestMatchingTemplate($model_name) {
        if (empty($this->templates)) {
            $this->loadTemplatesFromDb();
        }
        
        $best_match = null;
        $best_score = 0;
        
        foreach ($this->templates as $template) {
            $pattern = $template['model_pattern'];
            
            // ワイルドカードをPHPの正規表現に変換
            $regex = '/^' . str_replace(['*', '?'], ['.*', '.'], $pattern) . '$/i';
            
            if (preg_match($regex, $model_name)) {
                // パターンが具体的であるほどスコアが高い
                $score = strlen($pattern) - substr_count($pattern, '*') * 2 - substr_count($pattern, '?');
                
                if ($score > $best_score) {
                    $best_score = $score;
                    $best_match = $template;
                }
            }
        }
        
        if ($best_match !== null) {
            $best_match['data'] = $this->loadTemplateJson($best_match['file_path']);
        }
        
        return $best_match;
    }
    
    /**
     * 新しいテンプレートの登録
     * 
     * @param array $template_data テンプレートデータ
     * @param string $json_content JSONコンテンツ
     * @return bool 成功したかどうか
     */
    public function registerTemplate($template_data, $json_content) {
        // テンプレートIDの確認
        if (empty($template_data['template_id'])) {
            return false;
        }
        
        try {
            // ファイルパスの設定
            $manufacturer = strtolower($template_data['manufacturer']);
            $template_id = $template_data['template_id'];
            $dir_path = $this->template_dir . '/' . $manufacturer;
            
            // ディレクトリが存在しない場合は作成
            if (!is_dir($dir_path)) {
                if (!mkdir($dir_path, 0755, true)) {
                    error_log("テンプレートディレクトリの作成に失敗: $dir_path");
                    return false;
                }
            }
            
            // ファイル名の生成
            $file_name = basename($template_id) . '.json';
            $file_path = "$manufacturer/$file_name";
            $full_path = $this->template_dir . '/' . $file_path;
            
            // JSONを保存
            if (file_put_contents($full_path, $json_content) === false) {
                error_log("テンプレートJSONの保存に失敗: $full_path");
                return false;
            }
            
            // データベースに登録
            $stmt = $this->db->prepare("
                INSERT INTO camera_templates 
                (template_id, manufacturer, model_pattern, description, file_path, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                manufacturer = VALUES(manufacturer),
                model_pattern = VALUES(model_pattern),
                description = VALUES(description),
                file_path = VALUES(file_path),
                is_active = VALUES(is_active),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            $is_active = isset($template_data['is_active']) ? (int)$template_data['is_active'] : 1;
            
            $stmt->bind_param(
                "sssssi",
                $template_data['template_id'],
                $template_data['manufacturer'],
                $template_data['model_pattern'],
                $template_data['description'],
                $file_path,
                $is_active
            );
            
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                // キャッシュをリフレッシュ
                $this->loadTemplatesFromDb(true);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("テンプレート登録エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * データベースからテンプレート情報を読み込む
     * 
     * @param bool $force_refresh 強制リフレッシュするかどうか
     */
    private function loadTemplatesFromDb($force_refresh = false) {
        if (!empty($this->templates) && !$force_refresh) {
            return;
        }
        
        $this->templates = [];
        
        $query = "SELECT * FROM camera_templates WHERE is_active = 1 ORDER BY manufacturer, template_id";
        $result = $this->db->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $this->templates[] = $row;
            }
            $result->free();
        }
    }
    
    /**
     * テンプレートJSONファイルを読み込む
     * 
     * @param string $relative_path 相対パス
     * @return array|null JSONデータ、読み込みに失敗した場合はnull
     */
    private function loadTemplateJson($relative_path) {
        $full_path = $this->template_dir . '/' . $relative_path;
        
        if (!file_exists($full_path)) {
            error_log("テンプレートファイルが存在しません: $full_path");
            return null;
        }
        
        $content = file_get_contents($full_path);
        if ($content === false) {
            error_log("テンプレートファイルの読み込みに失敗: $full_path");
            return null;
        }
        
        $data = json_decode($content, true);
        if ($data === null) {
            error_log("テンプレートJSONのパースに失敗: $full_path, Error: " . json_last_error_msg());
            return null;
        }
        
        return $data;
    }
    
    /**
     * テンプレートの有効性検証
     * 
     * @param array $template_data テンプレートデータ
     * @return bool 有効なテンプレートかどうか
     */
    public function validateTemplate($template_data) {
        // 必須フィールドの確認
        $required_fields = ['manufacturer', 'model_pattern', 'description', 'rtsp_patterns'];
        
        foreach ($required_fields as $field) {
            if (!isset($template_data[$field])) {
                return false;
            }
        }
        
        // rtsp_patternsの確認
        if (!is_array($template_data['rtsp_patterns']) || empty($template_data['rtsp_patterns'])) {
            return false;
        }
        
        foreach ($template_data['rtsp_patterns'] as $pattern) {
            if (!isset($pattern['name']) || !isset($pattern['url_pattern'])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * テンプレートを使用してRTSP URLを生成
     * 
     * @param array $template テンプレートデータ
     * @param array $params URLパラメータ (ip, port, username, password, etc.)
     * @param int $pattern_index 使用するパターンのインデックス (デフォルト: 0)
     * @return string 生成されたRTSP URL
     */
    public function generateRtspUrl($template, $params, $pattern_index = 0) {
        if (empty($template['data']) || empty($template['data']['rtsp_patterns'])) {
            return '';
        }
        
        $patterns = $template['data']['rtsp_patterns'];
        if (!isset($patterns[$pattern_index])) {
            return '';
        }
        
        $pattern = $patterns[$pattern_index];
        $url = $pattern['url_pattern'];
        
        // ポートがない場合はデフォルトを使用
        if (!isset($params['port']) && isset($pattern['default_port'])) {
            $params['port'] = $pattern['default_port'];
        }
        
        // パラメータを置換
        foreach ($params as $key => $value) {
            $url = str_replace('{' . $key . '}', urlencode($value), $url);
        }
        
        return $url;
    }
} 
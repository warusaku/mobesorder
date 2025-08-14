<?php
/**
 * ユーティリティクラス
 */
class Utils {
    /**
     * JSONレスポンスを送信
     * 
     * @param mixed $data レスポンスデータ
     * @param int $statusCode HTTPステータスコード
     */
    public static function sendJsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * エラーレスポンスを送信
     * 
     * @param string $message エラーメッセージ
     * @param int $statusCode HTTPステータスコード
     */
    public static function sendErrorResponse($message, $statusCode = 400) {
        self::sendJsonResponse(['success' => false, 'error' => $message], $statusCode);
    }
    
    /**
     * ランダムなトークンを生成
     * 
     * @param int $length トークンの長さ
     * @return string 生成されたトークン
     */
    public static function generateToken($length = 6) {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $token;
    }
    
    /**
     * リクエストからJSONデータを取得
     * 
     * @return array パースされたJSONデータ
     */
    public static function getJsonInput() {
        $json = file_get_contents('php://input');
        
        if (empty($json)) {
            self::log("JSONリクエストデータが空です", 'WARNING', 'Utils');
            return [];
        }
        
        self::log("入力JSON: " . $json, 'DEBUG', 'Utils');
        
        $data = json_decode($json, true);
        $jsonError = json_last_error();
        
        if ($jsonError !== JSON_ERROR_NONE) {
            $errorMsg = json_last_error_msg();
            $position = self::findJsonErrorPosition($json);
            
            $errorDetails = "JSONパースエラー: {$errorMsg}";
            if ($position !== null) {
                $errorDetails .= " at position {$position}";
            }
            
            self::log($errorDetails, 'ERROR', 'Utils');
            self::log("パースできなかったJSON: " . $json, 'ERROR', 'Utils');
            
            // エラーレスポンスを送信
            self::sendErrorResponse($errorDetails, 400);
        }
        
        return $data;
    }
    
    /**
     * JSON構文エラーの位置を特定する
     * 
     * @param string $json JSONの文字列
     * @return int|null エラー位置、または特定できない場合はnull
     */
    private static function findJsonErrorPosition($json) {
        // 1文字ずつJSONをパースしていき、エラーが発生する位置を特定
        for ($i = 0; $i < strlen($json); $i++) {
            $subJson = substr($json, 0, $i + 1);
            json_decode($subJson);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $i;
            }
        }
        
        return null;
    }
    
    /**
     * リクエストヘッダーからBearerトークンを取得
     * 
     * @return string|null トークン、または存在しない場合はnull
     */
    public static function getBearerToken() {
        $headers = self::getAuthorizationHeader();
        
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                $token = $matches[1];
                
                // 空のトークンの場合はnullを返す
                if (empty($token) || $token === 'null' || $token === 'undefined') {
                    error_log('Warning: Empty or invalid Bearer token received');
                    return null;
                }
                
                return $token;
            }
        }
        
        // デバッグ情報
        $debugInfo = [
            'headers' => $headers,
            'server_vars' => [
                'HTTP_AUTHORIZATION' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'not set',
                'Authorization' => $_SERVER['Authorization'] ?? 'not set'
            ]
        ];
        error_log('Debug - No Bearer token found: ' . json_encode($debugInfo));
        
        return null;
    }
    
    /**
     * Authorizationヘッダーを取得
     * 
     * @return string|null ヘッダー値、または存在しない場合はnull
     */
    private static function getAuthorizationHeader() {
        $headers = null;
        
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(
                array_map('ucwords', array_keys($requestHeaders)),
                array_values($requestHeaders)
            );
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        
        return $headers;
    }
    
    /**
     * CORSヘッダーを設定
     */
    public static function setCorsHeaders() {
        $allowedOrigins = explode(',', CORS_ALLOWED_ORIGINS);
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        
        if (in_array($origin, $allowedOrigins) || in_array('*', $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization");
            header("Access-Control-Allow-Credentials: true");
            
            // プリフライトリクエストの場合は、ここで終了
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                exit(0);
            }
        }
    }
    
    /**
     * ログを記録
     * 
     * @param string $message ログメッセージ
     * @param string $level ログレベル（DEBUG, INFO, WARNING, ERROR）
     * @param string $source ログソース（クラス名など）
     * @return bool 成功した場合はtrue
     */
    public static function log($message, $level = 'INFO', $source = 'System') {
        try {
            // ログレベルの検証（デフォルトはINFO）
            $validLevels = ['DEBUG', 'INFO', 'WARNING', 'ERROR'];
            if (!in_array($level, $validLevels)) {
                $level = 'INFO';
            }
            
            // ログレベルの比較（定義されていなければINFOとして処理）
            $configLogLevel = defined('LOG_LEVEL') ? LOG_LEVEL : 'INFO';
            $logLevelValues = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
        
            // 設定レベル以上の場合のみログを記録
            if ($logLevelValues[$level] < $logLevelValues[$configLogLevel]) {
                return true; // レベルが低いので記録しない
            }
        
            // ファイルにログを書き込み
            $timestamp = date('Y-m-d H:i:s');
            $formattedMessage = "[$timestamp] [$level] [$source] $message\n";
            
            // ログファイルパス（定義されていなければ標準のパスを使用）
            $logFile = defined('LOG_FILE') ? LOG_FILE : __DIR__ . '/../logs/app.log';
            
            // ログディレクトリがなければ作成
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            // ディレクトリの書き込み権限をチェック
            $dirWritable = is_writable($logDir);
            
            // 書き込み可能な場合のみファイルに書き込む
            $result = false;
            if ($dirWritable) {
                $result = @file_put_contents($logFile, $formattedMessage, FILE_APPEND);
            }
            
            // ファイル書き込みに失敗した場合はPHPのエラーログに記録
            if (!$result && function_exists('error_log')) {
                error_log("Utils: " . $formattedMessage);
                error_log("Utils: ログファイルへの書き込みに失敗しました: " . $logFile);
                
                if (!$dirWritable) {
                    error_log("Utils: ログディレクトリに書き込み権限がありません: " . $logDir);
                }
            }
            
            // データベースにもログを記録（利用可能な場合）
            self::logToDatabase($message, $level, $source);
            
            return true;
        } catch (Exception $e) {
            // エラーメッセージを標準エラー出力に書き込み
            error_log("ログ記録エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * データベースにログを記録する
     * 
     * @param string $message ログメッセージ
     * @param string $level ログレベル
     * @param string $source ログソース
     * @return bool 成功した場合はtrue
     */
    private static function logToDatabase($message, $level, $source) {
        try {
            // データベースクラスがあるか確認（まだ読み込まれていない可能性がある）
            if (!class_exists('Database', false)) {
                return false;
            }
            
            // フォールバック：ファイルにログを記録
            $timestamp = date('Y-m-d H:i:s');
            $formattedMessage = "[$timestamp] [$level] [$source] $message\n";
            $logFile = __DIR__ . '/../logs/fallback.log';
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            file_put_contents($logFile, $formattedMessage, FILE_APPEND);
            
            return true;
        } catch (Exception $e) {
            // エラーが発生した場合は無視して続行（データベースログは補助的）
            error_log("ログDBエラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 税込金額を計算する
     * 
     * @param float $amount 税抜金額
     * @param float $taxRate 税率（0.1 = 10%）
     * @return int 税込金額（整数）
     */
    public static function calculateTax($amount, $taxRate = 0.1) {
        return (int)($amount + ($amount * $taxRate));
    }
    
    /**
     * 日付を日本語フォーマットに変換する
     * 
     * @param string $date 日付文字列（YYYY-MM-DD HH:MM:SS形式）
     * @return string フォーマットされた日付（YYYY年M月D日 HH:MM形式）
     */
    public static function formatDate($date) {
        $timestamp = strtotime($date);
        return date('Y年n月j日 H:i', $timestamp);
    }
    
    /**
     * メールアドレスの妥当性をチェックする
     * 
     * @param string $email メールアドレス
     * @return bool 有効な場合はtrue
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * 文字列をサニタイズする（XSS対策）
     * 
     * @param string $text サニタイズする文字列
     * @return string サニタイズされた文字列
     */
    public static function sanitizeText($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * URLが存在するか確認
     * 
     * @param string $url 確認するURL
     * @return bool URLが有効な場合はtrue
     */
    public static function checkUrlExists($url) {
        // URLの形式チェック
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // cURLを使用してHEADリクエストを送信
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10秒でタイムアウト
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // SSL証明書の検証を有効化（本番環境向け）
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // ホスト名の検証
        curl_setopt($ch, CURLOPT_USERAGENT, 'LacisMobileOrder/1.0');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 接続タイムアウト
        
        // リクエスト実行
        curl_exec($ch);
        
        // エラーがあれば記録
        $error = curl_errno($ch);
        if ($error) {
            self::log("URL check failed for $url: " . curl_error($ch), 'WARNING', 'Utils');
        }
        
        // HTTPステータスコードを取得
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // 200番台または300番台のステータスコードであれば存在すると判断
        return ($httpCode >= 200 && $httpCode < 400);
    }
    
    /**
     * 一意のIDを生成する
     * 
     * @param string $prefix プレフィックス（オプション）
     * @return string 生成されたID
     */
    public static function generateUniqueId($prefix = '') {
        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        return $prefix ? $prefix . '_' . $uuid : $uuid;
    }
} 
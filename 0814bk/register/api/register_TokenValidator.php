<?php
/**
 * register_TokenValidator.php
 * Version: 1.0.0
 * 
 * LINEのIDトークン検証を担当するクラス
 * JWTトークンの形式検証とユーザーID抽出を実装
 */

require_once 'register_Logger.php';

class RegisterTokenValidator {
    private $logger;
    private $skipVerification = false; // デバッグ用フラグ
    
    /**
     * コンストラクタ
     * 
     * @param RegisterLogger $logger ログインスタンス
     * @param bool $skipVerification トークン検証をスキップするかどうか（デバッグ用）
     */
    public function __construct(RegisterLogger $logger, $skipVerification = false) {
        $this->logger = $logger;
        $this->skipVerification = $skipVerification;
    }
    
    /**
     * IDトークンを検証してユーザーIDを取得
     * 
     * @param string $token LINEのIDトークン
     * @return string ユーザーID
     * @throws Exception 検証失敗時
     */
    public function validateAndGetUserId($token) {
        if (empty($token)) {
            $this->logger->error('トークンが未指定です');
            throw new Exception('認証トークンが必要です');
        }
        
        // デバッグモードの場合
        if ($this->skipVerification) {
            $this->logger->warning('トークン検証をスキップ（デバッグモード）');
            return "test_user_" . time();
        }
        
        $this->logger->info('IDトークンを検証: ' . substr($token, 0, 20) . '...');
        
        try {
            $userId = $this->verifyToken($token);
            
            if (!$userId) {
                $this->logger->error('IDトークン検証に失敗しました');
                throw new Exception('認証に失敗しました');
            }
            
            $this->logger->info('IDトークン検証成功: userId=' . $userId);
            return $userId;
            
        } catch (Exception $e) {
            $this->logger->error('トークン検証中にエラー: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * LINEのIDトークンを検証し、ユーザーIDを取得する
     * 
     * @param string $token LINEのIDトークン
     * @return string|false 成功時はユーザーID、失敗時はfalse
     */
    private function verifyToken($token) {
        try {
            $this->logger->debug('トークン検証開始: ' . substr($token, 0, 20) . '...');
            
            // JWTトークンの形式検証
            $tokenParts = explode('.', $token);
            if (count($tokenParts) !== 3) {
                $this->logger->error('トークンフォーマットが不正: パート数 ' . count($tokenParts));
                return false;
            }
            
            // ヘッダーの検証
            $header = $this->decodeJwtPart($tokenParts[0]);
            if (!$header) {
                $this->logger->error('トークンヘッダーのデコードに失敗');
                return false;
            }
            
            if (!isset($header['alg']) || !isset($header['typ'])) {
                $this->logger->error('トークンヘッダーに必須フィールドがありません');
                return false;
            }
            
            $this->logger->debug('トークンヘッダー', $header);
            
            // ペイロードの検証
            $payload = $this->decodeJwtPart($tokenParts[1]);
            if (!$payload) {
                $this->logger->error('トークンペイロードのデコードに失敗');
                return false;
            }
            
            $this->logger->debug('トークンペイロード', $payload);
            
            // 必須フィールドの確認
            if (!isset($payload['sub'])) {
                $this->logger->error("ペイロードに'sub'フィールドがありません");
                return false;
            }
            
            if (!isset($payload['iss'])) {
                $this->logger->error("ペイロードに'iss'フィールドがありません");
                return false;
            }
            
            // 発行者の確認（LINEからのトークンであることを確認）
            if ($payload['iss'] !== 'https://access.line.me') {
                $this->logger->error('トークンの発行者が不正: ' . $payload['iss']);
                return false;
            }
            
            // 有効期限の確認
            if (isset($payload['exp'])) {
                $expireTime = $payload['exp'];
                $currentTime = time();
                
                if ($expireTime < $currentTime) {
                    $this->logger->error('トークンの有効期限が切れています: ' . date('Y-m-d H:i:s', $expireTime));
                    return false;
                }
                
                $this->logger->debug('トークンの有効期限: ' . date('Y-m-d H:i:s', $expireTime));
            }
            
            // userIdを返す
            $userId = $payload['sub'];
            $this->logger->info('トークン検証成功: ユーザーID ' . $userId);
            
            return $userId;
            
        } catch (Exception $e) {
            $this->logger->error('トークン検証中に例外: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * JWTの部分をデコード
     * 
     * @param string $part JWT部分（Base64エンコード）
     * @return array|false デコードされたデータ、失敗時はfalse
     */
    private function decodeJwtPart($part) {
        try {
            // Base64URLデコード
            $base64 = str_replace(['-', '_'], ['+', '/'], $part);
            $base64 = str_pad($base64, strlen($base64) % 4, '=', STR_PAD_RIGHT);
            
            $json = base64_decode($base64);
            if (!$json) {
                return false;
            }
            
            $data = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('JSONデコードエラー: ' . json_last_error_msg());
                return false;
            }
            
            return $data;
            
        } catch (Exception $e) {
            $this->logger->error('JWTデコード中にエラー: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * デバッグモードの設定
     * 
     * @param bool $skip トークン検証をスキップするかどうか
     */
    public function setSkipVerification($skip) {
        $this->skipVerification = $skip;
        if ($skip) {
            $this->logger->warning('トークン検証のスキップが有効化されました（デバッグ用）');
        }
    }
} 
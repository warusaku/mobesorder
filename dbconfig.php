<?php
/**
 * RTSP_Reader - データベース接続設定
 * 
 * このファイルはデータベース接続情報を定義します。
 * セキュリティ上、このファイルを公開ディレクトリに置かないでください。
 */

// MariaDB接続情報
define('DB_HOST', 'localhost');
define('DB_NAME', 'rtsp_reader_db');
define('DB_USER', 'rtsp_user');
define('DB_PASS', 'rtsp_pass_2024');

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

/**
 * データベース接続を取得する
 * @return mysqli データベース接続オブジェクト
 */
function get_db_connection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            error_log('データベース接続エラー: ' . $conn->connect_error);
            die('データベース接続に失敗しました');
        }
        
        $conn->set_charset('utf8mb4');
    }
    
    return $conn;
}
?> 
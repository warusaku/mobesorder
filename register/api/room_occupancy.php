<?php
/**
 * room_occupancy.php
 * 指定した room_number に紐付くアクティブな line_room_links を集計し、
 * 在室人数とユーザー名一覧を返却するエンドポイント。
 *
 * リクエスト:
 *   POST or GET room_number=<部屋番号>
 * レスポンス:
 *   {
 *     success: true,
 *     room_number: "fg#01",
 *     number_of_people_in_room: 2,
 *     usernames: ["山田","佐藤"]
 *   }
 *
 * ログ: logs/room_occupancy.log に出力 (300KB/20% ローテーション)
 */

require_once dirname(__DIR__, 2) . '/order/php/log_helper.php';
require_once dirname(__DIR__, 2) . '/api/config/config.php';

header('Content-Type: application/json; charset=utf-8');

$logFile = LogHelper::getLogFileNameFromPhp(__FILE__);

function jsonResponse(array $data, int $code = 200){
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$roomNumber = $_POST['room_number'] ?? $_GET['room_number'] ?? '';
if($roomNumber===''){
    LogHelper::warn('room_number 未指定', $logFile);
    jsonResponse(['success'=>false,'message'=>'room_number required'],400);
}

try{
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    ]);
}catch(PDOException $e){
    LogHelper::error('DB接続失敗: '.$e->getMessage(), $logFile);
    jsonResponse(['success'=>false,'message'=>'DB connect failed'],500);
}

try{
    $sql = 'SELECT user_name FROM line_room_links WHERE room_number=:room AND is_active=1';
    $stmt=$pdo->prepare($sql);
    $stmt->execute([':room'=>$roomNumber]);
    $names=array_column($stmt->fetchAll(), 'user_name');
    $count=count($names);

    LogHelper::info("Occupancy fetched: room={$roomNumber}, count={$count}", $logFile);

    jsonResponse([
        'success'=>true,
        'room_number'=>$roomNumber,
        'number_of_people_in_room'=>$count,
        'usernames'=>$names,
    ]);
}catch(Exception $ex){
    LogHelper::error('処理中例外: '.$ex->getMessage(), $logFile);
    jsonResponse(['success'=>false,'message'=>'internal error'],500);
} 
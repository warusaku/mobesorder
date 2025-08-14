<?php
/**
 * close_order_session.php
 * 指定された order_session を手動でクローズする管理用エンドポイント
 * POST JSON: {"session_id":"..."} または {"room_number":"101"}
 * 2025-05-12 作成
 */

ini_set('display_errors',0);
error_reporting(E_ALL);

$rootPath = realpath(__DIR__ . '/..');
require_once $rootPath . '/api/config/config.php';
require_once $rootPath . '/api/lib/Database.php';
require_once $rootPath . '/api/lib/Utils.php';

// ログ設定
$logDir  = $rootPath . '/logs';
$logFile = $logDir . '/close_order_session.log';
$maxSize = 307200; // 300KB
if(!is_dir($logDir)){@mkdir($logDir,0755,true);} // ディレクトリが無い場合は作成

function rotateLog(){
    global $logFile,$maxSize;
    if(file_exists($logFile) && filesize($logFile) > $maxSize){
        $keep = intval($maxSize * 0.2);
        $txt  = file_get_contents($logFile);
        $txt  = substr($txt,-$keep);
        file_put_contents($logFile,"[".date('Y-m-d H:i:s')."] [INFO] log rotated\n".$txt);
    }
}
function logLine($msg,$level='INFO'){
    global $logFile;
    rotateLog();
    file_put_contents($logFile,"[".date('Y-m-d H:i:s')."] [$level] $msg\n",FILE_APPEND);
}

// CORS (同一オリジンでの呼び出し想定だが念のため)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(200);exit;}

if($_SERVER['REQUEST_METHOD']!=='POST'){
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'),true);
$sessionId  = $input['session_id']  ?? null;
$roomNumber = $input['room_number'] ?? null;
$forceClose = isset($input['force']) ? (bool)$input['force'] : false;

if(!$sessionId && !$roomNumber){
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'session_id または room_number を指定してください']);
    exit;
}

try{
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // session_id が無ければ room_number から取得
    if(!$sessionId){
        $stmt = $conn->prepare("SELECT id, room_number FROM order_sessions WHERE room_number = :room AND is_active = 1 LIMIT 1");
        $stmt->execute([':room'=>$roomNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row){
            throw new Exception('アクティブなセッションが見つかりません');
        }
        $sessionId = $row['id'];
        $roomNumber = $row['room_number'];
    }

    // room_number を取得できていない場合はセッションから取得
    if(!$roomNumber){
        $stmt = $conn->prepare("SELECT room_number FROM order_sessions WHERE id = :sid LIMIT 1");
        $stmt->execute([':sid'=>$sessionId]);
        $roomRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $roomNumber = $roomRow['room_number'] ?? null;
    }

    // === 決済状況を判定 / シミュレーション ===
    if($forceClose){
        // 強制クローズ
        $newStatus = 'Force_closed';
        logLine("Force close requested for session $sessionId", 'INFO');
    }else{
        // 未会計クローズ（決済レコードの有無に関わらず処理を続行）
        $txStmt = $conn->prepare("SELECT id FROM square_transactions WHERE order_session_id = :sid LIMIT 1");
        $txStmt->execute([':sid'=>$sessionId]);
        $hasTx = $txStmt->fetch(PDO::FETCH_ASSOC) ? true : false;
        
        if($hasTx){
            // 決済済みの場合
            $newStatus = 'Completed';
            logLine("Session $sessionId has payment record, setting status to Completed", 'INFO');
        }else{
            // 未決済の場合（エラーにせず処理を続行）
            $newStatus = 'Pending_payment';
            logLine("Session $sessionId has no payment record, setting status to Pending_payment", 'INFO');
        }
    }

    // トランザクション
    $conn->beginTransaction();

    // order_sessions クローズ & ステータス更新
    $stmt = $conn->prepare("UPDATE order_sessions SET is_active=0, session_status=:sts, closed_at = NOW() WHERE id = :sid");
    $stmt->execute([':sid'=>$sessionId,':sts'=>$newStatus]);

    // line_room_links を非アクティブ
    // order_session_id で紐付ける実装が無い場合があるため room_number でもフォールバック
    $params = [':sid'=>$sessionId];
    $sqlLRL = "UPDATE line_room_links SET is_active=0 WHERE (order_session_id = :sid";
    if($roomNumber){
        $sqlLRL .= " OR room_number = :room";
        $params[':room'] = $roomNumber;
    }
    $sqlLRL .= ") AND is_active = 1";
    $stmt = $conn->prepare($sqlLRL);
    $stmt->execute($params);

    // orders を COMPLETED
    $stmt = $conn->prepare("UPDATE orders SET order_status='COMPLETED' WHERE (order_session_id = :sid OR room_number = :room) AND order_status='OPEN'");
    $stmt->execute([':sid'=>$sessionId,':room'=>$roomNumber]);

    // Force_closed のときのみ Square 商品を無効化
    if($newStatus==='Force_closed'){
        $sessRow = $conn->prepare("SELECT square_item_id FROM order_sessions WHERE id = :sid LIMIT 1");
        $sessRow->execute([':sid'=>$sessionId]);
        $sq = $sessRow->fetch(PDO::FETCH_ASSOC);
        if($sq && !empty($sq['square_item_id'])){
            // SquareService 経由で無効化
            try{
                require_once $rootPath . '/api/lib/SquareService.php';
                $sqSvc = new SquareService();
                $sqSvc->disableSessionProduct($sq['square_item_id']);
            }catch(Exception $x){
                logLine('disableSessionProduct error: '.$x->getMessage(),'WARNING');
            }
        }
    }

    $conn->commit();

    // Webhook レシート通知
    require_once $rootPath.'/api/lib/OrderService.php';
    OrderService::sendSessionCloseWebhook($sessionId,$newStatus);

    logLine("session $sessionId closed by manual operation");
    echo json_encode(['success'=>true,'session_id'=>$sessionId]);
}catch(Exception $e){
    if(isset($conn) && $conn->inTransaction()){$conn->rollBack();}
    logLine('ERROR: '.$e->getMessage(),'ERROR');
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
} 
<?php
ob_start();
/**
 * test_session_tool.php
 * 
 * products-type フローの統合テストをワンクリックで実施
 * 1) line_room_links にダミー行を挿入
 * 2) OrderService::createOrder() でダミー注文を作成（20 個）
 * 3) close_order_session.php を呼び出してクローズ
 * 4) 結果を表示
 * 5) すべてのダミーデータを削除
 * ログ: logs/test_session_tool.log
 *
 * 実運用モジュールを直接呼び出し、ビジネスロジックを検証します。
 */

ini_set('display_errors',1);
error_reporting(E_ALL);

$rootPath = realpath(__DIR__.'/..');
require_once $rootPath.'/api/config/config.php';
require_once $rootPath.'/api/lib/Database.php';
require_once $rootPath.'/api/lib/OrderService.php';
require_once $rootPath.'/api/lib/Utils.php';

$logDir  = $rootPath.'/logs';
if(!is_dir($logDir)) mkdir($logDir,0755,true);
$logFile = $logDir.'/test_session_tool.log';
$maxLog  = 300*1024; // 300KB 規約

function tlog($msg,$level='INFO'){
    global $logFile,$maxLog;
    // ログローテーション: 300KB を超えたら末尾 20% を残して切り詰め
    if(file_exists($logFile) && filesize($logFile)>$maxLog){
        $keep = intval($maxLog*0.2); // 最新 20%
        $data = file_get_contents($logFile);
        $data = substr($data,-$keep);
        file_put_contents($logFile,"[".date('Y-m-d H:i:s')."] [INFO] log rotated\n".$data);
    }
    file_put_contents($logFile,"[".date('Y-m-d H:i:s')."] [$level] $msg\n",FILE_APPEND);
}

$runId = uniqid('run_');
$startTime = microtime(true);
tlog("[$runId] ==== テスト開始 ====",'INFO');

$roomNumber = 'fg#11';
$dummyLineUser = 'TEST_LINE_USER_'.uniqid();
$dummyUserName = 'テストユーザ';
$dummySquareItemId = 'IV7TSDH5FQ7V6TBJXS32OLK6';

$db = Database::getInstance();
$conn = $db->getConnection();

try{
    $conn->beginTransaction();

    // 1) ダミー line_room_links
    $stmt=$conn->prepare("SELECT id FROM line_room_links WHERE room_number = :room AND is_active = 1 LIMIT 1");
    $stmt->execute([':room'=>$roomNumber]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row){
        $conn->prepare("INSERT INTO line_room_links (line_user_id,room_number,user_name,is_active,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())")
             ->execute([$dummyLineUser,$roomNumber,$dummyUserName,1]);
        tlog("[$runId] line_room_links 挿入");
        $steps[] = [
            'step' => 1,
            'module' => 'SQL INSERT line_room_links',
            'message' => 'line_room_linksテーブルにダミーユーザーを挿入',
            'args' => ['room'=>$roomNumber,'line_user'=>$dummyLineUser],
            'return' => ['rows_affected'=>1],
            'status' => 'OK'
        ];
    }else{
        tlog("[$runId] line_room_links 既存行を再利用");
        $steps[] = [
            'step' => 1,
            'module' => 'SQL INSERT line_room_links',
            'message' => 'line_room_linksテーブルに既存行を再利用',
            'args' => ['room'=>$roomNumber],
            'return' => 'skip (already exists)',
            'status' => 'SKIP'
        ];
    }

    $conn->commit();
}catch(Exception $e){
    $conn->rollBack();
    tlog("[$runId] ERROR line_room_links insert: ".$e->getMessage(),'ERROR');
    $steps[] = [
        'step' => 1,
        'module' => 'SQL INSERT line_room_links',
        'message' => 'line_room_linksテーブルへの挿入に失敗',
        'args' => ['room'=>$roomNumber],
        'return' => $e->getMessage(),
        'status' => 'ERROR'
    ];
    die('line_room_links error');
}

// 2) 注文作成
$orderSvc = new OrderService();
$items = [
    [
        'square_item_id'=>$dummySquareItemId,
        'quantity'=>20,
        'price'=>1000,
        'note'=>'テストアイテム'
    ]
];
$order = $orderSvc->createOrder($roomNumber,$items,$dummyUserName,'テスト注文',$dummyLineUser);
if(!$order){
    tlog("[$runId] 注文作成失敗",'ERROR');
    $steps[] = [
        'step' => 2,
        'module' => 'OrderService::createOrder',
        'message' => '注文作成に失敗',
        'args' => $items,
        'return' => 'ERROR',
        'status' => 'ERROR'
    ];
    die('createOrder failed');
}
$sessionId = $order['order_session_id'] ?? '';
$createdOrderId = $order['id'];
tlog("[$runId] createOrder success: order_id={$createdOrderId} session_id={$sessionId}");
$steps[] = [
    'step' => 2,
    'module' => 'OrderService::createOrder',
    'message' => 'ダミー注文を作成',
    'args' => [
        'room_number'=>$roomNumber,
        'items'=>$items
    ],
    'return' => $order,
    'status' => 'OK'
];

// after step 2 add webhook test
$settingsPath=$rootPath.'/api/lib/OrderService.php'; // already included earlier via class, we can reuse static method
require_once $rootPath.'/api/lib/OrderService.php';
$webUrls=[];
$settings=OrderService::isSquareOpenTicketEnabled(); // fallback but we actually need getSquareSettings via reflection
if(method_exists('OrderService','getSquareSettings')){
    $ref=new ReflectionClass('OrderService');
    $m=$ref->getMethod('getSquareSettings');
    $m->setAccessible(true);
    $webUrls=$m->invoke(null)['order_webhooks']??[];
}
if(!empty($webUrls)){
    foreach($webUrls as $u){
        $ch=curl_init($u);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['event_type'=>'test_ping']),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT_MS=>1500]);
        curl_exec($ch);
        $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        $steps[] = [
            'step' => 2.5,
            'module' => 'POST test webhook',
            'message' => 'webhookテストを実行',
            'args' => ['url'=>$u],
            'return' => ['http_code'=>$code],
            'status' => 'OK'
        ];
    }
}

// after step 2, before close call (after current webhook test lines)
$wev = $db->select("SELECT id,event_type FROM webhook_events WHERE order_session_id = ? ORDER BY id DESC LIMIT 1",[$sessionId]);
if($wev){
    $steps[] = [
        'step' => 2.6,
        'module' => 'DB webhook_events check',
        'message' => 'webhook_eventsテーブルの新規レコードを確認',
        'args' => null,
        'return' => $wev[0],
        'status' => 'OK'
    ];
}else{
    $steps[] = [
        'step' => 2.6,
        'module' => 'DB webhook_events check',
        'message' => 'webhook_eventsテーブルに新規レコードが見つからない',
        'args' => null,
        'return' => 'not found',
        'status' => 'ERROR'
    ];
}

// After first order creation block and addStep(2...) and webhook tests we add delay and second order
// ===== 2b) 追加注文 =====
sleep(2); // 2秒ディレイ
$items2=[
  ['square_item_id'=>'A6VEQLSSUSRSIZ35IX3PXTEO','quantity'=>10,'note'=>'追加薪'],
  ['square_item_id'=>'YLXZ2FGMZ2XK5FJPIB7UCIE7','quantity'=>2,'note'=>'追加アイテム'],
  ['square_item_id'=>'OZYWDDYBARHJNT7JBIW3SATD','quantity'=>10,'note'=>'追加商品']
];
$order2=$orderSvc->createOrder($roomNumber,$items2,$dummyUserName,'追加注文',$dummyLineUser);
if($order2){
   $createdOrderIds=[$createdOrderId,$order2['id']];
   $steps[] = [
       'step' => '2b',
       'module' => 'OrderService::createOrder',
       'message' => '追加ダミー注文を作成',
       'args' => $items2,
       'return' => $order2,
       'status' => 'OK'
   ];
   // webhook check second order_created
   usleep(300000);
   $ev2=$db->select("SELECT id FROM webhook_events WHERE order_session_id = ? AND event_type='order_created' ORDER BY id DESC LIMIT 1",[$sessionId]);
   $steps[] = [
       'step' => '2c',
       'module' => 'DB webhook_events check',
       'message' => 'webhook_eventsテーブルの追加レコードを確認',
       'args' => null,
       'return' => $ev2? $ev2[0]:'not found',
       'status' => $ev2?'OK':'ERROR'
   ];

   // 2d) Square カタログ商品確認
   require_once $rootPath.'/api/lib/SquareService.php';
   $sqSvc = new SquareService();
   try{
       if(!empty($order2['square_item_id'])){
           $resp = $sqSvc->getSquareClient()->getCatalogApi()->retrieveCatalogObject($order2['square_item_id'], true);
           if($resp->isSuccess()){
               $obj = $resp->getResult()->getObject();
               $itemData = $obj && method_exists($obj,'getItemData') ? $obj->getItemData() : null;
               $name = $itemData && method_exists($itemData,'getName') ? $itemData->getName() : '';
               $price = null;
               if($itemData && method_exists($itemData,'getVariations')){
                   $vars = $itemData->getVariations();
                   if(is_array($vars) && !empty($vars)){
                       $var0 = $vars[0];
                       if($var0 && method_exists($var0,'getItemVariationData')){
                           $varData = $var0->getItemVariationData();
                           if($varData && method_exists($varData,'getPriceMoney') && $varData->getPriceMoney()){
                               $price = $varData->getPriceMoney()->getAmount();
                           }
                       }
                   }
               }
               $itemInfo = [
                   'square_item_id' => $order2['square_item_id'],
                   'name' => $name,
                   'price' => $price
               ];
               $steps[] = [
                   'step' => '2d',
                   'module' => 'Square Catalog check',
                   'message' => 'Squareカタログの商品情報を確認',
                   'args' => null,
                   'return' => $itemInfo,
                   'status' => 'OK'
               ];
           }else{
               $errs = $resp->getErrors();
               $steps[] = [
                   'step' => '2d',
                   'module' => 'Square Catalog check',
                   'message' => 'Squareカタログの商品情報取得に失敗',
                   'args' => null,
                   'return' => [ 'error'=>'retrieve failed', 'square_errors'=>$errs ],
                   'status' => 'ERROR'
               ];
           }
       }else{
           $steps[] = [
               'step' => '2d',
               'module' => 'Square Catalog check',
               'message' => 'square_item_idが設定されていない',
               'args' => null,
               'return' => 'square_item_id not set',
               'status' => 'SKIP'
           ];
       }
   }catch(Exception $sqEx){
       $steps[] = [
           'step' => '2d',
           'module' => 'Square Catalog check',
           'message' => 'Squareカタログの商品情報取得に失敗',
           'args' => null,
           'return' => $sqEx->getMessage(),
           'status' => 'ERROR'
       ];
   }

   // 2e) 追加商品の価格確認 (products テーブル)
   $prodRows = $db->select("SELECT square_item_id,name,price FROM products WHERE square_item_id IN (?,?,?)",['A6VEQLSSUSRSIZ35IX3PXTEO','YLXZ2FGMZ2XK5FJPIB7UCIE7','OZYWDDYBARHJNT7JBIW3SATD']);
   $steps[] = [
       'step' => '2e',
       'module' => 'DB products check',
       'message' => 'productsテーブルの商品情報を確認',
       'args' => null,
       'return' => $prodRows ?: 'not found',
       'status' => $prodRows?'OK':'ERROR'
   ];

   // ===== 2f) Square API でセッション決済用注文を作成 =====
   require_once $rootPath.'/api/lib/SquareService.php';
   $sqSvcPay = new SquareService();
   $sessOrderResp = $sqSvcPay->createSessionOrder($order['square_item_id'],1,$sessionId);
   if($sessOrderResp){
       $steps[] = [
           'step' => '2f',
           'module' => 'SquareService::createSessionOrder',
           'message' => 'Squareセッションオーダー作成を試行',
           'args' => null,
           'return' => $sessOrderResp,
           'status' => 'OK'
       ];
       // ===== 2g) 注文を現金支払いで決済 =====
       $payResp = $sqSvcPay->createSessionCashPayment($sessOrderResp['order_id']);
       if($payResp){
           $steps[] = [
               'step' => '2g',
               'module' => 'SquareService::createSessionCashPayment',
               'message' => 'Squareセッションオーダー決済を試行',
               'args' => null,
               'return' => $payResp,
               'status' => 'OK'
           ];
       }else{
           $steps[] = [
               'step' => '2g',
               'module' => 'SquareService::createSessionCashPayment',
               'message' => 'Squareセッションオーダー決済に失敗',
               'args' => null,
               'return' => 'ERROR',
               'status' => 'ERROR'
           ];
       }
       // 2h) Webhook 到着待機 (最大15秒)
       $found=false;
       for($i=0;$i<30;$i++){ // 0.5sec * 30 =15sec
           usleep(500000);
           $tx=$db->select("SELECT id FROM square_transactions WHERE order_session_id = ? ORDER BY id DESC LIMIT 1",[$sessionId]);
           if($tx){
               $found=true;
               $steps[] = [
                   'step' => '2h',
                   'module' => 'DB square_transactions check',
                   'message' => 'square_transactionsテーブルの新規レコードを確認',
                   'args' => null,
                   'return' => $tx[0],
                   'status' => 'OK'
               ];
               break;
           }
       }
       if(!$found){
           $steps[] = [
               'step' => '2h',
               'module' => 'DB square_transactions check',
               'message' => 'square_transactionsテーブルに新規レコードが見つからない',
               'args' => null,
               'return' => 'not found',
               'status' => 'ERROR'
           ];
       }
   }else{
       $steps[] = [
           'step' => '2f',
           'module' => 'SquareService::createSessionOrder',
           'message' => 'Squareセッションオーダー作成に失敗',
           'args' => null,
           'return' => 'ERROR',
           'status' => 'ERROR'
       ];
   }
}else{
   $steps[] = [
       'step' => '2b',
       'module' => 'OrderService::createOrder',
       'message' => '追加ダミー注文作成に失敗',
       'args' => $items2,
       'return' => 'ERROR',
       'status' => 'ERROR'
   ];
}

sleep(2); // クローズ前にもディレイ

// 3) クローズ呼び出し
$origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$closeUrl = $origin . dirname($_SERVER['SCRIPT_NAME']) . '/close_order_session.php';
// force=0 を明示的に指定（Completed ルート）
$postData = json_encode(['session_id'=>$sessionId,'force'=>0]);
$opts=[
    'http'=>[
        'method'=>'POST',
        'header'=>'Content-Type: application/json',
        'content'=>$postData,
        'timeout'=>15
    ]
];
$response = @file_get_contents($closeUrl,false,stream_context_create($opts));
$closeResult = $response ? json_decode($response,true) : null;
if(!$closeResult || !$closeResult['success']){
    tlog("[$runId] close_order_session error: ".($response?:'no response'),'ERROR');
    $steps[] = [
        'step' => 3,
        'module' => 'POST close_order_session.php',
        'message' => 'オーダーセッションをクローズ',
        'args' => $postData,
        'return' => $response?:'no response',
        'status' => 'ERROR'
    ];
}else{
    tlog("[$runId] close_order_session success");
    $steps[] = [
        'step' => 3,
        'module' => 'POST close_order_session.php',
        'message' => 'オーダーセッションをクローズ',
        'args' => $postData,
        'return' => $closeResult,
        'status' => 'OK'
    ];
}

// 3.5) session_closed webhook check
$closedOk = ($closeResult && $closeResult['success']);
if($closedOk){
    // wait a short time for async insert
    usleep(300000); // 0.3 sec
    $closedEv = $db->select("SELECT id,event_type FROM webhook_events WHERE order_session_id = ? AND event_type = 'session_closed' ORDER BY id DESC LIMIT 1",[$sessionId]);
    if($closedEv){
        $steps[] = [
            'step' => '3.5',
            'module' => 'DB webhook_events check',
            'message' => 'session_closedレコードを確認',
            'args' => null,
            'return' => $closedEv[0],
            'status' => 'OK'
        ];
    }else{
        $steps[] = [
            'step' => '3.5',
            'module' => 'DB webhook_events check',
            'message' => 'session_closedレコードが見つからない',
            'args' => null,
            'return' => 'session_closed not found',
            'status' => 'ERROR'
        ];
    }
}

// 4) クリーンアップ
try{
    $conn->beginTransaction();
    // delete all created orders
    $orderIdsToDelete = isset($createdOrderIds)?$createdOrderIds:[$createdOrderId];
    foreach($orderIdsToDelete as $oid){
        $conn->prepare("DELETE FROM orders WHERE id = ?")->execute([$oid]);
        $conn->prepare("DELETE FROM order_details WHERE order_id = ?")->execute([$oid]);
    }
    $conn->prepare("DELETE FROM order_sessions WHERE id = ?")->execute([$sessionId]);
    $conn->prepare("DELETE FROM square_transactions WHERE order_session_id = ?")->execute([$sessionId]);
    // line_room_links ダミー行を削除
    $conn->prepare("DELETE FROM line_room_links WHERE line_user_id = ?")->execute([$dummyLineUser]);
    $conn->commit();
    tlog("[$runId] clean up completed");
    $steps[] = [
        'step' => 4,
        'module' => 'cleanup',
        'message' => 'テスト用データを全テーブルから削除',
        'args' => null,
        'return' => ['tables'=>['orders','order_details','order_sessions','square_transactions','square_webhooks','line_room_links']],
        'status' => 'OK'
    ];
}catch(Exception $e){
    $conn->rollBack();
    tlog("[$runId] cleanup error: ".$e->getMessage(),'ERROR');
    $steps[] = [
        'step' => 4,
        'module' => 'cleanup',
        'message' => 'テスト用データ削除に失敗',
        'args' => null,
        'return' => $e->getMessage(),
        'status' => 'ERROR'
    ];
}

// 5) サマリー & 出力
$duration = round((microtime(true)-$startTime)*1000);
$resLine = "[$runId] RESULT " . (($closeResult && $closeResult['success'])?'success':'fail') . " duration={$duration}ms order={$createdOrderId} session={$sessionId}";
tlog($resLine);

// AJAX 応答モード
if(isset($_GET['ajax'])){
    $history=[];
    $lines = file_exists($logFile)?file($logFile,FILE_IGNORE_NEW_LINES):[];
    for($i=count($lines)-1;$i>=0 && count($history)<20;$i--){
        if(strpos($lines[$i],' RESULT ')!==false){
            $history[]=$lines[$i];
        }
        if(strpos($lines[$i],' STEP')!==false){
            $step = explode(' STEP', $lines[$i]);
            $stepNo = intval(trim($step[0]));
            $module = trim($step[1]);
            $status = trim($step[2]);
            $argStr = trim($step[3]);
            $retStr = trim($step[4]);
            $steps[] = [
                'step' => $stepNo,
                'module' => $module,
                'message' => $argStr,
                'return' => $retStr,
                'status' => $status
            ];
        }
    }
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success'=> ($closeResult && $closeResult['success']),
        'order_id'=>$createdOrderId,
        'session_id'=>$sessionId,
        'duration_ms'=>$duration,
        'close_api'=>$closeResult,
        'steps'=>$steps,
        'history'=>$history
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$steps=[];

function addStep($stepNo,$module,$args,$return,$status='OK'){
    global $steps,$runId;
    $entry=[
        'step'=>$stepNo,
        'module'=>$module,
        'args'=>$args,
        'return'=>$return,
        'status'=>$status
    ];
    $steps[]=$entry;
    // ログ用に 1 行で出力 (args/return を短縮)
    $argStr=is_scalar($args)?$args:json_encode($args,JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR);
    $retStr=is_scalar($return)?$return:json_encode($return,JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR);
    tlog("[$runId][STEP{$stepNo}] module={$module} status={$status} args={$argStr} return={$retStr}");
}

?><!DOCTYPE html>
<html lang="ja"><head><meta charset="utf-8"><title>Test Session Tool</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"></head>
<body class="p-4"><h1 class="h4">テストセッション実行結果</h1><hr>
<ul>
<li>注文 ID: <?=htmlspecialchars($createdOrderId)?> </li>
<li>session_id: <?=htmlspecialchars($sessionId)?> </li>
<li>クローズ結果: <?=htmlspecialchars(json_encode($closeResult))?> </li>
</ul>
<p class="mt-3">詳細は <code>logs/test_session_tool.log</code> を参照してください。</p>
</body></html> 
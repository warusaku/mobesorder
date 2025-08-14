<?php
// 設定の読み込み
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/Utils.php';
require_once __DIR__ . '/lib/SquareService.php';

// ログ関数
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";
    echo $logMessage;
    
    // ログファイルに書き込み
    $logFile = __DIR__ . '/../logs/room_number_test.log';
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// テスト関数：部屋番号の検証
function testRoomNumber($roomNumber) {
    logMessage("部屋番号テスト開始: {$roomNumber}", 'INFO');
    
    // 特殊文字の確認
    if (!preg_match('/^[a-zA-Z0-9#\-_]+$/', $roomNumber)) {
        logMessage("部屋番号に特殊文字が含まれています", 'WARNING');
        $safeRoomNumber = preg_replace('/[^a-zA-Z0-9#\-_]/', '', $roomNumber);
        logMessage("安全な部屋番号に変換: {$roomNumber} -> {$safeRoomNumber}", 'INFO');
    } else {
        logMessage("部屋番号は有効な文字のみを含んでいます", 'INFO');
    }
    
    // エンコード/デコードテスト
    $encoded = urlencode($roomNumber);
    $decoded = urldecode($encoded);
    logMessage("URLエンコード: {$roomNumber} -> {$encoded}", 'INFO');
    logMessage("URLデコード後: {$encoded} -> {$decoded}", 'INFO');
    
    if ($decoded !== $roomNumber) {
        logMessage("デコード後の文字列が元の文字列と一致しません", 'ERROR');
    }
    
    // JSON処理テスト
    $jsonEncoded = json_encode(['room' => $roomNumber]);
    $jsonDecoded = json_decode($jsonEncoded, true);
    logMessage("JSON処理: {$jsonEncoded}", 'INFO');
    
    if ($jsonDecoded['room'] !== $roomNumber) {
        logMessage("JSONデコード後の部屋番号が元の部屋番号と一致しません", 'ERROR');
    }
}

// Square API接続テスト（部屋番号処理込み）
function testSquareApiWithRoomNumber($roomNumber) {
    logMessage("Square API接続テスト開始: {$roomNumber}", 'INFO');
    
    try {
        $squareService = new SquareService();
        
        // テスト環境情報
        logMessage("環境: " . SQUARE_ENVIRONMENT, 'INFO');
        logMessage("ロケーションID: " . SQUARE_LOCATION_ID, 'INFO');
        
        // 接続テスト
        $testResult = $squareService->testConnection();
        if (!$testResult) {
            logMessage("Square API接続テストに失敗しました", 'ERROR');
            return false;
        }
        
        logMessage("Square API接続テスト成功", 'INFO');
        
        // ロケーション情報確認
        logMessage("ロケーション情報を取得します", 'INFO');
        $locations = $squareService->getSquareClient()->getLocationsApi()->listLocations();
        
        if ($locations->isSuccess()) {
            $locationData = $locations->getResult()->getLocations();
            logMessage("ロケーション取得成功: " . count($locationData) . "件", 'INFO');
            
            foreach ($locationData as $location) {
                logMessage("ロケーション: " . $location->getId() . " - " . $location->getName(), 'INFO');
            }
        } else {
            logMessage("ロケーション取得失敗", 'ERROR');
        }
        
        // クリーンな部屋番号を生成
        $safeRoomNumber = preg_replace('/[^a-zA-Z0-9#\-_]/', '', $roomNumber);
        if ($safeRoomNumber !== $roomNumber) {
            logMessage("部屋番号を安全な形式に変換: {$roomNumber} -> {$safeRoomNumber}", 'INFO');
            $roomNumber = $safeRoomNumber;
        }
        
        // テスト注文作成（保留伝票用メソッドではなく、通常の注文作成を使用）
        logMessage("テスト注文作成を開始します: {$roomNumber}", 'INFO');
        
        $testItems = [
            [
                'name' => 'テスト商品',
                'price' => 100,
                'quantity' => 1,
                'note' => 'テスト用'
            ]
        ];
        
        $order = $squareService->createOrder($roomNumber, $testItems, '', "部屋番号テスト: {$roomNumber}");
        
        if ($order) {
            logMessage("テスト注文作成成功: " . json_encode($order), 'INFO');
            return true;
        } else {
            logMessage("テスト注文作成失敗", 'ERROR');
            return false;
        }
        
    } catch (Exception $e) {
        logMessage("Square API例外: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// テストする部屋番号
$testRoomNumbers = [
    'fg#02',   // 実際の問題がある部屋番号
    'fg-02',   // ハイフン版
    'room101', // 英数字のみ
    'テスト部屋',  // 日本語
    'test/room', // スラッシュ入り
    'room 101'  // スペース入り
];

// HTMLヘッダー
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>部屋番号処理テスト</title>
    <style>
        body { font-family: sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2 { color: #333; }
        .container { max-width: 800px; margin: 0 auto; }
        .test-section { background: #f7f7f7; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>部屋番号処理テスト</h1>
        
        <div class="test-section">
            <h2>1. 部屋番号検証テスト</h2>
            <?php
            echo "<pre>";
            foreach ($testRoomNumbers as $roomNumber) {
                echo "=== テスト: {$roomNumber} ===\n";
                testRoomNumber($roomNumber);
                echo "\n";
            }
            echo "</pre>";
            ?>
        </div>
        
        <div class="test-section">
            <h2>2. Square API接続テスト（問題の部屋番号）</h2>
            <?php
            echo "<pre>";
            echo "=== Square API接続テスト with 部屋番号: {$testRoomNumbers[0]} ===\n";
            $result = testSquareApiWithRoomNumber($testRoomNumbers[0]);
            echo "\n結果: " . ($result ? "<span class='success'>成功</span>" : "<span class='error'>失敗</span>");
            echo "</pre>";
            ?>
        </div>
    </div>
</body>
</html> 
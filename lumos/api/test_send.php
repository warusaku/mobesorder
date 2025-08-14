<?php
// テスト用メッセージを指定
$message = '全員一斉送信テストメッセージ';

// 送信先URL
$url = 'https://mobes.online/lumos/api/lineMessage_Tx.php';

// POSTデータ
$data = [
    'message' => $message
];

$options = [
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json",
        'content' => json_encode($data),
        'ignore_errors' => true // エラー時もレスポンス取得
    ]
];
$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);

// 結果表示
header('Content-Type: application/json; charset=UTF-8');
echo $result; 
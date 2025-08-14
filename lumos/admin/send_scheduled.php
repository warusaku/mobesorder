<?php
// 予約送信バッチスクリプト
require_once __DIR__ . '/../config/lumos_config.php';

$pdo = new PDO(
    'mysql:host=' . LUMOS_DB_HOST . ';dbname=' . LUMOS_DB_NAME . ';charset=utf8mb4',
    LUMOS_DB_USER,
    LUMOS_DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// 1. 送信対象の予約メッセージ取得
$stmt = $pdo->prepare("SELECT * FROM scheduled_messages WHERE status = 'pending' AND scheduled_at <= NOW()");
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($messages as $msg) {
    try {
        // 送信先情報
        $send_type = $msg['send_type'];
        $to_user_id = $msg['to_user_id'];
        $base_url = 'https://mobes.online';
        // テンプレートセットか単体か
        if ($msg['template_set_id']) {
            // セット送信
            $stmt_set = $pdo->prepare("SELECT template_id_1, template_id_2 FROM message_template_sets WHERE id = ?");
            $stmt_set->execute([$msg['template_set_id']]);
            $set = $stmt_set->fetch(PDO::FETCH_ASSOC);
            $template_ids = [$set['template_id_1'], $set['template_id_2']];
        } else {
            $template_ids = [$msg['template_id']];
        }
        foreach ($template_ids as $tid) {
            $stmt_tpl = $pdo->prepare("SELECT message_type, content FROM message_templates WHERE id = ?");
            $stmt_tpl->execute([$tid]);
            $tpl = $stmt_tpl->fetch(PDO::FETCH_ASSOC);
            if (!$tpl) continue;
            $message_type = $tpl['message_type'];
            $message_data = ($message_type === 'rich') ? json_decode($tpl['content'], true) : $tpl['content'];
            if ($message_type === 'rich') {
                $url = $base_url . '/lumos/api/lineMessage_Imagemap.php';
            } else {
                $url = $base_url . '/lumos/api/lineMessage_Tx.php';
            }
            if ($send_type === 'active_all') {
                // 全ユーザー取得
                $stmt_users = $pdo->query("SELECT line_user_id FROM line_room_links WHERE is_active = 1");
                $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
                foreach ($users as $user) {
                    $data = ($message_type === 'text') ? [
                        'message' => $message_data,
                        'to' => $user['line_user_id']
                    ] : [
                        'to' => $user['line_user_id'],
                        'messages' => [$message_data]
                    ];
                    $options = [
                        'http' => [
                            'method'  => 'POST',
                            'header'  => "Content-Type: application/json",
                            'content' => json_encode($data),
                            'ignore_errors' => true
                        ]
                    ];
                    $context = stream_context_create($options);
                    file_get_contents($url, false, $context);
                }
            } elseif ($send_type === 'individual' && $to_user_id) {
                $data = ($message_type === 'text') ? [
                    'message' => $message_data,
                    'to' => $to_user_id
                ] : [
                    'to' => $to_user_id,
                    'messages' => [$message_data]
                ];
                $options = [
                    'http' => [
                        'method'  => 'POST',
                        'header'  => "Content-Type: application/json",
                        'content' => json_encode($data),
                        'ignore_errors' => true
                    ]
                ];
                $context = stream_context_create($options);
                file_get_contents($url, false, $context);
            }
        }
        // ステータス更新
        $stmt_upd = $pdo->prepare("UPDATE scheduled_messages SET status = 'sent', updated_at = NOW() WHERE id = ?");
        $stmt_upd->execute([$msg['id']]);
    } catch (Exception $e) {
        // エラー時はstatusをfailedに
        $stmt_upd = $pdo->prepare("UPDATE scheduled_messages SET status = 'failed', updated_at = NOW() WHERE id = ?");
        $stmt_upd->execute([$msg['id']]);
    }
} 
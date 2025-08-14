<?php
// テンプレートセットプレビュー用API
if (isset($_GET['get_set_preview'])) {
    require_once __DIR__ . '/../config/lumos_config.php';
    $pdo = new PDO(
        'mysql:host=' . LUMOS_DB_HOST . ';dbname=' . LUMOS_DB_NAME . ';charset=utf8mb4',
        LUMOS_DB_USER,
        LUMOS_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $set_id = intval($_GET['get_set_preview']);
    $stmt = $pdo->prepare("SELECT template_id_1, template_id_2 FROM message_template_sets WHERE id = ?");
    $stmt->execute([$set_id]);
    $set = $stmt->fetch(PDO::FETCH_ASSOC);
    $result = ['templates' => []];
    if ($set) {
        foreach ([$set['template_id_1'], $set['template_id_2']] as $tid) {
            $stmt2 = $pdo->prepare("SELECT id, title, message_type, content FROM message_templates WHERE id = ?");
            $stmt2->execute([$tid]);
            $tpl = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($tpl) {
                $result['templates'][] = $tpl;
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

session_start();
require_once __DIR__ . '/../config/lumos_config.php';
$user_list = [];
try {
    $pdo = new PDO(
        'mysql:host=' . LUMOS_DB_HOST . ';dbname=' . LUMOS_DB_NAME . ';charset=utf8mb4',
        LUMOS_DB_USER,
        LUMOS_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $stmt = $pdo->query("SELECT line_user_id, user_name, room_number FROM line_room_links WHERE is_active = 1");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $user_list[] = $row;
    }
} catch (Exception $e) {
    $user_list = [];
}
// メッセージテンプレートの取得
$templates = [];
try {
    $stmt = $pdo->query("SELECT id, title, message_type, content FROM message_templates ORDER BY created_at DESC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $templates = [];
}

// テンプレートセット一覧の取得
$template_sets = [];
try {
    $stmt = $pdo->query("SELECT id, title FROM message_template_sets ORDER BY created_at DESC");
    $template_sets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $template_sets = [];
}

// POST-Redirect-GETパターン
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $send_timing = $_POST['send_timing'] ?? 'now';
    if ($send_timing === 'reserve') {
        // 予約送信の場合はscheduled_messagesにINSERT
        $reserve_datetime = $_POST['reserve_datetime'] ?? '';
        if (!$reserve_datetime) {
            $_SESSION['send_result'] = '<div class="result"><p class="error">予約日時を指定してください。</p></div>';
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
        $free_message = trim($_POST['free_message'] ?? '');
        $template_id = $_POST['template_id'] ?? null;
        $template_set_id = $_POST['template_set_id'] ?? null;
        $send_type = $_POST['send_type'] ?? 'active_all';
        $to_user_id = ($send_type === 'individual') ? ($_POST['to_user_id'] ?? null) : null;
        
        try {
            // フリーメッセージの場合は一時テンプレートを作成
            if (!empty($free_message)) {
                // 文字数制限チェック
                if (mb_strlen($free_message) > 1000) {
                    $_SESSION['send_result'] = '<div class="result"><p class="error">メッセージが1000文字を超えています。</p></div>';
                    header('Location: ' . $_SERVER['REQUEST_URI']);
                    exit;
                }
                
                // 一時テンプレートを作成
                $temp_title = '予約送信フリーメッセージ_' . date('Y-m-d_H:i:s');
                $sql_temp = "INSERT INTO message_templates (title, message_type, content, created_at, updated_at) VALUES (?, 'text', ?, NOW(), NOW())";
                $stmt_temp = $pdo->prepare($sql_temp);
                $stmt_temp->execute([$temp_title, $free_message]);
                $template_id = $pdo->lastInsertId();
            }
            
            $sql = "INSERT INTO scheduled_messages (template_id, template_set_id, send_type, to_user_id, scheduled_at, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $template_id ?: null,
                $template_set_id ?: null,
                $send_type,
                $to_user_id,
                $reserve_datetime
            ]);
            $_SESSION['send_result'] = '<div class="result"><p class="success">予約送信を登録しました。</p></div>';
        } catch (Exception $e) {
            $_SESSION['send_result'] = '<div class="result"><p class="error">予約送信の登録に失敗しました：' . htmlspecialchars($e->getMessage()) . '</p></div>';
        }
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        // フリーメッセージ送信処理
        $free_message = trim($_POST['free_message'] ?? '');
        if (!empty($free_message)) {
            try {
                $send_type = $_POST['send_type'] ?? 'active_all';
                $to_user_id = $_POST['to_user_id'] ?? null;
                $base_url = 'https://mobes.online';
                $url = $base_url . '/lumos/api/lineMessage_Tx.php';
                
                // 文字数制限チェック
                if (mb_strlen($free_message) > 1000) {
                    $_SESSION['send_result'] = '<div class="result"><p class="error">メッセージが1000文字を超えています。</p></div>';
                    header('Location: ' . $_SERVER['REQUEST_URI']);
                    exit;
                }
                
                $result_html = '';
                
                if ($send_type === 'active_all') {
                    $results = [];
                    foreach ($user_list as $user) {
                        $data = [
                            'message' => $free_message,
                            'to' => $user['line_user_id']
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
                        $res = file_get_contents($url, false, $context);
                        $results[] = json_decode($res, true);
                    }
                    
                    $result_html .= '<div class="result">';
                    $result_html .= '<p>宿泊中ユーザー全員にフリーメッセージを送信しました。</p>';
                    foreach ($results as $r) {
                        if ($r && isset($r['http_code']) && $r['http_code'] === 200) {
                            $result_html .= '<p class="success">送信成功</p>';
                        } else {
                            $result_html .= '<p class="error">送信エラー</p>';
                            if (isset($r['response'])) {
                                $result_html .= '<pre style="color:#e74c3c; background:#fff0f0; border:1px solid #e74c3c; padding:0.5em;">' . htmlspecialchars(json_encode($r['response'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</pre>';
                            }
                        }
                    }
                    $result_html .= '</div>';
                } elseif ($send_type === 'individual' && $to_user_id) {
                    $data = ['message' => $free_message, 'to' => $to_user_id];
                    $options = [
                        'http' => [
                            'method'  => 'POST',
                            'header'  => "Content-Type: application/json",
                            'content' => json_encode($data),
                            'ignore_errors' => true
                        ]
                    ];
                    $context = stream_context_create($options);
                    $result = file_get_contents($url, false, $context);
                    $response = json_decode($result, true);
                    $result_html .= '<div class="result">';
                    if ($response && isset($response['http_code'])) {
                        if ($response['http_code'] === 200) {
                            $result_html .= '<p class="success">フリーメッセージ送信成功！</p>';
                        } else {
                            $result_html .= '<p class="error">送信エラー（HTTP: ' . $response['http_code'] . '）</p>';
                            if (isset($response['response'])) {
                                $result_html .= '<pre>' . htmlspecialchars($response['response']) . '</pre>';
                            }
                        }
                    } else {
                        $result_html .= '<p class="error">予期せぬエラーが発生しました</p>';
                        $result_html .= '<pre>' . htmlspecialchars($result) . '</pre>';
                    }
                    $result_html .= '</div>';
                }
                
                $_SESSION['send_result'] = $result_html;
            } catch (Exception $e) {
                $_SESSION['send_result'] = '<div class="result"><p class="error">フリーメッセージ送信に失敗しました：' . htmlspecialchars($e->getMessage()) . '</p></div>';
            }
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
        
        $template_set_id = $_POST['template_set_id'] ?? null;
        if ($template_set_id) {
            // セット送信の場合
            try {
                $stmt = $pdo->prepare("SELECT template_id_1, template_id_2 FROM message_template_sets WHERE id = ?");
                $stmt->execute([$template_set_id]);
                $set = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($set) {
                    $template_ids = [$set['template_id_1'], $set['template_id_2']];
                    foreach ($template_ids as $tid) {
                        $stmt2 = $pdo->prepare("SELECT message_type, content FROM message_templates WHERE id = ?");
                        $stmt2->execute([$tid]);
                        $template = $stmt2->fetch(PDO::FETCH_ASSOC);
                        if ($template) {
                            $message_type = $template['message_type'];
                            $message_data = ($message_type === 'rich') ? json_decode($template['content'], true) : $template['content'];
                            $base_url = 'https://mobes.online';  // 本番環境のURLを直接指定
                            if ($message_type === 'rich') {
                                $url = $base_url . '/lumos/api/lineMessage_Imagemap.php';
                            } else {
                                $url = $base_url . '/lumos/api/lineMessage_Tx.php';
                            }
                            // 送信種別取得
                            $send_type = $_POST['send_type'] ?? 'active_all';
                            $to_user_id = $_POST['to_user_id'] ?? null;
                            // 送信処理（既存のロジックを流用）
                            if ($send_type === 'active_all') {
                                foreach ($user_list as $user) {
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
                    }
                    $_SESSION['send_result'] = '<div class="result"><p class="success">テンプレートセットの2つのメッセージを送信しました。</p></div>';
                        }
            } catch (Exception $e) {
                $_SESSION['send_result'] = '<div class="result"><p class="error">セット送信に失敗しました：' . htmlspecialchars($e->getMessage()) . '</p></div>';
            }
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $template_id = $_POST['template_id'] ?? null;
            $send_type = $_POST['send_type'] ?? 'active_all';
            $to_user_id = $_POST['to_user_id'] ?? null;
            
            if ($template_id) {
                try {
                    $stmt = $pdo->prepare("SELECT message_type, content FROM message_templates WHERE id = ?");
                    $stmt->execute([$template_id]);
                    $template = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($template) {
                        $message_type = $template['message_type'];
                        $message_data = ($message_type === 'rich') ? json_decode($template['content'], true) : $template['content'];
                        $base_url = 'https://mobes.online';  // 本番環境のURLを直接指定
                        
                        // メッセージタイプで送信先APIを切り替え
                        if ($message_type === 'rich') {
                            $url = $base_url . '/lumos/api/lineMessage_Imagemap.php';
                        } else {
                            $url = $base_url . '/lumos/api/lineMessage_Tx.php';
                        }
                        
                        $result_html = '';

        if ($send_type === 'active_all') {
            $results = [];
            foreach ($user_list as $user) {
                if ($message_type === 'text') {
                    $data = [
                                        'message' => $message_data,
                        'to' => $user['line_user_id']
                    ];
                } else {
                    $data = [
                        'to' => $user['line_user_id'],
                        'messages' => [$message_data]
                    ];
                }
                $options = [
                    'http' => [
                        'method'  => 'POST',
                        'header'  => "Content-Type: application/json",
                        'content' => json_encode($data),
                        'ignore_errors' => true
                    ]
                ];
                $context = stream_context_create($options);
                $res = file_get_contents($url, false, $context);
                $results[] = json_decode($res, true);
            }
                            
            $result_html .= '<div class="result">';
            $result_html .= '<p>宿泊中ユーザー全員に送信しました。</p>';
            foreach ($results as $r) {
                if ($r && isset($r['http_code']) && $r['http_code'] === 200) {
                    $result_html .= '<p class="success">送信成功</p>';
                } else {
                    $result_html .= '<p class="error">送信エラー</p>';
                    if (isset($r['response'])) {
                        $result_html .= '<pre style="color:#e74c3c; background:#fff0f0; border:1px solid #e74c3c; padding:0.5em;">' . htmlspecialchars(json_encode($r['response'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</pre>';
                    }
                }
            }
            $result_html .= '</div>';
        } elseif ($send_type === 'individual' && $to_user_id) {
                            $data = [ 'message' => $message_data, 'to' => $to_user_id ];
            $options = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json",
                    'content' => json_encode($data),
                    'ignore_errors' => true
                ]
            ];
                            $context = stream_context_create($options);
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            $response = json_decode($result, true);
            $result_html .= '<div class="result">';
            if ($response && isset($response['http_code'])) {
                if ($response['http_code'] === 200) {
                    $result_html .= '<p class="success">送信成功！</p>';
                } else {
                    $result_html .= '<p class="error">送信エラー（HTTP: ' . $response['http_code'] . '）</p>';
                    if (isset($response['response'])) {
                        $result_html .= '<pre>' . htmlspecialchars($response['response']) . '</pre>';
                    }
                }
            } else {
                $result_html .= '<p class="error">予期せぬエラーが発生しました</p>';
                $result_html .= '<pre>' . htmlspecialchars($result) . '</pre>';
            }
            $result_html .= '</div>';
        } elseif ($send_type === 'broadcast') {
                            $data = [ 'message' => $message_data ];
            $options = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json",
                    'content' => json_encode($data),
                    'ignore_errors' => true
                ]
            ];
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            $response = json_decode($result, true);
            $result_html .= '<div class="result">';
            if ($response && isset($response['http_code'])) {
                if ($response['http_code'] === 200) {
                    $result_html .= '<p class="success">友達全員に送信成功！</p>';
                } else {
                    $result_html .= '<p class="error">送信エラー（HTTP: ' . $response['http_code'] . '）</p>';
                    if (isset($response['response'])) {
                        $result_html .= '<pre>' . htmlspecialchars($response['response']) . '</pre>';
                    }
                }
            } else {
                $result_html .= '<p class="error">予期せぬエラーが発生しました</p>';
                $result_html .= '<pre>' . htmlspecialchars($result) . '</pre>';
            }
            $result_html .= '</div>';
        }
                        
        $_SESSION['send_result'] = $result_html;
                    }
                } catch (Exception $e) {
                    $_SESSION['send_result'] = '<div class="result"><p class="error">送信に失敗しました：' . htmlspecialchars($e->getMessage()) . '</p></div>';
                }
            }
        }
    }
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
}
// 一斉送信管理画面（雛形）

// アカウント情報の取得
$base_url = 'https://' . $_SERVER['HTTP_HOST'];
$account_info_url = $base_url . '/fgsquare/lumos/api/lineAccountInfo.php';

$account_options = [
    'http' => [
        'method'  => 'GET',
        'ignore_errors' => true
    ]
];

$account_context = stream_context_create($account_options);
$account_result = file_get_contents($account_info_url, false, $account_context);
$account_info = json_decode($account_result, true);

// 既存画像ディレクトリ一覧を取得
$existing_image_dirs = [];
$images_dir = __DIR__ . '/../upload/images/';
if (is_dir($images_dir)) {
    foreach (scandir($images_dir) as $d) {
        if ($d === '.' || $d === '..') continue;
        if (is_dir($images_dir . $d) && file_exists($images_dir . $d . '/1040')) {
            $existing_image_dirs[] = $d;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>LINE一斉送信管理画面</title>
    <link rel="stylesheet" href="send.css">
</head>
<body>
<div class="container">
    <h1>LINE一斉送信</h1>
    
    <?php if ($account_info && isset($account_info['response'])): ?>
    <div class="account-info">
        <h2>公式アカウント情報</h2>
        <dl>
            <?php foreach ($account_info['response'] as $key => $value): ?>
                <dt><?php echo htmlspecialchars($key); ?></dt>
                <dd><?php echo is_string($value) ? htmlspecialchars($value) : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?></dd>
            <?php endforeach; ?>
        </dl>
        <?php if (($account_info['http_code'] ?? 200) !== 200): ?>
            <div style="color:#e74c3c; margin-top:1em;">
                <strong>エラー:</strong> HTTP <?php echo $account_info['http_code']; ?><br>
                <pre><?php echo htmlspecialchars(json_encode($account_info['response'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="post">
        <label>送信種別：</label>
        <select name="send_type" id="send_type" onchange="toggleUserSelect()">
            <option value="active_all">宿泊中ユーザー全員</option>
            <option value="individual">特定のユーザー</option>
        </select><br><br>
        <div id="user_select_area" style="display:none;">
            <label>送信先ユーザー：</label>
            <select name="to_user_id">
                <?php foreach ($user_list as $user): ?>
                    <option value="<?php echo htmlspecialchars($user['line_user_id']); ?>"><?php echo htmlspecialchars($user['user_name']); ?>（<?php echo htmlspecialchars($user['line_user_id']); ?>）</option>
                <?php endforeach; ?>
            </select><br><br>
        </div>
        <label>送信方法：</label><br>
        <input type="radio" name="template_mode" id="mode_free" value="free" checked onchange="toggleTemplateMode()"><label for="mode_free">フリーメッセージ</label>
        <input type="radio" name="template_mode" id="mode_single" value="single" onchange="toggleTemplateMode()"><label for="mode_single">単体テンプレート</label>
        <input type="radio" name="template_mode" id="mode_set" value="set" onchange="toggleTemplateMode()"><label for="mode_set">テンプレートセット</label><br><br>
        
        <div id="free_message_area">
            <label>メッセージ内容：</label><br>
            <textarea name="free_message" id="free_message" style="width: 100%; height: 120px; margin-bottom: 1em; padding: 0.5em; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; line-height: 1.4;" placeholder="送信したいメッセージを入力してください..." onkeyup="showFreeMessagePreview()"></textarea>
            <div style="font-size: 0.9em; color: #666; margin-bottom: 1em;">
                <span id="char_count">0</span> 文字 | 最大 1000文字
            </div>
        </div>
        
        <div id="single_template_area" style="display:none;">
            <label>メッセージテンプレート：</label><br>
            <select name="template_id" id="template_id" style="width: 100%; margin-bottom: 1em;" onchange="showPreview(this.value)">
                <option value="">テンプレートを選択してください</option>
                <?php foreach ($templates as $template): ?>
                    <option value="<?php echo htmlspecialchars($template['id']); ?>" 
                            data-type="<?php echo htmlspecialchars($template['message_type']); ?>"
                            data-content='<?php
                                if ($template['message_type'] === 'rich') {
                                    echo htmlspecialchars($template['content'], ENT_QUOTES, 'UTF-8');
                                } else {
                                    echo json_encode($template['content'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                                }
                            ?>'>
                        <?php echo htmlspecialchars($template['title']); ?> 
                        (<?php echo $template['message_type'] === 'text' ? 'テキスト' : 'リッチ'; ?>)
                    </option>
                <?php endforeach; ?>
            </select><br>
        </div>
        <?php if (!empty($template_sets)): ?>
        <div id="set_template_area" style="display:none;">
            <label>テンプレートセット：</label><br>
            <select name="template_set_id" id="template_set_id" style="width: 100%; margin-bottom: 1em;">
                <option value="">セットを選択してください</option>
                <?php foreach ($template_sets as $set): ?>
                    <option value="<?php echo htmlspecialchars($set['id']); ?>"><?php echo htmlspecialchars($set['title']); ?></option>
                <?php endforeach; ?>
            </select><br>
            </div>
        <?php endif; ?>
        <div id="free_preview_area" style="display: block; margin: 1em 0; padding: 1em; border: 1px solid #ccc; border-radius: 4px;">
            <h3>プレビュー</h3>
            <div style="background: #f8f9fa; padding: 1em; border-radius: 4px; min-height: 60px; border-left: 4px solid #00c851;">
                <div style="font-size: 0.9em; color: #666; margin-bottom: 0.5em;">📱 LINE メッセージ</div>
                <p id="free_preview_text" style="margin: 0; white-space: pre-wrap; color: #333; font-size: 14px; line-height: 1.4;">メッセージを入力するとここにプレビューが表示されます</p>
            </div>
        </div>
        
        <div id="preview_area" style="display: none; margin: 1em 0; padding: 1em; border: 1px solid #ccc; border-radius: 4px;">
            <h3>プレビュー</h3>
            <div id="text_preview" style="display: none;">
                <div style="background: #fff; padding: 1em; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <p id="preview_text" style="margin: 0; white-space: pre-wrap;"></p>
                </div>
            </div>
            <div id="rich_preview" style="display: none;">
                <div style="background: #fff; padding: 1em; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div id="preview_image" style="margin-bottom: 1em;">
                        <img src="" alt="プレビュー画像" style="max-width: 100%; height: auto;">
                    </div>
                    <div id="preview_links" style="margin-top: 1em;">
                        <h4>リンク先：</h4>
                        <ul id="preview_links_list" style="list-style: none; padding: 0;"></ul>
            </div>
                </div>
            </div>
        </div>
        <div id="preview_area_set" style="display:none; margin: 1em 0; padding: 1em; border: 1px solid #ccc; border-radius: 4px;"></div>
        <label>送信タイミング：</label><br>
        <input type="radio" name="send_timing" id="timing_now" value="now" checked onchange="toggleSendTiming()"><label for="timing_now">今すぐ送信</label>
        <input type="radio" name="send_timing" id="timing_reserve" value="reserve" onchange="toggleSendTiming()"><label for="timing_reserve">予約送信</label><br>
        <div id="reserve_time_area" style="display:none; margin-bottom:1em;">
            <label>送信日時：</label>
            <input type="datetime-local" name="reserve_datetime" id="reserve_datetime">
        </div>
        <button type="submit" onclick="return validateForm()">送信</button>
    </form>
    
    <?php
    if (isset($_SESSION['send_result'])) {
        echo $_SESSION['send_result'];
        unset($_SESSION['send_result']);
    }
    ?>
</div>
<script>
function toggleTemplateMode() {
    const free = document.getElementById('mode_free').checked;
    const single = document.getElementById('mode_single').checked;
    const set = document.getElementById('mode_set').checked;
    
    // エリアの表示制御
    document.getElementById('free_message_area').style.display = free ? '' : 'none';
    document.getElementById('single_template_area').style.display = single ? '' : 'none';
    const setArea = document.getElementById('set_template_area');
    if (setArea) setArea.style.display = set ? '' : 'none';
    
    // プレビューエリアの表示制御
    document.getElementById('free_preview_area').style.display = free ? 'block' : 'none';
    document.getElementById('preview_area').style.display = (single && !free) ? '' : 'none';
    document.getElementById('preview_area_set').style.display = (set && !free) ? '' : 'none';
    
    // フリーメッセージが選択された場合、プレビューを更新
    if (free) {
        showFreeMessagePreview();
    }
}
window.addEventListener('DOMContentLoaded', toggleTemplateMode);

// フリーメッセージのプレビュー機能
function showFreeMessagePreview() {
    const textarea = document.getElementById('free_message');
    const previewText = document.getElementById('free_preview_text');
    const charCount = document.getElementById('char_count');
    
    if (!textarea || !previewText || !charCount) return;
    
    const message = textarea.value;
    const charLength = message.length;
    
    // 文字数更新
    charCount.textContent = charLength;
    charCount.style.color = charLength > 1000 ? '#e74c3c' : '#666';
    
    // プレビュー更新
    if (message.trim() === '') {
        previewText.textContent = 'メッセージを入力するとここにプレビューが表示されます';
        previewText.style.color = '#999';
        previewText.style.fontStyle = 'italic';
    } else {
        previewText.textContent = message;
        previewText.style.color = '#333';
        previewText.style.fontStyle = 'normal';
    }
}

function showPreview(templateId) {
    const select = document.getElementById('template_id');
    const option = select.options[select.selectedIndex];
    const previewArea = document.getElementById('preview_area');
    const textPreview = document.getElementById('text_preview');
    const richPreview = document.getElementById('rich_preview');
    
    if (!templateId) {
        previewArea.style.display = 'none';
        return;
    }
    
    const messageType = option.getAttribute('data-type');
    const content = JSON.parse(option.getAttribute('data-content'));
    
    previewArea.style.display = 'block';
    
    if (messageType === 'text') {
        textPreview.style.display = 'block';
        richPreview.style.display = 'none';
        document.getElementById('preview_text').textContent = content;
    } else {
        textPreview.style.display = 'none';
        richPreview.style.display = 'block';
        
        // 画像プレビュー
        const previewImage = document.getElementById('preview_image').querySelector('img');
        const previewImageUrl = content.baseUrl + '/1040';
        previewImage.src = previewImageUrl;
        
        // 画像URLを表示
        let urlDisplay = document.getElementById('preview_image_url');
        if (!urlDisplay) {
            urlDisplay = document.createElement('div');
            urlDisplay.id = 'preview_image_url';
            urlDisplay.style = 'margin-top: 0.5em; font-size: 0.9em; color: #888; word-break: break-all;';
            previewImage.parentNode.appendChild(urlDisplay);
        }
        urlDisplay.textContent = '参照URL: ' + previewImageUrl;
        
        // リンク先の表示
        const linksList = document.getElementById('preview_links_list');
        linksList.innerHTML = '';
        
        if (content.actions && content.actions.length > 0) {
            content.actions.forEach((action, index) => {
                if (action.linkUri) {
                    const li = document.createElement('li');
                    li.style.marginBottom = '0.5em';
                    li.innerHTML = `
                        <strong>エリア ${index + 1}:</strong>
                        <a href="${action.linkUri}" target="_blank" style="color: #0066cc; text-decoration: none;">
                            ${action.linkUri}
                        </a>
                    `;
                    linksList.appendChild(li);
                }
            });
        } else {
            const li = document.createElement('li');
            li.textContent = 'リンクは設定されていません';
            linksList.appendChild(li);
        }
    }
}

// テンプレートセットのプレビュー
document.getElementById('template_set_id') && document.getElementById('template_set_id').addEventListener('change', async function() {
    const setId = this.value;
    const previewAreaSet = document.getElementById('preview_area_set');
    previewAreaSet.innerHTML = '';
    if (!setId) {
        previewAreaSet.style.display = 'none';
        return;
    }
    // Ajaxでセット内容取得
    try {
        const res = await fetch('?get_set_preview=' + encodeURIComponent(setId));
        const data = await res.json();
        if (data && data.templates && data.templates.length === 2) {
            previewAreaSet.style.display = 'block';
            data.templates.forEach((tpl, idx) => {
                const div = document.createElement('div');
                div.style.marginBottom = '2em';
                div.innerHTML = `<h4>テンプレート${idx+1}：${tpl.title} (${tpl.message_type === 'text' ? 'テキスト' : 'リッチ'})</h4>`;
                if (tpl.message_type === 'text') {
                    div.innerHTML += `<div style='background:#fff; padding:1em; border-radius:4px;'><pre style='margin:0;'>${tpl.content}</pre></div>`;
                } else {
                    const imgUrl = JSON.parse(tpl.content).baseUrl + '/1040';
                    div.innerHTML += `<div style='background:#fff; padding:1em; border-radius:4px;'><img src='${imgUrl}' style='max-width:100%;'><div style='font-size:0.9em; color:#888; margin-top:0.5em;'>参照URL: ${imgUrl}</div></div>`;
                }
                previewAreaSet.appendChild(div);
            });
        } else {
            previewAreaSet.style.display = 'none';
        }
    } catch(e) {
        previewAreaSet.style.display = 'none';
    }
});

function toggleSendTiming() {
    const reserve = document.getElementById('timing_reserve').checked;
    document.getElementById('reserve_time_area').style.display = reserve ? '' : 'none';
}
window.addEventListener('DOMContentLoaded', toggleSendTiming);

// バリデーション関数
function validateForm() {
    const free = document.getElementById('mode_free').checked;
    const single = document.getElementById('mode_single').checked;
    const set = document.getElementById('mode_set').checked;
    
    if (free) {
        const freeMessage = document.getElementById('free_message').value.trim();
        if (freeMessage === '') {
            alert('メッセージを入力してください。');
            return false;
        }
        if (freeMessage.length > 1000) {
            alert('メッセージが1000文字を超えています。');
            return false;
        }
    } else if (single) {
        const templateId = document.getElementById('template_id').value;
        if (templateId === '') {
            alert('テンプレートを選択してください。');
            return false;
        }
    } else if (set) {
        const templateSetId = document.getElementById('template_set_id').value;
        if (templateSetId === '') {
            alert('テンプレートセットを選択してください。');
            return false;
        }
    }
    
    // 個別送信の場合のユーザー選択チェック
    const sendType = document.getElementById('send_type').value;
    if (sendType === 'individual') {
        const userSelect = document.querySelector('select[name="to_user_id"]');
        if (userSelect && userSelect.value === '') {
            alert('送信先ユーザーを選択してください。');
            return false;
        }
    }
    
    // 予約送信の場合の日時チェック
    const reserve = document.getElementById('timing_reserve').checked;
    if (reserve) {
        const datetime = document.getElementById('reserve_datetime').value;
        if (datetime === '') {
            alert('予約送信日時を指定してください。');
            return false;
        }
    }
    
    return true;
}

// 追加: toggleUserSelect関数
function toggleUserSelect() {
    var sendType = document.getElementById('send_type').value;
    document.getElementById('user_select_area').style.display = (sendType === 'individual') ? '' : 'none';
}
window.addEventListener('DOMContentLoaded', toggleUserSelect);
</script>
</body>
</html> 
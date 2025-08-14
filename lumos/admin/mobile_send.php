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
        $template_id = $_POST['template_id'] ?? null;
        $template_set_id = $_POST['template_set_id'] ?? null;
        $send_type = $_POST['send_type'] ?? 'active_all';
        $to_user_id = ($send_type === 'individual') ? ($_POST['to_user_id'] ?? null) : null;
        try {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LINE一斉送信管理画面</title>
    <link rel="stylesheet" href="mobile_send.css">
</head>
<body>
<div class="container">
    <div class="loading">
        <div class="loading-spinner"></div>
    </div>
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
        <div class="form-group">
            <label>送信種別：</label>
            <select name="send_type" id="send_type" onchange="toggleUserSelect()">
                <option value="active_all">宿泊中ユーザー全員</option>
                <option value="individual">特定のユーザー</option>
            </select>
        </div>

        <div id="user_select_area" style="display:none;">
            <div class="form-group">
                <label>送信先ユーザー：</label>
                <select name="to_user_id">
                    <?php foreach ($user_list as $user): ?>
                        <option value="<?php echo htmlspecialchars($user['line_user_id']); ?>"><?php echo htmlspecialchars($user['user_name']); ?>（<?php echo htmlspecialchars($user['line_user_id']); ?>）</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>送信方法：</label>
            <div class="segmented-control">
                <input type="radio" name="template_mode" id="mode_single" value="single" checked>
                <label for="mode_single">単体テンプレート</label>
                <input type="radio" name="template_mode" id="mode_set" value="set">
                <label for="mode_set">テンプレートセット</label>
            </div>
        </div>

        <div id="single_template_area">
            <div class="form-group">
                <label>メッセージテンプレート：</label>
                <select name="template_id" id="template_id" onchange="showPreview(this.value)">
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
                </select>
            </div>
        </div>

        <?php if (!empty($template_sets)): ?>
        <div id="set_template_area" style="display:none;">
            <div class="form-group">
                <label>テンプレートセット：</label>
                <select name="template_set_id" id="template_set_id">
                    <option value="">セットを選択してください</option>
                    <?php foreach ($template_sets as $set): ?>
                        <option value="<?php echo htmlspecialchars($set['id']); ?>"><?php echo htmlspecialchars($set['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>

        <div id="preview_area" style="display: none;">
            <h3>プレビュー</h3>
            <div id="text_preview" style="display: none;">
                <div class="preview-content">
                    <p id="preview_text"></p>
                </div>
            </div>
            <div id="rich_preview" style="display: none;">
                <div class="preview-content">
                    <div id="preview_image">
                        <img src="" alt="プレビュー画像">
                    </div>
                    <div id="preview_links">
                        <h4>リンク先：</h4>
                        <ul id="preview_links_list"></ul>
                    </div>
                </div>
            </div>
        </div>

        <div id="preview_area_set" style="display:none;"></div>

        <div class="form-group">
            <label>送信タイミング：</label>
            <div class="segmented-control">
                <input type="radio" name="send_timing" id="timing_now" value="now" checked>
                <label for="timing_now">今すぐ送信</label>
                <input type="radio" name="send_timing" id="timing_reserve" value="reserve">
                <label for="timing_reserve">予約送信</label>
            </div>
        </div>

        <div id="reserve_time_area" style="display:none;">
            <div class="form-group">
                <label>送信日時：</label>
                <input type="datetime-local" name="reserve_datetime" id="reserve_datetime">
            </div>
        </div>

        <button type="submit">送信</button>
    </form>
    
    <?php
    if (isset($_SESSION['send_result'])) {
        echo $_SESSION['send_result'];
        unset($_SESSION['send_result']);
    }
    ?>
</div>
<script>
// ローディング制御
const loading = document.querySelector('.loading');
function showLoading() {
    loading.style.display = 'flex';
}

function hideLoading() {
    loading.style.display = 'none';
}

// タッチイベントの最適化
document.addEventListener('DOMContentLoaded', function() {
    // タッチデバイスでのホバー効果を無効化
    if ('ontouchstart' in window) {
        document.querySelectorAll('button, select, input').forEach(function(element) {
            element.style.webkitTapHighlightColor = 'transparent';
        });
    }

    // フォーム送信時のローディング表示
    document.querySelector('form').addEventListener('submit', function() {
        showLoading();
    });

    // セグメントコントロールのラジオ切り替えでJSを動作させる
    document.querySelectorAll('input[name="template_mode"]').forEach(function(el) {
        el.addEventListener('change', toggleTemplateMode);
    });
    document.querySelectorAll('input[name="send_timing"]').forEach(function(el) {
        el.addEventListener('change', toggleSendTiming);
    });
});

// 画像の遅延読み込み
function lazyLoadImage(img) {
    const src = img.dataset.src;
    if ('loading' in HTMLImageElement.prototype) {
        img.loading = 'lazy';
        img.src = src;
    } else {
        img.src = src;
        // IntersectionObserverは省略（srcを必ずセット）
    }
}

function toggleTemplateMode() {
    const single = document.getElementById('mode_single').checked;
    document.getElementById('single_template_area').style.display = single ? '' : 'none';
    const setArea = document.getElementById('set_template_area');
    if (setArea) setArea.style.display = single ? 'none' : '';
    // プレビューもリセット
    document.getElementById('preview_area').style.display = 'none';
    document.getElementById('preview_area_set').style.display = 'none';
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
        previewImage.dataset.src = previewImageUrl;
        lazyLoadImage(previewImage);
        
        // 画像URLを表示
        let urlDisplay = document.getElementById('preview_image_url');
        if (!urlDisplay) {
            urlDisplay = document.createElement('div');
            urlDisplay.id = 'preview_image_url';
            urlDisplay.className = 'preview-url';
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
                    li.className = 'preview-link-item';
                    li.innerHTML = `
                        <strong>エリア ${index + 1}:</strong>
                        <a href="${action.linkUri}" target="_blank" class="preview-link">
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

    showLoading();
    try {
        const res = await fetch('?get_set_preview=' + encodeURIComponent(setId));
        const data = await res.json();
        if (data && data.templates && data.templates.length === 2) {
            previewAreaSet.style.display = 'block';
            data.templates.forEach((tpl, idx) => {
                const div = document.createElement('div');
                div.className = 'preview-set-item';
                div.innerHTML = `<h4>テンプレート${idx+1}：${tpl.title} (${tpl.message_type === 'text' ? 'テキスト' : 'リッチ'})</h4>`;
                if (tpl.message_type === 'text') {
                    div.innerHTML += `<div class="preview-content"><pre>${tpl.content}</pre></div>`;
                } else {
                    const imgUrl = JSON.parse(tpl.content).baseUrl + '/1040';
                    div.innerHTML += `
                        <div class="preview-content">
                            <img data-src="${imgUrl}" alt="プレビュー画像">
                            <div class="preview-url">参照URL: ${imgUrl}</div>
                        </div>
                    `;
                    const img = div.querySelector('img');
                    lazyLoadImage(img);
                }
                previewAreaSet.appendChild(div);
            });
        } else {
            previewAreaSet.style.display = 'none';
        }
    } catch(e) {
        previewAreaSet.innerHTML = '<div class="error-message">プレビューの読み込みに失敗しました</div>';
    } finally {
        hideLoading();
    }
});

function toggleSendTiming() {
    const reserve = document.getElementById('timing_reserve').checked;
    document.getElementById('reserve_time_area').style.display = reserve ? '' : 'none';
}

function toggleUserSelect() {
    const sendType = document.getElementById('send_type').value;
    document.getElementById('user_select_area').style.display = (sendType === 'individual') ? '' : 'none';
}

// 初期化
document.addEventListener('DOMContentLoaded', function() {
    toggleTemplateMode();
    toggleSendTiming();
    toggleUserSelect();

    // 画像の遅延読み込みを初期化
    document.querySelectorAll('img[data-src]').forEach(lazyLoadImage);
});
</script>
</body>
</html> 
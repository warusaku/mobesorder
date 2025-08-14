<?php
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
// POST-Redirect-GETパターン
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message_type = $_POST['message_type'] ?? 'text';
    $rich_image_uploaded = isset($_FILES['rich_image']) && is_uploaded_file($_FILES['rich_image']['tmp_name']) && !empty($_FILES['rich_image']['name']);
    if (
        ($message_type === 'text' && !empty($_POST['message'])) ||
        ($message_type === 'rich' && (
            !empty($_POST['rich_title']) ||
            !empty($_POST['rich_description']) ||
            $rich_image_uploaded
        ))
    ) {
        $message = trim($_POST['message']);
        $title = trim($_POST['template_title'] ?? '');
        if (empty($title)) {
            $_SESSION['save_result'] = '<div class="result"><p class="error">テンプレート名を入力してください。</p></div>';
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
        $send_type = $_POST['send_type'] ?? 'active_all';
        $to_user_id = $_POST['to_user_id'] ?? null;
        $base_url = 'https://mobes.online';
        // メッセージタイプで送信先APIを切り替え
        if ($message_type === 'rich') {
            $url = $base_url . '/lumos/api/lineMessage_Imagemap.php';
        } else {
            $url = $base_url . '/lumos/api/lineMessage_Tx.php';
        }
        $result_html = '';

        // 画像アップロード処理
        $image_url = null;
        if ($message_type === 'rich' && isset($_FILES['rich_image']) && $_FILES['rich_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../upload/images/';
            $file_extension = strtolower(pathinfo($_FILES['rich_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                // YYMMDD + ランダム文字列（7桁）でファイル名を生成
                $date_prefix = date('ymd');
                $random_suffix = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 7);
                $new_filename = $date_prefix . $random_suffix;
                $upload_path = $upload_dir . $new_filename . '.' . $file_extension;
                
                if (move_uploaded_file($_FILES['rich_image']['tmp_name'], $upload_path)) {
                    // 元画像を読み込み
                    $source_image = null;
                    switch ($file_extension) {
                        case 'jpg':
                        case 'jpeg':
                            $source_image = imagecreatefromjpeg($upload_path);
                            break;
                        case 'png':
                            $source_image = imagecreatefrompng($upload_path);
                            break;
                        case 'gif':
                            $source_image = imagecreatefromgif($upload_path);
                            break;
                    }

                    if ($source_image) {
                        // 元画像のサイズを取得
                        $source_width = imagesx($source_image);
                        $source_height = imagesy($source_image);
                        
                        // 必要なサイズの配列
                        $sizes = [
                            '240' => 240,
                            '300' => 300,
                            '460' => 460,
                            '700' => 700,
                            '1040' => 1040
                        ];

                        // 各サイズの画像を生成
                        foreach ($sizes as $size_name => $size) {
                            $resized = imagecreatetruecolor($size, $size);
                            
                            // アスペクト比を保持しながら中央部分を切り取る
                            $source_ratio = $source_width / $source_height;
                            $target_ratio = 1; // 正方形
                            
                            if ($source_ratio > $target_ratio) {
                                // 元画像が横長の場合
                                $new_width = $source_height;
                                $new_height = $source_height;
                                $x_offset = ($source_width - $new_width) / 2;
                                $y_offset = 0;
                            } else {
                                // 元画像が縦長の場合
                                $new_width = $source_width;
                                $new_height = $source_width;
                                $x_offset = 0;
                                $y_offset = ($source_height - $new_height) / 2;
                            }
                            
                            // 画像のリサイズと中央部分の切り取り
                            imagecopyresampled(
                                $resized, $source_image,
                                0, 0, $x_offset, $y_offset,
                                $size, $size,
                                $new_width, $new_height
                            );
                            
                            // 通常サイズを保存
                            $resized_path = $upload_dir . $new_filename . '/' . $size_name;
                            if (!is_dir(dirname($resized_path))) {
                                mkdir(dirname($resized_path), 0777, true);
                            }
                            imagejpeg($resized, $resized_path, 90);
                            
                            // @2xサイズを保存（通常サイズの2倍）
                            $resized_2x = imagecreatetruecolor($size * 2, $size * 2);
                            imagecopyresampled(
                                $resized_2x, $source_image,
                                0, 0, $x_offset, $y_offset,
                                $size * 2, $size * 2,
                                $new_width, $new_height
                            );
                            $resized_2x_path = $upload_dir . $new_filename . '/' . $size_name . '@2x';
                            imagejpeg($resized_2x, $resized_2x_path, 90);
                            
                            // メモリ解放
                            imagedestroy($resized);
                            imagedestroy($resized_2x);
                        }

                        // 元画像を削除
                        unlink($upload_path);
                        imagedestroy($source_image);

                        // 画像URLを設定（拡張子なし）
                        $image_url = $base_url . '/lumos/upload/images/' . $new_filename;
                    }
                }
            }
        }

        // メッセージデータの構築
        $alt_text = !empty($_POST['rich_title']) ? $_POST['rich_title'] : '画像メッセージ';
        $message_data = [];
        if ($message_type === 'text') {
            $message_data = [
                'type' => 'text',
                'text' => $message
            ];
        } else {
            // テンプレート種別とリンク配列を取得
            $template_type = $_POST['template_type'] ?? 'block1';
            $template_links = $_POST['template_links'] ?? [];
            // テンプレートごとのエリア定義
            $template_areas = [
                'block1' => [
                    [ 'x' => 0, 'y' => 0, 'width' => 1040, 'height' => 1040 ]
                ],
                'block2yoko' => [
                    [ 'x' => 0, 'y' => 0, 'width' => 520, 'height' => 1040 ],
                    [ 'x' => 520, 'y' => 0, 'width' => 520, 'height' => 1040 ]
                ],
                'block2tate' => [
                    [ 'x' => 0, 'y' => 0, 'width' => 1040, 'height' => 520 ],
                    [ 'x' => 0, 'y' => 520, 'width' => 1040, 'height' => 520 ]
                ],
                'block4' => [
                    [ 'x' => 0, 'y' => 0, 'width' => 520, 'height' => 520 ],
                    [ 'x' => 520, 'y' => 0, 'width' => 520, 'height' => 520 ],
                    [ 'x' => 0, 'y' => 520, 'width' => 520, 'height' => 520 ],
                    [ 'x' => 520, 'y' => 520, 'width' => 520, 'height' => 520 ]
                ]
            ];
            $areas = $template_areas[$template_type];
            $actions = [];
            foreach ($areas as $i => $area) {
                $actions[] = [
                    'type' => 'uri',
                    'linkUri' => $template_links[$i] ?? '',
                    'area' => $area
                ];
            }
            // 画像URLを決定
            $base_url_img = null;
            if ($message_type === 'rich') {
                if ($rich_image_uploaded && isset($image_url) && $image_url) {
                    // アップロード画像を使う
                    $base_url_img = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '', $image_url);
                } else {
                    // 画像が選択されていない場合のエラー処理
                    $_SESSION['send_result'] = '<div class="result"><p class="error">画像が選択されていません。</p></div>';
                    header('Location: ' . $_SERVER['REQUEST_URI']);
                    exit;
                }
            }
            $message_data = [
                'type' => 'imagemap',
                'baseUrl' => $base_url_img,
                'altText' => $alt_text,
                'baseSize' => [
                    'width' => 1040,
                    'height' => 1040
                ],
                'actions' => $actions
            ];
        }

        // DBへの保存
        try {
            $sql = "INSERT INTO message_templates (
                title, message_type, content, created_at, updated_at
            ) VALUES (?, ?, ?, NOW(), NOW())";
            
            $stmt = $pdo->prepare($sql);
            $content = ($message_type === 'rich') ? json_encode($message_data, JSON_UNESCAPED_UNICODE) : $message;
            $stmt->execute([$title, $message_type, $content]);
            
            $_SESSION['save_result'] = '<div class="result"><p class="success">メッセージテンプレートを保存しました。</p></div>';
        } catch (Exception $e) {
            $_SESSION['save_result'] = '<div class="result"><p class="error">保存に失敗しました：' . htmlspecialchars($e->getMessage()) . '</p></div>';
        }
        
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}
// --- テンプレートセット保存処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_title'], $_POST['set_template_1'], $_POST['set_template_2'])) {
    $set_title = trim($_POST['set_title']);
    $set_template_1 = intval($_POST['set_template_1']);
    $set_template_2 = intval($_POST['set_template_2']);
    if ($set_title && $set_template_1 && $set_template_2 && $set_template_1 !== $set_template_2) {
        try {
            $sql = "INSERT INTO message_template_sets (title, template_id_1, template_id_2, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$set_title, $set_template_1, $set_template_2]);
            $_SESSION['save_result'] = '<div class="result"><p class="success">テンプレートセットを保存しました。</p></div>';
        } catch (Exception $e) {
            $_SESSION['save_result'] = '<div class="result"><p class="error">セット保存に失敗しました：' . htmlspecialchars($e->getMessage()) . '</p></div>';
        }
    } else {
        $_SESSION['save_result'] = '<div class="result"><p class="error">セット名と2つの異なるテンプレートを選択してください。</p></div>';
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

// 既存テンプレート一覧の取得
$templates = [];
try {
    $stmt = $pdo->query("SELECT id, title, message_type FROM message_templates ORDER BY created_at DESC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $templates = [];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>メッセージテンプレート作成</title>
    <link rel="stylesheet" href="send.css">
</head>
<body>
<div class="container">
    <h1>メッセージテンプレート作成</h1>
    
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

    <form method="post" enctype="multipart/form-data">
        <label>テンプレート名：</label><br>
        <input type="text" name="template_title" required style="width: 100%; margin-bottom: 1em;"><br>
        
        <label>メッセージタイプ：</label>
        <select name="message_type" id="message_type">
            <option value="text">テキストメッセージ</option>
            <option value="rich">リッチメッセージ</option>
        </select><br><br>
        
        <div id="text_message_area">
            <label>送信メッセージ：</label><br>
            <textarea name="message" required></textarea><br>
        </div>
        
        <div id="rich_message_area" style="display:none;">
            <label>テンプレート選択：</label><br>
            <div id="template_select" style="display: flex; gap: 1em; margin-bottom: 1em;">
                <label style="text-align: center; cursor: pointer;">
                    <input type="radio" name="template_type" value="block1" checked style="display:none;">
                    <img src="/lumos/template/block1-100.jpg" alt="1分割" style="width: 80px; border: 2px solid #2ecc40; border-radius: 4px; display: block; margin-bottom: 0.5em;">
                    <div>通常</div>
                </label>
                <label style="text-align: center; cursor: pointer;">
                    <input type="radio" name="template_type" value="block2yoko" style="display:none;">
                    <img src="/lumos/template/block2yoko-100.jpg" alt="横2分割" style="width: 80px; border: 2px solid #ccc; border-radius: 4px; display: block; margin-bottom: 0.5em;">
                    <div>横2分割</div>
                </label>
                <label style="text-align: center; cursor: pointer;">
                    <input type="radio" name="template_type" value="block2tate" style="display:none;">
                    <img src="/lumos/template/block2tate-100.jpg" alt="縦2分割" style="width: 80px; border: 2px solid #ccc; border-radius: 4px; display: block; margin-bottom: 0.5em;">
                    <div>縦2分割</div>
                </label>
                <label style="text-align: center; cursor: pointer;">
                    <input type="radio" name="template_type" value="block4" style="display:none;">
                    <img src="/lumos/template/block4-100.jpg" alt="4分割" style="width: 80px; border: 2px solid #ccc; border-radius: 4px; display: block; margin-bottom: 0.5em;">
                    <div>4分割</div>
                </label>
            </div>
            <div id="template_links_area"></div>
            <label>タイトル：</label><br>
            <input type="text" name="rich_title" style="width: 100%; margin-bottom: 1em;"><br>
            <span style="color:#e57373; font-size:0.95em;">※この内容はLINEの通知で表示されます</span><br>
            
            <label>画像：</label><br>
            <div id="drop_zone" style="border: 2px dashed #ccc; padding: 20px; text-align: center; margin-bottom: 1em; background: #fafafa; cursor: pointer;">
                <input type="file" name="rich_image" id="rich_image" accept="image/*" style="display: none;">
                <div id="drop_text">
                    <p style="margin: 0; color: #666;">画像をドラッグ＆ドロップ<br>または<br>クリックしてファイルを選択</p>
                </div>
            </div>
            <div id="image_preview" style="margin: 1em 0; display: none;">
                <h3>プレビュー</h3>
                <div>
                    <p>通常サイズ (1040×1040)</p>
                    <img id="preview_normal" style="max-width: 300px; border: 1px solid #ccc;">
                </div>
                <div id="image_info" style="margin-top: 1em; font-size: 0.9em; color: #666;"></div>
            </div>
            <span style="color:#e57373; font-size:0.95em;">※LINE公式リッチメッセージの基本サイズは <b>1040×1040px</b>（正方形）です。アップロードされた画像は自動的に正方形にリサイズされ、中央部分が切り取られます。ファイルサイズが1MBを超える場合は画質が自動的に調整されます。</span><br><br>
        </div>
        
        <button type="submit">テンプレートを保存</button>
    </form>

    <hr style="margin:2em 0;">
    <h2>テンプレートセット作成（画像＋紹介文など）</h2>
    <form method="post">
        <label>セット名：</label><br>
        <input type="text" name="set_title" required style="width: 100%; margin-bottom: 1em;"><br>
        <label>1つ目のテンプレート：</label><br>
        <select name="set_template_1" required style="width: 100%; margin-bottom: 1em;">
            <option value="">選択してください</option>
            <?php foreach ($templates as $t): ?>
                <option value="<?php echo htmlspecialchars($t['id']); ?>"><?php echo htmlspecialchars($t['title']); ?> (<?php echo $t['message_type'] === 'text' ? 'テキスト' : 'リッチ'; ?>)</option>
            <?php endforeach; ?>
        </select><br>
        <label>2つ目のテンプレート：</label><br>
        <select name="set_template_2" required style="width: 100%; margin-bottom: 1em;">
            <option value="">選択してください</option>
            <?php foreach ($templates as $t): ?>
                <option value="<?php echo htmlspecialchars($t['id']); ?>"><?php echo htmlspecialchars($t['title']); ?> (<?php echo $t['message_type'] === 'text' ? 'テキスト' : 'リッチ'; ?>)</option>
            <?php endforeach; ?>
        </select><br>
        <button type="submit">テンプレートセットを保存</button>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // メッセージタイプ切り替え
        function toggleMessageForm() {
            const type = document.getElementById('message_type').value;
            const textArea = document.querySelector('textarea[name="message"]');
            document.getElementById('text_message_area').style.display = (type === 'text') ? '' : 'none';
            document.getElementById('rich_message_area').style.display = (type === 'rich') ? '' : 'none';
            
            // required属性の制御
            if (type === 'text') {
                textArea.setAttribute('required', 'required');
            } else {
                textArea.removeAttribute('required');
            }
        }
        document.getElementById('message_type').addEventListener('change', toggleMessageForm);
        toggleMessageForm();

        // テンプレート選択に応じたリンク入力欄の動的生成
        const templateTypes = {
            block1: 1,
            block2yoko: 2,
            block2tate: 2,
            block4: 4
        };
        
        function updateTemplateLinks() {
            const selected = document.querySelector('input[name="template_type"]:checked');
            if (!selected) return;
            
            const templateType = selected.value;
            const num = templateTypes[templateType];
            const area = document.getElementById('template_links_area');
            let html = '';
            
            for (let i = 1; i <= num; i++) {
                html += `<div style='margin-bottom:0.5em;'><label>リンク${i}：</label><input type='text' name='template_links[]' style='width:80%;'></div>`;
            }
            area.innerHTML = html;
        }

        // テンプレート画像クリックでラジオボタンを選択
        document.querySelectorAll('#template_select label').forEach(function(label) {
            label.addEventListener('click', function() {
                // すべての画像の枠線をリセット
                document.querySelectorAll('#template_select img').forEach(function(img) {
                    img.style.border = '2px solid #ccc';
                });
                // このラベル内のinputをcheckedに
                var radio = label.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    // 枠線を強調
                    label.querySelector('img').style.border = '2px solid #2ecc40';
                    // リンク欄を更新
                    updateTemplateLinks();
                }
            });
        });
        
        // 初期選択の枠線を強調
        var checkedRadio = document.querySelector('#template_select input[type="radio"]:checked');
        if (checkedRadio) {
            checkedRadio.closest('label').querySelector('img').style.border = '2px solid #2ecc40';
        }
        
        // 初期表示時にリンク欄を生成
        updateTemplateLinks();
        
        // 画像アップロード機能: drop_zoneクリック時にファイル選択ダイアログを開く
        const dropZone = document.getElementById('drop_zone');
        const fileInput = document.getElementById('rich_image');
        if (dropZone && fileInput) {
            dropZone.addEventListener('click', function() {
                fileInput.click();
            });
            // ファイル選択時のプレビュー表示
            fileInput.addEventListener('change', function() {
                previewImage(this);
            });
        }

        // 画像プレビュー機能
        function previewImage(input) {
            const preview = document.getElementById('image_preview');
            const previewNormal = document.getElementById('preview_normal');
            const imageInfo = document.getElementById('image_info');

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.style.display = 'block';
                    previewNormal.src = e.target.result;
                    imageInfo.textContent = ''; // 情報をクリア
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
                previewNormal.src = '';
                imageInfo.textContent = '';
            }
        }
    });
    </script>
    <?php
    if (isset($_SESSION['save_result'])) {
        echo $_SESSION['save_result'];
        unset($_SESSION['save_result']);
    }
    ?>
</div>
</body>
</html> 
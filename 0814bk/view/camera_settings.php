<?php
/**
 * RTSPカメラ設定インターフェース
 * 
 * このファイルはRTSPカメラの設定を管理するためのWeb UIを提供します。
 * 
 * @author Hideaki Kurata
 * @created 2023-06-15
 */

// 必要なファイルをインクルード
require_once(dirname(__FILE__) . '/../includes/config.php');
require_once(dirname(__FILE__) . '/../includes/db_connect.php');
require_once(dirname(__FILE__) . '/../includes/functions.php');
require_once(dirname(__FILE__) . '/../includes/auth.php');
require_once(dirname(__FILE__) . '/../includes/template_manager.php');

// セッション開始
session_start();

// 認証チェック
if (!is_authenticated()) {
    header('Location: login.php');
    exit;
}

// メッセージ初期化
$message = '';
$message_type = '';

// テンプレートマネージャーの初期化
$template_manager = new TemplateManager($db_conn);
$available_templates = $template_manager->getAllTemplates();
// テンプレートをメーカー別にグループ化
$templates_by_manufacturer = [];
foreach ($available_templates as $template) {
    if (!isset($templates_by_manufacturer[$template['manufacturer']])) {
        $templates_by_manufacturer[$template['manufacturer']] = [];
    }
    $templates_by_manufacturer[$template['manufacturer']][] = $template;
}

// カメラ情報の取得
$cameras = [];
try {
    $query = "SELECT * FROM camera_settings ORDER BY name ASC";
    $result = $db_conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $cameras[] = $row;
        }
        $result->free();
    }
} catch (Exception $e) {
    $message = 'カメラ情報の取得に失敗しました: ' . $e->getMessage();
    $message_type = 'error';
}

// カメラの新規追加または更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' || $_POST['action'] === 'update') {
        $camera_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $rtsp_url = isset($_POST['rtsp_url']) ? trim($_POST['rtsp_url']) : '';
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $frame_rate = isset($_POST['frame_rate']) ? (int)$_POST['frame_rate'] : 10;
        $resolution = isset($_POST['resolution']) ? trim($_POST['resolution']) : '640x480';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        
        // テンプレート関連の情報を取得
        $manufacturer = isset($_POST['manufacturer']) ? trim($_POST['manufacturer']) : '';
        $model = isset($_POST['model']) ? trim($_POST['model']) : '';
        $template_id = isset($_POST['template_id']) ? trim($_POST['template_id']) : '';
        $extra_settings = isset($_POST['extra_settings']) ? trim($_POST['extra_settings']) : '';
        
        // OCR設定を取得
        $ocr_mode = isset($_POST['ocr_mode']) ? trim($_POST['ocr_mode']) : 'generic';
        
        // 7セグメントOCR設定を作成
        $ocr_segment_config = null;
        if ($ocr_mode === 'segment') {
            $digit_mode = isset($_POST['digit_mode']) ? trim($_POST['digit_mode']) : 'variable';
            $digit_count = isset($_POST['digit_count']) ? (int)$_POST['digit_count'] : 6;
            $max_digits = isset($_POST['max_digits']) ? (int)$_POST['max_digits'] : 8;
            $char_set = isset($_POST['char_set']) ? trim($_POST['char_set']) : 'numeric';
            $orientation = isset($_POST['orientation']) ? trim($_POST['orientation']) : 'horizontal';
            $segment_color = isset($_POST['segment_color']) ? trim($_POST['segment_color']) : 'light';
            $segment_width = isset($_POST['segment_width']) ? trim($_POST['segment_width']) : 'normal';
            $segment_shape = isset($_POST['segment_shape']) ? trim($_POST['segment_shape']) : 'straight';
            
            $ocr_segment_config = json_encode([
                'digit_mode' => $digit_mode,
                'digit_count' => $digit_count,
                'max_digits' => $max_digits,
                'char_set' => $char_set,
                'orientation' => $orientation,
                'segment_color' => $segment_color,
                'segment_width' => $segment_width,
                'segment_shape' => $segment_shape
            ]);
        }
        
        // 入力検証
        if (empty($name)) {
            $message = 'カメラ名は必須です。';
            $message_type = 'error';
        } elseif (empty($rtsp_url)) {
            $message = 'RTSP URLは必須です。';
            $message_type = 'error';
        } else {
            try {
                if ($_POST['action'] === 'add') {
                    // 新規追加
                    $query = "INSERT INTO camera_settings (name, rtsp_url, username, password, enabled, frame_rate, resolution, 
                              description, manufacturer, model, template_id, extra_settings, ocr_mode, ocr_segment_config, created_at, updated_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                    $stmt = $db_conn->prepare($query);
                    $stmt->bind_param("ssssissssssss", $name, $rtsp_url, $username, $password, $enabled, 
                                      $frame_rate, $resolution, $description, $manufacturer, $model, $template_id, $extra_settings, 
                                      $ocr_mode, $ocr_segment_config);
                    
                    if ($stmt->execute()) {
                        $message = 'カメラを追加しました。';
                        $message_type = 'success';
                        
                        // 変更をsync_changesテーブルに記録
                        $new_id = $stmt->insert_id;
                        log_sync_change('camera_settings', $new_id, 'INSERT', 'REMOTE');
                    } else {
                        $message = 'カメラの追加に失敗しました。';
                        $message_type = 'error';
                    }
                } else {
                    // 更新
                    // パスワードが空の場合は更新しない
                    if (empty($password)) {
                        $query = "UPDATE camera_settings 
                                  SET name = ?, rtsp_url = ?, username = ?, 
                                      enabled = ?, frame_rate = ?, resolution = ?, description = ?,
                                      manufacturer = ?, model = ?, template_id = ?, extra_settings = ?,
                                      ocr_mode = ?, ocr_segment_config = ?, updated_at = NOW() 
                                  WHERE id = ?";
                        $stmt = $db_conn->prepare($query);
                        $stmt->bind_param("ssssissssssssi", $name, $rtsp_url, $username, $enabled, 
                                          $frame_rate, $resolution, $description, $manufacturer, $model, $template_id, $extra_settings, 
                                          $ocr_mode, $ocr_segment_config, $camera_id);
                    } else {
                        $query = "UPDATE camera_settings 
                                  SET name = ?, rtsp_url = ?, username = ?, password = ?, 
                                      enabled = ?, frame_rate = ?, resolution = ?, description = ?,
                                      manufacturer = ?, model = ?, template_id = ?, extra_settings = ?,
                                      ocr_mode = ?, ocr_segment_config = ?, updated_at = NOW() 
                                  WHERE id = ?";
                        $stmt = $db_conn->prepare($query);
                        $stmt->bind_param("sssssissssssssi", $name, $rtsp_url, $username, $password, $enabled, 
                                          $frame_rate, $resolution, $description, $manufacturer, $model, $template_id, $extra_settings, 
                                          $ocr_mode, $ocr_segment_config, $camera_id);
                    }
                    
                    if ($stmt->execute()) {
                        $message = 'カメラ設定を更新しました。';
                        $message_type = 'success';
                        
                        // 変更をsync_changesテーブルに記録
                        log_sync_change('camera_settings', $camera_id, 'UPDATE', 'REMOTE');
                    } else {
                        $message = 'カメラ設定の更新に失敗しました。';
                        $message_type = 'error';
                    }
                }
                
                // カメラリストを更新
                $result = $db_conn->query("SELECT * FROM camera_settings ORDER BY name ASC");
                $cameras = [];
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $cameras[] = $row;
                    }
                    $result->free();
                }
                
            } catch (Exception $e) {
                $message = 'データベースエラー: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        // カメラ削除
        $camera_id = (int)$_POST['id'];
        
        try {
            $query = "DELETE FROM camera_settings WHERE id = ?";
            $stmt = $db_conn->prepare($query);
            $stmt->bind_param("i", $camera_id);
            
            if ($stmt->execute()) {
                $message = 'カメラを削除しました。';
                $message_type = 'success';
                
                // 変更をsync_changesテーブルに記録
                log_sync_change('camera_settings', $camera_id, 'DELETE', 'REMOTE');
                
                // カメラリストを更新
                $result = $db_conn->query("SELECT * FROM camera_settings ORDER BY name ASC");
                $cameras = [];
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $cameras[] = $row;
                    }
                    $result->free();
                }
            } else {
                $message = 'カメラの削除に失敗しました。';
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $message = 'データベースエラー: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// 同期変更をログに記録する関数
function log_sync_change($table_name, $record_id, $change_type, $origin, $status = 'PENDING') {
    global $db_conn;
    
    try {
        $query = "INSERT INTO sync_changes 
                 (table_name, record_id, change_type, origin, sync_status, priority) 
                 VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db_conn->prepare($query);
        $priority = 8; // カメラ設定は優先度高め
        $stmt->bind_param("sisssi", $table_name, $record_id, $change_type, $origin, $status, $priority);
        $stmt->execute();
        return true;
    } catch (Exception $e) {
        error_log("同期変更ログ記録エラー: " . $e->getMessage());
        return false;
    }
}

// ページタイトル
$page_title = 'RTSPカメラ設定';
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - RTSP Reader</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .camera-card {
            margin-bottom: 20px;
        }
        .camera-actions {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .camera-enabled {
            position: absolute;
            top: 10px;
            left: 10px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
        }
        .enabled-true {
            background-color: #28a745;
        }
        .enabled-false {
            background-color: #dc3545;
        }
        .rtsp-url {
            word-break: break-all;
            font-size: 0.8rem;
            color: #6c757d;
        }
        .camera-preview {
            width: 100%;
            height: 150px;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php include_once('../includes/header.php'); ?>
    
    <div class="container mt-4">
        <h1><?php echo $page_title; ?></h1>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo ($message_type === 'success') ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-12">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addCameraModal">
                    <i class="fas fa-plus"></i> カメラを追加
                </button>
                <button type="button" class="btn btn-info ml-2" id="syncNowBtn">
                    <i class="fas fa-sync"></i> 今すぐ同期
                </button>
                <button type="button" class="btn btn-secondary ml-2" data-toggle="modal" data-target="#logModal">
                    <i class="fas fa-list-alt"></i> ログ表示
                </button>
            </div>
        </div>
        
        <div class="row">
            <?php if (empty($cameras)): ?>
                <div class="col-md-12">
                    <div class="alert alert-info">
                        登録されているカメラはありません。「カメラを追加」ボタンから新しいカメラを登録してください。
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($cameras as $camera): ?>
                    <div class="col-md-4">
                        <div class="card camera-card">
                            <div class="camera-preview">
                                <i class="fas fa-video fa-3x text-muted"></i>
                            </div>
                            <div class="camera-enabled enabled-<?php echo $camera['enabled'] ? 'true' : 'false'; ?>" 
                                 title="<?php echo $camera['enabled'] ? '有効' : '無効'; ?>"></div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($camera['name']); ?></h5>
                                <p class="card-text rtsp-url"><?php echo htmlspecialchars($camera['rtsp_url']); ?></p>
                                <p class="card-text">
                                    <small class="text-muted">
                                        解像度: <?php echo htmlspecialchars($camera['resolution']); ?>, 
                                        フレームレート: <?php echo $camera['frame_rate']; ?> fps
                                    </small>
                                </p>
                                <?php if (!empty($camera['manufacturer']) || !empty($camera['model'])): ?>
                                <p class="card-text">
                                    <small class="text-muted">
                                        <?php if (!empty($camera['manufacturer'])): ?>
                                            メーカー: <?php echo htmlspecialchars($camera['manufacturer']); ?> 
                                        <?php endif; ?>
                                        <?php if (!empty($camera['model'])): ?>
                                            モデル: <?php echo htmlspecialchars($camera['model']); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($camera['template_id'])): ?>
                                            <br>テンプレート: <?php echo htmlspecialchars($camera['template_id']); ?>
                                        <?php endif; ?>
                                    </small>
                                </p>
                                <?php endif; ?>
                                <?php if (!empty($camera['description'])): ?>
                                    <p class="card-text"><?php echo htmlspecialchars($camera['description']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($camera['last_connection_status'])): ?>
                                <p class="card-text">
                                    <small class="<?php echo $camera['last_connection_status'] === 'success' ? 'text-success' : 'text-danger'; ?>">
                                        最終接続: <?php echo $camera['last_connection_status'] === 'success' ? '成功' : '失敗'; ?>
                                        <?php if (!empty($camera['last_connection_time'])): ?>
                                            (<?php echo date('Y-m-d H:i:s', strtotime($camera['last_connection_time'])); ?>)
                                        <?php endif; ?>
                                    </small>
                                </p>
                                <?php endif; ?>
                            </div>
                            <div class="camera-actions">
                                <button type="button" class="btn btn-sm btn-outline-primary edit-camera" 
                                        data-id="<?php echo $camera['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($camera['name']); ?>"
                                        data-rtsp-url="<?php echo htmlspecialchars($camera['rtsp_url']); ?>"
                                        data-username="<?php echo htmlspecialchars($camera['username']); ?>"
                                        data-password="<?php echo htmlspecialchars($camera['password']); ?>"
                                        data-enabled="<?php echo $camera['enabled']; ?>"
                                        data-frame-rate="<?php echo $camera['frame_rate']; ?>"
                                        data-resolution="<?php echo htmlspecialchars($camera['resolution']); ?>"
                                        data-description="<?php echo htmlspecialchars($camera['description']); ?>"
                                        data-manufacturer="<?php echo htmlspecialchars($camera['manufacturer'] ?? ''); ?>"
                                        data-model="<?php echo htmlspecialchars($camera['model'] ?? ''); ?>"
                                        data-template-id="<?php echo htmlspecialchars($camera['template_id'] ?? ''); ?>"
                                        data-extra-settings="<?php echo htmlspecialchars($camera['extra_settings'] ?? ''); ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-camera" 
                                        data-id="<?php echo $camera['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($camera['name']); ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- カメラ追加モーダル -->
    <div class="modal fade" id="addCameraModal" tabindex="-1" role="dialog" aria-labelledby="addCameraModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCameraModalLabel">カメラを追加</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <ul class="nav nav-tabs" id="cameraTabAdd" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="basic-tab-add" data-toggle="tab" href="#basic-add" role="tab" aria-controls="basic-add" aria-selected="true">基本設定</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="template-tab-add" data-toggle="tab" href="#template-add" role="tab" aria-controls="template-add" aria-selected="false">テンプレート</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="ocr-tab-add" data-toggle="tab" href="#ocr-add" role="tab" aria-controls="ocr-add" aria-selected="false">OCR設定</a>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="cameraTabAddContent">
                            <!-- 基本設定タブ -->
                            <div class="tab-pane fade show active" id="basic-add" role="tabpanel" aria-labelledby="basic-tab-add">
                                <div class="mt-3">
                                    <div class="form-group">
                                        <label for="name">カメラ名 <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="rtsp_url">RTSP URL <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="rtsp_url" name="rtsp_url" required
                                               placeholder="例: rtsp://192.168.33.10:554/stream">
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="username">ユーザー名</label>
                                            <input type="text" class="form-control" id="username" name="username">
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="password">パスワード</label>
                                            <input type="password" class="form-control" id="password" name="password">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="enabled" name="enabled" checked>
                                            <label class="custom-control-label" for="enabled">有効</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- テンプレートタブ -->
                            <div class="tab-pane fade" id="template-add" role="tabpanel" aria-labelledby="template-tab-add">
                                <div class="mt-3">
                                    <div class="form-group">
                                        <label for="manufacturer">メーカー</label>
                                        <select class="form-control" id="manufacturer" name="manufacturer">
                                            <option value="">選択してください</option>
                                            <?php foreach (array_keys($templates_by_manufacturer) as $manufacturer): ?>
                                                <option value="<?php echo htmlspecialchars($manufacturer); ?>">
                                                    <?php echo htmlspecialchars($manufacturer); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="model">モデル</label>
                                        <input type="text" class="form-control" id="model" name="model">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="template_id">テンプレート</label>
                                        <select class="form-control" id="template_id" name="template_id">
                                            <option value="">選択してください</option>
                                            <?php foreach ($templates_by_manufacturer as $manufacturer => $templates): ?>
                                                <optgroup label="<?php echo htmlspecialchars($manufacturer); ?>" class="template-group" data-manufacturer="<?php echo htmlspecialchars($manufacturer); ?>">
                                                    <?php foreach ($templates as $template): ?>
                                                        <option value="<?php echo htmlspecialchars($template['template_id']); ?>" data-manufacturer="<?php echo htmlspecialchars($manufacturer); ?>">
                                                            <?php echo htmlspecialchars($template['description']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="card">
                                            <div class="card-header">
                                                テンプレート情報
                                            </div>
                                            <div class="card-body" id="template_info_add">
                                                <p class="text-muted">テンプレートを選択すると、詳細情報がここに表示されます。</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- OCR設定タブ -->
                            <div class="tab-pane fade" id="ocr-add" role="tabpanel" aria-labelledby="ocr-tab-add">
                                <div class="mt-3">
                                    <div class="form-group">
                                        <label for="ocr_mode">OCRモード</label>
                                        <select class="form-control" id="ocr_mode" name="ocr_mode">
                                            <option value="generic">汎用OCR</option>
                                            <option value="segment">7セグメントOCR</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">キャンセル</button>
                        <button type="submit" class="btn btn-primary">追加</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- カメラ編集モーダル -->
    <div class="modal fade" id="editCameraModal" tabindex="-1" role="dialog" aria-labelledby="editCameraModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCameraModalLabel">カメラを編集</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <ul class="nav nav-tabs" id="cameraTabEdit" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="basic-tab-edit" data-toggle="tab" href="#basic-edit" role="tab" aria-controls="basic-edit" aria-selected="true">基本設定</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="template-tab-edit" data-toggle="tab" href="#template-edit" role="tab" aria-controls="template-edit" aria-selected="false">テンプレート</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="ocr-tab-edit" data-toggle="tab" href="#ocr-edit" role="tab" aria-controls="ocr-edit" aria-selected="false">OCR設定</a>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="cameraTabEditContent">
                            <!-- 基本設定タブ -->
                            <div class="tab-pane fade show active" id="basic-edit" role="tabpanel" aria-labelledby="basic-tab-edit">
                                <div class="mt-3">
                                    <div class="form-group">
                                        <label for="edit_name">カメラ名 <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="edit_name" name="name" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="edit_rtsp_url">RTSP URL <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="edit_rtsp_url" name="rtsp_url" required>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="edit_username">ユーザー名</label>
                                            <input type="text" class="form-control" id="edit_username" name="username">
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="edit_password">パスワード</label>
                                            <input type="password" class="form-control" id="edit_password" name="password" placeholder="(変更しない場合は空白)">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="edit_enabled" name="enabled">
                                            <label class="custom-control-label" for="edit_enabled">有効</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- テンプレートタブ -->
                            <div class="tab-pane fade" id="template-edit" role="tabpanel" aria-labelledby="template-tab-edit">
                                <div class="mt-3">
                                    <div class="form-group">
                                        <label for="edit_manufacturer">メーカー</label>
                                        <select class="form-control" id="edit_manufacturer" name="manufacturer">
                                            <option value="">選択してください</option>
                                            <?php foreach (array_keys($templates_by_manufacturer) as $manufacturer): ?>
                                                <option value="<?php echo htmlspecialchars($manufacturer); ?>">
                                                    <?php echo htmlspecialchars($manufacturer); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="edit_model">モデル</label>
                                        <input type="text" class="form-control" id="edit_model" name="model">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="edit_template_id">テンプレート</label>
                                        <select class="form-control" id="edit_template_id" name="template_id">
                                            <option value="">選択してください</option>
                                            <?php foreach ($templates_by_manufacturer as $manufacturer => $templates): ?>
                                                <optgroup label="<?php echo htmlspecialchars($manufacturer); ?>" class="template-group" data-manufacturer="<?php echo htmlspecialchars($manufacturer); ?>">
                                                    <?php foreach ($templates as $template): ?>
                                                        <option value="<?php echo htmlspecialchars($template['template_id']); ?>" data-manufacturer="<?php echo htmlspecialchars($manufacturer); ?>">
                                                            <?php echo htmlspecialchars($template['description']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="card">
                                            <div class="card-header">
                                                テンプレート情報
                                            </div>
                                            <div class="card-body" id="template_info_edit">
                                                <p class="text-muted">テンプレートを選択すると、詳細情報がここに表示されます。</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- OCR設定タブ -->
                            <div class="tab-pane fade" id="ocr-edit" role="tabpanel" aria-labelledby="ocr-tab-edit">
                                <div class="mt-3">
                                    <div class="form-group">
                                        <label for="edit_ocr_mode">OCRモード</label>
                                        <select class="form-control" id="edit_ocr_mode" name="ocr_mode">
                                            <option value="generic">汎用OCR</option>
                                            <option value="segment">7セグメントOCR</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">キャンセル</button>
                        <button type="submit" class="btn btn-primary">更新</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- カメラ削除確認モーダル -->
    <div class="modal fade" id="deleteCameraModal" tabindex="-1" role="dialog" aria-labelledby="deleteCameraModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteCameraModalLabel">カメラを削除</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>カメラ "<span id="delete_camera_name"></span>" を削除してもよろしいですか？</p>
                    <p class="text-danger">この操作は取り消せません。</p>
                </div>
                <div class="modal-footer">
                    <form method="post" action="">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_id">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">キャンセル</button>
                        <button type="submit" class="btn btn-danger">削除</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ログ表示モーダル -->
    <div class="modal fade" id="logModal" tabindex="-1" role="dialog" aria-labelledby="logModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logModalLabel">システムログ</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">表示行数</span>
                                </div>
                                <select id="logLines" class="form-control">
                                    <option value="50">50行</option>
                                    <option value="100" selected>100行</option>
                                    <option value="200">200行</option>
                                    <option value="500">500行</option>
                                    <option value="1000">1000行</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">フィルター</span>
                                </div>
                                <input type="text" id="logFilter" class="form-control" placeholder="キーワードでフィルター...">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" id="applyLogFilter">適用</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="logModalContent" style="max-height: 500px; overflow-y: auto; font-family: monospace; white-space: pre-wrap; background-color: #f8f9fa; padding: 10px; border-radius: 4px;">
                        <div class="d-flex justify-content-center my-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">読み込み中...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>
                    <button type="button" class="btn btn-primary" id="refreshLogs">
                        <i class="fas fa-sync-alt"></i> 更新
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../js/jquery-3.5.1.min.js"></script>
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // 編集ボタンのクリックイベント
            $('.edit-camera').click(function() {
                var id = $(this).data('id');
                var name = $(this).data('name');
                var rtspUrl = $(this).data('rtsp-url');
                var username = $(this).data('username');
                var enabled = $(this).data('enabled');
                var frameRate = $(this).data('frame-rate');
                var resolution = $(this).data('resolution');
                var description = $(this).data('description');
                var manufacturer = $(this).data('manufacturer');
                var model = $(this).data('model');
                var templateId = $(this).data('template-id');
                var extraSettings = $(this).data('extra-settings');
                
                $('#edit_id').val(id);
                $('#edit_name').val(name);
                $('#edit_rtsp_url').val(rtspUrl);
                $('#edit_username').val(username);
                $('#edit_password').val(''); // パスワードは表示しない
                $('#edit_enabled').prop('checked', enabled == 1);
                $('#edit_frame_rate').val(frameRate);
                $('#edit_resolution').val(resolution);
                $('#edit_description').val(description);
                $('#edit_manufacturer').val(manufacturer);
                $('#edit_model').val(model);
                $('#edit_template_id').val(templateId);
                $('#edit_extra_settings').val(extraSettings);
                
                // テンプレートが選択されている場合は情報を表示
                if (templateId) {
                    loadTemplateInfo(templateId, 'edit');
                }
                
                $('#editCameraModal').modal('show');
            });
            
            // 削除ボタンのクリックイベント
            $('.delete-camera').click(function() {
                var id = $(this).data('id');
                var name = $(this).data('name');
                
                $('#delete_id').val(id);
                $('#delete_camera_name').text(name);
                
                $('#deleteCameraModal').modal('show');
            });
            
            // 今すぐ同期ボタンのクリックイベント
            $('#syncNowBtn').click(function() {
                $(this).prop('disabled', true);
                $(this).html('<i class="fas fa-sync fa-spin"></i> 同期中...');
                
                $.ajax({
                    url: '../api/trigger_sync.php',
                    type: 'POST',
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            alert('同期を開始しました。');
                        } else {
                            alert('エラー: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('同期リクエストの送信に失敗しました。');
                    },
                    complete: function() {
                        $('#syncNowBtn').prop('disabled', false);
                        $('#syncNowBtn').html('<i class="fas fa-sync"></i> 今すぐ同期');
                    }
                });
            });
            
            // テンプレート選択イベント（追加モーダル）
            $('#template_id').change(function() {
                var templateId = $(this).val();
                if (templateId) {
                    loadTemplateInfo(templateId, 'add');
                    
                    // 選択されたオプションのmanufacturer属性を取得
                    var manufacturer = $('option:selected', this).data('manufacturer');
                    $('#manufacturer').val(manufacturer);
                } else {
                    $('#template_info_add').html('<p class="text-muted">テンプレートを選択すると、詳細情報がここに表示されます。</p>');
                }
            });
            
            // テンプレート選択イベント（編集モーダル）
            $('#edit_template_id').change(function() {
                var templateId = $(this).val();
                if (templateId) {
                    loadTemplateInfo(templateId, 'edit');
                    
                    // 選択されたオプションのmanufacturer属性を取得
                    var manufacturer = $('option:selected', this).data('manufacturer');
                    $('#edit_manufacturer').val(manufacturer);
                } else {
                    $('#template_info_edit').html('<p class="text-muted">テンプレートを選択すると、詳細情報がここに表示されます。</p>');
                }
            });
            
            // メーカー選択イベント（追加モーダル）
            $('#manufacturer').change(function() {
                var manufacturer = $(this).val();
                filterTemplateOptions('#template_id', manufacturer);
            });
            
            // メーカー選択イベント（編集モーダル）
            $('#edit_manufacturer').change(function() {
                var manufacturer = $(this).val();
                filterTemplateOptions('#edit_template_id', manufacturer);
            });
            
            // テンプレート情報を読み込む関数
            function loadTemplateInfo(templateId, mode) {
                var targetElement = mode === 'add' ? '#template_info_add' : '#template_info_edit';
                
                $(targetElement).html('<p class="text-center"><i class="fas fa-spinner fa-spin"></i> 読み込み中...</p>');
                
                $.ajax({
                    url: '../api/templates.php',
                    type: 'GET',
                    data: {
                        action: 'get',
                        id: templateId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success' && response.data) {
                            var template = response.data;
                            var templateData = template.data || {};
                            
                            var html = '<div class="template-info">';
                            
                            // 基本情報
                            html += '<h5>' + escapeHtml(templateData.description || template.description) + '</h5>';
                            html += '<p><strong>メーカー:</strong> ' + escapeHtml(templateData.manufacturer || template.manufacturer) + '<br>';
                            html += '<strong>モデルパターン:</strong> ' + escapeHtml(templateData.model_pattern || template.model_pattern) + '</p>';
                            
                            // RTSP パターン
                            if (templateData.rtsp_patterns && templateData.rtsp_patterns.length > 0) {
                                html += '<div class="mt-3"><strong>RTSP パターン:</strong>';
                                html += '<ul class="list-group">';
                                
                                for (var i = 0; i < templateData.rtsp_patterns.length; i++) {
                                    var pattern = templateData.rtsp_patterns[i];
                                    html += '<li class="list-group-item">';
                                    html += '<div class="d-flex justify-content-between align-items-center">';
                                    html += '<strong>' + escapeHtml(pattern.name) + '</strong>';
                                    html += '<button type="button" class="btn btn-sm btn-outline-primary use-pattern" data-pattern-index="' + i + '" data-template-id="' + templateId + '" data-mode="' + mode + '">使用</button>';
                                    html += '</div>';
                                    html += '<small class="text-muted">' + escapeHtml(pattern.url_pattern) + '</small>';
                                    if (pattern.description) {
                                        html += '<p class="mt-1 mb-0 small">' + escapeHtml(pattern.description) + '</p>';
                                    }
                                    html += '</li>';
                                }
                                
                                html += '</ul></div>';
                            }
                            
                            // 認証情報
                            if (templateData.authentication) {
                                html += '<div class="mt-3"><strong>認証:</strong>';
                                html += '<p>';
                                if (templateData.authentication.required) {
                                    html += '認証が必要です<br>';
                                    if (templateData.authentication.default_username) {
                                        html += '標準ユーザー名: ' + escapeHtml(templateData.authentication.default_username) + '<br>';
                                    }
                                    if (templateData.authentication.default_password) {
                                        html += '標準パスワード: ' + escapeHtml(templateData.authentication.default_password);
                                    }
                                } else {
                                    html += '認証は必要ありません';
                                }
                                html += '</p></div>';
                            }
                            
                            // 推奨設定
                            if (templateData.recommended_settings) {
                                html += '<div class="mt-3"><strong>推奨設定:</strong>';
                                html += '<ul>';
                                
                                var settings = templateData.recommended_settings;
                                if (settings.fps) {
                                    html += '<li>FPS: ' + settings.fps + '</li>';
                                }
                                if (settings.resolution) {
                                    html += '<li>解像度: ' + escapeHtml(settings.resolution) + '</li>';
                                }
                                if (typeof settings.ptz_support !== 'undefined') {
                                    html += '<li>PTZサポート: ' + (settings.ptz_support ? 'あり' : 'なし') + '</li>';
                                }
                                if (typeof settings.audio_support !== 'undefined') {
                                    html += '<li>音声サポート: ' + (settings.audio_support ? 'あり' : 'なし') + '</li>';
                                }
                                
                                html += '</ul></div>';
                            }
                            
                            // セットアップ手順
                            if (templateData.setup_instructions && templateData.setup_instructions.length > 0) {
                                html += '<div class="mt-3"><strong>セットアップ手順:</strong>';
                                html += '<ol>';
                                
                                for (var i = 0; i < templateData.setup_instructions.length; i++) {
                                    html += '<li>' + escapeHtml(templateData.setup_instructions[i]) + '</li>';
                                }
                                
                                html += '</ol></div>';
                            }
                            
                            html += '</div>';
                            $(targetElement).html(html);
                            
                            // パターン適用ボタンのイベントを設定
                            $('.use-pattern').click(function() {
                                var patternIndex = $(this).data('pattern-index');
                                var templateId = $(this).data('template-id');
                                var mode = $(this).data('mode');
                                applyTemplatePattern(templateId, patternIndex, mode);
                            });
                        } else {
                            $(targetElement).html('<p class="text-danger">テンプレート情報の取得に失敗しました。</p>');
                        }
                    },
                    error: function() {
                        $(targetElement).html('<p class="text-danger">テンプレート情報の取得に失敗しました。</p>');
                    }
                });
            }
            
            // テンプレートパターンを適用する関数
            function applyTemplatePattern(templateId, patternIndex, mode) {
                // フィールド接頭辞を決定
                var prefix = mode === 'add' ? '' : 'edit_';
                
                // カメラ情報の入力値を取得
                var ipAddress = prompt('カメラのIPアドレスを入力してください:');
                if (!ipAddress) return;
                
                var port = prompt('ポート番号を入力してください (空白の場合はデフォルト):');
                var username = $('#' + prefix + 'username').val() || prompt('ユーザー名を入力してください:');
                var password = $('#' + prefix + 'password').val() || prompt('パスワードを入力してください:');
                
                // テンプレートからURLを生成
                $.ajax({
                    url: '../api/templates.php',
                    type: 'POST',
                    data: {
                        action: 'generate_url',
                        template_id: templateId,
                        pattern_index: patternIndex,
                        params: JSON.stringify({
                            ip: ipAddress,
                            port: port,
                            username: username,
                            password: password
                        })
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success' && response.data && response.data.url) {
                            // 生成されたURLをフォームに設定
                            $('#' + prefix + 'rtsp_url').val(response.data.url);
                            $('#' + prefix + 'username').val(username);
                            $('#' + prefix + 'password').val(password);
                            
                            // 基本設定タブに切り替え
                            $('#' + prefix + 'basic-tab').tab('show');
                            
                            alert('RTSP URLを生成しました。');
                        } else {
                            alert('エラー: ' + (response.message || 'RTSP URLの生成に失敗しました。'));
                        }
                    },
                    error: function() {
                        alert('RTSP URLの生成リクエストに失敗しました。');
                    }
                });
            }
            
            // テンプレートオプションをメーカーでフィルタリングする関数
            function filterTemplateOptions(selectId, manufacturer) {
                if (!manufacturer) {
                    // メーカーが選択されていない場合、すべてのオプションを表示
                    $(selectId + ' optgroup').show();
                    return;
                }
                
                // すべてのオプショングループを非表示
                $(selectId + ' optgroup').hide();
                
                // 選択されたメーカーのオプショングループを表示
                $(selectId + ' optgroup[data-manufacturer="' + manufacturer + '"]').show();
                
                // 現在選択されているオプションがフィルタに合わない場合、選択をクリア
                var currentOption = $(selectId + ' option:selected');
                if (currentOption.parent('optgroup').data('manufacturer') !== manufacturer) {
                    $(selectId).val('');
                }
            }
            
            // HTML特殊文字をエスケープする関数
            function escapeHtml(text) {
                if (!text) return '';
                return text
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }
            
            // ログ表示用のボタンをヘッダーに追加
            $('.container .row.mb-4 .col-md-12').append(`
                <button type="button" class="btn btn-secondary ml-2" data-toggle="modal" data-target="#logModal">
                    <i class="fas fa-list-alt"></i> ログ表示
                </button>
            `);
            
            // ログモーダル機能
            const logContent = document.getElementById('logModalContent');
            const logLines = document.getElementById('logLines');
            const logFilter = document.getElementById('logFilter');
            const applyLogFilter = document.getElementById('applyLogFilter');
            const refreshLogs = document.getElementById('refreshLogs');
            
            // ログモーダルが表示されたときにログを取得
            $('#logModal').on('shown.bs.modal', function() {
                fetchLogs();
            });
            
            // ログを取得する関数
            function fetchLogs() {
                const lines = logLines.value;
                const filter = logFilter.value;
                
                // ローディング表示
                logContent.innerHTML = `
                    <div class="d-flex justify-content-center my-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">読み込み中...</span>
                        </div>
                    </div>
                `;
                
                // APIからログを取得
                fetch(`../api/view_logs.php?lines=${lines}&filter=${encodeURIComponent(filter)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            displayLogs(data.logs);
                        } else {
                            logContent.innerHTML = `<div class="alert alert-danger">${data.message || 'ログの取得に失敗しました'}</div>`;
                        }
                    })
                    .catch(error => {
                        logContent.innerHTML = `<div class="alert alert-danger">エラー: ${error.message}</div>`;
                    });
            }
            
            // ログを表示する関数
            function displayLogs(logs) {
                if (!logs || logs.length === 0) {
                    logContent.innerHTML = '<div class="alert alert-info">ログデータがありません</div>';
                    return;
                }
                
                const logHtml = logs.map(line => {
                    // ログの形式に合わせてフォーマット
                    let formattedLine = line;
                    
                    // タイムスタンプ、モジュール名、エラーメッセージなどを強調表示
                    formattedLine = formattedLine.replace(/\[([\d-]+ [\d:]+)\]/g, '<span style="color: #0d6efd; font-weight: bold;">[$1]</span>');
                    formattedLine = formattedLine.replace(/\[([A-Z_]+)\]/g, '<span style="color: #6610f2; font-weight: bold;">[$1]</span>');
                    
                    // エラーとワーニングを強調表示
                    if (line.toLowerCase().includes('error') || line.toLowerCase().includes('exception')) {
                        formattedLine = `<div style="color: #dc3545;">${formattedLine}</div>`;
                    } else if (line.toLowerCase().includes('warning') || line.toLowerCase().includes('warn')) {
                        formattedLine = `<div style="color: #fd7e14;">${formattedLine}</div>`;
                    } else if (line.toLowerCase().includes('success')) {
                        formattedLine = `<div style="color: #198754;">${formattedLine}</div>`;
                    } else {
                        formattedLine = `<div>${formattedLine}</div>`;
                    }
                    
                    return formattedLine;
                }).join('');
                
                logContent.innerHTML = logHtml;
                
                // 最下部にスクロール
                logContent.scrollTop = logContent.scrollHeight;
            }
            
            // 更新ボタンのイベントリスナー
            refreshLogs.addEventListener('click', fetchLogs);
            
            // フィルターのイベントリスナー
            applyLogFilter.addEventListener('click', fetchLogs);
            
            // フィルター入力欄でEnterキーを押したときにフィルターを適用
            logFilter.addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    fetchLogs();
                }
            });
            
            // 行数選択が変更されたときにログを再取得
            logLines.addEventListener('change', fetchLogs);
        });
    </script>
    
    <?php include_once('../includes/footer.php'); ?>
</body>
</html> 
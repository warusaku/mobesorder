<?php
/**
 * RTSPカメラテンプレートAPI
 * 
 * カメラテンプレートの取得、登録、更新を行うAPIエンドポイント
 * 
 * @author RTSP_Reader Development Team
 * @created 2025-07-10
 */

// 必要なファイルのインクルード
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/template_manager.php';

// セッション開始とCSRF対策
session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// リクエストメソッドの確認
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// 接続データベースの取得
$connection = get_db_connection();

// テンプレートマネージャーの初期化
$template_manager = new TemplateManager($connection);

// APIレスポンスの初期化
$response = [
    'status' => 'error',
    'message' => '無効なリクエスト',
    'data' => null
];

// セッションチェック（管理者のみアクセス可能）
if (!is_admin_logged_in()) {
    $response['message'] = '認証エラー：管理者権限が必要です';
    echo json_encode($response);
    exit;
}

try {
    // アクションに基づいて処理
    switch ($action) {
        case 'list':
            // すべてのテンプレート一覧を取得
            if ($method === 'GET') {
                $manufacturer = isset($_GET['manufacturer']) ? $_GET['manufacturer'] : null;
                
                if ($manufacturer) {
                    $templates = $template_manager->getTemplatesByManufacturer($manufacturer);
                } else {
                    $templates = $template_manager->getAllTemplates();
                }
                
                $response['status'] = 'success';
                $response['message'] = 'テンプレート一覧を取得しました';
                $response['data'] = $templates;
            }
            break;
            
        case 'get':
            // 特定のテンプレートを取得
            if ($method === 'GET' && isset($_GET['id'])) {
                $template_id = $_GET['id'];
                $template = $template_manager->getTemplateById($template_id);
                
                if ($template) {
                    $response['status'] = 'success';
                    $response['message'] = 'テンプレートを取得しました';
                    $response['data'] = $template;
                } else {
                    $response['message'] = 'テンプレートが見つかりません';
                }
            }
            break;
            
        case 'match':
            // モデル名に一致するテンプレートを取得
            if ($method === 'GET' && isset($_GET['model'])) {
                $model_name = $_GET['model'];
                $template = $template_manager->findBestMatchingTemplate($model_name);
                
                if ($template) {
                    $response['status'] = 'success';
                    $response['message'] = 'テンプレートが見つかりました';
                    $response['data'] = $template;
                } else {
                    $response['message'] = '一致するテンプレートが見つかりません';
                }
            }
            break;
            
        case 'add':
        case 'update':
            // テンプレートの追加または更新
            if ($method === 'POST') {
                // CSRFチェック
                if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                    $response['message'] = 'セキュリティトークンが無効です';
                    break;
                }
                
                // JSONデータの取得
                $json_content = isset($_POST['json_content']) ? $_POST['json_content'] : '';
                $template_data = json_decode($json_content, true);
                
                if (!$template_data) {
                    $response['message'] = '無効なJSONフォーマット';
                    break;
                }
                
                // テンプレートの検証
                if (!$template_manager->validateTemplate($template_data)) {
                    $response['message'] = 'テンプレートデータが不完全です';
                    break;
                }
                
                // メタデータの準備
                $meta = [
                    'template_id' => isset($_POST['template_id']) ? $_POST['template_id'] : $template_data['manufacturer'] . '_' . basename($template_data['model_pattern']),
                    'manufacturer' => $template_data['manufacturer'],
                    'model_pattern' => $template_data['model_pattern'],
                    'description' => $template_data['description'],
                    'is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1
                ];
                
                // テンプレート登録
                if ($template_manager->registerTemplate($meta, $json_content)) {
                    $response['status'] = 'success';
                    $response['message'] = 'テンプレートを保存しました';
                    $response['data'] = ['template_id' => $meta['template_id']];
                    
                    // 同期テーブルに変更を記録
                    log_sync_change('camera_templates', $meta['template_id'], 'update');
                } else {
                    $response['message'] = 'テンプレートの保存に失敗しました';
                }
            }
            break;
            
        case 'delete':
            // テンプレートの無効化（削除フラグ）
            if ($method === 'POST') {
                // CSRFチェック
                if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                    $response['message'] = 'セキュリティトークンが無効です';
                    break;
                }
                
                if (isset($_POST['template_id'])) {
                    $template_id = $_POST['template_id'];
                    
                    // データベースでテンプレートを無効化（物理削除ではなく論理削除）
                    $stmt = $connection->prepare("UPDATE camera_templates SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE template_id = ?");
                    $stmt->bind_param("s", $template_id);
                    
                    if ($stmt->execute()) {
                        $response['status'] = 'success';
                        $response['message'] = 'テンプレートを無効化しました';
                        
                        // 同期テーブルに変更を記録
                        log_sync_change('camera_templates', $template_id, 'delete');
                    } else {
                        $response['message'] = 'テンプレートの無効化に失敗しました';
                    }
                    
                    $stmt->close();
                }
            }
            break;
            
        case 'generate_url':
            // テンプレートからRTSP URLを生成
            if ($method === 'POST') {
                if (isset($_POST['template_id']) && isset($_POST['params'])) {
                    $template_id = $_POST['template_id'];
                    $params = json_decode($_POST['params'], true);
                    $pattern_index = isset($_POST['pattern_index']) ? (int)$_POST['pattern_index'] : 0;
                    
                    if (!$params) {
                        $response['message'] = '無効なパラメータフォーマット';
                        break;
                    }
                    
                    $template = $template_manager->getTemplateById($template_id);
                    if (!$template) {
                        $response['message'] = 'テンプレートが見つかりません';
                        break;
                    }
                    
                    $url = $template_manager->generateRtspUrl($template, $params, $pattern_index);
                    
                    if (!empty($url)) {
                        $response['status'] = 'success';
                        $response['message'] = 'RTSP URLを生成しました';
                        $response['data'] = ['url' => $url];
                    } else {
                        $response['message'] = 'RTSP URLの生成に失敗しました';
                    }
                }
            }
            break;
            
        default:
            $response['message'] = '無効なアクション';
            break;
    }
} catch (Exception $e) {
    $response['message'] = 'エラーが発生しました: ' . $e->getMessage();
    error_log('Template API Error: ' . $e->getMessage());
}

// レスポンス出力
header('Content-Type: application/json');
echo json_encode($response); 
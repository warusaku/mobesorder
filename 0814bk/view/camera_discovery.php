<?php
/**
 * RTSPカメラ検出結果表示インターフェース
 * 
 * このファイルはローカルサーバーで検出されたRTSPカメラの情報を表示し、
 * システムへの登録を行うためのインターフェースを提供します。
 * 
 * @package RTSP_Reader
 * @author Hideaki Kurata
 * @version 1.0
 */

// セッション開始
session_start();

// 認証確認
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: ../index.php');
    exit;
}

// 必要なファイルをインクルード
require_once('../dbconfig.php');
require_once('../commons/functions.php');

// ログファイルの設定
$log_file = '../logs/php.log';

// ログ関数
function log_message($msg) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $msg\n", FILE_APPEND);
}

// エラーハンドリング
$error_message = '';
$success_message = '';

// データベース接続
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $error_message = 'データベース接続エラー: ' . $e->getMessage();
    log_message($error_message);
}

// フィルター用のデータを取得
$statuses = ['未登録', '登録済み', '接続不可', '登録失敗'];
$manufacturers = [];

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT DISTINCT manufacturer FROM rtspcam_scanresult WHERE manufacturer != '' ORDER BY manufacturer");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $manufacturers[] = $row['manufacturer'];
        }
    } catch (PDOException $e) {
        log_message("メーカー一覧取得エラー: " . $e->getMessage());
    }
}

// フィルター適用
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$manufacturer_filter = isset($_GET['manufacturer']) ? $_GET['manufacturer'] : '';

// カメラ登録処理
if (isset($_POST['register_camera']) && !empty($_POST['camera_id'])) {
    $camera_id = $_POST['camera_id'];
    
    try {
        // カメラ情報取得
        $stmt = $pdo->prepare("SELECT * FROM rtspcam_scanresult WHERE id = ?");
        $stmt->execute([$camera_id]);
        $camera = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($camera) {
            // LacisID生成（MACアドレスまたはIPアドレスから）
            $lacis_id = '';
            if (!empty($camera['mac_address'])) {
                // MACアドレスの形式変換（コロン除去）
                $mac_clean = str_replace(':', '', $camera['mac_address']);
                $lacis_id = 'CAM' . $mac_clean;
            } else {
                // IPアドレスをハッシュ化して利用
                $ip_hash = substr(md5($camera['ip_address']), 0, 10);
                $lacis_id = 'CAM' . $ip_hash;
            }
            
            // 既存のカメラ設定確認
            $stmt = $pdo->prepare("SELECT id FROM camera_settings WHERE lacis_id = ?");
            $stmt->execute([$lacis_id]);
            $existing_camera = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // RTSP URLの構築
            $rtsp_protocol = !empty($camera['rtsp_protocol']) ? $camera['rtsp_protocol'] : 'rtsp://';
            $ip_address = $camera['ip_address'];
            $rtsp_port = !empty($camera['rtsp_port']) ? $camera['rtsp_port'] : 554;
            $rtsp_path = !empty($camera['rtsp_path']) ? $camera['rtsp_path'] : '/';
            
            $rtsp_url = $rtsp_protocol . $ip_address . ':' . $rtsp_port . $rtsp_path;
            
            // 名前の生成（メーカー + IPアドレス）
            $camera_name = '';
            if (!empty($camera['manufacturer'])) {
                $camera_name = $camera['manufacturer'] . '_' . $ip_address;
            } else {
                $camera_name = 'Camera_' . $ip_address;
            }
            
            // カメラ設定の登録または更新
            if ($existing_camera) {
                $stmt = $pdo->prepare("
                    UPDATE camera_settings SET 
                    name = ?, 
                    rtsp_url = ?, 
                    ip_address = ?, 
                    model = ?, 
                    note = CONCAT(note, '\n[', NOW(), '] スキャンから更新'),
                    last_updated = NOW()
                    WHERE lacis_id = ?
                ");
                $stmt->execute([
                    $camera_name,
                    $rtsp_url,
                    $ip_address,
                    $camera['model'] ?? '',
                    $lacis_id
                ]);
                
                $success_message = '既存のカメラ設定を更新しました。';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO camera_settings 
                    (lacis_id, name, rtsp_url, ip_address, model, is_enabled, note, created_at, last_updated) 
                    VALUES (?, ?, ?, ?, ?, 1, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $lacis_id,
                    $camera_name,
                    $rtsp_url,
                    $ip_address,
                    $camera['model'] ?? '',
                    'スキャンから自動登録されました。'
                ]);
                
                $success_message = '新しいカメラを登録しました。';
            }
            
            // スキャン結果を更新（登録済み状態に）
            $stmt = $pdo->prepare("UPDATE rtspcam_scanresult SET status = '登録済み', last_updated = NOW() WHERE id = ?");
            $stmt->execute([$camera_id]);
            
            log_message("カメラ登録成功: ID=$camera_id, LacisID=$lacis_id, URL=$rtsp_url");
        } else {
            $error_message = '指定されたカメラが見つかりません';
        }
    } catch (PDOException $e) {
        $error_message = 'カメラ登録エラー: ' . $e->getMessage();
        log_message($error_message);
    }
}

// スキャン開始リクエスト
if (isset($_POST['start_scan'])) {
    $scan_type = $_POST['scan_type'] ?? 'incremental';
    $target_subnet = $_POST['target_subnet'] ?? '';
    
    try {
        // APIリクエスト
        $api_url = '../api/scan_management.php?key=rtsp_test&action=start_scan';
        $post_data = json_encode([
            'scan_type' => $scan_type,
            'subnet' => $target_subnet
        ]);
        
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($post_data)
        ]);
        
        $result = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($status_code == 200) {
            $response = json_decode($result, true);
            if (isset($response['status']) && $response['status'] === 'success') {
                $success_message = 'スキャンを開始しました。ID: ' . $response['scan_id'];
            } else {
                $error_message = 'スキャン開始エラー: ' . ($response['message'] ?? '不明なエラー');
            }
        } else {
            $error_message = 'APIリクエストエラー: ステータスコード ' . $status_code;
        }
    } catch (Exception $e) {
        $error_message = 'スキャンリクエストエラー: ' . $e->getMessage();
        log_message($error_message);
    }
}

// 現在実行中のスキャン情報を取得
$current_scan = null;
try {
    if ($pdo) {
        $stmt = $pdo->query("
            SELECT * FROM rtspcam_scan_history 
            WHERE scan_status = 'running' 
            ORDER BY scan_start_time DESC 
            LIMIT 1
        ");
        $current_scan = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    log_message("実行中スキャン情報取得エラー: " . $e->getMessage());
}

// 最新のスキャン結果を取得
$cameras = [];
try {
    if ($pdo) {
        $query = "SELECT * FROM rtspcam_scanresult WHERE 1=1";
        $params = [];
        
        if (!empty($status_filter)) {
            $query .= " AND status = ?";
            $params[] = $status_filter;
        }
        
        if (!empty($manufacturer_filter)) {
            $query .= " AND manufacturer = ?";
            $params[] = $manufacturer_filter;
        }
        
        $query .= " ORDER BY last_seen DESC LIMIT 100";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $cameras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error_message = 'カメラ情報取得エラー: ' . $e->getMessage();
    log_message($error_message);
}

// 最近のスキャン履歴を取得
$scan_history = [];
try {
    if ($pdo) {
        $stmt = $pdo->query("
            SELECT * FROM rtspcam_scan_history 
            ORDER BY scan_start_time DESC 
            LIMIT 5
        ");
        $scan_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    log_message("スキャン履歴取得エラー: " . $e->getMessage());
}

// タイトル設定
$page_title = 'カメラ検出・登録';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RTSP Reader - <?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .card-header.bg-primary {
            color: white;
        }
        .scan-controls {
            margin-bottom: 20px;
        }
        .scan-progress {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 5px;
        }
        .scan-running {
            background-color: #f8f9fa;
            border-left: 4px solid #17a2b8;
        }
        .filter-form {
            margin-bottom: 20px;
        }
        .camera-table th {
            background-color: #f8f9fa;
        }
        .camera-card {
            margin-bottom: 15px;
        }
        .scan-progress-container {
            margin-top: 15px;
            margin-bottom: 20px;
            background-color: #f0f0f0;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .progress-bar-container {
            width: 100%;
            height: 25px;
            background-color: #e0e0e0;
            border-radius: 5px;
            margin: 10px 0;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            background-color: #4CAF50;
            transition: width 0.5s ease-in-out;
            text-align: center;
            color: white;
            line-height: 25px;
            font-weight: bold;
        }
        .progress-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 14px;
        }
        .progress-stats div {
            flex: 1;
            margin-right: 10px;
        }
        .progress-stats .stat-box {
            background-color: #fff;
            padding: 8px;
            border-radius: 3px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }
        .progress-stats .stat-value {
            font-weight: bold;
            font-size: 16px;
            color: #333;
        }
        .progress-stats .stat-label {
            color: #666;
            font-size: 12px;
        }
        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }
        .scanning-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            background-color: #4CAF50;
            border-radius: 50%;
            margin-right: 8px;
            animation: pulse 1.5s infinite ease-in-out;
        }
    </style>
</head>
<body>
    <?php include '../commons/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <?php include '../commons/sidebar.php'; ?>
            
            <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $page_title; ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group mr-2">
                            <a href="camera_settings.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-cog"></i> カメラ設定
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card scan-controls">
                            <div class="card-header bg-primary">
                                <h5 class="card-title mb-0"><i class="fas fa-search"></i> スキャン制御</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="">
                                    <div class="form-group">
                                        <label for="scan_type">スキャンタイプ</label>
                                        <select class="form-control" id="scan_type" name="scan_type">
                                            <option value="incremental">増分スキャン (最新状態の更新)</option>
                                            <option value="full">完全スキャン (すべてのネットワークセグメント)</option>
                                            <option value="targeted">対象指定スキャン (特定のサブネットのみ)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group" id="subnet_group" style="display: none;">
                                        <label for="target_subnet">対象サブネット</label>
                                        <input type="text" class="form-control" id="target_subnet" name="target_subnet" 
                                               placeholder="例: 192.168.33.0/24">
                                        <small class="form-text text-muted">
                                            CIDR形式で入力してください (例: 192.168.33.0/24)
                                        </small>
                                    </div>
                                    
                                    <button type="submit" name="start_scan" class="btn btn-primary" 
                                            <?php echo $current_scan ? 'disabled' : ''; ?>>
                                        <i class="fas fa-play"></i> スキャン開始
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary">
                                <h5 class="card-title mb-0"><i class="fas fa-history"></i> 最近のスキャン履歴</h5>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>開始時間</th>
                                            <th>タイプ</th>
                                            <th>状態</th>
                                            <th>検出数</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($scan_history)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center">スキャン履歴がありません</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($scan_history as $scan): ?>
                                                <tr>
                                                    <td><?php echo $scan['scan_start_time']; ?></td>
                                                    <td><?php echo $scan['scan_type']; ?></td>
                                                    <td>
                                                        <?php if ($scan['scan_status'] == 'running'): ?>
                                                            <span class="badge badge-info">実行中</span>
                                                        <?php elseif ($scan['scan_status'] == 'completed'): ?>
                                                            <span class="badge badge-success">完了</span>
                                                        <?php elseif ($scan['scan_status'] == 'failed'): ?>
                                                            <span class="badge badge-danger">失敗</span>
                                                        <?php else: ?>
                                                            <?php echo $scan['scan_status']; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $scan['detected_cameras']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($current_scan): ?>
                    <div class="scan-progress scan-running mt-3">
                        <h5><i class="fas fa-spinner fa-spin"></i> スキャン実行中</h5>
                        <p>
                            開始時間: <?php echo $current_scan['scan_start_time']; ?><br>
                            スキャンタイプ: <?php echo $current_scan['scan_type']; ?>
                            <?php if (!empty($current_scan['target_subnets'])): ?>
                                <br>対象サブネット: <?php echo $current_scan['target_subnets']; ?>
                            <?php endif; ?>
                        </p>
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 100%"></div>
                        </div>
                        <p class="mt-2 text-muted">スキャンが完了するまでお待ちください。このページは30秒ごとに自動更新されます。</p>
                    </div>
                <?php endif; ?>
                
                <div class="card mt-3">
                    <div class="card-header bg-primary">
                        <h5 class="card-title mb-0"><i class="fas fa-camera"></i> 検出されたカメラ</h5>
                    </div>
                    <div class="card-body">
                        <div class="filter-form">
                            <form method="get" action="" class="form-inline">
                                <div class="form-group mr-2">
                                    <label for="status" class="mr-2">状態:</label>
                                    <select class="form-control form-control-sm" id="status" name="status">
                                        <option value="">すべて</option>
                                        <?php foreach ($statuses as $status): ?>
                                            <option value="<?php echo $status; ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                                <?php echo $status; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group mr-2">
                                    <label for="manufacturer" class="mr-2">メーカー:</label>
                                    <select class="form-control form-control-sm" id="manufacturer" name="manufacturer">
                                        <option value="">すべて</option>
                                        <?php foreach ($manufacturers as $manufacturer): ?>
                                            <option value="<?php echo $manufacturer; ?>" <?php echo $manufacturer_filter === $manufacturer ? 'selected' : ''; ?>>
                                                <?php echo $manufacturer; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="fas fa-filter"></i> フィルター適用
                                </button>
                                
                                <?php if (!empty($status_filter) || !empty($manufacturer_filter)): ?>
                                    <a href="?" class="btn btn-sm btn-outline-secondary ml-2">
                                        <i class="fas fa-times"></i> フィルターリセット
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                        
                        <?php if (empty($cameras)): ?>
                            <div class="alert alert-info" role="alert">
                                検出されたカメラはありません。スキャンを実行してください。
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-sm camera-table">
                                    <thead>
                                        <tr>
                                            <th>IPアドレス</th>
                                            <th>MACアドレス</th>
                                            <th>メーカー</th>
                                            <th>モデル</th>
                                            <th>RTSPポート</th>
                                            <th>最終検出</th>
                                            <th>状態</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cameras as $camera): ?>
                                            <tr>
                                                <td><?php echo $camera['ip_address']; ?></td>
                                                <td><?php echo $camera['mac_address']; ?></td>
                                                <td><?php echo $camera['manufacturer']; ?></td>
                                                <td><?php echo $camera['model']; ?></td>
                                                <td><?php echo $camera['rtsp_port']; ?></td>
                                                <td><?php echo $camera['last_seen']; ?></td>
                                                <td>
                                                    <?php if ($camera['status'] == '未登録'): ?>
                                                        <span class="badge badge-warning">未登録</span>
                                                    <?php elseif ($camera['status'] == '登録済み'): ?>
                                                        <span class="badge badge-success">登録済み</span>
                                                    <?php elseif ($camera['status'] == '接続不可'): ?>
                                                        <span class="badge badge-danger">接続不可</span>
                                                    <?php elseif ($camera['status'] == '登録失敗'): ?>
                                                        <span class="badge badge-dark">登録失敗</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary"><?php echo $camera['status']; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($camera['status'] == '未登録' || $camera['status'] == '接続不可' || $camera['status'] == '登録失敗'): ?>
                                                        <form method="post" action="" onsubmit="return confirm('このカメラを登録しますか？');" style="display: inline;">
                                                            <input type="hidden" name="camera_id" value="<?php echo $camera['id']; ?>">
                                                            <button type="submit" name="register_camera" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-plus"></i> 登録
                                                            </button>
                                                        </form>
                                                    <?php elseif ($camera['status'] == '登録済み'): ?>
                                                        <a href="camera_settings.php?ip=<?php echo $camera['ip_address']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-cog"></i> 設定
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // 対象指定スキャン時にサブネット入力欄を表示
        $(document).ready(function() {
            $('#scan_type').change(function() {
                if ($(this).val() === 'targeted') {
                    $('#subnet_group').show();
                } else {
                    $('#subnet_group').hide();
                }
            });
            
            // 初期状態の設定
            if ($('#scan_type').val() === 'targeted') {
                $('#subnet_group').show();
            }
            
            <?php if ($current_scan): ?>
            // スキャン実行中の場合、30秒ごとに自動更新
            setTimeout(function() {
                location.reload();
            }, 30000);
            <?php endif; ?>
        });
    </script>
</body>
</html> 
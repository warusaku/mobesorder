<?php
/**
 * QRコード生成処理
 * 
 * QRコードの生成と画像の合成を行うAPIエンドポイントです。
 */

// 設定ファイルを読み込み
$rootPath = realpath(__DIR__ . '/..');
require_once $rootPath . '/api/config/config.php';

// セッション開始
session_start();

// ログファイルの設定
$logDir = $rootPath . '/api/logs';
$logFile = $logDir . '/QRgenerate_php.log';

// ログディレクトリの存在確認と作成
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// 一時ファイル保存ディレクトリの確認と作成
$tmpDir = __DIR__ . '/images/TMP';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0755, true);
}

// ログ関数
function writeLog($message, $level = 'INFO') {
    global $logFile;
    
    // ファイルサイズをチェック
    if (file_exists($logFile) && filesize($logFile) > 204800) { // 200KB
        // ファイルを削除して新規作成
        unlink($logFile);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// CORSヘッダー設定
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// リクエストメソッドの確認
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POSTリクエストのみ許可されています']);
    writeLog('不正なリクエストメソッド: ' . $_SERVER['REQUEST_METHOD'], 'ERROR');
    exit;
}

// ログイン状態チェック
$isLoggedIn = false;
$authToken = '';

if (isset($_SESSION['auth_user']) && isset($_SESSION['auth_token'])) {
    $isLoggedIn = true;
    $currentUser = $_SESSION['auth_user'];
    $authToken = $_SESSION['auth_token'];
} else if (isset($_POST['token'])) {
    $authToken = $_POST['token'];
    // トークンが有効かチェック (簡易的な実装)
    if (!empty($authToken)) {
        $isLoggedIn = true;
    }
}

if (!$isLoggedIn) {
    echo json_encode(['success' => false, 'message' => '認証が必要です']);
    writeLog('未認証アクセス', 'ERROR');
    exit;
}

// POSTパラメータの取得
$content = isset($_POST['content']) ? $_POST['content'] : '';
$label = isset($_POST['label']) ? $_POST['label'] : '';
$roomNumber = isset($_POST['room_number']) ? $_POST['room_number'] : '';
$qrType = isset($_POST['qr_type']) ? $_POST['qr_type'] : 'common';

if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'コンテンツが指定されていません']);
    writeLog('コンテンツが指定されていません', 'ERROR');
    exit;
}

// ファイル名生成（一意性を持たせるためにタイムスタンプ付き）
$timestamp = time();
$contentHash = md5($content);
$fileName = $qrType . '_' . $contentHash . '_' . $timestamp . '.png';
$filePath = $tmpDir . '/' . $fileName;

// 古い一時ファイルをクリーンアップ（1時間以上前のファイル）
$files = glob($tmpDir . '/*.png');
$now = time();
foreach ($files as $file) {
    if (is_file($file) && $now - filemtime($file) >= 3600) {
        unlink($file);
        writeLog('古い一時ファイルを削除: ' . basename($file));
    }
}

try {
    // QRコードの取得
    $encodedContent = urlencode($content);
    // APIからQRコードを取得（白QRコードに青背景）
    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?data={$encodedContent}&size=400x400&color=FFFFFF&bgcolor=658BC1";
    
    $qrImage = @file_get_contents($qrUrl);
    
    if ($qrImage === false) {
        throw new Exception('QRコードの取得に失敗しました');
    }
    
    // 画像の作成
    $image = imagecreatefromstring($qrImage);
    
    if (!$image) {
        throw new Exception('画像のデコードに失敗しました');
    }
    
    // 500x600の新しい画像を作成
    $newImage = imagecreatetruecolor(500, 600);
    
    // 背景色を設定（青: 658BC1）
    $bgColor = imagecolorallocate($newImage, 101, 139, 193);
    imagefill($newImage, 0, 0, $bgColor);
    
    // QRコードを新しい画像に配置（50px左右マージン）
    imagecopy($newImage, $image, 50, 50, 0, 0, 400, 400);
    
    // テキスト用の色を設定（白: FFFFFF）
    $textColor = imagecolorallocate($newImage, 255, 255, 255);
    
    // QRコードの下端Y位置
    $qrBottomY = 450; // 50px(上マージン) + 400px(QRコード高さ)
    
    // ロゴの開始Y位置：QRコードの下から15px空ける
    $logoY = $qrBottomY + 15;
    
    // ロゴの配置（下部エリアの上部）
    $logoPath = __DIR__ . '/images/logo_w400.png';
    if (file_exists($logoPath)) {
        // PNGロゴの読み込み
        $logo = @imagecreatefrompng($logoPath);
        
        if ($logo !== false) {
            // アルファブレンディングを有効化
            imagealphablending($newImage, true);
            imagesavealpha($newImage, true);
            
            // ロゴのサイズを取得
            $logoWidth = imagesx($logo);
            $logoHeight = imagesy($logo);
            
            // ロゴのサイズが400×48pxでない場合はリサイズ
            if ($logoWidth != 400 || $logoHeight != 48) {
                // リサイズ用の一時画像を作成（400×48px）
                $resizedLogo = imagecreatetruecolor(400, 48);
                
                // 背景を透明に設定
                imagealphablending($resizedLogo, false);
                imagesavealpha($resizedLogo, true);
                $transparent = imagecolorallocatealpha($resizedLogo, 0, 0, 0, 127);
                imagefill($resizedLogo, 0, 0, $transparent);
                imagealphablending($resizedLogo, true);
                
                // アスペクト比を計算
                $aspectRatio = $logoWidth / $logoHeight;
                $targetAspectRatio = 400 / 48;
                
                if ($aspectRatio > $targetAspectRatio) {
                    // 横長の画像: 幅を400pxに合わせる
                    $newWidth = 400;
                    $newHeight = 400 / $aspectRatio;
                    $dstX = 0;
                    $dstY = (48 - $newHeight) / 2; // 垂直方向に中央揃え
                } else {
                    // 縦長の画像: 高さを48pxに合わせる
                    $newHeight = 48;
                    $newWidth = 48 * $aspectRatio;
                    $dstX = (400 - $newWidth) / 2; // 水平方向に中央揃え
                    $dstY = 0;
                }
                
                // リサイズして配置
                imagecopyresampled(
                    $resizedLogo, $logo,
                    $dstX, $dstY, 0, 0,
                    $newWidth, $newHeight, $logoWidth, $logoHeight
                );
                
                // 元のロゴリソースを解放して新しいロゴを使用
                imagedestroy($logo);
                $logo = $resizedLogo;
                $logoWidth = 400;
                $logoHeight = 48;
            }
            
            // ロゴを中央に配置
            $logoX = (500 - $logoWidth) / 2;
            
            // ロゴの貼り付け
            imagecopy($newImage, $logo, $logoX, $logoY, 0, 0, $logoWidth, $logoHeight);
            
            // ロゴのリソース解放
            imagedestroy($logo);
        } else {
            writeLog('PNGロゴの読み込みに失敗: ' . $logoPath, 'WARNING');
        }
    } else {
        writeLog('PNGロゴファイルが見つかりません: ' . $logoPath, 'WARNING');
    }
    
    // テキストエリアの開始Y位置（ロゴの下）
    $textAreaY = $logoY + 48 + 10; // ロゴ(48px) + 間隔(10px)
    
    // テキストエリアの高さ (82px)
    $textAreaHeight = 600 - $textAreaY;
    
    // テキスト内容の設定
    if ($qrType === 'common') {
        $text = "ActivationQR:roomsetting";
    } else {
        $text = "ActivationQR:" . $roomNumber;
    }
    
    // デバッグログ
    writeLog("QRタイプ: {$qrType}, 部屋番号: {$roomNumber}, テキスト: {$text}", 'DEBUG');
    
    // テキスト色を設定（純粋な白: #FFFFFF）
    $textColor = imagecolorallocate($newImage, 255, 255, 255);
    
    // 使用可能なシステムフォントリスト（優先順）
    $fontPaths = [
        __DIR__ . '/fonts/ArialMTStd-Black.otf', // アップロードしたカスタムフォント（最優先）
        __DIR__ . '/images/arial_black.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', // Linuxシステムフォント
        '/Library/Fonts/Arial Bold.ttf', // macOSシステムフォント
    ];
    
    $fontPath = null;
    foreach ($fontPaths as $path) {
        if (file_exists($path)) {
            $fontPath = $path;
            writeLog("フォントを使用: " . $fontPath, 'INFO');
            break;
        }
    }
    
    // TTF/OTFフォントが見つかった場合
    if ($fontPath !== null) {
        // フォントサイズ
        $fontSize = 24; // 大きめのサイズ
        
        // テキストのバウンディングボックスを取得
        $textBox = imagettfbbox($fontSize, 0, $fontPath, $text);
        if ($textBox !== false) {
            $textWidth = $textBox[2] - $textBox[0];
            $textHeight = $textBox[1] - $textBox[7];
            
            // 中央揃えの計算
            $textX = (500 - $textWidth) / 2;
            // テキストを5px上に調整（$textAreaHeight + $textHeight) / 2 から 5px引く）
            $textY = $textAreaY + ($textAreaHeight + $textHeight) / 2 - 5;
            
            // フォントでテキスト描画（太さを増すために複数回描画）
            $success = false;
            
            // 背景色と同じ青での輪郭描画（テキストを強調するため）
            $bgBlue = imagecolorallocate($newImage, 101, 139, 193);
            
            // 太字効果のためのオフセット値
            $offset = 1;
            
            // 輪郭描画（テキストを目立たせる）
            for ($x = -$offset; $x <= $offset; $x++) {
                for ($y = -$offset; $y <= $offset; $y++) {
                    // 中心位置はスキップ（メインのテキスト用）
                    if ($x == 0 && $y == 0) continue;
                    
                    // 薄い青色で縁取り
                    imagettftext($newImage, $fontSize, 0, $textX + $x, $textY + $y, $bgBlue, $fontPath, $text);
                }
            }
            
            // メインのテキスト描画（白色）
            if (imagettftext($newImage, $fontSize, 0, $textX, $textY, $textColor, $fontPath, $text) !== false) {
                writeLog("フォントでテキスト描画成功: サイズ=$fontSize, 幅=$textWidth, 高さ=$textHeight", 'INFO');
                $success = true;
            }
            
            if (!$success) {
                writeLog("フォントでテキスト描画失敗", 'ERROR');
                // 内蔵フォントを使用（フォールバック）
                drawWithBitmapFont($newImage, $text, $textAreaY, $textAreaHeight, $textColor);
            }
        } else {
            writeLog("テキストボックスの計算失敗", 'ERROR');
            // 内蔵フォントを使用（フォールバック）
            drawWithBitmapFont($newImage, $text, $textAreaY, $textAreaHeight, $textColor);
        }
    } else {
        writeLog("適切なフォントが見つかりませんでした。内蔵フォントを使用します。", 'WARNING');
        // 内蔵フォントを使用
        drawWithBitmapFont($newImage, $text, $textAreaY, $textAreaHeight, $textColor);
    }
    
    // 画像の保存
    imagepng($newImage, $filePath);
    
    // リソースの解放
    imagedestroy($image);
    imagedestroy($newImage);
    
    // 成功レスポンス
    $imageUrl = "images/TMP/" . $fileName;
    echo json_encode([
        'success' => true, 
        'image_url' => $imageUrl,
        'file_name' => $fileName
    ]);
    
    writeLog('QRコード画像作成成功: ' . $fileName);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    writeLog('QRコード作成エラー: ' . $e->getMessage(), 'ERROR');
}

// 内蔵フォントで描画する関数
function drawWithBitmapFont($image, $text, $areaY, $areaHeight, $textColor) {
    // 内蔵フォントを使用
    $font = 5; // 大きいビルトインフォント
    $textWidth = imagefontwidth($font) * strlen($text);
    $textHeight = imagefontheight($font);
    
    // 拡大なしの中央揃え（拡大処理は省略）
    $textX = (500 - $textWidth) / 2;
    // テキストを5px上に調整
    $textY = $areaY + ($areaHeight - $textHeight) / 2 - 5;
    
    // シンプルに描画
    imagestring($image, $font, $textX, $textY, $text, $textColor);
    writeLog('内蔵フォントでテキスト描画: x=' . $textX . ', y=' . $textY, 'INFO');
} 
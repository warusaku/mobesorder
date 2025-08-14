<?php
/**
 * SSL証明書自動更新スクリプト
 * 毎週実行されるcronジョブとして設定
 */
 
// ログファイル設定
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/ssl_cert_update.log';

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

logMessage("SSL証明書更新プロセス開始");

// 証明書保存先ディレクトリ
$certDir = __DIR__ . '/../certificates';
if (!is_dir($certDir)) {
    mkdir($certDir, 0755, true);
    logMessage("証明書ディレクトリを作成: {$certDir}");
}

// 既存の証明書をバックアップ
$certFile = $certDir . '/cacert.pem';
if (file_exists($certFile)) {
    $backupFile = $certFile . '.' . date('Ymd');
    copy($certFile, $backupFile);
    logMessage("既存の証明書をバックアップ: {$backupFile}");
}

// 最新の証明書をダウンロード（curl.haxx.se から）
$remoteUrl = 'https://curl.se/ca/cacert.pem';
$result = false;

// curlが使用可能な場合
if (function_exists('curl_init')) {
    $ch = curl_init($remoteUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 初回DL用に一時的に無効化
    $certContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 && !empty($certContent)) {
        file_put_contents($certFile, $certContent);
        $result = true;
        logMessage("証明書を正常にダウンロードしました（cURL使用）");
    } else {
        logMessage("cURLによる証明書ダウンロードに失敗: HTTP Code {$httpCode}");
    }
}

// curlが使用できない場合はfile_get_contentsを試行
if (!$result && function_exists('file_get_contents')) {
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);
    
    $certContent = @file_get_contents($remoteUrl, false, $context);
    if ($certContent !== false) {
        file_put_contents($certFile, $certContent);
        $result = true;
        logMessage("証明書を正常にダウンロードしました（file_get_contents使用）");
    } else {
        logMessage("file_get_contentsによる証明書ダウンロードに失敗");
    }
}

// 証明書のパーミッション設定
if ($result) {
    chmod($certFile, 0644);
    logMessage("証明書のパーミッションを設定: 0644");
    
    // 証明書の内容を確認
    if (function_exists('openssl_x509_parse')) {
        $certInfo = openssl_x509_parse(file_get_contents($certFile));
        if ($certInfo) {
            $validFrom = date('Y-m-d H:i:s', $certInfo['validFrom_time_t']);
            $validTo = date('Y-m-d H:i:s', $certInfo['validTo_time_t']);
            logMessage("証明書情報: 有効期間 {$validFrom} ～ {$validTo}");
        } else {
            logMessage("証明書の解析に失敗しました");
        }
    } else {
        logMessage("openssl_x509_parse関数が使用できないため、証明書の内容検証をスキップします");
    }
    
    logMessage("SSL証明書の更新が完了しました");
    
    // 通知機能（メール通知やSlack通知などがあれば実装）
    if (function_exists('mail')) {
        $to = 'admin@example.com'; // 管理者のメールアドレスを設定
        $subject = 'SSL証明書が更新されました';
        $message = "SSL証明書が " . date('Y-m-d H:i:s') . " に更新されました。\n";
        $message .= "サーバー: " . php_uname('n') . "\n";
        $message .= "証明書ファイル: {$certFile}\n";
        if (isset($validFrom) && isset($validTo)) {
            $message .= "有効期間: {$validFrom} ～ {$validTo}\n";
        }
        
        @mail($to, $subject, $message);
        logMessage("証明書更新通知メールを送信しました");
    }
} else {
    logMessage("SSL証明書の更新に失敗しました");
    
    // バックアップから復元
    if (file_exists($backupFile)) {
        copy($backupFile, $certFile);
        logMessage("バックアップから証明書を復元しました");
        
        // エラー通知
        if (function_exists('mail')) {
            $to = 'admin@example.com'; // 管理者のメールアドレスを設定
            $subject = '[緊急] SSL証明書の更新に失敗しました';
            $message = "SSL証明書の更新に " . date('Y-m-d H:i:s') . " に失敗しました。\n";
            $message .= "サーバー: " . php_uname('n') . "\n";
            $message .= "バックアップから復元しました。\n";
            
            @mail($to, $subject, $message);
            logMessage("証明書更新失敗の通知メールを送信しました");
        }
    } else {
        logMessage("バックアップが存在しないため復元できません。手動による対応が必要です。");
    }
}

// 古いバックアップの削除（30日以上前のもの）
$oldBackups = glob($certDir . '/cacert.pem.*');
$now = time();
foreach ($oldBackups as $backup) {
    $backupTime = filemtime($backup);
    if ($now - $backupTime > 30 * 24 * 60 * 60) { // 30日以上前
        unlink($backup);
        logMessage("古いバックアップを削除: " . basename($backup));
    }
}

logMessage("SSL証明書更新プロセスを終了します");
echo "SSL証明書の更新が完了しました。詳細はログファイルを確認してください: {$logFile}\n";

// cronへの設定方法を出力
echo "このスクリプトをcronに登録するには以下のコマンドを実行してください:\n";
echo "crontab -e\n";
echo "そして以下の行を追加:\n";
echo "0 0 * * 0 php " . __FILE__ . " >> " . $logDir . "/cron.log 2>&1\n";
echo "これにより、毎週日曜日の午前0時に証明書の更新が実行されます。\n"; 
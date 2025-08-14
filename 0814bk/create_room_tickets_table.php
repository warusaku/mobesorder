<?php
/**
 * room_ticketsテーブル作成スクリプト
 * 
 * 各客室に対応する保留伝票IDを管理するためのテーブルを作成します。
 */

// エラー表示を最大化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// コマンドラインから実行されたかどうかをチェック
$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    echo "コマンドラインモードで実行中...\n";
} else {
    echo '<html><head><title>Room Tickets テーブル作成</title>';
    echo '<style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1, h2 { color: #333; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background-color: #f5f5f5; padding: 10px; border-radius: 5px; overflow: auto; }
    </style>';
    echo '</head><body>';
    echo '<h1>Room Tickets テーブル作成</h1>';
}

// 設定ファイルを読み込む
try {
    require_once 'api/config/config.php';
    if ($isCli) echo "設定ファイルを読み込みました\n";
} catch (Exception $e) {
    if ($isCli) {
        echo "設定ファイル読み込みエラー: " . $e->getMessage() . "\n";
    } else {
        echo '<p class="error">設定ファイル読み込みエラー: ' . $e->getMessage() . '</p>';
    }
    exit;
}

try {
    require_once 'api/lib/Database.php';
    if ($isCli) echo "Databaseクラスを読み込みました\n";
} catch (Exception $e) {
    if ($isCli) {
        echo "Databaseクラス読み込みエラー: " . $e->getMessage() . "\n";
    } else {
        echo '<p class="error">Databaseクラス読み込みエラー: ' . $e->getMessage() . '</p>';
    }
    exit;
}

try {
    require_once 'api/lib/Utils.php';
    if ($isCli) echo "Utilsクラスを読み込みました\n";
} catch (Exception $e) {
    if ($isCli) {
        echo "Utilsクラス読み込みエラー: " . $e->getMessage() . "\n";
    } else {
        echo '<p class="error">Utilsクラス読み込みエラー: ' . $e->getMessage() . '</p>';
    }
    exit;
}

try {
    // データベース接続
    $db = Database::getInstance();
    
    if ($isCli) {
        echo "データベース接続成功\n";
    } else {
        echo '<p class="success">データベース接続成功</p>';
    }
    
    // テーブルの存在チェック
    $tableExists = $db->query("SHOW TABLES LIKE 'room_tickets'")->rowCount() > 0;
    
    if ($tableExists) {
        if ($isCli) {
            echo "room_ticketsテーブルは既に存在します。\n";
        } else {
            echo '<p class="warning">room_ticketsテーブルは既に存在します。</p>';
        }
        
        // テーブル構造を表示
        $columns = $db->query("DESCRIBE room_tickets")->fetchAll(PDO::FETCH_ASSOC);
        
        if ($isCli) {
            echo "現在のテーブル構造:\n";
            print_r($columns);
        } else {
            echo '<h2>現在のテーブル構造:</h2>';
            echo '<pre>';
            print_r($columns);
            echo '</pre>';
        }
        
        // レコード数を表示
        $count = $db->query("SELECT COUNT(*) FROM room_tickets")->fetchColumn();
        
        if ($isCli) {
            echo "現在のレコード数: {$count}件\n";
            echo "テーブルの再作成が必要な場合は、-f オプションを指定してください。\n";
            
            // 強制再作成フラグをチェック
            $forceRecreate = in_array('-f', $_SERVER['argv']);
            if ($forceRecreate) {
                $db->exec("DROP TABLE room_tickets");
                echo "room_ticketsテーブルを削除しました。\n";
                $tableExists = false;
            } else {
                exit;
            }
        } else {
            echo "<p>現在のレコード数: {$count}件</p>";
            echo '<p>テーブルを再作成しますか？ <strong>既存のデータはすべて失われます。</strong></p>';
            echo '<form method="post">';
            echo '<input type="hidden" name="drop_table" value="1">';
            echo '<button type="submit" style="background-color: #f44336; color: white; padding: 10px; border: none; cursor: pointer;">テーブルを削除して再作成</button>';
            echo '</form>';
            
            // テーブル削除のリクエストがあった場合
            if (isset($_POST['drop_table']) && $_POST['drop_table'] == 1) {
                $db->exec("DROP TABLE room_tickets");
                echo '<p class="success">room_ticketsテーブルを削除しました。</p>';
                $tableExists = false;
            } else {
                exit;
            }
        }
    }
    
    if (!$tableExists) {
        // テーブル作成SQL
        $sql = "CREATE TABLE `room_tickets` (
            `id` int NOT NULL AUTO_INCREMENT,
            `room_number` varchar(20) NOT NULL,
            `square_order_id` varchar(255) NOT NULL,
            `status` enum('OPEN','COMPLETED','CANCELED') NOT NULL DEFAULT 'OPEN',
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `room_number` (`room_number`),
            KEY `square_order_id` (`square_order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci";
        
        $db->exec($sql);
        
        if ($isCli) {
            echo "room_ticketsテーブルを作成しました。\n";
        } else {
            echo '<p class="success">room_ticketsテーブルを作成しました。</p>';
        }
        
        // テーブル構造を確認
        $columns = $db->query("DESCRIBE room_tickets")->fetchAll(PDO::FETCH_ASSOC);
        
        if ($isCli) {
            echo "作成されたテーブル構造:\n";
            print_r($columns);
        } else {
            echo '<h2>作成されたテーブル構造:</h2>';
            echo '<pre>';
            print_r($columns);
            echo '</pre>';
        }
    }
    
} catch (Exception $e) {
    if ($isCli) {
        echo "エラー: " . $e->getMessage() . "\n";
        // バックトレースを表示
        echo "詳細:\n";
        echo $e->getTraceAsString() . "\n";
    } else {
        echo '<p class="error">エラー: ' . $e->getMessage() . '</p>';
        echo '<pre class="error">' . $e->getTraceAsString() . '</pre>';
    }
}

if (!$isCli) {
    echo '<p><a href="test_dashboard.php">テストダッシュボードに戻る</a></p>';
    echo '</body></html>';
}
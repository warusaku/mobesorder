<div class="card">
    <h2>LacisMobileOrder テストダッシュボード</h2>
    <p>このダッシュボードでは、LacisMobileOrderシステムのテスト、監視、診断を行うことができます。</p>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-top: 30px;">
        <div class="card">
            <h3>ユニットテスト</h3>
            <p>個別の機能単位のテストを実行します</p>
            <a href="/fgsquare/test_dashboard.php?action=unittest" class="button">実行する</a>
        </div>
        
        <div class="card">
            <h3>統合テスト</h3>
            <p>複数の機能を連携させたテストを実行します</p>
            <a href="/fgsquare/test_dashboard.php?action=integrationtest" class="button">実行する</a>
        </div>
        
        <div class="card">
            <h3>E2Eテスト</h3>
            <p>エンドツーエンドのユーザーフローテストを実行します</p>
            <a href="/fgsquare/test_dashboard.php?action=e2etest" class="button">実行する</a>
        </div>
        
        <div class="card">
            <h3>ログ確認</h3>
            <p>システムログを確認します</p>
            <a href="/fgsquare/test_dashboard.php?action=logs" class="button secondary">確認する</a>
        </div>
        
        <div class="card">
            <h3>データベース確認</h3>
            <p>データベースの状態を確認します</p>
            <a href="/fgsquare/test_dashboard.php?action=database" class="button secondary">確認する</a>
        </div>
        
        <div class="card">
            <h3>Square連携確認</h3>
            <p>Square APIとの連携状態を確認します</p>
            <a href="/fgsquare/test_dashboard.php?action=square" class="button secondary">確認する</a>
        </div>
        
        <div class="card">
            <h3>保留伝票管理</h3>
            <p>各客室の保留伝票を管理します</p>
            <a href="/fgsquare/test_dashboard.php?action=room_tickets" class="button secondary">管理する</a>
        </div>
    </div>
</div>

<div class="card">
    <h2>システム情報</h2>
    <table>
        <tr>
            <th>項目</th>
            <th>値</th>
        </tr>
        <tr>
            <td>PHPバージョン</td>
            <td><?php echo phpversion(); ?></td>
        </tr>
        <tr>
            <td>サーバー</td>
            <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
        </tr>
        <tr>
            <td>データベース接続</td>
            <td>
                <?php
                try {
                    $db = Database::getInstance();
                    echo '<span style="color: green;">接続成功</span>';
                } catch (Exception $e) {
                    echo '<span style="color: red;">接続失敗: ' . htmlspecialchars($e->getMessage()) . '</span>';
                }
                ?>
            </td>
        </tr>
        <tr>
            <td>Square API</td>
            <td>
                <?php
                if (defined('SQUARE_ACCESS_TOKEN') && !empty(SQUARE_ACCESS_TOKEN) && 
                    defined('SQUARE_LOCATION_ID') && !empty(SQUARE_LOCATION_ID)) {
                    echo '<span style="color: green;">設定済み</span>';
                } else {
                    echo '<span style="color: red;">設定ファイルなし</span>';
                }
                ?>
            </td>
        </tr>
    </table>
</div> 
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LacisMobileOrder テスト環境 - 認証</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .auth-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 50px;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            text-align: center;
            transition: background 0.3s;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .error-message {
            color: #f44336;
            margin-top: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <h1>LacisMobileOrder<br>テスト環境認証</h1>
        
        <?php if (isset($_POST['admin_key']) && $_POST['admin_key'] !== ADMIN_KEY): ?>
        <div class="error-message">
            認証キーが正しくありません。
        </div>
        <?php endif; ?>
        
        <form method="POST" action="/fgsquare/test_dashboard.php">
            <div class="form-group">
                <label for="admin_key">管理者キー</label>
                <input type="password" id="admin_key" name="admin_key" required autofocus>
            </div>
            <button type="submit" class="btn">認証</button>
        </form>
    </div>
</body>
</html> 
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LacisMobileOrder テストダッシュボード</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .navbar {
            background-color: #333;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar h1 {
            margin: 0;
            font-size: 1.5rem;
        }
        .nav-links {
            display: flex;
            gap: 20px;
        }
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .nav-links a:hover {
            background-color: #555;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 5px;
        }
        .card {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .card h2 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        button, .button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            margin: 4px 2px;
            cursor: pointer;
            border-radius: 4px;
        }
        button.secondary, .button.secondary {
            background-color: #2196F3;
        }
        button.danger, .button.danger {
            background-color: #f44336;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
        }
        th {
            background-color: #f8f8f8;
        }
        pre {
            background-color: #f8f8f8;
            padding: 15px;
            border-radius: 5px;
            overflow: auto;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>LacisMobileOrder テストダッシュボード</h1>
        <div class="nav-links">
            <a href="test_dashboard.php">ホーム</a>
            <a href="test_dashboard.php?action=unittest">ユニットテスト</a>
            <a href="test_dashboard.php?action=integrationtest">統合テスト</a>
            <a href="test_dashboard.php?action=e2etest">E2Eテスト</a>
            <a href="test_dashboard.php?action=logs">ログ確認</a>
            <a href="test_dashboard.php?action=database">DB確認</a>
            <a href="test_dashboard.php?action=square">Square連携</a>
            <a href="test_dashboard.php?action=room_tickets">保留伝票管理</a>
            <a href="test_dashboard.php?logout=1">ログアウト</a>
        </div>
    </div>
    
    <div class="container"> 
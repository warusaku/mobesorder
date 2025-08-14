# テスト用ファイル一覧

このREADMEには、システムのデバッグとテストに使用するファイルの一覧とその目的を記載しています。

## データベース関連テスト

| ファイル名 | 目的 | 使用方法 |
|----------|------|---------|
| `lolipop_test.php` | ロリポップサーバーでのデータベース接続をテスト | FTPでサーバーにアップロードし、ブラウザでアクセス |
| `test_db_connection.php` | PDOを使用したデータベース接続をテスト | ローカル開発環境で実行 |
| `test_mysqli_connection.php` | MySQLiを使用したデータベース接続をテスト | ローカル開発環境で実行 |
| `test_sqlite_connection.php` | SQLiteを使用したデータベース接続をテスト | ローカル開発環境で実行 |
| `check_logs.php` | システムログの確認 | ローカル開発環境またはサーバーで実行 |
| `check_php_info.php` | PHP環境の設定情報を表示 | ローカル開発環境またはサーバーで実行 |

## テーブル作成用スクリプト

| ファイル名 | 目的 | 使用方法 |
|----------|------|---------|
| `create_room_tickets_table.php` | room_ticketsテーブルを作成 | PHPから実行するか、ブラウザでアクセス |
| `sql_room_tickets_table.sql` | room_ticketsテーブル作成用SQL | PHPMyAdminなどからSQLを実行 |

## 一時的なファイル（必要に応じて削除可能）

以下のファイルは一時的なもので、テストが完了したら削除してもかまいません：

- `db_test_result.html`
- `mysqli_test_result.html`
- `sqlite_test_result.html`
- `logs_result.html`
- `test_db.sqlite`

## テスト手順

1. まず `lolipop_test.php` をロリポップサーバーにアップロードし、データベース接続が正常に機能するか確認します
2. 接続に問題がなければ、`create_room_tickets_table.php` または `sql_room_tickets_table.sql` を使ってテーブルを作成します
3. テーブル作成後、実際のアプリケーションが正常に動作するか確認します

## トラブルシューティング

問題が発生した場合は以下を確認してください：

1. データベース接続情報（ホスト、ユーザー名、パスワード、データベース名）が正確か
2. PHP拡張機能（pdo_mysql、mysqli）が有効になっているか
3. ロリポップサーバーでデータベースへの接続が許可されているか
4. エラーログにアクセスして詳細な情報を確認する 
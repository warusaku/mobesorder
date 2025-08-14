# Mobes Kitchen Monitor

厨房管理システム for FGSquare プロジェクト

## 概要

Mobes Kitchen Monitor は、宿泊施設の厨房業務を効率化するためのタブレット対応システムです。注文の受信から配達まで、全ての工程をリアルタイムで管理できます。

## 主な機能

- **リアルタイム注文表示**: 新規注文の即座な表示と通知
- **ステータス管理**: 4段階の注文処理状況（注文済み→スタンバイ完了→配達済み→キャンセル）
- **優先度表示**: 経過時間による緊急度の自動判定
- **音声通知**: 新規注文受信時のチャイム音
- **タブレット最適化**: 8.4インチタブレット縦表示対応
- **キオスクモード**: 厨房での全画面表示モード

## システム要件

- PHP 8.0+
- MySQL 8.0+
- モダンブラウザ（Chrome, Firefox, Safari, Edge）
- タブレット推奨: 8.4インチ以上

## インストール手順

### 1. データベースセットアップ

```sql
-- データベースにステータス関連のテーブルを追加
SOURCE kitchen_monitor/database_schema.sql;
```

### 2. ファイル配置

```bash
# ロリポップサーバーの場合
/home/users/0/lolipop.jp-xxxx/web/kitchen_monitor/
```

### 3. 設定ファイル

`kitchen_monitor/includes/config.php` で以下を設定：

- データベース接続情報
- IPアクセス制限
- 音声通知設定
- キオスクモード設定

### 4. アクセス権限

```bash
chmod 755 kitchen_monitor/
chmod 644 kitchen_monitor/*.php
chmod 755 kitchen_monitor/api/
chmod 644 kitchen_monitor/api/*.php
```

## 使用方法

### アクセス

```
https://your-domain.com/kitchen_monitor/monitor.php
```

### 基本操作

1. **注文表示**: メイン画面に未完了の注文が時系列順で表示
2. **ステータス更新**: 
   - 「調理完了」ボタン: 注文済み → スタンバイ完了
   - 「配達完了」ボタン: スタンバイ完了 → 配達済み
   - 「キャンセル」ボタン: 任意のステータス → キャンセル
3. **完了済み表示**: 「完了済み表示」ボタンで配達済み・キャンセル済み注文を表示

### キーボードショートカット

- `R`: 手動更新
- `C`: 完了済み表示切り替え
- `M`: 音声通知切り替え
- `ESC`: モーダル閉じる

## ファイル構成

```
kitchen_monitor/
├── monitor.php              # メイン画面
├── database_schema.sql      # データベーススキーマ
├── README.md               # このファイル
├── api/
│   ├── get_orders.php       # 注文データ取得API
│   ├── update_status.php    # ステータス更新API
│   └── get_stats.php        # 統計情報取得API
├── css/
│   ├── monitor.css          # メインスタイル
│   └── tablet.css           # タブレット専用スタイル
├── js/
│   ├── monitor.js           # メイン機能
│   ├── status-update.js     # ステータス更新機能
│   ├── notifications.js     # 通知機能
│   └── sounds/
│       └── order-chime.mp3  # 注文通知音
└── includes/
    ├── config.php           # 設定ファイル
    └── functions.php        # 共通関数
```

## API エンドポイント

### GET /api/get_orders.php

注文データを取得

**パラメータ:**
- `show_completed`: boolean (デフォルト: false)
- `last_update`: datetime (差分取得用)

**レスポンス:**
```json
{
    "success": true,
    "data": {
        "orders": [...],
        "stats": {...},
        "last_update": "2025-08-02 15:13:45"
    }
}
```

### POST /api/update_status.php

注文ステータスを更新

**パラメータ:**
```json
{
    "order_detail_id": 123,
    "new_status": "ready",
    "note": "調理完了",
    "csrf_token": "..."
}
```

### GET /api/get_stats.php

統計情報を取得

**レスポンス:**
```json
{
    "success": true,
    "data": {
        "pending_orders": 8,
        "ready_orders": 3,
        "completed_today": 45,
        "average_prep_time": 12.5
    }
}
```

## 設定オプション

### config.php 主要設定

```php
return [
    'auto_refresh_interval' => 30000,  // 自動更新間隔（ミリ秒）
    'audio_enabled' => true,           // 音声通知有効
    'kiosk_mode' => true,             // キオスクモード
    'allowed_ips' => [                // アクセス許可IP
        '192.168.1.100',
        '192.168.1.101'
    ]
];
```

## トラブルシューティング

### よくある問題

1. **注文が表示されない**
   - データベース接続確認
   - order_details テーブルにstatusカラムが追加されているか確認

2. **音声が鳴らない**
   - ブラウザの自動再生ポリシーによる制限
   - ユーザー操作後に音声が有効化される

3. **IPアクセス制限エラー**
   - config.php の allowed_ips 設定を確認
   - サーバーのIPアドレス設定を確認

### ログ確認

```bash
# アプリケーションログ
tail -f /path/to/logs/app.log

# PHPエラーログ
tail -f /path/to/logs/php_errors.log
```

## セキュリティ

- IPアドレス制限による厨房内アクセス限定
- CSRF保護
- SQL インジェクション対策（プリペアドステートメント使用）
- XSS対策（出力エスケープ）

## パフォーマンス

- 30秒間隔の自動更新
- 楽観的UI更新による即座のフィードバック
- 差分更新による通信量削減
- インデックス最適化されたデータベースクエリ

## ブラウザ対応

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## ライセンス

FGSquare プロジェクト専用

## サポート

技術的な問題や質問については、開発チームまでお問い合わせください。
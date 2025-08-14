<?php
/**
 * 初期化ファイル
 * システム起動時に読み込まれる共通設定と初期化処理
 */

// メモリ制限を設定（メモリ不足エラー対策）
ini_set('memory_limit', '1536M'); // 1.5GBに増加

// ガベージコレクション設定
gc_enable();
ini_set('zend.enable_gc', 1);

// 大きな配列用にメモリを最適化
ini_set('pcre.backtrack_limit', 1000000);
ini_set('pcre.recursion_limit', 100000);

// 最大実行時間を設定（タイムアウト対策）
ini_set('max_execution_time', 300); // 300秒（5分）に延長

// コンフィグファイルの読み込み
require_once __DIR__ . '/config/config.php';

// クラスの自動読み込み設定
spl_autoload_register(function ($class_name) {
    // ライブラリディレクトリのクラスファイルをロード
    $library_path = __DIR__ . '/lib/' . $class_name . '.php';
    if (file_exists($library_path)) {
        require_once $library_path;
        return;
    }

    // モデルディレクトリのクラスファイルをロード
    $model_path = __DIR__ . '/models/' . $class_name . '.php';
    if (file_exists($model_path)) {
        require_once $model_path;
        return;
    }
});

// グローバルデータベース接続の初期化
$db = Database::getInstance();

// ユーティリティクラスの初期化
Utils::setCorsHeaders();

// GC（ガベージコレクション）を強制実行する関数
function forceGarbageCollection() {
    // 参照を解除して明示的にGCを実行
    if (gc_enabled()) {
        gc_collect_cycles();
    }
}

// スクリプト終了時にGCを実行するよう登録
register_shutdown_function('forceGarbageCollection'); 
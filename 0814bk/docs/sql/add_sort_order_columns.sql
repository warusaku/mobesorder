-- Products テーブルに表示順と表示設定のカラムを追加
ALTER TABLE products
ADD COLUMN sort_order INT NULL,
ADD COLUMN order_dsp TINYINT(1) NULL DEFAULT 1;

-- インデックスを追加してパフォーマンス向上
ALTER TABLE products
ADD INDEX idx_sort_order (sort_order),
ADD INDEX idx_order_dsp (order_dsp); 
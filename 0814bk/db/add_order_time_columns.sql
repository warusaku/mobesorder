-- category_descripterテーブルに営業時間関連のカラムを追加
ALTER TABLE category_descripter 
ADD COLUMN open_order_time TIME DEFAULT NULL AFTER last_order_time,
ADD COLUMN default_order_time TINYINT DEFAULT 1 AFTER open_order_time;

-- コメント追加
COMMENT ON COLUMN category_descripter.open_order_time IS '営業開始時間';
COMMENT ON COLUMN category_descripter.default_order_time IS 'デフォルト営業時間使用フラグ（1:使用、0:使用しない、NULL:使用）'; 
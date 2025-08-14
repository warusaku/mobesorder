テーブル名	カラム順	カラム名	型	NULL 制約	デフォルト値	追加情報	インデックス種別	ストレージエンジン	推定行数	
category_descripter	1	id	int	NOT NULL	NULL	auto_increment	PRIMARY KEY	InnoDB	18	
category_descripter	2	category_id	varchar(255)	NOT NULL	NULL		UNIQUE	InnoDB	18	
category_descripter	3	category_name	varchar(255)	NOT NULL	NULL			InnoDB	18	
category_descripter	4	display_order	int	NULL 可	100		INDEX	InnoDB	18	
category_descripter	5	is_active	tinyint(1)	NULL 可	1			InnoDB	18	
category_descripter	6	last_order_time	time	NULL 可	NULL			InnoDB	18	
category_descripter	7	created_at	timestamp	NULL 可	CURRENT_TIMESTAMP	DEFAULT_GENERATED		InnoDB	18	
category_descripter	8	updated_at	timestamp	NULL 可	CURRENT_TIMESTAMP	DEFAULT_GENERATED on update CURRENT_TIMESTAMP		InnoDB	18	
line_room_links	1	id	int	NOT NULL	NULL	auto_increment	PRIMARY KEY	InnoDB	0	
line_room_links	2	line_user_id	varchar(255)	NOT NULL	NULL		UNIQUE	InnoDB	0	
line_room_links	3	room_number	varchar(20)	NULL 可	NULL		INDEX	InnoDB	0	
line_room_links	4	user_name	varchar(255)	NULL 可	NULL			InnoDB	0	
line_room_links	5	check_in_date	date	NULL 可	NULL			InnoDB	0	
line_room_links	6	check_out_date	date	NULL 可	NULL			InnoDB	0	
line_room_links	7	access_token	varchar(64)	NULL 可	NULL		INDEX	InnoDB	0	
line_room_links	8	is_active	tinyint(1)	NULL 可	1			InnoDB	0	
line_room_links	9	created_at	timestamp	NULL 可	CURRENT_TIMESTAMP	DEFAULT_GENERATED		InnoDB	0	
line_room_links	10	updated_at	timestamp	NULL 可	CURRENT_TIMESTAMP	DEFAULT_GENERATED on update CURRENT_TIMESTAMP		InnoDB	0	
order_details	1	id	int	NOT NULL	NULL	auto_increment	PRIMARY KEY	InnoDB	7	
order_details	2	order_id	int	NOT NULL	NULL		INDEX	InnoDB	7	
order_details	3	square_item_id	varchar(255)	NOT NULL	NULL		INDEX	InnoDB	7	
order_details	4	product_name	varchar(255)	NULL 可	NULL			InnoDB	7	
order_details	5	unit_price	decimal(10,2)	NULL 可	NULL			InnoDB	7	
order_details	6	quantity	int	NOT NULL	1			InnoDB	7	
order_details	7	subtotal	decimal(10,2)	NULL 可	NULL			InnoDB	7	
order_details	8	note	text	NULL 可	NULL			InnoDB	7	
order_details	9	created_at	timestamp	NULL 可	CURRENT_TIMESTAMP	DEFAULT_GENERATED		InnoDB	7	
orders	1	id	int	NOT NULL	NULL	auto_increment	PRIMARY KEY	InnoDB	4	
orders	2	square_order_id	varchar(255)	NULL 可	NULL		UNIQUE	InnoDB	4	
orders	3	room_number	varchar(20)	NOT NULL	NULL		INDEX	InnoDB	4	
orders	4	guest_name	varchar(255)	NULL 可	NULL			InnoDB	4	
orders	5	line_user_id	varchar(50)	NULL 可	NULL			InnoDB	4	
orders	6	order_status	enum('OPEN','COMPLETED','CANCELED')	NOT NULL	OPEN		INDEX	InnoDB	4	
orders	7	total_amount	decimal(10,2)	NULL 可	0			InnoDB	4	
orders	8	note	text	NULL 可	NULL			InnoDB	4	
orders	9	order_datetime	datetime	NULL 可	CURRENT_TIMESTAMP	DEFAULT_GENERATED		InnoDB	4	
orders	10	checkout_datetime	datetime	NULL 可	NULL			InnoDB	4	
orders	11	created_at	timestamp	NULL 可	CURRENT_TIMESTAMP	DEFAULT_GENERATED		InnoDB	4	
orders	12	updated_at	timestamp	NULL 可	CURRENT_TIMESTAMP	DEFAULT_GENERATED on update CURRENT_TIMESTAMP		InnoDB	4	
products	1	id	int	NOT NULL	NULL	auto_increment	PRIMARY KEY	InnoDB	126	
products	2	square_item_id	varchar(255)	NOT NULL	NULL		UNIQUE	InnoDB	126	
products	3	name	varchar(255)	NOT NULL	NULL			InnoDB	126	
products	4	description	text	NULL 可	NULL			InnoDB	126	
products	5	price	decimal(10,2)	NOT NULL	NULL			InnoDB	126	
products	6	image_url	varchar(1024)	NULL 可	NULL			InnoDB	126	
products	7	stock_quantity	int	NULL 可	0			InnoDB	126	
products	8	local_stock_quantity	int	NULL 可	0			InnoDB	126	
products	9	category	varchar(255)	NULL 可	NULL		INDEX	InnoDB	126	
products	10	category_name	varchar(255)	NULL 可	NULL		INDEX	InnoDB	126	
products	11	is_active	tinyint(1)	NULL 可	1		INDEX	InnoDB	126	
products	12	created_at	timestamp	NULL 可	CURRENT_TIMESTAMP	DEFAULT_GENERATED		InnoDB	126	
products	13	updated_at	timestamp	NULL 可	CURRENT_TIMESTAMP	DEFAULT_GENERATED on update CURRENT_TIMESTAMP		InnoDB	126	
products	14	sort_order	int	NULL 可	NULL		INDEX	InnoDB	126	
products	15	order_dsp	tinyint(1)	NULL 可	1		INDEX	InnoDB	126	
room_tickets	1	id	int	NOT NULL	NULL	auto_increment	PRIMARY KEY	InnoDB	1	
room_tickets	2	room_number	varchar(20)	NOT NULL	NULL		INDEX	InnoDB	1	
room_tickets	3	square_order_id	varchar(255)	NOT NULL	NULL		INDEX	InnoDB	1	
room_tickets	4	status	enum('OPEN','COMPLETED','CANCELED')	NOT NULL	OPEN			InnoDB	1	
room_tickets	5	created_at	timestamp	NULL 可	CURRENT_TIMESTAMP	DEFAULT_GENERATED		InnoDB	1	
room_tickets	6	updated_at	timestamp	NULL 可	CURRENT_TIMESTAMP	DEFAULT_GENERATED on update CURRENT_TIMESTAMP		InnoDB	1	
room_tickets	7	guest_name	varchar(100)	NULL 可				InnoDB	1	
room_tokens	1	id	int	NOT NULL	NULL	auto_increment	PRIMARY KEY	InnoDB	0	
room_tokens	2	room_number	varchar(20)	NOT NULL	NULL		UNIQUE	InnoDB	0	
room_tokens	3	token	varchar(255)	NULL 可	NULL			InnoDB	0	
room_tokens	4	expires_at	datetime	NOT NULL	NULL			InnoDB	0	
room_tokens	5	access_token	varchar(64)	NOT NULL	NULL		UNIQUE	InnoDB	0	
room_tokens	6	is_active	tinyint(1)	NULL 可	1		INDEX	InnoDB	0	
room_tokens	7	guest_name	varchar(255)	NULL 可	NULL			InnoDB	0	
room_tokens	8	check_in_date	date	NULL 可	NULL			InnoDB	0	
room_tokens	9	check_out_date	date	NULL 可	NULL			InnoDB	0	
room_tokens	10	created_at	timestamp	NULL 可	CURRENT_TIMESTAMP	DEFAULT_GENERATED		InnoDB	0	
room_tokens	11	updated_at	timestamp	NULL 可	CURRENT_TIMESTAMP	DEFAULT_GENERATED on update CURRENT_TIMESTAMP		InnoDB	0	
roomdatasettings	1	id	int	NOT NULL	NULL	auto_increment	PRIMARY KEY	InnoDB	14	
roomdatasettings	2	room_number	varchar(20)	NULL 可	NULL		UNIQUE	InnoDB	14	
roomdatasettings	3	description	varchar(255)	NULL 可	NULL			InnoDB	14	
roomdatasettings	4	is_active	tinyint(1)	NOT NULL	1			InnoDB	14	
roomdatasettings	5	last_update	timestamp	NOT NULL	CURRENT_TIMESTAMP	DEFAULT_GENERATED on update CURRENT_TIMESTAMP		InnoDB	14	
status_master	1	status_code	varchar(20)	NOT NULL	NULL		PRIMARY KEY	InnoDB	3	
status_master	2	description	varchar(100)	NOT NULL	NULL			InnoDB	3	
status_master	3	created_at	timestamp	NULL 可	CURRENT_TIMESTAMP	DEFAULT_GENERATED		InnoDB	3	
sync_status	1	id	int	NOT NULL	NULL	auto_increment	PRIMARY KEY	InnoDB	0	
sync_status	2	provider	varchar(50)	NOT NULL	NULL		INDEX	InnoDB	0	
sync_status	3	table_name	varchar(50)	NOT NULL	NULL			InnoDB	0	
sync_status	4	last_sync_time	datetime	NOT NULL	NULL			InnoDB	0	
sync_status	5	status	varchar(20)	NOT NULL	NULL			InnoDB	0	
sync_status	6	details	text	NULL 可	NULL			InnoDB	0	
sync_status	7	created_at	timestamp	NULL 可	CURRENT_TIMESTAMP	DEFAULT_GENERATED		InnoDB	0	
sync_status	8	updated_at	timestamp	NULL 可	CURRENT_TIMESTAMP	DEFAULT_GENERATED on update CURRENT_TIMESTAMP		InnoDB	0	
system_logs	1	id	int	NOT NULL	NULL	auto_increment	PRIMARY KEY	InnoDB	3	
system_logs	2	log_level	enum('DEBUG','INFO','WARNING','ERROR','CRITICAL')	NULL 可	NULL		INDEX	InnoDB	3	
system_logs	3	log_source	varchar(255)	NOT NULL	NULL		INDEX	InnoDB	3	
system_logs	4	message	text	NOT NULL	NULL			InnoDB	3	
system_logs	5	context	json	NULL 可	NULL			InnoDB	3	
system_logs	6	created_at	timestamp	NULL 可	CURRENT_TIMESTAMP	DEFAULT_GENERATED	INDEX	InnoDB	3	
system_settings	1	setting_key	varchar(255)	NOT NULL	NULL		PRIMARY KEY	InnoDB	4	
system_settings	2	setting_value	text	NULL 可	NULL			InnoDB	4	
system_settings	3	updated_at	timestamp	NULL 可	CURRENT_TIMESTAMP	DEFAULT_GENERATED on update CURRENT_TIMESTAMP		InnoDB	4	
test_results	1	id	int	NOT NULL	NULL	auto_increment	PRIMARY KEY	InnoDB	0	
test_results	2	test_type	enum('UNIT','INTEGRATION','E2E')	NOT NULL	NULL		INDEX	InnoDB	0	
test_results	3	test_name	varchar(100)	NOT NULL	NULL			InnoDB	0	
テーブル名	カラム順	カラム名	型	NULL 制約	デフォルト値	追加情報	インデックス種別	ストレージエンジン	推定行数	
test_results	4	status	enum('PASS','FAIL','ERROR')	NOT NULL	NULL		INDEX	InnoDB	0	
test_results	5	message	text	NULL 可	NULL			InnoDB	0	
test_results	6	execution_time	float	NOT NULL	NULL			InnoDB	0	
test_results	7	executed_at	datetime	NOT NULL	NULL		INDEX	InnoDB	0	
test_write	1	id	int	NULL 可	NULL			InnoDB	0	
test_write	2	data	varchar(50)	NULL 可	NULL			InnoDB	0	
webhook_events	1	id	int	NOT NULL	NULL	auto_increment	PRIMARY KEY	InnoDB	0	
webhook_events	2	event_id	varchar(100)	NOT NULL	NULL		UNIQUE	InnoDB	0	
webhook_events	3	event_type	varchar(50)	NOT NULL	NULL			InnoDB	0	
webhook_events	4	processed_at	datetime	NOT NULL	NULL			InnoDB	0	
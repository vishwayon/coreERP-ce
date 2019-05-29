CREATE EXTENSION "uuid-ossp";

?==?
create schema sys;

?==?
Create Table sys.db_ver
(   db_ver_id Bigserial Primary Key,
    db_name varchar(50) Not Null,
    coreerp_ver varchar(50) Not Null,
    modules varchar(8000) Not Null,
    last_updated timestamp without time zone Not Null
);

?==?
Create table sys.table_def
(   table_def_id uuid Not Null,
    table_name Varchar(100) Not Null,
    column_def jsonb Not NUll,
    Constraint pk_sys_table_def Primary Key (table_def_id)
);

?==?
CREATE UNIQUE INDEX uk_sys_table_def on sys.table_def (Lower(table_name));

?==?
CREATE TABLE sys.mast_seq
(
  mast_seq_type varchar(50) NOT NULL PRIMARY KEY,
  seed bigint NOT NULL
);

?==?
CREATE TABLE sys.mast_seq_tran
(
  mast_seq_tran_id serial NOT NULL,
  company_id bigint NOT NULL,
  mast_seq_type varchar(50) NOT NULL,
  max_id bigint NOT NULL,
  lock_bit boolean NOT NULL,
  CONSTRAINT pk_sys_mast_seq_tran PRIMARY KEY (mast_seq_tran_id),
  CONSTRAINT uk_sys_mast_seq_tran UNIQUE (company_id, mast_seq_type)
);

?==?
CREATE TABLE sys.company
(
  company_id bigint NOT NULL,
  company_code varchar(2) NOT NULL,
  company_name varchar(500) NOT NULL,
  company_short_name varchar(80) NOT NULL,
  company_address varchar(1000) not null,
  company_logo character varying(500) NOT NULL DEFAULT '/cwf/vsla/assets/coreerp_logo.png',
  database varchar(128) not null,
  user_time_zone varchar(50) Not Null Default 'Asia/Kolkata',
  last_updated timestamp without time zone NOT NULL,
  CONSTRAINT pk_sys_company PRIMARY KEY (company_id),
  CONSTRAINT uk_sys_company_company_code UNIQUE (company_code)
);

?==?
INSERT INTO sys.mast_seq(mast_seq_type, seed) 
VALUES ('sys.company', 0);

?==?
CREATE TABLE sys.user
(
  user_id bigint NOT NULL,
  user_name varchar(20) NOT NULL,
  user_pass varchar(60) NOT NULL,
  full_user_name varchar(100) NOT NULL,
  email varchar(100) NOT NULL,
  is_active boolean NOT NULL DEFAULT false,
  is_owner boolean NOT NULL default false,
  is_admin boolean default false,
  auth_client Varchar(50) NOT NULL DEFAULT '',
  auth_person_id Varchar(50) NOT NULL DEFAULT '',
  auth_account Varchar(250) NOT NULL DEFAULT '',
  mac_addr varchar(20)[] not null Default '{}',
  is_mac_addr boolean not null default false, 
  mobile varchar(50) not null DEFAULT '',
  phone varchar(50) not null DEFAULT '',
  clfy_access boolean not null DEFAULT (FALSE),
  user_attr jsonb Not Null Default '{}',
  last_updated timestamp Not Null default current_timestamp(0),
  CONSTRAINT pk_sys_user PRIMARY KEY (user_id),
  CONSTRAINT uk_sys_user_user_name UNIQUE (user_name)
);

?==?
INSERT INTO sys.mast_seq(mast_seq_type, seed) 
VALUES ('sys.user', 0);

?==?
CREATE TABLE sys.user_session
(
  user_session_id varchar(32) NOT NULL,
  user_id bigint NOT NULL,
  auth_id varchar(32) NOT NULL,
  login_time timestamp without time zone NOT NULL,
  last_refresh_time timestamp without time zone NOT NULL,
  session_variables varchar(2500) NOT NULL,
  CONSTRAINT pk_sys_user_session PRIMARY KEY (user_session_id)
);

?==?
CREATE TABLE sys.user_logout
(
  user_session_id character varying(32) NOT NULL,
  user_id bigint NOT NULL,
  login_time timestamp without time zone NOT NULL,
  last_refresh_time timestamp without time zone NOT NULL,
  session_variables character varying(2500) NOT NULL,
  CONSTRAINT pk_sys_user_logout PRIMARY KEY (user_session_id)
);

?==?
Create table sys.user_token
(	user_id BigInt Not Null,
	token Varchar(32) Not Null,
	created_on Timestamp Not Null,
	Constraint pk_sys_user_token Primary key
	( user_id)
);

?==?
Create Table sys.user_failed_login
(   user_failed_login_id BigSerial Not Null,
    user_name Varchar(100) Null,
    user_ip inet,
    fail_rsn text,
    info json,
    last_updated timestamp not null default current_timestamp(0),
    Constraint pk_sys_user_failed_login Primary Key 
    (user_failed_login_id)
);

?==?
Create Table sys.user_otp
(   user_otp_id BigSerial Not Null,
    token uuid Not Null, 
    user_id BigInt Not Null,
    auth_id Varchar(32) Not Null,
    otp Varchar(20) Not Null,
    created_on Timestamp Not Null Default current_timestamp(0),
    used bool Not Null default false,
    used_on Timestamp,
    Constraint pk_sys_user_otp_id Primary Key
    (user_otp_id),
    Constraint uk_sys_user_otp_token Unique
    (token)
);

?==?
Create Table sys.time_zone
(   time_zone_id Varchar(50) Not Null,
    time_zone Varchar(50) Not Null,
    Constraint pk_sys_time_zone Primary Key (time_zone_id)
);

?==?
CREATE TABLE sys.feedback(
	feedback_id bigint NOT NULL default -1 primary key,
	user_id bigint NOT NULL default -1,
	feedback varchar(8000) Not NULL default '',
	username varchar(50) NOT NULL default '',
	category_id bigint NOT NULL default -1,
	is_closed boolean NOT NULL default false,
	status smallint NOT NULL default 0,
	remarks varchar(250) NOT NULL default '',
	bug_key varchar(50) NOT NULL default '',
	is_bug_created boolean NOT NULL default false,
	priority_id bigint NOT NULL default -1,
	closed_date timestamp NULL,
	summary varchar(250) NOT NULL default '',
	menu varchar(250) NOT NULL default '',
	company_id bigint NULL,
	last_updated timestamp NOT NULL default current_timestamp(0),
	additional_info xml,
	bug_id varchar(8000) NOT NULL default '',
	closed_by varchar(50) NOT NULL default '',
	application_type varchar(50) NOT NULL default ''
);

?==?
insert into sys.mast_seq(mast_seq_type, seed)
Values ('sys.feedback', 0);

?==?
CREATE TABLE sys.feedback_category(
	category_id bigint NOT NULL default -1,
	category varchar(250) NOT NULL default '',
	classification_id varchar(50) NOT NULL default ''
);

?==?
insert into sys.feedback_category(category_id, category, classification_id)
Values  (1000001 , 'Security',''),
        (1000002 , 'Crash/Hang',''),
        (1000003 , 'Data loss',''),
        (1000004 , 'Performance',''),
        (1000005 , 'UI/Usability',''),
        (1000006 , 'Other bug',''),
        (1000007 , 'Feature(New)',''),
        (1000008 , 'Enhancement','');

?==?
CREATE TABLE sys.feedback_priority
(
	priority_id bigint NOT NULL default -1,
	description varchar(50) NOT NULL default '',
	severity_id varchar(50) NOT NULL default ''
);

?==?
insert into sys.feedback_priority(priority_id, description, severity_id)
Values  (1, 'Show stopper',''),
        (2, 'Critical',''),
        (3, 'Major',''),
        (4, 'Minor','');

?==?
CREATE TABLE sys.feedback_status
(
	status_id bigint NOT NULL default -1,
	status varchar(10) NOT NULL default ''
);

?==?
insert into sys.feedback_status(status_id, status)
Values  (1, 'Open'),
        (2, 'InProgress'),
        (3, 'ToBeTested'),
        (4, 'Closed');

?==?

CREATE TABLE sys.user_session_history
(
  user_session_id varchar(32) NOT NULL,
  user_id bigint NOT NULL,
  login_time timestamp without time zone NOT NULL,
  last_refresh_time timestamp without time zone NOT NULL,
  session_variables varchar(2500) NOT NULL,
  CONSTRAINT pk_sys_user_session_history PRIMARY KEY (user_session_id)
);

?==?
create table sys.user_to_company
(
  user_to_company_id varchar(50),
  user_id bigint not null,
  company_id bigint not null,
  last_updated timestamp not null,
    CONSTRAINT pk_user_to_company PRIMARY KEY (user_to_company_id)
);

?==?
insert into sys.mast_seq(mast_seq_type, seed)
Values ('sys.user_company_association', 0);

?==?
CREATE TABLE sys.user_pass_reset
(
  reset_id bigint NOT NULL,
  user_id bigint NOT NULL,
  reset_uuid uuid NULL,
  reset_status int NOT NULL,
  used_time timestamp  NULL,
  CONSTRAINT pk_sys_user_pass_reset PRIMARY KEY (reset_id)
);

?==?
CREATE TABLE sys.notification_mail
(
  notification_mail_id bigint NOT NULL,
  mail_to varchar(2000) NOT NULL,
  mail_from varchar(100) NOT NULL,
  body text NOT NULL,
  subject varchar(250) NOT NULL,
  cc varchar(2000) NOT NULL,
  bcc varchar(2000) NOT NULL,
  is_send smallint NOT NULL,
  reply_to varchar(100) NOT NULL,
  last_updated timestamp NOT NULL DEFAULT now(),
  attachment_path character varying(250) default('')
);

?==?
CREATE TABLE sys.restrict_ip 
(
	restrict_ip_id bigint NOT NULL,
	domain Varchar(50) NOT NULL, 
	ip Varchar(18) NOT NULL, 
	last_updated timestamp without time zone, 
	CONSTRAINT pk_sys_restrict_ip PRIMARY KEY (restrict_ip_id), 
	CONSTRAINT uk_sys_restrict_ip UNIQUE (domain, ip) 
);

?==?
INSERT INTO sys.mast_seq(mast_seq_type, seed)
VALUES ('sys.restrict_ip', 0);

?==?
Create Table sys.track_report
(	track_report_id BigSerial Not Null,
	report_uuid uuid Not Null,
	report_path Varchar(768) Not Null,
	Constraint pk_sys_track_report Primary Key
	( report_uuid ),
	Constraint uk_sys_track_report Unique
	( report_path )
);

?==?
CREATE TABLE sys.menu_admin
(
  menu_id bigint NOT NULL,
  parent_menu_id bigint NOT NULL,
  menu_name character varying(100) NOT NULL,
  menu_text character varying(250) NOT NULL,
  menu_type smallint NOT NULL,
  bo_id uuid,
  is_hidden boolean NOT NULL,
  last_updated timestamp without time zone NOT NULL DEFAULT now(),
  link_path character varying(250),
  menu_key character varying(10) DEFAULT ''::character varying,
  company_na boolean default false,
  is_staged boolean not null default(false),
  CONSTRAINT pk_sys_menu_admin PRIMARY KEY (menu_id),
  CONSTRAINT uk_sys_menu_admin UNIQUE (menu_name)
);

?==?
CREATE TABLE sys.menu_admin_seq
(
  menu_level character varying(1) NOT NULL,
  max_id bigint NOT NULL,
  CONSTRAINT pk_sys_menu_admin_seq PRIMARY KEY (menu_level)
);

?==?
CREATE TABLE sys.user_dashboard
(
  user_dashboard_id bigint NOT NULL,
  user_id bigint NOT NULL,
  dashboard_xml xml NOT NULL,
  last_updated timestamp without time zone,
  CONSTRAINT pk_sys_user_dashboard PRIMARY KEY (user_dashboard_id)
);

?==?
CREATE TABLE sys.subscription
(
  subscription_id bigint not null,
  company_id bigint not null,
  user_id bigint not null,
  sub_name varchar(50) not null default(''),
  report_path character varying(250) not null,
  report_options jsonb NOT NULL DEFAULT '{}'::jsonb,
  schedule_info jsonb NOT NULL DEFAULT '{}'::jsonb,
  is_active boolean not null default(false),
  last_updated timestamp without time zone not null DEFAULT now(),
  CONSTRAINT pk_sys_subscription PRIMARY KEY (subscription_id)
);

?==?
insert into sys.mast_seq(mast_seq_type, seed)
Select 'sys.subscription', 0;

?==?
CREATE TABLE sys.subscription_log
(
  subscription_log_id bigint not null,
  subscription_id bigint not null,
  exec_time timestamp without time zone not null,
  exec_log character varying(2500) not null,
  last_updated timestamp without time zone not null,
  CONSTRAINT pk_sys_subscription_log PRIMARY KEY (subscription_log_id)
);

?==?
Create Table sys.remote_server
(	remote_server_id BigInt Not Null,
	remote_server_name Varchar(50) Not Null,
	remote_server_user Varchar(20) Not Null,
	remote_server_pass Varchar(50) Not Null,
	last_updated Timestamp Not Null,
	Constraint pk_sys_remote_server Primary Key (remote_server_id)
);

?==?
Insert Into sys.remote_server
Select 1, 'local', '', '', current_timestamp(0);

?==?
insert into sys.mast_seq(mast_seq_type, seed)
Select 'sys.remote_server', 1;

?==?
Create Table sys.wiz_cache
(   wiz_cache_id uuid Not Null Primary Key, 
    wiz_data text Not Null, 
    last_updated timestamp Not Null
);

?==?
Create Table sys.hsn_sc
(   hsn_sc_id BigInt Not Null,
    hsn_sc_ch Varchar(2) Not Null,
    hsn_sc_code Varchar(8) Not Null,
    hsn_sc_desc Varchar(250) Not Null,
    hsn_sc_type Varchar(1) Not Null,
    last_updated Timestamp Not Null,
    Constraint pk_tx_hsn_sc Primary Key (hsn_sc_id)
);

?==?
-- Defaults required for Exmempt Goods and Services
INSERT INTO sys.hsn_sc(hsn_sc_id, hsn_sc_ch, hsn_sc_code, hsn_sc_desc, hsn_sc_type, last_updated)
VALUES  (-99, 'NG', 'NONGST', 'Non-GST Goods/Services', 'G', current_timestamp(0)),
        (0, '00', '00', 'GST Exempt Goods', 'G', current_timestamp(0)),
        (99000, '99', '9900', 'GST Exempt Services', 'S', current_timestamp(0));

?==?
Create Table sys.hsn_sc_uom
(   hsn_sc_uom_id BigInt Not Null,
    uom_code Varchar(3) Not Null,
    uom_desc Varchar(50) Not Null,
    last_updated Timestamp Not Null,
    Constraint pk_sys_hsn_sc_uom Primary Key (hsn_sc_uom_id)
);

?==?
Insert Into sys.hsn_sc_uom(hsn_sc_uom_id, uom_code, uom_desc, last_updated)
Values
    (1, 'BAG', 'BAGS', current_timestamp(0)),
    (2, 'BAL', 'BALE', current_timestamp(0)),
    (3, 'BDL', 'BUNDLES', current_timestamp(0)),
    (4, 'BKL', 'BUCKLES', current_timestamp(0)),
    (5, 'BOU', 'BILLION OF UNITS', current_timestamp(0)),
    (6, 'BOX', 'BOX', current_timestamp(0)),
    (7, 'BTL', 'BOTTLES', current_timestamp(0)),
    (8, 'BUN', 'BUNCHES', current_timestamp(0)),
    (9, 'CAN', 'CANS', current_timestamp(0)),
    (10, 'CBM', 'CUBIC METERS', current_timestamp(0)),
    (11, 'CCM', 'CUBIC CENTIMETERS', current_timestamp(0)),
    (12, 'CMS', 'CENTIMETERS', current_timestamp(0)),
    (13, 'CTN', 'CARTONS', current_timestamp(0)),
    (14, 'DOZ', 'DOZENS', current_timestamp(0)),
    (15, 'DRM', 'DRUMS', current_timestamp(0)),
    (16, 'GGK', 'GREAT GROSS', current_timestamp(0)),
    (17, 'GMS', 'GRAMMES', current_timestamp(0)),
    (18, 'GRS', 'GROSS', current_timestamp(0)),
    (19, 'GYD', 'GROSS YARDS', current_timestamp(0)),
    (20, 'KGS', 'KILOGRAMS', current_timestamp(0)),
    (21, 'KLR', 'KILOLITRE', current_timestamp(0)),
    (22, 'KME', 'KILOMETRE', current_timestamp(0)),
    (23, 'MLT', 'MILILITRE', current_timestamp(0)),
    (24, 'MTR', 'METERS', current_timestamp(0)),
    (25, 'MTS', 'METRIC TON', current_timestamp(0)),
    (26, 'NOS', 'NUMBERS', current_timestamp(0)),
    (27, 'PAC', 'PACKS', current_timestamp(0)),
    (28, 'PCS', 'PIECES', current_timestamp(0)),
    (29, 'PRS', 'PAIRS', current_timestamp(0)),
    (30, 'QTL', 'QUINTAL', current_timestamp(0)),
    (31, 'ROL', 'ROLLS', current_timestamp(0)),
    (32, 'SET', 'SETS', current_timestamp(0)),
    (33, 'SQF', 'SQUARE FEET', current_timestamp(0)),
    (34, 'SQM', 'SQUARE METERS', current_timestamp(0)),
    (35, 'SQY', 'SQUARE YARDS', current_timestamp(0)),
    (36, 'TBS', 'TABLETS', current_timestamp(0)),
    (37, 'TGM', 'TEN GROSS', current_timestamp(0)),
    (38, 'THD', 'THOUSANDS', current_timestamp(0)),
    (39, 'TON', 'TONNES', current_timestamp(0)),
    (40, 'TUB', 'TUBES', current_timestamp(0)),
    (41, 'UGS', 'US GALLONS', current_timestamp(0)),
    (42, 'UNT', 'UNITS', current_timestamp(0)),
    (43, 'YDS', 'YARDS', current_timestamp(0)),
    (44, 'OTH', 'OTHERS', current_timestamp(0));

?==?
Create Table sys.ewb_uom
(   ewb_uom_id BigInt Not Null,
    uom_code Varchar(3) Not Null,
    uom_desc Varchar(50) Not Null,
    last_updated Timestamp Not Null,
    Constraint pk_sys_ewb_uom Primary Key (ewb_uom_id)
);

?==?
Insert Into sys.ewb_uom(ewb_uom_id, uom_code, uom_desc, last_updated)
Values
    (1, 'BGS', 'BAGS', current_timestamp(0)),
    (2, 'BND', 'BUNDLES', current_timestamp(0)),
    (3, 'BOX', 'BOX', current_timestamp(0)),
    (4, 'CMS', 'CENTIMETERS', current_timestamp(0)),
    (5, 'DZN', 'DOZENS', current_timestamp(0)),
    (6, 'GMS', 'GRAMMES', current_timestamp(0)),
    (7, 'HKM', 'HUNDRED KILOMETERS', current_timestamp(0)),
    (8, 'HNO', 'HUNDRED NUMBERS/UNITS', current_timestamp(0)),
    (9, 'KGS', 'KILOGRAMS', current_timestamp(0)),
    (10, 'KMS', 'KILOMETRE', current_timestamp(0)),
    (11, 'LTS', 'LITRES', current_timestamp(0)),
    (12, 'MLS', 'MILILITRE', current_timestamp(0)),
    (13, 'MTR', 'METERS', current_timestamp(0)),
    (14, 'MTS', 'METRIC TON', current_timestamp(0)),
    (15, 'NOS', 'NUMBERS', current_timestamp(0)),
    (16, 'PAR', 'PAIRS', current_timestamp(0)),
    (17, 'QTS', 'QUINTAL', current_timestamp(0)),
    (18, 'SNO', 'THOUSAND NUMBERS/UNITS', current_timestamp(0)),
    (19, 'TKM', 'THOUSAND KILOMETERS', current_timestamp(0)),
    (20, 'TLT', 'THOUSAND LITRES', current_timestamp(0)),
    (21, 'TNO', 'TEN NUMBERS/UNITS', current_timestamp(0)),
    (22, 'TON', 'TONNES', current_timestamp(0));

?==?
Create Table sys.gst_state
(	gst_state_id BigInt Not Null,
 	gst_state_code Varchar(2) Not Null,
 	state_name Varchar(250) Not Null,
 	state_code Varchar(2) Not Null,
 	active Boolean Not Null,
 	last_updated Timestamp Not Null,
 	Constraint pk_tx_gst_state Primary Key (gst_state_id),
 	Constraint uk_tx_gst_state_gst_code Unique (gst_state_code)
);

?==?
Insert Into sys.gst_state(gst_state_id, state_name, state_code, gst_state_code, active, last_updated)
Values(1,'Jammu and Kashmir','JK','01',TRUE, current_timestamp(0)),
    (2,'Himachal Pradesh','HP','02',TRUE, current_timestamp(0)),
    (3,'Punjab','PB','03',TRUE, current_timestamp(0)),
    (4,'Chandigarh','CH','04',TRUE, current_timestamp(0)),
    (5,'Uttarakhand','UT','05',TRUE, current_timestamp(0)),
    (6,'Haryana','HR','06',TRUE, current_timestamp(0)),
    (7,'Delhi','DL','07',TRUE, current_timestamp(0)),
    (8,'Rajasthan','RJ','08',TRUE, current_timestamp(0)),
    (9,'Uttar Pradesh','UP','09',TRUE, current_timestamp(0)),
    (10,'Bihar','BH','10',TRUE, current_timestamp(0)),
    (11,'Sikkim','SK','11',TRUE, current_timestamp(0)),
    (12,'Arunachal Pradesh','AR','12',TRUE, current_timestamp(0)),
    (13,'Nagaland','NL','13',TRUE, current_timestamp(0)),
    (14,'Manipur','MN','14',TRUE, current_timestamp(0)),
    (15,'Mizoram','MI','15',TRUE, current_timestamp(0)),
    (16,'Tripura','TR','16',TRUE, current_timestamp(0)),
    (17,'Meghalaya','ME','17',TRUE, current_timestamp(0)),
    (18,'Assam','AS','18',TRUE, current_timestamp(0)),
    (19,'West Bengal','WB','19',TRUE, current_timestamp(0)),
    (20,'Jharkhand','JH','20',TRUE, current_timestamp(0)),
    (21,'Odisha','OR','21',TRUE, current_timestamp(0)),
    (22,'Chattisgarh','CT','22',TRUE, current_timestamp(0)),
    (23,'Madhya Pradesh','MP','23',TRUE, current_timestamp(0)),
    (24,'Gujarat','GJ','24',TRUE, current_timestamp(0)),
    (25,'Daman and Diu','DD','25',TRUE, current_timestamp(0)),
    (26,'Dadra and Nagar Haveli','DN','26',TRUE, current_timestamp(0)),
    (27,'Maharashtra','MH','27',TRUE, current_timestamp(0)),
    (29,'Karnataka','KA','29',TRUE, current_timestamp(0)),
    (30,'Goa','GA','30',TRUE, current_timestamp(0)),
    (31,'Lakshadweep Islands','LD','31',TRUE, current_timestamp(0)),
    (32,'Kerala','KL','32',TRUE, current_timestamp(0)),
    (33,'Tamil Nadu','TN','33',TRUE, current_timestamp(0)),
    (34,'Pondicherry','PY','34',TRUE, current_timestamp(0)),
    (35,'Andaman and Nicobar Islands','AN','35',TRUE, current_timestamp(0)),
    (36,'Telangana','TS','36',TRUE, current_timestamp(0)),
    (37,'Andhra Pradesh (New)','AD','37',TRUE, current_timestamp(0)),
    (98,'Special Economic Zones (SEZ)','SZ', '98', TRUE, current_timestamp(0)),
    (99,'Outside India','OI','99',TRUE, current_timestamp(0));

?==?

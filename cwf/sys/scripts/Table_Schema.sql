Create Extension postgres_fdw;

?==?
Create Extension tablefunc;

?==?
CREATE SERVER {dbMain} FOREIGN DATA WRAPPER postgres_fdw OPTIONS (host 'localhost', dbname '{dbMain}', port '5432');

?==?
create USER MAPPING FOR {suName} SERVER {dbMain}  OPTIONS (user '{suName}', password '{suPass}');

?==?
create schema sys;

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
CREATE foreign TABLE sys.user
(
  user_id bigint NOT NULL,
  user_name varchar(20) NOT NULL,
  full_user_name varchar(100) NOT NULL,
  email varchar(100) NOT NULL,
  is_active boolean NOT NULL DEFAULT false,
  is_owner boolean NOT NULL default false,
  is_admin boolean default false, 
  mobile varchar(50) not null,
  phone varchar(50) not null,
  clfy_access boolean not null DEFAULT (FALSE),
  last_updated timestamp Not Null
)
server {dbMain} options(table_name 'user' );

?==?
CREATE foreign TABLE sys.company
(
  company_id bigint NOT NULL,
  company_code varchar(2) NOT NULL,
  company_name varchar(500) NOT NULL,
  company_short_name varchar(80) NOT NULL,
  company_address varchar(1000) not null,
  company_logo character varying(500) NOT NULL DEFAULT '/cwf/vsla/assets/coreerp_logo.png',
  database varchar(128) not null,
  user_time_zone varchar(50) Not Null Default 'Asia/Kolkata',
  last_updated timestamp without time zone NOT NULL
)
server {dbMain} options(table_name 'company' );

?==?
CREATE foreign TABLE sys.remote_server
(   remote_server_id BigInt Not Null,
    remote_server_name Varchar(50) Not Null,
    remote_server_user Varchar(20) Not Null,
    remote_server_pass Varchar(50) Not Null,
    last_updated Timestamp Not Null
)
server {dbMain} options(table_name 'remote_server' );

?==?
Create Table sys.menu_seq
(	menu_level Varchar(1) Not Null,
	max_id BigInt Not Null,
	Constraint pk_sys_menu_seq Primary Key (menu_level)
);

?==?
CREATE TABLE sys.menu
(
  menu_id bigint NOT NULL,
  parent_menu_id BigInt NOT NULL,
  menu_key Varchar(10),
  menu_name varchar(100) NOT NULL,
  menu_text varchar(250) NOT NULL,
  menu_type smallint NOT NULL,
  bo_id uuid NULL,
  is_hidden boolean NOT NULL,
  last_updated timestamp without time zone NOT NULL,
  link_path varchar(250),
  menu_code varchar(4) not null default '',
  is_staged boolean not null default(false),
  count_class character varying not null default '',
  vch_type varchar[] default '{}',
  CONSTRAINT pk_sys_menu PRIMARY KEY (menu_id)
);

?==?
CREATE UNIQUE INDEX uk_sys_menu on sys.menu (Lower(menu_name)); 

?==?
Create Table sys.db_ver
(   db_ver_id Bigserial Primary Key,
    db_name varchar(50) Not Null,
    coreerp_ver varchar(50) Not Null,
    modules varchar(8000) Not Null,
    last_updated timestamp without time zone Not Null
);

?==?
CREATE TABLE sys.mast_seq
(
  mast_seq_type varchar(50) NOT NULL,
  seed bigint NOT NULL,
  CONSTRAINT pk_sys_mast_seq PRIMARY KEY (mast_seq_type)
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
CREATE TABLE sys.doc_seq
(
  branch_id bigint NOT NULL,
  doc_type varchar(4) NOT NULL,
  doc_table varchar(150) NOT NULL,
  finyear varchar(4) NOT NULL,
  max_voucher_no bigint NOT NULL DEFAULT 0,
  lock_bit boolean NOT NULL,
  CONSTRAINT pk_doc_seq PRIMARY KEY (branch_id, doc_type, finyear)
);

?==?
Create table sys.doc_es(
	voucher_id varchar(50) not null primary key,	
        doc_date date not null default '1970-01-01',
	entered_by varchar(100) not null default '',
	entered_user varchar(20) not null default '',
	entered_on timestamp null,
	posted_by varchar(100) not null default '',
	posted_user varchar(100) not null default '',
	posted_on timestamp null
);

?==?
Create Table sys.doc_created
(	doc_id Varchar(50), 
	branch_id BigInt, 
	bo_id Varchar(250), 
	user_id_created BigInt, 
	doc_status Int, 
	last_updated Timestamp,
        Constraint pk_sys_doc_created Primary Key
	( doc_id )
);

?==?
Create Table sys.doc_wf
(	doc_id Varchar(50) Not Null,
        doc_date date NOT NULL default '1970-01-01',
        branch_id BigInt Not Null,
        finyear varchar(4) not null default '',
	bo_id Varchar(250) Not Null,
	edit_view Varchar(250) Not Null,
	doc_name Varchar(250) Not Null,
	doc_sender_comment Varchar(500) Not Null,
	user_id_from BigInt Not Null,
	doc_sent_on Timestamp without time zone Not Null,
	doc_action Varchar(1) Not Null,
	user_id_to BigInt Not Null,
        doc_stage_id_from Varchar(50),
        doc_stage_id Varchar(50) Not Null,
	last_updated Timestamp Not Null,
	Constraint pk_sys_doc_wf Primary Key
	( doc_id )
);

?==?
Create Table sys.doc_wf_history
(	doc_wf_history_id uuid NOT NULL, 
	doc_id Varchar(50) Not Null,
        doc_date date NOT NULL default '1970-01-01',
        branch_id BigInt,
        finyear varchar(4) not null default '',
	bo_id Varchar(250) Not Null,
	edit_view Varchar(250) Not Null,
	doc_name Varchar(250) Not Null,
	doc_sender_comment Varchar(500) Not Null,
	user_id_from BigInt Not Null,
	doc_sent_on Timestamp without time zone Not Null,
	doc_action Varchar(1) Not Null,
	user_id_to BigInt Not Null,
	doc_acted_on Timestamp without time zone Not Null,
        doc_stage_id_from Varchar(50),
        doc_stage_id Varchar(50) Not Null,
	last_updated Timestamp Not Null,
	Constraint sys_doc_wf_history_id Primary Key
	( doc_wf_history_id )
);

?==?
CREATE INDEX ix_sys_doc_wf_history on sys.doc_wf_history (doc_id); 

?==?
CREATE TABLE sys.address_type
(
  address_type_id bigint NOT NULL,
  address_type varchar(250) NOT NULL,
  address_type_desc varchar(500) NOT NULL,
  company_id bigint NOT NULL,
  last_updated timestamp NOT NULL,
  CONSTRAINT pk_address_type_id PRIMARY KEY (address_type_id)
);

?==?
Insert into sys.address_type(address_type_id, address_type, address_type_desc, company_id, last_updated)
Select ({company_id}*1000000) + 1, 'Office Address', 'Office Address', {company_id}, current_timestamp(0);

?==?
Insert into sys.address_type(address_type_id, address_type, address_type_desc, company_id, last_updated)
Select ({company_id}*1000000) + 2, 'Shipping Address', 'Shipping Address', {company_id}, current_timestamp(0);

?==?
Insert into sys.address_type(address_type_id, address_type, address_type_desc, company_id, last_updated)
Select ({company_id}*1000000) + 3, 'Billing Address', 'Billing Address', {company_id}, current_timestamp(0);

?==?
CREATE TABLE sys.address
(
  address_id bigint NOT NULL,
  company_id bigint NOT NULL DEFAULT (-1),
  address_type_id bigint NOT NULL DEFAULT (-1),
  address varchar(500) NOT NULL,
  city varchar(50) NOT NULL,
  country varchar(50) NOT NULL,
  pin varchar(8) NOT NULL,
  fax varchar(50) NOT NULL,
  mobile varchar(50) NOT NULL,
  phone varchar(50) NOT NULL,
  email varchar(50) NOT NULL,
  contact_person varchar(50) NOT NULL,
  state varchar(50) NOT NULL,
  gst_state_id BigInt Not Null,
  gstin varchar(15) not null,
  CONSTRAINT address_pkey PRIMARY KEY (address_id)
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'sys.address', 0;

?==?
CREATE TABLE sys.branch
(
  branch_id bigint NOT NULL,
  company_id bigint NOT NULL,
  branch_is_ho boolean NOT NULL,
  company_code varchar(2) NOT NULL,
  branch_code varchar(2) NOT NULL,
  branch_name varchar(100) NOT NULL,
  branch_description varchar(100) NOT NULL,
  branch_address varchar(250) NOT NULL,
  currency varchar(50) NOT NULL,
  sub_currency varchar(50) NOT NULL,
  currency_displayed varchar(50) NOT NULL,
  number_format varchar(20) NOT NULL,
  currency_system smallint NOT NULL,
  date_format varchar(50) NOT NULL,
  has_access_rights boolean NOT NULL,
  has_work_flow boolean NOT NULL,
  last_updated timestamp NOT NULL,
  company_group_id bigint NOT NULL,
  address_id bigint not null,
  zone_id bigint NOT NULL DEFAULT -1,
  gst_state_id BigInt Not Null,
  gstin varchar(15) not null,
  city varchar(50) not null default '',
  pin varchar(8) not null default '',
  annex_info jsonb not null default '{}',
  CONSTRAINT pk_sys_branch PRIMARY KEY (branch_id),
  CONSTRAINT uk_sys_branch UNIQUE (company_code, branch_code)
);

?==?
insert into sys.mast_seq
select 'sys.branch', 0;

?==?
CREATE TABLE sys.finyear
(
  finyear_id bigint NOT NULL,
  finyear_code varchar(4) NOT NULL,
  company_id bigint NOT NULL,
  year_begin date NOT NULL,
  year_end date NOT NULL,
  year_close boolean NOT NULL,
  last_updated timestamp Not Null default current_timestamp(0),
  CONSTRAINT pk_sys_finyear PRIMARY KEY (finyear_id),
  CONSTRAINT uk_sys_finyear_code UNIQUE (company_id, finyear_code)
);

?==?
INSERT INTO sys.mast_seq(mast_seq_type, seed) 
VALUES ('sys.finyear', 0);

?==?
Create Table sys.fiscal_month
(	fiscal_month_id BigInt Not Null,
	company_id BigInt Not Null, 
	finyear Varchar(4) Not Null, 
	fiscal_month_desc Varchar(100) Not Null, 
	month_begin Date Not Null, 
	month_end Date Not Null, 
	month_close boolean Not Null, 
	last_updated timestamp Not Null,
        annex_info jsonb not null default '{}',
	Constraint pk_sys_fiscal_month Primary Key
	(fiscal_month_id)
);

?==?
INSERT INTO sys.mast_seq(mast_seq_type, seed) 
VALUES ('sys.fiscal_month', 0);

?==?
CREATE TABLE sys.role
(
  role_id bigint NOT NULL,
  company_id bigint NOT NULL DEFAULT 0,
  role_name varchar(50) NOT NULL DEFAULT '',
  role_description varchar(250) NOT NULL,
  parent_role_id bigint Not Null Default(-1),
  last_updated timestamp NOT NULL,
  CONSTRAINT pk_sys_role PRIMARY KEY (role_id),
  CONSTRAINT uk_sys_role_role_name UNIQUE (role_name)
);

?==?
INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('sys.role', 0)

?==?
create table sys.settings
(
    key varchar(50) not null,
    value varchar(500) not null,
    constraint pk_settings primary key (key)
);

?==?
insert into sys.settings(key, value)
select 'ac_AcHeadCodeReqd', '0';

?==?

Insert into sys.settings (key, value)
Select 'ap_pymt_write_off_account', -1;

?==?
Insert into sys.settings (key, value)
Select 'ar_rcpt_write_off_account', -1;

?==?
Insert into sys.settings (key, value)
Select 'ar_rcpt_other_exp_account', -1;

?==?
Insert into sys.settings (key, value)
Select 'ar_rcpt_tds_account', -1;

?==?
Insert into sys.settings (key, value)
Select 'ar_CustomerCodeReqd', 1;

?==?
Insert into sys.settings (key, value)
Select 'ap_SupplierCodeReqd', 1;

?==?
Insert into sys.settings(key, value)
Select 'ac_exch_diff_account', -1;

?==?
Insert into sys.settings (key, value)
Select 'st_MaterialCodeReqd', 1;

?==?
Insert into sys.settings (key, value)
Select 'st_round_off_account', -1;

?==?
Insert into sys.settings (key, value)
Select 'tds_interest_penalty_account', -1;

?==?
Insert into sys.settings (key, value)
Select 'fa_round_off_account', -1;

?==?
Insert Into sys.settings
values('doc_build_sql', 'doc_id := pdoc_type || left(pfinyear, 2) || pbranch_code || ''/'' || pv_id;');

?==?
insert into sys.settings(key, value)
select 'ac_start_finyear', '';

?==?
Insert into sys.settings (key, value)
Select 'pub_round_off_account', -1;

?==?
CREATE TABLE sys.role_to_user
(
  role_id bigint NOT NULL,
  user_id bigint NOT NULL,
  role_to_user_id varchar(80) NOT NULL,
  CONSTRAINT pk_sys_role_to_user PRIMARY KEY (role_to_user_id),
  CONSTRAINT fk_sys_role_to_user_role FOREIGN KEY (role_id)
      REFERENCES sys.role (role_id)
);

?==?
CREATE TABLE sys.role_access_level_doc
(
	role_access_level_doc_id varchar(50) NOT NULL,
	role_id bigint NOT NULL,
	branch_id bigint NOT NULL,
	menu_id bigint NOT NULL,
	en_access_level smallint NOT NULL,
	allow_unpost boolean NOT NULL,
	allow_delete boolean NOT NULL,
        doc_stages character varying(50)[] NOT NULL DEFAULT '{}',
	CONSTRAINT pk_sys_role_access_level PRIMARY KEY (role_access_level_doc_id),
	CONSTRAINT uk_sys_role_access_level UNIQUE (role_id, branch_id, menu_id),
	CONSTRAINT fk_sys_role_access_level_role FOREIGN KEY (role_id)
		REFERENCES sys.role (role_id) MATCH SIMPLE
		ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
CREATE TABLE sys.role_access_level_master
(
	role_access_level_master_id varchar(50) NOT NULL,
	role_id bigint NOT NULL,
	menu_id bigint NOT NULL,
	en_access_level_master smallint NOT NULL,

	CONSTRAINT pk_sys_role_access_level_master PRIMARY KEY (role_access_level_master_id),
	CONSTRAINT uk_sys_role_access_level_master UNIQUE (role_id, menu_id),
	CONSTRAINT fk_sys_role_access_level_master_role FOREIGN KEY (role_id)
		REFERENCES sys.role (role_id) MATCH SIMPLE
		ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
CREATE TABLE sys.role_access_level_report
(
	role_access_level_report_id varchar(50) NOT NULL,
	role_id bigint NOT NULL,
	menu_id bigint NOT NULL,
	en_access_level_report smallint NOT NULL,
	branch_id bigint NOT NULL,

	CONSTRAINT pk_sys_role_access_level_report PRIMARY KEY (role_access_level_report_id),
	CONSTRAINT uk_sys_role_access_level_report UNIQUE (role_id, branch_id, menu_id),
	CONSTRAINT fk_sys_role_access_level_report_role FOREIGN KEY (role_id)
		REFERENCES sys.role (role_id) MATCH SIMPLE
		ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
CREATE TABLE sys.role_access_level_ui_form
(
	role_access_level_ui_form_id varchar(50) NOT NULL,
	role_id bigint NOT NULL,
	menu_id bigint NOT NULL,
	en_access_level_ui_form smallint NOT NULL,
	branch_id bigint NOT NULL,
	CONSTRAINT pk_sys_role_access_level_ui_form PRIMARY KEY (role_access_level_ui_form_id),
	CONSTRAINT uk_sys_role_access_level_ui_form UNIQUE (role_id, branch_id, menu_id),
	CONSTRAINT fk_sys_role_access_level_ui_form_role FOREIGN KEY (role_id)
		REFERENCES sys.role (role_id) MATCH SIMPLE
		ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
CREATE TABLE sys.role_access_level
(
  role_access_level_id character varying(50) NOT NULL,
  role_id bigint NOT NULL,
  menu_id bigint NOT NULL,
  en_access_level smallint NOT NULL,
  doc_stages character varying(50)[] NOT NULL DEFAULT '{}'::character varying[],
  last_updated timestamp without time zone NOT NULL DEFAULT now(),
  CONSTRAINT pk_sys_role_access_level2 PRIMARY KEY (role_access_level_id),
  CONSTRAINT fk_sys_role_access_level2_role FOREIGN KEY (role_id)
      REFERENCES sys.role (role_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
CREATE TABLE sys.user_branch_role
(
  user_branch_role_id character varying(50) NOT NULL,
  user_id bigint NOT NULL,
  branch_id bigint NOT NULL,
  role_id bigint NOT NULL,
  company_id bigint NOT NULL,
  last_updated timestamp without time zone NOT NULL,
  CONSTRAINT pk_sys_user_branch_role PRIMARY KEY (user_branch_role_id)
);

?==?
CREATE TABLE sys.tax_info_type
(
  tax_info_type_id bigint NOT NULL,
  company_id bigint NOT NULL,
  tax_info_type_desc character varying(250) NOT NULL,
  parameter character varying(50) NOT NULL,
  sl_no smallint not null,
  for_gst boolean not null default false,
  last_updated timestamp without time zone NOT NULL,
  CONSTRAINT tax_info_type_pkey PRIMARY KEY (tax_info_type_id),
  CONSTRAINT ix_tbltaxinfotype UNIQUE (tax_info_type_desc)
)

?==?
INSERT INTO sys.tax_info_type(tax_info_type_id, company_id, tax_info_type_desc, parameter, sl_no, last_updated, for_gst) 
VALUES (({company_id} * 1000000) + 1, {company_id}, 'VAT TIN.', 'VAT TIN.', 1, current_timestamp(0), false);

?==?
INSERT INTO sys.tax_info_type(tax_info_type_id, company_id, tax_info_type_desc, parameter, sl_no, last_updated, for_gst) 
VALUES (({company_id} * 1000000) + 2, {company_id}, 'C.S.T. No.', 'C.S.T. No.', 2, current_timestamp(0), false);

?==?
INSERT INTO sys.tax_info_type(tax_info_type_id, company_id, tax_info_type_desc, parameter, sl_no, last_updated, for_gst) 
VALUES (({company_id} * 1000000) + 3, {company_id}, 'Service Tax No.', 'Service Tax No.', 3, current_timestamp(0), false);

?==?
INSERT INTO sys.tax_info_type(tax_info_type_id, company_id, tax_info_type_desc, parameter, sl_no, last_updated, for_gst) 
VALUES (({company_id} * 1000000) + 4, {company_id}, 'PAN', 'PAN', 4, current_timestamp(0), true);

?==?
INSERT INTO sys.tax_info_type(tax_info_type_id, company_id, tax_info_type_desc, parameter, sl_no, last_updated, for_gst) 
VALUES (({company_id} * 1000000) + 5, {company_id}, 'TAN', 'TAN', 5, current_timestamp(0), true);

?==?
INSERT INTO sys.tax_info_type(tax_info_type_id, company_id, tax_info_type_desc, parameter, sl_no, last_updated, for_gst) 
VALUES (({company_id} * 1000000) + 6, {company_id}, 'CIN', 'CIN', 6, current_timestamp(0), true);

?==?
INSERT INTO sys.tax_info_type(tax_info_type_id, company_id, tax_info_type_desc, parameter, sl_no, last_updated, for_gst) 
VALUES (({company_id} * 1000000) + 7, {company_id}, 'GSTIN', 'GSTIN', 7, current_timestamp(0), true);

?==?
CREATE TABLE sys.branch_tax_info
(
	branch_tax_info_id varchar(50) NOT NULL,
	branch_id bigint NOT NULL,
	company_id bigint NOT NULL,
	tax_info_type_id bigint NOT NULL,
	branch_tax_info_desc varchar(250) NOT NULL,
        sl_no smallint not null,
	CONSTRAINT branch_tax_info_id_pkey PRIMARY KEY (branch_tax_info_id),
	CONSTRAINT fk_sys_branch_branch_tax_info FOREIGN KEY (branch_id)
	      REFERENCES sys.branch (branch_id) MATCH SIMPLE
	      ON UPDATE NO ACTION ON DELETE NO ACTION
)

?==?
CREATE TABLE sys.rpt_company_info
(
    key varchar(50) not null,
    value text not null,
    constraint pk_rpt_company_info primary key (key)
);

?==?
INSERT INTO sys.rpt_company_info(key, value)
SELECT 'inv_note', 'Bills not paid on or before due date will be charged interest @ 24% p.a.';

?==?
INSERT INTO sys.rpt_company_info(key, value)
SELECT 'inv_auth_text', 'Authorised signatory';

?==?
insert into sys.rpt_company_info
Select 'inv_decl', 'I/We hereby certify that my/our registration certificate under the Maharashtra Value. Added Tax Act, 2002 is in force on the date on which the sale of the goods specified in this tax invoice is made by me/us and that the transaction of sale covered by this tax invoice has been effected by me/us and it shall be accounted  for in the turnover of sales while filling of return and the due tax, if any, payable on the sale has been  paid or shall be paid.'


?==?
Create Table sys.dm_file
(	dm_file_id BigInt Not Null,
	company_id BigInt Not Null,
	file_name Varchar(256) Not Null,
	checksum Varchar(32) Not Null,
	file_path Varchar(256) Not Null,
	file_store Varchar(10) Not Null,
	last_updated timestamp Not Null,
	Constraint pk_dm_file Primary Key
	( dm_file_id )
);

?==?
INSERT INTO sys.mast_seq(mast_seq_type, seed) 
VALUES ('sys.dm_file', 0);

?==?
Create Table sys.dm_filelink
(	dm_filelink_id uuid Not Null,
	company_id BigInt Not Null,
	business_object Varchar(128) Not Null,
	ref_id varchar(50) Not Null,
	dm_file_id BigInt Not Null,
	last_updated timestamp Not Null,
	Constraint pk_dm_filelink Primary Key
	( dm_filelink_id ),
	Constraint fk_dm_file_filelink Foreign Key (dm_file_id)
	References sys.dm_file(dm_file_id) 
);

?==?
CREATE TABLE sys.doc_warning
(
  log_id bigint NOT NULL,
  document_type character varying(50) NOT NULL,
  voucher_id character varying(50) NOT NULL,
  en_log_action smallint NOT NULL,
  user_name character varying(50) NOT NULL,
  machine_name character varying(50) NOT NULL,
  json_log json NOT NULL,
  warning_desc character varying(3000),
  custom_action_desc character varying(250),
  last_updated timestamp without time zone NOT NULL DEFAULT now(),
  CONSTRAINT doc_warning_pkey PRIMARY KEY (log_id)
);

?==?
Insert into sys.mast_seq values('sys.doc_warning', 0);

?==?
CREATE TABLE sys.user_access_level
(
	user_access_level_id character varying(50) NOT NULL,
	user_id bigint NOT NULL,
	branch_id bigint NOT NULL,
	menu_id bigint NOT NULL,
	allow_delete boolean,
	allow_unpost boolean,
        allow_audit_trail boolean NOT NULL,
	CONSTRAINT pk_user_access_level PRIMARY KEY (user_access_level_id)
);

?==?
Create Table sys.rpt_option
(	rpt_option_id BigInt Not Null,
	rpt_path Varchar(250) Not Null,
	rpt_name Varchar(250) Not Null,
	rpt_replace_path Varchar(250) Not Null Default(''),
	rpt_header_template Varchar(250) Not Null Default(''),
	rpt_header_image Varchar(250) Not Null Default(''),
	rpt_footer_template Varchar(250) Not Null Default(''),
	last_updated timestamp Not Null Default current_timestamp(0),
	Constraint pk_sys_rpt_option Primary Key
	(rpt_option_id),
	Constraint uk_sys_rpt_option Unique 
	(rpt_path, rpt_name)
);

?==?
Insert into sys.mast_seq values('sys.rpt_option', 0);

?==?
Create Table sys.rpt_user_pref
(	rpt_user_pref_id uuid Not Null, 
 	rpt_id Text Not Null, 
 	user_id BigInt Not Null, 
 	jdata JsonB Not Null, 
 	last_updated TimeStamp Not Null,
 	Constraint pk_sys_rpt_user_pref Primary Key (rpt_user_pref_id)
);

?==?
CREATE TABLE sys.import_log
(
	import_log_id bigint NOT NULL,
	company_id bigint NOT NULL,
	user_id bigint NOT NULL,
	business_object character varying(128) NOT NULL,
	records_total bigint NOT NULL DEFAULT(0),
	records_saved bigint NOT NULL DEFAULT(0),
	records_invalid bigint NOT NULL DEFAULT(0),
	record_import_log character varying(500) NOT NULL,
	last_updated timestamp without time zone NOT NULL,
	constraint pk_sys_import_log PRIMARY KEY(import_log_id)
);

?==?
Create Table sys.entity_extn
(
	entity_extn_id bigint not null,
	bo_id uuid not null,
	company_id bigint not null,
	extn_info xml not null,
	last_updated timestamp without time zone not null,
	constraint pk_entity_extn primary key (entity_extn_id),
	constraint uk_entity_extn unique (bo_id)
);

?==?
INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('sys.entity_extn', 0);

?==?
CREATE TABLE sys.user_to_branch
(
  user_to_branch_id varchar(50) NOT NULL,
  user_id bigint NOT NULL,
  branch_id bigint NOT NULL,
  company_id bigint NOT NULL,
  last_updated timestamp without time zone NOT NULL,
  CONSTRAINT pk_user_branch_association PRIMARY KEY (user_to_branch_id)
);

?==?
CREATE TABLE sys.doc_view_log
(
  doc_view_log_id bigserial NOT NULL,
  bo_id character varying(50) NOT NULL,
  doc_id character varying(50) NOT NULL,
  user_id bigint NOT NULL,
  last_updated timestamp without time zone NOT NULL DEFAULT now(),
  CONSTRAINT pk_sys_doc_view_log PRIMARY KEY (doc_view_log_id)
);

?==?
CREATE TABLE sys.doc_print_log
(
  doc_print_log_id bigserial NOT NULL,
  bo_id character varying(50) NOT NULL,
  doc_id character varying(50) NOT NULL,
  user_id bigint NOT NULL,
  doc_status bigint NOT NULL,
  last_updated timestamp without time zone NOT NULL DEFAULT now(),
  CONSTRAINT pk_sys_doc_print_log PRIMARY KEY (doc_print_log_id)
);

?==?
CREATE TABLE sys.doc_print_control
(
  doc_print_control_id bigint NOT NULL,
  bo_id character varying(50) NOT NULL,
  print_allow_post bigint NOT NULL DEFAULT 0,
  print_allow_unpost bigint NOT NULL DEFAULT 0,
  export_allow boolean NOT NULL DEFAULT FALSE,
  report_mail_allow boolean NOT NULL DEFAULT FALSE,
  last_updated timestamp without time zone NOT NULL DEFAULT now(),
  export_types varchar[] Not Null Default '{}',
  CONSTRAINT pk_sys_doc_print_control PRIMARY KEY (doc_print_control_id)
);

?==?
Create Table sys.doc_print_request
(
	doc_print_request_id bigint not null,
	doc_id varchar(50) not null,
	requested_by_user_id bigint not null,
	requested_on timestamp without time zone not null default now(),
	allowed_by_user_id bigint,
	printed_on timestamp without time zone,
	closed boolean not null default false,
	last_updated timestamp without time zone not null DEFAULT now(),
	CONSTRAINT pk_sys_doc_print_request PRIMARY KEY (doc_print_request_id)	
);

?==?
insert into sys.settings values('print_allow_unpost',-1);

?==?
insert into sys.settings values('print_allow_post',-1);

?==?
insert into sys.settings values('export_allow', 'true');

?==?
insert into sys.settings values('audit_trail_allow', 'true');

?==?
Create Table sys.widget
(
	widget_id bigint not null,
	widget_name varchar(50) not null,
	widget_path varchar(100) not null,
	widget_type varchar(10) not null,
        widget_size varchar(10) not null default('default'),
	last_updated timestamp without time zone not null default now(),
	constraint pk_sys_widget primary key (widget_id)
);

?==?
Create Table sys.widget_request
(
	widget_request_id bigint not null,
	widget_id bigint not null,
	requested_by_user_id bigint not null,
	request_date timestamp without time zone not null default now(),
	handler_user_id bigint,
	handled_on timestamp without time zone,
	subscribe boolean not null default false,
	request_closed boolean not null default false,
	last_updated timestamp without time zone not null default now(),
	constraint pk_sys_widget_request primary key (widget_request_id)
);

?==?
Create Table sys.user_widget_access
(
	user_widget_access_id bigint not null,
	user_id bigint not null,
	widget_id bigint not null,
	last_updated timestamp without time zone not null default now(),
	constraint pk_sys_user_widget_access primary key (user_widget_access_id)
);

?==?
Create Table sys.user_dashboard
(
	user_dashboard_id bigint not null,
	user_id bigint not null,
	dashboard_xml xml not null,
	last_updated timestamp without time zone not null default now(),
	constraint pk_sys_user_dashbaord primary key (user_dashboard_id)
);

?==?
insert into sys.settings 
values('report_mail_allow', 'false');

?==?
insert into sys.settings
select 'pub_tax_before_disc', '0'

?==?
CREATE TABLE sys.user_action_log
(
  log_id serial NOT NULL,
  utility_name varchar(50) NOT NULL,
  user_id bigint NOT NULL,
  machine_name varchar(50) NOT NULL,
  json_log jsonb NOT NULL,
  last_updated timestamp without time zone NOT NULL DEFAULT now(),
  CONSTRAINT pk_sys_user_action_log PRIMARY KEY (log_id)
)

?==?
Create Table sys.wiz_cache
(   wiz_cache_id uuid Not Null Primary Key, 
    wiz_data text Not Null, 
    last_updated timestamp Not Null
);

?==?
insert into sys.settings
values ('confirm_post', '0');

?==?
Insert into sys.settings (key, value)
Select 'pub_comm_hsn_sc_id', -1;

?==?
Insert into sys.settings (key, value)
Select 'pub_rop_hsn_sc_id', -1;

?==?
Insert into sys.settings (key, value)
Select 'pub_ror_hsn_sc_id', -1;

?==?
Insert into sys.settings (key, value)
Select 'pub_rot_hsn_sc_id', -1;

?==?
Insert into sys.settings (key, value)
Select 'pub_row_hsn_sc_id', -1;

?==?
Insert into sys.settings (key, value)
Select 'tx_sez_without_gst', 0;

?==?
Insert into sys.settings (key, value)
Select 'pub_disc_on_ro_amt', '0';

?==?
insert into sys.settings(key, value)
Select 'crm_blocking', 0;

?==?
insert into sys.settings(key, value)
Select 'crm_val_overdue_inv', 0;

?==?
insert into sys.settings(key, value)
Select 'crm_grace_period', 0

?==?
create table sys.business_unit
(
	business_unit_id bigint not null,
	business_unit character varying not null,
	company_id bigint not null,
	last_updated timestamp not null default current_timestamp(0),
	constraint pk_sys_business_unit primary key (business_unit_id)
);

?==?
INSERT INTO sys.mast_seq (mast_seq_type, seed)
SELECT 'sys.business_unit', 0;

?==?
Insert into sys.business_unit(business_unit_id, business_unit, company_id, last_updated)
Values(0, 'Originating Branch', {company_id}, current_timestamp(0));

?==?
insert into sys.settings(key, value)
Select 'pub_auto_round_off', 0;

?==?
insert into sys.settings
select 'st_walk_in_cust_id', '-1'

?==?
CREATE TABLE sys.report_preset
(
  report_preset_id bigint NOT NULL,
  report_id character varying(50) NOT NULL,
  mail_body text DEFAULT ''::text,
  last_updated timestamp without time zone NOT NULL DEFAULT now(),
  CONSTRAINT pk_sys_report_preset PRIMARY KEY (report_preset_id)
);

?==?
CREATE TABLE sys.user_to_ledger
(
  user_to_ledger_id varchar(50) NOT NULL,
  user_id bigint NOT NULL,
  account_id bigint NOT NULL,
  company_id bigint NOT NULL,
  last_updated timestamp without time zone NOT NULL,
  CONSTRAINT pk_user_to_ledger PRIMARY KEY (user_to_ledger_id)
);

?==?
CREATE TABLE sys.menu_mob
(
  menu_mob_id bigint NOT NULL,
  parent_menu_mob_id BigInt NOT NULL,
  menu_key Varchar(10),
  menu_name varchar(100) NOT NULL,
  menu_text varchar(250) NOT NULL,
  menu_type smallint NOT NULL,
  bo_id uuid NULL,
  is_hidden boolean NOT NULL,
  last_updated timestamp without time zone NOT NULL,
  link_path varchar(250),
  menu_code varchar(4) not null default '',
  is_staged boolean not null default(false),
  CONSTRAINT pk_sys_menu_mob PRIMARY KEY (menu_mob_id),
  CONSTRAINT uk_sys_menu_mob UNIQUE (menu_name)
);

?==?
insert into sys.settings(key, value)
Values('ac_payc_limit', 0);

?==?
insert into sys.settings
values('sys_gmf_user_id', '-1')

?==?
create table sys.wf_ar
(
    wf_ar_id serial not null,
    bo_id varchar(50) not null,
    branch_id bigint not null,
    doc_id varchar(50) not null,
    doc_date date not null,
    wf_desc varchar(500) not null default '',
    user_from bigint not null,
    user_to bigint not null,
    route varchar(150) not null,
    formname varchar(150) not null,
    formparams varchar(150) not null,
    wf_comment varchar(500) not null default '',
    apr_type character varying(10) not null,
    wf_approved smallint not null default 0,
    added_on timestamp without time zone not null DEFAULT current_timestamp(0),
    acted_on timestamp without time zone,
    last_updated timestamp without time zone not null DEFAULT current_timestamp(0),
    constraint pk_sys_wf_ar primary key (wf_ar_id)
);

?==?
insert into sys.settings
values('logon_branch_search','false');

?==?
create table sys.user_to_clfinyear
(
  user_to_clfinyear_id varchar(50),
  finyear_id bigint not null,
  user_id bigint not null,
  last_updated timestamp without time zone not null DEFAULT now(),
    CONSTRAINT pk_user_to_clfinyear PRIMARY KEY (user_to_clfinyear_id)
);

?==?
create table sys.apr_matrix
(
	apr_matrix_id serial not null,
    matrix_type varchar(10) not null default '',
    annex_info jsonb not null default '{}'
);

?==?
Insert into sys.apr_matrix(matrix_type, annex_info)
Values('IO', '{"min":"0","max":"10","user_to":"-1"}'),
        ('IO', '{"min":"10","max":"30","user_to":"-1"}'),
        ('CL', '{"min":"0","max":"10","user_to":"-1"}'),
        ('CL', '{"min":"10","max":"25","user_to":"-1"}');

?==?
create table sys.data_set
(
	ds_id serial not null,
	ds_name varchar(100) not null,
	ds_desc varchar(500) not null,
	ds_path varchar(500) not null,
	last_updated timestamp without time zone NOT NULL,
	CONSTRAINT pk_st_data_set PRIMARY KEY (ds_id),
	CONSTRAINT uk_st_data_set UNIQUE (ds_name)
);

?==?
CREATE TABLE sys.role_access_level_dataset
(
	role_access_level_dataset_id varchar(50) NOT NULL,
	role_id bigint NOT NULL,
	menu_id bigint NOT NULL,
	en_access_level_dataset smallint NOT NULL,
	branch_id bigint NOT NULL,

	CONSTRAINT pk_sys_role_access_level_dataset PRIMARY KEY (role_access_level_dataset_id),
	CONSTRAINT uk_sys_role_access_level_dataset UNIQUE (role_id, branch_id, menu_id),
	CONSTRAINT fk_sys_role_access_level_dataset_role FOREIGN KEY (role_id)
		REFERENCES sys.role (role_id) MATCH SIMPLE
		ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
CREATE TABLE sys.doc_group
(
  doc_group_id bigint NOT NULL,
  doc_group varchar(50) NOT NULL,
  company_id bigint NOT NULL,
  last_updated timestamp Not Null default current_timestamp(0),
  CONSTRAINT pk_sys_doc_group PRIMARY KEY (doc_group_id)
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'sys.doc_group', 0;

?==?
create table sys.doc_group_tran
(
  doc_group_tran_id varchar(50),
  doc_group_id bigint not null,
  bo_id uuid NOT NULL,
  last_updated timestamp without time zone not null DEFAULT now(),
  CONSTRAINT pk_doc_group_tran PRIMARY KEY (doc_group_tran_id),
  CONSTRAINT uk_doc_group_tran UNIQUE (bo_id)
);

?==?
create table sys.user_pref
(
	user_pref_id bigint not null,
	company_id bigint not null,
	user_id bigint not null,
	pref_info jsonb not null default '{}',
	last_updated timestamp without time zone NOT NULL DEFAULT current_timestamp(0),
	Constraint pk_sys_user_pref Primary Key	(user_pref_id),
	Constraint uk_sys_user_pref Unique (user_id)
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'sys.user_pref', 0;

?==?
create foreign table sys.user_to_company
(
  user_to_company_id varchar(50),
  user_id bigint not null,
  company_id bigint not null,
  last_updated timestamp not null
)
server {dbMain} options(table_name 'user_to_company' );

?==?

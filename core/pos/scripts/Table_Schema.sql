create schema pos;

?==?
Insert Into sys.settings
select 'bb_purchase_account', -1;

?==?
Insert Into sys.settings
select 'bb_tax_account', -1;

?==?
Insert Into sys.settings
select 'bb_adj_account', -1;

?==?
Create Table pos.doc_seq
(	doc_seq_id Serial Not Null,
	terminal_id Bigint Not Null, 
	doc_type Varchar(4) Not Null, 
	finyear Varchar(4) Not Null, 
	max_voucher_no BigInt Not Null, 
	lock_bit Boolean Not Null
);

?==?
Create Table pos.terminal
(	terminal_id BigInt Not Null,
	company_id BigInt NOT NULL,
        terminal_code Varchar(2) Not Null,
	terminal Varchar(50) Not Null,
	terminal_loc Varchar(250) Not Null,
	is_remote Boolean Not Null,
        branch_id BigInt Not Null,
        stock_location_id BigInt Not Null,
        sale_account_id BigInt Not Null,
        cash_account_id BigInt Not Null,
        cheque_account_id BigInt Not Null,
        cc_mac_id BigInt Not Null,
        annex_info JsonB Not Null Default('{}'),
	last_updated Timestamp Not Null,
	Constraint pk_pos_terminal Primary Key (terminal_id)
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'pos.terminal', 0;

?==?
Create Table pos.cc_mac
(	cc_mac_id BigInt Not Null,
	cc_mac_code Varchar(20) Not Null,
	company_id BigInt Not Null,
	account_id BigInt Not Null,
	is_discontinued Boolean Not Null,
	last_updated Timestamp Not Null,
	Constraint pk_pos_cc_mac Primary Key (cc_mac_id)
);

?==?
Insert Into sys.mast_seq(mast_seq_type, seed)
Select 'pos.cc_mac', 0;

?==?
Create Table pos.tday
(	tday_id BigInt Not Null,
	tday_session_id uuid Not Null,
	company_id BigInt Not Null,
	branch_id BigInt Not Null,
	terminal_id BigInt Not Null,
	remote_server_id BigInt Not Null,
        finYear Varchar(4) Not Null,
	tday_date Date Not Null,	
	tday_status Int Not Null,
	user_id BigInt Not Null,
	start_time Timestamp Not Null,
	end_time Timestamp Null,
	last_updated Timestamp Not Null,
	Constraint pk_pos_tday Primary Key (tday_id),
	Constraint fk_pos_terminal_tday Foreign Key (terminal_id)
	References pos.terminal (terminal_id),
	Constraint uk_pos_tday_session Unique (tday_session_id)
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'pos.tday', 0;

?==?
Create Table pos.user_setting
(	user_setting_id BigInt Not Null,
	company_id BigInt Not Null,
	user_id BigInt Not Null,
	jdata JsonB Not Null,
	last_updated Timestamp Not Null
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'pos.user_setting', 0;

?==?
CREATE TABLE pos.inv_control
(	inv_id Varchar(50) Not Null,
	company_id BigInt Not Null,
	finyear Varchar(4) Not Null,
	branch_id BigInt Not Null,
	terminal_id BigInt Not Null,
	tday_session_id uuid Not Null,
	doc_type Varchar(20) Not Null,
	doc_date Date Not Null,
	item_amt_tot Numeric(18,4) Not Null,
	tax_amt_tot Numeric(18,4) Not Null,
	nt_amt Numeric(18,4) Not Null,
	rof_amt Numeric(18,4) Not Null,
	inv_amt Numeric(18,4) Not Null,
	status smallint Not Null,
	narration Varchar(500) Not Null,
	amt_in_words Varchar(250) Not Null,
	cust_tin Varchar(20) Not Null,
	cust_name Varchar(50) Not Null,
	cust_address Varchar(500) Not Null,
        cust_mob character varying(20) NOT Null,
        cust_tel character varying(20) NOT NULL,
	sale_account_id BigInt Not Null,
	salesman_id BigInt Not Null,
        vat_type_id BigInt Not Null,
        doc_stage_id Varchar(50) Not Null,
        doc_stage_status smallint Not Null,
	last_updated timestamp without time zone Not Null,
	merge_status BigInt Not Null,  --0:ForMerge, 1:MergeRerun, 2:MergeError, 3:OK
	merge_ref Varchar(50) Not Null,
	merge_updated Timestamp Not Null,
	merge_msg JsonB Not Null,
        annex_info JsonB Not Null Default('{}'),
	CONSTRAINT pk_pos_inv_control Primary Key (inv_id)
);

?==?
Create Table pos.inv_tran
(	inv_tran_id Varchar(50) Not Null,
	inv_id Varchar(50) Not Null,
	sl_no BigInt Not Null,
        mfg_id BigInt NOT NULL,
        bar_code Varchar(20) NOT NULL,
        material_type_id BigInt NOT NULL,
	material_id BigInt Not Null,
	stock_location_id BigInt Not Null,
	uom_id BigInt Not Null,
	issued_qty Numeric(18,4) Not Null,
	received_qty Numeric(18,4) Not Null,
	rate Numeric(18,4) Not Null,
	disc_is_value boolean Not Null,
	disc_pcnt Numeric(5,2) Not Null,
	disc_amt Numeric(18,4) Not Null,
	bt_amt Numeric(18,4) Not Null,
	tax_schedule_id BigInt Not Null,
        en_tax_type smallint Not Null,
	tax_pcnt Numeric(18,4) Not Null,
	tax_amt Numeric(18,4) Not Null,
	item_amt Numeric(18,4) Not Null,
        ref_tran_id Varchar(50) Not Null,
	Constraint pk_pos_inv_tran Primary Key (inv_tran_id),
	Constraint fk_pos_inv_control_tran Foreign Key (inv_id)
	References pos.inv_control (inv_id)
);

?==?
Create Table pos.inv_settle
(	inv_settle_id Varchar(50) Not Null,
	inv_id Varchar(50) Not Null,
	is_cash Boolean Not Null,
	cash_account_id BigInt Not Null,
	cash_amt Numeric(18,4) Not Null,
        is_cheque boolean NOT NULL,
        cheque_account_id BigInt Not Null,
        cheque_no varchar(20) Not Null,
        cheque_amt numeric(18,4) NOT NULL,
	is_card Boolean Not Null,
	cc_mac_id BigInt Not Null,
	card_ref_no Varchar(50) Not Null,
	card_no Varchar(50) Not Null,
	card_amt Numeric(18,4) Not Null,
	is_customer Boolean Not Null,
	customer_id BigInt Not Null,
	customer_amt Numeric(18,4) Not Null,
	Constraint pk_pos_inv_settle Primary Key (inv_settle_id),
	Constraint fk_pos_inv_control_settle Foreign Key (inv_id)
	References pos.inv_control (inv_id)	
);

?==?
Create Table pos.inv_bb
(	inv_bb_id Varchar(50) Not Null,
	inv_id Varchar(50) Not Null,
	sl_no BigInt Not Null,
        material_type_id BigInt NOT NULL,
        bar_code Varchar(20) NOT NULL,
	material_id BigInt Not Null,
	stock_location_id BigInt Not Null,
	uom_id BigInt Not Null,
	received_qty Numeric(18,4) Not Null,
	rate Numeric(18,4) Not Null,
	bt_amt Numeric(18,4) Not Null,
	tax_schedule_id BigInt Not Null,
        en_tax_type smallint Not Null,
	tax_pcnt Numeric(18,4) Not Null,
	tax_amt Numeric(18,4) Not Null,
	item_amt Numeric(18,4) Not Null,
	Constraint pk_pos_inv_bb Primary Key (inv_bb_id),
	Constraint fk_pos_inv_control_bb Foreign Key (inv_id)
	References pos.inv_control (inv_id)
);

?==?
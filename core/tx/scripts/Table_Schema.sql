create schema tx authorization postgres;

?==?
CREATE TABLE tx.tax_type
(
	tax_type_id bigint NOT NULL,
	tax_type varchar(50) NOT NULL,
	company_id bigint NOT NULL,
        in_use boolean Not Null,
        last_updated timestamp not null,
	CONSTRAINT pk_tx_tax_type PRIMARY KEY (tax_type_id)	
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'tx.tax_type', 0;

?==?
Insert into tx.tax_type
Select 1, 'Service Tax', {company_id}, true, current_timestamp(0);

?==?
Insert into tx.tax_type
Select 2, 'VAT', {company_id}, true, current_timestamp(0);

?==?
Insert into tx.tax_type
Select 3, 'GST', {company_id}, true, current_timestamp(0);

?==?
Insert into sys.mast_seq_tran(company_id, mast_seq_type, max_id, lock_bit)
Select {company_id}, 'tx.tax_type', 2, false;

?==?
CREATE TABLE tx.tax_schedule
(
	tax_schedule_id bigint NOT NULL,
	company_id bigint NOT NULL,
	description varchar(120) NOT NULL,
	tax_schedule_code varchar(20) NOT NULL,
	is_discontinued boolean NOT NULL,
	tax_type_id bigint NOT NULL,
	applicable_to_customer boolean NOT NULL,
	applicable_to_supplier boolean NOT NULL,
	last_updated timestamp without time zone NOT NULL,
	CONSTRAINT pk_tx_tax_schedule PRIMARY KEY (tax_schedule_id),
	CONSTRAINT fk_tx_tax_schedule_tax_type FOREIGN KEY (tax_type_id)
	      REFERENCES tx.tax_type (tax_type_id) MATCH SIMPLE
	      ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'tx.tax_schedule', 0;

?==?
CREATE TABLE tx.tax_detail
(
	tax_detail_id bigint NOT NULL,
	tax_schedule_id bigint NOT NULL,
	step_id bigint NOT NULL,
	parent_tax_details varchar(500) NOT NULL,	
	description varchar(120) NOT NULL,
	account_id bigint NOT NULL,
	en_tax_type smallint NOT NULL,	
	en_round_type smallint NOT NULL,
	tax_perc numeric(18,4) NOT NULL,
	tax_on_perc numeric(18,4) NOT NULL,
	tax_on_min_amt numeric(18,4) NOT NULL,
	tax_on_max_amt numeric(18,4) NOT NULL,
	min_tax_amt numeric(18,4) NOT NULL,
	max_tax_amt numeric(18,4) NOT NULL,
	CONSTRAINT pk_tx_tax_detail PRIMARY KEY (tax_detail_id),
	CONSTRAINT fk_tx_tax_detail_tax_schedule FOREIGN KEY (tax_schedule_id)
	      REFERENCES tx.tax_schedule (tax_schedule_id) MATCH SIMPLE
	      ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'tx.tax_detail', 0;

?==?
CREATE TABLE tx.tax_tran
(
	voucher_id varchar(50) NOT NULL,
        vch_tran_id varchar(50) not null,
	tax_tran_id varchar(50) NOT NULL,
	tax_schedule_id bigint NOT NULL,
	tax_detail_id bigint NOT NULL,
	description varchar(250) NOT NULL,
	step_id smallint NOT NULL,
	account_id bigint NOT NULL,
	tax_amt_fc numeric(18,4) NOT NULL,
	tax_amt numeric(18,4) NOT NULL,
	custom_rate numeric(18,4) NOT NULL,
	include_in_lc boolean NOT NULL,
	account_affected_id bigint NOT NULL,
        supplier_paid boolean not null,
	CONSTRAINT pk_tax_tran PRIMARY KEY (tax_tran_id)
);

?==?
Create Table tx.vat_type
(	vat_type_id BigInt Not Null,
	vat_type_code Varchar(5) Not Null,
	vat_type_desc Varchar(100) Not Null,
        short_desc varchar(20) not null default '',
	apply_item_tax Boolean Not Null,
	apply_tax_schedule_id BigInt Not Null,
	for_purchase Boolean Not Null,
	for_sale Boolean Not Null,
	last_updated Timestamp Not Null,
	Constraint pk_tx_vat_type Primary Key (vat_type_id)
);

?==?
-- VAT Sale Types
Insert Into tx.vat_type(vat_type_id, vat_type_code, vat_type_desc, apply_item_tax, apply_tax_schedule_id, 
	for_purchase, for_sale, last_updated)
Values(101,  'LS', 'Local Sales', true, -1, 
	false, true, current_timestamp(0)),
    (102, 'C', 'Interstate With Form ''C''', true, -1, 
	false, true, current_timestamp(0)),
    (103, 'WC', 'Interstate Without Form ''C''', true, -1, 
	false, true, current_timestamp(0)),
    (104, 'E1', 'Interstate ''E1''', true, -1, 
	false, true, current_timestamp(0))

?==?
-- VAT Purchase Types
Insert Into tx.vat_type(vat_type_id, vat_type_code, vat_type_desc, apply_item_tax, apply_tax_schedule_id, 
	for_purchase, for_sale, last_updated)
Values(201,  'LP', 'Local Purchase', true, -1, 
	true, false, current_timestamp(0)),
    (202, 'C', 'Interstate Purchase With Form ''C''', true, -1, 
	true, false, current_timestamp(0)),
    (203, 'WC', 'Interstate Purchase Without Form ''C''', true, -1, 
	true, false, current_timestamp(0)),
    (204, 'E1', 'Interstate Purchase ''E1''', true, -1, 
	true, false, current_timestamp(0)),
    (205, 'UR', 'URD Purchase', true, -1, 
	true, false, current_timestamp(0))

?==?
-- GST Sale Types
Insert Into tx.vat_type(vat_type_id, vat_type_code, vat_type_desc, apply_item_tax, apply_tax_schedule_id, 
	for_purchase, for_sale, last_updated, short_desc)
Values(301, 'LS', 'Local Sale - SGST/CGST', true, -1, 
	false, true, current_timestamp(0), 'Local/Intra'),
    (302, 'IS', 'Interstate Sale - IGST', true, -1, 
	false, true, current_timestamp(0), 'Interstate'),
    (303, 'DE', 'Deemed Exports', true, -1, 
	false, true, current_timestamp(0), 'Deem Exp'),
    (304, 'SEWP', 'SEZ Exports with payment', true, -1, 
	false, true, current_timestamp(0), 'SEZ WP'),
    (305, 'SEWOP', 'SEZ Exports without payment', true, -1, 
	false, true, current_timestamp(0), 'SEZ WOP'),
    (306, 'EXWP', 'Exports with payment', true, -1,
        false, true, current_timestamp(0), 'Exp WP'),
    (307, 'EXWOP', 'Exports without payment', true, -1,
        false, true, current_timestamp(0), 'Exp WOP');

?==?
-- GST Purchase Types
Insert Into tx.vat_type(vat_type_id, vat_type_code, vat_type_desc, apply_item_tax, apply_tax_schedule_id, 
	for_purchase, for_sale, last_updated, short_desc)
Values(401, 'LP', 'Local Purchase - SGST/CGST', true, -1, 
	true, false, current_timestamp(0), 'Local/Intra'),
    (402, 'IP', 'Interstate Purchase - IGST', true, -1, 
	true, false, current_timestamp(0), 'Interstate'),
    (403, 'PI', 'Purchase Import', true, -1, 
	true, false, current_timestamp(0), 'Import'),
    (404, 'CP', 'Composition Purchase', true, -1, 
	true, false, current_timestamp(0), 'Composition'),
    (405, 'SZ', 'SEZ Purchase', true, -1, 
	true, false, current_timestamp(0), 'SEZ');

?==?
Create Foreign Table tx.hsn_sc
(   hsn_sc_id BigInt Not Null,
    hsn_sc_ch Varchar(2) Not Null,
    hsn_sc_code Varchar(8) Not Null,
    hsn_sc_desc Varchar(250) Not Null,
    hsn_sc_type Varchar(1) Not Null,
    last_updated Timestamp Not Null
)
server {dbMain} options(schema_name 'sys', table_name 'hsn_sc' );

?==?
Create Foreign Table tx.hsn_sc_uom
(   hsn_sc_uom_id BigInt Not Null,
    uom_code Varchar(3) Not Null,
    uom_desc Varchar(50) Not Null,
    last_updated Timestamp Not Null
)
server {dbMain} options(schema_name 'sys', table_name 'hsn_sc_uom' );

?==?
Create Table tx.gst_rate
(   gst_rate_id BigInt Not Null,
    company_id BigInt Not Null,
    gst_rate_desc Varchar(50) Not Null,
    sgst_pcnt Numeric(5,2) Not Null,
    sgst_itc_account_id BigInt Not Null,
    sgst_account_id BigInt Not Null,
    sgst_rc_account_id BigInt Not Null,
    sgst_tds_account_id bigint not null default -1,
    cgst_pcnt Numeric(5,2) Not Null,
    cgst_itc_account_id BigInt Not Null,
    cgst_account_id BigInt Not Null,
    cgst_rc_account_id BigInt Not Null,
    cgst_tds_account_id bigint not null default -1,
    igst_pcnt Numeric(5,2) Not Null,
    igst_itc_account_id BigInt Not Null,
    igst_account_id BigInt Not Null,
    igst_rc_account_id BigInt Not Null,
    igst_tds_account_id bigint not null default -1,
    cess_pcnt Numeric(5,2) Not Null,
    cess_itc_account_id BigInt Not Null,
    cess_account_id BigInt Not Null,
    cess_rc_account_id BigInt Not Null,
    cess_tds_account_id bigint not null default -1,
    last_updated Timestamp Not Null,
    Constraint pk_tx_gst_rate Primary Key
    ( gst_rate_id )
 );

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'tx.gst_rate', 0;

?==?
Create Table tx.hsn_sc_rate
(   hsn_sc_rate_id uuid Not Null,
    hsn_sc_id BigInt Not Null,
    company_id BigInt Not Null,
    gst_rate_id BigInt Not Null,
    hsn_sc_uom_id BigInt Not Null default(26), -- Default is set to Nos (26)
    is_exempt Boolean Not Null default(false),
    last_updated Timestamp Not Null,
    Constraint pk_tx_hsn_sc_rate Primary Key (hsn_sc_rate_id),
    Constraint fk_tx_hsn_sc_rate_gst_rate Foreign Key (gst_rate_id)
    References tx.gst_rate (gst_rate_id)
);

?==?
Create Table tx.gst_tax_tran
(   gst_tax_tran_id Varchar(50) Not Null,
    voucher_id Varchar(50) Not Null,
    tran_group Varchar(50) Not Null,
    hsn_sc_code Varchar(8) Not Null,
    hsn_sc_type Varchar(1) Not Null,
    supplier_gstin Varchar(15) Not Null Default(''),
    hsn_qty Numeric(18,3) Not Null Default(1),
    gst_rate_id BigInt Not Null,
    is_rc Boolean Not Null,
    rc_sec_id BigInt Not Null,
    apply_itc Boolean Not Null,
    is_ctp boolean not null,
    bt_amt Numeric(18,4) Not Null,
    tax_amt_ov Boolean Not Null,
    sgst_pcnt Numeric(5,2) Not Null,
    sgst_amt Numeric(18,2) Not Null,
    sgst_itc_account_id BigInt Not Null,
    sgst_account_id BigInt Not Null,
    cgst_pcnt Numeric(5,2) Not Null,
    cgst_amt Numeric(18,2) Not Null,
    cgst_itc_account_id BigInt Not Null,
    cgst_account_id BigInt Not Null,
    igst_pcnt Numeric(5,2) Not Null,
    igst_amt Numeric(18,2) Not Null,
    igst_itc_account_id BigInt Not Null,
    igst_account_id BigInt Not Null,
    cess_pcnt Numeric(5,2) Not Null,
    cess_amt Numeric(18,2) Not Null,
    cess_itc_account_id BigInt Not Null,
    cess_account_id BigInt Not Null,
    Constraint pk_tx_gst_tax_tran Primary Key (gst_tax_tran_id),
    Constraint fk_tx_gst_tax_tran_gst_rate Foreign Key (gst_rate_id) 
    References tx.gst_rate (gst_rate_id)
);

?==?
Create Index ix_tx_gst_tax_tran On tx.gst_tax_tran (voucher_id, tran_group);

?==?
Create Table tx.gst_state
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
Insert Into tx.gst_state(gst_state_id, state_name, state_code, gst_state_code, active, last_updated)
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
Create table tx.rc_sec
(	rc_sec_id BigInt Not Null,
	rc_sec_desc Varchar(100) Not Null,
 	last_updated Timestamp Not Null,
 	Constraint pk_rc_sec_id Primary Key (rc_sec_id)
);

?==?
Insert Into tx.rc_sec
Values	(93, '9(3) - Specified Supplies (CGST/SGST)', current_timestamp(0)),
        (94, '9(4) - Taxable Supplies (CGST/SGST)', current_timestamp(0)),
        (53, '5(3) - Specified Supplies (IGST)', current_timestamp(0)),
        (54, '5(4) - Taxable Supplies (IGST)', current_timestamp(0));

?==?
Create Table tx.gst_ret_type 
(   gst_ret_type_id BigInt Not Null,
    ret_type Varchar(250) Not Null,
    last_updated Timestamp Not Null,
    Constraint pk_tx_gst_ret_type Primary Key (gst_ret_type_id)
);

?==?
Insert Into tx.gst_ret_type (gst_ret_type_id, ret_type, last_updated)
Values 
    (101, 'GSTR1 - Return for Outward Supplies', current_timestamp(0)),
    (102, 'GSTR2 - Return for Inward Supplies', current_timestamp(0));

?==?
Create Table tx.gst_ret
(   gst_ret_id BigInt Not Null,
    gst_ret_type_id BigInt Not Null,
    company_id BigInt Not Null,
    gst_state_id BigInt Not Null,
    ret_period Varchar(6) Not Null,
    ret_period_from Date Not Null,
    ret_period_to Date Not Null,
    ret_status SmallInt Not Null,
    annex_info JsonB Not Null Default '{}',
    last_updated Timestamp Not Null,
    Constraint pk_tx_gst_ret_id Primary Key (gst_ret_id),
    Constraint uk_tx_gst_ret_id Unique (gst_ret_type_id, ret_period_from, gst_state_id),
    Constraint fk_tx_gst_ret_type_ret Foreign Key (gst_ret_type_id) References tx.gst_ret_type(gst_ret_type_id)
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'tx.gst_ret', 0;

?==?
Create Table tx.gst_ret_data
(   gst_ret_data_id BigSerial Not Null,
    gst_ret_id BigInt Not Null,
    jdata JsonB Not Null,
    last_updated Timestamp Not Null,
    Constraint pk_tx_gst_ret_data Primary Key (gst_ret_data_id),
    Constraint fk_tx_gst_ret_data_gst_ret Foreign Key (gst_ret_id) References tx.gst_ret(gst_ret_id)
);


?==?
Create Table tx.gstr_resp
(   gstr_resp_id BigSerial Not Null,
    jdata JsonB Not Null,
    b2b_data JsonB Not Null Default '{}',
    last_updated Timestamp Not Null Default(current_timestamp(0)),
    Constraint pk_tx_gstr_resp_id Primary Key (gstr_resp_id)
);

?==?
create table sys.gstn_session
(
	core_session_id character varying not null,
	session_info jsonb not null,
	branch_id bigint not null,
        auth_time timestamp without time zone not null default(current_timestamp(0)),
	last_updated Timestamp without time zone not null default(current_timestamp(0)),
	Constraint pk_sys_gstn_session Primary Key (core_session_id)
	
)

?==?
create table sys.gstn_session_log
(
	gstn_session_log_id bigserial not null,
	core_session_id character varying not null,
	session_info jsonb not null,
	branch_id bigint not null,
	last_updated Timestamp without time zone not null default(current_timestamp(0)),
	Constraint pk_sys_gstn_session_log_id Primary Key (gstn_session_log_id)
)

?==?
Create Table tx.gstr2a_reco
(	gstr2a_reco_id BigInt Not Null,
 	gst_ret_id BigInt Not Null,
 	gstr_resp_id BigInt Not Null,
 	jdata jsonb Not Null,
 	last_updated Timestamp Not Null,
	Constraint pk_tx_gstr2a_reco Primary Key
 	(gstr2a_reco_id),
 	Constraint uk_tx_gstr2a_reco Unique
 	(gst_ret_id)
);

?==?
create table sys.gstn_auth_history
(
    gstn_auth_history_id bigserial not null,
    core_session_id character varying not null,
    session_info jsonb not null,
    branch_id bigint not null,
    auth_time timestamp without time zone not null,
    last_updated timestamp without time zone not null default(current_timestamp(0)),
    CONSTRAINT pk_sys_gstn_auth_history PRIMARY KEY (gstn_auth_history_id)
);
?==?
Create Table tx.ewb
(   ewb_id BigSerial Not Null,
    doc_id Varchar(50) Not Null,
    annex_info JsonB Not Null Default '{}',
    last_updated Timestamp without time zone not null default(current_timestamp(0)),
    Constraint pk_tx_ewb Primary Key (ewb_id)
);

?==?
Create Foreign Table tx.ewb_uom
(   ewb_uom_id BigInt Not Null,
    uom_code Varchar(3) Not Null,
    uom_desc Varchar(50) Not Null,
    last_updated Timestamp Not Null
)
server {dbMain} options(schema_name 'sys', table_name 'ewb_uom' );

?==?
Create Table tx.gstr2a
(   gstr2a_id uuid Not Null,
    gst_ret_id BigInt Not Null,
    gstr_resp_id BigInt Not Null,
    supp_gstin Varchar(15) Not Null,
    txn_type Varchar(5) Not Null,
    pos Varchar(2) Not Null,
    bill_no Varchar(20) Not Null,
    bill_dt Date Not Null,
    base_amt Numeric(18,4) Not Null,
    sgst_amt Numeric(18,4) Not Null,
    cgst_amt Numeric(18,4) Not Null,
    igst_amt Numeric(18,4) Not Null,
    bill_amt Numeric(18,4) Not Null,
    chksum Varchar(64) Not Null,
    ref_bill_no Varchar(20) Not Null,
    ref_bill_dt Date Not Null,
    bill_info JsonB Not Null,
    voucher_id Varchar(50) Not Null Default(''),
    doc_date Date Not Null Default('1970-01-01'),
    voucher_amt Numeric Not Null Default(0),
    match_by Varchar(1) Not Null Default '',
    supplier_id BigInt Not Null Default(-1),
    Constraint pk_tx_gstr2a Primary Key (gstr2a_id),
    Constraint fk_tx_gst_ret_gstr2a Foreign Key (gst_ret_id)
    References tx.gst_ret (gst_ret_id)
);

?==?

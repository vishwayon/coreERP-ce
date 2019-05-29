create schema st authorization postgres;

?==?
CREATE TABLE st.uom_sch
(
	uom_sch_id bigint NOT NULL,
	company_id bigint NOT NULL,
	uom_sch_desc varchar(50) NOT NULL,
	last_updated timestamp without time zone NOT NULL,
	CONSTRAINT pk_st_uom_sch PRIMARY KEY (uom_sch_id),
	CONSTRAINT uk_st_uom_sch UNIQUE (uom_sch_desc)
);

?==?
CREATE TABLE st.uom_sch_item
(
	uom_sch_item_id varchar(50) NOT NULL,
	uom_sch_id bigint NOT NULL,
	uom_desc varchar(20) NOT NULL,
	uom_qty numeric(18,4) NOT NULL, 
	is_base boolean NOT NULL,
	CONSTRAINT pk_st_uom_sch_item PRIMARY KEY (uom_sch_item_id),
	CONSTRAINT fk_st_uom_sch_item_uom_sch FOREIGN KEY (uom_sch_id)
		REFERENCES st.uom_sch (uom_sch_id) MATCH SIMPLE
		ON UPDATE NO ACTION ON DELETE NO ACTION,
	CONSTRAINT uk_st_uom_sch_item UNIQUE (uom_sch_id, uom_desc)
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'st.uom_sch', 0;

?==?
CREATE TABLE st.material_type
(
	material_type_id bigint NOT NULL,
	company_id bigint NOT NULL,
	material_type varchar(50) NOT NULL,
	material_type_code varchar(10) NOT NULL,
        rof_dec smallint not null default 3,
	last_updated timestamp without time zone NOT NULL,
	CONSTRAINT pk_st_material_type PRIMARY KEY (material_type_id),
	CONSTRAINT uk_st_material_type UNIQUE (company_id, material_type)
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'st.material_type', 0;

?==?
CREATE TABLE st.mfg
(
	mfg_id bigint NOT NULL,
	company_id bigint NOT NULL,
	mfg varchar(250) NOT NULL,
	last_updated timestamp without time zone NOT NULL,
	CONSTRAINT pk_st_mfg PRIMARY KEY (mfg_id),
	CONSTRAINT uk_st_mfg UNIQUE (company_id, mfg)
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'st.mfg', 0;

?==?
CREATE TABLE st.hsn
(
	hsn_id bigint NOT NULL,
	company_id bigint NOT NULL,
        hsn_code varchar(250) NOT NULL,
	hsn varchar(250) NOT NULL,
	last_updated timestamp without time zone NOT NULL,
	CONSTRAINT pk_st_hsn PRIMARY KEY (hsn_id),
	CONSTRAINT uk_st_hsn_code UNIQUE (company_id, hsn_code),
        CONSTRAINT uk_st_hsn UNIQUE (company_id, hsn)
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'st.hsn', 0;

?==?
CREATE TABLE st.material
(
	material_id bigint NOT NULL,
	company_id bigint NOT NULL,
	material_name varchar(250) NOT NULL,
	material_code varchar(20) NOT NULL, 
	material_desc varchar(2000) NOT NULL,
	material_type_id bigint NOT NULL,
	inventory_account_id bigint NOT NULL,
        consumed_account_id bigint NOT NULL,
        annex_info JsonB Not Null Default '{}',
        last_updated timestamp without time zone NOT NULL,
	CONSTRAINT pk_st_material PRIMARY KEY (material_id),
	CONSTRAINT fk_st_material_account_head_consumed FOREIGN KEY (consumed_account_id)
		REFERENCES ac.account_head (account_id) MATCH SIMPLE
		ON UPDATE NO ACTION ON DELETE NO ACTION,
	CONSTRAINT fk_st_material_account_head_inventory FOREIGN KEY (inventory_account_id)
		REFERENCES ac.account_head (account_id) MATCH SIMPLE
		ON UPDATE NO ACTION ON DELETE NO ACTION,
	CONSTRAINT fk_st_material_material_type FOREIGN KEY (material_type_id)
		REFERENCES st.material_type (material_type_id) MATCH SIMPLE
		ON UPDATE NO ACTION ON DELETE NO ACTION,
	CONSTRAINT uk_st_material UNIQUE (company_id, material_name)	
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'st.material', 0;

?==?
Create Table st.uom
(   
	uom_id bigint NOT NULL,
        uom_type_id bigint not null default 101,
	material_id bigint NOT NULL,
	uom_desc varchar(20) NOT NULL,
	uom_qty numeric(18,4) NOT NULL,
	is_base boolean NOT NULL,
        is_su boolean not null,
        is_discontinued boolean NOT NULL,
	CONSTRAINT pk_st_uom PRIMARY KEY (uom_id),
	CONSTRAINT uk_st_uom UNIQUE (material_id, uom_type_id, uom_desc),
	CONSTRAINT fk_st_uom_material FOREIGN KEY (material_id)
		REFERENCES st.material (material_id) MATCH SIMPLE
		ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'st.uom', 0;

?==?
CREATE TABLE st.stock_location  
(
	stock_location_id bigint NOT NULL,
	branch_id bigint NOT NULL,
	stock_location_code varchar(4) NOT NULL,
	stock_location_name varchar(250) NOT NULL,
	is_default_for_branch boolean NOT NULL,
	company_id bigint NOT NULL,
        sl_type_id bigint not null default -1,
        jdata jsonb not null default '{}',
	last_updated timestamp without time zone NOT NULL,
	CONSTRAINT pk_sys_stock_location PRIMARY KEY (stock_location_id),
	CONSTRAINT uk_sys_stock_location UNIQUE (branch_id, stock_location_name)
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'st.stock_location', 0;

?==?
Create table st.lc_type
(	lc_type_id BigInt Not Null,
 	company_id BigInt Not Null,
	lc_desc Varchar(250) Not Null,
 	exp_ac_id BigInt Not Null,
 	liab_ac_id BigInt Not Null,
 	jdata jsonb Not Null default '{}',
 	last_updated timestamp Not Null Default current_timestamp(0),
 	Constraint pk_st_lc_type Primary Key
 	(lc_type_id)
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'st.lc_type', 0;

?==?
CREATE TABLE st.stock_control
(
	stock_id  varchar(50) NOT NULL,
	company_id bigint NOT NULL,
	finyear varchar(4) NOT NULL,
	branch_id bigint NOT NULL,
	doc_type varchar(20) NOT NULL,
	doc_date date NOT NULL,
	account_id bigint NOT NULL,
        vat_type_id BigInt Not Null,
	bill_no varchar(50) NOT NULL,
	bill_date date NOT NULL,
	bill_amt numeric(18,4) NOT NULL,
	bill_amt_fc numeric(18,4) NOT NULL,
	bill_receipt_date  date NOT NULL,
	fc_type_id bigint NOT NULL,
	exch_rate numeric(18,6) NOT NULL,
	amt numeric(18,4) NOT NULL,
	amt_fc numeric(18,4) NOT NULL,
	gross_amt numeric(18,4) NOT NULL,
	gross_amt_fc  numeric(18,4) NOT NULL,
	disc_is_value  boolean NOT NULL,
	disc_percent numeric(18,4) NOT NULL,
	disc_amt  numeric(18,4) NOT NULL,
	disc_amt_fc numeric(18,4) NOT NULL,
	misc_taxable_amt  numeric(18,4) NOT NULL,
	misc_taxable_amt_fc numeric(18,4) NOT NULL,
	before_tax_amt numeric(18,4) NOT NULL,
	before_tax_amt_fc numeric(18,4) NOT NULL,
	tax_amt numeric(18,4) NOT NULL,
	tax_amt_fc numeric(18,4) NOT NULL,
	round_off_amt numeric(18,4) NOT NULL,
	round_off_amt_fc numeric(18,4) NOT NULL,
	misc_non_taxable_amt  numeric(18,4) NOT NULL,
	misc_non_taxable_amt_fc numeric(18,4) NOT NULL,
	total_amt numeric(18,4) NOT NULL,
	total_amt_fc numeric(18,4) NOT NULL,
	advance_amt  numeric(18,4) NOT NULL,
	advance_amt_fc numeric(18,4) NOT NULL,
	net_amt numeric(18,4) NOT NULL,
	net_amt_fc numeric(18,4) NOT NULL,
	status smallint NOT NULL,
	en_tax_type smallint NOT NULL,
	narration varchar(8000) NOT NULL,
	remarks varchar(8000) NOT NULL,
	amt_in_words varchar(250) NOT NULL,
	amt_in_words_fc varchar(250) NOT NULL,
	customer_address varchar(500) NOT NULL,
	customer_consignee_id bigint NOT NULL,
	customer_consignee_address  varchar(500) NOT NULL,
	reference_id varchar(50) NOT NULL,
	reference_parent_id varchar(50) NOT NULL,
	target_branch_id bigint NOT NULL,
	sale_account_id bigint NOT NULL,
	terms_and_conditions varchar(500)  NOT NULL,
        salesman_id bigint not null,
        annex_info JsonB Not Null,
        doc_stage_id Varchar(50) Not Null,
        doc_stage_status Smallint Not Null,
	last_updated timestamp without time zone NOT NULL,
	CONSTRAINT pk_st_stock_control PRIMARY KEY (stock_id)
);

?==?
CREATE TABLE st.stock_tran
(
	stock_id varchar(50) NOT NULL,
	stock_tran_id varchar(50) NOT NULL,
	sl_no bigint NOT NULL,
        bar_code character varying(20) Not Null,
        material_type_id bigint NOT NULL,       -- rename column mfg_id to material_type_id
	material_id bigint NOT NULL,
	stock_location_id bigint NOT NULL,
	uom_id bigint NOT NULL,
	issued_qty numeric(18, 4) NOT NULL,
	received_qty numeric(18,4) NOT NULL,
	rate numeric(18,4) NOT NULL,
	rate_fc numeric(18,4) NOT NULL,
	disc_is_value boolean NOT NULL,
	disc_percent numeric(18,4) NOT NULL,
	disc_amt numeric(18,4) NOT NULL,
	disc_amt_fc numeric (18,4) NOT NULL,
        bt_amt numeric(18,4) NOT NULL,
        bt_amt_fc numeric(18,4) NOT NULL,
        tax_schedule_id bigint NOT NULL,
        apply_itc boolean Not Null,
        en_tax_type smallint NOT Null,
        tax_pcnt numeric(18,4) NOT NULL,
        tax_amt numeric(18,4) NOT NULL,
        tax_amt_fc numeric(18,4) NOT NULL,
	item_amt numeric(18,4) NOT NULL,
	item_amt_fc numeric(18,4) NOT NULL,
	reference_id varchar(50) NOT NULL,
	reference_tran_id varchar(50) NOT NULL,
	reference_parent_id varchar(50) NOT NULL,
	reference_parent_tran_id varchar(50) NOT NULL,
	target_stock_location_id bigint NOT NULL,
        is_service Boolean Not Null Default false,
        in_lc Boolean Not Null Default false,
        other_amt Numeric(18,4) Not Null Default 0,
	CONSTRAINT pk_st_stock_tran PRIMARY KEY (stock_tran_id),
	CONSTRAINT fk_st_stock_tran_stock_control FOREIGN KEY (stock_id)
		REFERENCES st.stock_control (stock_id) MATCH SIMPLE
		ON UPDATE NO ACTION ON DELETE NO ACTION,
	CONSTRAINT fk_st_stock_tran_material FOREIGN KEY (material_id)
		REFERENCES st.material (material_id) MATCH SIMPLE
		ON UPDATE NO ACTION ON DELETE NO ACTION,
	CONSTRAINT fk_st_stock_tran_stock_location FOREIGN KEY (stock_location_id)
		REFERENCES st.stock_location (stock_location_id) MATCH SIMPLE
		ON UPDATE NO ACTION ON DELETE NO ACTION,
	CONSTRAINT fk_st_stock_tran_uom FOREIGN KEY (uom_id)
		REFERENCES st.uom (uom_id) MATCH SIMPLE
		ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
CREATE INDEX ix_st_stock_tran on st.stock_tran (stock_id);

?==?
CREATE TABLE st.stock_lc_tran
(       stock_id varchar(50) NOT NULL,
	stock_lc_tran_id varchar(50) NOT NULL,
	en_apportion_type smallint NOT NULL,
	account_id bigint NOT NULL,
	supplier_paid boolean not null,
	account_affected_id bigint NOT NULL,
	debit_amt_fc numeric(18,4) NOT NULL,
	debit_amt numeric(18,4) NOT NULL,
        tax_schedule_id bigint NOT NULL,
        en_tax_type smallint NOT NULL,
        tax_pcnt numeric(5,2) NOT NULL,
        tax_amt numeric(18,4) NOT NULL,
	bill_no varchar(50) NOT NULL,
	bill_date date NOT NULL,
	is_taxable boolean NOT NULL,
	description varchar(250) NOT NULL,
        apply_itc boolean Not Null,
        lc_type_id BigInt Not Null,
        req_alloc boolean Not Null,
        post_gl boolean Not Null,
	CONSTRAINT pk_st_stock_lc_tran PRIMARY KEY (stock_lc_tran_id),
	CONSTRAINT fk_st_stock_lc_tran_stock_control FOREIGN KEY (stock_id)
            REFERENCES st.stock_control (stock_id) MATCH SIMPLE
            ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
Create Table st.stock_tran_war
(   stock_tran_war_id Varchar(50) Not Null,
    stock_id Varchar(50) Not Null,
    stock_tran_id Varchar(50) Not Null,
    material_id BigInt Not Null,
    mfg_serial Varchar(250) Not Null,
    mfg_date Date Not Null,
    Constraint pk_st_stock_tran_war Primary Key (stock_tran_war_id),
    CONSTRAINT fk_st_stock_tran_war_material FOREIGN KEY (material_id)
	REFERENCES st.material (material_id)
);

?==?
Create Table st.stock_tran_qc
(	stock_tran_qc_id Varchar(50) Not Null,
 	stock_id Varchar(50) Not Null,
 	stock_tran_id Varchar(50) Not Null,
 	test_insp_id Varchar(50) Not Null,
 	test_insp_date Date Not Null,
 	material_id BigInt Not Null,
 	test_result_id BigInt Not Null,
 	accept_qty Numeric(18,3) Not Null,
 	reject_qty Numeric(18,3) Not Null,
 	lot_no Varchar(256) Not Null,
 	mfg_date Date Not Null,
 	exp_date Date Not Null,
 	best_before Date Not Null,
        ref_info text not null default '',
 	Constraint pk_st_stock_tran_qc Primary Key
 	( stock_tran_qc_id)
);

?==?
Create Table st.inv_bb
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
	Constraint fk_st_inv_control_bb Foreign Key (inv_id)
	References st.stock_control (stock_id)
);

?==?
CREATE TABLE st.stock_transfer_park_post
(
	stock_id varchar(50) NOT NULL,
	stock_transfer_id varchar(50) NOT NULL,
	source_branch_id bigint NOT NULL,
	target_branch_id bigint NOT NULL,
	status smallint NOT NULL,
	doc_date date NULL,
	finyear varchar(4) NOT NULL,
	reference varchar(50) NOT NULL,
	authorised_by varchar(50) NOT NULL,
	last_updated timestamp without time zone NOT NULL,
	CONSTRAINT pk_st_stock_transfer_park_post PRIMARY KEY (stock_transfer_id),
	CONSTRAINT fk_st_stock_transfer_park_post_stock_control FOREIGN KEY (stock_id)
		REFERENCES st.stock_control (stock_id) MATCH SIMPLE
		ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
create table st.stock_movement_type
(
	stock_movement_type_id bigint not null,
	stock_movement_type varchar(50) not null,
	stock_movement_type_group char(1) not null, 
        seq_type varchar(200) not null,
	CONSTRAINT pk_stock_movement_type PRIMARY KEY (stock_movement_type_id)
)

?==?
Insert into st.stock_movement_type(stock_movement_type_id, stock_movement_type, stock_movement_type_group, seq_type)
Values
    (-1, 'Opening Balance', 'R', ''),
    (1, 'Purchase', 'R', 'SP'),
    (2, 'Direct Consumption', 'I', 'SC'),
    (3, 'Purchase Return', 'I', 'PR'),
    (4, 'Adjustment', 'R', 'SAN'),
    (5, 'Contra', 'C', 'LTN'),
    (6, 'Sale', 'I', 'SI'),
    (7, 'Sale Return', 'R', 'SR'),
    (8, 'Stock Transfer Out', 'I', 'ST'),
    (9, 'Stock Transfer IN', 'R', 'STIN'),
    (10, 'Sale Return Note', 'R', 'SRN'),
    (11, 'Purchase Return Note', 'I', 'PRN'),
    (12, 'Sale', 'I', 'SIV'),
    (13, 'Job Work Receipt', 'R', 'JWR'),
    (14, 'Material Conversion Issue', 'I', 'MCN'),
    (15, 'Material COnversion Receipt', 'R', 'MCN'),
    (16, 'Loss/Gain in Transit', 'I', 'LGT'),
    (101, 'Issue To Production', 'I', 'MI'),
    (102, 'Receipt In Production', 'R', 'MI'),
    (103, 'Batch Consumption', 'I', 'BSH'),
    (104, 'Production Output', 'R', 'BSH'),
    (105, 'Material Recovery', 'R', 'MRC'),
    (106, 'RnD Issue', 'I', 'RDRI');

?==?
create table st.stock_ledger
(
	stock_ledger_id uuid not null,
	company_id bigint not null,
	branch_id bigint not null,
	finyear varchar(4) not null,
	voucher_id varchar(50) NOT NULL,
	vch_tran_id varchar(50) NOT NULL,
	doc_date date NOT NULL,
	material_id bigint not null,
	stock_location_id bigint not null,
	reference_id varchar(50) not null,
	reference_tran_id varchar(50) not null,
	reference_date date null,
	narration varchar(500) NOT NULL,
	uom_id bigint not null,
	uom_qty numeric(18,4) not null,
	received_qty numeric(18,4) not null,
	issued_qty numeric(18,4) not null,
	unit_rate_lc numeric(18,4) not null,
	stock_movement_type_id bigint not null,
	inserted_on timestamp not null,
        account_id bigint not null,
	last_updated timestamp not null, 
        is_opbl boolean DEFAULT false, 
        bp_id varchar(50) not null default '',
        CONSTRAINT pk_stock_ledger PRIMARY KEY (stock_ledger_id),
        CONSTRAINT fk_stock_ledger_material FOREIGN KEY (material_id)
	      REFERENCES st.material (material_id),
        CONSTRAINT fk_stock_ledger_account_head FOREIGN KEY (account_id)
	      REFERENCES ac.account_head (account_id),
        CONSTRAINT fk_stock_ledger_stock_movement_type FOREIGN KEY (stock_movement_type_id)
	      REFERENCES st.stock_movement_type (stock_movement_type_id),
        CONSTRAINT uk_stock_ledger UNIQUE (voucher_id, vch_tran_id, stock_location_id, material_id),
        CONSTRAINT fk_stock_ledger_stock_loc FOREIGN KEY (stock_location_id)
	      REFERENCES st.stock_location (stock_location_id)
);

?==?
Create Index ix_st_stock_ledger_voucher 
On st.stock_ledger (voucher_id);

?==?
Create table st.lot_state
(	lot_state_id Int Not Null,
 	lot_state Varchar(100) Not Null,
 	state_desc varchar(250) Not NUll,
 	Constraint pk_st_lot_state Primary Key (lot_state_id)
);

?==?
Insert Into st.lot_state (lot_state_id, lot_state, state_desc)
Values 	(101, 'Normal/QC Accepted', 'Normal Stock Available for Issue'),
        (102, 'QC Rejected', 'Rejected Stock Not Available for Issue'),
        (103, 'Preserved', 'Preserved Stock for Statutory Compliance'),
        (104, 'Damaged', 'Damaged Stock Not Available for Issue'),
        (105, 'Quarantined', 'Quarantined Stock Not Available for Issue');

?==?
Create table st.sl_lot
(	sl_lot_id uuid Not Null,
 	sl_id uuid Not Null,
 	test_insp_id Varchar(50) Not Null,
 	test_insp_date Date Not Null,
 	lot_no Varchar(256) Not Null,
 	lot_qty Numeric(18, 3) Not Null,
 	mfg_date Date Not Null,
 	exp_date Date Not Null,
 	best_before Date Not Null,
 	lot_state_id Int Not Null,
 	ref_info JsonB Not Null Default '{}',  
	Constraint pk_st_sl_lot Primary Key (sl_lot_id),
 	Constraint fk_st_sl_lot_stock_ledger Foreign Key (sl_id)
 	References st.stock_ledger(stock_ledger_id)
);

?==?
Create table st.sl_lot_alloc
(	sl_lot_alloc_id uuid Not Null,
	sl_lot_id uuid Not Null,
	voucher_id Varchar(50) Not Null,
	vch_tran_id Varchar(50) Not Null,
	vch_date Date Not Null,
	material_id BigInt Not Null,
	lot_issue_qty Numeric(18,3) Not Null,
 	status Int Not Null,
	sl_id uuid Null,
        tran_group Varchar(50) Not Null default '',
	Constraint pk_st_sl_lot_alloc Primary Key (sl_lot_alloc_id),
 	Constraint fk_st_sl_lot_alloc_sl_lot Foreign Key (sl_lot_id)
 	References st.sl_lot(sl_lot_id)
);

?==?
CREATE INDEX ix_st_sl_lot_alloc_vch_tran_id
    ON st.sl_lot_alloc (vch_tran_id ASC);

?==?
Create Index ix_st_sl_lot_alloc On st.sl_lot_alloc (voucher_id);

?==?
Create Table st.mat_cat
(	mat_cat_id BigInt Not Null,
	mat_cat Varchar(100) Not Null,
	mat_cat_desc Varchar(500) Not Null,
	company_id BigInt Not Null,
	mat_cat_parent_id BigInt Not Null,
	last_updated timestamp Not Null,
	Constraint pk_st_mat_cat Primary Key
	( mat_cat_id )
);

?==?
Create Table st.mat_cat_key
(	mat_cat_key_id BigInt Not Null,
	mat_cat_id BigInt Not Null,
	mat_cat_key Varchar(100) Not Null,
	mat_cat_key_desc Varchar(500) Not Null,
	Constraint pk_st_mat_cat_key Primary Key( mat_cat_key_id ),
	Constraint fk_st_mat_cat_key_mat_cat Foreign Key (mat_cat_id)
	References st.mat_cat(mat_cat_id)
);

?==?
Create Table st.mat_cat_attr
(	mat_cat_attr_id BigInt Not Null,
	mat_cat_id BigInt Not Null,
	mat_cat_attr Varchar(50) Not Null,
	mat_cat_attr_desc Varchar(500) Not Null,
	Constraint pk_st_mat_cat_attr Primary Key( mat_cat_attr_id ),
	Constraint fk_st_mat_cat_attr_mat_cat Foreign Key (mat_cat_id)
	References st.mat_cat(mat_cat_id)
);

?==?
insert into sys.mast_seq(mast_seq_type, seed)
select 'st.mat_cat', 0 

?==?
insert into sys.mast_seq(mast_seq_type, seed)
select 'st.mat_cat_key', 0 

?==?
insert into sys.mast_seq(mast_seq_type, seed)
select 'st.mat_cat_attr', 0 

?==?
create table st.sl_type
(
    sl_type_id bigint not null,
    sl_type varchar(50) not null,
    constraint pk_sl_type_id primary key (sl_type_id)
);

?==?
Insert into st.sl_type(sl_type_id, sl_type)
values (1, 'Stores'),
(2, 'Production Floor'),
(3, 'Warehouse'),
(4, 'Production Storage');

?==?
create table st.stock_barcode_print
(   stock_barcode_print_id serial not null,
    stock_id varchar(50) not null,
    barcode_info jsonb not null,
    last_updated timestamp without time zone NOT NULL DEFAULT current_timestamp(0),
    CONSTRAINT pk_st_stock_barcode_print PRIMARY KEY (stock_barcode_print_id)
)

?==?
insert into sys.settings(key, value)
values ('stock_neg_allow', 'false');

?==?
Create table st.cons_type 
(
    cons_type_id bigint not null,
    company_id bigint not null,
    cons_type_desc character varying(100) not null,
    last_updated timestamp without time zone not null,
    constraint pk_st_cons_type primary key (cons_type_id),
    constraint uk_st_cons_type unique (company_id, cons_type_desc)
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'st.cons_type', 0;

?==?
Insert into sys.settings(key, value)
Values('st_qc_reqd', '0');

?==?
Insert into sys.settings(key, value)
Values('st_lot_alloc_reqd', '0');

?==?
create table st.stock_tran_extn(
    stock_id varchar(50) not null,
    stock_tran_id varchar(50) not null,
    receipt_qty numeric(18,4) not null,
    short_qty numeric(18,4) not null,
    receipt_sl_id bigint not null,
	CONSTRAINT pk_st_stock_tran_extn PRIMARY KEY (stock_tran_id)    
);

?==?
insert into sys.rpt_company_info(key, value)
Select 'inv_bank_info', '';

?==?
create table st.uom_type
(
    uom_type_id bigint,
    uom_type varchar(20) not null,
    CONSTRAINT pk_st_uom_type PRIMARY KEY (uom_type_id)
);

?==?
Insert into st.uom_type
Values(101, 'Base Unit'),
    (104, 'Sale Unit'),
    (103, 'Purchase Unit');

?==?
Create table st.mat_level
(	
    mat_level_id uuid NOT NULL,
    branch_id bigint not null,	
    material_id bigint not null,
    min_qty numeric(18,3) not null,
    reorder_level numeric(18,3) not null,
    reorder_qty numeric(18,3) not null,
    max_qty numeric(18,3) not null,
    lead_time smallint not null,
    CONSTRAINT pk_mat_level PRIMARY KEY (mat_level_id),
    CONSTRAINT fk_mat_level_material FOREIGN KEY (material_id)
	  REFERENCES st.material (material_id)
);

?==?
CREATE TABLE st.mrgp_tran
(
    sl_no smallint NOT NULL,
    stock_id varchar(50) NOT NULL,
    vch_tran_id varchar(50) NOT NULL,
    out_qty numeric(18,4) NOT NULL,
    in_qty numeric(18,4) NOT NULL,
    description varchar(250) NOT NULL,
    item_name varchar(50) not null default '',
    CONSTRAINT pk_st_mrgp_tran PRIMARY KEY (vch_tran_id),
    CONSTRAINT fk_st_mrgp_tran_st_control FOREIGN KEY (stock_id)
            REFERENCES st.stock_control (stock_id)
);

?==?
CREATE TABLE st.srr
(
    srr_id bigint NOT NULL,
    company_id bigint NOT NULL,
    srr_desc varchar(500) NOT NULL,
    last_updated timestamp without time zone NOT NULL,
    CONSTRAINT pk_st_srr PRIMARY KEY (srr_id)
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'st.srr', 0;

?==?
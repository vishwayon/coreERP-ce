create schema ac;

?==?
CREATE TABLE ac.account_group
(
	parent_key varchar(20) NOT NULL,
	group_key varchar(20) NOT NULL,
	group_id bigint NOT NULL,
	group_name varchar(250) NOT NULL,
	group_code varchar(10) NOT NULL,
	company_id bigint NOT NULL,
	group_path varchar(3500) NOT NULL,
	last_updated timestamp without time zone NOT NULL,
	CONSTRAINT pk_account_group PRIMARY KEY (group_id),
	CONSTRAINT uk_account_group UNIQUE (company_id, group_key)
);

?==?

CREATE TABLE ac.account_group_sequence
(
	level varchar(1) NOT NULL,
	max_id bigint NOT NULL DEFAULT 0,
        CONSTRAINT pk_account_group_sequence PRIMARY KEY (level)
);


?==?
Insert into ac.account_group(parent_key, group_key, group_id, group_name, group_code, company_id, group_path, last_updated)
values (0, 'A001', ({company_id}*1000000) + 1, 'Assets', '', {company_id}, 'A001', current_timestamp(0)), 
       (0, 'A002', ({company_id}*1000000) + 2, 'Owner''s Funds', '', {company_id}, 'A002', current_timestamp(0)),
       (0, 'A003', ({company_id}*1000000) + 3, 'Liabilities', '', {company_id}, 'A003', current_timestamp(0)),
       (0, 'A004', ({company_id}*1000000) + 4, 'Income', '', {company_id}, 'A004', current_timestamp(0)),
       (0, 'A005', ({company_id}*1000000) + 5, 'Cost of Goods Consumed', '', {company_id}, 'A005', current_timestamp(0)),
       (0, 'A006', ({company_id}*1000000) + 6, 'Expenses', '', {company_id}, 'A006', current_timestamp(0)),
       (0, 'A007', ({company_id}*1000000) + 7, 'P&L Appropriations', '', {company_id}, 'A007', current_timestamp(0));
 
?==?
-- Ensure that the group sequence is max of previous inserts
INSERT INTO sys.mast_seq (mast_seq_type, seed)
SELECT 'ac.account_group', 7;

?==?
CREATE TABLE ac.account_type
(
  account_type_id bigint NOT NULL,
  account_type_desc varchar(50) NOT NULL, 
  is_inactive boolean default false,
  last_updated timestamp NOT NULL,
  CONSTRAINT pk_account_type PRIMARY KEY (account_type_id),
  constraint uk_account_type unique (account_type_desc)
);

?==?
Insert into ac.account_type(account_type_id, account_type_desc, is_inactive, last_updated)
Values (0, 'Hidden', false, current_timestamp(0)),
       (1, 'Bank', false, current_timestamp(0)),
       (2, 'Cash', false, current_timestamp(0)),
       (3, 'Others', false, current_timestamp(0)),
       (4, 'Investments - Trade', false, current_timestamp(0)),
       (5, 'Investments - Non Trade', false, current_timestamp(0)),
       (6, 'Inventories', false, current_timestamp(0)),
       (7, 'Debtors', false, current_timestamp(0)),
       (8, 'Deffered Revenue Expenditure', false, current_timestamp(0)),
       (9, 'Capital', false, current_timestamp(0)),
       (10, 'Long Term Loans', false, current_timestamp(0)),
       (11, 'Short Term Loans', false, current_timestamp(0)),
       (12, 'Creditors', false, current_timestamp(0)),
       (13, 'Other Liabilities', false, current_timestamp(0)),
       (14, 'Other Assets', false, current_timestamp(0)),
       (15, 'Retained Earnings', false, current_timestamp(0)),
       (16, 'Fixed Assets', false, current_timestamp(0)),
       (17, 'Purchases', false, current_timestamp(0)),
       (18, 'Sales', false, current_timestamp(0)),
       (19, 'Loans & Advances', false, current_timestamp(0)),
       (20, 'Branch/Divisions', false, current_timestamp(0)),
       (21, 'Direct Expenses', false, current_timestamp(0)),
       (22, 'Indirect Expenses', false,  current_timestamp(0)),
       (23, 'Direct Income', false, current_timestamp(0)),
       (24, 'Indirect Income', false, current_timestamp(0)),
       (25, 'Inventory Consumption', false, current_timestamp(0)),
       (26, 'Deposits', false, current_timestamp(0)),
       (27, 'Duties', false, current_timestamp(0)),
       (29, 'Provisions', false, current_timestamp(0)),
       (30, 'Profit & Loss A/c', false, current_timestamp(0)),
       (31, 'Overheads', false, current_timestamp(0)),
       (32, 'Petty Cash', false, current_timestamp(0)),
       (33, 'Packing Credit', false, current_timestamp(0)),
       (34, 'Bill Discounting', false, current_timestamp(0)),
       (35, 'Purchase Voucher Discounting', false, current_timestamp(0)),
       (36, 'Landed Cost Liability', false, current_timestamp(0)),
       (37, 'PDC Discounting', false, current_timestamp(0)),
       (38, 'Selling Expenses', false, current_timestamp(0)),
       (39, 'Cash Supplier', false, current_timestamp(0)),
       (40, 'Cash Customer', false, current_timestamp(0)),
       (41, 'Leasable Fixed Asset', false, current_timestamp(0)),
       (42, 'Depreciation', false, current_timestamp(0)),
       (43, 'Accumulated Depreciation', false, current_timestamp(0)),
       (44, 'TDS Payable(Other Liability)', false, current_timestamp(0)),
       (45, 'Inter Branch', false, current_timestamp(0)),
       (46, 'Bills Receivable Control', false, current_timestamp(0)),
       (47, 'Bills Payable Control', false, current_timestamp(0)),
       (48, 'Repairs And Maintenance', false, current_timestamp(0)),
       (49, 'Landed Cost', false, current_timestamp(0)),
       (50, 'Purchase Return', false, current_timestamp(0)),
       (51, 'Sales Return', false, current_timestamp(0)),
       (52, 'Customer Advance', false, current_timestamp(0));

?==?
CREATE TABLE ac.account_head
(
  account_id bigint NOT NULL,
  account_head varchar(250) NOT NULL,
  account_code varchar(20) NOT NULL,
  company_id bigint NOT NULL,
  consolidate_group_id bigint NOT NULL,
  group_id bigint NOT NULL,
  account_type_id bigint NOT NULL,
  last_updated timestamp NOT NULL,
  en_advance_mode smallint NOT NULL,
  sub_head_dim_id bigint not null ,
  is_ref_ledger boolean not null,
  CONSTRAINT pk_account_head PRIMARY KEY (account_id),
  CONSTRAINT fk_account_group_account_head FOREIGN KEY (group_id)
      REFERENCES ac.account_group (group_id),
  CONSTRAINT fk_account_type_account_head FOREIGN KEY (account_type_id)
      REFERENCES ac.account_type (account_type_id),
  CONSTRAINT uk_account_head UNIQUE (company_id, account_head)
);

?==?
create table ac.account_balance
(
	account_balance_id varchar(50),
	finyear varchar(4) not null,
	account_id bigint not null,
	company_id bigint not null,
	branch_id bigint not null,
	debit_balance numeric(18,4) not null,
	credit_balance numeric(18,4) not null,
	last_updated timestamp	,
        CONSTRAINT pk_account_balance primary key (account_balance_id)
);

?==?
insert into sys.mast_seq(mast_seq_type, seed)
select 'ac.account_head', 0;

?==?
Insert into ac.account_head(account_id, account_head, account_code, company_id, consolidate_group_id,  group_id, account_type_id, last_updated, en_advance_mode, sub_head_dim_id, is_ref_ledger)
select 0, 'Refer Voucher', '00', {company_id}, 0, ({company_id}*1000000) + 1, 0, current_timestamp(0), 0, -1, false

?==?
CREATE TABLE ac.fc_type
(
  company_id bigint NOT NULL,
  fc_type_id bigint NOT NULL,
  fc_type varchar(20) NOT NULL,
  currency varchar(20) NOT NULL,
  sub_currency varchar(20) NOT NULL,
  exch_rate numeric(18,6) not null,
  last_updated timestamp not null,
  CONSTRAINT pk_fc_type_id PRIMARY KEY (fc_type_id)
);

?==?
Insert into ac.fc_type(company_id, fc_type_id, fc_type, currency, sub_currency, exch_rate, last_updated)
Select {company_id}, 0, 'Local', '', '', 1, current_timestamp(0);

?==?
insert into sys.mast_seq
select 'ac.fc_type', 0;

?==?
Create Table ac.general_ledger
(
	general_ledger_id uuid,
	company_id bigint not null,
	branch_id bigint not null, 
	finyear varchar(4) NOT NULL,
	voucher_id varchar(50) NOT NULL,
	doc_date date NOT NULL,
	account_id bigint NOT NULL,
	account_affected_id bigint NOT NULL,
	fc_type_id bigint NOT NULL,
	exch_rate numeric(18,6) NOT NULL,
	debit_amt_fc numeric(18,4) NOT NULL,
	credit_amt_fc numeric(18,4) NOT NULL,
	debit_amt numeric(18,4) NOT NULL,
	credit_amt numeric(18,4) NOT NULL,
	narration varchar(500) NOT NULL,
	cheque_details varchar(500) NOT NULL,
        CONSTRAINT pk_general_ledger primary key (general_ledger_id),
	CONSTRAINT fk_general_ledger_account_account_head FOREIGN KEY (account_id)
        REFERENCES ac.account_head (account_id),
	CONSTRAINT fk_general_ledger_account_account_head_affected FOREIGN KEY (account_affected_id)
        REFERENCES ac.account_head (account_id),
        CONSTRAINT uk_general_ledger UNIQUE (voucher_id, branch_id, account_id, account_affected_id)
);

?==?
Create table ac.ib_account
(
	ib_account_id varchar(50),
	account_id bigint not null,
	branch_id bigint not null,
	Constraint pk_ib_account_id primary key (ib_account_id)
);

?==?
Create Table ac.bank_reco_optxn
(   voucher_id Varchar(50) Not Null,
    company_id BigInt Not Null,
    branch_id BigInt Not Null,
    doc_date Date Not Null,
    vch_caption Varchar(100) Not Null,
    account_id BigInt Not Null,
    cheque_number Varchar(20) Not Null,
    cheque_date Date Not Null,
    debit_amt Numeric(18,4) Not Null,
    credit_amt Numeric(18,4) Not Null,
    collected Boolean Not Null,
    collection_date Date,
    Constraint pk_ac_bank_reco_optxn Primary Key
    (voucher_id)
);

?==?
CREATE TABLE ac.vch_control
(
  company_id bigint NOT NULL,
  doc_type varchar(20) NOT NULL,
  finyear varchar(4) NOT NULL,
  branch_id bigint NOT NULL,
  voucher_id varchar(50) NOT NULL,
  doc_date date NOT NULL,
  is_inter_branch boolean NOT NULL,
  vch_caption varchar(100) NOT NULL,
  dc character(1) NOT NULL,
  account_id bigint NOT NULL,
  fc_type_id bigint NOT NULL,
  debit_amt_fc numeric(18,4) NOT NULL,
  credit_amt_fc numeric(18,4) NOT NULL,
  exch_rate numeric(18,6) NOT NULL,
  debit_amt numeric(18,4) NOT NULL,
  credit_amt numeric(18,4) NOT NULL,
  narration varchar(2000) NOT NULL,
  status smallint NOT NULL,
  amt_in_words varchar(250) NOT NULL,
  amt_in_words_fc varchar(250) NOT NULL,
  remarks varchar(500) NOT NULL,
  bank_charges numeric(18,4) NOT NULL,
  cheque_number varchar(20) NOT NULL,
  cheque_date date NOT NULL,
  collected boolean NOT NULL,
  collection_date date,
  is_reversal boolean NOT NULL,
  reversal_date date,
  reversal_comments text not null default '',
  pdc_id varchar(50) NOT NULL,
  pdc_date date,
  is_pdc boolean NOT NULL,
  cheque_bank varchar(50) not null default '',
  cheque_branch varchar(50) not null default '',
  form_type smallint NOT NULL,
  doc_object_id integer NOT NULL,
  txn_type smallint not null default -1,
  is_ac_payee boolean not null,
  is_non_negotiable boolean not null,
  annex_info JsonB Not Null Default '{}',
  last_updated timestamp NOT NULL,
  CONSTRAINT pk_ac_vch_control PRIMARY KEY (voucher_id)
);

?==?
CREATE TABLE ac.vch_tran
(
  sl_no smallint NOT NULL,
  vch_tran_id varchar(50) NOT NULL,
  voucher_id varchar(50) NOT NULL,
  branch_id bigint NOT NULL,
  dc character(1) NOT NULL,
  account_id bigint NOT NULL,
  debit_amt_fc numeric(18,4) NOT NULL,
  credit_amt_fc numeric(18,4) NOT NULL,
  debit_amt numeric(18,4) NOT NULL,
  credit_amt numeric(18,4) NOT NULL,
  hsn_sc_id BigInt Not Null Default -1,
  tran_desc Varchar(250) Not Null Default '',
  bill_no Varchar(16) Not Null Default '',
  bill_dt Date Not Null Default '1970-01-01',
  bill_amt Numeric(18,4) Default 0,
  roff_amt Numeric(5,4) Default 0,
  supp_name Varchar(250) Not Null Default '',
  supp_addr Varchar(500) Not Null Default '',
  CONSTRAINT pk_ac_vch_tran PRIMARY KEY (vch_tran_id),
  CONSTRAINT fk_ac_vch_control_tran FOREIGN KEY (voucher_id)
      REFERENCES ac.vch_control (voucher_id) 
);

?==?
CREATE TABLE ac.cv_reconciled
(
  vch_tran_id varchar(50) NOT NULL,
  company_id bigint NOT NULL,
  finyear varchar(4) NOT NULL,
  branch_id bigint NOT NULL,
  voucher_id varchar(50) NOT NULL,
  doc_date date NOT NULL,
  vch_caption varchar(100) NOT NULL,
  account_id bigint NOT NULL,
  fc_type_id bigint NOT NULL,
  debit_amt_fc numeric(18,4) NOT NULL,
  credit_amt_fc numeric(18,4) NOT NULL,
  exch_rate numeric(18,6) NOT NULL,
  debit_amt numeric(18,4) NOT NULL,
  credit_amt numeric(18,4) NOT NULL,
  cheque_number varchar(20) NOT NULL,
  cheque_date date NOT NULL,
  collected boolean NOT NULL,
  collection_date date NULL,
  last_updated timestamp without time zone NOT NULL,
  CONSTRAINT pk_cv_reconciled primary key (vch_tran_id)
);

?==?
CREATE TABLE ac.account_head_hidden
(
    account_head_hidden_id varchar(50),
    account_id bigint NOT NULL,
    branch_id bigint NOT NULL,
    company_id bigint NOT NULL,
    CONSTRAINT pk_ac_account_head_hidden PRIMARY KEY (account_head_hidden_id),
    CONSTRAINT uk_ac_account_head_hidden UNIQUE (account_id, branch_id),
    CONSTRAINT fk_ac_account_head_hidden_account_head FOREIGN KEY (account_id)
    REFERENCES ac.account_head(account_id)
);

?==?
CREATE TABLE ac.pay_term
(
	company_id bigint NOT NULL,
	pay_term_id bigint NOT NULL,
	pay_term varchar(50) NOT NULL,
	pay_term_desc varchar(250) NOT NULL,
        calc_type smallint not null,
	pay_days smallint NOT NULL,
	last_updated timestamp NOT NULL,
        for_cust boolean not null default false,
        for_supp boolean not null default false,
	CONSTRAINT pk_pay_term PRIMARY KEY (pay_term_id)
);

?==?
INSERT INTO sys.mast_seq (mast_seq_type, seed)
SELECT 'ac.pay_term', 0;

?==?﻿
CREATE TABLE ac.sub_head_dim
(
	sub_head_dim_id bigint NOT NULL,
	sub_head_dim varchar(250) NOT NULL,
	company_id bigint NOT NULL,
	last_updated timestamp without time zone NOT NULL,
	CONSTRAINT pk_ac_sub_head_dim PRIMARY KEY (sub_head_dim_id),
	CONSTRAINT uk_ac_sub_head_dim UNIQUE (company_id, sub_head_dim)
);

?==?
INSERT INTO sys.mast_seq(mast_seq_type, seed)
SELECT 'ac.sub_head_dim', 0;

?==?
CREATE TABLE ac.sub_head_dim_acc
(
	sub_head_dim_acc_id varchar(50) NOT NULL,
	sub_head_dim_id bigint NOT NULL,
	account_id bigint NOT NULL,
	CONSTRAINT pk_ac_sub_head_dim_acc PRIMARY KEY (sub_head_dim_acc_id),
	CONSTRAINT fk_ac_sub_head_dim_acc_account_head FOREIGN KEY (account_id)
		REFERENCES ac.account_head (account_id) MATCH SIMPLE
		ON UPDATE NO ACTION ON DELETE NO ACTION,
	CONSTRAINT fk_ac_sub_head_dim_acc_sub_head_dim FOREIGN KEY (sub_head_dim_id)
		REFERENCES ac.sub_head_dim (sub_head_dim_id) MATCH SIMPLE
		ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
CREATE TABLE ac.sub_head
( 
        sub_head_id bigint NOT NULL,
	sub_head varchar(250) NOT NULL,
	sub_head_code varchar(10) NOT NULL,
	sub_head_dim_id bigint NOT NULL,
	company_id bigint NOT NULL,
	closed_date date NULL,
	is_closed boolean NOT NULL,
	last_updated timestamp without time zone NOT NULL,
	CONSTRAINT pk_ac_sub_head PRIMARY KEY (sub_head_id),
	CONSTRAINT fk_ac_sub_head_sub_head_dim FOREIGN KEY (sub_head_dim_id)
		REFERENCES ac.sub_head_dim (sub_head_dim_id) MATCH SIMPLE
		ON UPDATE NO ACTION ON DELETE NO ACTION,
	CONSTRAINT uk_ac_sub_head UNIQUE (company_id, sub_head)
	
);

?==?
INSERT INTO sys.mast_seq(mast_seq_type, seed)
SELECT 'ac.sub_head', 0;

?==?
Insert into sys.settings
values('bs_closing_stock', '0')

?==?
Insert into sys.settings
values('bs_pnl_account', '-1')

?==?
create table ac.sub_head_ledger
(
	sub_head_ledger_id uuid,
	company_id bigint not null,
	branch_id bigint not null, 
	finyear varchar(4) NOT NULL,
	voucher_id varchar(50) NOT NULL, 
	vch_tran_id varchar(50) not null,
	doc_date date NOT NULL,
	account_id bigint NOT NULL,
	sub_head_id bigint NOT NULL,
	fc_type_id bigint NOT NULL,
	exch_rate numeric(18,6) NOT NULL,
	debit_amt_fc numeric(18,4) NOT NULL,
	credit_amt_fc numeric(18,4) NOT NULL,
	debit_amt numeric(18,4) NOT NULL,
	credit_amt numeric(18,4) NOT NULL,
	narration varchar(500) NOT NULL,
	status smallint not null,
	not_by_alloc boolean not null,
        CONSTRAINT pk_sub_head_ledger primary key (sub_head_ledger_id),
	CONSTRAINT fk_sub_head_ledger_account_account_head FOREIGN KEY (account_id)
        REFERENCES ac.account_head (account_id),
	CONSTRAINT fk_sub_head_ledger_sub_head FOREIGN KEY (sub_head_id)
        REFERENCES ac.sub_head (sub_head_id)
        -- uk Constraint removed as we allow same sub-head to repeat for a line item
);

?==?
Create Table ac.ref_ledger
(	ref_ledger_id uuid Not Null,
	voucher_id Varchar(50) Not Null,
	doc_date Date Not Null,
	account_id BigInt Not Null,
	branch_id BigInt Not Null,
	ref_no Varchar(50) Not Null,
	ref_desc Varchar(250) Not Null,
	debit_amt Numeric(18,4) Not Null,
	credit_amt Numeric(18,4) Not Null,
	last_updated Timestamp Not Null,
        vch_tran_id varchar(50) not null,
	status smallint Not Null,
	Constraint pk_ac_ref_ledger_id Primary Key
	( ref_ledger_id )
);

?==?
Create Table ac.ref_ledger_alloc
(	ref_ledger_alloc_id uuid Not Null,
	ref_ledger_id uuid Not Null,
	branch_id BigInt Not Null,
	affect_voucher_id Varchar(50) Not Null,
	affect_vch_tran_id Varchar(50) Not Null,
	affect_doc_date Date Not Null,
	account_id BigInt Not Null,
	net_debit_amt Numeric(18,4) Not Null,
	net_credit_amt Numeric(18,4) Not Null,
	status smallint Not Null,
	last_updated Timestamp Not Null,
	Constraint pk_ac_ref_ledger_alloc_id Primary Key
	( ref_ledger_alloc_id ),
	Constraint fk_ac_ref_ledger_alloc Foreign Key (ref_ledger_id)
	References ac.ref_ledger(ref_ledger_id)
);

?==?
create table ac.doc_reversal(
	reversal_id varchar(50),
	reversal_date date not null,	
	voucher_id varchar(50) not null,	
	doc_date date not null,
	account_id bigint not null,
	company_id bigint not null,
	branch_id bigint not null,
	collected boolean NOT NULL,
	collection_date date,
	debit_amt numeric(18,4) NOT NULL,
	credit_amt numeric(18,4) NOT NULL,
	cheque_number varchar(20) NOT NULL,
	cheque_date date NOT NULL,
	caption character varying not null,
	CONSTRAINT pk_ac_bank_reversal PRIMARY KEY (reversal_id)
)

?==?
CREATE TABLE ac.si_tran
(	sl_no smallint NOT NULL,
        si_tran_id varchar(50) Not Null,
        voucher_id varchar(50) Not Null,
        ref_id varchar(50) Not Null,
 	ref_tran_id Varchar(50) Not Null,
 	ref_date Date Not Null,
	branch_id BigInt Not Null,
 	account_id BigInt Not Null,
        hsn_sc_id bigint NOT NULL,
        CONSTRAINT pk_ac_si_tran PRIMARY KEY (si_tran_id),
        CONSTRAINT fk_ac_vch_control_si FOREIGN KEY (voucher_id)
            REFERENCES ac.vch_control (voucher_id)
);

?==?
create table ac.cash_acc_limit
(
    cash_acc_limit_id bigint not null,
  	account_id bigint NOT NULL,
	company_id bigint NOT NULL,
	last_updated timestamp without time zone NOT NULL,
	CONSTRAINT pk_cash_acc_limit PRIMARY KEY (cash_acc_limit_id)
);

?==?
create table ac.cash_acc_limit_tran
(	
    cash_acc_limit_tran_id varchar(50) not null,
    cash_acc_limit_id bigint not null,
    branch_id bigint not null,
    limit_type_id smallint not null,
    limit_val numeric(18, 4) not null,
	CONSTRAINT pk_cash_acc_limit_tran PRIMARY KEY (cash_acc_limit_tran_id),
  	CONSTRAINT fk_cash_acc_limit_tran FOREIGN KEY (cash_acc_limit_id)
      	REFERENCES ac.cash_acc_limit (cash_acc_limit_id)    
);

?==?
insert into sys.mast_seq(mast_seq_type, seed)
select 'ac.cash_acc_limit', 0;

?==?
CREATE TABLE ac.saj_tran
(
  sl_no smallint NOT NULL,
  vch_tran_id varchar(50) NOT NULL,
  voucher_id varchar(50) NOT NULL,
  branch_id bigint NOT NULL,
  account_id bigint NOT NULL,
  item_amt numeric(18,4) NOT NULL,
  debit_sub_head_id BigInt Not Null Default -1,
  credit_sub_head_id BigInt Not Null Default -1,
  remarks Varchar(2000) Not Null Default '',
  CONSTRAINT pk_ac_saj_tran PRIMARY KEY (vch_tran_id)
);

?==?
CREATE TABLE ac.rl_pl
(
  rl_pl_id uuid NOT NULL,
  company_id bigint NOT NULL,
  branch_id bigint NOT NULL,
  voucher_id varchar(50) NOT NULL,
  vch_tran_id varchar(50) NOT NULL,
  doc_date date NOT NULL,
  account_id bigint NOT NULL,
  bill_no varchar(50) NOT NULL,
  bill_date date NOT NULL,
  fc_type_id bigint NOT NULL,
  exch_rate numeric(18,6) NOT NULL,
  debit_amt_fc numeric(18,4) NOT NULL,
  credit_amt_fc numeric(18,4) NOT NULL,
  debit_amt numeric(18,4) NOT NULL,
  credit_amt numeric(18,4) NOT NULL,
  narration varchar(500) NOT NULL,
  en_bill_type smallint not null, 
  is_opbl boolean default false,
  due_date date not null,
  CONSTRAINT pk_rl_pl PRIMARY KEY (rl_pl_id),
  CONSTRAINT fk_rl_pl_account_account_head FOREIGN KEY (account_id)
      REFERENCES ac.account_head (account_id),
  CONSTRAINT uk_rl_pl UNIQUE (voucher_id, branch_id, account_id)
);

?==?
CREATE TABLE ac.rl_pl_alloc
(
  rl_pl_alloc_id uuid NOT NULL,
  rl_pl_id uuid NOT NULL,
  branch_id bigint NOT NULL,
  voucher_id varchar(50) NOT NULL,
  vch_tran_id varchar(50) NOT NULL,
  doc_date date NOT NULL,
  account_id bigint NOT NULL,
  exch_rate numeric(18,6) NOT NULL,
  debit_amt numeric(18,4) NOT NULL,
  debit_amt_fc numeric(18,4) NOT NULL,
  credit_amt numeric(18,4) NOT NULL,
  credit_amt_fc numeric(18,4) NOT NULL,
  write_off_amt numeric(18,4) NOT NULL,
  write_off_amt_fc numeric(18,4) NOT NULL,
  tds_amt numeric(18,4) not null,
  tds_amt_fc numeric(18,4) not null,
  other_exp numeric(18,4) not null,
  other_exp_fc numeric(18,4) not null,
  debit_exch_diff numeric(18,4) NOT NULL,
  credit_exch_diff numeric(18,4) NOT NULL,
  net_debit_amt numeric(18,4) NOT NULL,
  net_debit_amt_fc numeric(18,4) NOT NULL,
  net_credit_amt numeric(18,4) NOT NULL,
  net_credit_amt_fc numeric(18,4) NOT NULL,
  status bigint not null,
  tran_group Varchar(50) Not Null,
  gst_tds_amt_fc numeric(18,4) not null default 0,
  gst_tds_amt numeric(18,4) not null default 0,
  CONSTRAINT pk_rl_pl_alloc PRIMARY KEY (rl_pl_alloc_id),
  CONSTRAINT fk_rl_pl_alloc_account_account_head FOREIGN KEY (account_id)
      REFERENCES ac.account_head (account_id),
  CONSTRAINT fk_rl_pl_alloc FOREIGN KEY (rl_pl_id)
      REFERENCES ac.rl_pl (rl_pl_id)
);

?==?
create table ac.gl_reco
(
    gl_reco_id uuid,
    voucher_id varchar(50) not null,
    account_id bigint not null,
    reconciled boolean not null,
    reco_date date,
    CONSTRAINT pk_ac_gl_reco PRIMARY KEY (gl_reco_id)
);

?==?

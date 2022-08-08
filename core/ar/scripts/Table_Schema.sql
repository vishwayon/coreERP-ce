-- Create new Schema Accounts Receivable

 CREATE SCHEMA ar;
?==?

-- Table script for Customer
CREATE TABLE ar.customer
(
    customer_id bigint NOT NULL,
    customer varchar(250) NOT NULL,
    customer_code varchar(20) NOT NULL,
    control_account_id bigint NOT NULL,
    address_id bigint NOT NULL, 
    credit_limit_type smallint NOT NULL,
    credit_limit numeric(18,4) NOT NULL,
    company_id bigint NOT NULL,
    pay_term_id bigint NOT NULL,
    shipping_address_id bigint not null,
    salesman_id bigint not null,
    tax_schedule_id bigint not null,
    annex_info JsonB Not Null Default '{}',
    customer_name varchar(250) not null,
    CONSTRAINT pk_ar_customer PRIMARY KEY (customer_id),
    CONSTRAINT fk_ar_customer_account FOREIGN KEY (control_account_id)
          REFERENCES ac.account_head (account_id) MATCH SIMPLE
          ON UPDATE NO ACTION ON DELETE NO ACTION,
    CONSTRAINT fk_ar_customer_address FOREIGN KEY (address_id)
          REFERENCES sys.address (address_id) MATCH SIMPLE
          ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?

-- Table script for Customer Bank Info
CREATE TABLE ar.customer_bank_info
(
customer_bank_info_id varchar(50) NOT NULL,
customer_id bigint NOT NULL,
bank_name varchar(250) NOT NULL,
bank_branch varchar(250) NOT NULL,
address varchar(500) NOT NULL,
switch_no varchar(50) NOT NULL,
other_bank_info varchar(500) NOT NULL,
default_bank boolean NOT NULL,
CONSTRAINT pk_customer_bank_info PRIMARY KEY (customer_bank_info_id),
CONSTRAINT fk_ar_customer_bank_info_customer FOREIGN KEY (customer_id)
      REFERENCES ar.customer (customer_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
CREATE TABLE ar.income_type 
(
    income_type_id bigint NOT NULL,
    company_id bigint NOT NULL,
    income_type_name varchar(50)NOT NULL,
    apply_st boolean NOT NULL,
    seq_type varchar(20) not null,
    last_updated timestamp without time zone,
    tax_schedule_id bigint  not null,
    is_system_created boolean default false,
    is_with_estimate boolean not null default false,
    CONSTRAINT income_type_id_pkey PRIMARY KEY (income_type_id)
);

?==?

Insert into sys.mast_seq(mast_seq_type, seed)
Select 'ar.income_type', 0;
?==?

CREATE TABLE ar.income_type_tran 
(
  income_type_tran_id varchar(50) NOT NULL,
  income_type_id bigint NOT NULL,
  account_id bigint NOT NULL,
  hsn_sc_id bigint not null,
  CONSTRAINT pk_income_type_tran_id_pkey PRIMARY KEY(income_type_tran_id),
  CONSTRAINT fk_ar_income_type_tran FOREIGN KEY (income_type_id)
      REFERENCES ar.income_type  (income_type_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
create table ar.invoice_control
(	company_id bigint not null,
	doc_type varchar(20) not null,
	finyear varchar(4) not null,
	branch_id bigint not null,
	invoice_id varchar(50) not null,
	doc_date date not null,
	fc_type_id bigint not null,
	exch_rate numeric(18,6) not null,
	status smallint not null,
	customer_id bigint not null,
	income_type_id bigint not null,
	en_invoice_action smallint not null,
	narration varchar(500) not null,
	invoice_amt numeric(18,4) not null,
	invoice_amt_fc numeric(18,4) not null,
	amt_in_words varchar(250) not null,
	amt_in_words_fc varchar(250) not null,
	remarks varchar(500) not null,
	last_updated timestamp not null,       
        po_no varchar(50) not null, 
        po_date date not null,
        salesman_id bigint not null,
        trigger_id varchar(50) not null,
        invoice_address text not null,
        is_dispatched boolean not null,
        dispatched_date date null,
        dispatch_method smallint not null,
        annex_info JsonB Not Null Default ('{}'),
        vat_type_id bigint not null,
        dispatch_remark varchar(500) not null default '',
	constraint pk_ar_invoice_control PRIMARY KEY (invoice_id)
);

?==?
create table ar.invoice_tran
(	sl_no smallint not null,
	invoice_id varchar(50) not null,
	invoice_tran_id varchar(50) not null,
	account_id bigint not null,
	credit_amt numeric(18,4) not null,
	credit_amt_fc numeric(18,4) not null,
	description varchar(250) not null,
        tax_amt numeric(18,4) not null default 0,
	constraint pk_ar_invoice_tran PRIMARY KEY (invoice_tran_id),
	CONSTRAINT fk_ar_invoice_control_invoice_tran FOREIGN KEY (invoice_id)
	REFERENCES ar.invoice_control (invoice_id)
);

?==?
create table ar.rcpt_control
(
	company_id bigint NOT NULL,
	doc_type varchar(20) NOT NULL,
	finyear character varying(4) NOT NULL,
	branch_id bigint NOT NULL,
	voucher_id varchar(50) NOT NULL,
	doc_date date NOT NULL,
	fc_type_id bigint NOT NULL,
	exch_rate numeric(18,6) NOT NULL,
	received_from varchar(100) not null, 
	rcpt_type smallint not null,
	account_id bigint not null,
	customer_account_id bigint not null,
	debit_amt numeric(18,4) not null,
	debit_amt_fc numeric(18,4) not null,
	cheque_number varchar(20) NOT NULL,
	cheque_date date NOT NULL,
	collected boolean NOT NULL,
	collection_date date,
	cheque_bank character varying(100) NOT NULL,
	cheque_branch character varying(50) NOT NULL,
        en_rcpt_action smallint not null,
	narration character varying(500) NOT NULL,
	status smallint NOT NULL,
	amt_in_words character varying(250) NOT NULL,
	amt_in_words_fc character varying(250) NOT NULL,
	remarks character varying(500) NOT NULL,
        trigger_id varchar(50) not null,
        is_inter_branch boolean NOT NULL,
        net_settled_fc numeric(18,4) not null,
        net_settled numeric(18,4) not null,
        adv_amt numeric(18,4) not null,
        adv_amt_fc numeric(18,4) not null,
        tds_amt numeric(18,4) Not Null,
        tds_amt_fc numeric(18,4) Not Null,
        target_branch_id bigint not null, 
        annex_info jsonb not null, 
        is_reversed boolean not null default false,
        reversal_date date null,
        reversal_comments text not null,
        is_pdc boolean not null default false,
	last_updated timestamp without time zone NOT NULL,
	constraint pk_ar_rcpt_control primary key (voucher_id)
);

?==?
CREATE TABLE ar.salesman
(
	salesman_id bigint NOT NULL,
	company_id bigint NOT NULL,
	salesman_name varchar(50) NOT NULL,
	salesman_type smallint NOT NULL,
        parent_salesman_id bigint not null,
        address_id bigint not null,
        user_id bigint not null default -1,
        annex_info jsonb not null default '{}',
	last_updated timestamp without time zone NOT NULL,
	CONSTRAINT pk_ar_salesman PRIMARY KEY (salesman_id)
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'ar.salesman', 0;

?==?
create table ar.segment
(
	segment_id bigint not null,
	company_id bigint not null,
	segment varchar(50) not null,
	segment_desc varchar(250) not null,
	last_updated timestamp default current_timestamp,
	constraint pk_ar_segment primary key (segment_id)
);

?==?
Insert into ar.segment(segment_id, company_id, segment, segment_desc, last_updated)
values (({company_id}*1000000) + 1, {company_id}, 'Others', 'Others', current_timestamp(0)); 

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'ar.segment', 1;

?==?
CREATE TABLE ar.rcpt_tran
(
  sl_no smallint NOT NULL,
  vch_tran_id varchar(50) NOT NULL,
  voucher_id varchar(50) NOT NULL,
  dc character(1) NOT NULL,
  account_id bigint NOT NULL,
  debit_amt_fc numeric(18,4) NOT NULL,
  credit_amt_fc numeric(18,4) NOT NULL,
  debit_amt numeric(18,4) NOT NULL,
  credit_amt numeric(18,4) NOT NULL,
  description varchar(2000) not null default '',
  reference_id varchar(50) not null default '',
  reference_tran_id varchar(50) not null default '',
  hsn_sc_id bigint not null default -1,
  tax_amt numeric(18, 4) not null default 0,
  CONSTRAINT pk_ar_rcpt_tran PRIMARY KEY (vch_tran_id),
  CONSTRAINT fk_ar_rcpt_control_tran FOREIGN KEY (voucher_id)
      REFERENCES ar.rcpt_control (voucher_id) 
);

?==? 
CREATE TABLE ar.tds_reconciled
(
  company_id bigint not null,
  customer_id bigint NOT NULL,
  branch_id bigint NOT NULL,
  voucher_id varchar(50) NOT NULL,
  doc_date date not null,
  tds_amt numeric(18,4) NOT NULL,
  tds_amt_fc numeric(18,4) NOT NULL,
  reconciled boolean NOT NULL,
  reco_date date NULL,
  last_updated timestamp without time zone NOT NULL,
  CONSTRAINT pk_ar_tds_reconciled primary key (voucher_id)
);

?==?
create table ar.bal_transfer_tran
(
    rl_pl_alloc_id uuid NOT NULL,
    voucher_id varchar(50) not null,
    target_branch_id bigint not null,
    tran_group Varchar(50) Not Null,
    constraint pk_bal_transfer_tran  PRIMARY KEY (rl_pl_alloc_id)
);

?==?
create table ar.rcpt_adv_tran
(
  sl_no smallint NOT NULL,
  vch_tran_id varchar(50) NOT NULL,
  voucher_id varchar(50) NOT NULL,
  vat_type_id bigint not null,
  account_id bigint NOT NULL,
  customer_state_id bigint not null,
  branch_id bigint NOT NULL,
  adv_amt numeric(18,4) NOT NULL,
  adv_amt_fc numeric(18,4) NOT NULL,    
  CONSTRAINT pk_ar_rcpt_adv_tran PRIMARY KEY (vch_tran_id),
  CONSTRAINT fk_ar_rcpt_adv_tran FOREIGN KEY (voucher_id)
      REFERENCES ar.rcpt_control (voucher_id) 
);

?==?

create table ar.rcpt_sel_acc_tran
(
  sl_no smallint NOT NULL,
  vch_tran_id varchar(50) NOT NULL,
  voucher_id varchar(50) NOT NULL,
  account_id bigint NOT NULL,
  sel_amt numeric(18,4) NOT NULL,
  sel_amt_fc numeric(18,4) NOT NULL,    
  CONSTRAINT pk_ar_rcpt_sel_acc_tran PRIMARY KEY (vch_tran_id),
  CONSTRAINT fk_ar_rcpt_sel_acc_tran FOREIGN KEY (voucher_id)
      REFERENCES ar.rcpt_control (voucher_id) 
);

?==?
insert into sys.settings(key, value)
Select 'ar_inv_dispatch_remark_reqd', '0';

?==?
CREATE TABLE ar.mcr_summary_tran
(
  sl_no smallint NOT NULL,
  vch_tran_id varchar(50) NOT NULL,
  voucher_id varchar(50) NOT NULL,
  account_id bigint NOT NULL,
  receivable_amt numeric(18,4) NOT NULL,
  amt_in_words character varying not null,
  CONSTRAINT pk_ar_mcr_summary_tran PRIMARY KEY (vch_tran_id)
);

?==?
insert into sys.settings
Select 'invoice_rf_to', 0;

?==?
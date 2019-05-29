-- Add new schema for Accounts Payable

CREATE SCHEMA ap;

?==?
-- Table scripts For Bill Control
CREATE TABLE ap.bill_control
(
	company_id bigint NOT NULL,
	doc_type varchar(20) NOT NULL,
	finyear varchar(4) NOT NULL,
	branch_id bigint NOT NULL,
	bill_id varchar(50) NOT NULL,
	doc_date date NOT NULL,
	fc_type_id bigint NOT NULL,
	exch_rate numeric(18,6) NOT NULL,
	status smallint NOT NULL,
	supplier_id bigint NOT NULL,
	bill_no varchar(50) NOT NULL,
	bill_date date NOT NULL,
	bill_amt numeric(18,4) NOT NULL,
	bill_amt_fc numeric(18,4) NOT NULL,
        round_off_amt numeric(18,4) not null,
        round_off_amt_fc numeric(18,4) not null,
        due_date date not null,
	en_bill_action smallint NOT NULL,
	narration varchar(500) NOT NULL,
	amt_in_words varchar(250) NOT NULL,
	amt_in_words_fc varchar(250) NOT NULL,
	remarks varchar(500) NOT NULL,
        annex_info JsonB Not Null Default ('{}'),
        vat_type_id bigint not null,
        doc_stage_id character varying(50) NOT NULL Default '',
        doc_stage_status smallint NOT NULL Default 0,
	last_updated timestamp without time zone NOT NULL,
	CONSTRAINT pk_ap_bill_control PRIMARY KEY (bill_id)
);

?==?
-- Table script For Bill Tran
CREATE TABLE ap.bill_tran
(
	sl_no smallint NOT NULL,
	bill_id varchar(50) NOT NULL,
	bill_tran_id varchar(50) NOT NULL,
	account_id bigint NOT NULL,
	debit_amt numeric(18,4) NOT NULL,
	debit_amt_fc numeric(18,4) NOT NULL,
	description varchar(250) NOT NULL,
	ref_id varchar(50) not null,
        ref_tran_id varchar(50) not null,
        ref_date date not null,
        hsn_sc_id bigint not null,
        business_unit_id bigint not null,
        task_ref_no varchar(50) not null default '',
        tax_amt numeric(18,4) not null default 0,
        branch_id bigint not null default -1,
	CONSTRAINT pk_ap_bill_tran PRIMARY KEY (bill_tran_id),
	CONSTRAINT fk_ap_bill_tran_bill_control FOREIGN KEY (bill_id)
		REFERENCES ap.bill_control (bill_id) MATCH SIMPLE
		ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
CREATE TABLE ap.supplier
(
  supplier_id bigint NOT NULL,
  supplier varchar(250) NOT NULL,
  supplier_code varchar(20) NOT NULL,
  address_id bigint NOT NULL DEFAULT (-1),
  credit_limit_type smallint NOT NULL DEFAULT 0,
  credit_limit numeric(18,4) NOT NULL DEFAULT 0,
  company_id bigint NOT NULL DEFAULT (-1),
  control_account_id bigint NOT NULL DEFAULT (-1),
  pay_term_id bigint not null, 
  annex_info JsonB Not Null Default '{}',
  supplier_name varchar(250) not null,
  CONSTRAINT supplier_pkey PRIMARY KEY (supplier_id),
  CONSTRAINT fk_supplier_account FOREIGN KEY (control_account_id)
      REFERENCES ac.account_head (account_id),
  CONSTRAINT fk_supplier_address FOREIGN KEY (address_id)
      REFERENCES sys.address (address_id)
);

?==?
CREATE TABLE ap.supplier_tax_info
(
    supplier_tax_info_id varchar(50) NOT NULL,
    supplier_id bigint NOT NULL DEFAULT (-1),
    tds_person_type_id bigint NOT NULL DEFAULT (-1),
    tds_section_id bigint NOT NULL DEFAULT (-1),
    is_tds_applied boolean NOT NULL DEFAULT false,
    tan varchar(50) NOT NULL,
    pan varchar(50) NOT NULL,
    tax_schedule_id bigint  not null,
    CONSTRAINT supplier_tax_info_pkey PRIMARY KEY (supplier_tax_info_id),
    CONSTRAINT fk_supplier_tax_info_supplier FOREIGN KEY (supplier_id)
        REFERENCES ap.supplier (supplier_id)
);

?==?
create table ap.pymt_control
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
	pymt_type smallint not null,
	account_id bigint not null,
	supplier_account_id bigint not null,
	credit_amt numeric(18,4) not null,
	credit_amt_fc numeric(18,4) not null,
        gross_adv_amt numeric(18,4) not null,
        gross_adv_amt_fc numeric(18,4) not null,
	cheque_number varchar(20) NOT NULL,
	cheque_date date,
	collected boolean NOT NULL,
	collection_date date,
	cheque_bank character varying(50) NOT NULL,
	cheque_branch character varying(50) NOT NULL,
	narration character varying(500) NOT NULL,
	status smallint NOT NULL,
	amt_in_words character varying(250) NOT NULL,
	amt_in_words_fc character varying(250) NOT NULL,
	remarks character varying(500) NOT NULL,
	form_type smallint NOT NULL,
        is_inter_branch boolean NOT NULL,
        is_ac_payee boolean not null,
        is_non_negotiable boolean not null,
        target_branch_id bigint not null,
        supplier_detail varchar(250) not null,
        annex_info jsonb not null,
        is_reversed boolean not null default false,
        reversal_date date null,
        reversal_comments text not null,
        is_pdc boolean not null default false,
	last_updated timestamp without time zone NOT NULL,
	constraint pk_ap_pymt_control primary key (voucher_id)
);

?==?
create table ap.supp_type
(
	supp_type_id bigint not null,
	company_id bigint not null,
	supp_type varchar(50) not null,
	supp_type_desc varchar(500) not null,
	last_updated timestamp default current_timestamp,
	constraint pk_ap_supp_type primary key (supp_type_id)
);

?==?
Insert into ap.supp_type(supp_type_id, company_id, supp_type, supp_type_desc, last_updated)
values (({company_id}*1000000) + 1, {company_id}, 'Others', 'Others', current_timestamp(0)); 

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'ap.supp_type', 1;

?==?
CREATE TABLE ap.bill_lc_tran
(       bill_id varchar(50) NOT NULL,
	bill_lc_tran_id varchar(50) NOT NULL,
	account_id bigint NOT NULL,
	debit_amt_fc numeric(18,4) NOT NULL,
	debit_amt numeric(18,4) NOT NULL,
	CONSTRAINT pk_ap_bill_lc_tran PRIMARY KEY (bill_lc_tran_id),
	CONSTRAINT fk_ap_bill_lc_tran_ap_bill_control FOREIGN KEY (bill_id)
            REFERENCES ap.bill_control (bill_id) MATCH SIMPLE
            ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
CREATE TABLE ap.pymt_tran
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
  annex_info jsonb not null,
  description varchar(2000) not null default '',
  reference_id varchar(50) not null default '',
  reference_tran_id varchar(50) not null default '',
  hsn_sc_id bigint not null default -1,
  vch_date date null,
  CONSTRAINT pk_ap_pymt_tran PRIMARY KEY (vch_tran_id),
  CONSTRAINT fk_ap_pymt_control_tran FOREIGN KEY (voucher_id)
      REFERENCES ap.pymt_control (voucher_id) 
);

?==?
create table ap.bal_transfer_tran
(
    rl_pl_alloc_id uuid NOT NULL,
    voucher_id varchar(50) not null,
    target_branch_id bigint not null,
    tran_group Varchar(50) Not Null,
    CONSTRAINT pk_bal_transfer_tran PRIMARY KEY (rl_pl_alloc_id)
);

?==?
insert into sys.settings
values ('ap_bill_gtt_ovrd', '0');

?==?
Insert into sys.settings values
('ap_gstbill_po_select_visible','1')

?==?
create table ap.adv_type
(
    company_id bigint NOT NULL,
    adv_type_id bigint not null,
    adv_type Varchar(20) Not Null,
    last_updated timestamp not null,
    CONSTRAINT pk_adv_type PRIMARY KEY (adv_type_id)
);

?==?
insert into sys.mast_seq
select 'ap.adv_type', 0;

?==?
create table ap.pay_cycle
(
    company_id bigint NOT NULL,
    pay_cycle_id bigint not null,
    pay_cycle Varchar(20) Not Null,
    last_updated timestamp not null,
    CONSTRAINT pk_pay_cycle PRIMARY KEY (pay_cycle_id)
);

?==?
insert into sys.mast_seq
select 'ap.pay_cycle', 0;

?==?
create table ap.supp_cust
(
    supplier_id bigint,
    customer_id bigint not null default -1,
    Constraint pk_ap_supp_cust PRIMARY KEY (supplier_id),
    Constraint uk_ap_supp_cust Unique (customer_id)
);

?==?

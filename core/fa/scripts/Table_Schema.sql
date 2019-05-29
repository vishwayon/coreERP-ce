--
CREATE SCHEMA fa;

?==?
CREATE TABLE fa.asset_book
(
  asset_book_id bigint NOT NULL,
  company_id bigint NOT NULL,
  asset_book_desc varchar(50) NOT NULL,
  last_updated timestamp NOT NULL,
  is_accounting_asset_book boolean NOT NULL DEFAULT false,
  CONSTRAINT pk_fa_asset_book PRIMARY KEY (asset_book_id),
  CONSTRAINT uk_fa_asset_book UNIQUE (company_id, asset_book_desc)
);

?==?
INSERT INTO fa.asset_book (asset_book_id, company_id, asset_book_desc, last_updated, is_accounting_asset_book)
VALUES({company_id}*1000001, {company_id}, 'Accounting Asset Book', current_timestamp(0), true);

?==?
INSERT INTO sys.mast_seq(mast_seq_type, seed) 
VALUES ('fa.asset_book', 0);

?==?
CREATE TABLE fa.asset_class
(
  asset_class_id bigint NOT NULL,
  company_id bigint NOT NULL,
  asset_class_code varchar(4) NOT NULL,
  asset_class varchar(250) NOT NULL,
  asset_account_id bigint NOT NULL,
  dep_account_id bigint NOT NULL,
  acc_dep_account_id bigint NOT NULL,
  profit_loss_account_id bigint NOT NULL,
  last_updated timestamp without time zone NOT NULL,
  en_asset_type smallint NOT NULL,
  CONSTRAINT pk_fa_asset_class PRIMARY KEY (asset_class_id),
  CONSTRAINT uk_fa_asset_class UNIQUE (company_id, asset_class)
);

?==?
CREATE TABLE fa.asset_class_book
(
  asset_class_book_id varchar(50) NOT NULL,
  asset_class_id bigint NOT NULL,
  asset_book_id bigint NOT NULL,
  en_dep_method smallint NOT NULL,
  dep_rate numeric(6,2) NOT NULL,
  CONSTRAINT pk_fa_asset_class_book PRIMARY KEY (asset_class_book_id),
  CONSTRAINT fk_fa_asset_book_asset_class_book FOREIGN KEY (asset_book_id)
      REFERENCES fa.asset_book (asset_book_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_fa_asset_class_asset_class_book FOREIGN KEY (asset_class_id)
      REFERENCES fa.asset_class (asset_class_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT uk_fa_asset_class_book UNIQUE (asset_class_id, asset_book_id)
);

?==?
INSERT INTO sys.mast_seq(mast_seq_type, seed) 
VALUES ('fa.asset_class', 0);

?==?
CREATE TABLE fa.asset_location
(
  asset_location_id bigint NOT NULL,
  company_id bigint NOT NULL,
  branch_id bigint NOT NULL,
  asset_location varchar(150) NOT NULL,
  last_updated timestamp NOT NULL,
  asset_location_code varchar(4) NOT NULL,
  CONSTRAINT pk_fa_asset_location PRIMARY KEY (asset_location_id),
  CONSTRAINT uk_fa_asset_location UNIQUE (branch_id, asset_location)
);

?==?
INSERT INTO sys.mast_seq(mast_seq_type, seed) 
VALUES ('fa.asset_location', 0);

?==?
CREATE TABLE fa.asset_item
(
  asset_item_id bigint NOT NULL,
  company_id bigint NOT NULL,
  branch_id bigint NOT NULL,
  asset_class_id bigint NOT NULL,
  asset_code varchar(50) NOT NULL,
  asset_name varchar(250) NOT NULL,
  asset_desc varchar(500) Not Null,
  purchase_date date NOT NULL,
  use_start_date date NOT NULL,
  purchase_amt numeric(18,4) NOT NULL,
  asset_location_id bigint NOT NULL,
  asset_qty bigint NOT NULL,
  voucher_id varchar(50) NOT NULL,
  voucher_tran_id varchar(50) NOT NULL,
  project_id varchar(50) NOT NULL,
  last_updated timestamp without time zone NOT NULL,
  disposed_on date,
  cost_head_id bigint NOT NULL,
  CONSTRAINT asset_item_pkey PRIMARY KEY (asset_item_id)
);

?==?
insert into sys.mast_seq(mast_seq_type, seed)
select 'fa.asset_item', 0


?==?
CREATE TABLE fa.asset_item_ledger
(
  asset_item_ledger_id uuid NOT NULL,
  asset_item_id bigint NOT NULL,
  asset_book_id bigint NOT NULL,
  voucher_date date NOT NULL,
  voucher_id character varying(50) NOT NULL,
  en_asset_tran_type smallint NOT NULL,
  asset_tran_amt numeric(18,4) NOT NULL,
  narration varchar(500) NOT NULL,
  CONSTRAINT asset_item_ledger_pkey PRIMARY KEY (asset_item_ledger_id),
  CONSTRAINT fk_asset_book_asset_item_ledger FOREIGN KEY (asset_book_id)
      REFERENCES fa.asset_book (asset_book_id),
  CONSTRAINT fk_asset_item_asset_item_ledger FOREIGN KEY (asset_item_id)
      REFERENCES fa.asset_item (asset_item_id) 
);

?==?
CREATE TABLE fa.as_control
(
  as_id varchar(50) NOT NULL,
  company_id bigint NOT NULL,
  branch_id bigint NOT NULL,
  doc_type varchar(4) NOT NULL,
  finyear varchar(4) NOT NULL,
  doc_month varchar(4) NOT NULL,
  v_id bigint NOT NULL,
  doc_date date NOT NULL,
  en_sales_type smallint NOT NULL,
  customer_id bigint NOT NULL,
  gross_debit_amt_fc numeric(18,4) NOT NULL,
  advance_amt_fc numeric(18,4) NOT NULL,
  net_debit_amt_fc numeric(18,4) NOT NULL,
  fc_type_id bigint NOT NULL,
  exch_rate numeric(18,6) NOT NULL,
  gross_debit_amt numeric(18,4) NOT NULL,
  debit_amt numeric(18,4) NOT NULL,
  debit_amt_fc numeric(18,4) NOT NULL,
  advance_amt numeric(18,4) NOT NULL,
  net_debit_amt numeric(18,4) NOT NULL,
  status smallint NOT NULL,
  cheque_number varchar(20) NOT NULL,
  cheque_date date,
  en_tax_type smallint NOT NULL,
  narration varchar(500) NOT NULL,
  remarks varchar(500) NOT NULL,
  amt_in_words varchar(250) NOT NULL,
  amt_in_words_fc varchar(250) NOT NULL,
  collected boolean NOT NULL,
  collection_date date,
  form_type smallint NOT NULL,
  last_updated timestamp without time zone NOT NULL,
  doc_object_id bigint NOT NULL,
  asset_class_id bigint NOT NULL,
  pay_day smallint NOT NULL DEFAULT 0,
  annex_info jsonb not null default '{}',
  CONSTRAINT as_control_pkey PRIMARY KEY (as_id)
);

?==?
CREATE TABLE fa.as_tran
(
  as_id varchar(50) NOT NULL,
  as_tran_id varchar(50) NOT NULL,
  sl_no smallint NOT NULL,
  asset_item_id bigint NOT NULL,
  credit_amt_fc numeric(18,4) NOT NULL,
  credit_amt numeric(18,4) NOT NULL,
  purchase_amt numeric(18,4) NOT NULL,
  tax_amt numeric(18, 4) not null default 0,
  CONSTRAINT as_tran_pkey PRIMARY KEY (as_tran_id),
  CONSTRAINT fk_as_tran_as_control FOREIGN KEY (as_id) REFERENCES fa.as_control (as_id)
);

?==?
CREATE TABLE fa.as_book_tran
(
  as_id varchar(50) NOT NULL,
  as_book_tran_id varchar(50) NOT NULL,
  asset_item_id bigint NOT NULL,
  asset_book_id bigint NOT NULL,
  dep_amt numeric(18,4) NOT NULL,
  acc_dep_amt numeric(18,4) NOT NULL,
  profit_loss_amt numeric(18,4) NOT NULL,
  dep_date_from date NOT NULL,
  CONSTRAINT pk_fa_as_book_tran PRIMARY KEY (as_book_tran_id),
  CONSTRAINT fk_fa_as_book_tran_as_control FOREIGN KEY (as_id) REFERENCES fa.as_control (as_id)
)

?==?
CREATE TABLE fa.ap_control
(
    company_id bigint NOT NULL,
    doc_type varchar(4) NOT NULL,
    finyear varchar(4) NOT NULL,
    branch_id bigint NOT NULL,
    ap_id varchar(50) NOT NULL,
    doc_date date NOT NULL,
    en_purchase_type smallint NOT NULL DEFAULT 0,
    account_id bigint NOT NULL,
    bill_date date,
    bill_no varchar(50) NOT NULL,
    project_id varchar(50) NOT NULL,
    fc_type_id bigint NOT NULL DEFAULT 0,
    exch_rate numeric(18,6) NOT NULL DEFAULT 1,
    disc_pcnt numeric(18,4) NOT NULL,
    disc_is_value boolean NOT NULL DEFAULT false,
    gross_credit_amt numeric(18,4) NOT NULL,
    gross_credit_amt_fc numeric(18,4) NOT NULL,
    disc_amt numeric(18,4) NOT NULL,
    disc_amt_fc numeric(18,4) NOT NULL,
    credit_amt numeric(18,4) NOT NULL,
    credit_amt_fc numeric(18,4) NOT NULL,
    advance_amt numeric(18,4) NOT NULL,
    advance_amt_fc numeric(18,4) NOT NULL,
    net_credit_amt numeric(18,4) NOT NULL,
    net_credit_amt_fc numeric(18,4) NOT NULL,
    status smallint NOT NULL,
    cheque_number varchar(20) NOT NULL,
    cheque_date date,
    en_tax_type smallint NOT NULL,
    narration varchar(500) NOT NULL,
    remarks varchar(500) NOT NULL,
    amt_in_words varchar(250) NOT NULL,
    amt_in_words_fc varchar(250) NOT NULL,
    collected boolean NOT NULL DEFAULT false,
    collection_date date,
    last_updated timestamp without time zone NOT NULL DEFAULT ('now'::text)::date,
    pay_day smallint NOT NULL DEFAULT 0,
    round_off_amt numeric(18,4) NOT NULL,
    round_off_amt_fc numeric(18,4) NOT NULL,
    annex_info jsonb not null default '{}',
    doc_stage_id varchar(50) not null default '',
    doc_stage_status smallint not null default 0,
    is_closed boolean not null default false,
    closed_on date null,
    closed_by varchar(50) not null default '',
    closed_reason varchar(250) not null default '',
    CONSTRAINT pk_fa_ap_control PRIMARY KEY (ap_id)
);

?==?
CREATE TABLE fa.ap_tran
(
  ap_id varchar(50) NOT NULL,
  ap_tran_id varchar(50) NOT NULL,
  sl_no smallint NOT NULL,
  asset_class_id bigint NOT NULL,
  asset_code varchar(50) NOT NULL,
  asset_name varchar(250) NOT NULL,
  sub_class_id bigint NOT NULL,
  use_start_date date NOT NULL,
  purchase_amt numeric(18,4) NOT NULL,
  purchase_amt_fc numeric(18,4) NOT NULL,
  asset_location_id bigint NOT NULL,
  asset_qty bigint NOT NULL,
  po_id varchar(50) NOT NULL,
  po_tran_id varchar(50) NOT NULL,
  original_cost numeric(18,4) NOT NULL,
  original_purchase_date date,
  cost_head_id bigint NOT NULL DEFAULT (-1),
  hsn_sc_id bigint not null,
  asset_desc text not null,
  bt_amt numeric(18,4) not null default 0,
  tax_amt numeric(18,4) not null default 0,
  asset_uom varchar(50) not null default '',
  asset_rate numeric(18,4) not null default 0,
  CONSTRAINT pk_fa_ap_tran PRIMARY KEY (ap_tran_id),
  CONSTRAINT fk_fa_ap_control_ap_tran FOREIGN KEY (ap_id)
      REFERENCES fa.ap_control (ap_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
CREATE TABLE fa.ap_lc_tran
(
    ap_id varchar(50) NOT NULL,
    ap_lc_tran_id varchar(50) NOT NULL,	
    is_taxable boolean NOT NULL,
    supplier_paid boolean not null,
    account_affected_id bigint NOT NULL DEFAULT 0,
    debit_amt_fc numeric(18,4) NOT NULL DEFAULT 0,
    exch_rate numeric(18,6) NOT NULL DEFAULT 1,
    debit_amt numeric(18,4) NOT NULL DEFAULT 0,
    bill_no varchar(50) NOT NULL DEFAULT ''::varchar,
    bill_date date,
  CONSTRAINT pk_fa_ap_lc_tran PRIMARY KEY (ap_lc_tran_id),
  CONSTRAINT fk_fa_ap_control_ap_lc_tran FOREIGN KEY (ap_id)
      REFERENCES fa.ap_control (ap_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
CREATE TABLE fa.ad_control
(
  company_id bigint NOT NULL,
  doc_type varchar(4) NOT NULL,
  finyear varchar(4) NOT NULL,
  branch_id bigint NOT NULL,
  ad_id varchar(50) NOT NULL,
  doc_date date NOT NULL,
  dep_date_from date NOT NULL,
  dep_date_to date NOT NULL,
  status smallint NOT NULL,
  narration varchar(500) NOT NULL,
  remarks varchar(500) NOT NULL,
  amt_in_words varchar(250) NOT NULL,
  last_updated timestamp NOT NULL,
  CONSTRAINT pk_fa_ad_control PRIMARY KEY (ad_id)
);

?==?
CREATE TABLE fa.ad_tran
(
  ad_tran_id varchar(50) NOT NULL,
  ad_id varchar(50) NOT NULL,
  sl_no smallint NOT NULL,
  asset_class_id bigint NOT NULL,
  asset_book_id bigint NOT NULL,
  dep_account_id bigint NOT NULL,
  acc_dep_account_id bigint NOT NULL,
  dep_amt numeric(18,4) NOT NULL,
  CONSTRAINT pk_fa_ad_tran PRIMARY KEY (ad_tran_id),
  CONSTRAINT fk_ad_tran_ad_control FOREIGN KEY (ad_id)
      REFERENCES fa.ad_control (ad_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_ad_tran_asset_book FOREIGN KEY (asset_book_id)
      REFERENCES fa.asset_book (asset_book_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_ad_tran_asset_class FOREIGN KEY (asset_class_id)
      REFERENCES fa.asset_class (asset_class_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT uk_adtran UNIQUE (ad_id, asset_class_id, asset_book_id)
);

?==?
CREATE TABLE fa.asset_dep_ledger
(
  asset_dep_ledger_id varchar(50) not null,
  asset_item_id bigint NOT NULL,
  asset_class_id bigint NOT NULL,
  asset_book_id bigint NOT NULL,
  company_id bigint NOT NULL,
  finyear varchar(4) NOT NULL,
  branch_id bigint NOT NULL,
  voucher_id varchar(50) NOT NULL,
  doc_date date NOT NULL,
  dep_date_from date NOT NULL,
  dep_date_to date NOT NULL,
  dep_amt numeric(18,4) NOT NULL,
  status smallint NOT NULL,
  narration varchar(500) NOT NULL,
  is_terminal boolean NOT NULL,
  CONSTRAINT pk_fa_asset_dep_ledger PRIMARY KEY (asset_dep_ledger_id)
);

?==?
Create Table fa.sub_class
(
    sub_class_id bigint not null,
    asset_class_id bigint not null,
    company_id bigint not null,
    sub_class_desc character varying(250) not null,
    last_updated timestamp without time zone not null,
    Constraint pk_fa_sub_class Primary key (sub_class_id),
    Constraint fk_fa_sub_class_asset_class Foreign key (asset_class_id)
	References fa.asset_class(asset_class_id)
);

?==?
Insert into sys.mast_seq(mast_seq_type, seed)
Select 'fa.sub_class', 0;

?==?
insert into sys.settings
values('fa_ap_without_po', '1');

?==?

  
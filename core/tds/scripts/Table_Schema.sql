-- Add new schema for Tax Deducted at Source

CREATE SCHEMA tds;
?==?

CREATE foreign TABLE tds.person_type
(
  person_type_id bigint NOT NULL,
  person_type_desc varchar(50) NOT NULL,
  last_updated timestamp without time zone NOT NULL DEFAULT current_timestamp(0),
  company_id bigint NOT NULL
)
server {dbMain} options(table_name 'person_type' );

?==?

-- Table script for section
CREATE foreign TABLE tds.section
(
    section_id bigint NOT NULL,
    section varchar(50) NOT NULL,
    section_code varchar(3) not null,
    section_desc varchar(250) NOT NULL,
    tds_account_id bigint NOT NULL,
    company_id bigint NOT NULL,
    last_updated timestamp without time zone NOT NULL DEFAULT current_timestamp(0)
)
server {dbMain} options(table_name 'section' );

?==?

create table tds.section_acc
(
    section_id bigint not null,
    tds_account_id bigint NOT NULL,
    CONSTRAINT pk_tds_section PRIMARY KEY (section_id)
);
?==?

-- Table script for Rate
CREATE foreign TABLE tds.rate
(
  rate_id bigint NOT NULL,
  section_id bigint NOT NULL,
  person_type_id bigint NOT NULL,
  base_rate_perc numeric(18,4) NOT NULL,
  ecess_perc numeric(18,4) NOT NULL,
  surcharge_perc numeric(18,4) NOT NULL,
  effective_from date NOT NULL DEFAULT current_timestamp(0),
  company_id bigint NOT NULL DEFAULT (-1),
  en_round_type smallint not null,
  last_updated timestamp NOT NULL DEFAULT current_timestamp(0)
)
server {dbMain} options(table_name 'rate' ); 

?==?

-- Table script for Deductor Info
CREATE TABLE tds.deductor_info
(
    deductor_info_id bigint NOT NULL,
    deductor_name varchar(75) NOT NULL,
    tan varchar(10) NOT NULL,
    pan varchar(10) NOT NULL,
    deductor_type_id bigint not null,
    branch_division varchar(75) NOT NULL,
    flat_no varchar(25) NOT NULL,
    building_premises varchar(25) NOT NULL, 
    area_location varchar(25) NOT NULL, 
    road_street_lane varchar(25) NOT NULL,
    town_city_district varchar(25) NOT NULL,
    state_id bigint not null,
    pin_code varchar(6) NOT NULL,
    std_code varchar(5) NOT NULL,
    telephone_no varchar(10) NOT NULL,
    std_code_alternate varchar(5) NOT NULL,
    telephone_no_alternate varchar(10) NOT NULL,
    email varchar(75) NOT NULL,
    email_alternate varchar(75) NOT NULL,
    tan_registration_no  varchar(12) NOT NULL,
    p_deductor_name  varchar(75) NOT NULL,
    p_designation varchar(20) NOT NULL,
    p_flat_no  varchar(25) NOT NULL,
    p_building_premises  varchar(25) NOT NULL,
    p_area_location varchar(25) NOT NULL, 
    p_road_street_lane  varchar(25) NOT NULL,
    p_town_city_district varchar(25) NOT NULL,
    p_state_id bigint not null,
    p_pin_code varchar(6) NOT NULL,
    p_std_code varchar(5) NOT NULL,
    p_telephone_no varchar(10) NOT NULL,
    p_std_code_alternate varchar(5) NOT NULL,
    p_telephone_no_alternate varchar(10) NOT NULL,
    p_mobile_no varchar(10) NOT NULL,
    p_email varchar(75) NOT NULL,
    p_email_alternate varchar(75) NOT NULL,
    company_id bigint NOT NULL,
    last_updated timestamp without time zone NOT NULL,
    CONSTRAINT pk_tds_deductor_info PRIMARY KEY (deductor_info_id)
);

?==?
Insert into tds.deductor_info(
    deductor_info_id, deductor_name, tan, pan, deductor_type_id, branch_division, flat_no, building_premises, area_location, road_street_lane,
    town_city_district, state_id, pin_code, std_code, telephone_no, std_code_alternate, telephone_no_alternate, email, email_alternate, tan_registration_no,
    p_deductor_name, p_designation, p_flat_no, p_building_premises, p_area_location, p_road_street_lane, p_town_city_district, p_state_id, p_pin_code, 
    p_std_code, p_telephone_no, p_std_code_alternate, p_telephone_no_alternate, p_mobile_no, p_email, p_email_alternate, company_id, last_updated)
Select ({company_id}*1000000) + 1, '', '', '', -1, '', '', '', '', '',
	'', -1, '', '', '', '', '', '', '', '',
	'', '', '', '', '', '', '', -1, '',
	'', '', '', '', '', '', '', {company_id}, current_timestamp(0);

?==?
INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('tds.deductor_info', 0);

?==?-- Table script For Bill TDS Tran
CREATE TABLE tds.bill_tds_tran
(
	company_id bigint NOT NULL,
	branch_id bigint NOT NULL,
	voucher_id varchar(50) NOT NULL,
	bill_tds_tran_id varchar(50) NOT NULL,
	person_type_id bigint NOT NULL,
	section_id bigint NOT NULL,
	bill_amt numeric(18,4) not null,
	bill_amt_fc numeric(18,4) not null,
	doc_date date not null,
	supplier_id bigint not null,
	certificate_number varchar(10) not null,
        amt_for_tds numeric(18,4) not null,
	tds_base_rate_perc numeric(18,4) NOT NULL,
	tds_base_rate_amt numeric(18,4) NOT NULL,
        tds_base_rate_amt_fc numeric(18,4) NOT NULL,
	tds_ecess_perc numeric(18,4) NOT NULL,
	tds_ecess_amt numeric(18,4) NOT NULL,
        tds_ecess_amt_fc numeric(18,4) NOT NULL,
	tds_surcharge_perc numeric(18,4) NOT NULL,
	tds_surcharge_amt numeric(18,4) NOT NULL,
        tds_surcharge_amt_fc numeric(18,4) NOT NULL,
        payment_id varchar(50) not null,
        payment_date date null,
        status smallint not null,
        tran_group Varchar(50) Not Null, 
	CONSTRAINT pk_tds_bill_tds_tran PRIMARY KEY (bill_tds_tran_id)
);

?==?
CREATE TABLE tds.tds_payment_control
(
  company_id bigint NOT NULL,
  doc_type varchar(20) NOT NULL,
  finyear varchar(4) NOT NULL,
  branch_id bigint NOT NULL,
  voucher_id varchar(50) NOT NULL,
  doc_date date NOT NULL,
  account_id bigint NOT NULL,
  tds_total_amt numeric(18,4),
  interest_amt numeric(18,4),
  penalty_amt numeric(18,4),
  amt numeric(18,4) NOT NULL,
  narration varchar(500) NOT NULL,
  status smallint NOT NULL,
  amt_in_words varchar(250) NOT NULL,
  remarks varchar(500) NOT NULL,
  cheque_number varchar(20) NOT NULL,
  cheque_date date NOT NULL,
  collected boolean NULL,
  collection_date date,
  challan_bsr varchar(5) not null, 
  challan_serial varchar(7) not null,
  last_updated timestamp NOT NULL,
  annex_info jsonb not null default '{}',
  CONSTRAINT pk_tds_payment_control PRIMARY KEY (voucher_id)
);

?==?
CREATE TABLE tds.tds_return_control
(
	company_id bigint NOT NULL,
	doc_type varchar(20) NOT NULL,
	finyear varchar(4) NOT NULL,
	branch_id bigint NOT NULL,
	voucher_id varchar(50) NOT NULL,
	doc_date date NOT NULL,
	return_type smallint not null,
	return_quarter varchar(2) not null,
	amt numeric(18,4) NOT NULL,
	narration varchar(500) NOT NULL,
	status smallint NOT NULL,
	amt_in_words varchar(250) NOT NULL,
	remarks varchar(500) NOT NULL,
	last_updated timestamp NOT NULL,
        prev_quarter_token_no varchar(15) not null,
        is_address_changed boolean not null,
        is_deductee_address_changed boolean not null,
	CONSTRAINT pk_tds_return_control PRIMARY KEY (voucher_id)
);

?==?
CREATE TABLE tds.tds_return_challan_tran
(
        sl_no smallint not null,
	voucher_id varchar(50) NOT NULL,
	challan_tran_id varchar(50) NOT NULL,
	payment_id varchar(50) not null,
	payment_date date not null,
	CONSTRAINT pk_tds_challan_tran PRIMARY KEY (challan_tran_id)
);

?==?
create table tds.deductor_state
(
	deductor_state_id bigint not null,
	deductor_state varchar(50) not null,
        last_updated timestamp without time zone NOT NULL DEFAULT current_timestamp(0),	
	constraint pk_deductor_state primary key (deductor_state_id)
);

?==?
Insert into tds.deductor_state(deductor_state, deductor_state_id)
Values ('ANDAMAN AND NICOBAR ISLANDS', 1),
        ('ANDHRA PRADESH', 2),
        ('ARUNACHAL PRADESH', 3),
        ('ASSAM', 4),
        ('BIHAR', 5),
        ('CHANDIGARH', 6),
        ('DADRA & NAGAR HAVELI', 7),
        ('DAMAN & DIU', 8),
        ('DELHI', 9),
        ('GOA', 10),
        ('GUJARAT', 11),
        ('HARYANA', 12),
        ('HIMACHAL PRADESH', 13),
        ('JAMMU & KASHMIR', 14),
        ('KARNATAKA', 15),
        ('KERALA', 16),
        ('LAKSHWADEEP', 17),
        ('MADHYA PRADESH', 18),
        ('MAHARASHTRA', 19),
        ('MANIPUR', 20),
        ('MEGHALAYA', 21),
        ('MIZORAM', 22),
        ('NAGALAND', 23),
        ('ORISSA', 24),
        ('PONDICHERRY', 25),
        ('PUNJAB', 26),
        ('RAJASTHAN', 27),
        ('SIKKIM', 28),
        ('TAMILNADU', 29),
        ('TRIPURA', 30),
        ('UTTAR PRADESH', 31),
        ('WEST BENGAL', 32),
        ('CHHATISHGARH', 33),
        ('UTTARAKHAND', 34),
        ('JHARKHAND', 35),
        ('TELANGANA', 36),
        ('OTHERS', 99);

?==?
create table tds.deductor_type
(
	deductor_type_id bigint not null,
	deductor_type varchar(50) not null,
        deductor_code varchar(1) not null,
        last_updated timestamp without time zone NOT NULL DEFAULT current_timestamp(0),	
	constraint pk_deductor_type primary key (deductor_type_id)
);

?==?
Insert into tds.deductor_type(deductor_type, deductor_type_id, deductor_code)
Values ('Central Government', 1, 'A'),
        ('State Government', 2, 'S'),
        ('Statutory body (Central Govt.)', 3, 'D'),
        ('atutory body (State Govt.)', 4, 'E'),
        ('Autonomous body (Central Govt.)', 5, 'G'),
        ('Autonomous body (State Govt.)', 6, 'H'),
        ('Local Authority (Central Govt.)', 7, 'L'),
        ('Local Authority (State Govt.)', 8, 'N'),
        ('Company', 9, 'K'),
        ('Branch / Division of Company', 10, 'M'),
        ('Association of Person (AOP)', 11, 'P'),
        ('Association of Person (Trust)', 12, 'T'),
        ('Artificial Juridical Person', 13, 'J'),
        ('Body of Individuals', 14, 'B'),
        ('Individual/HUF', 15, 'Q'),
        ('Firm', 16, 'F');

?==?
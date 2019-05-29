create schema tds;

?==?
-- Table script for Person Type
CREATE TABLE tds.person_type
(
  person_type_id bigint NOT NULL,
  person_type_desc varchar(50) NOT NULL,
  last_updated timestamp without time zone NOT NULL DEFAULT current_timestamp(0),
  company_id bigint NOT NULL,
  CONSTRAINT pk_tds_person_type PRIMARY KEY (person_type_id)
);
?==?


INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('tds.person_type', 0);
?==?

Insert into tds.person_type(person_type_id, person_type_desc, last_updated, company_id)
values(1, 'Individual/Others (Resident)', current_timestamp, -1),
	(2,'Company (Resident)', current_timestamp, -1),
	(3,'Individual/Others (Non Resident)', current_timestamp, -1),
	(4,'Company (Non Resident)', current_timestamp, -1),
	(5,'Individual/HUF', current_timestamp, -1),
	(6,'Others', current_timestamp, -1);

?==?
Insert into sys.mast_seq_tran(company_id, mast_seq_type, max_id, lock_bit)
select -1, 'tds.person_type', 6, false;

?==?
-- Table script for section
CREATE TABLE tds.section
(
    section_id bigint NOT NULL,
    section varchar(50) NOT NULL,
    section_code varchar(3) not null,
    section_desc varchar(250) NOT NULL,
    company_id bigint NOT NULL,
    tds_account_id bigint NOT NULL,
    last_updated timestamp without time zone NOT NULL DEFAULT current_timestamp(0),
    CONSTRAINT pk_tds_section PRIMARY KEY (section_id)
);

?==?
INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('tds.section', 0);

?==?
Insert into tds.section(section_id, section, section_code, section_desc, company_id, tds_account_id, last_updated)
values (1,'193','193','Interest on securities', -1, -1, current_timestamp),
(2,'194','194','Dividend', -1, -1, current_timestamp),
(3,'194A','94A','Interest other than Interest on securities', -1, -1, current_timestamp),
(4,'194B','94B','Winnings from lotteries etc', -1, -1, current_timestamp),
(5,'194BB','4BB','Winnings from Horse races', -1, -1, current_timestamp),
(6,'194C','94C','Payment to contractor/sub-contractor', -1, -1, current_timestamp),
(7,'194D','94D','Insurance commission', -1, -1, current_timestamp),
(8,'194DA','4DA','Payment in respect of life insurance policy', -1, -1, current_timestamp),
(9,'194EE','4EE','Payment in respect of deposit under NSS', -1, -1, current_timestamp),
(10,'194F','94F','Payment on account of repurchase of unit by Mutual Fund or UTI', -1, -1, current_timestamp),
(11,'194G','94G','Commision, etx., on sale of lottery tickets', -1, -1, current_timestamp),
(12,'194H','94H','Commission or Brokerage', -1, -1, current_timestamp),
(13,'194la','4la','Rent - Plant & Machinery', -1, -1, current_timestamp),
(14,'194lb','4lb','Rent - Land or building or furniture or fitting', -1, -1, current_timestamp),
(15,'194J','94J','Fee for professional services', -1, -1, current_timestamp),
(16,'194LA','4LA','Payment of compensation on acquisition of certain immovable property', -1, -1, current_timestamp),
(17,'194LB','4LB','Payment of interest on infrastructure debt fund', -1, -1, current_timestamp);

?==?
Insert into sys.mast_seq_tran(company_id, mast_seq_type, max_id, lock_bit)
select -1, 'tds.section', 17, false;

?==?
-- Table script for Rate
CREATE TABLE tds.rate
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
  last_updated timestamp NOT NULL DEFAULT current_timestamp(0),
  CONSTRAINT pk_tds_rate PRIMARY KEY (rate_id),
  CONSTRAINT fk_tds_rate_person_type FOREIGN KEY (person_type_id)
      REFERENCES tds.person_type (person_type_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_tds_rate_section FOREIGN KEY (section_id)
      REFERENCES tds.section (section_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
); 

?==?
INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('tds.rate', 0);

?==?
Insert into tds.rate(rate_id, section_id, person_type_id, base_rate_perc, ecess_perc, surcharge_perc, effective_from, company_id, en_round_type, last_updated)
values (1,1,1,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(2,1,2,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(3,1,3,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(4,1,4,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(5,2,1,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(6,2,2,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(7,2,3,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(8,2,4,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(9,3,1,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(10,3,2,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(11,3,3,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(12,3,4,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(13,4,1,30.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(14,4,2,30.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(15,4,3,30.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(16,4,4,30.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(17,5,1,30.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(18,5,4,30.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(19,5,2,30.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(20,5,3,30.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(21,6,4,2.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(22,6,2,2.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(23,6,5,1.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(24,6,6,2.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(25,7,4,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(26,7,2,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(27,7,3,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(28,7,1,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(29,8,4,2.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(30,8,2,2.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(31,8,3,2.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(32,8,1,2.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(33,9,4,20.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(34,9,3,20.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(35,9,2,20.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(36,9,1,20.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(37,10,4,20.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(38,10,2,20.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(39,10,3,20.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(40,10,1,20.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(41,11,4,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(42,11,2,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(43,11,3,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(44,11,1,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(46,12,2,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(47,12,3,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(48,12,1,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(49,13,4,2.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(50,13,2,2.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(51,13,3,2.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(52,13,1,2.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(53,16,4,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(54,16,2,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(55,16,3,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(56,16,1,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(57,15,4,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(58,15,2,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(59,15,3,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(60,15,1,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(61,14,4,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(62,14,2,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(63,14,3,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(64,14,1,10.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(65,17,4,5.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(66,17,2,5.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(67,17,3,5.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp),
(68,17,1,5.0000,0.0000,0.0000,'2014-04-01',-1,2,current_timestamp);

?==?
Insert into sys.mast_seq_tran(company_id, mast_seq_type, max_id, lock_bit)
select -1, 'tds.rate', 68, false;

?==?
create schema hr;

?==?

CREATE TABLE hr.employee
(
  company_id bigint NOT NULL,
  employee_id bigint NOT NULL,
  employee_no character varying(50) NOT NULL,
  title character varying(10) NOT NULL,
  firstname character varying(100) NOT NULL,
  middlename character varying(100) NOT NULL,
  lastname character varying(100) NOT NULL,
  full_employee_name character varying(320) NOT NULL,
  fathername character varying(100) NOT NULL,
  gender character varying(10) NOT NULL,
  dob date NOT NULL,
  birthplace character varying(100) NOT NULL,
  nationality character varying(100) NOT NULL,
  height character varying(50) NOT NULL,
  weight character varying(50) NOT NULL,
  bloodgroup character varying(4) NOT NULL,
  religion character varying(50) NOT NULL,
  marital_status character varying(10) NOT NULL,
  join_date date NOT NULL,
  is_resign_date boolean not null,
  resign_date date,
  en_resign_type int NOT NULL DEFAULT (-1),
  report_to_employee_id bigint NOT NULL DEFAULT (-1),
  payroll_group_id bigint NOT NULL DEFAULT (-1),
  skill_id bigint NOT NULL,
  subhead_id bigint NOT NULL,
  bank_account_id bigint NOT NULL,
  address_id bigint NOT NULL,
  remarks character varying(500) NOT NULL,
  last_updated timestamp without time zone NOT NULL,
  CONSTRAINT pk_employee PRIMARY KEY (employee_id),
  CONSTRAINT uk_employee UNIQUE (company_id, employee_id)
);

?==?

INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('hr.employee', 0);

?==?

CREATE TABLE hr.employee_bank_info
(
  employee_bank_info_id character varying(50) NOT NULL,
  employee_id bigint NOT NULL,
  bank_name character varying(250) NOT NULL,
  bank_branch character varying(250) NOT NULL,
  bank_address character varying(500) NOT NULL,
  bank_account_no character varying(50) NOT NULL,
  cb_id_code character varying(8) NOT NULL,
  routing_code character varying(9) NOT NULL,
  other_bank_info character varying(500) NOT NULL,
  default_bank boolean NOT NULL,
  CONSTRAINT pk_employee_bank_info PRIMARY KEY (employee_bank_info_id),
  CONSTRAINT fk_employee_bank_info FOREIGN KEY (employee_id)
      REFERENCES hr.employee (employee_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?

INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('hr.skill', 0);

?==?

CREATE TABLE hr.skill
(
	skill_id bigint NOT NULL,
	skill character varying(50) NOT NULL,
        company_id bigint NOT NULL,
        last_updated timestamp without time zone NOT NULL DEFAULT now(),
 CONSTRAINT pk_grade PRIMARY KEY (skill_id),
 CONSTRAINT uk_grade UNIQUE (company_ID,skill_id)
);

?==?

CREATE TABLE hr.employee_org_detail
(
  employee_org_id bigint NOT NULL,
  employee_id bigint NOT NULL,
  effective_date date NOT NULL,
  branch_id bigint NOT NULL,
  ac_subhead_id bigint NOT NULL,
  manager_id bigint NOT NULL,
  designation_id bigint NOT NULL,
  company_id bigint NOT NULL,
  last_updated timestamp without time zone NOT NULL DEFAULT now(),
  CONSTRAINT pk_employee_org_detail PRIMARY KEY (employee_org_id),
  CONSTRAINT uk_employee_org_detail UNIQUE (company_id, employee_org_id)
);

?==?

INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('hr.employee_org_detail', 0);

?==?

CREATE TABLE hr.payhead
(
  payhead_id bigint NOT NULL,
  payhead character varying(50) NOT NULL,
  payhead_alias character varying(20) NOT NULL,
  account_id bigint NOT NULL,
  accrual_account_id bigint NOT NULL,
  payhead_type character varying(1) NOT NULL DEFAULT 'E'::character varying,
  monthly_or_onetime smallint NOT NULL DEFAULT 0,
  incl_in_gratuity boolean NOT NULL DEFAULT false,
  incl_in_leave boolean NOT NULL DEFAULT false,
  incl_in_nopay boolean NOT NULL DEFAULT false,
  calc_type character varying(50) NOT NULL,
  company_id bigint NOT NULL,
  last_updated timestamp without time zone NOT NULL DEFAULT now(),
  CONSTRAINT pk_payhead PRIMARY KEY (payhead_id),
  CONSTRAINT uk_payhead UNIQUE (company_id, payhead)
);

?==?

INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('hr.payhead', 0);

?==?

CREATE TABLE hr.grade
(
  grade_id bigint NOT NULL,
  grade character varying(50) NOT NULL,
  grade_alias character varying(20) NOT NULL,
  company_id bigint NOT NULL,
  last_updated timestamp without time zone NOT NULL DEFAULT current_timestamp(0),
  CONSTRAINT pk_hr_grade PRIMARY KEY (grade_id),
  CONSTRAINT uk_hr_grade UNIQUE (company_id, grade_alias)
);

?==?

INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('hr.grade', 0);

?==?

CREATE TABLE hr.grade_detail
(
  grade_id bigint NOT NULL,
  leave_type_id bigint NOT NULL,
  leave_days integer NOT NULL,
  leave_entitled_per_year numeric(18,10) NOT NULL,
  sl_no smallint NOT NULL,
  grade_detail_id character varying(50) NOT NULL,
  CONSTRAINT pk_grade_detail_tran PRIMARY KEY (grade_detail_id),
  CONSTRAINT fk_hr_grade_grade_detail_tran_ FOREIGN KEY (grade_id)
      REFERENCES hr.grade (grade_id)
);

?==?

CREATE TABLE hr.holiday_list
(
  holiday_id bigint NOT NULL,
  holiday_year character varying(4) NOT NULL,
  holiday_date date NOT NULL,
  holiday_desc character varying(20) NOT NULL,
  company_id bigint NOT NULL,
  last_updated timestamp without time zone NOT NULL DEFAULT now(),
  CONSTRAINT pk_holiday_list PRIMARY KEY (holiday_id)
);

?==?

INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('hr.holiday_list', 0);

?==?

CREATE TABLE hr.weeklyoff
(
  company_id bigint NOT NULL,
  branch_id bigint NOT NULL,
  weeklyoff_id bigint NOT NULL,
  day_of_week character varying(10) NOT NULL,
  overtime_type character varying(50) NOT NULL,
  working_hours bigint NOT NULL,
  last_updated timestamp without time zone NOT NULL DEFAULT now(),
  CONSTRAINT pk_weeklyoff PRIMARY KEY (weeklyoff_id)
);

?==?

CREATE TABLE hr.designation
(
  designation_id bigint NOT NULL,
  designation character varying(100) NOT NULL,
  rank smallint NOT NULL,
  is_training_reqd boolean NOT NULL DEFAULT false,
  company_id bigint NOT NULL,
  last_updated timestamp without time zone NOT NULL DEFAULT now(),
  CONSTRAINT pk_designation PRIMARY KEY (designation_id),
  CONSTRAINT uk_designation UNIQUE (company_id, designation_id)
);

?==?

INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('hr.designation', 0);

?==?

CREATE TABLE hr.leave_type
(
  leave_type_id bigint NOT NULL,
  leave_type character varying(50) NOT NULL,
  paid_leave boolean NOT NULL DEFAULT false,
  pay_percent numeric(18,4) NOT NULL DEFAULT 0,
  carry_forward_at_yearend boolean NOT NULL DEFAULT false,
  carry_forward_limit bigint NOT NULL DEFAULT 0,
  en_entitlement_type smallint NOT NULL DEFAULT 0,
  rejoin_compulsory boolean NOT NULL DEFAULT false,
  company_id bigint NOT NULL,
  last_updated timestamp without time zone NOT NULL DEFAULT current_timestamp(0),
  CONSTRAINT pk_leave_type PRIMARY KEY (leave_type_id)
);

?==?
INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('hr.leave_type', 0);

?==?
CREATE TABLE hr.leave
(
    leave_id bigint NOT NULL,
    employee_id bigint NOT NULL,
    finyear character varying(4) NOT NULL,
    applied_on date NOT NULL,
    from_date date NOT NULL,
    to_date date NOT NULL,
    leave_type_id bigint NOT NULL,
    replacement_required boolean NOT NULL Default false,
    replacing_emp_id bigint NOT NULL,
    is_authorised_on boolean not null,
    authorised_on date NULL,
    authorised_by_emp_id bigint NOT NULL,
    is_rejoin_date boolean not null,
    rejoin_date date NULL,
    company_id bigint NOT NULL,
    last_updated timestamp without time zone NOT NULL DEFAULT current_timestamp(0),
    CONSTRAINT pk_leave PRIMARY KEY (leave_id),
    CONSTRAINT fk_leave_employee FOREIGN KEY (employee_id)
        REFERENCES hr.employee (employee_id),
    CONSTRAINT fk_leave_leave_type FOREIGN KEY (leave_type_id)
        REFERENCES hr.leave_type (leave_type_id)
);

?==?
INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('hr.leave', 0);

?==?
CREATE TABLE hr.attendance
(
  attendance_id bigint NOT NULL,
  employee_id bigint NOT NULL,
  attendance_date date NOT NULL,
  in_time character varying(5) NOT NULL,
  out_time character varying(5) NOT NULL,
  overtime numeric(5,2) NOT NULL,
  ot_holiday numeric(5,2) NOT NULL,
  ot_special numeric(5,2) NOT NULL,
  company_id bigint NOT NULL,
  finyear varchar(4) not null,
  last_updated timestamp without time zone,
  CONSTRAINT pk_attendance PRIMARY KEY (attendance_id),
  CONSTRAINT fk_attendance_employee FOREIGN KEY (employee_id)
      REFERENCES hr.employee (employee_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('hr.attendance', 0);

?==?
CREATE TABLE hr.payroll_group
(
  payroll_group_id bigint NOT NULL,
  payroll_group character varying(250) NOT NULL,
  en_pay_period smallint NOT NULL,
  overtime_applicable boolean NOT NULL DEFAULT false,
  company_id bigint NOT NULL,
  last_updated timestamp without time zone NOT NULL DEFAULT now(),
  CONSTRAINT pk_payroll_group PRIMARY KEY (payroll_group_id),
  CONSTRAINT uk_payroll_group UNIQUE (company_id, payroll_group)
);

?==?
INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('hr.payroll_group', 0);

?==?
CREATE TABLE hr.pay_schedule
(
  pay_schedule_id bigint NOT NULL,
  company_id bigint NOT NULL,
  description character varying(120) NOT NULL,
  pay_schedule_code character varying(20) NOT NULL,
  is_discontinued boolean NOT NULL,
  ot_calc_method integer NOT NULL,
  ot_rate numeric(18,4) NOT NULL,
  ot_holiday_rate numeric(18,4) NOT NULL,
  ot_special_rate numeric(18,4) NOT NULL,
  last_updated timestamp without time zone NOT NULL,
  CONSTRAINT pk_hr_pay_schedule PRIMARY KEY (pay_schedule_id),
  CONSTRAINT uk_hr_pay_schedule UNIQUE (company_id, description)
);

?==?
INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('hr.pay_schedule', 0);

?==?
CREATE TABLE hr.pay_schedule_detail
(
  pay_schedule_detail_id bigint NOT NULL,
  pay_schedule_id bigint NOT NULL,
  step_id bigint NOT NULL,
  parent_pay_schedule_details character varying(500) NOT NULL,
  description character varying(120) NOT NULL,
  payhead_id bigint NOT NULL,
  en_pay_type smallint NOT NULL,
  en_round_type smallint NOT NULL,
  pay_perc numeric(18,4) NOT NULL,
  pay_on_perc numeric(18,4) NOT NULL,
  pay_on_min_amt numeric(18,4) NOT NULL,
  pay_on_max_amt numeric(18,4) NOT NULL,
  min_pay_amt numeric(18,4) NOT NULL,
  max_pay_amt numeric(18,4) NOT NULL,
  amt numeric(18,4) NOT NULL,
  do_not_display boolean NOT NULL,
  payhead_type  varchar(1) not null,
  CONSTRAINT pk_hr_pay_schedule_detail PRIMARY KEY (pay_schedule_detail_id),
  CONSTRAINT fk_hr_pay_schedule_detail_pay_schedule FOREIGN KEY (pay_schedule_id)
      REFERENCES hr.pay_schedule (pay_schedule_id)
);

?==?
INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('hr.pay_schedule_detail', 0);

?==?
CREATE TABLE hr.employee_payplan
(
  employee_payplan_id bigint NOT NULL,
  grade_id bigint NOT NULL,
  employee_id bigint NOT NULL,
  schedule_type smallint not null,
  pay_schedule_id bigint NOT NULL,
  effective_from_date date NOT NULL,
  is_effective_to_date boolean not null,
  effective_to_date date,
  company_accomodation boolean NOT NULL DEFAULT false,
  ot_rate numeric(18,4) NOT NULL DEFAULT 0,
  ot_holiday_rate numeric(18,4) NOT NULL DEFAULT 0,
  ot_special_rate numeric(18,4) NOT NULL DEFAULT 0,
  company_id bigint NOT NULL,
  last_updated timestamp without time zone NOT NULL DEFAULT now(),
  CONSTRAINT pk_employee_payplan PRIMARY KEY (employee_payplan_id)
);

?==?
INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('hr.employee_payplan', 0);

?==?
CREATE TABLE hr.employee_payplan_detail
(
  employee_payplan_detail_id bigint NOT NULL,
  employee_payplan_id bigint NOT NULL,
  step_id bigint NOT NULL,
  parent_details character varying(500) NOT NULL,
  description character varying(120) NOT NULL,
  payhead_id bigint NOT NULL,
  en_pay_type smallint NOT NULL,
  en_round_type smallint NOT NULL,
  pay_perc numeric(18,4) NOT NULL,
  pay_on_perc numeric(18,4) NOT NULL,
  pay_on_min_amt numeric(18,4) NOT NULL,
  pay_on_max_amt numeric(18,4) NOT NULL,
  min_pay_amt numeric(18,4) NOT NULL,
  max_pay_amt numeric(18,4) NOT NULL,
  amt numeric(18,4) NOT NULL,
  do_not_display boolean NOT NULL,
  payhead_type  varchar(1) not null,
  CONSTRAINT pk_hr_employee_payplan_detail PRIMARY KEY (employee_payplan_detail_id),
  CONSTRAINT fk_hr_employee_payplan_detail_employee_payplan FOREIGN KEY (employee_payplan_id)
      REFERENCES hr.employee_payplan (employee_payplan_id) 
);

?==?
INSERT INTO sys.mast_seq (mast_seq_type, seed)
VALUES ('hr.employee_payplan_detail', 0);

?==?
CREATE TABLE hr.payroll_control
(
  company_id bigint NOT NULL,
  doc_type character varying(20) NOT NULL,
  finyear character varying(4) NOT NULL,
  finmonth character varying(4) NOT NULL,
  branch_id bigint NOT NULL,
  payroll_id character varying(50) NOT NULL,
  payroll_group_id bigint NOT NULL,
  doc_date date NOT NULL,
  voucher_id character varying(50) NOT NULL,
  pay_from_date date NOT NULL,
  pay_to_date date NOT NULL,
  gross_overtime_amt numeric(18,4) NOT NULL,
  gross_emolument_amt numeric(18,4) NOT NULL,
  gross_deduction_amt character varying(250) NOT NULL,
  status smallint NOT NULL,
  remarks character varying(500) NOT NULL,
  last_updated timestamp without time zone NOT NULL,
  CONSTRAINT pk_hr_payroll_control PRIMARY KEY (payroll_id)
);

?==?
CREATE TABLE hr.payroll_tran
(
  sl_no smallint NOT NULL,
  payroll_id character varying(50) NOT NULL,
  payroll_tran_id character varying(50) NOT NULL,
  employee_id bigint NOT NULL,
  voucher_id varchar(50) not null,
  pay_days smallint NOT NULL,
  no_pay_days smallint NOT NULL,
  half_pay_days smallint NOT NULL,
  tot_ot_hour numeric(18,4) NOT NULL,
  tot_ot_holiday_hour numeric(18,4) NOT NULL,
  tot_ot_special_hour numeric(18,4) NOT NULL,
  tot_ot_amt numeric(18,4) NOT NULL,
  tot_ot_holiday_amt numeric(18,4) NOT NULL,
  tot_ot_special_amt numeric(18,4) NOT NULL,
  tot_overtime_amt numeric(18,4) NOT NULL,
  tot_emolument_amt numeric(18,4) NOT NULL,
  tot_deduction_amt numeric(18,4) NOT NULL,
  amt_in_words character varying(250) NOT NULL,
  block_payment boolean NOT NULL,
  last_updated timestamp without time zone NOT NULL,
  CONSTRAINT pk_hr_payroll_tran PRIMARY KEY (payroll_tran_id),
  CONSTRAINT fk_hr_payroll_control_payroll_tran FOREIGN KEY (payroll_id)
      REFERENCES hr.payroll_control (payroll_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
);
?==?

CREATE TABLE hr.payroll_tran_detail
(
  payroll_id character varying(50) NOT NULL,
  payroll_tran_id character varying(50) NOT NULL,
  payroll_tran_detail_id character varying(50) NOT NULL,
  employee_id bigint NOT NULL,
  sl_no smallint NOT NULL,
  payhead_id bigint NOT NULL,
  payhead_type character(1) NOT NULL,
  emolument_amt numeric(18,4) NOT NULL,
  deduction_amt numeric(18,4) NOT NULL,
  monthly_or_onetime smallint NOT NULL DEFAULT 0,
  CONSTRAINT pk_hr_payroll_tran_detail PRIMARY KEY (payroll_tran_detail_id),
  CONSTRAINT fk_hr_payroll_control_payroll_tran_detail FOREIGN KEY (payroll_id)
      REFERENCES hr.payroll_control (payroll_id),
  CONSTRAINT fk_hr_payroll_tran_payroll_tran_detail FOREIGN KEY (payroll_tran_id)
      REFERENCES hr.payroll_tran (payroll_tran_id)
);

?==?
CREATE TABLE hr.paysheet_ledger
(
  paysheet_ledger_id character varying(50) NOT NULL,
  company_id bigint NOT NULL,
  branch_id bigint NOT NULL,
  finyear character varying(4) NOT NULL,
  doc_date date NOT NULL,
  payroll_id character varying(50) NOT NULL,
  employee_id bigint NOT NULL,
  subhead_id bigint NOT NULL,
  pay_from_date date NOT NULL,
  pay_to_date date NOT NULL,
  pay_days smallint NOT NULL,
  no_pay_days smallint NOT NULL,
  half_pay_days smallint NOT NULL,
  tot_ot_hour numeric(18,4) NOT NULL,
  tot_ot_holiday_hour numeric(18,4) NOT NULL,
  tot_ot_special_hour numeric(18,4) NOT NULL,
  tot_ot_amt numeric(18,4) NOT NULL,
  tot_ot_holiday_amt numeric(18,4) NOT NULL,
  tot_ot_special_amt numeric(18,4) NOT NULL,
  tot_overtime_amt numeric(18,4) NOT NULL,
  tot_emolument_amt numeric(18,4) NOT NULL,
  tot_deduction_amt numeric(18,4) NOT NULL,
  amt_in_words character varying(250) NOT NULL,
  CONSTRAINT pk_hr_paysheet_ledger PRIMARY KEY (paysheet_ledger_id)
);

?==?
CREATE TABLE hr.paysheet_ledger_tran
(
  paysheet_ledger_tran_id character varying(50) NOT NULL,
  paysheet_ledger_id character varying(50) NOT NULL,
  employee_id bigint NOT NULL,
  sl_no smallint NOT NULL,
  payhead_id bigint NOT NULL,
  payhead_type character(1) NOT NULL,
  emolument_amt numeric(18,4) NOT NULL,
  deduction_amt numeric(18,4) NOT NULL,
  monthly_or_onetime smallint NOT NULL DEFAULT 0,
  CONSTRAINT pk_hr_paysheet_ledger_tran PRIMARY KEY (paysheet_ledger_tran_id),
  CONSTRAINT fk_hr_paysheet_ledger_paysheet_ledger_tran FOREIGN KEY (paysheet_ledger_id)
      REFERENCES hr.paysheet_ledger (paysheet_ledger_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
CREATE TABLE hr.gratuity_control
(
  company_id bigint NOT NULL,
  branch_id bigint NOT NULL,
  finyear character varying(4) NOT NULL,
  doc_type character varying(20) NOT NULL,
  doc_month character varying(4) NOT NULL,
  gratuity_id character varying(50) NOT NULL,
  doc_date date NOT NULL,
  employee_id bigint NOT NULL,
  gratuity_from_date date NOT NULL,
  gratuity_to_date date NOT NULL,
  total_amt numeric(18,4) NOT NULL,
  reducible_amt numeric(18,4) NOT NULL,
  net_gratuity_amt numeric(18,4) NOT NULL,
  status smallint NOT NULL,
  remarks character varying(500) NOT NULL,
  last_updated timestamp without time zone NOT NULL,
  CONSTRAINT pk_hr_gratuity_control PRIMARY KEY (gratuity_id)
);

?==?
CREATE TABLE hr.gratuity_tran
(
  gratuity_id character varying(50) NOT NULL,
  gratuity_tran_id character varying(50) NOT NULL,
  sl_no smallint NOT NULL,
  slab_from_date date NOT NULL,
  slab_to_date date NOT NULL,
  slab_days smallint NOT NULL,
  gratuity_days smallint NOT NULL,
  amount numeric(18,4) NOT NULL,
  unpaid_days smallint NOT NULL,
  CONSTRAINT pk_hr_gratuity_tran PRIMARY KEY (gratuity_tran_id),
  CONSTRAINT fk_hr_gratuity_control_gratuity_tran FOREIGN KEY (gratuity_id)
      REFERENCES hr.gratuity_control (gratuity_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
CREATE TABLE hr.gratuity_formula
(
  company_id bigint NOT NULL,
  entry_id bigint NOT NULL,
  days_from bigint NOT NULL,
  days_to bigint NOT NULL,
  days_worked bigint NOT NULL,
  gratuity_days bigint NOT NULL,
  CONSTRAINT pk_gratuity_formula PRIMARY KEY (entry_id)
);

?==?
CREATE TABLE hr.loan_control
(
  company_id bigint NOT NULL,
  branch_id bigint NOT NULL,
  finyear character varying(4) NOT NULL,
  doc_type character varying(20) NOT NULL,
  doc_month character varying(4) NOT NULL,
  loan_id character varying(50) NOT NULL,
  doc_date date NOT NULL,
  employee_id bigint NOT NULL,
  loan_from_date date NOT NULL,
  loan_to_date date NOT NULL,
  en_calculate_by smallint NOT NULL,
  loan_desc character varying(100) NOT NULL,
  loan_principal numeric(18,4) NOT NULL,
  interest_percentage numeric(18,4) NOT NULL,
  loan_interest numeric(18,4) NOT NULL,
  total_recovery numeric(18,4) NOT NULL,
  installment_principal numeric(18,4) NOT NULL,
  installment_interest numeric(18,4) NOT NULL,
  no_of_installments smallint NOT NULL,
  status smallint NOT NULL,
  remarks character varying(500) NOT NULL,
  last_updated timestamp without time zone NOT NULL,
  CONSTRAINT pk_hr_loan_control PRIMARY KEY (loan_id)
);

?==?
CREATE TABLE hr.loan_tran
(
    loan_id character varying(50) NOT NULL,
    loan_tran_id character varying(50) NOT NULL,
    sl_no smallint NOT NULL,
    employee_id bigint NOT NULL,
    installment_date date NOT NULL,
    installment_principal numeric(18,4) NOT NULL,
    installment_interest numeric(18,4) NOT NULL,
    cl_balance numeric(18,4) not null,
    installment numeric(18,4) not null,
    os_amt numeric(18,4) not null,
    loan_principal_amt numeric(18,4) not null,
    CONSTRAINT pk_hr_loan_tran PRIMARY KEY (loan_tran_id),
    CONSTRAINT fk_hr_loan_tran FOREIGN KEY (loan_id)
        REFERENCES hr.loan_control (loan_id) MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?

CREATE TABLE hr.loan_repayment
(
  company_id bigint NOT NULL,
  loan_repayment_id character varying(50) NOT NULL,
  loan_id character varying(50) NOT NULL,
  sl_no integer NOT NULL,
  payroll_id character varying(50) NOT NULL,
  employee_id bigint NOT NULL,
  payroll_tran_detail_id character varying(50) NOT NULL,
  installment_principal numeric(18,4) NOT NULL,
  installment_interest numeric(18,4) NOT NULL,
  installment_amount numeric(18,4) NOT NULL,
  loan_tran_id character varying(50) NOT NULL,
  CONSTRAINT pk_hr_loan_repayment PRIMARY KEY (loan_repayment_id)
);

?==?
CREATE TABLE hr.fin_set_control
(
  company_id bigint NOT NULL,
  branch_id bigint NOT NULL,
  finyear character varying(4) NOT NULL,
  doc_type character varying(20) NOT NULL,
  doc_month character varying(4) NOT NULL,
  final_settlement_id character varying(50) NOT NULL,
  doc_date date NOT NULL,
  employee_id bigint NOT NULL,
  fin_set_from_date date NOT NULL,
  fin_set_to_date date NOT NULL,
  en_resign_type int NOT NULL,
  notice_pay numeric(18,4),
  total_pay_amt numeric(18,4) NOT NULL,
  total_gratuity_amt numeric(18,4) NOT NULL,
  total_leave_salary_amt numeric(18,4) NOT NULL,
  net_settlement_amt numeric(18,4) NOT NULL,
  net_amt_in_words character varying(250) NOT NULL,
  status smallint NOT NULL,
  remarks character varying(500) NOT NULL,
  last_updated timestamp without time zone NOT NULL,
  CONSTRAINT pk_fin_set_control PRIMARY KEY (final_settlement_id)
);

?==?
CREATE TABLE hr.fin_set_payroll_tran
(  
  fin_set_payroll_tran_id character varying(50) NOT NULL,
  final_settlement_id character varying(50) NOT NULL,  
  employee_id bigint NOT NULL,
  pay_days smallint NOT NULL,
  no_pay_days smallint NOT NULL,
  half_pay_days smallint NOT NULL,
  tot_ot_hr numeric(18,4) NOT NULL,
  tot_ot_holiday_hr numeric(18,4) NOT NULL,
  tot_ot_special_hr numeric(18,4) NOT NULL,
  tot_ot_amt numeric(18,4) NOT NULL,
  tot_ot_holiday_amt numeric(18,4) NOT NULL,
  tot_ot_special_amt numeric(18,4) NOT NULL,
  tot_overtime_amt numeric(18,4) NOT NULL,
  tot_emolument_amt numeric(18,4) NOT NULL,
  tot_deduction_amt numeric(18,4) NOT NULL,
  last_updated timestamp without time zone NOT NULL,
  CONSTRAINT pk_hr_fin_set_payroll_tran PRIMARY KEY (fin_set_payroll_tran_id),
  CONSTRAINT fk_hr_fin_set_control_tran FOREIGN KEY (final_settlement_id)
      REFERENCES hr.fin_set_control (final_settlement_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
CREATE TABLE hr.fin_set_payroll_tran_detail
(
  fin_set_payroll_tran_detail_id character varying(50) NOT NULL,
  fin_set_payroll_tran_id character varying(50) NOT NULL,
  final_settlement_id character varying(50) NOT NULL,
  employee_id bigint NOT NULL,
  sl_no smallint NOT NULL,
  payhead_id bigint NOT NULL,
  payhead_type character(1) NOT NULL,
  emolument_amt numeric(18,4) NOT NULL,
  deduction_amt numeric(18,4) NOT NULL,
  CONSTRAINT pk_hr_fin_set_payroll_tran_detail PRIMARY KEY (fin_set_payroll_tran_detail_id),
  CONSTRAINT fk_hr_fin_set_ctrl_payroll_tran_detail FOREIGN KEY (final_settlement_id)
      REFERENCES hr.fin_set_control (final_settlement_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_hr_fin_set_payroll_tran_payroll_tran_detail FOREIGN KEY (fin_set_payroll_tran_id)
      REFERENCES hr.fin_set_payroll_tran (fin_set_payroll_tran_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
CREATE TABLE hr.fin_set_gratuity_tran
(
  fin_set_gratuity_tran_id character varying(50) NOT NULL,
  final_settlement_id character varying(50) NOT NULL,
  employee_id bigint NOT NULL,
  gratuity_from_date date NOT NULL,
  gratuity_to_date date NOT NULL,
  gratuity_days numeric(18,4) NOT NULL,
  gratuity_amt numeric(18,4) NOT NULL,
  gratuity_already_paid numeric(18,4) NOT NULL,
  reducible_amt numeric(18,4) NOT NULL,
  CONSTRAINT pk_hr_fin_set_gratuity_tran PRIMARY KEY (fin_set_gratuity_tran_id),
  CONSTRAINT fk_hr_fin_set_ctrl_gratuity_tran FOREIGN KEY (final_settlement_id)
      REFERENCES hr.fin_set_control (final_settlement_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
CREATE TABLE hr.fin_set_gratuity_tran_detail
(
  fin_set_gratuity_tran_detail_id character varying(50) NOT NULL,
  fin_set_gratuity_tran_id character varying(50) NOT NULL,
  final_settlement_id character varying(50) NOT NULL,
  sl_no smallint NOT NULL,
  slab_from_date date NOT NULL,
  slab_to_date date NOT NULL,
  slab_days smallint NOT NULL,
  gratuity_days smallint NOT NULL,
  gratuity_amt numeric(18,4) NOT NULL,
  unpaid_days smallint NOT NULL,
  CONSTRAINT pk_hr_fin_set_gratuity_tran_detail PRIMARY KEY (fin_set_gratuity_tran_detail_id),
  CONSTRAINT fk_hr_fin_set_ctrl_gratuity_tran_detail FOREIGN KEY (final_settlement_id)
      REFERENCES hr.fin_set_control (final_settlement_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_hr_fin_set_gratuity_tran_gratuity_tran_detail FOREIGN KEY (fin_set_gratuity_tran_id)
      REFERENCES hr.fin_set_gratuity_tran (fin_set_gratuity_tran_id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
);

?==?
Insert into sys.settings (key, value)
Select 'hr_notice_pay_payhead', -1;

?==?
insert into sys.settings(key, value)
Select 'hr_suppress_payhead_in_pay_slip', '0';

?==?
create table hr.payroll_custom_tran
(
	payroll_custom_tran_id varchar(50),
	payroll_id varchar(50) not null,
	employee_id bigint not null,
	payhead_id bigint not null,
	payhead_type character(1) NOT NULL,
	emolument_amt numeric(18,4) NOT NULL,
	deduction_amt numeric(18,4) NOT NULL,
        employee_payplan_detail_id bigint not null,
	CONSTRAINT pk_payroll_custom_tran PRIMARY KEY (payroll_custom_tran_id),
	CONSTRAINT fk_hr_payroll_control_payroll_custom_tran FOREIGN KEY (payroll_id)
		REFERENCES hr.payroll_control (payroll_id)
);

?==?
insert into sys.settings(key, value)
Select 'hr_days_in_month', '30';

?==?
create table hr.employee_stat_regn
(
	employee_id bigint not null,
	pf_acc_no varchar(50) not null,
	esic_acc_no varchar(50) not null,
	pan varchar(10) not null,
	CONSTRAINT pk_employee_stat_regn PRIMARY KEY (employee_id)
	
);

?==?

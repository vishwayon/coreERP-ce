CREATE OR REPLACE FUNCTION hr.fn_eff_date_emp_for_payroll_posting(IN ppay_from_date date, IN ppay_to_date date)
  RETURNS TABLE(employee_id bigint, employee_no character varying, employee_full_name character varying, resign_date date, employee_org_id bigint, branch_id bigint, branch_name character varying, sub_head_id bigint, sub_head character varying, effective_date date) AS
$BODY$
begin

	DROP TABLE IF EXISTS eff_date_emp_temp;
	create temp TABLE  eff_date_emp_temp
	(	
	    employee_id bigint,
	    employee_no varchar(50),
	    employee_full_name varchar(500),
            resign_date date,
            employee_org_id bigint,
	    branch_id bigint,
	    branch_name varchar(100),
	    sub_head_id bigint,
            sub_head varchar(100),
	    effective_date date
	);

	DROP TABLE IF EXISTS emp_org_temp;
	create temp TABLE emp_org_temp
	(	
            employee_org_id bigint,
	    employee_id bigint,
	    branch_id bigint,
	    branch_name varchar(100),
            sub_head_id bigint,
            sub_head varchar(100),
            effective_date date,
	    employee_no varchar(50),
	    employee_full_name varchar(500)
	);
	
	insert into emp_org_temp(employee_org_id, employee_id, branch_id, branch_name, sub_head_id, sub_head,  employee_no, employee_full_name, effective_date)
	select a.employee_org_id, a.employee_id, a.branch_id, c.branch_name, a.ac_subhead_id, d.sub_head,  b.employee_no, b.full_employee_name, a.effective_date
	from hr.employee_org_detail a      
	inner join hr.employee b on a.employee_id = b.employee_id      
	inner join sys.branch c on a.branch_id = c.branch_id  
	left join ac.sub_head d on a.ac_subhead_id = d.sub_head_id ; 
	
	insert into eff_date_emp_temp(employee_id,employee_no,employee_full_name,resign_date,employee_org_id,branch_id,branch_name,sub_head_id,sub_head,effective_date)
	select a.employee_id, a.employee_no, a.full_employee_name, a.resign_date, b.employee_org_id, b.branch_id, b.branch_name, b.sub_head_id, b.sub_head, b.effective_date
	from hr.employee a
	inner join emp_org_temp b on a.employee_id=b.employee_id and b.effective_date=(Select (Max(c.effective_date)) from emp_org_temp c 
										       where c.employee_id=b.employee_id and c.effective_date <= ppay_to_date);
	
	return query 
	select * from eff_date_emp_temp;

END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION hr.fn_payroll_info_for_gl_post(IN pvoucher_id character varying)
  RETURNS TABLE(index integer, company_id bigint, branch_id bigint, dc character, account_id bigint, debit_amt_fc numeric, credit_amt_fc numeric, debit_amt numeric, credit_amt numeric) AS
$BODY$ 
	Declare vpayroll_ID bigint =0; vSalaryPayable numeric(18,2)=0; vPayFromDate Date; vPayToDate Date;	 
Begin	
	-- This function is used by the Posting Trigger to get information on the Payroll

	DROP TABLE IF EXISTS vch_detail;	
	create temp TABLE  vch_detail
	(	
		index serial, 
		company_id bigint,
		branch_id bigint,
		dc char(1),
		account_id bigint,
		debit_amt_fc numeric(18,4),
		credit_amt_fc numeric(18,4),
		debit_amt numeric(18,4),
		credit_amt numeric(18,4)
	);
	-- Fetch constant values required for posting into ledger  
	Select pay_from_date into vPayFromDate from hr.payroll_control where payroll_id=pVoucher_id;
	Select pay_to_date into vPayToDate from hr.payroll_control where payroll_id=pVoucher_id;

	-- Fill payroll Data for Emolument (Debits) in payhead account
	Insert into vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 'D', c.account_id, 0, 0, coalesce(Sum(b.emolument_amt),0), 0 
        from hr.payroll_control a 
	inner join hr.payroll_tran_detail b on a.payroll_id=b.payroll_id 
	inner join hr.payHead c On b.payhead_id=c.payhead_id  
	inner join (Select * from hr.fn_eff_date_emp_for_payroll_posting(vPayFromDate, vPayToDate)) d On b.employee_ID = d.employee_ID  
	where a.payroll_id=pvoucher_id And c.payhead_type='E'  
	group by a.payroll_id, c.account_id, a.branch_id;
	
	-- Fill payroll Data for Emolument (Credits) in payhead accrual account
	Insert into vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 'C', c.accrual_account_id, 0, 0, 0 , coalesce(Sum(b.emolument_amt),0)
        from hr.payroll_control a 
	inner join hr.payroll_tran_detail b on a.payroll_id=b.payroll_id 
	inner join hr.payHead c On b.payhead_id=c.payhead_id  
	inner join (Select * from hr.fn_eff_date_emp_for_payroll_posting(vPayFromDate, vPayToDate)) d On b.employee_ID = d.employee_ID  
	where a.payroll_id=pvoucher_id And c.payhead_type='E'  
	group by a.payroll_id, c.accrual_account_id, a.branch_id;

	-- Fill payroll Data for Deduction(credits) in payhead account
	Insert into vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 'C', c.account_id, 0, 0, 0, coalesce(Sum(b.deduction_amt),0)  
	from hr.payroll_control a 
	inner join hr.payroll_tran_detail b on a.payroll_id=b.payroll_id   
	inner join hr.payHead c On b.payhead_id=c.payhead_id  
	inner join (Select * from hr.fn_eff_date_emp_for_payroll_posting(vPayFromDate, vPayToDate)) d On b.employee_ID = d.employee_ID  
	where a.payroll_id=pvoucher_id And c.payhead_type in ('D', 'L')
	group by a.payroll_id, c.account_id,a.branch_id;

	-- Fill payroll Data for Deduction(debits) in payhead accrual account
	Insert into vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 'D', c.accrual_account_id, 0, 0, coalesce(Sum(b.deduction_amt),0), 0  
	from hr.payroll_control a 
	inner join hr.payroll_tran_detail b on a.payroll_id=b.payroll_id   
	inner join hr.payHead c On b.payhead_id=c.payhead_id  
	inner join (Select * from hr.fn_eff_date_emp_for_payroll_posting(vPayFromDate, vPayToDate)) d On b.employee_ID = d.employee_ID  
	where a.payroll_id=pvoucher_id And c.payhead_type in ('D', 'L') 
	group by a.payroll_id, c.accrual_account_id,a.branch_id;

	return query 
	select a.index, a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
	from vch_detail a;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
create or replace function hr.fn_emp_payhead_for_promt_in_payroll(pfrom_date date, pto_date date, ppayroll_group_id bigint)
RETURNS TABLE  
(	
	employee_payplan_detail_id bigint,
	employee_id bigint,
	employee_name character varying, 
	payhead_id bigint,
	payhead varchar(50),
	payhead_type varchar(1),
	emolument_amt numeric(18,4),
	deduction_amt numeric(18,4),
	calc_effective_from_date date
)
AS
$BODY$ 
	Declare vSupressPayHead boolean = false;
Begin	
	Drop table if exists PayplanTranTemp;
	Create temp table PayplanTranTemp
	(
		employee_payplan_detail_id bigint,
		employee_id bigint,
		employee_name character varying, 
		payhead_id bigint,
		payhead varchar(50),
		payhead_type varchar(1),
		emolument_amt numeric(18,4),
		deduction_amt numeric(18,4),
		calc_effective_from_date date
	);
	-- 	****	Create cursor and build totals for each group
	DECLARE	
		cur_emp Cursor For (select a.employee_id, a.employee_name from hr.sp_eff_employee(pfrom_date, pto_date, ppayroll_group_id) a);
		debit_opt numeric; credit_opt numeric; debit_pt numeric; credit_pt numeric; debit_cpt numeric; credit_cpt numeric ;
	Begin
		For emp In cur_emp Loop 
			Insert into PayplanTranTemp(employee_payplan_detail_id, employee_id, employee_name, payhead_id, payhead, payhead_type, 
				emolument_amt, deduction_amt, calc_effective_from_date)
			Select c.employee_payplan_detail_id, emp.employee_id, emp.employee_name, c.payhead_id, d.payhead, d.payhead_type, 
				0, 0, a.calc_effective_from_date
			from hr.sp_emp_eff_payplan(emp.employee_id, pfrom_date, pto_date) a 
			inner join hr.employee_payplan_detail c on a.employee_payplan_id=c.employee_payplan_id 
			inner join hr.payhead d on c.payhead_id=d.payhead_id 
			where d.monthly_or_onetime=1
				And c.en_pay_type = 3;
		End loop;
	End;
	
	return query 
	select a.employee_payplan_detail_id, a.employee_id, a.employee_name, a.payhead_id, a.payhead, a.payhead_type, 
		a.emolument_amt, a.deduction_amt, a.calc_effective_from_date
	from PayplanTranTemp a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
Create OR REPLACE Function hr.sp_get_payhead(ppayhead_id bigint, out ppayhead varchar(50))
Returns varchar(50) as
$BODY$
Declare vPayhead varchar(50) = '';
Begin
	-- Fetch the conversion unit
	select payhead into vPayhead from hr.payhead where payhead_id=ppayhead_id;
	
	-- Generate the output
	ppayhead:=vPayhead;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
create or replace function hr.payslip_report(pbranch_id bigint, ppayroll_group_id bigint, pemployee_id bigint, pfrom_date date, pto_date date)
RETURNS TABLE  
(	
	finyear character varying(4),
	branch_id bigint,
	branch_name varchar(100),
	employee_id bigint,
	full_employee_name character varying(320),
	employee_no character varying(50),
	doc_date date,
	payroll_id character varying(50),
	pay_from_date date,
	pay_to_date date,
	pay_days smallint,
	no_pay_days smallint,
	half_pay_days smallint,
	tot_ot_hour numeric(18,2),
	tot_ot_holiday_hour numeric(18,2),
	tot_ot_special_hour numeric(18,2),
	tot_ot_amt numeric(18,2),
	tot_ot_holiday_amt numeric(18,2),
	tot_ot_special_amt numeric(18,2),
	tot_overtime_amt numeric(18,2),
	tot_emolument_amt numeric(18,2),
	tot_deduction_amt numeric(18,2),
	amt_in_words character varying(250),
	payroll_group_id bigint,
	entered_by varchar(100),
	posted_by varchar(100),
	designation varchar(100),
	join_date date,
	bank_account_no character varying(50),
	pf_acc_no varchar(50),
	esic_acc_no varchar(50),
	pan varchar(10),
	tot_working_days smallint
)
AS
$BODY$ 
Begin	
	-- This function is used by the Posting Trigger to get information on the Supplier Payment (PYMT)
	DROP TABLE IF EXISTS payslip_temp;	
	create temp TABLE  payslip_temp
	(	
		finyear character varying(4),
		branch_id bigint,
		branch_name varchar(100),
		employee_id bigint,
		full_employee_name character varying(320),
		employee_no character varying(50),
		doc_date date,
		payroll_id character varying(50),
		pay_from_date date,
		pay_to_date date,
		pay_days smallint,
		no_pay_days smallint,
		half_pay_days smallint,
		tot_ot_hour numeric(18,2),
		tot_ot_holiday_hour numeric(18,2),
		tot_ot_special_hour numeric(18,2),
		tot_ot_amt numeric(18,2),
		tot_ot_holiday_amt numeric(18,2),
		tot_ot_special_amt numeric(18,2),
		tot_overtime_amt numeric(18,2),
		tot_emolument_amt numeric(18,2),
		tot_deduction_amt numeric(18,2),
		amt_in_words character varying(250),
		payroll_group_id bigint,
		entered_by varchar(100),
		posted_by varchar(100),
		designation varchar(100),
		join_date date,
		bank_account_no character varying(50),
		pf_acc_no varchar(50),
		esic_acc_no varchar(50),
		pan varchar(10),
		tot_working_days smallint
		
	);

	Insert into payslip_temp (finyear, branch_id, branch_name, employee_id, full_employee_name, employee_no, doc_date,
			payroll_id, pay_from_date, pay_to_date, pay_days, no_pay_days, half_pay_days, tot_ot_hour, tot_ot_holiday_hour, tot_ot_special_hour,
			tot_ot_amt, tot_ot_holiday_amt, tot_ot_special_amt, tot_overtime_amt, tot_emolument_amt, tot_deduction_amt, amt_in_words,
			payroll_group_id, entered_by, posted_by, join_date, pf_acc_no, esic_acc_no, pan)	
	Select a.finyear, a.branch_id, d.branch_name, b.employee_id, c.full_employee_name, c.employee_no, a.doc_date,
		a.payroll_id, a.pay_from_date, a.pay_to_date, b.pay_days, b.no_pay_days, b.half_pay_days, b.tot_ot_hour, b.tot_ot_holiday_hour, b.tot_ot_special_hour,
		b.tot_ot_amt, b.tot_ot_holiday_amt, b.tot_ot_special_amt, b.tot_overtime_amt, b.tot_emolument_amt, b.tot_deduction_amt, b.amt_in_words,
		c.payroll_group_id, e.entered_by, e.posted_by, c.join_date, coalesce(f.pf_acc_no, ''), coalesce(f.esic_acc_no, ''), coalesce(f.pan, '')
	from hr.payroll_control a
	Inner join hr.payroll_tran b on a.payroll_id = b.payroll_id 
	Inner Join hr.employee c on b.employee_id = c.employee_id
	left join hr.employee_stat_regn f on c.employee_id = f.employee_id
	Inner Join sys.branch d on a.branch_id = d.branch_id
	Inner Join sys.doc_es e on a.payroll_id = e.voucher_id
	where a.pay_from_date >= pfrom_date and a.pay_to_date <=pto_date
		And a.status = 5
		And (b.employee_id = pemployee_id or pemployee_id = 0)
		And (a.payroll_group_id = ppayroll_group_id or ppayroll_group_id = 0)
		And (a.branch_id = pbranch_id or pbranch_id = 0);

	-- Update designation
	Update payslip_temp a
	set designation = d.designation
	From hr.employee_org_detail b 
	Inner join hr.designation d on b.designation_id = d.designation_id
	Where a.employee_id = b.employee_id 
		and b.effective_date = (select max(c.effective_date) from hr.employee_org_detail c
					where c.employee_id = a.employee_id);

	update payslip_temp x
	set tot_working_days = x.pay_days - (select COALESCE(sum(a.no_pay_days),0) no_pay_days from (select a.leave_type_id, b.paid_leave,  
            ((case when a.to_date > pto_date then pto_date else a.To_Date end) -
            (case when a.from_date < pfrom_date then pfrom_date else a.from_date end )) + 1  no_pay_days 
            from   hr.leave  a inner join hr.leave_type b  on a.leave_type_ID = b.leave_type_id 
             Where a.employee_id = x.employee_id and a.from_date <= pto_date and a.to_Date >= pfrom_date)  a where paid_leave = true);

	-- Update Bank Details
	update payslip_temp a
	set bank_account_no = b.bank_account_no	
	From  hr.employee_bank_info b 
	where a.employee_id = b.employee_id
		And b.default_bank = true;
	
	return query 
	select a.finyear, a.branch_id, a.branch_name, a.employee_id, a.full_employee_name, a.employee_no, a.doc_date,
		a.payroll_id, a.pay_from_date, a.pay_to_date, a.pay_days, a.no_pay_days, a.half_pay_days, a.tot_ot_hour, a.tot_ot_holiday_hour, a.tot_ot_special_hour,
		a.tot_ot_amt, a.tot_ot_holiday_amt, a.tot_ot_special_amt, a.tot_overtime_amt, a.tot_emolument_amt, a.tot_deduction_amt, a.amt_in_words,
		a.payroll_group_id, a.entered_by, a.posted_by, a.designation, a.join_date, a.bank_account_no, a.pf_acc_no, a.esic_acc_no, a.pan, a.tot_working_days
	from payslip_temp a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
create or replace function hr.payslip_tran_report(pbranch_id bigint, pemployee_id bigint, pfrom_date date, pto_date date)
RETURNS TABLE  
(	
	payroll_id varchar(50),
	employee_id bigint,
	emolument_payhead_id bigint,
	emolument_payhead character varying(50),
	deduction_payhead_id bigint,
	deduction_payhead character varying(50),
	deduction_amt numeric(18,2),
	emolument_amt numeric(18,2)
)
AS
$BODY$ 
	Declare vSupressPayHead boolean = false;
Begin	
	
	DROP TABLE IF EXISTS payslip_tran_temp;	
	create temp TABLE  payslip_tran_temp
	(	
		payroll_id varchar(50),
		employee_id bigint,
		emolument_payhead_id bigint,
		deduction_payhead_id bigint,
		emolument_payhead character varying(50),
		deduction_payhead character varying(50),
		deduction_amt numeric(18,2),
		emolument_amt numeric(18,2)
	);

	
	DROP TABLE IF EXISTS payslip_emo_temp;	
	create temp TABLE  payslip_emo_temp
	(	
		sl_no smallint,
		payroll_id varchar(50),
		employee_id bigint,
		emolument_payhead_id bigint,
		deduction_payhead_id bigint,
		emolument_payhead character varying(50),
		deduction_payhead character varying(50),
		deduction_amt numeric(18,2),
		emolument_amt numeric(18,2)
	);

	Insert into payslip_emo_temp (sl_no, payroll_id, employee_id, emolument_payhead_id, deduction_payhead_id, deduction_amt, emolument_amt)		
	select row_number() over (order by a.payhead_id), a.payroll_id, a.employee_id, case when payhead_type = 'E' then a.payhead_id else 0 end as emolument_payhead_id, 
		case when payhead_type = 'E' then 0 else a.payhead_id end as deduction_payhead_id, a.deduction_amt, a.emolument_amt
	From hr.payroll_tran_detail a
	inner join hr.payroll_control b on a.payroll_id = b.payroll_id	
	where b.pay_from_date >= pfrom_date and b.pay_to_date <=pto_date
		And b.status = 5	
		And a.employee_id = pemployee_id
		And a.payhead_type = 'E'
                And (b.branch_id = pbranch_id or pbranch_id = 0);

	DROP TABLE IF EXISTS payslip_ded_temp;	
	create temp TABLE  payslip_ded_temp
	(	
		sl_no smallint,
		payroll_id varchar(50),
		employee_id bigint,
		emolument_payhead_id bigint,
		deduction_payhead_id bigint,
		emolument_payhead character varying(50),
		deduction_payhead character varying(50),
		deduction_amt numeric(18,2),
		emolument_amt numeric(18,2)
	);

	Insert into payslip_ded_temp (sl_no, payroll_id, employee_id, emolument_payhead_id, deduction_payhead_id, deduction_amt, emolument_amt)		
	select row_number() over (order by a.payhead_id), a.payroll_id, a.employee_id, case when payhead_type = 'E' then a.payhead_id else 0 end as emolument_payhead_id, 
		case when payhead_type = 'E' then 0 else a.payhead_id end as deduction_payhead_id, a.deduction_amt, a.emolument_amt
	From hr.payroll_tran_detail a
	inner join hr.payroll_control b on a.payroll_id = b.payroll_id	
	where b.pay_from_date >= pfrom_date and b.pay_to_date <=pto_date
		And b.status = 5	
		And a.employee_id = pemployee_id
		And a.payhead_type = 'D'
                And (b.branch_id = pbranch_id or pbranch_id = 0);

	
	Select cast(value as varchar) into vSupressPayHead from sys.settings where key='hr_suppress_payhead_in_pay_slip';
	if vSupressPayHead = '1' Then
		Insert into payslip_tran_temp (payroll_id, employee_id, emolument_payhead_id, deduction_payhead_id, deduction_amt, emolument_amt)		
		select a.payroll_id, a.employee_id, a.emolument_payhead_id, b.deduction_payhead_id, b.deduction_amt, a.emolument_amt
		From payslip_emo_temp a
		left join payslip_ded_temp b on a.sl_no = b.sl_no		
		Where (a.emolument_amt <> 0 or a.deduction_amt <>0);
	Else
		Insert into payslip_tran_temp (payroll_id, employee_id, emolument_payhead_id, deduction_payhead_id, deduction_amt, emolument_amt)		
		select a.payroll_id, a.employee_id, a.emolument_payhead_id, b.deduction_payhead_id, b.deduction_amt, a.emolument_amt
		From payslip_emo_temp a
		left join payslip_ded_temp b on a.sl_no = b.sl_no;
	End If;

	Update payslip_tran_temp  a
	set emolument_payhead = hr.sp_get_payhead(a.emolument_payhead_id), 
		deduction_payhead = hr.sp_get_payhead(a.deduction_payhead_id);

	
	return query 
	select a.payroll_id, a.employee_id, a.emolument_payhead_id, a.emolument_payhead, a.deduction_payhead_id, a.deduction_payhead, a.deduction_amt, a.emolument_amt
	from payslip_tran_temp a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION hr.fn_get_payroll_pay_items(pbranch_id bigint, pto_date date, pvoucher_id varchar(50))  
RETURNS TABLE
(
	payroll_id varchar(50),
	doc_date date,
	pay_from_date date,
	pay_to_date date,
	branch_id bigint,
	payroll_tran_id character varying(50),
	employee_id bigint,
	tot_emolument_amt numeric(18,4),
	tot_deduction_amt numeric(18,4),
        voucher_id varchar(50), 
	employee_no varchar(50),
	full_employee_name character varying(320),
	bank_account_no character varying(50),
	net_amt numeric(18,4),
	block_payment boolean,
	status smallint,
	pay_month varchar(10)
) 
AS
$BODY$
BEGIN	
	return query
	select a.payroll_id, a.doc_date, a.pay_from_date, a.pay_to_date, a.branch_id, b.payroll_tran_id, b.employee_id, b.tot_emolument_amt, b.tot_deduction_amt,
		b.voucher_id, c.employee_no, c.full_employee_name, d.bank_account_no, (b.tot_emolument_amt - b.tot_deduction_amt) as net_amt, b.block_payment, 
		a.status, cast(to_char(to_timestamp(to_char(EXTRACT(MONTH FROM a.pay_from_date), '999'), 'MM'), 'Mon') || ', ' || EXTRACT(YEAR FROM a.pay_from_date) as varchar)
	from hr.payroll_control a
        INNER JOIN hr.payroll_tran b ON a.payroll_id = b.payroll_id
	INNER JOIN hr.employee c ON b.employee_id = c.employee_id
	left JOIN hr.employee_bank_info d ON c.employee_id = d.employee_id and d.default_bank = true
	where a.status = 5
		And (a.branch_id = pbranch_id or pbranch_id = 0)
		And a.doc_date <= pto_date
		And b.voucher_id = pvoucher_id;      
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION hr.paysheet_report(IN ppayroll_group_id bigint, IN pemployee_id bigint, IN pfrom_date date, IN pto_date date)
  RETURNS TABLE(sl_no smallint, dummy smallint, finyear character varying, branch_id bigint, branch_name character varying, employee_id bigint, full_employee_name character varying, employee_no character varying, payroll_id character varying, pay_from_date date, pay_to_date date, payhead_id bigint, payhead_type character varying, payhead character varying, amount numeric, bank_name character varying, bank_account_no character varying, payroll_group_id bigint) AS
$BODY$ 
Begin

	Drop table if exists PaySheetFinal;
	Create temp table PaySheetFinal
	(
		sl_no smallint,
		dummy smallint,
		finyear character varying(4),
		branch_id bigint,
		branch_name varchar(100),
		employee_id bigint,
		full_employee_name character varying(320),
		employee_no character varying(50),
		payroll_id character varying(50),
		pay_from_date date,
		pay_to_date date,
		payhead_id bigint,
		payhead_type varchar(1),
		payhead varchar(50),
		amount numeric(18,4),
		bank_name varchar(250),
		bank_account_no character varying(50),
		payroll_group_id bigint
	);

	Drop table if exists PaySheetFinalTemp;
	Create temp table PaySheetFinalTemp
	(
		sl_no smallint,
		dummy smallint,
		finyear character varying(4),
		branch_id bigint,
		employee_id bigint,
		payroll_id character varying(50),
		pay_from_date date,
		pay_to_date date,
		payhead_id bigint,
		payhead_type varchar(1),
		payhead varchar(50),
		amount numeric(18,4),
		payroll_group_id bigint
	);
	
	-- Step 1: Declare Temp Table required to display error
	DROP TABLE IF EXISTS PaySheetTemp;	
	create temp TABLE  PaySheetTemp
	(	
		sl_no smallint,
		dummy smallint,
		finyear character varying(4),
		branch_id bigint,
		employee_id bigint,
		payroll_id character varying(50),
		pay_from_date date,
		pay_to_date date,
		payhead_id bigint,
		payhead_type varchar(1),
		payhead varchar(50),
		amount numeric(18,4),
		tot_emolument_amt numeric(18,2),
		tot_deduction_amt numeric(18,2),
		emolument_amt numeric(18,2),
		deduction_amt numeric(18,2),
		payroll_group_id bigint
	);

	insert into PaySheetTemp(sl_no, dummy, finyear, branch_id, employee_id, payroll_id, pay_from_date, pay_to_date, payhead_id, payhead_type, payhead,
			amount, tot_emolument_amt, tot_deduction_amt, emolument_amt, deduction_amt, payroll_group_id)
	select c.sl_no, case when c.payhead_type = 'E' then 1
			when c.payhead_type = 'D' then 2
			when c.payhead_type = 'C' then 3
			else 0 
		End as dummy,
		a.finyear, a.branch_id, b.employee_id, a.payroll_id, a.pay_from_date, a.pay_to_date, c.payhead_id, c.payhead_type, d.payhead,
		case when c.payhead_type = 'E' then c.emolument_amt
			when c.payhead_type = 'D' OR c.payhead_type = 'C' then c.deduction_amt
			else 0
		End as amt, 
		b.tot_emolument_amt, b.tot_deduction_amt, c.emolument_amt, c.deduction_amt, a.payroll_group_id
	from hr.payroll_control a
	inner join hr.payroll_tran b on a.payroll_id = b.payroll_id
	inner join hr.payroll_tran_detail c on b.payroll_tran_id = c.payroll_tran_id 
	inner join hr.payhead d on c.payhead_id = d.payhead_id
	where a.status = 5
		And a.pay_from_date >= pfrom_date and a.pay_to_date <=pto_date
		And (a.payroll_group_id = ppayroll_group_id or ppayroll_group_id=0);
	-- Net Payable
	Insert into PaySheetFinalTemp(sl_no, dummy, finyear, branch_id, employee_id, payroll_id, pay_from_date, pay_to_date, payhead_id, payhead_type, payhead, amount, payroll_group_id)
	Select 99, 99, a.finyear, a.branch_id, a.employee_id, a.payroll_id, a.pay_from_date, a.pay_to_date, 1, 'A', 'Net Payable', a.tot_emolument_amt - a.tot_deduction_amt, a.payroll_group_id
	from PaySheetTemp a
	Group by a.finyear, a.branch_id, a.employee_id, a.payroll_id, a.pay_from_date, a.pay_to_date, a.tot_emolument_amt, a.tot_deduction_amt, a.payroll_group_id;
	
	-- -- Add Emoluments
	Insert into PaySheetFinalTemp(sl_no, dummy, finyear, branch_id, employee_id, payroll_id, pay_from_date, pay_to_date, payhead_id, payhead_type, payhead, amount, payroll_group_id)
	Select 99, 1, a.finyear, a.branch_id, a.employee_id, a.payroll_id, a.pay_from_date, a.pay_to_date, 1000999, 'E', 'Total', a.tot_emolument_amt, a.payroll_group_id
	from PaySheetTemp a
	Group by a.finyear, a.branch_id, a.employee_id, a.payroll_id, a.pay_from_date, a.pay_to_date, a.tot_emolument_amt, a.payroll_group_id;
	
	-- Add Deductions
	Insert into PaySheetFinalTemp(sl_no, dummy, finyear, branch_id, employee_id, payroll_id, pay_from_date, pay_to_date, payhead_id, payhead_type, payhead, amount, payroll_group_id)
	Select 99, 2, a.finyear, a.branch_id, a.employee_id, a.payroll_id, a.pay_from_date, a.pay_to_date, 1000999, 'D', 'Total', a.tot_deduction_amt, a.payroll_group_id
	from PaySheetTemp a
	Group by a.finyear, a.branch_id, a.employee_id, a.payroll_id, a.pay_from_date, a.pay_to_date, a.tot_deduction_amt, a.payroll_group_id;

	-- Add Deductions
	Insert into PaySheetFinal(sl_no, dummy, finyear, branch_id, branch_name, employee_id, full_employee_name, employee_no, payroll_id, pay_from_date, pay_to_date, 
			payhead_id, payhead_type, payhead, amount, bank_name, bank_account_no, payroll_group_id)
	Select a.sl_no, a.dummy, a.finyear, a.branch_id, b.branch_name, a.employee_id, c.full_employee_name, c.employee_no, a.payroll_id, a.pay_from_date, a.pay_to_date, 
		a.payhead_id, a.payhead_type, a.payhead, a.amount, '', '', a.payroll_group_id
	from (
		Select a.sl_no, a.dummy, a.finyear, a.branch_id, a.employee_id, a.payroll_id, a.pay_from_date, a.pay_to_date, a.payhead_id, a.payhead_type, a.payhead, a.amount, a.payroll_group_id
		from PaySheetTemp a
		union all 
		Select a.sl_no, a.dummy, a.finyear, a.branch_id, a.employee_id, a.payroll_id, a.pay_from_date, a.pay_to_date, a.payhead_id, a.payhead_type, a.payhead, a.amount, a.payroll_group_id
		from PaySheetFinalTemp a
	) a
	inner join sys.branch b on a.branch_id = b.branch_id
	inner join hr.employee c on a.employee_id = c.employee_id;
	
	Return query
	Select a.sl_no, a.dummy, a.finyear, a.branch_id, a.branch_name, a.employee_id, a.full_employee_name, a.employee_no, a.payroll_id, a.pay_from_date, a.pay_to_date, 
			a.payhead_id, a.payhead_type, a.payhead, a.amount, a.bank_name, a.bank_account_no, a.payroll_group_id
	From PaySheetFinal a;

END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION hr.fn_fin_set_report(IN pfinal_settlement_id character varying)
  RETURNS TABLE(final_settlement_id character varying, doc_date date, employee_id bigint, fin_set_from_date date, fin_set_to_date date, total_pay_amt numeric, total_gratuity_amt numeric, total_leave_salary_amt numeric, net_settlement_amt numeric, net_amt_in_words character varying, resign_type character varying, remarks character varying, employee_no character varying, full_employee_name character varying, join_date date, resign_date date, entered_by character varying, posted_by character varying, gratuity_amt numeric, notice_pay numeric, status smallint) AS
$BODY$ 
Begin	

	DROP TABLE IF EXISTS fin_set_temp;	
	create temp TABLE  fin_set_temp
	(	
		final_settlement_id character varying(50),
		doc_date date,
		employee_id bigint,
		fin_set_from_date date,
		fin_set_to_date date,
		total_pay_amt numeric(18,2),
		total_gratuity_amt numeric(18,2),
		total_leave_salary_amt numeric(18,2),
		net_settlement_amt numeric(18,2),
		net_amt_in_words character varying(250),		
		resign_type character varying(10),
		remarks character varying(500),
		employee_no character varying(50),
		full_employee_name character varying(320),
		join_date date,
		resign_date date,
		entered_by character varying(50),
		posted_by character varying(50),
		gratuity_amt numeric(18,2),
		notice_pay numeric(18,2),
		status smallint
	);

	Insert into fin_set_temp (final_settlement_id,doc_date, employee_id,fin_set_from_date,fin_set_to_date,total_pay_amt,
               total_gratuity_amt,total_leave_salary_amt,net_settlement_amt,net_amt_in_words,resign_type, 
	       remarks, employee_no,full_employee_name,join_date,resign_date,entered_by,posted_by,gratuity_amt,notice_pay, status)
	select a.final_settlement_id, a.doc_date, a.employee_id, a.fin_set_from_date, a.fin_set_to_date, a.total_pay_amt,
               a.total_gratuity_amt, a.total_leave_salary_amt, a.net_settlement_amt, a.net_amt_in_words,
               Case a.en_resign_type When 1 then 'Resigned' When 2 then 'Terminated' End as resign_type, 
	       a.remarks, b.employee_no,  b.full_employee_name, b.join_date, b.resign_date, c.entered_by, 
               c.posted_by, d.gratuity_amt, a.notice_pay, a.status
        from hr.fin_set_control a 
        inner join hr.employee b on a.employee_id=b.employee_id
        inner join sys.doc_es c on c.voucher_id=a.final_settlement_id 
        left outer join hr.fin_set_gratuity_tran d on a.final_settlement_id=d.final_settlement_id
        where a.final_settlement_id=  pfinal_settlement_id;
	

	return query
	select a.final_settlement_id, a.doc_date, a.employee_id, a.fin_set_from_date, a.fin_set_to_date, a.total_pay_amt,
               a.total_gratuity_amt, a.total_leave_salary_amt, a.net_settlement_amt, a.net_amt_in_words,a.resign_type, 
	       a.remarks, a.employee_no, a.full_employee_name,a.join_date,a.resign_date, a.entered_by, 
               a.posted_by, a.gratuity_amt, a.notice_pay, a.status
	from fin_set_temp a;
	       
END;
$BODY$
  LANGUAGE plpgsql;

?==?

CREATE OR REPLACE FUNCTION hr.fn_fin_set_payroll_tran_report(IN pfinal_settlement_id character varying)
  RETURNS TABLE(final_settlement_id character varying, fin_set_payroll_tran_id character varying, emolument_payhead_id bigint, deduction_payhead_id bigint, emolument_payhead character varying, deduction_payhead character varying, emolument_amt numeric, deduction_amt numeric, pay_days smallint, no_pay_days smallint) AS
$BODY$ 
Begin 

	DROP TABLE IF EXISTS fin_set_payroll_temp;	
        create temp TABLE  fin_set_payroll_temp
	(	
		final_settlement_id varchar(50),
		fin_set_payroll_tran_id  varchar(50),
		emolument_payhead_id bigint,
		deduction_payhead_id bigint,
		emolument_payhead character varying(50),
		deduction_payhead character varying(50),
		deduction_amt numeric(18,2),
		emolument_amt numeric(18,2),
		pay_days smallint,
		no_pay_days smallint
	);

	DROP TABLE IF EXISTS fin_set_payroll_emo;	
        create temp TABLE fin_set_payroll_emo
	(	
		final_settlement_id varchar(50),
		fin_set_payroll_tran_id  varchar(50),
		sl_no smallint,
		emolument_payhead_id bigint,
		deduction_payhead_id bigint,
		emolument_payhead character varying(50),
		deduction_payhead character varying(50),
		deduction_amt numeric(18,2),
		emolument_amt numeric(18,2)
	);

	DROP TABLE IF EXISTS fin_set_payroll_ded;	
        create temp TABLE fin_set_payroll_ded
	(	
		final_settlement_id varchar(50),
		fin_set_payroll_tran_id  varchar(50),
		sl_no smallint,
		emolument_payhead_id bigint,
		deduction_payhead_id bigint,
		emolument_payhead character varying(50),
		deduction_payhead character varying(50),
		deduction_amt numeric(18,2),
		emolument_amt numeric(18,2)
	);

	Insert into fin_set_payroll_emo (sl_no, final_settlement_id, fin_set_payroll_tran_id,  emolument_payhead_id, deduction_payhead_id, deduction_amt, emolument_amt)		
	select row_number() over (order by a.payhead_id), a.final_settlement_id, a.fin_set_payroll_tran_id, case when payhead_type = 'E' then a.payhead_id else 0 end as emolument_payhead_id, 
		case when payhead_type = 'E' then 0 else a.payhead_id end as deduction_payhead_id, a.deduction_amt, a.emolument_amt
	From hr.fin_set_payroll_tran_detail a
	where a.final_settlement_id = pfinal_settlement_id
		And a.payhead_type = 'E';

	Insert into fin_set_payroll_ded (sl_no, final_settlement_id, fin_set_payroll_tran_id,  emolument_payhead_id, deduction_payhead_id, deduction_amt, emolument_amt)		
	select row_number() over (order by a.payhead_id), a.final_settlement_id, a.fin_set_payroll_tran_id, case when a.payhead_type = 'D' then 0 else a.payhead_id end as emolument_payhead_id, 
		case when a.payhead_type = 'D' then a.payhead_id  else 0 end as deduction_payhead_id, a.deduction_amt, a.emolument_amt
	From hr.fin_set_payroll_tran_detail a
	where a.final_settlement_id = pfinal_settlement_id
		And a.payhead_type = 'D';

	Insert into fin_set_payroll_temp (final_settlement_id, fin_set_payroll_tran_id,  emolument_payhead_id, deduction_payhead_id, deduction_amt, emolument_amt, pay_days, no_pay_days)
	select a.final_settlement_id, a.fin_set_payroll_tran_id, a.emolument_payhead_id, b.deduction_payhead_id, b.deduction_amt, a.emolument_amt, c.pay_days, c.no_pay_days
	From fin_set_payroll_emo a
	left join fin_set_payroll_ded b on a.sl_no = b.sl_no	
	inner join hr.fin_set_payroll_tran c on a.fin_set_payroll_tran_id=c.fin_set_payroll_tran_id	
	Where c.final_settlement_id = pfinal_settlement_id;  --and (a.emolument_amt <> 0 or a.deduction_amt <>0);

	Update fin_set_payroll_temp  a
	set emolument_payhead = hr.sp_get_payhead(a.emolument_payhead_id), 
		deduction_payhead = hr.sp_get_payhead(a.deduction_payhead_id);

        return query 
	select a.final_settlement_id,a.fin_set_payroll_tran_id,  a.emolument_payhead_id, a.deduction_payhead_id, a.emolument_payhead, 
	a.deduction_payhead, a.emolument_amt, a.deduction_amt, a.pay_days, a.no_pay_days
	from fin_set_payroll_temp a;
	
END;
$BODY$
  LANGUAGE plpgsql VOLATILE

?==?

CREATE OR REPLACE FUNCTION hr.fn_fin_set_gratuity_tran_report(IN pfinal_settlement_id character varying)
  RETURNS TABLE(final_settlement_id character varying, fin_set_gratuity_tran_id character varying, sl_no smallint, slab_from_date date, slab_to_date date, slab_days smallint, gratuity_days smallint, gratuity_amt numeric, unpaid_days smallint, tot_gratuity_days numeric, tot_gratuity_amt numeric, reducible_amt numeric) AS
$BODY$ 
Begin 
        -- create tarn side function for print report
        return query 
	select a.final_settlement_id,a.fin_set_gratuity_tran_id,a.sl_no, a.slab_from_date, a.slab_to_date, a.slab_days,a.gratuity_days,
	a.gratuity_amt, a.unpaid_days, b.gratuity_days as tot_gratuity_days,b.gratuity_amt as tot_gratuity_amt, b.reducible_amt 
	from hr.fin_set_gratuity_tran_detail a 
	inner join hr.fin_set_gratuity_tran b on a.fin_set_gratuity_tran_id = b.fin_set_gratuity_tran_id
	where a.final_settlement_id = pfinal_settlement_id;
END;
$BODY$
  LANGUAGE plpgsql VOLATILE

?==?

CREATE OR REPLACE FUNCTION hr.fn_gratuity_report(IN pgratuity_id character varying)
  RETURNS TABLE(gratuity_id character varying, gratuity_date date, employee_id bigint, full_employee_name character varying, employee_no character varying, join_date date, resign_date date, gratuity_from_date date, gratuity_to_date date, total_amt numeric, sl_no smallint, slab_from_date date, slab_to_date date, slab_days smallint, gratuity_days smallint, amount numeric) AS
$BODY$ 
Begin	

	DROP TABLE IF EXISTS gratuity_temp;	
	create temp TABLE  gratuity_temp
	(	
		gratuity_id character varying(50),
		gratuity_date date,
		employee_id bigint,
		full_employee_name character varying(320),
		employee_no character varying(50),
		join_date date,
		resign_date date,
		gratuity_from_date date,
		gratuity_to_date date,
		total_amt numeric(18,2),
		sl_no smallint,
		slab_from_date date,
		slab_to_date date,
		slab_days smallint,
		gratuity_days smallint,
		amount numeric(18,2)
	);

	Insert into gratuity_temp (gratuity_id, gratuity_date, employee_id, full_employee_name, employee_no, join_date, resign_date,
				   gratuity_from_date, gratuity_to_date, total_amt, sl_no, slab_from_date, slab_to_date, slab_days, gratuity_days, amount)
	
	Select a.gratuity_id, a.doc_date, a.employee_id, c.full_employee_name, c.employee_no, c.join_date, c.resign_date,
	 a.gratuity_from_date, a.gratuity_to_date, a.total_amt,    
         b.sl_no, b.slab_from_date, b.slab_to_date, b.slab_days, b.gratuity_days, b.amount
	from hr.gratuity_control a
	inner join hr.gratuity_tran b on a.gratuity_id=b.gratuity_id
	inner join hr.employee c on a.employee_id=c.employee_id
	where a.gratuity_id= pgratuity_id;
	

	return query
	select 	a.gratuity_id, a.gratuity_date, a.employee_id, a.full_employee_name, a.employee_no, a.join_date, a.resign_date,
	 a.gratuity_from_date, a.gratuity_to_date, a.total_amt,    
         a.sl_no, a.slab_from_date, a.slab_to_date, a.slab_days, a.gratuity_days, a.amount
	from gratuity_temp a;
	       
END;
$BODY$
  LANGUAGE plpgsql VOLATILE

?==?
CREATE OR REPLACE FUNCTION hr.fn_ppt_print(In pvoucher_id varchar(50)) 
RETURNS TABLE
(
	voucher_id varchar(50), 
	doc_date date,
        fc_type_id bigint,
        fc_type varchar(20),
        exch_rate numeric(18,6),
	liability_account_id bigint,
	liability_account_head varchar(250),
	txn_type varchar(50), 
	account_id bigint,
	account_head varchar(250), 
	status smallint,
	credit_amt numeric(18,4), 
	cheque_number varchar(20),
	cheque_date date, 	
	narration varchar(500),
	amt_in_words varchar(250), 
	amt_in_words_fc varchar(250), 
	entered_by varchar(100), 
	posted_by varchar(100)
) 
AS
$BODY$
BEGIN	
	return query
	select a.voucher_id, a.doc_date, a.fc_type_id, e.fc_type, a.exch_rate, f.account_id, b.account_head, 
		case when a.txn_type = 0 then 'Cash Bank'::varchar Else 'Journal'::varchar End as txn_type, 
		a.account_id, c.account_head, a.status, coalesce(a.credit_amt,0) as credit_amt, a.cheque_number, a.cheque_date, 
		a.narration, a.amt_in_words, a.amt_in_words_fc, d.entered_by, d.posted_by
	from ac.vch_control a
	inner join ac.account_head c on a.account_id = c.account_id
	inner join sys.doc_es d on a.voucher_id = d.voucher_id
	inner join ac.fc_type e on a.fc_type_id = e.fc_type_id
	inner join (select g.account_id, g.voucher_id from ac.vch_tran g where g.voucher_id = pvoucher_id limit 1) f on f.voucher_id = a.voucher_id
	inner join ac.account_head b on f.account_id = b.account_id
	where a.voucher_id = pvoucher_id;
	   
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION hr.fn_ppt_tran_print(pvoucher_id varchar(50))  
RETURNS TABLE
(
	payroll_id varchar(50),
	doc_date date,
	pay_from_date date,
	pay_to_date date,
	branch_id bigint,
	payroll_tran_id character varying(50),
	employee_id bigint,
	tot_emolument_amt numeric(18,4),
	tot_deduction_amt numeric(18,4),
        voucher_id varchar(50), 
	employee_no varchar(50),
	full_employee_name character varying(320),
	bank_account_no character varying(50),
	net_amt numeric(18,4),
	block_payment boolean,
	status smallint,
	pay_month varchar(10)
) 
AS
$BODY$
BEGIN	
	return query
	select a.payroll_id, a.doc_date, a.pay_from_date, a.pay_to_date, a.branch_id, b.payroll_tran_id, b.employee_id, b.tot_emolument_amt, b.tot_deduction_amt,
		b.voucher_id, c.employee_no, c.full_employee_name, d.bank_account_no, (b.tot_emolument_amt - b.tot_deduction_amt) as net_amt, b.block_payment, 
		a.status, cast(to_char(to_timestamp(to_char(EXTRACT(MONTH FROM a.pay_from_date), '999'), 'MM'), 'Mon') || ', ' || EXTRACT(YEAR FROM a.pay_from_date) as varchar)
	from hr.payroll_control a
        INNER JOIN hr.payroll_tran b ON a.payroll_id = b.payroll_id
	INNER JOIN hr.employee c ON b.employee_id = c.employee_id
	left JOIN hr.employee_bank_info d ON c.employee_id = d.employee_id and d.default_bank = true
	where  b.voucher_id = pvoucher_id;      
END;
$BODY$
  LANGUAGE plpgsql;

?==?
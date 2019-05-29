CREATE OR REPLACE FUNCTION hr.sp_eff_employee(IN ppay_from date, IN ppay_to date, IN ppayroll_group_id bigint)
  RETURNS TABLE(employee_id bigint, employee_no character varying, employee_name character varying, resign_date date) AS
$BODY$ 
Begin	
	DROP TABLE IF EXISTS eff_employee_temp;
	CREATE temp TABLE  eff_employee_temp
	(
		employee_id bigint, 
		employee_no character varying, 
		employee_name character varying, 
		resign_date date
	);

	Insert into eff_employee_temp(employee_id, employee_no, employee_name, resign_date)
        select a.employee_id, a.employee_no, a.full_employee_name, a.resign_date
	from hr.employee a
        where (a.resign_date > ppay_from or a.resign_date is null) 
		and a.payroll_group_id = ppayroll_group_id
		and a.employee_id in (select distinct(b.employee_id) from hr.employee_payplan b where b.effective_from_date <= ppay_to) ;
		
	return query 	
	select a.employee_id, a.employee_no, a.employee_name, a.resign_date
	from eff_employee_temp a;
	
 END;
 $BODY$ 
   LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION hr.sp_emp_eff_payplan(IN pemployee_id bigint, IN ppay_from_date date, IN ppay_to_date date)
  RETURNS TABLE(employee_id bigint, full_employee_name character varying, resign_date date, employee_payplan_id bigint, effective_from_date date, effective_to_date date, calc_effective_from_date date, calc_effective_to_date date, ot_rate numeric, ot_holiday_rate numeric, ot_special_rate numeric) AS
$BODY$ 
Begin	
	DROP TABLE IF EXISTS eff_employee_temp;
        CREATE temp TABLE  eff_employee_temp
	(	
		employee_id bigint,
		full_employee_name varchar(500),
		resign_date date
	);

	DROP TABLE IF EXISTS employee_payplan_temp;
        CREATE temp TABLE  employee_payplan_temp
	(	
		employee_id bigint, 
		full_employee_name character varying, 
		resign_date date, 
		employee_payplan_id bigint, 
		effective_from_date date, 
		effective_to_date date, 
		calc_effective_from_date date,
		calc_effective_to_date date,
		ot_rate numeric, 
		ot_holiday_rate numeric, 
		ot_special_rate numeric
	);

        -- ****** Insert Effective Employees for that date    
	Insert Into eff_employee_temp(employee_id,full_employee_name, resign_date)
		Select a.employee_id, a.full_employee_name, a.resign_date From hr.employee a
		where (a.resign_date>ppay_from_date or a.resign_date is null) and a.employee_id=pemployee_id;

        -- ***** Insert Employees with their PayPlan 
	Insert Into employee_payplan_temp(employee_id,full_employee_name, resign_date,employee_payplan_id,effective_from_date,effective_to_date,calc_effective_from_date,calc_effective_to_date,ot_rate,ot_holiday_rate,ot_special_rate)
        Select  a.employee_id, a.full_employee_name, a.resign_date, b.employee_payplan_id, b.effective_from_date, b.effective_to_date, b.effective_from_date,
        case when b.effective_to_date is null and a.resign_date is null then ppay_to_date
             when (b.effective_to_date is null and a.resign_date is not null) or (b.effective_to_date is not null and b.effective_to_date > a.resign_date) then (a.resign_date - INTERVAL '1 day')
             when b.effective_to_date is not null then b.effective_to_date end,
        b.ot_rate, b.ot_holiday_rate, b.ot_special_rate
        from eff_employee_temp a
        inner join (select * from hr.employee_payplan x where x.employee_id=pemployee_id and x.effective_from_date <=ppay_to_date and coalesce(x.effective_to_date, ppay_to_date) >= ppay_from_date) b
        on a.employee_id=b.employee_id ;

         -- ***** Delete all PayPlans which are on and after Resignation date    
	delete from employee_payplan_temp a    
        Where a.effective_from_date >= a.resign_date ;   
     
        -- ***** Swap Effective From Date If it is less than Pay From Date & Effective To Date if it is greater than Pay To Date for calculation    
	Update employee_payplan_temp a   
	Set calc_effective_from_date = case when a.calc_effective_from_date < ppay_from_date then ppay_from_date else a.calc_effective_from_date end,    
	calc_effective_to_date = case when a.calc_effective_to_date > ppay_to_date then ppay_to_date else a.calc_effective_to_date end    ;

	return query 	
	select a.employee_id,a.full_employee_name, a.resign_date,a.employee_payplan_id,a.effective_from_date,a.effective_to_date,a.calc_effective_from_date,a.calc_effective_to_date,a.ot_rate,a.ot_holiday_rate,a.ot_special_rate
	from employee_payplan_temp a;
        
END;
 $BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION hr.trgporc_payroll_post()
  RETURNS trigger AS
$BODY$

        Declare vFinYear varchar(4); vCompany_id BigInt=-1; vVoucher_ID varchar(50)=''; vFCType_ID BigInt=-1; vExchRate Numeric(18,6)=1; vDocDate Date;
	vStatus smallint=0; vOldStatus smallint=0; vNarration Varchar(500)=''; vPayroll_ID varchar(50)=''; vEmolumentTotal Numeric(18,2)=0; VDeductionTotal Numeric(18,2)=0;
	vGross_Emolument_Amt Numeric(18,2)=0; vGross_Deduction_Amt Numeric(18,2)=0; vSalaryPayableAccount_id BigInt=-1; vPayFromDate Date; vPayToDate Date; vType varchar(4)='';

BEGIN

    -- **** Get the Existing and new values in the table    
    Select NEW.status, OLD.status, NEW.finyear, New.company_id,NEW.doc_date, NEW.payroll_id, NEW.pay_from_date, NEW.pay_to_date, NEW.gross_emolument_amt, NEW.gross_deduction_amt, NEW.doc_type
    into vStatus, vOldStatus, vFinYear, vCompany_id, vDocDate, vVoucher_ID, vPayFromDate, vPayToDate, vGross_Emolument_Amt, vGross_Deduction_Amt, vType;
   
    -- ***** Unpost the voucher  
    If vStatus<=4 and vOldStatus=5 then
    
        -- *** Fire the stored procedure to unpost the entry
        perform ac.sp_gl_unpost(vVoucher_ID);
        
        --perform ac.sp_subhead_unpost(vVoucher_ID);

        perform hr.sp_paysheet_ledger_unpost(vVoucher_ID);

    End if;

    If vStatus=5 and vOldStatus<=4 then

	vNarration = 'Being payroll generated for the month of ' ||  to_char(vPayFromDate,'Mon') || ' ' || to_char(vPayFromDate,'YYYY') || '.';

--         Select cast(value as varchar) into vSalaryPayableAccount_id from sys.settings where key='hr salary payable account';
-- 
--         if vSalaryPayableAccount_id = 0 then
-- 		RAISE EXCEPTION 'Salary Payable Account not associated. Could not Authorise.';
-- 	End If;
	
	DROP TABLE IF EXISTS Employee_org_detail_temp;
	create temp TABLE  Employee_org_detail_temp
	(	
		employee_id BigInt,
		employee_full_name varchar(500)
	)
	on commit drop;	

	Insert Into Employee_org_detail_temp(employee_id, employee_full_name)
	Select a.employee_ID, c.full_employee_name
	from
	   (
		Select Distinct employee_ID from hr.payroll_tran_detail
		Where payroll_id = vVoucher_ID
	   ) a
	Left Outer Join hr.fn_eff_date_emp_for_payroll_posting(vPayFromDate, vPayToDate) b On a.employee_ID = b.employee_ID
	Inner Join hr.employee c On a.employee_ID = c.employee_ID
	Where b.employee_id Is Null;

        If Exists(select * from Employee_org_detail_temp) Then
            RAISE EXCEPTION 'Organisation details not associated to employees. Could not Authorise.';
	End If;

	-- ***	Fire the stored procedure to post the entry in General Ledger
	perform ac.sp_gl_post('hr.payroll_control', vFinYear, vVoucher_ID, vDocDate, vFCType_ID, vExchRate, '', vNarration, vType);
		
	-- ***	Fire the stored procedure to post the entry in Subhead Ledger
	--perform ap.sp_subhead_post('hr.payroll_control', vFinYear, Voucher_ID, vDocDate, vFCType_ID, vExchRate,  vNarration, vStatus);

	-- ***	Fire the stored procedure to post the entry in pays Ledger
	perform hr.sp_paysheet_ledger_post(vVoucher_ID, vPayFromDate, vPayToDate, vFinYear, vDocDate);

    End IF;
    RETURN NEW;
END
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE TRIGGER trg_payroll_post
  AFTER UPDATE
  ON hr.payroll_control
  FOR EACH ROW
  EXECUTE PROCEDURE hr.trgporc_payroll_post();

?==?
CREATE OR REPLACE FUNCTION hr.sp_paysheet_ledger_unpost(pvoucher_id character varying)
  RETURNS void AS
$BODY$
Begin
	-- Delete paysheet Ledger tran
	Delete from hr.paysheet_ledger_tran where paysheet_ledger_id in (select paysheet_ledger_id from hr.paysheet_ledger where payroll_id=pvoucher_id);

	-- Delete paysheet Ledger(pVoucher_ID, 'AJ:'|| pVoucher_ID);
        Delete from hr.paysheet_ledger where payroll_id =pvoucher_id;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION hr.sp_paysheet_ledger_post(pvoucher_id character varying, ppay_from_date date, ppay_to_date date, pfin_year character varying, pdoc_date date)
  RETURNS void AS
$BODY$ 
Begin	
	Insert into hr.paysheet_ledger (paysheet_ledger_id, company_id, branch_id, finyear, doc_date, employee_id, payroll_id, subhead_id,      
	pay_from_date, pay_to_date, pay_days, no_pay_days, half_pay_days, tot_ot_hour,tot_ot_holiday_hour, tot_ot_special_hour, tot_ot_amt, tot_ot_holiday_amt,
        tot_ot_special_amt, tot_overtime_amt, tot_emolument_amt, tot_deduction_amt, amt_in_words) 

	Select sys.sp_gl_create_id(a.payroll_id,b.employee_id,0,0),a.company_id,a.branch_id, a.finyear, pdoc_date, b.employee_id, a.payroll_id, c.sub_head_id,
                ppay_from_date, ppay_to_date, b.pay_days, b.no_pay_days, b.half_pay_days, b.tot_ot_hour, b.tot_ot_holiday_hour,      
		b.tot_ot_special_hour, b.tot_ot_amt, b.tot_ot_holiday_amt, b.tot_ot_special_amt, b.tot_overtime_amt, b.tot_emolument_amt,      
		b.tot_deduction_amt, b.amt_in_words     
  
	From hr.payroll_control a
	inner join hr.payroll_tran b on a.payroll_id=b.payroll_id
	left outer join hr.fn_eff_date_emp_for_payroll_posting (ppay_from_date, ppay_to_date) c On b.employee_id = c.employee_id      
	where a.payroll_id=pvoucher_id ;     

END;
$BODY$
  LANGUAGE plpgsql;

?==?



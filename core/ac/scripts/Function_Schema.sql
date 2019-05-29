CREATE OR REPLACE FUNCTION ac.fn_gl_criteria(IN pcategory varchar(50))
  RETURNS TABLE(account_type_id bigint, account_type_desc varchar(50)) AS
$BODY$
BEGIN  
	DROP TABLE IF EXISTS gl_criteria_temp;
	CREATE temp TABLE  gl_criteria_temp
	(	
		account_type_id bigint,
		account_type_desc varchar(50)
	);
	if pcategory='Bank' then
		Insert Into gl_criteria_temp(account_type_id,account_type_desc )
		Select a.account_type_id, a.account_type_desc From ac.account_type a
		Where a.account_type_id = 1;
	End if;

	if pcategory='Cash' then
		Insert Into gl_criteria_temp(account_type_id, account_type_desc)
		Select a.account_type_id, a.account_type_desc From ac.account_type a
		Where a.account_type_id in (2,32);
	End if;	

	if pcategory='GL' then
		Insert Into gl_criteria_temp(account_type_id, account_type_desc)
		Select a.account_type_id, a.account_type_desc From ac.account_type a
		Where a.account_type_id  NOT IN (0,1,2,7,12,32, 46, 47);
	End if;

	if pcategory='Debtors' then
		Insert Into gl_criteria_temp(account_type_id, account_type_desc)
		Select a.account_type_id, a.account_type_desc From ac.account_type a
		Where a.account_type_id IN (7);
	End if;

	if pcategory='Creditors' then
		Insert Into gl_criteria_temp(account_type_id, account_type_desc)
		Select a.account_type_id, a.account_type_desc From ac.account_type a
		Where a.account_type_id IN (12);
	End if;
	return query 
	select a.account_type_id , a.account_type_desc from gl_criteria_temp a;

END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE FUNCTION ac.fn_gl_criteria_accounts(IN pcategory varchar(50))
  RETURNS TABLE(account_id bigint, account_head varchar(250)) AS
$BODY$
BEGIN  
	DROP TABLE IF EXISTS gl_criteria_accounts_temp;
	CREATE temp TABLE  gl_criteria_accounts_temp
	(	
		account_id bigint,
		account_head varchar(250)
	);

	insert into gl_criteria_accounts_temp(account_id,account_head)
		select a.account_id, a.account_head
		from ac.account_head a 
		inner join ac.fn_gl_criteria(pcategory) b on a.account_type_id = b.account_type_id;
	
	return query 
	select a.account_id , a.account_head from gl_criteria_accounts_temp a;

END;
$BODY$
  LANGUAGE plpgsql;
?==?



CREATE FUNCTION ac.fn_gl_sum_of_tran(IN pcompany_id bigint, IN pbranch_id bigint, IN pyear varchar(4), IN paccount_id bigint, IN pfrom_date date, IN pcategory varchar(50))
  RETURNS TABLE(debits_for_the_period numeric, credits_for_the_period numeric, account_id bigint) AS
$BODY$
begin
	DROP TABLE IF EXISTS gl_sum_of_tran_temp;
	create temp TABLE  gl_sum_of_tran_temp
	(	
		 debits_for_the_period numeric(18,4),
		 credits_for_the_period numeric(18,4),
		 account_id bigint 
	 );

	--	*****	first step fetch the total transactions during the year before the from date
	if paccount_id > 0 
	Then -- specific account only
		insert into gl_sum_of_tran_temp(debits_for_the_period, credits_for_the_period, account_id)
			select  sum(a.debit_amt) as debits_for_the_period,
				sum(a.credit_amt) as credits_for_the_period,
				a.account_id	 
			from ac.general_ledger a
			where a.company_id=pcompany_id and a.finyear=pyear and a.doc_date < pfrom_date and a.account_id=paccount_id
				and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
			group by a.account_id;
	else
		-- all accounts in the category
		insert into gl_sum_of_tran_temp(debits_for_the_period, credits_for_the_period, account_id)
			select  sum(a.debit_amt) as debits_for_the_period,
				sum(a.credit_amt) as credits_for_the_period,
				a.account_id	 
			from ac.general_ledger a
			inner join ac.fn_gl_criteria_accounts(pcategory) b on a.account_id=b.account_id
			where a.company_id=pcompany_id and a.finyear=pyear and a.doc_date < pfrom_date
				and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
			group by a.account_id;
	end if;

		return query 
		select a.debits_for_the_period , a.credits_for_the_period , a.account_id from gl_sum_of_tran_temp a;
END;
$BODY$
  LANGUAGE plpgsql;
?==?


CREATE FUNCTION ac.fn_gl_op_bal(IN pcompany_id bigint, IN pbranch_id bigint, IN pyear varchar(4), IN paccount_id bigint, IN pfrom_date date, IN pcategory varchar(50))
  RETURNS TABLE(account_id bigint, debit_balance numeric, credit_balance numeric) AS
$BODY$
Begin 
	DROP TABLE IF EXISTS gl_op_bal_temp;
	CREATE temp TABLE  gl_op_bal_temp
	(
		account_id bigint,
		debit_balance numeric(18,4),
		credit_balance numeric(18,4)
	 );

	--	*****	second step add the opening balance for the year and sum of transactions before the from date
	if paccount_id > 0
	then --	specific account only
		insert into gl_op_bal_temp(account_id, debit_balance, credit_balance)
		select 	a.account_id, sum(a.debit_balance), sum(a.credit_balance)
		from (	select c.account_id, c.debit_balance, c.credit_balance 
				from ac.account_balance  c
				where c.company_id=pcompany_id and c.finyear=pyear and c.account_id=paccount_id
					and (c.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
				union all
				select c.account_id, c.debits_for_the_period, c.credits_for_the_period
				from ac.fn_gl_sum_of_tran(pcompany_id, pbranch_id, pyear, paccount_id, pfrom_date, pcategory) c
			) a
		group by a.account_id;
	else
		insert into gl_op_bal_temp(account_id, debit_balance, credit_balance)
		select 	a.account_id, sum(a.debit_balance), sum(a.credit_balance)
		from (	select c.account_id, c.debit_balance, c.credit_balance 
				from ac.account_balance c
				where c.company_id=pcompany_id and c.finyear=pyear
					and (c.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
				union all
				select c.account_id, c.debits_for_the_period, c.credits_for_the_period
				from ac.fn_gl_sum_of_tran(pcompany_id, pbranch_id, pyear, paccount_id, pfrom_date, pcategory) c
			) a			
		inner join ac.fn_gl_criteria_accounts(pcategory) b on a.account_id=b.account_id
		group by a.account_id;
	end if;

	return query 
	select a.account_id , a.debit_balance, a.credit_balance from gl_op_bal_temp a;

END;
$BODY$
  LANGUAGE plpgsql;
?==?

CREATE FUNCTION ac.fn_gl_tran(IN pcompany_id bigint, IN pbranch_id bigint, IN pyear varchar(4), IN paccount_id bigint, IN pfrom_date date, IN pto_date date, IN pcategory varchar(50))
  RETURNS TABLE(account_id bigint, doc_date date, voucher_id varchar(50), account_affected_id bigint, narration varchar(500), cheque_details varchar(250), debit_amt_fc numeric, credit_amt_fc numeric, fc_type_id bigint, exch_rate numeric, debit_amt numeric, credit_amt numeric) AS
$BODY$
begin 
	DROP TABLE IF EXISTS gl_tran_temp;
	CREATE temp TABLE gl_tran_temp
	(
		account_id bigint, 
		doc_date date, 
		voucher_id varchar(50), 
		account_affected_id bigint, 
		narration varchar(500), 
		cheque_details varchar(250),  
		debit_amt_fc numeric(18,4), 
		credit_amt_fc numeric(18,4), 
		fc_type_id bigint, 
		exch_rate numeric(18,8), 
		debit_amt numeric(18,4), 
		credit_amt numeric(18,4)
	);

	--	****	third step extract only the data required for the period ( from_date upto to_date)
	if paccount_id > 0
	then -- specific account
		insert into gl_tran_temp(account_id, doc_date, voucher_id, account_affected_id, narration, cheque_details, debit_amt_fc, credit_amt_fc,
					fc_type_id , exch_rate, debit_amt, credit_amt)
		select a.account_id, a.doc_date, a.voucher_id, 
			a.account_affected_id, a.narration, a.cheque_details,
			a.debit_amt_fc, a.credit_amt_fc, a.fc_type_id, a.exch_rate,
			a.debit_amt, a.credit_amt
		from ac.general_ledger a
		where a.company_id=pcompany_id and a.finyear=pyear and a.account_id=paccount_id
				and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
				and a.doc_date between pfrom_date and pto_date;
	else
	-- all accounts of the category
		insert into gl_tran_temp(account_id, doc_date, voucher_id, account_affected_id, narration, cheque_details, debit_amt_fc, credit_amt_fc,
					fc_type_id , exch_rate, debit_amt, credit_amt)
		select a.account_id, a.doc_date, a.voucher_id, 
			a.account_affected_id, a.narration, a.cheque_details,
			a.debit_amt_fc, a.credit_amt_fc, a.fc_type_id, a.exch_rate,
			a.debit_amt, a.credit_amt
		from ac.general_ledger a
		inner join ac.fn_gl_criteria_accounts(pcategory) b on a.account_id=b.account_id
		where a.company_id=pcompany_id and a.finyear=pyear and a.doc_date between pfrom_date and pto_date
				and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0);
	end if;

	return query 
	select a.account_id, a.doc_date, a.voucher_id, a.account_affected_id, a.narration, a.cheque_details, a.debit_amt_fc, 
		a.credit_amt_fc, a.fc_type_id, a.exch_rate, a.debit_amt, a.credit_amt from gl_tran_temp a;

END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.fn_gl_report(
    IN pcompany_id bigint,
    IN pbranch_id bigint,
    IN pyear character varying,
    IN paccount_id bigint,
    IN pfrom_date date,
    IN pto_date date,
    IN pcategory character varying,
    IN psuppress_blank boolean)
RETURNS TABLE
(   account_id bigint, 
    account_head character varying, 
    acc_head character varying, 
    debit_balance numeric, 
    credit_balance numeric, 
    doc_date date, 
    sorted_voucher_id character varying, 
    voucher_id character varying, 
    account_head_affected_description character varying, 
    narration character varying, 
    cheque_details character varying, 
    debit_amt numeric, 
    credit_amt numeric, 
    fc_type_id bigint, 
    fc_type character varying, 
    exch_rate numeric, 
    debit_amt_fc numeric, 
    credit_amt_fc numeric, 
    group_key character varying,
    account_affected_id bigint
) 
AS
$BODY$
Begin 
	DROP TABLE IF EXISTS gl_report_temp;
	CREATE temp TABLE  gl_report_temp
	(
		account_id bigint,
		account_head varchar(250),
		acc_head varchar(250),
		debit_balance numeric(18,4), 
		credit_balance numeric(18,4), 
		doc_date date, 
		sorted_voucher_id varchar(50),
		voucher_id varchar(50), 
		account_head_affected_description varchar(250), 
		narration varchar(500),
		cheque_details varchar(250), 
		debit_amt numeric(18,4), 
		credit_amt numeric(18,4),
		fc_type_id bigint, 
		fc_type varchar(20), 
		exch_rate numeric(18,8), 
		debit_amt_fc numeric(18,4), 
		credit_amt_fc numeric(18,4), 
		group_key varchar(20),
                account_affected_id bigint
	 );

	--	*****	second step add the opening balance for the year and sum of transactions before the from date
	
		insert into gl_report_temp(account_id ,account_head,acc_head, debit_balance, credit_balance, doc_date, 
		sorted_voucher_id, voucher_id, account_head_affected_description, narration,	cheque_details, 
		debit_amt, credit_amt, fc_type_id, fc_type, exch_rate, debit_amt_fc, credit_amt_fc, group_key, account_affected_id)
			
                select 	a.account_id,
                        case when c.account_code <> '' then c.account_code ||' - '|| c.account_head else c.account_head end, c.account_head,
                        case when (a.debit_balance - a.credit_balance)<0 then 0 else a.debit_balance - a.credit_balance end as debit_balance,
                        case when (a.credit_balance-a.debit_balance)<0 then 0 else a.credit_balance-a.debit_balance end as credit_balance,
                        b.doc_date, b.voucher_id, 
                        b.voucher_id, 
                        case when d.account_code <> '' then d.account_code ||' - '|| d.account_head else d.account_head end, 
                        b.narration, 
                        b.cheque_details, b.debit_amt, b.credit_amt,
                        b.fc_type_id, e.fc_type, b.exch_rate, b.debit_amt_fc, b.credit_amt_fc, f.group_key, b.account_affected_id
                from ac.fn_gl_op_bal (pcompany_id, pbranch_id, pyear, paccount_id, pfrom_date, pcategory) a
                left join ac.fn_gl_tran(pcompany_id, pbranch_id, pyear, paccount_id, pfrom_date, pto_date, pcategory) b on a.account_id=b.account_id
                inner join ac.account_head c on a.account_id=c.account_id
                left join ac.account_head d on b.account_affected_id=d.account_id
                left join ac.fc_type e on b.fc_type_id=e.fc_type_id
                inner join ac.account_group f on c.group_id = f.group_id
                order by c.account_head, b.doc_date;

		If psuppress_blank and paccount_id <= 0 Then			
				Delete from gl_report_temp
				where debit_balance=0 and credit_balance=0 and doc_date is null;
		End If;

		return query 
		select a.account_id , a.account_head, a.acc_head, a.debit_balance, a.credit_balance, a.doc_date, 
		a.sorted_voucher_id, a.voucher_id, a.account_head_affected_description, a.narration,a.cheque_details, 
		a.debit_amt, a.credit_amt, a.fc_type_id, a.fc_type, a.exch_rate, a.debit_amt_fc, a.credit_amt_fc, a.group_key, a.account_affected_id 
		from gl_report_temp a
		Order by a.account_head, a.doc_date, a.voucher_id;

END;
$BODY$
  LANGUAGE plpgsql;

?==?

CREATE or Replace FUNCTION ac.fn_tb_op_tran_cl(IN pcompany_id bigint, IN pbranch_id bigint, IN pyear varchar(4), IN pfrom_date date, IN pto_date date)
RETURNS TABLE
(	account_id bigint, 
	debit_opening_balance numeric(18,4), 
	credit_opening_balance numeric(18,4), 
	period_debits numeric(18,4), 
	period_credits numeric(18,4), 
	debit_closing_balance numeric(18,4), 
	credit_closing_balance numeric(18,4)) 
AS
$BODY$
BEGIN

	-- Step 1: Generate Opening Balance
	DROP TABLE IF EXISTS tb_ac_temp;
	CREATE temp TABLE  tb_ac_temp
	(	account_id bigint Primary Key,
		debit_opening_balance numeric(18,4), 
		credit_opening_balance numeric(18,4), 
		period_debits numeric(18,4), 
		period_credits numeric(18,4),
		debit_closing_balance numeric(18,4), 
		credit_closing_balance numeric(18,4));

	-- Calculate the opening balances before FromDate for the FinYear
	Insert Into tb_ac_temp(account_id, debit_opening_balance, credit_opening_balance, 
		period_debits, period_credits, 
		debit_closing_balance, credit_closing_balance)
	select 	g.account_id, 
		Case When Sum(g.debit_balance-g.credit_balance)>=0 Then Sum(g.debit_balance-g.credit_balance) Else 0 End, 
		Case When Sum(g.debit_balance-g.credit_balance)<0 Then Sum(g.credit_balance-g.debit_balance) Else 0 End, 
		sum(g.debit_balance_period), sum(g.credit_balance_period), 
		0.00, 0.00
	from (
		Select a.account_id, Sum(a.debit_balance) as debit_balance, Sum(a.credit_balance) as credit_balance,
			0.00 as debit_balance_period, 0.00 as credit_balance_period
		From ac.account_balance a
		Where a.finyear=pyear 
			And a.company_id=pcompany_id 
			And (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_id=0)
		Group By a.account_id
		Union All -- GL Summary Before From Date
		Select a.account_id, Sum(a.debit_amt), Sum(a.credit_amt), 0.00, 0.00
		From ac.general_ledger  a 
		Where a.finyear=pyear  
			And a.doc_date<pfrom_date 
			And a.company_id=pcompany_id 
			And (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_id=0)
		Group By a.account_id
		Union All -- GL Summary Between From And To
		Select a.account_id, 0.00, 0.00, Sum(a.debit_amt), Sum(a.credit_amt)
		From ac.general_ledger a
		Where a.finyear=pyear  
			And a.doc_date Between pfrom_date And pto_date 
			And a.company_id=pcompany_id
			And (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_id=0)
		Group By a.account_id
	   ) g
	group by g.account_id;
	
	
	--	Second Step: Update Closing Balance
	Update tb_ac_temp a
	Set  debit_closing_balance = Case When (a.debit_opening_balance-a.credit_opening_balance+a.period_debits-a.period_credits)>=0 
					Then a.debit_opening_balance-a.credit_opening_balance+a.period_debits-a.period_credits Else 0 End,
	     credit_closing_balance = Case When (a.debit_opening_balance-a.credit_opening_balance+a.period_debits-a.period_credits)<0 
					Then (a.debit_opening_balance-a.credit_opening_balance+a.period_debits-a.period_credits) * -1 Else 0 End;	
		

return query
select a.account_id, a.debit_opening_balance, a.credit_opening_balance, a.period_debits, a.period_credits, a.debit_closing_balance,
	a.credit_closing_balance
from tb_ac_temp a;

END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.fn_tb_report(IN pcompany_id bigint, IN pbranch_id bigint, IN pyear character varying, IN pfrom_date date, IN pto_date date)
  RETURNS TABLE(account_id bigint, 
                account_head character varying, 
                account_code character varying, 
                parent_key character varying, 
                group_key character varying, 
                group_code character varying, 
                group_name character varying, 
                group_path character varying, 
                debit_opening_balance numeric, 
                credit_opening_balance numeric, 
                period_debits numeric, 
                period_credits numeric, 
                debit_closing_balance numeric, 
                credit_closing_balance numeric, 
                debit_opening_total numeric, 
                credit_opening_total numeric, 
                period_debits_total numeric, 
                period_credits_total numeric, 
                debit_closing_total numeric, 
                credit_closing_total numeric, 
                opening_bal_bit bigint) 
AS
$BODY$
DECLARE diff  numeric(18,4) = 0;
BEGIN

	DROP TABLE IF EXISTS tb_report;
	CREATE temp TABLE  tb_report
	(
		account_id bigint, 
		account_head varchar(250), 
		account_code varchar(20),
		parent_key varchar(20),
		group_key varchar(20),
		group_code varchar(10),
		group_name varchar(250),
		group_path varchar(3500), 
		debit_opening_balance numeric(18,4), 
		credit_opening_balance numeric(18,4), 
		period_debits numeric(18,4), 
		period_credits numeric(18,4), 
		debit_closing_balance numeric(18,4), 
		credit_closing_balance numeric(18,4),
		debit_opening_total numeric(18,4), 
		credit_opening_total numeric(18,4), 
		period_debits_total numeric(18,4), 
		period_credits_total numeric(18,4), 
		debit_closing_total numeric(18,4), 
		credit_closing_total numeric(18,4),
		opening_bal_bit bigint
	);

	--	*****	Final Third Step: Get the related Description of the Accounts and Groups

	
	-- **** Sub Step 1: Fetch Data into a Temp Table with Control Accounts
        DROP TABLE IF EXISTS tb_report_temp;
	CREATE temp TABLE  tb_report_temp
	(
		account_id bigint, 
		debit_opening_balance numeric(18,4), 
		credit_opening_balance numeric(18,4), 
		period_debits numeric(18,4), 
		period_credits numeric(18,4), 
		debit_closing_balance numeric(18,4), 
		credit_closing_balance numeric(18,4)
	);
	
	Insert into tb_report_temp(account_id, debit_opening_balance, credit_opening_balance, 
				period_debits, period_credits, 
				debit_closing_balance, credit_closing_balance)
	Select a.account_id, a.debit_opening_balance, a.credit_opening_balance, 
				a.period_debits, a.period_credits, 
				a.debit_closing_balance, a.credit_closing_balance
	From ac.fn_tb_op_tran_cl(pcompany_id, pbranch_id, pyear, pfrom_date, pto_date) a;	

        
	-- Update Customers
	update tb_report_temp
	set account_id=a.control_account_id
	From ar.customer a
	where tb_report_temp.account_id=a.customer_id;
        
	-- Update Suppliers
	update tb_report_temp
	set account_id=a.control_account_id
	From ap.supplier a
	where tb_report_temp.account_id=a.supplier_id;
        
	
	Insert Into tb_report(account_id, account_head, account_code, parent_key, group_key, group_code, group_name, group_path, 
				debit_opening_balance, credit_opening_balance, 
				period_debits, period_credits, 
				debit_closing_balance, credit_closing_balance, opening_bal_bit)
	select 	b.account_id, b.account_head, b.account_code, a.parent_key, a.group_key, a.group_code, a.group_name, a.group_path,
		Case When (b.debit_opening_balance - b.credit_opening_balance) >= 0 Then b.debit_opening_balance - b.credit_opening_balance Else 0 End, 
		Case When (b.debit_opening_balance - b.credit_opening_balance) < 0 Then (b.debit_opening_balance - b.credit_opening_balance) * -1 Else 0 End, 
		b.period_debits, b.period_credits, 
		Case When (b.debit_closing_balance - b.credit_closing_balance) >= 0 Then b.debit_closing_balance - b.credit_closing_balance Else 0 End,
		Case When (b.debit_closing_balance - b.credit_closing_balance) < 0 Then (b.debit_closing_balance - b.credit_closing_balance) * -1 Else 0 End, 
		0
	from ac.account_group a
	left join 
		(Select	a.account_id, b.account_head, b.account_code, b.group_id, 
			sum(a.debit_opening_balance) as debit_opening_balance, sum(a.credit_opening_balance) as credit_opening_balance,
			sum(a.period_debits) as period_debits, sum(a.period_credits) as period_credits,
			sum(a.debit_closing_balance) as debit_closing_balance, sum(a.credit_closing_balance) as credit_closing_balance,0
		From tb_report_temp a
		inner join ac.account_head b on a.account_id=b.account_id
		group by a.account_id, b.account_code, b.account_head, b.group_id
		) b on a.group_id=b.group_id;


      
	 --	*****	Resolve opening Balance differences
	 diff := (select sum(debit_balance-credit_balance) as diff from ac.account_balance where finyear=pyear 
											and (branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_id=0));
	 IF diff!=0 then
		Insert Into tb_report(account_id, account_head, account_code, parent_key, group_key, group_code, group_name, group_path,debit_opening_balance, 
				      credit_opening_balance, period_debits, period_credits,debit_closing_balance, credit_closing_balance,opening_bal_bit )
		select -1, 'Opening balance difference', 'A8', '0', 'A8', 'A8','Opening Balance Difference', 'A8', Case When diff< 0 Then (diff *(-1)) Else  0 END,
		Case When diff< 0 Then  0 Else diff END, 0, 0, Case When diff< 0 Then (diff *(-1)) Else 0 END,Case When diff< 0 Then 0 Else diff END,1 ;
	END IF;

	-- 	****	Create cursor and build totals for each group
	DECLARE	
		cur_group Cursor For (Select a.group_path from (Select a.group_path from ac.account_group a
					Union All
					Select 'A8') a Order By a.group_path);
		debit_opt numeric; credit_opt numeric; debit_pt numeric; credit_pt numeric; debit_cpt numeric; credit_cpt numeric ;
	Begin
		For ac_group In cur_group Loop 

			Select 	coalesce(Sum(a.debit_opening_balance), 0), coalesce(Sum(a.credit_opening_balance), 0), 
				coalesce(Sum(a.period_debits), 0), coalesce(Sum(a.period_credits), 0), 
				coalesce(Sum(a.debit_closing_balance), 0), coalesce(Sum(a.credit_closing_balance), 0) , 0
				Into debit_opt, credit_opt, debit_pt, credit_pt, debit_cpt, credit_cpt 
			From tb_report a
			Where a.group_path like ac_group.group_path || '%'; 

			Update tb_report a
			Set 	debit_opening_total=debit_opt,
				credit_opening_total=credit_opt,
				period_debits_total=debit_pt, 
				period_credits_total=credit_pt, 
				debit_closing_total=debit_cpt, 
				credit_closing_total=credit_cpt
			Where a.group_path=ac_group.group_path;
			
		End loop;
	End;

	return query
	Select	a.account_id, a.account_head, a.account_code, a.parent_key, a.group_key, a.group_code, a.group_name, a.group_path, 
		a.debit_opening_balance, a.credit_opening_balance, a.period_debits, a.period_credits, a.debit_closing_balance, a.credit_closing_balance,
		a.debit_opening_total, a.credit_opening_total, a.period_debits_total, a.period_credits_total, a.debit_closing_total, a.credit_closing_total,a.opening_bal_bit
	From tb_report a
	order by a.group_path, a.account_head;
END;
$BODY$
  LANGUAGE plpgsql;


?==?
CREATE OR REPLACE FUNCTION ac.fn_vch_report(IN pvoucher_id varchar(50))
  RETURNS TABLE 
  (
	voucher_id varchar(50),
	doc_date date,
	branch_id bigint,
	fc_type_id bigint,
	account_id bigint,
	dc character,
	credit_amt numeric,
	credit_amt_fc numeric,
	debit_amt numeric,
	debit_amt_fc numeric,
	currency_displayed varchar(50),
	fc_type varchar(50),
	cheque_number varchar(20),
	is_inter_branch boolean,
	exch_rate numeric,
	narration varchar(500),
	status smallint,
	account_head varchar(250),
	branch_code varchar(2),
	amt_in_words varchar(250),
	amt_in_words_fc varchar(250),
	vch_caption varchar(100),
	bank_charges numeric,
	pdc_id varchar(50),
	cheque_date date,
	remarks varchar(500),
	cheque_bank varchar(50),
	cheque_branch varchar(50),
	entered_by varchar(100),
	posted_by varchar(100),
	is_ac_payee boolean,
	is_non_negotiable boolean
) As
$BODY$
Begin 
        -- create control side function for print report
	return query 
	select a.voucher_id, a.doc_date, a.branch_id, a.fc_type_id, a.account_id, a.dc, a.credit_amt, 
	a.credit_amt_fc, a.debit_amt, a.debit_amt_fc, c.currency_displayed, d.fc_type, 
	a.cheque_number, a.is_inter_branch, a.exch_rate, a.narration, a.status, b.account_head, 
	c.branch_code, (initcap(a.amt_in_words)::varchar) as amt_in_words, (initcap(a.amt_in_words_fc)::varchar) as amt_in_words_fc, 
        a.vch_caption, a.bank_charges,
	a.pdc_id, a.cheque_date, a.remarks, a.cheque_bank, a.cheque_branch, e.entered_by, e.posted_by, a.is_ac_payee, a.is_non_negotiable
	from ac.vch_control a
		INNER JOIN ac.account_head b on a.account_id = b.account_id
		INNER JOIN sys.branch c on a.branch_id = c.branch_id
		INNER JOIN ac.fc_type d on a.fc_type_id = d.fc_type_id
		Inner Join sys.doc_es e on a.voucher_id = e.voucher_id
			where a.voucher_id = pvoucher_id;

END;
$BODY$
  LANGUAGE plpgsql;

?==?

CREATE OR REPLACE FUNCTION ac.fn_vch_tran_report(IN pvoucher_id varchar(50))
  RETURNS TABLE
  (
	voucher_id varchar(50),
	vch_tran_id varchar(50),
	branch_id bigint,
	sl_no smallint, 
	dc character,
	account_id bigint,
	credit_amt numeric,
	debit_amt numeric,
	debit_amt_fc numeric,
	credit_amt_fc numeric,
	branch_code varchar(2),
	account_head varchar(250)
  ) As
$BODY$
Begin 
        -- create tarn side function for print report
        return query 
	select a.voucher_id, a.vch_tran_id, a.branch_id, a.sl_no, a.dc, a.account_id, a.credit_amt, a.debit_amt, 
	a.debit_amt_fc, a.credit_amt_fc, c.branch_code, b.account_head 
	from ac.vch_tran a 
		INNER JOIN ac.account_head b on a.account_id = b.account_id
		INNER JOIN sys.branch c on a.branch_id = c.branch_id	
			where a.voucher_id = pvoucher_id;
END;
$BODY$
 LANGUAGE plpgsql;

?==?

CREATE FUNCTION ac.sp_bank_reco_book_balance(IN pcompany_id bigint, IN pbranch_id bigint, IN paccount_id bigint, IN pyear varchar(4), IN pas_on date, OUT pcredit_balance numeric, OUT pdebit_balance numeric)
  RETURNS record AS
$BODY$
declare vCr numeric(18,4) = 0; vDr numeric(18,4) = 0;  
Begin
 
    --    *****    Fetch balance from Account Balance  
    Select  (CASE WHEN sum(debit_balance) IS NULL THEN 0 ELSE sum(debit_balance) END),    
	    (CASE WHEN sum(credit_balance) IS NULL THEN 0 ELSE sum(credit_balance) END) into vDr, vCr
    From ac.account_balance   
    Where account_id=paccount_id And finyear=pyear
   		 And (branch_id=pbranch_id or pbranch_id=0)
   		 And (company_id=pcompany_id);

    --    ****    Fetch balance from GL as on to Date (Closing balance for the day is included)
   Select (vDr+(CASE WHEN sum(debit_amt) IS NULL THEN 0 ELSE sum(debit_amt) END)),
          (vCr+(CASE WHEN sum(credit_amt) IS NULL THEN 0 ELSE sum(credit_amt) END)) into vDr, vCr
    From ac.general_ledger  
    Where account_id=paccount_id and finyear=pyear and doc_date <= pas_on  
   		 And (branch_id=pbranch_id or pbranch_id=0)
   		 And (company_id=pcompany_id);

    --    ****    Resolve Debit or Credit
    If vDr>vCr Then
	 pdebit_balance:=vDr-vCr;  
   	 pcredit_balance:=0;  
    Else
    	 pcredit_balance:=vCr-vDr; 
   	 pdebit_balance:=0;  
    End If;
End
$BODY$
  LANGUAGE plpgsql;

?==?

CREATE FUNCTION ac.sp_account_group_key_create(IN pparent_group_id bigint)
	RETURNS TABLE(parent_key varchar(20), group_key varchar(20), group_path varchar(3500)) AS
$BODY$
declare vParentKey varchar(20) = ''; vSequence bigint = 0; vParentLevel varchar(1) = ''; vGroupLevel varchar(1) = ''; 
	vCode varchar(20) = ''; vParentPath varchar(3500) = ''; vGroupKey varchar(20) = ''; vGroupPath varchar(3500) = '';
	vGroupId bigint = -1; 
BEGIN  
	DROP TABLE IF EXISTS account_group_key_create_temp;
	CREATE temp TABLE  account_group_key_create_temp
	(	
		parent_key varchar(20),
		group_key varchar(20),
		group_path varchar(3500)
	);

	-- *** Get ParentKey
	SELECT a.group_key INTO vParentKey
	FROM ac.account_group a
	WHERE a.group_id = pparent_group_id;


	-- *** Generate Group Key
	if vParentKey <> '' then
		vParentLevel := left(vParentKey, 1);
		vGroupLevel := chr(ascii(vParentLevel) + 1);
	Else
		vGroupLevel := 'A';
	End If;

	if NOT EXISTS (SELECT level FROM ac.account_group_sequence WHERE level = vGroupLevel) then
		-- *** Creating a new group sequence
		INSERT INTO ac.account_group_sequence (level, max_id)
		VALUES (vGroupLevel, 0);
	End if;
		
	-- ***	Generate the sequence and create groupkey
	SELECT max_id + 1 INTO vSequence
	FROM ac.account_group_sequence 
	WHERE level = vGroupLevel;
	
	UPDATE ac.account_group_sequence
		SET max_id = vSequence
	WHERE level = vGroupLevel;

	-- ***	Create the numeric part of the groupkey 
        SELECT  lpad(cast(vSequence as text), 3, '0') INTO vCode;

	-- ***	Create the Groupkey
	SELECT concat(vGroupLevel, cast(vCode as text)) INTO vGroupKey;

	-- ***	Get the Parentpath
	SELECT a.group_path INTO vParentPath 
	FROM ac.account_group a 
	WHERE a.group_key = vParentKey ;

	-- ***	Generate the Grouppath
	SELECT concat(vParentPath, vGroupKey) INTO vGroupPath;

	INSERT INTO account_group_key_create_temp(parent_key, group_key, group_path)
		VALUES(vParentKey, vGroupKey, vGroupPath);
	
	RETURN query 
	SELECT a.parent_key, a.group_key, a.group_path FROM account_group_key_create_temp a;

END;
$BODY$
  LANGUAGE plpgsql;

?==?


CREATE FUNCTION ac.sp_account_group_move(IN pparent_group_id bigint, IN pgroup_id bigint, IN pcompany_id bigint, OUT pgroup_key varchar(20), OUT pgroup_path varchar(3500), OUT pparent_key varchar(20))
  RETURNS record AS
$BODY$
declare vParentKey varchar(20) = ''; vOldParentLevel varchar(1) = ''; vNewGroupKey varchar(20) = '';
	vNewGroupPath varchar(3500) = ''; vParentPath varchar(3500) = ''; vChildGroupId bigint = -1; vGroupKey varchar(20)= ''; vGroupPath varchar(3500) = '';
	vDummy varchar(20) = '';
BEGIN  	
	-- ***	Fetch Existing Values
	SELECT left(a.parent_key,1), a.group_key, a.group_path INTO vOldParentLevel, vGroupKey, vGroupPath
	FROM ac.account_group a
	WHERE a.group_id = pgroup_id;
	
	-- ***	Fetch The New Parent Path
	SELECT a.group_key, a.group_path INTO vParentKey, vParentPath
	FROM ac.account_group a
	WHERE a.group_id = pparent_group_id AND a.company_id = pcompany_id;

	-- ***	Verify whether the ParentKey is already a Child in the Group
	if EXISTS(SELECT a.group_id FROM ac.account_group a 
			WHERE a.group_key = vParentKey AND a.group_path LIKE concat(vGroupPath, '%') ) Then
		RAISE EXCEPTION 'Parent is Child in the target group. This is prohibited.'; 
		return;
	End if;

	if vOldParentLevel <> left(vParentKey, 1) Then
		-- ***	Generate New Groupkey, GroupPath
		SELECT group_key, group_path INTO vNewGroupKey, vNewGroupPath 
		FROM ac.sp_account_group_key_create(pparent_group_id);
	else
		-- *** Use existing keys as the group level did not change.
		vNewGroupKey := vGroupKey;
		vNewGroupPath := concat(vParentPath, vGroupKey);
	End if;

	-- ***	Modify the group
	UPDATE ac.account_group 
	SET parent_key = vParentKey,
		group_key = vNewGroupKey,
		group_path = vNewGroupPath,
		last_updated = current_timestamp(0)
	WHERE group_id = pgroup_id;

	-- ***	If the level does not change, alter the grouppath of all children
	if vNewGroupKey = vGroupKey Then
		UPDATE ac.account_group 
			SET group_path = replace(group_path, vGroupPath, concat(vParentPath, vNewGroupKey))
		WHERE group_path LIKE concat(vGroupPath, '%');
	else
		-- *** Create Cursor for Records that are children for this Group
		WHILE EXISTS(SELECT group_key FROM ac.account_group WHERE parent_key = vGroupKey) LOOP
			SELECT min(a.group_id) INTO vChildGroupId
			FROM ac.account_group a
			WHERE a.parent_key = vGroupKey;

			SELECT vGroupKey INTO vDummy FROM ac.sp_account_group_move (pgroup_id, vChildGroupId, pcompany_id);
		END LOOP;
	End if;
		pgroup_key := vNewGroupKey;
		pgroup_path := vNewGroupPath;
		pparent_key := vParentKey;
END;
$BODY$
  LANGUAGE plpgsql

?==?


CREATE FUNCTION ac.sp_account_group_validate_is_child(IN pgroup_id bigint, IN ptarget_group_id bigint, OUT pis_child boolean)
	RETURNS boolean AS
$BODY$
declare vGroupKey varchar(20) = ''; 
BEGIN  

	SELECT a.group_key INTO vGroupKey
	FROM ac.account_group a
	WHERE a.group_id = pgroup_id;

	SELECT case when position(vGroupKey in a.group_path) > 0 then 'true' else 'false' End INTO pis_child
	FROM ac.account_group a
	WHERE a.group_id = ptarget_group_id;

END;
$BODY$
  LANGUAGE plpgsql

?==?
CREATE OR REPLACE FUNCTION ac.fn_tb_report_ct(IN pcompany_id bigint, IN pbranch_id bigint, IN pyear character varying, IN pfrom_date date, IN pto_date date)
  RETURNS TABLE(branch_id bigint, branch_code character varying, branch_name character varying, account_id bigint, account_head character varying, account_code character varying, parent_key character varying, group_key character varying, group_code character varying, group_name character varying, group_path character varying, debit_opening_balance numeric, credit_opening_balance numeric, period_debits numeric, period_credits numeric, debit_closing_balance numeric, credit_closing_balance numeric, debit_opening_total numeric, credit_opening_total numeric, period_debits_total numeric, period_credits_total numeric, debit_closing_total numeric, credit_closing_total numeric, opening_bal_bit bigint) AS
$BODY$
DECLARE diff  numeric(18,4) = 0;
BEGIN

	--DROP TABLE IF EXISTS tb_report;
	CREATE temp TABLE  tb_report_CT
	(
		branch_id bigint,
		branch_code varchar(50),
		branch_name varchar(250),
		account_id bigint, 
		account_head varchar(250), 
		account_code varchar(20),
		parent_key varchar(20),
		group_key varchar(20),
		group_code varchar(10),
		group_name varchar(250),
		group_path varchar(3500), 
		debit_opening_balance numeric(18,4), 
		credit_opening_balance numeric(18,4), 
		period_debits numeric(18,4), 
		period_credits numeric(18,4), 
		debit_closing_balance numeric(18,4), 
		credit_closing_balance numeric(18,4),
		debit_opening_total numeric(18,4), 
		credit_opening_total numeric(18,4), 
		period_debits_total numeric(18,4), 
		period_credits_total numeric(18,4), 
		debit_closing_total numeric(18,4), 
		credit_closing_total numeric(18,4),
		opening_bal_bit bigint
	)
	on commit drop;

	--	*****	Final Third Step: Get the related Description of the Accounts and Groups

	
	-- **** Sub Step 1: Fetch Data into a Temp Table with Control Accounts
	CREATE temp TABLE  tb_report_temp
	(	branch_id bigint,
		account_id bigint, 
		debit_opening_balance numeric(18,4), 
		credit_opening_balance numeric(18,4), 
		period_debits numeric(18,4), 
		period_credits numeric(18,4), 
		debit_closing_balance numeric(18,4), 
		credit_closing_balance numeric(18,4)
	)
	on commit drop;
	
	Insert into tb_report_temp(branch_id,account_id, debit_opening_balance, credit_opening_balance, 
				period_debits, period_credits, 
				debit_closing_balance, credit_closing_balance)
	Select a.branch_id,a.account_id, a.debit_opening_balance, a.credit_opening_balance, 
				a.period_debits, a.period_credits, 
				a.debit_closing_balance, a.credit_closing_balance
	From ac.fn_tb_op_tran_cl_CT(pcompany_id, pbranch_id, pyear, pfrom_date, pto_date) a;	

	--Update tb_report_temp
	UPDATE tb_report_temp b
	SET account_id =control_account_id
	FROM ar.customer
	WHERE b.account_id IN (select control_account_id as account_id from  ar.customer);

	--Update Suppliers
	UPDATE tb_report_temp c
	SET account_id =control_account_id
	FROM ap.supplier
	WHERE c.account_id IN (select control_account_id as account_id from  ap.supplier);
	
	--Delete Rows without transaction
	DELETE FROM tb_report_temp d
	WHERE d.debit_opening_balance=0 AND d.credit_opening_balance=0 AND d.period_debits=0 AND d.period_credits=0 AND d.debit_closing_balance=0 AND d.credit_closing_balance=0;

	
	Insert Into tb_report_CT(branch_id , branch_code, branch_name,account_id, account_head, account_code, parent_key, group_key, group_code, group_name, group_path, 
				debit_opening_balance, credit_opening_balance, period_debits, period_credits, 
				debit_closing_balance, credit_closing_balance,opening_bal_bit)
	select 	b.branch_id , b.branch_code, b.branch_name ,b.account_id, b.account_head, b.account_code, a.parent_key, a.group_key, a.group_code, a.group_name, a.group_path,
		b.debit_opening_balance, b.credit_opening_balance, b.period_debits, b.period_credits, 
		b.debit_closing_balance, b.credit_closing_balance,0
	from ac.account_group a
	left join 
		(Select	a.branch_id , c.branch_code, c.branch_name, a.account_id, b.account_head, b.account_code, b.group_id, 
			sum(a.debit_opening_balance) as debit_opening_balance, sum(a.credit_opening_balance) as credit_opening_balance,
			sum(a.period_debits) as period_debits, sum(a.period_credits) as period_credits,
			sum(a.debit_closing_balance) as debit_closing_balance, sum(a.credit_closing_balance) as credit_closing_balance,0
		From tb_report_temp a
		inner join ac.account_head b on a.account_id=b.account_id
		inner join sys.branch c on a.branch_id=c.branch_id
		group by a.account_id, b.account_code, b.account_head, b.group_id,a.branch_id , c.branch_code, c.branch_name
		) b on a.group_id=b.group_id;


        /*
	 --	*****	Resolve opening Balance differences
	 diff := (select sum(t.debit_balance-t.credit_balance) as diff from ac.account_balance t where t.finyear=pyear and t.branch_id=pbranch_id);
	 IF diff!=0 then
		Insert Into tb_report_CT(branch_id,branch_code,branch_name,account_id, account_head, account_code, parent_key, group_key, group_code, group_name, group_path,debit_opening_balance, 
				      credit_opening_balance, period_debits, period_credits,debit_closing_balance, credit_closing_balance,opening_bal_bit )
		select -1,'','',-1, 'Opening balance difference', 'A8', '0', 'A8', 'A8','Opening Balance Difference', 'A8', Case When diff< 0 Then (diff *(-1)) Else  0 END,
		Case When diff< 0 Then  0 Else diff END, 0, 0, Case When diff< 0 Then (diff *(-1)) Else 0 END,Case When diff< 0 Then 0 Else diff END,1 ;
	END IF;

	-- 	****	Create cursor and build totals for each group
	DECLARE	
		cur_group Cursor For (Select a.group_path from (Select a.group_path from ac.account_group a
					Union All
					Select 'A8') a Order By a.group_path);
		debit_opt numeric; credit_opt numeric; debit_pt numeric; credit_pt numeric; debit_cpt numeric; credit_cpt numeric ;
	Begin
		For ac_group In cur_group Loop 

			Select 	coalesce(Sum(a.debit_opening_balance), 0), coalesce(Sum(a.credit_opening_balance), 0), 
				coalesce(Sum(a.period_debits), 0), coalesce(Sum(a.period_credits), 0), 
				coalesce(Sum(a.debit_closing_balance), 0), coalesce(Sum(a.credit_closing_balance), 0) , 0
				Into debit_opt, credit_opt, debit_pt, credit_pt, debit_cpt, credit_cpt 
			From tb_report_CT a
			Where a.group_path like ac_group.group_path || '%'; 

			Update tb_report_CT a
			Set 	debit_opening_total=debit_opt,
				credit_opening_total=credit_opt,
				period_debits_total=debit_pt, 
				period_credits_total=credit_pt, 
				debit_closing_total=debit_cpt, 
				credit_closing_total=credit_cpt
			Where a.group_path=ac_group.group_path;
			
		End loop;
	End;
        */
	return query
	Select	a.branch_id , a.branch_code, a.branch_name,a.account_id, a.account_head, a.account_code, a.parent_key, a.group_key, a.group_code, a.group_name, a.group_path, 
		a.debit_opening_balance, a.credit_opening_balance, a.period_debits, a.period_credits, a.debit_closing_balance, a.credit_closing_balance,
		a.debit_opening_total, a.credit_opening_total, a.period_debits_total, a.period_credits_total, a.debit_closing_total, a.credit_closing_total,a.opening_bal_bit
	From tb_report_CT a
	order by a.group_path, a.account_head;
END;
$BODY$
  LANGUAGE plpgsql;

?==?

CREATE OR REPLACE FUNCTION ac.fn_tb_op_tran_cl_ct(IN pcompany_id bigint, IN pbranch_id bigint, IN pyear character varying, IN pfrom_date date, IN pto_date date)
  RETURNS TABLE(branch_id bigint, account_id bigint, debit_opening_balance numeric, credit_opening_balance numeric, period_debits numeric, period_credits numeric, debit_closing_balance numeric, credit_closing_balance numeric) AS
$BODY$
BEGIN
	--DROP TABLE IF EXISTS tb_op_trancl;
	CREATE temp TABLE  tb_op_trancl_CT
	(	branch_id bigint,
		account_id bigint,
		debit_opening_balance numeric(18,4), 
		credit_opening_balance numeric(18,4), 
		period_debits numeric(18,4), 
		period_credits numeric(18,4), 
		debit_closing_balance numeric(18,4), 
		credit_closing_balance numeric(18,4)
	)
	on commit drop;
	--	Second Step: Summarise the Opening, Transactions and Closing Balance
	Insert Into tb_op_tranCL_CT(branch_id,account_id, debit_opening_balance, credit_opening_balance, period_debits, period_credits,
				debit_closing_balance, credit_closing_balance)
	Select b.branch_id ,b.account_id,
		Case When (b.debit_opening_balance-b.credit_opening_balance)>=0 Then b.debit_opening_balance-b.credit_opening_balance Else 0 End,
		Case When (b.debit_opening_balance-b.credit_opening_balance)<0 Then (b.debit_opening_balance-b.credit_opening_balance) * -1 Else 0 End,
		b.period_debits, b.period_credits,
		Case When (b.debit_opening_balance-b.credit_opening_balance+b.period_debits-b.period_credits)>=0 
			 Then b.debit_opening_balance-b.credit_opening_balance+b.period_debits-b.period_credits Else 0 End,
		Case When (b.debit_opening_balance-b.credit_opening_balance+b.period_debits-b.period_credits)<0 
			 Then (b.debit_opening_balance-b.credit_opening_balance+b.period_debits-b.period_credits) * -1 Else 0 End
	From ac.fn_tb_op_bal_CT(pcompany_id, pbranch_id, pyear, pfrom_date, pto_date) b;
	
	--) a;

return query
select a.branch_id,a.account_id, a.debit_opening_balance, a.credit_opening_balance, a.period_debits, a.period_credits, a.debit_closing_balance,
a.credit_closing_balance
from tb_op_trancl_CT a
order by a.account_id;
--group by a.account_id,a.account_id,a.debit_balance,a.credit_balance,a.debit_balance_period,a.credit_balance_period,a.debit_balance_close,a.credit_balance_close,a.doc_date  ;

END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.fn_tb_op_bal_ct(IN pcompany_id bigint, IN pbranch_id bigint, IN pyear character varying, IN pfrom_date date, IN pto_date date)
  RETURNS TABLE(branch_id bigint, account_id bigint, debit_opening_balance numeric, credit_opening_balance numeric, period_debits numeric, period_credits numeric) AS
$BODY$
Begin
	CREATE temp TABLE  tb_op_bal_temp_CT
	(	branch_id bigint,
		account_id bigint,
		debit_opening_balance numeric(18,4), 
		credit_opening_balance numeric(18,4), 
		period_debits numeric(18,4), 
		period_credits numeric(18,4)
	)
	on commit drop;


	--	*****	First Step: This extracts the Opening Balance Before FromDate
	Insert Into tb_op_bal_temp_CT(branch_id,account_id, debit_opening_balance, credit_opening_balance, period_debits, period_credits)
	select g.branch_id,g.account_id, Sum(g.debit_balance), Sum(g.credit_balance), sum(g.debit_balance_period), sum(g.credit_balance_period)
	from (
		Select a.branch_id,a.account_id, Sum(a.debit_balance) as debit_balance, Sum(a.credit_balance) as credit_balance,
			0.00 as debit_balance_period, 0.00 as credit_balance_period
		From ac.account_balance a
		Where a.finyear=pyear 
			And a.company_id=pcompany_id 
			And (a.branch_id in (select a.branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id) a) or pbranch_id=0)
		Group By a.branch_id, a.account_id
		Union All -- GL Summary Before From Date
		Select a.branch_id,a.account_id, Sum(a.debit_amt), Sum(a.credit_amt), 0.00, 0.00
		From ac.general_ledger  a 
		Where a.finyear=pyear  
			And a.doc_date<pfrom_date 
			And a.company_id=pcompany_id 
			And (a.branch_id in (select a.branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id) a) or pbranch_id=0)
		Group By a.branch_id,a.account_id
		Union All -- GL Summary Between From And To
		Select a.branch_id,a.account_id, 0.00, 0.00, Sum(a.debit_amt), Sum(a.credit_amt)
		From ac.general_ledger a
		Where a.finyear=pyear  
			And a.doc_date Between pfrom_date And pto_date 
			And a.company_id=pcompany_id
			And (a.branch_id in (select a.branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id) a) or pbranch_id=0)
		Group By a.branch_id,a.account_id
	   ) g
	group by g.branch_id,g.account_id;
	
	return query 
	select a.branch_id,a.account_id, a.debit_opening_balance, a.credit_opening_balance, a.period_debits, a.period_credits
	from tb_op_bal_temp_CT a
	Group By a.branch_id,a.account_id, a.debit_opening_balance, a.credit_opening_balance, a.period_debits, a.period_credits
	order by a.account_id;
	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE or Replace FUNCTION ac.fn_tb_ct(IN pcompany_id bigint, IN pbranch_ids bigint[], IN pyear varchar(4), IN pfrom_date date, IN pto_date date)
RETURNS TABLE
(	account_id bigint, 
    branch_id BigInt,
	op_debit numeric(18,4), 
	op_credit numeric(18,4), 
	txn_debit numeric(18,4), 
	txn_credit numeric(18,4), 
	cl_debit numeric(18,4), 
	cl_credit numeric(18,4)) 
AS
$BODY$
BEGIN

    return query
	With period_txn
    As
    (   Select a.account_id, a.branch_id, Sum(a.debit_balance) as op_debit, Sum(a.credit_balance) as op_credit,
			0.00 as period_debit, 0.00 as period_credit
		From ac.account_balance a
		Where a.finyear=pyear 
			And a.company_id=pcompany_id 
			And (a.branch_id = Any(pbranch_ids) or '{0}'::BigInt[] = pbranch_ids)
		Group By a.account_id, a.branch_id
		Union All -- GL Summary Before From Date
		Select a.account_id, a.branch_id, Sum(a.debit_amt), Sum(a.credit_amt), 0.00, 0.00
		From ac.general_ledger  a 
		Where a.finyear=pyear  
			And a.doc_date<pfrom_date 
			And a.company_id=pcompany_id 
			And (a.branch_id = Any(pbranch_ids) or '{0}'::BigInt[] = pbranch_ids)
		Group By a.account_id, a.branch_id
		Union All -- GL Summary Between From And To
		Select a.account_id, a.branch_id, 0.00, 0.00, Sum(a.debit_amt), Sum(a.credit_amt)
		From ac.general_ledger a
		Where a.finyear=pyear  
			And a.doc_date Between pfrom_date And pto_date 
			And a.company_id=pcompany_id
			And (a.branch_id = Any(pbranch_ids) or '{0}'::BigInt[] = pbranch_ids)
		Group By a.account_id, a.branch_id
    )
    select g.account_id, g.branch_id,
		Case When Sum(g.op_debit-g.op_credit)>=0 Then Sum(g.op_debit-g.op_credit) Else 0.00 End, 
		Case When Sum(g.op_debit-g.op_credit)<0 Then Sum(g.op_credit-g.op_debit) Else 0.00 End, 
		sum(g.period_debit), sum(g.period_credit), 
		Case When Sum(g.op_debit-g.op_credit+g.period_debit-g.period_credit)>=0 
					Then Sum(g.op_debit-g.op_credit+g.period_debit-g.period_credit) Else 0.00 End, 
        Case When Sum(g.op_debit-g.op_credit+g.period_debit-g.period_credit)<0 
					Then Sum(g.op_debit-g.op_credit+g.period_debit-g.period_credit) * -1 Else 0.00 End
    From period_txn g
    Group By g.account_id, g.branch_id;

END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE FUNCTION ac.fn_tb_ct_report(IN pcompany_id bigint, IN pbranch_id bigint[], IN pyear character varying, IN pfrom_date date, IN pto_date date)
  RETURNS TABLE(branch_id bigint, branch_code character varying, branch_name character varying, account_id bigint, account_head character varying, account_code character varying, parent_key character varying, group_key character varying, group_code character varying, group_name character varying, group_path character varying, debit_opening_balance numeric, credit_opening_balance numeric, period_debits numeric, period_credits numeric, debit_closing_balance numeric, credit_closing_balance numeric, debit_opening_total numeric, credit_opening_total numeric, period_debits_total numeric, period_credits_total numeric, debit_closing_total numeric, credit_closing_total numeric, opening_bal_bit bigint) AS
$BODY$
DECLARE diff  numeric(18,4) = 0;
BEGIN

	--DROP TABLE IF EXISTS tb_report;
	CREATE temp TABLE  tb_report_CT
	(
		branch_id bigint,
		branch_code varchar(50),
		branch_name varchar(250),
		account_id bigint, 
		account_head varchar(250), 
		account_code varchar(20),
		parent_key varchar(20),
		group_key varchar(20),
		group_code varchar(10),
		group_name varchar(250),
		group_path varchar(3500), 
		debit_opening_balance numeric(18,4), 
		credit_opening_balance numeric(18,4), 
		period_debits numeric(18,4), 
		period_credits numeric(18,4), 
		debit_closing_balance numeric(18,4), 
		credit_closing_balance numeric(18,4),
		debit_opening_total numeric(18,4), 
		credit_opening_total numeric(18,4), 
		period_debits_total numeric(18,4), 
		period_credits_total numeric(18,4), 
		debit_closing_total numeric(18,4), 
		credit_closing_total numeric(18,4),
		opening_bal_bit bigint
	)
	on commit drop;

	--	*****	Final Third Step: Get the related Description of the Accounts and Groups

	
	-- **** Sub Step 1: Fetch Data into a Temp Table with Control Accounts
	CREATE temp TABLE  tb_report_temp
	(	branch_id bigint,
		account_id bigint, 
		debit_opening_balance numeric(18,4), 
		credit_opening_balance numeric(18,4), 
		period_debits numeric(18,4), 
		period_credits numeric(18,4), 
		debit_closing_balance numeric(18,4), 
		credit_closing_balance numeric(18,4)
	)
	on commit drop;
	
	Insert into tb_report_temp(branch_id,account_id, debit_opening_balance, credit_opening_balance, 
				period_debits, period_credits, 
				debit_closing_balance, credit_closing_balance)
	Select a.branch_id,a.account_id, a.op_debit, a.op_credit, 
				a.txn_debit, a.txn_credit, 
				a.cl_debit, a.cl_credit
	From ac.fn_tb_ct(pcompany_id, pbranch_id, pyear, pfrom_date, pto_date) a;	

	--Update tb_report_temp
	UPDATE tb_report_temp b
	SET account_id =control_account_id
	FROM ar.customer
	WHERE b.account_id IN (select control_account_id as account_id from  ar.customer);

	--Update Suppliers
	UPDATE tb_report_temp c
	SET account_id =control_account_id
	FROM ap.supplier
	WHERE c.account_id IN (select control_account_id as account_id from  ap.supplier);
	
	--Delete Rows without transaction
	DELETE FROM tb_report_temp d
	WHERE d.debit_opening_balance=0 AND d.credit_opening_balance=0 AND d.period_debits=0 AND d.period_credits=0 AND d.debit_closing_balance=0 AND d.credit_closing_balance=0;

	
	Insert Into tb_report_CT(branch_id , branch_code, branch_name,account_id, account_head, account_code, parent_key, group_key, group_code, group_name, group_path, 
				debit_opening_balance, credit_opening_balance, period_debits, period_credits, 
				debit_closing_balance, credit_closing_balance,opening_bal_bit)
	select 	b.branch_id, b.branch_code, b.branch_name ,b.account_id, b.account_head, b.account_code, a.parent_key, a.group_key, a.group_code, a.group_name, a.group_path,
		b.debit_opening_balance, b.credit_opening_balance, b.period_debits, b.period_credits, 
		b.debit_closing_balance, b.credit_closing_balance,0
	from ac.account_group a
	left join 
		(Select	a.branch_id , c.branch_code, c.branch_name, a.account_id, b.account_head, b.account_code, b.group_id, 
			sum(a.debit_opening_balance) as debit_opening_balance, sum(a.credit_opening_balance) as credit_opening_balance,
			sum(a.period_debits) as period_debits, sum(a.period_credits) as period_credits,
			sum(a.debit_closing_balance) as debit_closing_balance, sum(a.credit_closing_balance) as credit_closing_balance,0
		From tb_report_temp a
		inner join ac.account_head b on a.account_id=b.account_id
		inner join sys.branch c on a.branch_id=c.branch_id
		group by a.account_id, b.account_code, b.account_head, b.group_id,a.branch_id , c.branch_code, c.branch_name
		) b on a.group_id=b.group_id;

	return query
	Select	a.branch_id , a.branch_code, a.branch_name,a.account_id, a.account_head, a.account_code, a.parent_key, a.group_key, a.group_code, a.group_name, a.group_path, 
		a.debit_opening_balance, a.credit_opening_balance, a.period_debits, a.period_credits, a.debit_closing_balance, a.credit_closing_balance,
		a.debit_opening_total, a.credit_opening_total, a.period_debits_total, a.period_credits_total, a.debit_closing_total, a.credit_closing_total,a.opening_bal_bit
	From tb_report_CT a
	order by a.group_path, a.account_head;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
Create function ac.fn_ib_account_get(ptarget_branch bigint, out paccount_id bigint)
RETURNS BigInt
AS
$BODY$
declare vAccount_ID bigint=0;
BEGIN
	SELECT account_id into vAccount_ID
	From ac.ib_account
	Where branch_ID=ptarget_branch;
	
	-- generate output
	paccount_id := vAccount_ID;	
	
END
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.fn_monthly_sales_collection(pcompany_id bigint, pfinyear varchar(4))  
RETURNS TABLE
(
	 month_name varchar(3),
	 month_no smallint,
	 sales Numeric(18,4),       
	 collection Numeric(18,4)
) 
AS
$BODY$
BEGIN	
	DROP TABLE IF EXISTS monthly_sales_coll_temp;	
	create temp table monthly_sales_coll_temp
	(
		 month_name varchar(3),
		 month_no smallint,
		 sales Numeric(18,4),       
		 collection Numeric(18,4)   
	);

        insert into monthly_sales_coll_temp(month_name, month_no, sales, collection)
	Select a.month_name, a.month_no, sum(a.sales) as sales, sum(a.collection) as collection
	From (
		Select 'Jan' as month_name, 1 as month_no, 0 as sales, 0 as collection
		union all
		Select 'Feb' as month_name, 2 as month_no, 0 as sales, 0 as collection
		union all
		Select 'Mar' as month_name, 3 as month_no, 0 as sales, 0 as collection
		union all
		Select 'Apr' as month_name, 4 as month_no, 0 as sales, 0 as collection
		union all
		Select 'May' as month_name, 5 as month_no, 0 as sales, 0 as collection
		union all
		Select 'Jun' as month_name, 6 as month_no, 0 as sales, 0 as collection
		union all
		Select 'Jul' as month_name, 7 as month_no, 0 as sales, 0 as collection
		union all
		Select 'Aug' as month_name, 8 as month_no, 0 as sales, 0 as collection
		union all
		Select 'Sep' as month_name, 9 as month_no, 0 as sales, 0 as collection
		union all
		Select 'Oct' as month_name, 10 as month_no, 0 as sales, 0 as collection
		union all
		Select 'Nov' as month_name, 11 as month_no, 0 as sales, 0 as collection
		union all
		Select 'Dec' as month_name, 12 as month_no, 0 as sales, 0 as collection
		union all
		Select x.month_name, x.month_no, x.sales, x.collection 
		From (
			Select to_char(to_timestamp(to_char(extract(month from a.doc_date), '999'), 'MM'), 'Mon') as month_name, extract(month from a.doc_date) as month_no, 
				a.credit_amt as sales, 0 as collection
			from ap.pymt_control a
			Where a.status= 5
				And a.finyear =pfinyear
				And a.company_id = pcompany_id
			Union All
			Select to_char(to_timestamp(to_char(extract(month from a.doc_date), '999'), 'MM'), 'Mon') as month_name, extract(month from a.doc_date) as month_no, 
				0 as sales, a.debit_amt
			from ar.rcpt_control a
			Where a.status= 5
				And a.finyear =pfinyear
				And a.company_id = pcompany_id
			) x 
	) a
	Group by a.month_name, a.month_no
	order by a.month_no;
 
	return query
	select 	a.month_name, a.month_no, a.sales, a.collection
	from monthly_sales_coll_temp a;
	       
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.fn_daybook_report(
    IN pcompany_id bigint,
    IN pbranch_id bigint,
    IN pyear character varying,
    IN pfrom_date date,
    IN pto_date date)
  RETURNS TABLE(doc_date date, voucher_id character varying, account_head character varying, debit_amt numeric, credit_amt numeric, narration character varying, cheque_details character varying) AS
$BODY$
 Begin 
       DROP TABLE IF EXISTS daybook_report_temp;
	CREATE temp TABLE  daybook_report_temp
	(
	       	doc_date date,
		voucher_id varchar(50),
                account_id BigInt,
                account_type_id BigInt,
		account_head varchar(250),
		debit_amt numeric(18,4),
		credit_amt numeric(18,4),
		narration varchar(500),
		cheque_details varchar(500)
	)
	on commit drop;
        insert into daybook_report_temp( doc_date, voucher_id, account_id, account_type_id, account_head,
		debit_amt, credit_amt, narration, cheque_details)
	Select a.doc_date, a.voucher_id, a.account_id, b.account_type_id, b.account_head,
		Sum(a.debit_amt) as debit_amt, Sum(a.credit_amt) as credit_amt, 
		Min(a.narration) as narration, Min(a.cheque_details) as cheque_details
		From ac.general_ledger a
		Inner Join ac.account_head b On a.account_id=b.account_id
	where a.company_id=pcompany_id and a.finyear=pyear
	      and (a.branch_id = pbranch_id or pbranch_id=0)
	      and a.doc_date between pfrom_date and pto_date
	Group By a.doc_date, a.voucher_id, a.account_id, b.account_type_id, b.account_head; 

        Return query
           select a.doc_date, a.voucher_id, a.account_head,
		a.debit_amt, a.credit_amt, a.narration, a.cheque_details
	   from daybook_report_temp a
	   Order By a.doc_date, a.voucher_id, a.account_type_id;
END;
$BODY$
  LANGUAGE plpgsql;


?==?
Create or Replace Function ac.fn_bs_report(pcompany_id BigInt, pbranch_id BigInt, pfinyear Varchar(4), pfrom_date Date, pto_date Date)  
Returns Table   
(   bs_type Varchar(1),  
    parent_key varchar(16),  
    group_key varchar(16), 
    group_name Varchar(250), 
    group_path Varchar(3500),
    account_id BigInt, 
    account_code Varchar(20), 
    account_head Varchar(250), 
    cl_bal_amt Numeric(18,4)
) 
AS
$BODY$
Declare vtotal_income Numeric(18,4):=0; vtotal_expense Numeric(18,4):=0; vst_req varchar(50):=0; vpnl_ac varchar(50):=0;
    vtotal_diff_in_stock_value Numeric(18,4):=0; vdiff_in_stock_value Numeric(18,4):=0; vnet_profit Numeric(18,4):=0;
    vfirst_period Boolean;
Begin  
    Drop Table If Exists bs_report;
    Create Temp Table bs_report   
    (   bs_type Varchar(1),  
        parent_key varchar(16),  
        group_key varchar(16), 
        group_name Varchar(250),
        group_path Varchar(3500),  
        account_id BigInt, 
        account_code Varchar(20), 
        account_head Varchar(250), 
        cl_bal_amt Numeric(18,4)
    );   
	
  	  
    -- ***** Fetch all entries from the Trial Balance  
    Drop Table If Exists tb_bs_report;
    Create Temp Table tb_bs_report  
    ( 	parent_key varchar(20),   
        group_key varchar(20),
        group_path Varchar(3500), 
        group_name varchar(250), 			
        account_id BigInt,   
        account_code Varchar(20),
        account_head varchar(250),   
        dr_cl_bal Numeric(18,4),
        cr_cl_bal Numeric(18,4)
    ); 

    -- **** Fetch data based on following types
    -- **** A001: Assets, A002: Owner Funds, A003: Liabilities
    -- **** A004: Income
    -- **** A005: COGC, A006: Expenses
    Insert Into tb_bs_report(parent_key, group_key, group_path, group_name, account_id, account_code, account_head, 
        dr_cl_bal, cr_cl_bal)
    Select a.parent_key, a.group_key, a.group_path, a.group_name, a.account_id, 
        a.account_code, a.account_head, 
        Case When a.group_path like Any('{A001%,A002%,A003%}') Then a.debit_closing_balance
            When a.group_path like Any('{A004%}') Then 0.00
            When a.group_path like Any('{A005%,A006%}') Then a.period_debits - a.period_credits
            Else a.debit_closing_balance End,
        Case When a.group_path like Any('{A001%,A002%,A003%}') Then a.credit_closing_balance
            When a.group_path like Any('{A004%}') Then a.period_credits - a.period_debits
            When a.group_path like Any('{A005%,A006%}') Then 0.00
            Else a.credit_closing_balance End
    From ac.fn_tb_report(pcompany_id, pbranch_id, pfinyear, pfrom_date, pto_date) a
    order by a.group_path;

    --*****	Fetch Closing Stock Entries based upon application settings
    Select a.value into vst_req From sys.settings a Where key='bs_closing_stock';

    If vst_req <> '0' Then
        -- Update the Trial Balance with Actual Opening Stock as per Stock Balance
        -- When finyear begins is not equal to from_date
        If Not Exists (Select * from sys.finyear Where year_begin = pfrom_date and finyear_code = pfinyear) Then
            With op_st
            As
            (   Select a.account_id, Sum(a.mat_value)::Numeric(18,2) as mat_value
                        From st.fn_material_balance_wac_by_inv_ac(pcompany_id, pbranch_id, pfinyear, (pfrom_date - interval '1 day')::Date) a
                        Group By a.account_id
            )
            Update tb_bs_report a
            Set dr_cl_bal= b.mat_value,
                cr_cl_bal=0
            From op_st b
            Where a.account_id = b.account_id;
        End If;

        -- Cursor to update closing stock
        Declare cl_stock_cur Cursor
        For Select a.account_id, Sum(a.mat_value)::Numeric(18,2) as mat_value
            From st.fn_material_balance_wac_by_inv_ac(pcompany_id, pbranch_id, pfinyear,  pto_date) a
            Group By a.account_id;
            -- cursor variables
            vaccount_id BigInt:=-1; vcl_stock_value Numeric(18,4):=0;

        Begin
            For cl_stock_cur_item in cl_stock_cur Loop

                Select cl_stock_cur_item.account_id, cl_stock_cur_item.mat_value Into vaccount_id, vcl_stock_value;

                -- Transfer the Opening Stock Balance into COGC within Opening Stock Group
                Insert Into tb_bs_report (parent_key, group_key, group_path, group_name, account_id, account_code, account_head, dr_cl_bal, cr_cl_bal)  
                Select 'A005', 'B0', 'A005B0', 'Opening Stock', a.account_id * -1, '', a.account_head || '-Op', a.dr_cl_bal, a.cr_cl_bal
                From tb_bs_report a where a.account_id = vaccount_id;

                --	****	Update the TB for Closing Stock
                Update tb_bs_report a
                Set 	dr_cl_bal=vcl_stock_value,
                        cr_cl_bal=0
                Where a.account_id=vaccount_id;

                -- Transfer the Closing Stock Balance into COGC within Closing Stock Group
                Insert Into tb_bs_report (parent_key, group_key, group_path, group_name, account_id, account_code, account_head, dr_cl_bal, cr_cl_bal) 
                Select 'A005', 'B999', 'A005B999', 'Closing Stock', vaccount_id * -2, '', a.account_head || '-Cl', 0, vcl_stock_value
                From ac.account_head a
                Where a.account_id=vaccount_id;	

            End Loop;
        End;
    End If;
	
    Select Sum(a.cr_cl_bal - a.dr_cl_bal) into vtotal_income
    From tb_bs_report a 
    Where a.group_path Like 'A004%'; 

    Select Sum(a.dr_cl_bal - a.cr_cl_bal) Into vtotal_expense
    From tb_bs_report a 
    Where a.group_path Like 'A005%' Or a.group_path Like 'A006%'; 
  
    --	****	Fetch P&L A/c based upon application settings
    Select value Into vpnl_ac From sys.settings Where key='bs_pnl_account';
    If vpnl_ac <> '-1' Then
        Select vtotal_income - vtotal_expense + (a.cr_cl_bal - a.dr_cl_bal) into vnet_profit
        From tb_bs_report a Where a.account_id = cast(vpnl_ac as BigInt);
        If vnet_profit >= 0 Then 
                Update tb_bs_report a
                Set	dr_cl_bal=0,
                        cr_cl_bal=vnet_profit
                Where a.account_id = cast(vpnl_ac as BigInt);
        Else
                Update tb_bs_report a
                Set	dr_cl_bal=vnet_profit * -1,
                        cr_cl_bal=0
                Where a.account_id = cast(vpnl_ac as BigInt);
        End If;
    End If;

    -- ***** Filter and Fetch Assets only  
    Insert Into bs_report (bs_type, parent_key, group_key, group_name, group_path, account_id, account_code, account_head, cl_bal_amt)
    Select 'A', a.parent_key, a.group_key, a.group_name, a.group_path, a.account_id, a.account_code, a.account_head,   
            a.dr_cl_bal - a.cr_cl_bal
    From tb_bs_report a 
    where a.group_path Like 'A001%';

    -- ***** Filter and Fetch Owner's Funds Only  
    Insert Into bs_report (bs_type, parent_key, group_key, group_name, group_path, account_id, account_code, account_head, cl_bal_amt)
    Select 'B', a.parent_key, a.group_key, a.group_name, a.group_path, a.account_id, a.account_code, a.account_head,   
            a.cr_cl_bal - a.dr_cl_bal
    From tb_bs_report a 
    where a.group_path Like 'A002%';  

    -- ***** Filter and Fetch Liabilities Only  
    Insert Into bs_report (bs_type, parent_key, group_key, group_name, group_path, account_id, account_code, account_head, cl_bal_amt)
    Select 'B', a.parent_key, a.group_key, a.group_name, a.group_path, a.account_id, a.account_code, a.account_head,   
            a.cr_cl_bal - a.dr_cl_bal
    From tb_bs_report a 
    where a.group_path Like 'A003%';

    If vpnl_ac = '-1' Then   --	****	There are no application settings
            Insert Into bs_report (bs_type, parent_key, group_key, group_name, group_path, account_id, account_code, account_head, cl_bal_amt)
            Select 'B', 'A003', 'A003', 'Liabilities', 'A003', -2, '', 'Profit/(Loss) for the Period',  
            vtotal_income - vtotal_expense;
    End If;

    -- **** Filter and Fetch Income Only  
    Insert Into bs_report (bs_type, parent_key, group_key, group_name, group_path, account_id, account_code, account_head, cl_bal_amt)
    Select 'C', a.parent_key, a.group_key, a.group_name, a.group_path, a.account_id, a.account_code, a.account_head,   
            a.cr_cl_bal - a.dr_cl_bal
    From tb_bs_report a 
    where a.group_path Like 'A004%' ; 

    -- **** Filter and Fetch COGC Only  
    Insert Into bs_report (bs_type, parent_key, group_key, group_name, group_path, account_id, account_code, account_head, cl_bal_amt)
    Select 'D', a.parent_key, a.group_key, a.group_name, a.group_path, a.account_id, a.account_code, a.account_head,   
            a.dr_cl_bal - a.cr_cl_bal
    From tb_bs_report a 
    where a.group_path Like 'A005%';

    -- **** Filter and Fetch Expenses Only  
    Insert Into bs_report (bs_type, parent_key, group_key, group_name, group_path, account_id, account_code, account_head, cl_bal_amt)
    Select 'D', a.parent_key, a.group_key, a.group_name, a.group_path, a.account_id, a.account_code, a.account_head,   
            a.dr_cl_bal - a.cr_cl_bal
    From tb_bs_report a 
    where a.group_path Like 'A006%';

    -- Return results
    Return Query
    Select a.bs_type, a.parent_key, a.group_key, a.group_name, a.group_path, a.account_id, a.account_code, a.account_head, a.cl_bal_amt
    From bs_report a
    Order by a.bs_type, a.group_path, a.account_head;
End;
$BODY$
language plpgsql;

?==?
Create or Replace Function ac.fn_bs_by_month(pcompany_id BigInt, pbranch_id BigInt, pfinyear Varchar(4), pfrom_date Date, pto_date Date)  
Returns Table   
(   sl_no Int,
    month_name Varchar(10),
    bs_type Varchar(1),  
    parent_key varchar(16),  
    group_key varchar(16), 
    group_name Varchar(250), 
    group_path Varchar(3500),
    account_id BigInt, 
    account_code Varchar(20), 
    account_head Varchar(250), 
    cl_bal_amt Numeric(18,4)
)  
AS
$BODY$
Declare 
    vpnl_ac varchar(50):=0; vtotal_income Numeric(18,4):=0; vtotal_expense Numeric(18,4):=0;
    vpp_profit Numeric(18,4):=0; vyear_begin Date;
Begin
    Drop Table If Exists bs_temp;
    Create Temp Table bs_temp
    (   sl_no Int,
        month_name Varchar(10),
        bs_type Varchar(1),  
        parent_key varchar(16),  
        group_key varchar(16), 
        group_name Varchar(250), 
        group_path Varchar(3500),
        account_id BigInt, 
        account_code Varchar(20), 
        account_head Varchar(250), 
        cl_bal_amt Numeric(18,4)
    );
    
    --	****	Fetch Year Begins and P&L A/c based upon application settings
    Select year_begin Into vyear_begin From sys.finyear Where finyear_code = pfinyear;
    Select value Into vpnl_ac From sys.settings Where key='bs_pnl_account';
    
    -- *****    If from date is greater than finyear_begin, then generate BS for prior period 
    -- *****    with sl_no: 0 This is not returned in the final result, but is required to calculate prior period profit/loss
    -- *****    for adjustment in balance sheet
    If vyear_begin < pfrom_date Then
        Insert Into bs_temp(sl_no, month_name, bs_type, parent_key, group_key, group_name, group_path, 
            account_id, account_code, account_head, cl_bal_amt)
        Select 0, 'PP', a.bs_type, a.parent_key, a.group_key, a.group_name, a.group_path, 
            a.account_id, a.account_code, a.account_head, a.cl_bal_amt
        From ac.fn_bs_report(pcompany_id, pbranch_id, pfinyear, vyear_begin, (pfrom_date - interval '1 day')::Date) a;
    End If;
    
    -- create monthly cursor and fetch BS data
    Declare month_cur Cursor
    For With month_col
        As
        (   select (pfrom_date + (interval '1 month' * generate_series(0,11)))::Date month_start,
            (pfrom_date + (interval '1 month' * generate_series(1,12)) - interval '1 day')::Date month_end
        )
        Select Row_number() Over() sl_no, a.month_start, a.month_end, to_char(a.month_start, 'MON') || ' ' || to_char(a.month_start, 'YYYY') month_name
        From month_col a
        Where month_start Between pfrom_date And pto_date
        Order By a.month_start;
        
    Begin
	    For month_cur_item in month_cur Loop
            Insert Into bs_temp(sl_no, month_name, bs_type, parent_key, group_key, group_name, group_path, 
                account_id, account_code, account_head, cl_bal_amt)
            Select month_cur_item.sl_no, month_cur_item.month_name, a.bs_type, a.parent_key, a.group_key, a.group_name, a.group_path, 
                a.account_id, a.account_code, a.account_head, a.cl_bal_amt
            From ac.fn_bs_report(pcompany_id, pbranch_id, pfinyear, month_cur_item.month_start, month_cur_item.month_end) a;
            
            If vpnl_ac <> '-1' Then -- Calculate and adjust previous period profit to PnL
                Select Coalesce(Sum(a.cl_bal_amt), 0) Into vtotal_income From bs_temp a Where a.sl_no = month_cur_item.sl_no - 1 And a.bs_type = 'C';
                Select Coalesce(Sum(a.cl_bal_amt), 0) Into vtotal_expense From bs_temp a Where a.sl_no = month_cur_item.sl_no - 1 And a.bs_type = 'D';
                vpp_profit := vpp_profit + (vtotal_income - vtotal_expense);
                
                Update bs_temp a
                Set	cl_bal_amt = a.cl_bal_amt + vpp_profit
                Where a.account_id = cast(vpnl_ac as BigInt)
                    And a.sl_no = month_cur_item.sl_no;
            End If;
        End Loop;
    End;
    
    Return Query
    Select a.sl_no, a.month_name, a.bs_type, a.parent_key, a.group_key, a.group_name, a.group_path, 
                a.account_id, a.account_code, a.account_head, a.cl_bal_amt
    From bs_temp a
    Where a.sl_no > 0
    Order by sl_no, group_path;

End
$BODY$
Language plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.fn_tax_payable_report(IN pcompany_id bigint, IN pbranch_id bigint, IN pfrom_date date, IN pto_date date, IN ptax_type_id bigint)
RETURNS TABLE
(
	company_id bigint,
	branch_id bigint,	
	doc_date date,
	customer_id bigint,
	customer varchar(250),
	voucher_id varchar(50),
	tax_tran_id varchar(50),
	tax_schedule_id bigint,
	tax_schedule_desc varchar(120),
	tax_type_id bigint,
	tax_type varchar(250),
	tax_detail_id bigint,
	tax_detail_desc varchar(250),
	desc_order smallint,
	account_id bigint,
	tax_amt_fc numeric(18,4),
	tax_amt numeric(18,4),
	doc_description varchar(250),
	pan varchar(10),
	tan varchar(50),
	ctin varchar(50),
	vtin varchar(50),
	gstin varchar(50),
	stin varchar(50)
)
AS
$BODY$
 Begin 
       DROP TABLE IF EXISTS tax_payable_report_temp;
	CREATE temp TABLE  tax_payable_report_temp
	(		
		company_id bigint,
		branch_id bigint,
		doc_date date,
		customer_id bigint,
		customer varchar(250),
		voucher_id varchar(50),
		tax_tran_id varchar(50),
		tax_schedule_id bigint,
		tax_schedule_desc varchar(120),
		tax_type_id bigint,
		tax_type varchar(250),
		tax_detail_id bigint,
		tax_detail_desc varchar(250),
		desc_order smallint,
		account_id bigint,
		tax_amt_fc numeric(18,4),
		tax_amt numeric(18,4),
		doc_description varchar(250),
		pan varchar(10),
		tan varchar(50),
		ctin varchar(50),
		vtin varchar(50),
		gstin varchar(50),
		stin varchar(50)
	);
	if exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'invoice_control') then
		insert into tax_payable_report_temp(company_id, branch_id, doc_date, customer_id, voucher_id, tax_tran_id, tax_schedule_id, tax_schedule_desc,
				tax_type_id, tax_type, tax_detail_id, tax_detail_desc, desc_order, account_id, 
				tax_amt_fc, tax_amt, 
				doc_description)
		select a.company_id, a.branch_id, a.doc_date, a.customer_id, a.voucher_id, '', 0, 'Taxable Amount', 
			0, 'Taxable Amount', 0, 'Taxable Amount', 1, 0, 
			a.before_tax_amt_fc As tax_amt_fc, a.before_tax_amt As tax_amt, 
			 case when a.media_type_id = 1 then 'Invoice - Publication'
			      when a.media_type_id = 2 then 'Invoice - Radio'
			      when a.media_type_id = 3 then 'Invoice - Television'
			      when a.media_type_id = 4 then 'Invoice - Web'
				when a.media_type_id = 6 then 'Invoice - Event'
			      Else 'Invoice - Miscellaneous'
			  End as doc_description
		from pub.invoice_control a
		inner join (select distinct b.voucher_id from tx.tax_tran b 
				Inner join tx.tax_schedule c on b.tax_schedule_id = c.tax_schedule_id 
				Inner join tx.tax_type d on c.tax_type_id = d.tax_type_id 
				where  (c.tax_type_id = ptax_type_id or ptax_type_id = 0)
			   ) b on a.voucher_id = b.voucher_id  
				where a.company_id = pcompany_id
					and (a.branch_id = pbranch_id or pbranch_id = 0)
					and a.doc_date between pfrom_date and pto_date
					and a.status = 5
		Union ALL		  	
		select a.company_id, a.branch_id, a.doc_date, a.customer_id, a.voucher_id, b.tax_tran_id, b.tax_schedule_id, c.description, 
			c.tax_type_id, d.tax_type, b.tax_detail_id, e.description, 2, b.account_id, 
			b.tax_amt_fc, b.tax_amt, 
			case when a.media_type_id = 1 then 'Invoice - Publication'
				when a.media_type_id = 2 then 'Invoice - Radio'
				when a.media_type_id = 3 then 'Invoice - Television'
				when a.media_type_id = 4 then 'Invoice - Web'
				when a.media_type_id = 6 then 'Invoice - Event'
				Else 'Invoice - Miscellaneous'
			End as doc_description
		from pub.invoice_control a 
		INNER JOIN tx.tax_tran b on a.voucher_id = b.voucher_id 
		Inner join tx.tax_schedule c on b.tax_schedule_id = c.tax_schedule_id 
		Inner join tx.tax_detail e on b.tax_schedule_id = e.tax_schedule_id and b.tax_detail_id = e.tax_detail_id 
		Inner join tx.tax_type d on c.tax_type_id = d.tax_type_id 
		where a.company_id = pcompany_id
			and (a.branch_id = pbranch_id or pbranch_id = 0)
			and a.doc_date between pfrom_date and pto_date
			and a.status = 5
			and (c.tax_type_id = ptax_type_id or ptax_type_id = 0);
	
	End If;
	
        insert into tax_payable_report_temp(company_id, branch_id, doc_date, customer_id, voucher_id, tax_tran_id, tax_schedule_id, tax_schedule_desc,
			tax_type_id, tax_type, tax_detail_id, tax_detail_desc, desc_order, account_id, 
			tax_amt_fc, tax_amt, 
			doc_description)	
	select a.company_id, a.branch_id, a.doc_date, a.customer_id, a.invoice_id, '', 0, 'Taxable Amount', 
		0, 'Taxable Amount', 0, 'Taxable Amount', 1, 0,
		sum(b.credit_amt_fc) As tax_amt_fc, sum(credit_amt) As tax_amt, 
		'Invoice' as doc_description
	from ar.invoice_control a 
	inner Join ar.invoice_tran b on a.invoice_id = b.invoice_id 
	INNER JOIN (select distinct b.voucher_id from tx.tax_tran b 
			Inner join tx.tax_schedule c on b.tax_schedule_id = c.tax_schedule_id 
			Inner join tx.tax_type d on c.tax_type_id = d.tax_type_id 
			where  (c.tax_type_id = ptax_type_id or ptax_type_id = 0)
		   ) c on a.invoice_id = c.voucher_id 	
	where a.company_id = pcompany_id
	        and (a.branch_id = pbranch_id or pbranch_id = 0)
		and a.doc_date between pfrom_date and pto_date
		and a.status = 5
	Group By a.company_id, a.branch_id, a.doc_date, a.invoice_id
	Union All
	select a.company_id, a.branch_id, a.doc_date, a.customer_id, a.invoice_id As voucher_id, b.tax_tran_id, b.tax_schedule_id, c.description,
		c.tax_type_id, d.tax_type, b.tax_detail_id, e.description, 2, b.account_id, 
		b.tax_amt_fc, b.tax_amt, 
		'Invoice' As doc_description
	from ar.invoice_control a 
	INNER JOIN tx.tax_tran b on a.invoice_id = b.voucher_id 
	Inner join tx.tax_schedule c on b.tax_schedule_id = c.tax_schedule_id 
	Inner join tx.tax_detail e on b.tax_schedule_id = e.tax_schedule_id and b.tax_detail_id = e.tax_detail_id 
	Inner join tx.tax_type d on c.tax_type_id = d.tax_type_id 
	where a.company_id = pcompany_id
		and (a.branch_id = pbranch_id or pbranch_id = 0)
		and a.doc_date between pfrom_date and pto_date
		and a.status = 5
		and (d.tax_type_id = ptax_type_id or ptax_type_id = 0);		

	update tax_payable_report_temp
	set customer = a.customer,
		pan = COALESCE(annex_info->'tax_info'->>'pan', ''),
		tan = COALESCE(annex_info->'tax_info'->>'tan', ''),
		ctin = COALESCE(annex_info->'tax_info'->>'ctin', ''),
		vtin = COALESCE(annex_info->'tax_info'->>'vtin', ''),
		gstin = COALESCE(annex_info->'tax_info'->>'gstin', ''),
		stin = COALESCE(annex_info->'tax_info'->>'stin', '')
	From ar.customer a
	where tax_payable_report_temp.customer_id = a.customer_id;
	
        Return query
           select a.company_id, a.branch_id, a.doc_date, a.customer_id, a.customer, a.voucher_id, a.tax_tran_id, a.tax_schedule_id, a.tax_schedule_desc, 
		a.tax_type_id, a.tax_type, a.tax_detail_id, a.tax_detail_desc, a.desc_order,
		a.account_id, a.tax_amt_fc, a.tax_amt, a.doc_description, a.pan, a.tan, a.ctin, a.vtin, a.gstin, a.stin
	   from tax_payable_report_temp a;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.fn_gl_bal_as_on(IN pcompany_id bigint, IN pbranch_id bigint, IN paccount_id BigInt, IN pyear character varying, IN pfrom_date date, IN pto_date date)
RETURNS TABLE(account_id bigint, debit_opening_balance numeric, credit_opening_balance numeric, period_debits numeric, period_credits numeric, debit_closing_balance numeric, credit_closing_balance numeric) 
AS
$BODY$
Begin
	CREATE temp TABLE  tb_op_bal_temp
	(
		account_id bigint,
		debit_opening_balance numeric(18,4), 
		credit_opening_balance numeric(18,4), 
		period_debits numeric(18,4), 
		period_credits numeric(18,4)
	)
	on commit drop;

	--	*****	First Step: This extracts the Opening Balance Before FromDate
	Insert Into tb_op_bal_temp(account_id, debit_opening_balance, credit_opening_balance, period_debits, period_credits)
	select g.account_id, Sum(g.debit_balance), Sum(g.credit_balance), sum(g.debit_balance_period), sum(g.credit_balance_period)
	from (
		Select a.account_id, Sum(a.debit_balance) as debit_balance, Sum(a.credit_balance) as credit_balance,
			0.00 as debit_balance_period, 0.00 as credit_balance_period
		From ac.account_balance a
		Where a.finyear=pyear 
			And a.company_id=pcompany_id 
			And (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_id=0)
			And a.account_id=paccount_id
		Group by a.account_id
		Union All -- GL Summary Before From Date
		Select a.account_id, Sum(a.debit_amt), Sum(a.credit_amt), 0.00, 0.00
		From ac.general_ledger  a 
		Where a.finyear=pyear  
			And a.doc_date<pfrom_date 
			And a.company_id=pcompany_id 
			And (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_id=0)
			And a.account_id=paccount_id
		Group by a.account_id
		Union All -- GL Summary Between From And To
		Select a.account_id, 0.00, 0.00, Sum(a.debit_amt), Sum(a.credit_amt)
		From ac.general_ledger a
		Where a.finyear=pyear  
			And a.doc_date Between pfrom_date And pto_date 
			And a.company_id=pcompany_id
			And (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_id=0)
			And a.account_id=paccount_id
		Group by a.account_id
	   ) g
	group by g.account_id;
	
	return query 
	select 	a.account_id, 
		Case When Sum(a.debit_opening_balance-a.credit_opening_balance)>=0 Then Sum(a.debit_opening_balance-a.credit_opening_balance) Else 0 End,
		Case When Sum(a.debit_opening_balance-a.credit_opening_balance)<0 Then Sum(a.debit_opening_balance-a.credit_opening_balance)*-1 Else 0 End, 
		Sum(a.period_debits), Sum(a.period_credits),
		Case When Sum(a.debit_opening_balance-a.credit_opening_balance+a.period_debits-a.period_credits)>=0 Then Sum(a.debit_opening_balance-a.credit_opening_balance+a.period_debits-a.period_credits) Else 0 End,
		Case When Sum(a.debit_opening_balance-a.credit_opening_balance+a.period_debits-a.period_credits)<0 Then Sum(a.debit_opening_balance-a.credit_opening_balance+a.period_debits-a.period_credits)*-1 Else 0 End
	from tb_op_bal_temp a
	Group By a.account_id;
	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
create OR REPLACE function ac.fn_ref_ledger_balance(pcompany_id bigint, pbranch_id bigint, paccount_id bigint, pto_date date, 
			pvoucher_id varchar(50), pdc varchar(1), pref_ledger_id uuid default null)
RETURNS TABLE  
(	ref_ledger_id uuid, 
	voucher_id varchar(50), 
	doc_date date,
	ref_no varchar(50),
	ref_desc varchar(250),
	account_id bigint,
	balance numeric(18,4),
	branch_id bigint
)
AS
$BODY$ 
Begin	
	DROP TABLE IF EXISTS ref_ledger_balance;	
	create temp TABLE  ref_ledger_balance
	(	
		ref_ledger_id uuid, 
		voucher_id varchar(50),
		doc_date date,
		ref_no varchar(50),
		ref_desc varchar(250),
		account_id bigint,
		balance numeric(18,4),
		branch_id bigint
	);
	
	if pdc='C' then
		Insert into ref_ledger_balance(ref_ledger_id, voucher_id, doc_date, ref_no, ref_desc, account_id, balance, branch_id)		
		Select a.ref_ledger_id, a.voucher_id, a.doc_date, a.ref_no, a.ref_desc, a.account_id, b.balance, a.branch_id
		From ac.ref_ledger a
		Inner Join ( 	Select a.ref_ledger_id, sum(a.balance) as balance
				From (  select a.ref_ledger_id, sum(a.credit_amt)- sum(a.debit_amt) as balance
					From ac.ref_ledger a
					where (a.account_id=paccount_id or paccount_id=0)
						And (a.voucher_id <> pvoucher_id)
						And (a.ref_ledger_id = pref_ledger_id or pref_ledger_id is null)
					Group By a.ref_ledger_id
					Union All -- In Alloc, Debits would be heavier and would automatically result in negatives
					select a.ref_ledger_id, sum(a.net_credit_amt) - sum(a.net_debit_amt) as balance
					From ac.ref_ledger_alloc a
					inner join ac.ref_ledger b on a.ref_ledger_id = b.ref_ledger_id
					where (a.account_id=paccount_id or paccount_id=0) and a.affect_voucher_id <> pvoucher_id
						And (a.ref_ledger_id = pref_ledger_id or pref_ledger_id is null)
					Group By a.ref_ledger_id
				     ) a
				Group By a.ref_ledger_id
			   ) b on a.ref_ledger_id=b.ref_ledger_id
				where a.doc_date <= pto_date 
					And (a.account_id=paccount_id or paccount_id=0)
					And (b.balance <> 0)
					And (a.branch_id=pbranch_id or pbranch_id=0)
					And (a.ref_ledger_id = pref_ledger_id or pref_ledger_id is null);
			-- Remove all setellement/Payables
			Delete from ref_ledger_balance a
			Where a.balance > 0;

			-- Convert negative advances to positive
			Update ref_ledger_balance a
			set balance = a.balance * -1;
	End If;
	
	if pdc='D' then
		Insert into ref_ledger_balance(ref_ledger_id, voucher_id, doc_date, ref_no, ref_desc, account_id, balance, branch_id)		
		Select a.ref_ledger_id, a.voucher_id, a.doc_date, a.ref_no, a.ref_desc, a.account_id, b.balance, a.branch_id
		From ac.ref_ledger a
		Inner Join ( 	Select a.ref_ledger_id, sum(a.balance) as balance
				From (  select a.ref_ledger_id, sum(a.debit_amt)- sum(a.credit_amt) as balance
					From ac.ref_ledger a
					where (a.account_id=paccount_id or paccount_id=0)
						And (a.voucher_id <> pvoucher_id)
						And (a.ref_ledger_id = pref_ledger_id or pref_ledger_id is null)
					Group By a.ref_ledger_id
					Union All -- In Alloc, Debits would be heavier and would automatically result in negatives
					select a.ref_ledger_id, sum(a.net_debit_amt) - sum(a.net_credit_amt) as balance
					From ac.ref_ledger_alloc a
					inner join ac.ref_ledger b on a.ref_ledger_id = b.ref_ledger_id
					where (a.account_id=paccount_id or paccount_id=0) and a.affect_voucher_id <> pvoucher_id
						And (a.ref_ledger_id = pref_ledger_id or pref_ledger_id is null)
					Group By a.ref_ledger_id
				     ) a
				Group By a.ref_ledger_id
			   ) b on a.ref_ledger_id=b.ref_ledger_id
				where a.doc_date <= pto_date 
					And (a.account_id=paccount_id or paccount_id=0)
					And (b.balance <> 0)
					And (a.branch_id=pbranch_id or pbranch_id=0)
					And (a.ref_ledger_id = pref_ledger_id or pref_ledger_id is null);
			-- Remove all setellement/Payables
			Delete from ref_ledger_balance a
			Where a.balance > 0;

			-- Convert negative advances to positive
			Update ref_ledger_balance a
			set balance = a.balance * -1;
	End If;

	
	return query 
	select a.ref_ledger_id, a.voucher_id, a.doc_date, a.ref_no, a.ref_desc, a.account_id, a.balance, a.branch_id
	from ref_ledger_balance a;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
create OR REPLACE function ac.fn_ref_ledger_report_detailed(pcompany_id bigint, pbranch_id bigint, paccount_id bigint, pto_date date)
RETURNS TABLE  
(	
	category character,
	ref_ledger_id uuid, 
	voucher_id varchar(50), 
	doc_date date,
	account_id bigint,
	bill_date date,
	debit_amt numeric(18,4),
	credit_amt numeric(18,4),
	narration varchar(500),
	account_head varchar(250)
)
AS
$BODY$ 
Begin	
	DROP TABLE IF EXISTS ref_ledger_detailed_temp;	
	create temp TABLE ref_ledger_detailed_temp
	(	
		category character,
		ref_ledger_id uuid, 
		voucher_id varchar(50), 
		doc_date date,
		account_id bigint,
		bill_date date,
		debit_amt numeric(18,4),
		credit_amt numeric(18,4),
		narration varchar(500),
		account_head varchar(250)
	);
	
	Insert into ref_ledger_detailed_temp(category, ref_ledger_id, voucher_id, doc_date, account_id, bill_date, 
		debit_amt, credit_amt, narration)
	Select 'A', a.ref_ledger_id, a.voucher_id, a.doc_date, a.account_id, null, 
		a.debit_amt, a.credit_amt, ''
	From ac.ref_ledger a
	where a.doc_date <= pto_date 
		And (a.account_id=paccount_id or paccount_id=0)
		And (a.branch_id=pbranch_id or pbranch_id=0)
	Union All
	Select 'B', a.ref_ledger_id, a.affect_voucher_id, b.doc_date, a.account_id, a.affect_doc_date, 
		a.net_debit_amt, a.net_credit_amt, ''
	From ac.ref_ledger_alloc a
	Inner join ac.ref_ledger b on a.ref_ledger_id = b.ref_ledger_id
	where a.affect_doc_date <= pto_date 
		And (a.account_id=paccount_id or paccount_id=0)
		And (a.branch_id=pbranch_id or pbranch_id=0)
		And a.status = 5;

	update ref_ledger_detailed_temp a
	set account_head = b.account_head
	from ac.account_head b
	where a.account_id = b.account_id;	

	return query 
	select a.category, a.ref_ledger_id, a.voucher_id, a.doc_date, a.account_id, a.bill_date, 
		a.debit_amt, a.credit_amt, a.narration, a.account_head
	from ref_ledger_detailed_temp a;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
create OR REPLACE function ac.fn_ref_ledger_report(pcompany_id bigint, pbranch_id bigint, paccount_id bigint, pto_date date)
RETURNS TABLE  
(	ref_ledger_id uuid, 
	voucher_id varchar(50), 
	vch_tran_id varchar(50),
	doc_date date,
	account_id bigint,
	balance numeric(18,4),
	branch_id bigint,
	ref_no varchar(50),
	ref_desc varchar(250),
	account_head varchar(250)
)
AS
$BODY$ 
Begin	
	DROP TABLE IF EXISTS ref_ledger_temp;	
	create temp TABLE ref_ledger_temp
	(	
		ref_ledger_id uuid, 
		voucher_id varchar(50), 
		vch_tran_id varchar(50),
		doc_date date,
		account_id bigint,
		balance numeric(18,4),
		branch_id bigint,
		ref_no varchar(50),
		ref_desc varchar(250),
		account_head varchar(250)
	);

	DROP TABLE IF EXISTS ref_balance;	
	create temp TABLE  ref_balance
	(	
		ref_ledger_id uuid primary key,
		balance numeric(18,4)
	);

	Insert into ref_balance(ref_ledger_id, balance)		
	select a.ref_ledger_id, sum(a.balance) 
	From (
		select a.ref_ledger_id, sum(a.debit_amt - a.credit_amt) as balance
		From ac.ref_ledger a
		 where a.doc_date <= pto_date
			And (a.account_id = paccount_id or paccount_id = 0)
			And (a.branch_id=pbranch_id or pbranch_id=0)
		Group by a.ref_ledger_id
		union all
		select a.ref_ledger_id, sum(a.net_debit_amt-a.net_credit_amt) 
		From ac.ref_ledger_alloc a
		where a.affect_doc_date <= pto_date
			And (a.account_id = paccount_id or paccount_id = 0)
			And (a.branch_id=pbranch_id or pbranch_id=0)
			And a.status = 5
		Group by a.ref_ledger_id
	) a
	Group by a.ref_ledger_id;

	Insert into ref_ledger_temp(ref_ledger_id, voucher_id, vch_tran_id, doc_date, account_id, balance, branch_id, 
		ref_no, ref_desc, account_head)
	Select a.ref_ledger_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id, b.balance, 
		a.branch_id, a.ref_no, a.ref_desc, c.account_head
	From ac.ref_ledger a
	Inner Join ref_balance b on a.ref_ledger_id=b.ref_ledger_id
	Inner Join ac.account_head c on a.account_id=c.account_id
	where a.doc_date <= pto_date 
		And (a.account_id=paccount_id or paccount_id=0)
		And  b.balance <> 0
		And (a.branch_id=pbranch_id or pbranch_id=0);

	return query 
	select a.ref_ledger_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id, a.balance,  
		a.branch_id, a.ref_no, a.ref_desc, a.account_head
	from ref_ledger_temp a;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
Drop  function if exists ac.fn_sub_head_ledger_report(pcompany_id bigint, pbranch_id bigint, pfinyear varchar(4), paccount_id bigint, psub_head_id bigint, pfrom_date date, pto_date date);

?==?
create OR REPLACE function ac.fn_sub_head_ledger_report(pcompany_id bigint, pbranch_id bigint, pfinyear varchar(4), paccount_id bigint, psub_head_id bigint, pfrom_date date, pto_date date, pshow_opbl bool)
RETURNS TABLE  
(	
	finyear varchar(4), 
	voucher_id varchar(50), 
	doc_date date,
	account_id bigint,
	account_head varchar(250),
	sub_head_id bigint,
	sub_head varchar(250),
	dc char,
	debit_amt numeric(18,4),
	credit_amt numeric(18,4),
	narration varchar(500)
)
AS
$BODY$ 
Begin	
	DROP TABLE IF EXISTS sub_head_temp;	
	create temp TABLE sub_head_temp
	(
		
		finyear varchar(4), 
		voucher_id varchar(50), 
		doc_date date,
		account_id bigint,
		account_head varchar(250),
		sub_head_id bigint,
		sub_head varchar(250),
		dc char,
		debit_amt numeric(18,4),
		credit_amt numeric(18,4),
		narration varchar(500)
	);
	
    if pshow_opbl then 
        --- 	Step 1: Extract Opening Balance
        Insert into sub_head_temp(finyear, voucher_id, doc_date, account_id, account_head, sub_head_id, sub_head, 
            dc,
            debit_amt, 
            credit_amt, 
            narration)
        Select a.finyear, 'Opening Balance', pfrom_date, a.account_id, c.account_head, a.sub_head_id, b.sub_head, 
            Case when sum(a.debit_amt-a.credit_amt) > 0 then 'D' else 'C' end as dc,
            case when sum(a.debit_amt-a.credit_amt) >= 0 then sum(a.debit_amt-a.credit_amt) else 0 end,
            Case when sum(a.debit_amt-a.credit_amt) < 0 then sum(a.credit_amt-a.debit_amt) else 0 end,
            ''
        From ac.sub_head_ledger a
        inner join ac.sub_head b on a.sub_head_id = b.sub_head_id
        inner join ac.account_head c on a.account_id = c.account_id
        where a.company_id = pcompany_id
            And (a.branch_id=pbranch_id or pbranch_id=0)
            And (a.account_id=paccount_id or paccount_id=-99)
            And (a.sub_head_id=psub_head_id or psub_head_id=0)
            And a.doc_date < pfrom_date 
            And a.finyear = pfinyear
        Group By a.finyear, a.account_id, c.account_head, a.sub_head_id, b.sub_head
        Having sum(a.debit_amt-a.credit_amt) != 0;
	End If;
	
	--- 	Step 1: Extract Transaction during the period
	Insert into sub_head_temp(finyear, voucher_id, doc_date, account_id, account_head, sub_head_id, sub_head, 
		dc,
		debit_amt, credit_amt, narration)
	Select a.finyear, a.voucher_id, a.doc_date, a.account_id, c.account_head, a.sub_head_id, b.sub_head, 
		Case when a.debit_amt > 0 then 'D' else 'C' end as dc,
		a.debit_amt, a.credit_amt, a.narration
	From ac.sub_head_ledger a
	inner join ac.sub_head b on a.sub_head_id = b.sub_head_id
	inner join ac.account_head c on a.account_id = c.account_id
	where a.company_id = pcompany_id
		And (a.branch_id=pbranch_id or pbranch_id=0)
		And (a.account_id=paccount_id or paccount_id=-99)
		And (a.sub_head_id=psub_head_id or psub_head_id=0)
		And a.doc_date between pfrom_date  and pto_date
		And a.finyear = pfinyear
		and a.status = 5;

	

	return query 
	select a.finyear, a.voucher_id, a.doc_date, a.account_id, a.account_head, a.sub_head_id, a.sub_head, a.dc, a.debit_amt, a.credit_amt, a.narration
	from sub_head_temp a;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.fn_tax_credit_report(IN pcompany_id bigint, IN pbranch_id bigint, IN pfrom_date date, IN pto_date date, IN ptax_type_id bigint)
RETURNS TABLE
(
	company_id bigint,
	branch_id bigint,	
	doc_date date,
	supplier_id bigint,
	supplier varchar(250),
	voucher_id varchar(50),
	tax_tran_id varchar(50),
	tax_schedule_id bigint,
	tax_schedule_desc varchar(120),
	tax_type_id bigint,
	tax_type varchar(250),
	tax_detail_id bigint,
	tax_detail_desc varchar(250),
	desc_order smallint,
	account_id bigint,
	tax_amt_fc numeric(18,4),
	tax_amt numeric(18,4),
	doc_description varchar(250),
	pan varchar(10),
	tan varchar(50),
	ctin varchar(50),
	vtin varchar(50),
	gstin varchar(50),
	stin varchar(50)
)
AS
$BODY$
 Begin 
       DROP TABLE IF EXISTS tax_payable_report_temp;
	CREATE temp TABLE  tax_payable_report_temp
	(		
		company_id bigint,
		branch_id bigint,
		doc_date date,
		supplier_id bigint,
		supplier varchar(250),
		voucher_id varchar(50),
		tax_tran_id varchar(50),
		tax_schedule_id bigint,
		tax_schedule_desc varchar(120),
		tax_type_id bigint,
		tax_type varchar(250),
		tax_detail_id bigint,
		tax_detail_desc varchar(250),
		desc_order smallint,
		account_id bigint,
		tax_amt_fc numeric(18,4),
		tax_amt numeric(18,4),
		doc_description varchar(250),
		pan varchar(10),
		tan varchar(50),
		ctin varchar(50),
		vtin varchar(50),
		gstin varchar(50),
		stin varchar(50)
	);
	if exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'abp_control') then
		insert into tax_payable_report_temp(company_id, branch_id, doc_date, supplier_id, voucher_id, tax_tran_id, tax_schedule_id, tax_schedule_desc,
				tax_type_id, tax_type, tax_detail_id, tax_detail_desc, desc_order, account_id, 
				tax_amt_fc, tax_amt, 
				doc_description)
		select a.company_id, a.branch_id, a.doc_date, a.account_id, a.voucher_id || E'\n'|| a.bill_no, 
			'', 0, 'Taxable Amount', 0, 'Taxable Amount', 0, 'Taxable Amount', 1, 0, 
			sum(c.gross_amt_fc) As tax_amt_fc, sum(c.gross_amt) As tax_amt, 
			 case when a.media_type_id = 1 then 'Bills Payable - Publication'
			      when a.media_type_id = 2 then 'Bills Payable - Radio'
			      when a.media_type_id = 3 then 'Bills Payable - Television'
			      when a.media_type_id = 4 then 'Bills Payable - Web'
				when a.media_type_id = 6 then 'Bills Payable - Event'
			      Else 'Bills Payable - Miscellaneous'
			  End as doc_description
		from pub.abp_control a
		inner join pub.abp_tran c on a.voucher_id = c.voucher_id
		inner join (select distinct b.voucher_id from tx.tax_tran b 
				Inner join tx.tax_schedule c on b.tax_schedule_id = c.tax_schedule_id 
				Inner join tx.tax_type d on c.tax_type_id = d.tax_type_id 
				where  (c.tax_type_id = ptax_type_id or ptax_type_id = 0)
			   ) b on a.voucher_id = b.voucher_id  
		where a.company_id = pcompany_id
			and (a.branch_id = pbranch_id or pbranch_id = 0)
			and a.doc_date between pfrom_date and pto_date
			and a.status = 5
		Group By a.company_id, a.branch_id, a.doc_date, a.account_id, a.voucher_id, a.bill_no, a.media_type_id
		Union ALL		  	
		select a.company_id, a.branch_id, a.doc_date, a.account_id, a.voucher_id || E'\n'|| a.bill_no, 
			b.tax_tran_id, b.tax_schedule_id, c.description, 
			c.tax_type_id, d.tax_type, b.tax_detail_id, e.description, 2, b.account_id, 
			b.tax_amt_fc, b.tax_amt, 
			case when a.media_type_id = 1 then 'Bills Payable - Publication'
				when a.media_type_id = 2 then 'Bills Payable - Radio'
				when a.media_type_id = 3 then 'Bills Payable - Television'
				when a.media_type_id = 4 then 'Bills Payable - Web'
				when a.media_type_id = 6 then 'Bills Payable - Event'
				Else 'Bills Payable - Miscellaneous'
			End as doc_description
		from pub.abp_control a 
		INNER JOIN tx.tax_tran b on a.voucher_id = b.voucher_id 
		Inner join tx.tax_schedule c on b.tax_schedule_id = c.tax_schedule_id 
		Inner join tx.tax_detail e on b.tax_schedule_id = e.tax_schedule_id and b.tax_detail_id = e.tax_detail_id
		Inner join tx.tax_type d on c.tax_type_id = d.tax_type_id 
		where a.company_id = pcompany_id
			and (a.branch_id = pbranch_id or pbranch_id = 0)
			and a.doc_date between pfrom_date and pto_date
			and a.status = 5
			and (c.tax_type_id = ptax_type_id or ptax_type_id = 0);
	
	End If;
	
        insert into tax_payable_report_temp(company_id, branch_id, doc_date, supplier_id, voucher_id, tax_tran_id, tax_schedule_id, tax_schedule_desc,
			tax_type_id, tax_type, tax_detail_id, tax_detail_desc, desc_order, account_id, 
			tax_amt_fc, tax_amt, 
			doc_description)	
	select a.company_id, a.branch_id, a.doc_date, a.supplier_id, a.bill_id || E'\n'|| a.bill_no, '', 0, 'Taxable Amount', 
		0, 'Taxable Amount', 0, 'Taxable Amount', 1, 0,
		sum(b.debit_amt_fc) As tax_amt_fc, sum(debit_amt) As tax_amt, 
		'Bill' as doc_description
	from ap.bill_control a 
	inner Join ap.bill_tran b on a.bill_id = b.bill_id 
	INNER JOIN (select distinct b.voucher_id from tx.tax_tran b 
			Inner join tx.tax_schedule c on b.tax_schedule_id = c.tax_schedule_id 
			Inner join tx.tax_type d on c.tax_type_id = d.tax_type_id 
			where  (c.tax_type_id = ptax_type_id or ptax_type_id = 0)
		   ) c on a.bill_id = c.voucher_id 	
	where a.company_id = pcompany_id
	        and (a.branch_id = pbranch_id or pbranch_id = 0)
		and a.doc_date between pfrom_date and pto_date
		and a.status = 5
	Group By a.company_id, a.branch_id, a.doc_date, a.bill_id
	Union All
	select a.company_id, a.branch_id, a.doc_date, a.supplier_id, a.bill_id  || E'\n'|| a.bill_no as voucher_id, 
		b.tax_tran_id, b.tax_schedule_id, c.description,
		c.tax_type_id, d.tax_type, b.tax_detail_id, e.description, 2, b.account_id, 
		b.tax_amt_fc, b.tax_amt, 
		'Bill' As doc_description
	from ap.bill_control a 
	INNER JOIN tx.tax_tran b on a.bill_id = b.voucher_id 
	Inner join tx.tax_schedule c on b.tax_schedule_id = c.tax_schedule_id
	Inner join tx.tax_detail e on b.tax_schedule_id = e.tax_schedule_id and b.tax_detail_id = e.tax_detail_id 
	Inner join tx.tax_type d on c.tax_type_id = d.tax_type_id 
	where a.company_id = pcompany_id
		and (a.branch_id = pbranch_id or pbranch_id = 0)
		and a.doc_date between pfrom_date and pto_date
		and a.status = 5
		and (d.tax_type_id = ptax_type_id or ptax_type_id = 0);		

	update tax_payable_report_temp
	set supplier = a.supplier,
		pan = COALESCE(annex_info->'satutory_details'->>'pan', ''),
		tan = COALESCE(annex_info->'satutory_details'->>'tan', ''),
		ctin = COALESCE(annex_info->'satutory_details'->>'ctin', ''),
		vtin = COALESCE(annex_info->'satutory_details'->>'vtin', ''),
		stin = COALESCE(annex_info->'satutory_details'->>'service_tax_no', '')
	From ap.supplier a
	where tax_payable_report_temp.supplier_id = a.supplier_id;
	
        Return query
           select a.company_id, a.branch_id, a.doc_date, a.supplier_id, a.supplier, a.voucher_id, a.tax_tran_id, a.tax_schedule_id, a.tax_schedule_desc, 
		a.tax_type_id, a.tax_type, a.tax_detail_id, a.tax_detail_desc, a.desc_order,
		a.account_id, a.tax_amt_fc, a.tax_amt, a.doc_description, a.pan, a.tan, a.ctin, a.vtin, a.gstin, a.stin
	   from tax_payable_report_temp a;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.fn_gst_pymt_report(In pvoucher_id varchar(50))  
RETURNS TABLE
(
	voucher_id varchar(50), 
	doc_date date,
        fc_type_id bigint,
        fc_type varchar(20),
	supplier varchar(250),
	supplier_address varchar(250),
	supplier_state varchar(25),
	supplier_gstin varchar(15),
	settlement_type varchar(50), 
	account_id bigint,
	account_head varchar(250), 
	exch_rate numeric(18,6),
	status smallint,
	credit_amt numeric(18,4), 
	cheque_number varchar(20),
	cheque_date date, 
	cheque_bank varchar(50), 
	cheque_branch varchar(50),	
	narration varchar(500),
	amt_in_words varchar(250), 
	amt_in_words_fc varchar(250), 
	remarks varchar(500),
	entered_by varchar(100), 
	posted_by varchar(100),
	is_ac_payee boolean,
	is_non_negotiable boolean,
	bill_no varchar(25),
	bill_amt numeric(18,4),
	bill_date date,
	bt_amt numeric(18,4),
	tax_amt numeric(18,4),
	rc_tax_amt numeric(18,4),
	apply_rc boolean,
	rc_sec varchar,
	rc_sec_desc varchar,
	branch_id bigint,
	branch_state varchar,
	branch_gstin varchar(15),
	round_off_amt numeric(18,4)
) 
AS
$BODY$
BEGIN	
	return query
	select a.voucher_id, a.doc_date, a.fc_type_id, e.fc_type, 
		COALESCE(a.annex_info->'gst_input_info'->>'supplier_name','')::varchar as supplier,
		COALESCE(a.annex_info->'gst_input_info'->>'supplier_address','')::varchar as supplier_address,
		(f.gst_state_code || ' - ' || f.state_name)::varchar as supplier_state,
		COALESCE(a.annex_info->'gst_input_info'->>'supplier_gstin','')::varchar as supplier_gstin,
		case when COALESCE((a.annex_info->>'pymt_type')::int,0) = 0 then 'Bank'::varchar when COALESCE((a.annex_info->>'pymt_type')::int,0) = 2 then 'Cash'::varchar Else 'Journal'::varchar End as settlement_type, 
		a.account_id, c.account_head, a.exch_rate, a.status, coalesce(a.credit_amt,0) as credit_amt, 
		a.cheque_number, a.cheque_date, a.cheque_bank, a.cheque_branch, a.narration, a.amt_in_words, a.amt_in_words_fc, a.remarks, 
		d.entered_by, d.posted_by, a.is_ac_payee, a.is_non_negotiable,
		COALESCE(a.annex_info->>'bill_no','')::varchar as bill_no,
		COALESCE(a.annex_info->>'bill_amt','0')::numeric(18,4) as bill_amt,
		COALESCE(a.annex_info->>'bill_date',current_date::varchar)::date as bill_date,
		COALESCE(a.annex_info->>'bt_amt','0')::numeric(18,4) as bt_amt,
		COALESCE(a.annex_info->>'tax_amt','0')::numeric(18,4) as tax_amt,
		COALESCE(a.annex_info->'gst_rc_info'->>'rc_tax_amt','0')::numeric(18,4) as rc_tax_amt,
		COALESCE(a.annex_info->'gst_rc_info'->>'apply_rc','false')::boolean as apply_rc,
		COALESCE(a.annex_info->'gst_rc_info'->>'rc_sec','')::varchar as rc_sec,
		'Reverse charge '::varchar as rc_sec_desc,
		a.branch_id, (h.gst_state_code || ' - ' || h.state_name)::varchar as branch_state, g.gstin,
		COALESCE(a.annex_info->>'round_off_amt','0')::numeric(18,4) as round_off_amt
	from ac.vch_control a
		inner join ac.account_head c on a.account_id = c.account_id
		inner join sys.doc_es d on a.voucher_id = d.voucher_id
                inner join ac.fc_type e on a.fc_type_id = e.fc_type_id
		inner join tx.gst_state f on (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = f.gst_state_id
		inner join sys.branch g on a.branch_id = g.branch_id
		inner join tx.gst_state h on g.gst_state_id = h.gst_state_id
		where a.voucher_id = pvoucher_id;	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.fn_gst_pymt_tran_report(In pvoucher_id varchar(50))  
RETURNS TABLE
(	sl_no smallint,
	vch_tran_id varchar(50), 
	voucher_id varchar(50), 
	branch_id bigint,
	account_id bigint,
	account_head varchar(250),
	debit_amt numeric(18,4),
	debit_amt_fc numeric(18,4),
	credit_amt numeric(18,4), 
	credit_amt_fc numeric(18,4), 
	hsn_sc_id bigint,
	hsn_sc_code varchar(8),
	hsc_sc_type varchar(1),
	hsn_desc varchar,
	gst_rate_id bigint,
	bt_amt numeric(18,4),
	sgst_pcnt numeric(5,2),
	sgst_amt numeric(18,2),
	cgst_pcnt numeric(5,2),
	cgst_amt numeric(18,2),
	igst_pcnt numeric(5,2),
	igst_amt numeric(18,2),
	cess_pcnt numeric(5,2),
	cess_amt numeric(18,2)
) 
AS
$BODY$
BEGIN	
	return query
	select a.sl_no, a.vch_tran_id, a.voucher_id, a.branch_id, a.account_id, c.account_head, a.debit_amt, a.debit_amt_fc, a.credit_amt, a.credit_amt_fc, 
	a.hsn_sc_id, b.hsn_sc_code, b.hsn_sc_type, (d.hsn_sc_code || '-' || d.hsn_sc_desc)::varchar as hsn_desc, b.gst_rate_id, b.bt_amt,
	b.sgst_pcnt, b.sgst_amt, b.cgst_pcnt, b.cgst_amt, b.igst_pcnt, b.igst_amt, b.cess_pcnt, b.cess_amt
	from ac.vch_tran a
	inner join tx.gst_tax_tran b on a.vch_tran_id = b.gst_tax_tran_id
	inner join ac.account_head c on a.account_id = c.account_id
	inner join tx.hsn_sc d on a.hsn_sc_id = d.hsn_sc_id
	where a.voucher_id = pvoucher_id;	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.fn_gst_pymt_tran_rpt(pvoucher_id character varying)
    RETURNS TABLE
(
        sl_no smallint, 
        vch_tran_id varchar(50), 
        voucher_id varchar(50), 
        branch_id bigint, 
        account_id bigint, 
        account_head varchar(250),
        debit_amt numeric(18,4), 
        debit_amt_fc numeric(18,4), 
        credit_amt numeric(18,4), 
        credit_amt_fc numeric(18,4),
        hsn_sc_id bigint, 
        hsn_sc_code varchar(8),
	hsc_sc_type varchar(1),
        hsn_desc varchar(20), 
        gst_rate_id bigint, 
        bt_amt numeric(18,4), 
        sgst_pcnt numeric(5,2), 
        sgst_amt numeric(18,2), 
        cgst_pcnt numeric(5,2), 
        cgst_amt numeric(18,2), 
        igst_pcnt numeric(5,2), 
        igst_amt numeric(18,2), 
        cess_pcnt numeric(5,2), 
        cess_amt numeric(18,2), 
        line_item_gst boolean,
        bill_no varchar(50), 
        bill_dt date, 
        bill_amt numeric(18,2), 
        roff_amt numeric(18,2), 
        supp_name varchar(250),
        supp_addr varchar(250),
        description character varying
) 
AS 
$BODY$
BEGIN	

	return query
	select a.sl_no, a.vch_tran_id, a.voucher_id, a.branch_id, a.account_id, d.account_head, a.debit_amt, a.debit_amt_fc, a.credit_amt, a.credit_amt_fc, 
                e.hsn_sc_id, c.hsn_sc_code, c.hsn_sc_type, (e.hsn_sc_code || '-' || e.hsn_sc_desc)::varchar as hsn_desc, c.gst_rate_id, c.bt_amt,
                c.sgst_pcnt, c.sgst_amt,c.cgst_pcnt, c.cgst_amt, c.igst_pcnt, c.igst_amt, c.cess_pcnt, c.cess_amt, (annex_info->>'line_item_gst')::boolean as line_item_gst,
                a.bill_no, a.bill_dt, a.bill_amt, a.roff_amt, a.supp_name, a.supp_addr, a.tran_desc
	from ac.vch_tran a
        inner join ac.vch_control b on a.voucher_id=b.voucher_id
	inner join tx.gst_tax_tran c on a.vch_tran_id = c.gst_tax_tran_id
	inner join ac.account_head d on a.account_id = d.account_id
	left join tx.hsn_sc e on c.hsn_sc_code = e.hsn_sc_code
	where a.voucher_id = pvoucher_id;	
    
END;
$BODY$
LANGUAGE 'plpgsql'

?==?
CREATE OR REPLACE FUNCTION ac.fn_si_report(IN pvoucher_id varchar(50))
  RETURNS TABLE 
  (
	voucher_id varchar(50),
	doc_type varchar(20),
	branch_id bigint,
	doc_date date,
	status smallint,
	bill_no character varying,
	bill_date date,
	supplier_name character varying,
	narration character varying,
	amt_in_words character varying,
	remarks character varying,
	credit_amt numeric(18,4),
	credit_amt_fc numeric(18,4),
	rc_sec_id bigint,
	rc_sec_desc character varying,
	entered_by character varying,
	posted_by character varying,
	supplier_address character varying,
	gst_state text,
	bt_amt numeric,
	tax_amt numeric
  )
As
$BODY$
Begin
	return query
	select 
	a.voucher_id, a.doc_type, a.branch_id, a.doc_date, a.status, 
	(a.annex_info->>'bill_no')::varchar as bill_no, (a.annex_info->>'bill_date')::date as bill_date, 
	(a.annex_info->'gst_input_info'->>'supplier_name')::varchar as supplier_name, a.narration, 
	a.amt_in_words, a.remarks, a.credit_amt, a.credit_amt_fc,  
	(a.annex_info->'gst_rc_info'->>'rc_sec_id')::bigint as rc_sec_id, j.rc_sec_desc,
	e.entered_by, e.posted_by,
	(annex_info->'gst_input_info'->>'supplier_address')::varchar as supplier_address, 
	i.gst_state_code || ' - ' || i.state_name as gst_state,
	(a.annex_info->>'bt_amt')::numeric as bt_amt, (a.annex_info->>'tax_amt')::numeric as tax_amt
		from ac.vch_control a
		inner join sys.doc_es e on a.voucher_id = e.voucher_id
		inner join tx.gst_state i on (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = i.gst_state_id
		inner join tx.rc_sec j on (a.annex_info->'gst_rc_info'->>'rc_sec_id')::bigint = j.rc_sec_id
		where a.voucher_id = pvoucher_id;
End;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.fn_si_tran_report(IN pvoucher_id varchar(50))
  RETURNS TABLE 
  (
	sl_no smallint,
	si_tran_id varchar(50),
	ref_tran_id varchar(50),
	ref_date date,
	branch_id bigint,
	branch_name varchar(100),
	account_id bigint,
	account_head character varying,
	hsn_sc_id bigint,
	hsn_sc_desc character varying,
	hsn_sc_code varchar(8),
	apply_itc boolean,
	bt_amt numeric(18,4),
	sgst_pcnt numeric(5,2),
	sgst_amt numeric(18,2),
	cgst_pcnt numeric(5,2),
	cgst_amt numeric(18,2),
	igst_pcnt numeric(5,2),
	igst_amt numeric(18,2),
	cess_pcnt numeric(5,2),
	cess_amt numeric(18,2)
  )
As
$BODY$
Begin
	return query
	select a.sl_no, a.si_tran_id, a.ref_tran_id, a.ref_date, 
		a.branch_id, e.branch_name,
		a.account_id, d.account_head,
		a.hsn_sc_id, c.hsn_sc_desc,
		b.hsn_sc_code, b.apply_itc, b.bt_amt, b.sgst_pcnt, b.sgst_amt,
		b.cgst_pcnt, b.cgst_amt, b.igst_pcnt, b.igst_amt, b.cess_pcnt, b.cess_amt
	from ac.si_tran a
	inner join tx.gst_tax_tran b on b.gst_tax_tran_id = a.si_tran_id
	inner join tx.hsn_sc c on a.hsn_sc_id = c. hsn_sc_id
	inner join ac.account_head d on a.account_id = d.account_id
	inner join sys.branch e on a.branch_id = e.branch_id
	where a.voucher_id = pvoucher_id;
End;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.fn_pdc_report(
    pcompany_id bigint,
    pbranch_id bigint,
    pdoc_type character varying,
    pto_date date)
    RETURNS TABLE( branch_id bigint, branch_name character varying, doc_type character varying,
                   doc_type_desc character varying, doc_date date, voucher_id character varying,
                   amt numeric, cheque_date date, cheque_number character varying, pdc_date date, vch_caption character varying)
AS 
$BODY$
Begin 

    DROP TABLE IF EXISTS pdc_temp;
    CREATE temp TABLE  pdc_temp
    (
        branch_id bigint,
        branch_name varchar(100),
        doc_type varchar(4),
        doc_type_desc varchar(50),
        doc_date date,
        voucher_id varchar(50),		
        amt numeric(18,4),
        cheque_date date,
        cheque_number varchar(100),
        pdc_date date,
        vch_caption character varying
    );
       
    Insert into pdc_temp( branch_id, doc_type, doc_type_desc, doc_date, voucher_id, 
                          amt, cheque_date, cheque_number, pdc_date, vch_caption)
    Select x.branch_id, x.doc_type, x.doc_type_desc, x.doc_date, x.voucher_id, 
           x.amt, x.cheque_date, x.cheque_number, x.pdc_date, x.vch_caption
    from (select a.branch_id, a.doc_type,
            case when a.doc_type in ('BPV') then 'Bank Payment'
                 when a.doc_type in ('PAYB') then 'GST Bank Payment'
                 when a.doc_type in ('BRV') then 'Bank Receipt'
                 when a.doc_type in ('CV') then 'Contra Voucher'
                 when a.doc_type in ('PAYV') then 'Payment Voucher'
                 when a.doc_type in ('PPT') then 'Payroll Payment'
                 else ''
            end as doc_type_desc,
            a.doc_date,  a.voucher_id,                
            case when debit_amt=0 then credit_amt else debit_amt end as amt,
            a.cheque_date, a.cheque_number, a.pdc_date, a.vch_caption
    from ac.vch_control a
    Where a.cheque_date > pto_date
         And a.company_id = pcompany_id
         And (a.branch_id in (select b.branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id) b) or pbranch_id=0)
         And a.is_pdc=true
         And a.doc_type in  ('BPV','BRV','PAYB','CV','PAYV','PPT')
         And (a.doc_type = pdoc_type or pdoc_type='All')
         And a.collected=false
    Union All
    Select a.branch_id, a.doc_type,
           case when a.doc_type in ('ASP') then 'Advance Supplier Payment'
                when a.doc_type in ('MSP') then 'Multi Supplier Payment'
                when a.doc_type in ('PYMT') then 'Supplier Payment'
                when a.doc_type in ('SREC') then 'Supplier Receipt'  
                else ''
           end as doc_type_desc,
           a.doc_date,  a.voucher_id, a.credit_amt as amt,
           a.cheque_date, a.cheque_number, a.cheque_date, b.supplier
     from ap.pymt_control a
     Inner join ap.supplier b on a.supplier_account_id = b.supplier_id
     Where a.cheque_date > pto_date
         And a.company_id = pcompany_id
         And (a.branch_id in (select b.branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id) b) or pbranch_id=0)
         And a.is_pdc=true
         And (a.doc_type = pdoc_type or pdoc_type='All')
         And a.collected=false
     Union All               
     Select a.branch_id, a.doc_type,
           case when a.doc_type in ('ACR') then 'Advance Customer Receipt'
                when a.doc_type in ('MCR') then 'Multi Customer Receipt'
                when a.doc_type in ('RCPT') then 'Customer Receipt'
                when a.doc_type in ('CREF') then 'Customer Refund'
           end as doc_type_desc,
           a.doc_date,  a.voucher_id, a.debit_amt as amt,
           a.cheque_date, a.cheque_number, a.cheque_date, b.customer
     from ar.rcpt_control a
     Inner join ar.customer b on a.customer_account_id = b.customer_id
     Where a.cheque_date > pto_date
         And a.company_id = pcompany_id
         And (a.branch_id in (select b.branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id) b) or pbranch_id=0)
         And a.is_pdc=true
         And (a.doc_type = pdoc_type or pdoc_type='All')
         And a.collected=false
     Union All               
     Select a.branch_id, a.doc_type, 'GST Asset Purchase' as doc_type_desc,
           a.doc_date,  a.ap_id, a.net_credit_amt as amt,
           a.cheque_date, a.cheque_number, a.cheque_date, b.account_head
     from fa.ap_control  a
     Inner join ac.account_head b on a.account_id = b.account_id
     Where a.cheque_date > pto_date
         And a.company_id = pcompany_id
         And (a.branch_id in (select b.branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id) b) or pbranch_id=0)
         And (a.annex_info->>'is_pdc')::boolean = true
         And (a.doc_type = pdoc_type or pdoc_type='All') 
         And a.collected=false               
     Union All
     Select a.branch_id, a.doc_type, 'TDS Payment' as doc_type_desc,
           a.doc_date,  a.voucher_id, a.amt as amt,
           a.cheque_date, a.cheque_number, a.cheque_date, b.account_head
     from tds.tds_payment_control a
     Inner join ac.account_head b on a.account_id = b.account_id
     Where a.cheque_date > pto_date 
         And a.company_id = pcompany_id
         And (a.branch_id in (select b.branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id) b) or pbranch_id=0)
         And (a.annex_info->>'is_pdc')::boolean = true
         And (a.doc_type = pdoc_type or pdoc_type='All') 
         And a.collected=false
    ) x ;

    Return query
    Select a.branch_id, b.branch_name, a.doc_type, a.doc_type_desc, a.doc_date, a.voucher_id, 
           a.amt, a.cheque_date, a.cheque_number, a.pdc_date, a.vch_caption
    From pdc_temp a
    Inner join sys.branch b on a.branch_id=b.branch_id
    Where (a.doc_type = pdoc_type or pdoc_type='All')
    Order By a.branch_name, a.doc_type_desc, a.doc_date, a.voucher_id;
               
END;
$BODY$
 LANGUAGE 'plpgsql';

?==?
CREATE or Replace FUNCTION ac.fn_tb_op_cl(IN pcompany_id bigint, IN pbranch_ids bigint[], IN pyear varchar(4), IN pfrom_date date, IN pto_date date)
RETURNS TABLE
(	account_id bigint, 
	debit_opening_balance numeric(18,4), 
	credit_opening_balance numeric(18,4), 
	period_debits numeric(18,4), 
	period_credits numeric(18,4), 
	debit_closing_balance numeric(18,4), 
	credit_closing_balance numeric(18,4)) 
AS
$BODY$
BEGIN

    return query
	With period_txn
    As
    (   Select a.account_id, Sum(a.debit_balance) as op_debit, Sum(a.credit_balance) as op_credit,
			0.00 as period_debit, 0.00 as period_credit
		From ac.account_balance a
		Where a.finyear=pyear 
			And a.company_id=pcompany_id 
			And (a.branch_id = Any(pbranch_ids) or '{0}'::BigInt[] = pbranch_ids)
		Group By a.account_id
		Union All -- GL Summary Before From Date
		Select a.account_id, Sum(a.debit_amt), Sum(a.credit_amt), 0.00, 0.00
		From ac.general_ledger  a 
		Where a.finyear=pyear  
			And a.doc_date<pfrom_date 
			And a.company_id=pcompany_id 
			And (a.branch_id = Any(pbranch_ids) or '{0}'::BigInt[] = pbranch_ids)
		Group By a.account_id
		Union All -- GL Summary Between From And To
		Select a.account_id, 0.00, 0.00, Sum(a.debit_amt), Sum(a.credit_amt)
		From ac.general_ledger a
		Where a.finyear=pyear  
			And a.doc_date Between pfrom_date And pto_date 
			And a.company_id=pcompany_id
			And (a.branch_id = Any(pbranch_ids) or '{0}'::BigInt[] = pbranch_ids)
		Group By a.account_id
    )
    select g.account_id, 
		Case When Sum(g.op_debit-g.op_credit)>=0 Then Sum(g.op_debit-g.op_credit) Else 0.00 End, 
		Case When Sum(g.op_debit-g.op_credit)<0 Then Sum(g.op_credit-g.op_debit) Else 0.00 End, 
		sum(g.period_debit), sum(g.period_credit), 
		Case When Sum(g.op_debit-g.op_credit+g.period_debit-g.period_credit)>=0 
					Then Sum(g.op_debit-g.op_credit+g.period_debit-g.period_credit) Else 0.00 End, 
        Case When Sum(g.op_debit-g.op_credit+g.period_debit-g.period_credit)<0 
					Then Sum(g.op_debit-g.op_credit+g.period_debit-g.period_credit) * -1 Else 0.00 End
    From period_txn g
    Group By g.account_id;

END;
$BODY$
  LANGUAGE plpgsql;


?==?
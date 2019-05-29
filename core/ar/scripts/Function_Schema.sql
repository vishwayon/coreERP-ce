-- Function for invoice tran document print report
CREATE FUNCTION ar.fn_invoice_tran_report(In pvoucher_id varchar)  
   RETURNS TABLE(invoice_id varchar, invoice_tran_id varchar, sl_no smallint, account_id bigint, account_head varchar,
	credit_amt numeric, credit_amt_fc numeric, description varchar) AS
$BODY$
BEGIN	
        DROP TABLE IF EXISTS invoice_tran_report_temp;	
	create temp table invoice_tran_report_temp
	(
		invoice_id varchar(50),
		invoice_tran_id varchar(50),
		sl_no smallint,
		account_id bigint,
		account_head varchar(250),
		credit_amt numeric(18,4),
		credit_amt_fc numeric(18,4),
		description varchar(250)
	);

        insert into invoice_tran_report_temp(invoice_id, invoice_tran_id, sl_no, account_id, account_head, credit_amt, credit_amt_fc, description)
	select 	a.invoice_id, a.invoice_tran_id, a.sl_no, a.account_id, b.account_head, a.credit_amt, a.credit_amt_fc, a.description
	from ar.invoice_tran a
		inner join ac.account_head b on a.account_id = b.account_id
	where a.invoice_id = pvoucher_id;
	
	return query
	select 	a.invoice_id, a.invoice_tran_id, a.sl_no, a.account_id, a.account_head, a.credit_amt, a.credit_amt_fc, a.description
	from invoice_tran_report_temp a
	order by a.sl_no;
	       
END;
$BODY$
  LANGUAGE plpgsql;

?==?
create or replace function ar.fn_invoice_info_for_gl_post(pvoucher_id varchar(50))
RETURNS TABLE  
(	index int4 , 
	company_id bigint,
	branch_id bigint,
	dc char(1),
	account_id bigint,
	debit_amt_fc numeric(18,4),
	credit_amt_fc numeric(18,4),
	debit_amt numeric(18,4),
	credit_amt numeric(18,4)
)
AS
$BODY$ 
	Declare vDiscountFC numeric(18,4)=0; vDiscount numeric(18,4)= 0; vTotalDebitFC numeric(18,4) = 0; vTotalDebit numeric(18,4) =0;
	vCompany_ID bigint =-1; vBranch_ID bigint = -1; vAccount_ID bigint =-1; vSTBasicAccount_ID bigint=-1; vRoundOffAcc_ID BigInt := -1;
	vis_cash boolean; vis_card boolean; vis_customer boolean; vis_cheque boolean; vtax_amt_fc Numeric(18,2):=0;
Begin	
	-- This function is used by the Posting Trigger to get information on the Supplier Payment (PYMT)
	DROP TABLE IF EXISTS invoice_vch_detail;	
	create temp TABLE  invoice_vch_detail
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

	-- Fetch Control
	If Not Exists(Select * From ar.invoice_control Where invoice_id=pvoucher_id And (annex_info->'pos'->>'is_pos')::boolean) Then
		-- Normal ar Invoices
		Insert into invoice_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
		Select a.company_id, a.branch_id, 'D', a.customer_id, a.invoice_amt_fc, 0, a.invoice_amt, 0
		From ar.invoice_control a
		Where invoice_id=pvoucher_ID;
	Else 
		-- for POS Invoices Get Settlement data
		Select (a.annex_info->'pos'->'inv_settle'->>'is_cash')::boolean, 
			(a.annex_info->'pos'->'inv_settle'->>'is_card')::boolean, 
			(a.annex_info->'pos'->'inv_settle'->>'is_cheque')::boolean, 
			(a.annex_info->'pos'->'inv_settle'->>'is_customer')::boolean 
		    Into vis_cash, vis_card, vis_cheque, vis_customer
		From ar.invoice_control a
		Where a.invoice_id = pvoucher_id;

		If vis_cash Then
			Insert into invoice_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
			Select a.company_id, a.branch_id, 'D', (a.annex_info->'pos'->'inv_settle'->>'cash_account_id')::BigInt, 0, 0, (a.annex_info->'pos'->'inv_settle'->>'cash_amt')::Numeric, 0
			from ar.invoice_control a
			Where a.invoice_id = pvoucher_id;
		End If;

		If vis_card Then
			Insert into invoice_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
			Select a.company_id, a.branch_id, 'D', b.account_id, 0, 0, (a.annex_info->'pos'->'inv_settle'->>'card_amt')::Numeric, 0
			from ar.invoice_control a
			Inner Join pos.cc_mac b On (a.annex_info->'pos'->'inv_settle'->>'cc_mac_id')::BigInt = b.cc_mac_id
			Where a.invoice_id = pvoucher_id;
		End If;

		If vis_cheque Then
			Insert into invoice_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
			Select a.company_id, a.branch_id, 'D', (a.annex_info->'pos'->'inv_settle'->>'cheque_account_id')::BigInt, 0, 0, (a.annex_info->'pos'->'inv_settle'->>'cheque_amt')::Numeric, 0
			from ar.invoice_control a
			Where a.invoice_id = pvoucher_id;
		End If;

		If vis_customer Then
			Insert into invoice_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
			Select a.company_id, a.branch_id, 'D', (a.annex_info->'pos'->'inv_settle'->>'customer_id')::BigInt, 0, 0, (a.annex_info->'pos'->'inv_settle'->>'customer_amt')::Numeric, 0
			from ar.invoice_control a
			Where a.invoice_id = pvoucher_id;
		End If;

		-- *****	Step 2: Get round off with -ve case
		Select cast(value as bigint) into vRoundOffAcc_ID from sys.settings where key='st_round_off_account';
		Insert into invoice_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
		Select a.company_id, a.branch_id, case when (a.annex_info->'pos'->>'rof_amt')::Numeric < 0 Then 'C' Else 'D' End, vRoundOffAcc_ID, 0, 0,
			case when (a.annex_info->'pos'->>'rof_amt')::Numeric < 0 Then -(a.annex_info->'pos'->>'rof_amt')::Numeric Else 0 End, 
			case when (a.annex_info->'pos'->>'rof_amt')::Numeric > 0 Then (a.annex_info->'pos'->>'rof_amt')::Numeric Else 0 End
		from ar.invoice_control a
		where a.invoice_id=pvoucher_id And (a.annex_info->'pos'->>'rof_amt')::Numeric != 0;
	End If;
	-- Fetch Tran			
	Insert into invoice_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 'C', b.account_id, 0, b.credit_amt_fc, 0, b.credit_amt
	From ar.invoice_control a
	Inner Join ar.invoice_tran b on a.invoice_id=b.invoice_id
	Where a.invoice_id=pvoucher_ID;
	
	-- -- Fetch Service Tax Tran			
	Insert into invoice_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 'C', b.account_id, 0, coalesce(sum(b.tax_amt_fc), 0) as tax_amt_fc, 0, coalesce(sum(b.tax_amt), 0) as tax_amt
	From ar.invoice_control a
	Inner Join tx.tax_tran b on a.invoice_id =b.voucher_id
	Where a.invoice_id=pvoucher_ID
	group by a.company_id, a.branch_id, b.account_id;

	-- Fetch GST Tax Tran 		
	Insert into invoice_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
            debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 'C', b.account_id, 0, (coalesce(sum(Case When a.fc_type_id > 0 Then b.tax_amt/a.exch_rate Else 0 End), 0))::Numeric(18,2) as tax_amt_fc, 
            0, coalesce(sum(b.tax_amt), 0) as tax_amt
	From ar.invoice_control a
	Inner Join tx.fn_gtt_info(pvoucher_ID, 'ar.invoice_tran') b on a.invoice_id =b.voucher_id
	Where a.invoice_id=pvoucher_ID
	group by a.company_id, a.branch_id, b.account_id;

        -- Adjust tax_amt fc
        If Exists(Select * From ar.invoice_control Where fc_type_id > 0 And invoice_id = pvoucher_id) Then
            Select (coalesce(sum(b.tax_amt/a.exch_rate), 0))::Numeric(18,2) Into vtax_amt_fc
            From ar.invoice_control a
            Inner Join tx.fn_gtt_info(pvoucher_ID, 'ar.invoice_tran') b on a.invoice_id =b.voucher_id
            Where a.invoice_id=pvoucher_ID;

            Update invoice_vch_detail a
            Set debit_amt_fc = a.debit_amt_fc + vtax_amt_fc
            Where a.index = 1;
        End If;
	-- *****	Step 2: Get round off with -ve case
	Select cast(value as bigint) into vRoundOffAcc_ID from sys.settings where key='st_round_off_account';
	Insert into invoice_vch_detail(company_id, branch_id, dc, account_id, 
		debit_amt_fc, 
		credit_amt_fc, 
		debit_amt, 
		credit_amt)
	Select a.company_id, a.branch_id, case when COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) < 0 Then 'C' Else 'D' End, vRoundOffAcc_ID, 
		case when COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0)  < 0 Then 0 else -1 * COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0) End, 
		case when COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0) > 0 Then COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0) Else 0 End, 
		case when COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) < 0 Then -1 * COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) Else 0 End, 
		case when COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) > 0 Then COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) Else 0 End
	from ar.invoice_control a
	where a.invoice_id = pvoucher_id And (COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0) != 0 Or COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) != 0);


	return query 
	select a.index, a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
	from invoice_vch_detail a
        Order by a.index;
END;
$BODY$
LANGUAGE plpgsql;

?==?
create OR REPLACE function ar.fn_receivable_ledger_balance(pcompany_id bigint, pbranch_id bigint, paccount_id bigint, pto_date date, 
	pvoucher_id varchar(50), pdc varchar(1), prl_pl_id uuid default null)
RETURNS TABLE  
(	rl_pl_id uuid, 
	voucher_id varchar(50), 
	vch_tran_id varchar(50),
	doc_date date,
	account_id bigint,
	balance numeric(18,4),
	balance_fc numeric(18,4),
	fc_type_id bigint,
	fc_type varchar(20),
	branch_id bigint,
	narration varchar(500),
	due_date date,
        is_opbl boolean
)
AS
$BODY$ 
Begin	
	DROP TABLE IF EXISTS receivable_ledger_balance;	
	create temp TABLE  receivable_ledger_balance
	(	
		rl_pl_id uuid primary key, 
		voucher_id varchar(50), 
		vch_tran_id varchar(50),
		doc_date date,
		account_id bigint,
		balance numeric(18,4),
		balance_fc numeric(18,4),
		fc_type_id bigint,
		fc_type varchar(20),
		branch_id bigint,
		narration varchar(500),
		due_date date,
                is_opbl boolean
	);

	DROP TABLE IF EXISTS rl_balance;	
	create temp TABLE  rl_balance
	(	
		rl_pl_id uuid primary key,
		balance numeric(18,4),
		balance_fc numeric(18,4)
	);

	Insert into rl_balance(rl_pl_id, balance_fc, balance)
	Select a.rl_pl_id, sum(a.balance_fc) as balance_fc, sum(a.balance) as balance
	From (  select a.rl_pl_id, sum(a.debit_amt_fc)- sum(a.credit_amt_fc) as balance_fc, sum(a.debit_amt)- sum(a.credit_amt) as balance
		From ac.rl_pl a
		where (a.account_id=paccount_id or paccount_id=0)
			And (a.rl_pl_id = prl_pl_id or prl_pl_id is null)
		Group By a.rl_pl_id
		Union All -- In Alloc, Debits would be heavier and would automatically result in negatives
		select a.rl_pl_id, sum(a.net_debit_amt_fc)- sum(a.net_credit_amt_fc) as settled_fc, 
			sum(a.net_debit_amt)  - sum(a.net_credit_amt) as balance
		From ac.rl_pl_alloc a
		where (a.account_id=paccount_id or paccount_id=0) and a.voucher_id <> pvoucher_id
			And (a.rl_pl_id = prl_pl_id or prl_pl_id is null)
		Group By a.rl_pl_id
	     ) a
	Group By a.rl_pl_id;

	Insert into receivable_ledger_balance(rl_pl_id, voucher_id, vch_tran_id, doc_date, account_id, balance, balance_fc, 
		fc_type_id, fc_type, branch_id, narration, due_date)
	Select a.rl_pl_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id, b.balance, b.balance_fc, 
		a.fc_type_id, c.fc_type, a.branch_id, a.narration, a.due_date
	From ac.rl_pl a
	Inner Join rl_balance b on a.rl_pl_id=b.rl_pl_id
	Inner Join ac.fc_type c on a.fc_type_id=c.fc_type_id
	where a.doc_date <= pto_date 
		And (a.account_id=paccount_id or paccount_id=0)
		And (b.balance_fc <>0 or b.balance <> 0)
		And (a.branch_id=pbranch_id or pbranch_id=0)
		And (a.rl_pl_id = prl_pl_id or prl_pl_id is null);
			
	if pdc='D' then
		-- Remove all advances
		Delete from receivable_ledger_balance a Where a.balance < 0;
	End If; 
	If pdc = 'C' then
		-- Remove all setellement/receivables
		Delete from receivable_ledger_balance a
		Where a.balance > 0;

		-- Convert negative advances to positive
		Update receivable_ledger_balance a
		set balance_fc = a.balance_fc * -1,
		    balance = a.balance * -1;
	End If;

        Update receivable_ledger_balance a
        set is_opbl = b.is_opbl
        From ac.rl_pl b 
        Where a.rl_pl_id = b.rl_pl_id;
            
	return query 
	select a.rl_pl_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id, a.balance, a.balance_fc, 
		a.fc_type_id, a.fc_type, a.branch_id, a.narration, a.due_date, a.is_opbl
	from receivable_ledger_balance a;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ar.fn_rcpt_info_for_gl_post(IN pvoucher_id character varying)
  RETURNS TABLE(
	index integer, 
	company_id bigint,
	branch_id bigint,
	dc char(1),
	account_id bigint,
	debit_amt_fc numeric(18,4),
	credit_amt_fc numeric(18,4),
	debit_amt numeric(18,4),
	credit_amt numeric(18,4),
	remarks varchar(50)
) AS
$BODY$ 
	Declare 
		vDocType Varchar(4) := ''; vOtherExpAcc_ID bigint = -1; vTDSAcc_ID bigint = -1; vWriteOffAcc_ID bigint = -1; 
        vRoundOffAcc_ID bigint = -1; vRcptType smallint = 0;
        vCompany_ID bigint =-1; vBranch_ID bigint = -1; vdcn_type Int:= 0;
        -- GST TDS Accounts
        vsgst_tds_acc_id bigint =-1; vcgst_tds_acc_id bigint =-1; vigst_tds_acc_id bigint =-1;
Begin	
	-- This function is used by the Posting Trigger to get information on the Supplier Payment (PYMT)
	DROP TABLE IF EXISTS rcpt_vch_detail;	
	create temp TABLE  rcpt_vch_detail
	(	
		index serial, 
		company_id bigint,
		branch_id bigint,
		dc char(1),
		account_id bigint,
		debit_amt_fc numeric(18,4),
		credit_amt_fc numeric(18,4),
		debit_amt numeric(18,4),
		credit_amt numeric(18,4),
		remarks varchar(50)
	);

	Select a.company_id, a.branch_id, a.doc_type, a.rcpt_type Into vCompany_ID, vBranch_ID, vDocType, vRcptType
	From ar.rcpt_control a
	Where a.voucher_id=pvoucher_ID;

	If vDocType = 'RCPT' then 
            If vRcptType != 2 Then -- Cash Bank/Journal
                -- *****	Group A: Debits
                -- *****	Step 1: Fetch Net Settled 
                Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)		
                Select vCompany_ID, vBranch_ID, 'D', a.account_id, a.net_settled_fc, 0, a.net_settled, 0, 'Control Account'
                From ar.rcpt_control a
                Where a.voucher_id=pvoucher_ID
                        and a.account_id <> -1;
            Else -- AR to AP

                -- *****	Group A: Debits
                -- *****	Step 1: Fetch Net Settled 
                Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)		
                            Select vCompany_ID, b.branch_id, 'D', b.account_id, Sum(b.debit_amt_fc + b.write_off_amt_fc), 0, 
                        Sum(b.debit_amt + b.write_off_amt), 0, 'Control Account - Supplier'
                From ar.rcpt_control a 
                Inner Join ac.rl_pl_alloc b on a.voucher_id=b.voucher_id
                Where a.voucher_id=pvoucher_ID
                Group By a.company_id, b.branch_id, b.account_id
                Having Sum(b.debit_amt_fc + b.write_off_amt_fc) > 0 
                    Or Sum(b.debit_amt + b.write_off_amt) > 0;
            End If;
        
		-- *****	Step 1: Fetch Addl Settled 
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)		
		Select vCompany_ID, vBranch_ID, 'D', a.account_id, a.sel_amt_fc, 0, a.sel_amt, 0, 'Control Account'
		From ar.rcpt_sel_acc_tran a
		Where a.voucher_id=pvoucher_ID;

		-- ****		Step 2: Fetch Write Off Information
		Select cast(value as bigint) into vWriteOffAcc_ID from sys.settings where key='ar_rcpt_write_off_account';
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)		
		Select vCompany_ID, b.branch_id, 'D', vWriteOffAcc_ID, Sum(b.write_off_amt_fc), 0, Sum(b.write_off_amt), 0, 'Discount'
		From ar.rcpt_control a 
		Inner Join ac.rl_pl_alloc b on a.voucher_id=b.voucher_id
		Where a.voucher_id=pvoucher_ID
		Group By a.company_id, b.branch_id
		Having Sum(b.write_off_amt_fc) > 0 Or Sum(b.write_off_amt) > 0;

		-- *****	Step 3: Fetch Other Expenses Information
		Select cast(value as bigint) into vOtherExpAcc_ID from sys.settings where key='ar_rcpt_other_exp_account';
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)		
		Select vCompany_ID, b.branch_id, 'D', vOtherExpAcc_ID, Sum(b.other_exp_fc), 0, Sum(b.other_exp), 0, 'Other Expenses'
		From ar.rcpt_control a 
		Inner Join ac.rl_pl_alloc b on a.voucher_id=b.voucher_id
		Where a.voucher_id=pvoucher_ID
		Group By a.company_id, b.branch_id
		Having Sum(b.other_exp_fc) > 0 Or Sum(b.other_exp) > 0;

		-- ****		Step 4: Fetch TDS Information (Credit)
		Select cast(value as bigint) into vTDSAcc_ID from sys.settings where key='ar_rcpt_tds_account';
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)		
		Select vCompany_ID, b.branch_id, 'D', vTDSAcc_ID, Sum(b.tds_amt_fc), 0, Sum(b.tds_amt), 0, 'TDS '
		From ar.rcpt_control a 
		Inner Join ac.rl_pl_alloc b on a.voucher_id=b.voucher_id
		Where a.voucher_id=pvoucher_ID
		Group By a.company_id, b.branch_id
		Having Sum(b.tds_amt_fc) > 0 Or Sum(b.tds_amt) > 0;
        
		-- ****		Step 4: Fetch GST TDS Information (Debit)
                Select a.sgst_tds_account_id, a.cgst_tds_account_id, a.igst_tds_account_id into vsgst_tds_acc_id, vcgst_tds_acc_id, vigst_tds_acc_id
                From tx.gst_rate a
                Limit 1;

                With vat_type_info
                As(
                    select b.voucher_id, case when sum(c.sgst_amt + c.cgst_amt) > 0 then 301 Else 302 End vat_type_id
                    from ac.rl_pl_alloc a
                    inner join ac.rl_pl b on a.rl_pl_id = b.rl_pl_id
                    Left Join tx.gst_tax_tran c on b.voucher_id = c.voucher_id
                    where a.voucher_id = pvoucher_ID
                    Group By b.voucher_id 
                )
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)		
		Select vCompany_ID, b.branch_id, 'D', vsgst_tds_acc_id , Sum(b.gst_tds_amt_fc)/2, 0, Sum(b.gst_tds_amt)/2, 0, 'SGST TDS '
		From ar.rcpt_control a 
		Inner Join ac.rl_pl_alloc b on a.voucher_id = b.voucher_id
                inner join ac.rl_pl c on b.rl_pl_id = c.rl_pl_id
		Inner Join vat_type_info d on c.voucher_id = d.voucher_id And d.vat_type_id = 301
		Where a.voucher_id=pvoucher_ID
		Group By a.company_id, b.branch_id
		Having Sum(b.gst_tds_amt_fc) > 0 Or Sum(b.gst_tds_amt) > 0
                Union All        	
		Select vCompany_ID, b.branch_id, 'D', vcgst_tds_acc_id , Sum(b.gst_tds_amt_fc)/2, 0, Sum(b.gst_tds_amt)/2, 0, 'CGST TDS'
		From ar.rcpt_control a 
		Inner Join ac.rl_pl_alloc b on a.voucher_id = b.voucher_id
                inner join ac.rl_pl c on b.rl_pl_id = c.rl_pl_id
		Inner Join vat_type_info d on c.voucher_id = d.voucher_id And d.vat_type_id = 301
		Where a.voucher_id=pvoucher_ID
		Group By a.company_id, b.branch_id
		Having Sum(b.gst_tds_amt_fc) > 0 Or Sum(b.gst_tds_amt) > 0
                Union All        	
		Select vCompany_ID, b.branch_id, 'D', vigst_tds_acc_id , Sum(b.gst_tds_amt_fc), 0, Sum(b.gst_tds_amt), 0, 'IGST TDS'
		From ar.rcpt_control a 
		Inner Join ac.rl_pl_alloc b on a.voucher_id = b.voucher_id
                inner join ac.rl_pl c on b.rl_pl_id = c.rl_pl_id
		Inner Join vat_type_info d on c.voucher_id = d.voucher_id And d.vat_type_id != 301
		Where a.voucher_id=pvoucher_ID
		Group By a.company_id, b.branch_id
		Having Sum(b.gst_tds_amt_fc) > 0 Or Sum(b.gst_tds_amt) > 0;

		-- *****	Group B Credits
		-- *****	Step 1: Fetch Customer Settlement Information
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, 
				credit_amt_fc, debit_amt, 
				credit_amt, remarks)		
		Select vCompany_ID, b.branch_id, 'C', a.customer_account_id, 0, 
				Sum(b.credit_amt_fc + b.write_off_amt_fc + b.tds_amt_fc + b.gst_tds_amt_fc + b.other_exp_fc), 0, 
				Sum(b.credit_amt + b.write_off_amt + b.tds_amt + b.gst_tds_amt + b.other_exp), 'Customer Credit'
		From ar.rcpt_control a 
		Inner Join ac.rl_pl_alloc b on a.voucher_id=b.voucher_id
		Where a.voucher_id=pvoucher_ID
		Group By a.company_id, b.branch_id, a.customer_account_id
		Having Sum(b.credit_amt_fc + b.write_off_amt_fc + b.tds_amt_fc + b.gst_tds_amt_fc + b.other_exp_fc) > 0 
			Or Sum(b.credit_amt + b.write_off_amt + b.tds_amt + b.gst_tds_amt + b.other_exp) > 0;

		-- **** 	Step 2: Fetch Customer Advance Amount
-- 		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)		
-- 		Select vCompany_ID, vBranch_ID, 'C', a.customer_account_id, 0, a.adv_amt_fc, 0, a.adv_amt, 'Advance'
-- 		From ar.rcpt_control a 
-- 		Where a.voucher_id=pvoucher_ID
-- 			And (a.adv_amt_fc > 0 Or a.adv_amt > 0);
		
                -- **** 	Step 3: Fetch Customer Advance         
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)		
		Select vCompany_ID, b.branch_id, 'C', a.customer_account_id, 0, sum(b.adv_amt_fc), 0, sum(b.adv_amt), 'Advance'
		From ar.rcpt_control a 
		inner join ar.rcpt_adv_tran b on a.voucher_id = b.voucher_id
		Where a.voucher_id=pvoucher_ID
                group by a.customer_account_id, b.branch_id
                having (sum(b.adv_amt_fc) > 0 or sum(b.adv_amt) > 0);

		-- **** 	Step 3: Fetch Other Adjustments
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
			debit_amt, credit_amt, remarks)		
		Select vCompany_ID, vBranch_ID, 'C', b.account_id, 0, b.credit_amt_fc, 
			0, b.credit_amt, 'Other Adj'
		From ar.rcpt_control a 
		inner join ar.rcpt_tran b on a.voucher_id = b.voucher_id
		Where a.voucher_id=pvoucher_ID;

	End If;
	If vDocType = 'ACR' Then
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
		Select vCompany_ID, vBranch_ID, 'D', a.account_id, a.debit_amt_fc, 0, a.debit_amt, 0
		From ar.rcpt_control a 
		Where a.voucher_id=pvoucher_ID;

                -- Fetch tds amount if required
                Select cast(value as bigint) into vTDSAcc_ID from sys.settings where key='ar_rcpt_tds_account';
                Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
		Select vCompany_ID, vBranch_ID, 'D', vTDSAcc_ID, 0.00, 0, a.tds_amt, 0
		From ar.rcpt_control a 
		Where a.voucher_id=pvoucher_ID 
                    And a.tds_amt > 0;
		
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
		Select vCompany_ID, a.target_branch_ID, 'C', a.customer_account_id, 0, a.debit_amt_fc, 0, a.debit_amt + a.tds_amt
		From ar.rcpt_control a 
		Where a.voucher_id=pvoucher_ID;	
	End If;	
	If vDocType = 'CREF' Then
		-- *****	Group A: Debits
		-- *****	Step 1: Fetch Net Settled 
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
		Select vCompany_ID, vBranch_ID, 'C', a.account_id, 0, a.net_settled_fc, 0, a.net_settled
		From ar.rcpt_control a
		Where a.voucher_id=pvoucher_ID;

		-- *****	Group B Credits
		-- *****	Step 1: Fetch Customer Settlement Information
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
				debit_amt, credit_amt)		
		Select vCompany_ID, b.branch_id, 'D', a.customer_account_id, Sum(b.debit_amt_fc), 0, 
				Sum(b.debit_amt), 0
		From ar.rcpt_control a 
		Inner Join ac.rl_pl_alloc b on a.voucher_id=b.voucher_id
		Where a.voucher_id=pvoucher_ID
		Group By a.company_id, b.branch_id, a.customer_account_id
		Having Sum(b.debit_amt_fc) > 0 
			Or Sum(b.debit_amt) > 0;
	End If;
	If vDocType = 'CN2' Then
            Select coalesce((annex_info->>'dcn_type')::Int, 0) into vdcn_type 
            from ar.rcpt_control
            where voucher_id =pvoucher_id;
       
            If vdcn_type = 1 Then -- Rate Adjustment (Increase)
                -- *****	Group A: Debits
                -- *****	Step 1: Get Customer Debit
                Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)		
                Select vCompany_ID, vBranch_ID, 'D', a.customer_account_id, 0, 0, a.debit_amt, 0, 'Customer Debit'
                From ar.rcpt_control a 
                Where a.voucher_id=pvoucher_ID;

                Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
                    debit_amt, credit_amt, remarks)		
                Select vCompany_ID, vBranch_ID, 'C', a.account_id, 0, a.debit_amt_fc, 
                    0, a.debit_amt, 'Sales Account Credit'
                From ar.rcpt_tran a
                Where a.voucher_id=pvoucher_ID;

                -- Fetch GST Tax Tran 		
                Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                Select vCompany_ID, vBranch_ID, 'C', a.account_id, 0, 0, 0, coalesce(sum(a.tax_amt), 0), 'Tax Credit'
                From tx.fn_gtt_info(pvoucher_ID, 'ar.rcpt_tran') a
                Where a.voucher_id=pvoucher_ID
                group by  a.account_id
                having sum(a.tax_amt) > 0;					

                -- Round Off
                Select cast(value as bigint) into vRoundOffAcc_ID from sys.settings where key='st_round_off_account';		
                Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, credit_amt_fc, debit_amt_fc, credit_amt, debit_amt, remarks)
                Select vCompany_ID, vBranch_ID, case when COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) < 0 Then 'C' Else 'D' End, vRoundOffAcc_ID,  
                    case when COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0) > 0 Then COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0) Else 0 End, 
                    case when COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0)  < 0 Then 0 else -1 * COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0) End, 
                    case when COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) > 0 Then COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) Else 0 End, 
                    case when COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) < 0 Then -1 * COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) Else 0 End,
                    'Round Off Amt'
                From ar.rcpt_control a
                Where a.voucher_id=pvoucher_ID
                    And (a.annex_info->>'round_off_amt')::numeric != 0;
                    Else
                Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)		
                Select vCompany_ID, vBranch_ID, 'C', a.customer_account_id, 0, 0, 0, a.debit_amt, 'Customer Credit'
                From ar.rcpt_control a 
                Where a.voucher_id=pvoucher_ID;

                Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
                    debit_amt, credit_amt, remarks)		
                Select vCompany_ID, vBranch_ID, 'D', a.account_id, a.debit_amt_fc, 0, 
                    a.debit_amt, 0, 'Sales Account Debit'
                From ar.rcpt_tran a
                Where a.voucher_id=pvoucher_ID;

                -- Fetch GST Tax Tran 		
                Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                Select vCompany_ID, vBranch_ID, 'D', a.account_id, 0, 0, coalesce(sum(a.tax_amt), 0), 0, 'Tax Debit'
                From tx.fn_gtt_info(pvoucher_ID, 'ar.rcpt_tran') a
                Where a.voucher_id=pvoucher_ID
                group by  a.account_id
                having sum(a.tax_amt) > 0;					

                -- Round Off
                Select cast(value as bigint) into vRoundOffAcc_ID from sys.settings where key='st_round_off_account';		
                Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                Select vCompany_ID, vBranch_ID, case when COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) < 0 Then 'D' Else 'C' End, vRoundOffAcc_ID,  
                    case when COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0) > 0 Then COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0) Else 0 End, 
                    case when COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0)  < 0 Then 0 else -1 * COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0) End, 
                    case when COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) > 0 Then COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) Else 0 End, 
                    case when COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) < 0 Then -1 * COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) Else 0 End,
                    'Round Off Amt'
                From ar.rcpt_control a
                Where a.voucher_id=pvoucher_ID
                    And (a.annex_info->>'round_off_amt')::numeric != 0;
            End If;
	End If;	
	If vDocType = 'MCR' then 
		-- *****	Group A: Debits
		-- *****	Step 1: Fetch Net Settled 		
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)		
		Select vCompany_ID, vBranch_ID, 'D', a.account_id, a.net_settled_fc, 0, a.net_settled, 0, 'Control Account'
		From ar.rcpt_control a
		Where a.voucher_id=pvoucher_ID;

		-- ****		Step 2: Fetch Write Off Information
		Select cast(value as bigint) into vWriteOffAcc_ID from sys.settings where key='ar_rcpt_write_off_account';
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)		
		Select vCompany_ID, b.branch_id, 'D', vWriteOffAcc_ID, Sum(b.write_off_amt_fc), 0, Sum(b.write_off_amt), 0, 'Discount'
		From ar.rcpt_control a 
		Inner Join ac.rl_pl_alloc b on a.voucher_id=b.voucher_id
		Where a.voucher_id=pvoucher_ID
		Group By a.company_id, b.branch_id
		Having Sum(b.write_off_amt_fc) > 0 Or Sum(b.write_off_amt) > 0;

		-- *****	Step 3: Fetch Other Expenses Information
		Select cast(value as bigint) into vOtherExpAcc_ID from sys.settings where key='ar_rcpt_other_exp_account';
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)		
		Select vCompany_ID, b.branch_id, 'D', vOtherExpAcc_ID, Sum(b.other_exp_fc), 0, Sum(b.other_exp), 0, 'Other Expenses'
		From ar.rcpt_control a 
		Inner Join ac.rl_pl_alloc b on a.voucher_id=b.voucher_id
		Where a.voucher_id=pvoucher_ID
		Group By a.company_id, b.branch_id
		Having Sum(b.other_exp_fc) > 0 Or Sum(b.other_exp) > 0;

		-- ****		Step 4: Fetch TDS Information (Credit)
		Select cast(value as bigint) into vTDSAcc_ID from sys.settings where key='ar_rcpt_tds_account';
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)		
		Select vCompany_ID, b.branch_id, 'D', vTDSAcc_ID, Sum(b.tds_amt_fc), 0, Sum(b.tds_amt), 0, 'TDS '
		From ar.rcpt_control a 
		Inner Join ac.rl_pl_alloc b on a.voucher_id=b.voucher_id
		Where a.voucher_id=pvoucher_ID
		Group By a.company_id, b.branch_id
		Having Sum(b.tds_amt_fc) > 0 Or Sum(b.tds_amt) > 0;
	
		-- ****		Step 4: Fetch GST TDS Information (Debit)
                Select a.sgst_tds_account_id, a.cgst_tds_account_id, a.igst_tds_account_id into vsgst_tds_acc_id, vcgst_tds_acc_id, vigst_tds_acc_id
                From tx.gst_rate a
                Limit 1;

                        With vat_type_info
                As(
                    select b.voucher_id, case when sum(c.sgst_amt + c.cgst_amt) > 0 then 301 Else 302 End vat_type_id
                    from ac.rl_pl_alloc a
                    inner join ac.rl_pl b on a.rl_pl_id = b.rl_pl_id
                    Inner Join tx.gst_tax_tran c on b.voucher_id = c.voucher_id
                    where a.voucher_id = pvoucher_ID
                    Group By b.voucher_id 
                )
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)		
		Select vCompany_ID, b.branch_id, 'D', vsgst_tds_acc_id , Sum(b.gst_tds_amt_fc)/2, 0, Sum(b.gst_tds_amt)/2, 0, 'SGST TDS '
		From ar.rcpt_control a 
		Inner Join ac.rl_pl_alloc b on a.voucher_id = b.voucher_id
                inner join ac.rl_pl c on b.rl_pl_id = c.rl_pl_id
		Inner Join vat_type_info d on c.voucher_id = d.voucher_id And d.vat_type_id = 301
		Where a.voucher_id=pvoucher_ID
		Group By a.company_id, b.branch_id
		Having Sum(b.gst_tds_amt_fc) > 0 Or Sum(b.gst_tds_amt) > 0
                Union All        	
		Select vCompany_ID, b.branch_id, 'D', vcgst_tds_acc_id , Sum(b.gst_tds_amt_fc)/2, 0, Sum(b.gst_tds_amt)/2, 0, 'CGST TDS'
		From ar.rcpt_control a 
            	Inner Join ac.rl_pl_alloc b on a.voucher_id = b.voucher_id
                inner join ac.rl_pl c on b.rl_pl_id = c.rl_pl_id
		Inner Join vat_type_info d on c.voucher_id = d.voucher_id And d.vat_type_id = 301
		Where a.voucher_id=pvoucher_ID
		Group By a.company_id, b.branch_id
		Having Sum(b.gst_tds_amt_fc) > 0 Or Sum(b.gst_tds_amt) > 0
                Union All        	
		Select vCompany_ID, b.branch_id, 'D', vigst_tds_acc_id , Sum(b.gst_tds_amt_fc), 0, Sum(b.gst_tds_amt), 0, 'IGST TDS'
		From ar.rcpt_control a 
		Inner Join ac.rl_pl_alloc b on a.voucher_id = b.voucher_id
                inner join ac.rl_pl c on b.rl_pl_id = c.rl_pl_id
		Inner Join vat_type_info d on c.voucher_id = d.voucher_id And d.vat_type_id != 301
		Where a.voucher_id=pvoucher_ID
		Group By a.company_id, b.branch_id
		Having Sum(b.gst_tds_amt_fc) > 0 Or Sum(b.gst_tds_amt) > 0;


		-- *****	Group B Credits
		-- *****	Step 1: Fetch Customer Settlement Information
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, 
				credit_amt_fc, debit_amt, 
				credit_amt, remarks)		
		Select vCompany_ID, b.branch_id, 'C', b.account_id, 0, 
				Sum(b.credit_amt_fc + b.write_off_amt_fc + b.tds_amt_fc + b.other_exp_fc), 0, 
				Sum(b.credit_amt + b.write_off_amt + b.tds_amt + b.other_exp), 'Customer Credit'
		From ar.rcpt_control a 
		Inner Join ac.rl_pl_alloc b on a.voucher_id=b.voucher_id
		Where a.voucher_id=pvoucher_ID
		Group By a.company_id, b.branch_id, b.account_id
		Having Sum(b.credit_amt_fc + b.write_off_amt_fc + b.tds_amt_fc + b.other_exp_fc) > 0 
			Or Sum(b.credit_amt + b.write_off_amt + b.tds_amt + b.other_exp) > 0;
		
                -- **** 	Step 3: Fetch Customer Advance         
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)		
		Select vCompany_ID, b.branch_id, 'C', b.account_id, 0, sum(b.adv_amt_fc), 0, sum(b.adv_amt), 'Advance'
		From ar.rcpt_control a 
		inner join ar.rcpt_adv_tran b on a.voucher_id = b.voucher_id
		Where a.voucher_id=pvoucher_ID
                group by b.branch_id, b.account_id
		Having sum(b.adv_amt) > 0 or sum(b.adv_amt_fc) > 0;

		-- **** 	Step 3: Fetch Other Adjustments
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
			debit_amt, credit_amt, remarks)		
		Select vCompany_ID, vBranch_ID, 'C', b.account_id, 0, b.credit_amt_fc, 
			0, b.credit_amt, 'Other Adj'
		From ar.rcpt_control a 
		inner join ar.rcpt_tran b on a.voucher_id = b.voucher_id
		Where a.voucher_id=pvoucher_ID;

	End If;
	If vDocType = 'CBT' Then
		-- *****	Group A: Debits
		-- *****	Step 1: Fetch Customer Settlement Information
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
				debit_amt, credit_amt)		
		Select vCompany_ID, b.branch_id, 'D', a.customer_account_id, Sum(b.debit_amt_fc), 0, 
				Sum(b.debit_amt), 0
		From ar.rcpt_control a 
		Inner Join ac.rl_pl_alloc b on a.voucher_id=b.voucher_id
		Where a.voucher_id=pvoucher_ID
		Group By a.company_id, b.branch_id, a.customer_account_id
		Having Sum(b.debit_amt_fc) > 0 
			Or Sum(b.debit_amt) > 0;
            
		Insert into rcpt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
				debit_amt, credit_amt)
                Select vCompany_ID, c.target_branch_id, 'C', a.customer_account_id, 0 as debit_amt_fc, Sum(b.debit_amt_fc) as credit_amt_fc, 
                        0 as debit_amt, Sum(b.debit_amt) as credit_amt
                From ar.rcpt_control a 
                Inner Join ac.rl_pl_alloc b on a.voucher_id=b.voucher_id
                Inner Join ar.bal_transfer_tran c on b.rl_pl_alloc_id=c.rl_pl_alloc_id
                Where a.voucher_id=pvoucher_ID
                Group By a.company_id, a.customer_account_id, c.target_branch_id
                Having Sum(b.debit_amt_fc) > 0 
                    Or Sum(b.debit_amt) > 0;
	End If;
	return query 
	select a.index, a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt, a.remarks
	from rcpt_vch_detail a;
END;
$BODY$
  LANGUAGE plpgsql;  

?==?
CREATE OR REPLACE FUNCTION ar.fn_stmt_of_ac_br_report(
    IN pcompany_id bigint,
    IN pbranch_id bigint,
    IN paccount_id bigint,
    IN pto_date date,
    IN pen_bill_type integer)
  RETURNS TABLE(rl_pl_id uuid, branch_id bigint, voucher_id character varying, vch_tran_id character varying, doc_date date, account_id bigint, en_bill_type smallint, balance_fc numeric, fc_type_id bigint, balance numeric, narration character varying, fc_type character varying, currency character varying) AS
$BODY$
Begin 
	DROP TABLE IF EXISTS receivable_ledger_balance_temp;
	CREATE temp TABLE  receivable_ledger_balance_temp
	(
		rl_pl_id uuid,  
		branch_id bigint,
		voucher_id varchar(50),  
		vch_tran_id varchar(50),  
		doc_date date,  
		account_id bigint,  
		en_bill_type smallint,
		balance_fc numeric(18,4),  
		fc_type_id bigint,  
		balance numeric(18,4),  
		narration varchar(500),
		fc_type varchar(20),
		currency varchar(20),
		CONSTRAINT pk_receivable_ledger_balance_temp PRIMARY KEY (rl_pl_id)
	 );

	-- Fetch Allocation Data

	DROP TABLE IF EXISTS rec_ledger_alloc_temp;
	CREATE temp TABLE  rec_ledger_alloc_temp
	(
		rl_pl_id uuid,
		balance_fc numeric(18,4),
		balance Numeric(18,4),
                CONSTRAINT pk_rec_ledger_alloc_temp PRIMARY KEY (rl_pl_id)
	 );

	Insert Into rec_ledger_alloc_temp(rl_pl_id, balance_fc, balance)
	Select a.rl_pl_id, sum(a.balance_fc) as balance_fc, sum(a.balance) as balance  
	From (	Select	b.rl_pl_id, sum(b.debit_amt_fc - b.credit_amt_fc) as balance_fc,  
			sum(b.debit_amt - b.credit_amt) as balance  
		From ac.rl_pl b 
		Where b.doc_date <= pto_date
			And (b.account_id = paccount_id or  paccount_id = 0) 
			And (b.branch_id In (Select a.branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id) a) or pbranch_id = 0)	
		Group By b.rl_pl_id  
		Union All -- In Alloc, Debits would be heavier and would automatically result in negatives  
		Select	c.rl_pl_id, sum(c.net_debit_amt_fc - c.net_credit_amt_fc) as settled_fc,   
			sum(c.net_debit_amt - c.net_credit_amt) as settled  
		From ac.rl_pl_alloc c  
		Where c.doc_date <= pto_date
			And (c.account_id = paccount_id or paccount_id = 0) 
			And (c.branch_id In (Select a.branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id) a) or pbranch_id = 0)
			And c.status = 5							
		Group By c.rl_pl_id
		) a  
	Group By a.rl_pl_id;

	Insert Into receivable_ledger_balance_temp(rl_pl_id, branch_id, voucher_id, vch_tran_id, doc_date, account_id,
                    en_bill_type, balance_fc, fc_type_id, balance, narration, fc_type, currency) 
        Select	a.rl_pl_id, a.branch_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id, a.en_bill_type, 
			b.balance_fc, a.fc_type_id, b.balance, a.narration, e.fc_type, e.currency
        From ac.rl_pl a  
        Inner Join rec_ledger_alloc_temp b On a.rl_pl_id = b.rl_pl_id  
        Inner Join ac.fc_type e on a.fc_type_id = e.fc_type_id 
        Where a.doc_date <= pto_date
                And (a.account_id = paccount_id or paccount_id = 0) 
                And (b.balance_fc <> 0 or b.balance <> 0)  
                And (a.branch_id In (Select a.branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id) a) or pbranch_id = 0)
                And (a.company_id = pcompany_id);

	--	Remove the records not required for display
	if pen_bill_type = 1 then
		-- Remove all Advances/Adjustments 
		Delete From receivable_ledger_balance_temp a 
		Where a.en_bill_type = 1;
	Elsif pen_bill_type = 2 then
		--Remove all Settlements/Receivables  
		Delete From receivable_ledger_balance_temp a 
		Where a.en_bill_type != 1;
	End if;

	return query 
	select a.rl_pl_id, a.branch_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id,
		a.en_bill_type, a.balance_fc, a.fc_type_id, a.balance, a.narration, a.fc_type, a.currency
	from receivable_ledger_balance_temp a;	

END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ar.fn_stmt_of_ac_br_report_future(IN pcompany_id bigint, IN pbranch_id bigint, IN paccount_id bigint, IN pto_date date, IN pen_bill_type integer)
  RETURNS TABLE(rl_pl_id uuid, voucher_id varchar, vch_tran_id varchar, doc_date date, account_id bigint, account_head varchar, en_bill_type smallint, 
		balance_fc numeric, fc_type_id bigint, balance numeric, bill_no varchar, bill_date date, net_debit_amount numeric, debit_exch_diff numeric,
		net_credit_amount numeric, credit_exch_diff numeric, debit_amount numeric, credit_amount numeric, net_debit_amount_fc numeric,
		net_credit_amount_fc numeric, fc_type varchar, currency varchar) AS
$BODY$
Begin 
	DROP TABLE IF EXISTS stmt_of_ac_br_report_future_temp;
	CREATE temp TABLE  stmt_of_ac_br_report_future_temp
	(
		rl_pl_id uuid,  
		voucher_id varchar(50),  
		vch_tran_id varchar(50),
		doc_date date,  
		account_id bigint,  
		account_head varchar(250),
		en_bill_type smallint,
		balance_fc numeric(18,4), 
		fc_type_id bigint,
		balance numeric(18,4),  
		bill_no varchar(600),  
		bill_date date, 
		net_debit_amount numeric(18,4),
		debit_exch_diff numeric(18,4),
		net_credit_amount numeric(18,4),
		credit_exch_diff numeric(18,4),
		debit_amount numeric(18,4),
		credit_amount numeric(18,4),
		net_debit_amount_fc numeric(18,4),
		net_credit_amount_fc numeric(18,4),		
		fc_type varchar(20),
		currency varchar(20)
	)
	on commit drop;

	INSERT INTO stmt_of_ac_br_report_future_temp(rl_pl_id, voucher_id, vch_tran_id, doc_date, account_id, account_head, en_bill_type,
		balance_fc, fc_type_id, balance, bill_no, bill_date, net_debit_amount, debit_exch_diff, net_credit_amount, credit_exch_diff,
		debit_amount, credit_amount, net_debit_amount_fc, net_credit_amount_fc, fc_type, currency)
	SELECT	a.rl_pl_id , a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id, c.account_head, a.en_bill_type, a.balance_fc, 
			a.fc_type_id, a.balance, b.voucher_id as voucher_affected, b.doc_date as date_affected,
			b.net_debit_amt as net_debit_amount, b.debit_exch_diff, b.net_credit_amt as net_credit_amount, 
			b.credit_exch_diff, 0 as debit_amount, 0 as credit_amount, b.net_debit_amt_fc, b.net_credit_amt_fc, a.fc_type, a.currency
	FROM ar.fn_stmt_of_ac_br_report(pcompany_id, pbranch_id, paccount_id, pto_date, pen_bill_type) a
	left join ac.rl_pl_alloc b on a.rl_pl_id = b.rl_pl_id
	Inner Join ac.account_head c on a.account_id = c.account_id
	Where a.doc_date <= pto_date AND (b.doc_date > pto_date OR Left(b.voucher_id, 4)='PDR/')
		And b.status = 5 
		AND (a.account_id = paccount_id Or paccount_id = 0);

	--	Remove the records not required for display
	if pen_bill_type = 1 then -- Exclude Advances
            Begin   
                -- Remove all Advances/Adjustments 
                Delete From stmt_of_ac_br_report_future_temp a
                Where a.en_bill_type = 1;
            End; 
	Elsif pen_bill_type = 2 then -- Advances Only
            Begin  
                -- Remove all Settlements/Payables  
                Delete From stmt_of_ac_br_report_future_temp b  
                Where b.en_bill_type != 1;
            End;	
	end if;


	return query 
		select a.rl_pl_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id, a.account_head, a.en_bill_type,
			a.balance_fc, a.fc_type_id, a.balance, a.bill_no, a.bill_date, a.net_debit_amount, a.debit_exch_diff, a.net_credit_amount, 
			a.credit_exch_diff, a.debit_amount, a.credit_amount, a.net_debit_amount_fc, a.net_credit_amount_fc, a.fc_type, a.currency 
		from stmt_of_ac_br_report_future_temp a;	

END;
$BODY$
  LANGUAGE plpgsql;

?==?
Drop FUNCTION If exists ar.fn_stmt_of_ac_br_report_detailed(IN pcompany_id bigint, IN pbranch_id bigint, IN paccount_id bigint, IN pto_date date);

?==?
CREATE OR REPLACE FUNCTION ar.fn_stmt_of_ac_br_report_detailed(IN pcompany_id bigint, IN pbranch_id bigint, IN paccount_id bigint, IN pto_date date, IN pen_bill_type integer)
RETURNS TABLE
(
    category character, 
    rl_pl_id uuid, 
    doc_date date, 
    voucher_id character varying, 
    settle_id character varying,
    bill_date date, 
    account_id bigint, 
    debit_amt numeric, 
    credit_amt numeric, 
    narration character varying,
    branch_id bigint
) AS
$BODY$
Begin 
    DROP TABLE IF EXISTS br_report_detailed_temp;
    CREATE temp TABLE  br_report_detailed_temp
    (
        category char(1),
        rl_pl_id uuid,
        doc_date date,
        voucher_id varchar(50),		
        settle_id character varying,
        bill_date date,
        account_id bigint,		
        debit_amt numeric(18,4),
        credit_amt numeric(18,4),
        narration varchar(500),
        branch_id bigint,
		en_bill_type smallint
     );

	
    Insert Into br_report_detailed_temp(category, rl_pl_id, doc_date, voucher_id, settle_id, bill_date, account_id, debit_amt,
				credit_amt, narration, branch_id, en_bill_type)
    SELECT 'A' as category, a.rl_pl_id, a.doc_date, a.voucher_id, '', Null, a.account_id, a.debit_amt, 
            a.credit_amt, a.narration, a.branch_id, a.en_bill_type
    FROM  ac.rl_pl a	
    Where ( a.account_id = paccount_id or paccount_id = 0)
            And (a.branch_id In (Select x.branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id) x) or pbranch_id = 0) 
            And (a.company_id = pcompany_id)
            And a.doc_date <= pto_date
    Union All 
    SELECT 'B' as category, a.rl_pl_id, b.doc_date, b.voucher_id, a.voucher_id, a.doc_date, a.account_id,  a.net_debit_amt,
            a.net_credit_amt, b.narration, b.branch_id, b.en_bill_type
    FROM  ac.rl_pl_alloc a		
    Inner Join ac.rl_pl b On a.rl_pl_id = b.rl_pl_id
    Where (a.account_id = paccount_id or paccount_id=0)
            And (b.branch_id In (Select x.branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id) x) or pbranch_id = 0)
            AND b.company_id = pcompany_id
            And a.doc_date <= pto_date AND a.status=5;
    --	Remove the records not required for display
    if pen_bill_type = 1 then
            -- Remove all Advances/Adjustments 
            Delete From br_report_detailed_temp a 
            Where a.en_bill_type = 1;
    Elsif pen_bill_type = 2 then
            --Remove all Settlements/Receivables  
            Delete From br_report_detailed_temp a 
            Where a.en_bill_type != 1;
    End if;
    return query 
    select a.category, a.rl_pl_id, a.doc_date, a.voucher_id, a.settle_id, 
           a.bill_date, a.account_id, a.debit_amt, a.credit_amt, a.narration, a.branch_id
    from br_report_detailed_temp a;	

END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ar.fn_stmt_of_ac_br_ageing_report(IN pcompany_id bigint, IN pbranch_id bigint, IN paccount_id bigint, IN pto_date date, IN pen_bill_type integer, IN ptype integer)
RETURNS TABLE
(
	dummy varchar(10),  
	rl_pl_id uuid, 
	branch_id bigint, 
	voucher_id varchar(50),  
	vch_tran_id varchar(50),  
	doc_date date,  
	account_id bigint,  
	en_bill_type smallint,   
	balance_fc numeric(18,4),  
	fc_type_id bigint,  
	balance numeric(18,4),  
	customer varchar(250),  
	address varchar(1000),
	fax varchar(50),
	phone varchar(50), 
	narration varchar(500), 
	fc_type varchar(20),
	currency varchar(20),
	pay_term_id bigint,
	pay_term_desc varchar(150),
	days integer,  
	period_id integer,  
	period varchar(15),
	control_account_id bigint,
	account_head varchar(250)
) AS
$BODY$
Begin 
	DROP TABLE IF EXISTS br_ageing_report_temp;
	CREATE temp TABLE  br_ageing_report_temp
	(
		dummy varchar(10),  
		rl_pl_id uuid, 
		branch_id bigint, 
		voucher_id varchar(50),  
		vch_tran_id varchar(50),  
		doc_date date,  
		account_id bigint,  
		en_bill_type smallint,   
		balance_fc numeric(18,4),  
		fc_type_id bigint,  
		balance numeric(18,4),  
		customer varchar(250),  
		address varchar(1000),
		fax varchar(50),
		phone varchar(50), 
		narration varchar(500), 
		fc_type varchar(20),
		currency varchar(20),
		pay_term_id bigint,
		pay_term_desc varchar(150),
		days integer,  
		period_id integer,  
		period varchar(15),
		control_account_id bigint,
		account_head varchar(250),
		CONSTRAINT pk_br_ageing_report_temp PRIMARY KEY (rl_pl_id)
	 );
	
	Insert Into br_ageing_report_temp(dummy, rl_pl_id, branch_id, voucher_id, vch_tran_id, doc_date, account_id,
			en_bill_type, balance_fc, fc_type_id, balance, customer, address, fax, phone, narration, fc_type,
			currency, pay_term_id, pay_term_desc, days, period_id,  period, control_account_id, account_head)
	Select 'Dummy' as dummy, a.rl_pl_id, a.branch_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id, a.en_bill_type, 
		a.balance_fc, a.fc_type_id, a.balance, b.customer, d.address, d.fax, d.phone, a.narration, a.fc_type, a.currency,
		d.pay_term_id, d.pay_term As pay_term_desc, 
		DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) as days,  
		Case  When DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) <= 30 then 0  
			When DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) > 30 and DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) <= 60 Then 1  
			When DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) > 60 and DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) <= 90 Then 2  
			When DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) > 90 and DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) <= 120 Then 3  
			When DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) > 120 and DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) <= 180 Then 4  
			When DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) > 180 then 5  
		End as period_id,  
		Case  When DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) <= 30 then '<= 30 Days'  
			When DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) > 30 and DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) <= 60 Then '31 - 60 Days'  
			When DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) > 60 and DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) <= 90 Then '61 - 90 Days'  
			When DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) > 90 and DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) <= 120 Then '91 - 120 Days'  
			When DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) > 120 and DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) <= 180 Then '121 - 180 Days'  
			When DATE_PART('day', pto_date::timestamp - a.doc_date::timestamp) > 180 then '> 180 Days'  
		End as period,
		Case When b.control_account_id is null Then 0 else b.control_account_id End as control_account_id,
		Case When c.account_head is null Then '' else c.account_head End as account_head
	From ar.fn_stmt_of_ac_br_report(pcompany_id, pbranch_id,  paccount_id, pto_date, pen_bill_type) a
	Inner join ar.customer b on a.account_id = b.customer_id
	left join ac.account_head c on b.control_account_id = c.account_id
	left join ar.fn_stmt_of_ac_br_report_customer_address(pcompany_id, paccount_id) d on a.account_id = d.customer_id
	where Left(a.voucher_id,4) <> 'PDI/';

	if ptype = 30 then   
	BEGIN  
		Delete From br_ageing_report_temp  
		Where period_id <> 0;  
	END;
	elsif ptype = 60 then  
	BEGIN  
		Delete From br_ageing_report_temp  
		Where period_id <> 1;  
	END;
	elsif ptype = 90 then
	BEGIN  
		Delete From br_ageing_report_temp  
		Where period_id <> 2;  
	END;
	elsif ptype = 120 then 
	BEGIN  
		Delete From br_ageing_report_temp  
		Where period_id <> 3;  
	END;  
	elsif ptype = 180 then
	BEGIN  
		Delete From br_ageing_report_temp  
		Where period_id <> 4; 
	END;
	end if;
	
	return query 
	select a.dummy, a.rl_pl_id, a.branch_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id,
		a.en_bill_type, a.balance_fc, a.fc_type_id, a.balance, a.customer, a.address, a.fax, a.phone, a.narration, a.fc_type,
		a.currency, a.pay_term_id, a.pay_term_desc, a.days, a.period_id,  a.period, a.control_account_id, a.account_head
	from br_ageing_report_temp a;	

END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE FUNCTION ar.fn_rcpt_report(In pvoucher_id varchar(50))  
RETURNS TABLE
(
	voucher_id character varying,
	branch_id bigint,
	doc_date date,
        fc_type_id bigint,
	fc_type character varying,
	exch_rate numeric(18,6),
	status smallint,
	rcpt_type character varying,
	received_from character varying,
	account_id bigint,
	account_head character varying,
	customer_account_id bigint,
	customer_account_head character varying,
	narration character varying,
	amt_in_words character varying,
	amt_in_words_fc character varying,
	remarks character varying,
        debit_amt numeric(18,4),
        debit_amt_fc numeric(18,4),
        cheque_number character varying,
        cheque_date date,
	collected boolean,
	collection_date date,
	cheque_bank character varying,
	cheque_branch character varying,
	entered_by character varying, 
	posted_by character varying,
        adv_amt numeric(18,4),
        adv_amt_fc numeric(18,4),
        other_adj numeric(18,4),
        other_adj_fc numeric(18,4),
        net_settled numeric(18,4),
        net_settled_fc numeric(18,4)
) 
AS
$BODY$
BEGIN	
	DROP TABLE IF EXISTS rcpt_report_temp;	
	create temp table rcpt_report_temp
	(
		voucher_id character varying,
		branch_id bigint,
		doc_date date,
                fc_type_id bigint,
		fc_type character varying,
		exch_rate numeric(18,6),
		status smallint,
		rcpt_type character varying,
		received_from character varying,
		account_id bigint,
		account_head character varying,
		customer_account_id bigint,
		customer_account_head character varying,
		narration character varying,
		amt_in_words character varying,
		amt_in_words_fc character varying,
		remarks character varying,
		debit_amt numeric(18,4),
		debit_amt_fc numeric(18,4),
		cheque_number character varying,
		cheque_date date,
		collected boolean,
		collection_date date,
		cheque_bank character varying,
		cheque_branch character varying,
		entered_by character varying, 
		posted_by character varying,
		adv_amt numeric(18,4),
		adv_amt_fc numeric(18,4),
		other_adj numeric(18,4),
		other_adj_fc numeric(18,4),
		net_settled numeric(18,4),
		net_settled_fc numeric(18,4)
	);

        insert into rcpt_report_temp(voucher_id, branch_id, doc_date, fc_type_id, fc_type, exch_rate, status, rcpt_type, received_from,
		account_id, account_head, customer_account_id, customer_account_head, narration, amt_in_words, amt_in_words_fc, 
		remarks, debit_amt, debit_amt_fc, cheque_number, cheque_date, collected, collection_date, cheque_bank, cheque_branch, 
		entered_by, posted_by, adv_amt, adv_amt_fc, other_adj, other_adj_fc, net_settled, net_settled_fc)
	select 	a.voucher_id, a.branch_id, a.doc_date, a.fc_type_id, e.fc_type, a.exch_rate, a.status, 
	        case when a.rcpt_type = 0 then 'Cash Bank' Else 'Journal' End as rcpt_type, a.received_from,
		a.account_id, b.account_head, a.customer_account_id, c.account_head , a.narration, a.amt_in_words, a.amt_in_words_fc,
		a.remarks, a.debit_amt, a.debit_amt_fc, a.cheque_number, a.cheque_date, a.collected, a.collection_date, a.cheque_bank, a.cheque_branch, 
		d.entered_by, d.posted_by, a.adv_amt, a.adv_amt_fc, (a.annex_info->>'other_adj')::numeric, (a.annex_info->>'other_adj_fc')::numeric,
		a.net_settled, a.net_settled_fc
	from ar.rcpt_control a
		inner join ac.account_head b on a.account_id = b.account_id
		inner join ac.account_head c on a.customer_account_id = c.account_id
		inner join sys.doc_es d on a.voucher_id = d.voucher_id
                inner join ac.fc_type e on a.fc_type_id = e.fc_type_id
	where a.voucher_id = pvoucher_id;
	
	return query
	select 	a.voucher_id, a.branch_id, a.doc_date, a.fc_type_id, a.fc_type, a.exch_rate, a.status, a.rcpt_type, a.received_from,
		a.account_id, a.account_head, a.customer_account_id, a.customer_account_head, a.narration, a.amt_in_words, a.amt_in_words_fc, 
		a.remarks, a.debit_amt, a.debit_amt_fc, a.cheque_number, a.cheque_date, a.collected, a.collection_date, a.cheque_bank, a.cheque_branch,
		a.entered_by, a.posted_by, a.adv_amt, a.adv_amt_fc, a.other_adj, a.other_adj_fc, a.net_settled, a.net_settled_fc
	from rcpt_report_temp a;
	       
END;
$BODY$
  LANGUAGE plpgsql;

?==?
-- Function for customer receipt receivable alloc document print report
CREATE FUNCTION ar.fn_receivable_ledger_alloc_report(In pvoucher_id varchar(50))  
RETURNS TABLE
(
	branch_id bigint,
	voucher_id varchar(50),
	vch_tran_id varchar(50),
	doc_date date,
	account_id bigint,
	exch_rate numeric(18,6),
	tds_amt numeric(18,4),
	tds_amt_fc numeric(18,4),
	credit_amt numeric(18,4),
	credit_amt_fc numeric(18,4),
	write_off_amt numeric(18,4),
	write_off_amt_fc numeric(18,4),
	debit_exch_diff numeric(18,4),
	credit_exch_diff numeric(18,4),
	other_exp numeric(18,4),
	other_exp_fc numeric(18,4),
	net_credit_amt numeric(18,4),
	net_credit_amt_fc numeric(18,4),
	invoice_id varchar(50)
) 
AS
$BODY$
BEGIN	
	return query
	select 	a.branch_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id, a.exch_rate, a.tds_amt, a.tds_amt_fc, 
		a.credit_amt, a.credit_amt_fc, a.write_off_amt, a.write_off_amt_fc, a.debit_exch_diff, a.credit_exch_diff, 
		a.other_exp, a.other_exp_fc, a.net_credit_amt, a.net_credit_amt_fc, b.voucher_id as invoice_id
	from ac.rl_pl_alloc a
	inner join ac.rl_pl b on a.rl_pl_id = b.rl_pl_id
	where a.voucher_id = pvoucher_id;
	       
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ar.fn_invoice_report(IN pvoucher_id character varying)
  RETURNS TABLE(invoice_id character varying, doc_type character varying, branch_id bigint, doc_date date, fc_type_id bigint, fc_type character varying, exch_rate numeric, status smallint, customer_id bigint, customer character varying, cust_address character varying, cust_city character varying, cust_country character varying, cust_pin character varying, cust_phone character varying, cust_mobile character varying, income_type_id bigint, income_type character varying, invoice_action character varying, narration character varying, amt_in_words character varying, amt_in_words_fc character varying, remarks character varying, invoice_amt numeric, invoice_amt_fc numeric, po_no character varying, po_date date, entered_by character varying, posted_by character varying, due_date date, invoice_address text) AS
$BODY$
	DEclare vCalcType smallint = 1; vDueDate date; vPayDays smallint = 0; vDocDate date;
BEGIN	
	DROP TABLE IF EXISTS invoice_report_temp;	
	create temp table invoice_report_temp
	(
		invoice_id varchar(50),
		doc_type varchar(4),
		branch_id bigint,
		doc_date date,
		fc_type_id bigint,
		fc_type varchar(20),
		exch_rate numeric(18,6),
		status smallint,
		customer_id bigint,
		customer varchar(250),
		cust_address varchar(500),
		cust_city varchar(50),
		cust_country varchar(50),
		cust_pin varchar(8),
		cust_phone varchar(50),
		cust_mobile varchar(50),
		income_type_id bigint,
		income_type varchar(250),
		invoice_action varchar(50),
		narration varchar(500),
		amt_in_words varchar(250),
		amt_in_words_fc varchar(250),
		remarks varchar(500),
		invoice_amt numeric(18,4),
		invoice_amt_fc numeric(18,4),
		po_no varchar(50),
		po_date date,
		entered_by varchar(100), 
		posted_by varchar(100),
		pay_term_id bigint,
		due_date date,
		invoice_address text 
	);

	If Exists(Select * From ar.invoice_control a Where a.invoice_id = pvoucher_id And (a.annex_info->'pos'->>'is_pos')::boolean) Then
		insert into invoice_report_temp(invoice_id, doc_type, branch_id, doc_date, fc_type_id, fc_type, exch_rate, status, customer_id, customer, cust_address, cust_city, cust_country,
			cust_pin, cust_phone, cust_mobile, income_type_id, income_type, invoice_action, narration, amt_in_words, amt_in_words_fc, remarks, invoice_amt, invoice_amt_fc, 
			po_no, po_date, entered_by, posted_by, pay_term_id, due_date, invoice_address)
		select 	a.invoice_id, a.doc_type, a.branch_id, a.doc_date, a.fc_type_id, g.fc_type, a.exch_rate, a.status, a.customer_id, a.annex_info->'pos'->>'cust_name', a.annex_info->'pos'->>'cust_address', '', '',
			'', a.annex_info->'pos'->>'cust_tel', a.annex_info->'pos'->>'cust_mob', a.income_type_id, d.income_type_name, '' as invoice_action, a.narration, a.amt_in_words, a.amt_in_words_fc, a.remarks, a.invoice_amt, a.invoice_amt_fc,  
			a.po_no, a.po_date, e.entered_by, e.posted_by, c.pay_term_id, a.doc_date + (cast(h.pay_days as varchar) || ' days')::interval, a.annex_info->'pos'->>'cust_address'
		from ar.invoice_control a
		inner join ar.customer c on a.customer_id = c.customer_id
		inner join ar.income_type d on a.income_type_id = d.income_type_id
		inner join sys.doc_es e on a.invoice_id = e.voucher_id
		inner join sys.address f on c.address_id = f.address_id
		inner join ac.fc_type g on a.fc_type_id = g.fc_type_id
		Inner join ac.pay_term h on c.pay_term_id = h.pay_term_id
		where a.invoice_id = pvoucher_id;
	Else 
		insert into invoice_report_temp(invoice_id, doc_type, branch_id, doc_date, fc_type_id, fc_type, exch_rate, status, customer_id, customer, cust_address, cust_city, cust_country,
			cust_pin, cust_phone, cust_mobile, income_type_id, income_type, invoice_action, narration, amt_in_words, amt_in_words_fc, remarks, invoice_amt, invoice_amt_fc, 
			po_no, po_date, entered_by, posted_by, pay_term_id, due_date, invoice_address)
		select 	a.invoice_id, a.doc_type, a.branch_id, a.doc_date, a.fc_type_id, g.fc_type, a.exch_rate, a.status, a.customer_id, c.customer, f.address, f.city, f.country,
			f.pin, f.phone, f.mobile, a.income_type_id, d.income_type_name, '' as invoice_action, a.narration, a.amt_in_words, a.amt_in_words_fc, a.remarks, a.invoice_amt, a.invoice_amt_fc,  
			a.po_no, a.po_date, e.entered_by, e.posted_by, c.pay_term_id, a.doc_date + (cast(h.pay_days as varchar) || ' days')::interval, a.invoice_address
		from ar.invoice_control a
		inner join ar.customer c on a.customer_id = c.customer_id
		inner join ar.income_type d on a.income_type_id = d.income_type_id
		inner join sys.doc_es e on a.invoice_id = e.voucher_id
		inner join sys.address f on c.address_id = f.address_id
		inner join ac.fc_type g on a.fc_type_id = g.fc_type_id
		Inner join ac.pay_term h on c.pay_term_id = h.pay_term_id
		where a.invoice_id = pvoucher_id;
	End If;

	-- Determine Due Date
	select calc_type, pay_days into vCalcType, vPayDays from ac.pay_term
	where pay_term_id in (Select pay_term_id from invoice_report_temp limit 1);

	Select a.doc_date into vDocDate
	From ar.invoice_control a
	where a.invoice_id = pvoucher_id;
	
	
	If vCalcType = 0 Then -- End of month			
		SELECT (date_trunc('MONTH', vDocDate) + INTERVAL '1 MONTH - 1 day')::date + (cast(vPayDays as varchar) || ' days')::interval into vDueDate;
	Else		
		SELECT vDocDate + (cast(vPayDays as varchar) || ' days')::interval into vDueDate;
	End If;
	
	return query
	select 	a.invoice_id, a.doc_type, a.branch_id, a.doc_date, a.fc_type_id, a.fc_type, a.exch_rate, a.status, a.customer_id, a.customer, a.cust_address, a.cust_city, a.cust_country, a.cust_pin, 
		a.cust_phone, a.cust_mobile, a.income_type_id, a.income_type, a.invoice_action, a.narration, a.amt_in_words, a.amt_in_words_fc, a.remarks, a.invoice_amt, a.invoice_amt_fc, 
		a.po_no, a.po_date, a.entered_by, a.posted_by, vDueDate, a.invoice_address
	from invoice_report_temp a;
	       
END;
$BODY$
  LANGUAGE plpgsql;


?==?
CREATE OR REPLACE FUNCTION ar.fn_stmt_of_ac_br_report_customer_address(IN pcompany_id bigint, IN paccount_id bigint)
  RETURNS TABLE(customer_id bigint, customer character varying, address character varying, fax character varying, phone character varying, 
                pay_term_id bigint, pay_term character varying, customer_type_id bigint, contact_person character varying, account_head character varying, is_overridden boolean) AS
$BODY$
Begin 
	-- Fetch Customer Address
	DROP TABLE IF EXISTS customer_address_temp;
	CREATE temp TABLE  customer_address_temp
	(
		customer_id bigint NOT NULL,
		customer varchar(250),
		address character varying,
		fax varchar(50),
		phone varchar(50),
		pay_term_id bigint,
		pay_term varchar(50),
		customer_type_id bigint,
        contact_person character varying,
        account_head character varying,
        is_overridden boolean,
		CONSTRAINT pk_customer_address_temp PRIMARY KEY (customer_id)
	 );
	 
	Insert Into customer_address_temp(customer_id, customer, address, fax, phone, pay_term_id, pay_term, customer_type_id, contact_person, account_head, is_overridden)
	Select a.customer_id, a.customer_name,  b.address || E'\n' || b.city || case when b.pin = '' then '' else ' - ' end  
			    || b.pin || case when b.state = '' then '' else E'\n' end  || b.state || case when b.country = '' then '' else E'\n' end || b.country AS address, b.fax, b.phone, a.pay_term_id, 
		'' AS pay_term, -1 AS customer_type_id, b.contact_person,
        a.customer, COALESCE((a.annex_info->>'is_overridden')::boolean, false)
	From ar.customer a
	Inner Join sys.address b On a.address_id = b.address_id
	Where a.company_ID = pcompany_id
		And (a.customer_id = paccount_id Or paccount_id = 0);

	Update customer_address_temp a
	Set pay_term = b.pay_term
	from ( select x.customer_id, x.pay_term_id, y.pay_term 
			from ar.customer x 
			Inner Join ac.pay_term y on x.pay_term_id = y.pay_term_id
	      ) b 
	Where a.customer_id = b.customer_id;
	
	return query 
	select a.customer_id, a.customer, a.address, a.fax, a.phone, a.pay_term_id, a.pay_term, a.customer_type_id, a.contact_person, a.account_head, a.is_overridden
	from customer_address_temp a;	

END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ar.fn_ar_tb_report(IN pcompany_id bigint, IN pbranch_id bigint, IN pyear character varying, IN pfrom_date date, IN pto_date date, IN pmodule varchar(2) = 'ar')
RETURNS TABLE
(
	account_id bigint, 
	account_head character varying, 
	account_code character varying,		
	control_account_id bigint, 
	control_account_head varchar(250), 
	control_account_code varchar(20), 
	debit_opening_balance numeric, 
	credit_opening_balance numeric, 
	period_debits numeric, 
	period_credits numeric, 
	debit_closing_balance numeric, 
	credit_closing_balance numeric, 
	debit_opening_total numeric(18,4), 
	credit_opening_total numeric(18,4), 
	period_debits_total numeric(18,4), 
	period_credits_total numeric(18,4), 
	debit_closing_total numeric(18,4), 
	credit_closing_total numeric(18,4),
	opening_bal_bit bigint
) AS
$BODY$
BEGIN

	DROP TABLE IF EXISTS tb_report;
	CREATE temp TABLE  tb_report
	(
		account_id bigint, 
		account_head varchar(250), 
		account_code varchar(20),		
		control_account_id bigint, 
		control_account_head varchar(250), 
		control_account_code varchar(20),
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
	--	*****	Final Third Step: Get the related Description of the Accounts and Groups excluding other Account Types
	
	Insert into tb_report_temp(account_id, debit_opening_balance, credit_opening_balance, 
				period_debits, period_credits, 
				debit_closing_balance, credit_closing_balance)
	Select a.account_id, a.debit_opening_balance, a.credit_opening_balance, 
				a.period_debits, a.period_credits, 
				a.debit_closing_balance, a.credit_closing_balance
	From ac.fn_tb_op_tran_cl(pcompany_id, pbranch_id, pyear, pfrom_date, pto_date) a;	

	if pmodule = 'ar' Then
		Insert Into tb_report(account_id, account_head, account_code, control_account_id, control_account_head, control_account_code, 
					debit_opening_balance, credit_opening_balance, period_debits, period_credits, 
					debit_closing_balance, credit_closing_balance, opening_bal_bit)
		Select	a.account_id, b.account_head, b.account_code, d.account_id as control_account_id, d.account_head as control_account_head, d.account_code as control_account_code, 	
			sum(a.debit_opening_balance) as debit_opening_balance, sum(a.credit_opening_balance) as credit_opening_balance, 
			sum(a.period_debits) as period_debits, sum(a.period_credits) as period_credits,
			sum(a.debit_closing_balance) as debit_closing_balance, sum(a.credit_closing_balance) as credit_closing_balance, 0
		From tb_report_temp a
		inner join ac.account_head b on a.account_id=b.account_id
		inner join ar.customer c on a.account_id = c.customer_id
		inner join ac.account_head d on c.control_account_id=d.account_id
		group by a.account_id, b.account_code, b.account_head, d.account_id, d.account_head, d.account_code;
	Elseif pmodule = 'ap' Then
		Insert Into tb_report(account_id, account_head, account_code, control_account_id, control_account_head, control_account_code, 
					debit_opening_balance, credit_opening_balance, period_debits, period_credits, 
					debit_closing_balance, credit_closing_balance, opening_bal_bit)
		Select	a.account_id, b.account_head, b.account_code, d.account_id as control_account_id, d.account_head as control_account_head, d.account_code as control_account_code, 	
			sum(a.debit_opening_balance) as debit_opening_balance, sum(a.credit_opening_balance) as credit_opening_balance, 
			sum(a.period_debits) as period_debits, sum(a.period_credits) as period_credits,
			sum(a.debit_closing_balance) as debit_closing_balance, sum(a.credit_closing_balance) as credit_closing_balance, 0
		From tb_report_temp a
		inner join ac.account_head b on a.account_id=b.account_id
		inner join ap.supplier c on a.account_id = c.supplier_id
		inner join ac.account_head d on c.control_account_id=d.account_id
		group by a.account_id, b.account_code, b.account_head, d.account_id, d.account_head, d.account_code;
	End If;

	return query
	Select	a.account_id, a.account_head, a.account_code, a.control_account_id, a.control_account_head, a.control_account_code,
		a.debit_opening_balance, a.credit_opening_balance, a.period_debits, a.period_credits, a.debit_closing_balance, a.credit_closing_balance,
		Sum(a.debit_opening_balance) over (partition by a.control_account_id), 
		Sum(a.credit_opening_balance) over (partition by a.control_account_id),
		Sum(a.period_debits) over (partition by a.control_account_id),
		Sum(a.period_credits) over (partition by a.control_account_id),
		Sum(a.debit_closing_balance) over (partition by a.control_account_id), 
		Sum(a.credit_closing_balance) over (partition by a.control_account_id),
		a.opening_bal_bit
	From tb_report a
	order by a.control_account_head, a.account_head;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ar.fn_ar_tb_report_ct(IN pcompany_id bigint, IN pbranch_id bigint, IN pyear character varying, IN pfrom_date date, IN pto_date date, IN pmodule varchar(2) = 'ar')
RETURNS TABLE
(
	branch_id bigint, 
	branch_code character varying, 
	branch_name character varying, 
	account_id bigint, 
	account_head character varying, 
	account_code character varying, 		
	control_account_id bigint, 
	control_account_head varchar(250), 
	control_account_code varchar(20),
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
	opening_bal_bit bigint
) AS
$BODY$
DECLARE diff  numeric(18,4) = 0;
BEGIN

	DROP TABLE IF EXISTS tb_report_CT;
	CREATE temp TABLE  tb_report_CT
	(
		branch_id bigint,
		branch_code varchar(50),
		branch_name varchar(250),
		account_id bigint, 
		account_head varchar(250), 
		account_code varchar(20),		
		control_account_id bigint, 
		control_account_head varchar(250), 
		control_account_code varchar(20),
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
	(	branch_id bigint,
		account_id bigint, 
		debit_opening_balance numeric(18,4), 
		credit_opening_balance numeric(18,4), 
		period_debits numeric(18,4), 
		period_credits numeric(18,4), 
		debit_closing_balance numeric(18,4), 
		credit_closing_balance numeric(18,4)
	);
	
	Insert into tb_report_temp(branch_id, account_id, debit_opening_balance, credit_opening_balance, 
				period_debits, period_credits, 
				debit_closing_balance, credit_closing_balance)
	Select a.branch_id,a.account_id, a.debit_opening_balance, a.credit_opening_balance, 
				a.period_debits, a.period_credits, 
				a.debit_closing_balance, a.credit_closing_balance
	From ac.fn_tb_op_tran_cl_CT(pcompany_id, pbranch_id, pyear, pfrom_date, pto_date) a;	
	
	--Delete Rows without transaction
	DELETE FROM tb_report_temp d
	WHERE d.debit_opening_balance=0 AND d.credit_opening_balance=0 AND d.period_debits=0 AND d.period_credits=0 AND d.debit_closing_balance=0 AND d.credit_closing_balance=0;

	
	if pmodule = 'ar' Then
		Insert Into tb_report_CT(branch_id , branch_code, branch_name, account_id, account_head, account_code, 
					control_account_id, control_account_head, control_account_code, 
					debit_opening_balance, credit_opening_balance, period_debits, period_credits, 
					debit_closing_balance, credit_closing_balance, opening_bal_bit)
		Select	a.branch_id , c.branch_code, c.branch_name, a.account_id, b.account_head, b.account_code, 
			e.account_id as control_account_id, e.account_head as control_account_head, e.account_code as control_account_code, 	
			sum(a.debit_opening_balance) as debit_opening_balance, sum(a.credit_opening_balance) as credit_opening_balance,
			sum(a.period_debits) as period_debits, sum(a.period_credits) as period_credits,
			sum(a.debit_closing_balance) as debit_closing_balance, sum(a.credit_closing_balance) as credit_closing_balance,0
		From tb_report_temp a
		inner join ac.account_head b on a.account_id=b.account_id
		inner join ar.customer d on a.account_id = d.customer_id
		inner join ac.account_head e on d.control_account_id=e.account_id
		inner join sys.branch c on a.branch_id=c.branch_id
		group by a.account_id, b.account_code, b.account_head, e.account_id, e.account_head, e.account_code, a.branch_id, c.branch_code, c.branch_name;
	Elseif pmodule = 'ap' Then
		Insert Into tb_report_CT(branch_id , branch_code, branch_name, account_id, account_head, account_code, 
					control_account_id, control_account_head, control_account_code, 
					debit_opening_balance, credit_opening_balance, period_debits, period_credits, 
					debit_closing_balance, credit_closing_balance, opening_bal_bit)
		Select	a.branch_id , c.branch_code, c.branch_name, a.account_id, b.account_head, b.account_code, 
			e.account_id as control_account_id, e.account_head as control_account_head, e.account_code as control_account_code, 	
			sum(a.debit_opening_balance) as debit_opening_balance, sum(a.credit_opening_balance) as credit_opening_balance,
			sum(a.period_debits) as period_debits, sum(a.period_credits) as period_credits,
			sum(a.debit_closing_balance) as debit_closing_balance, sum(a.credit_closing_balance) as credit_closing_balance,0
		From tb_report_temp a
		inner join ac.account_head b on a.account_id=b.account_id
		inner join ap.supplier d on a.account_id = d.supplier_id
		inner join ac.account_head e on d.control_account_id=e.account_id
		inner join sys.branch c on a.branch_id=c.branch_id
		group by a.account_id, b.account_code, b.account_head, e.account_id, e.account_head, e.account_code, a.branch_id, c.branch_code, c.branch_name;
	End If;

	return query
	Select	a.branch_id , a.branch_code, a.branch_name, a.account_id, a.account_head, a.account_code,
		a.control_account_id, a.control_account_head, a.control_account_code,  
		a.debit_opening_balance, a.credit_opening_balance, a.period_debits, a.period_credits, a.debit_closing_balance, a.credit_closing_balance,
		a.debit_opening_total, a.credit_opening_total, a.period_debits_total, a.period_credits_total, a.debit_closing_total, a.credit_closing_total, a.opening_bal_bit
	From tb_report_CT a
	order by a.control_account_head, a.account_head;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
create or replace function ar.fn_customer_credit_limit(pcompany_id bigint, pbranch_id bigint, pcustomer_id bigint, pto_date date)
RETURNS TABLE  
(	
	branch_id bigint,
	voucher_id varchar(50),
	doc_date date,
	customer_id bigint,
	customer varchar(250),
	credit_limit_type smallint,
	credit_limit numeric(18,4),
	credit_availed numeric(18,4),
	billed numeric(18,4),
	not_billed numeric(18,4)
)
AS
$BODY$
Begin	
	-- This function is used by the Posting Trigger to get information on the Supplier Payment (PYMT)
	DROP TABLE IF EXISTS cust_limit_temp;	
	create temp TABLE  cust_limit_temp
	(		
		branch_id bigint,	
		voucher_id varchar(50),
		doc_date date,
		customer_id bigint,
		customer varchar(250),
		credit_limit_type smallint,
		credit_limit numeric(18,4),
		credit_availed numeric(18,4),
		billed numeric(18,4),
		not_billed numeric(18,4)
	);

	DROP TABLE IF EXISTS cust_limit;	
	create temp TABLE  cust_limit
	(		
		branch_id bigint,	
		voucher_id varchar(50),
		doc_date date,
		customer_id bigint,
		customer varchar(250),
		credit_limit_type smallint,
		credit_limit numeric(18,4),
		credit_availed numeric(18,4),
		billed numeric(18,4),
		not_billed numeric(18,4)
	);
		
	Insert into cust_limit_temp (branch_id, voucher_id, doc_date, customer_id, customer, credit_limit_type, 
		credit_limit, 
		credit_availed, billed, not_billed)
	Select COALESCE(a.branch_id, -1), COALESCE(a.voucher_id, ''), COALESCE(a.doc_date, '1970-01-01'), COALESCE(a.account_id, -1), b.customer, b.credit_limit_type,
		b.credit_limit, COALESCE(a.balance, 0), COALESCE(a.balance, 0), 0
	From ar.fn_stmt_of_ac_br_report(pcompany_id, pbranch_id, pcustomer_id, pto_date, 0) a
	inner join ar.customer b on a.account_id = b.customer_id;

	if exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'ro_control') then
		Insert into cust_limit_temp (branch_id, voucher_id, doc_date, customer_id, customer, credit_limit_type, 
			credit_limit, 
			credit_availed, billed, not_billed)
		Select a.branch_id, case when a.ro_id != '' then a.ro_id else a.invoice_id end, a.doc_date, a.customer_id, b.customer, b.credit_limit_type,
			b.credit_limit as credit_limit, 
			sum(a.ro_tran_amt + a.inv_amt), 0, sum(a.ro_tran_amt + a.inv_amt)
		From pub.fn_customer_bal_credit_limit(pcompany_id, pbranch_id, pto_date, pcustomer_id, '') a
		inner join ar.customer b on a.customer_id = b.customer_id
		Group By a.branch_id, a.ro_id, a.invoice_id, a.doc_date, a.customer_id, b.customer, b.credit_limit_type, b.credit_limit;
	End If;
    
	
--  Get details from CRM Opportunity
	if exists (SELECT * FROM information_schema.tables where table_schema='sd' And table_name = 'dmr_control') then		
		Insert into cust_limit_temp (branch_id, voucher_id, doc_date, customer_id, customer, credit_limit_type, 
			credit_limit, 
			credit_availed, billed, not_billed)
                select a.branch_id, a.opportunity_id, a.doc_date, a.customer_id, c.customer,  c.credit_limit_type,
                                c.credit_limit as credit_limit, 
                    sum(b.item_amt), 0, sum(b.item_amt)
                from crm.opportunity_control a
                inner join crm.opportunity_tran b on a.opportunity_id = b.opportunity_id
                        inner join ar.customer c on a.customer_id = c.customer_id
                where a.status = 5
                    And (a.customer_id = pcustomer_id or pcustomer_id = 0)
                    And a.is_close_date = false
                    And b.opportunity_id not in (Select (a.annex_info->'sd_info'->>'so_id')::varchar from st.stock_control a
                                                where a.status = 5 
                                                And (a.annex_info->'sd_info'->>'so_id')::varchar != '') 
                Group By a.branch_id, a.opportunity_id, a.doc_date, a.customer_id, c.customer,  c.credit_limit_type,
                                c.credit_limit;
	Elseif exists (SELECT * FROM information_schema.tables where table_schema='crm' And table_name = 'opportunity_control') then		
			Insert into cust_limit_temp (branch_id, voucher_id, doc_date, customer_id, customer, credit_limit_type, 
                                credit_limit, 
                                credit_availed, billed, not_billed)
            select a.branch_id, a.opportunity_id, a.doc_date, a.customer_id, c.customer,  c.credit_limit_type,
                            c.credit_limit as credit_limit, 
                sum(b.item_amt), 0, sum(b.item_amt)
            from crm.opportunity_control a
            inner join crm.opportunity_tran b on a.opportunity_id = b.opportunity_id
            inner join ar.customer c on a.customer_id = c.customer_id
            where a.status = 5
                And (a.customer_id = pcustomer_id or pcustomer_id = 0)
                And a.is_close_date = false
                And b.opportunity_id not in (Select b.reference_id from st.stock_control a
                                            inner join st.stock_tran b on a.stock_id = b.stock_id
                                            where a.status = 5 
                                            And b.reference_id != ''
                                            And a.doc_type = 'SIV') 
            Group By a.branch_id, a.opportunity_id, a.doc_date, a.customer_id, c.customer,  c.credit_limit_type,
                            c.credit_limit;
	End If;
    
	-- Get details from CRM Estimate
	if exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'invoice_control') then
		Insert into cust_limit_temp (branch_id, voucher_id, doc_date, customer_id, customer, credit_limit_type, 
			credit_limit, 
			credit_availed, billed, not_billed)
        select a.branch_id, a.opportunity_id, a.doc_date, a.customer_id, c.customer,  c.credit_limit_type,
			c.credit_limit as credit_limit, 
            sum(b.item_amt), 0, sum(b.item_amt)
        from crm.opportunity_control a
        inner join crm.estimate_tran b on a.opportunity_id = b.opportunity_id
		inner join ar.customer c on a.customer_id = c.customer_id
        where a.status = 5
            And (a.customer_id = pcustomer_id or pcustomer_id = 0)
            And a.is_close_date = false
            And b.estimate_tran_id not in  (Select b.ro_tran_id from pub.invoice_control a
                            inner join pub.invoice_ro_tran b on a.voucher_id = b.voucher_id
                            where a.status = 5)
        Group By  a.branch_id, a.opportunity_id, a.doc_date, a.customer_id, c.customer,  c.credit_limit_type,
			c.credit_limit;
	End If;
	
	Insert into cust_limit (branch_id, voucher_id, doc_date, customer_id, customer, credit_limit_type, 
		credit_limit, credit_availed, billed, not_billed)
	Select a.branch_id, a.voucher_id, a.doc_date, a.customer_id, a.customer, a.credit_limit_type, 
		a.credit_limit, sum(a.credit_availed), sum(a.billed), sum(a.not_billed)
	From cust_limit_temp a
	Group by a.branch_id, a.voucher_id, a.doc_date, a.customer_id, a.customer, a.credit_limit_type, a.credit_limit;
	
	return query 
	select a.branch_id, a.voucher_id, a.doc_date, a.customer_id, a.customer, a.credit_limit_type, a.credit_limit, a.credit_availed, a.billed, a.not_billed
	from cust_limit a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
create or replace function ar.fn_customer_overdue(pcompany_id bigint, pbranch_id bigint, paccount_id bigint, pto_date date, 
	pvoucher_id varchar(50), pdc varchar(1))
RETURNS TABLE  
(	
	account_id bigint,
	account_head varchar(250),
	voucher_id varchar(50),
	doc_date date,
	fc_type_id bigint,
	fc_type varchar(20),
	overdue_days smallint,
	due_date date,
	overdue numeric(18,4),
	overdue_fc numeric(18,4),
	not_due numeric(18,4),
	not_due_fc numeric(18,4),
	branch_id bigint
)
AS
$BODY$ 
	
Begin	
	-- This function is used by the Posting Trigger to get information on the Supplier Payment (PYMT)
	DROP TABLE IF EXISTS cust_overdue_temp;	
	create temp TABLE  cust_overdue_temp
	(	
		account_id bigint,
		account_head varchar(250),
		voucher_id varchar(50),
		doc_date date,
		fc_type_id bigint,
		fc_type varchar(20),
		overdue_days smallint,
		due_date date,
		overdue numeric(18,4),
		overdue_fc numeric(18,4),
		not_due numeric(18,4),
		not_due_fc numeric(18,4),
		branch_id bigint
	);

	Insert into cust_overdue_temp(account_id, account_head, voucher_id, doc_date, fc_type_id, fc_type, 
		overdue_days, 
		due_date, 
                overdue, 
                overdue_fc, 
                not_due, 
                not_due_fc,
                branch_id)
	select a.account_id, b.account_head, a.voucher_id, a.doc_date, a.fc_type_id, a.fc_type, 
		DATE_PART('day', pto_date::timestamp - a.due_date::timestamp), -- will return -ve for not due
		a.due_date, 
                case when a.due_date < pto_date then sum(a.balance) else 0 end as overdue, 
                case when a.due_date < pto_date then sum(a.balance_fc) else 0 end as overdue_fc, 
                case when a.due_date >= pto_date then sum(a.balance) else 0 end as not_due, 
                case when a.due_date >= pto_date then sum(a.balance_fc) else 0 end as not_due_fc,
                a.branch_id
	from ar.fn_pending_inv(pcompany_id, pbranch_id, paccount_id, pto_date, pvoucher_id, pdc, 5::smallint) a
	inner Join ac.account_head b on a.account_id = b.account_id
	group by a.account_id, b.account_head, a.voucher_id, a.doc_date, a.fc_type_id, a.fc_type, a.due_date, a.branch_id;
	
	return query 
	select a.account_id, a.account_head, a.voucher_id, a.doc_date, a.fc_type_id, a.fc_type, a.overdue_days, a.due_date, a.overdue, a.overdue_fc, 
                a.not_due, a.not_due_fc, a.branch_id
	from cust_overdue_temp a
	order by a.account_head;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ar.fn_cust_due_by_salesman_report(IN pcompany_id bigint, IN pbranch_id bigint, IN psalesman_id bigint, IN pto_date date, pin_reporting_sm boolean)
RETURNS TABLE
(
	dummy varchar(10),  
	rl_pl_id uuid, 
	branch_id bigint, 
	voucher_id varchar(50),  
	vch_tran_id varchar(50),  
	doc_date date,  
	account_id bigint,  
	en_bill_type smallint,   
	balance_fc numeric(18,4),  
	fc_type_id bigint,  
	balance numeric(18,4),  
	customer varchar(250),  
	address varchar(1000),
	fax varchar(50),
	phone varchar(50), 
	narration varchar(500), 
	fc_type varchar(20),
	currency varchar(20),
	pay_term_id bigint,
	pay_term_desc varchar(150),
	days integer,  
	period_id integer,  
	period varchar(15),
	control_account_id bigint,
	account_head varchar(250),
	salesman_id bigint,
	salesman_name varchar(50)
) AS
$BODY$
Declare sm_parent bigint[];
Begin 
	DROP TABLE IF EXISTS cust_due_temp;
	CREATE temp TABLE  cust_due_temp
	(
		dummy varchar(10),  
		rl_pl_id uuid, 
		branch_id bigint, 
		voucher_id varchar(50),  
		vch_tran_id varchar(50),  
		doc_date date,  
		account_id bigint,  
		en_bill_type smallint,   
		balance_fc numeric(18,4),  
		fc_type_id bigint,  
		balance numeric(18,4),  
		customer varchar(250),  
		address varchar(1000),
		fax varchar(50),
		phone varchar(50), 
		narration varchar(500), 
		fc_type varchar(20),
		currency varchar(20),
		pay_term_id bigint,
		pay_term_desc varchar(150),
		days integer,  
		period_id integer,  
		period varchar(15),
		control_account_id bigint,
		account_head varchar(250),
		salesman_id bigint,
		salesman_name varchar(50),
		CONSTRAINT pk_cust_due_report_temp PRIMARY KEY (rl_pl_id)
	 );

	Insert Into cust_due_temp(dummy, rl_pl_id, branch_id, voucher_id, vch_tran_id, doc_date, account_id,
			en_bill_type, balance_fc, fc_type_id, balance, customer, address, fax, phone, narration, fc_type,
			currency, pay_term_id, pay_term_desc, days, period_id,  period, control_account_id, account_head, salesman_id, salesman_name) 
        Select a.dummy, a.rl_pl_id, a.branch_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id,
		a.en_bill_type, a.balance_fc, a.fc_type_id, a.balance, a.customer, a.address, a.fax, a.phone, a.narration, a.fc_type,
		a.currency, a.pay_term_id, a.pay_term_desc, a.days, a.period_id,  a.period, a.control_account_id, a.account_head, -1, ''
	FROM ar.fn_stmt_of_ac_br_ageing_report(pcompany_id, pbranch_id, 0, pto_date, 0, 0) a;
	
	update cust_due_temp a
	set salesman_id = b.salesman_id, 
	    salesman_name = c.salesman_name
	from ar.invoice_control b 
	inner join ar.salesman c on b.salesman_id = c.salesman_id
	where a.voucher_id = b.invoice_id;
		--And (b.salesman_id = psalesman_id or psalesman_id = 0);

	if exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'invoice_control') then
		update cust_due_temp a
		set salesman_id = b.salesman_id, 
		    salesman_name = c.salesman_name
		from pub.invoice_control b 
		inner join ar.salesman c on b.salesman_id = c.salesman_id
		where a.voucher_id = b.voucher_id; 
			--And (b.salesman_id = psalesman_id or psalesman_id = 0);
	End If;
	
	if exists (SELECT * FROM information_schema.tables where table_schema='st' And table_name = 'stock_control') then	
		update cust_due_temp a
		set salesman_id = b.salesman_id, 
		    salesman_name = c.salesman_name
		from st.stock_control b 
		inner join ar.salesman c on b.salesman_id = c.salesman_id
		where a.voucher_id = b.stock_id; 
			--And (b.salesman_id = psalesman_id or psalesman_id = 0);
	End If;

	Update cust_due_temp a
	set salesman_id = d.salesman_id, 
	    salesman_name = d.salesman_name
	From ar.customer c 
	inner join ar.salesman d on c.salesman_id = d.salesman_id
	where a.account_id = c.customer_id and a.salesman_id = -1;

	With recursive sm_parent
        As
        (	Select a.parent_salesman_id, a.salesman_id, 1 as level, a.salesman_name,  
                array[a.salesman_id] as sm_path, false as cycle
            From ar.salesman a
            Where a.salesman_id = psalesman_id
            Union All
            Select a.parent_salesman_id, a.salesman_id, b.level + 1, a.salesman_name, 
                b.sm_path||a.salesman_id, a.salesman_id = Any(b.sm_path)
            From ar.salesman a
            Inner Join sm_parent b On a.parent_salesman_id = b.salesman_id
            Where not cycle
        )
        Select array_agg(a.salesman_id) into sm_parent
        From sm_parent a;   

	
	return query 
	select a.dummy, a.rl_pl_id, a.branch_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id,
		a.en_bill_type, a.balance_fc, a.fc_type_id, a.balance, a.customer, a.address, a.fax, a.phone, a.narration, a.fc_type,
		a.currency, a.pay_term_id, a.pay_term_desc, a.days, a.period_id,  a.period, a.control_account_id, a.account_head, a.salesman_id, case when a.salesman_id = -1 then 'Others' else a.salesman_name end
	from cust_due_temp a
        Where case when (psalesman_id != 0 And pin_reporting_sm = true) Then a.salesman_id = Any (sm_parent)
    		Else (a.salesman_id = psalesman_id or psalesman_id = 0) End;		

END;
$BODY$
  LANGUAGE plpgsql;

?==?
create OR REPLACE function ar.salesman_outstanding_report(pcompany_id bigint, pbranch_id bigint, psalesman_id bigint, pto_date date, pin_reporting_sm boolean)
RETURNS TABLE  
(	rl_pl_id uuid, 
	voucher_id varchar(50), 
	vch_tran_id varchar(50),
	doc_date date,
	account_id bigint,
	account_head varchar(250),
	balance numeric(18,4),
	balance_fc numeric(18,4),
	fc_type_id bigint,
	fc_type varchar(20),
	branch_id bigint,
	narration varchar(500),
	due_date date,
	salesman_id bigint,
	salesman_name varchar(50),
	adv_amt numeric(18,4),
	adv_amt_fc numeric(18,4)
)
AS
$BODY$
Declare sm_parent bigint[]; 
Begin	

	DROP TABLE IF EXISTS salesman_os_temp;	
	create temp TABLE  salesman_os_temp
	(	
		rl_pl_id uuid, 
		voucher_id varchar(50), 
		vch_tran_id varchar(50),
		doc_date date,
		account_id bigint,
		account_head varchar(250),
		balance numeric(18,4),
		balance_fc numeric(18,4),
		fc_type_id bigint,
		fc_type varchar(20),
		branch_id bigint,
		narration varchar(500),
		due_date date,
		salesman_id bigint,
		salesman_name varchar(50),
		adv_amt numeric(18,4),
		adv_amt_fc numeric(18,4)
	);
	
	Insert into salesman_os_temp(rl_pl_id, voucher_id, vch_tran_id, doc_date, account_id, account_head, balance, balance_fc, 
		fc_type_id, fc_type, branch_id, narration, due_date, salesman_id, salesman_name, adv_amt, adv_amt_fc)
	select a.rl_pl_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id, b.account_head, a.balance, a.balance_fc, 
		a.fc_type_id, a.fc_type, a.branch_id, a.narration, a.due_date, -1, '', 0, 0	    
	from ar.fn_pending_inv(pcompany_id, pbranch_id, 0, pto_date, '', 'D') a
	inner Join ac.account_head b on a.account_id = b.account_id;
		
	-- Advances
	Insert into salesman_os_temp(rl_pl_id, voucher_id, vch_tran_id, doc_date, account_id, account_head, balance, balance_fc, 
		fc_type_id, fc_type, branch_id, narration, due_date, salesman_id, salesman_name, adv_amt, adv_amt_fc)
	select a.rl_pl_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id, b.account_head, 0, 0, 
		a.fc_type_id, a.fc_type, a.branch_id, a.narration, a.due_date, -1, '', a.balance, a.balance_fc
	from ar.fn_pending_inv(pcompany_id, pbranch_id, 0, pto_date, '', 'C') a
	inner Join ac.account_head b on a.account_id = b.account_id;
	
	Update salesman_os_temp a
	set salesman_id = d.salesman_id, 
	    salesman_name = d.salesman_name
	From ar.invoice_control c 
	inner join ar.salesman d on c.salesman_id = d.salesman_id
	where a.voucher_id = c.invoice_id;-- And (d.salesman_id = psalesman_id or psalesman_id = 0);

	if exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'invoice_control') then	
		Update salesman_os_temp a
		set salesman_id = d.salesman_id, 
		    salesman_name = d.salesman_name
		From pub.invoice_control c 
		inner join ar.salesman d on c.salesman_id = d.salesman_id
		where a.voucher_id = c.voucher_id;-- And (d.salesman_id = psalesman_id or psalesman_id = 0);
	End If;

	if exists (SELECT * FROM information_schema.tables where table_schema='st' And table_name = 'stock_control') then	
		Update salesman_os_temp a
		set salesman_id = d.salesman_id, 
		    salesman_name = d.salesman_name
		From st.stock_control c 
		inner join ar.salesman d on c.salesman_id = d.salesman_id
		where a.voucher_id = c.stock_id;-- And (d.salesman_id = psalesman_id or psalesman_id = 0);
	End If;

	Update salesman_os_temp a
	set salesman_id = d.salesman_id, 
	    salesman_name = d.salesman_name
	From ar.customer c 
	inner join ar.salesman d on c.salesman_id = d.salesman_id
	where a.account_id = c.customer_id and a.salesman_id = -1;

	With recursive sm_parent
        As
        (   Select a.parent_salesman_id, a.salesman_id, 1 as level, a.salesman_name,  
                array[a.salesman_id] as sm_path, false as cycle
            From ar.salesman a
            Where a.salesman_id = psalesman_id
            Union All
            Select a.parent_salesman_id, a.salesman_id, b.level + 1, a.salesman_name, 
                b.sm_path||a.salesman_id, a.salesman_id = Any(b.sm_path)
            From ar.salesman a
            Inner Join sm_parent b On a.parent_salesman_id = b.salesman_id
            Where not cycle
        )
        Select array_agg(a.salesman_id) into sm_parent
        From sm_parent a; 
	
	return query 
	select a.rl_pl_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id, a.account_head, a.balance, a.balance_fc, 
		a.fc_type_id, a.fc_type, a.branch_id, a.narration, a.due_date, a.salesman_id, case when a.salesman_id = -1 then 'Others' else a.salesman_name end,
		a.adv_amt, a.adv_amt_fc
	from salesman_os_temp a
        Where case when (psalesman_id != 0 And pin_reporting_sm = true) Then a.salesman_id = Any (sm_parent)
    		Else (a.salesman_id = psalesman_id or psalesman_id = 0) End;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
create or replace function ar.fn_business_turnover(pcompany_id bigint, pbranch_id bigint, psalesman_id bigint, pcustomer_id bigint, pfrom_date date, pto_date date)
RETURNS TABLE  
(	
    voucher_id varchar(50),
    doc_date date,
    customer_id bigint,
    customer varchar(250),
    salesman_id bigint,
    salesman_name varchar(50),
    invoice_amt numeric(18,4),
    invoice_amt_fc numeric(18,4),
    bt_amt numeric(18,4),
    tax_amt numeric(18,4),
    branch_id bigint
)
AS
$BODY$
Begin	
	-- This function is used by the Posting Trigger to get information on the Supplier Payment (PYMT)
	DROP TABLE IF EXISTS bt_temp;	
	create temp TABLE  bt_temp
	(	
            voucher_id varchar(50),
            doc_date date,
            customer_id bigint,
            customer varchar(250),
            salesman_id bigint,
            salesman_name varchar(50),
            invoice_amt numeric(18,4),
            invoice_amt_fc numeric(18,4),
            bt_amt numeric(18,4),
            tax_amt numeric(18,4),
            branch_id bigint
	);
	with tax_tran 
	As 
	(
		Select a.voucher_id, sum(a.tax_amt) as tax_amt, sum(a.tax_amt_fc) as tax_amt_fc 
		from tx.tax_tran a
		group by a.voucher_id
	)
	Insert into bt_temp (voucher_id, doc_date, customer_id, customer, salesman_id, salesman_name, invoice_amt, invoice_amt_fc, bt_amt, tax_amt, branch_id)
	Select a.invoice_id, a.doc_date, a.customer_id, b.customer, a.salesman_id, d.salesman_name, a.invoice_amt, a.invoice_amt_fc, 
		case when COALESCE((a.annex_info->>'bt_amt')::numeric, 0) = 0 then (a.invoice_amt -  COALESCE(c.tax_amt, 0)) else COALESCE((a.annex_info->>'bt_amt')::numeric, 0)  end  as bt_amt, 
		(COALESCE((a.annex_info->>'tax_amt')::numeric, 0) + COALESCE(c.tax_amt, 0)) as tax_amt, a.branch_id
	From  ar.invoice_control a
	left join tax_tran c on a.invoice_id = c.voucher_id
	inner join ar.customer b on a.customer_id = b.customer_id
	Inner Join ar.salesman d on a.salesman_id = d.salesman_id
	where a.company_id = pcompany_id
		And (a.branch_id = pbranch_id or pbranch_id = 0)
		And (a.customer_id = pcustomer_id or pcustomer_id = 0)
		And a.doc_date between pfrom_date and pto_date
		And a.status = 5
		And (a.salesman_id = psalesman_id or psalesman_id = 0);
        
    -- Include Sales Return Credit/Debit note
    Insert into bt_temp (voucher_id, doc_date, customer_id, customer, salesman_id, salesman_name, invoice_amt, invoice_amt_fc, bt_amt, tax_amt, branch_id)	
    Select a.voucher_id, a.doc_date, a.customer_account_id, b.customer, b.salesman_id, d.salesman_name, 
        case when (a.annex_info->>'dcn_type')::smallint != 1 then -a.debit_amt else a.debit_amt end, a.debit_amt_fc, 
        case when (a.annex_info->>'dcn_type')::smallint != 1 then -COALESCE((a.annex_info->>'items_total_amt')::numeric, 0) else COALESCE((a.annex_info->>'items_total_amt')::numeric, 0) end as bt_amt, 
        case when (a.annex_info->>'dcn_type')::smallint != 1 then -COALESCE((a.annex_info->>'tax_amt')::numeric, 0) else COALESCE((a.annex_info->>'tax_amt')::numeric, 0) end as tax_amt,
        a.branch_id
    From ar.rcpt_control a
    inner join ar.customer b on a.customer_account_id = b.customer_id
    Inner Join ar.salesman d on b.salesman_id = d.salesman_id
    where a.company_id = pcompany_id
        And (a.branch_id = pbranch_id or pbranch_id = 0)
        And (a.customer_account_id = pcustomer_id or pcustomer_id = 0)
        And a.doc_date between pfrom_date and pto_date
        And a.status = 5
        And a.doc_type = 'CN2'
        And (b.salesman_id = psalesman_id or psalesman_id = 0);

    if exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'invoice_control') then
        Insert into bt_temp (voucher_id, doc_date, customer_id, customer, salesman_id, salesman_name, invoice_amt, invoice_amt_fc, bt_amt, tax_amt, branch_id)
        Select a.voucher_id, a.doc_date, a.customer_id, a.customer, a.salesman_id, a.salesman_name, a.gross_amt, a.gross_amt_fc, a.bt_amt, a.tax_amt, a.branch_id
        From  pub.fn_business_turnover(pcompany_id, pbranch_id, psalesman_id, pcustomer_id, pfrom_date, pto_date, 0) a;
    End If;

    if exists (SELECT * FROM information_schema.tables where table_schema='st' And table_name = 'stock_control') then
        Insert into bt_temp (voucher_id, doc_date, customer_id, customer, salesman_id, salesman_name, invoice_amt, invoice_amt_fc, bt_amt, tax_amt, branch_id)
        Select a.voucher_id, a.doc_date, a.customer_id, a.customer, a.salesman_id, a.salesman_name, a.invoice_amt, a.invoice_amt_fc, a.bt_amt, a.tax_amt, a.branch_id
        From  st.fn_business_turnover(pcompany_id, pbranch_id, psalesman_id, pcustomer_id, pfrom_date, pto_date) a;
    End If;


    return query 
    select a.voucher_id, a.doc_date, a.customer_id, a.customer, a.salesman_id, a.salesman_name, a.invoice_amt, a.invoice_amt_fc, a.bt_amt, a.tax_amt, a.branch_id
    from bt_temp a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
-- Function for invoice tran document print report
CREATE OR REPLACE FUNCTION ar.fn_customer_collection(pcompany_id bigint, pbranch_id bigint, paccount_id bigint, pfrom_date date, pto_date date)  
RETURNS TABLE
(
	 voucher_id varchar(50) ,      
	 doc_date date,        
	 customer_id BigInt,      
	 customer varchar(250),
	 branch_id bigint,  
	 branch_name varchar(50),
	 collected Numeric(18,4),       
	 disc_amt Numeric(18,4),      
	 other_exp Numeric(18,4),       
	 write_off_amt Numeric(18,4)
) 
AS
$BODY$
BEGIN	
	DROP TABLE IF EXISTS cust_coll_temp;	
	create temp table cust_coll_temp
	(
		voucher_id varchar(50) ,      
		doc_date date,        
		customer_id BigInt,      
		customer varchar(250),
		branch_id bigint,  
		branch_name varchar(50),
		collected Numeric(18,4),       
		disc_amt Numeric(18,4),      
		other_exp Numeric(18,4),       
		write_off_amt Numeric(18,4)
	);

        insert into cust_coll_temp(voucher_id, doc_date, customer_id, customer, branch_id, branch_name, 
		collected, disc_amt, 
		other_exp, write_off_amt)
	select a.voucher_id, a.doc_date, a.customer_account_id, c.customer, a.branch_id, d.branch_name,
		case when rcpt_type = 0 then sum(b.credit_amt + b.tds_amt) else 0 end as collected_amt, sum(b.write_off_amt) as disc_amt, 
		sum(b.other_exp) as other_exp,  case when rcpt_type = 1 then sum(b.credit_amt + b.tds_amt) else 0 end as write_off_amt
	from ar.rcpt_control a
	inner join ac.rl_pl_alloc b on a.voucher_id = b.voucher_id 
	inner join ar.customer c on a.customer_account_id = c.customer_id
	inner join sys.branch d on a.branch_id = d.branch_id
	where a.status = 5
		And (a.branch_id = pbranch_id or pbranch_id = 0)
		And (a.customer_account_id = paccount_id or paccount_id = 0)
		And a.doc_date between pfrom_date and pto_date
	group by a.voucher_id, a.doc_date, a.customer_account_id, c.customer, a.branch_id, d.branch_name;

 
	return query
	select a.voucher_id, a.doc_date, a.customer_id, a.customer, a.branch_id, a.branch_name, a.collected, a.disc_amt, a.other_exp, a.write_off_amt
	from cust_coll_temp a;
	       
END;
$BODY$
  LANGUAGE plpgsql;

?==?
create or replace function ar.fn_customer_bal_credit_limit(pcompany_id bigint, pbranch_id bigint, pto_date date, pcustomer_id bigint, pvoucher_id varchar(50))
RETURNS TABLE  
(	table_desc varchar(50), 
	branch_id bigint,
	doc_date date,
	ro_id varchar(50),
	ro_tran_id varchar(50),
	ro_tran_amt  numeric(18,4),
	ro_tran_amt_fc  numeric(18,4),
	invoice_id varchar(50),
	inv_amt numeric(18,4),
	inv_amt_fc numeric(18,4),
	balance_credit numeric(18,4),
	disputed_inv_id varchar(50),
	disputed_amt numeric(18,4),
	customer_id bigint,
	days_since_inv bigint	
)
AS
$BODY$ 
Begin	
	-- This function is used by the Posting Trigger to get information on the Supplier Payment (PYMT)
	DROP TABLE IF EXISTS cust_credit_bal;	
	create temp TABLE  cust_credit_bal
	(	
		table_desc varchar(50), 
		branch_id bigint,
		doc_date date,
		ro_id varchar(50),
		ro_tran_id varchar(50),
		ro_tran_amt  numeric(18,4),
		ro_tran_amt_fc  numeric(18,4),
		invoice_id varchar(50),
		inv_amt numeric(18,4),
		inv_amt_fc numeric(18,4),
		balance_credit numeric(18,4),
		disputed_inv_id varchar(50),
		disputed_amt numeric(18,4),
		customer_id bigint,
		days_since_inv bigint
	);

    Insert into cust_credit_bal(table_desc, branch_id, doc_date, ro_id, ro_tran_id, ro_tran_amt, ro_tran_amt_fc, invoice_id, inv_amt, inv_amt_fc, balance_credit,
                disputed_inv_id, disputed_amt, customer_id, days_since_inv)
	Select 'STMT', COALESCE(a.branch_id, -1), COALESCE(a.doc_date, '1970-01-01'), COALESCE(a.voucher_id, ''), '', 0, 0, '', 0, 0, COALESCE(a.balance, 0),
    	'', 0, a.account_id, 0 
	From ar.fn_stmt_of_ac_br_report(pcompany_id, pbranch_id, pcustomer_id, pto_date, 0) a
	inner join ar.customer b on a.account_id = b.customer_id;

	if exists (SELECT * FROM information_schema.tables where table_schema='sd' And table_name = 'dmr_control') then	
    	Insert into cust_credit_bal(table_desc, branch_id, doc_date, ro_id, ro_tran_id, ro_tran_amt, ro_tran_amt_fc, invoice_id, inv_amt, inv_amt_fc, balance_credit,
                disputed_inv_id, disputed_amt, customer_id, days_since_inv)							
		select 'SO', a.branch_id, a.doc_date, a.opportunity_id, b.opportunity_tran_id, b.item_amt, b.item_amt_fc, '', 0, 0, 0, 
			'', 0, a.customer_id, 0
		from crm.opportunity_control a
		inner join crm.opportunity_tran b on a.opportunity_id = b.opportunity_id
		inner join ar.customer c on a.customer_id = c.customer_id
		where a.status = 5
			And (a.customer_id = pcustomer_id or pcustomer_id = 0)
			And a.is_close_date = false
			And b.opportunity_id not in (Select (a.annex_info->'sd_info'->>'so_id')::varchar from st.stock_control a
										where a.status = 5 
										And (a.annex_info->'sd_info'->>'so_id')::varchar != '');
	Elseif exists (SELECT * FROM information_schema.tables where table_schema='crm' And table_name = 'opportunity_control') then
    	Insert into cust_credit_bal(table_desc, branch_id, doc_date, ro_id, ro_tran_id, ro_tran_amt, ro_tran_amt_fc, invoice_id, inv_amt, inv_amt_fc, balance_credit,
                disputed_inv_id, disputed_amt, customer_id, days_since_inv)							
		select 'Opportunity Control', a.branch_id, a.doc_date, a.opportunity_id, b.opportunity_tran_id, b.item_amt, b.item_amt_fc, '', 0, 0, 0, 
			'', 0, a.customer_id, 0
		from crm.opportunity_control a
		inner join crm.opportunity_tran b on a.opportunity_id = b.opportunity_id
		inner join ar.customer c on a.customer_id = c.customer_id
		where a.status = 5
			And (a.customer_id = pcustomer_id or pcustomer_id = 0)
			And a.is_close_date = false
			And b.opportunity_id not in (Select b.reference_id from st.stock_control a
										inner join st.stock_tran b on a.stock_id = b.stock_id
										where a.status = 5 
										And b.reference_id != ''
										And a.doc_type = 'SIV');
	End If;
           
        -- Get details from CRM Opportunity for estimate
	if exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'invoice_control') then
		Insert into cust_credit_bal(table_desc, branch_id, doc_date, ro_id, ro_tran_id, ro_tran_amt, ro_tran_amt_fc, invoice_id, inv_amt, inv_amt_fc, balance_credit,
					disputed_inv_id, disputed_amt, customer_id, days_since_inv)
		select 'Estimate', a.branch_id, a.doc_date, a.opportunity_id, b.estimate_tran_id, b.item_amt, b.item_amt_fc, '', 0, 0, 0,
			'', 0, a.customer_id, 0
		From crm.opportunity_control a
		inner join crm.estimate_tran b on a.opportunity_id = b.opportunity_id
		Where (a.status = 5)
			And (a.customer_id = pcustomer_id or pcustomer_id =0)
                        And a.is_close_date = false
			And b.estimate_tran_id not in (Select b.ro_tran_id from pub.invoice_control a
                                                            inner join pub.invoice_ro_tran b on a.voucher_id = b.voucher_id
                                                            where a.status = 5);
	End If;

	-- Get details from RO control
	if exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'ro_control') then
		Insert into cust_credit_bal(table_desc, branch_id, doc_date, ro_id, ro_tran_id, ro_tran_amt, ro_tran_amt_fc, invoice_id, inv_amt, inv_amt_fc, balance_credit,
					disputed_inv_id, disputed_amt, customer_id, days_since_inv)
		select a.table_desc, a.branch_id, a.doc_date, a.ro_id, a.ro_tran_id, a.ro_tran_amt, a.ro_tran_amt_fc, a.invoice_id, a.inv_amt, a.inv_amt_fc, a.inv_amt,
			a.disputed_inv_id, a.disputed_amt, a.customer_id, a.days_since_inv
		From pub.fn_customer_bal_credit_limit(pcompany_id, pbranch_id, pto_date, pcustomer_id, pvoucher_id) a;
	End If;
	
	return query 
	select a.table_desc, a.branch_id, a.doc_date, a.ro_id, a.ro_tran_id, a.ro_tran_amt, a.ro_tran_amt_fc, a.invoice_id, a.inv_amt, a.inv_amt_fc, a.balance_credit,
				a.disputed_inv_id, a.disputed_amt, a.customer_id, a.days_since_inv
	from cust_credit_bal a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ar.fn_tds_withheld_report(IN pcompany_id bigint, pbranch_id bigint, pcustomer_id bigint, pfrom_date date, pto_date date)
  RETURNS TABLE
  (
    voucher_id varchar(50),
    doc_date date,
    customer_id bigint,
    customer varchar(250),
    invoice_id varchar(50),
    tds_amt numeric(18,4),
    credit_amt numeric(18,4)
 )
AS
 $BODY$
 Begin 
	DROP TABLE IF EXISTS tds_withheld_temp;
	CREATE temp TABLE tds_withheld_temp
	(            
	    voucher_id varchar(50),
	    doc_date date,
	    customer_id bigint,
	    customer varchar(250),
	    invoice_id varchar(50),
	    tds_amt numeric(18,4),
	    credit_amt numeric(18,4)
	);
        insert into tds_withheld_temp(voucher_id, doc_date, customer_id, customer, invoice_id, tds_amt, credit_amt)
	select a.voucher_id, a.doc_date, a.customer_account_id, d.customer, c.voucher_id, b.tds_amt, b.credit_amt
	from ar.rcpt_control a
	inner join ac.rl_pl_alloc b on a.voucher_id = b.voucher_id
	inner join ac.rl_pl c on b.rl_pl_id = c.rl_pl_id
	inner join ar.customer d on a.customer_account_id = d.customer_id
	Where (a.customer_account_id = pcustomer_id or pcustomer_id = 0)
		And (a.branch_id In (Select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_id = 0)
		AND a.company_id = pcompany_id
		And a.doc_date between pfrom_date and pto_date 		
		AND a.status=5
		And b.tds_amt != 0;

        Return query
	select a.voucher_id, a.doc_date, a.customer_id, a.customer, a.invoice_id, a.tds_amt, a.credit_amt
	from tds_withheld_temp a;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ar.fn_pending_inv_for_dispatch(IN pcompany_id bigint, IN pbranch_id bigint, IN pcustomer_id bigint, IN pfrom_date date, IN pto_date date)
RETURNS TABLE
(   
    inv_type varchar(1),
    doc_date date, 
    voucher_id varchar(50), 
    invoice_amt numeric(18,4), 
    invoice_amt_fc numeric(18,4), 
    customer_id bigint,
    customer varchar(250),
    salesman_id bigint,
    salesman_name varchar(50),
    is_dispatched boolean,
    dispatched_date date,
    dispatch_method smallint,
    dispatch_remark character varying
) 
AS
$BODY$
Begin	 
	DROP TABLE IF EXISTS pending_inv_temp;	
	create temp TABLE pending_inv_temp
	(	
	    inv_type varchar(1),
	    doc_date date, 
	    voucher_id varchar(50), 
	    invoice_amt numeric(18,4), 
	    invoice_amt_fc numeric(18,4), 
	    customer_id bigint,
	    customer varchar(250),
	    salesman_id bigint,
	    salesman_name varchar(50),
	    is_dispatched boolean,
	    dispatched_date date,
	    dispatch_method smallint,
    	dispatch_remark character varying
	);

	Insert into pending_inv_temp(inv_type, doc_date, voucher_id, invoice_amt, invoice_amt_fc, customer_id, customer, 
			salesman_id, salesman_name, is_dispatched, dispatched_date, 
            dispatch_method, dispatch_remark)
	Select 'A', a.doc_date, a.invoice_id, a.invoice_amt, a.invoice_amt_fc, a.customer_id, b.customer, 
		a.salesman_id, c.salesman_name, a.is_dispatched, case when a.is_dispatched then a.dispatched_date else '1970-01-01' end as dispatched_date, 
        a.dispatch_method, a.dispatch_remark
	from ar.invoice_control a
	inner join ar.customer b on a.customer_id = b.customer_id
	inner join ar.salesman c on a.salesman_id = c.salesman_id
	where (a.customer_id = pcustomer_id or pcustomer_id = 0)
		And a.doc_date between pfrom_date and pto_date
		And (a.branch_id=pbranch_id or pbranch_id=0)
		And a.company_id=pcompany_id
		And a.status = 5
		And a.is_dispatched = false;

	if exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'ro_control') then	
		Insert into pending_inv_temp(inv_type, doc_date, voucher_id, invoice_amt, invoice_amt_fc, customer_id, customer, 
			salesman_id, salesman_name, is_dispatched, dispatched_date, dispatch_method, dispatch_remark)
		Select 'B', a.doc_date, a.voucher_id, a.net_debit_amt, a.net_debit_amt_fc, a.customer_id, b.customer, 
			a.salesman_id, c.salesman_name, a.is_dispatched, case when a.is_dispatched then a.dispatched_date else '1970-01-01' end as dispatched_date, 
            a.dispatch_method, a.dispatch_remark
		from pub.invoice_control a
		inner join ar.customer b on a.customer_id = b.customer_id
		inner join ar.salesman c on a.salesman_id = c.salesman_id
		inner join pub.media_type d on a.media_type_id = d.media_type_id
		where (a.customer_id = pcustomer_id or pcustomer_id = 0)
			And a.doc_date between pfrom_date and pto_date
			And (a.branch_id=pbranch_id or pbranch_id=0)
			And a.company_id=pcompany_id
			And a.status = 5
			And a.is_dispatched = false;
	End If;

	
	return query
	Select a.inv_type, a.doc_date, a.voucher_id, a.invoice_amt, a.invoice_amt_fc, a.customer_id, a.customer, 
		a.salesman_id, a.salesman_name, a.is_dispatched, a.dispatched_date, a.dispatch_method, a.dispatch_remark
	from pending_inv_temp a;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ar.sp_tds_reco_collection
(	pcompany_id bigint,
	pbranch_id bigint,
	pcustomer_id bigint,
	preconciled bigint,
	pas_on date)
Returns Table
(	
 	doc_date date, 
 	voucher_id character varying, 
 	customer character varying,
 	customer_id bigint,
 	tds_amt numeric, 
 	tds_amt_fc numeric, 
 	reconciled boolean, 
 	reco_date date,
    pan character varying,
    tan character varying)
AS 
$BODY$
Declare 
	vYearBegin date;
Begin
    -- Parameter preconciled values
    -- 0 - Unreconciled
    -- 1 - Reconciled
    -- 2 - All
    
    -- Fetch Year Begins based on AsOn Date
    Select a.year_begin into vYearBegin From sys.finyear a
    Where pas_on between a.year_begin and a.year_end;

	-- Generate Data
    Return Query
    With brc_temp (doc_date, voucher_id, customer, customer_id, tds_amt, tds_amt_fc, reconciled, reco_date)
    As
    (	Select a.doc_date, a.voucher_id, b.customer, a.customer_id, a.tds_amt, a.tds_amt_fc, a.reconciled, 
            Case When a.reconciled Then a.reco_date Else '1970-01-01' End As reco_date,
     		COALESCE((b.annex_info->'tax_info'->>'pan')::varchar, '') as pan,
            COALESCE((b.annex_info->'tax_info'->>'tan')::varchar, '') as tan
        From ar.tds_reconciled a
        inner join ar.customer b on a.customer_id = b.customer_id
        Where Case 
                When preconciled = 0 Then 
                    (a.reconciled = false)
                When preconciled = 1 Then
                    (a.reconciled=true And a.reco_date between vYearBegin and pas_on)
                When preconciled = 2 Then
                    (a.doc_date between vYearBegin and pas_on)
                End
            And (a.customer_id=pcustomer_id or pcustomer_id = 0)
            And a.doc_date <= pas_on
            And (a.branch_id=pbranch_id or pbranch_id=0)
            And a.company_id=pcompany_id
	)
	Select a.doc_date, a.voucher_id, a.customer, a.customer_id, a.tds_amt, a.tds_amt_fc, a.reconciled, a.reco_date, a.pan, a.tan
	From brc_temp a
    Order By a.doc_date, a.voucher_id;
END
$BODY$
Language plpgsql;

?==?
Create or replace function ar.fn_customer_overdue_with_adv(pcompany_id bigint, pbranch_id bigint, paccount_id bigint, 
        pto_date date, pvoucher_id varchar(50), pdc varchar(1), pcon_account_id bigint)
RETURNS TABLE  
(	
	account_id bigint,
	account_head varchar(250),
        con_account_id bigint,
        con_account_head varchar(250),
	voucher_id varchar(50),
	doc_date date,
	fc_type_id bigint,
	fc_type varchar(20),
	overdue_days smallint,
	due_date date,
	overdue numeric(18,4),
	overdue_fc numeric(18,4),
	not_due numeric(18,4),
	not_due_fc numeric(18,4),
	branch_id bigint,
	adv_amt numeric(18,4),
	adv_amt_fc numeric(18,4)
)
AS
$BODY$ 
	
Begin	
	-- This function is used by the Posting Trigger to get information on the Supplier Payment (PYMT)
	DROP TABLE IF EXISTS cust_overdue_temp;	
	create temp TABLE  cust_overdue_temp
	(	
		account_id bigint,
		account_head varchar(250),
                con_account_id bigint,
                con_account_head varchar(250),
		voucher_id varchar(50),
		doc_date date,
		fc_type_id bigint,
		fc_type varchar(20),
		overdue_days smallint,
		due_date date,
		overdue numeric(18,4),
		overdue_fc numeric(18,4),
		not_due numeric(18,4),
		not_due_fc numeric(18,4),
		branch_id bigint,
		adv_amt numeric(18,4),
		adv_amt_fc numeric(18,4)
	);
	-- Invoice
	Insert into cust_overdue_temp(account_id, account_head, con_account_id, con_account_head, 
                                    voucher_id, doc_date, fc_type_id, fc_type, overdue_days, 
                                    due_date, overdue, overdue_fc, not_due, not_due_fc,
                                    branch_id, adv_amt, adv_amt_fc)
	select a.account_id, b.account_head, c.control_account_id as con_account_id, d.account_head as con_account_head,
                a.voucher_id, a.doc_date, a.fc_type_id, a.fc_type, 
		DATE_PART('day', pto_date::timestamp - a.due_date::timestamp), -- will return -ve for not due
		a.due_date, 
                case when a.due_date < pto_date then sum(a.balance) else 0 end as overdue, 
                case when a.due_date < pto_date then sum(a.balance_fc) else 0 end as overdue_fc, 
                case when a.due_date >= pto_date then sum(a.balance) else 0 end as not_due, 
                case when a.due_date >= pto_date then sum(a.balance_fc) else 0 end as not_due_fc,
                a.branch_id, 0, 0
	from ar.fn_pending_inv(pcompany_id, pbranch_id, paccount_id, pto_date, pvoucher_id, pdc, 5::smallint) a
	inner Join ac.account_head b on a.account_id = b.account_id
        inner join ar.customer c on a.account_id=c.customer_id
        inner Join ac.account_head d on c.control_account_id=d.account_id
	group by a.account_id, b.account_head, c.control_account_id, d.account_head, a.voucher_id, a.doc_date, a.fc_type_id, a.fc_type, a.due_date, a.branch_id;

	-- Advances
	Insert into cust_overdue_temp(account_id, account_head, con_account_id, con_account_head, 
                                  voucher_id, doc_date, fc_type_id, fc_type, overdue_days, 
                                  due_date, overdue, overdue_fc, not_due, not_due_fc, branch_id, 
                                  adv_amt, adv_amt_fc)
	select a.account_id, b.account_head, c.control_account_id as con_account_id, d.account_head as con_account_head,
    	a.voucher_id, a.doc_date, a.fc_type_id, a.fc_type, 
		DATE_PART('day', pto_date::timestamp - a.due_date::timestamp), -- will return -ve for not due
		a.due_date, 0, 0, 0, 0, a.branch_id, sum(a.balance), sum(a.balance_fc)
	from ar.fn_pending_inv(pcompany_id, pbranch_id, paccount_id, pto_date, pvoucher_id, 'C', 5::smallint) a
	inner Join ac.account_head b on a.account_id = b.account_id
        inner join ar.customer c on a.account_id=c.customer_id
        inner Join ac.account_head d on c.control_account_id=d.account_id
	group by a.account_id, b.account_head, c.control_account_id, d.account_head, a.voucher_id, a.doc_date, a.fc_type_id, a.fc_type, a.due_date, a.branch_id;
	
	return query 
	select a.account_id, a.account_head, a.con_account_id, a.con_account_head, a.voucher_id, 
    	   a.doc_date, a.fc_type_id, a.fc_type, a.overdue_days, a.due_date, a.overdue, a.overdue_fc, 
           a.not_due, a.not_due_fc, a.branch_id, a.adv_amt, a.adv_amt_fc
	from cust_overdue_temp a
        where (a.con_account_id=pcon_account_id or pcon_account_id=-99)   
	order by a.account_head;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE FUNCTION ar.fn_gst_inv_tran_print(In pvoucher_id varchar)  
RETURNS TABLE
(
	invoice_id character varying, 
	invoice_tran_id character varying, 
	sl_no smallint, 
	account_id bigint, 
	account_head character varying,
	credit_amt numeric, 
	credit_amt_fc numeric, 
	description character varying,
	bt_amt numeric(18,4),
	hsn_sc_code varchar(8),	
	sgst_pcnt Numeric(5,2),
	sgst_amt Numeric(18,2),
	cgst_pcnt Numeric(5,2),
	cgst_amt Numeric(18,2),
	igst_pcnt Numeric(5,2),
	igst_amt Numeric(18,2),
	cess_pcnt Numeric(5,2),
	cess_amt Numeric(18,2) 
) AS
$BODY$
BEGIN	
	return query
	select 	a.invoice_id, a.invoice_tran_id, a.sl_no, a.account_id, b.account_head, a.credit_amt, a.credit_amt_fc, a.description,
		c.bt_amt, c.hsn_sc_code, c.sgst_pcnt, c.sgst_amt, c.cgst_pcnt, c.cgst_amt, c.igst_pcnt, c.igst_amt, c.cess_pcnt, c.cess_amt		
	from ar.invoice_tran a
	inner join ac.account_head b on a.account_id = b.account_id
	inner join tx.gst_tax_tran c on a.invoice_tran_id = c.gst_tax_tran_id
	where a.invoice_id = pvoucher_id
	order by a.sl_no;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ar.fn_gst_inv_print(IN pvoucher_id character varying, IN pcp_option smallint)
RETURNS TABLE
(	cp_id bigint, cp_desc Character Varying, invoice_id character varying, doc_type character varying, branch_id bigint, 
        doc_date date, fc_type_id bigint,fc_type character varying, exch_rate numeric, status smallint, customer_id bigint, customer character varying,
        income_type_id bigint, income_type character varying, invoice_action character varying, narration character varying, 
        amt_in_words character varying, amt_in_words_fc character varying, remarks character varying, invoice_amt numeric, invoice_amt_fc numeric, 
        po_no character varying, po_date date, entered_by character varying, posted_by character varying, due_date date, 
        invoice_address text, customer_gst_state character varying, customer_gstin character varying,
        bt_amt numeric(18,4), bt_amt_fc numeric(18,4), tax_amt numeric(18,4), tax_amt_fc numeric(18,4),
        advance_amt numeric(18,4), advance_amt_fc numeric(18,4),
	round_off_amt numeric(18,4), round_off_amt_fc numeric(18,4)
)
AS
$BODY$
	DEclare vCalcType smallint = 1; vDueDate date; vPayDays smallint = 0; vDocDate date;
BEGIN	
	DROP TABLE IF EXISTS invoice_report_temp;	
	create temp table invoice_report_temp
	(	cp_id bigint,
		cp_desc Character Varying,
		invoice_id varchar(50),
		doc_type varchar(4),
		branch_id bigint,
		doc_date date,
		fc_type_id bigint,
		fc_type varchar(20),
		exch_rate numeric(18,6),
		status smallint,
		customer_id bigint,
		customer varchar(250),
		income_type_id bigint,
		income_type varchar(250),
		invoice_action varchar(50),
		narration varchar(500),
		amt_in_words varchar(250),
		amt_in_words_fc varchar(250),
		remarks varchar(500),
		invoice_amt numeric(18,4),
		invoice_amt_fc numeric(18,4),
		po_no varchar(50),
		po_date date,
		entered_by varchar(100), 
		posted_by varchar(100),
		pay_term_id bigint,
		due_date date,
		invoice_address text ,
		customer_gst_state character varying,
		customer_gstin character varying,
		bt_amt numeric(18,4),
		bt_amt_fc numeric(18,4),
		tax_amt numeric(18,4),
		tax_amt_fc numeric(18,4),
		advance_amt numeric(18,4),
		advance_amt_fc numeric(18,4),
		round_off_amt numeric(18,4),
		round_off_amt_fc numeric(18,4)
	);

	If pcp_option = 1 Then
		Insert into invoice_report_temp(cp_id, cp_desc, invoice_id, doc_type, branch_id, doc_date, fc_type_id, fc_type, exch_rate, status, customer_id, customer, 
			income_type_id, income_type, invoice_action, narration, amt_in_words, amt_in_words_fc, remarks, invoice_amt, invoice_amt_fc, 
			po_no, po_date, entered_by, posted_by, pay_term_id, due_date, 
			invoice_address, customer_gst_state, customer_gstin, 
			bt_amt, bt_amt_fc, tax_amt, tax_amt_fc,
			advance_amt, advance_amt_fc,
			round_off_amt, round_off_amt_fc)
		select 1, 'Original For Recipient',	a.invoice_id, a.doc_type, a.branch_id, a.doc_date, a.fc_type_id, g.fc_type, a.exch_rate, a.status, a.customer_id, c.customer_name,
			a.income_type_id, d.income_type_name, '' as invoice_action, a.narration, a.amt_in_words, a.amt_in_words_fc, a.remarks, a.invoice_amt, a.invoice_amt_fc,  
			a.po_no, a.po_date, e.entered_by, e.posted_by, c.pay_term_id, a.doc_date + (cast(h.pay_days as varchar) || ' days')::interval, 
			a.invoice_address, i.gst_state_code || ' - ' || i.state_name, (a.annex_info->'gst_output_info'->>'customer_gstin')::varchar, 
			(a.annex_info->>'bt_amt')::numeric, (a.annex_info->>'bt_amt_fc')::numeric, (a.annex_info->>'tax_amt')::numeric, (a.annex_info->>'tax_amt_fc')::numeric,
			(a.annex_info->>'advance_amt')::numeric, (a.annex_info->>'advance_amt_fc')::numeric,
			COALESCE((a.annex_info->>'round_off_amt')::numeric, 0), COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0)
		from ar.invoice_control a
		inner join ar.customer c on a.customer_id = c.customer_id
		inner join ar.income_type d on a.income_type_id = d.income_type_id
		inner join sys.doc_es e on a.invoice_id = e.voucher_id
		inner join ac.fc_type g on a.fc_type_id = g.fc_type_id
		Inner join ac.pay_term h on c.pay_term_id = h.pay_term_id
		inner join tx.gst_state i on (a.annex_info->'gst_output_info'->>'customer_state_id')::bigint = i.gst_state_id
		where a.invoice_id = pvoucher_id
                Union All
                select 2, 'Duplicate For Supplier',	a.invoice_id, a.doc_type, a.branch_id, a.doc_date, a.fc_type_id, g.fc_type, a.exch_rate, a.status, a.customer_id, c.customer_name,
			a.income_type_id, d.income_type_name, '' as invoice_action, a.narration, a.amt_in_words, a.amt_in_words_fc, a.remarks, a.invoice_amt, a.invoice_amt_fc,  
			a.po_no, a.po_date, e.entered_by, e.posted_by, c.pay_term_id, a.doc_date + (cast(h.pay_days as varchar) || ' days')::interval, 
			a.invoice_address, i.gst_state_code || ' - ' || i.state_name, (a.annex_info->'gst_output_info'->>'customer_gstin')::varchar, 
			(a.annex_info->>'bt_amt')::numeric, (a.annex_info->>'bt_amt_fc')::numeric, (a.annex_info->>'tax_amt')::numeric, (a.annex_info->>'tax_amt_fc')::numeric,
			(a.annex_info->>'advance_amt')::numeric, (a.annex_info->>'advance_amt_fc')::numeric,
			COALESCE((a.annex_info->>'round_off_amt')::numeric, 0), COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0)
		from ar.invoice_control a
		inner join ar.customer c on a.customer_id = c.customer_id
		inner join ar.income_type d on a.income_type_id = d.income_type_id
		inner join sys.doc_es e on a.invoice_id = e.voucher_id
		inner join sys.address f on c.address_id = f.address_id
		inner join ac.fc_type g on a.fc_type_id = g.fc_type_id
		Inner join ac.pay_term h on c.pay_term_id = h.pay_term_id
		inner join tx.gst_state i on (a.annex_info->'gst_output_info'->>'customer_state_id')::bigint = i.gst_state_id
		where a.invoice_id = pvoucher_id;
    ElseIf pcp_option = 2 Then
    	Insert into invoice_report_temp(cp_id, cp_desc, invoice_id, doc_type, branch_id, doc_date, fc_type_id, fc_type, exch_rate, status, customer_id, customer,
			income_type_id, income_type, invoice_action, narration, amt_in_words, amt_in_words_fc, remarks, invoice_amt, invoice_amt_fc, 
			po_no, po_date, entered_by, posted_by, pay_term_id, due_date, 
			invoice_address, customer_gst_state, customer_gstin, 
			bt_amt, bt_amt_fc, tax_amt, tax_amt_fc,
			advance_amt, advance_amt_fc, 
			round_off_amt, round_off_amt_fc)
		select 1, 'Duplicate For Supplier',	a.invoice_id, a.doc_type, a.branch_id, a.doc_date, a.fc_type_id, g.fc_type, a.exch_rate, a.status, a.customer_id, c.customer_name,
			a.income_type_id, d.income_type_name, '' as invoice_action, a.narration, a.amt_in_words, a.amt_in_words_fc, a.remarks, a.invoice_amt, a.invoice_amt_fc,  
			a.po_no, a.po_date, e.entered_by, e.posted_by, c.pay_term_id, a.doc_date + (cast(h.pay_days as varchar) || ' days')::interval, 
			a.invoice_address, i.gst_state_code || ' - ' || i.state_name, (a.annex_info->'gst_output_info'->>'customer_gstin')::varchar, 
			(a.annex_info->>'bt_amt')::numeric, (a.annex_info->>'bt_amt_fc')::numeric, (a.annex_info->>'tax_amt')::numeric, (a.annex_info->>'tax_amt_fc')::numeric,
			(a.annex_info->>'advance_amt')::numeric, (a.annex_info->>'advance_amt_fc')::numeric,
			COALESCE((a.annex_info->>'round_off_amt')::numeric, 0), COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0)
		from ar.invoice_control a
		inner join ar.customer c on a.customer_id = c.customer_id
		inner join ar.income_type d on a.income_type_id = d.income_type_id
		inner join sys.doc_es e on a.invoice_id = e.voucher_id
		inner join sys.address f on c.address_id = f.address_id
		inner join ac.fc_type g on a.fc_type_id = g.fc_type_id
		Inner join ac.pay_term h on c.pay_term_id = h.pay_term_id
		inner join tx.gst_state i on (a.annex_info->'gst_output_info'->>'customer_state_id')::bigint = i.gst_state_id
		where a.invoice_id = pvoucher_id;
	End If;

	-- Determine Due Date
	select calc_type, pay_days into vCalcType, vPayDays from ac.pay_term
	where pay_term_id in (Select pay_term_id from invoice_report_temp limit 1);

	Select a.doc_date into vDocDate
	From ar.invoice_control a
	where a.invoice_id = pvoucher_id;
	
	
	If vCalcType = 0 Then -- End of month			
		SELECT (date_trunc('MONTH', vDocDate) + INTERVAL '1 MONTH - 1 day')::date + (cast(vPayDays as varchar) || ' days')::interval into vDueDate;
	Else		
		SELECT vDocDate + (cast(vPayDays as varchar) || ' days')::interval into vDueDate;
	End If;
	
	return query
	select a.cp_id, a.cp_desc, a.invoice_id, a.doc_type, a.branch_id, a.doc_date, a.fc_type_id, a.fc_type, a.exch_rate, a.status, a.customer_id, a.customer, 
		a.income_type_id, a.income_type, a.invoice_action, a.narration, a.amt_in_words, a.amt_in_words_fc, a.remarks, a.invoice_amt, a.invoice_amt_fc, 
		a.po_no, a.po_date, a.entered_by, a.posted_by, vDueDate, a.invoice_address, a.customer_gst_state, a.customer_gstin, a.bt_amt, a.bt_amt_fc,
		a.tax_amt, a.tax_amt_fc, a.advance_amt, a.advance_amt_fc, a.round_off_amt, a.round_off_amt_fc
	from invoice_report_temp a;
	       
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ar.fn_cust_refund_tran_print(In pvoucher_id varchar(50))  
RETURNS TABLE
(
	branch_id bigint,
	voucher_id varchar(50),
	vch_tran_id varchar(50),
	doc_date date,
	account_id bigint,
	exch_rate numeric(18,6),
	debit_amt numeric(18,4),
	debit_amt_fc numeric(18,4),
	net_debit_amt numeric(18,4),
	net_debit_amt_fc numeric(18,4),
	invoice_id varchar(50)
) 
AS
$BODY$
BEGIN	
	return query
	select 	a.branch_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id, a.exch_rate, 
		a.debit_amt, a.debit_amt_fc, a.net_debit_amt, a.net_debit_amt_fc, b.voucher_id as invoice_id
	from ac.rl_pl_alloc a
	inner join ac.rl_pl b on a.rl_pl_id = b.rl_pl_id
	where a.voucher_id = pvoucher_id;
	       
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ar.fn_gst_inv_tax_print(IN pvoucher_id character varying, IN ptran_group character varying)
RETURNS TABLE
(	inv_id character varying,
	item_taxable_amt numeric, 
 	gst_rate_id BigInt,
	sgst_pcnt numeric, 
 	sgst_amt numeric,
 	cgst_pcnt numeric, 
 	cgst_amt numeric,
 	igst_pcnt numeric, 
 	igst_amt numeric,
 	cess_pcnt numeric, 
 	cess_amt numeric,
	tax_amt numeric,
	item_amt numeric,
	hsn_sc_code character varying) 
AS
$BODY$
Begin
	Return Query
	Select a.voucher_id, Sum(a.bt_amt), a.gst_rate_id, 
    	min(a.sgst_pcnt), Sum(a.sgst_amt),
        min(a.cgst_pcnt), Sum(a.cgst_amt),
        min(a.igst_pcnt), Sum(a.igst_amt),
        min(a.cess_pcnt), Sum(a.cess_amt),
        Sum(a.sgst_amt+a.cgst_amt+a.igst_amt+a.cess_amt),
        Sum(a.bt_amt+a.sgst_amt+a.cgst_amt+a.igst_amt+a.cess_amt), a.hsn_sc_code
	From tx.gst_tax_tran a
	Where a.voucher_id=pvoucher_id and (a.tran_group = ptran_group or ptran_group = '')
	Group by a.voucher_id, a.gst_rate_id, a.hsn_sc_code;
End
$BODY$
  LANGUAGE plpgsql;

?==?
create OR REPLACE function ar.fn_pending_inv(pcompany_id bigint, pbranch_id bigint, paccount_id bigint, pto_date date, 
	pvoucher_id varchar(50), pdc varchar(1), pstatus smallint default 0)
RETURNS TABLE  
(	rl_pl_id uuid, 
	voucher_id varchar(50), 
	vch_tran_id varchar(50),
	doc_date date,
	account_id bigint,
	balance numeric(18,4),
	balance_fc numeric(18,4),
	fc_type_id bigint,
	fc_type varchar(20),
	branch_id bigint,
	narration varchar(500),
	due_date date
)
AS
$BODY$ 
Begin	
	DROP TABLE IF EXISTS pending_inv;	
	create temp TABLE  pending_inv
	(	
		rl_pl_id uuid primary key, 
		voucher_id varchar(50), 
		vch_tran_id varchar(50),
		doc_date date,
		account_id bigint,
		balance numeric(18,4),
		balance_fc numeric(18,4),
		fc_type_id bigint,
		fc_type varchar(20),
		branch_id bigint,
		narration varchar(500),
		due_date date
	);

	DROP TABLE IF EXISTS rl_balance;	
	create temp TABLE  rl_balance
	(	
		rl_pl_id uuid primary key,
		balance numeric(18,4),
		balance_fc numeric(18,4)
	);

	Insert into rl_balance(rl_pl_id, balance_fc, balance)
	Select a.rl_pl_id, sum(a.balance_fc) as balance_fc, sum(a.balance) as balance
	From (  select a.rl_pl_id, sum(a.debit_amt_fc)- sum(a.credit_amt_fc) as balance_fc, sum(a.debit_amt)- sum(a.credit_amt) as balance
		From ac.rl_pl a
		where (a.account_id=paccount_id or paccount_id=0)
		Group By a.rl_pl_id
		Union All -- In Alloc, Debits would be heavier and would automatically result in negatives
		select a.rl_pl_id, sum(a.net_debit_amt_fc)- sum(a.net_credit_amt_fc) as settled_fc, 
			sum(a.net_debit_amt)  - sum(a.net_credit_amt) as balance
		From ac.rl_pl_alloc a
		where (a.account_id=paccount_id or paccount_id=0) and a.voucher_id <> pvoucher_id
			And (a.status = pstatus or pstatus = 0)
		Group By a.rl_pl_id
	     ) a
	Group By a.rl_pl_id;

	Insert into pending_inv(rl_pl_id, voucher_id, vch_tran_id, doc_date, account_id, balance, balance_fc, 
		fc_type_id, fc_type, branch_id, narration, due_date)
	Select a.rl_pl_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id, b.balance, b.balance_fc, 
		a.fc_type_id, c.fc_type, a.branch_id, a.narration, a.due_date
	From ac.rl_pl a
	Inner Join rl_balance b on a.rl_pl_id=b.rl_pl_id
	Inner Join ac.fc_type c on a.fc_type_id=c.fc_type_id
	where a.doc_date <= pto_date 
		And (a.account_id=paccount_id or paccount_id=0)
		And (b.balance_fc <>0 or b.balance <> 0)
		And (a.branch_id=pbranch_id or pbranch_id=0);
			
	if pdc='D' then
		-- Remove all advances
		Delete from pending_inv a Where a.balance < 0;
	End If; 
	If pdc = 'C' then
		-- Remove all setellement/receivables
		Delete from pending_inv a
		Where a.balance > 0;

		-- Convert negative advances to positive
		Update pending_inv a
		set balance_fc = a.balance_fc * -1,
		    balance = a.balance * -1;
	End If;

	return query 
	select a.rl_pl_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id, a.balance, a.balance_fc, 
		a.fc_type_id, a.fc_type, a.branch_id, a.narration, a.due_date
	from pending_inv a;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
create or replace function ar.fn_sales_register_report(pcompany_id bigint, pbranch_id bigint, pcustomer_id bigint, pfrom_date date, pto_date date, pgst_state_id bigint)
RETURNS TABLE  
(	voucher_id varchar(50), 
	doc_date date,
	customer_id bigint,
	customer character varying,
	gstin character varying,
	gst_state character varying,
	vat_type_id bigint,
	vat_type_code character varying,
	bt_amt numeric(18,4),
	sgst_amt numeric(18,4),
	cgst_amt numeric(18,4),
	igst_amt numeric(18,4),
	gst_rate numeric(18,4)
)
AS
$BODY$ 
	declare vwalkincust bigint := 0;
Begin	
	Select a.customer_id into vwalkincust from ar.customer a where a.customer ilike 'Walk-in%';

        
	-- This function is used by the Posting Trigger to get information on the Supplier Payment (PYMT)
	DROP TABLE IF EXISTS pur_reg;	
	create temp TABLE  pur_reg
	(	
		voucher_id varchar(50), 
		doc_date date,
		customer_id bigint,
		customer character varying,
		gstin character varying,
		gst_state character varying,
		vat_type_id bigint,
		vat_type_code character varying,
		bt_amt numeric(18,4),
		sgst_amt numeric(18,4),
		cgst_amt numeric(18,4),
		igst_amt numeric(18,4),
		gst_rate numeric(18,4)
	);
		
	Insert into pur_reg (voucher_id, doc_date, customer_id, customer, 
		gstin, 
		gst_state, vat_type_id, vat_type_code,
		bt_amt, sgst_amt, cgst_amt, igst_amt, 
		gst_rate)
	select a.invoice_id, a.doc_date, a.customer_id, b.account_head, 
		(a.annex_info->'gst_output_info'->>'customer_gstin')::varchar as gstin, 
		(d.gst_state_code || '-' || d.state_code) as gst_state, a.vat_type_id, e.short_desc,
		sum(c.bt_amt) as bt_amt, sum(c.sgst_amt) as sgst_amt, sum(c.cgst_amt) as cgst_amt, sum(c.igst_amt) as igst_amt,
		(c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt) as gst_rate
	from ar.invoice_control a
	inner join ac.account_head b on a.customer_id = b.account_id
	Inner join tx.gst_tax_tran c on a.invoice_id = c.voucher_id
	inner join tx.gst_state d on (a.annex_info->'gst_output_info'->>'customer_state_id')::bigint = d.gst_state_id
	Inner join tx.vat_type e on a.vat_type_id = e.vat_type_id
	Where a.company_id = pcompany_id
		And (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
		And a.doc_date between pfrom_date and pto_date
		And ((a.annex_info->'gst_output_info'->>'customer_state_id')::bigint = pgst_state_id or pgst_state_id = 0)
		And (a.customer_id = pcustomer_id or pcustomer_id = 0)		
		And a.status = 5
	Group by a.invoice_id, a.customer_id, b.account_head, d.state_code, d.gst_state_code, 
		a.vat_type_id, e.short_desc, c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt
	Union All	-- Credit Note
	select a.voucher_id, a.doc_date, a.customer_account_id, b.account_head, 
            (a.annex_info->'gst_output_info'->>'customer_gstin')::varchar as gstin,
            (d.gst_state_code || '-' || d.state_code) as gst_state, (a.annex_info->'gst_output_info'->>'vat_type_id')::bigint, e.short_desc,
            case when (a.annex_info->>'dcn_type')::bigint = 1 then sum(c.bt_amt) Else -1 * sum(c.bt_amt) End as bt_amt, 
            case when (a.annex_info->>'dcn_type')::bigint = 1 then sum(c.sgst_amt) Else -1 * sum(c.sgst_amt) End as sgst_amt, 
            case when (a.annex_info->>'dcn_type')::bigint = 1 then sum(c.cgst_amt) Else -1 * sum(c.cgst_amt) End as cgst_amt, 
            case when (a.annex_info->>'dcn_type')::bigint = 1 then sum(c.igst_amt) Else -1 * sum(c.igst_amt) End as igst_amt, 
            (c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt) as gst_rate
        from ar.rcpt_control a
            inner join ac.account_head b on a.customer_account_id = b.account_id
            Inner join tx.gst_tax_tran c on a.voucher_id = c.voucher_id
            inner join tx.gst_state d on (a.annex_info->'gst_output_info'->>'customer_state_id')::bigint = d.gst_state_id
            Inner join tx.vat_type e on (a.annex_info->'gst_output_info'->>'vat_type_id')::bigint  = e.vat_type_id
            where a.company_id = pcompany_id
                    and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
                    And a.doc_date between pfrom_date and pto_date
                    And ((a.annex_info->'gst_output_info'->>'customer_state_id')::bigint = pgst_state_id or pgst_state_id = 0)
                    And (a.customer_account_id = pcustomer_id or pcustomer_id = 0)		
                    And a.status = 5
                    And a.doc_type= 'CN2'
            Group by a.voucher_id, a.account_id, b.account_head, d.state_code, d.gst_state_code, 
                    (a.annex_info->'gst_output_info'->>'vat_type_id')::bigint , e.short_desc, c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt
	Union All	
	select a.stock_id, a.doc_date, a.account_id, b.account_head, 
		(a.annex_info->'gst_output_info'->>'customer_gstin')::varchar as gstin,
		(d.gst_state_code || '-' || d.state_code) as gst_state, a.vat_type_id, e.short_desc,
		case when a.doc_type in ('SI','SIV') then sum(c.bt_amt)	Else -1 * sum(c.bt_amt) End as bt_amt, 
		case when a.doc_type in ('SI','SIV') then sum(c.sgst_amt) Else -1 * sum(c.sgst_amt) End as sgst_amt, 
		case when a.doc_type in ('SI','SIV') then sum(c.cgst_amt) Else -1 * sum(c.cgst_amt) End as cgst_amt, 
		case when a.doc_type in ('SI','SIV') then sum(c.igst_amt) Else -1 * sum(c.igst_amt) End as igst_amt, 
		(c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt) as gst_rate
	from st.stock_control a
	inner join ac.account_head b on a.account_id = b.account_id
	Inner join tx.gst_tax_tran c on a.stock_id = c.voucher_id
 	inner join tx.gst_state d on (a.annex_info->'gst_output_info'->>'customer_state_id')::bigint = d.gst_state_id
	Inner join tx.vat_type e on a.vat_type_id = e.vat_type_id
	where a.company_id = pcompany_id
		and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
		And a.doc_date between pfrom_date and pto_date
		And ((a.annex_info->'gst_output_info'->>'customer_state_id')::bigint = pgst_state_id or pgst_state_id = 0)
		And (a.account_id = pcustomer_id or pcustomer_id = 0)		
		And a.status = 5
		And a.doc_type= Any('{SI,SIV,SR,SRV,SRN}')
	Group by a.stock_id, a.account_id, b.account_head, d.state_code, d.gst_state_code, 
		a.vat_type_id, e.short_desc, c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt
	Union All
	Select case when a.cust_tin != 'N.A.' then a.inv_id else 'DAY-TXN' end as stock_id, a.doc_date, vwalkincust, 'Walk-in Customer', 
		a.cust_tin, 
		(d.gst_state_code || '-' || d.state_code) as gst_state, a.vat_type_id, e.short_desc, 
		case when a.doc_type in ('PI','PIV') then sum(c.bt_amt)	Else -1 * sum(c.bt_amt) End as bt_amt, 
		case when a.doc_type in ('PI','PIV') then sum(c.sgst_amt) Else -1 * sum(c.sgst_amt) End as sgst_amt, 
		case when a.doc_type in ('PI','PIV') then sum(c.cgst_amt) Else -1 * sum(c.cgst_amt) End as cgst_amt, 
		case when a.doc_type in ('PI','PIV') then sum(c.igst_amt) Else -1 * sum(c.igst_amt) End as igst_amt, 
		(c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt) as gst_rate
	From  pos.inv_control a
	Inner join tx.gst_tax_tran c on a.inv_id = c.voucher_id
	inner join tx.gst_state d on (a.annex_info->'gst_output_info'->>'cust_state_id')::bigint = d.gst_state_id
	Inner join tx.vat_type e on a.vat_type_id = e.vat_type_id
	where a.company_id = pcompany_id
		and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
		And a.doc_date between pfrom_date and pto_date
		And ((a.annex_info->'gst_output_info'->>'cust_state_id')::bigint = pgst_state_id or pgst_state_id = 0)		
		And (vwalkincust = pcustomer_id Or pcustomer_id = 0)	
		And a.status = 5
	group by case when a.cust_tin != 'N.A.' then a.inv_id else 'DAY-TXN' end, a.doc_date, a.cust_tin, d.state_code, d.gst_state_code, a.doc_type,
		a.vat_type_id, e.short_desc, c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt;


	if exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'abp_control') then
		Insert into pur_reg (voucher_id, doc_date, customer_id, customer, 
			gstin, gst_state, vat_type_id, vat_type_code,
			bt_amt, sgst_amt, cgst_amt, igst_amt, 
			gst_rate)
		select a.voucher_id, a.doc_date, a.customer_id, b.account_head, 
			(a.annex_info->'gst_output_info'->>'customer_gstin')::varchar as gstin, 
			(d.gst_state_code || '-' || d.state_code) as gst_state, a.vat_type_id, e.short_desc,
			sum(c.bt_amt) as bt_amt, sum(c.sgst_amt) as sgst_amt, sum(c.cgst_amt) as cgst_amt, sum(c.igst_amt) as igst_amt,
			(c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt) as gst_rate
		from pub.invoice_control a
		inner join ac.account_head b on a.customer_id = b.account_id
		Inner join tx.gst_tax_tran c on a.voucher_id = c.voucher_id
		inner join tx.gst_state d on (a.annex_info->'gst_output_info'->>'customer_state_id')::bigint = d.gst_state_id
		Inner join tx.vat_type e on a.vat_type_id = e.vat_type_id
		Where a.company_id = pcompany_id
			and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
			And a.doc_date between pfrom_date and pto_date
			And ((a.annex_info->'gst_output_info'->>'customer_state_id')::bigint = pgst_state_id or pgst_state_id = 0)
			And (a.customer_id = pcustomer_id or pcustomer_id = 0)		
			And a.status = 5
		Group by a.voucher_id, a.customer_id, b.account_head, d.state_code, d.gst_state_code, 
			a.vat_type_id, e.short_desc, c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt;
	End If;
	
	return query 
	select a.voucher_id, a.doc_date, a.customer_id, a.customer, a.gstin, a.gst_state, a.vat_type_id, a.vat_type_code,
		a.bt_amt, a.sgst_amt, a.cgst_amt, a.igst_amt, a.gst_rate
	from pur_reg a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ar.fn_gst_cn_print(IN pvoucher_id character varying, IN pcp_option smallint)
RETURNS TABLE
(	cp_id bigint, cp_desc Character Varying, voucher_id character varying, doc_type character varying, branch_id bigint, 
        doc_date date, fc_type_id bigint,fc_type character varying, exch_rate numeric, status smallint, customer_id bigint, customer character varying,
        narration character varying, amt_in_words character varying, amt_in_words_fc character varying, remarks character varying, 
        cn_amt numeric, cn_amt_fc numeric, entered_by character varying, posted_by character varying,
        customer_address text, customer_gst_state character varying, customer_gstin character varying,
        bt_amt numeric(18,4), bt_amt_fc numeric(18,4), tax_amt numeric(18,4), tax_amt_fc numeric(18,4),
		round_off_amt numeric(18,4), round_off_amt_fc numeric(18,4), origin_inv_id character varying, origin_inv_date date,
     	dnc_type smallint
)
AS
$BODY$
	DEclare vCalcType smallint = 1; vDocDate date;
BEGIN	
	DROP TABLE IF EXISTS invoice_report_temp;	
	create temp table invoice_report_temp
	(	cp_id bigint,
		cp_desc Character Varying,
		voucher_id varchar(50),
		doc_type varchar(4),
		branch_id bigint,
		doc_date date,
		fc_type_id bigint,
		fc_type varchar(20),
		exch_rate numeric(18,6),
		status smallint,
		customer_id bigint,
		customer varchar(250),
		narration varchar(500),
		amt_in_words varchar(250),
		amt_in_words_fc varchar(250),
		remarks varchar(500),
		cn_amt numeric(18,4),
		cn_amt_fc numeric(18,4),
		entered_by varchar(100), 
		posted_by varchar(100),
		customer_address text ,
		customer_gst_state character varying,
		customer_gstin character varying,
		bt_amt numeric(18,4),
		bt_amt_fc numeric(18,4),
		tax_amt numeric(18,4),
		tax_amt_fc numeric(18,4),
		round_off_amt numeric(18,4),
		round_off_amt_fc numeric(18,4),
		origin_inv_id character varying,
		origin_inv_date date,
     	dnc_type smallint
	);

	If pcp_option = 1 Then
		Insert into invoice_report_temp(cp_id, cp_desc, voucher_id, doc_type, branch_id, doc_date, fc_type_id, fc_type, exch_rate, status, 
			customer_id, customer, narration, amt_in_words, amt_in_words_fc, remarks, cn_amt, cn_amt_fc, 
			entered_by, posted_by, 
			customer_address, customer_gst_state, 
			customer_gstin, 
			bt_amt, bt_amt_fc, 
			tax_amt, tax_amt_fc,	
			round_off_amt, round_off_amt_fc,
			origin_inv_id, origin_inv_date, dnc_type)
		select 1, 'Original For Recipient', a.voucher_id, a.doc_type, a.branch_id, a.doc_date, a.fc_type_id, g.fc_type, a.exch_rate, a.status, 
			a.customer_account_id, c.customer_name,	a.narration, a.amt_in_words, a.amt_in_words_fc, a.remarks, a.debit_amt, a.debit_amt_fc,  
			e.entered_by, e.posted_by, 
			(a.annex_info->'gst_output_info'->>'customer_addr')::varchar as customer_addr, i.gst_state_code || ' - ' || i.state_name, 
			(a.annex_info->'gst_output_info'->>'customer_gstin')::varchar, 
			(a.annex_info->>'items_total_amt')::numeric, 0, 
			(a.annex_info->>'tax_amt')::numeric, (a.annex_info->>'tax_amt_fc')::numeric,
			COALESCE((a.annex_info->>'round_off_amt')::numeric, 0), COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0),
			COALESCE((a.annex_info->>'origin_inv_id')::varchar, ''), (a.annex_info->>'origin_inv_date')::date, (a.annex_info->>'dcn_type')::smallint
		from ar.rcpt_control a
		inner join ar.customer c on a.customer_account_id = c.customer_id
		inner join sys.doc_es e on a.voucher_id = e.voucher_id
		inner join ac.fc_type g on a.fc_type_id = g.fc_type_id
		inner join tx.gst_state i on (a.annex_info->'gst_output_info'->>'customer_state_id')::bigint = i.gst_state_id
		where a.voucher_id = pvoucher_id
                Union All
                select 2, 'Duplicate For Supplier', a.voucher_id, a.doc_type, a.branch_id, a.doc_date, a.fc_type_id, g.fc_type, a.exch_rate, a.status, 
			a.customer_account_id, c.customer_name,
			a.narration, a.amt_in_words, a.amt_in_words_fc, a.remarks, a.debit_amt, a.debit_amt_fc,  
			e.entered_by, e.posted_by, 
			(a.annex_info->'gst_output_info'->>'customer_addr')::varchar as customer_addr, i.gst_state_code || ' - ' || i.state_name, 
			(a.annex_info->'gst_output_info'->>'customer_gstin')::varchar, 
			(a.annex_info->>'items_total_amt')::numeric, 0, (a.annex_info->>'tax_amt')::numeric, (a.annex_info->>'tax_amt_fc')::numeric,
			COALESCE((a.annex_info->>'round_off_amt')::numeric, 0), COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0),
			COALESCE((a.annex_info->>'origin_inv_id')::varchar, ''), (a.annex_info->>'origin_inv_date')::date, (a.annex_info->>'dcn_type')::smallint
		from ar.rcpt_control a
		inner join ar.customer c on a.customer_account_id = c.customer_id
		inner join sys.doc_es e on a.voucher_id = e.voucher_id
		inner join ac.fc_type g on a.fc_type_id = g.fc_type_id
		inner join tx.gst_state i on (a.annex_info->'gst_output_info'->>'customer_state_id')::bigint = i.gst_state_id
		where a.voucher_id = pvoucher_id;
	ElseIf pcp_option = 2 Then
		Insert into invoice_report_temp(cp_id, cp_desc, voucher_id, doc_type, branch_id, doc_date, fc_type_id, fc_type, exch_rate, status, 
			customer_id, customer, narration, amt_in_words, amt_in_words_fc, remarks, cn_amt, cn_amt_fc, 
			entered_by, posted_by, 
			customer_address, customer_gst_state, 
			customer_gstin, 
			bt_amt, bt_amt_fc, 
			tax_amt, tax_amt_fc,	
			round_off_amt, round_off_amt_fc,
			origin_inv_id, origin_inv_date, dnc_type)
		select 1, 'Duplicate For Supplier', a.voucher_id, a.doc_type, a.branch_id, a.doc_date, a.fc_type_id, g.fc_type, a.exch_rate, a.status, 
			a.customer_account_id, c.customer_name,
			a.narration, a.amt_in_words, a.amt_in_words_fc, a.remarks, a.debit_amt, a.debit_amt_fc,  
			e.entered_by, e.posted_by, 
			(a.annex_info->'gst_output_info'->>'customer_addr')::varchar as customer_addr, i.gst_state_code || ' - ' || i.state_name, 
			(a.annex_info->'gst_output_info'->>'customer_gstin')::varchar, 
			(a.annex_info->>'items_total_amt')::numeric, 0, (a.annex_info->>'tax_amt')::numeric, (a.annex_info->>'tax_amt_fc')::numeric,
			COALESCE((a.annex_info->>'round_off_amt')::numeric, 0), COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0),
			COALESCE((a.annex_info->>'origin_inv_id')::varchar, ''), (a.annex_info->>'origin_inv_date')::date, (a.annex_info->>'dcn_type')::smallint
		from ar.rcpt_control a
		inner join ar.customer c on a.customer_account_id = c.customer_id
		inner join sys.doc_es e on a.voucher_id = e.voucher_id
		inner join ac.fc_type g on a.fc_type_id = g.fc_type_id
		inner join tx.gst_state i on (a.annex_info->'gst_output_info'->>'customer_state_id')::bigint = i.gst_state_id
		where a.voucher_id = pvoucher_id;
	ElseIf pcp_option = 3 Then
		Insert into invoice_report_temp(cp_id, cp_desc, voucher_id, doc_type, branch_id, doc_date, fc_type_id, fc_type, exch_rate, status, 
			customer_id, customer, narration, amt_in_words, amt_in_words_fc, remarks, cn_amt, cn_amt_fc, 
			entered_by, posted_by, 
			customer_address, customer_gst_state, 
			customer_gstin, 
			bt_amt, bt_amt_fc, 
			tax_amt, tax_amt_fc,	
			round_off_amt, round_off_amt_fc,
			origin_inv_id, origin_inv_date, dnc_type)
		select 1, 'Original For Recipient', a.voucher_id, a.doc_type, a.branch_id, a.doc_date, a.fc_type_id, g.fc_type, a.exch_rate, a.status, 
			a.customer_account_id, c.customer_name,	a.narration, a.amt_in_words, a.amt_in_words_fc, a.remarks, a.debit_amt, a.debit_amt_fc,  
			e.entered_by, e.posted_by, 
			(a.annex_info->'gst_output_info'->>'customer_addr')::varchar as customer_addr, i.gst_state_code || ' - ' || i.state_name, 
			(a.annex_info->'gst_output_info'->>'customer_gstin')::varchar, 
			(a.annex_info->>'items_total_amt')::numeric, 0, 
			(a.annex_info->>'tax_amt')::numeric, (a.annex_info->>'tax_amt_fc')::numeric,
			COALESCE((a.annex_info->>'round_off_amt')::numeric, 0), COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0),
			COALESCE((a.annex_info->>'origin_inv_id')::varchar, ''), (a.annex_info->>'origin_inv_date')::date, (a.annex_info->>'dcn_type')::smallint
		from ar.rcpt_control a
		inner join ar.customer c on a.customer_account_id = c.customer_id
		inner join sys.doc_es e on a.voucher_id = e.voucher_id
		inner join ac.fc_type g on a.fc_type_id = g.fc_type_id
		inner join tx.gst_state i on (a.annex_info->'gst_output_info'->>'customer_state_id')::bigint = i.gst_state_id
		where a.voucher_id = pvoucher_id;
	End If;

	return query
	select a.cp_id, a.cp_desc, a.voucher_id, a.doc_type, a.branch_id, a.doc_date, a.fc_type_id, a.fc_type, a.exch_rate, a.status, a.customer_id, a.customer, 
		a.narration, a.amt_in_words, a.amt_in_words_fc, a.remarks, a.cn_amt, a.cn_amt_fc, 
		a.entered_by, a.posted_by, a.customer_address, a.customer_gst_state, a.customer_gstin, a.bt_amt, a.bt_amt_fc,
		a.tax_amt, a.tax_amt_fc, a.round_off_amt, a.round_off_amt_fc, a.origin_inv_id, a.origin_inv_date, a.dnc_type
	from invoice_report_temp a;
	       
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ar.fn_inv_for_cn(pbranch_id bigint, pcustomer_id bigint, pfrom_date date, pto_date date, In pvoucher_id varchar)  
RETURNS TABLE(
	invoice_id varchar,  
	doc_date date,
	invoice_tran_id varchar,
	sl_no smallint, 
	account_id bigint, 
	account_head varchar,
	invoice_amt numeric, 
	invoice_amt_fc numeric, 
	description varchar,
	customer_state_id bigint,
	customer_gstin varchar,
	customer_addr character varying,
	hsn_sc_id bigint,
	hsn_sc_desc character varying,
	vat_type_id bigint,
        tax_amt numeric
) AS
$BODY$
BEGIN	
    DROP TABLE IF EXISTS inv_temp;	
    create temp table inv_temp
    (
        invoice_id varchar,
        doc_date date, 
        invoice_tran_id varchar, 
        sl_no smallint, 
        account_id bigint, 
        account_head varchar,
        invoice_amt numeric, 
        invoice_amt_fc numeric, 
        description varchar,
        customer_state_id bigint,
        customer_gstin varchar,
        customer_addr character varying,
        hsn_sc_id bigint,
        hsn_sc_desc character varying,
        vat_type_id bigint,
        tax_amt numeric
    );

    insert into inv_temp(invoice_id, doc_date, invoice_tran_id, sl_no, account_id, account_head, invoice_amt, invoice_amt_fc, 
                    description, customer_state_id,  
                    customer_gstin, 
                    customer_addr, vat_type_id, tax_amt)
    select a.invoice_id, a.doc_date, b.invoice_tran_id, b.sl_no, b.account_id, c.account_head, b.credit_amt, b.credit_amt_fc, 
                    b.description, COALESCE((a.annex_info->'gst_output_info'->>'customer_state_id')::bigint, -1), 
                    COALESCE((a.annex_info->'gst_output_info'->>'customer_gstin')::varchar, ''), 
                    a.invoice_address, a.vat_type_id, (d.sgst_amt + d.cgst_amt + d.igst_amt)
    from ar.invoice_control a
    inner join ar.invoice_tran b on a.invoice_id = b.invoice_id
    inner join ac.account_head c on b.account_id = c.account_id
    inner join tx.gst_tax_tran d on b.invoice_tran_id = d.gst_tax_tran_id
    where (a.customer_id=pcustomer_id or pcustomer_id=0)
            And a.branch_id=pbranch_id
            And case when pvoucher_id = '' then a.doc_date between pfrom_date and pto_date 
                    else a.invoice_id = pvoucher_id 
                    End
            And status = 5
    Union All
    select a.as_id, a.doc_date, b.as_tran_id, b.sl_no, e.asset_account_id, c.account_head, b.credit_amt, b.credit_amt_fc, 
            f.asset_name, COALESCE((a.annex_info->'gst_output_info'->>'customer_state_id')::bigint, -1), 
            COALESCE((a.annex_info->'gst_output_info'->>'customer_gstin')::varchar, ''),  
            COALESCE((a.annex_info->'gst_output_info'->>'customer_address')::varchar, ''), 
            COALESCE((a.annex_info->'gst_output_info'->>'vat_type_id')::bigint, -1),
            (d.sgst_amt + d.cgst_amt + d.igst_amt)
    from fa.as_control a
    inner join fa.as_tran b on a.as_id = b.as_id
    inner join fa.asset_class e on a.asset_class_id = e.asset_class_id
    inner join ac.account_head c on e.asset_account_id = c.account_id
    inner join fa.asset_item f on b.asset_item_id = f.asset_item_id
    inner join tx.gst_tax_tran d on b.as_tran_id = d.gst_tax_tran_id
    where (a.customer_id=pcustomer_id or pcustomer_id=0)
        And a.branch_id=pbranch_id
        And case when pvoucher_id = '' then a.doc_date between pfrom_date and pto_date 
            else a.as_id = pvoucher_id 
            End
        And status = 5;

    if exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'invoice_control') then
        insert into inv_temp(invoice_id, doc_date, invoice_tran_id, sl_no, account_id, account_head, invoice_amt, invoice_amt_fc, 
                description, customer_state_id, customer_gstin,
                customer_addr, vat_type_id, tax_amt)
        select a.voucher_id, a.doc_date, b.vch_tran_id, b.sl_no, a.sale_account_id, d.account_head, b.amt, b.amt_fc, 
                b.editions, COALESCE((a.annex_info->'gst_output_info'->>'customer_state_id')::bigint, -1), 
                COALESCE((a.annex_info->'gst_output_info'->>'customer_gstin')::varchar, ''), 
                a.invoice_address, a.vat_type_id, (e.sgst_amt + e.cgst_amt + e.igst_amt)
        from pub.invoice_control a
        inner join pub.invoice_adv_tran b on a.voucher_id = b.voucher_id
        inner join ac.account_head d on a.sale_account_id = d.account_id
        inner join tx.gst_tax_tran e on b.vch_tran_id = e.gst_tax_tran_id
        where (a.customer_id=pcustomer_id or pcustomer_id=0)
                And a.branch_id=pbranch_id
                And case when pvoucher_id = '' then a.doc_date between pfrom_date and pto_date 
                        else a.voucher_id = pvoucher_id 
                        End
                And a.status = 5
        Union All 
        select a.voucher_id, a.doc_date, b.vch_tran_id, b.sl_no, a.sale_account_id, f.account_head, b.amt, b.amt_fc, 
                c.radio_location_desc || ' - ' || d.radio_time_band_name, COALESCE((a.annex_info->'gst_output_info'->>'customer_state_id')::bigint, -1), 
                COALESCE((a.annex_info->'gst_output_info'->>'customer_gstin')::varchar, ''), 
                a.invoice_address, a.vat_type_id, (g.sgst_amt + g.cgst_amt + g.igst_amt)
        from pub.invoice_control a
        inner join pub.invoice_radio_tran b on a.voucher_id = b.voucher_id
        inner join pub.radio_location c on b.radio_location_id = c.radio_location_id
        inner join pub.radio_time_band d on b.radio_time_band_id = d.radio_time_band_id
        inner join pub.ro_control e on b.ro_id = e.ro_id
        inner join ac.account_head f on a.sale_account_id = f.account_id
        inner join tx.gst_tax_tran g on b.vch_tran_id = g.gst_tax_tran_id
        where (a.customer_id=pcustomer_id or pcustomer_id=0)
                And a.branch_id=pbranch_id
                And case when pvoucher_id = '' then a.doc_date between pfrom_date and pto_date 
                        else a.voucher_id = pvoucher_id 
                        End
                And a.status = 5
        Union All 
        select a.voucher_id, a.doc_date, b.vch_tran_id, b.sl_no, a.sale_account_id, f.account_head, b.amt, b.amt_fc, 
                c.tv_location_desc || ' - ' || d.tv_time_band_name, COALESCE((a.annex_info->'gst_output_info'->>'customer_state_id')::bigint, -1), 
                COALESCE((a.annex_info->'gst_output_info'->>'customer_gstin')::varchar, ''), 
                a.invoice_address, a.vat_type_id, (g.sgst_amt + g.cgst_amt + g.igst_amt)
        from pub.invoice_control a
        inner join pub.invoice_tv_tran b on a.voucher_id = b.voucher_id
        inner join pub.tv_location c on b.tv_location_id = c.tv_location_id
        inner join pub.tv_time_band d on b.tv_time_band_id = d.tv_time_band_id
        inner join pub.ro_control e on b.ro_id = e.ro_id
        inner join ac.account_head f on a.sale_account_id = f.account_id
        inner join tx.gst_tax_tran g on b.vch_tran_id = g.gst_tax_tran_id
        where (a.customer_id=pcustomer_id or pcustomer_id=0)
                And a.branch_id=pbranch_id
                And case when pvoucher_id = '' then a.doc_date between pfrom_date and pto_date 
                        else a.voucher_id = pvoucher_id 
                        End
                And a.status = 5
        Union All 
        select a.voucher_id, a.doc_date, b.vch_tran_id, b.sl_no, a.sale_account_id, f.account_head, b.amt, b.amt_fc, 
                c.web_location_desc, COALESCE((a.annex_info->'gst_output_info'->>'customer_state_id')::bigint, -1), 
                COALESCE((a.annex_info->'gst_output_info'->>'customer_gstin')::varchar, ''), 
                a.invoice_address, a.vat_type_id, (g.sgst_amt + g.cgst_amt + g.igst_amt)
        from pub.invoice_control a
        inner join pub.invoice_web_tran b on a.voucher_id = b.voucher_id
        inner join pub.web_location c on b.web_location_id = c.web_location_id
        inner join pub.ro_control e on b.ro_id = e.ro_id
        inner join ac.account_head f on a.sale_account_id = f.account_id
        inner join tx.gst_tax_tran g on b.vch_tran_id = g.gst_tax_tran_id
        where (a.customer_id=pcustomer_id or pcustomer_id=0)
                And a.branch_id=pbranch_id
                And case when pvoucher_id = '' then a.doc_date between pfrom_date and pto_date 
                        else a.voucher_id = pvoucher_id 
                        End
                And a.status = 5
        union all        
        select a.voucher_id, a.doc_date, b.vch_tran_id, b.sl_no, a.sale_account_id, d.account_head, b.amt, b.amt_fc, 
            b.description, COALESCE((a.annex_info->'gst_output_info'->>'customer_state_id')::bigint, -1), 
            COALESCE((a.annex_info->'gst_output_info'->>'customer_gstin')::varchar, ''), 
            a.invoice_address, a.vat_type_id, (e.sgst_amt + e.cgst_amt + e.igst_amt)
        from pub.invoice_control a
        inner join pub.invoice_misc_tran b on a.voucher_id = b.voucher_id
        inner join ac.account_head d on a.sale_account_id = d.account_id
        inner join tx.gst_tax_tran e on b.vch_tran_id = e.gst_tax_tran_id
                where (a.customer_id=pcustomer_id or pcustomer_id=0)
                        And a.branch_id=pbranch_id
                        And case when pvoucher_id = '' then a.doc_date between pfrom_date and pto_date 
                                else a.voucher_id = pvoucher_id 
                                End
                        And a.status = 5
        Union All
        select a.voucher_id, a.doc_date, b.vch_tran_id, b.sl_no, b.sale_account_id, d.account_head, b.item_amt, b.item_amt_fc, 
                b.detailed_desc, COALESCE((a.annex_info->'gst_output_info'->>'customer_state_id')::bigint, -1), 
                COALESCE((a.annex_info->'gst_output_info'->>'customer_gstin')::varchar, ''), 
                a.invoice_address, a.vat_type_id, (e.sgst_amt + e.cgst_amt + e.igst_amt)
        from pub.invoice_control a
        inner join pub.invoice_task_tran b on a.voucher_id = b.voucher_id
        inner join ac.account_head d on b.sale_account_id = d.account_id
        inner join tx.gst_tax_tran e on b.vch_tran_id = e.gst_tax_tran_id
        where (a.customer_id=pcustomer_id or pcustomer_id=0)
                And a.branch_id=pbranch_id
                And case when pvoucher_id = '' then a.doc_date between pfrom_date and pto_date 
                        else a.voucher_id = pvoucher_id 
                        End
                And a.status = 5;
    End If;

    update inv_temp a
    set hsn_sc_id = c.hsn_sc_id,
            hsn_sc_desc = c.hsn_sc_desc
    From tx.gst_tax_tran b 
    inner join tx.hsn_sc c on b.hsn_sc_code = c.hsn_sc_code
    where a.invoice_tran_id = b.gst_tax_tran_id;
	
    DROP TABLE IF EXISTS dcn_tran;	
    create temp table dcn_tran
    (
        invoice_id varchar,
        invoice_tran_id varchar
    );
    Insert into dcn_tran(invoice_id, invoice_tran_id)
    Select (c.annex_info->>'origin_inv_id')::varchar, b.reference_tran_id
    from inv_temp a 
    Inner join ar.rcpt_tran b On a.invoice_id = b.reference_id And a.invoice_tran_id = b.reference_tran_id
    inner join ar.rcpt_control c on c.voucher_id = b.voucher_id 
    where (c.annex_info->>'dcn_type')::int = 0
            And c.status = 5;
    
    return query
    select a.invoice_id, a.doc_date, a.invoice_tran_id, a.sl_no, a.account_id, a.account_head,--, -1::bigint, ''::varchar, -- 
            a.invoice_amt, a.invoice_amt_fc, 
            a.description, a.customer_state_id, a.customer_gstin,
            a.customer_addr, a.hsn_sc_id, a.hsn_sc_desc, a.vat_type_id, a.tax_amt
    from inv_temp a
    Where a.invoice_tran_id not in (select x.invoice_tran_id from dcn_tran x);
	       
END;
$BODY$
LANGUAGE plpgsql;
  
?==?
CREATE OR REPLACE FUNCTION ar.fn_cref_cheque_print(In pvoucher_id varchar(50))  
RETURNS TABLE
(
	voucher_id varchar(50), 
	doc_date date,
	customer_account_id bigint,
	customer varchar(250),
	customer_detail varchar(250),
	credit_amt numeric(18,4),
	credit_amt_fc numeric(18,4),
    is_ac_payee boolean,
    is_non_negotiable boolean
) 
AS
$BODY$
BEGIN	
	DROP TABLE IF EXISTS cref_cheque_temp;	
	create temp table cref_cheque_temp
	(
		voucher_id varchar(50), 
		doc_date date,
		customer_account_id bigint,
		customer varchar(250),
		customer_detail varchar(250),
		credit_amt numeric(18,4),
		credit_amt_fc numeric(18,4),
		is_ac_payee boolean,
		is_non_negotiable boolean
	);

	Insert into cref_cheque_temp (voucher_id, doc_date, customer_account_id, customer, customer_detail, is_ac_payee, is_non_negotiable)
	select a.voucher_id, a.cheque_date, a.customer_account_id, b.customer, a.received_from, (a.annex_info->>'is_ac_payee')::boolean, (a.annex_info->>'is_non_negotiable')::boolean
	from ar.rcpt_control a
	inner join ar.customer b on a.customer_account_id = b.customer_id
	where a.voucher_id = pvoucher_id;
	
	update cref_cheque_temp a
	set credit_amt = b.credit_amt,
		credit_amt_fc = b.credit_amt_fc
	from (
		 select sum(a.credit_amt) as credit_amt, sum(a.credit_amt_fc) as credit_amt_fc 
		 from ar.fn_rcpt_info_for_gl_post(pvoucher_id) a
		 inner join ac.account_head b on a.account_id = b.account_id
		 where b.account_type_id = 1
		 ) b;

	 return query
	 Select a.voucher_id, a.doc_date, a.customer_account_id, a.customer, a.customer_detail, a.credit_amt, a.credit_amt_fc, a.is_ac_payee, a.is_non_negotiable
	 From cref_cheque_temp a;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
create OR REPLACE function ar.fn_salesman_coll(pcompany_id bigint, pbranch_id bigint, psalesman_id bigint, paccount_id bigint, pas_on date, pcoll_days smallint)
RETURNS TABLE  
(	
    voucher_id character varying,
    doc_Date date,
    account_id bigint,
    account_head character varying,
    branch_id bigint,
    salesman_id bigint,
    salesman_name character varying,
    coll_amt numeric(18,4),
    rcpt_id character varying,
    rcpt_date date,
    credit_amt numeric(18,4),
    debit_amt numeric(18,4)
)
AS
$BODY$ 
Begin	
    DROP TABLE IF EXISTS sm_coll;	
    create temp TABLE sm_coll
    (	
        rl_pl_id uuid,
        voucher_id character varying,
        doc_Date date,
        account_id bigint,
        account_head character varying,
        branch_id bigint,
        salesman_id bigint,
        salesman_name character varying,
        coll_amt numeric(18,4),
        rcpt_id character varying,
        rcpt_date date,
        credit_amt numeric(18,4),
        debit_amt numeric(18,4)
    );

    Insert into sm_coll (rl_pl_id, voucher_id, doc_Date, account_id, account_head, branch_id, coll_amt, rcpt_id, rcpt_date, credit_amt, debit_amt)
    select a.rl_pl_id, a.voucher_id, a.doc_date, a.account_id, c.customer, a.branch_id, b.coll_amt, b.rcpt_id, b.rcpt_date, b.credit_amt, b.debit_amt
    from ac.rl_pl a
    inner join (select a.rl_pl_id, a.voucher_id rcpt_id, a.doc_date rcpt_date, sum(a.credit_amt) credit_amt, sum(a.debit_amt) debit_amt, sum(a.credit_amt- a.debit_amt) as coll_amt 
                from ac.rl_pl_alloc a
                where (a.branch_id = pbranch_id or pbranch_id = 0)
                        And (a.doc_date >= (pas_on - (cast(pcoll_days as varchar) || ' days')::interval) And a.doc_date < pas_on)
                		And a.status = 5
                		And (a.account_id = paccount_id or paccount_id = 0)
                Group By a.rl_pl_id, a.voucher_id, a.doc_date
                having sum(a.credit_amt- a.debit_amt) > 0) b on a.rl_pl_id = b.rl_pl_id
    inner join ar.customer c on a.account_id = c.customer_id
    where a.company_id = pcompany_id 
            and a.en_bill_type = 0
            And a.is_opbl = false
    order by a.voucher_id;
    
    Update sm_coll a
    set salesman_id = b.salesman_id
	From ar.fn_inv_for_salesman(pcompany_id, pbranch_id) b
    where a.voucher_id = b.voucher_id;    
    
    Update sm_coll a
    set salesman_name = b.salesman_name
	From ar.salesman b
    where a.salesman_id = b.salesman_id;    
    
	return query 
	select a.voucher_id, a.doc_Date, a.account_id, a.account_head, a.branch_id, COALESCE(a.salesman_id, 0), COALESCE(a.salesman_name, ''),
    		a.coll_amt, a.rcpt_id, a.rcpt_date, a.credit_amt, a.debit_amt
	from sm_coll a
    where (a.salesman_id = psalesman_id or psalesman_id = 0);
END
$BODY$ 
LANGUAGE plpgsql;

?==?
create OR REPLACE function ar.fn_inv_for_salesman(pcompany_id bigint, pbranch_id bigint)
RETURNS TABLE  
(	
    voucher_id character varying ,
    doc_Date date,
    customer_id bigint,
    branch_id bigint,
    salesman_id bigint
)
AS
$BODY$ 
Begin	
	DROP TABLE IF EXISTS sm_inv;	
	create temp TABLE sm_inv
	(	
        voucher_id character varying primary key,
        doc_Date date,
		customer_id bigint,
        branch_id bigint,
        salesman_id bigint
	);


	Insert into sm_inv(voucher_id, doc_Date, customer_id, branch_id, salesman_id)
	Select a.invoice_id, a.doc_date, a.customer_id, a.branch_id, a.salesman_id
	From ar.invoice_control a
    where a.company_id = pcompany_id
    		And (a.branch_id = pbranch_id or pbranch_id = 0)
    Union All
	Select a.stock_id, a.doc_date, a.account_id, a.branch_id, a.salesman_id
	From st.stock_control a
    where a.company_id = pcompany_id
    		And (a.branch_id = pbranch_id or pbranch_id = 0)
            And a.doc_type = 'SIV';            
            
	if exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'invoice_control') then
        Insert into sm_inv(voucher_id, doc_Date, customer_id, branch_id, salesman_id)
        Select a.voucher_id, a.doc_date, a.customer_id, a.branch_id, a.salesman_id
        From pub.invoice_control a
        where a.company_id = pcompany_id
                And (a.branch_id = pbranch_id or pbranch_id = 0);
    End If;

	return query 
	select a.voucher_id, a.doc_Date, a.customer_id, a.branch_id, a.salesman_id
	from sm_inv a;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ar.fn_overdue_bill_interest
(   pcompany_id bigint,
    pbranch_id bigint,
    paccount_id bigint,
    pcon_account_id bigint,
    pfrom_date date,
    pto_date date,
    ppercentage decimal
)
RETURNS TABLE(  category character, rl_pl_id uuid, doc_date date, 
                voucher_id character varying, settle_id character varying, 
                bill_date date, account_id bigint, customer character varying, 
                con_account_id bigint, con_account_head character varying, 
                debit_amt numeric, credit_amt numeric, invoice_amt numeric,
                settled_amt numeric, branch_id bigint, no_of_days smallint, 
                due_days smallint, due_date date, interest_amt numeric, 
                neg_interest_amt numeric, act_interest_amt numeric
) 
AS 
$BODY$
    Declare vno_of_day smallint; vbefore_from_date date;
Begin 

    --Calculate No of days
    vno_of_day =  (pto_date - pfrom_date) + 1;
    
    --BeforeFromDate
    vbefore_from_date  = pfrom_date - Interval '1 day';
  
    DROP TABLE IF EXISTS overdue_inv_temp;
    CREATE temp TABLE  overdue_inv_temp
    (   category char(1),
        rl_pl_id uuid,
        doc_date date,
        voucher_id varchar(50),		
        settle_id character varying,
        bill_date date,
        account_id bigint,		
        customer varchar(250),
     	con_account_id bigint,
    	con_account_head varchar(250),
        debit_amt numeric(18,4),
        credit_amt numeric(18,4),
        invoice_amt numeric(18,4),
        settled_amt numeric(18,4),
        branch_id bigint,   
        no_of_days smallint,
        due_days smallint,
        due_date date,
        interest_amt numeric(18,4),
        neg_interest_amt numeric(18,4),
        act_interest_amt numeric(18,4)
    );
	
    DROP TABLE IF EXISTS rl_temp;	
    Create temp TABLE  rl_temp
    (	
	rl_pl_id uuid primary key,
        voucher_id varchar(50),
        bal_inv_amt numeric(18,4)
    );    
     
    -- Get the Receivable Ledger Id for the given period and also which have balance
    Insert into rl_temp(rl_pl_id, voucher_id, bal_inv_amt)
    Select a.rl_pl_id, a.voucher_id, a.bal_inv_amt
    From (--Get all the pending invoices from statment of accounts
          select a.rl_pl_id, a.voucher_id, a.balance as bal_inv_amt
          from  ar.fn_stmt_of_ac_br_report(pcompany_id,pbranch_id,paccount_id,vbefore_from_date,0) a
          inner join ac.rl_pl b on a.rl_pl_id=b.rl_pl_id
          where b.due_date <= pto_date
     	  Union -- Get all the invoices for the given period
          Select a.rl_pl_id, a.voucher_id, a.debit_amt 
          From ac.rl_pl a 
          Where (a.company_id = pcompany_id)
              And (a.branch_id In (Select x.branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id) x) or pbranch_id = 0) 
              And (a.account_id = paccount_id or paccount_id = 0)
              And a.doc_date>= pfrom_date and a.doc_date<= pto_date
              And a.due_date <= pto_date
     ) a  Group By a.rl_pl_id, a.voucher_id, a.bal_inv_amt ;
    
    Insert Into overdue_inv_temp(category, rl_pl_id, doc_date, voucher_id, settle_id,
                                     bill_date, account_id, debit_amt, credit_amt,invoice_amt, 
                                     settled_amt, branch_id,no_of_days, due_days, due_date, interest_amt, 
                                     neg_interest_amt, act_interest_amt)                                     
    SELECT 'A' as category, a.rl_pl_id, a.doc_date, a.voucher_id, '', Null, 
            a.account_id, a.debit_amt, a.credit_amt, b.bal_inv_amt, a.credit_amt, a.branch_id, 
            Case When a.due_date < pfrom_date Then vno_of_day Else (pto_date - a.due_date) End no_of_days,
            0, a.due_date,
            0,0,0
    FROM ac.rl_pl a	
    Inner join rl_temp b on a.rl_pl_id=b.rl_pl_id
    Union All 
    SELECT 'B' as category, a.rl_pl_id, b.doc_date, b.voucher_id, a.voucher_id, 
    	a.doc_date, a.account_id,  a.net_debit_amt, a.net_credit_amt,  a.net_debit_amt, 
        a.net_credit_amt, b.branch_id, 0,
        Case When a.doc_date > pfrom_date Then 
              Case When b.doc_date >= pfrom_date then (pto_date - a.doc_date) Else (pto_date - a.doc_date) +1 End 
              Else 0 End due_days,
        b.due_date,0,0,0
    FROM  ac.rl_pl_alloc a		
    Inner Join ac.rl_pl b On a.rl_pl_id = b.rl_pl_id
    Where (a.rl_pl_id in (select x.rl_pl_id from rl_temp x))
    And a.doc_date <= pto_date AND a.status=5;

    Update overdue_inv_temp a set customer = b.customer, con_account_id=b.control_account_id,
    con_account_head = c.account_head
    from ar.customer b 
    inner Join ac.account_head c on b.control_account_id=c.account_id
    Where a.account_id = b.customer_id;

    Update overdue_inv_temp a set interest_amt = ((a.invoice_amt * ppercentage/100) / 365) * a.no_of_days
    Where  a.category = 'A';
    
    Update overdue_inv_temp a set neg_interest_amt = ((a.settled_amt * ppercentage/100) / 365) * a.due_days
    Where  a.category = 'B';

    Update overdue_inv_temp a
	Set act_interest_amt = b.interest_amt - b.neg_interest_amt
	from ( select x.rl_pl_id, x.voucher_id, sum(x.interest_amt) as interest_amt, 
               sum(x.neg_interest_amt) as neg_interest_amt
               from overdue_inv_temp x
               group by x.rl_pl_id, x.voucher_id
	      ) b 
	Where a.voucher_id = b.voucher_id;
    
    return query 
    select a.category, a.rl_pl_id, a.doc_date, a.voucher_id, a.settle_id, 
           a.bill_date, a.account_id, a.customer, a.con_account_id, a.con_account_head, 
           a.debit_amt, a.credit_amt, a.invoice_amt,
           a.settled_amt, a.branch_id, a.no_of_days, a.due_days, a.due_date, a.interest_amt, 
           a.neg_interest_amt, case when a.category='A' then a.act_interest_amt else 0 end
    from overdue_inv_temp a 
    where (a.con_account_id=pcon_account_id or pcon_account_id=-99);

END;
$BODY$
LANGUAGE 'plpgsql';

?==?

create or replace function ar.fn_ccl_spent(pcompany_id bigint, pto_date date, pcustomer_id bigint)
RETURNS TABLE  
(
    table_desc varchar(50), 
    branch_id bigint,
    doc_date date,
    voucher_id varchar(50),
    inv_amt numeric(18,4),
    order_amt numeric(18,4),
    is_disputed boolean,
    customer_id bigint	
)
AS
$BODY$ 
Begin	
    DROP TABLE IF EXISTS ccl_spent;	
    create temp TABLE  ccl_spent
    (	
        table_desc varchar(50), 
        branch_id bigint,
        doc_date date,
        voucher_id varchar(50),
        inv_amt numeric(18,4),
        order_amt numeric(18,4),
        is_disputed boolean,
        customer_id bigint
    );

    Insert into ccl_spent(table_desc, branch_id, doc_date, voucher_id, inv_amt, order_amt, is_disputed, customer_id)
	Select 'SOA', a.branch_id, a.doc_date, a.voucher_id, a.balance, 0, false, a.account_id
	From ar.fn_stmt_of_ac_br_report(pcompany_id, 0, pcustomer_id, pto_date, 0) a
	inner join ar.customer b on a.account_id = b.customer_id;

	if exists (SELECT * FROM information_schema.tables where table_schema='sd' And table_name = 'dmr_control') then	
    	Insert into ccl_spent(table_desc, branch_id, doc_date, voucher_id, inv_amt, order_amt, is_disputed, customer_id)							
		select 'sd-OPP', a.branch_id, a.doc_date, a.opportunity_id, 0, a.net_amt, false, a.customer_id
		from crm.opportunity_control a
		inner join ar.customer c on a.customer_id = c.customer_id
		where a.status = 5
			And (a.customer_id = pcustomer_id or pcustomer_id = 0)
			And a.is_close_date = false
            And (a.annex_info->>'doc_module')::varchar = 'sd'
			And a.opportunity_id not in (Select (x.annex_info->'sd_info'->>'so_id')::varchar 
                                         from st.stock_control x
										 where x.status = 5 
                                         	And x.doc_type = 'SIV'
										 	And (x.annex_info->'sd_info'->>'so_id')::varchar != '');
    End If;
    If exists (SELECT * FROM information_schema.tables where table_schema='crm' And table_name = 'opportunity_control') then
    	Insert into ccl_spent(table_desc, branch_id, doc_date, voucher_id, inv_amt, order_amt, is_disputed, customer_id)							
		select 'crm-OPP', a.branch_id, a.doc_date, a.opportunity_id, 0, sum(b.item_amt), false, a.customer_id
		from crm.opportunity_control a
		inner join crm.opportunity_tran b on a.opportunity_id = b.opportunity_id
		inner join ar.customer c on a.customer_id = c.customer_id
		where a.status = 5
			And (a.customer_id = pcustomer_id or pcustomer_id = 0)
			And a.is_close_date = false
            And (a.annex_info->>'doc_module')::varchar = 'crm'
			And b.opportunity_id not in (Select b.reference_id from st.stock_control a
										inner join st.stock_tran b on a.stock_id = b.stock_id
										where a.status = 5 
										And b.reference_id != ''
										And a.doc_type = 'SIV')
        Group By a.branch_id, a.doc_date, a.opportunity_id, a.customer_id;
    End If;
           
        -- Get details from CRM Opportunity for estimate
    if exists (SELECT * FROM information_schema.tables where table_schema = 'pub' And table_name = 'invoice_control') then 
        If exists (SELECT * FROM information_schema.tables where table_schema='crm' And table_name = 'opportunity_control') then
            Insert into ccl_spent(table_desc, branch_id, doc_date, voucher_id, inv_amt, order_amt, is_disputed, customer_id)
            select 'Estimate', a.branch_id, a.doc_date, a.opportunity_id, 0, sum(b.item_amt), false, a.customer_id
            From crm.opportunity_control a
            inner join crm.estimate_tran b on a.opportunity_id = b.opportunity_id
            Where (a.status = 5)
                And (a.customer_id = pcustomer_id or pcustomer_id =0)
                And a.is_close_date = false
                And b.estimate_tran_id not in (Select b.ro_tran_id from pub.invoice_control a
                                                                inner join pub.invoice_ro_tran b on a.voucher_id = b.voucher_id
                                                                where a.status = 5)
            Group By a.branch_id, a.doc_date, a.opportunity_id, a.customer_id;
        End If;
    End If;

    -- Get details from RO control
    if exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'ro_control') then
            Insert into ccl_spent(table_desc, branch_id, doc_date, voucher_id, inv_amt, order_amt, is_disputed, customer_id)
            select a.table_desc, a.branch_id, a.doc_date, a.ro_id, 0, sum(a.ro_tran_amt), false, a.customer_id
            From pub.fn_customer_bal_credit_limit(pcompany_id, 0, pto_date, pcustomer_id, '') a
            Group By a.table_desc, a.branch_id, a.doc_date, a.ro_id, a.customer_id;
    End If;

    return query 
    select a.table_desc, a.branch_id, a.doc_date, a.voucher_id, a.inv_amt, a.order_amt, a.is_disputed, a.customer_id
    from ccl_spent a;
END;
$BODY$
LANGUAGE plpgsql;

?==?

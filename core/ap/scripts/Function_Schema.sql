create OR REPLACE function ap.fn_payable_ledger_balance(pcompany_id bigint, pbranch_id bigint, paccount_id bigint, pto_date date, 
			pvoucher_id varchar(50), pdc varchar(1), prl_pl_id uuid default null)
RETURNS TABLE  
(	rl_pl_id uuid, 
	voucher_id varchar(50), 
	vch_tran_id varchar(50),
	doc_date date,
	bill_no varchar(50),
	bill_date date,
	account_id bigint,
	balance numeric(18,4),
	balance_fc numeric(18,4),
	fc_type_id bigint,
	fc_type varchar(20),
	branch_id bigint,
	due_date date
)
AS
$BODY$ 
Begin	
	DROP TABLE IF EXISTS payable_ledger_balance;	
	create temp TABLE  payable_ledger_balance
	(	
		rl_pl_id uuid primary key, 
		voucher_id varchar(50), 
		vch_tran_id varchar(50),
		doc_date date,
		bill_no varchar(50),
		bill_date date,
		account_id bigint,
		balance numeric(18,4),
		balance_fc numeric(18,4),
		fc_type_id bigint,
		fc_type varchar(20),
		branch_id bigint,
		due_date date
	);


	Insert into payable_ledger_balance(rl_pl_id, voucher_id, vch_tran_id, doc_date, bill_no, bill_date, account_id, 
			balance, balance_fc, fc_type_id, fc_type, branch_id, due_date)
	Select a.rl_pl_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.bill_no, a.bill_date, a.account_id, 
		b.balance, b.balance_fc, a.fc_type_id, c.fc_type, a.branch_id, a.due_date
	From ac.rl_pl a
	Inner Join ( 	Select a.rl_pl_id, sum(a.balance_fc) as balance_fc, sum(a.balance) as balance, a.due_date
			From (  select a.rl_pl_id, sum(a.credit_amt_fc)- sum(a.debit_amt_fc) as balance_fc, 
					sum(a.credit_amt)- sum(a.debit_amt) as balance, a.due_date
				From ac.rl_pl a
				where (a.account_id=paccount_id or paccount_id=0)
					And (a.rl_pl_id = prl_pl_id or prl_pl_id is null)
				Group By a.rl_pl_id, a.due_date
				Union All -- In Alloc, Debits would be heavier and would automatically result in negatives
				select a.rl_pl_id, sum(a.net_credit_amt_fc)- sum(a.net_debit_amt_fc) as settled_fc, 
					sum(a.net_credit_amt) - sum(a.net_debit_amt) as balance, b.due_date
				From ac.rl_pl_alloc a
				inner join ac.rl_pl b on a.rl_pl_id = b.rl_pl_id
				where (a.account_id=paccount_id or paccount_id=0) and a.voucher_id <> pvoucher_id
					And (a.rl_pl_id = prl_pl_id or prl_pl_id is null)
				Group By a.rl_pl_id, b.due_date
			     ) a
			Group By a.rl_pl_id, a.due_date
		   ) b on a.rl_pl_id=b.rl_pl_id
	Inner Join ac.fc_type c on a.fc_type_id=c.fc_type_id
	where a.doc_date <= pto_date 
		And (a.account_id=paccount_id or paccount_id=0)
		And (b.balance_fc <>0 or b.balance <> 0)
		And (a.branch_id=pbranch_id or pbranch_id=0)
		And (a.rl_pl_id = prl_pl_id or prl_pl_id is null);
			
	if pdc='C' then
		-- Remove all advances
		Delete from payable_ledger_balance a Where a.balance < 0;
	End If; 
	If pdc = 'D' then
		-- Remove all setellement/Payables
		Delete from payable_ledger_balance a
		Where a.balance > 0;

		-- Convert negative advances to positive
		Update payable_ledger_balance a
		set balance_fc = a.balance_fc * -1,
		    balance = a.balance * -1;
	End If;

	return query 
	select a.rl_pl_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.bill_no, a.bill_date, a.account_id, 
		a.balance, a.balance_fc, a.fc_type_id, a.fc_type, a.branch_id, a.due_date
	from payable_ledger_balance a;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
create or replace function ap.fn_pymt_info_for_gl_post(pvoucher_id varchar(50))
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
	Declare vCompany_ID bigint =-1; vWriteOffAcc_ID bigint = -1; vDocType varchar(4) = ''; vRoundOffAcc_ID bigint = -1;     
			vBranch_ID bigint = -1; vdcn_type Int:= 0; vorigin_bill_id varchar(50) = '';            
            vApply_rc boolean := false; vrc_sec_id BigInt := -1; vis_reg_supp Boolean := false;
Begin	
        -- This function is used by the Posting Trigger to get information on the Supplier Payment (PYMT)
	DROP TABLE IF EXISTS pymt_vch_detail;	
	create temp TABLE  pymt_vch_detail
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
	-- *****	Step 1: Fetch control values
	Select a.company_id, a.branch_id, doc_type into vCompany_ID, vBranch_ID, vDocType From ap.pymt_control a
	where voucher_id=pvoucher_id;

    If vDocType = 'PYMT' Then 
		-- *****	Step 1: Fetch Bank information (Credits)		
		Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
		Select a.company_ID, a.branch_ID, 'C', a.account_id, 0, a.credit_amt_fc, 0, a.credit_amt
		From ap.pymt_control a 
		Where a.voucher_id=pvoucher_ID;
        
		-- *****	Step 2: Fetch Supplier Settlement Information (Debits)
		Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
		Select vCompany_ID, a.branch_id, 'D', a.account_id, a.debit_amt_fc, 0, a.debit_amt, 0
		From ac.rl_pl_alloc a
		Where a.voucher_id=pvoucher_ID
        		And (a.debit_amt != 0 Or a.debit_amt_fc != 0);
        
		-- *****	Step 3: Fetch Supplier Settlement Information (Credits)
		Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
		Select vCompany_ID, a.branch_id, 'C', a.account_id, 0, a.credit_amt_fc, 0, a.credit_amt
		From ac.rl_pl_alloc a
		Where a.voucher_id=pvoucher_ID
        		And (a.credit_amt != 0 Or a.credit_amt_fc != 0);

		-- ****		Step 4: Fetch Write Off Information (Credit)
		Select cast(value as varchar) into vWriteOffAcc_ID from sys.settings where key='ap_pymt_write_off_account';

		Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
		Select a.company_id, b.branch_id, 'C', vWriteOffAcc_ID, 0, sum(b.write_off_amt_fc), 0, sum(b.write_off_amt)
		From ap.pymt_control a 
		Inner Join ac.rl_pl_alloc b on a.voucher_id=b.voucher_id
		Where a.voucher_id=pvoucher_ID
                group by a.company_id, b.branch_id
		Having Sum(b.write_off_amt_fc) > 0 Or Sum(b.write_off_amt) > 0;

-- 		-- **** 	Step 5: Fetch Other Adjustments 
		Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
			debit_amt, credit_amt)		
		Select a.company_ID, a.branch_ID, 'D', b.account_id, b.debit_amt_fc, 0, 
			b.debit_amt, 0
		From ap.pymt_control a 
		inner join ap.pymt_tran b on a.voucher_id = b.voucher_id
		Where a.voucher_id=pvoucher_ID;
	End If;
	If vDocType in ('MSP') Then 
		-- *****	Step 2: Fetch Bank information (Credits)		
		Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
		Select a.company_ID, a.branch_ID, 'C', a.account_id, 0, sum(b.debit_amt_fc), 0, sum(b.debit_amt)
		From ap.pymt_control a 
		Inner Join ac.rl_pl_alloc b on a.voucher_id=b.voucher_id
		Where a.voucher_id=pvoucher_ID
                group by a.company_ID, a.branch_ID, a.account_id;
        
		-- *****	Step 3: Fetch Supplier Settlement Information (Debits)
		Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
		Select vCompany_ID, a.branch_id, 'D', a.account_id, (a.debit_amt_fc + a.write_off_amt_fc), 0, (a.debit_amt + a.write_off_amt), 0
		From ac.rl_pl_alloc a
		Where a.voucher_id=pvoucher_ID;

		-- ****		Step 4: Fetch Write Off Information (Credit)
		Select cast(value as varchar) into vWriteOffAcc_ID from sys.settings where key='ap_pymt_write_off_account';

		Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
		Select a.company_id, b.branch_id, 'C', vWriteOffAcc_ID, 0, sum(b.write_off_amt_fc), 0, sum(b.write_off_amt)
		From ap.pymt_control a 
		Inner Join ac.rl_pl_alloc b on a.voucher_id=b.voucher_id
		Where a.voucher_id=pvoucher_ID
                group by a.company_id, b.branch_id
		Having Sum(b.write_off_amt_fc) > 0 Or Sum(b.write_off_amt) > 0;

		-- **** 	Step 5: Fetch Other Adjustments 
		Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
		Select a.company_ID, a.branch_ID, 'C', a.account_id, 0, b.debit_amt_fc, 0, b.debit_amt
		From ap.pymt_control a 
		inner join ap.pymt_tran b on a.voucher_id = b.voucher_id
		Where a.voucher_id=pvoucher_ID; 

		Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
			debit_amt, credit_amt)		
		Select a.company_ID, a.branch_ID, 'D', b.account_id, b.debit_amt_fc, 0, 
			b.debit_amt, 0
		From ap.pymt_control a 
		inner join ap.pymt_tran b on a.voucher_id = b.voucher_id
		Where a.voucher_id=pvoucher_ID;
	End If;
    If vDocType = 'MSPY' Then 
		-- *****	Step 1: Fetch Bank information (Credits)		
		Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
		Select a.company_ID, a.branch_ID, 'C', a.account_id, 0, a.credit_amt_fc, 0, a.credit_amt
		From ap.pymt_control a 
		Where a.voucher_id=pvoucher_ID;
        
		-- *****	Step 2: Fetch Supplier Settlement Information (Debits)
		Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
		Select vCompany_ID, a.branch_id, 'D', a.account_id, a.debit_amt_fc, 0, a.debit_amt, 0
		From ac.rl_pl_alloc a
		Where a.voucher_id=pvoucher_ID
        		And (a.debit_amt != 0 Or a.debit_amt_fc != 0);
        
		-- *****	Step 3: Fetch Supplier Settlement Information (Credits)
		Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
		Select vCompany_ID, a.branch_id, 'C', a.account_id, 0, a.credit_amt_fc, 0, a.credit_amt
		From ac.rl_pl_alloc a
		Where a.voucher_id=pvoucher_ID
        		And (a.credit_amt != 0 Or a.credit_amt_fc != 0);
	End If;

	If vDocType = 'ASP' Then
		Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
		Select a.company_ID, a.branch_ID, 'C', a.account_id, 0, a.credit_amt_fc, 0, a.credit_amt
		From ap.pymt_control a 
		Where a.voucher_id=pvoucher_ID;
		
		Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
		Select a.company_ID, a.target_branch_ID, 'D', a.supplier_account_id, a.credit_amt_fc, 0, a.credit_amt, 0
		From ap.pymt_control a 
		Where a.voucher_id=pvoucher_ID;
	End If;
	If vDocType = 'SREC' Then 
		-- *****	Step 2: Fetch Bank information (Credits) 
		Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
		Select a.company_ID, a.branch_ID, 'D', a.account_id, a.credit_amt_fc, 0, a.credit_amt, 0
		From ap.pymt_control a 
		Where a.voucher_id=pvoucher_ID;

		-- *****	Step 3: Fetch Supplier Settlement Information (Debits)
		Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
		Select a.company_ID, a.branch_ID, 'C', a.supplier_account_id, 0, sum(b.credit_amt_fc), 0, sum(b.credit_amt)
		From ap.pymt_control a 
		Inner Join ac.rl_pl_alloc b on a.voucher_id=b.voucher_id
		Where a.voucher_id=pvoucher_ID
		Group By a.company_ID, a.branch_ID, a.supplier_account_id
		Having Sum(b.credit_amt_fc) > 0 
			Or Sum(b.credit_amt) > 0;
	End If;
	If vDocType = 'DN2' Then
            Select coalesce((annex_info->>'dcn_type')::Int, 0), (annex_info->>'origin_bill_id') into vdcn_type, vorigin_bill_id
            from ap.pymt_control
            where voucher_id =pvoucher_id;

            Select (a.annex_info->'gst_rc_info'->>'apply_rc')::boolean, (a.annex_info->'gst_rc_info'->>'rc_sec_id')::BigInt,
                    length(coalesce(a.annex_info->'gst_input_info'->>'supplier_gstin', '')) = 15
                Into vApply_rc, vrc_sec_id, vis_reg_supp
            From ap.bill_control a
            Where a.bill_id = vorigin_bill_id;

            If vdcn_type = 1 Then -- Rate Adjustment (Decrease)
                    -- *****	Group A: Credits
                    -- *****	Step 1: Get Supplier Credit
                Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
                Select vCompany_ID, vBranch_ID, 'C', a.supplier_account_id, 0, 0, 0, a.credit_amt
                From ap.pymt_control a 
                Where a.voucher_id=pvoucher_ID;

                -- Round Off
                Select cast(value as bigint) into vRoundOffAcc_ID from sys.settings where key='st_round_off_account';

                Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select vCompany_ID, vBranch_ID, case when COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) < 0 Then 'D' Else 'C' End, vRoundOffAcc_ID,  
                    case when COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0) > 0 Then COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0) Else 0 End, 
                    case when COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0)  < 0 Then 0 else -1 * COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0) End, 
                    case when COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) > 0 Then COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) Else 0 End, 
                    case when COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) < 0 Then -1 * COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) Else 0 End
                From ap.pymt_control a
                Where a.voucher_id=pvoucher_ID
                    And (a.annex_info->>'round_off_amt')::numeric != 0;

                Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
                    debit_amt, credit_amt)		
                Select vCompany_ID, vBranch_ID, 'D', a.account_id, a.credit_amt_fc, 0, 
                    a.credit_amt, 0
                From ap.pymt_tran a
                Where a.voucher_id=pvoucher_ID;       


                -- Fetch GST Tax Tran (ITC)		
                Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, 'D', b.account_id, 0, 0, coalesce(sum(b.tax_amt), 0), 0
                From ap.pymt_control a
                Inner Join tx.fn_gtt_itc_info(pvoucher_ID, 'ap.pymt_tran', '{-1}'::BigInt[]) b on a.voucher_id =b.voucher_id
                Where a.voucher_id=pvoucher_ID
                group by a.company_id, a.branch_id, b.account_id;

                -- Fetch GST Tax Tran (Non-ITC)
                -- Fetch Tran			
                Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, 'D', b.account_id, 0, 0, c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt, 0
                From ap.pymt_control a
                Inner Join ap.pymt_tran b On a.voucher_id = b.voucher_id
                Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
                Where a.voucher_id=pvoucher_ID And c.apply_itc = False 
                    And c.rc_sec_id = -1
                    And c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt > 0;

            Else        
                -- *****	Group A: Credits
                -- *****	Step 1: Get Supplier Credit
                Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)		
                Select vCompany_ID, vBranch_ID, 'D', a.supplier_account_id, 0, 0, a.credit_amt, 0
                From ap.pymt_control a 
                Where a.voucher_id=pvoucher_ID;

                Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
                    debit_amt, credit_amt)		
                Select vCompany_ID, vBranch_ID, 'C', a.account_id, 0, a.credit_amt_fc, 0, 
                    a.credit_amt
                From ap.pymt_tran a
                Where a.voucher_id=pvoucher_ID;		

                -- Round Off
                Select cast(value as bigint) into vRoundOffAcc_ID from sys.settings where key='st_round_off_account';		
                Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, credit_amt_fc, debit_amt_fc, credit_amt, debit_amt)
                Select a.company_ID, a.branch_ID, case when COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) < 0 Then 'D' Else 'C' End, vRoundOffAcc_ID,  
                    case when COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0) > 0 Then COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0) Else 0 End, 
                    case when COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0)  < 0 Then 0 else -1 * COALESCE((a.annex_info->>'round_off_amt_fc')::numeric, 0) End, 
                    case when COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) > 0 Then COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) Else 0 End, 
                    case when COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) < 0 Then -1 * COALESCE((a.annex_info->>'round_off_amt')::numeric, 0) Else 0 End
                From ap.pymt_control a
                Where a.voucher_id=pvoucher_ID
                    And (a.annex_info->>'round_off_amt')::numeric != 0;

                -- Fetch GST Tax Tran (ITC)		
                Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, 'C', b.account_id, 0, 0, 0, coalesce(sum(b.tax_amt), 0)
                From ap.pymt_control a
                Inner Join tx.fn_gtt_itc_info(pvoucher_ID, 'ap.pymt_tran', '{-1}'::BigInt[]) b on a.voucher_id =b.voucher_id
                Where a.voucher_id=pvoucher_ID
                group by a.company_id, a.branch_id, b.account_id;

                -- Fetch GST Tax Tran (Non-ITC)
                -- Fetch Tran			
                Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, 'C', b.account_id, 0, 0, 0, c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt
                From ap.pymt_control a
                Inner Join ap.pymt_tran b On a.voucher_id = b.voucher_id
                Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
                Where a.voucher_id=pvoucher_ID And c.apply_itc = False 
                    And c.rc_sec_id = -1
                    And c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt > 0;
            End If;


            If vApply_rc And vis_reg_supp And vrc_sec_id In (93, 53) Then
                -- GST Tran becomes Liability for Reverse Charge
                Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, 
                                            credit_amt)
                Select a.company_id, a.branch_id, 'C', b.account_id, 0, 0, coalesce(sum( case when vdcn_type != 1 then b.tax_amt else 0 end), 0), 
                            coalesce(sum( case when vdcn_type = 1 then b.tax_amt else 0 end), 0)
                From ap.pymt_control a
                Inner Join tx.fn_gtt_rc_info(pvoucher_ID, 'ap.pymt_tran', '{93,53}'::BigInt[]) b on a.voucher_id = b.voucher_id
                Where a.voucher_id=pvoucher_ID
                Group by a.company_id, a.branch_id, b.account_id;

                -- GST ITC on Reverse Charge	
                Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, 'D', b.account_id, 0, 0,  coalesce(sum(case when vdcn_type = 1 then b.tax_amt else 0 end), 0), 
                             coalesce(sum( case when vdcn_type != 1 then b.tax_amt else 0 end), 0)
                From ap.pymt_control a
                Inner Join tx.fn_gtt_itc_info(pvoucher_ID, 'ap.pymt_tran', '{93,53}'::BigInt[]) b on a.voucher_id =b.voucher_id
                Where a.voucher_id=pvoucher_ID
                group by a.company_id, a.branch_id, b.account_id;

                -- Fetch GST Tax Tran (Non-ITC)
                Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, 'D', b.account_id, 0, 0, coalesce((case when vdcn_type = 1 then (c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt) else 0 end), 0),
                    coalesce((case when vdcn_type != 1 then (c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt) else 0 end), 0)
                From ap.pymt_control a
                Inner Join ap.pymt_tran b On a.voucher_id = b.voucher_id
                Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
                Where a.voucher_id=pvoucher_ID And c.apply_itc = False 
                    And c.rc_sec_id = Any('{93,53}'::BigInt[])
                    And c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt > 0;
            End If;

	End If;	    
	If vDocType = 'SBT' Then        
            Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
            Select a.company_ID, a.branch_ID, 'C', a.supplier_account_id, 0, sum(b.credit_amt_fc), 0, sum(b.credit_amt)
            From ap.pymt_control a 
            Inner Join ac.rl_pl_alloc b on a.voucher_id=b.voucher_id
            Where a.voucher_id=pvoucher_id
            Group By a.company_ID, a.branch_ID, a.supplier_account_id
            Having Sum(b.credit_amt_fc) > 0 
                Or Sum(b.credit_amt) > 0;

            Insert into pymt_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
            Select a.company_ID, c.target_branch_ID, 'D', a.supplier_account_id, sum(b.credit_amt_fc), 0, sum(b.credit_amt), 0
            From ap.pymt_control a 
            Inner Join ac.rl_pl_alloc b on a.voucher_id=b.voucher_id
            Inner Join ap.bal_transfer_tran c on b.rl_pl_alloc_id=c.rl_pl_alloc_id
            Where a.voucher_id=pvoucher_id
            Group By a.company_ID, c.target_branch_ID, a.supplier_account_id;
	End If;

	return query 
	select a.index, a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
	from pymt_vch_detail a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ap.fn_pymt_report(In pvoucher_id varchar(50))  
RETURNS TABLE
(
	voucher_id varchar(50), 
	doc_date date,
        fc_type_id bigint,
        fc_type varchar(20),
	supplier_account_id bigint,
	supplier varchar(250),
	received_from varchar(100),
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
	gross_adv_amt numeric(18,4),
	gross_adv_amt_fc numeric(18,4),
	is_ac_payee boolean,
	is_non_negotiable boolean,
	supplier_detail varchar(250),
	other_adj numeric(18,4),
	chk_amt_in_words varchar(250)
) 
AS
$BODY$
BEGIN	
	return query
	select a.voucher_id, a.doc_date, a.fc_type_id, e.fc_type, a.supplier_account_id, b.supplier, a.received_from, 
		case when a.pymt_type = 0 then 'Cash Bank'::varchar Else 'Journal'::varchar End as settlement_type, 
		a.account_id, c.account_head, a.exch_rate, a.status, coalesce(a.credit_amt,0) as credit_amt, 
		a.cheque_number, a.cheque_date, a.cheque_bank, a.cheque_branch, a.narration, a.amt_in_words, a.amt_in_words_fc, a.remarks, 
		d.entered_by, d.posted_by, a.gross_adv_amt, a.gross_adv_amt_fc, a.is_ac_payee, a.is_non_negotiable, a.supplier_detail, 
                (a.annex_info->>'other_adj')::numeric as other_adj, (initcap(REPLACE(a.amt_in_words,(f.currency||' '),'')))::varchar(250) as chk_amt_in_words
	from ap.pymt_control a
		inner join ap.supplier b on a.supplier_account_id = b.supplier_id
		inner join ac.account_head c on a.account_id = c.account_id
		inner join sys.doc_es d on a.voucher_id = d.voucher_id
                inner join ac.fc_type e on a.fc_type_id = e.fc_type_id
                inner join sys.branch f on a.branch_id = f.branch_id
		where a.voucher_id = pvoucher_id;	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
-- Create function for supplier payment payable ledger document print report
CREATE OR REPLACE FUNCTION ap.fn_payable_ledger_alloc_report(In pvoucher_id varchar(50))  
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
	credit_amt numeric(18,4),
	credit_amt_fc numeric(18,4),
	write_off_amt numeric(18,4),
	write_off_amt_fc numeric(18,4),
	debit_exch_diff numeric(18,4),
	credit_exch_diff numeric(18,4),
	net_debit_amt numeric(18,4),
	net_debit_amt_fc numeric(18,4),
	net_credit_amt numeric(18,4),
	net_credit_amt_fc numeric(18,4),
	bill_id varchar(50),
	bill_doc_date date,
	bill_no varchar(50),
	bill_date date
) 
AS
$BODY$
BEGIN	
	return query
	select a.branch_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id, a.exch_rate, a.debit_amt, a.debit_amt_fc, a.credit_amt, a.credit_amt_fc, 
		a.write_off_amt, a.write_off_amt_fc, a.debit_exch_diff,
	       a.credit_exch_diff, a.net_debit_amt, a.net_debit_amt_fc, a.net_credit_amt, a.net_credit_amt_fc, b.voucher_id, b.doc_date, b.bill_no, b.bill_date 
	from ac.rl_pl_alloc a
	inner join ac.rl_pl b on a.rl_pl_id = b.rl_pl_id
	where a.voucher_id = pvoucher_id;
	       
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ap.fn_bill_report(In pvoucher_id varchar(50))  
RETURNS TABLE
(
	branch_id bigint,
	bill_id varchar(50),
	doc_date date,
        fc_type_id bigint,
        fc_type varchar(20),
	exch_rate numeric(18,6),
	status smallint,
	supplier_id bigint,
	supplier varchar(250),
	bill_no varchar(50),
	bill_date date,
	bill_amt numeric(18,4),
        bill_amt_fc numeric(18,4),
	bill_action varchar(50),
	gross_amt numeric(18,4),
	gross_amt_fc numeric(18,4),
	round_off_amt numeric(18,4),
	round_off_amt_fc numeric(18,4),
	narration varchar(500),
	amt_in_words varchar(250),
	amt_in_words_fc varchar(250),
	remarks varchar(500),
	entered_by varchar(100), 
	posted_by varchar(100)  
) 
AS
$BODY$
BEGIN	
	DROP TABLE IF EXISTS bill_report_temp;	
	create temp table bill_report_temp
	(
		branch_id bigint,
		bill_id varchar(50),
		doc_date date,
                fc_type_id bigint,
                fc_type varchar(20),
		exch_rate numeric(18,6),
		status smallint,
		supplier_id bigint,
		supplier varchar(250),
		bill_no varchar(50),
		bill_date date,
		bill_amt numeric(18,4),
                bill_amt_fc numeric(18,4),
		bill_action varchar(50),
		gross_amt numeric(18,4),
		gross_amt_fc numeric(18,4),
		round_off_amt numeric(18,4),
		round_off_amt_fc numeric(18,4),
		narration varchar(500),
		amt_in_words varchar(250),
		amt_in_words_fc varchar(250),
		remarks varchar(500),
		entered_by varchar(100), 
		posted_by varchar(100)   
	);

        insert into bill_report_temp(branch_id, bill_id, doc_date, fc_type_id, fc_type, exch_rate, status, supplier_id, supplier,
		bill_no, bill_date, bill_amt, bill_amt_fc, bill_action, 
		round_off_amt, round_off_amt_fc, 
		narration, amt_in_words, amt_in_words_fc, remarks, entered_by, posted_by)
	select 	a.branch_id, a.bill_id, a.doc_date, a.fc_type_id, g.fc_type, a.exch_rate, a.status, a.supplier_id, c.supplier, a.bill_no,
		a.bill_date, a.bill_amt, a.bill_amt_fc, case when a.en_bill_action = 0 then 'Bill' Else 'Advance' End as bill_action, 
		a.round_off_amt, a.round_off_amt_fc,
		a.narration, a.amt_in_words, a.amt_in_words_fc, a.remarks, f.entered_by, f.posted_by
	from ap.bill_control a
		inner join ap.supplier c on a.supplier_id = c.supplier_id
		inner join sys.doc_es f on a.bill_id = f.voucher_id
                inner join ac.fc_type g on a.fc_type_id = g.fc_type_id
	where a.bill_id = pvoucher_id;
	
	return query
	select 	a.branch_id, a.bill_id, a.doc_date, a.fc_type_id, a.fc_type, a.exch_rate, a.status, a.supplier_id, a.supplier, a.bill_no,
		a.bill_date, a.bill_amt, a.bill_amt_fc, a.bill_action, a.gross_amt, a.gross_amt_fc, a.round_off_amt, a.round_off_amt_fc, a.narration, 
		a.amt_in_words, a.amt_in_words_fc, a.remarks, a.entered_by, a.posted_by
	from bill_report_temp a;
	       
END;
$BODY$
  LANGUAGE plpgsql;
	
?==?
-- Create function for bill transaction document print report
CREATE FUNCTION ap.fn_bill_tran_report(In pvoucher_id varchar(50))  
RETURNS TABLE
(
	sl_no smallint,
	bill_id varchar(50),
	bill_tran_id varchar(50),
	account_id bigint,
	account_head varchar(250),
	debit_amt numeric(18,4),
	debit_amt_fc numeric(18,4),
	description varchar(250)
) 
AS
$BODY$
BEGIN	
	return query
	select 	a.sl_no, a.bill_id, a.bill_tran_id, a.account_id, b.account_head, a.debit_amt, a.debit_amt_fc, a.description 
		from ap.bill_tran a
		inner join ac.account_head b on a.account_id = b.account_id
	where a.bill_id = pvoucher_id;     
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ap.fn_bill_tds_tran_report(In pvoucher_id varchar(50))  
RETURNS TABLE
(
        voucher_id varchar(50), 
	bill_tds_tran_id varchar(50),
	person_type_id bigint,
	person_type_desc varchar(50),
	section_id bigint,
	section varchar(50),
	tds_base_rate_perc numeric(18,4),
	tds_base_rate_amt numeric(18,4),
	tds_base_rate_amt_fc numeric(18,4),
	tds_ecess_perc numeric(18,4),
	tds_ecess_amt numeric(18,4),
	tds_ecess_amt_fc numeric(18,4),
	tds_surcharge_perc numeric(18,4),
	tds_surcharge_amt numeric(18,4),
	tds_surcharge_amt_fc numeric(18,4)
) 
AS
$BODY$
BEGIN	
	DROP TABLE IF EXISTS bill_tds_tran_report_temp;	
	create temp table bill_tds_tran_report_temp
	(
		voucher_id varchar(50), 
		bill_tds_tran_id varchar(50),
		person_type_id bigint,
		person_type_desc varchar(50),
		section_id bigint,
		section varchar(50),
		tds_base_rate_perc numeric(18,4),
		tds_base_rate_amt numeric(18,4),
		tds_base_rate_amt_fc numeric(18,4),
		tds_ecess_perc numeric(18,4),
		tds_ecess_amt numeric(18,4),
		tds_ecess_amt_fc numeric(18,4),
		tds_surcharge_perc numeric(18,4),
		tds_surcharge_amt numeric(18,4),
		tds_surcharge_amt_fc numeric(18,4)
	);

        insert into bill_tds_tran_report_temp( voucher_id, bill_tds_tran_id, person_type_id, person_type_desc, section_id, section, tds_base_rate_perc, 
		tds_base_rate_amt, tds_base_rate_amt_fc, tds_ecess_perc, tds_ecess_amt, tds_ecess_amt_fc, 
		tds_surcharge_perc, tds_surcharge_amt, tds_surcharge_amt_fc)
	select 	a.voucher_id, a.bill_tds_tran_id, a.person_type_id, b.person_type_desc,	a.section_id, c.section, a.tds_base_rate_perc, 
		a.tds_base_rate_amt, a.tds_base_rate_amt_fc, a.tds_ecess_perc, a.tds_ecess_amt, a.tds_ecess_amt_fc,
		a.tds_surcharge_perc, a.tds_surcharge_amt, a.tds_surcharge_amt_fc
	from tds.bill_tds_tran a 
		left join tds.person_type b on a.person_type_id = b.person_type_id
		left join tds.section c on a.section_id = c.section_id
	where a.voucher_id = pvoucher_id;
	
	return query
	select a.voucher_id, a.bill_tds_tran_id, a.person_type_id, a.person_type_desc,
	       a.section_id, a.section, a.tds_base_rate_perc, a.tds_base_rate_amt, a.tds_base_rate_amt_fc, a.tds_ecess_perc, a.tds_ecess_amt, a.tds_ecess_amt_fc,
	       a.tds_surcharge_perc, a.tds_surcharge_amt, a.tds_surcharge_amt_fc
	       from bill_tds_tran_report_temp a;
	       
END;
$BODY$
  LANGUAGE plpgsql;

?==?
-- Create function for bill transaction document print report
CREATE OR REPLACE FUNCTION ap.fn_bill_advance_tran_report(pcompany_id bigint, pbranch_id bigint, In pvoucher_id varchar(50))  
RETURNS TABLE
(
	bill_id varchar(50),
	doc_date date, 
	credit_amt numeric(18,4),
	credit_amt_fc numeric(18,4)
) 
AS
$BODY$
	Declare vAccount_ID bigint =-1; vDocDate date ;
BEGIN

	Select a.supplier_id, a.doc_date into vAccount_ID, vDocDate 
	From ap.bill_control a
	where a.bill_id = pvoucher_id;
	
	DROP TABLE IF EXISTS bill_adv_tran;
	create temp TABLE bill_adv_tran
	(
		bill_id varchar(50),
		doc_date date, 
		credit_amt numeric(18,4),
		credit_amt_fc numeric(18,4)
	) ;
	
	Insert into bill_adv_tran(bill_id, doc_date, credit_amt, credit_amt_fc)
	select b.voucher_id, b.doc_date, a.credit_amt, a.credit_amt_fc
	from ac.rl_pl_alloc a
	inner join ac.rl_pl b on a.rl_pl_id = b.rl_pl_id
	where a.voucher_id = pvoucher_id;
	  

	return query
	select a.bill_id, a.doc_date, a.credit_amt, a.credit_amt_fc
	from bill_adv_tran a;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ap.fn_stmt_of_ac_bp_report(IN pcompany_id bigint, IN pbranch_id bigint, IN paccount_id bigint, IN pto_date date, IN pen_bill_type integer)
  RETURNS TABLE(rl_pl_id uuid, voucher_id varchar, vch_tran_id varchar, doc_date date, bill_no varchar, bill_date date,  
		en_bill_type smallint, account_id bigint, balance_fc numeric, fc_type_id bigint, balance numeric, fc_type varchar, currency varchar, branch_id bigint) AS
$BODY$
Begin 
	DROP TABLE IF EXISTS payable_ledger_balance_temp;
	CREATE temp TABLE  payable_ledger_balance_temp
	(
		rl_pl_id uuid,  
		voucher_id varchar(50),  
		vch_tran_id varchar(50),  
		doc_date date,  
		bill_no varchar(600),  
		bill_date date,  
		en_bill_type smallint,
		account_id bigint,  
		balance_fc numeric(18,4),  
		fc_type_id bigint,  
		balance numeric(18,4),  
		fc_type varchar(20),
		currency varchar(20),
        branch_id bigint,
		CONSTRAINT pk_payable_ledger_balance_temp PRIMARY KEY (rl_pl_id)
	 );

	-- Fetch Allocation Data

	DROP TABLE IF EXISTS pay_ledger_alloc_temp;
	CREATE temp TABLE  pay_ledger_alloc_temp
	(
		rl_pl_id uuid,
		balance_fc numeric(18,4),
		balance Numeric(18,4),

		CONSTRAINT pk_pay_ledger_alloc_temp PRIMARY KEY (rl_pl_id)
	 )
	on commit drop;

	Insert Into pay_ledger_alloc_temp(rl_pl_id, balance_fc, balance)
		Select a.rl_pl_id, sum(a.balance_fc) as balance_fc, sum(a.balance) as balance  
		From (	Select	b.rl_pl_id, sum(b.credit_amt_fc - b.debit_amt_fc) as balance_fc,  
						sum(b.credit_amt - b.debit_amt) as balance  
				From ac.rl_pl b 
				Where b.doc_date <= pto_date
					And (b.account_id = paccount_id or  paccount_id = 0) 
					And (b.branch_id In (Select x.branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id) x) or pbranch_id = 0)	
				Group By b.rl_pl_id  
				Union All -- In Alloc, Debits would be heavier and would automatically result in negatives  
				Select	c.rl_pl_id, sum(c.net_credit_amt_fc - c.net_debit_amt_fc) as settled_fc,   
						sum(c.net_credit_amt - c.net_debit_amt) as settled  
				From ac.rl_pl_alloc c  
				Where c.doc_date <= pto_date
					And (c.account_id = paccount_id or paccount_id = 0) 
					And (c.branch_id In (Select x.branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id) x) or pbranch_id = 0)
					And c.status = 5							
				Group By c.rl_pl_id
			) a  
		Group By a.rl_pl_id;

	Insert Into payable_ledger_balance_temp(rl_pl_id, voucher_id, vch_tran_id, doc_date, bill_no, bill_date,  
		en_bill_type, account_id, balance_fc, fc_type_id, balance, fc_type, currency, branch_id) 
	Select	a.rl_pl_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.bill_no || CHR(13) || a.narration, a.bill_date, a.en_bill_type, 
		a.account_id, b.balance_fc, a.fc_type_id, b.balance, e.fc_type, e.currency, a.branch_id
    From ac.rl_pl a  
    Inner Join  pay_ledger_alloc_temp b On a.rl_pl_id = b.rl_pl_id  
    Inner Join ac.fc_type e on a.fc_type_id = e.fc_type_id 
    Where a.doc_date <= pto_date
            And (a.account_id = paccount_id or paccount_id = 0) 
            And (b.balance_fc <> 0 or b.balance <> 0)  
            And (a.branch_id In (Select x.branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id) x) or pbranch_id = 0)
            And (a.company_id = pcompany_id);

	--	Remove the records not required for display
	if pen_bill_type = 1 then -- Exclude Advances
            Begin   
                -- Remove all Advances/Adjustments 
                Delete From payable_ledger_balance_temp a 
                Where a.en_bill_type = 1;
            End;
	Elsif pen_bill_type = 2 then -- Advances Only
            Begin  
                -- Remove all Settlements/Payables  
                Delete From payable_ledger_balance_temp b
                Where b.en_bill_type != 1;
            End;
	End if;

	return query 
		select a.rl_pl_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.bill_no, a.bill_date,  
			a.en_bill_type, a.account_id, a.balance_fc, a.fc_type_id, a.balance, a.fc_type, a.currency, a.branch_id
		from payable_ledger_balance_temp a;	

END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ap.fn_stmt_of_ac_bp_report_future(IN pcompany_id bigint, IN pbranch_id bigint, IN paccount_id bigint, IN pto_date date, IN pen_bill_type integer)
  RETURNS TABLE(rl_pl_id uuid, voucher_id varchar, vch_tran_id varchar, doc_date date, account_id bigint, account_head varchar, en_bill_type smallint, 
		balance_fc numeric, fc_type_id bigint, balance numeric, bill_no varchar, bill_date date, net_debit_amt numeric, debit_exch_diff numeric,
		net_credit_amt numeric, credit_exch_diff numeric, debit_amt numeric, credit_amt numeric, net_debit_amt_fc numeric,
		net_credit_amt_fc numeric, fc_type varchar, currency varchar) AS
$BODY$
Begin 
	DROP TABLE IF EXISTS stmt_of_ac_bp_report_future_temp;
	CREATE temp TABLE  stmt_of_ac_bp_report_future_temp
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
		net_debit_amt numeric(18,4),
		debit_exch_diff numeric(18,4),
		net_credit_amt numeric(18,4),
		credit_exch_diff numeric(18,4),
		debit_amt numeric(18,4),
		credit_amt numeric(18,4),
		net_debit_amt_fc numeric(18,4),
		net_credit_amt_fc numeric(18,4),		
		fc_type varchar(20),
		currency varchar(20)
	);

	INSERT INTO stmt_of_ac_bp_report_future_temp(rl_pl_id, voucher_id, vch_tran_id, doc_date, account_id, account_head, en_bill_type,
		balance_fc, fc_type_id, balance, bill_no, bill_date, net_debit_amt, debit_exch_diff, net_credit_amt, credit_exch_diff,
		debit_amt, credit_amt, net_debit_amt_fc, net_credit_amt_fc, fc_type, currency)
	SELECT	a.rl_pl_id , a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id, c.account_head, a.en_bill_type, a.balance_fc, 
			a.fc_type_id, a.balance, b.voucher_id as voucher_affected, b.doc_date as date_affected,
			b.net_debit_amt, b.debit_exch_diff, b.net_credit_amt, 
			b.credit_exch_diff, 0 as debit_amt, 0 as credit_amt, b.net_debit_amt_fc, b.net_credit_amt_fc, a.fc_type, a.currency
	FROM ap.fn_stmt_of_ac_bp_report(pcompany_id, pbranch_id, paccount_id, pto_date, pen_bill_type) a
	left join ac.rl_pl_alloc b on a.rl_pl_id = b.rl_pl_id
	Inner Join ac.account_head c on a.account_id = c.account_id
	Where a.doc_date <= pto_date AND (b.doc_date > pto_date OR Left(b.voucher_id, 4)='PDI/')
		And b.status = 5 
		AND (a.account_id = paccount_id Or paccount_id = 0);

	--	Remove the records not required for display
	if pen_bill_type = 1 then -- Exclude Advances
            Begin   
                -- Remove all Advances/Adjustments 
                Delete From stmt_of_ac_bp_report_future_temp a
                Where a.en_bill_type = 1;
            End; 
	Elsif pen_bill_type = 2 then -- Advances Only
            Begin  
                -- Remove all Settlements/Payables  
                Delete From stmt_of_ac_bp_report_future_temp b  
                Where b.en_bill_type != 1;
            End;	
	end if;


	return query 
		select a.rl_pl_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.account_id, a.account_head, a.en_bill_type,
			a.balance_fc, a.fc_type_id, a.balance, a.bill_no, a.bill_date, a.net_debit_amt, a.debit_exch_diff, a.net_credit_amt, 
			a.credit_exch_diff, a.debit_amt, a.credit_amt, a.net_debit_amt_fc, a.net_credit_amt_fc, a.fc_type, a.currency 
		from stmt_of_ac_bp_report_future_temp a;	

END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ap.fn_stmt_of_ac_bp_report_detailed(IN pcompany_id bigint, IN pbranch_id bigint, IN paccount_id bigint, IN pto_date date)
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
	bill_no varchar(50),
	ref_bill_date date
) AS
$BODY$
Begin 
	DROP TABLE IF EXISTS bp_report_detailed_temp;
	CREATE temp TABLE  bp_report_detailed_temp
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
		bill_no varchar(50),
		ref_bill_date date
	 );

	
	Insert Into bp_report_detailed_temp(category, rl_pl_id, doc_date, voucher_id, settle_id, bill_date, account_id, debit_amt,
				credit_amt, narration, bill_no, ref_bill_date)
		SELECT 'A' as category, a.rl_pl_id, a.doc_date, a.voucher_id, '', Null, a.account_id, a.debit_amt, 
			a.credit_amt, a.narration, COALESCE(a.bill_no, ''), a.bill_date
		FROM  ac.rl_pl	a	
		Where ( a.account_id = paccount_id or paccount_id = 0)
				And (a.branch_id In (Select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_id = 0) 
				And (a.company_id = pcompany_id)
				And a.doc_date <= pto_date
		Union All -- In Alloc, Debits would be heavier and would automatically result in negatives
		SELECT 'B' as category, a.rl_pl_id, b.doc_date, b.voucher_id, a.voucher_id, a.doc_date, a.account_id,  a.net_debit_amt,
				a.net_credit_amt, b.narration, null, null
		FROM  ac.rl_pl_alloc a		
		Inner Join ac.rl_pl b On a.rl_pl_id = b.rl_pl_id
		Where (a.account_id = paccount_id or paccount_id=0)
				And (b.branch_id In (Select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_id = 0)
				AND b.company_id = pcompany_id
				And a.doc_date <= pto_date AND a.status=5;

	return query 
        select a.category, a.rl_pl_id, a.doc_date, a.voucher_id, a.settle_id, a.bill_date, a.account_id, a.debit_amt, a.credit_amt, a.narration,
                a.bill_no, a.ref_bill_date
        from bp_report_detailed_temp a;	

END;
$BODY$
  LANGUAGE plpgsql; 

?==?
CREATE OR REPLACE FUNCTION ap.fn_stmt_of_ac_bp_ageing_report(IN pcompany_id bigint, IN pbranch_id bigint, IN paccount_id bigint, IN pto_date date, IN pen_bill_type integer, IN ptype integer)
  RETURNS TABLE(dummy varchar, rl_pl_id uuid, voucher_id varchar, vch_tran_id varchar, doc_date date, bill_no varchar,  
		bill_date date, en_bill_type smallint, account_id bigint, balance_fc numeric, fc_type_id bigint, balance numeric,  
		supplier varchar, address varchar, fax varchar, phone varchar, fc_type varchar, currency varchar, days integer,  
		period_id integer, period Varchar) AS
$BODY$
Begin 
	DROP TABLE IF EXISTS bp_ageing_report_temp;
	CREATE temp TABLE  bp_ageing_report_temp
	(
		dummy varchar(10),  
		rl_pl_id uuid,  
		voucher_id varchar(50),  
		vch_tran_id varchar(50),  
		doc_date date,  
		bill_no varchar(600),  
		bill_date date, 
		en_bill_type smallint,   
		account_id bigint,  
		balance_fc numeric(18,4),  
		fc_type_id bigint,  
		balance numeric(18,4),  
		supplier varchar(250),  
		address varchar(500),
		fax varchar(50),
		phone varchar(50), 
		fc_type varchar(20),
		currency varchar(20),  
		days integer,  
		period_id integer,  
		period varchar(15),

		CONSTRAINT pk_bp_ageing_report_temp PRIMARY KEY (rl_pl_id)
	 )
	on commit drop;

	
	Insert Into bp_ageing_report_temp(dummy, rl_pl_id, voucher_id, vch_tran_id, doc_date, bill_no, bill_date, 
			en_bill_type, account_id, balance_fc, fc_type_id, balance, supplier, address, fax, phone, fc_type,
			currency, days, period_id,  period)
		Select 'Dummy' as dummy, a.rl_pl_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.bill_no, a.bill_date, a.en_bill_type, 
			a.account_id, a.balance_fc, a.fc_type_id, a.balance, b.supplier, b.address, b.fax, b.phone, a.fc_type, a.currency,  
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
			End as period  
		From ap.fn_stmt_of_ac_bp_report(pcompany_id, pbranch_id,  paccount_id, pto_date, pen_bill_type) a
                left Join ap.fn_stmt_of_ac_bp_report_supplier_address(pcompany_id, paccount_id) b on a.account_id = b.supplier_id;

	if ptype = 30 then   
		BEGIN  
			Delete From bp_ageing_report_temp  
			Where period_id <> 0;  
		END;
	elsif ptype = 60 then  
		BEGIN  
			Delete From bp_ageing_report_temp  
			Where period_id <> 1;  
		END;
	elsif ptype = 90 then
		BEGIN  
			Delete From bp_ageing_report_temp  
			Where period_id <> 2;  
		END;
	elsif ptype = 120 then 
		BEGIN  
			Delete From bp_ageing_report_temp  
			Where period_id <> 3;  
		END;  
	elsif ptype = 180 then
		BEGIN  
			Delete From bp_ageing_report_temp  
			Where period_id <> 4; 
		END;
	end if;
	
	return query 
		select a.dummy, a.rl_pl_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.bill_no, a.bill_date, a.en_bill_type, 
			a.account_id, a.balance_fc, a.fc_type_id, a.balance, a.supplier, a.address, a.fax, a.phone, a.fc_type, 
			a.currency, a.days, a.period_id,  a.period
		from bp_ageing_report_temp a;	

END;
$BODY$
  LANGUAGE plpgsql;

?==?
Create Or Replace Function ap.fn_bill_info_for_gl_post(pvoucher_id varchar(50))
RETURNS TABLE
(       index int4,
	company_id bigint,
	branch_id bigint,
	dc char(1),
	account_id bigint,
	debit_amt_fc numeric(18,4),
	credit_amt_fc numeric(18,4),
	debit_amt numeric(18,4),
	credit_amt numeric(18,4),
	remarks varchar(100)
)
AS
$BODY$ 
	Declare vDiscountFC numeric(18,4)=0; vDiscount numeric(18,4)= 0; vTotalDebitFC numeric(18,4) = 0; vTotalDebit numeric(18,4) =0;
            vCompany_ID bigint =-1; vBranch_ID bigint = -1; vAccount_ID bigint =-1; vRoundOffAcc_ID bigint = -1;
            vApply_rc boolean := false; vrc_sec_id BigInt := -1; vis_reg_supp Boolean := false; vvat_type_id bigint = -1;
	
Begin	
	-- This function is used by the Posting Trigger to get information on the Supplier Bill
	DROP TABLE IF EXISTS bill_vch_detail;	
	create temp TABLE  bill_vch_detail
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
		remarks varchar(100)
	);

	-- Fetch Control
	Insert into bill_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
	Select a.company_id, a.branch_id, 'C', a.supplier_id, 0, a.bill_amt_fc, 0, a.bill_amt, 'Control Amt'
	From ap.bill_control a
	Where bill_id=pvoucher_ID;

	-- Fetch Tran			
	Insert into bill_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
	Select a.company_id, b.branch_id, 'D', b.account_id, b.debit_amt_fc, 0, b.debit_amt, 0, 'Tran Amt'
	From ap.bill_control a
	Inner Join ap.bill_tran b on a.bill_id=b.bill_id
	Where a.bill_id=pvoucher_ID;

	-- Fetch LC Tran			
	Insert into bill_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
	Select a.company_id, a.branch_id, 'D', b.account_id, b.debit_amt_fc, 0, b.debit_amt, 0, 'LC Tran Amt'
	From ap.bill_control a
	Inner Join ap.bill_lc_tran b on a.bill_id=b.bill_id
	Where a.bill_id=pvoucher_ID;

	-- Round Off
	Select cast(value as bigint) into vRoundOffAcc_ID from sys.settings where key='st_round_off_account';		
	Insert into bill_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
	Select a.company_id, a.branch_id, 'D', vRoundOffAcc_ID, 
		case when a.round_off_amt_fc < 0 Then 0 Else -a.round_off_amt_fc End, case when a.round_off_amt_fc > 0 Then a.round_off_amt_fc Else 0 End, 
		case when a.round_off_amt > 0 Then a.round_off_amt Else 0 End, case when a.round_off_amt < 0 Then -a.round_off_amt Else 0 End, 'Round Off'
	From ap.bill_control a
	Where a.bill_id=pvoucher_ID
                And (a.round_off_amt_fc != 0 or a.round_off_amt != 0);

	-- -- Fetch Service Tax Tran
	Insert into bill_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
	Select a.company_id, a.branch_id, 'D', b.account_id, coalesce(sum(b.tax_amt_fc), 0) as tax_amt_fc, 0, coalesce(sum(b.tax_amt), 0) as tax_amt, 0, 'Service Tax'
	From ap.bill_control a
	Inner Join tx.tax_tran b on a.bill_id =b.voucher_id	
	where a.bill_id=pvoucher_id
	group by a.company_id, a.branch_id, b.account_id;

        -- Fetch GST Tax Tran (ITC)		
        Insert into bill_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
        Select a.company_id, a.branch_id, 'D', b.account_id, 0, 0, coalesce(sum(b.tax_amt), 0), 0, 'Tax Amt'
        From ap.bill_control a
        Inner Join tx.fn_gtt_itc_info(pvoucher_ID, 'ap.bill_tran', '{-1}'::BigInt[]) b on a.bill_id =b.voucher_id
        Where a.bill_id=pvoucher_ID
        group by a.company_id, a.branch_id, b.account_id;

        -- Fetch GST Tax Tran (Non-ITC)
        -- Fetch Tran			
        Insert into bill_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
        Select a.company_id, b.branch_id, 'D', b.account_id, 0, 0, c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt, 0, 'Tran Amt'
        From ap.bill_control a
        Inner Join ap.bill_tran b On a.bill_id = b.bill_id
        Inner Join tx.gst_tax_tran c On b.bill_tran_id = c.gst_tax_tran_id
        Where a.bill_id=pvoucher_ID And c.apply_itc = False 
            And c.rc_sec_id = -1
            And c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt > 0;
        
        -- Reverse Charge Calculations
        Select (a.annex_info->'gst_rc_info'->>'apply_rc')::boolean, (a.annex_info->'gst_rc_info'->>'rc_sec_id')::BigInt, 
        		a.vat_type_id,
                length(coalesce(a.annex_info->'gst_input_info'->>'supplier_gstin', '')) = 15
            Into vApply_rc, vrc_sec_id, vvat_type_id, vis_reg_supp
        From ap.bill_control a
        Where a.bill_id = pvoucher_id;

        If vApply_rc And ((vis_reg_supp And vrc_sec_id In (93, 53)) OR vvat_type_id = 403) Then
            -- GST Tran becomes Liability for Reverse Charge
            Insert into bill_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
            Select a.company_id, a.branch_id, 'C', b.account_id, 0, 0, 0, coalesce(sum(b.tax_amt), 0), 'RC Tax Amt'
            From ap.bill_control a
            Inner Join tx.fn_gtt_rc_info(pvoucher_ID, 'ap.bill_tran', case when vvat_type_id = 403 then '{93,53,94,54}'::BigInt[] Else '{93,53}'::BigInt[] End) b on a.bill_id = b.voucher_id
            Where a.bill_id=pvoucher_ID
            Group by a.company_id, a.branch_id, b.account_id;

            -- GST ITC on Reverse Charge	
            Insert into bill_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
            Select a.company_id, a.branch_id, 'D', b.account_id, 0, 0, coalesce(sum(b.tax_amt), 0), 0, 'Tax Amt'
            From ap.bill_control a
            Inner Join tx.fn_gtt_itc_info(pvoucher_ID, 'ap.bill_tran', case when vvat_type_id = 403 then '{93,53,94,54}'::BigInt[] Else '{93,53}'::BigInt[] End) b on a.bill_id =b.voucher_id
            Where a.bill_id=pvoucher_ID
            group by a.company_id, a.branch_id, b.account_id;

            -- Fetch GST Tax Tran (Non-ITC)
            Insert into bill_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
            Select a.company_id, b.branch_id, 'D', b.account_id, 0, 0, c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt, 0, 'Tran Amt'
            From ap.bill_control a
            Inner Join ap.bill_tran b On a.bill_id = b.bill_id
            Inner Join tx.gst_tax_tran c On b.bill_tran_id = c.gst_tax_tran_id
            Where a.bill_id=pvoucher_ID And c.apply_itc = False 
                And case when vvat_type_id = 403 then c.rc_sec_id = Any('{93,53,94,54}'::BigInt[]) Else c.rc_sec_id = Any('{93,53}'::BigInt[]) End
                And c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt > 0;
        End If;

	return query 
	select a.index, a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt, a.remarks
	from bill_vch_detail a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ap.fn_stmt_of_ac_bp_report_supplier_address(IN pcompany_id bigint, IN paccount_id bigint)
  RETURNS TABLE(supplier_id bigint, supplier varchar, address varchar, fax varchar,
   phone varchar, pay_term_desc varchar, supplier_type_id bigint, contact_person character varying) AS
$BODY$
Begin 
	-- Fetch Supplier Address
	DROP TABLE IF EXISTS supplier_address_temp;
	CREATE temp TABLE  supplier_address_temp
	(
		supplier_id bigint,
		supplier varchar(250),
		address varchar(500),
		fax varchar(50),
		phone varchar(50),
		pay_term varchar(50),
		supplier_type_id bigint,
                contact_person character varying,
		CONSTRAINT pk_supplier_address_temp PRIMARY KEY (supplier_id)
	 );

	Insert Into supplier_address_temp(supplier_id, supplier, address, fax, phone, pay_term, 
                                      supplier_type_id, contact_person)
	Select a.supplier_id, a.supplier_name, b.address || E'\n' || b.city || case when b.pin = '' then '' else ' - ' end  
			    || b.pin || case when b.state = '' then '' else E'\n' end  || b.state || case when b.country = '' then '' else E'\n' end || b.country as address, b.fax, b.phone, '' as pay_term, 
                -1 AS supplier_type_id, b.contact_person
	From ap.supplier a
	Inner Join sys.address b On a.address_id = b.address_id
	Where a.company_ID = pcompany_id
		And (a.supplier_id = paccount_id Or paccount_id = 0);

	Update supplier_address_temp a
	Set pay_term = b.pay_term
	from ( select x.supplier_id, x.pay_term_id, y.pay_term 
			from ap.supplier x 
			Inner Join ac.pay_term y on x.pay_term_id = y.pay_term_id
	      ) b 
	Where a.supplier_id = b.supplier_id;

	return query 
	select a.supplier_id, a.supplier, a.address, a.fax, a.phone, a.pay_term, a.supplier_type_id, a.contact_person
	from supplier_address_temp a;	

END;
$BODY$
  LANGUAGE plpgsql;

?==?
create or replace function ap.fn_supplier_overdue(pcompany_id bigint, pbranch_id bigint, paccount_id bigint, pto_date date, 
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
	not_due_fc numeric(18,4)
)
AS
$BODY$ 
	
Begin	
	DROP TABLE IF EXISTS supp_overdue_temp;	
	create temp TABLE  supp_overdue_temp
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
		not_due_fc numeric(18,4)
	);

	Insert into supp_overdue_temp(account_id, account_head, voucher_id, doc_date, fc_type_id, fc_type, 
		overdue_days, 
		due_date, overdue, overdue_fc, not_due, not_due_fc)
	select a.account_id, b.account_head, a.voucher_id, a.doc_date, a.fc_type_id, a.fc_type, 
		case when DATE_PART('day', pto_date::timestamp - a.due_date::timestamp) <= 0 then 0 else DATE_PART('day', pto_date::timestamp - a.due_date::timestamp) end as overdue_days, 
		a.due_date, case when a.due_date <= pto_date then sum(a.balance) else 0 end as over_due, case when a.due_date <= pto_date then sum(a.balance_fc) else 0 end as over_due_fc, 
	    case when a.due_date > pto_date then sum(a.balance) else 0 end as not_due, case when a.due_date > pto_date then sum(a.balance_fc) else 0 end as not_due_fc
	from ap.fn_pending_bills(pcompany_id, pbranch_id, paccount_id, pto_date, pvoucher_id, pdc, 0::smallint) a
	inner Join ac.account_head b on a.account_id=b.account_id
	group by a.account_id, b.account_head, a.voucher_id, a.doc_date, a.fc_type_id, a.fc_type, a.due_date;
	
	return query 
	select a.account_id, a.account_head, a.voucher_id, a.doc_date, a.fc_type_id, a.fc_type, a.overdue_days, a.due_date, a.overdue, a.overdue_fc, a.not_due, a.not_due_fc
	from supp_overdue_temp a
	order by a.account_head;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ap.fn_bill_collection(IN pcompany_id bigint, IN pbranch_id bigint, IN paccount_id bigint, IN pfrom_date date, IN pto_date date, pbill_status smallint = 0, pbill_id varchar(50) = '')
RETURNS TABLE
(   
    bill_type  varchar(1),
    doc_date date, 
    voucher_id character varying, 
    bill_no character varying, 
    bill_date date, 
    bill_amt numeric(18,4), 
    bill_amt_fc numeric(18,4), 
    supplier_id bigint,
    supplier varchar(250) 
) 
AS
$BODY$
Begin	 
	DROP TABLE IF EXISTS bill_temp;
	create temp TABLE  bill_temp
	( 		
		bill_type  varchar(1),
		doc_date date, 
		voucher_id character varying, 
		bill_no character varying, 
		bill_date date, 
		bill_amt numeric(18,4), 
		bill_amt_fc numeric(18,4), 
		supplier_id bigint,
		supplier varchar(250) 
	);

	Insert into bill_temp (bill_type, voucher_id, doc_date, bill_no, bill_date, bill_amt, bill_amt_fc, supplier_id, supplier)
	Select 'A', a.bill_id, a.doc_date, a.bill_no, a.bill_date, a.bill_amt, a.bill_amt_fc, a.supplier_id, b.supplier
	from ap.bill_control a
	inner join ap.supplier b on a.supplier_id = b.supplier_id
	where case when pbill_status = 0 then a.bill_no = 'BNR'
		Else a.bill_id = pbill_id
		End
		And (a.supplier_id = paccount_id or paccount_id = 0)
		And a.doc_date between pfrom_date and pto_date
		And (a.branch_id=pbranch_id or pbranch_id=0)
		And a.company_id=pcompany_id
		And a.status = 5;
	
	Insert into bill_temp (bill_type, voucher_id, doc_date, supplier_id, supplier, bill_no, bill_date, bill_amt, bill_amt_fc)
        select 'D', a.stock_id, a.doc_date, a.account_id, b.supplier, a.bill_no, a.bill_date, a.bill_amt, a.bill_amt_fc
        from  st.stock_control a
        inner join ap.supplier b on a.account_id = b.supplier_id
        where  case when pbill_status = 0 then a.bill_no = 'BNR'
                Else a.stock_id = pbill_id
                End
                And (a.account_id = paccount_id or paccount_id = 0)
                And a.doc_date between pfrom_date and pto_date
                And (a.branch_id=pbranch_id or pbranch_id=0)
                And a.company_id=pcompany_id
                And a.status = 5;

	return query
	Select a.bill_type, a.doc_date, a.voucher_id, a.bill_no, a.bill_date, a.bill_amt, a.bill_amt_fc, a.supplier_id, a.supplier
	From bill_temp a;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ap.fn_pymt_cheque_print(In pvoucher_id varchar(50))  
RETURNS TABLE
(
	voucher_id varchar(50), 
	doc_date date,
	supplier_account_id bigint,
	supplier varchar(250),
	supplier_detail varchar(250),
	credit_amt numeric(18,4),
	credit_amt_fc numeric(18,4),
	is_ac_payee boolean,
	is_non_negotiable boolean
) 
AS
$BODY$
BEGIN	
	DROP TABLE IF EXISTS pymt_cheque_temp;	
	create temp table pymt_cheque_temp
	(
		voucher_id varchar(50), 
		doc_date date,
		supplier_account_id bigint,
		supplier varchar(250),
		supplier_detail varchar(250),
		credit_amt numeric(18,4),
		credit_amt_fc numeric(18,4),
		is_ac_payee boolean,
		is_non_negotiable boolean
	);

	Insert into pymt_cheque_temp (voucher_id, doc_date, supplier_account_id, supplier, supplier_detail, is_ac_payee, is_non_negotiable)
	select a.voucher_id, a.cheque_date, a.supplier_account_id, b.supplier, a.supplier_detail, a.is_ac_payee, a.is_non_negotiable
	from ap.pymt_control a
	inner join ap.supplier b on a.supplier_account_id = b.supplier_id
	where a.voucher_id = pvoucher_id;
	
	update pymt_cheque_temp a
	set credit_amt = b.credit_amt,
		credit_amt_fc = b.credit_amt_fc
	from (
		 select sum(a.credit_amt) as credit_amt, sum(a.credit_amt_fc) as credit_amt_fc 
		 from ap.fn_pymt_info_for_gl_post(pvoucher_id) a
		 inner join ac.account_head b on a.account_id = b.account_id
		 where b.account_type_id = 1
		 ) b;

	 return query
	 Select a.voucher_id, a.doc_date, a.supplier_account_id, a.supplier, a.supplier_detail, a.credit_amt, a.credit_amt_fc, a.is_ac_payee, a.is_non_negotiable
	 From pymt_cheque_temp a;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
create or replace function ap.sp_pymt_reversal_collection(pcompany_id bigint, pbranch_id bigint, pvoucher_id varchar(50), paccount_id bigint)
RETURNS TABLE  
(	
	voucher_id varchar(50),
	doc_date date,
	supplier_id bigint,
	supplier varchar(250),
	account_id bigint,
	account_head varchar(250),
	received_from varchar(100),
	settled_amt numeric(18,4)	
)
AS
$BODY$ 
Begin	
	return query 
	select a.voucher_id, a.doc_date, a.supplier_account_id, c.supplier, a.account_id, b.account_head, a.received_from, a.credit_amt
	from ap.pymt_control a
	inner join ac.account_head b on a.account_id = b.account_id
	inner join ap.supplier c on a.supplier_account_id = c.supplier_id
	where a.voucher_id = pvoucher_id
		and b.account_type_id = 1
		and a.is_reversed = false
		and a.collected = false
		and (a.branch_id = pbranch_id or pbranch_id = 0)
		and a.company_id = pcompany_id
		and a.account_id = paccount_id;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ap.fn_gst_bill_report(In pvoucher_id varchar(50))  
RETURNS TABLE
(
	branch_id bigint,
	bill_id varchar(50),
	doc_date date,
        fc_type_id bigint,
        fc_type varchar(20),
	exch_rate numeric(18,6),
	status smallint,
	supplier_id bigint,
	supplier varchar(250),
	bill_no varchar(50),
	bill_date date,
	bill_amt numeric(18,4),
        bill_amt_fc numeric(18,4),
	bill_action varchar(50),
	bt_amt numeric(18,4),
	bt_amt_fc numeric(18,4),
	round_off_amt numeric(18,4),
	round_off_amt_fc numeric(18,4),
	narration varchar(500),
	amt_in_words varchar(250),
	amt_in_words_fc varchar(250),
	remarks varchar(500),
	entered_by varchar(100), 
	posted_by varchar(100),
	supplier_gst_state character varying,
	supplier_gstin character varying,
	tax_amt numeric(18,4),
	tax_amt_fc numeric(18,4),
	misc_amt numeric(18,4),
	misc_amt_fc numeric(18,4)
) 
AS
$BODY$
BEGIN	
	return query
	select 	a.branch_id, a.bill_id, a.doc_date, a.fc_type_id, g.fc_type, a.exch_rate, a.status, a.supplier_id, c.supplier, a.bill_no,
		a.bill_date, a.bill_amt, a.bill_amt_fc, (case when a.en_bill_action = 0 then 'Bill' Else 'Advance' End)::varchar, 
		COALESCE((a.annex_info->>'bt_amt')::numeric, 0), COALESCE((a.annex_info->>'bt_amt_fc')::numeric, 0), a.round_off_amt, a.round_off_amt_fc,
		a.narration, a.amt_in_words, a.amt_in_words_fc, a.remarks, f.entered_by, f.posted_by, 
		(e.gst_state_code || ' - ' || e.state_name)::varchar, (a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar,
		COALESCE((a.annex_info->>'tax_amt')::numeric, 0), COALESCE((a.annex_info->>'tax_amt_fc')::numeric, 0),
		COALESCE((a.annex_info->>'misc_amt')::numeric, 0), COALESCE((a.annex_info->>'misc_amt_fc')::numeric, 0)
	from ap.bill_control a
		inner join ap.supplier c on a.supplier_id = c.supplier_id
		inner join sys.doc_es f on a.bill_id = f.voucher_id
                inner join ac.fc_type g on a.fc_type_id = g.fc_type_id
		inner join tx.gst_state e on (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = e.gst_state_id
	where a.bill_id = pvoucher_id;	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE FUNCTION ap.fn_gst_bill_tran_report(In pvoucher_id varchar(50))  
RETURNS TABLE
(
	sl_no smallint,
	bill_id varchar(50),
	bill_tran_id varchar(50),
	account_id bigint,
	account_head varchar(250),
	debit_amt numeric(18,4),
	debit_amt_fc numeric(18,4),
	description varchar(250),
	hsn_sc_code varchar(8),	
	sgst_pcnt Numeric(5,2),
	sgst_amt Numeric(18,2),
	cgst_pcnt Numeric(5,2),
	cgst_amt Numeric(18,2),
	igst_pcnt Numeric(5,2),
	igst_amt Numeric(18,2),
	cess_pcnt Numeric(5,2),
	cess_amt Numeric(18,2)
) 
AS
$BODY$
BEGIN	
	return query
	select 	a.sl_no, a.bill_id, a.bill_tran_id, a.account_id, b.account_head, a.debit_amt, a.debit_amt_fc, a.description,
		e.hsn_sc_code, e.sgst_pcnt, e.sgst_amt, e.cgst_pcnt, e.cgst_amt, e.igst_pcnt, e.igst_amt, e.cess_pcnt, e.cess_amt
	from ap.bill_tran a
	inner join ac.account_head b on a.account_id = b.account_id
	inner join tx.gst_tax_tran e on a.bill_tran_id = e.gst_tax_tran_id
	where a.bill_id = pvoucher_id;     
END;
$BODY$
  LANGUAGE plpgsql;

?==?
Drop  function IF EXISTS ap.fn_supplier_overdue_with_adv(pcompany_id bigint, pbranch_id bigint, paccount_id bigint, pto_date date, 
	pvoucher_id varchar(50), pdc varchar(1), pcon_account_id bigint);

?==?
create or replace function ap.fn_supplier_overdue_with_adv(pcompany_id bigint, pbranch_id bigint, paccount_id bigint, pto_date date, 
	pvoucher_id varchar(50), pdc varchar(1), pcon_account_id bigint, pcurr_pos boolean = true, preg_msmeda boolean = false)
RETURNS TABLE  
(	
	account_id bigint,
	account_head varchar(250),
	voucher_id varchar(50),
	doc_date date,
	fc_type_id bigint,
	fc_type varchar(20),   
	bill_no character varying,
	bill_date date,
        con_account_id bigint,
        con_account_head varchar(250),
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
	DROP TABLE IF EXISTS supp_overdue_temp;	
	create temp TABLE  supp_overdue_temp
	(	
		account_id bigint,
		account_head varchar(250),
		voucher_id varchar(50),
		doc_date date,
		fc_type_id bigint,
		fc_type varchar(20),
                con_account_id bigint,
                con_account_head varchar(250),
		bill_no character varying,
		bill_date date,
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

	Insert into supp_overdue_temp(account_id, account_head, voucher_id, doc_date, fc_type_id, fc_type, bill_no, bill_date,
		con_account_id, con_account_head, overdue_days, 
		due_date, overdue, overdue_fc, not_due, not_due_fc,
                branch_id, adv_amt, adv_amt_fc)
	select a.account_id, b.account_head, a.voucher_id, a.doc_date, a.fc_type_id, a.fc_type, a.bill_no, a.bill_date,
	    c.control_account_id as con_account_id, d.account_head as con_account_head,
		case when DATE_PART('day', pto_date::timestamp - a.due_date::timestamp) <= 0 then 0 else DATE_PART('day', pto_date::timestamp - a.due_date::timestamp) end as overdue_days, 
		a.due_date, case when a.due_date <= pto_date then sum(a.balance) else 0 end as over_due, case when a.due_date <= pto_date then sum(a.balance_fc) else 0 end as over_due_fc, 
		case when a.due_date > pto_date then sum(a.balance) else 0 end as not_due, case when a.due_date > pto_date then sum(a.balance_fc) else 0 end as not_due_fc,
                a.branch_id, 0, 0	    
	from ap.fn_pending_bills(pcompany_id, pbranch_id, paccount_id, pto_date, pvoucher_id, pdc, 5::smallint, pcurr_pos) a
	inner Join ac.account_head b on a.account_id=b.account_id
        inner join ap.supplier c on a.account_id=c.supplier_id
        inner Join ac.account_head d on c.control_account_id=d.account_id
        where ((c.annex_info->'msmeda'->>'is_msmeda_registered')::boolean = preg_msmeda or preg_msmeda = false)
	group by a.account_id, b.account_head, a.voucher_id, a.doc_date, a.fc_type_id, a.fc_type, a.due_date, a.branch_id, a.bill_no, a.bill_date, c.control_account_id, d.account_head;
    
	-- Advances
	Insert into supp_overdue_temp(account_id, account_head, voucher_id, doc_date, fc_type_id, fc_type, bill_no, bill_date, 
		con_account_id, con_account_head, overdue_days, 
		due_date, overdue, overdue_fc, not_due, not_due_fc, branch_id, adv_amt, adv_amt_fc)
	select a.account_id, b.account_head, a.voucher_id, a.doc_date, a.fc_type_id, a.fc_type, a.bill_no, a.bill_date, 
    	c.control_account_id as con_ac_id, d.account_head as con_ac_head,
		DATE_PART('day', pto_date::timestamp - a.due_date::timestamp), -- will return -ve for not due
		a.due_date, 0, 0, 0, 0, a.branch_id, sum(a.balance), sum(a.balance_fc)
	from ap.fn_pending_bills(pcompany_id, pbranch_id, paccount_id, pto_date, pvoucher_id, 'D', 5::smallint, pcurr_pos) a
	inner Join ac.account_head b on a.account_id = b.account_id
        inner join ap.supplier c on a.account_id=c.supplier_id
        inner Join ac.account_head d on c.control_account_id=d.account_id
        where ((c.annex_info->'msmeda'->>'is_msmeda_registered')::boolean = preg_msmeda or preg_msmeda = false)
	group by a.account_id, b.account_head, a.voucher_id, a.doc_date, a.fc_type_id, a.fc_type, a.due_date, a.branch_id, a.bill_no, a.bill_date,c.control_account_id,d.account_head;
	    
	return query 
	select a.account_id, a.account_head, a.voucher_id, a.doc_date, a.fc_type_id, a.fc_type, a.bill_no, a.bill_date, 
		a.con_account_id, a.con_account_head, a.overdue_days, a.due_date, a.overdue, a.overdue_fc, 
		a.not_due, a.not_due_fc, a.branch_id, a.adv_amt, a.adv_amt_fc
	from supp_overdue_temp a
        where (a.con_account_id=pcon_account_id or pcon_account_id=-99)  
	order by a.account_head;
    
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ap.fn_pymt_tds_tran_report(In pvoucher_id varchar(50))  
RETURNS TABLE
(
        voucher_id varchar(50), 
        bill_id varchar(50),
        doc_date date,
        bill_amt numeric(18,4),
        amt_for_tds numeric(18,4),
	bill_tds_tran_id varchar(50),
	person_type_id bigint,
	person_type_desc varchar(50),
	section_id bigint,
	section varchar(50),
	tds_base_rate_perc numeric(18,4),
	tds_base_rate_amt numeric(18,4),
	tds_base_rate_amt_fc numeric(18,4),
	tds_ecess_perc numeric(18,4),
	tds_ecess_amt numeric(18,4),
	tds_ecess_amt_fc numeric(18,4),
	tds_surcharge_perc numeric(18,4),
	tds_surcharge_amt numeric(18,4),
	tds_surcharge_amt_fc numeric(18,4)
) 
AS
$BODY$
BEGIN	
	return query
	select 	a.voucher_id, b.voucher_id, b.doc_date, c.bill_amt, c.amt_for_tds, c.bill_tds_tran_id, c.person_type_id, d.person_type_desc,	c.section_id, e.section, c.tds_base_rate_perc, 
		c.tds_base_rate_amt, c.tds_base_rate_amt_fc, c.tds_ecess_perc, c.tds_ecess_amt, c.tds_ecess_amt_fc,
		c.tds_surcharge_perc, c.tds_surcharge_amt, c.tds_surcharge_amt_fc
	from ac.rl_pl_alloc a
	inner join ac.rl_pl b on a.rl_pl_id = b.rl_pl_id
	Inner Join tds.bill_tds_tran c  on b.voucher_id = c.voucher_id
	left join tds.person_type d on c.person_type_id = d.person_type_id
	left join tds.section e on c.section_id = e.section_id
	where a.voucher_id = pvoucher_id
                And (c.tds_base_rate_amt + c.tds_ecess_amt + c.tds_surcharge_amt) > 0;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
Drop function IF EXISTS ap.fn_pending_bills(pcompany_id bigint, pbranch_id bigint, paccount_id bigint, pto_date date, 
			pvoucher_id varchar(50), pdc varchar(1), pstatus smallint);

?==?
create OR REPLACE function ap.fn_pending_bills(pcompany_id bigint, pbranch_id bigint, paccount_id bigint, pto_date date, 
			pvoucher_id varchar(50), pdc varchar(1), pstatus smallint, pcurr_pos boolean = true)
RETURNS TABLE  
(	rl_pl_id uuid, 
	voucher_id varchar(50), 
	vch_tran_id varchar(50),
	doc_date date,
	bill_no varchar(50),
	bill_date date,
	account_id bigint,
	balance numeric(18,4),
	balance_fc numeric(18,4),
	fc_type_id bigint,
	fc_type varchar(20),
	branch_id bigint,
	due_date date
)
AS
$BODY$ 
Begin	
	DROP TABLE IF EXISTS pending_bills;	
	create temp TABLE  pending_bills
	(	
		rl_pl_id uuid primary key, 
		voucher_id varchar(50), 
		vch_tran_id varchar(50),
		doc_date date,
		bill_no varchar(50),
		bill_date date,
		account_id bigint,
		balance numeric(18,4),
		balance_fc numeric(18,4),
		fc_type_id bigint,
		fc_type varchar(20),
		branch_id bigint,
		due_date date
	);


	Insert into pending_bills(rl_pl_id, voucher_id, vch_tran_id, doc_date, bill_no, bill_date, account_id, 
			balance, balance_fc, fc_type_id, fc_type, branch_id, due_date)
	Select a.rl_pl_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.bill_no, a.bill_date, a.account_id, 
		b.balance, b.balance_fc, a.fc_type_id, c.fc_type, a.branch_id, a.due_date
	From ac.rl_pl a
	Inner Join ( 	Select a.rl_pl_id, sum(a.balance_fc) as balance_fc, sum(a.balance) as balance, a.due_date
			From (  select a.rl_pl_id, sum(a.credit_amt_fc)- sum(a.debit_amt_fc) as balance_fc, 
					sum(a.credit_amt)- sum(a.debit_amt) as balance, a.due_date
				From ac.rl_pl a
				where a.doc_date <= pto_date
					And (a.account_id=paccount_id or paccount_id=0)
				Group By a.rl_pl_id, a.due_date
				Union All -- In Alloc, Debits would be heavier and would automatically result in negatives
				select a.rl_pl_id, sum(a.net_credit_amt_fc)- sum(a.net_debit_amt_fc) as settled_fc, 
					sum(a.net_credit_amt) - sum(a.net_debit_amt) as balance, b.due_date
				From ac.rl_pl_alloc a
				inner join ac.rl_pl b on a.rl_pl_id = b.rl_pl_id
				where case when pcurr_pos then 1=1 else a.doc_date <= pto_date end
					And (a.account_id=paccount_id or paccount_id=0) and a.voucher_id <> pvoucher_id
					And (a.status = pstatus or pstatus = 0)
				Group By a.rl_pl_id, b.due_date
			     ) a
			Group By a.rl_pl_id, a.due_date
		   ) b on a.rl_pl_id=b.rl_pl_id
	Inner Join ac.fc_type c on a.fc_type_id=c.fc_type_id
	where a.doc_date <= pto_date 
		And (a.account_id=paccount_id or paccount_id=0)
		And (b.balance_fc <>0 or b.balance <> 0)
		And (a.branch_id=pbranch_id or pbranch_id=0);
			
	if pdc='C' then
		-- Remove all advances
		Delete from pending_bills a Where a.balance < 0;
	End If; 
	If pdc = 'D' then
		-- Remove all setellement/Payables
		Delete from pending_bills a
		Where a.balance > 0;

		-- Convert negative advances to positive
		Update pending_bills a
		set balance_fc = a.balance_fc * -1,
		    balance = a.balance * -1;
	End If;

	return query 
	select a.rl_pl_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.bill_no, a.bill_date, a.account_id, 
		a.balance, a.balance_fc, a.fc_type_id, a.fc_type, a.branch_id, a.due_date
	from pending_bills a;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
Drop function if exists ap.fn_purchase_register_report(pcompany_id bigint, pbranch_id bigint, psupplier_id bigint, pfrom_date date, pto_date date, pgst_state_id bigint, pgroup_path character varying);

?==?
create or replace function ap.fn_purchase_register_report(pcompany_id bigint, pbranch_id bigint, psupplier_id bigint, pfrom_date date, pto_date date, pgst_state_id bigint, 
                                                          pgroup_path character varying, pinclude_non_gst boolean)
RETURNS TABLE  
(   voucher_id varchar(50), 
    doc_date date,
    supplier_id bigint,
    supplier character varying,
    bill_no character varying,
    bill_date date,
    gstin character varying,
    gst_state character varying,
    vat_type_id bigint,
    vat_type_code character varying,
    bt_amt numeric(18,4),
    sgst_amt numeric(18,4),
    cgst_amt numeric(18,4),
    igst_amt numeric(18,4),
    gst_rate numeric(18,4),
 	rc_sec_id BigInt 
)
AS
$BODY$ 
    Declare vcogc_ac_ids BigInt[]; vAccGroupPath character varying;
Begin	
    if pgroup_path != 'NX' then 
        Select array_agg(a.account_id) into vcogc_ac_ids
        From ac.account_head a 
        Inner Join ac.account_group b On a.group_id = b.group_id
        Where (b.group_path like (pgroup_path || '%') or pgroup_path = 'All');
    Else 
        Select array_agg(a.account_id) into vcogc_ac_ids
        From ac.account_head a 
        Inner Join ac.account_group b On a.group_id = b.group_id
        Where (b.group_path not like All('{A005%,A006%}'));
    End If;

    DROP TABLE IF EXISTS pur_reg;	
    create temp TABLE  pur_reg
    (	
        voucher_id varchar(50), 
        doc_date date,
        supplier_id bigint,
        supplier character varying,
        bill_no character varying,
        bill_date date,
        gstin character varying,
        gst_state character varying,
        vat_type_id bigint,
        vat_type_code character varying,
        bt_amt numeric(18,4),
        sgst_amt numeric(18,4),
        cgst_amt numeric(18,4),
        igst_amt numeric(18,4),
        gst_rate numeric(18,4),
 		rc_sec_id BigInt 
    );
    Insert into pur_reg (voucher_id, doc_date, supplier_id, supplier, bill_no, bill_date, 
            gstin, 
            gst_state, vat_type_id, vat_type_code,
            bt_amt, sgst_amt, cgst_amt, igst_amt, 
            gst_rate, rc_sec_id)
    select a.bill_id, a.doc_date, a.supplier_id, b.account_head, a.bill_no, a.bill_date, 
            (a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar as gstin, 
            (d.gst_state_code || '-' || d.state_code) as gst_state, a.vat_type_id, e.short_desc,
            sum(c.bt_amt) as bt_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else sum(c.sgst_amt) End as sgst_amt, 
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else sum(c.cgst_amt) End as cgst_amt, 
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else sum(c.igst_amt) End as igst_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt) end as gst_rate,
            c.rc_sec_id
    from ap.bill_control a	
    inner join ac.account_head b on a.supplier_id = b.account_id
    inner join tx.gst_state d on (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = d.gst_state_id
    Inner join tx.vat_type e on a.vat_type_id = e.vat_type_id
    inner join ap.bill_tran f on a.bill_id = f.bill_id
    Inner join tx.gst_tax_tran c on f.bill_tran_id = c.gst_tax_tran_id
    Where a.company_id = pcompany_id
        and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
        And a.doc_date between pfrom_date and pto_date
        And ((a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = pgst_state_id or pgst_state_id = 0)
        And (a.supplier_id = psupplier_id or psupplier_id = 0)		
        And (f.account_id = Any(vcogc_ac_ids))
        And (pgroup_path != 'A001' or pgroup_path = 'All')
        And a.status = 5
        And case when pinclude_non_gst then 1=1 Else c.hsn_sc_code != 'NONGST' end
    Group by a.bill_id, a.supplier_id, b.account_head, d.state_code, d.gst_state_code, 
            a.vat_type_id, e.short_desc, c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt, c.rc_sec_id
    Union All -- AP DebitCreditNote
    select a.voucher_id, a.doc_date, a.supplier_account_id, b.account_head, (a.annex_info->>'supp_ref_no')::varchar, (a.annex_info->>'supp_ref_date')::date, 
        (a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar as gstin, 
        (d.gst_state_code || '-' || d.state_code) as gst_state, (a.annex_info->'gst_input_info'->>'vat_type_id')::bigint, e.short_desc,        
        case when (a.annex_info->>'dcn_type')::int =1 then sum(c.bt_amt) else -1 * sum(c.bt_amt) end bt_amt,
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (case when (a.annex_info->>'dcn_type')::int =1 then sum(c.sgst_amt) else -1 * sum(c.sgst_amt) end) End as sgst_amt, 
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (case when (a.annex_info->>'dcn_type')::int =1 then sum(c.cgst_amt) else -1 * sum(c.cgst_amt) end) End as cgst_amt, 
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (case when (a.annex_info->>'dcn_type')::int =1 then sum(c.igst_amt) else -1 * sum(c.igst_amt) end) End as igst_amt,
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt) end as gst_rate,
        c.rc_sec_id
    from ap.pymt_control a	
    inner join ac.account_head b on a.supplier_account_id = b.account_id
    inner join tx.gst_state d on (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = d.gst_state_id
    Inner join tx.vat_type e on (a.annex_info->'gst_input_info'->>'vat_type_id')::bigint  = e.vat_type_id
    inner join ap.pymt_tran f on a.voucher_id = f.voucher_id
    Inner join tx.gst_tax_tran c on f.vch_tran_id = c.gst_tax_tran_id
    Where a.company_id = pcompany_id
        and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
        And a.doc_date between pfrom_date and pto_date
        And ((a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = pgst_state_id or pgst_state_id = 0)
        And (a.supplier_account_id = psupplier_id or psupplier_id = 0)		
        And (f.account_id = Any(vcogc_ac_ids))
        And (pgroup_path != 'A001' or pgroup_path = 'All')
        And a.status = 5	
        And case when pinclude_non_gst then 1=1 Else c.hsn_sc_code != 'NONGST' end	
        And a.doc_type = 'DN2'	
    Group by a.voucher_id, a.supplier_account_id, b.account_head, d.state_code, d.gst_state_code, 
        (a.annex_info->'gst_input_info'->>'vat_type_id')::bigint, e.short_desc, c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt, c.rc_sec_id
    Union All 
    Select a.voucher_id, a.doc_date, 99, 'Various Suppliers', (a.annex_info->>'bill_no')::varchar as bill_no, (a.annex_info->>'bill_date')::date as bill_date,
            (a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar as gstin, 
            (d.gst_state_code || '-' || d.state_code) as gst_state, (a.annex_info->'gst_input_info'->>'vat_type_id')::bigint, e.short_desc,
            Sum(c.bt_amt) as bt_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else sum(c.sgst_amt) End as sgst_amt, 
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else sum(c.cgst_amt) End as cgst_amt, 
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else sum(c.igst_amt) End as igst_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt) end as gst_rate,
            c.rc_sec_id
    From ac.vch_control a
    Inner Join ac.vch_tran b On a.voucher_id = b.voucher_id
    Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
    inner join tx.gst_state d on (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = d.gst_state_id
    Inner join tx.vat_type e on (a.annex_info->'gst_input_info'->>'vat_type_id')::bigint = e.vat_type_id
    Where  a.company_id = pcompany_id        	
        And (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        And a.doc_date Between pfrom_date And pto_date
        And ((a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = pgst_state_id or pgst_state_id = 0)
        And a.doc_type = Any('{PAYV, PAYC, PAYB}')	
        And (b.account_id = Any(vcogc_ac_ids))
        And a.status = 5
        And case when pinclude_non_gst then 1=1 Else c.hsn_sc_code != 'NONGST' end
        And psupplier_id =0
        And (pgroup_path != 'A001' or pgroup_path = 'All')	
    Group by a.voucher_id, d.state_code, d.gst_state_code, 
            (a.annex_info->'gst_input_info'->>'vat_type_id')::bigint, e.short_desc, c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt, c.rc_sec_id
    Union All 	
    select a.ap_id, a.doc_date, a.account_id, b.account_head, a.bill_no, a.bill_date, 
        (a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar as gstin, 
        (d.gst_state_code || '-' || d.state_code) as gst_state, (a.annex_info->'gst_input_info'->>'vat_type_id')::bigint, e.short_desc,
        sum(c.bt_amt) as bt_amt,         
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else sum(c.sgst_amt) End as sgst_amt, 
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else sum(c.cgst_amt) End as cgst_amt, 
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else sum(c.igst_amt) End as igst_amt,
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt) end as gst_rate,
        c.rc_sec_id
    from fa.ap_control a	
    inner join ac.account_head b on a.account_id = b.account_id
    inner join tx.gst_state d on (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = d.gst_state_id
    Inner join tx.vat_type e on (a.annex_info->'gst_input_info'->>'vat_type_id')::bigint = e.vat_type_id
    inner join fa.ap_tran f on a.ap_id = f.ap_id
    inner join fa.asset_class g on f.asset_class_id = g.asset_class_id
    Inner join tx.gst_tax_tran c on f.ap_tran_id = c.gst_tax_tran_id
    Where a.company_id = pcompany_id
        And (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        And a.doc_date Between pfrom_date And pto_date
        And ((a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = pgst_state_id or pgst_state_id = 0)
        And (a.account_id = psupplier_id or psupplier_id = 0)			
        And (g.asset_account_id = Any(vcogc_ac_ids))
        And a.status = 5	
        And case when pinclude_non_gst then 1=1 Else c.hsn_sc_code != 'NONGST' end
        And (pgroup_path = 'A001' or pgroup_path = 'All')
    Group by a.ap_id, a.account_id, b.account_head, d.state_code, d.gst_state_code, 
        (a.annex_info->'gst_input_info'->>'vat_type_id'), e.short_desc, c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt, c.rc_sec_id;

    with gtt 
    As 
    (	select a.stock_id, (c->>'bt_amt')::numeric as bt_amt, (c->>'sgst_amt')::numeric as sgst_amt, (c->>'cgst_amt')::numeric as cgst_amt, 
                (c->>'igst_amt')::numeric as igst_amt, ((c->>'sgst_pcnt')::numeric + (c->>'cgst_pcnt')::numeric + (c->>'igst_pcnt')::numeric) as gst_rate,
                (c->>'hsn_sc_code')::varchar hsn_sc_code, (a.annex_info->'gst_rc_info'->>'rc_sec_id')::smallint rc_sec_id
            from st.stock_control a, jsonb_array_elements(a.annex_info->'gst_tax_tran') c
    )
    Insert into pur_reg (voucher_id, doc_date, supplier_id, supplier, bill_no, bill_date, 
            gstin, 
            gst_state, vat_type_id, vat_type_code,
            bt_amt, sgst_amt, cgst_amt, igst_amt, 
            gst_rate, rc_sec_id)
    select a.stock_id, a.doc_date, a.account_id, b.account_head, a.bill_no, a.bill_date, 
            (a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar as gstin, 
            (d.gst_state_code || '-' || d.state_code) as gst_state, a.vat_type_id, e.short_desc,
            case when a.doc_type in ('SP','SPG') then sum(c.bt_amt)	Else -1 * sum(c.bt_amt) End as bt_amt,            
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (case when a.doc_type in ('SP','SPG') then sum(c.sgst_amt) Else -1 * sum(c.sgst_amt) End) End as sgst_amt, 
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (case when a.doc_type in ('SP','SPG') then sum(c.cgst_amt) Else -1 * sum(c.cgst_amt) End) End as cgst_amt, 
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (case when a.doc_type in ('SP','SPG') then sum(c.igst_amt) Else -1 * sum(c.igst_amt) End) End as igst_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else c.gst_rate end as gst_rate,
            c.rc_sec_id
    from st.stock_control a
    inner join ac.account_head b on a.account_id = b.account_id
    Inner join gtt c on a.stock_id = c.stock_id
    inner join tx.gst_state d on (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = d.gst_state_id
    Inner join tx.vat_type e on a.vat_type_id = e.vat_type_id
    Where a.company_id = pcompany_id
        and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
        And a.doc_date between pfrom_date and pto_date
        And ((a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = pgst_state_id or pgst_state_id = 0)
        And (a.account_id = psupplier_id or psupplier_id = 0)	
        And (a.sale_account_id = Any(vcogc_ac_ids))		
        And a.status = 5
        And case when pinclude_non_gst then 1=1 Else (c.hsn_sc_code != 'NONGST' or c.hsn_sc_code is Null) end
        And a.doc_type= Any('{SP, SPG, PR, PRV}')
        And (pgroup_path != 'A001' or pgroup_path = 'All')
    Group by a.stock_id, a.account_id, b.account_head, d.state_code, d.gst_state_code, 
        a.vat_type_id, e.short_desc, c.gst_rate, c.rc_sec_id;

    -- Sales Purchase Return 
    Insert into pur_reg (voucher_id, doc_date, supplier_id, supplier, bill_no, bill_date, 
            gstin, 
            gst_state, vat_type_id, vat_type_code,
            bt_amt, sgst_amt, cgst_amt, igst_amt, 
            gst_rate, rc_sec_id)	
    select a.stock_id, a.doc_date, a.account_id, b.account_head, a.bill_no, a.bill_date, 
        (a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar as gstin, 
        (d.gst_state_code || '-' || d.state_code) as gst_state, a.vat_type_id, e.short_desc,
        case when (a.annex_info->>'dcn_type')::int =1 then sum(c.bt_amt) else -1 * sum(c.bt_amt) end bt_amt,
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (case when (a.annex_info->>'dcn_type')::int =1 then sum(c.sgst_amt) else -1 * sum(c.sgst_amt) end) End as sgst_amt, 
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (case when (a.annex_info->>'dcn_type')::int =1 then sum(c.cgst_amt) else -1 * sum(c.cgst_amt) end) End as cgst_amt, 
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (case when (a.annex_info->>'dcn_type')::int =1 then sum(c.igst_amt) else -1 * sum(c.igst_amt) end) End as igst_amt,
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt) end as gst_rate,
        c.rc_sec_id
    from st.stock_control a
    inner join ac.account_head b on a.account_id = b.account_id
    inner join st.stock_tran f on a.stock_id = f.stock_id
    Inner join tx.gst_tax_tran c on f.stock_tran_id = c.gst_tax_tran_id
    inner join tx.gst_state d on (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = d.gst_state_id
    Inner join tx.vat_type e on a.vat_type_id = e.vat_type_id
    Where a.company_id = pcompany_id
        and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
        And a.doc_date between pfrom_date and pto_date
        And ((a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = pgst_state_id or pgst_state_id = 0)
        And (a.account_id = psupplier_id or psupplier_id = 0)	
        And (a.sale_account_id = Any(vcogc_ac_ids))		
        And a.status = 5
        And case when pinclude_non_gst then 1=1 Else c.hsn_sc_code != 'NONGST' end
        And a.doc_type= Any('{PR, PRV}')
        And (pgroup_path != 'A001' or pgroup_path = 'All')
    Group by a.stock_id, a.account_id, b.account_head, d.state_code, d.gst_state_code, 
        a.vat_type_id, e.short_desc, (c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt), c.rc_sec_id;

    if exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'abp_control') then
        Insert into pur_reg (voucher_id, doc_date, supplier_id, supplier, bill_no, bill_date, gstin, gst_state, vat_type_id, vat_type_code,
                bt_amt, sgst_amt, cgst_amt, igst_amt, gst_rate, rc_sec_id)
        select a.voucher_id, a.doc_date, a.account_id, b.account_head, a.bill_no, a.bill_date,
                (a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar as gstin, 
                (d.gst_state_code || '-' || d.state_code) as gst_state, a.vat_type_id, e.short_desc,
                sum(c.bt_amt) as bt_amt, 
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else sum(c.sgst_amt) End as sgst_amt, 
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else sum(c.cgst_amt) End as cgst_amt, 
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else sum(c.igst_amt) End as igst_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt) end as gst_rate,
            c.rc_sec_id
        from pub.abp_control a
        inner join ac.account_head b on a.account_id = b.account_id
        Inner join tx.gst_tax_tran c on a.voucher_id = c.voucher_id
        inner join tx.gst_state d on (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = d.gst_state_id
        Inner join tx.vat_type e on a.vat_type_id = e.vat_type_id
        Where a.company_id = pcompany_id
            and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
            And a.doc_date between pfrom_date and pto_date
            And ((a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = pgst_state_id or pgst_state_id = 0)
            And (a.account_id = psupplier_id or psupplier_id = 0)
            And (a.payable_account_id = Any(vcogc_ac_ids))		
            And a.status = 5
            And case when pinclude_non_gst then 1=1 Else c.hsn_sc_code != 'NONGST' end
            And (pgroup_path != 'A001' or pgroup_path = 'All')
        Group by a.voucher_id, a.account_id, b.account_head, d.state_code, d.gst_state_code,
            a.vat_type_id, e.short_desc, c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt, c.rc_sec_id;
    End If;

    return query 
    select a.voucher_id, a.doc_date, a.supplier_id, a.supplier, a.bill_no, a.bill_date, a.gstin, a.gst_state, a.vat_type_id, a.vat_type_code,
            a.bt_amt, a.sgst_amt, a.cgst_amt, a.igst_amt, a.gst_rate, a.rc_sec_id
    from pur_reg a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ap.fn_bill_for_dn(pbranch_id bigint, psupplier_id bigint, pfrom_date date, pto_date date, In pvoucher_id varchar)  
RETURNS TABLE(
	bill_id varchar,  
	doc_date date,
	bill_tran_id varchar,
	sl_no smallint, 
	account_id bigint, 
	account_head varchar,
	bill_amt numeric, 
	bill_amt_fc numeric, 
	description varchar,
	supp_state_id bigint,
	supp_gstin varchar,
	supp_addr character varying,
	hsn_sc_id bigint,
	hsn_sc_desc character varying,
	vat_type_id bigint,
    tax_amt numeric
) AS
$BODY$
BEGIN	
    DROP TABLE IF EXISTS bill_temp;	
    create temp table bill_temp
    (
        bill_id varchar,  
        doc_date date,
        bill_tran_id varchar,
        sl_no smallint, 
        account_id bigint, 
        account_head varchar,
        bill_amt numeric, 
        bill_amt_fc numeric, 
        description varchar,
        supp_state_id bigint,
        supp_gstin varchar,
        supp_addr character varying,
        hsn_sc_id bigint,
        hsn_sc_desc character varying,
        vat_type_id bigint,
        tax_amt numeric
    );

    insert into bill_temp(bill_id, doc_date, bill_tran_id, sl_no, account_id, account_head, bill_amt, bill_amt_fc, 
			description, supp_state_id,  
			supp_gstin, 
			supp_addr, vat_type_id,
                        tax_amt)
    select a.bill_id, a.doc_date, b.bill_tran_id, b.sl_no, b.account_id, c.account_head, b.debit_amt, b.debit_amt_fc, 
            b.description, COALESCE((a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint, -1), 
            COALESCE((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar, ''), 
            COALESCE((a.annex_info->'gst_input_info'->>'supplier_address')::varchar, ''), a.vat_type_id,
            (d.sgst_amt + d.cgst_amt + d.igst_amt)
    from ap.bill_control a
    inner join ap.bill_tran b on a.bill_id = b.bill_id
    inner join ac.account_head c on b.account_id = c.account_id
    inner join tx.gst_tax_tran d on b.bill_tran_id = d.gst_tax_tran_id
    where (a.supplier_id=psupplier_id or psupplier_id=0)
            And a.branch_id=pbranch_id
            And case when pvoucher_id = '' then a.doc_date between pfrom_date and pto_date 
                    else a.bill_id = pvoucher_id 
                    End
            And status = 5
    Union All
    Select a.ap_id, a.doc_date, b.ap_tran_id, b.sl_no, e.asset_account_id, c.account_head, b.bt_amt, 0, 
            b.asset_name, COALESCE((a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint, -1), 
            COALESCE((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar, ''), 
            COALESCE((a.annex_info->'gst_input_info'->>'supplier_address')::varchar, ''), 
            COALESCE((a.annex_info->'gst_input_info'->>'vat_type_id')::bigint, -1),
            (d.sgst_amt + d.cgst_amt + d.igst_amt)
    from fa.ap_control a
    inner join fa.ap_tran b on a.ap_id = b.ap_id
    inner join fa.asset_class e on b.asset_class_id = e.asset_class_id
    inner join ac.account_head c on e.asset_account_id = c.account_id
    inner join tx.gst_tax_tran d on b.ap_tran_id = d.gst_tax_tran_id
    where (a.account_id=psupplier_id or psupplier_id=0)
            And a.branch_id=pbranch_id
            And case when pvoucher_id = '' then a.doc_date between pfrom_date and pto_date 
                    else a.ap_id = pvoucher_id 
                    End
            And status = 5;

    update bill_temp a
    set hsn_sc_id = c.hsn_sc_id,
        hsn_sc_desc = c.hsn_sc_desc
    From tx.gst_tax_tran b 
    inner join tx.hsn_sc c on b.hsn_sc_code = c.hsn_sc_code
    where a.bill_tran_id = b.gst_tax_tran_id;

    return query
    select 	a.bill_id, a.doc_date, a.bill_tran_id, a.sl_no, a.account_id, a.account_head,--, -1::bigint, ''::varchar, -- 
            a.bill_amt, a.bill_amt_fc, 
            a.description, a.supp_state_id, a.supp_gstin,
            a.supp_addr, a.hsn_sc_id, a.hsn_sc_desc, a.vat_type_id, a.tax_amt
    from bill_temp a;
	       
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ap.fn_po_for_adv_req(In pcompany_id bigint, pbranch_id bigint)  
RETURNS TABLE
(
	stock_id varchar(50),
	doc_date date,
    account_id bigint,
    supplier character varying,
	advance_amt numeric(18,6),
    target_branch_id bigint
) 
AS
$BODY$
BEGIN	
	DROP TABLE IF EXISTS po_temp;	
	create temp table po_temp
	(
        stock_id varchar(50),
        doc_date date,
        account_id bigint,
        supplier character varying,
        advance_amt numeric(18,6), 
		target_branch_id bigint
	);

    insert into po_temp( stock_id, doc_date, account_id, supplier, advance_amt, target_branch_id)
    Select a.stock_id, a.doc_date, a.account_id, b.supplier, a.advance_amt, a.target_branch_id
    from st.stock_control a
    inner join ap.supplier b on a.account_id = b.supplier_id
    where a.doc_type = 'PO'
            And COALESCE((a.annex_info->>'adv_ref_no')::varchar, '') = ''
            And a.advance_amt != 0
            and a.company_id = pcompany_id
            and a.branch_id = pbranch_id 
            and a.status = 3
    Union All
    Select a.ap_id, a.doc_date, a.account_id, b.supplier, a.advance_amt, (a.annex_info->>'target_branch_id')::bigint
    from fa.ap_control a
    inner join ap.supplier b on a.account_id = b.supplier_id
    where a.doc_type = 'POCG'
            And COALESCE((a.annex_info->>'adv_ref_no')::varchar, '') = ''
            And a.advance_amt != 0
            and a.company_id = pcompany_id
            and a.branch_id = pbranch_id 
            and a.status = 3;
    
	if exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'po_control') then
    	insert into po_temp(stock_id, doc_date, account_id, supplier, advance_amt, target_branch_id)
        Select a.po_id, a.doc_date, a.supplier_id, b.supplier, a.advance_amt, a.target_branch_id
        from pub.po_control a
        inner join ap.supplier b on a.supplier_id = b.supplier_id
        where a.doc_type = 'SPO2'
                And COALESCE((a.annex_info->>'adv_ref_no')::varchar, '') = ''
                And a.advance_amt != 0
                and a.company_id = pcompany_id
                and a.branch_id = pbranch_id 
                and a.status != 5;
	End If;
    
	return query
	select a.stock_id, a.doc_date, a.account_id, a.supplier, a.advance_amt, a.target_branch_id
	from po_temp a;
	       
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ap.fn_purchase_register_ip_report(pcompany_id bigint, pbranch_id bigint, psupplier_id bigint, pfrom_date date, pto_date date, pgst_state_id bigint, 
                                                          pgroup_path character varying, pinclude_non_gst boolean)
RETURNS TABLE  
(   voucher_id varchar(50), 
    doc_date date,
    supplier_id bigint,
    supplier character varying,
    bill_no character varying,
    bill_date date,
    gstin character varying,
    gst_state character varying,
    vat_type_id bigint,
    vat_type_code varchar(1),
    bt_amt numeric(18,4),
 	itc_amt numeric(18,4),
 	non_itc_amt numeric(18,4),
    gst_amt numeric(18,4),
    gst_rate numeric(18,4)
)
AS
$BODY$ 
    Declare vcogc_ac_ids BigInt[]; vAccGroupPath character varying;
Begin	
    if pgroup_path != 'NX' then 
        Select array_agg(a.account_id) into vcogc_ac_ids
        From ac.account_head a 
        Inner Join ac.account_group b On a.group_id = b.group_id
        Where (b.group_path like (pgroup_path || '%') or pgroup_path = 'All');
    Else 
        Select array_agg(a.account_id) into vcogc_ac_ids
        From ac.account_head a 
        Inner Join ac.account_group b On a.group_id = b.group_id
        Where (b.group_path not like All('{A005%,A006%}'));
    End If;

    DROP TABLE IF EXISTS pur_reg;	
    create temp TABLE  pur_reg
    (	
        voucher_id varchar(50), 
        doc_date date,
        supplier_id bigint,
        supplier character varying,
        bill_no character varying,
        bill_date date,
        gstin character varying,
        gst_state character varying,
        vat_type_id bigint,
        vat_type_code character varying,
        bt_amt numeric(18,4),
        itc_amt numeric(18,4),
        non_itc_amt numeric(18,4),
        gst_amt numeric(18,4),
        gst_rate numeric(18,4)
    );
    Insert into pur_reg (voucher_id, doc_date, supplier_id, supplier, bill_no, bill_date, 
            gstin, 
            gst_state, vat_type_id, vat_type_code,
            bt_amt, itc_amt, non_itc_amt, gst_amt,
            gst_rate)
    select a.bill_id, a.doc_date, a.supplier_id, b.account_head, a.bill_no, a.bill_date, 
            (a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar as gstin, 
            (d.gst_state_code || '-' || d.state_code) as gst_state, a.vat_type_id, e.short_desc,
            sum(c.bt_amt) as bt_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else sum(case when c.apply_itc then (c.sgst_amt + c.cgst_amt + c.igst_amt) else 0 end) End as itc_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else sum(case when not c.apply_itc then (c.sgst_amt + c.cgst_amt + c.igst_amt) else 0 end) End as non_itc_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else sum(c.sgst_amt+c.cgst_amt+c.igst_amt) End as gst_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt) end as gst_rate
    from ap.bill_control a	
    inner join ac.account_head b on a.supplier_id = b.account_id
    inner join tx.gst_state d on (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = d.gst_state_id
    Inner join tx.vat_type e on a.vat_type_id = e.vat_type_id
    inner join ap.bill_tran f on a.bill_id = f.bill_id
    Inner join tx.gst_tax_tran c on f.bill_tran_id = c.gst_tax_tran_id
    Where a.company_id = pcompany_id
        and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
        And a.doc_date between pfrom_date and pto_date
        And ((a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = pgst_state_id or pgst_state_id = 0)
        And (a.supplier_id = psupplier_id or psupplier_id = 0)		
        And (f.account_id = Any(vcogc_ac_ids))
        And (pgroup_path != 'A001' or pgroup_path = 'All')
        And a.status = 5
        And case when pinclude_non_gst then 1=1 Else c.hsn_sc_code != 'NONGST' end
    Group by a.bill_id, a.supplier_id, b.account_head, d.state_code, d.gst_state_code,
            a.vat_type_id, e.short_desc, c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt, c.rc_sec_id
    Union All -- AP DebitCreditNote
    select a.voucher_id, a.doc_date, a.supplier_account_id, b.account_head, (a.annex_info->>'supp_ref_no')::varchar, (a.annex_info->>'supp_ref_date')::date, 
        (a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar as gstin, 
        (d.gst_state_code || '-' || d.state_code) as gst_state, (a.annex_info->'gst_input_info'->>'vat_type_id')::bigint, e.short_desc,        
        case when (a.annex_info->>'dcn_type')::int =1 then sum(c.bt_amt) else -1 * sum(c.bt_amt) end as bt_amt,
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 
        	else (case when (a.annex_info->>'dcn_type')::int =1 then sum(case when c.apply_itc then (c.sgst_amt + c.cgst_amt + c.igst_amt) else 0 end) else -1 * sum(case when c.apply_itc then (c.sgst_amt + c.cgst_amt + c.igst_amt) else 0 end) end) End as itc_amt,
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 
        	else (case when (a.annex_info->>'dcn_type')::int =1 then sum(case when not c.apply_itc then (c.sgst_amt + c.cgst_amt + c.igst_amt) else 0 end) else -1 * sum(case when not c.apply_itc then (c.sgst_amt + c.cgst_amt + c.igst_amt) else 0 end) end) End as non_itc_amt,        
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 
        	else (case when (a.annex_info->>'dcn_type')::int =1 then sum(c.sgst_amt+c.cgst_amt+c.igst_amt) else -1 * sum(c.sgst_amt+c.cgst_amt+c.igst_amt) end) End as gst_amt,
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt) end as gst_rate
    from ap.pymt_control a	
    inner join ac.account_head b on a.supplier_account_id = b.account_id
    inner join tx.gst_state d on (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = d.gst_state_id
    Inner join tx.vat_type e on (a.annex_info->'gst_input_info'->>'vat_type_id')::bigint  = e.vat_type_id
    inner join ap.pymt_tran f on a.voucher_id = f.voucher_id
    Inner join tx.gst_tax_tran c on f.vch_tran_id = c.gst_tax_tran_id
    Where a.company_id = pcompany_id
        and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
        And a.doc_date between pfrom_date and pto_date
        And ((a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = pgst_state_id or pgst_state_id = 0)
        And (a.supplier_account_id = psupplier_id or psupplier_id = 0)		
        And (f.account_id = Any(vcogc_ac_ids))
        And (pgroup_path != 'A001' or pgroup_path = 'All')
        And a.status = 5	
        And case when pinclude_non_gst then 1=1 Else c.hsn_sc_code != 'NONGST' end	
        And a.doc_type = 'DN2'	
    Group by a.voucher_id, a.supplier_account_id, b.account_head, d.state_code, d.gst_state_code,
        (a.annex_info->'gst_input_info'->>'vat_type_id')::bigint, e.short_desc, c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt, c.rc_sec_id
    Union All 
    Select a.voucher_id, a.doc_date, 99, 'Various Suppliers', (a.annex_info->>'bill_no')::varchar as bill_no, (a.annex_info->>'bill_date')::date as bill_date,
            (a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar as gstin, 
            (d.gst_state_code || '-' || d.state_code) as gst_state, (a.annex_info->'gst_input_info'->>'vat_type_id')::bigint, e.short_desc,
            sum(c.bt_amt) as bt_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 
            	else sum(case when c.apply_itc then (c.sgst_amt + c.cgst_amt + c.igst_amt) else 0 end) End as itc_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 
            	else sum(case when not c.apply_itc then (c.sgst_amt + c.cgst_amt + c.igst_amt) else 0 end) End as non_itc_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 
            	else sum(c.sgst_amt+c.cgst_amt+c.igst_amt) End as gst_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 
            	else (c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt) end as gst_rate
    From ac.vch_control a
    Inner Join ac.vch_tran b On a.voucher_id = b.voucher_id
    Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
    inner join tx.gst_state d on (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = d.gst_state_id
    Inner join tx.vat_type e on (a.annex_info->'gst_input_info'->>'vat_type_id')::bigint = e.vat_type_id
    Where  a.company_id = pcompany_id        	
        And (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        And a.doc_date Between pfrom_date And pto_date
        And ((a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = pgst_state_id or pgst_state_id = 0)
        And a.doc_type = Any('{PAYV, PAYC, PAYB}')	
        And (b.account_id = Any(vcogc_ac_ids))
        And a.status = 5
        And case when pinclude_non_gst then 1=1 Else c.hsn_sc_code != 'NONGST' end
        And psupplier_id =0
        And (pgroup_path != 'A001' or pgroup_path = 'All')	
    Group by a.voucher_id, d.state_code, d.gst_state_code, c.bt_amt,
            (a.annex_info->'gst_input_info'->>'vat_type_id')::bigint, e.short_desc, c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt, c.rc_sec_id
    Union All 	
    select a.ap_id, a.doc_date, a.account_id, b.account_head, a.bill_no, a.bill_date, 
        (a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar as gstin, 
        (d.gst_state_code || '-' || d.state_code) as gst_state, (a.annex_info->'gst_input_info'->>'vat_type_id')::bigint, e.short_desc,
        sum(c.bt_amt) as bt_amt,
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 
        	else sum(case when c.apply_itc then (c.sgst_amt + c.cgst_amt + c.igst_amt) else 0 end) End as itc_amt,
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 
        	else sum(case when not c.apply_itc then (c.sgst_amt + c.cgst_amt + c.igst_amt) else 0 end) End as non_itc_amt,
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 
        	else sum(c.sgst_amt+c.cgst_amt+c.igst_amt) End as gst_amt,
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 
        	else (c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt) end as gst_rate
    from fa.ap_control a	
    inner join ac.account_head b on a.account_id = b.account_id
    inner join tx.gst_state d on (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = d.gst_state_id
    Inner join tx.vat_type e on (a.annex_info->'gst_input_info'->>'vat_type_id')::bigint = e.vat_type_id
    inner join fa.ap_tran f on a.ap_id = f.ap_id
    inner join fa.asset_class g on f.asset_class_id = g.asset_class_id
    Inner join tx.gst_tax_tran c on f.ap_tran_id = c.gst_tax_tran_id
    Where a.company_id = pcompany_id
        And (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        And a.doc_date Between pfrom_date And pto_date
        And ((a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = pgst_state_id or pgst_state_id = 0)
        And (a.account_id = psupplier_id or psupplier_id = 0)			
        And (g.asset_account_id = Any(vcogc_ac_ids))
        And a.status = 5	
        And case when pinclude_non_gst then 1=1 Else c.hsn_sc_code != 'NONGST' end
        And (pgroup_path = 'A001' or pgroup_path = 'All')
    Group by a.ap_id, a.account_id, b.account_head, d.state_code, d.gst_state_code,
        (a.annex_info->'gst_input_info'->>'vat_type_id'), e.short_desc, c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt, c.rc_sec_id;

    with gtt 
    As 
    (	select a.stock_id, (c->>'bt_amt')::numeric as bt_amt, ((c->>'sgst_amt')::numeric + (c->>'cgst_amt')::numeric + (c->>'igst_amt')::numeric) as gst_amt, 
     			((c->>'sgst_pcnt')::numeric + (c->>'cgst_pcnt')::numeric + (c->>'igst_pcnt')::numeric) as gst_rate,
                (c->>'hsn_sc_code')::varchar hsn_sc_code, (a.annex_info->'gst_rc_info'->>'rc_sec_id')::smallint rc_sec_id,(c->>'apply_itc')::bool as apply_itc
            from st.stock_control a, jsonb_array_elements(a.annex_info->'gst_tax_tran') c
    )
    Insert into pur_reg (voucher_id, doc_date, supplier_id, supplier, bill_no, bill_date, 
            gstin, 
            gst_state, vat_type_id, vat_type_code,
            bt_amt, itc_amt, non_itc_amt, gst_amt, 
            gst_rate)
    select a.stock_id, a.doc_date, a.account_id, b.account_head, a.bill_no, a.bill_date, 
            (a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar as gstin, 
            (d.gst_state_code || '-' || d.state_code) as gst_state, a.vat_type_id, e.short_desc,
            case when a.doc_type in ('SP','SPG') then sum(c.bt_amt)	Else -1 * sum(c.bt_amt) End as bt_amt,  
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 
            	else (case when a.doc_type in ('SP','SPG') then sum(case when c.apply_itc then c.gst_amt else 0 end) Else -1 * sum(case when c.apply_itc then c.gst_amt else 0 end) End) End as itc_amt,
             case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 
             		else (case when a.doc_type in ('SP','SPG') then sum(case when not c.apply_itc then c.gst_amt else 0 end) Else -1 * sum(case when not c.apply_itc then c.gst_amt else 0 end) End) End as non_itc_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 
            	else (case when a.doc_type in ('SP','SPG') then sum(c.gst_amt) Else -1 * sum(c.gst_amt) End) End as gst_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 
            	else c.gst_rate end as gst_rate
    from st.stock_control a
    inner join ac.account_head b on a.account_id = b.account_id
    Inner join gtt c on a.stock_id = c.stock_id
    inner join tx.gst_state d on (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = d.gst_state_id
    Inner join tx.vat_type e on a.vat_type_id = e.vat_type_id
    Where a.company_id = pcompany_id
        and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
        And a.doc_date between pfrom_date and pto_date
        And ((a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = pgst_state_id or pgst_state_id = 0)
        And (a.account_id = psupplier_id or psupplier_id = 0)	
        And (a.sale_account_id = Any(vcogc_ac_ids))		
        And a.status = 5
        And case when pinclude_non_gst then 1=1 Else (c.hsn_sc_code != 'NONGST' or c.hsn_sc_code is Null) end
        And a.doc_type= Any('{SP, SPG, PR, PRV}')
        And (pgroup_path != 'A001' or pgroup_path = 'All')
    Group by a.stock_id, a.account_id, b.account_head, d.state_code, d.gst_state_code,c.apply_itc,
        a.vat_type_id, e.short_desc, c.gst_rate, c.rc_sec_id;

    -- Sales Purchase Return 
    Insert into pur_reg (voucher_id, doc_date, supplier_id, supplier, bill_no, bill_date, 
            gstin, 
            gst_state, vat_type_id, vat_type_code,
            bt_amt, itc_amt, non_itc_amt, gst_amt, 
            gst_rate)	
    select a.stock_id, a.doc_date, a.account_id, b.account_head, a.bill_no, a.bill_date, 
        (a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar as gstin, 
        (d.gst_state_code || '-' || d.state_code) as gst_state, a.vat_type_id, e.short_desc,
        case when (a.annex_info->>'dcn_type')::int =1 then sum(c.bt_amt) else -1 * sum(c.bt_amt) end as bt_amt,
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (case when (a.annex_info->>'dcn_type')::int =1 
                                then sum(case when c.apply_itc then (c.sgst_amt + c.cgst_amt + c.igst_amt) else 0 end) else -1 * sum(case when c.apply_itc then (c.sgst_amt + c.cgst_amt + c.igst_amt) else 0 end) end) End as itc_amt,
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (case when (a.annex_info->>'dcn_type')::int =1 
                                then sum(case when not c.apply_itc then (c.sgst_amt + c.cgst_amt + c.igst_amt) else 0 end) else -1 * sum(case when not c.apply_itc then (c.sgst_amt + c.cgst_amt + c.igst_amt) else 0 end) end) End as non_itc_amt,
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (case when (a.annex_info->>'dcn_type')::int =1 
                                then sum(c.sgst_amt+c.cgst_amt+c.igst_amt) else -1 * sum(c.sgst_amt+c.cgst_amt+c.igst_amt) end) End as gst_amt,
        case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 else (c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt) end as gst_rate        
    from st.stock_control a
    inner join ac.account_head b on a.account_id = b.account_id
    inner join st.stock_tran f on a.stock_id = f.stock_id
    Inner join tx.gst_tax_tran c on f.stock_tran_id = c.gst_tax_tran_id
    inner join tx.gst_state d on (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = d.gst_state_id
    Inner join tx.vat_type e on a.vat_type_id = e.vat_type_id
    Where a.company_id = pcompany_id
        and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
        And a.doc_date between pfrom_date and pto_date
        And ((a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = pgst_state_id or pgst_state_id = 0)
        And (a.account_id = psupplier_id or psupplier_id = 0)	
        And (a.sale_account_id = Any(vcogc_ac_ids))		
        And a.status = 5
        And case when pinclude_non_gst then 1=1 Else c.hsn_sc_code != 'NONGST' end
        And a.doc_type= Any('{PR, PRV}')
        And (pgroup_path != 'A001' or pgroup_path = 'All')
    Group by a.stock_id, a.account_id, b.account_head, d.state_code, d.gst_state_code,
        a.vat_type_id, e.short_desc, (c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt), c.rc_sec_id;

    if exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'abp_control') then
        Insert into pur_reg (voucher_id, doc_date, supplier_id, supplier, bill_no, bill_date, gstin, gst_state, vat_type_id, vat_type_code,
                bt_amt, itc_amt, non_itc_amt, gst_amt, gst_rate)
        select a.voucher_id, a.doc_date, a.account_id, b.account_head, a.bill_no, a.bill_date,
                (a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar as gstin, 
                (d.gst_state_code || '-' || d.state_code) as gst_state, a.vat_type_id, e.short_desc,
                sum(c.bt_amt) as bt_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 
            		else sum(case when c.apply_itc then (c.sgst_amt + c.cgst_amt + c.igst_amt) else 0 end) End as itc_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 
            		else sum(case when not c.apply_itc then (c.sgst_amt + c.cgst_amt + c.igst_amt) else 0 end) End as non_itc_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 
            		else sum(c.sgst_amt + c.cgst_amt + c.igst_amt) End as gst_amt,
            case when char_length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2 And c.rc_sec_id = 94 then 0 
            		else (c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt) end as gst_rate
        from pub.abp_control a
        inner join ac.account_head b on a.account_id = b.account_id
        Inner join tx.gst_tax_tran c on a.voucher_id = c.voucher_id
        inner join tx.gst_state d on (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = d.gst_state_id
        Inner join tx.vat_type e on a.vat_type_id = e.vat_type_id
        Where a.company_id = pcompany_id
            and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
            And a.doc_date between pfrom_date and pto_date
            And ((a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = pgst_state_id or pgst_state_id = 0)
            And (a.account_id = psupplier_id or psupplier_id = 0)
            And (a.payable_account_id = Any(vcogc_ac_ids))		
            And a.status = 5
            And case when pinclude_non_gst then 1=1 Else c.hsn_sc_code != 'NONGST' end
            And (pgroup_path != 'A001' or pgroup_path = 'All')
        Group by a.voucher_id, a.account_id, b.account_head, d.state_code, d.gst_state_code,
            a.vat_type_id, e.short_desc, c.sgst_pcnt + c.cgst_pcnt + c.igst_pcnt, c.rc_sec_id;
    End If;

    return query 
    select a.voucher_id, a.doc_date, a.supplier_id, a.supplier, a.bill_no, a.bill_date, 
    		a.gstin, a.gst_state, a.vat_type_id, 
            (case when a.vat_type_code='Interstate' then 'I' else 'L' end)::varchar(1) as vat_type_code,
            a.bt_amt, a.itc_amt, a.non_itc_amt, a.gst_amt, a.gst_rate
    from pur_reg a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
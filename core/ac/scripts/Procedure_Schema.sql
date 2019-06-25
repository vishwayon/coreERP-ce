CREATE FUNCTION sys.fn_pad_id(pid bigint, ppad_len smallint)
RETURNS VARCHAR(20)
AS
$BODY$
declare vResult varchar(20)=''; vActualLen smallint = 0;
Begin
        -- ********* Pad function to get fixed length ******
	select cast(pid as varchar) into vResult;
	select length(cast(pid as varchar)) into vActualLen;
	if vActualLen < ppad_len Then
		Select overlay(cast(pid as varchar) placing repeat('0', ppad_len-vActualLen) from 1 for 0) into vResult;
	End If;
	return vResult;
END
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.sp_gl_create_id (pvoucher_id Varchar(50), pbranch_id BigInt, paccount_id bigint, pacc_affected_id bigint)
RETURNS uuid
AS
$BODY$
declare vGL_ID varchar(100); 
BEGIN
        -- Procedure to create General Ledger ID
	select pvoucher_id || ':' || pbranch_id || ':' || paccount_id || ':' || pacc_affected_id into vGL_ID;
	return md5(vGL_ID)::uuid;
END
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.sp_gl_post_data(
    IN ptable_name character varying,
    IN pvoucher_id character varying)
  RETURNS TABLE(index integer, company_id bigint, branch_id bigint, dc character, account_id bigint, debit_amt_fc numeric, credit_amt_fc numeric, debit_amt numeric, credit_amt numeric) AS
$BODY$ 
    Declare vApply_rc Boolean := false; vRoundOffAcc_ID bigint = -1; vrc_sec_id BigInt := -1; vis_reg_supp Boolean := false;
    		vLine_item_gst boolean := false;
Begin	
	DROP TABLE IF EXISTS vch_detail_temp2;	
	create temp TABLE  vch_detail_temp2
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

	If ptable_name ='ac.vch_mcj_control' then 
		Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
		Select a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
		from ac.fn_vch_mcj_info_for_gl_post(pvoucher_id) a;
	End If;

	If ptable_name ='ac.vch_control' And Left(pvoucher_ID, 4) != 'SIRC' then 
            -- Fetch Control
            Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
            Select a.company_id, a.branch_id, case when a.credit_amt >0 then 'C' else 'D' End, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
            From ac.vch_control a
            Where voucher_id=pvoucher_ID;

            -- Fetch Tran			
            Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
            Select sys.fn_get_company_id(a.branch_id), a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
            From ac.vch_tran a
            Where a.voucher_id=pvoucher_ID;

            -- Round Off
            Select cast(value as bigint) into vRoundOffAcc_ID from sys.settings where key='st_round_off_account';		
            Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
            Select a.company_id, a.branch_id, 'D', vRoundOffAcc_ID, 
                    0, 0, 
                    case when (a.annex_info->>'round_off_amt')::numeric > 0 Then (a.annex_info->>'round_off_amt')::numeric Else 0 End, 
                    case when (a.annex_info->>'round_off_amt')::numeric < 0 Then -(a.annex_info->>'round_off_amt')::numeric Else 0 End
            From ac.vch_control a
            Where a.voucher_id=pvoucher_ID
                    And (a.annex_info->>'round_off_amt')::numeric != 0;
			
            -- Fetch GST related info
            -- Fetch GST Tax Tran (ITC)		
            Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
            Select a.company_id, a.branch_id, 'D', b.account_id, 0, 0, coalesce(sum(b.tax_amt), 0), 0
            From ac.vch_control a
            Inner Join tx.fn_gtt_itc_info(pvoucher_ID, 'ac.vch_tran', '{-1}'::BigInt[]) b on a.voucher_id = b.voucher_id
            Where a.voucher_id=pvoucher_ID
            group by a.company_id, a.branch_id, b.account_id;

            -- Fetch GST Tax Tran (Non-ITC)
            Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
            Select a.company_id, b.branch_id, 'D', b.account_id, 0, 0, c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt, 0
            From ac.vch_control a
            Inner Join ac.vch_tran b On a.voucher_id = b.voucher_id
            Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
            Where a.voucher_id=pvoucher_ID And c.apply_itc = False 
                And c.rc_sec_id = -1
                And c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt > 0;

            Select (a.annex_info->>'line_item_gst')::boolean, (a.annex_info->'gst_rc_info'->>'apply_rc')::boolean, (a.annex_info->'gst_rc_info'->>'rc_sec_id')::BigInt,
                    length(coalesce(a.annex_info->'gst_input_info'->>'supplier_gstin', '')) = 15
            Into vLine_item_gst, vApply_rc, vrc_sec_id, vis_reg_supp
            From ac.vch_control a
            Where a.voucher_id = pvoucher_id;

            If vLine_item_gst = false And vApply_rc And vis_reg_supp And vrc_sec_id In (93, 53) then
                -- Reverse Charge Calculations
                -- Fetch Cumpolsory Reverse Charge u/s 9(3), 5(3)
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, 'C', b.account_id, 0, 0, 0, coalesce(sum(b.tax_amt), 0)
                From ac.vch_control a
                Inner Join tx.fn_gtt_rc_info(pvoucher_ID, 'ac.vch_tran', '{93,53}'::BigInt[], false) b on a.voucher_id = b.voucher_id
                Where a.voucher_id=pvoucher_ID
                Group by a.company_id, a.branch_id, b.account_id;

                -- Fetch GST Tax Tran (ITC)		
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, 'D', b.account_id, 0, 0, coalesce(sum(b.tax_amt), 0), 0
                From ac.vch_control a
                Inner Join tx.fn_gtt_itc_info(pvoucher_ID, 'ac.vch_tran', '{93,53}'::BigInt[], false) b on a.voucher_id = b.voucher_id
                Where a.voucher_id=pvoucher_ID
                group by a.company_id, a.branch_id, b.account_id;

                -- Fetch GST Tax Tran (Non-ITC)
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, b.branch_id, 'D', b.account_id, 0, 0, c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt, 0
                From ac.vch_control a
                Inner Join ac.vch_tran b On a.voucher_id = b.voucher_id
                Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
                Where a.voucher_id=pvoucher_ID And c.apply_itc = False 
                    And c.rc_sec_id = Any('{93,53}'::BigInt[])
                    And c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt > 0;
            Else 
                -- Reverse Charge Calculations
                -- Fetch Cumpolsory Reverse Charge u/s 9(3), 5(3)
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, 'C', b.account_id, 0, 0, 0, coalesce(sum(b.tax_amt), 0)
                From ac.vch_control a
                Inner Join tx.fn_gtt_rc_info(pvoucher_ID, 'ac.vch_tran', '{93,53}'::BigInt[], true) b on a.voucher_id = b.voucher_id
                Where a.voucher_id=pvoucher_ID
                Group by a.company_id, a.branch_id, b.account_id;

                -- Fetch GST Tax Tran (ITC)		
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, 'D', b.account_id, 0, 0, coalesce(sum(b.tax_amt), 0), 0
                From ac.vch_control a
                Inner Join tx.fn_gtt_itc_info(pvoucher_ID, 'ac.vch_tran', '{93,53}'::BigInt[], true) b on a.voucher_id = b.voucher_id
                Where a.voucher_id=pvoucher_ID
                group by a.company_id, a.branch_id, b.account_id;

                -- Fetch GST Tax Tran (Non-ITC)
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, b.branch_id, 'D', b.account_id, 0, 0, c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt, 0
                From ac.vch_control a
                Inner Join ac.vch_tran b On a.voucher_id = b.voucher_id
                Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
                Where a.voucher_id=pvoucher_ID And c.apply_itc = False 
                    And c.rc_sec_id = Any('{93,53}'::BigInt[])
                    And c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt > 0
                    And length(c.supplier_gstin) = 15;
            End If;
        End If;
        If ptable_name ='ac.vch_control' And Left(pvoucher_ID, 4) = 'SIRC' then
            -- Fetch debits (non ITC)
            Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
            Select a.company_id, b.branch_id, 'D', b.account_id, 0, 0, c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt, 0
            From ac.vch_control a
            Inner Join ac.si_tran b On a.voucher_id = b.voucher_id
            Inner Join tx.gst_tax_tran c On b.si_tran_id = c.gst_tax_tran_id
            Where a.voucher_id=pvoucher_ID And c.apply_itc = False
                And c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt > 0;

            -- Fetch Debits ITC
            Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
            Select a.company_id, a.branch_id, 'D', b.account_id, 0, 0, coalesce(sum(b.tax_amt), 0), 0
            From ac.vch_control a
            Inner Join tx.fn_gtt_itc_info(pvoucher_ID, '', '{93,94,53,54}'::BigInt[]) b on a.voucher_id =b.voucher_id
            Where a.voucher_id=pvoucher_ID
            group by a.company_id, a.branch_id, b.account_id;

            -- Fetch Credits (RC Liability All)
            Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
            Select a.company_id, a.branch_id, 'C', b.account_id, 0, 0, 0, coalesce(sum(b.tax_amt), 0)
            From ac.vch_control a
            Inner Join tx.fn_gtt_rc_info(pvoucher_ID, 'ac.si_tran', '{93,94,53,54}'::BigInt[]) b on a.voucher_id = b.voucher_id
            Where a.voucher_id=pvoucher_ID
            Group by a.company_id, a.branch_id, b.account_id;

        End If;        
        If ptable_name = 'fa.ap_control' then
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
                from fa.fn_ap_acc_info_for_gl_post(pvoucher_id) a;
        End If;
        If ptable_name = 'fa.ad_control' then
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
                from fa.fn_ad_acc_info_for_gl_post(pvoucher_id) a;
        End If;
        If ptable_name ='ap.bill_control' then
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
                from ap.fn_bill_info_for_gl_post(pvoucher_id) a;
        End If;
        If ptable_name = 'fa.as_control' then
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
                from fa.fn_as_acc_info_for_gl_post(pvoucher_id) a;
        End If;
        If ptable_name = 'ap.pymt_control' then
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
                from ap.fn_pymt_info_for_gl_post(pvoucher_id) a;
        End If;
        If ptable_name = 'ar.invoice_control' then
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
                from ar.fn_invoice_info_for_gl_post(pvoucher_id) a;
        End If;
        If ptable_name = 'ar.rcpt_control' then
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
                from ar.fn_rcpt_info_for_gl_post(pvoucher_id) a;
        End If;
        If ptable_name = 'st.stock_control' then
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
                from st.fn_stock_info_for_gl_post(pvoucher_id) a;
        End If;
        If ptable_name = 'tds.bill_tds_tran' then
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
                from tds.fn_tds_info_for_gl_post(pvoucher_id) a;
        End If;
        If ptable_name = 'tds.tds_payment_control' then
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
                from tds.fn_tdpy_info_for_gl_post(pvoucher_id) a;
        End If;
        If ptable_name = 'ar.rcpt_control_gns' then
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
                from ar.fn_rcpt_info_for_gl_post_gns(pvoucher_id) a;
        End If;
        If ptable_name = 'hr.payroll_control' then
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
                from hr.fn_payroll_info_for_gl_post(pvoucher_id) a;
        End If;
        If ptable_name = 'pos.inv_control' Or ptable_name = 'pos.inv_bb' Then
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
                from pos.fn_gl_post_data(pvoucher_id, ptable_name) a;
        End If;
        If ptable_name = 'st.stock_transfer_park_post' Then
                Insert into vch_detail_temp2(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
                Select a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
                from st.fn_st_park_post_info_for_gl_post(pvoucher_id) a;
        End If;

        return query 
        select a.index, a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
        from vch_detail_temp2 a;
END;
$BODY$
LANGUAGE plpgsql;

?==?

CREATE OR REPLACE FUNCTION ac.fn_gl_post_exch_diff(IN pvoucher_id character varying)
  RETURNS TABLE(index integer, company_id bigint, branch_id bigint, dc character, account_id bigint, debit_amt_fc numeric, credit_amt_fc numeric, debit_amt numeric, credit_amt numeric) AS
$BODY$ 
	Declare vDiscountFC numeric(18,4)=0; vDiscount numeric(18,4)= 0; vTotalDebitFC numeric(18,4) = 0; vTotalDebit numeric(18,4) =0;
	vCompany_ID bigint =-1; vBranch_ID bigint = -1; vAccount_ID bigint =-1; vExchAccount_ID bigint=-1;
	
Begin	
	-- This function is used by the Posting Trigger to get information on the Supplier Payment (PYMT)
	DROP TABLE IF EXISTS exch_diff_detail;	
	create temp TABLE  exch_diff_detail
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

	-- 	****	Step 1: Create a temp summary Table
	DROP TABLE IF EXISTS exch_diff_temp;	
	create temp TABLE  exch_diff_temp
	(	
		voucher_id varchar(50),
		branch_id bigint,
		account_id bigint,
		exch_diff numeric(18,4)
	);

	-- 	****	Step 2: Extract Sum of Exch Diff (Debits are as is, credits are reversed)
	Insert into exch_diff_temp(voucher_id, branch_id, account_id, exch_diff)
	Select a.voucher_id, a.branch_id, a.account_id, sum(a.exch_diff)
	From (
		Select a.voucher_id, a.branch_id, a.account_id, (debit_exch_diff + (-1 * credit_exch_diff)) as exch_diff
		From ac.rl_pl_alloc a
		where a.voucher_id=pvoucher_id
		Union All 
		Select a.voucher_id, a.branch_id, a.account_id, (debit_exch_diff + (-1 * credit_exch_diff)) as exch_diff
		From ac.rl_pl_alloc a
		where a.voucher_id=pvoucher_id
	) a
	Group By a.voucher_id, a.branch_id, a.account_id
	having sum(a.exch_diff) <> 0;

	--	****	Step 3: Proceed only if there are records
	If exists (Select * from exch_diff_temp) Then
		--  ****	Extract Exch Diff Account 
		Select cast(value as varchar) into vExchAccount_ID from sys.settings where key='ac_exch_diff_account';

		--  ****	Step 4: Create final summary table for customer supplier account
		Insert into exch_diff_detail(company_id, branch_id, dc, 
			account_id, debit_amt_fc, credit_amt_fc, 
			debit_amt, credit_amt)
		Select sys.fn_get_company_id(a.branch_id), a.branch_id, Case When coalesce(sum(a.exch_diff), 0) > 0 then 'D' Else 'C' End,
			a.account_id, 0, 0, 
			Case When coalesce(sum(a.exch_diff), 0) > 0 then coalesce(sum(a.exch_diff), 0) else 0 End as debit_amt,
			Case When coalesce(sum(a.exch_diff), 0) < 0 then coalesce(sum(a.exch_diff), 0) * -1 else 0 End as credit_amt
		From exch_diff_temp a
		Group By a.branch_id, a.account_id;

		--  ****	Step 5: Creat Final Summary table for exch diff account (reverse the inserted)
		Insert into exch_diff_detail(company_id, branch_id, dc, account_id, 
			debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
		Select a.company_id, a.branch_id, Case When a.dc = 'D' then 'C' else 'D' End, vExchAccount_ID, 
			a.credit_amt_fc, a.debit_amt_fc, a.credit_amt, a.debit_amt
		From exch_diff_detail a;
	End If;

	return query 
	select a.index, a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
	from exch_diff_detail a;
END;
$BODY$
  LANGUAGE plpgsql;


?==?
CREATE OR REPLACE function ac.sp_gl_post(ptable_name Varchar(150), pdoc_year varchar(4), pvoucher_id Varchar(50), pvoucher_date Date, pfc_type_id bigint, pexch_rate numeric(18,6),
						pcheque_details varchar(250), pnarration Varchar(500), pdoc_type varchar(4))

  RETURNS void AS
$BODY$
Declare 
	vDr int4=0; vCr int4=0; vDebitTotal numeric(18,4) =0; vCreditTotal numeric(18,4) =0; vCreditFCTotal numeric(18,4) =0; vDebitFCTotal numeric(18,4) =0;
	vAccountAffected_ID bigint=0; vIndexCount bigint =0; 
	vBranchCount int4 =0; vCompanyCount int4 =0; vSourceBranch_ID bigint=0; vIBVoucher_ID varchar(50)=''; vCBranch_ID bigint =0 ; vMsg varchar(250) = '';
	
Begin
	/* WARNING THIS PROCEDURE IS AUTOMATICALLY CALLED BY A TRIGGER. CALLING THIS PROCEDURE MANUALLY IS PROHIBITED
	*/

	-- Create table to hold Voucher Data
	DROP TABLE IF EXISTS vch_detail_temp1;
	create temp TABLE  vch_detail_temp1
	(	
		index int4, 
		company_id bigint,
		branch_id bigint,
		dc char(1),
		account_id bigint,
		debit_amt_fc numeric(18,4),
		credit_amt_fc numeric(18,4),
		debit_amt numeric(18,4),
		credit_amt numeric(18,4)
	);

	-- ****	Fetch Vch Table Information
	Insert into vch_detail_temp1(index, company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
	select index, company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt
	from ac.sp_gl_post_data(ptable_name, pvoucher_id)
	where debit_amt<>0 or credit_amt<>0;

	-- ****	Verify that the Accounts are valid Accounts
	if exists(select * from vch_detail_temp1 where account_id<=0) then
		RAISE EXCEPTION 'GL Posting data contained an invalid account (ID<=0). Failed to Post'; 
		return;
	End IF;

	-- **** Fetch Exch Rate Diff 
	Select count(index) into vIndexCount from vch_detail_temp1;
	
	Insert into vch_detail_temp1(index, company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
	select index + vIndexCount, company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt
	from ac.fn_gl_post_exch_diff(pvoucher_id);

	-- Verify that the exch diff account is associated properly
	if Exists(Select * from vch_detail_temp1 Where account_id=-1 and index > vIndexCount) Then	
		RAISE EXCEPTION 'Exchange Fluctuation Account not Associated. Failed to Post.'; 
		return;
	End If;

	-- ****	Get the Debit and Credit Totals and Exit if the do not match
	
	Select coalesce(Sum(debit_amt), 0), coalesce(Sum(credit_amt), 0)
	into vDebitTotal, vCreditTotal
	From vch_detail_temp1;
	
	if vDebitTotal <>  vCreditTotal Then	
		RAISE EXCEPTION 'Total mismatch on server (sp_gl_post). Failed to Post/Authorise.'; 
		return;
	End If;

	-- ****	Prohibit posting a blank voucher
	If vDebitTotal=0 and vCreditTotal=0 Then	
		RAISE EXCEPTION 'Function sp_gl_post_data did not return any result. Failed to Post/Authorise.'; 
		return;
	End If;


	Select count(*) into vBranchCount From (Select count(*) cnt from vch_detail_temp1 group by branch_id) a;
	Select count(*) into vCompanyCount From (Select count(*) cnt from vch_detail_temp1 group by company_id) a;
	
	Select branch_id into vSourceBranch_ID from vch_detail_temp1 where index=1;

	if vCompanyCount=1 then
		-- **** Simple InterBranch/Normal
		-- **** Temp table to hold data before summary
                DROP TABLE IF EXISTS ib_vch_table_temp;
		create temp TABLE  ib_vch_table_temp
		(	
			company_id bigint,
			branch_id bigint,
			dc char(1),
			account_id bigint,
			debit_amt_fc numeric(18,4),
			credit_amt_fc numeric(18,4),
			debit_amt numeric(18,4),
			credit_amt numeric(18,4)
		);

		-- **** Data in this table would be used for Posting
                DROP TABLE IF EXISTS ib_vch_table;
		create temp TABLE  ib_vch_table
		(	
			company_id bigint,
			branch_id bigint,
			dc char(1),
			account_id bigint,
			debit_amt_fc numeric(18,4),
			credit_amt_fc numeric(18,4),
			debit_amt numeric(18,4),
			credit_amt numeric(18,4)
		);
	
		DECLARE
		    cursor_vch_table CURSOR FOR SELECT distinct branch_id FROM vch_detail_temp1;
		    vCBranch_ID int;
		BEGIN
		    FOR branch_id IN cursor_vch_table LOOP
			-- **** Step 1: Insert Records Into ib_vch_table_temp
			Insert into ib_vch_table_temp(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
			Select company_id, a.branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt
			from vch_detail_temp1 a
			where a.branch_id=branch_id.branch_id;

			-- **** Step 2: Insert into the table Inter Branch Acount for each other branch		
			If vSourceBranch_ID = branch_id.branch_id  then		
				Insert into ib_vch_table_temp(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
				Select company_id, branch_id.branch_id, dc, ac.fn_ib_account_get(a.branch_id), debit_amt_fc, credit_amt_fc, debit_amt, credit_amt
				from vch_detail_temp1 a
				where a.branch_id<>branch_id.branch_id;
			Else		
				Insert into ib_vch_table_temp(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
				Select company_id, branch_id.branch_id, Case when dc ='D' Then 'C' else 'D' end, ac.fn_ib_account_get(vSourceBranch_ID), credit_amt_fc, debit_amt_fc, credit_amt, debit_amt
				from vch_detail_temp1 a
				where a.branch_id=branch_id.branch_id;
			End If;

			-- **** Validation for Interbranch control account
			if exists(select * from ib_vch_table_temp where account_id=0) then
				Select 'Document is Interbranch. However, the InterBranch control A/C has not been associated for ' || branch_name || '. Failed to Post/Authorise.' into vMsg
				From sys.branch_detail 
				where branch_id= ( select branch_id from ib_vch_table_temp where account_id=0 limit 1);
				RAISE EXCEPTION '%', vMsg;
				return;
			End IF;

			-- **** Step 3: Summarise the Data for Posting		
			Insert into ib_vch_table (company_id, branch_id, dc, account_id, 
						debit_amt_fc, 
						credit_amt_fc, debit_amt, credit_amt)
			Select company_id, a.branch_id, Case When sum(debit_amt) >= sum(credit_amt) Then 'D' Else 'C' End, account_id, 
				Case When sum(debit_amt_fc) > sum(credit_amt_fc) Then sum(debit_amt_fc) - sum(credit_amt_fc) Else 0 End,
				Case When sum(debit_amt_fc) < sum(credit_amt_fc) Then (sum(debit_amt_fc) - sum(credit_amt_fc)) * -1 Else 0 End,
				Case When sum(debit_amt) > sum(credit_amt) Then sum(debit_amt) - sum(credit_amt) Else 0 End,
				Case When sum(debit_amt) < sum(credit_amt) Then (sum(debit_amt) - sum(credit_amt)) * -1 Else 0 End
			From ib_vch_table_temp a
			group by company_id, a.branch_id, account_id;

			-- Remove unnecessary entries with all amounts as zero
			Delete from ib_vch_table
			Where debit_amt_fc=0 And credit_amt_fc=0 And debit_amt=0 And credit_amt=0;

			-- **** Step 4: Ensure that Interbranch Voucher ID is concatenated with AJ:
			if not exists(select * from ib_vch_table a where a.branch_id=vSourceBranch_ID limit 1) then
				Select pvoucher_id || ':AJ' into vIBVoucher_ID;
			Else
				Select pvoucher_id into vIBVoucher_ID;
			End If;

			-- **** Step 5: Post the Entry just like normal entry
			Select count(*) into vDr from ib_vch_table where dc='D';
			Select count(*) into vCr from ib_vch_table where dc='C';
			
			If vDr=1 And vCr=1 Then
				Select account_id into vAccountAffected_ID from ib_vch_table  where dc='C';

				-- **** Insert Control			
				Insert into ac.general_ledger(general_ledger_id, company_id, branch_id, finyear, voucher_id, doc_date, account_id, account_affected_id, fc_type_id,
							exch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, narration, cheque_details)
				select sys.sp_gl_create_id(pvoucher_id, a.branch_id, account_id, vAccountAffected_ID), company_id, a.branch_id, pdoc_year, pvoucher_id, pvoucher_date, account_id, vAccountAffected_ID, pfc_type_id,
							pexch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, pnarration, pcheque_details
				from ib_vch_table a
				where dc='D';

				-- **** Insert Tran ( swap Debits, Credits and Account_ID)		
				Insert into ac.general_ledger(general_ledger_id, company_id, branch_id, finyear, voucher_id, doc_date, account_id, account_affected_id, fc_type_id,
							exch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, narration, cheque_details)
				select sys.sp_gl_create_id(pvoucher_id, a.branch_id, vAccountAffected_ID, account_id), company_id, a.branch_id, pdoc_year, pvoucher_id, pvoucher_date, vAccountAffected_ID, account_id, pfc_type_id,
							pexch_rate, credit_amt_fc, debit_amt_fc, credit_amt, debit_amt, pnarration, pcheque_details
				from ib_vch_table a
				where dc='D';
			ELSEIf vDr > 1 And vCr=1 Then
				Select account_id into vAccountAffected_ID from ib_vch_table  where dc='C';

				-- **** Insert Control			
				Insert into ac.general_ledger(general_ledger_id, company_id, branch_id, finyear, voucher_id, doc_date, account_id, account_affected_id, fc_type_id,
							exch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, narration, cheque_details)
				select sys.sp_gl_create_id(pvoucher_id, a.branch_id, account_id, vAccountAffected_ID), company_id, a.branch_id, pdoc_year, pvoucher_id, pvoucher_date, account_id, vAccountAffected_ID, pfc_type_id,
							pexch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, pnarration, pcheque_details
				from ib_vch_table a
				where dc='D';

				-- **** Insert Tran ( swap Debits, Credits and Account_ID)		
				Insert into ac.general_ledger(general_ledger_id, company_id, branch_id, finyear, voucher_id, doc_date, account_id, account_affected_id, fc_type_id,
							exch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, narration, cheque_details)
				select sys.sp_gl_create_id(pvoucher_id, a.branch_id, vAccountAffected_ID, account_id), company_id, a.branch_id, pdoc_year, pvoucher_id, pvoucher_date, vAccountAffected_ID, account_id, pfc_type_id,
							pexch_rate, credit_amt_fc, debit_amt_fc, credit_amt, debit_amt, pnarration, pcheque_details
				from ib_vch_table a
				where dc='D';
			ELSEIf vDr=1 And vCr > 1 Then
				Select account_id into vAccountAffected_ID from ib_vch_table  where dc='D';

				-- **** Insert Control			
				Insert into ac.general_ledger(general_ledger_id, company_id, branch_id, finyear, voucher_id, doc_date, account_id, account_affected_id, fc_type_id,
							exch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, narration, cheque_details)
				select sys.sp_gl_create_id(pvoucher_id, a.branch_id, account_id, vAccountAffected_ID), company_id, a.branch_id, pdoc_year, pvoucher_id, pvoucher_date, account_id, vAccountAffected_ID, pfc_type_id,
							pexch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, pnarration, pcheque_details
				from ib_vch_table a
				where dc='C';

				-- **** Insert Tran ( swap Debits, Credits and Account_ID)		
				Insert into ac.general_ledger(general_ledger_id, company_id, branch_id, finyear, voucher_id, doc_date, account_id, account_affected_id, fc_type_id,
							exch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, narration, cheque_details)
				select sys.sp_gl_create_id(pvoucher_id, a.branch_id, vAccountAffected_ID, account_id), company_id, a.branch_id, pdoc_year, pvoucher_id, pvoucher_date, vAccountAffected_ID, account_id, pfc_type_id,
							pexch_rate, credit_amt_fc, debit_amt_fc, credit_amt, debit_amt, pnarration, pcheque_details
				from ib_vch_table a
				where dc='C';
			ELSEIf vDr > 1 And vCr > 1 Then
				Select 0 into vAccountAffected_ID;

				-- **** Insert Control			
				Insert into ac.general_ledger(general_ledger_id, company_id, branch_id, finyear, voucher_id, doc_date, account_id, account_affected_id, fc_type_id,
							exch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, narration, cheque_details)
				select sys.sp_gl_create_id(pvoucher_id, a.branch_id, account_id, vAccountAffected_ID), company_id, a.branch_id, pdoc_year, pvoucher_id, pvoucher_date, account_id, vAccountAffected_ID, pfc_type_id,
							pexch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, pnarration, pcheque_details
				from ib_vch_table a;
			ELSE			
				RAISE EXCEPTION 'Inadequate number of Debit and Credit entries found (IB Posting). Failed to Post/Authorise'; 
				return;
			End If;

			-- **** Last Step: Clear ib_vch_table and ib_vch_table_temp
			Delete from ib_vch_table;
			Delete from ib_vch_table_temp;
			Select 0 , 0 into vDr, vCr;
			
		    END LOOP;
		END;
		
		select coalesce(Sum(debit_amt), 0), coalesce(Sum(credit_amt), 0) into vDebitTotal, vCreditTotal
		from ac.general_ledger 
		where voucher_id=pvoucher_id or voucher_id=vIBVoucher_ID;
		
		if vDebitTotal<>vCreditTotal then			
			RAISE EXCEPTION 'After Verification of Posting resulted in Total Mismatch. Failed to Post/Authorise'; 
			return;	
		End If;
	Else		
		-- **** Inter Company Posting	
		RAISE EXCEPTION 'After Verification of Posting resulted in Total Mismatch. Failed to Post/Authorise'; 
		return;	
	End If;
	DROP TABLE IF EXISTS vch_detail_temp1;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
-- Procedure to unpost Voucher
CREATE OR REPLACE function ac.sp_gl_unpost(pvoucher_id Varchar(50))
Returns void as
$Body$
Begin
	--	Delete Accounts Ledger
	Delete from ac.general_ledger where voucher_id in (pVoucher_ID, pVoucher_ID || ':AJ');
END;
$Body$
LANGUAGE plpgsql; 

?==?
CREATE or REPLACE FUNCTION ac.trgporc_vch_post() 
RETURNS trigger 
AS $BODY$
Declare vFinYear varchar(4); vVoucher_ID varchar(50)=''; vFCType_ID BigInt=0; vExchRate Numeric(18,6)=1; vDocDate Date;
        vStatus smallint=0; vOldStatus smallint; vChequeDetails varchar(250)=''; vNarration Varchar(500):= '';
        vIsPDC boolean=false; vBankCharges numeric(18,4)=0; vType varchar(40)=''; vEnBankChargeType smallint=2; vOldBRV_ID varchar(50)='';
        vBillNo varchar(50)  =''; vBillDate date; vEnBillType smallint=0;
        vIsReversal boolean = false; vReversalDate date; vReferalVoucher_ID varchar(50);
        vPayVTranDesc Character Varying:='';
        vCompany_ID bigint; vBranch_ID bigint;
BEGIN
	-- **** Get the Existing and new values in the table    
	Select NEW.status, OLD.status, NEW.finyear, NEW.doc_date, NEW.voucher_id, NEW.fc_type_id, NEW.exch_rate, substring(NEW.narration from 0 for 500), NEW.doc_type, 
    			NEW.is_pdc, NEW.bank_charges, NEW.company_id, NEW.branch_id
            Into vStatus, vOldStatus, vFinYear, vDocDate, vVoucher_ID, vFCType_ID, vExchRate, vNarration, vType, 
            	vIsPDC, vBankCharges, vCompany_ID, vBranch_ID;
			
	Select NEW.voucher_id || ':AJ', NEW.is_reversal, NEW.reversal_date into vReferalVoucher_ID, vIsReversal, vReversalDate;
		
	-- ***** Unpost the voucher  
	If vStatus<=4 and vOldStatus=5 then
            If vType='SAJ' then -- Sub Head Adjustment Journal
                -- ***** Change status in Sub Head Ledger
                Delete From ac.sub_head_ledger
                Where voucher_id = vVoucher_id;
            Else
                perform ac.sp_gl_unpost(vVoucher_ID);

                If vType='CV' then
                    Delete from ac.cv_reconciled where voucher_id=vVoucher_ID;
                End if;

                If vType='MCJ' then
                    perform ac.sp_gl_unpost(vReferalVoucher_ID);
                End If;

                -- ***** Change status in Sub Head Ledger
                update ac.sub_head_ledger
                set status = vStatus
                Where voucher_id = vVoucher_id;

                -- ***** Change status in Ref Ledger
                update ac.ref_ledger
                set status = vStatus
                Where voucher_id = vVoucher_id;

                -- ***** Change status in Ref Ledger Alloc
                update ac.ref_ledger_alloc
                set status = vStatus
                Where affect_voucher_id = vVoucher_id;	
            
            End If;
        End if;

        If vStatus=5 and vOldStatus<=4 then
            If vType='SAJ' then -- Sub Head Adjustment Journal
                -- Post entry in subhead ledger
                Insert into ac.sub_head_ledger(sub_head_ledger_id, company_id, branch_id, finyear, voucher_id, vch_tran_id, doc_date,
                            account_id, sub_head_id, fc_type_id, exch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt,
                            narration, status, not_by_alloc)
                Select md5(a.vch_tran_id||':D')::uuid, vCompany_ID, vBranch_ID, vFinYear, vVoucher_id, a.vch_tran_id, vDocDate, 
                        a.account_id, a.debit_sub_head_id, vFCType_ID, vExchRate, 0, 0, a.item_amt, 0, 
                        a.remarks, vStatus, false
                From ac.saj_tran a
                Where a.voucher_id = vVoucher_id
                Union All 
                Select md5(a.vch_tran_id||':C')::uuid, vCompany_ID, vBranch_ID, vFinYear, vVoucher_id, a.vch_tran_id, vDocDate, 
                        a.account_id, a.credit_sub_head_id, vFCType_ID, vExchRate, 0, 0, 0, a.item_amt, 
                        a.remarks, vStatus, false
                From ac.saj_tran a
                Where a.voucher_id = vVoucher_id;
            Else
                IF vIsPDC=false then   -- Post only if document is not PDC
                    -- **** Fetch Cheque information  
                    If NEW.cheque_number<>'' then
                        Select 'Ch No. ' || cast(NEW.cheque_number as varchar) || ' Dt. ' || to_char(NEW.cheque_date, 'dd/MM/yyyy') into vChequeDetails;
                    End If;
                    If vType = 'PAYV' Then
                        Select string_agg(tran_desc || to_char(debit_amt, ': FM99,99,99,999.00'), E'\n') Into vPayVTranDesc
                        From ac.vch_tran
                        Where voucher_id=vVoucher_ID;
                        vNarration := (vPayVTranDesc || E'\n' ||vNarration)::Varchar(500);
                    End If;

                    -- ***	Fire the stored procedure to post the entry
                    perform ac.sp_gl_post('ac.vch_control' , vFinYear, vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vChequeDetails, vNarration, vType);

                    If vType='CV' then
                            -- ***	If account type in tran is bank, post entries into cv_reconciled table
                            Insert into ac.cv_reconciled (vch_tran_id, company_id, finyear, branch_id, voucher_id, doc_date, vch_caption, account_id, fc_type_id, debit_amt_fc, credit_amt_fc, exch_rate,
                                                            debit_amt, credit_amt, cheque_number, cheque_date, collected, collection_date, last_updated)
                            Select a.vch_tran_id, NEW.company_id, vFinYear, a.branch_id, vVoucher_ID, vDocDate, NEW.vch_caption, a.account_id, vFCType_ID, a.debit_amt_fc, a.credit_amt_fc, vExchRate,
                                    a.debit_amt, a.credit_amt, NEW.cheque_number, NEW.cheque_date, NEW.collected, NEW.collection_date, current_timestamp(0)
                            From ac.vch_tran a
                            Inner Join ac.account_head b on a.account_id=b.account_id
                            Where b.account_type_id=1 
                                    And a.voucher_id=vVoucher_ID;
                    End If;

                    If vType='MCJ' then					
                            if vIsReversal = true then
                                    perform ac.sp_gl_post('ac.vch_mcj_control' , vFinYear, vReferalVoucher_ID, vReversalDate, vFCType_ID, vExchRate, vChequeDetails, vNarration, vType);
                            End If;
                    End If;
                End IF;

                -- ***** Change status in Sub Head Ledger
                update ac.sub_head_ledger
                set status = vStatus
                Where voucher_id = vVoucher_id;

                -- ***** Change status in Ref Ledger
                update ac.ref_ledger
                set status = vStatus
                Where voucher_id = vVoucher_id;

                -- ***** Change status in Ref Ledger Alloc
                update ac.ref_ledger_alloc
                set status = vStatus
                Where affect_voucher_id = vVoucher_id;
                
                -- Insert row in subhead ledger if itc false for any tran row
                Perform ac.sp_shl_non_itc_post(vVoucher_id);
        	End IF;
        End If;
RETURN NEW;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
-- Trigger on vch control table
CREATE TRIGGER trg_vch_post
  AFTER UPDATE
  ON ac.vch_control
  FOR EACH ROW
  EXECUTE PROCEDURE ac.trgporc_vch_post();

?==?
CREATE OR REPLACE FUNCTION ac.sp_bank_reco_update(ptype varchar(4), pvoucher_id varchar(50), paccount_id bigint, pcollected boolean, pcollection_date date)
  RETURNS void AS
$BODY$
Begin
	if pcollected = false then
		select null into pcollection_date;
	End If;

	if ptype = 'A' then
		update ac.vch_control
		Set collected=pcollected,
			collection_date=pcollection_date
		where voucher_id=pvoucher_id;
	End if;
	If ptype = 'C' then
		update ac.cv_reconciled
		Set collected=pcollected,
			collection_date=pcollection_date
		where voucher_id=pvoucher_id;
	End If;
	If ptype = 'D' then
		update ap.pymt_control
		Set collected=pcollected,
			collection_date=pcollection_date
		where voucher_id=pvoucher_id;
	End If;
	If ptype = 'E' then
		update tds.tds_payment_control
		Set collected=pcollected,
			collection_date=pcollection_date
		where voucher_id=pvoucher_id;
	End If;
	If ptype = 'F' then
		update ar.rcpt_control
		Set collected=pcollected,
			collection_date=pcollection_date
		where voucher_id=pvoucher_id;
	End If;
	If ptype = 'G' then
		update fa.ap_control
		Set collected=pcollected,
			collection_date=pcollection_date
		where ap_id=pvoucher_id;
	End If;
	If ptype = 'H' then
		update ac.doc_reversal
		Set collected=pcollected,
			collection_date=pcollection_date
		where reversal_id=pvoucher_id;
	End If;
	If ptype = 'I' then
		update fa.as_control
		Set collected=pcollected,
			collection_date=pcollection_date
		where as_id=pvoucher_id;
	End If;
        If ptype = 'X' then
		update ac.bank_reco_optxn
		Set collected=pcollected,
			collection_date=pcollection_date
		where voucher_id=pvoucher_id;
	End If;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
Drop FUNCTION if exists ac.sp_bank_reco_collection(pcompany_id bigint, pbranch_id bigint, paccount_id bigint, preconciled bigint, pas_on date);

?==?
CREATE OR REPLACE FUNCTION ac.sp_bank_reco_collection
(	pcompany_id bigint,
	pbranch_id bigint,
	paccount_id bigint,
	preconciled bigint,
 	pfrom_date date,
	pas_on date)
Returns Table
(	reco_type character varying, 
 	doc_date date, 
 	voucher_id character varying, 
 	vch_caption character varying, 
 	cheque_number character varying, 
 	cheque_date date, 
 	debit_amt numeric, 
 	debit_amt_fc numeric, 
 	credit_amt numeric, 
 	credit_amt_fc numeric, 
 	collected boolean, 
 	collection_date date)
AS 
$BODY$
Declare 
	vYearBegin date;
Begin
    -- Parameter preconciled values
    -- 0 - Unreconciled
    -- 1 - Reconciled
    -- 2 - All
    
--     -- Fetch Year Begins based on AsOn Date
--     Select a.year_begin into vYearBegin From sys.finyear a
--     Where pas_on between a.year_begin and a.year_end;
	Select pfrom_date into vYearBegin;
	-- Generate Data
    Return Query
    With brc_temp (reco_type, doc_date, voucher_id, vch_caption, cheque_number, cheque_date, debit_amt, debit_amt_fc,
		credit_amt, credit_amt_fc, collected, collection_date)
    As
    (	Select 'A'::Varchar(1), a.doc_date, a.voucher_id, a.vch_caption, a.cheque_number, a.cheque_date, a.debit_amt, a.debit_amt_fc, 
            a.credit_amt, a.credit_amt_fc, 
            a.collected, Case When a.collected Then a.collection_date Else '1970-01-01' End As collection_date
        From ac.vch_control a
        Where a.status=5
            And Case 
                When preconciled = 0 Then 
                    (a.collected = false Or (a.collected = true And a.collection_date > pas_on))
                When preconciled = 1 Then
                    (a.collected=true And a.collection_date between vYearBegin and pas_on)
                When preconciled = 2 Then
                    (a.doc_date between vYearBegin and pas_on)
                End
            And a.account_id=paccount_id
            And a.doc_date <= pas_on
            And (a.branch_id=pbranch_id or pbranch_id=0)
            And a.company_id=pcompany_id
        Union All
        Select 'C' as reco_type, a.doc_date, a.voucher_id, a.vch_caption, a.cheque_number, a.cheque_date, a.debit_amt, a.debit_amt_fc,
            a.credit_amt, a.credit_amt_fc,
            a.collected, Case When a.collected Then a.collection_date Else '1970-01-01' End
        From ac.cv_reconciled a
        Where Case 
                When preconciled = 0 Then 
                    (a.collected = false Or (a.collected = true And a.collection_date > pas_on))
                When preconciled = 1 Then
                    (a.collected=true And a.collection_date between vYearBegin and pas_on)
                When preconciled = 2 Then
                    (a.doc_date between vYearBegin and pas_on)
                End
            And a.account_id=paccount_id
            And a.doc_date <= pas_on
            And (a.branch_id=pbranch_id or pbranch_id=0)
            And a.company_id=pcompany_id
        Union All
        Select 'D' as reco_type, a.doc_date, a.voucher_id, a.received_from, a.cheque_number, a.cheque_date,                 
            case when a.doc_type = 'SREC' then a.credit_amt else 0 end as debit_amt, case when a.doc_type = 'SREC' then a.credit_amt_fc else 0 end as debit_amt_fc, 
            case when a.doc_type = 'SREC' then 0 else a.credit_amt end as credit_amt, case when a.doc_type = 'SREC' then 0 else a.credit_amt_fc end as credit_amt_fc, 
            a.collected, Case When a.collected Then a.collection_date Else '1970-01-01' End
        From ap.pymt_control a
        Where a.status=5 And a.doc_type != 'PYMT'
            And Case 
                When preconciled = 0 Then 
                    (a.collected = false Or (a.collected = true And a.collection_date > pas_on))
                When preconciled = 1 Then
                    (a.collected=true And a.collection_date between vYearBegin and pas_on)
                When preconciled = 2 Then
                    (a.doc_date between vYearBegin and pas_on)
                End
            And a.account_id=paccount_id
            And a.doc_date <= pas_on
            And (a.branch_id=pbranch_id or pbranch_id=0)
            And a.company_id=pcompany_id
        Union All
        Select 'D' as reco_type, a.doc_date, a.voucher_id, a.received_from, a.cheque_number, a.cheque_date, 0 as debit_amt, 0 as debit_amt_fc, 
            Sum(b.debit_amt) + COALESCE((a.annex_info->>'other_adj')::numeric, 0) , Sum(b.debit_amt_fc) + COALESCE((a.annex_info->>'other_adj_fc')::numeric, 0),
            a.collected, Case When a.collected Then a.collection_date Else '1970-01-01' End
        From ap.pymt_control a
        Inner Join ac.rl_pl_alloc b On a.voucher_id=b.voucher_id
        Where a.status=5 And a.doc_type = 'PYMT'
            And Case 
                When preconciled = 0 Then 
                    (a.collected = false Or (a.collected = true And a.collection_date > pas_on))
                When preconciled = 1 Then
                    (a.collected=true And a.collection_date between vYearBegin and pas_on)
                When preconciled = 2 Then
                    (a.doc_date between vYearBegin and pas_on)
                End
            And a.account_id=paccount_id
            And a.doc_date <= pas_on
            And (a.branch_id=pbranch_id or pbranch_id=0)
            And a.company_id=pcompany_id
        Group By a.doc_date, a.voucher_id, a.received_from, a.cheque_number, a.cheque_date, a.collected
        Union All
        Select 'E' as reco_type, a.doc_date, a.voucher_id, '' as received_from, a.cheque_number, a.cheque_date, 0 as debit_amt, 0 as debit_amt_fc, 
            a.amt as credit_amt, 0 as credit_amt_fc, 
            a.collected, Case When a.collected Then a.collection_date Else '1970-01-01' End
        From tds.tds_payment_control a
        Where a.status=5
            And Case 
                When preconciled = 0 Then 
                    (a.collected = false Or (a.collected = true And a.collection_date > pas_on))
                When preconciled = 1 Then
                    (a.collected=true And a.collection_date between vYearBegin and pas_on)
                When preconciled = 2 Then
                    (a.doc_date between vYearBegin and pas_on)
                End
            And a.account_id=paccount_id
            And a.doc_date <= pas_on
            And (a.branch_id=pbranch_id or pbranch_id=0)
            And a.company_id=pcompany_id
        Union All		
        Select 'F' as reco_type, a.doc_date, a.voucher_id, b.account_head, a.cheque_number, a.cheque_date, 					  
            Case When a.doc_type = 'RCPT' Then a.net_settled when a.doc_type = 'CREF' then 0 Else a.debit_amt End as debit_amt, 
            Case when a.doc_type = 'RCPT' Then a.net_settled_fc when a.doc_type = 'CREF' then 0 Else a.debit_amt_fc End as debit_amt_fc, 
            Case When a.doc_type = 'CREF' then a.debit_amt Else 0 End as credit_amt, 
            Case When a.doc_type = 'CREF' then a.debit_amt_fc Else 0 End as credit_amt_fc,
            a.collected, Case When a.collected Then a.collection_date Else '1970-01-01' End
        From ar.rcpt_control a
        Inner Join ac.account_head b on a.customer_account_id = b.account_id			   
        Where a.status=5
            And Case 
                When preconciled = 0 Then 
                    (a.collected = false Or (a.collected = true And a.collection_date > pas_on))
                When preconciled = 1 Then
                    (a.collected=true And a.collection_date between vYearBegin and pas_on)
                When preconciled = 2 Then
                    (a.doc_date between vYearBegin and pas_on)
                End
            And a.account_id = paccount_id
            And a.doc_date <= pas_on 
            And (a.branch_id=pbranch_id or pbranch_id=0)
            And a.company_id=pcompany_id
        Union All		
        Select 'F' as reco_type, a.doc_date, a.voucher_id, 'Multi Customer Receipt', a.cheque_number, a.cheque_date, 					  
            a.net_settled as debit_amt, 
            a.net_settled_fc, 
            0 as credit_amt, 
            0 as credit_amt_fc,
            a.collected, Case When a.collected Then a.collection_date Else '1970-01-01' End
        From ar.rcpt_control a	   
        Where a.status=5
            And Case 
                When preconciled = 0 Then 
                    (a.collected = false Or (a.collected = true And a.collection_date > pas_on))
                When preconciled = 1 Then
                    (a.collected=true And a.collection_date between vYearBegin and pas_on)
                When preconciled = 2 Then
                    (a.doc_date between vYearBegin and pas_on)
                End
            And a.account_id = paccount_id
            And a.doc_date <= pas_on 
            And (a.branch_id=pbranch_id or pbranch_id=0)
            And a.company_id=pcompany_id
     		And doc_type = 'MCR'
        Union All
        Select 'G' as reco_type, a.doc_date, a.ap_id, '' as received_from, a.cheque_number, a.cheque_date, 0 as debit_amt, 0 as debit_amt_fc, 
            a.credit_amt, 0 as credit_amt_fc, 
            a.collected, Case When a.collected Then a.collection_date Else '1970-01-01' End
        From fa.ap_control a
        Where a.status=5
            And Case 
                When preconciled = 0 Then 
                    (a.collected = false Or (a.collected = true And a.collection_date > pas_on))
                When preconciled = 1 Then
                    (a.collected=true And a.collection_date between vYearBegin and pas_on)
                When preconciled = 2 Then
                    (a.doc_date between vYearBegin and pas_on)
                End
            And a.account_id=paccount_id
            And a.doc_date <= pas_on
            And (a.branch_id=pbranch_id or pbranch_id=0)
            And a.company_id=pcompany_id
        Union All
        Select 'H' as reco_type, a.reversal_date, a.reversal_id, a.caption as received_from, a.cheque_number, a.cheque_date, a.debit_amt, 0 as debit_amt_fc, 
            a.credit_amt, 0 as credit_amt_fc, 
            a.collected, Case When a.collected Then a.collection_date Else '1970-01-01' End
        From ac.doc_reversal a
        Where Case 
                When preconciled = 0 Then 
                    (a.collected = false Or (a.collected = true And a.collection_date > pas_on))
                When preconciled = 1 Then
                    (a.collected=true And a.collection_date between vYearBegin and pas_on)
                When preconciled = 2 Then
                    (a.reversal_date between vYearBegin and pas_on)
                End
            And a.account_id=paccount_id
            And a.reversal_date <= pas_on
            And (a.branch_id=pbranch_id or pbranch_id=0)
            And a.company_id=pcompany_id
        Union All
        Select 'I' as reco_type, a.doc_date, a.as_id, '' as received_from, a.cheque_number, a.cheque_date, a.debit_amt, 0 as debit_amt_fc, 
            0 as credit_amt, 0 as credit_amt_fc, 
            a.collected, Case When a.collected Then a.collection_date Else '1970-01-01' End
        From fa.as_control a
        Where a.status=5
            And Case 
                When preconciled = 0 Then 
                    (a.collected = false Or (a.collected = true And a.collection_date > pas_on))
                When preconciled = 1 Then
                    (a.collected=true And a.collection_date between vYearBegin and pas_on)
                When preconciled = 2 Then
                    (a.doc_date between vYearBegin and pas_on)
                End
            And a.customer_id=paccount_id
            And a.doc_date <= pas_on
            And (a.branch_id=pbranch_id or pbranch_id=0)
            And a.company_id=pcompany_id
        Union All
        Select 'X'::Varchar(1), a.doc_date, a.voucher_id, a.vch_caption, a.cheque_number, a.cheque_date, a.debit_amt, 0.00, 
            a.credit_amt, 0.00, 
            a.collected, Case When a.collected Then a.collection_date Else '1970-01-01' End As collection_date
        From ac.bank_reco_optxn a
        Where Case 
                When preconciled = 0 Then 
                    (a.collected = false Or (a.collected = true And a.collection_date > pas_on))
                When preconciled = 1 Then
                    (a.collected=true And a.collection_date between vYearBegin and pas_on)
                When preconciled = 2 Then
                    (a.doc_date between vYearBegin and pas_on)
                End
            And a.account_id=paccount_id
            And a.doc_date <= pas_on
            And (a.branch_id=pbranch_id or pbranch_id=0)
            And a.company_id=pcompany_id
	)
	Select a.reco_type, a.doc_date, a.voucher_id, a.vch_caption, a.cheque_number, case when a.cheque_number = '' then '1970-01-01' else a.cheque_date end, a.debit_amt, a.debit_amt_fc, 
		a.credit_amt, a.credit_amt_fc, a.collected, a.collection_date
	From brc_temp a
        Order By a.doc_date, a.voucher_id;
END
$BODY$
Language plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.fn_get_account_balance(paccount_id bigint)
  RETURNS TABLE(
	account_balance_id varchar(50),
	finyear varchar(4),
	account_id bigint,
	company_id bigint,
	branch_id bigint,
	debit_balance numeric(18,4),
	credit_balance numeric(18,4),
	last_updated timestamp
) AS
$BODY$
	Declare vFinYear varchar(4) = '';
BEGIN
	
	Select cast(value as varchar) into vFinYear from sys.settings where key='ac_start_finyear';

	if vFinYear = '' then
		Select a.finyear_code into vFinYear
		From sys.finyear a
		where a.year_begin = (Select min(year_begin) from sys.finyear);
	End If;
	
	DROP TABLE IF EXISTS acc_op_bal;
	CREATE temp TABLE  acc_op_bal
	(	account_balance_id varchar(50),
		finyear varchar(4) not null,
		account_id bigint not null,
		company_id bigint not null,
		branch_id bigint not null,
		debit_balance numeric(18,4) not null,
		credit_balance numeric(18,4) not null,
		last_updated timestamp
	);
	--	Second Step: Summarise the Opening, Transactions and Closing Balance
	Insert Into acc_op_bal(account_balance_id, finyear, account_id, company_id, branch_id, debit_balance, credit_balance, last_updated)
	select a.account_balance_id, a.finyear, a.account_id, a.company_id, a.branch_id, a.debit_balance, a.credit_balance, a.last_updated
	from ac.account_balance a
	where a.finyear = vFinYear
	    And a.account_id = paccount_id;
	
	return query
	select a.account_balance_id, a.finyear, a.account_id, a.company_id, a.branch_id, a.debit_balance, a.credit_balance, a.last_updated
	from acc_op_bal a;

END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE or replace FUNCTION ac.sp_account_balance_add(pfinyear varchar(4), pcompany_id bigint, pbranch_id bigint, paccount_id bigint default -1, pis_account boolean default false)
RETURNS void AS
$BODY$
Begin
	If pis_account = true then 
		Insert into ac.account_balance (account_balance_id, finyear, account_id, company_id, branch_id, debit_balance, credit_balance, last_updated)
		select b.account_id || ':' || a.branch_id || ':' || a.finyear, a.finyear, b.account_id, pcompany_id, a.branch_id, 0, 0, current_timestamp(0)
		from (select a.finyear_code as finyear, b.branch_id from sys.finyear a
			cross join sys.branch b) a
		cross join ac.account_head b 
		where b.account_id = paccount_id;
	Else
		If pfinyear<>'' Then
			Insert into ac.account_balance (account_balance_id, finyear, account_id, company_id, branch_id, debit_balance, credit_balance, last_updated)
			select b.account_id || ':' || a.branch_id || ':' || pfinyear, pfinyear, b.account_id, pcompany_id, a.branch_id, 0, 0, current_timestamp(0)
			from sys.branch a
			cross join ac.account_head b 
			where b.account_id<>0;
		End If;
		If pbranch_id <> 0 Then
			Insert into ac.account_balance (account_balance_id, finyear, account_id, company_id, branch_id, debit_balance, credit_balance, last_updated)
			select b.account_id || ':' || pbranch_id || ':' || a.finyear_code, a.finyear_code, b.account_id, pcompany_id, pbranch_id, 0, 0, current_timestamp(0)
			from sys.finyear a
			cross join ac.account_head b 
			where b.account_id<>0;
		End If; 
	End If;
END;
$BODY$
  LANGUAGE plpgsql;

  
?==?
CREATE OR REPLACE FUNCTION ac.sp_account_balance_add_update(pcompany_id bigint, pbranch_id bigint, paccount_id bigint, pbalance numeric(18,4))
RETURNS void AS
$BODY$
Declare vFinYear varchar(4); vDebitBalance numeric(18,4); vCreditBalance numeric(18,4); 
Begin

	Select cast(value as varchar) into vFinYear from sys.settings where key='ac_start_finyear';

	if vFinYear = '' then
		Select a.finyear_code into vFinYear
		From sys.finyear a
		where a.year_begin = (Select min(year_begin) from sys.finyear);
	End If;
	
	If pbalance > 0 Then 
		vDebitBalance := pbalance;
		vCreditBalance := 0;
	Else
		vDebitBalance := 0;
		vCreditBalance := pbalance * -1;
	End If;
		
	If exists (Select * from ac.account_balance where account_id = paccount_id and finyear = vFinYear and branch_id = pbranch_id) Then
		Update ac.account_balance 
		Set debit_balance = vDebitBalance,
			credit_balance = vCreditBalance
		where account_id = paccount_id and finyear = vFinYear and branch_id = pbranch_id;
	Else 
		Insert into ac.account_balance (account_balance_id, finyear, account_id, company_id, branch_id, debit_balance, credit_balance, last_updated)
		select paccount_id || ':' || pbranch_id || ':' || vFinYear, vFinYear, paccount_id, pcompany_id, pbranch_id, vDebitBalance, vCreditBalance, current_timestamp(0);
	End If;  
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.sp_ib_account_add_update(pbranch_id bigint, paccount_id bigint)
  RETURNS void AS
$BODY$
Begin	
        if exists (select * from ac.ib_account where branch_id = pbranch_id) then
                update ac.ib_account
                set ib_account_id = pbranch_id ||':' || paccount_id, 
                        account_id = paccount_id
                where branch_id=pbranch_id;
        else		
                Insert into ac.ib_account(ib_account_id, account_id, branch_id)
                Select pbranch_id ||':' || paccount_id, paccount_id, pbranch_id;
        End If;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.sp_import_account_opbal(pcompany_id bigint, ptarget_year Varchar(4), pvoucher_id Varchar(20)=null)
  RETURNS void AS
$BODY$
Declare vfrom_date Date; vto_date Date; vprevious_year Varchar(4); vst_req Varchar(50)='0';
Begin
	-- Fetch Previous FinYear details
	Select a.year_begin, a.year_end, a.finyear_code
	From sys.finyear a Into vfrom_date, vto_date, vprevious_year
	Where a.year_end = (Select b.year_begin - Interval '1 day' 
			  From sys.finyear b
			  Where b.finyear_code=ptarget_year)
		And a.company_id=pcompany_id;
	
	--Exit if previous year is not found
	if vprevious_year Is Null Then
		Return;
	End If;

	-- Step 1: Extract detailed Trial balance (Assets, Owners Funds, Liabilites Only) By Branch (CT)
	DROP TABLE IF EXISTS tb_detail;
	CREATE temp TABLE  tb_detail
	(	branch_id bigint,
		account_id bigint,
		dr_cl_bal numeric(18,4), 
		cr_cl_bal numeric(18,4)
	);

	Insert Into tb_detail(branch_id, account_id, dr_cl_bal, cr_cl_bal)
	Select a.branch_id, a.account_id, a.debit_closing_balance, a.credit_closing_balance
	From ac.fn_tb_op_tran_cl_ct(pcompany_id, 0, vprevious_year, vfrom_date, vto_date) a
	Inner Join ac.account_head b On a.account_id=b.account_id
	Inner Join ac.account_group c On b.group_id=c.group_id
	Where group_path like 'A001%' or group_path like 'A002%' or group_path like 'A003%';

	-- Step 2: Reset the Inventory Account Balances to zero
	--*****	Fetch Closing Stock Entries based upon application settings
	Select a.value into vst_req From sys.settings a Where key='bs_closing_stock';

	if vst_req <> '0' Then	
		With inv_ac(inventory_account_id)
		As
		(	Select a.inventory_account_id 
			From st.material a 
			Group by a.inventory_account_id
		)
		Update tb_detail
		Set 	dr_cl_bal=0,
			cr_cl_bal=0
		From inv_ac a 
		Where tb_detail.account_id=a.inventory_account_id;

		-- Step 3: update Closing balance with Inventory Valuation
		With mat_bal(branch_id, material_id, mat_value)
		As
		(	Select x.branch_id, x.material_id, cast(Sum(x.balance_qty_base * x.rate) as Numeric(18,4)) as mat_value
			From st.fn_material_balance_wac_detail(pcompany_id, 0, 0, 0, vprevious_year, vto_date) x
			Group By x.branch_id, x.material_id
		),
		mat_ac_bal(branch_id, inventory_account_id, mat_value)
		As
		(	Select a.branch_id, b.inventory_account_id, cast(Sum(mat_value) as Numeric(18,2))
			From mat_bal a
			Inner Join st.material b On a.material_id=b.material_id
			Group By a.branch_id, b.inventory_account_id
		)
		update tb_detail
		Set dr_cl_bal = b.mat_value
		From mat_ac_bal b
		Where tb_detail.account_id=b.inventory_account_id And tb_detail.branch_id=b.branch_id;
	End If;
	
	-- Step 4: Compute Excess/Shortfall for each branch
	Declare branch_cur cursor
	For Select a.branch_id 
	    From sys.branch a 
	    Where a.company_id=pcompany_id;
	-- cursor variables
	Declare vpnl_id BigInt:=-1; vnet_bal Numeric(18,4):=0;

	Begin
		-- Fetch the pnl Account for the company
		Select a.account_id into vpnl_id
		From ac.account_head a
		Where a.account_type_id = 30 Limit 1;

		If vpnl_id = -1 Or vpnl_id Is Null Then
			RAISE EXCEPTION 'Profit And Loss A/c Not Created for Balance Carry Forward' USING ERRCODE = 'data_exception';
			return;
		End If;
	
		For branch_cur_item in branch_cur Loop

			-- reset the balance for pnl to zero
			Update tb_detail a
			Set dr_cl_bal = 0,
			    cr_cl_bal = 0
			Where a.branch_id=branch_cur_item.branch_id And a.account_id=vpnl_id;

			-- Get net diff in Assets and liabs
			Select Cast(Sum(a.dr_cl_bal - a.cr_cl_bal) as Numeric(18,4)) Into vnet_bal
			From tb_detail a
			Where a.branch_id=branch_cur_item.branch_id;

			if vnet_bal >=0 Then
				Update tb_detail a
				Set cr_cl_bal = vnet_bal
				Where a.branch_id=branch_cur_item.branch_id And a.account_id=vpnl_id;
			Else 
				Update tb_detail a
				Set dr_cl_bal = vnet_bal * -1
				Where a.branch_id=branch_cur_item.branch_id And a.account_id=vpnl_id;
			End If;
			
			vnet_bal:= 0;
		End loop;
	End;

	-- Step 5: Reset all balances to zero for the next year
	Update ac.account_balance a
	Set debit_balance = 0,
	    credit_balance = 0,
	    last_updated = current_timestamp(0)
	Where a.finyear=ptarget_year
	    And a.company_id=pcompany_id;
	
	-- Step 5: Update Assets/Liabs Account Balance for next year
	Update ac.account_balance a
	Set debit_balance = b.dr_cl_bal,
	    credit_balance = b.cr_cl_bal,
	    last_updated = current_timestamp(0)
	From tb_detail b
	Where a.finyear=ptarget_year
	    And a.company_id=pcompany_id
	    And a.branch_id=b.branch_id
	    And a.account_id=b.account_id;

End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.fn_vch_mcj_info_for_gl_post(IN pvoucher_id character varying)
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
		vDocType Varchar(4) := ''; vReferalVoucher_ID varchar(50);
Begin	
	-- This function is used by the Posting Trigger to get information on the Supplier Payment (PYMT)
	DROP TABLE IF EXISTS vch_mcj_detail;	
	create temp TABLE  vch_mcj_detail
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

	Select a.doc_type Into vDocType
	From ac.vch_control a
	Where a.voucher_id=pvoucher_ID;

	vReferalVoucher_ID := substring(pvoucher_id, 0, length(pvoucher_id)-2);
	
	Insert into vch_mcj_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
	Select a.company_id, a.branch_id, case when a.dc = 'C' then 'D' else 'C' End, a.account_id, a.credit_amt_fc, a.debit_amt_fc, a.credit_amt, a.debit_amt
	From ac.vch_control a
	Where voucher_id=vReferalVoucher_ID;
				
	Insert into vch_mcj_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
	Select sys.fn_get_company_id(a.branch_id), a.branch_id, case when a.dc = 'C' then 'D' else 'C' End, a.account_id, a.credit_amt_fc, a.debit_amt_fc, a.credit_amt, a.debit_amt
	From ac.vch_tran a
	Where a.voucher_id=vReferalVoucher_ID;

	return query 
	select a.index, a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt, a.remarks
	from vch_mcj_detail a;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
create or replace function ac.sp_pymt_reversal_collection(pcompany_id bigint, pbranch_id bigint, pvoucher_id varchar(50), paccount_id bigint)
RETURNS TABLE  
(	
	voucher_id varchar(50),
	doc_date date,
	supplier_id bigint,
	supplier varchar(250),
	account_id bigint,
	account_head varchar(250),
	received_from varchar(100),
	settled_amt numeric(18,4),
	category char(1),
        vchstatus varchar(20)
)
AS
$BODY$ 
Begin	 
	return query 
	select a.voucher_id, a.doc_date, a.supplier_account_id, c.supplier, a.account_id, b.account_head, a.received_from, a.credit_amt, 'A'::char,
                case when (a.status = 5 and a.is_reversed = false) then 'OK'::varchar
                     when (a.is_reversed = true) then 'Reversed'::varchar
                     when (a.status != 5) then 'Notposted'::varchar
                End
	from ap.pymt_control a
	inner join ac.account_head b on a.account_id = b.account_id
	inner join ap.supplier c on a.supplier_account_id = c.supplier_id
	where a.voucher_id = pvoucher_id
		and b.account_type_id = 1
		and (a.branch_id = pbranch_id or pbranch_id = 0)
		and a.company_id = pcompany_id
		and a.account_id = paccount_id
        Union All
        select a.voucher_id, a.doc_date, 0, a.vch_caption::varchar, a.account_id, b.account_head, '', a.credit_amt, 'B'::char,
                case when (a.status = 5 and a.is_reversal = false) then 'OK'::varchar 
                     when (a.is_reversal = true) then 'Reversed'::varchar
                     when (a.status != 5) then 'Notposted'::varchar
                End
	from ac.vch_control a
	inner join ac.account_head b on a.account_id = b.account_id
        where a.voucher_id = pvoucher_id
		and b.account_type_id = 1
		and (a.branch_id = pbranch_id or pbranch_id = 0)
		and a.company_id = pcompany_id
		and a.account_id = paccount_id;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.sp_pymt_reversal_update(
    pcompany_id bigint,
    pbranch_id bigint,
    pvoucher_id character varying,
    paccount_id bigint,
    preversal_date date,
    preversal_remark character varying, 
    pcategory character varying, 
    pfinyear character varying)
RETURNS void AS
$BODY$ 
Begin	
	if pcategory = 'A' then	
		Update ap.pymt_control
			set is_reversed = true,
			reversal_date = preversal_date,
			reversal_comments = preversal_remark::text
		where voucher_id = pvoucher_id
			and (branch_id = pbranch_id or pbranch_id = 0)
			and company_id = pcompany_id
			and account_id = paccount_id;
		
		-- Insert values in bank reversal table to show txn in bank reco
		Insert into ac.doc_reversal(reversal_id, reversal_date, voucher_id, doc_date, company_id, branch_id, 
                                account_id, collected, collection_date, debit_amt, credit_amt, cheque_number, cheque_date, caption)
		Select a.voucher_id || ':R', preversal_date, a.voucher_id, a.doc_date, a.company_id, a.branch_id, 
                                a.account_id, false, null, sum(b.debit_amt), 0, a.cheque_number, a.cheque_date, a.received_from
		from ap.pymt_control a
		Inner Join ac.rl_pl_alloc b On a.voucher_id=b.voucher_id
		where a.voucher_id = pvoucher_id
		group by a.voucher_id, preversal_date, a.voucher_id, a.doc_date, a.company_id, a.branch_id, a.account_id, a.cheque_number, a.cheque_date, a.received_from;
	End If;

        if pcategory = 'B' then	
                Update ac.vch_control
                        set is_reversal = true,
                        reversal_date = preversal_date,
                        reversal_comments = preversal_remark::text
                where voucher_id = pvoucher_id
                        and (branch_id = pbranch_id or pbranch_id = 0)
                        and company_id = pcompany_id
                        and account_id = paccount_id;

                Insert into ac.doc_reversal(reversal_id, reversal_date, voucher_id, doc_date, company_id, branch_id, 
                                account_id, collected, collection_date, debit_amt, credit_amt, cheque_number, cheque_date, caption)
                Select a.voucher_id || ':R', preversal_date, a.voucher_id, a.doc_date, a.company_id, a.branch_id, 
                                a.account_id, false, null, sum(b.debit_amt), 0, a.cheque_number, a.cheque_date, a.vch_caption
                from ac.vch_control a
                Inner Join ac.general_ledger b On a.voucher_id=b.voucher_id
                where a.voucher_id = pvoucher_id
                group by a.voucher_id, preversal_date, a.voucher_id, a.doc_date, a.company_id, a.branch_id, 
        a.account_id, a.cheque_number, a.cheque_date, a.vch_caption;
	End If;

	INSERT INTO ac.rl_pl_alloc(rl_pl_alloc_id, rl_pl_id, branch_id, voucher_id, vch_tran_id, 
		doc_date, account_id, exch_rate, debit_amt, debit_amt_fc, credit_amt, credit_amt_fc,  write_off_amt, write_off_amt_fc, 
		debit_exch_diff, credit_exch_diff, net_debit_amt, net_debit_amt_fc, net_credit_amt, net_credit_amt_fc, status)        
	SELECT sys.sp_gl_create_id(vch_tran_id || ':R', branch_id, account_id, 0), rl_pl_id, branch_id, voucher_id || ':R', vch_tran_id || ':R', 
		preversal_date, account_id, exch_rate, credit_amt, credit_amt_fc, debit_amt, debit_amt_fc, (-1) * write_off_amt, (-1)* write_off_amt_fc, 
		credit_exch_diff, debit_exch_diff, net_credit_amt, net_credit_amt_fc, net_debit_amt, net_debit_amt_fc, status
	FROM ac.rl_pl_alloc where voucher_id = pvoucher_id;

	INSERT INTO ac.general_ledger(general_ledger_id, company_id, branch_id, finyear, voucher_id, 
                doc_date, account_id, account_affected_id, fc_type_id, exch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, 
                narration, cheque_details)
        SELECT sys.sp_gl_create_id(voucher_id || ':R', branch_id, account_id, account_affected_id), company_id, branch_id, pfinyear, voucher_id || ':R', 
		preversal_date, account_id, account_affected_id, fc_type_id, exch_rate, credit_amt_fc, debit_amt_fc, credit_amt, debit_amt,
		preversal_remark, cheque_details
	FROM ac.general_ledger
	where voucher_id = pvoucher_id;

        Insert into ac.sub_head_ledger(sub_head_ledger_id, company_id, branch_id, finyear, voucher_id, vch_tran_id, doc_date, 
                        account_id, sub_head_id, fc_type_id, exch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, 
                        narration, status, not_by_alloc)
        select sys.sp_gl_create_id(voucher_id || ':R', branch_id, sub_head_id, 0), company_id, branch_id, finyear, 
                voucher_id || ':R', vch_tran_id, preversal_date, account_id, sub_head_id, fc_type_id, exch_rate, credit_amt_fc, debit_amt_fc,
                credit_amt, debit_amt, preversal_remark, 5, false
        From ac.sub_head_ledger
	where voucher_id = pvoucher_id;

        Insert Into ac.ref_ledger(ref_ledger_id, voucher_id, vch_tran_id, doc_date, account_id, branch_id, ref_no, ref_desc, 
                debit_amt, credit_amt, status, last_updated)
        Select sys.sp_gl_create_id(voucher_id || ':R', branch_id, account_id, 0),voucher_id || ':R', vch_tran_id, preversal_date,
                account_id, branch_id, ref_no, ref_desc, credit_amt, debit_amt, 5, now()::timestamp(0)
        From ac.ref_ledger
	where voucher_id = pvoucher_id;

        INSERT INTO ac.ref_ledger_alloc(ref_ledger_alloc_id, ref_ledger_id, branch_id, affect_voucher_id, affect_vch_tran_id, affect_doc_date, 
            account_id, net_debit_amt, net_credit_amt, status, last_updated)
        SELECT (md5(ref_ledger_alloc_id||':R'||account_id||now()::timestamp(0)))::uuid, ref_ledger_id, branch_id, affect_voucher_id||':R', affect_vch_tran_id, preversal_date, 
                account_id, net_credit_amt, net_debit_amt, status, now()::timestamp(0)
	FROM ac.ref_ledger_alloc where affect_voucher_id = pvoucher_id;

        IF Left(pvoucher_id, 3) = 'ASP' THEN
            Insert into ac.doc_reversal(reversal_id, reversal_date, voucher_id, doc_date, company_id, branch_id, account_id, collected, collection_date, debit_amt, credit_amt, cheque_number, cheque_date, caption)
            Select a.voucher_id || ':R', preversal_date, a.voucher_id, a.doc_date, a.company_id, a.branch_id, a.account_id, false, null,b.debit_amt, 0, a.cheque_number, a.cheque_date, a.received_from
            From ap.pymt_control a
            Inner Join ac.rl_pl b On a.voucher_id=b.voucher_id
            where a.voucher_id = pvoucher_id;

            INSERT INTO ac.rl_pl_alloc(rl_pl_alloc_id, rl_pl_id, branch_id, voucher_id, vch_tran_id, 
                    doc_date, account_id, exch_rate, debit_amt, debit_amt_fc, credit_amt, credit_amt_fc,  write_off_amt, write_off_amt_fc, 
                    debit_exch_diff, credit_exch_diff, net_debit_amt, net_debit_amt_fc, net_credit_amt, net_credit_amt_fc, status)        
            SELECT sys.sp_gl_create_id(voucher_id || ':R', branch_id, account_id, 0), rl_pl_id, branch_id, voucher_id || ':R', voucher_id || ':R', 
                    preversal_date, account_id, exch_rate, credit_amt, credit_amt_fc, debit_amt, debit_amt_fc, 0, 0, 
                    0, 0, credit_amt, credit_amt_fc, debit_amt, debit_amt_fc, 5
            FROM ac.rl_pl where voucher_id = pvoucher_id;
        END IF;

END;
$BODY$
  LANGUAGE plpgsql;

?==?

CREATE OR REPLACE FUNCTION ac.sp_rcpt_reversal_update(
    pcompany_id bigint,
    pbranch_id bigint,
    pvoucher_id character varying,
    paccount_id bigint,
    preversal_date date,
    preversal_remark character varying, 
    pcategory character varying,
    pfinyear character varying)
RETURNS void AS
$BODY$ 
Begin	
	If pcategory = 'A' then	
		Update ar.rcpt_control
			set is_reversed = true,
			reversal_date = preversal_date,
			reversal_comments = preversal_remark::text
		where voucher_id = pvoucher_id
			and (branch_id = pbranch_id or pbranch_id = 0)
			and company_id = pcompany_id
			and account_id = paccount_id;
		
		-- Insert values in bank reversal table to show txn in bank reco
		INSERT INTO ac.doc_reversal(reversal_id, reversal_date, voucher_id, doc_date, company_id, branch_id, account_id, collected, collection_date, debit_amt, credit_amt, cheque_number, cheque_date, caption)
		Select a.voucher_id || ':R', preversal_date, a.voucher_id, a.doc_date, a.company_id, a.branch_id, a.account_id, false, null, 0, 
                        case when Left(pvoucher_id, 3) = 'ACR' then a.debit_amt 
                            when Left(pvoucher_id, 4) = 'RCPT' then a.net_settled
                            else 0 end, a.cheque_number, a.cheque_date, b.account_head
		from ar.rcpt_control a
		Inner Join ac.account_head b on a.customer_account_id = b.account_id	
		where a.voucher_id = pvoucher_id;
	End If;

        If pcategory = 'B' then	
		Update ac.vch_control
                        set is_reversal = true,
                        reversal_date = preversal_date,
                        reversal_comments = preversal_remark::text
                where voucher_id = pvoucher_id
                        and (branch_id = pbranch_id or pbranch_id = 0)
                        and company_id = pcompany_id
                        and account_id = paccount_id;
		
		-- Insert values in bank reversal table to show txn in bank reco
		INSERT INTO ac.doc_reversal(reversal_id, reversal_date, voucher_id, doc_date, company_id, branch_id, account_id, collected, collection_date, debit_amt, credit_amt, cheque_number, cheque_date, caption)
		Select a.voucher_id || ':R', preversal_date, a.voucher_id, a.doc_date, a.company_id, a.branch_id, a.account_id, false, null, 0, 
                        a.debit_amt, a.cheque_number, a.cheque_date, b.account_head
		from ac.vch_control a
		Inner Join ac.account_head b on a.account_id = b.account_id	
		where a.voucher_id = pvoucher_id;
	End If;

	INSERT INTO ac.rl_pl_alloc(rl_pl_alloc_id, rl_pl_id, branch_id, voucher_id, vch_tran_id, 
		doc_date, account_id, exch_rate, debit_amt, debit_amt_fc, credit_amt, credit_amt_fc,  write_off_amt, write_off_amt_fc, 
		tds_amt, tds_amt_fc, other_exp, other_exp_fc, debit_exch_diff, credit_exch_diff, net_debit_amt, net_debit_amt_fc, 
		net_credit_amt, net_credit_amt_fc, status)        
	SELECT sys.sp_gl_create_id(vch_tran_id || ':R', branch_id, account_id, 0), rl_pl_id, branch_id, voucher_id || ':R', vch_tran_id || ':R', 
		preversal_date, account_id, exch_rate, credit_amt, credit_amt_fc, debit_amt, debit_amt_fc, (-1) * write_off_amt, (-1)* write_off_amt_fc, 
		-1*tds_amt, -1*tds_amt_fc, -1*other_exp, -1*other_exp_fc, credit_exch_diff, debit_exch_diff, net_credit_amt, net_credit_amt_fc, 
		net_debit_amt, net_debit_amt_fc, status
	FROM ac.rl_pl_alloc where voucher_id = pvoucher_id;

	INSERT INTO ac.general_ledger(general_ledger_id, company_id, branch_id, finyear, voucher_id, 
                doc_date, account_id, account_affected_id, fc_type_id, exch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, 
                narration, cheque_details)
        SELECT sys.sp_gl_create_id(voucher_id || ':R', branch_id, account_id, account_affected_id), company_id, branch_id, pfinyear, voucher_id || ':R', 
		preversal_date, account_id, account_affected_id, fc_type_id, exch_rate, credit_amt_fc, debit_amt_fc, credit_amt, debit_amt,
		preversal_remark, cheque_details
	FROM ac.general_ledger
	where voucher_id = pvoucher_id;

        INSERT INTO ac.sub_head_ledger(sub_head_ledger_id, company_id, branch_id, finyear, voucher_id, vch_tran_id, doc_date, 
                        account_id, sub_head_id, fc_type_id, exch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, 
                        narration, status, not_by_alloc)
        select sys.sp_gl_create_id(voucher_id || ':R', branch_id, sub_head_id, 0), company_id, branch_id, finyear, 
                voucher_id || ':R', vch_tran_id, preversal_date, account_id, sub_head_id, fc_type_id, exch_rate, credit_amt_fc, debit_amt_fc,
                credit_amt, debit_amt, preversal_remark, 5, false
        From ac.sub_head_ledger
	where voucher_id = pvoucher_id;

        INSERT INTO ac.ref_ledger(ref_ledger_id, voucher_id, vch_tran_id, doc_date, account_id, branch_id, ref_no, ref_desc, 
                debit_amt, credit_amt, status, last_updated)
        Select sys.sp_gl_create_id(voucher_id || ':R', branch_id, account_id, 0),voucher_id || ':R', vch_tran_id, preversal_date,
                account_id, branch_id, ref_no, ref_desc, credit_amt, debit_amt, 5, now()::timestamp(0)
        From ac.ref_ledger
	where voucher_id = pvoucher_id;

        INSERT INTO ac.ref_ledger_alloc(ref_ledger_alloc_id, ref_ledger_id, branch_id, affect_voucher_id, affect_vch_tran_id, affect_doc_date, 
            account_id, net_debit_amt, net_credit_amt, status, last_updated)
        SELECT (md5(ref_ledger_alloc_id||':R'||account_id||now()::timestamp(0)))::uuid, ref_ledger_id, branch_id, affect_voucher_id||':R', affect_vch_tran_id, preversal_date, 
                account_id, net_credit_amt, net_debit_amt, status, now()::timestamp(0)
	FROM ac.ref_ledger_alloc where affect_voucher_id = pvoucher_id;

        INSERT INTO ac.rl_pl_alloc(rl_pl_alloc_id, rl_pl_id, branch_id, voucher_id, vch_tran_id, 
                doc_date, account_id, exch_rate, debit_amt, debit_amt_fc, credit_amt, credit_amt_fc,  write_off_amt, write_off_amt_fc, 
                tds_amt, tds_amt_fc, other_exp, other_exp_fc, debit_exch_diff, credit_exch_diff, net_debit_amt, net_debit_amt_fc, 
                net_credit_amt, net_credit_amt_fc, status)        
        SELECT sys.sp_gl_create_id(voucher_id || ':R', branch_id, account_id, 0), rl_pl_id, branch_id, voucher_id || ':R', voucher_id || ':R', 
                preversal_date, account_id, exch_rate, credit_amt, credit_amt_fc, debit_amt, debit_amt_fc, 0, 0, 
                0, 0, 0, 0, 0, 0, credit_amt, credit_amt_fc, debit_amt, debit_amt_fc, 5
        FROM ac.rl_pl where voucher_id in (pvoucher_id, 'AJ:' || pvoucher_id);
 
END;
$BODY$
  LANGUAGE plpgsql;
  
?==?
create or replace function ac.sp_rcpt_reversal_collection(pcompany_id bigint, pbranch_id bigint, pvoucher_id varchar(50), paccount_id bigint)
RETURNS TABLE  
(	
	voucher_id character varying,
	doc_date date,
	customer_id bigint,
	customer character varying,
	account_id bigint,
	account_head character varying,
	received_from character varying,
	settled_amt numeric(18,4),
	category char(1),
        vchstatus varchar(20)	
)
AS
$BODY$ 
Begin	
	return query 
	select a.voucher_id, a.doc_date, a.customer_account_id, c.customer, a.account_id, b.account_head, a.received_from, a.debit_amt, 'A'::char,
                case when (a.status = 5 and a.is_reversed = false) then 'OK'::varchar
                     when (a.is_reversed = true) then 'Reversed'::varchar
                     when (a.status != 5) then 'Notposted'::varchar
                End
	from ar.rcpt_control a
	inner join ac.account_head b on a.account_id = b.account_id
	inner join ar.customer c on a.customer_account_id = c.customer_id
	where a.voucher_id = pvoucher_id
		and b.account_type_id = 1
		and (a.branch_id = pbranch_id or pbranch_id = 0)
		and a.company_id = pcompany_id
		and a.account_id = paccount_id
        Union All
        select a.voucher_id, a.doc_date, 0, a.vch_caption::varchar, a.account_id, b.account_head, '', a.debit_amt, 'B'::char,
                case when (a.status = 5 and a.is_reversal = false) then 'OK'::varchar 
                     when (a.is_reversal = true) then 'Reversed'::varchar
                     when (a.status != 5) then 'Notposted'::varchar
                End
	from ac.vch_control a
	inner join ac.account_head b on a.account_id = b.account_id
        where a.voucher_id = pvoucher_id
		and b.account_type_id = 1
		and (a.branch_id = pbranch_id or pbranch_id = 0)
		and a.company_id = pcompany_id
		and a.account_id = paccount_id;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.sp_sub_head_opbl_add_update(INOUT psub_head_ledger_id uuid, pcompany_id bigint, pbranch_id bigint, pfinyear character varying, 
              	pvoucher_id character varying, pdoc_date date, paccount_id bigint, psub_head_id bigint, pfc_type_id bigint, pexch_rate numeric, 
                pdebit_amt_fc numeric, pcredit_amt_fc numeric, pdebit_amt numeric, pcredit_amt numeric, pnarration character varying)
  RETURNS uuid AS
$BODY$
Begin
	if pnarration = '' then
    	pnarration:='Opening Balance';
    End If;
	if exists(Select * from ac.sub_head_ledger where sub_head_ledger_id=psub_head_ledger_id) Then
		Update ac.sub_head_ledger
		Set doc_date=pdoc_date,
			fc_type_id=pfc_type_id,	
			exch_rate=pexch_rate, 
			debit_amt_fc=pdebit_amt_fc, 
			credit_amt_fc=pcredit_amt_fc, 
			debit_amt=pdebit_amt, 
			credit_amt=pcredit_amt, 
			narration=pnarration
		Where sub_head_ledger_id=psub_head_ledger_id;
			
	Else
		Insert into ac.sub_head_ledger(sub_head_ledger_id, company_id, branch_id, finyear, voucher_id, vch_tran_id, doc_date, account_id, 
				 sub_head_id, fc_type_id, exch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, narration, status, not_by_alloc)
		select psub_head_ledger_id, pcompany_id, pbranch_id, pfinyear, pvoucher_id, pvoucher_id, pdoc_date, paccount_id, 
				 psub_head_id, pfc_type_id, pexch_rate, pdebit_amt_fc, pcredit_amt_fc, pdebit_amt, pcredit_amt, pnarration, 5, true;
	End If;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.sp_shl_non_itc_post(pvoucher_id varchar(50))
RETURNS void AS
$BODY$
Begin	
    Declare 
        vtax_amt Numeric(18,2):=0;
        gst_tax_cursor Cursor For Select gst_tax_tran_id, voucher_id From tx.gst_tax_tran
                                    where voucher_id = pvoucher_id;
        Begin
            For rec in gst_tax_cursor Loop
            Select sgst_amt+cgst_amt+igst_amt Into vtax_amt
            From tx.gst_tax_tran a
            Where gst_tax_tran_id = rec.gst_tax_tran_id
               And apply_itc = false;

            If vtax_amt > 0 Then
                INSERT INTO ac.sub_head_ledger(sub_head_ledger_id, company_id, branch_id, finyear, voucher_id, vch_tran_id, doc_date, 
                                               account_id, sub_head_id, fc_type_id, exch_rate, debit_amt_fc, credit_amt_fc, 
                                               debit_amt, credit_amt, narration, status, not_by_alloc)
                Select md5(a.vch_tran_id || ':GST')::uuid, a.company_id, a.branch_id, a.finyear, a.voucher_id, a.vch_tran_id || ':GST', a.doc_date, 
                                               a.account_id, a.sub_head_id, a.fc_type_id, a.exch_rate, 0, 0,
                                               vtax_amt, 0, a.narration, a.status, a.not_by_alloc
                From ac.sub_head_ledger a
                Where a.vch_tran_id = rec.gst_tax_tran_id
                Limit 1;

            End If;
            End Loop;
        End;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.sp_gl_reco_collection
(	pcompany_id bigint,
	pbranch_id bigint,
	paccount_id bigint,
	preconciled bigint,
 	pfrom_date date,
	pas_on date)
Returns Table
(	
 	doc_date date, 
 	voucher_id character varying, 
    account_id bigint,
 	narration character varying, 
 	cheque_details character varying, 
 	debit_amt numeric, 
 	credit_amt numeric, 
 	reconciled boolean, 
 	reco_date date)
AS 
$BODY$
Declare 
	vYearBegin date;
Begin
    -- Parameter preconciled values
    -- 0 - Unreconciled
    -- 1 - Reconciled
    -- 2 - All
    
--     -- Fetch Year Begins based on AsOn Date
--     Select a.year_begin into vYearBegin From sys.finyear a
--     Where pas_on between a.year_begin and a.year_end;
	Select pfrom_date into vYearBegin;
	-- Generate Data
    Return Query
    	Select a.doc_date, a.voucher_id, a.account_id, left(a.narration, 50)::varchar, a.cheque_details,
			sum(a.debit_amt) debit_amt, sum(a.credit_amt) credit_amt,
    		COALESCE(b.reconciled, false) is_reconciled, Case When COALESCE(b.reconciled, false) Then b.reco_date Else '1970-01-01' End As reco_date
        From ac.general_ledger a
        left join ac.gl_reco b on a.voucher_id = b.voucher_id And a.account_id = b.account_id
        Where Case 
                When preconciled = 0 Then 
                    (COALESCE(b.reconciled, false) = false Or (COALESCE(b.reconciled, false) = true And b.reco_date > pas_on))
                When preconciled = 1 Then
                    (COALESCE(b.reconciled, false) = true And b.reco_date between vYearBegin and pas_on)
                When preconciled = 2 Then
                    (a.doc_date between vYearBegin and pas_on)
                End
            And a.account_id =paccount_id
            And a.doc_date between vYearBegin and pas_on
            And (a.branch_id = pbranch_id or pbranch_id = 0)
            And a.company_id  =pcompany_id
	Group By a.doc_date, a.voucher_id, a.cheque_details, a.account_id, a.narration, COALESCE(b.reconciled, false), b.reco_date;
END
$BODY$
Language plpgsql;

?==?
CREATE OR REPLACE FUNCTION ac.sp_gl_reco_update(pvoucher_id varchar(50), paccount_id bigint, preconciled boolean, preco_date date)
  RETURNS void AS
$BODY$
Begin
	if preconciled = false then
		select null into preco_date;
	End If;
	
    If exists (select * from ac.gl_reco where voucher_id = pvoucher_id and account_id = paccount_id) then    	
		update ac.gl_reco
		Set reconciled = preconciled,
			reco_date = preco_date
		where voucher_id = pvoucher_id And account_id = paccount_id;
    Else
    	Insert into ac.gl_reco (gl_reco_id, voucher_id, account_id, reconciled, reco_date)
        Select md5(pvoucher_id || ':' || paccount_id)::uuid, pvoucher_id, paccount_id, preconciled, preco_date;
    End If;

END;
$BODY$
LANGUAGE plpgsql;

?==?

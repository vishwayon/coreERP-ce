CREATE OR REPLACE FUNCTION pos.fn_gl_post_data(IN pvoucher_id character varying, ptable_name character varying)
  RETURNS TABLE(index integer, company_id bigint, branch_id bigint, dc character, account_id bigint, debit_amt_fc numeric, credit_amt_fc numeric, debit_amt numeric, credit_amt numeric, remarks character varying) AS
$BODY$ 
	Declare 
		vDocType Varchar(4):=''; vCompany_id BigInt:=-1; vBranch_id BigInt:=-1; vRoundOffAcc_ID BigInt:=-1; 
		vBB_adj_account_id BigInt:=-1;	vBB_purchase_account_id BigInt:= -1; vBB_tax_account_id BigInt:=-1;
		vis_cash boolean; vis_card boolean; vis_customer boolean; vis_cheque boolean;
Begin	
	-- *****	Step 1: Fetch basic Control Information
	Select a.company_id, a.doc_type, a.branch_id
		into vCompany_id, vDocType, vBranch_id
	From pos.inv_control a
	where a.inv_id=replace(pvoucher_id, ':BB', '');
	-- **** Create temp table for tran data
	DROP TABLE IF EXISTS pos_inv_detail;	
	create temp TABLE pos_inv_detail
	(	index serial, 
		company_id bigint,
		branch_id bigint,
		dc char(1),
		account_id bigint,
		debit_amt numeric(18,4),
		credit_amt numeric(18,4),
		remarks varchar(100)
	);
	
	-- This function is used by the Posting Trigger to get information on the Supplier Payment (PYMT)
	If vDocType in ('PI', 'PIV') And ptable_name = 'pos.inv_control' Then
		-- *****	Group A: Credits
		-- *****	Step 1: Basic value of items
		Insert into pos_inv_detail(company_id, branch_id, dc, account_id, debit_amt, credit_amt, remarks)
		Select vCompany_id, vBranch_id, 'C', a.sale_account_id, 0, Sum(b.bt_amt), 'Line Items without taxes (net sales)'
		from pos.inv_control a
		Inner Join pos.inv_tran b On a.inv_id=b.inv_id
		where a.inv_id=pvoucher_id
		Group By a.sale_account_id;

		-- *****	Step 2: Line Item Taxes
		Insert into pos_inv_detail(company_id, branch_id, dc, account_id, debit_amt, credit_amt, remarks)
		Select vCompany_ID, vBranch_ID, 'C', c.account_id, 0, sum(b.tax_amt), 'Line Items Taxes'
		from pos.inv_control a
		Inner Join pos.inv_tran b On a.inv_id=b.inv_id
		Inner Join tx.tax_detail c On b.tax_schedule_id=c.tax_schedule_id And c.step_id=1
		where a.inv_id=pvoucher_id
		Group by c.account_id;

               -- *****	Step 3: GST Taxes
                with gtt 
                as 
                (	select sum(sgst_amt) as tax_amt, sgst_account_id as account_id
                        from tx.gst_tax_tran
                        where voucher_id = pvoucher_ID And tran_group='pos.inv_tran'
                        group by sgst_account_id
                        union all
                        select sum(cgst_amt), cgst_account_id
                        from tx.gst_tax_tran
                        where voucher_id = pvoucher_ID And tran_group='pos.inv_tran'
                        group by cgst_account_id
                        union all
                        select sum(igst_amt), igst_account_id
                        from tx.gst_tax_tran
                        where voucher_id = pvoucher_ID And tran_group='pos.inv_tran'
                        group by igst_account_id
                        union all
                        select sum(cess_amt), cess_account_id
                        from tx.gst_tax_tran
                        where voucher_id = pvoucher_ID And tran_group='pos.inv_tran'
                        group by cess_account_id
                )
                Insert into pos_inv_detail(company_id, branch_id, dc, account_id, debit_amt, credit_amt, remarks)
                Select vCompany_ID, vBranch_ID, 'C', a.account_id, 0, sum(a.tax_amt), 'Tax Amt'
                From gtt a
                group by a.account_id
                having sum(a.tax_amt) > 0;

		-- *****	Group B: Debits
		-- *****	Step 1: Get Settlement Debit(s)
		Select a.is_cash, a.is_card, a.is_cheque, a.is_customer Into vis_cash, vis_card, vis_cheque, vis_customer
		From pos.inv_settle a
		Where a.inv_id = pvoucher_id;

		If vis_cash Then
			Insert into pos_inv_detail(company_id, branch_id, dc, account_id, debit_amt, credit_amt, remarks)
			Select vCompany_id, vBranch_id, 'D', a.cash_account_id, a.cash_amt, 0, 'Cash Settlement'
			from pos.inv_settle a
			where a.inv_settle_id = pvoucher_id || ':S';
		End If;

		If vis_card Then
			Insert into pos_inv_detail(company_id, branch_id, dc, account_id, debit_amt, credit_amt, remarks)
			Select vCompany_id, vBranch_id, 'D', b.account_id, a.card_amt, 0, 'Card Settlement'
			From pos.inv_settle a
			Inner Join pos.cc_mac b On a.cc_mac_id = b.cc_mac_id
			Where a.inv_settle_id = pvoucher_id || ':S';
		End If;

		If vis_cheque Then
			Insert into pos_inv_detail(company_id, branch_id, dc, account_id, debit_amt, credit_amt, remarks)
			Select vCompany_id, vBranch_id, 'D', a.cheque_account_id, a.cheque_amt, 0, 'Cheque Settlement'
			from pos.inv_settle a
			where a.inv_settle_id = pvoucher_id || ':S';
		End If;

		If vis_customer Then
			Insert into pos_inv_detail(company_id, branch_id, dc, account_id, debit_amt, credit_amt, remarks)
			Select vCompany_id, vBranch_id, 'D', a.customer_id, a.customer_amt, 0, 'Walk-in Customer Settlement'
			from pos.inv_settle a
			where a.inv_settle_id = pvoucher_id || ':S';
		End If;

		-- *****	Step 2: Get round off with -ve case
		Select cast(value as bigint) into vRoundOffAcc_ID from sys.settings where key='st_round_off_account';
		Insert into pos_inv_detail(company_id, branch_id, dc, account_id,
			debit_amt, credit_amt, 
			remarks)
		Select vCompany_ID, vBranch_ID, case when a.rof_amt < 0 Then 'C' Else 'D' End, vRoundOffAcc_ID, 
			case when a.rof_amt < 0 Then -a.rof_amt Else 0 End, case when a.rof_amt > 0 Then a.rof_amt Else 0 End, 
			'Round Off Amt'
		from pos.inv_control a
		where a.inv_id=pvoucher_id And a.rof_amt != 0;

		-- *****	Step 3: Reverse Buy Back effects (posting of Buy Back is handled in next case)
		Select cast(value as bigint) into vBB_adj_account_id from sys.settings where key='bb_adj_account';
		Insert into pos_inv_detail(company_id, branch_id, dc, account_id, debit_amt, credit_amt, remarks)
		Select vCompany_id, vBranch_id, 'D', vBB_adj_account_id, sum(b.bt_amt), 0, 'Buy Back Adjustment'
		from pos.inv_control a
		Inner Join pos.inv_bb b On a.inv_id = b.inv_id
		where a.inv_id = pvoucher_id
                Having sum(b.bt_amt)>0;
	End If;
	If vDocType in ('PI', 'PIV') And ptable_name = 'pos.inv_bb' Then
		-- *****	Group A: Debits
		-- *****	Step 1: Basic value of items
		Select cast(value as bigint) into vBB_purchase_account_id from sys.settings where key='bb_purchase_account';
		Insert into pos_inv_detail(company_id, branch_id, dc, account_id, debit_amt, credit_amt, remarks)
		Select vCompany_id, vBranch_id, 'D', vBB_purchase_account_id, Sum(a.bt_amt), 0, 'Buy Back Line Items without Taxes'
		from pos.inv_bb a
		where a.inv_id = replace(pvoucher_id, ':BB', '');
		
		-- *****	Step 2: Line Item Taxes
		Select cast(value as bigint) into vBB_tax_account_id from sys.settings where key='bb_tax_account';
		Insert into pos_inv_detail(company_id, branch_id, dc, account_id, debit_amt, credit_amt, remarks)
		Select vCompany_ID, vBranch_ID, 'D', vBB_tax_account_id, sum(b.tax_amt), 0, 'Line Items Taxes'
		from pos.inv_control a
		Inner Join pos.inv_bb b On a.inv_id=b.inv_id
		Inner Join tx.tax_detail c On b.tax_schedule_id=c.tax_schedule_id And c.step_id=1
		where a.inv_id = replace(pvoucher_id, ':BB', '');

		-- *****	Group B: Credits
		-- *****	Step 1: Cash Adjustment
		Select cast(value as bigint) into vBB_adj_account_id from sys.settings where key='bb_adj_account';
		Insert into pos_inv_detail(company_id, branch_id, dc, account_id, debit_amt, credit_amt, remarks)
		Select vCompany_id, vBranch_id, 'C', vBB_adj_account_id, 0, sum(b.bt_amt), 'Buy Back Adjustment'
		from pos.inv_control a
		Inner Join pos.inv_bb b On a.inv_id = b.inv_id
		where a.inv_id = replace(pvoucher_id, ':BB', '');

		-- *****	Step 2: Tax payable
		Insert into pos_inv_detail(company_id, branch_id, dc, account_id, debit_amt, credit_amt, remarks)
		Select vCompany_ID, vBranch_ID, 'C', c.account_id, 0, sum(b.tax_amt), 'Line Items Taxes'
		from pos.inv_control a
		Inner Join pos.inv_bb b On a.inv_id=b.inv_id
		Inner Join tx.tax_detail c On b.tax_schedule_id=c.tax_schedule_id And c.step_id=1
		where a.inv_id = replace(pvoucher_id, ':BB', '')
		Group by c.account_id;

	End If;
	If vDocType In ('PSR', 'PIR') And ptable_name = 'pos.inv_control' Then
		-- *****	Group A: Debits
		-- *****	Step 1: Basic value of items
		Insert into pos_inv_detail(company_id, branch_id, dc, account_id, debit_amt, credit_amt, remarks)
		Select vCompany_id, vBranch_id, 'D', a.sale_account_id, Sum(b.bt_amt), 0, 'Line Items without taxes (net sales)'
		from pos.inv_control a
		Inner Join pos.inv_tran b On a.inv_id=b.inv_id
		where a.inv_id=pvoucher_id
		Group By a.sale_account_id;

		-- *****	Step 2: Line Item Taxes
		Insert into pos_inv_detail(company_id, branch_id, dc, account_id, debit_amt, credit_amt, remarks)
		Select vCompany_ID, vBranch_ID, 'D', c.account_id, sum(b.tax_amt), 0, 'Line Items Taxes'
		from pos.inv_control a
		Inner Join pos.inv_tran b On a.inv_id=b.inv_id
		Inner Join tx.tax_detail c On b.tax_schedule_id=c.tax_schedule_id And c.step_id=1
		where a.inv_id=pvoucher_id
		Group by c.account_id;

                -- *****	Step 3: GST Taxes
                with gtt 
                as 
                (	select sum(sgst_amt) as tax_amt, sgst_account_id as account_id
                        from tx.gst_tax_tran
                        where voucher_id = pvoucher_ID And tran_group='pos.inv_tran'
                        group by sgst_account_id
                        union all
                        select sum(cgst_amt), cgst_account_id
                        from tx.gst_tax_tran
                        where voucher_id = pvoucher_ID And tran_group='pos.inv_tran'
                        group by cgst_account_id
                        union all
                        select sum(igst_amt), igst_account_id
                        from tx.gst_tax_tran
                        where voucher_id = pvoucher_ID And tran_group='pos.inv_tran'
                        group by igst_account_id
                        union all
                        select sum(cess_amt), cess_account_id
                        from tx.gst_tax_tran
                        where voucher_id = pvoucher_ID And tran_group='pos.inv_tran'
                        group by cess_account_id
                )
                Insert into pos_inv_detail(company_id, branch_id, dc, account_id, debit_amt, credit_amt, remarks)
                Select vCompany_ID, vBranch_ID, 'D', a.account_id, sum(a.tax_amt), 0, 'Tax Amt'
                From gtt a
                group by a.account_id
                having sum(a.tax_amt) > 0;

		-- *****	Group B: Credits
		-- *****	Step 1: Get Settlement Debit(s)
		Select a.is_cash, a.is_card, a.is_cheque, a.is_customer Into vis_cash, vis_card, vis_cheque, vis_customer
		From pos.inv_settle a
		Where a.inv_id = pvoucher_id;

		If vis_cash Then
			Insert into pos_inv_detail(company_id, branch_id, dc, account_id, debit_amt, credit_amt, remarks)
			Select vCompany_id, vBranch_id, 'C', a.cash_account_id, 0, a.cash_amt, 'Cash Settlement'
			from pos.inv_settle a
			where a.inv_settle_id = pvoucher_id || ':S';
		End If;

		If vis_card Then
			Insert into pos_inv_detail(company_id, branch_id, dc, account_id, debit_amt, credit_amt, remarks)
			Select vCompany_id, vBranch_id, 'C', b.account_id, 0, a.card_amt, 'Card Settlement'
			From pos.inv_settle a
			Inner Join pos.cc_mac b On a.cc_mac_id = b.cc_mac_id
			Where a.inv_settle_id = pvoucher_id || ':S';
		End If;

		If vis_cheque Then
			Insert into pos_inv_detail(company_id, branch_id, dc, account_id, debit_amt, credit_amt, remarks)
			Select vCompany_id, vBranch_id, 'C', a.cheque_account_id, 0, a.cheque_amt, 'Cheque Settlement'
			from pos.inv_settle a
			where a.inv_settle_id = pvoucher_id || ':S';
		End If;

		If vis_customer Then
			Insert into pos_inv_detail(company_id, branch_id, dc, account_id, debit_amt, credit_amt, remarks)
			Select vCompany_id, vBranch_id, 'C', a.customer_id, 0, a.customer_amt, 'Walk-in Customer Settlement'
			from pos.inv_settle a
			where a.inv_settle_id = pvoucher_id || ':S';
		End If;

		-- *****	Step 2: Get round off with -ve case
		Select cast(value as bigint) into vRoundOffAcc_ID from sys.settings where key='st_round_off_account';
		Insert into pos_inv_detail(company_id, branch_id, dc, account_id,
			debit_amt, credit_amt, 
			remarks)
		Select vCompany_ID, vBranch_ID, case when a.rof_amt < 0 Then 'D' Else 'C' End, vRoundOffAcc_ID, 
			case when a.rof_amt < 0 Then 0 Else a.rof_amt End, case when a.rof_amt > 0 Then 0 Else -a.rof_amt End, 
			'Round Off Amt'
		from pos.inv_control a
		where a.inv_id=pvoucher_id And a.rof_amt != 0;
	End If;

	return query 
	select a.index, a.company_id, a.branch_id, a.dc, a.account_id, 0.00, 0.00, a.debit_amt, a.credit_amt, a.remarks
	from pos_inv_detail a;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION pos.trgporc_inv_post()
  RETURNS trigger AS
$BODY$
Declare vFinYear varchar(4); vVoucher_ID varchar(50)=''; vFCType_ID BigInt=0; vExchRate Numeric(18,6)=1; vDocDate Date; vCompany_ID bigint;
	vStatus smallint=0; vOldStatus smallint; vChequeDetails varchar(250)=''; vNarration Varchar(500)='';
	vType varchar(40)=''; vBillNo varchar(50)  =''; vBillDate date; vEnBillType smallint=0; vTargetBranch_ID bigint=-1; vBranch_ID bigint = -1;
	vTargetYear varchar(4) =''; vDocStageID varchar(50) = ''; vOldDocStageID varchar(50) = ''; vStageStatus smallint=0; vOldStageStatus smallint;
	vWalkInCustomer Character Varying:=''; vSmType_id BigInt:=-1;
BEGIN
		-- **** Get the Existing and new values in the table    
		Select NEW.status, OLD.status, NEW.finyear, NEW.doc_date, NEW.inv_id, NEW.narration, NEW.doc_type, NEW.branch_id,
			NEW.doc_stage_id, OLD.doc_stage_id, NEW.doc_stage_status, OLD.doc_stage_status
		into vStatus, vOldStatus, vFinYear, vDocDate, vVoucher_ID, vNarration, vType, vBranch_ID,
			vDocStageID, vOldDocStageID, vStageStatus, vOldStageStatus;

                If vType In ('PI', 'PIV') Then
                    vSmType_id:= 6; -- For Sales
                ElseIf vType In ('PSR', 'PIR') Then
                    vSmType_id:= 7; -- For Sales Return
                End If;
		
		If vType In ('PI', 'PSR', 'PIV', 'PIR') Then
			if vOldDocStageID != 'dispatched' and vDocStageID = 'dispatched' and vStatus = 3 And vStageStatus > vOldStageStatus  then	
					
			End if;
			if vOldDocStageID = 'dispatched' and vDocStageID != 'dispatched' and vStatus = 3 and vOldStatus = 3  And vStageStatus < vOldStageStatus then				
                            perform st.sp_sl_unpost (vVoucher_ID);
			End if;
			
			-- ***** Unpost the voucher 
			If vStatus<=4 and vOldStatus=5 then
				perform st.sp_sl_unpost(vVoucher_ID);
				perform st.sp_sl_unpost(vVoucher_ID || ':BB');
				perform ac.sp_gl_unpost(vVoucher_ID);
                                perform ac.sp_gl_unpost(vVoucher_ID|| ':BB');
				perform ar.sp_rl_unpost(vVoucher_ID);

                                Delete From ac.ref_ledger Where voucher_id = vVoucher_ID;
			End If;
			
			-- ***** Post the voucher 
			If vStatus=5 and vOldStatus<=4 then
				-- Post into Stock Ledger
				perform st.sp_sl_post('pos.inv_control' , vFinYear, vVoucher_ID, vDocDate, vNarration, false, vSmType_id);
				perform st.sp_sl_post_mat_lc_issue(vVoucher_ID);
				
				-- Post Into Financials
				perform ac.sp_gl_post('pos.inv_control', vFinYear, vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vChequeDetails, vNarration, vType);
				If Exists (Select * From pos.inv_settle Where inv_id=vVoucher_ID And is_customer) Then
					Select cust_name Into vWalkInCustomer From pos.inv_control Where inv_id=vVoucher_ID;
					perform ar.sp_rl_post('pos.inv_control', vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vWalkInCustomer, vEnBillType);
				End If;
				
				-- Post ByuBacks if required
				If Exists (Select * From pos.inv_bb Where inv_id = vVoucher_ID) Then
					perform st.sp_sl_post('pos.inv_bb' , vFinYear, vVoucher_ID || ':BB', vDocDate, 'Buy Backs', false);
					perform ac.sp_gl_post('pos.inv_bb' , vFinYear, vVoucher_ID || ':BB', vDocDate, vFCType_ID, vExchRate, vChequeDetails, 'Stock Buy Backs', vType);
				End If;

                                -- Post Credit Card Settlement reference
                                Insert Into ac.ref_ledger(ref_ledger_id, voucher_id, vch_tran_id, doc_date, account_id, branch_id, ref_no, ref_desc, debit_amt, credit_amt, status, last_updated)
                                Select md5(a.inv_id || ':' || a.branch_id || ':' || c.account_id)::uuid, a.inv_id, b.inv_settle_id, a.doc_date, c.account_id, a.branch_id, b.card_ref_no || '-' || b.card_no, a.inv_id || ' - card settlement', card_amt, 0, 5, current_timestamp(0)
                                From pos.inv_control a
                                Inner Join pos.inv_settle b On a.inv_id = b.inv_id
                                Inner Join pos.cc_mac c On b.cc_mac_id = c.cc_mac_id
                                Where a.inv_id = vVoucher_id 
                                    And b.is_card And a.status=5;

                                -- Post Cheques on hand reference
                                Insert Into ac.ref_ledger(ref_ledger_id, voucher_id, vch_tran_id, doc_date, account_id, branch_id, ref_no, ref_desc, debit_amt, credit_amt, status, last_updated)
                                Select md5(a.inv_id || ':' || a.branch_id || ':' || b.cheque_account_id)::uuid, a.inv_id, b.inv_settle_id, a.doc_date, b.cheque_account_id, a.branch_id, b.cheque_no , a.inv_id ||' - cheque settlement', cheque_amt, 0, 5, current_timestamp(0)
                                From pos.inv_control a
                                Inner Join pos.inv_settle b On a.inv_id = b.inv_id
                                Where a.inv_id = vVoucher_id 
                                    And b.is_cheque;
			End If;
		End If;
		
		--	Import opening Balance for next fin year if exists
		select COALESCE(a.finyear_code, '') into vTargetYear
		from sys.finyear a
		where a.year_begin = (Select (b.year_end + integer '1') from sys.finyear b where b.finyear_code =vFinYear);

		if vTargetYear != '' Then
			perform st.sp_import_stock_opbal(vCompany_ID, vTargetYear, vVoucher_ID);
		End If;
		
	RETURN NEW;
END
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE TRIGGER trg_pos_inv_post
  AFTER UPDATE
  ON pos.inv_control
  FOR EACH ROW
  EXECUTE PROCEDURE pos.trgporc_inv_post();

?==?




CREATE OR REPLACE function ar.sp_rl_status_update(pvoucher_id Varchar(50), pstatus smallint)
Returns void as
$Body$
Begin	 
    Update ac.rl_pl_alloc
    set status = pstatus 
    where voucher_id = pvoucher_id;
End;
$Body$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ar.sp_rl_unpost(pvoucher_id varchar(50))
  RETURNS void AS
$BODY$
Begin
	-- 	Delete Receivable Ledger
	Delete from ac.rl_pl where voucher_id in (pvoucher_id, 'AJ:'|| pvoucher_id);
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE or REPLACE FUNCTION ar.trgporc_invoice_post() 
RETURNS trigger 
AS $BODY$
Declare vFinYear varchar(4); vVoucher_ID varchar(50)=''; vFCType_ID BigInt=0; vExchRate Numeric(18,6)=1; vDocDate Date;
	vStatus smallint=0; vOldStatus smallint; vNarration Varchar(500)=''; vType varchar(4)=''; vChequeDetails varchar(250)=''; 
	vBillNo varchar(50)  =''; vBillDate date; vEnBillType smallint=0;
BEGIN
	If NEW.trigger_id = 'core' Then 
	    -- **** Get the Existing and new values in the table    
	    Select NEW.status, OLD.status, NEW.finyear, NEW.doc_date, NEW.invoice_id, NEW.fc_type_id, NEW.exch_rate, NEW.narration, NEW.doc_type
	    into vStatus, vOldStatus, vFinYear, vDocDate, vVoucher_ID, vFCType_ID, vExchRate, vNarration, vType;
		
	    -- ***** Unpost the voucher  
	    If vStatus<=4 and vOldStatus=5 then 

		-- *** Fire the stored procedure to update status in Receivable Ledger
		perform ar.sp_rl_status_update(vVoucher_ID, vStatus);
		
		perform ac.sp_gl_unpost(vVoucher_ID);	

		perform ar.sp_rl_unpost(vVoucher_ID);
	    End if;

	    If vStatus=5 and vOldStatus<=4 then
		
		-- *** Fire the stored procedure to update status in Receivable Ledger
		perform ar.sp_rl_status_update(vVoucher_ID, vStatus);


		-- ***	Fire the stored procedure to post the entry in General Ledger
		perform ac.sp_gl_post('ar.invoice_control', vFinYear, vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vChequeDetails, vNarration, vType);

		-- ***	Fire the stored procedure to post the entry in Receivable Ledger
		perform ar.sp_rl_post('ar.invoice_control', vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vNarration, vEnBillType);
                
                -- **** Push cheques on hand/credit card info for reference
                If Exists(Select * From information_schema.tables Where table_schema='pos' And table_name = 'inv_control') Then
                    If Exists(Select * From ar.invoice_control Where invoice_id=vVoucher_id And (annex_info->'pos'->>'is_pos')::boolean) Then
                        -- Post Credit Card Settlement reference
                        Insert Into ac.ref_ledger(ref_ledger_id, voucher_id, vch_tran_id, doc_date, account_id, branch_id, 
				ref_no, ref_desc, debit_amt, credit_amt, status, last_updated)
                        Select md5(a.invoice_id || ':' || a.branch_id || ':' || c.account_id)::uuid, a.invoice_id, a.invoice_id||':S', a.doc_date, c.account_id, a.branch_id, 
				(a.annex_info->'pos'->'inv_settle'->>'card_ref_no') || '-' || (a.annex_info->'pos'->'inv_settle'->>'card_no'), (a.annex_info->'pos'->'inv_settle'->>'card_ref_no') || '-' || (a.annex_info->'pos'->'inv_settle'->>'card_no') || ' - card settlement', (a.annex_info->'pos'->'inv_settle'->>'card_amt')::Numeric, 0, 5, current_timestamp(0)
                        From ar.invoice_control a
                        Inner Join pos.cc_mac c On (a.annex_info->'pos'->'inv_settle'->>'cc_mac_id')::BigInt = c.cc_mac_id
                        Where a.invoice_id = vVoucher_id 
                            And (a.annex_info->'pos'->'inv_settle'->>'is_card')::Boolean;

                        -- Post Cheques on hand reference
                        Insert Into ac.ref_ledger(ref_ledger_id, voucher_id, vch_tran_id, doc_date, account_id, branch_id, 
				ref_no, ref_desc, debit_amt, credit_amt, status, last_updated)
                        Select md5(a.invoice_id || ':' || a.branch_id || ':' || (a.annex_info->'pos'->'inv_settle'->>'cheque_account_id'))::uuid, a.invoice_id, a.invoice_id||':S', a.doc_date, (a.annex_info->'pos'->'inv_settle'->>'cheque_account_id')::BigInt, a.branch_id, 
				a.annex_info->'pos'->'inv_settle'->>'cheque_no', (a.annex_info->'pos'->'inv_settle'->>'cheque_no') || ' - cheque settlement', (a.annex_info->'pos'->'inv_settle'->>'cheque_amt')::Numeric, 0, 5, current_timestamp(0)
                        From ar.invoice_control a
                        Where a.invoice_id = vVoucher_id 
                            And (a.annex_info->'pos'->'inv_settle'->>'is_cheque')::Boolean;
                    End If;
                End If;
            End IF;
	End If;
RETURN NEW;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
-- Trigger on Bill control table
CREATE TRIGGER trg_invoice_post
  AFTER UPDATE
  ON ar.invoice_control
  FOR EACH ROW
  EXECUTE PROCEDURE ar.trgporc_invoice_post();

?==?
CREATE OR REPLACE function ar.sp_rl_post(ptable_name Varchar(150), pvoucher_id Varchar(50), pvoucher_date Date, pfc_type_id bigint, pexch_rate numeric(18,6),
				         pnarration Varchar(500), pen_bill_type smallint)
Returns void as
$Body$
Declare 
	vSourceBranch_ID bigint=0; vIBVoucher_ID varchar(50)=''; vCompany_ID bigint=-1; vDueDate date; vPayDays smallint = 0; vCalcType smallint = 1;
    vPayTerm_ID bigint = -1;
	
Begin
	/* WARNING THIS PROCEDURE IS AUTOMATICALLY CALLED BY A TRIGGER. CALLING THIS PROCEDURE MANUALLY IS PROHIBITED
	*/

	-- Create table to hold Voucher Data
	drop table if exists vch_detail;
	create temp TABLE vch_detail
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
		due_date date,
		pay_term_id bigint
	);


	-- Create table to hold Voucher Data
	drop table if exists vch_detail_for_rl_post;
	create temp TABLE  vch_detail_for_rl_post
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
	Insert into vch_detail_for_rl_post(index, company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
	select index, company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt
	from ac.sp_gl_post_data(ptable_name, pvoucher_id)
	where debit_amt>0 or credit_amt>0;

	-- ****	Get source branch	
	Select branch_id, company_id into vSourceBranch_ID, vCompany_ID from vch_detail_for_rl_post where index=1;
	
	-- **** Fetch Vch Table information for Debtors only
	Insert into vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt,
		pay_term_id, due_date)
	select vCompany_ID, branch_id, 
		Case when sum(debit_amt) >= sum(credit_amt) then 'D' else 'C' End, 
		account_id, 
		Case When sum(debit_amt_fc) > sum(credit_amt_fc) then (sum(debit_amt_fc) - sum(credit_amt_fc)) Else 0 End, 
		Case When sum(debit_amt_fc) < sum(credit_amt_fc) then (sum(debit_amt_fc) - sum(credit_amt_fc)) * -1 Else 0 End, 
		Case When sum(debit_amt) > sum(credit_amt) then (sum(debit_amt) - sum(credit_amt)) Else 0 End, 
		Case When sum(debit_amt) < sum(credit_amt) then (sum(debit_amt) - sum(credit_amt)) * -1 Else 0 End,
		b.pay_term_id, pvoucher_date + (cast(COALESCE(max(c.pay_days), 0) as varchar) || ' days')::interval
	from ( Select a.branch_id, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
		From vch_detail_for_rl_post a
		Inner Join ar.customer b on a.account_id = b.customer_id
		Union All
		Select a.branch_id, a.account_id, -a.net_debit_amt_fc, -a.net_credit_amt_fc, -(a.net_debit_amt - debit_exch_diff), -(a.net_credit_amt - credit_exch_diff)
		From ac.rl_pl_alloc a
		where voucher_id in (pvoucher_id, 'AJ:' || pvoucher_id)
	     ) a
        Inner Join ar.customer b on a.account_id = b.customer_id 
        Left Join ac.pay_term c on b.pay_term_id = c.pay_term_id
	group by a.branch_id, a.account_id, b.pay_term_id;

	If exists(Select * from vch_detail) Then
		-- ****	Detemine Due Date
                Select pay_term_id into vPayTerm_ID from vch_detail limit 1;
                If vPayTerm_ID != -1 then
                    select calc_type, pay_days into vCalcType, vPayDays from ac.pay_term
                    where pay_term_id in (Select pay_term_id from vch_detail limit 1);
                End If;
		If vCalcType = 0 Then -- End of month			
			SELECT (date_trunc('MONTH', pvoucher_date) + INTERVAL '1 MONTH - 1 day')::date + (cast(vPayDays as varchar) || ' days')::interval into vDueDate;
		else -- Date of Document		
			SELECT pvoucher_date + (cast(vPayDays as varchar) || ' days')::interval into vDueDate;
		End If;
		
		--raise exception 'vDueDate-%, pay_days-%', vDueDate, pay_days;
		-- **** For each record, of type Creditor make the payable reference 
		DECLARE
		    cursor_vch_table CURSOR FOR SELECT index, branch_id, account_id FROM vch_detail where debit_amt_fc > 0 or credit_amt_fc > 0 or debit_amt > 0 or credit_amt > 0;
		    vIndex int4; vBranch_ID bigint; vAccount_ID bigint;
		BEGIN
		    FOR vch_table IN cursor_vch_table LOOP
-- 			If not exists ( Select * from vch_detail where branch_id=vSourceBranch_ID and index=vch_table.index) then 
-- 				vIBVoucher_ID := 'AJ:' || pvoucher_id;			
-- 			Else 
-- 				vIBVoucher_ID := pvoucher_id;
-- 			End If;
			
			-- ****	Insert record into the Payable Ledger	
			Insert into ac.rl_pl(rl_pl_id, company_id, branch_id, voucher_id, vch_tran_id, doc_date, account_id, bill_no, bill_date, 
						fc_type_id,	exch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, narration, en_bill_type, due_date)
			select sys.sp_gl_create_id(pvoucher_id, vch_table.branch_id, vch_table.account_id, 0), a.company_id, a.branch_id, pvoucher_id, '', pvoucher_date, account_id, '', pvoucher_date,
				pfc_type_id, pexch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, pnarration, pen_bill_type, vDueDate
			from vch_detail a
			where a.index = vch_table.index;
		    END LOOP;
		END;
	End If;
End;
$Body$
LANGUAGE plpgsql;

?==?
CREATE or REPLACE FUNCTION ar.trgporc_rcpt_post() 
RETURNS trigger 
AS $BODY$
Declare vFinYear varchar(4); vVoucher_ID varchar(50)=''; vFCType_ID BigInt=0; vExchRate Numeric(18,6)=1; vDocDate Date; vEnBillType smallint = 0;
	vStatus smallint=0; vOldStatus smallint; vNarration Varchar(500)=''; vType varchar(4)=''; vChequeDetails varchar(250)=''; vTDSAmt numeric(18,4) = 0;
BEGIN
	If NEW.trigger_id = 'core' Then
                    -- **** Get the Existing and new values in the table    
            Select NEW.status, OLD.status, NEW.finyear, NEW.doc_date, NEW.voucher_id, NEW.fc_type_id, NEW.exch_rate, NEW.narration, NEW.doc_type,NEW.tds_amt
            into vStatus, vOldStatus, vFinYear, vDocDate, vVoucher_ID, vFCType_ID, vExchRate, vNarration, vType, vTDSAmt;

            -- Set en_bill_type = 1 for advance and 0 for others in receivable_ledger
            If vType = 'ACR' then 
                    vEnBillType := 1;
            ElseIf vType in ('RCPT', 'MCR') And NEW.adv_amt > 0  then 
                    vEnBillType := 1;
            End If;
            -- ***** Unpost the voucher  
            If vStatus<=4 and vOldStatus=5 then 		
		    
                if vType in ('ACR', 'RCPT', 'MCR') then
                    Delete from ar.tds_reconciled where voucher_id=vVoucher_ID;
                End if;

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

                -- *** Fire the stored procedure to update status in Receivable Ledger
                perform ar.sp_rl_status_update(vVoucher_ID, vStatus);

                perform ac.sp_gl_unpost(vVoucher_ID);		    

                perform ar.sp_rl_unpost(vVoucher_ID);		    

                -- *** Update PL Alloc statuc for RCPT Type AR to AP
                perform ap.sp_pl_status_update(vVoucher_ID, vStatus);
            End if;

            If vStatus=5 and vOldStatus<=4 then
                If NEW.cheque_number<>'' then
                        Select 'Ch No. ' || cast(NEW.cheque_number as varchar) || ' Dt. ' || to_char(NEW.cheque_date, 'dd/MM/yyyy') || ' of ' || NEW.cheque_bank || ', ' || NEW.cheque_branch into vChequeDetails;
                End If;

                If vTDSAmt != 0 then 
                    If vNarration != '' then
                        vNarration := 'Tax Ded./With.:' || to_char(vTDSAmt, '99,99,99,999.99')  || E'\n' ||vNarration;
                    Else
                        vNarration := 'Tax Ded./With.:' || to_char(vTDSAmt, '99,99,99,999.99');
                    End If;
                End If;

                -- *** Fire the stored procedure to update status in Receivable Ledger
                perform ar.sp_rl_status_update(vVoucher_ID, vStatus);


                -- ***	Fire the stored procedure to post the entry in General Ledger
                perform ac.sp_gl_post('ar.rcpt_control', vFinYear, vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vChequeDetails, vNarration, vType);


                -- ***	Fire the stored procedure to post the entry in Receivable Ledger
                perform ar.sp_rl_post('ar.rcpt_control', vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vNarration, vEnBillType);


                -- *** Update PL Alloc statuc for RCPT Type AR to AP
                perform ap.sp_pl_status_update(vVoucher_ID, vStatus);

                if vType in ('ACR', 'RCPT', 'MCR') then
                        -- ***	If post entries into tds_reconciled table to reconcile TDS
                        Insert into ar.tds_reconciled (company_id, branch_id, voucher_id, doc_date, customer_id, tds_amt, tds_amt_fc, 
                                                        reconciled, reco_date, last_updated)
                        select a.company_id, a.branch_id, a.voucher_id, a.doc_date, a.customer_account_id, a.tds_amt, a.tds_amt_fc,
                                                            false, null, current_timestamp(0)
                        from ar.rcpt_control a
                        Where a.voucher_id=vVoucher_ID
                            And a.tds_amt > 0;
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
            End IF;
	End IF;
RETURN NEW;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
-- Trigger on Bill control table
CREATE TRIGGER trg_rcpt_post
  AFTER UPDATE
  ON ar.rcpt_control
  FOR EACH ROW
  EXECUTE PROCEDURE ar.trgporc_rcpt_post();

?==?
CREATE OR REPLACE FUNCTION ar.sp_customer_opbl_ref_add_update(INOUT prl_pl_id uuid, pcompany_id bigint, pbranch_id bigint, pvoucher_id character varying, 
				pdoc_date date, paccount_id bigint, pfc_type_id bigint, pexch_rate numeric, pdebit_amt_fc numeric, pcredit_amt_fc numeric, 
				pdebit_amt numeric, pcredit_amt numeric, pnarration character varying, pen_bill_type smallint)
RETURNS uuid AS
$BODY$
	Declare vPayDays smallint = 0; vDueDate date;
Begin
	Select b.pay_days into vPayDays 
	From ar.customer a 
	Inner Join ac.pay_term b on a.pay_term_id = b.pay_term_id
	Where a.customer_id = paccount_id;
	
	vDueDate := pdoc_date + (cast(vPayDays as varchar) || ' days')::interval;
	if exists(Select * from ac.rl_pl where rl_pl_id=prl_pl_id) Then
		Update ac.rl_pl
		Set voucher_id=pvoucher_id, 
			doc_date=pdoc_date,
			fc_type_id=pfc_type_id,	
			exch_rate=pexch_rate, 
			debit_amt_fc=pdebit_amt_fc, 
			credit_amt_fc=pcredit_amt_fc, 
			debit_amt=pdebit_amt, 
			credit_amt=pcredit_amt, 
			narration=pnarration,
			due_date = vDueDate
		Where rl_pl_id=prl_pl_id;
			
	Else
		Insert into ac.rl_pl(rl_pl_id, company_id, branch_id, voucher_id, vch_tran_id, doc_date, account_id, bill_no, bill_date, 
				 fc_type_id, exch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, narration, en_bill_type, is_opbl, due_date)
		select prl_pl_id, pcompany_id, pbranch_id, pvoucher_id, '', pdoc_date, paccount_id, '', pdoc_date,
				 pfc_type_id, pexch_rate, pdebit_amt_fc, pcredit_amt_fc, pdebit_amt, pcredit_amt, pnarration, pen_bill_type, true, vDueDate;
	End If;
End;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE or REPLACE FUNCTION ar.trgporc_rl_alloc_add() 
RETURNS trigger 
AS $BODY$
	Declare vAccount_ID bigint = -1; vVoucher_ID varchar(50)=''; vExchRate Numeric(18,6)=1; vReceivableLedger_ID uuid=null; vNetDebitAmt numeric(18,4)=0; vNetDebitAmtFC numeric(18,4) = 0; vDC varchar(1);
	vNetCreditAmt numeric(18,4)=0; vNetCreditAmtFC numeric(18,4) = 0; vBalanceAmt numeric(18,4) = 0; vBalanceAmtFC numeric(18,4) = 0; vSettledAmt numeric(18,4) = 0; vSettledAmtFC numeric(18,4)=0;
	vBranch_ID bigint; vCompany_ID bigint; vDocDate date; vBalLocal numeric(18,4) = 0;
BEGIN
    -- **** Get the Existing and new values in the table    
    Select NEW.account_id, NEW.voucher_id, NEW.exch_rate, NEW.rl_pl_id, NEW.net_debit_amt, NEW.net_debit_amt_fc, NEW.net_credit_amt, NEW.net_credit_amt_fc, NEW.doc_date
    into vAccount_ID, vVoucher_ID, vExchRate, vReceivableLedger_ID, vNetDebitAmt, vNetDebitAmtFC, vNetCreditAmt, vNetCreditAmtFC, vDocDate;

    If NEW.debit_amt_fc > 0 or NEW.debit_amt > 0 then
	vSettledAmt := NEW.debit_amt + NEW.write_off_amt + NEW.tds_amt + NEW.other_exp;
	vSettledAmtFC := vNetDebitAmtFC;
	vDC := 'C';
    ElseIf NEW.credit_amt_fc > 0 or NEW.credit_amt > 0 then
	vSettledAmt := NEW.credit_amt + NEW.write_off_amt + NEW.tds_amt + NEW.other_exp;
	vSettledAmtFC := vNetCreditAmtFC;
	vDC := 'D';
    End If;
        
    If vSettledAmtFC > 0 Then
	Select branch_id, company_id into vBranch_ID, vCompany_ID
	From ac.rl_pl
	where rl_pl_id=vReceivableLedger_ID;

	
	select a.balance, a.balance_fc into vBalanceAmt, vBalanceAmtFC
	from ar.fn_receivable_ledger_balance(vCompany_ID, vBranch_ID, vAccount_ID, vDocDate, vVoucher_ID, vDC, vReceivableLedger_ID) a;	
	
	If vBalanceAmtFC = vSettledAmtFC Then
	    If NEW.debit_amt_fc > 0 or NEW.debit_amt > 0 then
		NEW.debit_exch_diff = vBalanceAmt - vSettledAmt;
		NEW.net_debit_amt = NEW.debit_amt + NEW.write_off_amt + NEW.tds_amt + NEW.other_exp + NEW.debit_exch_diff;
	    ElseIf NEW.credit_amt_fc > 0 or NEW.credit_amt > 0 then
		NEW.credit_exch_diff = vBalanceAmt - vSettledAmt;
		NEW.net_credit_amt = NEW.credit_amt + NEW.write_off_amt + NEW.tds_amt + NEW.other_exp + NEW.credit_exch_diff;
	    End If;  
	ElseIf vBalanceAmtFC > vSettledAmtFC Then
            If vBalanceAmtFC !=0 Then
		vBalLocal = vBalanceAmt * (vSettledAmtFC/vBalanceAmtFC);
            End If;
	
            If NEW.debit_amt_fc > 0 or NEW.debit_amt > 0 then
		NEW.debit_exch_diff = vBalLocal - vSettledAmt;
		NEW.net_debit_amt = NEW.debit_amt + NEW.write_off_amt + NEW.tds_amt + NEW.other_exp + NEW.debit_exch_diff;		
	    ElseIf NEW.credit_amt_fc > 0 or NEW.credit_amt > 0 then
		NEW.credit_exch_diff = vBalLocal - vSettledAmt;
		NEW.net_credit_amt = NEW.credit_amt + NEW.write_off_amt + NEW.tds_amt + NEW.other_exp + NEW.credit_exch_diff;
	    End If;
	End If;  
    Else
	NEW.credit_exch_diff = 0;
	NEW.debit_exch_diff = 0;
    End If;
    
RETURN NEW;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
CREATE TRIGGER trg_rl_alloc
  BEFORE INSERT
  ON ac.rl_pl_alloc
  FOR EACH ROW
  EXECUTE PROCEDURE ar.trgporc_rl_alloc_add();

?==?
CREATE OR REPLACE FUNCTION ar.sp_inv_dispatched_update(pinv_type varchar(1), pvoucher_id varchar(50), pdispatched_date date, pdispatch_method smallint, pdispatch_remark character varying)
  RETURNS void AS
$BODY$
Begin	
	If pinv_type = 'A' then
		Update ar.invoice_control
		Set dispatched_date = pdispatched_date,
			dispatch_method = pdispatch_method,
			is_dispatched = true,
            dispatch_remark = pdispatch_remark
		Where invoice_id = pvoucher_id;
	End If;
	If pinv_type = 'B' then
		Update pub.invoice_control
		Set dispatched_date = pdispatched_date,
			dispatch_method = pdispatch_method,
			is_dispatched = true,
            dispatch_remark = pdispatch_remark
		Where voucher_id = pvoucher_id;
	End If;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE function ar.sp_rl_manual_ref_post(prl_id uuid, pamt_to_be_settled numeric(18,4))
Returns void as
$Body$
Declare vDebitAmt numeric(18,4) = 0;
Begin	 
    Update ac.rl_pl a
    set debit_amt = (a.debit_amt - pamt_to_be_settled)
    where a.rl_pl_id = prl_id;

    select a.debit_amt into vDebitAmt from ac.rl_pl a
    where a.rl_pl_id = prl_id;

    if vDebitAmt = 0 then
	delete from ac.rl_pl a
	where a.rl_pl_id = prl_id;
    End If;
End;
$Body$
LANGUAGE plpgsql;


?==?
CREATE OR REPLACE function ar.sp_inv_manual_ref_update(pvoucher_id Varchar(50), padv_amt_tot numeric(18,4))
Returns void as
$Body$
Begin	 
    --ToDo:: Add code to update adv_amt in invoice control
    --ToDo: Remove the following code
	if exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'invoice_control') then
		if exists (SELECT * FROM pub.invoice_control where voucher_id = pvoucher_id) then
			Update pub.invoice_control a
			set advance_amt = advance_amt + padv_amt_tot,
				net_debit_amt = net_debit_amt - padv_amt_tot
			where a.voucher_id = pvoucher_id;
		End If;
	End If;
End;
$Body$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ar.sp_cust_gst_update(pcustomer_id bigint, pnew_gst_state_id bigint, pnew_gstin varchar(17))
  RETURNS void AS
$BODY$
Begin	
	-- Update gst_state_id in billing address and customer tax_info	
	Update ar.customer
	Set annex_info = jsonb_set(annex_info, '{tax_info, gst_state_id}', (pnew_gst_state_id::varchar)::jsonb, true)
	Where customer_id = pcustomer_id;
	
	-- Update gstin in billing address and customer tax_info	
	Update ar.customer
	Set annex_info = jsonb_set(annex_info, '{tax_info, gstin}', pnew_gstin::jsonb, true)
	Where customer_id = pcustomer_id;

	update sys.address
	set gst_state_id = pnew_gst_state_id,
		gstin = trim(both '"' from pnew_gstin)
	where address_id = (select a.address_id from ar.customer a where a.customer_id = pcustomer_id);
	
END;
$BODY$
  LANGUAGE plpgsql;

?==?  
CREATE OR REPLACE FUNCTION ar.fn_cust_gst_coll(pview_type_id bigint)
RETURNS TABLE
(    
    customer_id bigint,
    customer varchar(250),
    address text,
    gst_state_id bigint,
    gst_state_with_code varchar(300),
    gstin varchar(15)
) 
AS
$BODY$
Begin	 
	return query
	Select a.customer_id, a.customer, '<span>' || c.address || E'<br/>' || c.city || case when c.pin = '' then '' else ' - ' end  || c.pin || E'<br/>' || c.country ||  case when c.state = '' then '' else ', ' end  || c.state || '</span>' as address,
		COALESCE((a.annex_info->'tax_info'->>'gst_state_id')::bigint, -1) as gst_state_id, (COALESCE(d.gst_state_code, '') || case when COALESCE(d.state_name, '') = '' then '' else ('-' || d.state_name) end)::varchar, 
		COALESCE((a.annex_info->'tax_info'->>'gstin')::varchar, '') as gstin
	from ar.customer a
	inner join sys.address c on a.address_id = c.address_id
	left join tx.gst_state d on COALESCE((a.annex_info->'tax_info'->>'gst_state_id')::bigint, -1) = d.gst_state_id
	where case 
		when pview_type_id = 0  then -- without GSTIN
			(COALESCE((a.annex_info->'tax_info'->>'gst_state_id')::bigint, -1) = -1 OR COALESCE((a.annex_info->'tax_info'->>'gstin')::varchar, '') = '')			
		when pview_type_id = 1  then -- with GSTIN
			(COALESCE((a.annex_info->'tax_info'->>'gst_state_id')::bigint, -1) != -1 AND COALESCE((a.annex_info->'tax_info'->>'gstin')::varchar, '') != '')
		Else
			(1=1)
		End;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ar.fn_cust_info_for_update(IN pcustomer_id bigint, IN pcredit_limit_type smallint, IN ppay_term_id bigint)
RETURNS TABLE
(   
    customer_id bigint,
    customer character varying,
    credit_limit_type smallint,
    credit_limit_type_desc character varying,
    credit_limit numeric(18,4),
    pay_term_id bigint,
    pay_term character varying
) 
AS
$BODY$
Begin	 
	return query
	Select a.customer_id, a.customer, a.credit_limit_type,
		case when a.credit_limit_type = 0 then 'No Credit'::varchar
		     when a.credit_limit_type = 1 then 'Unlimited Credit'::varchar
		     when a.credit_limit_type = 2 then 'Apply Credit Limit'::varchar
		End as credit_limit_type_desc, a.credit_limit,
                 a.pay_term_id, b.pay_term
	from ar.customer a
	inner join ac.pay_term b on a.pay_term_id = b.pay_term_id and b.for_cust = true
	where (a.customer_id = pcustomer_id or pcustomer_id = 0)
		And (a.pay_term_id = ppay_term_id or ppay_term_id = 0)
		And (a.credit_limit_type = pcredit_limit_type or pcredit_limit_type = -1);
END;
$BODY$
  LANGUAGE plpgsql;

?==?
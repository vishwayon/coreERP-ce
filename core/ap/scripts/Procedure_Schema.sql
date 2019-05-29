CREATE OR REPLACE function ap.sp_pl_status_update(pvoucher_id Varchar(50), pstatus smallint)
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
CREATE OR REPLACE FUNCTION ap.sp_pl_unpost(pvoucher_id varchar(50))
  RETURNS void AS
$BODY$
Begin
	-- 	Delete Payable Ledger
	Delete from ac.rl_pl where voucher_id in (pVoucher_ID, 'AJ:'|| pVoucher_ID);
        

        -- *** Update status in tds.bill_tds_tran
        Update tds.bill_tds_tran
        set status = 0
        Where voucher_id = pvoucher_id;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
-- Procedure to Post voucher entries in Payable Ledger
CREATE OR REPLACE function ap.sp_pl_post(ptable_name Varchar(150), pvoucher_id Varchar(50), pvoucher_date Date, pfc_type_id bigint, pexch_rate numeric(18,6),
						pbill_no varchar(50), pbill_date date, pnarration Varchar(500), pen_bill_type smallint)
Returns void as
$Body$
Declare 
	vSourceBranch_ID bigint=0; vIBVoucher_ID varchar(50)=''; vCompany_ID bigint=-1; vCalcType smallint = 1; vDueDate date; vPayDays smallint = 0; vPayTerm_ID bigint = -1;
	
Begin
	/* WARNING THIS PROCEDURE IS AUTOMATICALLY CALLED BY A TRIGGER. CALLING THIS PROCEDURE MANUALLY IS PROHIBITED
	*/

	-- Avoid null error 
	If pbill_date is null Then 
		pbill_date:=pvoucher_date;
	End If;

	-- Create table to hold Voucher Data
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
		credit_amt numeric(18,4),
		due_date date,
		pay_term_id bigint
	);


	-- Create table to hold Voucher Data
	DROP TABLE IF EXISTS vch_detail_for_pl_post;
	create temp TABLE  vch_detail_for_pl_post
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
	Insert into vch_detail_for_pl_post(index, company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
	select index, company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt
	from ac.sp_gl_post_data(ptable_name, pvoucher_id)
	where debit_amt<>0 or credit_amt<>0;


	-- ****	Get source branch	
	Select branch_id, company_id into vSourceBranch_ID, vCompany_ID from vch_detail_for_pl_post where index=1;
	
	-- **** Fetch Vch Table information for creditors only
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
		From vch_detail_for_pl_post a
		Inner Join ap.supplier b on a.account_id = b.supplier_id
		Union All
		Select a.branch_id, a.account_id, -a.net_debit_amt_fc, -a.net_credit_amt_fc, -(a.net_debit_amt - debit_exch_diff), -(a.net_credit_amt - credit_exch_diff)
		From ac.rl_pl_alloc a
		where voucher_id in (pvoucher_id, 'AJ:' || pvoucher_id)
	     ) a
        Inner Join ap.supplier b on a.account_id = b.supplier_id 
        Left Join ac.pay_term c on b.pay_term_id = c.pay_term_id
	group by branch_id, account_id, b.pay_term_id;

	If exists(Select * from vch_detail) Then

		-- ****	Detemine Due Date
        Select pay_term_id into vPayTerm_ID from vch_detail limit 1;
        If vPayTerm_ID != -1 then
            select calc_type, pay_days into vCalcType, vPayDays from ac.pay_term
            where pay_term_id in (Select pay_term_id from vch_detail limit 1);
        End If;

		If vCalcType = 0 Then -- End of month			
			SELECT (date_trunc('MONTH', pvoucher_date) + INTERVAL '1 MONTH - 1 day')::date + (cast(vPayDays as varchar) || ' days')::interval into vDueDate;
		End If;
		
		If vCalcType = 1 Then -- Date of Document		
			SELECT pvoucher_date + (cast(vPayDays as varchar) || ' days')::interval into vDueDate;
		End If;
		
		If vCalcType = 2 Then -- Bill Date (Payable only)		
			SELECT pbill_date + (cast(vPayDays as varchar) || ' days')::interval into vDueDate;
		End If;
		

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
			Insert into ac.rl_pl(rl_pl_id, company_id, branch_id, voucher_id, vch_tran_id, doc_date, account_id, 
						bill_no, bill_date, fc_type_id,	exch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, narration, en_bill_type, due_date)
			select sys.sp_gl_create_id(pvoucher_id, vch_table.branch_id, vch_table.account_id, 0), a.company_id, a.branch_id, pvoucher_id, '', pvoucher_date, account_id, 
				pbill_no, pbill_date, pfc_type_id, pexch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, pnarration, pen_bill_type, vDueDate
			from vch_detail a
			where a.index = vch_table.index;
		    END LOOP;
		END;
	End If;

        -- *** Update status in tds.bill_tds_tran
        Update tds.bill_tds_tran
        set status = 5
        Where voucher_id = pvoucher_id;
End;
$Body$
LANGUAGE plpgsql;

?==?
Create or Replace function ap.sp_ref_ledger_post(pvch_id Varchar(50))
Returns Void
As
$BODY$
Begin
	Insert Into ac.ref_ledger(ref_ledger_id, voucher_id, doc_date, account_id, branch_id, 
				ref_no, ref_desc, debit_amt, credit_amt, last_updated, vch_tran_id, status)
	Select md5(b.bill_tran_id)::uuid, a.bill_id, a.doc_date, b.account_id, b.branch_id,  
		a.bill_no, c.account_head || '[' || a.bill_no || ' Dt ' || a.bill_date || ']',
		b.debit_amt, 0, current_timestamp(0), b.bill_tran_id, 5
	From ap.bill_control a
	Inner Join ap.bill_tran b On a.bill_id = b.bill_id
	Inner Join ac.account_head c On a.supplier_id = c.account_id
	Inner Join ac.account_head d On b.account_id = d.account_id
	Where a.bill_id = pvch_id And d.account_type_id = 49; --Landed Cost Type
End
$BODY$
Language plpgsql;

?==?
Create function ap.sp_ref_ledger_unpost(pvch_id Varchar(50))
Returns Void
As
$BODY$
Begin
	Delete From ac.ref_ledger a
        Where a.voucher_id = pvch_id
            And a.account_id In (Select a.account_id from ac.account_head b where b.account_type_id = 49); --Landed Cost Type
End
$BODY$
Language plpgsql;

?==?
CREATE or REPLACE FUNCTION ap.trgporc_bill_post() 
RETURNS trigger 
AS $BODY$
Declare vFinYear varchar(4); vVoucher_ID varchar(50)=''; vFCType_ID BigInt=0; vExchRate Numeric(18,6)=1; vDocDate Date;
	vStatus smallint=0; vOldStatus smallint; vNarration Varchar(1000)=''; vType varchar(4)=''; vChequeDetails varchar(250)=''; 
	vBillNo varchar(50)  =''; vBillDate date; vEnBillType smallint=0; 
BEGIN
    -- **** Get the Existing and new values in the table    
    Select NEW.status, OLD.status, NEW.finyear, NEW.doc_date, NEW.bill_id, NEW.fc_type_id, NEW.exch_rate, NEW.narration, NEW.doc_type, NEW.bill_no, NEW.bill_date
    into vStatus, vOldStatus, vFinYear, vDocDate, vVoucher_ID, vFCType_ID, vExchRate, vNarration, vType, vBillNo, vBillDate;
    
    --**** Skip posting for Purchase Order Others and Trip Sheet
    If vType = 'POTH' or vType = 'TRPS' then 
    	RETURN NEW;
    End If;

    -- ***** Unpost the voucher  
    If vStatus<=4 and vOldStatus=5 then
        -- *** Fire the stored procedure to update status in Payable Ledger Alloc
        perform ap.sp_pl_status_update(vVoucher_ID, vStatus); 		
        perform ac.sp_gl_unpost(vVoucher_ID);
        perform tds.sp_tds_unpost(vVoucher_ID);
        perform ap.sp_pl_unpost(vVoucher_ID);
        perform ap.sp_pl_unpost(vVoucher_ID|| ':TDS');
        perform ap.sp_ref_ledger_unpost(vVoucher_ID);
        
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
    End if;

    If vStatus=5 and vOldStatus<=4 then    
        If (vBillNo != '' or vBillNo != 'BNR') then 
            If vNarration != '' then
                vNarration := 'Bill No: ' || vBillNo || ' Dated: ' || to_char(vBillDate, 'DD-mm-yyyy') || E'\n' ||vNarration;
            Else
                vNarration := 'Bill No: ' || vBillNo || ' Dated: ' || to_char(vBillDate, 'DD-mm-yyyy');
            End If;
        End If;

        -- *** Fire the stored procedure to update status in Payable Ledger Alloc
        perform ap.sp_pl_status_update(vVoucher_ID, vStatus);
        -- ***	Fire the stored procedure to post the entry in General Ledger
        perform ac.sp_gl_post('ap.bill_control', vFinYear, vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vChequeDetails, left(vNarration, 500), vType);
        
        -- ***	Fire the stored procedure to post the entry in Payable Ledger
        perform ap.sp_pl_post('ap.bill_control', vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vBillNo, vBillDate, left(vNarration, 500), vEnBillType);

	if exists(Select * from tds.bill_tds_tran where bill_tds_tran_id = vVoucher_ID and (tds_base_rate_amt + tds_ecess_amt + tds_surcharge_amt) > 0)then 
		-- ***  Post TDS entries
		perform tds.sp_tds_post('ap.bill_control', vFinYear, vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vChequeDetails, left(vNarration, 500), vType);
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
                
        -- Insert row in subhead ledger if itc false for any tran row
        Perform ac.sp_shl_non_itc_post(vVoucher_id);

        -- Insert reference ledger entries where required
        Perform ap.sp_ref_ledger_post(vVoucher_id);
    End IF;
    RETURN NEW;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
-- Trigger on Bill control table
CREATE TRIGGER trg_bill_post
  AFTER UPDATE
  ON ap.bill_control
  FOR EACH ROW
  EXECUTE PROCEDURE ap.trgporc_bill_post();

?==?
-- Procedure to call from trigger
CREATE or REPLACE FUNCTION ap.trgporc_pymt_post() 
RETURNS trigger 
AS $BODY$
Declare vFinYear varchar(4); vVoucher_ID varchar(50)=''; vFCType_ID BigInt=0; vExchRate Numeric(18,6)=1; vDocDate Date;
	vStatus smallint=0; vOldStatus smallint; vNarration Varchar(500)=''; vType varchar(4)=''; vChequeDetails varchar(250)=''; 
	vBillNo varchar(50)  =''; vBillDate date; vEnBillType smallint=0;
BEGIN
        -- **** Get the Existing and new values in the table    
        Select NEW.status, OLD.status, NEW.finyear, NEW.doc_date, NEW.voucher_id, NEW.fc_type_id, NEW.exch_rate, NEW.narration, NEW.doc_type
        into vStatus, vOldStatus, vFinYear, vDocDate, vVoucher_ID, vFCType_ID, vExchRate, vNarration, vType;

                          --**** Skip posting for Bank Transfer
        If vType = 'BT' then 
            RETURN NEW;
        End If;

	-- Set en_bill_type = 1 for advance and 0 for others in receivable_ledger
	If vType = 'ASP' then 
		vEnBillType := 1;
	End If;

        -- ***** Unpost the voucher  
        If vStatus<=4 and vOldStatus=5 then 
            -- *** Fire the stored procedure to update status in Payable Ledger Alloc
            perform ap.sp_pl_status_update(vVoucher_ID, vStatus);		
            perform ac.sp_gl_unpost(vVoucher_ID);
            perform ap.sp_pl_unpost(vVoucher_ID);         	
            perform ac.sp_gl_unpost(vVoucher_ID|| ':TDS');   
	    	perform ap.sp_pl_unpost(vVoucher_ID|| ':TDS');
        
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
        End if;

        If vStatus=5 and vOldStatus<=4 then
            If NEW.cheque_number<>'' then
                    Select 'Ch No. ' || cast(NEW.cheque_number as varchar) || ' Dt. ' || to_char(NEW.cheque_date, 'dd/MM/yyyy') || ' of ' || NEW.cheque_bank || ', ' || NEW.cheque_branch into vChequeDetails;
            End If;
            -- *** Fire the stored procedure to update status in Payable Ledger Alloc
            perform ap.sp_pl_status_update(vVoucher_ID, vStatus);
            -- ***	Fire the stored procedure to post the entry in General Ledger
            perform ac.sp_gl_post('ap.pymt_control', vFinYear, vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vChequeDetails, vNarration, vType);

            -- ***	Fire the stored procedure to post the entry in Payable Ledger
            perform ap.sp_pl_post('ap.pymt_control', vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vBillNo, vBillDate, vNarration, vEnBillType);

            
            If vType = 'ASP' then 
                if exists(Select * from tds.bill_tds_tran where bill_tds_tran_id = vVoucher_ID  and (tds_base_rate_amt + tds_ecess_amt + tds_surcharge_amt) > 0) then 		    
                    -- Step 2: Call sp_gl_post with table_name tds to post tds entry 
                    perform ac.sp_gl_post('tds.bill_tds_tran' , vFinYear, vVoucher_ID || ':TDS', vDocDate, vFCType_ID, vExchRate, '', 'Being Tax Deducted at Source', vType);

                    perform ap.sp_pl_post('tds.bill_tds_tran', vVoucher_ID || ':TDS', vDocDate, vFCType_ID, vExchRate, '', vBillDate, 'Being Tax Deducted at Source', vEnBillType);
                End If;
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
    RETURN NEW;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
-- Trigger on Bill control table
CREATE TRIGGER trg_pymt_post
  AFTER UPDATE
  ON ap.pymt_control
  FOR EACH ROW
  EXECUTE PROCEDURE ap.trgporc_pymt_post();

?==?
CREATE OR REPLACE FUNCTION ap.sp_supplier_opbl_ref_add_update(INOUT prl_pl_id uuid, pcompany_id bigint, pbranch_id bigint, 
                    pvoucher_id character varying, pdoc_date date, paccount_id bigint, pbill_no character varying, 
                    pbill_date date, pfc_type_id bigint, pexch_rate numeric, pdebit_amt_fc numeric, pcredit_amt_fc numeric, pdebit_amt numeric, 
                    pcredit_amt numeric, pnarration character varying, pen_bill_type smallint)
RETURNS uuid AS
$BODY$
	Declare vPayDays smallint = 0; vDueDate date;
Begin
	Select b.pay_days into vPayDays 
	From ap.supplier a 
	Inner Join ac.pay_term b on a.pay_term_id = b.pay_term_id
	Where a.supplier_id = paccount_id;
	
	vDueDate := pdoc_date + (cast(vPayDays as varchar) || ' days')::interval;
	
	if exists(Select * from ac.rl_pl where rl_pl_id=prl_pl_id) Then
		Update ac.rl_pl
		Set voucher_id=pvoucher_id, 
			doc_date=pdoc_date,
			bill_date=pdoc_date, 
			fc_type_id=pfc_type_id,	
			exch_rate=pexch_rate, 
			debit_amt_fc=pdebit_amt_fc, 
			credit_amt_fc=pcredit_amt_fc, 
			debit_amt=pdebit_amt, 
			credit_amt=pcredit_amt, 
			narration=pnarration,
			due_date=vDueDate
		Where rl_pl_id=prl_pl_id;
			
	Else
		Insert into ac.rl_pl(rl_pl_id, company_id, branch_id, voucher_id, vch_tran_id, doc_date, account_id, 
					bill_no, bill_date, fc_type_id,	exch_rate, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, narration, en_bill_type, is_opbl, due_date)
		select prl_pl_id, pcompany_id, pbranch_id, pvoucher_id, '', pdoc_date, paccount_id, 
			pbill_no, pbill_date, pfc_type_id, pexch_rate, pdebit_amt_fc, pcredit_amt_fc, pdebit_amt, pcredit_amt, pnarration, pen_bill_type, true, vDueDate;
	End If;
End;
$BODY$
  LANGUAGE plpgsql;

?==?

CREATE or REPLACE FUNCTION ap.trgporc_pl_alloc_add() 
RETURNS trigger 
AS $BODY$
	Declare vAccount_ID bigint = -1; vVoucher_ID varchar(50)=''; vExchRate Numeric(18,6)=1; vPayableLedger_ID uuid=null; vNetDebitAmt numeric(18,4)=0; vNetDebitAmtFC numeric(18,4) = 0; vDC varchar(1);
	vNetCreditAmt numeric(18,4)=0; vNetCreditAmtFC numeric(18,4) = 0; vBalanceAmt numeric(18,4) = 0; vBalanceAmtFC numeric(18,4) = 0; vSettledAmt numeric(18,4) = 0; vSettledAmtFC numeric(18,4)=0;
	vBranch_ID bigint; vCompany_ID bigint; vDocDate date; vBalLocal numeric(18,4) = 0;
BEGIN
    -- **** Get the Existing and new values in the table    
    Select NEW.account_id, NEW.voucher_id, NEW.exch_rate, NEW.rl_pl_id, NEW.net_debit_amt, NEW.net_debit_amt_fc, NEW.net_credit_amt, NEW.net_credit_amt_fc, NEW.doc_date
    into vAccount_ID, vVoucher_ID, vExchRate, vPayableLedger_ID, vNetDebitAmt, vNetDebitAmtFC, vNetCreditAmt, vNetCreditAmtFC, vDocDate;

    If NEW.debit_amt_fc > 0 or NEW.debit_amt > 0 then
	vSettledAmt := NEW.debit_amt + NEW.write_off_amt;
	vSettledAmtFC := vNetDebitAmtFC;
	vDC := 'C';
    ElseIf NEW.credit_amt_fc > 0 or NEW.credit_amt > 0 then
	vSettledAmt := NEW.credit_amt + NEW.write_off_amt;
	vSettledAmtFC := vNetCreditAmtFC;
	vDC := 'D';
    End If;
        
    If vSettledAmtFC > 0 Then
	Select branch_id, company_id into vBranch_ID, vCompany_ID
	From ac.rl_pl
	where rl_pl_id=vPayableLedger_ID;
	
	select a.balance, a.balance_fc into vBalanceAmt, vBalanceAmtFC
	from ap.fn_payable_ledger_balance(vCompany_ID, vBranch_ID, vAccount_ID, vDocDate, vVoucher_ID, vDC, vPayableLedger_ID) a;	
	
	If vBalanceAmtFC = vSettledAmtFC Then
	    If NEW.debit_amt_fc > 0 or NEW.debit_amt > 0 then
		NEW.debit_exch_diff = vBalanceAmt - vSettledAmt;
		NEW.net_debit_amt = NEW.debit_amt + NEW.write_off_amt + NEW.debit_exch_diff;
	    ElseIf NEW.credit_amt_fc > 0 or NEW.credit_amt > 0 then
		NEW.credit_exch_diff = vBalanceAmt - vSettledAmt;
		NEW.net_credit_amt = NEW.credit_amt + NEW.write_off_amt + NEW.credit_exch_diff;
	    End If;  
	ElseIf vBalanceAmtFC > vSettledAmtFC Then
            If vBalanceAmtFC !=0 Then
		vBalLocal = vBalanceAmt * (vSettledAmtFC/vBalanceAmtFC);
            End If;
	
            If NEW.debit_amt_fc > 0 or NEW.debit_amt > 0 then
		NEW.debit_exch_diff = vBalLocal - vSettledAmt;
		NEW.net_debit_amt = NEW.debit_amt + NEW.write_off_amt + NEW.debit_exch_diff;		
	    ElseIf NEW.credit_amt_fc > 0 or NEW.credit_amt > 0 then
		NEW.credit_exch_diff = vBalLocal - vSettledAmt;
		NEW.net_credit_amt = NEW.credit_amt + NEW.write_off_amt + NEW.credit_exch_diff;
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
CREATE TRIGGER trg_pl_alloc
  BEFORE INSERT
  ON ac.rl_pl_alloc
  FOR EACH ROW
  EXECUTE PROCEDURE ap.trgporc_pl_alloc_add();

?==?
CREATE OR REPLACE FUNCTION ap.sp_bill_no_update(pbill_type varchar(1), pvoucher_id varchar(50), pbill_no varchar(20), pbill_date date, paccount_id bigint)
  RETURNS void AS
$BODY$
	Declare vCalcType smallint = 1; vDueDate date; vPayDays smallint = 0;
Begin
	-- ****	Detemine Due Date
	select a.calc_type, a.pay_days into vCalcType, vPayDays from ac.pay_term a
	where a.pay_term_id = (select b.pay_term_id from ap.supplier b where b.supplier_id = paccount_id);
	
	if pbill_type = 'A' then
		update ap.bill_control
		Set bill_no=pbill_no,
			bill_date=pbill_date
		where bill_id=pvoucher_id;
	End if;
	If pbill_type = 'B' then
		update pub.abp_control
		Set bill_no=pbill_no,
			bill_date=pbill_date
		where voucher_id=pvoucher_id;
	End If;
	If pbill_type = 'C' then
		update fa.ap_control
		Set bill_no=pbill_no,
			bill_date=pbill_date
		where ap_id=pvoucher_id;
	End If;
	If pbill_type = 'D' then
		update st.stock_control
		Set bill_no=pbill_no,
			bill_date=pbill_date
		where stock_id=pvoucher_id;
	End If;

	Select due_date into vDueDate
	from ac.rl_pl
	Where voucher_id = pvoucher_id;

	If vCalcType = 2 Then -- Bill Date (Payable only)		
		SELECT pbill_date + (cast(vPayDays as varchar) || ' days')::interval into vDueDate;
	End If;
	
	Update ac.rl_pl
	Set bill_no = pbill_no,
		bill_date = pbill_date,
		due_date = vDueDate
	Where voucher_id = pvoucher_id;
	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE function ap.sp_pl_manual_ref_post(ppl_id uuid, pamt_to_be_settled numeric(18,4))
Returns void as
$Body$
Declare vCreditAmt numeric(18,4) = 0;
Begin	 
    Update ac.rl_pl a
    set credit_amt = (a.credit_amt - pamt_to_be_settled)
    where a.rl_pl_id = ppl_id;

    select a.credit_amt into vCreditAmt from ac.rl_pl a
    where a.rl_pl_id = ppl_id;

    if vCreditAmt = 0 then
	delete from ac.rl_pl a
	where a.rl_pl_id = ppl_id;
    End If;
End;
$Body$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE function ap.sp_bill_manual_ref_update(pvoucher_id Varchar(50), padv_amt_tot numeric(18,4))
Returns void as
$Body$
Begin	 

	if exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'abp_control') then
		if exists (SELECT * FROM pub.abp_control where voucher_id = pvoucher_id) then
			Update pub.abp_control a
			set advance_amt = advance_amt + padv_amt_tot,
				net_credit_amt = net_credit_amt - padv_amt_tot
			where a.voucher_id = pvoucher_id;
		End If;
	End If;
End;
$Body$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ap.fn_supp_gst_coll(pview_type_id bigint)
RETURNS TABLE
(    
    supplier_id bigint,
    supplier varchar(250),
    address text,
    gst_state_id bigint,
    gst_state_with_code varchar(300),
    gstin varchar(15)
) 
AS
$BODY$
Begin	 
	return query
	Select a.supplier_id, a.supplier, '<span>' || c.address || E'<br/>' || c.city || case when c.pin = '' then '' else ' - ' end  || c.pin || E'<br/>' || c.country ||  case when c.state = '' then '' else ', ' end  || c.state || '</span>' as address,
		COALESCE((a.annex_info->'satutory_details'->>'gst_state_id')::bigint, -1) as gst_state_id, (COALESCE(d.gst_state_code, '') || case when COALESCE(d.state_name, '') = '' then '' else ('-' || d.state_name) end)::varchar, 
		COALESCE((a.annex_info->'satutory_details'->>'gstin')::varchar, '') as gstin
	from ap.supplier a
	inner join sys.address c on a.address_id = c.address_id
	left join tx.gst_state d on COALESCE((a.annex_info->'satutory_details'->>'gst_state_id')::bigint, -1) = d.gst_state_id
	where case 
		when pview_type_id = 0  then -- without GSTIN
			(COALESCE((a.annex_info->'satutory_details'->>'gst_state_id')::bigint, -1) = -1 OR COALESCE((a.annex_info->'satutory_details'->>'gstin')::varchar, '') = '')			
		when pview_type_id = 1  then -- with GSTIN
			(COALESCE((a.annex_info->'satutory_details'->>'gst_state_id')::bigint, -1) != -1 AND COALESCE((a.annex_info->'satutory_details'->>'gstin')::varchar, '') != '')
		Else
			(1=1)
		End;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ap.sp_supp_gst_update(
	psupplier_id bigint,
	pnew_gst_state_id bigint,
	pnew_gstin character varying)
Returns Void
AS 
$BODY$
Begin

	-- Create satutory_details if not exists
        If Exists(Select * From ap.supplier Where supplier_id = psupplier_id And annex_info->'satutory_details' Is Null) Then
            Update ap.supplier
            Set annex_info = jsonb_set(annex_info, '{satutory_details}', '{}'::jsonb, true)
            Where supplier_id = psupplier_id And annex_info->'satutory_details' Is Null;
	End If;
    
	-- Update gst_state_id in billing address and supplier tax_info	
	Update ap.supplier
	Set annex_info = jsonb_set(annex_info, '{satutory_details, gst_state_id}', (pnew_gst_state_id::varchar)::jsonb, true)
	Where supplier_id = psupplier_id;
	
	-- Update gstin in billing address and customer tax_info	
	Update ap.supplier
	Set annex_info = jsonb_set(annex_info, '{satutory_details, gstin}', pnew_gstin::jsonb, true)
	Where supplier_id = psupplier_id;

	update sys.address
	set gst_state_id = pnew_gst_state_id,
		gstin = trim(both '"' from pnew_gstin)
	where address_id = (select a.address_id from ap.supplier a where a.supplier_id = psupplier_id);
	
END;
$BODY$
LANGUAGE 'plpgsql';

?==?
CREATE OR REPLACE FUNCTION ap.fn_validate_bill_no(IN paccount_id bigint, IN pbill_no varchar(20), pbill_date date, pvoucher_id varchar(50))
RETURNS TABLE
(   
    voucher_id varchar(50), 
    bill_no varchar(20), 
    bill_date date
) 
AS
$BODY$
Begin	 
        DROP TABLE IF EXISTS bill_no_temp;
        create temp TABLE  bill_no_temp
        ( 	
            voucher_id varchar(50), 
            bill_no varchar(20), 
            bill_date date
        );

        Insert into bill_no_temp (bill_no, bill_date, voucher_id)
        Select a.bill_no, a.bill_date, a.bill_id
        from ap.bill_control a
        where a.supplier_id = paccount_id 
            and a.bill_no ilike pbill_no 
            and a.bill_date = pbill_date 
            and a.bill_id != pvoucher_id
        Union All
        Select a.bill_no, a.bill_date, a.stock_id 
        from st.stock_control a
        where a.account_id = paccount_id 
            and a.bill_no ilike pbill_no 
            and a.bill_date = pbill_date 
            and a.stock_id != pvoucher_id
            And a.doc_type = 'SPG'
        Union All
        Select a.bill_no, a.bill_date, a.ap_id 
        from fa.ap_control a
        where a.account_id = paccount_id 
            and a.bill_no ilike pbill_no 
            and a.bill_date = pbill_date 
            and a.ap_id != pvoucher_id;

	If exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'abp_control') then
            Insert into bill_no_temp (bill_no, bill_date, voucher_id)
            Select a.bill_no, a.bill_date, a.voucher_id 
            from pub.abp_control a
            where a.account_id = paccount_id 
                    and a.bill_no ilike pbill_no 
                and a.bill_date = pbill_date 
                and a.voucher_id != pvoucher_id;
        End If;
    
	return query
	Select a.voucher_id, a.bill_no, a.bill_date
	From bill_no_temp a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ap.sp_supp_to_cust_update(psupplier_id bigint, psalesman_id bigint, pcontrol_account_id bigint, psegment_id bigint, ppay_term_id bigint)
Returns Void
AS 
$BODY$
Declare vannex_info jsonb = '{}'; vaddr_id bigint = -1; vcomp_id bigint = -1; vcust_id bigint = -1;
Begin
    Select ('{"segment_id":"'|| psegment_id ||'","ship_addrs":[],"__type__ship_addrs":{"sl_no":-1,"city":"","gstin":"","pin":"","gst_state_id":null,"ship_to":""},
            "has_kyc_docs":' || COALESCE((a.annex_info->>'has_kyc_docs')::boolean, false) ||',"tax_info":{"ctin":"'||(a.annex_info->'satutory_details'->>'cst_no')::varchar || '",
            "tan":"'||(a.annex_info->'satutory_details'->>'tan')::varchar || '","gstin":"'||(a.annex_info->'satutory_details'->>'gstin')::varchar || '",
            "gst_state_id":"'||(a.annex_info->'satutory_details'->>'gst_state_id')::bigint || '","gst_reg_name":"'||COALESCE((a.annex_info->'satutory_details'->>'gst_reg_name')::varchar, '') || '",
            "vtin":"'||(a.annex_info->'satutory_details'->>'vat_no')::varchar || '","dup_pan":false,"pan":"'||(a.annex_info->'satutory_details'->>'pan')::varchar || '",
            "diff_gst_name":'||COALESCE((a.annex_info->'satutory_details'->>'diff_gst_name')::boolean, false) || ',"stin":"'||(a.annex_info->'satutory_details'->>'service_tax_no')::varchar || '",
            "dup_gstin":false},"is_overridden":'||(a.annex_info->>'is_overridden')::boolean ||'}')::jsonb, company_id into vannex_info, vcomp_id
    From ap.supplier a
    where a.supplier_id = psupplier_id;

    If vannex_info is null Then
        vannex_info:='{}';
    End If;

    If not exists (select * from ap.supp_cust where supplier_id = psupplier_id) Then
    	-- generate address_id
        Select * into vaddr_id 
        From sys.sp_get_mast_id(vcomp_id, 'sys.address', -1);
        
        Insert Into sys.address(address_id, company_id, address_type_id, address, city, country, pin, fax, mobile, phone, email, contact_person, 
                    state, gst_state_id, gstin)
        Select vaddr_id, a.company_id, a.address_type_id, a.address, a.city, a.country, a.pin, a.fax, a.mobile, a.phone, a.email, a.contact_person, 
                    a.state, a.gst_state_id, a.gstin
        From ap.supplier b
        Inner join sys.address a on a.address_id = b.address_id
        Where b.supplier_id = psupplier_id;
    	
    	-- generate address_id
        Select * into vcust_id 
        From sys.sp_get_mast_id(vcomp_id, 'ac.account_head', -1);

    	Insert into ar.customer(customer_id, customer, customer_code, control_account_id, address_id, credit_limit_type, credit_limit, company_id,
    				pay_term_id, shipping_address_id, salesman_id, tax_schedule_id, annex_info, customer_name)
        Select vcust_id, a.supplier || ' (Customer)', a.supplier_code, pcontrol_account_id, vaddr_id, a.credit_limit_type, a.credit_limit, a.company_id,
        		ppay_term_id, -1, psalesman_id, -1, vannex_info, a.supplier_name
        From ap.supplier a
        where a.supplier_id = psupplier_id; 
                        
        If exists (SELECT * FROM information_schema.tables where table_schema='crm' And table_name = 'customer') Then
            Insert Into crm.customer(customer_id, price_list_id)
            Select psupplier_id, -1;
        End If;    

        Insert Into ac.account_head(account_id, account_head, account_code, company_id, consolidate_group_id, group_id, account_type_id, last_updated, 
                            en_advance_mode, sub_head_dim_id, is_ref_ledger)
        Select vcust_id, a.supplier || ' (Customer)', '', vcomp_id, -1, (Select group_id from ac.account_head where account_id=pcontrol_account_id), 12, current_timestamp(0),
            -1, -1, false
        From ap.supplier a
        where a.supplier_id = psupplier_id;

        -- Step 2: Insert Into account balance
        Insert Into ac.account_balance(account_balance_id, finyear, account_id, company_id, branch_id, debit_balance, credit_balance, last_updated)
        Select vcust_id || ':' || b.branch_id || ':' || a.finyear_code, a.finyear_code, vcust_id, vcomp_id, b.branch_id, 0, 0, current_timestamp(0)
        From sys.finyear a
        Cross Join sys.branch b;

        Insert into ap.supp_cust(supplier_id, customer_id)
        Select psupplier_id, vcust_id;
    End If;
END;
$BODY$
LANGUAGE 'plpgsql';

?==?
CREATE OR REPLACE FUNCTION ap.sp_supp_cust_update(
	psupplier_id bigint,
	pcustomer_id bigint)
    RETURNS void
    
AS $BODY$
Begin 
    If not exists (select * from ap.supp_cust where supplier_id = psupplier_id) Then     
        Insert Into ap.supp_cust(supplier_id, customer_id)
        Select psupplier_id, pcustomer_id;
    Else
        Update ap.supp_cust set customer_id=pcustomer_id where supplier_id=psupplier_id;
    End If;   
END;
$BODY$
    LANGUAGE 'plpgsql';

?==?
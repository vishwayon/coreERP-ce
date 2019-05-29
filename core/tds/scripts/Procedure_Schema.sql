CREATE OR REPLACE FUNCTION tds.section_acc_add_update(psection_id bigint, ptds_account_id bigint)
  RETURNS void AS
$BODY$
Begin
	if exists(Select * from tds.section_acc where section_id=psection_id) Then
		Update tds.section_acc
		Set tds_account_id=ptds_account_id
		Where section_id=psection_id;
			
	Else
		Insert into tds.section_acc(section_id, tds_account_id)
		select psection_id, ptds_account_id;
	End If;
End;
$BODY$
  LANGUAGE plpgsql;

?==? 
CREATE OR REPLACE FUNCTION tds.sp_tds_post(ptable_name Varchar(150), pdoc_year varchar(4), pvoucher_id Varchar(50), pvoucher_date Date, pfc_type_id bigint, pexch_rate numeric(18,6),
						pcheque_details varchar(250), pnarration Varchar(500), pdoc_type varchar(4))
  RETURNS void AS
$BODY$
Declare 
	vPay_ledger_id uuid; vCompany_id BigInt; vBranch_id BigInt; vAccount_id BigInt; vtds_amt_fc numeric(18,4); vtds_amt numeric(18,4);
	vBillDate date;
Begin
	-- Step 1: Insert Into Payable Ledger Alloc for partial settlement
	Select rl_pl_id, company_id, branch_id, account_id Into vPay_ledger_id, vCompany_id, vBranch_id, vAccount_id
	From ac.rl_pl 
	Where voucher_id=pvoucher_id;

	Select tds_base_rate_amt_fc + tds_ecess_amt_fc + tds_surcharge_amt_fc, 
		tds_base_rate_amt + tds_ecess_amt + tds_surcharge_amt 
	Into vtds_amt_fc, vtds_amt
	From tds.bill_tds_tran
	Where bill_tds_tran_id = pvoucher_id;

	if vPay_ledger_id is not null then	
		Insert Into ac.rl_pl_alloc(rl_pl_alloc_id, rl_pl_id, branch_id, voucher_id, vch_tran_id, doc_date, 
			account_id, exch_rate, debit_amt, debit_amt_fc, credit_amt, credit_amt_fc, write_off_amt, write_off_amt_fc, tds_amt, tds_amt_fc, other_exp, other_exp_fc,
			debit_exch_diff, credit_exch_diff, net_debit_amt, net_debit_amt_fc, net_credit_amt, net_credit_amt_fc, status, tran_group)
		Select sys.sp_gl_create_id(pvoucher_id || ':TDS', vBranch_id, vAccount_id, 0), vPay_ledger_id, vBranch_id, pvoucher_id || ':TDS', pvoucher_id || ':TDS', pvoucher_date,
			vAccount_id, pexch_rate, vtds_amt, vtds_amt_fc, 0, 0, 0, 0, 0, 0, 0, 0,
			0, 0, vtds_amt, vtds_amt_fc, 0, 0, 5, '';
	End If;
	-- Step 2: Call sp_gl_post with table_name tds to post tds entry 
	perform ac.sp_gl_post('tds.bill_tds_tran' , pdoc_year, pvoucher_id || ':TDS', pvoucher_date, pfc_type_id, pexch_rate, '', 'Being Tax Deducted at Source', pdoc_type);

	perform ap.sp_pl_post('tds.bill_tds_tran', pvoucher_id || ':TDS', pvoucher_date, pfc_type_id, pexch_rate, '', vBillDate, '', 0::smallint);
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION tds.sp_tds_unpost(pvoucher_id character varying)
  RETURNS void AS
$BODY$
Begin
	-- Step 1: Unpost From General ledger
	perform ac.sp_gl_unpost(pvoucher_id || ':TDS');

	-- Step 2: Delete From Payable ledger Alloc
	Delete 
	From ac.rl_pl_alloc
	Where voucher_id = pvoucher_id || ':TDS';

End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE or REPLACE FUNCTION ap.trgporc_tdpy_post() 
RETURNS trigger 
AS $BODY$
Declare vFinYear varchar(4); vVoucher_ID varchar(50)=''; vFCType_ID BigInt=0; vExchRate Numeric(18,6)=1; vDocDate Date;
	vStatus smallint=0; vOldStatus smallint; vNarration Varchar(500)=''; vType varchar(4)=''; vChequeDetails varchar(250)=''; 
BEGIN
    -- **** Get the Existing and new values in the table    
    Select NEW.status, OLD.status, NEW.finyear, NEW.doc_date, NEW.voucher_id, NEW.narration, NEW.doc_type
    into vStatus, vOldStatus, vFinYear, vDocDate, vVoucher_ID, vNarration, vType;

    -- ***** Unpost the voucher  
    If vStatus<=4 and vOldStatus=5 then		
        perform ac.sp_gl_unpost(vVoucher_ID);
    End if;

    If vStatus=5 and vOldStatus<=4 then
	-- **** Fetch Cheque information  
	If NEW.cheque_number<>'' then
		Select 'Ch No. ' || cast(NEW.cheque_number as varchar) || ' Dt. ' || to_char(NEW.cheque_date, 'dd/MM/yyyy') into vChequeDetails;
	End If;

        -- ***	Fire the stored procedure to post the entry in General Ledger
        perform ac.sp_gl_post('tds.tds_payment_control', vFinYear, vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vChequeDetails, vNarration, vType);
    End IF;
    RETURN NEW;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
-- Trigger on vch control table
CREATE TRIGGER trg_tdpy_post
  AFTER UPDATE
  ON tds.tds_payment_control
  FOR EACH ROW
  EXECUTE PROCEDURE ap.trgporc_tdpy_post();

?==?
CREATE OR REPLACE FUNCTION tds.sp_tds_challan_collection(IN pcompany_id bigint, IN pbranch_id bigint, IN pupdated bigint, IN pas_on date)
RETURNS TABLE
(   
    selected boolean,
    doc_date date, 
    voucher_id varchar(50), 
    amt numeric(18,4), 
    challan_bsr varchar(5), 
    challan_serial varchar(7)
) 
AS
$BODY$
Begin
	 
	 DROP TABLE IF EXISTS tds_challan_temp;
	 create temp TABLE  tds_challan_temp
	 ( 
	    selected boolean,
	    doc_date date, 
	    voucher_id varchar(50), 
	    amt numeric(18,4), 
	    challan_bsr varchar(5), 
	    challan_serial varchar(7)
	 );


	 if pupdated = 0 then -- not updated entries
		Insert into tds_challan_temp (selected, doc_date, voucher_id, amt, challan_bsr, challan_serial)
		Select false, a.doc_date, a.voucher_id, a.amt, a.challan_bsr, a.challan_serial				
		From tds.tds_payment_control a
		Where a.status=5
			And (a.challan_bsr ='' or a.challan_serial = '')
			And a.doc_date <= pas_on
			And (a.branch_id=pbranch_id or pbranch_id=0)
			And a.company_id=pcompany_id;
	 End If;

	If pupdated = 1 then  -- Updated  entries
		Insert into tds_challan_temp (selected, doc_date, voucher_id, amt, challan_bsr, challan_serial)
		Select true, a.doc_date, a.voucher_id, a.amt, a.challan_bsr, a.challan_serial				
		From tds.tds_payment_control a
		Where a.status=5
			And (a.challan_bsr !='' or a.challan_serial != '')
			And a.doc_date <= pas_on
			And (a.branch_id=pbranch_id or pbranch_id=0)
			And a.company_id=pcompany_id;
	End If;

	If pupdated = 2 then -- All entries 		
		Insert into tds_challan_temp (selected, doc_date, voucher_id, amt, challan_bsr, challan_serial)
		Select case when (a.challan_bsr ='' and a.challan_serial ='') then false else true end, a.doc_date, a.voucher_id, a.amt, a.challan_bsr, a.challan_serial				
		From tds.tds_payment_control a
		Where a.status=5
			And a.doc_date <= pas_on
			And (a.branch_id=pbranch_id or pbranch_id=0)
			And a.company_id=pcompany_id;
	End If;

	return query
	Select a.selected, a.doc_date, a.voucher_id, a.amt, a.challan_bsr, a.challan_serial
	From tds_challan_temp a;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
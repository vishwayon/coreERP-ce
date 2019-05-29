CREATE OR REPLACE FUNCTION fa.sp_asset_item_lc_post(pvoucher_id  Varchar(50))
  RETURNS void AS
$BODY$
	Declare vDiscount Numeric(18,4)=0; vItemTotal Numeric(18,4)=0; vTotalLandedCost Numeric(18,4)=0;
Begin
	--	This Procedure Posts the Landed Costs for a Asset Purchase.
	--	This proc is called from trgAPPosting

	--	****	Fetch Asset Item transactions into a temporary table
	DROP TABLE IF EXISTS APTran;
	create temp TABLE APTran
	(
		ap_tran_id Varchar(50),
		purchase_amt Numeric(18,4)
	);

	Insert Into APTran(ap_tran_id, purchase_amt)
	Select a.ap_tran_id, case when b.apply_itc = true then a.bt_amt else (a.bt_amt + b.sgst_amt + b.cgst_amt + b.igst_amt + b.cess_amt) end
	From fa.ap_tran  a
        inner join tx.gst_tax_tran b on a.ap_tran_id = b.gst_tax_tran_id
	Where ap_id=pVoucher_ID;

	--	*****	Extract various summaries
	--	****	Fetch Voucher Level Discounts
	Select disc_amt into vDiscount
	From fa.ap_control 
	Where ap_id=pVoucher_ID;

	--	****	Fetch Item Amount Total
	Select Sum(purchase_amt) into vItemTotal
	From APTran;
 
	--	****	Fetch AP level Landed Cost
	Select coalesce(Sum(debit_amt), 0) into vTotalLandedCost
	From fa.ap_lc_tran 
	Where ap_id=pVoucher_ID;

	--	****	Fetch AP Level Tax
	--Select  vTotalLandedCost + coalesce(Sum(tax_amt), 0) into vTotalLandedCost
	--From sys.tax_applied Where voucher_id=pVoucher_ID And include_in_lc=TRUE;

	--	****	Apportion the disount on the transactions summary
	if vDiscount>0 then 
		--	****	Reduce the Discounts In Materials (Avoid Divide By Zero Error)
		if vItemTotal>0 then
			Update APTran a
			Set	purchase_amt = a.purchase_amt - (a.purchase_amt*vDiscount/vItemTotal);
		end if;
	End if;
	
	--	****	Fetch Item Total again after Discount
	Select Sum(purchase_amt) into vItemTotal
	From APTran;

	--	****	Apportion the Landed Cost upon the Material transactions
	If vTotalLandedCost > 0 And vItemTotal > 0 then
		--	****	Increase the costs In Materials (Avoid Divide By Zero Error)
		Update APTran a
		Set purchase_amt = a.purchase_amt + (a.purchase_amt*vTotalLandedCost/vItemTotal);
	End if;
	--	*****	Update tblAssetItem
	Update fa.asset_item
	Set purchase_amt = a.purchase_amt
	From APTran a
	Where fa.asset_item.voucher_tran_id=a.ap_tran_id
		And fa.asset_item.voucher_id=pVoucher_ID;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION fa.sp_asset_item_ledger_post(ptable_name  Varchar(150), pvoucher_id  Varchar(50), pvoucher_date date, pnarration  Varchar(500), pen_asset_tran_type smallint)
  RETURNS void AS
$BODY$
Begin
	--	Insert Records into AssetItemLedger
	Insert into fa.asset_item_ledger(asset_item_ledger_id, asset_item_id, asset_book_id, voucher_date, voucher_id, 
									en_asset_tran_type, asset_tran_amt, narration)
	Select md5(pvoucher_id || ':' || asset_item_id || ':' || asset_book_id || ':' || pen_asset_tran_type)::uuid, asset_item_id, asset_book_id, pvoucher_date, pvoucher_id, 
				pen_asset_tran_type, asset_tran_amt, pnarration
	From fa.fn_asset_item_ledger_for_post(ptable_name, pvoucher_id, pen_asset_tran_type);
END;
$BODY$
  LANGUAGE plpgsql;


?==?
CREATE OR REPLACE FUNCTION fa.trgproc_ap_post()
  RETURNS trigger AS
$BODY$
Declare 
	vAssetItem_ID Bigint=-1; vFinYear varchar(4); vVoucher_ID varchar(50)=''; vFCType_ID BigInt=0; vExchRate Numeric(18,6)=1; vDocDate Date;
	vStatus smallint=0; vOldStatus smallint; vChequeDetails varchar(250)=''; vNarration Varchar(500)=''; vType varchar(4)='';vBillNo varchar(50)  =''; vBillDate date; vEnBillType smallint=0; 
BEGIN
            -- **** Get the Existing and new values in the table    
            Select NEW.status, OLD.status, NEW.finyear, NEW.doc_date, NEW.ap_id, NEW.fc_type_id, NEW.exch_rate, NEW.narration, NEW.doc_type, NEW.bill_no, NEW.bill_date
            into vStatus, vOldStatus, vFinYear, vDocDate, vVoucher_ID, vFCType_ID, vExchRate, vNarration, vType, vBillNo, vBillDate;
			
            if vType in('AP', 'AP2') then 
		-- ***** Unpost the voucher  
		If vStatus<=4 and vOldStatus=5 then 
		
			-- Prohibit Unposting an Asset Item that has Asset Ledger Entries other than Purchase  
-- 			if Exists(Select * From fa.asset_item_ledger Where en_asset_tran_type<>0  
-- 			And asset_item_id In (Select asset_item_id From fa.asset_item Where voucher_ID=vVoucher_ID))  then
-- 				RAISE EXCEPTION 'The Asset Items created from this document have been used in other documents. Unpost failed.'; 
-- 				Return NEW; 
-- 			End  if;
			
			-- Unpost accounting effect
			perform ac.sp_gl_unpost(vVoucher_ID);
			perform ap.sp_pl_unpost(vVoucher_ID);
                        perform ap.sp_pl_status_update(vVoucher_ID, vStatus);

			-- Unpost Asset Item Ledger Entries 
			Delete From fa.asset_item_ledger
			where voucher_id =vVoucher_ID;
			
			-- Unpost the Asset Item  
			Delete From fa.asset_item 
			where voucher_id =vVoucher_ID;
		End if;

		If vStatus=5 and vOldStatus<=4 then
			-- **** Fetch Cheque information  
			If NEW.cheque_number<>'' then
				Select 'Ch No. ' || cast(NEW.cheque_number as varchar) || ' Dt. ' || cast(NEW.cheque_date as date) into vChequeDetails;
			End If;
			
			If (vBillNo != '' or vBillNo != 'BNR') then 
                            If vNarration != '' then
                                vNarration := 'Bill No: ' || vBillNo || ' Dated: ' || to_char(vBillDate, 'DD-mm-yyyy') || E'\n' ||vNarration;
                            Else
                                vNarration := 'Bill No: ' || vBillNo || ' Dated: ' || to_char(vBillDate, 'DD-mm-yyyy');
                            End If;
                        End If;
                        -- Fire stored procedure to post accounting
                        perform ac.sp_gl_post('fa.ap_control' , vFinYear, vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vChequeDetails, vNarration, vType);
			perform ap.sp_pl_post('fa.ap_control', vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vBillNo, vBillDate, vNarration, vEnBillType);

                        perform ap.sp_pl_status_update(vVoucher_ID, vStatus);
                        -- Post the entry into asset_item
			Declare AssetTran_cursor Cursor 
			For Select ap_tran_id from fa.ap_tran where ap_id=vVoucher_ID;
			vAPTran_ID varchar(50);
			Begin
				OPEN AssetTran_cursor;
				Loop
					FETCH AssetTran_cursor INTO vAPTran_ID;
					exit when vAPTran_ID is null;
					
                                        -- Generate master key
					select pnew_mast_id into vAssetItem_ID from sys.sp_get_mast_id(NEW.company_ID,'fa.asset_item', -1);
					
                                        -- insert record into asset_item
					Insert Into fa.asset_item(asset_item_id, company_id, branch_id, asset_class_id, asset_code,  
						asset_name, purchase_date, use_start_date, purchase_amt, asset_location_id,  
						asset_qty, voucher_id, voucher_tran_id, project_id, cost_head_id, last_updated)  
					Select vAssetItem_ID, NEW.company_ID, a.branch_id, b.asset_class_id, b.asset_code ,  
						b.asset_name, a.doc_date, b.use_start_date, b.bt_amt, b.asset_location_id,  
						b.asset_qty, vVoucher_ID, vAPTran_ID, a.project_id, b.cost_head_id, current_timestamp(0)  
					From fa.ap_control a  
					Inner Join fa.ap_tran b ON a.ap_id=b.ap_id  
					Where a.ap_id=vVoucher_ID And b.ap_tran_id=vAPTran_ID;
					
				End loop;
				--RAISE EXCEPTION  '%  % Asset Item _ID', vAssetItem_ID, vAPTran_ID;
			Close AssetTran_cursor;
			End;

			perform fa.sp_asset_item_lc_post(vVoucher_ID);			
			perform fa.sp_asset_item_ledger_post('fa.ap_control', vVoucher_ID, vDocDate, '', cast(0 as smallint));
			
		End IF;
            End IF;
	RETURN NEW;
END
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE TRIGGER trg_ap_post
  AFTER UPDATE
  ON fa.ap_control
  FOR EACH ROW
  EXECUTE PROCEDURE fa.trgproc_ap_post();

?==?
CREATE OR REPLACE FUNCTION fa.trgproc_ad_post()
RETURNS trigger AS
$BODY$
Declare 
	vBranch_ID Bigint=-1; vFinYear varchar(4); vVoucher_ID varchar(50)=''; vFCType_ID BigInt=0; vExchRate Numeric(18,6)=1; vDocDate Date;
	vStatus smallint=0; vOldStatus smallint; vChequeDetails varchar(250)=''; vNarration Varchar(500)=''; vDepDateFrom date; vDepDateTo date; vType varchar(4)= '';
BEGIN
		-- **** Get the Existing and new values in the table    
		Select NEW.status, OLD.status, NEW.finyear, NEW.doc_date, NEW.ad_id, NEW.narration, NEW.branch_id, NEW.dep_date_from, NEW.dep_date_to, NEW.doc_type
		into vStatus, vOldStatus, vFinYear, vDocDate, vVoucher_ID, vNarration, vBranch_ID, vDepDateFrom, vDepDateTo, vType;
			
		-- ***** Unpost the voucher  
		If vStatus<=4 and vOldStatus=5 then 
		
-- 			-- Prohibit Unposting of Depreciation entry if subsequent depreciation entries are posted
-- 			if Exists(Select * From fa.asset_dep_ledger Where dep_date_from > vDepDateFrom and branch_id=vBranch_ID)  then
-- 				RAISE EXCEPTION 'Depreciation for subsequent period already charged. Unpost failed.'; 
-- 				Return NEW; 
-- 			End  if;
-- 
-- 			-- Prohibit Unposting of depreciation entries if Asset Items of the voucher are sold.
-- 			If Exists(Select * from fa.asset_dep_ledger where asset_item_id in (Select asset_item_id from fa.as_book_tran) 
-- 										and dep_date_from = vDepDateFrom and dep_date_to=vDepDateTo
-- 										and voucher_id=vVoucher_ID) Then
-- 				RAISE EXCEPTION 'Asset Item(s) already sold. Unpost failed.'; 
-- 				Return NEW; 
-- 			End If;

			-- Unpost accounting effect
			perform ac.sp_gl_unpost(vVoucher_ID);
			
			-- Toggle the status in asset_dep_ledger
			update fa.asset_dep_ledger
			set status=vStatus
			where voucher_id=vVoucher_ID;
		End If;
		If vStatus=5 and vOldStatus<=4 then
			-- Fire the stored procedure to post accounting
			perform ac.sp_gl_post('fa.ad_control' , vFinYear, vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vChequeDetails, vNarration, vType);

			-- Toggle status in asset_dep_ledger
			update fa.asset_dep_ledger
			set status=vStatus
			where voucher_id=vVoucher_ID;
		End IF;
	RETURN NEW;
END
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE TRIGGER trg_ad_post
  AFTER UPDATE
  ON fa.ad_control
  FOR EACH ROW
  EXECUTE PROCEDURE fa.trgproc_ad_post();

?==?
create function fa.sp_as_unpost(pvoucher_id varchar(50))
RETURNS void AS
$BODY$
Begin
	--Step 1: Close the Asset Item as Sold/Discarded
	Update fa.asset_item
	Set disposed_on=null
	from fa.as_tran b 
	Inner Join fa.as_control c on b.as_id=c.as_id
	where c.as_id=pvoucher_id
                and fa.asset_item.asset_item_id=b.asset_item_id;

	-- Step 2: Unpost Depreciation on Sale
	Delete From fa.asset_dep_ledger
	where voucher_id =pvoucher_id;

	-- Step 3: Unpost Sale into Asset Item Ledger
	Delete From fa.asset_item_ledger
	where voucher_id =pvoucher_id;
END;
$BODY$
  LANGUAGE plpgsql;
  
?==?

create or replace function fa.sp_as_post(pvoucher_id varchar(50), pdoc_date date)
RETURNS void AS
$BODY$
Begin
	--Step 1: Close the Asset Item as Sold/discarded
	Update fa.asset_item
	set disposed_on=c.doc_date
	From fa.as_tran b
	inner join fa.as_control c on b.as_id=c.as_id
	where fa.asset_item.asset_item_id=b.asset_item_id
		and c.as_id=pvoucher_id;

	-- Post Depreciation On Sale
-- 	Insert into fa.asset_dep_ledger(asset_dep_ledger_id, asset_item_id, asset_class_id, asset_book_id, company_id, finyear, branch_id,
-- 					voucher_id, doc_date, dep_date_from, dep_date_to, dep_amt, status, narration, is_terminal)
-- 	Select a.as_book_tran_id, a.asset_item_id, b.asset_class_id, a.asset_book_id, b.company_id, b.finyear, b.branch_id, 
-- 		b.as_id, b.doc_date, a.dep_date_from, b.doc_date, a.dep_amt, b.status, b.narration, true
-- 	From fa.as_book_tran a
-- 	inner join fa.as_control b on a.as_id=b.as_id
-- 	where b.as_id=pvoucher_id;	

	-- Post Sale into Asset Item Ledger			
	perform fa.sp_asset_item_ledger_post('fa.as_control', pvoucher_id, pdoc_date, '', cast(2 as smallint));			

	-- Post Profit/Loss into Asset Item Ledger			
	perform fa.sp_asset_item_ledger_post('fa.as_control', pvoucher_id, pdoc_date, '', cast(1 as smallint));
END;
$BODY$
  LANGUAGE plpgsql;

?==?

CREATE OR REPLACE FUNCTION fa.trgproc_as_post()
  RETURNS trigger AS
$BODY$
Declare 
	vAssetItem_ID Bigint=-1; vFinYear varchar(4); vVoucher_ID varchar(50)=''; vFCType_ID BigInt=0; vExchRate Numeric(18,6)=1; vDocDate Date;
	vStatus smallint=0; vOldStatus smallint; vChequeDetails varchar(250)=''; vNarration Varchar(500)=''; vType varchar(4)='';
BEGIN
		-- **** Get the Existing and new values in the table    
		Select NEW.status, OLD.status, NEW.finyear, NEW.doc_date, NEW.as_id, NEW.fc_type_id, NEW.exch_rate, NEW.narration, NEW.doc_type
		into vStatus, vOldStatus, vFinYear, vDocDate, vVoucher_ID, vFCType_ID, vExchRate, vNarration, vType;
			
		-- ***** Unpost the voucher  
		If vStatus<=4 and vOldStatus=5 then 				
			-- Unpost accounting effect
			perform ac.sp_gl_unpost(vVoucher_ID);

			perform fa.sp_as_unpost(vVoucher_ID);

		End if;

		If vStatus=5 and vOldStatus<=4 then			
                        -- Fire stored procedure to post accounting
                        perform ac.sp_gl_post('fa.as_control' , vFinYear, vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vChequeDetails, vNarration, vType);

			Perform fa.sp_as_post(vVoucher_ID, vDocDate);
		End IF;
	RETURN NEW;
END
$BODY$
LANGUAGE plpgsql;

?==?
CREATE TRIGGER trg_as_post
  AFTER UPDATE
  ON fa.as_control
  FOR EACH ROW
  EXECUTE PROCEDURE fa.trgproc_as_post();
?==?
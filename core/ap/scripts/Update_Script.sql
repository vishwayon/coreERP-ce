update ap.bill_tran a
set tax_amt  = b.sgst_amt + b.cgst_amt + b.igst_amt + b.cess_amt
From tx.gst_tax_tran b 
where a.bill_tran_id = b.gst_tax_tran_id 
		And	tax_amt = 0;

?==?
update tds.bill_tds_tran
set tran_group = 'ap.bill_control'
where voucher_id ilike 'BL2%';

?==?
update tds.bill_tds_tran
set tran_group = 'ap.pymt_control'
where voucher_id ilike 'ASP%'
        And tran_group = '';

?==?
Update ap.bill_tran a
set branch_id = b.branch_id
from ap.bill_control b
where a.bill_id = b.bill_id
	and a.branch_id = -1;

?==?
update ap.bill_control
set annex_info = jsonb_set(annex_info, '{is_inter_branch}', 'false'::jsonb, true)
Where annex_info->>'is_inter_branch' is Null;

?==?
update ap.supplier
Set annex_info = jsonb_set(annex_info, '{satutory_details,dup_pan}', 'false'::jsonb, true)
Where annex_info->'satutory_details'->>'dup_pan' Is Null;

?==?
update ap.supplier
Set annex_info = jsonb_set(annex_info, '{satutory_details,dup_gstin}', 'false'::jsonb, true)
Where annex_info->'satutory_details'->>'dup_gstin' Is Null;

?==?
update ap.supplier
Set annex_info = jsonb_set(annex_info, '{satutory_details,diff_gst_name}', 'false'::jsonb, true)
Where annex_info->'satutory_details'->>'diff_gst_name' Is Null;

?==?
update ap.supplier
Set annex_info = jsonb_set(annex_info, '{satutory_details,gst_reg_name}', '""'::jsonb, true)
Where annex_info->'satutory_details'->>'gst_reg_name' Is Null;

?==?
DO LANGUAGE plpgsql $$
Begin
 Declare 
  vitc_account_id bigint;
  gtt_cursor Cursor For 
            select distinct a.voucher_id, a.account_id
            from ac.general_ledger a
            inner join tx.gst_tax_tran b on a.voucher_id = b.voucher_id and a.account_id = b.sgst_account_id
            where a.voucher_id ilike '%DN2%';
 Begin
  For rec in gtt_cursor Loop
  
   select sgst_itc_account_id into vitc_account_id
   From tx.gst_tax_tran 
   Where sgst_account_id = rec.account_id;

   update ac.general_ledger
   set account_id = vitc_account_id
   where voucher_id = rec.voucher_id and account_id = rec.account_id;
            
  End Loop;
 End;
End
$$;

?==?
DO LANGUAGE plpgsql $$
Begin
 Declare 
  vitc_account_id bigint;
  gtt_cursor Cursor For 
            select distinct a.voucher_id, a.account_id
            from ac.general_ledger a
            inner join tx.gst_tax_tran b on a.voucher_id = b.voucher_id and a.account_id = b.cgst_account_id
            where a.voucher_id ilike '%DN2%';
 Begin
  For rec in gtt_cursor Loop
  
   select cgst_itc_account_id into vitc_account_id
   From tx.gst_tax_tran 
   Where cgst_account_id = rec.account_id;

   update ac.general_ledger
   set account_id = vitc_account_id
   where voucher_id = rec.voucher_id and account_id = rec.account_id;
            
  End Loop;
 End;
End
$$;

?==?
DO LANGUAGE plpgsql $$
Begin
 Declare 
  vitc_account_id bigint;
  gtt_cursor Cursor For 
            select distinct a.voucher_id, a.account_id
            from ac.general_ledger a
            inner join tx.gst_tax_tran b on a.voucher_id = b.voucher_id and a.account_id = b.igst_account_id
            where a.voucher_id ilike '%DN2%';
 Begin
  For rec in gtt_cursor Loop
  
   select igst_itc_account_id into vitc_account_id
   From tx.gst_tax_tran 
   Where igst_account_id = rec.account_id;

   update ac.general_ledger
   set account_id = vitc_account_id
   where voucher_id = rec.voucher_id and account_id = rec.account_id;
            
  End Loop;
 End;
End
$$;

?==?
DO LANGUAGE plpgsql $$
Begin
 Declare 
  vitc_account_id bigint;
  gtt_cursor Cursor For 
            select distinct a.voucher_id, a.account_affected_id
            from ac.general_ledger a
            inner join tx.gst_tax_tran b on a.voucher_id = b.voucher_id and a.account_affected_id = b.sgst_account_id
            where a.voucher_id ilike '%DN2%';
 Begin
  For rec in gtt_cursor Loop
  
   select sgst_itc_account_id into vitc_account_id
   From tx.gst_tax_tran 
   Where sgst_account_id = rec.account_affected_id;

   update ac.general_ledger
   set account_affected_id = vitc_account_id
   where voucher_id = rec.voucher_id and account_affected_id = rec.account_affected_id;
            
  End Loop;
 End;
End
$$;

?==?
DO LANGUAGE plpgsql $$
Begin
 Declare 
  vitc_account_id bigint;
  gtt_cursor Cursor For 
            select distinct a.voucher_id, a.account_affected_id
            from ac.general_ledger a
            inner join tx.gst_tax_tran b on a.voucher_id = b.voucher_id and a.account_affected_id = b.cgst_account_id
            where a.voucher_id ilike '%DN2%';
 Begin
  For rec in gtt_cursor Loop
  
   select cgst_itc_account_id into vitc_account_id
   From tx.gst_tax_tran 
   Where cgst_account_id = rec.account_affected_id;

   update ac.general_ledger
   set account_affected_id = vitc_account_id
   where voucher_id = rec.voucher_id and account_affected_id = rec.account_affected_id;
            
  End Loop;
 End;
End
$$;

?==?
DO LANGUAGE plpgsql $$
Begin
 Declare 
  vitc_account_id bigint;
  gtt_cursor Cursor For 
            select distinct a.voucher_id, a.account_affected_id
            from ac.general_ledger a
            inner join tx.gst_tax_tran b on a.voucher_id = b.voucher_id and a.account_affected_id = b.igst_account_id
            where a.voucher_id ilike '%DN2%';
 Begin
  For rec in gtt_cursor Loop
  
   select igst_itc_account_id into vitc_account_id
   From tx.gst_tax_tran 
   Where igst_account_id = rec.account_affected_id;

   update ac.general_ledger
   set account_affected_id = vitc_account_id
   where voucher_id = rec.voucher_id and account_affected_id = rec.account_affected_id;
            
  End Loop;
 End;
End
$$;

?==?
update ap.pymt_control
set annex_info = jsonb_set(annex_info, '{rc_tax_amt}', ('"0"')::jsonb, true)
where annex_info->'rc_tax_amt' is null
	And annex_info is not null
    And doc_type = 'DN2';

?==?
update ap.supplier
set annex_info = jsonb_set(annex_info, '{has_kyc_docs}', 'false'::jsonb, true)
Where annex_info->>'has_kyc_docs' is Null;

?==?
update ap.supplier
set annex_info = jsonb_set(annex_info, '{block_pymt}', 'false'::jsonb, true)
Where annex_info->>'block_pymt' is Null;

?==?
/*
* To update vch_type in menu table for the purpose of systemwide document search - 16 Aug, 2018
Update sys.menu
set vch_type = '{BL}'
where menu_name = 'mnuBill';

Update sys.menu
set vch_type = '{BL2}'
where menu_name = 'mnuGstBill';

Update sys.menu
set vch_type = '{DN2}'
where menu_name = 'mnuGstDebitNote';

Update sys.menu
set vch_type = '{MCP}'
where menu_name = 'mnuMultiSuppPayment';

Update sys.menu
set vch_type = '{SBT}'
where menu_name = 'mnuSuppBalTransfer';

Update sys.menu
set vch_type = '{ASP}'
where menu_name = 'mnuAdvanceSupplierPayment';

Update sys.menu
set vch_type = '{PYMT}'
where menu_name = 'mnuSupplierPayment';

Update sys.menu
set vch_type = '{SREC}'
where menu_name = 'mnuSupplierReceipt';

Update sys.menu
set vch_type = '{CN}'
where menu_name = 'mnuCreditNote';
*/
?==?
update ap.supplier
Set annex_info = jsonb_set(annex_info, '{pay_cycle_id}', '-1'::jsonb, true)

?==?
update ap.pymt_control
Set annex_info = jsonb_set(annex_info, '{is_bt}', 'true'::jsonb, true)

?==?

update ar.rcpt_control
set annex_info = jsonb_set(annex_info, '{is_ac_payee}', 'false'::jsonb, true)
Where annex_info->>'is_ac_payee' is Null
	And doc_type = 'CREF';

?==?
update ar.rcpt_control
set annex_info = jsonb_set(annex_info, '{is_non_negotiable}', 'false'::jsonb, true)
Where annex_info->>'is_non_negotiable' is Null
	And doc_type = 'CREF';

?==?
-- query to update adv for existing customer receipt
DO LANGUAGE plpgsql $$
Begin
	Declare 
		vcust_state_id bigint; vhsn_sc_id bigint; vvat_type_id bigint; vbranch_state_id bigint;
		cust_abr_cursor Cursor For select * from ar.rcpt_control
                                    where adv_amt > 0
                                        And doc_type = 'RCPT'
                                        And voucher_id not in (select voucher_id from ar.rcpt_adv_tran)
                                    order by voucher_id
                                    limit 20;
	Begin
            For rec in cust_abr_cursor Loop
            Select COALESCE((a.annex_info->'tax_info'->>'gst_state_id')::bigint, -1) into vcust_state_id
            From ar.customer a
            Where a.customer_id = rec.customer_account_id; 
			
            select hsn_sc_id into vhsn_sc_id
            From tx.hsn_sc a
            where a.hsn_sc_code = '00';

            select a.gst_state_id into vbranch_state_id
            from sys.branch a
            where a.branch_id = rec.branch_id;

            if vbranch_state_id = vcust_state_id then
                select 301 into vvat_type_id;
            Else
                select 302 into vvat_type_id;
            End If;

            Insert into ar.rcpt_adv_tran (sl_no, vch_tran_id, voucher_id, vat_type_id, account_id, customer_state_id,
                branch_id, adv_amt, adv_amt_fc)
            Select 1, rec.voucher_id || ':1', rec.voucher_id, vvat_type_id, rec.customer_account_id, vcust_state_id, 
                rec.branch_id, rec.adv_amt, 0;

            Insert into tx.gst_tax_tran (gst_tax_tran_id, voucher_id, tran_group, hsn_sc_code, hsn_sc_type, supplier_gstin, hsn_qty, gst_rate_id,
                    is_rc, rc_sec_id, apply_itc, is_ctp, bt_amt, tax_amt_ov, sgst_pcnt, sgst_amt, sgst_itc_account_id, sgst_account_id, 
                    cgst_pcnt, cgst_amt, cgst_itc_account_id, cgst_account_id, igst_pcnt, igst_amt, igst_itc_account_id, igst_account_id,
                    cess_pcnt, cess_amt, cess_itc_account_id, cess_account_id)
            Select rec.voucher_id || ':1', rec.voucher_id, 'ar.rcpt_adv_tran' tran_group, b.hsn_sc_code, b.hsn_sc_type, '' supplier_gstin, 0 hsn_qty, d.gst_rate_id,
                    false is_rc, -1 rc_sec_id, false apply_itc, false is_ctp, rec.adv_amt bt_amt, false tax_amt_ov, d.sgst_pcnt, 0 sgst_amt, d.sgst_itc_account_id, d.sgst_account_id, 
                    d.cgst_pcnt, 0 cgst_amt, d.cgst_itc_account_id, d.cgst_account_id, d.igst_pcnt, 0 igst_amt, d.igst_itc_account_id, d.igst_account_id,
                    d.cess_pcnt, 0 cess_amt, d.cess_itc_account_id, d.cess_account_id
            From tx.hsn_sc b 
            Inner Join tx.hsn_sc_rate c On b.hsn_sc_id = c.hsn_sc_id
            Inner Join tx.gst_rate d On c.gst_rate_id = d.gst_rate_id
            Where b.hsn_sc_id = 0;

            raise notice 'voucher_id - %', rec.voucher_id;
		End Loop;
	End;
End
$$;

?==?
-- Script to update vat_type_id and customer state id in RCPT control
DO LANGUAGE plpgsql $$
Begin
	Declare 
		vcust_state_id bigint; vhsn_sc_id bigint; vvat_type_id bigint; vbranch_state_id bigint;
		cust_abr_cursor Cursor For select * from ar.rcpt_control
                                    where annex_info->'gst_output_info' is null
                                        And doc_type = 'RCPT'
                                    order by voucher_id desc
                                    limit 200;
	Begin
            For rec in cust_abr_cursor Loop
            Select COALESCE((a.annex_info->'tax_info'->>'gst_state_id')::bigint, -1) into vcust_state_id
            From ar.customer a
            Where a.customer_id = rec.customer_account_id; 
            
            select a.gst_state_id into vbranch_state_id
            from sys.branch a
            where a.branch_id = rec.branch_id;

            if vbranch_state_id = vcust_state_id then
                select 301 into vvat_type_id;
            Else
                select 302 into vvat_type_id;
            End If;

            update ar.rcpt_control
            set annex_info = jsonb_set(annex_info, '{gst_output_info}', '{}'::jsonb, true)
            Where voucher_id = rec.voucher_id;
            
            update ar.rcpt_control
            set annex_info = jsonb_set(annex_info, '{gst_output_info,vat_type_id}', (vvat_type_id::varchar)::jsonb, true)
            Where voucher_id = rec.voucher_id;
            
            update ar.rcpt_control
            set annex_info = jsonb_set(annex_info, '{gst_output_info,customer_state_id}', (vcust_state_id::varchar)::jsonb, true)
            Where voucher_id = rec.voucher_id;

            raise notice 'voucher_id - %', rec.voucher_id;
		End Loop;
	End;
End
$$;

?==?
update ar.customer
Set annex_info = jsonb_set(annex_info, '{tax_info,dup_pan}', 'false'::jsonb, true)
Where annex_info->'tax_info'->>'dup_pan' Is Null;
?==?

update ar.customer
Set annex_info = jsonb_set(annex_info, '{tax_info,dup_gstin}', 'false'::jsonb, true)
Where annex_info->'tax_info'->>'dup_gstin' Is Null;
?==?

update ar.customer
Set annex_info = jsonb_set(annex_info, '{tax_info,diff_gst_name}', 'false'::jsonb, true)
Where annex_info->'tax_info'->>'diff_gst_name' Is Null;

?==?
update ar.customer
Set annex_info = jsonb_set(annex_info, '{tax_info,gst_reg_name}', '""'::jsonb, true)
Where annex_info->'tax_info'->>'gst_reg_name' Is Null;

?==?
update ar.rcpt_control
set annex_info = jsonb_set(annex_info, '{is_multi_settl}', 'false'::jsonb, true)
Where annex_info->>'is_multi_settl' is Null
	And doc_type = 'RCPT';

?==?
update ar.invoice_tran a
set tax_amt = b.sgst_amt+b.cgst_amt+b.igst_amt
from tx.gst_tax_tran b 
where a.invoice_tran_id = b.gst_tax_tran_id
		And tax_amt = 0;

?==?
Update ac.sub_head_ledger
set debit_amt = credit_amt,
debit_amt_fc = credit_amt_fc
where left(voucher_id, 4) = 'RCPT'
        And credit_amt != 0;

?==?
Update ac.sub_head_ledger
set credit_amt = 0,
	credit_amt_fc = 0
where left(voucher_id, 4) = 'RCPT'
        And credit_amt != 0;

?==?
Update ac.sub_head_ledger
set debit_amt = credit_amt,
debit_amt_fc = credit_amt_fc
where left(voucher_id, 3) = 'ACR'
        And credit_amt != 0;

?==?
Update ac.sub_head_ledger
set credit_amt = 0,
	credit_amt_fc = 0
where left(voucher_id, 3) = 'ACR'
        And credit_amt != 0;

?==?
update ar.rcpt_tran a
set tax_amt = b.sgst_amt+b.cgst_amt+b.igst_amt
from tx.gst_tax_tran b 
where a.vch_tran_id = b.gst_tax_tran_id
		And tax_amt = 0;

?==?
-- Update en_bill_type = 1 for advance in MCR document
Update ar.receivable_ledger
set en_bill_type = 1
where left(voucher_id, 3) = 'MCR'
    And en_bill_type = 0;

?==?
-- Use adv_amt field from control table instead of annex_info field
Update ar.rcpt_control 
set adv_amt = COALESCE((annex_info->>'adv_amt')::numeric, 0)
where doc_type = 'MCR'
	And adv_amt = 0;

?==?

/*
* To update vch_type in menu table for the purpose of systemwide document search - 16 Aug, 2018
Update sys.menu
set vch_type = '{INV}'
where menu_name = 'mnuInvoice';

Update sys.menu
set vch_type = '{DN}'
where menu_name = 'mnuDebitNote';

Update sys.menu
set vch_type = '{RCPT}'
where menu_name = 'mnuCustomerReceipt';

Update sys.menu
set vch_type = '{ACR}'
where menu_name = 'mnuAdvanceCustomerReceipt';

Update sys.menu
set vch_type = '{CREF}'
where menu_name = 'mnuCustomerRefund';

Update sys.menu
set vch_type = '{CBT}'
where menu_name = 'mnuCustBalTransfer';

Update sys.menu
set vch_type = '{CN2}'
where menu_name = 'mnuGstCreditNote';

Update sys.menu
set vch_type = '{INV2}'
where menu_name = 'mnuGstInvoice';

Update sys.menu
set vch_type = '{MCR}'
where menu_name = 'mnuMultiCustReceipt';
*/

/* 21 Aug, 2018 Bit for Document received in customer master
update ar.customer
set annex_info = jsonb_set(annex_info, '{has_kyc_docs}', ('"false"')::jsonb, true)
where annex_info->'has_kyc_docs' is null
	And annex_info is not null;
*/

?==?
update ar.rcpt_control
set annex_info = jsonb_set(annex_info, '{to_date}',('"'|| doc_date::varchar ||'"')::jsonb, true)
Where annex_info->>'to_date' is Null
		And doc_type = 'RCPT';

?==?
update ac.sub_head_ledger
set not_by_alloc = true
where not_by_alloc = false
and voucher_id ilike 'OPBL%';

?==?

/*
* To update vch_type in menu table for the purpose of systemwide document search - 16 Aug, 2018
Update sys.menu
set vch_type = '{BPV}'
where menu_name = 'mnuBankPayment';

Update sys.menu
set vch_type = '{BRV}'
where menu_name = 'mnuBankReceipt';

Update sys.menu
set vch_type = '{CPV}'
where menu_name = 'mnuCashPayment';

Update sys.menu
set vch_type = '{CRV}'
where menu_name = 'mnuCashReceipt';

Update sys.menu
set vch_type = '{CV}'
where menu_name = 'mnuContraVoucher';

Update sys.menu
set vch_type = '{JV}'
where menu_name = 'mnuJournalVoucher';

Update sys.menu
set vch_type = '{PAYB}'
where menu_name = 'mnuGstBankPymt';

Update sys.menu
set vch_type = '{PAYC}'
where menu_name = 'mnuGstCashPymt';

Update sys.menu
set vch_type = '{PAYV}'
where menu_name = 'mnuGstPymt';

Update sys.menu
set vch_type = '{SIRC}'
where menu_name = 'mnuGstSi';

Update sys.menu
set vch_type = '{SAJ}'
where menu_name = 'mnuSaj';

Update sys.menu
set vch_type = '{MCJ}'
where menu_name = 'mnuMCJ';
*/
-- To insert row in subhead ledger for non itc amt - 24 Sep, 2018
Do Language plpgsql
$BODY$
	Declare
        vtax_amt Numeric(18,2):=0;
        gst_tax_cursor Cursor For Select gst_tax_tran_id, voucher_id From tx.gst_tax_tran 
        							where left(voucher_id, 4) in ('PAYV', 'PAYB', 'PAYC', 'BL2')
                                    	And (gst_tax_tran_id || ':GST') not in (select vch_tran_id from ac.sub_head_ledger)
                                    	And gst_tax_tran_id in (select vch_tran_id from ac.sub_head_ledger);
    Begin
            For rec in gst_tax_cursor Loop
            
            Select sgst_amt+cgst_amt+igst_amt Into vtax_amt
            From tx.gst_tax_tran a
            Where gst_tax_tran_id = rec.gst_tax_tran_id
               And apply_itc = false;

            If vtax_amt > 0 Then
            	raise notice 'voucher_id - %', rec.gst_tax_tran_id;
                INSERT INTO ac.sub_head_ledger(sub_head_ledger_id, company_id, branch_id, finyear, voucher_id, vch_tran_id, doc_date, 
                                               account_id, sub_head_id, fc_type_id, exch_rate, debit_amt_fc, credit_amt_fc, 
                                               debit_amt, credit_amt, narration, status, not_by_alloc)
                Select md5(a.vch_tran_id || ':GST')::uuid, a.company_id, a.branch_id, a.finyear, a.voucher_id, a.vch_tran_id || ':GST', a.doc_date, 
                                               a.account_id, a.sub_head_id, a.fc_type_id, a.exch_rate, 0, 0,
                                               0, 0, a.narration, a.status, a.not_by_alloc
                From ac.sub_head_ledger a
                Where a.vch_tran_id = rec.gst_tax_tran_id
                Limit 1;

            End If;
            End Loop;
End
$BODY$;
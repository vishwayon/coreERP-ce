<?php

namespace app\core\providers;

/**
 * Gstr2Provider - Provides all data required for GSTR2 
 * @author girishshenoy
 */
class Gstr2Provider {
    // list of documents that would be processed
    public $docList = [
        'GstBill' => "Select 'GST Bill' as doc, doc_date, bill_id as voucher_id, vat_type_id, status, 
                            (annex_info->'gst_input_info'->>'supplier_state_id')::BigInt as supplier_state_id,
                            (annex_info->'gst_input_info'->>'supplier_gstin')::Varchar as supplier_gstin,
                            (annex_info->>'bt_amt')::Numeric as bt_amt, (annex_info->>'tax_amt')::Numeric as tax_amt,
                            (annex_info->>'bill_total')::Numeric as total_amt,
                            (annex_info->'gst_rc_info'->>'apply_rc')::Boolean as apply_rc,
                            (annex_info->'gst_rc_info'->>'rc_sec_id')::BigInt as rc_sec_id,
                            bill_no, bill_date, bill_amt, 'is' as itc_type
                         From ap.bill_control 
                         Where doc_date between :pfrom_date And :pto_date
                            And branch_id = Any(:pbranch_ids::BigInt[])
                            And doc_type = Any('{BL2}'::Varchar[])
                            And vat_type_id Between 400 And 499",
        'StockGstPurchase' => "Select 'GST Stock Purchase' as doc, doc_date, stock_id as voucher_id, vat_type_id, status, 
                            (annex_info->'gst_input_info'->>'supplier_state_id')::BigInt as supplier_state_id,
                            (annex_info->'gst_input_info'->>'supplier_gstin')::Varchar as supplier_gstin, 
                            before_tax_amt as bt_amt, tax_amt, total_amt, false, -1,
                            bill_no, bill_date, bill_amt, 'ip' as itc_type
                         From st.stock_control 
                         Where  doc_date between :pfrom_date And :pto_date
                            And branch_id = Any(:pbranch_ids::BigInt[])
                            And doc_type = Any('{SPG}'::Varchar[])
                            And vat_type_id Between 400 And 499",
        'GstPayv' => "Select 'GST Payment Voucher' as doc, doc_date, voucher_id, (annex_info->'gst_input_info'->>'vat_type_id')::BigInt vat_type_id, status, 
                            (annex_info->'gst_input_info'->>'supplier_state_id')::BigInt as supplier_state_id,
                            (annex_info->'gst_input_info'->>'supplier_gstin')::Varchar as supplier_gstin,
                            (annex_info->>'bt_amt')::Numeric as bt_amt, (annex_info->>'tax_amt')::Numeric as tax_amt,
                            credit_amt as total_amt, 
                            (annex_info->'gst_rc_info'->>'apply_rc')::Boolean,
                            (annex_info->'gst_rc_info'->>'rc_sec_id')::BigInt,
                            (annex_info->>'bill_no')::Varchar, (annex_info->>'bill_date')::Date, (annex_info->>'bill_amt')::Numeric, 'is' as itc_type
                    From ac.vch_control 
                    Where  doc_date between :pfrom_date And :pto_date
                       And branch_id = Any(:pbranch_ids::BigInt[])
                       And doc_type = Any('{PAYV,PAYC,PAYB}'::Varchar[])
                       And (annex_info->>'line_item_gst' is Null Or (annex_info->>'line_item_gst')::Boolean = false)",
        'AssetPurchase' => "Select 'Asset Purchase' as doc, doc_date, ap_id as voucher_id, (annex_info->'gst_input_info'->>'vat_type_id')::BigInt vat_type_id, status, 
                            (annex_info->'gst_input_info'->>'supplier_state_id')::BigInt as supplier_state_id,
                            (annex_info->'gst_input_info'->>'supplier_gstin')::Varchar as supplier_gstin,
                            (annex_info->>'bt_amt')::Numeric as bt_amt, (annex_info->>'tax_amt')::Numeric as tax_amt,
                            credit_amt as total_amt, 
                            (annex_info->'gst_rc_info'->>'apply_rc')::Boolean,
                            (annex_info->'gst_rc_info'->>'rc_sec_id')::BigInt,
                            bill_no, bill_date, credit_amt, 'cp' as itc_type
                    From fa.ap_control 
                    Where  doc_date between :pfrom_date And :pto_date
                       And branch_id = Any(:pbranch_ids::BigInt[])
                       And doc_type = Any('{AP2}'::Varchar[])"
    ];
    
    /*
     * This method returns pending documents that have not been posted
     */
    public function preProcessPendingDocs(\app\core\tx\gstr2\Gstr2ProviderOption $option) : \app\cwf\vsla\data\DataTable {
        $pendingDocsSQL = "With bill_list 
                           As
                           (    ".implode("\nUnion All\n", $this->docList) .")
                           Select * 
                           From bill_list
                           Where status != 5
                           Order by doc, doc_date, voucher_id";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($pendingDocsSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{".$this->getBranchInState($option->gst_state_id)."}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    public function getB2B_summary(\app\core\tx\gstr2\Gstr2ProviderOption $option) : \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With bill_list 
                        As
                        (    ".implode("\nUnion All\n", $this->docList) ."),
                        gst_tran
                        As
                        (   Select a.voucher_id, a.supplier_gstin, Sum(a.bt_amt) as bt_amt, Sum(a.sgst_amt) as sgst_amt,
                                Sum(a.cgst_amt) as cgst_amt, Sum(a.igst_amt) as igst_amt,
                                Sum(Case When a.apply_itc Then a.sgst_amt Else 0 End) as sgst_itc_amt,
                                Sum(Case When a.apply_itc Then a.cgst_amt Else 0 End) as cgst_itc_amt,
                                Sum(Case When a.apply_itc Then a.igst_amt Else 0 End) as igst_itc_amt
                            From (  Select voucher_id, apply_itc, supplier_gstin, bt_amt, sgst_amt, cgst_amt, igst_amt
                                    From tx.gst_tax_tran
                                    Where is_rc = false
                                        And sgst_pcnt+cgst_pcnt+igst_pcnt > 0
                                    Union All
                                    Select stock_id, (gtt->>'apply_itc')::Boolean, (annex_info->'gst_input_info'->>'supplier_gstin')::Varchar, 
                                        (gtt->>'bt_amt')::Numeric, (gtt->>'sgst_amt')::Numeric, (gtt->>'cgst_amt')::Numeric, (gtt->>'igst_amt')::Numeric
                                    From st.stock_control, jsonb_array_elements(annex_info->'gst_tax_tran') gtt
                                    Where vat_type_id Between 400 And 499
                                        And (annex_info->'gst_rc_info'->>'apply_rc')::Boolean = false
                                        And ((gtt->>'sgst_pcnt')::Numeric + (gtt->>'cgst_pcnt')::Numeric + (gtt->>'igst_pcnt')::Numeric) > 0
                                ) a
                            Group by a.voucher_id, a.supplier_gstin
                        )
                        Select a.doc, count(distinct a.voucher_id) as inv_count, Sum(b.bt_amt) as bt_amt_tot, Sum(b.sgst_amt) as sgst_amt_tot,
                            Sum(b.cgst_amt) as cgst_amt_tot, Sum(igst_amt) as igst_amt_tot, 
                            Sum(b.bt_amt+b.sgst_amt+b.cgst_amt+b.igst_amt) as inv_amt_tot,
                            Sum(b.sgst_itc_amt) as sgst_itc_amt_tot, Sum(b.cgst_itc_amt) as cgst_itc_amt_tot,
                            Sum(b.igst_itc_amt) as igst_itc_amt_tot
                        From bill_list a
                        Inner Join gst_tran b On a.voucher_id = b.voucher_id
                        Where a.status = 5
                            And a.vat_type_id in (401,402)
                            And (length(a.supplier_gstin) > 2 or length(b.supplier_gstin) > 2)
                            --And a.apply_rc = false
                        Group by a.doc
                        Union All
                        Select 'PAYV-MLT', count(distinct a.voucher_id) as inv_count, Sum(b.bt_amt) as bt_amt_tot, Sum(b.sgst_amt) as sgst_amt_tot,
                            Sum(b.cgst_amt) as cgst_amt_tot, Sum(igst_amt) as igst_amt_tot, 
                            Sum(b.bt_amt+b.sgst_amt+b.cgst_amt+b.igst_amt) as inv_amt_tot,
                            Sum(b.sgst_itc_amt) as sgst_itc_amt_tot, Sum(b.cgst_itc_amt) as cgst_itc_amt_tot,
                            Sum(b.igst_itc_amt) as igst_itc_amt_tot
                        From (  Select x.voucher_id, y.vch_tran_id, (x.annex_info->'gst_input_info'->>'vat_type_id')::BigInt vat_type_id
                                From ac.vch_control x
                                Inner Join ac.vch_tran y On x.voucher_id = y.voucher_id
                                Where  x.doc_date between :pfrom_date And :pto_date
                                   And x.branch_id = Any(:pbranch_ids::BigInt[])
                                   And x.doc_type = Any('{PAYV,PAYC,PAYB}'::Varchar[])
                                   And (x.annex_info->>'line_item_gst')::Boolean ) a
                        Inner Join (Select x.voucher_id, x.gst_tax_tran_id, x.apply_itc, x.supplier_gstin, x.bt_amt, 
                                    x.sgst_amt, x.cgst_amt, x.igst_amt,
                                    Case When x.apply_itc Then x.sgst_amt Else 0 End as sgst_itc_amt,
                                    Case When x.apply_itc Then x.cgst_amt Else 0 End as cgst_itc_amt,
                                    Case When x.apply_itc Then x.igst_amt Else 0 End as igst_itc_amt
                                    From tx.gst_tax_tran x
                                    Where x.is_rc = false
                                        And sgst_pcnt+cgst_pcnt+igst_pcnt > 0) b On a.voucher_id = b.voucher_id And a.vch_tran_id = b.gst_tax_tran_id
                        Where a.vat_type_id In (401, 402)
                            And length(b.supplier_gstin) > 2
                        Having Sum(b.bt_amt) >0
                        Order by doc";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{".$this->getBranchInState($option->gst_state_id)."}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    public function getB2B_detail(\app\core\tx\gstr2\Gstr2ProviderOption $option) : \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With bill_list 
                        As
                        (    ".implode("\nUnion All\n", $this->docList) ."),
                        gst_tran
                        As
                        (   Select a.voucher_id, a.supplier_gstin, a.gst_rate_id, a.gst_pcnt,
                         		Sum(a.bt_amt) as bt_amt, Sum(a.sgst_amt) as sgst_amt,
                                Sum(a.cgst_amt) as cgst_amt, Sum(a.igst_amt) as igst_amt,
                         		Sum(a.cess_amt) as cess_amt,
                                Sum(Case When a.apply_itc Then a.sgst_amt Else 0 End) as sgst_itc_amt,
                                Sum(Case When a.apply_itc Then a.cgst_amt Else 0 End) as cgst_itc_amt,
                                Sum(Case When a.apply_itc Then a.igst_amt Else 0 End) as igst_itc_amt,
                         		Sum(Case When a.apply_itc Then a.cess_amt Else 0 End) as cess_itc_amt
                            From (  Select voucher_id, gst_rate_id, apply_itc, supplier_gstin,
                                  		sgst_pcnt+cgst_pcnt+igst_pcnt as gst_pcnt,
                                  		bt_amt, sgst_amt, cgst_amt, 
                                  		igst_amt, cess_amt
                                    From tx.gst_tax_tran
                                    Where is_rc = false
                                        And (sgst_amt + cgst_amt + igst_amt) > 0
                                    Union All
                                    Select stock_id, (gtt->>'gst_rate_id')::BigInt gst_rate_id, (gtt->>'apply_itc')::Boolean, (annex_info->'gst_input_info'->>'supplier_gstin')::Varchar,
                                        (gtt->>'sgst_pcnt')::Numeric+(gtt->>'cgst_pcnt')::Numeric+(gtt->>'igst_pcnt')::Numeric,
                                  		(gtt->>'bt_amt')::Numeric, (gtt->>'sgst_amt')::Numeric, (gtt->>'cgst_amt')::Numeric, 
                                  		(gtt->>'igst_amt')::Numeric, (gtt->>'cess_amt')::Numeric
                                    From st.stock_control, jsonb_array_elements(annex_info->'gst_tax_tran') gtt
                                    Where vat_type_id Between 400 And 499
                                ) a
                            Group by a.voucher_id, a.supplier_gstin, a.gst_rate_id, a.gst_pcnt
                        )
                        Select a.doc, a.doc_date, a.voucher_id, a.supplier_gstin, a.bill_no, a.bill_date, a.bill_amt, a.itc_type,
                            Sum(b.bt_amt) as bt_amt, Sum(b.sgst_amt + b.cgst_amt + b.igst_amt + b.cess_amt) as tax_amt,
                            Sum(b.sgst_itc_amt + b.cgst_itc_amt + b.igst_itc_amt + b.cess_itc_amt) as itc_amt
                        From bill_list a
                        Inner Join gst_tran b On a.voucher_id = b.voucher_id
                        Where a.status = 5
                            And a.vat_type_id in (401, 402)
                            And (length(a.supplier_gstin) > 2 or length(b.supplier_gstin) > 2)
                        Group By a.doc, a.doc_date, a.voucher_id, a.supplier_gstin, a.bill_no, a.bill_date, a.bill_amt, a.itc_type
                        Order by a.doc, a.supplier_gstin, a.doc_date, a.voucher_id";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{".$this->getBranchInState($option->gst_state_id)."}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    public function getB2C93_summary(\app\core\tx\gstr2\Gstr2ProviderOption $option, $type) : \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With bill_list 
                        As
                        (    ".implode("\nUnion All\n", $this->docList) .")
                        Select a.doc, a.doc_date, a.voucher_id, gtt.gst_tax_tran_id, gtt.bt_amt, gtt.sgst_amt, gtt.cgst_amt, gtt.igst_amt,
                            Case When gtt.apply_itc Then sgst_amt Else 0 End as sgst_itc_amt,
                            Case When gtt.apply_itc Then cgst_amt Else 0 End as cgst_itc_amt,
                            Case When gtt.apply_itc Then igst_amt Else 0 End as igst_itc_amt,
                            Case When length(a.supplier_gstin) = 15 Then '--' Else coalesce(d.si_tran_id, 'Pending') End as si_tran_id
                        From bill_list a
                        Inner Join tx.gst_tax_tran gtt On a.voucher_id = gtt.voucher_id
                        Left Join ac.si_tran d On gtt.gst_tax_tran_id = d.ref_tran_id
                        Where a.status = 5
                            And a.apply_rc = true And gtt.is_rc = true
                            And a.rc_sec_id In (93,53)
                            And a.vat_type_id in (401,402)
                            And (gtt.sgst_pcnt+gtt.cgst_pcnt+gtt.igst_pcnt) > 0
                            And Case When :prs = 'rs' Then length(a.supplier_gstin) = 15 Else length(a.supplier_gstin) != 15 End
                        Union All
                        Select 'PAYV-MLT', a.doc_date, a.voucher_id, b.gst_tax_tran_id, b.bt_amt, b.sgst_amt, b.cgst_amt, b.igst_amt, 
                            b.sgst_itc_amt, b.cgst_itc_amt, b.igst_itc_amt,
                            Case When length(b.supplier_gstin) = 15 Then '--' Else coalesce(d.si_tran_id, 'Pending') End as si_tran_id
                        From (  Select x.doc_date, x.voucher_id, y.vch_tran_id, (x.annex_info->'gst_input_info'->>'vat_type_id')::BigInt vat_type_id
                                From ac.vch_control x
                                Inner Join ac.vch_tran y On x.voucher_id = y.voucher_id
                                Where  x.doc_date between :pfrom_date And :pto_date
                                   And x.branch_id = Any(:pbranch_ids::BigInt[])
                                   And x.doc_type = Any('{PAYV,PAYC,PAYB}'::Varchar[])
                                   And (x.annex_info->>'line_item_gst')::Boolean ) a
                        Inner Join (Select x.voucher_id, x.gst_tax_tran_id, x.apply_itc, x.supplier_gstin, x.bt_amt, 
                                    x.sgst_amt, x.cgst_amt, x.igst_amt,
                                    Case When x.apply_itc Then x.sgst_amt Else 0 End as sgst_itc_amt,
                                    Case When x.apply_itc Then x.cgst_amt Else 0 End as cgst_itc_amt,
                                    Case When x.apply_itc Then x.igst_amt Else 0 End as igst_itc_amt
                                    From tx.gst_tax_tran x
                                    Where x.is_rc = true And x.rc_sec_id in (93,53)
                                        And sgst_pcnt+cgst_pcnt+igst_pcnt > 0) b On a.voucher_id = b.voucher_id And a.vch_tran_id = b.gst_tax_tran_id
                        Left Join ac.si_tran d On b.gst_tax_tran_id = d.ref_tran_id
                        Where a.vat_type_id In (401, 402)
                            And Case When :prs = 'rs' Then length(b.supplier_gstin) = 15 Else length(b.supplier_gstin) != 15 End
                        Order by doc_date, voucher_id";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{".$this->getBranchInState($option->gst_state_id)."}");
        $cmm->addParam('prs', $type);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    public function getB2C94_summary(\app\core\tx\gstr2\Gstr2ProviderOption $option) : \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With bill_list 
                        As
                        (    ".implode("\nUnion All\n", $this->docList) ."
                            Union All -- Include Buy Backs
                            Select 'Buy Back' as doc, a.doc_date, bb.inv_bb_id as voucher_id, 401, status, 
                                29 as supplier_state_id, '29' as supplier_gstin,
                                bb.bt_amt, 0.00 as tax_amt, bb.item_amt, true, 94,
                                '', a.doc_date, bb.bt_amt, 'ip' as itc_type
                            From st.stock_control a
                            Inner Join st.inv_bb bb On a.stock_id = bb.inv_id
                            Where a.doc_date between :pfrom_date And :pto_date
                                And a.branch_id = Any(:pbranch_ids::BigInt[])
                                And a.doc_type = Any('{SIV}'::Varchar[])
                            Union All
                            Select 'Buy Back POS' as doc, a.doc_date, bb.inv_bb_id as voucher_id, 401, status, 
                                29 as supplier_state_id, '29' as supplier_gstin,
                                bb.bt_amt, 0.00 as tax_amt, bb.item_amt, true, 94,
                                '', a.doc_date, bb.bt_amt, 'ip' as itc_type
                            From pos.inv_control a
                            Inner Join pos.inv_bb bb On a.inv_id = bb.inv_id
                            Where a.doc_date between :pfrom_date And :pto_date
                                And a.branch_id = Any(:pbranch_ids::BigInt[])
                                And a.doc_type = Any('{PIV}'::Varchar[])
                        ),
                        txn_date
                        As
                        (   Select a.doc_date, Case When Sum(gtt.bt_amt) > 5000 Then true Else false End as above_limit
                            From bill_list a
                            Inner Join tx.gst_tax_tran gtt On a.voucher_id = gtt.voucher_id
                            Where a.apply_rc = True And gtt.is_rc = true
                                And gtt.rc_sec_id in (94,54)
                                Group by a.doc_date
                        )
                        Select a.doc, a.doc_date, a.voucher_id, gtt.gst_tax_tran_id, gtt.bt_amt, gtt.sgst_amt, gtt.cgst_amt, gtt.igst_amt,
                            Case When gtt.apply_itc Then sgst_amt Else 0 End as sgst_itc_amt,
                            Case When gtt.apply_itc Then cgst_amt Else 0 End as cgst_itc_amt,
                            Case When gtt.apply_itc Then igst_amt Else 0 End as igst_itc_amt,
                            coalesce(d.si_tran_id, 'Pending') as si_tran_id
                        From bill_list a
                        Inner Join tx.gst_tax_tran gtt On a.voucher_id = gtt.voucher_id
                        Inner Join txn_date c On a.doc_date = c.doc_date
                        Left Join ac.si_tran d On gtt.gst_tax_tran_id = d.ref_tran_id
                        Where a.status =5 
                            And a.vat_type_id in (401,402)
                            And a.apply_rc = True And gtt.is_rc = true
                            And gtt.rc_sec_id In (94,54)
                            And gtt.sgst_pcnt + gtt.cgst_pcnt + gtt.igst_pcnt > 0
                            And c.above_limit
                        Union All
                        Select 'PAYV-MLT', a.doc_date, a.voucher_id, b.gst_tax_tran_id, b.bt_amt, b.sgst_amt, b.cgst_amt, b.igst_amt, 
                            b.sgst_itc_amt, b.cgst_itc_amt, b.igst_itc_amt,
                            Case When length(b.supplier_gstin) = 15 Then '--' Else coalesce(d.si_tran_id, 'Pending') End as si_tran_id
                        From (  Select x.doc_date, x.voucher_id, y.vch_tran_id, (x.annex_info->'gst_input_info'->>'vat_type_id')::BigInt vat_type_id
                                From ac.vch_control x
                                Inner Join ac.vch_tran y On x.voucher_id = y.voucher_id
                                Where  x.doc_date between :pfrom_date And :pto_date
                                   And x.branch_id = Any(:pbranch_ids::BigInt[])
                                   And x.doc_type = Any('{PAYV,PAYC,PAYB}'::Varchar[])
                                   And (x.annex_info->>'line_item_gst')::Boolean ) a
                        Inner Join (Select x.voucher_id, x.gst_tax_tran_id, x.apply_itc, x.supplier_gstin, x.bt_amt, 
                                    x.sgst_amt, x.cgst_amt, x.igst_amt,
                                    Case When x.apply_itc Then x.sgst_amt Else 0 End as sgst_itc_amt,
                                    Case When x.apply_itc Then x.cgst_amt Else 0 End as cgst_itc_amt,
                                    Case When x.apply_itc Then x.igst_amt Else 0 End as igst_itc_amt
                                    From tx.gst_tax_tran x
                                    Where x.is_rc = true And x.rc_sec_id in (94,54)
                                        And sgst_pcnt+cgst_pcnt+igst_pcnt > 0) b On a.voucher_id = b.voucher_id And a.vch_tran_id = b.gst_tax_tran_id
                        Left Join ac.si_tran d On b.gst_tax_tran_id = d.ref_tran_id
                        Where a.vat_type_id In (401, 402)
                        Union All
                        Select 'StockPurchase', a.doc_date, a.stock_id, a.stock_id || (gtt->>'sl_no')::Varchar, (gtt->>'bt_amt')::Numeric, 
                            (gtt->>'sgst_amt')::Numeric, (gtt->>'cgst_amt')::Numeric, (gtt->>'igst_amt')::Numeric,
                            Case When (gtt->>'apply_itc')::Boolean Then (gtt->>'sgst_amt')::Numeric Else 0 End as sgst_itc_amt,
                            Case When (gtt->>'apply_itc')::Boolean Then (gtt->>'cgst_amt')::Numeric Else 0 End as cgst_itc_amt,
                            Case When (gtt->>'apply_itc')::Boolean Then (gtt->>'igst_amt')::Numeric Else 0 End as igst_itc_amt,
                            'Pending'
                            --coalesce(d.si_tran_id, 'Pending') as si_tran_id
                        From st.stock_control a, jsonb_array_elements(annex_info->'gst_tax_tran') gtt
                        --Left Join ac.si_tran d On a.stock_id = d.ref_id
                        Where a.status =5 
                            And a.vat_type_id in (401,402)
                            And length(a.annex_info->'gst_input_info'->>'supplier_gstin') = 2
                            And a.doc_date Between :pfrom_date And :pto_date
                            And ((gtt->>'sgst_pcnt')::Numeric + (gtt->>'cgst_pcnt')::Numeric +(gtt->>'igst_pcnt')::Numeric) > 0
                        Order by doc_date, voucher_id";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{".$this->getBranchInState($option->gst_state_id)."}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    public function getB2C94_exmpt_summary(\app\core\tx\gstr2\Gstr2ProviderOption $option) : \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With bill_list 
                        As
                        (    ".implode("\nUnion All\n", $this->docList) ."
                            Union All -- Include Buy Backs
                            Select 'Buy Back' as doc, a.doc_date, bb.inv_bb_id as voucher_id, 401, status, 
                                29 as supplier_state_id, '29' as supplier_gstin,
                                bb.bt_amt, 0.00 as tax_amt, bb.item_amt, true, 94,
                                '', a.doc_date, bb.bt_amt, 'ip' as itc_type
                            From st.stock_control a
                            Inner Join st.inv_bb bb On a.stock_id = bb.inv_id
                            Where a.doc_date between :pfrom_date And :pto_date
                                And a.branch_id = Any(:pbranch_ids::BigInt[])
                                And a.doc_type = Any('{SIV}'::Varchar[])
                            Union All
                            Select 'Buy Back POS' as doc, a.doc_date, bb.inv_bb_id as voucher_id, 401, status, 
                                29 as supplier_state_id, '29' as supplier_gstin,
                                bb.bt_amt, 0.00 as tax_amt, bb.item_amt, true, 94,
                                '', a.doc_date, bb.bt_amt, 'ip' as itc_type
                            From pos.inv_control a
                            Inner Join pos.inv_bb bb On a.inv_id = bb.inv_id
                            Where a.doc_date between :pfrom_date And :pto_date
                                And a.branch_id = Any(:pbranch_ids::BigInt[])
                                And a.doc_type = Any('{PIV}'::Varchar[])
                        ),
                        txn_date
                        As
                        (   Select a.doc_date, Case When Sum(gtt.bt_amt) > 5000 Then true Else false End as above_limit
                            From bill_list a
                            Inner Join tx.gst_tax_tran gtt On a.voucher_id = gtt.voucher_id
                            Where a.apply_rc = True And gtt.is_rc = true
                                And gtt.rc_sec_id In (94, 54)
                                And gtt.sgst_pcnt + gtt.cgst_pcnt + gtt.igst_pcnt > 0
                                Group by a.doc_date
                        )
                        Select a.doc, a.doc_date, a.voucher_id, gtt.gst_tax_tran_id, gtt.bt_amt, gtt.sgst_amt, gtt.cgst_amt, gtt.igst_amt,
                            Case When gtt.apply_itc Then sgst_amt Else 0 End as sgst_itc_amt,
                            Case When gtt.apply_itc Then cgst_amt Else 0 End as cgst_itc_amt,
                            Case When gtt.apply_itc Then igst_amt Else 0 End as igst_itc_amt
                        From bill_list a
                        Inner Join tx.gst_tax_tran gtt On a.voucher_id = gtt.voucher_id
                        Inner Join txn_date c On a.doc_date = c.doc_date
                        Where a.status = 5
                            And a.vat_type_id In (401,402)
                            And a.apply_rc = True And gtt.is_rc = true
                            And gtt.rc_sec_id In (94, 54)
                            And gtt.sgst_pcnt + gtt.cgst_pcnt + gtt.igst_pcnt > 0
                            And c.above_limit = false
                        Order by doc_date";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{".$this->getBranchInState($option->gst_state_id)."}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    public function getB2B_ur_detail(\app\core\tx\gstr2\Gstr2ProviderOption $option) : \app\cwf\vsla\data\DataTable {
        // Returns detailed information of all Self-Invoices created for Local and Inter State
        $siSql = "With si_list
                    As
                    (   Select 'Self Invoice'::Varchar as doc, doc_date, voucher_id, 
                            (annex_info->'gst_input_info'->>'vat_type_id')::BigInt as vat_type_id, 
                            status, annex_info->'gst_input_info'->>'supplier_name' as supplier_name,
                            (annex_info->'gst_input_info'->>'supplier_state_id')::BigInt as supplier_state_id,
                            (annex_info->>'bt_amt')::Numeric as bt_amt, 
                            (annex_info->>'tax_amt')::Numeric as tax_amt, credit_amt, 'is' as itc_type
                        From ac.vch_control
                        Where  doc_date between :pfrom_date And :pto_date
                            And branch_id = Any(:pbranch_ids::BigInt[])
                            And doc_type = Any('{SIRC}'::Varchar[])
                            And (annex_info->'gst_input_info'->>'vat_type_id')::BigInt Between 400 And 499
                    ),
                    gst_tran
                    As
                    (   Select voucher_id, gst_rate_id, sgst_pcnt+cgst_pcnt+igst_pcnt as gst_pcnt,
                            Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                            Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt,
                            Sum(cess_amt) as cess_amt,
                                    Sum(Case When apply_itc Then sgst_amt Else 0 End) as sgst_itc_amt,
                            Sum(Case When apply_itc Then cgst_amt Else 0 End) as cgst_itc_amt,
                            Sum(Case When apply_itc Then igst_amt Else 0 End) as igst_itc_amt,
                                    Sum(Case When apply_itc Then cess_amt Else 0 End) as cess_itc_amt
                        From tx.gst_tax_tran
                        Group by voucher_id, gst_rate_id, sgst_pcnt+cgst_pcnt+igst_pcnt
                    )
                    Select a.doc, a.doc_date, a.voucher_id, a.vat_type_id, a.supplier_state_id, a.supplier_name,
                    	a.credit_amt as inv_amt, a.itc_type, 
                        row_number() Over (Partition By a.voucher_id) as sl_no, b.gst_rate_id, b.gst_pcnt, 
                        b.bt_amt, b.sgst_amt, b.cgst_amt, b.igst_amt, b.cess_amt,
                        b.sgst_itc_amt, b.cgst_itc_amt, b.igst_itc_amt, b.cess_itc_amt
                    From si_list a
                    Inner Join gst_tran b On a.voucher_id = b.voucher_id
                    Where a.status = 5
                        And a.vat_type_id In (401,402)
                    Order By a.doc_date";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($siSql);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{".$this->getBranchInState($option->gst_state_id)."}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    public function getIMP_ovs_summary(\app\core\tx\gstr2\Gstr2ProviderOption $option) : \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With bill_list 
                        As
                        (    ".implode("\nUnion All\n", $this->docList) .")
                        Select a.doc, a.doc_date, a.voucher_id, gtt.gst_tax_tran_id, gtt.bt_amt, gtt.sgst_amt, gtt.cgst_amt, gtt.igst_amt,
                            Case When gtt.apply_itc Then sgst_amt Else 0 End as sgst_itc_amt,
                            Case When gtt.apply_itc Then cgst_amt Else 0 End as cgst_itc_amt,
                            Case When gtt.apply_itc Then igst_amt Else 0 End as igst_itc_amt,
                            Case When length(a.supplier_gstin) = 15 Then '--' Else coalesce(d.si_tran_id, 'Pending') End as si_tran_id
                        From bill_list a
                        Inner Join tx.gst_tax_tran gtt On a.voucher_id = gtt.voucher_id
                        Left Join ac.si_tran d On gtt.gst_tax_tran_id = d.ref_tran_id
                        Where a.status = 5
                            And (a.vat_type_id = 403)
                            And a.apply_rc = true And gtt.is_rc = true
                        Order by a.doc, a.doc_date, a.voucher_id";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{".$this->getBranchInState($option->gst_state_id)."}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    public function getIMP_sez_summary(\app\core\tx\gstr2\Gstr2ProviderOption $option) : \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With bill_list 
                        As
                        (    ".implode("\nUnion All\n", $this->docList) .")
                        Select a.doc, a.doc_date, a.voucher_id, gtt.gst_tax_tran_id, gtt.bt_amt, gtt.sgst_amt, gtt.cgst_amt, gtt.igst_amt,
                            Case When gtt.apply_itc Then sgst_amt Else 0 End as sgst_itc_amt,
                            Case When gtt.apply_itc Then cgst_amt Else 0 End as cgst_itc_amt,
                            Case When gtt.apply_itc Then igst_amt Else 0 End as igst_itc_amt,
                            Case When length(a.supplier_gstin) = 15 Then '--' Else coalesce(d.si_tran_id, 'Pending') End as si_tran_id
                        From bill_list a
                        Inner Join tx.gst_tax_tran gtt On a.voucher_id = gtt.voucher_id
                        Left Join ac.si_tran d On gtt.gst_tax_tran_id = d.ref_tran_id
                        Where a.status = 5
                            And (a.vat_type_id = 405)
                            And a.apply_rc = true And gtt.is_rc = true
                        Order by a.doc, a.doc_date, a.voucher_id";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{".$this->getBranchInState($option->gst_state_id)."}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    public function getIMP_s_detail(\app\core\tx\gstr2\Gstr2ProviderOption $option) : \app\cwf\vsla\data\DataTable {
        // Returns detailed information of all Self-Invoices created for Imports
        $siSql = "With si_list
                    As
                    (   Select 'Self Invoice'::Varchar as doc, doc_date, voucher_id, 
                            (annex_info->'gst_input_info'->>'vat_type_id')::BigInt as vat_type_id, 
                            status, annex_info->'gst_input_info'->>'supplier_name' as supplier_name,
                            (annex_info->'gst_input_info'->>'supplier_state_id')::BigInt as supplier_state_id,
                            (annex_info->>'bt_amt')::Numeric as bt_amt, 
                            (annex_info->>'tax_amt')::Numeric as tax_amt, credit_amt, 'is' as itc_type
                        From ac.vch_control
                        Where  doc_date between :pfrom_date And :pto_date
                            And branch_id = Any(:pbranch_ids::BigInt[])
                            And doc_type = Any('{SIRC}'::Varchar[])
                            And (annex_info->'gst_input_info'->>'vat_type_id')::BigInt Between 400 And 499
                    ),
                    gst_tran
                    As
                    (   Select voucher_id, gst_rate_id, sgst_pcnt+cgst_pcnt+igst_pcnt as gst_pcnt,
                            Case When apply_itc Then
                         		Case When hsn_sc_type = 'G' Then 'in' Else 'is' End
                         	Else 'no' End as elg,
                            Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                            Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt,
                            Sum(cess_amt) as cess_amt,
                                    Sum(Case When apply_itc Then sgst_amt Else 0 End) as sgst_itc_amt,
                            Sum(Case When apply_itc Then cgst_amt Else 0 End) as cgst_itc_amt,
                            Sum(Case When apply_itc Then igst_amt Else 0 End) as igst_itc_amt,
                                    Sum(Case When apply_itc Then cess_amt Else 0 End) as cess_itc_amt
                        From tx.gst_tax_tran
                        Group by voucher_id, gst_rate_id, sgst_pcnt+cgst_pcnt+igst_pcnt, apply_itc, hsn_sc_type
                    )
                    Select a.doc, a.doc_date, a.voucher_id, a.vat_type_id, a.supplier_state_id, a.supplier_name,
                    	a.credit_amt as inv_amt, a.itc_type,
                        row_number() Over (Partition By a.voucher_id) as sl_no, b.gst_rate_id, b.gst_pcnt, 
                        b.bt_amt, b.sgst_amt, b.cgst_amt, b.igst_amt, b.cess_amt, b.elg,
                        b.sgst_itc_amt, b.cgst_itc_amt, b.igst_itc_amt, b.cess_itc_amt
                    From si_list a
                    Inner Join gst_tran b On a.voucher_id = b.voucher_id
                    Where a.status = 5
                        And a.vat_type_id In (403)
                    Order By a.doc_date";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($siSql);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{".$this->getBranchInState($option->gst_state_id)."}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    public function getCDNR_summary(\app\core\tx\gstr2\Gstr2ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $sql = "With bill_list 
                As
                (   Select 'Purchase Returns'::Varchar as doc, doc_date, stock_id as voucher_id, vat_type_id, status, 
                        annex_info->'gst_input_info'->>'supplier_state_id' as supplier_state_id,
                        annex_info->'gst_input_info'->>'supplier_gstin' as supplier_gstin, 
                        before_tax_amt as bt_amt, tax_amt, total_amt,
                        annex_info->>'origin_inv_id' as origin_inv_id,
                        annex_info->>'origin_inv_date' as origin_inv_date
                    From st.stock_control 
                    Where vat_type_id Between 400 and 499
                        And doc_date between :pfrom_date And :pto_date
                        And branch_id = Any(:pbranch_ids::BigInt[])
                        And doc_type = Any('{PRV}'::Varchar[])
                    Union All
                    Select 'Debit Notes'::Varchar as doc, doc_date, voucher_id, (annex_info->'gst_input_info'->>'vat_type_id')::BigInt, status, 
                        annex_info->'gst_input_info'->>'supplier_state_id' as supplier_state_id,
                        annex_info->'gst_input_info'->>'supplier_gstin' as supplier_gstin, 
                        0 as bt_amt, 0 tax_amt, credit_amt,
                        annex_info->>'origin_bill_id' as origin_inv_id,
                        annex_info->>'origin_bill_date' as origin_inv_date
                    From ap.pymt_control
                    Where (annex_info->'gst_input_info'->>'vat_type_id')::BigInt Between 400 and 499
                        And doc_date between :pfrom_date And :pto_date
                        And branch_id = Any(:pbranch_ids::BigInt[])
                        And doc_type = Any('{DN2}'::Varchar[])
                ),
                gst_tran
                As
                (   Select voucher_id, Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                        Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt
                    From tx.gst_tax_tran
                    Where sgst_pcnt+cgst_pcnt+igst_pcnt != 0
                    Group by voucher_id
                )
                Select a.doc, a.doc_date, a.voucher_id, a.origin_inv_id, a.origin_inv_date, 
                    Sum(b.bt_amt) as bt_amt_tot, Sum(b.sgst_amt) as sgst_amt_tot,
                    Sum(b.cgst_amt) as cgst_amt_tot, Sum(b.igst_amt) as igst_amt_tot, 
                    Sum(b.bt_amt+b.sgst_amt+b.cgst_amt+b.igst_amt) as inv_amt_tot
                From bill_list a
                Inner Join gst_tran b On a.voucher_id = b.voucher_id
                Where a.status = 5
                    And length(a.supplier_gstin) > 2
                    And a.vat_type_id In (401, 402)
                Group by a.doc, a.doc_date, a.voucher_id, a.origin_inv_id, a.origin_inv_date
                Order by a.doc, a.doc_date, a.voucher_id";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($sql);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    public function getCP_NIL_EXEMP_summary(\app\core\tx\gstr2\Gstr2ProviderOption $option) : array {
        $result = [];
        //Purchases from Composite Taxable Person
        $invSumSQL = "With bill_list 
                        As
                        (    ".implode("\nUnion All\n", $this->docList) ."),
                        gst_tran
                        As
                        (   Select a.voucher_id, a.supplier_gstin, Sum(a.bt_amt) as bt_amt, Sum(a.sgst_amt) as sgst_amt,
                                Sum(a.cgst_amt) as cgst_amt, Sum(a.igst_amt) as igst_amt,
                                Sum(Case When a.apply_itc Then a.sgst_amt Else 0 End) as sgst_itc_amt,
                                Sum(Case When a.apply_itc Then a.cgst_amt Else 0 End) as cgst_itc_amt,
                                Sum(Case When a.apply_itc Then a.igst_amt Else 0 End) as igst_itc_amt
                            From (  Select voucher_id, apply_itc, supplier_gstin, bt_amt, sgst_amt, cgst_amt, igst_amt
                                    From tx.gst_tax_tran
                                    Where is_rc = false
                                        And (sgst_pcnt + cgst_pcnt + igst_pcnt) = 0
                                    Union All
                                    Select stock_id, (gtt->>'apply_itc')::Boolean, (annex_info->'gst_input_info'->>'supplier_gstin')::Varchar, 
                                        (gtt->>'bt_amt')::Numeric, (gtt->>'sgst_amt')::Numeric, (gtt->>'cgst_amt')::Numeric, (gtt->>'igst_amt')::Numeric
                                    From st.stock_control, jsonb_array_elements(annex_info->'gst_tax_tran') gtt
                                    Where vat_type_id = 404
                                ) a
                            Group by a.voucher_id, a.supplier_gstin
                        )
                        Select coalesce(Sum(b.bt_amt), 0) as bt_amt_tot
                        From bill_list a
                        Inner Join gst_tran b On a.voucher_id = b.voucher_id
                        Where a.status = 5
                            And a.vat_type_id = 404
                            And (length(a.supplier_gstin) > 2 or length(b.supplier_gstin) > 2)";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{".$this->getBranchInState($option->gst_state_id)."}");
        $dt_cp = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt_cp->Rows()) == 1) {
            $result['cp'] = $dt_cp->Rows()[0]['bt_amt_tot'];
        } else {
            $result['cp'] = 0.00;
        }
        
        // Nil/Exempt/non-gst purchases
        $comp_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
        $cmm_exmp = new \app\cwf\vsla\data\SqlCommand();
        $cmm_exmp->setCommandText("Select coalesce(sum(non_gst_amt), 0) as non_gst_amt, 
                                coalesce(Sum(exempt_local_amt), 0) as exempt,
                                coalesce(Sum(exempt_inter_amt), 0) as exempt_inter_amt
                                From tx.fn_gst_exp_reco_v2(:pcompany_id, :pbranch_id, 0, :pfrom_date, :pto_date, '{A005%,A006%}')");
        $cmm_exmp->addParam('pcompany_id', $comp_id);
        $cmm_exmp->addParam('pfrom_date', $option->ret_period_from);
        $cmm_exmp->addParam('pto_date', $option->ret_period_to);
        $cmm_exmp->addParam('pbranch_id', (($comp_id * 1000000) + 500000) + $option->gst_state_id);
        $dt_exmp = \app\cwf\vsla\data\DataConnect::getData($cmm_exmp);
        if(count($dt_exmp->Rows()) == 1) {
            // Do not reduce CTP as it is part of GSTPaid and not included in exempt
            $result['exemp'] = floatval($dt_exmp->Rows()[0]['exempt']); // - floatval($result['cp']); 
            $result['exemp_inter'] = floatval($dt_exmp->Rows()[0]['exempt_inter_amt']);
            $result['non_gst'] = $dt_exmp->Rows()[0]['non_gst_amt'];
        } else {
            $result['exemp'] = 0;
            $result['exemp_inter'] = 0;
            $result['non_gst'] = 0;
        }
        
        // Non Expense gst purchase
        $cmm_exmp2 = new \app\cwf\vsla\data\SqlCommand();
        $cmm_exmp2->setCommandText("Select coalesce(Sum(exempt_amt), 0) as exempt_amt
                                From tx.fn_gst_rc_nonexp_reco(:pcompany_id, :pbranch_id, 0, :pfrom_date, :pto_date)");
        $cmm_exmp2->addParam('pcompany_id', $comp_id);
        $cmm_exmp2->addParam('pfrom_date', $option->ret_period_from);
        $cmm_exmp2->addParam('pto_date', $option->ret_period_to);
        $cmm_exmp2->addParam('pbranch_id', (($comp_id * 1000000) + 500000) + $option->gst_state_id);
        $dt_exmp2 = \app\cwf\vsla\data\DataConnect::getData($cmm_exmp2);
        if(count($dt_exmp2->Rows()) == 1) {
            $result['exemp'] = floatval($result['exemp']) + $dt_exmp2->Rows()[0]['exempt_amt'];
        }
        return $result;
    }
    
    public function getHSN_summary(\app\core\tx\gstr2\Gstr2ProviderOption $option) : \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With bill_list 
                        As
                        (    ".implode("\nUnion All\n", $this->docList) ."
                            Union All
                            Select 'DCN'::Varchar as doc, doc_date, stock_id as voucher_id, vat_type_id, status, 
                                (annex_info->'gst_input_info'->>'supplier_state_id')::BigInt as supplier_state_id,
                                annex_info->'gst_input_info'->>'supplier_gstin' as supplier_gstin, 
                                before_tax_amt as bt_amt, tax_amt, total_amt,
                                false, -1,
                                annex_info->>'origin_inv_id' as origin_inv_id,
                                (annex_info->>'origin_inv_date')::Date as origin_inv_date, 0 bill_amt, 'is' as itc_type
                            From st.stock_control 
                            Where vat_type_id Between 400 and 499
                                And doc_date between :pfrom_date And :pto_date
                                And branch_id = Any(:pbranch_ids::BigInt[])
                                And doc_type = Any('{PRV}'::Varchar[])
                            Union All
                            Select 'DCN'::Varchar as doc, doc_date, voucher_id, (annex_info->'gst_input_info'->>'vat_type_id')::BigInt, status, 
                                (annex_info->'gst_input_info'->>'supplier_state_id')::BigInt as supplier_state_id,
                                annex_info->'gst_input_info'->>'supplier_gstin' as supplier_gstin, 
                                0 as bt_amt, 0 tax_amt, credit_amt,
                                false, -1,
                                annex_info->>'origin_inv_id' as origin_inv_id,
                                (annex_info->>'origin_inv_date')::Date as origin_inv_date, 0 bill_amt, 'is' as itc_type
                            From ap.pymt_control
                            Where (annex_info->'gst_input_info'->>'vat_type_id')::BigInt Between 400 and 499
                                And doc_date between :pfrom_date And :pto_date
                                And branch_id = Any(:pbranch_ids::BigInt[])
                                And doc_type = Any('{DN2}'::Varchar[])
                            Union All
                            Select 'GST Payment Voucher' as doc, doc_date, voucher_id, (annex_info->'gst_input_info'->>'vat_type_id')::BigInt vat_type_id, status, 
                                    (annex_info->'gst_input_info'->>'supplier_state_id')::BigInt as supplier_state_id,
                                    (annex_info->'gst_input_info'->>'supplier_gstin')::Varchar as supplier_gstin,
                                    (annex_info->>'bt_amt')::Numeric as bt_amt, (annex_info->>'tax_amt')::Numeric as tax_amt,
                                    credit_amt as total_amt, 
                                    (annex_info->'gst_rc_info'->>'apply_rc')::Boolean,
                                    (annex_info->'gst_rc_info'->>'rc_sec_id')::BigInt,
                                    (annex_info->>'bill_no')::Varchar, (annex_info->>'bill_date')::Date, (annex_info->>'bill_amt')::Numeric, 'is' as itc_type
                            From ac.vch_control 
                            Where  doc_date between :pfrom_date And :pto_date
                               And branch_id = Any(:pbranch_ids::BigInt[])
                               And doc_type = Any('{PAYV,PAYC,PAYB}'::Varchar[])
                               And (annex_info->>'line_item_gst')::Boolean = true
                        ),
                        gst_tran
                        As
                        (   Select voucher_id, hsn_sc_code, Sum(hsn_qty) as hsn_qty, Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                                Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt, Sum(cess_amt) as cess_amt
                            From tx.gst_tax_tran
                            Group by voucher_id, hsn_sc_code
                            Union All
                            Select stock_id, gtt->>'hsn_sc_code', 1, (gtt->>'bt_amt')::Numeric, (gtt->>'sgst_amt')::Numeric,
                                (gtt->>'cgst_amt')::Numeric, (gtt->>'igst_amt')::Numeric, (gtt->>'cess_amt')::Numeric
                            From st.stock_control, jsonb_array_elements(annex_info->'gst_tax_tran') gtt
                            Where vat_type_id Between 400 And 499
                        ),
                        hsn_sc
                        As
                        (   Select a.hsn_sc_id, a.hsn_sc_code, c.uom_code
                            From tx.hsn_sc a
                            Inner Join tx.hsn_sc_rate b On a.hsn_sc_id = b.hsn_sc_id
                            Inner Join tx.hsn_sc_uom c On b.hsn_sc_uom_id = c.hsn_sc_uom_id
                        )
                        Select row_number() over(order by b.hsn_sc_code) as sl_no, b.hsn_sc_code, COALESCE(c.uom_code, 'NOS') as hsn_sc_uom,
                            Sum(COALESCE(b.hsn_qty, 1))::Numeric(18,2) as hsn_qty_tot,
                            Sum(Case When a.doc = 'DCN' Then -b.bt_amt Else b.bt_amt End) as bt_amt_tot, 
                            Sum(Case When a.doc = 'DCN' Then -b.sgst_amt Else b.sgst_amt End) as sgst_amt_tot,
                            Sum(Case When a.doc = 'DCN' Then -b.cgst_amt Else b.cgst_amt End) as cgst_amt_tot,
                            Sum(Case When a.doc = 'DCN' Then -b.igst_amt Else b.igst_amt End) as igst_amt_tot,
                            Sum(Case When a.doc = 'DCN' Then -b.cess_amt Else b.cess_amt End) as cess_amt_tot,
                            Sum(Case When a.doc = 'DCN' Then -(b.bt_amt+b.sgst_amt+b.cgst_amt+b.igst_amt+b.cess_amt) Else b.bt_amt+b.sgst_amt+b.cgst_amt+b.igst_amt+b.cess_amt End) as inv_amt_tot
                        From bill_list a
                        Inner Join gst_tran b On a.voucher_id = b.voucher_id
                        Left Join hsn_sc c On b.hsn_sc_code = c.hsn_sc_code
                        Where a.status = 5
                            And (Case When a.itc_type = 'cp' Then b.hsn_sc_code not in ('00', '9900') Else 1=1 End)
                        Group by b.hsn_sc_code, c.uom_code
                        Order by sl_no";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{".$this->getBranchInState($option->gst_state_id)."}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    private $branchInState;
    private function getBranchInState($gst_state_id) : string {
        if($this->branchInState == null) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select branch_id from sys.branch Where gst_state_id = :pgst_state_id And company_id = {company_id}");
            $cmm->addParam('pgst_state_id', $gst_state_id);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            $this->branchInState = implode(",", $dt->select('branch_id'));
        }
        return $this->branchInState;
    }
}

/*  Missing data Validations
 * Select a.doc_date, a.voucher_id, c.bt_amt, (c.sgst_amt + c.cgst_amt + c.igst_amt) as tax_amt, a.annex_info
From ac.vch_control a
Inner Join ac.vch_tran b On a.voucher_id = b.voucher_id
Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
Where is_rc = false
	And hsn_sc_id Not in (0, 99000)
    And (c.sgst_amt + c.cgst_amt + c.igst_amt) > 0
    And length(a.annex_info->'gst_input_info'->>'supplier_gstin') = 2
Order by a.voucher_id
 * 
 */

/* Patch to fix introduction of rc_sec_id
 * ﻿Select jsonb_set(annex_info, '{gst_rc_info,rc_sec_id}', '"94"'::jsonb, true)
--Update ac.vch_control
--Set annex_info = jsonb_set(annex_info, '{gst_rc_info,rc_sec_id}', '"94"'::jsonb, true)
Where voucher_id = 'PAYV17MMKS00001'

Select * From tx.gst_tax_tran
--Update tx.gst_tax_tran
--Set is_rc = true, rc_sec_id = 94
Where voucher_id = 'PAYV17MMKS00005'
 * 
 */

<?php

namespace app\core\providers;

/**
 *  GstrProvider
 * @author girishshenoy
 */
class Gstr1Provider extends \app\core\tx\gstr1\Gstr1ProviderBase {

    // list of documents that would be processed
    public $docList = [
        'GstInvoice' => "Select 'GST Invoice' as doc, doc_date, invoice_id as voucher_id, vat_type_id, status, 
                            (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                            (annex_info->'gst_output_info'->>'customer_gstin')::Varchar as customer_gstin,
                            (annex_info->>'bt_amt')::Numeric as bt_amt, (annex_info->>'tax_amt')::Numeric as tax_amt, invoice_amt as total_amt
                         From ar.invoice_control 
                         Where doc_date between :pfrom_date And :pto_date
                            And branch_id = Any(:pbranch_ids::BigInt[])
                            And doc_type = Any(:pinv_types::Varchar[])
                            And vat_type_id Between 300 And 399",
        'StockGstInvoice' => "Select 'GST Stock Invoice' as doc, doc_date, stock_id as voucher_id, vat_type_id, status, 
                            (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                            (annex_info->'gst_output_info'->>'customer_gstin')::Varchar as customer_gstin, 
                            before_tax_amt as bt_amt, tax_amt, total_amt
                         From st.stock_control 
                         Where  doc_date between :pfrom_date And :pto_date
                            And branch_id = Any(:pbranch_ids::BigInt[])
                            And doc_type = Any('{SIV}'::Varchar[])
                            And vat_type_id Between 300 And 399",
        'PosGstInv' => "Select 'GST POS Invoice' as doc, doc_date, inv_id as voucher_id, vat_type_id, status, 
                            (annex_info->'gst_output_info'->>'cust_state_id')::BigInt as customer_state_id,
                            Case When cust_tin = 'N.A.' Then (annex_info->'gst_output_info'->>'cust_state_id')::Varchar Else cust_tin End as customer_gstin,
                            item_amt_tot as bt_amt, tax_amt_tot as tax_amt, inv_amt as total_amt
                    From pos.inv_control 
                    Where  doc_date between :pfrom_date And :pto_date
                       And branch_id = Any(:pbranch_ids::BigInt[])
                       And doc_type = Any('{PIV}'::Varchar[])
                       And vat_type_id Between 300 And 399",
        'AssetSale' => "Select 'GST Asset Sale' as doc, doc_date, as_id as voucher_id, (annex_info->'gst_output_info'->>'vat_type_id')::bigint vat_type_id, status, 
                            (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                            (annex_info->'gst_output_info'->>'customer_gstin')::Varchar as customer_gstin, 
                            gross_debit_amt as bt_amt, (annex_info->>'tax_amt')::Numeric as tax_amt, debit_amt
                    From fa.as_control 
                    Where doc_date between :pfrom_date And :pto_date
                       And branch_id = Any(:pbranch_ids::BigInt[])
                       And doc_type = Any('{AS2}'::Varchar[])
                       And (annex_info->'gst_output_info'->>'vat_type_id')::bigint Between 300 And 399"
    ];
    public $audList = [
        'GstInvoice',
        'StockGstInvoice',
        'GstInv',
        'GstAssetSale'
    ];

    /*
     * This method returns pending documents that have not been posted
     */

    public function preProcessPendingDocs(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $pendingDocsSQL = "With inv_list 
                           As
                           (    " . implode("\nUnion All\n", $this->docList) . 
                               
                            "Union All -- All unregistered pos sales returns for pos invoices during the period
                                Select 'POS Sale Returns'::Varchar as doc, doc_date, inv_id as voucher_id, vat_type_id, status, 
                                    (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                                    Case When cust_tin = 'N.A.' Then (annex_info->'gst_output_info'->>'cust_state_id')::Varchar Else cust_tin End as customer_gstin, 
                                    -item_amt_tot as bt_amt, -tax_amt_tot as tax_amt, -inv_amt as total_amt
                                From pos.inv_control 
                                Where vat_type_id Between 300 and 399
                                    And doc_date between :pfrom_date And :pto_date
                                    --And (annex_info->>'origin_inv_date')::Date between :pfrom_date And :pto_date
                                    And branch_id = Any(:pbranch_ids::BigInt[])
                                    And doc_type = Any('{PIR}'::Varchar[])
                              Union All -- All unregistered Stock sale returns for Stock invoices during the period
                                Select 'Stock Sale Returns'::Varchar as doc, doc_date, stock_id as voucher_id, vat_type_id, status, 
                                    (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                                    (annex_info->'gst_output_info'->>'customer_gstin')::Varchar as customer_gstin, 
                                    -(annex_info->>'items_total_amt')::Numeric as bt_amt, -tax_amt, -total_amt
                                From st.stock_control 
                                Where vat_type_id Between 300 and 399
                                    And doc_date between :pfrom_date And :pto_date
                                    --And (annex_info->>'origin_inv_date')::Date between :pfrom_date And :pto_date
                                    And branch_id = Any(:pbranch_ids::BigInt[])
                                    And doc_type = Any('{SRV}'::Varchar[]) 
                              Union All -- All unregistered Other sale returns for invoices during the period
                                Select 'Other Sale Returns'::Varchar as doc, doc_date, voucher_id, (annex_info->'gst_output_info'->>'vat_type_id')::BigInt, status, 
                                    (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                                    (annex_info->'gst_output_info'->>'customer_gstin')::Varchar as customer_gstin, 
                                    -(annex_info->>'items_total_amt')::Numeric as bt_amt, -(annex_info->>'tax_amt')::Numeric, -debit_amt
                                From ar.rcpt_control 
                                Where (annex_info->'gst_output_info'->>'vat_type_id')::BigInt Between 300 and 399
                                    And doc_date between :pfrom_date And :pto_date
                                    --And (annex_info->>'origin_inv_date')::Date between :pfrom_date And :pto_date
                                    And branch_id = Any(:pbranch_ids::BigInt[])
                                    And doc_type = Any('{CN2}'::Varchar[]) 
                           )
                           Select * 
                           From inv_list
                           Where status != 5
                           Order by doc, doc_date, voucher_id";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($pendingDocsSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $cmm->addParam('pinv_types', "{" . $this->getInvoiceTypeList() . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        // Process Pending Self Invoice
//        $pendSelfInv = "With inv_list
//                        As 
//                        (   Select voucher_id, doc_date, debit_amt"
        return $dt;
    }

    public function getB2B_summary(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With inv_list 
                        As
                        (    " . implode("\nUnion All\n", $this->docList) . "),
                        gst_tran
                        As
                        (   Select voucher_id, Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                                Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt
                            From tx.gst_tax_tran
                            Where sgst_pcnt+cgst_pcnt+igst_pcnt != 0
                            Group by voucher_id
                        )
                        Select a.doc, count(a.*) as inv_count, Sum(b.bt_amt) as bt_amt_tot, Sum(b.sgst_amt) as sgst_amt_tot,
                            Sum(b.cgst_amt) as cgst_amt_tot, Sum(igst_amt) as igst_amt_tot, 
                            Sum(b.bt_amt+b.sgst_amt+b.cgst_amt+b.igst_amt) as inv_amt_tot
                        From inv_list a
                        Inner Join gst_tran b On a.voucher_id = b.voucher_id
                        Where a.status = 5
                            And a.vat_type_id in (301, 302)
                            And length(a.customer_gstin) > 2
                        Group by a.doc
                        Order by a.doc";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $cmm->addParam('pinv_types', "{" . $this->getInvoiceTypeList() . "}");
        \yii::info($invSumSQL);
        \yii::info($cmm->getParamsForBind());
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    public function getB2B_raw_detail(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With inv_list 
                        As
                        (    " . implode("\nUnion All\n", $this->docList) . "),
                        gst_tran
                        As
                        (   Select voucher_id, Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                                Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt
                            From tx.gst_tax_tran
                            Where sgst_pcnt+cgst_pcnt+igst_pcnt != 0
                            Group by voucher_id
                        )
                        Select a.doc, a.doc_date, a.voucher_id, a.customer_gstin, a.customer_state_id, 
                            a.total_amt as inv_amt, a.vat_type_id,
                            row_number() Over (Partition By a.voucher_id) as sl_no, 
                            b.bt_amt, b.sgst_amt, b.cgst_amt, b.igst_amt
                        From inv_list a
                        Inner Join gst_tran b On a.voucher_id = b.voucher_id
                        Where a.status = 5
                            And a.vat_type_id in (301, 302)
                            And length(a.customer_gstin) > 2
                        Order by a.doc_date, a.voucher_id, sl_no";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $cmm->addParam('pinv_types', "{" . $this->getInvoiceTypeList() . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

    public function getB2B_detail(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        // vat type , 303, 304, 305 needs to be done seperately and then merged in json
        $invSumSQL = "With inv_list 
                        As
                        (    " . implode("\nUnion All\n", $this->docList) . "),
                        gst_tran
                        As
                        (   Select voucher_id, gst_rate_id, sgst_pcnt+cgst_pcnt+igst_pcnt as gst_pcnt, 
                                Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                                Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt,
                                Sum(cess_amt) as cess_amt
                            From tx.gst_tax_tran
                            Where sgst_pcnt+cgst_pcnt+igst_pcnt != 0
                            Group by voucher_id, gst_rate_id, sgst_pcnt+cgst_pcnt+igst_pcnt
                        )
                        Select a.doc, a.doc_date, a.voucher_id, a.customer_gstin, a.customer_state_id, 
                            a.total_amt as inv_amt, 'R' as inv_type, a.vat_type_id,
                            row_number() Over (Partition By a.voucher_id) as sl_no, b.gst_rate_id, b.gst_pcnt, 
                            b.bt_amt, b.sgst_amt, b.cgst_amt, b.igst_amt, b.cess_amt
                        From inv_list a
                        Inner Join gst_tran b On a.voucher_id = b.voucher_id
                        Where a.status = 5
                            And a.vat_type_id in (301, 302)
                            And length(a.customer_gstin) > 2
                        Order by a.customer_gstin, a.doc_date, a.voucher_id, sl_no";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $cmm->addParam('pinv_types', "{" . $this->getInvoiceTypeList() . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    public function getB2B_SEZ_detail(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        // This contains only the sez details vat type: 303, 304, 305 
        // to be deperately appended to json
        $invSumSQL = "With inv_list 
                        As
                        (    " . implode("\nUnion All\n", $this->docList) . "),
                        gst_tran
                        As
                        (   Select voucher_id, sgst_pcnt+cgst_pcnt+igst_pcnt as gst_pcnt, 
                                Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                                Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt,
                                Sum(cess_amt) as cess_amt
                            From tx.gst_tax_tran
                            Group by voucher_id, sgst_pcnt+cgst_pcnt+igst_pcnt
                        )
                        Select a.doc, a.doc_date, a.voucher_id, a.customer_gstin, a.customer_state_id, 
                            a.total_amt as inv_amt, 'R' as inv_type, a.vat_type_id,
                            row_number() Over (Partition By a.voucher_id) as sl_no, b.gst_pcnt, 
                            b.bt_amt, b.sgst_amt, b.cgst_amt, b.igst_amt, b.cess_amt
                        From inv_list a
                        Inner Join gst_tran b On a.voucher_id = b.voucher_id
                        Where a.status = 5
                            And a.vat_type_id in (303, 304, 305)
                            And length(a.customer_gstin) > 2
                        Order by a.customer_gstin, a.doc_date, a.voucher_id, sl_no";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $cmm->addParam('pinv_types', "{" . $this->getInvoiceTypeList() . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

    public function getB2CL_summary(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With inv_list 
                        As
                        (    " . implode("\nUnion All\n", $this->docList) . "),
                        gst_tran
                        As
                        (   Select voucher_id, Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                                Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt
                            From tx.gst_tax_tran
                            Where sgst_pcnt+cgst_pcnt+igst_pcnt != 0
                            Group by voucher_id
                            Having Sum(bt_amt+sgst_amt+cgst_amt+igst_amt) > 250000
                        )
                        Select a.doc, count(a.*) as inv_count, Sum(b.bt_amt) as bt_amt_tot, Sum(b.sgst_amt) as sgst_amt_tot,
                            Sum(b.cgst_amt) as cgst_amt_tot, Sum(b.igst_amt) as igst_amt_tot, 
                            Sum(b.bt_amt+b.sgst_amt+b.cgst_amt+b.igst_amt) as inv_amt_tot
                        From inv_list a
                        Inner Join gst_tran b On a.voucher_id = b.voucher_id
                        Where a.status = 5
                            And a.vat_type_id = 302
                            And length(a.customer_gstin) = 2
                        Group by a.doc
                        Order by a.doc";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $cmm->addParam('pinv_types', "{" . $this->getInvoiceTypeList() . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

    public function getB2CL_detail(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With inv_list 
                        As
                        (    " . implode("\nUnion All\n", $this->docList) . "),
                        gst_tran
                        As
                        (   Select voucher_id, gst_rate_id, sgst_pcnt+cgst_pcnt+igst_pcnt as gst_pcnt, 
                                Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                                Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt,
                                Sum(cess_amt) as cess_amt
                            From tx.gst_tax_tran
                            Where sgst_pcnt+cgst_pcnt+igst_pcnt != 0
                            Group by voucher_id, gst_rate_id, sgst_pcnt+cgst_pcnt+igst_pcnt
                        )
                        Select a.doc, a.doc_date, a.voucher_id, a.customer_gstin, a.customer_state_id,
                            a.total_amt as inv_amt, 'R' as inv_type,
                            row_number() Over (Partition By a.voucher_id) as sl_no, b.gst_rate_id, b.gst_pcnt, 
                            b.bt_amt, b.sgst_amt, b.cgst_amt, b.igst_amt, b.cess_amt
                        From inv_list a
                        Inner Join gst_tran b On a.voucher_id = b.voucher_id
                        Where a.status = 5
                            And a.vat_type_id = 302
                            And length(a.customer_gstin) = 2
                            And a.total_amt > 250000
                        Order by a.customer_state_id, a.doc, a.doc_date, a.voucher_id, sl_no";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $cmm->addParam('pinv_types', "{" . $this->getInvoiceTypeList() . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

    public function getB2CS_summary(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With inv_list 
                        As
                        (    " . implode("\nUnion All\n", $this->docList) .
                "Union All -- All unregistered pos sales returns for pos invoices during the period
                                Select 'POS Sale Returns'::Varchar as doc, doc_date, inv_id as voucher_id, vat_type_id, status, 
                                    (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                                    Case When cust_tin = 'N.A.' Then (annex_info->'gst_output_info'->>'cust_state_id')::Varchar Else cust_tin End as customer_gstin, 
                                    -item_amt_tot as bt_amt, -tax_amt_tot as tax_amt, -inv_amt as total_amt
                                From pos.inv_control 
                                Where vat_type_id Between 300 and 399
                                    And doc_date between :pfrom_date And :pto_date
                                    --And (annex_info->>'origin_inv_date')::Date between :pfrom_date And :pto_date
                                    And branch_id = Any(:pbranch_ids::BigInt[])
                                    And doc_type = Any('{PIR}'::Varchar[])
                              Union All -- All unregistered Stock sale returns for Stock invoices during the period
                                Select 'Stock Sale Returns'::Varchar as doc, doc_date, stock_id as voucher_id, vat_type_id, status, 
                                    (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                                    (annex_info->'gst_output_info'->>'customer_gstin')::Varchar as customer_gstin, 
                                    -(annex_info->>'items_total_amt')::Numeric as bt_amt, -tax_amt, -total_amt
                                From st.stock_control 
                                Where vat_type_id Between 300 and 399
                                    And doc_date between :pfrom_date And :pto_date
                                    --And (annex_info->>'origin_inv_date')::Date between :pfrom_date And :pto_date
                                    And branch_id = Any(:pbranch_ids::BigInt[])
                                    And doc_type = Any('{SRV}'::Varchar[]) 
                              Union All -- All unregistered Other sale returns for invoices during the period
                                Select 'Other Sale Returns'::Varchar as doc, doc_date, voucher_id, (annex_info->'gst_output_info'->>'vat_type_id')::BigInt, status, 
                                    (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                                    (annex_info->'gst_output_info'->>'customer_gstin')::Varchar as customer_gstin, 
                                    -(annex_info->>'items_total_amt')::Numeric as bt_amt, -(annex_info->>'tax_amt')::Numeric, -debit_amt
                                From ar.rcpt_control 
                                Where (annex_info->'gst_output_info'->>'vat_type_id')::BigInt Between 300 and 399
                                    And doc_date between :pfrom_date And :pto_date
                                    --And (annex_info->>'origin_inv_date')::Date between :pfrom_date And :pto_date
                                    And branch_id = Any(:pbranch_ids::BigInt[])
                                    And doc_type = Any('{CN2}'::Varchar[]) 
                        ),
                        gst_tran
                        As
                        (   Select voucher_id, Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                                Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt
                            From tx.gst_tax_tran
                            Where sgst_pcnt+cgst_pcnt+igst_pcnt != 0
                            Group by voucher_id
                            Having Case When Sum(igst_amt) > 0 Then Sum(bt_amt+sgst_amt+cgst_amt+igst_amt) <= 250000 Else 1=1 End
                        )
                        Select a.doc, count(a.*) as inv_count, 
                            Sum(Case When a.bt_amt < 0 Then -b.bt_amt Else b.bt_amt End) as bt_amt_tot, 
                            Sum(Case When a.bt_amt < 0 Then -b.sgst_amt Else b.sgst_amt End) as sgst_amt_tot,
                            Sum(Case When a.bt_amt < 0 Then -b.cgst_amt Else b.cgst_amt End) as cgst_amt_tot, 
                            Sum(Case When a.bt_amt < 0 Then -b.igst_amt Else b.igst_amt End) as igst_amt_tot, 
                            Sum(Case When a.bt_amt < 0 Then -(b.bt_amt+b.sgst_amt+b.cgst_amt+b.igst_amt) Else b.bt_amt+b.sgst_amt+b.cgst_amt+b.igst_amt End) as inv_amt_tot
                        From inv_list a
                        Inner Join gst_tran b On a.voucher_id = b.voucher_id
                        Where a.status = 5
                            And a.vat_type_id in (301, 302)
                            And length(a.customer_gstin) = 2
                        Group by a.doc, a.vat_type_id
                        Order by a.doc";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $cmm->addParam('pinv_types', "{" . $this->getInvoiceTypeList() . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

    public function getB2CS_detail(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With inv_list 
                        As
                        (    " . implode("\nUnion All\n", $this->docList) .
                "Union All -- All unregistered pos sales returns for pos invoices during the period
                                Select 'POS Sale Returns'::Varchar as doc, doc_date, inv_id as voucher_id, vat_type_id, status, 
                                    (annex_info->'gst_output_info'->>'cust_state_id')::BigInt as customer_state_id,
                                    Case When cust_tin = 'N.A.' Then (annex_info->'gst_output_info'->>'cust_state_id')::Varchar Else cust_tin End as customer_gstin, 
                                    -item_amt_tot as bt_amt, -tax_amt_tot as tax_amt, -inv_amt as total_amt
                                From pos.inv_control 
                                Where vat_type_id Between 300 and 399
                                    And doc_date between :pfrom_date And :pto_date
                                    --And (annex_info->>'origin_inv_date')::Date between :pfrom_date And :pto_date
                                    And branch_id = Any(:pbranch_ids::BigInt[])
                                    And doc_type = Any('{PIR}'::Varchar[])
                              Union All -- All unregistered Stock sale returns for Stock invoices during the period
                                Select 'Stock Sale Returns'::Varchar as doc, doc_date, stock_id as voucher_id, vat_type_id, status, 
                                    (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                                    (annex_info->'gst_output_info'->>'customer_gstin')::Varchar as customer_gstin, 
                                    -(annex_info->>'items_total_amt')::Numeric as bt_amt, -tax_amt, -total_amt
                                From st.stock_control 
                                Where vat_type_id Between 300 and 399
                                    And doc_date between :pfrom_date And :pto_date
                                    --And (annex_info->>'origin_inv_date')::Date between :pfrom_date And :pto_date
                                    And branch_id = Any(:pbranch_ids::BigInt[])
                                    And doc_type = Any('{SRV}'::Varchar[]) 
                              Union All -- All unregistered Other sale returns for invoices during the period
                                Select 'Other Sale Returns'::Varchar as doc, doc_date, voucher_id, (annex_info->'gst_output_info'->>'vat_type_id')::BigInt, status, 
                                    (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                                    (annex_info->'gst_output_info'->>'customer_gstin')::Varchar as customer_gstin, 
                                    -(annex_info->>'items_total_amt')::Numeric as bt_amt, -(annex_info->>'tax_amt')::Numeric, -debit_amt
                                From ar.rcpt_control 
                                Where (annex_info->'gst_output_info'->>'vat_type_id')::BigInt Between 300 and 399
                                    And doc_date between :pfrom_date And :pto_date
                                    --And (annex_info->>'origin_inv_date')::Date between :pfrom_date And :pto_date
                                    And branch_id = Any(:pbranch_ids::BigInt[])
                                    And doc_type = Any('{CN2}'::Varchar[]) 
                        ),
                        gst_tran
                        As
                        (   Select voucher_id, gst_rate_id, sgst_pcnt+cgst_pcnt+igst_pcnt as gst_pcnt, 
                                Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                                Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt,
                                Sum(cess_amt) as cess_amt
                            From tx.gst_tax_tran
                            Where sgst_pcnt+cgst_pcnt+igst_pcnt != 0
                            Group by voucher_id, gst_rate_id, sgst_pcnt+cgst_pcnt+igst_pcnt
                            Having Case When Sum(igst_amt) > 0 Then Sum(bt_amt+sgst_amt+cgst_amt+igst_amt) <= 250000 Else 1=1 End
                        )
                        Select a.customer_state_id, a.vat_type_id, b.gst_rate_id, b.gst_pcnt, 
                            Sum(Case When a.bt_amt < 0 Then -b.bt_amt Else b.bt_amt End) as bt_amt_tot, 
                            Sum(Case When a.bt_amt < 0 Then -b.sgst_amt Else b.sgst_amt End) as sgst_amt_tot, 
                            Sum(Case When a.bt_amt < 0 Then -b.cgst_amt Else b.cgst_amt End) as cgst_amt_tot, 
                            Sum(Case When a.bt_amt < 0 Then -b.igst_amt Else b.igst_amt End) as igst_amt_tot, 
                            Sum(Case When a.bt_amt < 0 Then -b.cess_amt Else b.cess_amt End) as cess_amt_tot
                        From inv_list a
                        Inner Join gst_tran b On a.voucher_id = b.voucher_id
                        Where a.status = 5
                            And a.vat_type_id in (301, 302)
                            And length(a.customer_gstin) = 2
                        Group by a.customer_state_id, a.vat_type_id, b.gst_rate_id, b.gst_pcnt
                        Order by a.customer_state_id, a.vat_type_id, b.gst_rate_id, b.gst_pcnt";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $cmm->addParam('pinv_types', "{" . $this->getInvoiceTypeList() . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

    public function getB2CS_raw_detail(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With inv_list 
                        As
                        (    " . implode("\nUnion All\n", $this->docList) .
                "Union All -- All unregistered pos sales returns for pos invoices during the period
                                Select 'POS Sale Returns'::Varchar as doc, doc_date, inv_id as voucher_id, vat_type_id, status, 
                                    (annex_info->'gst_output_info'->>'cust_state_id')::BigInt as customer_state_id,
                                    Case When cust_tin = 'N.A.' Then (annex_info->'gst_output_info'->>'cust_state_id')::Varchar Else cust_tin End as customer_gstin, 
                                    -item_amt_tot as bt_amt, -tax_amt_tot as tax_amt, -inv_amt as total_amt
                                From pos.inv_control 
                                Where vat_type_id Between 300 and 399
                                    And doc_date between :pfrom_date And :pto_date
                                    --And (annex_info->>'origin_inv_date')::Date between :pfrom_date And :pto_date
                                    And branch_id = Any(:pbranch_ids::BigInt[])
                                    And doc_type = Any('{PIR}'::Varchar[])
                              Union All -- All unregistered Stock sale returns for Stock invoices during the period
                                Select 'Stock Sale Returns'::Varchar as doc, doc_date, stock_id as voucher_id, vat_type_id, status, 
                                    (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                                    (annex_info->'gst_output_info'->>'customer_gstin')::Varchar as customer_gstin, 
                                    -(annex_info->>'items_total_amt')::Numeric as bt_amt, -tax_amt, -total_amt
                                From st.stock_control 
                                Where vat_type_id Between 300 and 399
                                    And doc_date between :pfrom_date And :pto_date
                                    --And (annex_info->>'origin_inv_date')::Date between :pfrom_date And :pto_date
                                    And branch_id = Any(:pbranch_ids::BigInt[])
                                    And doc_type = Any('{SRV}'::Varchar[]) 
                              Union All -- All unregistered Other sale returns for invoices during the period
                                Select 'Other Sale Returns'::Varchar as doc, doc_date, voucher_id, (annex_info->'gst_output_info'->>'vat_type_id')::BigInt, status, 
                                    (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                                    (annex_info->'gst_output_info'->>'customer_gstin')::Varchar as customer_gstin, 
                                    -(annex_info->>'items_total_amt')::Numeric as bt_amt, -(annex_info->>'tax_amt')::Numeric, -debit_amt
                                From ar.rcpt_control 
                                Where (annex_info->'gst_output_info'->>'vat_type_id')::BigInt Between 300 and 399
                                    And doc_date between :pfrom_date And :pto_date
                                    --And (annex_info->>'origin_inv_date')::Date between :pfrom_date And :pto_date
                                    And branch_id = Any(:pbranch_ids::BigInt[])
                                    And doc_type = Any('{CN2}'::Varchar[]) 
                        ),
                        gst_tran
                        As
                        (   Select voucher_id, gst_rate_id, sgst_pcnt+cgst_pcnt+igst_pcnt as gst_pcnt, 
                                Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                                Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt,
                                Sum(cess_amt) as cess_amt
                            From tx.gst_tax_tran
                            Where sgst_pcnt+cgst_pcnt+igst_pcnt != 0
                            Group by voucher_id, gst_rate_id, sgst_pcnt+cgst_pcnt+igst_pcnt
                            Having Case When Sum(igst_amt) > 0 Then Sum(bt_amt+sgst_amt+cgst_amt+igst_amt) <= 250000 Else 1=1 End
                        )
                        Select a.doc_date, a.voucher_id, a.customer_state_id, a.vat_type_id, b.gst_rate_id, b.gst_pcnt, 
                            Sum(Case When a.bt_amt < 0 Then -b.bt_amt Else b.bt_amt End) as bt_amt, 
                            Sum(Case When a.bt_amt < 0 Then -b.sgst_amt Else b.sgst_amt End) as sgst_amt, 
                            Sum(Case When a.bt_amt < 0 Then -b.cgst_amt Else b.cgst_amt End) as cgst_amt, 
                            Sum(Case When a.bt_amt < 0 Then -b.igst_amt Else b.igst_amt End) as igst_amt, 
                            Sum(Case When a.bt_amt < 0 Then -b.cess_amt Else b.cess_amt End) as cess_amt
                        From inv_list a
                        Inner Join gst_tran b On a.voucher_id = b.voucher_id
                        Where a.status = 5
                            And a.vat_type_id in (301, 302)
                            And length(a.customer_gstin) = 2
                        Group by a.doc_date, a.voucher_id, a.customer_state_id, a.vat_type_id, b.gst_rate_id, b.gst_pcnt
                        Order by a.doc_date, a.voucher_id, a.customer_state_id, a.vat_type_id, b.gst_rate_id, b.gst_pcnt";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $cmm->addParam('pinv_types', "{" . $this->getInvoiceTypeList() . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

    public function getEXP_ex_summary(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With inv_list 
                        As
                        (    " . implode("\nUnion All\n", $this->docList) . "),
                        gst_tran
                        As
                        (   Select voucher_id, Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                                Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt
                            From tx.gst_tax_tran
                            Group by voucher_id
                        )
                        Select a.doc, count(a.*) as inv_count, Sum(b.bt_amt) as bt_amt_tot, Sum(b.sgst_amt) as sgst_amt_tot,
                            Sum(b.cgst_amt) as cgst_amt_tot, Sum(b.igst_amt) as igst_amt_tot, 
                            Sum(b.bt_amt+b.sgst_amt+b.cgst_amt+b.igst_amt) as inv_amt_tot
                        From inv_list a
                        Inner Join gst_tran b On a.voucher_id = b.voucher_id
                        Where a.status = 5
                            And a.vat_type_id in (306, 307)
                        Group by a.doc, a.vat_type_id
                        Order by a.doc";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $cmm->addParam('pinv_types', "{" . $this->getInvoiceTypeList() . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

    public function getEXP_ex_detail(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With inv_list 
                        As
                        (    " . implode("\nUnion All\n", $this->docList) . "),
                        gst_tran
                        As
                        (   Select voucher_id, gst_rate_id, sgst_pcnt+cgst_pcnt+igst_pcnt as gst_pcnt, 
                                Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                                Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt,
                                Sum(cess_amt) as cess_amt
                            From tx.gst_tax_tran
                            Group by voucher_id, gst_rate_id, sgst_pcnt+cgst_pcnt+igst_pcnt
                        )
                        Select a.doc, a.doc_date, a.voucher_id, a.total_amt as inv_amt, a.vat_type_id,
                            row_number() Over (Partition By a.voucher_id) as sl_no, b.gst_rate_id, b.gst_pcnt, 
                            b.bt_amt, b.sgst_amt, b.cgst_amt, b.igst_amt, b.cess_amt
                        From inv_list a
                        Inner Join gst_tran b On a.voucher_id = b.voucher_id
                        Where a.status = 5
                            And a.vat_type_id in (306, 307)
                        Order by a.doc, a.doc_date, a.voucher_id";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $cmm->addParam('pinv_types', "{" . $this->getInvoiceTypeList() . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

    public function getEXP_sez_summary(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With inv_list 
                        As
                        (    " . implode("\nUnion All\n", $this->docList) . "),
                        gst_tran
                        As
                        (   Select voucher_id, Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                                Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt
                            From tx.gst_tax_tran
                            Group by voucher_id
                        )
                        Select a.doc, count(a.*) as inv_count, Sum(b.bt_amt) as bt_amt_tot, Sum(b.sgst_amt) as sgst_amt_tot,
                            Sum(b.cgst_amt) as cgst_amt_tot, Sum(b.igst_amt) as igst_amt_tot, 
                            Sum(b.bt_amt+b.sgst_amt+b.cgst_amt+b.igst_amt) as inv_amt_tot
                        From inv_list a
                        Inner Join gst_tran b On a.voucher_id = b.voucher_id
                        Where a.status = 5
                            And a.vat_type_id in (303, 304, 305)
                        Group by a.doc, a.vat_type_id
                        Order by a.doc";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $cmm->addParam('pinv_types', "{" . $this->getInvoiceTypeList() . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

    public function getEXEMP_summary(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With inv_list 
                        As
                        (    " . implode("\nUnion All\n", $this->docList) .
                            "Union All -- All unregistered pos sales returns for pos invoices during the period
                                Select 'POS Sale Returns'::Varchar as doc, doc_date, inv_id as voucher_id, vat_type_id, status, 
                                    (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                                    Case When cust_tin = 'N.A.' Then (annex_info->'gst_output_info'->>'cust_state_id')::Varchar Else cust_tin End as customer_gstin, 
                                    -item_amt_tot as bt_amt, -tax_amt_tot as tax_amt, -inv_amt as total_amt
                                From pos.inv_control 
                                Where vat_type_id Between 300 and 399
                                    And doc_date between :pfrom_date And :pto_date
                                    --And (annex_info->>'origin_inv_date')::Date between :pfrom_date And :pto_date
                                    And branch_id = Any(:pbranch_ids::BigInt[])
                                    And doc_type = Any('{PIR}'::Varchar[])
                              Union All -- All unregistered Stock sale returns for Stock invoices during the period
                                Select 'Stock Sale Returns'::Varchar as doc, doc_date, stock_id as voucher_id, vat_type_id, status, 
                                    (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                                    (annex_info->'gst_output_info'->>'customer_gstin')::Varchar as customer_gstin, 
                                    -(annex_info->>'items_total_amt')::Numeric as bt_amt, -tax_amt, -total_amt
                                From st.stock_control 
                                Where vat_type_id Between 300 and 399
                                    And doc_date between :pfrom_date And :pto_date
                                    --And (annex_info->>'origin_inv_date')::Date between :pfrom_date And :pto_date
                                    And branch_id = Any(:pbranch_ids::BigInt[])
                                    And doc_type = Any('{SRV}'::Varchar[])
                              Union All -- All unregistered Other sale returns for invoices during the period
                                Select 'Other Sale Returns'::Varchar as doc, doc_date, voucher_id, (annex_info->'gst_output_info'->>'vat_type_id')::BigInt, status, 
                                    (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                                    (annex_info->'gst_output_info'->>'customer_gstin')::Varchar as customer_gstin, 
                                    -(annex_info->>'items_total_amt')::Numeric as bt_amt, -(annex_info->>'tax_amt')::Numeric, -debit_amt
                                From ar.rcpt_control 
                                Where (annex_info->'gst_output_info'->>'vat_type_id')::BigInt Between 300 and 399
                                    And doc_date between :pfrom_date And :pto_date
                                    --And (annex_info->>'origin_inv_date')::Date between :pfrom_date And :pto_date
                                    And branch_id = Any(:pbranch_ids::BigInt[])
                                    And doc_type = Any('{CN2}'::Varchar[]) 
                        ),
                        gst_tran
                        As
                        (   Select voucher_id, hsn_sc_code, Sum(cgst_pcnt+sgst_pcnt+igst_pcnt) as gst_pcnt, Sum(bt_amt) as bt_amt
                            From tx.gst_tax_tran
                            Where sgst_pcnt+cgst_pcnt+igst_pcnt = 0
                            Group by voucher_id, hsn_sc_code
                        ),
                        inv_items
                        As
                        (   Select Case a.vat_type_id When 301 Then 'Local Supply' Else 'Inter-State Supply' End as supply_type, 
                                Case When length(a.customer_gstin) > 2 Then 'Registered Person' Else 'Unregistered Person' End as gstin_status,
                                Case When b.hsn_sc_code In ('00', '9900') Then 
                                        Case When left(a.voucher_id, 3) in ('SRV', 'PIR', 'CN2') Then -b.bt_amt Else b.bt_amt End 
                                    Else 0 End as nil_amt,
                                Case When b.hsn_sc_code Not In ('00', '9900') Then 
                                        Case When left(a.voucher_id, 3) in ('SRV', 'PIR', 'CN2') Then -b.bt_amt Else b.bt_amt End
                                    Else 0 End as exempt_amt
                            From inv_list a
                            Inner Join gst_tran b On a.voucher_id = b.voucher_id
                            Where a.status = 5
                                And a.vat_type_id in (301, 302)
                        )
                        Select a.supply_type, a.gstin_status, Sum(nil_amt) as nil_amt_tot, Sum(exempt_amt) as exempt_amt_tot
                        From inv_items a
                        Group by a.supply_type, a.gstin_status";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $cmm->addParam('pinv_types', "{" . $this->getInvoiceTypeList() . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    public function getEXEMP_raw_detail(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With inv_list 
                        As
                        (    " . implode("\nUnion All\n", $this->docList) .
                            "Union All -- All unregistered pos sales returns for pos invoices during the period
                                Select 'POS Sale Returns'::Varchar as doc, doc_date, inv_id as voucher_id, vat_type_id, status, 
                                    (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                                    Case When cust_tin = 'N.A.' Then (annex_info->'gst_output_info'->>'cust_state_id')::Varchar Else cust_tin End as customer_gstin, 
                                    -item_amt_tot as bt_amt, -tax_amt_tot as tax_amt, -inv_amt as total_amt
                                From pos.inv_control 
                                Where vat_type_id Between 300 and 399
                                    And doc_date between :pfrom_date And :pto_date
                                    --And (annex_info->>'origin_inv_date')::Date between :pfrom_date And :pto_date
                                    And branch_id = Any(:pbranch_ids::BigInt[])
                                    And doc_type = Any('{PIR}'::Varchar[])
                              Union All -- All unregistered Stock sale returns for Stock invoices during the period
                                Select 'Stock Sale Returns'::Varchar as doc, doc_date, stock_id as voucher_id, vat_type_id, status, 
                                    (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                                    (annex_info->'gst_output_info'->>'customer_gstin')::Varchar as customer_gstin, 
                                    -(annex_info->>'items_total_amt')::Numeric as bt_amt, -tax_amt, -total_amt
                                From st.stock_control 
                                Where vat_type_id Between 300 and 399
                                    And doc_date between :pfrom_date And :pto_date
                                    --And (annex_info->>'origin_inv_date')::Date between :pfrom_date And :pto_date
                                    And branch_id = Any(:pbranch_ids::BigInt[])
                                    And doc_type = Any('{SRV}'::Varchar[]) 
                              Union All -- All unregistered Other sale returns for invoices during the period
                                Select 'Other Sale Returns'::Varchar as doc, doc_date, voucher_id, (annex_info->'gst_output_info'->>'vat_type_id')::BigInt, status, 
                                    (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                                    (annex_info->'gst_output_info'->>'customer_gstin')::Varchar as customer_gstin, 
                                    -(annex_info->>'items_total_amt')::Numeric as bt_amt, -(annex_info->>'tax_amt')::Numeric, -debit_amt
                                From ar.rcpt_control 
                                Where (annex_info->'gst_output_info'->>'vat_type_id')::BigInt Between 300 and 399
                                    And doc_date between :pfrom_date And :pto_date
                                    --And (annex_info->>'origin_inv_date')::Date between :pfrom_date And :pto_date
                                    And branch_id = Any(:pbranch_ids::BigInt[])
                                    And doc_type = Any('{CN2}'::Varchar[]) 
                        ),
                        gst_tran
                        As
                        (   Select voucher_id, hsn_sc_code, Sum(cgst_pcnt+sgst_pcnt+igst_pcnt) as gst_pcnt, Sum(bt_amt) as bt_amt,
                                Sum(sgst_amt+cgst_amt+igst_amt) tax_amt
                            From tx.gst_tax_tran
                            Where sgst_pcnt+cgst_pcnt+igst_pcnt = 0
                            Group by voucher_id, hsn_sc_code
                        ),
                        inv_items
                        As
                        (   Select a.doc_date, a.voucher_id, Case a.vat_type_id When 301 Then 'Local Supply' Else 'Inter-State Supply' End as supply_type, 
                                a.customer_gstin,
                                Case When b.hsn_sc_code Not In ('00', '9900') Then 
                                        Case When left(a.voucher_id, 3) in ('SRV', 'PIR', 'CN2') Then -b.bt_amt Else b.bt_amt End 
                                    Else 0 End as nil_amt,
                                Case When b.hsn_sc_code In ('00', '9900') Then 
                                        Case When left(a.voucher_id, 3) in ('SRV', 'PIR', 'CN2') Then -b.bt_amt Else b.bt_amt End
                                    Else 0 End as exempt_amt
                            From inv_list a
                            Inner Join gst_tran b On a.voucher_id = b.voucher_id
                            Where a.status = 5
                                And a.vat_type_id in (301, 302)
                        )
                        Select a.*
                        From inv_items a
                        Order By a.doc_date, a.voucher_id";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $cmm->addParam('pinv_types', "{" . $this->getInvoiceTypeList() . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

    /* This is in relation to outward supplies only
     * Therefore, we include only Sales Returns here
     */

    public function getCDNR_summary(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $sql = "With inv_list 
                As
                (   Select 'Sale Returns'::Varchar as doc, doc_date, stock_id as voucher_id, vat_type_id, status, 
                        annex_info->'gst_output_info'->>'customer_state_id' as customer_state_id,
                        annex_info->'gst_output_info'->>'customer_gstin' as customer_gstin, 
                        before_tax_amt as bt_amt, tax_amt, total_amt,
                        annex_info->>'origin_inv_id' as origin_inv_id,
                        annex_info->>'origin_inv_date' as origin_inv_date
                    From st.stock_control 
                    Where vat_type_id Between 300 and 399
                        And doc_date between :pfrom_date And :pto_date
                        And branch_id = Any(:pbranch_ids::BigInt[])
                        And doc_type = Any('{SRV}'::Varchar[])
                    Union All
                    Select 'POS Sale Returns'::Varchar as doc, doc_date, inv_id as voucher_id, vat_type_id, status, 
                        annex_info->'gst_output_info'->>'customer_state_id' as customer_state_id,
                        Case When cust_tin = 'N.A.' Then (annex_info->'gst_output_info'->>'cust_state_id')::Varchar Else cust_tin End as customer_gstin, 
                        item_amt_tot as bt_amt, tax_amt_tot as tax_amt, inv_amt as total_amt,
                        annex_info->>'origin_inv_id' as origin_inv_id,
                        annex_info->>'origin_inv_date' as origin_inv_date
                    From pos.inv_control 
                    Where vat_type_id Between 300 and 399
                        And doc_date between :pfrom_date And :pto_date
                        And branch_id = Any(:pbranch_ids::BigInt[])
                        And doc_type = Any('{PIR}'::Varchar[])
                    Union All -- All unregistered Other sale returns for invoices during the period
                    Select 'Other Sale Returns'::Varchar as doc, doc_date, voucher_id, (annex_info->'gst_output_info'->>'vat_type_id')::BigInt, status, 
                        annex_info->'gst_output_info'->>'customer_state_id' as customer_state_id,
                        annex_info->'gst_output_info'->>'customer_gstin' as customer_gstin, 
                        (annex_info->>'items_total_amt')::Numeric as bt_amt, (annex_info->>'tax_amt')::Numeric, debit_amt,
                        annex_info->>'origin_inv_id' as origin_inv_id,
                        annex_info->>'origin_inv_date' as origin_inv_date
                    From ar.rcpt_control 
                    Where (annex_info->'gst_output_info'->>'vat_type_id')::BigInt Between 300 and 399
                        And doc_date between :pfrom_date And :pto_date
                        And branch_id = Any(:pbranch_ids::BigInt[])
                        And doc_type = Any('{CN2}'::Varchar[])
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
                From inv_list a
                Inner Join gst_tran b On a.voucher_id = b.voucher_id
                Where a.status = 5
                    And length(a.customer_gstin) > 2
                    And a.vat_type_id In (301, 302)
                    -- Filter required for Interstate more than 2.5L
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

    /* This is in relation to outward supplies only
     * Therefore, we include only Sales Returns here
     */

    public function getCDNR_detail(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $sql = "With inv_list 
                As
                (   Select 'Sale Returns'::Varchar as doc, doc_date, stock_id as voucher_id, vat_type_id, status, 
                        annex_info->'gst_output_info'->>'customer_state_id' as customer_state_id,
                        annex_info->'gst_output_info'->>'customer_gstin' as customer_gstin, 
                        before_tax_amt as bt_amt, tax_amt, total_amt,
                        annex_info->>'origin_inv_id' as origin_inv_id,
                        annex_info->>'origin_inv_date' as origin_inv_date
                    From st.stock_control 
                    Where vat_type_id Between 300 and 399
                        And doc_date between :pfrom_date And :pto_date
                        And branch_id = Any(:pbranch_ids::BigInt[])
                        And doc_type = Any('{SRV}'::Varchar[])
                    Union All
                    Select 'POS Sale Returns'::Varchar as doc, doc_date, inv_id as voucher_id, vat_type_id, status, 
                        annex_info->'gst_output_info'->>'customer_state_id' as customer_state_id,
                        Case When cust_tin = 'N.A.' Then (annex_info->'gst_output_info'->>'cust_state_id')::Varchar Else cust_tin End as customer_gstin, 
                        item_amt_tot as bt_amt, tax_amt_tot as tax_amt, inv_amt as total_amt,
                        annex_info->>'origin_inv_id' as origin_inv_id,
                        annex_info->>'origin_inv_date' as origin_inv_date
                    From pos.inv_control 
                    Where vat_type_id Between 300 and 399
                        And doc_date between :pfrom_date And :pto_date
                        And branch_id = Any(:pbranch_ids::BigInt[])
                        And doc_type = Any('{PIR}'::Varchar[])
                    Union All -- All unregistered Other sale returns for invoices during the period
                    Select 'Other Sale Returns'::Varchar as doc, doc_date, voucher_id, (annex_info->'gst_output_info'->>'vat_type_id')::BigInt, status, 
                        annex_info->'gst_output_info'->>'customer_state_id' as customer_state_id,
                        (annex_info->'gst_output_info'->>'customer_gstin')::Varchar as customer_gstin, 
                        (annex_info->>'items_total_amt')::Numeric as bt_amt, (annex_info->>'tax_amt')::Numeric, debit_amt,
                        annex_info->>'origin_inv_id' as origin_inv_id,
                        annex_info->>'origin_inv_date' as origin_inv_date
                      From ar.rcpt_control 
                      Where (annex_info->'gst_output_info'->>'vat_type_id')::BigInt Between 300 and 399
                          And doc_date between :pfrom_date And :pto_date
                          And branch_id = Any(:pbranch_ids::BigInt[])
                          And doc_type = Any('{CN2}'::Varchar[])
                ),
                gst_tran
                As
                (   Select voucher_id, gst_rate_id, sgst_pcnt+cgst_pcnt+igst_pcnt as gst_pcnt, 
                        Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                        Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt,
                        Sum(cess_amt) as cess_amt
                    From tx.gst_tax_tran
                    Where sgst_pcnt+cgst_pcnt+igst_pcnt != 0
                    Group by voucher_id, gst_rate_id, sgst_pcnt+cgst_pcnt+igst_pcnt
                )
                Select a.doc, a.doc_date, a.voucher_id, a.customer_gstin, a.customer_state_id, a.origin_inv_id, a.origin_inv_date, 
                    a.total_amt as inv_amt, a.vat_type_id,
                    row_number() Over (Partition By a.voucher_id) as sl_no, b.gst_rate_id, b.gst_pcnt, 
                    b.bt_amt, b.sgst_amt, b.cgst_amt, b.igst_amt, b.cess_amt
                From inv_list a
                Inner Join gst_tran b On a.voucher_id = b.voucher_id
                Where a.status = 5
                    And length(a.customer_gstin) > 2
                    And a.vat_type_id In (301, 302)
                Order by a.doc, a.doc_date, a.voucher_id, sl_no";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($sql);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

    /* This is in relation to outward supplies only
     * Therefore, we include only Sales Returns here
     */

    public function getCDNUR_summary(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $sql =  '';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'invoice_control'");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows())>0) {
        $sql = " With inv_list 
                As
                (   Select 'Sale Returns'::Varchar as doc, doc_date, stock_id as voucher_id, vat_type_id, status, 
                        annex_info->'gst_output_info'->>'customer_state_id' as customer_state_id,
                        annex_info->'gst_output_info'->>'customer_gstin' as customer_gstin, 
                        before_tax_amt as bt_amt, tax_amt, total_amt,
                        annex_info->>'origin_inv_id' as origin_inv_id,
                        annex_info->>'origin_inv_date' as origin_inv_date
                     From st.stock_control 
                     Where doc_date between :pfrom_date And :pto_date
                        And (annex_info->>'origin_inv_date')::Date < :pfrom_date
                        And branch_id = Any(:pbranch_ids::BigInt[])
                        And doc_type = Any('{SRV}'::Varchar[])
                        And length(annex_info->'gst_output_info'->>'customer_gstin') = 2
                        And vat_type_id in (301, 302)
                        And annex_info->>'origin_inv_id' In (Select s.stock_id From st.stock_control s Where s.total_amt > 250000 And s.vat_type_id = 302)
                    Union All 
                    Select 'Other Sale Returns'::Varchar as doc, doc_date, voucher_id, (annex_info->'gst_output_info'->>'vat_type_id')::BigInt, status, 
                        annex_info->'gst_output_info'->>'customer_state_id' as customer_state_id,
                        annex_info->'gst_output_info'->>'customer_gstin' as customer_gstin, 
                        (annex_info->>'items_total_amt')::Numeric as bt_amt, (annex_info->>'tax_amt')::Numeric, debit_amt,
                        annex_info->>'origin_inv_id' as origin_inv_id,
                        annex_info->>'origin_inv_date' as origin_inv_date
                    From ar.rcpt_control 
                    Where doc_date between :pfrom_date And :pto_date
                        And (annex_info->>'origin_inv_date')::Date < :pfrom_date
                        And branch_id = Any(:pbranch_ids::BigInt[])
                        And doc_type = Any('{CN2}'::Varchar[]) 
                        And length(annex_info->'gst_output_info'->>'customer_gstin') = 2
                        And (annex_info->'gst_output_info'->>'vat_type_id')::BigInt in (301, 302)
                        And annex_info->>'origin_inv_id' In (Select s.invoice_id From ar.invoice_control s Where s.invoice_amt > 250000 And s.vat_type_id = 302
                                                                Union All
                                                                Select s.voucher_id from pub.invoice_control s where s.gross_amt > 250000 and s.vat_type_id = 302)
                ),
                gst_tran
                As
                (   Select voucher_id, Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                        Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt
                    From tx.gst_tax_tran
                    Where sgst_pcnt+cgst_pcnt+igst_pcnt = 0
                    Group by voucher_id
                )Select a.doc, a.doc_date, a.voucher_id, a.origin_inv_id, a.origin_inv_date, 
                    Sum(b.bt_amt) as bt_amt_tot, Sum(b.sgst_amt) as sgst_amt_tot,
                    Sum(b.cgst_amt) as cgst_amt_tot, Sum(b.igst_amt) as igst_amt_tot, 
                    Sum(b.bt_amt+b.sgst_amt+b.cgst_amt+b.igst_amt) as inv_amt_tot
                From inv_list a
                Inner Join gst_tran b On a.voucher_id = b.voucher_id
                Where a.status = 5
                Group by a.doc, a.doc_date, a.voucher_id, a.origin_inv_id, a.origin_inv_date
                Order by a.doc, a.doc_date, a.voucher_id";

        }
        else{
        $sql = " With inv_list 
                As
                (   Select 'Sale Returns'::Varchar as doc, doc_date, stock_id as voucher_id, vat_type_id, status, 
                        annex_info->'gst_output_info'->>'customer_state_id' as customer_state_id,
                        annex_info->'gst_output_info'->>'customer_gstin' as customer_gstin, 
                        before_tax_amt as bt_amt, tax_amt, total_amt,
                        annex_info->>'origin_inv_id' as origin_inv_id,
                        annex_info->>'origin_inv_date' as origin_inv_date
                     From st.stock_control 
                     Where doc_date between :pfrom_date And :pto_date
                        And (annex_info->>'origin_inv_date')::Date < :pfrom_date
                        And branch_id = Any(:pbranch_ids::BigInt[])
                        And doc_type = Any('{SRV}'::Varchar[])
                        And length(annex_info->'gst_output_info'->>'customer_gstin') = 2
                        And vat_type_id in (301, 302)
                        And annex_info->>'origin_inv_id' In (Select s.stock_id From st.stock_control s Where s.total_amt > 250000 And s.vat_type_id = 302)
                    Union All 
                    Select 'Other Sale Returns'::Varchar as doc, doc_date, voucher_id, (annex_info->'gst_output_info'->>'vat_type_id')::BigInt, status, 
                        annex_info->'gst_output_info'->>'customer_state_id' as customer_state_id,
                        annex_info->'gst_output_info'->>'customer_gstin' as customer_gstin, 
                        (annex_info->>'items_total_amt')::Numeric as bt_amt, (annex_info->>'tax_amt')::Numeric, debit_amt,
                        annex_info->>'origin_inv_id' as origin_inv_id,
                        annex_info->>'origin_inv_date' as origin_inv_date
                    From ar.rcpt_control 
                    Where doc_date between :pfrom_date And :pto_date
                        And (annex_info->>'origin_inv_date')::Date < :pfrom_date
                        And branch_id = Any(:pbranch_ids::BigInt[])
                        And doc_type = Any('{CN2}'::Varchar[]) 
                        And length(annex_info->'gst_output_info'->>'customer_gstin') = 2
                        And (annex_info->'gst_output_info'->>'vat_type_id')::BigInt in (301, 302)
                        And annex_info->>'origin_inv_id' In (Select s.invoice_id From ar.invoice_control s Where s.invoice_amt > 250000 And s.vat_type_id = 302)
                ),
                gst_tran
                As
                (   Select voucher_id, Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                        Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt
                    From tx.gst_tax_tran
                    Where sgst_pcnt+cgst_pcnt+igst_pcnt = 0
                    Group by voucher_id
                )Select a.doc, a.doc_date, a.voucher_id, a.origin_inv_id, a.origin_inv_date, 
                    Sum(b.bt_amt) as bt_amt_tot, Sum(b.sgst_amt) as sgst_amt_tot,
                    Sum(b.cgst_amt) as cgst_amt_tot, Sum(b.igst_amt) as igst_amt_tot, 
                    Sum(b.bt_amt+b.sgst_amt+b.cgst_amt+b.igst_amt) as inv_amt_tot
                From inv_list a
                Inner Join gst_tran b On a.voucher_id = b.voucher_id
                Where a.status = 5
                Group by a.doc, a.doc_date, a.voucher_id, a.origin_inv_id, a.origin_inv_date
                Order by a.doc, a.doc_date, a.voucher_id";
        }
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($sql);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

    /* These are Advances received during the period but outstanding at the end of the period
     */

    public function getAT_summary(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $sql = "With adv_list 
                As
                (   SELECT a.doc_date, a.voucher_id, -a.balance as bt_amt,  
                        Case (b.annex_info->'gst_output_info'->>'vat_type_id')::BigInt When 301 Then 'Local Supply' Else 'Interstate Supply' End as supply_type,
                        c.gst_state_code, c.state_name
                    FROM ar.fn_stmt_of_ac_br_report(:pcompany_id, 0, 0, :pto_date, 2) a
                    Inner Join ar.rcpt_control b On a.voucher_id = b.voucher_id
                    Inner Join tx.gst_state c On (b.annex_info->'gst_output_info'->>'customer_state_id')::BigInt = c.gst_state_id
                    Where a.doc_date between :pfrom_date And :pto_date
                        And a.branch_id = Any(:pbranch_ids::BigInt[])
                ),
                gst_tran
                As
                (   Select voucher_id, gst_rate_id, Sum(sgst_pcnt+cgst_pcnt+igst_pcnt) as gst_pcnt,
                        Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                        Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt
                    From tx.gst_tax_tran
                    Where (sgst_amt+cgst_amt+igst_amt)>0
                    Group by voucher_id, gst_rate_id 
                ),
                adv_tax_tran
                As
                (   Select a.supply_type, a.doc_date, a.voucher_id, a.gst_state_code, a.state_name,
                        b.gst_rate_id, b.gst_pcnt,
                        Sum(a.bt_amt) as bt_amt_tot, 
                        Sum(b.sgst_amt * a.bt_amt / (b.bt_amt + b.sgst_amt + b.cgst_amt + b.igst_amt))::Numeric(18,2) as sgst_amt_tot,
                        Sum(b.cgst_amt * a.bt_amt / (b.bt_amt + b.sgst_amt + b.cgst_amt + b.igst_amt))::Numeric(18,2) as cgst_amt_tot,
                        Sum(b.igst_amt * a.bt_amt / (b.bt_amt + b.sgst_amt + b.cgst_amt + b.igst_amt))::Numeric(18,2) as igst_amt_tot,
                        Sum(b.bt_amt+b.sgst_amt+b.cgst_amt+b.igst_amt) as inv_amt_tot
                    From adv_list a
                    Inner Join gst_tran b On a.voucher_id = b.voucher_id
                    Group by a.supply_type, a.doc_date, a.voucher_id, a.gst_state_code, a.state_name, b.gst_rate_id, b.gst_pcnt
                )
                Select a.supply_type, a.doc_date, a.voucher_id, a.gst_state_code, a.state_name,
                        a.gst_rate_id, a.gst_pcnt,
                        a.bt_amt_tot - (a.sgst_amt_tot + a.cgst_amt_tot + a.igst_amt_tot) as bt_amt_tot, 
                        a.sgst_amt_tot, a.cgst_amt_tot, a.igst_amt_tot, a.inv_amt_tot
                From adv_tax_tran a
                Order by a.supply_type, a.doc_date, a.voucher_id";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($sql);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    public function getAT_detail(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $sql = "With adv_list 
                As
                (   SELECT a.doc_date, a.voucher_id, -a.balance as bt_amt,
                        Case (b.annex_info->'gst_output_info'->>'vat_type_id')::BigInt When 301 Then 'Local Supply' Else 'Interstate Supply' End as supply_type,
                        c.gst_state_id, c.gst_state_code, c.state_name
                    FROM ar.fn_stmt_of_ac_br_report(:pcompany_id, 0, 0, :pto_date, 2) a
                    Inner Join ar.rcpt_control b On a.voucher_id = b.voucher_id
                    Inner Join tx.gst_state c On (b.annex_info->'gst_output_info'->>'customer_state_id')::BigInt = c.gst_state_id
                    Where a.doc_date between :pfrom_date And :pto_date
                        And a.branch_id = Any(:pbranch_ids::BigInt[])
                ),
                gst_tran
                As
                (   Select voucher_id, gst_rate_id, sgst_pcnt+cgst_pcnt+igst_pcnt as gst_pcnt,
                        Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                        Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt
                    From tx.gst_tax_tran
                    Where (sgst_amt+cgst_amt+igst_amt)>0
                    Group by voucher_id, gst_rate_id, sgst_pcnt+cgst_pcnt+igst_pcnt
                ),
                adv_tax_tran
                As
                (   Select a.supply_type, a.gst_state_id, a.gst_state_code, a.state_name, a.doc_date, a.voucher_id, 
                        b.gst_rate_id, b.gst_pcnt,
                        Sum(a.bt_amt) as bt_amt_tot, 
                        Sum(b.sgst_amt * a.bt_amt / (b.bt_amt + b.sgst_amt + b.cgst_amt + b.igst_amt))::Numeric(18,2) as sgst_amt_tot,
                        Sum(b.cgst_amt * a.bt_amt / (b.bt_amt + b.sgst_amt + b.cgst_amt + b.igst_amt))::Numeric(18,2) as cgst_amt_tot,
                        Sum(b.igst_amt * a.bt_amt / (b.bt_amt + b.sgst_amt + b.cgst_amt + b.igst_amt))::Numeric(18,2) as igst_amt_tot,
                        Sum(b.bt_amt+b.sgst_amt+b.cgst_amt+b.igst_amt) as inv_amt_tot
                    From adv_list a
                    Inner Join gst_tran b On a.voucher_id = b.voucher_id
                    Group by a.supply_type, a.gst_state_id, a.doc_date, a.voucher_id, a.gst_state_code, a.state_name, b.gst_rate_id, b.gst_pcnt
                )
                Select a.gst_state_id, a.gst_state_code, a.gst_rate_id, a.gst_pcnt,
                        Sum(a.bt_amt_tot - (a.sgst_amt_tot + a.cgst_amt_tot + a.igst_amt_tot)) as bt_amt_tot, 
                        Sum(a.sgst_amt_tot) as sgst_amt_tot, 
                        Sum(a.cgst_amt_tot) as cgst_amt_tot, 
                        Sum(a.igst_amt_tot) as igst_amt_tot, 
                        Sum(a.inv_amt_tot) as inv_amt_tot
                From adv_tax_tran a
                Group by a.gst_state_id, a.gst_state_code, a.gst_rate_id, a.gst_pcnt
                Order by a.gst_state_id, a.gst_state_code, a.gst_rate_id, a.gst_pcnt";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($sql);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

    public function getATADJ_summary(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $sql = "With adv_list 
                As
                (   SELECT a.doc_date, a.voucher_id, -a.balance as bt_amt,  
                        Case (b.annex_info->'gst_output_info'->>'vat_type_id')::BigInt When 301 Then 'Local Supply' Else 'Interstate Supply' End as supply_type,
                        c.gst_state_code, c.state_name
                    FROM ar.fn_stmt_of_ac_br_report(:pcompany_id, 0, 0, :pprev_mon_to_date, 2) a
                    Inner Join ar.rcpt_control b On a.voucher_id = b.voucher_id
                    Inner Join tx.gst_state c On (b.annex_info->'gst_output_info'->>'customer_state_id')::BigInt = c.gst_state_id
                    Where a.doc_date between '2017-07-01' And :pprev_mon_to_date
                        And a.branch_id = Any(:pbranch_ids::BigInt[])
                ),
                gst_tran
                As
                (   Select voucher_id, gst_rate_id, Sum(sgst_pcnt+cgst_pcnt+igst_pcnt) as gst_pcnt,
                        Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                        Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt
                    From tx.gst_tax_tran
                    Where (sgst_amt+cgst_amt+igst_amt)>0
                    Group by voucher_id, gst_rate_id 
                ),
                adv_tax_tran
                As
                (   Select a.supply_type, a.doc_date, a.voucher_id, a.gst_state_code, a.state_name,
                        b.gst_rate_id, b.gst_pcnt,
                        Sum(a.bt_amt) as bt_amt_tot, 
                        Sum(b.sgst_amt * a.bt_amt / (b.bt_amt + b.sgst_amt + b.cgst_amt + b.igst_amt))::Numeric(18,2) as sgst_amt_tot,
                        Sum(b.cgst_amt * a.bt_amt / (b.bt_amt + b.sgst_amt + b.cgst_amt + b.igst_amt))::Numeric(18,2) as cgst_amt_tot,
                        Sum(b.igst_amt * a.bt_amt / (b.bt_amt + b.sgst_amt + b.cgst_amt + b.igst_amt))::Numeric(18,2) as igst_amt_tot
                    From adv_list a
                    Inner Join gst_tran b On a.voucher_id = b.voucher_id
                    Group by a.supply_type, a.doc_date, a.voucher_id, a.gst_state_code, a.state_name, b.gst_rate_id, b.gst_pcnt
                ),
                settl_tran
                As
                (   Select b.voucher_id, b.doc_date, Sum(a.debit_amt) as settl_amt
                    From ac.rl_pl_alloc a
                    Inner Join ac.rl_pl b On a.rl_pl_id = b.rl_pl_id
                    Where a.doc_date Between :pfrom_date And :pto_date
                        And a.branch_id = Any(:pbranch_ids::BigInt[])
                        And Left(b.voucher_id, 3) = 'ACR'
                    Group by b.voucher_id, b.doc_date
                )
                Select a.supply_type, a.doc_date, a.voucher_id, a.gst_state_code, a.state_name,
                        a.gst_rate_id, a.gst_pcnt, 
                        a.bt_amt_tot, a.sgst_amt_tot, a.cgst_amt_tot, a.igst_amt_tot,
                        b.settl_amt - ((a.sgst_amt_tot + a.cgst_amt_tot + a.igst_amt_tot) * b.settl_amt / a.bt_amt_tot)::Numeric(18,2) as bt_settl_amt,
                        (a.sgst_amt_tot * b.settl_amt / a.bt_amt_tot)::Numeric(18,2) as sgst_settl_amt, 
                        (a.cgst_amt_tot * b.settl_amt / a.bt_amt_tot)::Numeric(18,2) as cgst_settl_amt, 
                        (a.igst_amt_tot * b.settl_amt / a.bt_amt_tot)::Numeric(18,2) as igst_settl_amt, 
                        b.settl_amt as inv_settl_amt
                From adv_tax_tran a
                Inner Join settl_tran b On a.voucher_id = b.voucher_id
                Order by a.supply_type, a.doc_date, a.voucher_id";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($sql);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pprev_mon_to_date', date_sub(new \DateTime($option->ret_period_from), new \DateInterval('P1D'))->format('Y-m-d'));
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        
        return $dt;
    }

    public function getATADJ_Detail(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $sql = "With adv_list 
                As
                (   SELECT a.doc_date, a.voucher_id, -a.balance as bt_amt,  
                        Case (b.annex_info->'gst_output_info'->>'vat_type_id')::BigInt When 301 Then 'Local Supply' Else 'Interstate Supply' End as supply_type,
                        c.gst_state_code, c.state_name, c.gst_state_id
                    FROM ar.fn_stmt_of_ac_br_report(:pcompany_id, 0, 0, :pprev_mon_to_date, 2) a
                    Inner Join ar.rcpt_control b On a.voucher_id = b.voucher_id
                    Inner Join tx.gst_state c On (b.annex_info->'gst_output_info'->>'customer_state_id')::BigInt = c.gst_state_id
                    Where a.doc_date between '2017-07-01' And :pprev_mon_to_date
                        And a.branch_id = Any(:pbranch_ids::BigInt[])
                ),
                gst_tran
                As
                (   Select voucher_id, gst_rate_id, Sum(sgst_pcnt+cgst_pcnt+igst_pcnt) as gst_pcnt,
                        Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                        Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt
                    From tx.gst_tax_tran
                    Where (sgst_amt+cgst_amt+igst_amt)>0
                    Group by voucher_id, gst_rate_id 
                ),
                adv_tax_tran
                As
                (   Select a.supply_type, a.doc_date, a.voucher_id, a.gst_state_id, a.gst_state_code, a.state_name,
                        b.gst_rate_id, b.gst_pcnt,
                        Sum(a.bt_amt) as bt_amt_tot, 
                        Sum(b.sgst_amt * a.bt_amt / (b.bt_amt + b.sgst_amt + b.cgst_amt + b.igst_amt))::Numeric(18,2) as sgst_amt_tot,
                        Sum(b.cgst_amt * a.bt_amt / (b.bt_amt + b.sgst_amt + b.cgst_amt + b.igst_amt))::Numeric(18,2) as cgst_amt_tot,
                        Sum(b.igst_amt * a.bt_amt / (b.bt_amt + b.sgst_amt + b.cgst_amt + b.igst_amt))::Numeric(18,2) as igst_amt_tot
                    From adv_list a
                    Inner Join gst_tran b On a.voucher_id = b.voucher_id
                    Group by a.supply_type, a.doc_date, a.voucher_id, a.gst_state_id, a.gst_state_code, a.state_name, b.gst_rate_id, b.gst_pcnt
                ),
                settl_tran
                As
                (   Select b.voucher_id, b.doc_date, Sum(a.debit_amt) as settl_amt
                    From ac.rl_pl_alloc a
                    Inner Join ac.rl_pl b On a.rl_pl_id = b.rl_pl_id
                    Where a.doc_date Between :pfrom_date And :pto_date
                        And a.branch_id = Any(:pbranch_ids::BigInt[])
                        And Left(b.voucher_id, 3) = 'ACR'
                    Group by b.voucher_id, b.doc_date
                )
                Select a.gst_state_id, a.gst_state_code, a.gst_rate_id, a.gst_pcnt,
                        Sum(b.settl_amt - ((a.sgst_amt_tot + a.cgst_amt_tot + a.igst_amt_tot) * b.settl_amt / a.bt_amt_tot)::Numeric(18,2)) as bt_settl_amt,
                        Sum((a.sgst_amt_tot * b.settl_amt / a.bt_amt_tot)::Numeric(18,2)) as sgst_settl_amt, 
                        Sum((a.cgst_amt_tot * b.settl_amt / a.bt_amt_tot)::Numeric(18,2)) as cgst_settl_amt, 
                        Sum((a.igst_amt_tot * b.settl_amt / a.bt_amt_tot)::Numeric(18,2)) as igst_settl_amt, 
                        Sum(b.settl_amt) as inv_settl_amt
                From adv_tax_tran a
                Inner Join settl_tran b On a.voucher_id = b.voucher_id
                Group by a.gst_state_id, a.gst_state_code, a.gst_rate_id, a.gst_pcnt
                Order by a.gst_state_id, a.gst_state_code, a.gst_rate_id, a.gst_pcnt";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($sql);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pprev_mon_to_date', date_sub(new \DateTime($option->ret_period_from), new \DateInterval('P1D'))->format('Y-m-d'));
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        
        return $dt;
    }
    
    public function getHSN_summary(\app\core\tx\gstr1\Gstr1ProviderOption $option): \app\cwf\vsla\data\DataTable {
        $invSumSQL = "With inv_list 
                        As
                        (    " . implode("\nUnion All\n", $this->docList) .
                "Union All
                            Select 'SaleReturn'::Varchar as doc, doc_date, stock_id as voucher_id, vat_type_id, status, 
                                (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                                (annex_info->'gst_output_info'->>'customer_gstin')::Varchar as customer_gstin, 
                                before_tax_amt as bt_amt, tax_amt, total_amt
                            From st.stock_control 
                            Where doc_date between :pfrom_date And :pto_date
                                And branch_id = Any(:pbranch_ids::BigInt[])
                                And doc_type = Any('{SRV}'::Varchar[])
                            Union All
                            Select 'SaleReturn'::Varchar as doc, doc_date, inv_id as voucher_id, vat_type_id, status, 
                                (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                                Case When cust_tin = 'N.A.' Then (annex_info->'gst_output_info'->>'cust_state_id')::Varchar Else cust_tin End as customer_gstin, 
                                item_amt_tot as bt_amt, tax_amt_tot as tax_amt, inv_amt as total_amt
                            From pos.inv_control 
                            Where doc_date between :pfrom_date And :pto_date
                                And branch_id = Any(:pbranch_ids::BigInt[])
                                And doc_type = Any('{PIR}'::Varchar[])
                            Union All
                            Select 'SaleReturn'::Varchar as doc, doc_date, voucher_id, (annex_info->'gst_output_info'->>'vat_type_id')::BigInt, status, 
                                (annex_info->'gst_output_info'->>'customer_state_id')::BigInt as customer_state_id,
                                (annex_info->'gst_output_info'->>'customer_gstin')::Varchar as customer_gstin, 
                                (annex_info->>'items_total_amt')::Numeric as bt_amt, (annex_info->>'tax_amt')::Numeric, debit_amt
                            From ar.rcpt_control 
                            Where doc_date between :pfrom_date And :pto_date
                                And branch_id = Any(:pbranch_ids::BigInt[])
                                And doc_type = Any('{CN2}'::Varchar[]) 
                        ),
                        gst_tran
                        As
                        (   Select voucher_id, hsn_sc_code, Sum(hsn_qty) as hsn_qty, Sum(bt_amt) as bt_amt, Sum(sgst_amt) as sgst_amt,
                                Sum(cgst_amt) as cgst_amt, Sum(igst_amt) as igst_amt, Sum(cess_amt) as cess_amt
                            From tx.gst_tax_tran
                            Group by voucher_id, hsn_sc_code
                        ),
                        hsn_sc
                        As
                        (   Select a.hsn_sc_id, a.hsn_sc_code, c.uom_code
                            From tx.hsn_sc a
                            Inner Join tx.hsn_sc_rate b On a.hsn_sc_id = b.hsn_sc_id
                            Inner Join tx.hsn_sc_uom c On b.hsn_sc_uom_id = c.hsn_sc_uom_id
                        )
                        Select row_number() over(order by b.hsn_sc_code) as sl_no, b.hsn_sc_code, COALESCE(c.uom_code, 'NOS') as hsn_sc_uom,
                            Sum(b.hsn_qty)::Numeric(18,2) as hsn_qty_tot,
                            Sum(Case doc When 'SaleReturn' Then -b.bt_amt Else b.bt_amt End) as bt_amt_tot, 
                            Sum(Case doc When 'SaleReturn' Then -b.sgst_amt Else b.sgst_amt End) as sgst_amt_tot,
                            Sum(Case doc When 'SaleReturn' Then -b.cgst_amt Else b.cgst_amt End) as cgst_amt_tot, 
                            Sum(Case doc When 'SaleReturn' Then -b.igst_amt Else b.igst_amt End) as igst_amt_tot,
                            Sum(Case doc When 'SaleReturn' Then -b.cess_amt Else b.cess_amt End) as cess_amt_tot,
                            Sum(Case doc When 'SaleReturn' Then -(b.bt_amt+b.sgst_amt+b.cgst_amt+b.igst_amt+b.cess_amt) Else b.bt_amt+b.sgst_amt+b.cgst_amt+b.igst_amt+b.cess_amt End) as inv_amt_tot
                        From inv_list a
                        Inner Join gst_tran b On a.voucher_id = b.voucher_id
                        Left Join hsn_sc c On b.hsn_sc_code = c.hsn_sc_code
                        Where a.status = 5
                        Group by b.hsn_sc_code, c.uom_code
                        Order by sl_no";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $cmm->addParam('pinv_types', "{" . $this->getInvoiceTypeList() . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }

    /* Doc Num. Nature of document
     *  1    	Invoices for outward supply
     *  2	Invoices for inward supply from unregistered person 
     *  3	Revised Invoice
     *  4	Debit Note
     *  5	Credit Note
     *  6	Receipt voucher
     *  7	Payment Voucher
     *  8	Refund voucher
     *  9	Delivery Challan for job work
     *  10	Delivery Challan for supply on approval
     *  11	Delivery Challan in case of liquid gas
     *  12	Delivery Challan in cases other than by way of supply (excluding at S no. 9 to 11)
     */

    public function getDOC_count(\app\core\tx\gstr1\Gstr1ProviderOption $option): array {
        $result = [];
        // Outward Supplies
        $invSumSQL = "With inv_list 
                        As
                        (    " . implode("\nUnion All\n", $this->docList) . ")
                        Select substring(voucher_id, '[A-Z]+[0-9]+[A-Z]+') as doc, 
                            min(voucher_id) as doc_min, 
                            max(voucher_id) as doc_max, 
                            count(*) as doc_count,
                            0 as cancelled
                        From inv_list a
                        Where a.status = 5
                        Group by substring(voucher_id, '[A-Z]+[0-9]+[A-Z]+')
                        Order by substring(voucher_id, '[A-Z]+[0-9]+[A-Z]+')";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($invSumSQL);
        $cmm->addParam('pfrom_date', $option->ret_period_from);
        $cmm->addParam('pto_date', $option->ret_period_to);
        $cmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $cmm->addParam('pinv_types', "{" . $this->getInvoiceTypeList() . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $outList = new \stdClass();
        $outList->sl_no = 1;
        $outList->doc_type = 'Outward Supplies';
        $outList->doc_list = $dt->Rows();
        $result[] = $outList;

        // Self Invoice
        $siSQL = "With inv_list 
                        As
                        (    Select voucher_id, status 
                             From ac.vch_control
                             Where doc_date between :pfrom_date And :pto_date
                                And branch_id = Any(:pbranch_ids::BigInt[])
                                And doc_type = Any('{SIRC}'::Varchar[]) 
                        )
                        Select substring(voucher_id, '[A-Z]+[0-9]+[A-Z]+') as doc, 
                            min(voucher_id) as doc_min, 
                            max(voucher_id) as doc_max, 
                            count(*) as doc_count,
                            0 as cancelled 
                        From inv_list a
                        Where a.status = 5
                        Group by substring(voucher_id, '[A-Z]+[0-9]+[A-Z]+')
                        Order by substring(voucher_id, '[A-Z]+[0-9]+[A-Z]+')";
        $siCmm = new \app\cwf\vsla\data\SqlCommand();
        $siCmm->setCommandText($siSQL);
        $siCmm->addParam('pfrom_date', $option->ret_period_from);
        $siCmm->addParam('pto_date', $option->ret_period_to);
        $siCmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $dtSi = \app\cwf\vsla\data\DataConnect::getData($siCmm);
        $siList = new \stdClass();
        $siList->sl_no = 2;
        $siList->doc_type = 'Self Invoice/Inward Supplies';
        $siList->doc_list = $dtSi->Rows();
        $result[] = $siList;

        // Sales Returns
        $cdnSQL = "With inv_list 
                As
                (   Select 'Sale Returns'::Varchar as doc, doc_date, stock_id as voucher_id, vat_type_id, status, 
                        annex_info->'gst_output_info'->>'customer_state_id' as customer_state_id,
                        annex_info->'gst_output_info'->>'customer_gstin' as customer_gstin, 
                        before_tax_amt as bt_amt, tax_amt, total_amt,
                        annex_info->>'origin_inv_id' as origin_inv_id,
                        annex_info->>'origin_inv_date' as origin_inv_date
                    From st.stock_control 
                    Where vat_type_id Between 300 and 399
                        And doc_date between :pfrom_date And :pto_date
                        And branch_id = Any(:pbranch_ids::BigInt[])
                        And doc_type = Any('{SRV}'::Varchar[])
                    Union All
                    Select 'POS Sale Returns'::Varchar as doc, doc_date, inv_id as voucher_id, vat_type_id, status, 
                        annex_info->'gst_output_info'->>'customer_state_id' as customer_state_id,
                        Case When cust_tin = 'N.A.' Then (annex_info->'gst_output_info'->>'cust_state_id')::Varchar Else cust_tin End as customer_gstin, 
                        item_amt_tot as bt_amt, tax_amt_tot as tax_amt, inv_amt as total_amt,
                        annex_info->>'origin_inv_id' as origin_inv_id,
                        annex_info->>'origin_inv_date' as origin_inv_date
                    From pos.inv_control 
                    Where vat_type_id Between 300 and 399
                        And doc_date between :pfrom_date And :pto_date
                        And branch_id = Any(:pbranch_ids::BigInt[])
                        And doc_type = Any('{PIR}'::Varchar[])
                    Union All -- All unregistered Other sale returns for invoices during the period
                    Select 'Other Sale Returns'::Varchar as doc, doc_date, voucher_id, (annex_info->'gst_output_info'->>'vat_type_id')::BigInt, status, 
                        annex_info->'gst_output_info'->>'customer_state_id' as customer_state_id,
                        annex_info->'gst_output_info'->>'customer_gstin' as customer_gstin, 
                        (annex_info->>'items_total_amt')::Numeric as bt_amt, (annex_info->>'tax_amt')::Numeric, debit_amt,
                        annex_info->>'origin_inv_id' as origin_inv_id,
                        annex_info->>'origin_inv_date' as origin_inv_date
                    From ar.rcpt_control 
                    Where (annex_info->'gst_output_info'->>'vat_type_id')::BigInt Between 300 and 399
                        And doc_date between :pfrom_date And :pto_date
                        And branch_id = Any(:pbranch_ids::BigInt[])
                        And doc_type = Any('{CN2}'::Varchar[]) 
                )
                Select substring(voucher_id, '[A-Z]+[0-9]+[A-Z]+') as doc, 
                            min(voucher_id) as doc_min, 
                            max(voucher_id) as doc_max, 
                            count(*) as doc_count,
                    0 as cancelled
                        From inv_list a
                        Where a.status = 5
                        Group by substring(voucher_id, '[A-Z]+[0-9]+[A-Z]+')
                        Order by substring(voucher_id, '[A-Z]+[0-9]+[A-Z]+')";
        $cdnCmm = new \app\cwf\vsla\data\SqlCommand();
        $cdnCmm->setCommandText($cdnSQL);
        $cdnCmm->addParam('pfrom_date', $option->ret_period_from);
        $cdnCmm->addParam('pto_date', $option->ret_period_to);
        $cdnCmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $dtCdn = \app\cwf\vsla\data\DataConnect::getData($cdnCmm);
        $cdnList = new \stdClass();
        $cdnList->sl_no = 5;
        $cdnList->doc_type = 'Credit Note/Sale Returns';
        $cdnList->doc_list = $dtCdn->Rows();
        $result[] = $cdnList;

        // Receipt Vouchers
        $advSql = "Select substring(voucher_id, '[A-Z]+[0-9]+[A-Z]+') as doc, 
                    min(voucher_id) as doc_min, 
                    max(voucher_id) as doc_max, 
                    count(*) as doc_count,
                    0 as cancelled
                From ar.rcpt_control
                Where doc_date between :pfrom_date And :pto_date
                    And branch_id = Any(:pbranch_ids::BigInt[])
                    And doc_type = Any('{ACR}'::Varchar[])
                Group by substring(voucher_id, '[A-Z]+[0-9]+[A-Z]+')";
        $advCmm = new \app\cwf\vsla\data\SqlCommand();
        $advCmm->setCommandText($advSql);
        $advCmm->addParam('pfrom_date', $option->ret_period_from);
        $advCmm->addParam('pto_date', $option->ret_period_to);
        $advCmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $dtAdv = \app\cwf\vsla\data\DataConnect::getData($advCmm);
        $advList = new \stdClass();
        $advList->sl_no = 6;
        $advList->doc_type = 'Receipt Vouchers';
        $advList->doc_list = $dtAdv->Rows();
        $result[] = $advList;

        // Payment Vouchers
        $payvSql = "Select substring(voucher_id, '[A-Z]+[0-9]+[A-Z]+') as doc, 
                    min(voucher_id) as doc_min, 
                    max(voucher_id) as doc_max, 
                    count(*) as doc_count,
                    0 as cancelled
                From ac.vch_control
                Where doc_date between :pfrom_date And :pto_date
                    And branch_id = Any(:pbranch_ids::BigInt[])
                    And doc_type = Any('{PAYV,PAYB,PAYC}'::Varchar[])
                Group by substring(voucher_id, '[A-Z]+[0-9]+[A-Z]+')";
        $payvCmm = new \app\cwf\vsla\data\SqlCommand();
        $payvCmm->setCommandText($payvSql);
        $payvCmm->addParam('pfrom_date', $option->ret_period_from);
        $payvCmm->addParam('pto_date', $option->ret_period_to);
        $payvCmm->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
        $dtPayv = \app\cwf\vsla\data\DataConnect::getData($payvCmm);
        $payvList = new \stdClass();
        $payvList->sl_no = 7;
        $payvList->doc_type = 'Payment Vouchers';
        $payvList->doc_list = $dtPayv->Rows();
        $result[] = $payvList;

        // process deleted docs
        $del_docs = $this->getDeletedDocs($option);
        foreach ($del_docs as $deldoc => $deldocitem) {
            $ddl = $deldocitem->asArray('doc_type', ['voucher_id']);
            foreach ($result as $doc_list) {
                foreach ($doc_list->doc_list as &$dlr) {
                    foreach($ddl as $ddlr => $ddlr_vch) {
                        if (substr($dlr['doc'], 0, strlen($ddlr)) == $ddlr) {
                            $dlr['cancelled'] = count($ddlr_vch);
                            $max_vch = max($ddlr_vch);
                            if($max_vch['voucher_id'] > $dlr['doc_max']) {
                                $dlr['doc_max'] = $max_vch['voucher_id'];
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }

    private $branchInState;

    private function getBranchInState($gst_state_id): string {
        if ($this->branchInState == null) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select branch_id from sys.branch Where gst_state_id = :pgst_state_id And company_id = {company_id}");
            $cmm->addParam('pgst_state_id', $gst_state_id);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            $this->branchInState = implode(",", $dt->select('branch_id'));
        }
        return $this->branchInState;
    }

    private $invTypeList;

    private function getInvoiceTypeList(): string {
        if ($this->invTypeList == null) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select seq_type from ar.income_type");
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            $this->invTypeList = implode(",", $dt->select("seq_type"));
        }
        return $this->invTypeList;
    }

    private function getDeletedDocs(\app\core\tx\gstr1\Gstr1ProviderOption $option): array {
        $result = [];
        foreach ($this->audList as $audItem) {
            $cmmIS = new \app\cwf\vsla\data\SqlCommand();
            $cmmIS->setCommandText("Select * From information_schema.tables Where table_schema = 'aud' And table_name = :pbo_id");
            $cmmIS->addParam('pbo_id', strtolower($audItem));
            $dtIS = \app\cwf\vsla\data\DataConnect::getAuditData($cmmIS);
            if (count($dtIS->Rows()) > 0) {
                $sql = "Select substring(voucher_id, '[A-Z]+[0-9]+[A-Z]+') as doc_type, voucher_id 
                        From aud." . strtolower($audItem) . "
                        where ((json_log::jsonb->>'doc_date')::Date between :pfrom_date and :pto_date) 
                                And en_log_action = 4
                                And ((json_log::json)->>'branch_id')::BigInt = Any(:pbranch_ids::BigInt[]);";
                $cmmdel = new \app\cwf\vsla\data\SqlCommand();
                $cmmdel->setCommandText($sql);
                $cmmdel->addParam('pfrom_date', $option->ret_period_from);
                $cmmdel->addParam('pto_date', $option->ret_period_to);
                $cmmdel->addParam('pbranch_ids', "{" . $this->getBranchInState($option->gst_state_id) . "}");
                $dt_del = \app\cwf\vsla\data\DataConnect::getAuditData($cmmdel);
                $result[$audItem] = $dt_del;
            }
        }
        return $result;
    }

}

/* query for hsn_qty fix
 * ﻿Update tx.gst_tax_tran a
Set hsn_qty = b.issued_qty
--Select a.* From tx.gst_tax_tran a,
From st.stock_tran b,  st.stock_control c
Where a.gst_tax_tran_id = b.stock_tran_id
	And b.stock_id = c.stock_id
	And c.doc_type = 'SIV'
 */

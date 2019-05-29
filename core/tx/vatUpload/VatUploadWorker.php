<?php

namespace app\core\tx\vatUpload;

class VatUploadWorker {
    
    public function getXML_localPurchase(array $options) {
        $rawData = $this->getLocalPurchaseData($options);
        $outXml = VatXmlBuilder::getDef_localPurchase($options);
        // following contains source => outNode
        $nodeDef = [
            'supplier_tin' => 'SelTin',
            'supplier' => 'SelName',
            'bill_no' => 'InvNo',
            'bill_date' => 'InvDate',
            'net_val' => 'NetVal',
            'tax_amt' => 'TaxCh',
            'misc_amt' => 'OthCh',
            'purchase_amt' => 'TotCh'
        ];
        VatXmlBuilder::toElements($outXml, 'PurchaseInvoiceDetails', $nodeDef, $rawData);
        return $outXml;
    }
    
    public function getXML_interstatePurchase(array $options) {
        $rawData = $this->getInterStatePurchaseData($options);
        $outXml = VatXmlBuilder::getDef_interstatePurchase($options);
        // following contains source => outNode
        $nodeDef = [
            'supplier_tin' => 'SelTin',
            'supplier' => 'SelName',
            'supplier_address' => 'SelAddr',
            'bill_no' => 'InvNo',
            'bill_date' => 'InvDate',
            'net_val' => 'NetVal',
            'tax_amt' => 'TaxCh',
            'misc_amt' => 'OthCh',
            'purchase_amt' => 'TotCh',
            'vat_type_code' => 'TranType',
            'hsn_code' => 'MainComm',
            'hsn_sub_code' => 'SubComm',
            'received_qty' => 'Qty',
            'cst_pur' => 'Purpose',
        ];
        VatXmlBuilder::toElements($outXml, 'ISPurInv', $nodeDef, $rawData);
        return $outXml;
    }
    
    public function getXML_localSale(array $options) : \SimpleXMLElement {
        $rawData = $this->getLocalSaleData($options);
        $outXml = VatXmlBuilder::getDef_localSale($options);
        // following contains source => outNode
        $nodeDef = [
            'customer_tin' => 'PurTin',
            'customer' => 'PurName',
            'voucher_id' => 'InvNo',
            'doc_date' => 'InvDate',
            'net_val' => 'NetVal',
            'tax_amt' => 'TaxCh',
            'misc_amt' => 'OthCh',
            'sale_amt' => 'TotCh'
        ];
        VatXmlBuilder::toElements($outXml, 'SaleInvoiceDetails', $nodeDef, $rawData);
        return $outXml;
    }
    
    public function getXML_localSaleReturn(array $options) {
        $rawData = $this->getSaleReturnData($options);
        $outXml = VatXmlBuilder::getDef_localSaleReturn($options);
        // following contains source => outNode
        $nodeDef = [
            'voucher_id' => 'CreditNo',
            'doc_date' => 'CreditDate',
            'customer_tin' => 'PurTin',
            'customer' => 'PurName',
            'net_val' => 'NetVal',
            'tax_amt' => 'TaxCh',
            'misc_amt' => 'OthCh',
            'sale_amt' => 'TotCh',
            'origin_inv_id' => 'OrgInvNo',
            'origin_inv_date' => 'OrgInvDate'
        ];
        VatXmlBuilder::toElements($outXml, 'CreditInvoiceDetails', $nodeDef, $rawData);
        return $outXml;
    }
    
    public function getXML_interstateSale(array $options) {
        $rawData = $this->getInterStateSaleData($options);
        $outXml = VatXmlBuilder::getDef_interstateSale($options);
        // following contains source => outNode
        $nodeDef = [
            'customer_tin' => 'PurTin',
            'customer' => 'PurName',
            'customer_address' => 'PurAddr',
            'voucher_id' => 'InvNo',
            'doc_date' => 'InvDate',
            'net_val' => 'NetVal',
            'tax_amt' => 'TaxCh',
            'misc_amt' => 'OthCh',
            'sale_amt' => 'TotCh',
            'vat_type_code' => 'TranType',
            'hsn_code' => 'MainComm',
            'hsn_sub_code' => 'SubComm',
            'issued_qty' => 'Qty'
        ];
        VatXmlBuilder::toElements($outXml, 'ISSalesInv', $nodeDef, $rawData);
        return $outXml;
    }
    
    public function getXML_interstateSaleReturn(array $options) {
        $rawData = $this->getInterStateSaleReturnData($options);
        $outXml = VatXmlBuilder::getDef_interstateSaleReturn($options);
        // following contains source => outNode
        $nodeDef = [
            'voucher_id' => 'CreditNo',
            'doc_date' => 'CreditDate',
            'customer_tin' => 'PurTin',
            'customer' => 'PurName',
            'net_val' => 'NetVal',
            'tax_amt' => 'TaxCh',
            'misc_amt' => 'OthCh',
            'sale_amt' => 'TotCh',
            'origin_inv_id' => 'OrgInvNo',
            'origin_inv_date' => 'OrgInvDate'
        ];
        VatXmlBuilder::toElements($outXml, 'CreditInvoiceDetails', $nodeDef, $rawData);
        return $outXml;
    }
    
    private function getPurchaseData(array $options) : \app\cwf\vsla\data\DataTable {
        
    }
    
    private function getLocalSaleData(array $options) : \app\cwf\vsla\data\DataTable {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "Select Case When customer_tin is Null Or customer_tin = '' Then '29000000000' Else customer_tin End as customer_tin, 
                    left(regexp_replace(customer, '[^a-zA-Z ]', '', 'g'), 30) as customer, voucher_id, doc_date, 
                    Case When misc_amt < 0 Then (Sum(bt_amt) + misc_amt)::Numeric(18,2) Else Sum(bt_amt)::Numeric(18,2) End as net_val,
                    Sum(tax_amt)::Numeric(18,2) as tax_amt, 
                    Case When misc_amt < 0 Then 0.00 Else misc_amt::Numeric(18,2) End as misc_amt,
                    ((Sum(bt_amt) + misc_amt) + Sum(tax_amt))::Numeric(18,2) as sale_amt
                From st.fn_sale_register_v2(:pcompany_id, :pbranch_id, :pfrom_date, :pto_date)
                Where (vat_type_id = :pvat_type_id Or :pvat_type_id = 0)
                    And doc_type In ('PI', 'SI')
                Group by customer_tin, customer, voucher_id, doc_date, sale_amt, misc_amt
                Order by voucher_id;";
        $cmm->setCommandText($sql);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0); // Always generate consolidated
        $cmm->addParam('pfrom_date', $options['from_date']);
        $cmm->addParam('pto_date', $options['to_date']);
        $cmm->addParam('pvat_type_id', 101); // Local Sales Only
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }
    
    private function getSaleReturnData(array $options) : \app\cwf\vsla\data\DataTable {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "Select Case When customer_tin is Null Or customer_tin = '' Then '29000000000' Else customer_tin End as customer_tin, 
                    left(regexp_replace(customer, '[^a-zA-Z ]', '', 'g'), 30) as customer, voucher_id, doc_date, 
                    Case When misc_amt < 0 Then (Sum(bt_amt) + misc_amt)::Numeric(18,2) Else Sum(bt_amt)::Numeric(18,2) End as net_val,
                    Sum(tax_amt)::Numeric(18,2) as tax_amt, 
                    Case When misc_amt < 0 Then 0.00 Else misc_amt::Numeric(18,2) End as misc_amt,
                    sale_amt::Numeric(18,2), origin_inv_id, origin_inv_date
                From st.fn_sale_register_v2(:pcompany_id, :pbranch_id, :pfrom_date, :pto_date)
                Where (vat_type_id = :pvat_type_id)
                    And doc_type Not In ('PI', 'SI')
                Group by customer_tin, customer, voucher_id, doc_date, sale_amt, misc_amt, origin_inv_id, origin_inv_date
                Order by voucher_id;";
        $cmm->setCommandText($sql);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0); // Always generate consolidated
        $cmm->addParam('pfrom_date', $options['from_date']);
        $cmm->addParam('pto_date', $options['to_date']);
        $cmm->addParam('pvat_type_id', 101); // Local Sales Returns Only
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }
    
    private function getInterStateSaleData(array $options) : \app\cwf\vsla\data\DataTable {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "With inter_state_tran
                As
                (   Select Case When a.customer_tin is Null Or a.customer_tin = '' Then '29000000000' Else a.customer_tin End as customer_tin, 
                        left(regexp_replace(a.customer, '[^a-zA-Z ]', '', 'g'), 30) as customer, a.voucher_id, a.doc_date, 
                        Case When a.misc_amt < 0 Then (Sum(a.bt_amt) + a.misc_amt)::Numeric(18,2) Else Sum(a.bt_amt)::Numeric(18,2) End as net_val,
                        Sum(a.tax_amt)::Numeric(18,2) as tax_amt, 
                        Case When a.misc_amt < 0 Then 0.00 Else a.misc_amt::Numeric(18,2) End as misc_amt,
                        a.sale_amt::Numeric(18,2), a.vat_type_id, a.customer_id
                    From st.fn_sale_register_v2(:pcompany_id, :pbranch_id, :pfrom_date, :pto_date) a
                    Inner Join ar.customer b On a.customer_id = b.customer_id
                    Inner Join sys.address c On b.address_id = c.address_id
                    Where (a.vat_type_id != :pvat_type_id)
                        And a.doc_type In ('PI', 'SI')
                    Group by a.customer_tin, a.customer, a.voucher_id, a.doc_date, a.sale_amt, a.misc_amt, a.vat_type_id, a.customer_id
                ),
                stock_tran
                As
                (   Select a.stock_id, Coalesce(c.hsn_code, '') as hsn_code, '1' as hsn_sub_code, issued_qty
                    From st.stock_tran a
                    Inner Join st.material b On a.material_id = b.material_id
                    Left Join st.hsn c On (b.annex_info->'supp_info'->>'hsn_id')::BigInt = c.hsn_id
                    Where a.sl_no = 1
                )
                Select a.customer_tin, a.customer, a.voucher_id, a.doc_date, a.net_val, a.tax_amt, a.misc_amt, a.sale_amt,
                    b.vat_type_code, c.hsn_code, c.hsn_sub_code, c.issued_qty,
                    left(regexp_replace((e.address || ' ' || city), '[^a-zA-Z ]', '', 'g'), 150) as customer_address
                From inter_state_tran a
                Left Join tx.vat_type b On a.vat_type_id = b.vat_type_id
                Left Join stock_tran c On a.voucher_id = c.stock_id
                Inner Join ar.customer d On a.customer_id = d.customer_id
                Left Join sys.address e On d.address_id = e.address_id And e.address_type_id = ({company_id} * 1000000) + 3
                Order by a.doc_date, a.voucher_id;";
        $cmm->setCommandText($sql);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0); // Always generate consolidated
        $cmm->addParam('pfrom_date', $options['from_date']);
        $cmm->addParam('pto_date', $options['to_date']);
        $cmm->addParam('pvat_type_id', 101); // Inter State Sales Only (condition is Negative)
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }
    
    private function getInterStateSaleReturnData(array $options) : \app\cwf\vsla\data\DataTable {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "Select Case When customer_tin is Null Or customer_tin = '' Then '29000000000' Else customer_tin End as customer_tin, 
                    left(regexp_replace(customer, '[^a-zA-Z ]', '', 'g'), 30) as customer, voucher_id, doc_date, 
                    Case When misc_amt < 0 Then (Sum(bt_amt) + misc_amt)::Numeric(18,2) Else Sum(bt_amt)::Numeric(18,2) End as net_val,
                    Sum(tax_amt)::Numeric(18,2) as tax_amt, 
                    Case When misc_amt < 0 Then 0.00 Else misc_amt::Numeric(18,2) End as misc_amt,
                    sale_amt::Numeric(18,2), origin_inv_id, origin_inv_date
                From st.fn_sale_register_v2(:pcompany_id, :pbranch_id, :pfrom_date, :pto_date)
                Where (vat_type_id != :pvat_type_id)
                    And doc_type Not In ('PI', 'SI')
                Group by customer_tin, customer, voucher_id, doc_date, sale_amt, misc_amt, origin_inv_id, origin_inv_date
                Order by voucher_id;";
        $cmm->setCommandText($sql);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0); // Always generate consolidated
        $cmm->addParam('pfrom_date', $options['from_date']);
        $cmm->addParam('pto_date', $options['to_date']);
        $cmm->addParam('pvat_type_id', 101); // Inter State Sales Returns Only (Negative Condition)
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }
    
    private function getLocalPurchaseData(array $options) : \app\cwf\vsla\data\DataTable {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "With urd_lumpsum
                As
                (   Select '29999999999'::Varchar as supplier_tin, 
                        'LUMP SUM'::Varchar supplier, '1111'::Varchar as voucher_id, :pfrom_date::date as doc_date,
                        '1111'::Varchar as bill_no, :pfrom_date::date as bill_date,
                        Case When misc_amt < 0 Then (Sum(bt_amt) + misc_amt)::Numeric(18,2) Else Sum(bt_amt)::Numeric(18,2) End as net_val,
                        Sum(tax_amt)::Numeric(18,2) as tax_amt, 
                        Case When misc_amt < 0 Then 0.00 Else misc_amt::Numeric(18,2) End as misc_amt,
                        purchase_amt::Numeric(18,2)
                    From st.fn_purchase_register_v2(:pcompany_id, :pbranch_id, :pfrom_date, :pto_date)
                    Where (vat_type_id = 205)
                        And doc_type In ('SP', 'SI', 'PI')
                    Group by supplier_tin, supplier, voucher_id, doc_date, bill_no, bill_date, purchase_amt, misc_amt
                )
                Select Case When supplier_tin is Null Or supplier_tin = '' Then '29000000000' Else supplier_tin End as supplier_tin, 
                    left(regexp_replace(supplier, '[^a-zA-Z ]', '', 'g'), 30) as supplier, voucher_id, doc_date,
                    left(regexp_replace(bill_no, '[^a-zA-Z0-9]', '', 'g'), 25) as bill_no, bill_date,
                    Case When misc_amt < 0 Then (Sum(bt_amt) + misc_amt)::Numeric(18,2) Else Sum(bt_amt)::Numeric(18,2) End as net_val,
                    Sum(tax_amt)::Numeric(18,2) as tax_amt, 
                    Case When misc_amt < 0 Then 0.00 Else misc_amt::Numeric(18,2) End as misc_amt,
                    purchase_amt::Numeric(18,2)
                From st.fn_purchase_register_v2(:pcompany_id, :pbranch_id, :pfrom_date, :pto_date)
                Where (vat_type_id = :pvat_type_id)
                    And doc_type In ('SP')
                Group by supplier_tin, supplier, voucher_id, doc_date, bill_no, bill_date, purchase_amt, misc_amt
                Union All -- Union with URD Purchases as Lump Sum
                Select supplier_tin, supplier, voucher_id, doc_date, bill_no, bill_date,
                    Sum(net_val), 0.00, Sum(misc_amt), Sum(net_val + misc_amt)
                From urd_lumpsum
                Group by supplier_tin, supplier, voucher_id, doc_date, bill_no, bill_date;";
        $cmm->setCommandText($sql);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0); // Always generate consolidated
        $cmm->addParam('pfrom_date', $options['from_date']);
        $cmm->addParam('pto_date', $options['to_date']);
        $cmm->addParam('pvat_type_id', 201); // Local Purchases Only + URD Purchases (hard coded)
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }
    
    private function getInterStatePurchaseData(array $options) : \app\cwf\vsla\data\DataTable {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "With inter_state_tran
                As
                (   Select Case When supplier_tin is Null Or supplier_tin = '' Then '29000000000' Else supplier_tin End as supplier_tin, 
                    left(regexp_replace(supplier, '[^a-zA-Z ]', '', 'g'), 30) as supplier, voucher_id, doc_date,
                    left(regexp_replace(bill_no, '[^a-zA-Z0-9]', '', 'g'), 25) as bill_no, bill_date,
                    Case When misc_amt < 0 Then (Sum(bt_amt) + misc_amt)::Numeric(18,2) Else Sum(bt_amt)::Numeric(18,2) End as net_val,
                    Sum(tax_amt)::Numeric(18,2) as tax_amt, 
                    Case When misc_amt < 0 Then 0.00 Else misc_amt::Numeric(18,2) End as misc_amt,
                    purchase_amt::Numeric(18,2), vat_type_id, supplier_id
                From st.fn_purchase_register_v2(:pcompany_id, :pbranch_id, :pfrom_date, :pto_date)
                Where (vat_type_id != :pvat_type_id)
                    And doc_type In ('SP')
                Group by supplier_tin, supplier, voucher_id, doc_date, bill_no, bill_date, vat_type_id, supplier_id, purchase_amt, misc_amt
                Order by voucher_id
                ),
                stock_tran
                As
                (   Select a.stock_id, Coalesce(c.hsn_code, '') as hsn_code, '1' as hsn_sub_code, received_qty
                    From st.stock_tran a
                    Inner Join st.material b On a.material_id = b.material_id
                    Left Join st.hsn c On (b.annex_info->'supp_info'->>'hsn_id')::BigInt = c.hsn_id
                    Where a.sl_no = 1
                )
                Select a.supplier_tin, a.supplier, a.voucher_id, a.doc_date, a.bill_no, a.bill_date, a.net_val, a.tax_amt, a.misc_amt, a.purchase_amt,
                    b.vat_type_code, c.hsn_code, c.hsn_sub_code, c.received_qty,
                    left(regexp_replace((e.address || ' ' || city), '[^a-zA-Z ]', '', 'g'), 150) as supplier_address, 1 as cst_pur
                From inter_state_tran a
                Left Join tx.vat_type b On a.vat_type_id = b.vat_type_id
                Left Join stock_tran c On a.voucher_id = c.stock_id
                Inner Join ap.supplier d On a.supplier_id = d.supplier_id
                Left Join sys.address e On d.address_id = e.address_id And e.address_type_id = ({company_id} * 1000000) + 3
                Order by a.doc_date, a.voucher_id";
        $cmm->setCommandText($sql);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0); // Always generate consolidated
        $cmm->addParam('pfrom_date', $options['from_date']);
        $cmm->addParam('pto_date', $options['to_date']);
        $cmm->addParam('pvat_type_id', 201); // Interstate Purchases (Negative condition applied)
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }
}
<?php

namespace app\core\ap\controllers;

use app\cwf\vsla\base\WebFormController;

class FormController extends WebFormController {

    public function actionAdvancealloc($voucher_id, $doc_date, $account_id, $fc_type_id, $exch_rate, $dc) {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select *,' . $exch_rate . ' as exch_rate from ap.fn_payable_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc) '
                . ' where fc_type_id=:pfc_type_id Order By doc_date, voucher_id');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('paccount_id', $account_id);
        $cmm->addParam('pto_date', $doc_date);
        $cmm->addParam('pvoucher_id', $voucher_id);
        $cmm->addParam('pdc', $dc);
        $cmm->addParam('pfc_type_id', $fc_type_id);
        $dtPLBalance = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = array();
        $result['pl_balance'] = $dtPLBalance;
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionSelectbillinpymt($voucher_id, $account_id, $fc_type_id, $is_inter_branch, $doc_date) {
        $branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
        if ($is_inter_branch == 'true') {
            $branch_id = 0;
        }
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select a.rl_pl_id, a.account_id, b.account_head, a.voucher_id, a.doc_date, a.bill_no, c.branch_name, 
                                        case when a.bill_no = '' then '1970-01-01' else a.bill_date end as bill_date, 
                                        case when a.due_date <= :pto_date then a.balance else 0 end as over_due, case when a.due_date <= :pto_date then a.balance_fc else 0 end as over_due_fc, 
                                                    case when a.due_date > :pto_date then a.balance else 0 end as not_due, case when a.due_date > :pto_date then a.balance_fc else 0 end as not_due_fc,
                                        a.fc_type_id, a.fc_type, a.due_date, a.branch_id
                                from ap.fn_payable_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc) a
                                inner join ac.account_head b on a.account_id = b.account_id
                                inner join sys.branch c on a.branch_id = c.branch_id
                                where a.fc_type_id = :pfc_type_id
                                order by a.doc_date, c.branch_name, a.voucher_id");


        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', $branch_id);
        $cmm->addParam('paccount_id', $account_id);
        $cmm->addParam('pto_date', $doc_date);
        $cmm->addParam('pvoucher_id', $voucher_id);
        $cmm->addParam('pdc', 'C');
        $cmm->addParam('pfc_type_id', $fc_type_id);
        $dtBillBalance = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = array();
        $result['bill_balance'] = $dtBillBalance;
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionFetchSuppName($supplier_id, $doc_date = null) {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText(' Select supplier_name  from ap.supplier  where supplier_id=:psupplier_id');
        $cmm->addParam('psupplier_id', $supplier_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = ['supplier_name' => ''];
        if (count($dt->Rows()) > 0) {
            $result['supplier_name'] = $dt->Rows()[0]['supplier_name'];
        }
        $rate_info =  new \app\cwf\vsla\data\DataTable();
        $result['is_tds_applied'] = false;
        $supp_tds_info = \app\core\tds\worker\TDSWorker::SuppTDSInfo($supplier_id);
        if (count($supp_tds_info->Rows()) > 0) {
            if ($supp_tds_info->Rows()[0]['is_tds_applied']) {
                $result['is_tds_applied'] = TRUE;
                
                $rate_info = \app\core\tds\worker\TDSWorker::SuppTDSRateInfo($supp_tds_info->Rows()[0]['tds_person_type_id'], $supp_tds_info->Rows()[0]['tds_section_id'], $doc_date);
            }
            $result['person_type_id'] = $supp_tds_info->Rows()[0]['tds_person_type_id'];
            $result['person_type'] = $supp_tds_info->Rows()[0]['person_type'];
            $result['section_id'] = $supp_tds_info->Rows()[0]['tds_section_id'];
            $result['section'] = $supp_tds_info->Rows()[0]['section'];
            
        }
        
        $result['unstl_adv_amt'] = \app\core\ap\advanceAlloc\AdvanceAllocHelper::GetUnsettledAdvAmt($supplier_id, $doc_date);
        $result['rate_info'] = $rate_info;
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionFetchSuppAddr($supplier_id) {
        $dt = \app\core\ap\supplier\SupplierHelper::getSuppAddr($supplier_id);
        if (count($dt->Rows()) == 1) {
            return json_encode($dt->Rows()[0]);
        }
        return json_encode([]);
    }
    
    
    public function actionFetchSuppCust($supplier_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select a.customer_id, b.customer_name From ap.supp_cust a 
                                Inner Join ar.customer b on a.customer_id = b.customer_id
                                Where supplier_id=:psupplier_id');
        $cmm->addParam('psupplier_id', $supplier_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        
        if (count($dt->Rows()) == 1) {
            return json_encode($dt->Rows()[0]);
        }
        return json_encode([]);
    }   

    public function actionListSuppAddr($supplier_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "Select false as select, a.annex_info->'satutory_details'->>'gst_state_id' as gst_state_id,
                    c.gst_state_code || ' - ' || c.state_name as gst_state,
                    a.annex_info->'satutory_details'->>'gstin' as gstin,
                    (a.annex_info->'satutory_details'->>'is_ctp')::Boolean as is_ctp,
                    b.address || E'\n' || b.city || case when b.pin = '' then '' else ' - ' end  
                        || b.pin || case when b.state = '' then '' else E'\n' end  || b.state || case when b.country = '' then '' else E'\n' end || b.country as addr
                From ap.supplier a
                Inner Join sys.address b On a.address_id = b.address_id
                Inner Join tx.gst_state c On (a.annex_info->'satutory_details'->>'gst_state_id')::BigInt = c.gst_state_id
                Where a.supplier_id = :psupp_id
                Union All
                Select false, b->>'gst_state_id', 
                    c.gst_state_code || ' - ' || c.state_name as gst_state,
                    b->>'gstin',
                    (a.annex_info->'satutory_details'->>'is_ctp')::Boolean as is_ctp,
                    b->>'branch_addr'
                From ap.supplier a, jsonb_array_elements(a.annex_info->'branch_addrs') b
                Inner Join tx.gst_state c On (b->>'gst_state_id')::BigInt = c.gst_state_id
                Where a.supplier_id = :psupp_id";
        $cmm->setCommandText($sql);
        $cmm->addParam('psupp_id', $supplier_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            return json_encode($dt->Rows());
        }
        return json_encode([]);
    }

    public function actionGetHsnGstInfo($hsn_sc_id) {
        $dt = \app\core\tx\gstIN\HsnScHelper::GetGstHSNInfo($hsn_sc_id);
        if (count($dt->Rows()) > 0) {
            return $dt->Rows()[0]['gst_hsn_info'];
        }
        return json_encode([]);
    }

    public function actionGetBillForDn($origin_bill_id, $supplier_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "Select a.*, row_to_json(b.*) as gst_hsn_info 
                From ap.fn_bill_for_dn(:pbranch_id, :psupplier_id, :pfrom_date, :pto_date, :pvoucher_id) a
                Inner Join tx.gst_tax_tran b On a.bill_tran_id = b.gst_tax_tran_id
                Where (bill_id = :pvoucher_id or :pvoucher_id = '')
                order by doc_date, bill_id, bill_tran_id";
        $cmm->setCommandText($sql);
        $cmm->addParam('psupplier_id', $supplier_id);
        $cmm->addParam('pvoucher_id', $origin_bill_id);
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('pfrom_date', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
        $cmm->addParam('pto_date', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = array();
        $result['bill_tran'] = $dt;
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionFetchTdsRate($person_type_id, $section_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "Select base_rate_perc, ecess_perc, surcharge_perc from tds.rate
                Where section_id = :psection_id and person_type_id = :pperson_type_id";
        $cmm->setCommandText($sql);
        $cmm->addParam('psection_id', $section_id);
        $cmm->addParam('pperson_type_id', $person_type_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $base_rate = 0;
        $ecess = 0;
        $surcharge = 0;
        if (count($dt->Rows()) > 0) {
            $base_rate = $dt->Rows()[0]['base_rate_perc'];
            $ecess = $dt->Rows()[0]['ecess_perc'];
            $surcharge = $dt->Rows()[0]['surcharge_perc'];
        }
        $result = array();
        $result['base_rate_perc'] = $base_rate;
        $result['ecess_perc'] = $ecess;
        $result['surcharge_perc'] = $surcharge;
        $result['status'] = 'ok';
        return json_encode($result);
    }


    public function actionFetchSupp($supplier_code) {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText(' Select supplier_id, supplier_name  from ap.supplier  where supplier_code ilike (:psupplier_code)');
        $cmm->addParam('psupplier_code', $supplier_code);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = ['supplier_name' => '', 'supplier_id' => -1];
        if (count($dt->Rows()) > 0) {
            $result['supplier_name'] = $dt->Rows()[0]['supplier_name'];
            $result['supplier_id'] = $dt->Rows()[0]['supplier_id'];
        }
        $result['status'] = 'ok';
        return json_encode($result);
    }
    
    public function actionSelectbillforpaycycle($pay_cycle_id, $bank_account_id, $voucher_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
         
        $sql = " Select a.supplier_account_id, b.supplier, a.voucher_id, a.doc_date, a.credit_amt,
                    a.annex_info->>'is_bt' as is_bt, (b.annex_info->>'pay_cycle_id')::bigint as pay_cycle_id
                    from ap.pymt_control a
                    inner join ap.supplier b on a.supplier_account_id=b.supplier_id
                    where a.company_id=:pcompany_id and a.branch_id=:pbranch_id
                    and a.status =5 and (a.annex_info->>'is_bt')::boolean
                    and (b.annex_info->>'pay_cycle_id')::bigint = :ppay_cycle_id
                    and a.account_id = :pbank_account_id 
                    and doc_type='PYMT'
                    and a.voucher_id not in (select reference_id from ap.pymt_tran where voucher_id like 'BT%' and voucher_id != :pvoucher_id)
                    order by  b.supplier, a.doc_date, a.voucher_id";
        
        $cmm->setCommandText($sql);        
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('ppay_cycle_id', $pay_cycle_id);
        $cmm->addParam('pbank_account_id', $bank_account_id);
        $cmm->addParam('pvoucher_id', $voucher_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = array();
        $result['bill_tran'] = $dt;
        $result['status'] = 'ok';
        return json_encode($result);
    }
    
    public function actionSelectInvInRcpt($voucher_id, $branch_id, $account_id, $fc_type_id, $is_inter_branch, $to_date) {
        $branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
        if ($is_inter_branch == 'true') {
            $branch_id = 0;
        }
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select a.branch_id, a.rl_pl_id, a.account_id, b.account_head, a.voucher_id, a.doc_date, c.branch_name,
                                        case when a.due_date <= :pto_date then a.balance else 0 end as over_due, case when a.due_date <= :pto_date then a.balance_fc else 0 end as over_due_fc, 
                                                    case when a.due_date > :pto_date then a.balance else 0 end as not_due, case when a.due_date > :pto_date then a.balance_fc else 0 end as not_due_fc,
                                        a.fc_type_id, a.fc_type, a.due_date, a.is_opbl
                                from ar.fn_receivable_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc) a
                                inner join ac.account_head b on a.account_id = b.account_id
                                inner join sys.branch c on a.branch_id = c.branch_id
                                where a.fc_type_id = :pfc_type_id
                                order by a.doc_Date, c.branch_name, a.voucher_id');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', $branch_id);
        $cmm->addParam('paccount_id', $account_id);
        $cmm->addParam('pto_date', $to_date);
        $cmm->addParam('pvoucher_id', $voucher_id);
        $cmm->addParam('pdc', 'D');
        $cmm->addParam('pfc_type_id', $fc_type_id);
        $dtinvBalance = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = array();
        $result['inv_balance'] = $dtinvBalance->Rows();
        $result['status'] = 'ok';
        return json_encode($result);
    }
    
}

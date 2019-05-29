<?php

namespace app\core\ar\controllers;

use app\cwf\vsla\base\WebFormController;

class FormController extends WebFormController {

    public function actionFetchcustcreditlimit($customer_id, $voucher_id) {
        $credit_limit = 0;
        $credit_limit_type = '';
        $balance = 0;
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select credit_limit_type, credit_limit from ar.customer where customer_id = :pcustomer_id');
        $cmm->addParam('pcustomer_id', $customer_id);
        $dtcr = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtcr->Rows()) > 0) {
            $credit_limit = $dtcr->Rows()[0]['credit_limit'];
            $credit_limit_type = $dtcr->Rows()[0]['credit_limit_type'];
        }


        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select COALESCE(sum(order_amt), 0) as order_amt, COALESCE(sum(inv_amt), 0) as inv_amt
                                From ar.fn_ccl_spent(:pcompany_id, :pto_date, :pcustomer_id)');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pcustomer_id', $customer_id);
        $cmm->addParam('pto_date', date('Y-m-d'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        if (count($dt->Rows()) == 1) {
            $not_billed_amt = $dt->Rows()[0]['order_amt'];
            $billed_amt = $dt->Rows()[0]['inv_amt'];
        }

        $result = array();
        $result['billed_amt'] = $billed_amt;
        $result['not_billed_amt'] = $not_billed_amt;
        $result['credit_limit_type'] = $credit_limit_type;
        $result['credit_limit'] = $credit_limit;
        $result['balance_credit'] = $credit_limit - $billed_amt - $not_billed_amt;
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionFetchcustsalesman($customer_id) {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select a.salesman_id, b.salesman_name, c.email, c.mobile  
                from ar.customer a
                Inner Join ar.salesman b On a.salesman_id = b.salesman_id
                Inner Join sys.address c on b.address_id = c.address_id
                where customer_id = :pcustomer_id');
        $cmm->addParam('pcustomer_id', $customer_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = ['salesman_id' => -1, 'salesman_name' => '', 'email' => '', 'mobile' => ''];
        if (count($dt->Rows()) > 0) {
            $result['salesman_id'] = $dt->Rows()[0]['salesman_id'];
            $result['salesman_name'] = $dt->Rows()[0]['salesman_name'];
            $result['email'] = $dt->Rows()[0]['email'];
            $result['mobile'] = $dt->Rows()[0]['mobile'];
        }
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionAdvancealloc($voucher_id, $doc_date, $account_id, $fc_type_id, $exch_rate, $dc, $branch_id) {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select *,' . $exch_rate . ' as exch_rate from ar.fn_receivable_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc) '
                . ' where fc_type_id=:pfc_type_id order by doc_date, voucher_id');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', $branch_id);
        $cmm->addParam('paccount_id', $account_id);
        $cmm->addParam('pto_date', $doc_date);
        $cmm->addParam('pvoucher_id', $voucher_id);
        $cmm->addParam('pdc', $dc);
        $cmm->addParam('pfc_type_id', $fc_type_id);
        $dtRLBalance = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = array();
        $result['rl_balance'] = $dtRLBalance;
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionSelectInvInRcpt($voucher_id, $doc_date, $branch_id, $account_id, $fc_type_id) {
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
        $cmm->addParam('pto_date', $doc_date);
        $cmm->addParam('pvoucher_id', $voucher_id);
        $cmm->addParam('pdc', 'D');
        $cmm->addParam('pfc_type_id', $fc_type_id);
        $dtinvBalance = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = array();
        $result['inv_balance'] = $dtinvBalance->Rows();
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionSelectInvInRcptByRoute($voucher_id, $doc_date, $branch_id, $account_id, $fc_type_id, $route_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select a.branch_id, a.rl_pl_id, a.account_id, b.account_head, a.voucher_id, a.doc_date, c.branch_name,
                                        case when a.due_date <= :pto_date then a.balance else 0 end as over_due, case when a.due_date <= :pto_date then a.balance_fc else 0 end as over_due_fc, 
                                                    case when a.due_date > :pto_date then a.balance else 0 end as not_due, case when a.due_date > :pto_date then a.balance_fc else 0 end as not_due_fc,
                                        a.fc_type_id, a.fc_type, a.due_date, a.is_opbl
                                from ar.fn_receivable_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc) a
                                inner join ac.account_head b on a.account_id = b.account_id
                                inner join sys.branch c on a.branch_id = c.branch_id
                                left join sd.route_tran e on a.account_id = e.cust_id
                                where a.fc_type_id = :pfc_type_id
                                    And (e.route_id = :proute_id Or :proute_id = 0)
                                order by b.account_head, a.doc_Date, c.branch_name, a.voucher_id');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', $branch_id);
        $cmm->addParam('paccount_id', $account_id);
        $cmm->addParam('pto_date', $doc_date);
        $cmm->addParam('pvoucher_id', $voucher_id);
        $cmm->addParam('proute_id', $route_id);
        $cmm->addParam('pdc', 'D');
        $cmm->addParam('pfc_type_id', $fc_type_id);
        $dtinvBalance = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = array();
        $result['inv_balance'] = $dtinvBalance->Rows();
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionFetchcusttax($bo) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select tax_schedule_id from ar.customer where customer_id = :pcustomer_id');
        $cmm->addParam('pcustomer_id', $bo->customer_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $tax_schedule_id = -1;
        if (count($dt->Rows()) > 0) {
            $tax_schedule_id = $dt->Rows()[0]['$tax_schedule_id'];
        }
        $result = array();
        $result['salesman_id'] = $salesman_id;
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionFetchcustaddrcollect($customer_id) {
        $result = \app\core\ar\customer\CustomerHelper::FetchCustAddr($customer_id);
        return json_encode($result);
    }

    public function actionFetchCustAddr($customer_id) {
        $dt = \app\core\ar\customer\CustomerHelper::getCustAddr($customer_id);
        if (count($dt->Rows()) == 1) {
            return json_encode($dt->Rows()[0]);
        }
        return json_encode([]);
    }

    public function actionListCustAddr($customer_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "Select false as select, a.annex_info->'tax_info'->>'gst_state_id' as gst_state_id,
                    c.gst_state_code || ' - ' || c.state_name as gst_state,
                    a.annex_info->'tax_info'->>'gstin' as gstin,
                    b.address || E'\n' || b.city || case when b.pin = '' then '' else ' - ' end  
                        || b.pin || case when b.state = '' then '' else E'\n' end  || b.state || case when b.country = '' then '' else E'\n' end || b.country as addr,
                        b.city, b.pin
                From ar.customer a
                Inner Join sys.address b On a.address_id = b.address_id
                Inner Join tx.gst_state c On (a.annex_info->'tax_info'->>'gst_state_id')::BigInt = c.gst_state_id
                Where a.customer_id = :pcust_id
                Union All
                Select false, b->>'gst_state_id', 
                    c.gst_state_code || ' - ' || c.state_name as gst_state,
                    b->>'gstin',
                    b->>'ship_to',COALESCE(b->>'city', '') city, COALESCE(b->>'pin', '') pin
                From ar.customer a, jsonb_array_elements(a.annex_info->'ship_addrs') b
                Inner Join tx.gst_state c On (b->>'gst_state_id')::BigInt = c.gst_state_id
                Where a.customer_id = :pcust_id";
        $cmm->setCommandText($sql);
        $cmm->addParam('pcust_id', $customer_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            return json_encode($dt->Rows());
        }
        return json_encode([]);
    }

    public function actionGetIncomeTypeHsnGstInfo($account_id, $doc_type = '', $income_type_id = -1) {
        $dt = \app\core\ar\customer\CustomerHelper::GetIncomeTypeHsnGstInfo($account_id, $doc_type, $income_type_id);
        if (count($dt->Rows()) > 0) {
            // The gst_hsn info field contains a json object which can be return directly
            return $dt->Rows()[0]['gst_hsn_info'];
        }
        return json_encode([]);
    }

    public function actionGetInvForCn($origin_inv_id, $customer_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "Select *, row_to_json(d.*) as gst_hsn_info 
                From ar.fn_inv_for_cn(:pbranch_id, :pcustomer_id, :pfrom_date, :pto_date, :pvoucher_id) a
                Inner Join tx.gst_tax_tran d On a.invoice_tran_id = d.gst_tax_tran_id   
                Where (a.invoice_id = :pvoucher_id or :pvoucher_id = '')
                order by a.doc_date, a.invoice_id, a.invoice_tran_id";
        $cmm->setCommandText($sql);
        $cmm->addParam('pcustomer_id', $customer_id);
        $cmm->addParam('pvoucher_id', $origin_inv_id);
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('pfrom_date', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
        $cmm->addParam('pto_date', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = array();
        $result['inv_tran'] = $dt;
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionFetchCustName($cust_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select customer_name from ar.customer where customer_id = :pcustomer_id');
        $cmm->addParam('pcustomer_id', $cust_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $cust_name = '';
        if (count($dt->Rows()) > 0) {
            $cust_name = $dt->Rows()[0]['customer_name'];
        }
        $result = array();
        $result['cust_name'] = $cust_name;
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionFetchCustAdv($customer_id, $doc_date) {
        $result['unstl_adv_amt'] = \app\core\ar\advanceAlloc\AdvanceAllocHelper::GetUnsettledAdvAmt($customer_id, $doc_date);
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionFetchPayTerm() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("SELECT pay_term_id , pay_term  From ac.pay_term where for_cust = true order by pay_term");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = array();
        $result['dt_pay_term'] = $dt;
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionFetchSalesmanInfo($salesman_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select a.salesman_id, a.salesman_name, b.email, b.mobile  
                from ar.salesman a
                Inner Join sys.address b on a.address_id = b.address_id
                where a.salesman_id = :psalesman_id');
        $cmm->addParam('psalesman_id', $salesman_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = ['email' => '', 'mobile' => ''];
        if (count($dt->Rows()) > 0) {
            $result['email'] = $dt->Rows()[0]['email'];
            $result['mobile'] = $dt->Rows()[0]['mobile'];
        }
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionFetchCustInvOverdueDays($customer_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select max(overdue_days) overdue_days from ar.fn_customer_overdue(:pcompany_id, 0, :pcustomer_id, :pto_date::Date, '', 'D')");
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pcustomer_id', $customer_id);
        $cmm->addParam('pto_date', date('Y-m-d'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $overdue_days = 0;
        if(count($dt->Rows()) > 0){
            $overdue_days = $dt->Rows()[0]['overdue_days'];
        }
        $result = array();
        $result['overdue_days'] = $overdue_days;
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionFetchCustOverdueInv($customer_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select * from ar.fn_customer_overdue(:pcompany_id, 0, :pcustomer_id, :pto_date::Date, '', 'D') where overdue > 500 and overdue_days > 5 order by overdue_days desc, voucher_id ");
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pcustomer_id', $customer_id);
        $cmm->addParam('pto_date', date('Y-m-d'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $result = array();
        $result['dt_ovd_inv'] = $dt;
        $result['status'] = 'ok';
        return json_encode($result);
    }
    
    public function actionFetchCclDetail($customer_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * From ar.fn_ccl_spent(:pcompany_id, :pto_date, :pcustomer_id)');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pcustomer_id', $customer_id);
        $cmm->addParam('pto_date', date('Y-m-d'));
        $dtCcl = \app\cwf\vsla\data\DataConnect::getData($cmm);
       
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select * from ar.fn_customer_overdue(:pcompany_id, 0, :pcustomer_id, :pto_date::Date, '', 'D') where overdue > 500 and overdue_days > 5 order by overdue_days desc, voucher_id ");
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pcustomer_id', $customer_id);
        $cmm->addParam('pto_date', date('Y-m-d'));
        $dtInvOd = \app\cwf\vsla\data\DataConnect::getData($cmm);
        
        $result = array();
        $result['dtCcl'] = $dtCcl;
        $result['dtInvOd'] = $dtInvOd;
        $result['status'] = 'ok';
        return json_encode($result);
    }
}

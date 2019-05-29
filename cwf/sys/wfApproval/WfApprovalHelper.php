<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\wfApproval;

use YaLinqo\Enumerable;

/**
 * Description of WfApprovalHelper
 *
 * @author priyanka
 */
class WfApprovalHelper {

    public static function CreateWfApprTemp($bo) {
        // Create temp teble for WfApproval Temp
        $bo->wf_appr_temp = new \app\cwf\vsla\data\DataTable();
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->wf_appr_temp->addColumn('user_to', $phpType, $default, 0, 0, false);
        $bo->wf_appr_temp->addColumn('user_from', $phpType, $default, 0, 0, false);
        $bo->wf_appr_temp->addColumn('wf_approved', $phpType, $default, 0, 0, false);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('varchar');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->wf_appr_temp->addColumn('doc_id', $phpType, $default, 50, 0, false);
        $bo->wf_appr_temp->addColumn('from_user', $phpType, $default, 100, 0, false);
        $bo->wf_appr_temp->addColumn('to_user', $phpType, $default, 100, 0, false);
        $bo->wf_appr_temp->addColumn('wf_comment', $phpType, $default, 500, 0, false);
        $bo->wf_appr_temp->addColumn('appr_status', $phpType, $default, 20, 0, false);
        $bo->wf_appr_temp->addColumn('appr_type', $phpType, $default, 50, 0, false);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('date');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->wf_appr_temp->addColumn('added_on', $phpType, $default, 0, 0, false);
        $bo->wf_appr_temp->addColumn('acted_on', $phpType, $default, 0, 0, false);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('bool');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->wf_appr_temp->addColumn('is_acted', $phpType, $default, 0, 0, false);

        foreach ($bo->wf_appr_temp->getColumns() as $col) {
            $cols[] = ['columnName' => $col->columnName, 'default' => $col->default];
        }
        $bo->setTranMetaData('wf_appr_temp', $cols);
    }

    public static function getWfApprDt($doc_id, $wf_appr_temp) {
        $dtResult = WfApprovalHelper::getWfApprData($doc_id);
        $wf_appr_temp->removeAll(); // Remove all existing rows
        foreach ($dtResult->Rows() as $row) {
            $newRow = $wf_appr_temp->NewRow();
            $newRow['user_to'] = $row['user_to'];
            $newRow['user_from'] = $row['user_from'];
            $newRow['wf_approved'] = $row['wf_approved'];
            $newRow['doc_id'] = $row['doc_id'];
            $newRow['from_user'] = $row['from_user'];
            $newRow['to_user'] = $row['to_user'];
            $newRow['wf_comment'] = $row['wf_comment'];
            $newRow['appr_status'] = $row['appr_status'];
            $newRow['appr_type'] = $row['appr_type'];
            $newRow['added_on'] = $row['added_on'];
            $newRow['acted_on'] = $row['acted_on'];
            $newRow['is_acted'] = $row['is_acted'];
            $wf_appr_temp->AddRow($newRow);
        }
    }

    public static function getWfApprData($doc_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = "SELECT a.wf_ar_id, a.bo_id, a.branch_id, a.doc_id, a.doc_date, a.wf_desc, a.user_from, 
                        a.user_to, a.route, a.formname, a.formparams, a.added_on, b.full_user_name as from_user, c.full_user_name as to_user, 
                        a.wf_comment, a.wf_approved, case when a.acted_on is null then false else true  end is_acted, COALESCE(a.acted_on::varchar, '') acted_on, 
                        case apr_type when 'CL' then 'Credit Limit Approval' else 'Invoice Overdue Approval' end appr_type,
                        case a.wf_approved when 0 then 'Pending' when 1 then 'Approved' when 2 then 'Rejected' end appr_status    
                    FROM sys.wf_ar a
                    inner join sys.user b on a.user_from = b.user_id
                    inner join sys.user c on a.user_to = c.user_id
                    where a.doc_id = :pdoc_id
                    order by added_on desc";
        $cmm->addParam('pdoc_id', $doc_id);
        $cmm->setCommandText($cmmtext);
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }

    public static function validateApproval($customer_id, $order_val) {
        $cur_ord_amt = $order_val;

        $result = array();

        // Create temp teble for WfApproval Temp
        $wf_ar_dt = new \app\cwf\vsla\data\DataTable();
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $wf_ar_dt->addColumn('user_to', $phpType, $default, 0, 0, false);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('varchar');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $wf_ar_dt->addColumn('to_user', $phpType, $default, 100, 0, false);
        $wf_ar_dt->addColumn('wf_comment', $phpType, $default, 500, 0, false);
        $wf_ar_dt->addColumn('appr_type', $phpType, $default, 50, 0, false);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('numeric');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $wf_ar_dt->addColumn('excess_val', $phpType, $default, 0, 0, false);

        // Get credit balance for customer        
        $credit_limit = 0;
        $credit_limit_type = '';
        $inv_amt = 0;
        $order_amt = 0;
        $cl_spent = 0;
        $cl_spent_pcnt = 0;
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select credit_limit_type, credit_limit from ar.customer where customer_id = :pcustomer_id');
        $cmm->addParam('pcustomer_id', $customer_id);
        $dtcr = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtcr->Rows()) > 0) {
            $credit_limit = $dtcr->Rows()[0]['credit_limit'];
            $credit_limit_type = $dtcr->Rows()[0]['credit_limit_type'];
        }
        if ($credit_limit_type != 1) {// Credit limit is not of type Unlimited, we proceed further
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select COALESCE(sum(order_amt), 0) as order_amt, COALESCE(sum(inv_amt), 0) as inv_amt
                                    From ar.fn_ccl_spent(:pcompany_id, :pto_date, :pcustomer_id)');
            $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
            $cmm->addParam('pcustomer_id', $customer_id);
            $cmm->addParam('pto_date', date('Y-m-d'));
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

            if (count($dt->Rows()) > 0) {
                $order_amt = $dt->Rows()[0]['order_amt'];
                $inv_amt = $dt->Rows()[0]['inv_amt'];
            }
            $cl_spent = $credit_limit - $inv_amt - $order_amt - $cur_ord_amt;
            if ($cl_spent < 0) { // Now we have exceeded the room available (anything less than 1% is ignored)
                if ($credit_limit == 0) {
                    $cl_spent_pcnt = 0;
                } else {
                    $cl_spent_pcnt = floor((abs($cl_spent) / $credit_limit) * 100);
                }
            }
        }

        // Validate credit limit
        $cl_dt = WfApprovalHelper::get_cl_info();
        $cl_user_to = -1;
        $cl_user_name = '';
        $cl_range_exhausted = false;
        foreach ($cl_dt->Rows() as $cl_row) {
            if ($cl_spent_pcnt > $cl_row['min_val'] && $cl_spent_pcnt <= $cl_row['max_val']) {
                $cl_user_to = $cl_row['cl_user_to'];
                $cl_user_name = $cl_row['full_user_name'];
                break;
            }
        }
        if ($cl_user_to == -1 && $cl_spent_pcnt > 0) {
            $cl_range_exhausted = true;
        }

        // Populate results for credit limit
        $result['cl_range_exhausted'] = $cl_range_exhausted;
        $result['balance'] = $credit_limit - $inv_amt - $order_amt;
        $result['order_val'] = (float) $cur_ord_amt;
        $result['excess_val'] = $cl_spent;
        $result['excess_pcnt'] = $cl_spent_pcnt;
        $result['cl_to_user'] = $cl_user_name;
        $result['cl_user_to'] = -1;
        $result['cl_reqd'] = false;
        if ($cl_user_to != -1) {
            $result['cl_reqd'] = true;
            $result['cl_user_to'] = $cl_user_to;
        }

        // Get invoice overdue days
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select voucher_id, doc_date, overdue, max(overdue_days) overdue_days 
                                from ar.fn_customer_overdue(:pcompany_id, 0, :pcustomer_id, :pto_date::Date, '', 'D')
                                where overdue > 500 And overdue_days > 3 
                                group by voucher_id, doc_date, overdue 
                                order by overdue_days desc, voucher_id");
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pcustomer_id', $customer_id);
        $cmm->addParam('pto_date', date('Y-m-d'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $overdue_days = 0;
        $inv_id = '';
        $inv_date = '1970-01-01';
        $overdue_amt = 0;
        if (count($dt->Rows()) > 0) {
            $overdue_days = $dt->Rows()[0]['overdue_days'];
            $inv_id = $dt->Rows()[0]['voucher_id'];
            $inv_date = $dt->Rows()[0]['doc_date'];
            $overdue_amt = $dt->Rows()[0]['overdue'];
        }

        $io_range_exhausted = false;
        // Validate overdue days
        $io_dt = WfApprovalHelper::get_io_info();
        $io_user_to = -1;
        $io_user_name = '';
        foreach ($io_dt->Rows() as $io_row) {
            if ($overdue_days > $io_row['min_val'] && $overdue_days <= $io_row['max_val']) {
                $io_user_to = $io_row['io_user_to'];
                $io_user_name = $io_row['full_user_name'];
                break;
            }
        }
        if ($io_user_to == -1 && $overdue_days > 0) {
            $io_range_exhausted = true;
        }

        $result['io_range_exhausted'] = $io_range_exhausted;
        $result['overdue_days'] = $overdue_days;
        $result['io_to_user'] = $io_user_name;
        $result['io_user_to'] = $io_user_to;
        $result['io_reqd'] = false;
        if ($io_user_to != -1) {
            $result['io_reqd'] = true;
        }
        $result['inv_id'] = $inv_id;
        $result['inv_date'] = $inv_date;
        $result['overdue_amt'] = $overdue_amt;

        return $result;
    }

    private static function get_cl_info() {
        // get credit limit approver
        $cl_txt = "select (a.annex_info->>'min')::numeric min_val, (a.annex_info->>'max')::numeric max_val, (a.annex_info->>'user_to')::bigint as cl_user_to , b.full_user_name 
                    from sys.apr_matrix a
                    inner join sys.user b on (a.annex_info->>'user_to')::bigint = b.user_id
                    where a.matrix_type = 'CL'
                    order by (a.annex_info->>'max')::numeric";
        $cl_cmm = new \app\cwf\vsla\data\SqlCommand();
        $cl_cmm->setCommandText($cl_txt);
        return \app\cwf\vsla\data\DataConnect::getData($cl_cmm);
    }

    private static function get_io_info() {
        // get invoice overdue approver
        $io_txt = "select (a.annex_info->>'min')::numeric min_val, (a.annex_info->>'max')::numeric max_val, (a.annex_info->>'user_to')::bigint as io_user_to , b.full_user_name 
                    from sys.apr_matrix a
                    inner join sys.user b on (a.annex_info->>'user_to')::bigint = b.user_id
                    where a.matrix_type = 'IO'";
        $io_cmm = new \app\cwf\vsla\data\SqlCommand();
        $io_cmm->setCommandText($io_txt);
        return \app\cwf\vsla\data\DataConnect::getData($io_cmm);
    }

    public function mnuWfApprovalCount() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = "SELECT count(*) cnt
                    FROM sys.wf_ar a
                    where a.user_to = :puser_id and a.acted_on is null";
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $cmm->setCommandText($cmmtext);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            return $dt->Rows()[0]['cnt'];
        }
        return 0;
    }

}

<?php

namespace app\core\ar\invoiceDispatched;

use app\cwf\vsla\data\DataConnect;
use app\cwf\vsla\security\SessionManager;

class ModelInvoiceDispatched {

    public $filters;
    public $from_date;
    public $to_date;
    public $brokenrules = array();
    public $customer_id;

    public function __construct() {
        $this->dt = array();
        $this->from_date = SessionManager::getInstance()->getUserInfo()->getSessionVariable('year_begin');
    }

    public function setFilters($filter) {
        $this->from_date = $filter['from_date'];
        $this->to_date = $filter['to_date'];
        $this->customer_id = $filter['customer_id'];
        $this->getData();
    }

    public function getData() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select *from ar.fn_pending_inv_for_dispatch(:pcompany_id, :pbranch_id, :pcustomer_id, :pfrom_date, :pto_date)');
        $cmm->addParam('pcompany_id', SessionManager::getInstance()->getUserInfo()->getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', SessionManager::getInstance()->getUserInfo()->getSessionVariable('branch_id'));
        $cmm->addParam('pcustomer_id', $this->customer_id);
        $cmm->addParam('pfrom_date', \app\cwf\vsla\utils\FormatHelper::GetDBDate($this->from_date));
        $cmm->addParam('pto_date', \app\cwf\vsla\utils\FormatHelper::GetDBDate($this->to_date));

        $recodt = DataConnect::getData($cmm);
        $recodt->addColumn('inv_date_sort', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        foreach ($recodt->Rows() as &$dr) {
            $dr['doc_date'] = ['display' => \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($dr['doc_date']),
                'sort' => strtotime($dr['doc_date'])];
        }
        $this->dt = $recodt;
    }

    public function setData($model) {
        $this->validate($model);
        if (count($this->brokenrules) == 0) {
            $cn = DataConnect::getCn(DataConnect::COMPANY_DB);
            try {

                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('select * from ar.sp_inv_dispatched_update(:pinv_type, :pvoucher_id, :pdispatched_date, :pdispatch_method, :pdispatch_remark)');
                $cmm->addParam('pvoucher_id', '');
                $cmm->addParam('pdispatched_date', null);
                $cmm->addParam('pinv_type', '');
                $cmm->addParam('pdispatch_method', -1);
                $cmm->addParam('pdispatch_remark', '');
                $cn->beginTransaction();
                for ($rowIndex = 0; $rowIndex < count($model->dt); $rowIndex++) {
                    if ($model->dt[$rowIndex]->is_dispatched == true) {
                        $cmm->setParamValue('pvoucher_id', $model->dt[$rowIndex]->voucher_id);
                        $cmm->setParamValue('pdispatched_date', $model->dt[$rowIndex]->dispatched_date);
                        $cmm->setParamValue('pinv_type', $model->dt[$rowIndex]->inv_type);
                        $cmm->setParamValue('pdispatch_method', $model->dt[$rowIndex]->dispatch_method);
                        $cmm->setParamValue('pdispatch_remark', $model->dt[$rowIndex]->dispatch_remark);
                        DataConnect::exeCmm($cmm, $cn);
                    }
                }
                $cn->commit();
                $cn = null;
            } catch (\Exception $ex) {
                if ($cn->inTransaction()) {
                    $cn->rollBack();
                    $cn = null;
                }
                return $ex->getMessage();
            }
        }
    }

    public function validate($model) {
        $remarks_reqd = \app\cwf\vsla\utils\SettingsHelper::GetKeyValue('ar_inv_dispatch_remark_reqd');
        for ($rowIndex = 0; $rowIndex < count($model->dt); $rowIndex++) {
            if ($model->dt[$rowIndex]->is_dispatched == true) {
                if ($model->dt[$rowIndex]->dispatched_date == '1970-01-01') {
                    array_push($this->brokenrules, $model->dt[$rowIndex]->voucher_id . ' : Select proper Dispatched Date.');
                }
                if (strtotime($model->dt[$rowIndex]->dispatched_date) < strtotime(\app\cwf\vsla\utils\FormatHelper::GetDBDate($model->dt[$rowIndex]->doc_date->display))) {
                    array_push($this->brokenrules, $model->dt[$rowIndex]->voucher_id . ' : Dispatched Date cannot be less than Doc Date');
                }
                if (strtotime($model->dt[$rowIndex]->dispatched_date) > strtotime(date('Y-m-d'))) {
                    array_push($this->brokenrules, $model->dt[$rowIndex]->voucher_id . ' : Dispatched Date cannot be greater than current Date');
                }
                if ($model->dt[$rowIndex]->dispatch_method == 0) {
                    array_push($this->brokenrules, $model->dt[$rowIndex]->voucher_id . ' : Select proper Dispatch Method.');
                }
                if ($remarks_reqd) {
                    if ($model->dt[$rowIndex]->dispatch_remark == '') {
                        array_push($this->brokenrules, $model->dt[$rowIndex]->voucher_id . ' : Remark is required.');
                    }
                }
            }
        }
    }

}

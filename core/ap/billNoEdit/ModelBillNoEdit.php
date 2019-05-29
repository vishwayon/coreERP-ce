<?php

namespace app\core\ap\billNoEdit;

use app\cwf\vsla\data\DataConnect;
use app\cwf\vsla\security\SessionManager;

class ModelBillNoEdit {

    public $view_type_option;
    public $filters;
    public $media_type_option;
    public $bill_id;
    public $view_type_id;
    public $from_date;
    public $to_date;
    public $as_on;
    public $is_insertion_date;
    public $rodata;
    public $closed;
    public $brokenrules = array();
    public $account_id;

    const VIEW_TYPE_OPENED = 0;
    const VIEW_TYPE_CLOSED = 1;
    const VIEW_TYPE_RELEASED = 2;

    public function __construct() {
        $this->view_type_option = array();
        $this->view_type_option[0] = 'With BNR';
        $this->view_type_option[1] = 'Without BNR';
        $this->view_type_id = 0;
        $this->dt = array();
        $this->from_date = SessionManager::getInstance()->getUserInfo()->getSessionVariable('year_begin');
        $this->to_date = SessionManager::getInstance()->getUserInfo()->getSessionVariable('year_end');
    }

    public function setFilters($filter) {
        $this->view_type_id = $filter['view_type_id'];
        $this->from_date = $filter['from_date'];
        $this->to_date = $filter['to_date'];
        $this->account_id = $filter['account_id'];
        $this->bill_id = $filter['bill_id'];
        if ($this->view_type_id == 1 && $this->bill_id == '') {
            $this->brokenrules[] = 'Enter valid bill ID for status Without BNR.';
        } else {
            $this->getData();
        }
    }

    public function getData() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select *, case when bill_no = 'BNR' then false else true end as selected from ap.fn_bill_collection(:pcompany_id, :pbranch_id, :paccount_id, :pfrom_date, :pto_date, :pbill_status, :pbill_id)");
        $cmm->addParam('pcompany_id', SessionManager::getInstance()->getUserInfo()->getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', SessionManager::getInstance()->getUserInfo()->getSessionVariable('branch_id'));
        $cmm->addParam('paccount_id', $this->account_id);
        $cmm->addParam('pbill_status', $this->view_type_id);
        $cmm->addParam('pbill_id', $this->bill_id);
        $cmm->addParam('pfrom_date', \app\cwf\vsla\utils\FormatHelper::GetDBDate($this->from_date));
        $cmm->addParam('pto_date', \app\cwf\vsla\utils\FormatHelper::GetDBDate($this->to_date));

        $recodt = DataConnect::getData($cmm);
        $recodt->addColumn('bill_date_sort', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        foreach ($recodt->Rows() as &$dr) {
            $dr['bill_date_sort'] = strtotime($dr['bill_date']);
        }
        $this->dt = $recodt;
    }

    public function setData($model) {
        $this->validate($model);
        if (count($this->brokenrules) == 0) {
            $cn = DataConnect::getCn(DataConnect::COMPANY_DB);
            try {

                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('select * from ap.sp_bill_no_update(:pbill_type, :pvoucher_id, :pbill_no, :pbill_date, :paccount_id)');
                $cmm->addParam('pbill_type', '');
                $cmm->addParam('pvoucher_id', '');
                $cmm->addParam('pbill_no', '');
                $cmm->addParam('paccount_id', -1);
                $cmm->addParam('pbill_date', null);
                $cn->beginTransaction();
                for ($rowIndex = 0; $rowIndex < count($model->dt); $rowIndex++) {
                    if ($model->dt[$rowIndex]->selected == true) {
                        $cmm->setParamValue('pbill_type', $model->dt[$rowIndex]->bill_type);
                        $cmm->setParamValue('pvoucher_id', $model->dt[$rowIndex]->voucher_id);
                        $cmm->setParamValue('pbill_no', $model->dt[$rowIndex]->bill_no);
                        $cmm->setParamValue('pbill_date', $model->dt[$rowIndex]->bill_date);
                        $cmm->setParamValue('paccount_id', $model->dt[$rowIndex]->supplier_id);
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
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * from ap.fn_validate_bill_no(:paccount_id, :pbill_no, :pbill_date, :pvoucher_id)');
        $cmm->addParam('paccount_id', -1);
        $cmm->addParam('pbill_no', '');
        $cmm->addParam('pvoucher_id', '');
        $cmm->addParam('pbill_date', '1970-01-01');        
        
        $cmmpl = new \app\cwf\vsla\data\SqlCommand();
        $cmmpl->setCommandText("Select bill_no, bill_date, voucher_id from ac.rl_pl "
                . " where account_id=:paccount_id and bill_no ilike :pbill_no and bill_date = :pbill_date and voucher_id!=:pvoucher_id");
        $cmmpl->addParam('paccount_id', -1);
        $cmmpl->addParam('pbill_no', '');
        $cmmpl->addParam('pvoucher_id', '');
        $cmmpl->addParam('pbill_date', '1970-01-01');
        for ($rowIndex = 0; $rowIndex < count($model->dt); $rowIndex++) {
            if ($model->dt[$rowIndex]->selected == true) {
                if (strtotime($model->dt[$rowIndex]->bill_date) > strtotime($model->dt[$rowIndex]->doc_date)) {
                    array_push($this->brokenrules, $model->dt[$rowIndex]->voucher_id . ' : Bill Date should be less than or equal to Doc Date.');
                }

                //  Validate duplicate bill no for a supplier
                $cmm->setParamValue('paccount_id', $model->dt[$rowIndex]->supplier_id);
                $cmm->setParamValue('pbill_no', $model->dt[$rowIndex]->bill_no);
                $cmm->setParamValue('pvoucher_id', $model->dt[$rowIndex]->voucher_id);
                $cmm->setParamValue('pbill_date', $model->dt[$rowIndex]->bill_date);
                $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
                if (count($result->Rows()) > 0) {
                    array_push($this->brokenrules, $model->dt[$rowIndex]->voucher_id . ' : Bill No ' . $model->dt[$rowIndex]->bill_no . ' Dt. ' . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($result->Rows()[0]['bill_date']) . ' already entered for the selected Supplier in (' . $result->Rows()[0]['voucher_id'] . '). Duplicate Bill No not allowed.');
                }
                else {
                    $cmmpl->setParamValue('paccount_id', $model->dt[$rowIndex]->supplier_id);
                    $cmmpl->setParamValue('pbill_no', $model->dt[$rowIndex]->bill_no);
                    $cmmpl->setParamValue('pvoucher_id', $model->dt[$rowIndex]->voucher_id);
                    $cmmpl->setParamValue('pbill_date', $model->dt[$rowIndex]->bill_date);
                    $resultpl = \app\cwf\vsla\data\DataConnect::getData($cmmpl);
                    if (count($resultpl->Rows()) > 0) {
                        $this->bo->addBRule('Bill No ' . $this->bo->bill_no . ' Dt. ' . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($resultpl->Rows()[0]['bill_date']) . ' already used for the selected Ledger Account in (' . $resultpl->Rows()[0]['voucher_id'] . '). Duplicate Bill No not allowed.');
                    }
                }
            }
        }
    }

}

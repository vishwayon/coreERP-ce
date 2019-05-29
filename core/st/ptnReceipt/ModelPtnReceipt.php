<?php

namespace app\core\st\ptnReceipt;

use app\cwf\vsla\data\DataConnect;
use app\cwf\vsla\security\SessionManager;

class ModelPtnReceipt {

    public $status_option;
    public $status;

    const STATUS_PENDING_POST = 0;
    const STATUS_POSTED = 1;

    public $filters;
    public $from_date;
    public $to_date;
    public $brokenrules = array();

    public function __construct() {
        $this->status_option = array();
        $this->status_option[0] = 'Pending Post';
        $this->status_option[1] = 'Posted';
        $this->status = 0;
        $this->from_date = SessionManager::getInstance()->getUserInfo()->getSessionVariable('year_begin');
        $this->to_date = SessionManager::getInstance()->getUserInfo()->getSessionVariable('year_end');
        $this->dt = array();
    }

    public function setFilters($filter) {
        $this->status = $filter['status'];
        if ($this->status != 0) {
            $this->from_date = \app\cwf\vsla\utils\FormatHelper::GetDBDate($filter['from_date']);
            $this->to_date = \app\cwf\vsla\utils\FormatHelper::GetDBDate($filter['to_date']);
        }
        $this->getData();
    }

    public function getData() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select a.*, b.branch_name from st.sp_get_st_for_part_post(:pstatus, :pyear_begin, :pyear_end, :ptarget_branch_id, 'PTN') a
                 Inner join sys.branch b on a.source_branch_id = b.branch_id");
        $cmm->addParam('pstatus', $this->status);
        $cmm->addParam('pyear_begin', $this->from_date);
        $cmm->addParam('pyear_end', $this->to_date);
        $cmm->addParam('ptarget_branch_id', SessionManager::getInstance()->getUserInfo()->getSessionVariable('branch_id'));
        $this->dt = DataConnect::getData($cmm);
    }

    public function validate($model) {
        if ($model->status == self::STATUS_PENDING_POST) {
            for ($rowIndex = 0; $rowIndex < count($model->dt); $rowIndex++) {
                if ($model->dt[$rowIndex]->posted == TRUE) {
                    if ($model->dt[$rowIndex]->doc_date == '1970-01-01') {
                        array_push($this->brokenrules, $model->dt[$rowIndex]->stock_id . ' :Select valid Date.');
                    }
                    if (strtotime($model->dt[$rowIndex]->doc_date) <
                            strtotime($model->dt[$rowIndex]->st_date)) {
                        array_push($this->brokenrules, $model->dt[$rowIndex]->stock_id . ' :Date cannot be less than Voucher Date');
                    }
                }
            }
        }
    }

    public function setData($model) {
        $this->validate($model);
        if ($model->status == self::STATUS_POSTED) {
            $this->validateOnUnpost($model);
        }
        $stock_id = '';
        if (count($this->brokenrules) == 0) {
            $cn = DataConnect::getCn(DataConnect::COMPANY_DB);
            try {

                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('select * from st.sp_st_part_post_update(:pstock_id, :psource_branch_id, :ptarget_branch_id, :pstatus, :pdoc_date, 
                                        :pfinyear, :preference, :pauthorised_by)');
                $cmm->addParam('pstock_id', '');
                $cmm->addParam('psource_branch_id', -1);
                $cmm->addParam('ptarget_branch_id', -1);
                $cmm->addParam('pstatus', 0);
                $cmm->addParam('pdoc_date', null);
                $cmm->addParam('pfinyear', '');
                $cmm->addParam('preference', '');
                $cmm->addParam('pauthorised_by', '');
                $cn->beginTransaction();

                if ($model->status == self::STATUS_PENDING_POST) {
                    for ($rowIndex = 0; $rowIndex < count($model->dt); $rowIndex++) {
                        if ($model->dt[$rowIndex]->posted == TRUE) {
                            $stock_id = $model->dt[$rowIndex]->stock_id;
                            $cmm->setParamValue('pstock_id', $stock_id);
                            $cmm->setParamValue('psource_branch_id', $model->dt[$rowIndex]->source_branch_id);
                            $cmm->setParamValue('ptarget_branch_id', $model->dt[$rowIndex]->target_branch_id);
                            $cmm->setParamValue('pstatus', 5);
                            $cmm->setParamValue('pdoc_date', $model->dt[$rowIndex]->doc_date);
                            $cmm->setParamValue('pfinyear', SessionManager::getSessionVariable('finyear'));
                            $cmm->setParamValue('preference', $model->dt[$rowIndex]->reference);
                            $cmm->setParamValue('pauthorised_by', SessionManager::getInstance()->getUserInfo()->getFullUserName());
                            DataConnect::exeCmm($cmm, $cn);
                        }
                    }
                } else if ($model->status == self::STATUS_POSTED) {
                    for ($rowIndex = 0; $rowIndex < count($model->dt); $rowIndex++) {
                        if ($model->dt[$rowIndex]->posted == FALSE) {
                            $stock_id = $model->dt[$rowIndex]->stock_id;
                            $cmm->setParamValue('pstock_id', $stock_id);
                            $cmm->setParamValue('psource_branch_id', $model->dt[$rowIndex]->source_branch_id);
                            $cmm->setParamValue('ptarget_branch_id', $model->dt[$rowIndex]->target_branch_id);
                            $cmm->setParamValue('pstatus', 0);
                            $cmm->setParamValue('pdoc_date', NULL);
                            $cmm->setParamValue('pfinyear', '');
                            $cmm->setParamValue('preference', '');
                            $cmm->setParamValue('pauthorised_by', '');
                            DataConnect::exeCmm($cmm, $cn);
                        }
                    }
                }
                $cn->commit();
                $cn = null;
            } catch (\Exception $ex) {
                if ($cn->inTransaction()) {
                    $cn->rollBack();
                    $cn = null;
                }
                throw new \Exception('Error posting/unposting ' . $stock_id, $ex->getCode(), $ex);
            }
        }
    }

    private function validateOnUnpost($model) {
        for ($rowIndex = 0; $rowIndex < count($model->dt); $rowIndex++) {
            if ($model->dt[$rowIndex]->posted == FALSE) {
                $stock_id = $model->dt[$rowIndex]->stock_id;
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText("select * from st.fn_sl_unpost_val_nst(:pvoucher_id)");
                $cmm->addParam('pvoucher_id', ($stock_id . ":AJ"));
                $bal_result = \app\cwf\vsla\data\DataConnect::getData($cmm);
                if (count($bal_result->Rows()) > 0) {
                    array_push($this->brokenrules, $stock_id . ': Stock balance for ' . $bal_result->Rows()[0]['material_name'] . ' is negative (' . \app\cwf\vsla\utils\FormatHelper::FormatAmt($bal_result->Rows()[0]['balance']) . ') in SL: ' . $bal_result->Rows()[0]['stock_location_name'] . ' on ' . $bal_result->Rows()[0]['doc_date']);
                }
            }
        }
    }
}

<?php

namespace app\core\ar\custInfoUpdate;

use app\cwf\vsla\data\DataConnect;
use app\cwf\vsla\security\SessionManager;

class ModelCustInfoUpdate {

    public $custinfoupdatedata;
    public $filters;
    public $customer_id = -1;
    public $pay_term_id = -1;
    public $credit_limit_type = -1;
    public $price_type = '';
    public $brokenrules = array();
    public $dt;

    public function __construct() {
        $this->dt = array();
    }

    public function setFilters($filter) {
        $this->filters = $filter;
        if ($filter['customer_id'] == '') {
            array_push($this->brokenrules, 'Select Customer.');
        }
        if ($filter['pay_term_id'] == '') {
            array_push($this->brokenrules, 'Select Pay Term.');
        }
        if (count($this->brokenrules) == 0) {
            $this->customer_id = $filter['customer_id'];
            $this->pay_term_id = $filter['pay_term_id'] == '' || $filter['pay_term_id'] == -1 ? 0 : intval($filter['pay_term_id']);
            $this->credit_limit_type = $filter['credit_limit_type'];
            $this->getData();
        }
    }

    public function getData() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select *,false as select,-1 as mcl_type, 0 as mcl, -1 as mpt_id '
                . 'from ar.fn_cust_info_for_update(:pcustomer_id, :pcredit_limit_type, :ppay_term_id)');
        $cmm->addParam('pcustomer_id', $this->customer_id);
        $cmm->addParam('pcredit_limit_type', $this->credit_limit_type);
        $cmm->addParam('ppay_term_id', $this->pay_term_id);
        $this->dt = DataConnect::getData($cmm);
    }

    public function validate($model) {
        for ($rowIndex = 0; $rowIndex < count($model->dt); $rowIndex++) {
            if ($model->dt[$rowIndex]->select == TRUE) {
                if ($model->dt[$rowIndex]->mcl_type == -1) {
                    array_push($this->brokenrules, $model->dt[$rowIndex]->customer . ' : Credit Limit Type is required.');
                }
                
                if ($model->dt[$rowIndex]->mpt_id == -1) {
                    array_push($this->brokenrules, $model->dt[$rowIndex]->customer . ' : Pay Term is required.');
                }
                
                if ($model->dt[$rowIndex]->mcl_type == 0 || $model->dt[$rowIndex]->mcl_type == 1) {
                    if ($model->dt[$rowIndex]->mcl != 0) {
                        array_push($this->brokenrules, $model->dt[$rowIndex]->customer . ' : Credit Limit should be zero for No Credit and Unlimited Credit.');
                    }
                }

                if ($model->dt[$rowIndex]->mcl_type == 2) {
                    if ($model->dt[$rowIndex]->mcl == 0) {
                        array_push($this->brokenrules, $model->dt[$rowIndex]->customer . ' : Credit Limit cannot be zero.');
                    }
                }
            }
        }
    }

    public function setData($model) {
        $this->validate($model);
        if (count($this->brokenrules) == 0) {
            $cn = DataConnect::getCn(DataConnect::COMPANY_DB);
            try {

                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('update ar.customer
                                            Set credit_limit_type = :pcredit_limit_type,
                                            credit_limit = :pcredit_limit,
                                            pay_term_id = :ppay_term_id
                                        where customer_id = :pcustomer_id;');
                $cmm->addParam('pcustomer_id', -1);
                $cmm->addParam('pcredit_limit_type', -1);
                $cmm->addParam('pcredit_limit', 0);
                $cmm->addParam('ppay_term_id', -1);
                $cn->beginTransaction();

                for ($rowIndex = 0; $rowIndex < count($model->dt); $rowIndex++) {
                    if ($model->dt[$rowIndex]->select == TRUE) {
                        $cmm->setParamValue('pcustomer_id', $model->dt[$rowIndex]->customer_id);
                        $cmm->setParamValue('pcredit_limit_type', $model->dt[$rowIndex]->mcl_type);
                        $cmm->setParamValue('pcredit_limit', $model->dt[$rowIndex]->mcl);
                        $cmm->setParamValue('ppay_term_id', $model->dt[$rowIndex]->mpt_id);
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
                throw $ex;
            }
        }
    }

}

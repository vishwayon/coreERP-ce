<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\employee;

/**
 * Description of EmployeeEventHandler
 *
 * @author Valli
 */
class EmployeeEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        if ($this->bo->employee_id == -1) {
            if (count($this->bo->employee_address_tran->Rows()) == 0) {
                $newRow = $this->bo->employee_address_tran->NewRow();
                $newRow['address_id'] = -1;
                $newRow['address_type_id'] = -1;
                $newRow['company_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
                $newRow['address'] = "";
                $newRow['city'] = "";
                $newRow['pin'] = "";
                $newRow['state'] = "";
                $newRow['country'] = "";
                $newRow['fax'] = "";
                $newRow['mobile'] = "";
                $newRow['phone'] = "";
                $newRow['email'] = "";
                $newRow['contact_person'] = "";
                $this->bo->employee_address_tran->AddRow($newRow);
            }
        }
        if (!$this->bo->is_resign_date) {
            $this->bo->resign_date = '1970-01-01';
        }
        if (count($this->bo->employee_stat_regn->Rows()) == 0) {
            $newRow = $this->bo->employee_stat_regn->NewRow();
            $newRow['employee_id'] = -1;
            $newRow['pf_acc_no'] = "";
            $newRow['esic_acc_no'] = "";
            $newRow['pan'] = "";
            $this->bo->employee_stat_regn->AddRow($newRow);
        }
    }

    public function beforeSave($cn) {
        parent::beforeSave($cn);

        $this->bo->full_employee_name = $this->bo->firstname . " " . $this->bo->middlename . " " . $this->bo->lastname;
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);

        if (!$this->bo->is_resign_date) {
            $this->bo->resign_date = '1970-01-01';
        }
    }

//    public function onFetch($criteriaparam, $tablename) {
//        parent::onFetch($criteriaparam, $tablename);
//        if ($tablename == 'hr.employee_stat_regn') {
//            $cmm = new \app\cwf\vsla\data\SqlCommand();
//            $cmm->setCommandText("Select pf_acc_no, esic_acc_no from hr.employee_stat_regn where employee_id = :pemployee_id");
//            $cmm->addParam('pemployee_id', $criteriaparam['employee_id']);
//            $dtresult = \app\cwf\vsla\data\DataConnect::getData($cmm);
//            if (count($dtresult->Rows()) > 0) {
//                $this->bo->pf_acc_no = $dtresult->Rows()[0]['pf_acc_no'];
//                $this->bo->esic_acc_no = $dtresult->Rows()[0]['esic_acc_no'];
//            }
//        }
//    }
//
//    public function onSave($cn, $tablename) {
//        parent::onSave($cn, $tablename);
//        if ($tablename == 'hr.employee_stat_regn') {
//            // Save new pay items
//            $ac = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts('hr.employee_stat_regn', \app\cwf\vsla\data\DataConnect::COMPANY_DB, \app\cwf\vsla\entity\ActionScript::TABLE_TYPE_MASTER_CONTROL);
//            if (count($this->bo->employee_stat_regn->Rows()) > 0) {
//                
//            }
//            if ($this->bo->employee_id == -1) {
//                $cmm = $ac->getInsertCmm();
//            } else {
//                $cmm = $ac->getUpdateCmm();
//            }
//            $cmm->setParamValue('pemployee_id', $this->bo->employee_id);
//            $cmm->setParamValue('ppf_acc_no', $this->bo->pf_acc_no);
//            $cmm->setParamValue('pesic_acc_no', $this->bo->esic_acc_no);
//            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
//        }
//    }
}

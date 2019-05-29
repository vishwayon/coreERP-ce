<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\supplier;

/**
 * Description of SupplierEventHandler
 *
 * @author Vaishali
 */
class SupplierEventHandler extends \app\core\ac\accountHead\AccountHeadEventHandler {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        $this->bo->is_new = false;
        if ($this->bo->supplier_id == -1) {
            $this->bo->is_new = true;
            $this->bo->account_type_id = 12;
            $this->bo->annex_info->Value()->is_overridden = false;

            $this->bo->annex_info->Value()->supp_type_id = (\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id') * 1000000) + 1;
            if (count($this->bo->supplier_tax_info_tran->Rows()) == 0) {
                $newRow = $this->bo->supplier_tax_info_tran->NewRow();
                $newRow['supplier_tax_info_id'] = -1;
                $newRow['supplier_id'] = -1;
                $newRow['tds_person_type_id'] = -1;
                $newRow['tds_section_id'] = -1;
                $newRow['is_tds_applied'] = false;
                $newRow['tan'] = "";
                $newRow['pan'] = "";
                $newRow['is_st_applied'] = false;
                $newRow['st_no'] = "";
                $this->bo->supplier_tax_info_tran->AddRow($newRow);
            }


            if (count($this->bo->supplier_address_tran->Rows()) == 0) {
                $newRow = $this->bo->supplier_address_tran->NewRow();
                $newRow['address_id'] = -1;
                $newRow['address_type_id'] = (\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id') * 1000000) + 3;
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
                $this->bo->supplier_address_tran->AddRow($newRow);
            }
        } else {
            foreach ($this->bo->supplier_tax_info_tran->Rows() as &$ref_row) {
                // Fetch tds rate for display
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $sql = "Select base_rate_perc, ecess_perc, surcharge_perc from tds.rate
                    Where section_id = :psection_id and person_type_id = :pperson_type_id";
                $cmm->setCommandText($sql);
                $cmm->addParam('psection_id', $ref_row['tds_section_id']);
                $cmm->addParam('pperson_type_id', $ref_row['tds_person_type_id']);
                $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
                if (count($dt->Rows()) > 0) {
                    $ref_row['base_rate_perc'] = $dt->Rows()[0]['base_rate_perc'];
                    $ref_row['ecess_perc'] = $dt->Rows()[0]['ecess_perc'];
                    $ref_row['surcharge_perc'] = $dt->Rows()[0]['surcharge_perc'];
                }
            }
        }
    }

    public function beforeSave($cn) {
        parent::beforeSave($cn);
    }

    public function customCode() {
        return '<div style="margin-top:30px;"><label>this is custom code.......</label></div>';
    }
}

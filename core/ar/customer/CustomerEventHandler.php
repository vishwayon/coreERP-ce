<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\customer;

/**
 * Description of CustomerEventHandler
 *
 * @author Shrishail
 */
class CustomerEventHandler extends \app\core\ac\accountHead\AccountHeadEventHandler {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        $this->bo->account_type_id = 7;
        if ($this->bo->customer_id == -1) {
            $this->bo->annex_info->Value()->is_overridden = false;
            $this->bo->annex_info->Value()->segment_id = (\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id') * 1000000) + 1;
        }
        if (count($this->bo->customer_address_tran->Rows()) == 0) {
            $newRow = $this->bo->customer_address_tran->NewRow();
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
            $this->bo->customer_address_tran->AddRow($newRow);
        }
//        if (count($this->bo->customer_shipping_address_tran->Rows()) == 0) {
//            $newRow = $this->bo->customer_shipping_address_tran->NewRow();
//            $newRow['address_id'] = -1;
//            $newRow['address_type_id'] = (\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id') * 1000000) + 2;
//            $newRow['company_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
//            $newRow['address'] = "";
//            $newRow['city'] = "";
//            $newRow['pin'] = "";
//            $newRow['state'] = "";
//            $newRow['country'] = "";
//            $newRow['fax'] = "";
//            $newRow['mobile'] = "";
//            $newRow['phone'] = "";
//            $newRow['email'] = "";
//            $newRow['contact_person'] = "";
//            $this->bo->customer_shipping_address_tran->AddRow($newRow);
//        }
//        }
    }

    public function beforeSave($cn) {
        parent::beforeSave($cn);
    }

}

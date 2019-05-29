<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\salesman;

/**
 * Description of salesmanEventHandler
 *
 * @author Priyanka
 */
class SalesmanEventHandler extends\app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        if (count($this->bo->salesman_address_tran->Rows()) == 0) {
            $newRow = $this->bo->salesman_address_tran->NewRow();
            $newRow['address_id'] = -1;
            $newRow['address_type_id'] = (\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id') * 1000000) + 1;
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
            $this->bo->salesman_address_tran->AddRow($newRow);
        }
    }

}

<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tds\deductorInfo;

/**
 * Description of DeductorInfoEventHandler
 *
 * @author Shrishail
 */
class DeductorInfoEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function beforeFetch(&$criteriaparam) {
        parent::beforeFetch($criteriaparam);
        
        // Fetch company name for selected company        
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.company where company_id=:pcompany_id');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $dtbr= \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        $this->bo->deductor_name=$dtbr->Rows()[0]['company_name'];
              
    }
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        
    }

    public function beforeSave($cn) {            
        parent::beforeSave($cn);
    }
    
    
}

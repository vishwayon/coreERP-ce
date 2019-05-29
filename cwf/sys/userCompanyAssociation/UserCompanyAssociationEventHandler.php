<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\userCompanyAssociation;
use app\cwf\vsla\data\DataConnect;
/**
 * Description of UserCompanyAssociationEventHandler
 *
 * @author Ravindra
 */
class UserCompanyAssociationEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
    }   
}

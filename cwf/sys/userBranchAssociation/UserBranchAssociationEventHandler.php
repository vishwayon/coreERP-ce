<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\userBranchAssociation;
use app\cwf\vsla\data\DataConnect;
/**
 * Description of UserBranchAssociationEventHandler
 *
 * @author vaishali
 */
class UserBranchAssociationEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        
        $this->bo->user_to_branch->getColumn("company_id")->default= \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
        $this->bo->setTranColDefault('user_to_branch', 'company_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        
    }   
}

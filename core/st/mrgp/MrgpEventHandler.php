<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\mrgp;

/**
 * Description of MrgpEventHandler
 *
 * @author Priyanka
 */
class MrgpEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        
        if ($this->bo->stock_id == "" or $this->bo->stock_id == "-1") {
            $this->bo->stock_id = "";
            $this->bo->status = 0;
            $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable("branch_id");
        } else {
            // nothing to be done here for the timebeing
        }
    }
}

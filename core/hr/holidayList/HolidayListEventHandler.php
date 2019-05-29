<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\holidayList;

/**
 * Description of HolidayListEventHandler
 *
 * @author Valli
 */

class HolidayListEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        if($this->bo->holiday_id == -1){
            $this->bo->holiday_year = \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear');
            
            if(strtotime($this->bo->holiday_date) > strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))){
                $this->bo->holiday_date= \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end');
            }
        }
    }

    public function beforeSave($cn) {            
        parent::beforeSave($cn);
       
    }
    
    
}

<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\grade;

/**
 * Description of gradeEventHandler
 *
 * @author Valli
 */

class GradeEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        
        $this->bo->setTranColDefault('grade_detail', 'leave_days', 0);
    }

    public function beforeSave($cn) {            
        parent::beforeSave($cn);
       
    }
}

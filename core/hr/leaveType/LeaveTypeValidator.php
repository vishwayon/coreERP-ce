<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\leaveType;
use YaLinqo\Enumerable;

/**
 * Description of LeaveTypeValidator
 *
 * @author Valli
 */

class LeaveTypeValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateLeaveTypeEditForm() 
    {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules()
    {
        // Validate duplicate leavetype
  
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select leave_type from hr.leave_type where leave_type ilike :pleave_type and leave_type_id!=:pleave_type_id');
        $cmm->addParam('pleave_type', $this->bo->leave_type);
        $cmm->addParam('pleave_type_id', $this->bo->leave_type_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Leave type already exists. Duplicate Leave type alias not allowed.');
        }
        
        if ($this->bo->paid_leave == 1)
        {
           if ($this->bo->pay_percent ==0)
           {
               $this->bo->addBRule('Pay percent cannot be zero.');
           }
           
           if ($this->bo->pay_percent >100)
           {
               $this->bo->addBRule('Pay percent cannot be greater than 100.');
           }
        }
        
        if ($this->bo->carry_forward_at_yearend == 1)
        {
           if ($this->bo->carry_forward_limit ==0)
           {
               $this->bo->addBRule('Carry forward limit cannot be zero.');
           }
        }
    }
}
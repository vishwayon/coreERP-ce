<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\holidayList;
use YaLinqo\Enumerable;

/**
 * Description of HolidayListValidator
 *
 * @author Valli
 */

class HolidayListValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateHolidayListEditForm() 
    {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules()
    {
       
       // Validate duplicate holiday_date
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select holiday_date from hr.holiday_list where holiday_date=:pholiday_date and holiday_id!=:pholiday_id');
        $cmm->addParam('pholiday_date', $this->bo->holiday_date);
        $cmm->addParam('pholiday_id', $this->bo->holiday_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Holiday date already exists. Duplicate holiday date not allowed.');
        }
    }
}
<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\uoMSchedule;


/**
 * Description of UomScheduleValidator
 *
 * @author Shrishail
 */
class UoMScheduleValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateUoMScheduleEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
        
    }
    
    private function validateBusinessRules() {

        // Validate duplicate Uom Schedule
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select uom_sch_desc from st.uom_sch where uom_sch_desc = :puom_sch_desc '
                            . 'and uom_sch_id != :puom_sch_id  and company_id=:pcompany_id');
        $cmm->addParam('puom_sch_desc', $this->bo->uom_sch_desc);
        $cmm->addParam('puom_sch_id', $this->bo->uom_sch_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('UoM Description already exists. Duplicate UoM Description not allowed.');
        }
        
        // Atleast one Uom required
        if(count($this->bo->uom_sch_item->Rows()) == 0){
            $this->bo->addBRule('Each UoM Schedule should have at least one item.');
        }
        else if(count($this->bo->uom_sch_item->Rows()) > 0){
            // Should have only one base unit
            $checkCount = 0;
            foreach ($this->bo->uom_sch_item->Rows() as $row) {
                if($row['is_base']==true){
                    $checkCount++;
                } 
            }
            if($checkCount == 0){
                $this->bo->addBRule('Each UoM Schedule should have one base unit');
            }
            else if($checkCount > 1){
                $this->bo->addBRule('Only one item is allowed as base unit.');
            }
            else
            {
                foreach ($this->bo->uom_sch_item->Rows() as $row){
                    if($row['is_base'] == true and $row['uom_qty'] <> 1){
                        $this->bo->addBRule('For base unit, UoM quantity should always be 1.');     
                    }    
                }  
            }
        }
    }
}

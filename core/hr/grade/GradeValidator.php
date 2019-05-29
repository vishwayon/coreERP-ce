<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\grade;
use YaLinqo\Enumerable;

/**
 * Description of GradeValidator
 *
 * @author Valli
 */

class GradeValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateGradeEditForm() 
    {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules()
    {
        // Set sl no         
        for ($rowIndex=0;$rowIndex< count ($this->bo->grade_detail->Rows());$rowIndex++) {
            $this->bo->grade_detail->Rows()[$rowIndex]['sl_no']=$rowIndex+1;
        } 
        
        // Validate duplicate Grade 
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select grade from hr.grade where grade ilike :pgrade and grade_id!=:pgrade_id');
        $cmm->addParam('pgrade', $this->bo->grade);
        $cmm->addParam('pgrade_id', $this->bo->grade_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Grade already exists. Duplicate Grade not allowed.');
        }
        
        // Validate duplicate Grade Alias  
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select grade_alias from hr.grade where grade_alias ilike :pgrade_alias and grade_id!=:pgrade_id');
        $cmm->addParam('pgrade_alias', $this->bo->grade_alias);
        $cmm->addParam('pgrade_id', $this->bo->grade_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Grade alias already exists. Duplicate Grade alias not allowed.');
        }
       
        if(count($this->bo->grade_detail->Rows()) == 0){
            $this->bo->addBRule('Grade Detail are required.');
        }
        
        $rowcnt=0;
        foreach ($this->bo->grade_detail->Rows() as $row) { 
            $rowcnt=$rowcnt+1;
            $accCount=0;
            if ($row['leave_days'] < 0 || $row['leave_days']>=365)
            {
                $this->bo->addBRule('Leave days should be in the range 1-365 - Row no ['.$rowcnt.']'); 
            }
            
             if ($row['leave_entitled_per_year'] < 0 || $row['leave_entitled_per_year']>=365)
            {
                $this->bo->addBRule('Leave entitled per year should be in the range 1-365 - Row no ['.$rowcnt.']'); 
            }
            
            foreach ($this->bo->grade_detail->Rows() as $row1) {
                if($row['leave_type_id']==$row1['leave_type_id']){
                    $accCount+=1;
                }
            }
            
            if($accCount>1){
                $this->bo->addBRule('Duplicate Leave types not allowed in Grade detail.'); 
                break;
            }
        }
    }
}
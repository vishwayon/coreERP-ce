<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\paySchedule;
use YaLinqo\Enumerable;
/**
 * Description of PayScheduleValidator
 *
 * @author vaishali
 */
class PayScheduleValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validatePayScheduleEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    protected function validateBusinessRules() {
        if(count($this->bo->pay_schedule_detail_emo_tran->Rows()) == 0 && count($this->bo->pay_schedule_detail_ded_tran->Rows())
            && count($this->bo->pay_schedule_detail_cc_tran->Rows()) == 0){
           $this->bo->addBRule('Atleast one Pay Schedule Detail is required.');
        }         
        
        foreach ($this->bo->pay_schedule_detail_emo_tran->Rows() as &$refemorow){
            $refemorow['description'] =  \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/hr/lookups/Payhead.xml', 'payhead_with_type', 'payhead_id', $refemorow['payhead_id']);
        }
        
        foreach ($this->bo->pay_schedule_detail_ded_tran->Rows() as &$refdedrow){
            $refdedrow['description'] =  \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/hr/lookups/Payhead.xml', 'payhead_with_type', 'payhead_id', $refdedrow['payhead_id']);
        }
        
        foreach ($this->bo->pay_schedule_detail_cc_tran->Rows() as &$refccrow){
            $refccrow['description'] =  \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/hr/lookups/Payhead.xml', 'payhead_with_type', 'payhead_id', $refccrow['payhead_id']);
        }
        
        // validate Emoluments
        $RowNo = 0;
        foreach ($this->bo->pay_schedule_detail_emo_tran->Rows() as $row){
            $RowNo++;
            if($row['pay_on_perc'] > 100 ){
                $this->bo->addBRule('Pay Schedule Detail(s) Emoluments - Row[' . $RowNo . '] : Pay On Percentage cannot be greater than 100.');     
            } 
            if($row['en_pay_type'] == 0 && $row['parent_pay_schedule_details'] = ''){
                $this->bo->addBRule('Pay Schedule Detail(s) Emoluments- Row[' . $RowNo . '] : Select atleast one Parent Details if calculation type is Percent Of Amount.');     
            } 
        }

        $list = Enumerable::from($this->bo->pay_schedule_detail_emo_tran->Rows())->groupBy('$a==>$a["payhead_id"]')->toList();
        foreach($list as $groupKey => $groupData) {
            if(count($groupData) >1){
               $this->bo->addBRule('Pay Schedule Detail(s) Emoluments- Duplicate Pay Schedule details not allowed.');
            }
        }

        // validate deductions
        $RowNo = 0;
        foreach ($this->bo->pay_schedule_detail_ded_tran->Rows() as $row){
            $RowNo++;
            if($row['pay_on_perc'] > 100 ){
                $this->bo->addBRule('Pay Schedule Detail(s) Deductions - Row[' . $RowNo . '] : Pay On Percentage cannot be greater than 100.');     
            } 
            if($row['en_pay_type'] == 0 && $row['parent_pay_schedule_details'] = ''){
                $this->bo->addBRule('Pay Schedule Detail(s) Deductions- Row[' . $RowNo . '] : Select atleast one Parent Details if calculation type is Percent Of Amount.');     
            } 
        }
        $list = Enumerable::from($this->bo->pay_schedule_detail_ded_tran->Rows())->groupBy('$a==>$a["payhead_id"]')->toList();
        foreach($list as $groupKey => $groupData) {
            if(count($groupData) >1){
               $this->bo->addBRule('Pay Schedule Detail(s) Deductions- Duplicate Pay Schedule details not allowed.');
            }
        }

        // validate company contribution
        $RowNo = 0;
        foreach ($this->bo->pay_schedule_detail_cc_tran->Rows() as $row){
            $RowNo++;
            if($row['pay_on_perc'] > 100 ){
                $this->bo->addBRule('Pay Schedule Detail(s) Company Contributions - Row[' . $RowNo . '] : Pay On Percentage cannot be greater than 100.');     
            } 
            if($row['en_pay_type'] == 0 && $row['parent_pay_schedule_details'] = ''){
                $this->bo->addBRule('Pay Schedule Detail(s) Company Contributions- Row[' . $RowNo . '] : Select atleast one Parent Details if calculation type is Percent Of Amount.');     
            } 
        }
        $list = Enumerable::from($this->bo->pay_schedule_detail_cc_tran->Rows())->groupBy('$a==>$a["payhead_id"]')->toList();
        foreach($list as $groupKey => $groupData) {
            if(count($groupData) >1){
               $this->bo->addBRule('Pay Schedule Detail(s) Company Contributions- Duplicate Pay Schedule details not allowed.');
            }
        }
        
        // Validate duplicate Pay schedule
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select description from hr.pay_schedule where description ilike :pdescription and pay_schedule_id!=:ppay_schedule_id');
        $cmm->addParam('pdescription', $this->bo->description);
        $cmm->addParam('ppay_schedule_id', $this->bo->pay_schedule_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::COMPANY_DB);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Pay Schedule already exists. Duplicate Pay Schedule not allowed.');        
        } 
    }
        
    public function validatePayScheduleEditFormBeforeDelete() {
        // conduct default form validations
        $this->validateBeforeDelete($this->bo);
    }
}

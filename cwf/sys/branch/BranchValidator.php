<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\branch;
use YaLinqo\Enumerable;


/**
 * Description of BranchValidator
 *
 * @author Shrishail
 */
class BranchValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateBranchEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
     }
    
    protected function validateBusinessRules() {        
         // Validate duplicate Branch name
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select branch_id, branch_name, company_id from sys.branch '
                . 'where company_id=:pcompany_id and branch_name=:pbranch_name '
                . 'and branch_id!=:pbranch_id');
        $cmm->addParam('pbranch_id', $this->bo->branch_id);
        $cmm->addParam('pbranch_name', $this->bo->branch_name);
        $cmm->addParam('pcompany_id', $this->bo->company_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Branch Name already exists. Duplicate Branch Name not allowed.');}
     
        // Validate duplicate Branch code
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select branch_id , branch_code, company_code from sys.branch '
                . 'where company_code=:pcompany_code and branch_code=:pbranch_code '
                . 'and branch_id!=:pbranch_id');
        $cmm->addParam('pbranch_id', $this->bo->branch_id);
        $cmm->addParam('pbranch_code', $this->bo->branch_code);
        $cmm->addParam('pcompany_code', $this->bo->company_code);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Branch code already exists. Duplicate Branch code not allowed.');
        }
        
        //update gstin info from branch tax info tran to branch field gstin
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select tax_info_type_id from sys.tax_info_type where parameter = :pparameter');
        $cmm->addParam('pparameter', 'GSTIN');
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows())>0) {
            $gstn_arr = Enumerable::from($this->bo->branch_tax_info->Rows())->where('$a==>$a["tax_info_type_id"] == '.$dt->Rows()[0]['tax_info_type_id'])->toList();
            if(count($gstn_arr) == 1){
                $this->bo->gstin = $gstn_arr[0]['branch_tax_info_desc'];
            }
        }
        
        // validate gst state code with gstin no
        $state_code = substr(\app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/tx/lookups/GstState.xml', 'gst_state_with_code', 'gst_state_id', $this->bo->gst_state_id), 0, 2);
        if(substr($this->bo->gstin, 0, 2) != $state_code){
            $this->bo->addBRule('GSTIN does not belong to GST State.');
        }
        if($this->bo->gstin != substr($this->bo->gst_state_id, 0, 2)){
            if(!preg_match("/^[0-9]{2}[a-zA-Z]{5}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[Z]{1}[0-9a-zA-Z]{1}$/", $this->bo->gstin)){
                $this->bo->addBRule('Tax Regn Details : Invalid GSTIN.');
            }        
        }
    }
}

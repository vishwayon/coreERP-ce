<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\xmlbo;
use app\cwf\vsla\design;
include_once '../cwf/vsla/design/CommonTypes.php';
use \app\cwf\vsla\design\RelationType;

/**
 * Description of ValidatorBase
 *
 * @author girish
 */
abstract class ValidatorBase {
    
    /**@var BoBase */
    protected $bo;
    protected $modulePath;
    protected $formName;
    protected $xmlFormPath;
    protected $validateMethod;
    protected $postData;
    protected $action;
    /**@var BoParser */
    protected $xBo;
    protected $sarrogateFields='';
    protected $excludeTables='';
    private $primaryKey='';
    /**
    *  @param string $formName
     *      */
    public function initialise(BoBase $bo, $formName = '', $xmlFormPath = '', BoParser $xBo=NULL, $modulePath = '', $postData = null, $action = null) {
        $this->bo = $bo;
        $this->formName = $formName;
        $this->validateMethod = 'validate'.$formName;
        $this->xmlFormPath = $xmlFormPath;
        $this->xBo=$xBo;
        $this->modulePath = $modulePath;
        $this->postData = $postData;
        $this->action = $action;
    }
    
    public function getValidateMethod() {
        return $this->validateMethod;
    }
    
    /**
     * 
     * @param \PDO $cn
     * @param array $generatedKeys
     */
    public function onSave($cn, $generatedKeys) {
        
    }
    
    /**
     * Validates the BO based on properties mentioned in the form xml
     * @param \app\cwf\vsla\xmlbo\BoBase $bo
     * @param \app\cwf\vsla\ui\viewparser $formInfo
     */   
    protected function validateUsingForm(\app\cwf\vsla\xmlbo\BoBase $bo, design\FormView $formView) {
        // First validate for last_updated
        if(!$this->docIsCurrent()) {
            if($bo instanceof MastBo) {
                $bo->addBRule('The version of item being edited, is not current. Please re-open the item and make changes.');
            } else {
                $bo->addBRule('The version of document being edited, is not current. Please re-open the document and make changes.');
            }
            return;
        }
        
        // Do other form validations
        if($formView->controlSection->editMode->allowEdit){
            $brules=array();
            foreach ($formView->controlSection->dataBinding->items as $fld){
                if($fld instanceof design\IDataBindingItem) {
                    $brules = $this->validateIDataBingingItem($fld, $bo);
                    foreach ($brules as $br) {
                        $bo->addBRule($br);
                    }
                }
            }
        }
    }
    
    protected function validateIDataBingingItem(design\IDataBindingItem $fld, $data, $row_id = -1){
        $itembrules=array();
        switch($fld->getType()){
            case design\IDataBindingItem::TYPE_FIELD :
                if($fld->isOptional || $fld->readOnly || $fld->control == design\ControlType::LABEL){break;}
                $fld_id = $fld->id;
                if(strpos($fld_id, ".")>0) {                     
                    $val = $this->get_val($data, $fld_id);
                } else {
                    $val = $row_id==-1? $data->$fld_id : $data[$fld_id];
                }
                $itembrules = $this->validateFormField($fld, $val);
                break;
            case design\IDataBindingItem::TYPE_CUSTOM_FIELD :
                if($fld->isOptional || $fld->readOnly || $fld->control == design\ControlType::LABEL){break;}
                $fld_id = $fld->id;
                $val = $row_id==-1? $data->$fld_id : $data[$fld_id];
                $itembrules = $this->validateFormField($fld, $val);
                break;
            case design\IDataBindingItem::TYPE_TRAN_SECTION :   
                $tranname = $fld->dataBinding->dataProperty;
                $trantable = $row_id==-1? $data->$tranname : $data[$tranname];
                $row_count = 1;
                if($trantable instanceof \app\cwf\vsla\data\DataTable) {
                    foreach ($trantable->Rows() as $rw){
                        foreach($fld->dataBinding->items as $tranfld){
                            if($tranfld instanceof design\IDataBindingItem) {
                                $brs = $this->validateIDataBingingItem($tranfld, $rw, $row_count);
                                foreach($brs as $br){
                                    array_push($itembrules, $fld->label.' - Row['.$row_count.'] : '.$br);
                                }
                            }
                        }
                        $row_count++;
                    }
                }
                break;
        }
        return $itembrules;
    }
    
    protected function get_val($data, $fld_id) {
        $paths = explode(".", $fld_id);
        $obj = $data;
        foreach($paths as $path) {
            $obj = $obj->$path;
            if($obj instanceof \app\cwf\vsla\data\JsonField) {
                $obj = $obj->Value();
            }
        }
        return $obj;
    }
    
    protected function validateFormField(design\FormField $fld, $val){
        $brules=array();
        switch ($fld->type){
        case design\FieldType::INT :
            if(!is_numeric($val) or is_null($val)) {
                array_push($brules, $fld->label.' is required');
            } else {
                if (intval($val) < 0) {
                    array_push($brules, $fld->label.' is required');
                }
            }

            break;
        case design\FieldType::DECIMAL :
            if(!is_numeric($val) or is_null($val)) {
                array_push($brules, $fld->label.' is required');
            } else {
                if(!$fld->allowNegative && floatval ($val) < 0){
                    array_push($brules, $fld->label.' cannot be negative');
                }
                if(!$fld->isOptional && floatval($val)==0){
                    array_push($brules, $fld->label. ' is required');
                }
            }
            break;
        case design\FieldType::STRING :
            if(is_null($val) or strlen($val) == 0 ) {
                array_push($brules, $fld->label.' is required');
            }
            break;
        case design\FieldType::DATE :
            if(is_null($val)){
                array_push($brules, $fld->label.' is required');
            } else if($fld->range == 'finYear') {
                if(!$this->validateDateValue($val)) {
                    array_push($brules, $fld->label.' is not a valid date for selected financial year');
                }
                if($this->docFiscalMonthClosed($val)) {
                     array_push($brules, 'Document date belongs to a closed fiscal month. Edit/post not allowed.');
                }
            }
            break;
        case design\FieldType::DATETIME :
            if(is_null($val)){
                array_push($brules, $fld->label.' is required');
            } else if($fld->range == 'finYear'){
                if(!$this->validateDateTimeValue($val)){
                    array_push($brules, $fld->label.' is not a valid datetime for selected financial year');
                }
                if($this->docFiscalMonthClosed($val)) {
                     array_push($brules, 'Document date belongs to a closed fiscal month. Edit/post not allowed.');
                }
            }
            break;
        default :
            break;
        }
        return $brules;
    }


    protected function validateDateValue($datevalue){
        if(!preg_match('/^[0-9]{4}-[0-1][0-9]-[0-3][0-9]$/', $datevalue)){
            return false;
        }
        $yearbegin = strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
        $yearend = strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
        $dtvalue = strtotime($datevalue);
        if($dtvalue<$yearbegin || $dtvalue>$yearend){
            return false;
        } else {
            return true;
        }
    }
    
    protected function validateDateTimeValue($datevalue){
        if(!preg_match('/^[0-9]{4}-[0-1][0-9]-[0-3][0-9] [0-2][0-9]:[0-5][0-9]:[0-5][0-9]$/', $datevalue)){
            return false;
        }
        $yearbegin = strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
        $yearend = strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
        $dtvalue = strtotime($datevalue);
        if($dtvalue<$yearbegin || $dtvalue>$yearend){
            return false;
        } else {
            return true;
        }
    }
    
    protected function validateEmail($emailvalue){
        if (filter_var($emailvalue, FILTER_VALIDATE_EMAIL)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

        public function validateBeforeDelete(){
        $includeField='';
        $excludeTable='';
        $excludeTableArray=array();
        $includeFieldArray=array();
        
        if ($this->xBo!=null){            
            $metatable=$this->xBo->bometa->controlTable;
            $this->excludeTables= $metatable->tableName;
            if($this->xBo->bometa->type === BoType::DOCUMENT){              
                $this->excludeTables= $this->excludeTables . ',sys.doc_es';
            }
            
            $this->primaryKey = $metatable->primaryKey;
            // Get primary key value
            $pkValue=$this->bo[$metatable->primaryKey];
            
            // Add primary field in include field list
            $this->sarrogateFields = $metatable->primaryKey;

            // push all tran table in do not validate list
            if(isset($metatable->tranTable)){
                foreach ($metatable->tranTable as $tranname => $tranTable) {
                    $this->findExcludeTables($metatable->tranTable[$tranname]);
                }
            }  
            
            // Push All elongated table in do not validate list
            foreach ($metatable->tableElongs as $met) {
                $this->excludeTables= $this->excludeTables . ', ' . $met->tableName;                
            }
            
            // Esclude table from bkp and de schema -- these are back up tables
            $cmm=new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("SELECT (table_schema || '.' || table_name) as bkp_table_name FROM information_schema.tables WHERE table_schema in ('bkp', 'de')");
            $dt_bkp=  \app\cwf\vsla\data\DataConnect::getData($cmm, $this->bo->connectionType);
            foreach($dt_bkp->Rows() as $row){ 
                $this->excludeTables= $this->excludeTables . ', ' . $row['bkp_table_name'];
            }
            
            // add excludeTables tables in Donot Validate list
            if($this->xBo->bometa->docDelete){
                if($this->xBo->bometa->docDelete->excludeTables!=''){
                    if($this->excludeTables ==''){
                        $this->excludeTables=  $this->xBo->bometa->docDelete->excludeTables;                     
                    }
                    else{
                        $this->excludeTables=$this->excludeTables . ','. $this->xBo->bometa->docDelete->excludeTables;   
                    }
                }
                // Add Sarrogate fields in include field list  
                if($this->xBo->bometa->docDelete->sarrogateFields!=''){ 
                    if($this->sarrogateFields==''){                    
                        $this->sarrogateFields= $this->xBo->bometa->docDelete->sarrogateFields;     
                    }
                    else{
                        $this->sarrogateFields= $this->sarrogateFields . ',' . $this->xBo->bometa->docDelete->sarrogateFields;     
                    }
                }
            }

        
            if($this->xBo->bometa->controlTable->isExtended){
                $this->validateExtendedTable($this->bo, $this->xBo->bometa->controlTable->extnTable);
            }
            
            // split and push donot validate list in exclude table array
            $excludeTableArray=explode(',', $this->excludeTables);
            $includeFieldArray=explode(',', $this->sarrogateFields);
            
            // Split and concat exclude tables in single quote
            foreach($excludeTableArray as $str ){
                if($excludeTable=='')    {
                    $excludeTable="'".$str."'";
                }
                else{
                    $excludeTable=$excludeTable.", '".trim($str)."'";
                }
            }
            
            // Split and concat sarrogate fields in single quote
            foreach($includeFieldArray as $str ){
                if($includeField=='')    {
                    $includeField="'".$str."'";
                }
                else{
                    $includeField=$includeField.", '".trim($str)."'";
                }
            }
            
            $errmsg='';
            $strQuery='';
            $strQuery='select a.table_schema, a.table_name, a.column_name from information_schema.columns a '
                        . " where a.column_name in (". $includeField .")"
                        . " and a.table_schema||'.'||a.table_name not in (" . $excludeTable . ")";

            $cmm=new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText($strQuery);       
            $dt=  \app\cwf\vsla\data\DataConnect::getData($cmm, $this->bo->connectionType);
            foreach($dt->Rows() as $row){     
                $strsql='';
                $strsql='Select count(*) as cnt from ' . $row['table_schema']. '.' . $row['table_name'] . ' where ' . $row['column_name'] . '=:ppkValue';
                $cmmt=new \app\cwf\vsla\data\SqlCommand();
                $cmmt->addParam('ppkValue', $pkValue);
                $cmmt->setCommandText($strsql);       
                $dtRec=  \app\cwf\vsla\data\DataConnect::getData($cmmt, $this->bo->connectionType);
                if(count($dtRec->Rows())>0){
                    if($dtRec->Rows()[0]['cnt']>0){
                        if($errmsg==''){
                            $errmsg=$row['table_schema']. '.' . $row['table_name'] . ' - ' . (string)$dtRec->Rows()[0]['cnt'] . ' Rows';
                        }
                        else{                            
                            $errmsg=$errmsg. ', ' . $row['table_schema']. '.' . $row['table_name'] . ' - ' .  (string)$dtRec->Rows()[0]['cnt'] . ' Rows';
                        }
                    }
                }
            }
        }
        
        if($errmsg!=''){                
            $this->bo->addBRule( 'This ' .$this->primaryKey.' cannot be deleted since it is used in the following database objects. ' . $errmsg);
        }        
    }
    
    private function findExcludeTables($metatable){
        if($this->excludeTables ==''){
            $this->excludeTables= $metatable->tableName;                     
        }
        else{
            $this->excludeTables=$this->excludeTables . ','. $metatable->tableName; 
            if(isset($metatable->tranTable)){
                foreach ($metatable->tranTable as $childtranname => $tranTable) {
                    $this->findExcludeTables($metatable->tranTable[$childtranname]);
                }
            }
        }
        foreach ($metatable->tableElongs as $met) {
            $this->excludeTables= $this->excludeTables . ', ' . $met->tableName;                
        }

    }
            
    private function validateExtendedTable(\app\cwf\vsla\xmlbo\BoBase $bo, $metatable){
        $includeField='';
        $excludeTable='';
        $excludeTableArray=array();
        $includeFieldArray=array();
        
        $this->primaryKey = $metatable->primaryKey;
        
        if($this->excludeTables ==''){
           $this->excludeTables= $metatable->tableName;                     
        }
        else{
            $this->excludeTables=$this->excludeTables . ','. $metatable->tableName;   
        } 
        
        foreach ($metatable->tableElongs as $met) {
            $this->excludeTables= $this->excludeTables . ', ' . $met->tableName;                
        }
        
        // Add primary field in include field list   
        if($this->sarrogateFields==''){                    
            $this->sarrogateFields= $metatable->primaryKey;     
        }
        else{
            $this->sarrogateFields= $this->sarrogateFields . ',' . $metatable->primaryKey;     
        }

        // push all tran table in do not validate list
        if(isset($metatable->tranTable)){
            foreach ($metatable->tranTable as $tranname => $tranTable) {
                if($this->excludeTables ==''){
                    $this->excludeTables= $metatable->tranTable[$tranname]->tableName;                     
                }
                else{
                    $this->excludeTables=$this->excludeTables . ','. $metatable->tranTable[$tranname]->tableName;   
                }
                foreach ($metatable->tranTable[$tranname]->tableElongs as $met) {
                    $this->excludeTables= $this->excludeTables . ', ' . $met->tableName;                
                }
            }
        } 
        
        if($metatable->isExtended){
            $this->validateExtendedTable($bo, $metatable->extnTable);
        }
    }
    
    protected function docIsCurrent() {
        if(isset($this->xBo)) {
            // Validate only the base table as it contains last_updated
            $metaTable = $this->xBo->bometa->controlTable;
            // for a new document, always return true
            if($this->bo[$metaTable->primaryKey] == '' || $this->bo[$metaTable->primaryKey] == -1) {
                return true;
            }
            // existing document is being updated. Proceed to validate
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $sql = 'Select last_updated From '.$metaTable->tableName.
                   ' Where '.$metaTable->primaryKey.'=:ppk_id;';
            $cmm->setCommandText($sql);
            $cmm->addParam('ppk_id', $this->bo[$metaTable->primaryKey]);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, $this->bo->connectionType);
            if(count($dt->Rows())==1) {
                if(strtotime($dt->Rows()[0]['last_updated']) == strtotime($this->bo->last_updated)) {
                    return true;
                }
            }
        } else {
            throw new \Exception('Failed to validate if item/document is current. Provide parser info for validatorBase['.$this->bo['__bo'].']');
        }
        return false;
    }
    
    public function docFiscalMonthClosed($val) : bool {
        // This is a loose validation. If no fiscal months exist, then the doc is allowed to be saved.
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select month_close From sys.fiscal_month Where month_begin<=:pdoc_date And month_end>=:pdoc_date And company_id=:pcompany_id And finyear=:pfinyear');
        $i = $this->bo;
        $cmm->addParam('pdoc_date', $val);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID());
        $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows())==1) {
            if(boolval($dt->Rows()[0]['month_close'])) {
                return true;
            }
        }
        return false;
    }    
    
    public function validateBeforeStage(\app\cwf\vsla\workflow\WfOption $wfOption) {
        // do nothing. for overriding purposes only
        \yii::beginProfile('val-test');
         \yii::endProfile('val-test');
    }
    
    public function validateBeforeArchive($action){
        
    }
}

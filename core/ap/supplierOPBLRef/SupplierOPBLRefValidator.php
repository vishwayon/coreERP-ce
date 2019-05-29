<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\supplierOPBLRef;


/**
 * Description of Supplier OPBLRef
 *
 * @author Priyanka
 */
class SupplierOPBLRefValidator  extends \app\cwf\vsla\xmlbo\ValidatorBase  {
    
    public function validateSupplierOPBLRefEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
     }
    
    public function validateBusinessRules() {
        foreach ($this->bo->supplier_payable_ledger->Rows() as &$refpl_row) { 
            if($refpl_row['fc_type_id']== 0){
                $refpl_row['debit_amt_fc']= 0;
                $refpl_row['credit_amt_fc']= 0;
            }
            else{
               $refpl_row['debit_amt']=round(($refpl_row['debit_amt_fc'] * $refpl_row['exch_rate']), \app\cwf\vsla\Math::$amtScale);
               $refpl_row['credit_amt']=round(($refpl_row['credit_amt_fc'] * $refpl_row['exch_rate']), \app\cwf\vsla\Math::$amtScale);
            }
        }
        
        //Invoice Ref No is blank & invoice date validation
        foreach($this->bo->supplier_payable_ledger->Rows() as $row){
            if($row['voucher_id'] == '' ){                
                $this->bo->addBRule('Bill Ref No. cannot be blank'); 
            }
            
            if(strtotime($row['doc_date'])>= strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'))){                
                $this->bo->addBRule('Bill Date should be less than Year Begin ('.\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin')).')'); 
            }
        }
        
        //Validation for Duplicate Bill Ref No
        foreach ($this->bo->supplier_payable_ledger->Rows() as $row) { 
            $RowNo = 0;
            $Count = 0;
            if($row['voucher_id']!='')   {
                foreach ($this->bo->supplier_payable_ledger->Rows() as $row1) {
                    $RowNo++;
                    if($row['voucher_id']==$row1['voucher_id']){
                        $Count++;
                    }
                    if($Count>1)
                    {
                        $this->bo->addBRule('Supplier OPBL Details - Row['. $RowNo . '] : Duplicate Invoice Ref No not allowed.');    
                        break;
                    } 
                }
                if($Count>1)
                {
                    break;
                } 
            }
        }
        
        //Validation for Debit amount and credit amount 
        $RowNo = 0;
        foreach ($this->bo->supplier_payable_ledger->Rows() as $row) {
            $RowNo++;
            if($row['debit_amt']==0 AND $row['credit_amt']==0)
            {
                $this->bo->addBRule('Supplier OPBL Details - Row['. $RowNo . '] : Enter either Debit Amt or Credit Amt.'); 
            }
            if($row['debit_amt']!=0 AND $row['credit_amt']!=0)
            {
                $this->bo->addBRule('Supplier OPBL Details - Row['. $RowNo . '] : Both Debit Amt and Credit Amt cannot have value.'); 
            }
        }
        
        $RowNo = 0;
        foreach($this->bo->supplier_payable_ledger_temp->Rows() as $temprow){
            $RowNo++;
            $deletedrow=true;
            foreach($this->bo->supplier_payable_ledger->Rows() as $row){
                if($row['rl_pl_id']==$temprow['rl_pl_id']){
                    $deletedrow=false;
                    break;
                }
                $deletedrow=TRUE;
            }

            if($deletedrow){ 
                // Check if the opening balance is used in any voucher                     
                $cmm_alloc = new \app\cwf\vsla\data\SqlCommand();
                $cmm_alloc->setCommandText('Select a.voucher_id from ac.rl_pl_alloc a
                                            where a.rl_pl_id = :prl_pl_id');                    
                $cmm_alloc->addParam('prl_pl_id', $temprow['rl_pl_id']);
                $dt_alloc=\app\cwf\vsla\data\DataConnect::getData($cmm_alloc); 
                if(count($dt_alloc->Rows()) >0){
                    $this->bo->addBRule('Supplier OPBL Details - Row['. $RowNo . '] : Delete not allowed as Bill ref no already used in the other documents.'); 
                }
            }
        }            
    }
    
    protected function docIsCurrent() {
        // Overridden as last updated validation is not required.
        // And anchoring table ar.customer is for cwf purposes only
        return true;
    }
}

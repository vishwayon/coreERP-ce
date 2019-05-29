<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\customerOPBLRef;


/**
 * Description of Customer OPBL Ref
 *
 * @author Kaustubh
 */
class CustomerOPBLRefValidator  extends \app\cwf\vsla\xmlbo\ValidatorBase  {
    
    public function validateCustomerOPBLRefEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
     }
    
    public function validateBusinessRules() {
         foreach ($this->bo->customer_receivable_ledger->Rows() as &$refrow) { 
             if($refrow['fc_type_id']== 0){
                 $refrow['debit_amt_fc']= 0;
                 $refrow['credit_amt_fc']= 0;
             }
             else{
                $refrow['debit_amt']=round(($refrow['debit_amt_fc'] * $refrow['exch_rate']), \app\cwf\vsla\Math::$amtScale);
                $refrow['credit_amt']=round(($refrow['credit_amt_fc'] * $refrow['exch_rate']), \app\cwf\vsla\Math::$amtScale);
             }
         }
        //If no row is present
//        if(count($this->bo->customer_receivable_ledger->Rows())==0) {
//            $this->bo->addBRule('At least one row is required in Customer OPBL Details.');
//        } 
        
        //Invoice Ref No is blank & invoice date validation
        $rowcnt=0;
        foreach($this->bo->customer_receivable_ledger->Rows() as $row){           
            $rowcnt= $rowcnt+1;
            if(strtotime($row['doc_date'])>= strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'))){                
                $this->bo->addBRule('Customer Opening Bill Details - Row['.$rowcnt .'] : Invoice Date should be less than Year Begin ('.\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin')).')'); 
            }
        }
        
        //Validation for Duplicate Invoice Ref No
        foreach ($this->bo->customer_receivable_ledger->Rows() as $row) { 
            $RowNo = 0;
            $Count = 0;
            if($row['voucher_id']!='')   {
                foreach ($this->bo->customer_receivable_ledger->Rows() as $row1) {
                    $RowNo++;
                    if($row['voucher_id']==$row1['voucher_id']){
                        $Count++;
                    }
                    if($Count>1)
                    {
                        $this->bo->addBRule('Customer Opening Bill Details - Row['. $RowNo . '] : Duplicate Invoice Ref No not allowed.');    
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
        foreach ($this->bo->customer_receivable_ledger->Rows() as $row) {
            $RowNo++;
            if($row['debit_amt']==0 AND $row['credit_amt']==0)
            {
                $this->bo->addBRule('Customer Opening Bill Details - Row['. $RowNo . '] : Enter either Debit Amt or Credit Amt.'); 
            }
            if($row['debit_amt']!=0 AND $row['credit_amt']!=0)
            {
                $this->bo->addBRule('Customer Opening Bill Details - Row['. $RowNo . '] : Both Debit Amt and Credit Amt cannot have value.'); 
            }
        }
        
        $RowNo = 0;
        foreach($this->bo->customer_receivable_ledger_temp->Rows() as $temprow){
            $RowNo++;
            $deletedrow=true;
            foreach($this->bo->customer_receivable_ledger->Rows() as $row){
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
                    $this->bo->addBRule('Customer OPBL Details - Row['. $RowNo . '] : Delete not allowed as Invoice ref no already used in the other documents.'); 
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

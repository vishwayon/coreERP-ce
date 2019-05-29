<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\subHeadOpbl;


/**
 * Description of SubHeadOpbl
 *
 * @author Priyanka
 */
class SubHeadOpblValidator  extends \app\cwf\vsla\xmlbo\ValidatorBase  {
    
    public function validateSubHeadOpblEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
     }
    
    public function validateBusinessRules() {
        
        //Invoice Ref No is blank & invoice date validation
        foreach($this->bo->sub_head_ledger->Rows() as $row){ 
            if(strtotime($row['doc_date'])>= strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'))){                
                $this->bo->addBRule('Details - Row['. $row['sl_no'] .'] : '.$row['sub_head'].' - Balance Date should be less than Year Begin ('.\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin')).')'); 
            }
        }

          
        //Validation for Debit amount and credit amount
        foreach ($this->bo->sub_head_ledger->Rows() as $row) {
            if($row['debit_amt']!=0 && $row['credit_amt']!=0)
            {
                $this->bo->addBRule('Details - Row['. $row['sl_no'] . '] : '.$row['sub_head'].' - Both Debit Amt and Credit Amt cannot have value.'); 
            }
        }
//        
//        $RowNo = 0;
//        foreach($this->bo->customer_receivable_ledger_temp->Rows() as $temprow){
//            $RowNo++;
//            $deletedrow=true;
//            foreach($this->bo->customer_receivable_ledger->Rows() as $row){
//                if($row['rl_pl_id']==$temprow['rl_pl_id']){
//                    $deletedrow=false;
//                    break;
//                }
//                $deletedrow=TRUE;
//            }
//
//            if($deletedrow){ 
//                // Check if the opening balance is used in any voucher                     
//                $cmm_alloc = new \app\cwf\vsla\data\SqlCommand();
//                $cmm_alloc->setCommandText('Select a.voucher_id from ac.rl_pl_alloc a
//                                            where a.rl_pl_id = :preceivable_ledger_id');                    
//                $cmm_alloc->addParam('preceivable_ledger_id', $temprow['rl_pl_id']);
//                $dt_alloc=\app\cwf\vsla\data\DataConnect::getData($cmm_alloc); 
//                if(count($dt_alloc->Rows()) >0){
//                    $this->bo->addBRule('Customer OPBL Details - Row['. $RowNo . '] : Delete not allowed as Invoice ref no already used in the other documents.'); 
//                }
//            }
//        }          
    }
}

<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tds\tdsPayment;

/**
 * Description of TDSPaymentEventHandler
 *
 * @author Priyanka
 */
class TDSPaymentEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        if($this->bo->voucher_id=="" or $this->bo->voucher_id=="-1")
        {
            $this->bo->voucher_id="";
            //$this->bo->cheque_number="0"; 
            $this->bo->status=0;
            $this->bo->company_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
            $this->bo->annex_info->Value()->person_type_id = $criteriaparam['formData']['SelectPersonType']['person_type_id'];
            
            $sl_no = 0;
            // Fill Bill TDS Tran
            foreach($criteriaparam['formData']['SelectBill'] as $pltran){
                $sl_no = $sl_no + 1;
                $newRow=$this->bo->bill_tds_tran->newRow();
                $newRow['company_id']= \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
                $newRow['bill_tds_tran_id']= $pltran['voucher_id'];
                $newRow['voucher_id']= $pltran['voucher_id'];
                $newRow['doc_date']= $pltran['doc_date'];
                $newRow['supplier_id']= $pltran['supplier_id'];
                $newRow['supplier']= $pltran['supplier'];
                $newRow['bill_amt']= $pltran['bill_amt'];
                $newRow['tds_amt']= $pltran['tds_amt']; 
                $newRow['branch_id']= $pltran['branch_id']; 
                $this->bo->bill_tds_tran->AddRow($newRow);                
            }
        } 
        else{
            foreach ($this->bo->bill_tds_tran->Rows() as &$reftds_row){
                $reftds_row['supplier']= \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ap/lookups/Supplier.xml', 'supplier', 'supplier_id', $reftds_row['supplier_id']);
                $reftds_row['tds_amt']= round($reftds_row['tds_base_rate_amt']+$reftds_row['tds_ecess_amt']+$reftds_row['tds_surcharge_amt'], \app\cwf\vsla\Math::$amtScale);
            }
        }
    }
    
    public function onSave($cn, $tablename) {
        parent::onSave($cn, $tablename);
         if($tablename=='tds.bill_tds_tran'){    
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Update tds.bill_tds_tran Set payment_id=''"
                                 . " WHERE payment_id=:ppayment_id ");
            $cmm->addParam('ppayment_id', $this->bo->voucher_id);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
            
            
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Update tds.bill_tds_tran Set payment_id=:ppayment_id, payment_date=:ppayment_date'
                                 . ' WHERE bill_tds_tran_id=:pbill_tds_tran_id ');            
            
            foreach($this->bo->bill_tds_tran->Rows() as &$ref_row){    
                $bill_tds_tran_id=$ref_row['voucher_id'];
                $cmm->addParam('ppayment_id', $this->bo->voucher_id);
                $cmm->addParam('ppayment_date', $this->bo->doc_date);
                $cmm->addParam('pbill_tds_tran_id', $ref_row['voucher_id']);
                
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                $ref_row['payment_id'] = $this->bo->voucher_id;
                $ref_row['payment_date'] = $this->bo->doc_date;
            }
        }
    }
    
    public function onDelete($cn, $tablename) {
        parent::onDelete($cn, $tablename);
        if($tablename=='tds.bill_tds_tran'){   
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Update tds.bill_tds_tran Set payment_id=:ppayment_id, payment_date=:ppayment_date'
                                 . ' WHERE bill_tds_tran_id=:pbill_tds_tran_id ');            
            
            foreach($this->bo->bill_tds_tran->Rows() as $row){    
                $bill_tds_tran_id=$row['bill_tds_tran_id'];
                $cmm->addParam('ppayment_id', '');
                $cmm->addParam('ppayment_date', null);
                $cmm->addParam('pbill_tds_tran_id', $row['bill_tds_tran_id']);
                
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
            }
        }
    }
}

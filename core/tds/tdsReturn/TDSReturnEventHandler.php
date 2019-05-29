<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tds\tdsReturn;

/**
 * Description of TDSReturnEventHandler
 *
 * @author Priyanka
 */
class TDSReturnEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        if($this->bo->voucher_id=="" or $this->bo->voucher_id=="-1")
        {
            $this->bo->voucher_id="";
            //$this->bo->cheque_number="0"; 
            $this->bo->status=0;
        } 
        else{
            
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select a.account_id, b.account_head, a.tds_total_amt, a.interest_amt, a.penalty_amt, a.amt	
                                    From tds.tds_payment_control a 
                                    Inner Join ac.account_head b on a.account_id = b.account_id
                                    where a.voucher_id =:ppayment_id ');            
            
            foreach($this->bo->tds_return_challan_tran->Rows() as &$ref_challan_row){    
                $cmm->addParam('ppayment_id', $ref_challan_row['payment_id']);
                $dt= \app\cwf\vsla\data\DataConnect::getData($cmm);
                
                if(count($dt->Rows()) > 0){
                    $ref_challan_row['account_head']=$dt->Rows()[0]['account_head'];
                    $ref_challan_row['tds_total_amt']=$dt->Rows()[0]['tds_total_amt'];
                    $ref_challan_row['interest_amt']=$dt->Rows()[0]['interest_amt'];
                    $ref_challan_row['penalty_amt']=$dt->Rows()[0]['penalty_amt'];
                    $ref_challan_row['tds_payment_amt']=$dt->Rows()[0]['amt'];
                }
            }
            
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select a.bill_tds_tran_id, a.voucher_id, a.bill_amt, a.doc_date, a.supplier_id, b.supplier, 
                                                    a.tds_base_rate_amt, a.tds_ecess_amt, a.tds_surcharge_amt		
                                    from tds.bill_tds_tran a
                                    Inner Join ap.supplier b on a.supplier_id = b.supplier_id
                                    where a.payment_id =:ppayment_id');            
            
            foreach($this->bo->tds_return_challan_tran->Rows() as &$ref_row){    
                $cmm->addParam('ppayment_id', $ref_row['payment_id']);
                $dt_tds= \app\cwf\vsla\data\DataConnect::getData($cmm);
                
                foreach($dt_tds->Rows() as $row){
                    $newRow=$ref_row['bill_tds_tran']->newRow();
                    $newRow['company_id']= \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
                    $newRow['branch_id']= \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
                    $newRow['bill_tds_tran_id']= $row['bill_tds_tran_id'];
                    $newRow['voucher_id']= $row['voucher_id'];
                    $newRow['doc_date']= $row['doc_date'];
                    $newRow['supplier_id']= $row['supplier_id'];
                    $newRow['supplier']= $row['supplier'];
                    $newRow['bill_amt']= $row['bill_amt'];
                    $newRow['tds_base_rate_amt']= $row['tds_base_rate_amt']; 
                    $newRow['tds_ecess_amt']= $row['tds_ecess_amt'];
                    $newRow['tds_surcharge_amt']= $row['tds_surcharge_amt']; 
                    $ref_row['bill_tds_tran']->AddRow($newRow);   
                }
            }
        }
    }
}

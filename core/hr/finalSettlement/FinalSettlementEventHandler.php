<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\finalSettlement;

/**
 * Description of FinalSettlementEventHandler
 *
 * @author Valli
 */

class FinalSettlementEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        
         if($this->bo->final_settlement_id=="" or $this->bo->final_settlement_id=="-1")
        {
            $this->bo->final_settlement_id="";
            $this->bo->status=0;
            $this->bo->en_resign_type=0;
            
            if (count($criteriaparam) != 0){
            $this->bo->employee_id=$criteriaparam['formData']['SelectEmployee']['employee_id'];            
            }
            
            $this->bo->company_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
            $this->bo->branch_id= \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            
            if(strtotime($this->bo->doc_date) > strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))){
                $this->bo->doc_date= \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end');
            }  
        }
        
            
        
        // Set Emp Joining & Resign Date 
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select employee_id, join_date, resign_date from hr.employee where employee_id=:pemp_id");                        
        $cmm->addParam('pemp_id', $this->bo->employee_id );
        $dtresult = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dtresult->Rows())>0){   
            
            $this->bo->emp_join_date =  $dtresult->Rows()[0]['join_date']; 
            
            if ($dtresult->Rows()[0]['resign_date'] !== null){
               $this->bo->emp_resign_date =  $dtresult->Rows()[0]['resign_date'];                        
            }
            
        }
                   
        if($this->bo->final_settlement_id=="" or $this->bo->final_settlement_id=="-1")
        {
            
            // Set Final Settlement From Date & To date                  
            $month = date("m", strtotime($this->bo->emp_resign_date ));
            $year = date("Y", strtotime($this->bo->emp_resign_date )); 
            $start_date=1;
            $from_date = date("Y-m-d", strtotime($start_date."-".$month."-".$year));
            $this->bo->fin_set_from_date = $from_date; 
            if(count($dtresult->Rows())>0){ 
                if ($dtresult->Rows()[0]['resign_date'] !== null){
                    $this->bo->fin_set_to_date =  $dtresult->Rows()[0]['resign_date'];                       
                }
            }
            
        }     
        else
        {
            
            $this->bo->old_resign_type=$this->bo->en_resign_type;
        
            // Fetch Text for id fields
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select a.final_settlement_id, a.fin_set_payroll_tran_detail_id,a.employee_id,  a.payhead_id, b.payhead 
                                  from hr.fin_set_payroll_tran_detail a 
                                  inner join hr.payhead b on a.payhead_id=b.payhead_id  where a.final_settlement_id = :pfinal_settlement_id');
            
            $cmm->addParam('pfinal_settlement_id', $this->bo->final_settlement_id);     
            
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($result->Rows())>0){
                foreach($this->bo->fin_set_payroll_tran->Rows() as &$reffin_set_payroll_tran_row){
                    foreach($reffin_set_payroll_tran_row['fin_set_payroll_tran_detail']->Rows() as &$reffin_set_payroll_tran_detail_row){
                        foreach($result->Rows() as $row){
                            if($row['fin_set_payroll_tran_detail_id'] == $reffin_set_payroll_tran_detail_row['fin_set_payroll_tran_detail_id']){
                                $reffin_set_payroll_tran_detail_row['payhead']=$row['payhead'];
                                break;
                            }
                        }
                    }
                }                    
            }  
        }
                 
    }
}

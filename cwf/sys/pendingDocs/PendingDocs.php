<?php

namespace app\cwf\sys\pendingDocs;
/**
 * Description of PendingDocs
 *
 * @author valli
 */
class PendingDocs extends \app\cwf\fwShell\base\ReportBase {
    
    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);
        
        //*** Validations ***
        
        if($rptOption->rptParams['pbranch_id']=='' || $rptOption->rptParams['pbranch_id']=='-1'){
            array_push($rptOption->brokenRules, 'Please select Branch.');
        }           
       
        if($rptOption->rptParams['pdoc_bo_id']==''|| $rptOption->rptParams['pdoc_bo_id']=='-1'){
              array_push($rptOption->brokenRules, 'Please Select Document.');
        } 
           
        if(strtotime($rptOption->rptParams["pfrom_date"]) > strtotime($rptOption->rptParams["pto_date"])){
            array_push($rptOption->brokenRules, 'From Date should be less than To Date.');
        }
        
        if($rptOption->rptParams['pdoc_action_id']==''|| $rptOption->rptParams['pdoc_action_id']=='-1'){
              array_push($rptOption->brokenRules, 'Please Select Doc Status.');
        } 
                
        if($rptOption->rptParams['pfrom_user_id']==-1){
            array_push($rptOption->brokenRules, 'Please Select From User.');
        }       
        
        if($rptOption->rptParams['pto_user_id']==-1){
            array_push($rptOption->brokenRules, 'Please Select To User.');
        } 
        
        if($rptOption->rptParams['pdoc_action_id']=='W'){ 
            $rptOption->rptParams['pdoc_status']=  'In Work Flow';
        }else if($rptOption->rptParams['pdoc_action_id']=='S'){ 
         $rptOption->rptParams['pdoc_status']=  'Sent';
        }else if($rptOption->rptParams['pdoc_action_id']=='A'){ 
         $rptOption->rptParams['pdoc_status']=  'Approved';
        }else if($rptOption->rptParams['pdoc_action_id']=='R'){ 
         $rptOption->rptParams['pdoc_status']=  'Rejected';
        }else if($rptOption->rptParams['pdoc_action_id']=='U'){ 
         $rptOption->rptParams['pdoc_status']=  'Unposted';
        }else if($rptOption->rptParams['pdoc_action_id']=='I'){ 
         $rptOption->rptParams['pdoc_status']=  'Assigned';
        }else if($rptOption->rptParams['pdoc_action_id']=='O'){ 
         $rptOption->rptParams['pdoc_status']=  'Saved';
        }
        
        $rptOption->rptParams['pdocument_type']  = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../cwf/sys/lookups/WfBOlistWithAll.xml', 'menu_text', 'bo_id',  $rptOption->rptParams['pdoc_bo_id']);       
          
        $rptOption->rptParams['pfrom_user']=  \app\cwf\vsla\utils\LookupHelper::GetLookupText('../cwf/sys/lookups/UserWithAll.xml', 'full_user_name', 'user_id',  $rptOption->rptParams['pfrom_user_id']);
        
        $rptOption->rptParams['pto_user']=  \app\cwf\vsla\utils\LookupHelper::GetLookupText('../cwf/sys/lookups/UserWithAll.xml', 'full_user_name', 'user_id',  $rptOption->rptParams['pto_user_id']);
        
        $rptOption->rptParams['pbranch_name']  = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../cwf/sys/lookups/BranchWithAll.xml', 'branch_name', 'branch_id', $rptOption->rptParams['pbranch_id']);       
               
        $rptOption->rptParams['prpt_period'] = "From ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pfrom_date"])
        ." To ".\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($rptOption->rptParams["pto_date"]);
        return $rptOption;
    }
}

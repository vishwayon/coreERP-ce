<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tx\taxSchedule;

/**
 * Description of TaxScheduleEventHandler
 *
 * @author vaishali
 */
class TaxScheduleEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam); 
        
        $this->bo->tax_detail_copy_temp = new \app\cwf\vsla\data\DataTable();
        $this->bo->tax_detail_copy_temp->cloneColumns($this->bo->tax_detail_tran);
        
        foreach ($this->bo->tax_detail_tran->Rows() as $row) {
            $newRow = $this->bo->tax_detail_copy_temp->NewRow();
            $newRow['tax_detail_id'] = $row['tax_detail_id'];
            $newRow['tax_schedule_id'] = $row['tax_schedule_id'];
            $newRow['step_id'] = $row['step_id'];
            $newRow['parent_tax_details'] =  $row['parent_tax_details'];
            $newRow['description'] = $row['description'];
            $newRow['account_id'] = $row['account_id'];
            $newRow['en_tax_type'] = $row['en_tax_type'];
            $newRow['en_round_type'] = $row['en_round_type'];
            $newRow['tax_perc'] = $row['tax_perc'];
            $newRow['tax_on_perc'] = $row['tax_on_perc'];
            $newRow['tax_on_min_amt'] = $row['tax_on_min_amt'];
            $newRow['tax_on_max_amt'] = $row['tax_on_max_amt'];
            $newRow['min_tax_amt'] = $row['min_tax_amt'];
            $newRow['max_tax_amt'] = $row['max_tax_amt'];
            $this->bo->tax_detail_copy_temp->AddRow($newRow);
        }  
        
    }
    
    public function onSave($cn, $tablename){
        parent::onSave($cn, $tablename);  
        
        if($tablename=='tx.tax_detail'){
            
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('DELETE FROM tx.tax_detail '
                                 . 'WHERE tax_detail_id=:ptax_detail_id ');
            
            foreach($this->bo->tax_detail_copy_temp->Rows() as $temprow){
                $deletedrow=true;
                foreach($this->bo->tax_detail_tran->Rows() as $row){
                     if($row['tax_detail_id']==$temprow['tax_detail_id']){
                         $deletedrow=false;
                         break;
                     }
                     $deletedrow=TRUE;
                 }
                if($deletedrow){                    
                    $cmm->addParam('ptax_detail_id', $temprow['tax_detail_id']);
                    \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn); 
                }
            }
            
            
            $taxDetails= array();
            
            // Save new uom items
            $ac = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts('tx.tax_detail',  \app\cwf\vsla\data\DataConnect::COMPANY_DB, \app\cwf\vsla\entity\ActionScript::TABLE_TYPE_MASTER_CONTROL);
            foreach($this->bo->tax_detail_tran->Rows() as &$reftax_detail_tran_Row)
            { 
                $ParentTaxDetails=$reftax_detail_tran_Row['parent_tax_details'];
                foreach($taxDetails as $item){
                    $ParentTaxDetails=  str_replace($item->CurrentTaxDetail_ID,$item->AfterUpdateTaxDetail_ID, $ParentTaxDetails);                   
                }
                
                
                if($reftax_detail_tran_Row['tax_detail_id']<0){
                    $cmm = $ac->getInsertCmm();
                    $taxdetailpkid = \app\cwf\vsla\entity\EntityManager::getMastSeqID($this->bo->company_id, 'tx.tax_detail', $cn);
                }
                else{
                    $cmm = $ac->getUpdateCmm();
                    $taxdetailpkid = $reftax_detail_tran_Row['tax_detail_id'];
                }
                $cmm->setParamValue('ptax_detail_id', $taxdetailpkid);
                $cmm->setParamValue('ptax_schedule_id', $this->bo->tax_schedule_id);
                $cmm->setParamValue('pstep_id', $reftax_detail_tran_Row['step_id']);
                $cmm->setParamValue('pparent_tax_details', $ParentTaxDetails);
                $cmm->setParamValue('pdescription', $reftax_detail_tran_Row['description']);
                $cmm->setParamValue('paccount_id', $reftax_detail_tran_Row['account_id']);
                $cmm->setParamValue('pen_tax_type', $reftax_detail_tran_Row['en_tax_type']);
                $cmm->setParamValue('pen_round_type', $reftax_detail_tran_Row['en_round_type']);
                $cmm->setParamValue('ptax_perc', $reftax_detail_tran_Row['tax_perc']);
                $cmm->setParamValue('ptax_on_perc', $reftax_detail_tran_Row['tax_on_perc']);
                $cmm->setParamValue('ptax_on_min_amt', $reftax_detail_tran_Row['tax_on_min_amt']);
                $cmm->setParamValue('ptax_on_max_amt', $reftax_detail_tran_Row['tax_on_max_amt']);
                $cmm->setParamValue('pmin_tax_amt', $reftax_detail_tran_Row['min_tax_amt']);
                $cmm->setParamValue('pmax_tax_amt', $reftax_detail_tran_Row['max_tax_amt']);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);  
                
                if($reftax_detail_tran_Row['tax_detail_id'] < 0){
                    $taxItem= new TaxDetailItem();
                    $taxItem->CurrentTaxDetail_ID=$reftax_detail_tran_Row['tax_detail_id'];
                    $taxItem->AfterUpdateTaxDetail_ID=$taxdetailpkid;
                    $taxItem->ParentTaxDetails=$reftax_detail_tran_Row['parent_tax_details'];
                    
                    array_push($taxDetails,$taxItem);
                    $reftax_detail_tran_Row['tax_detail_id']=$taxdetailpkid;
                    $reftax_detail_tran_Row['tax_schedule_id']=$this->bo->tax_schedule_id;
                }
            }
        }
    }
}

class TaxDetailItem{
        /** @var MethodInfo **/
        public $CurrentTaxDetail_ID;
        public $AfterUpdateTaxDetail_ID; 
        public $ParentTaxDetails; 
    }
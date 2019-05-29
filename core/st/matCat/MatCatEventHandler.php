<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\matCat;

/**
 * Description of MatCatEventhandler
 *
 * @author priyanka
 */
class MatCatEventHandler extends\app\cwf\vsla\xmlbo\EventHandlerBase  {
    //put your code here
    
    
    public function onSave($cn, $tablename){
        parent::onSave($cn, $tablename);  
        
        if($tablename=='st.mat_cat_key'){
                        
            // Save new uom items
            $ac = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts('st.mat_cat_key',  \app\cwf\vsla\data\DataConnect::COMPANY_DB, \app\cwf\vsla\entity\ActionScript::TABLE_TYPE_MASTER_TRAN);
            foreach($this->bo->mat_cat_key->Rows() as &$ref_key_row)
            { 
                if($ref_key_row['mat_cat_key_id']==-1){
                    $cmm = $ac->getInsertCmm();
                    $keypkid = \app\cwf\vsla\entity\EntityManager::getMastSeqID($this->bo->company_id, 'st.mat_cat_key', $cn);
                }
                else{
                    $cmm = $ac->getUpdateCmm();
                    $keypkid = $ref_key_row['mat_cat_key_id'];
                }
                $cmm->setParamValue('pmat_cat_key_id', $keypkid);
                $cmm->setParamValue('pmat_cat_id', $this->bo->mat_cat_id);
                $cmm->setParamValue('pmat_cat_key', $ref_key_row['mat_cat_key']);
                $cmm->setParamValue('pmat_cat_key_desc', $ref_key_row['mat_cat_key_desc']);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn); 
                $ref_key_row['mat_cat_key_id'] = $keypkid;
            }
        }
        
         if($tablename=='st.mat_cat_attr'){
                        
            // Save new uom items
            $ac = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts('st.mat_cat_attr',  \app\cwf\vsla\data\DataConnect::COMPANY_DB, \app\cwf\vsla\entity\ActionScript::TABLE_TYPE_MASTER_CONTROL);
            foreach($this->bo->mat_cat_attr->Rows() as &$ref_attr_row)
            { 
                if($ref_attr_row['mat_cat_attr_id']==-1){
                    $cmm = $ac->getInsertCmm();
                    $attrpkid = \app\cwf\vsla\entity\EntityManager::getMastSeqID($this->bo->company_id, 'st.mat_cat_attr', $cn);
                }
                else{
                    $cmm = $ac->getUpdateCmm();
                    $attrpkid = $ref_attr_row['mat_cat_attr_id'];
                }
                $cmm->setParamValue('pmat_cat_attr_id', $attrpkid);
                $cmm->setParamValue('pmat_cat_id', $this->bo->mat_cat_id);
                $cmm->setParamValue('pmat_cat_attr', $ref_attr_row['mat_cat_attr']);
                $cmm->setParamValue('pmat_cat_attr_desc', $ref_attr_row['mat_cat_attr_desc']);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn); 
                $ref_attr_row['mat_cat_attr_id'] = $attrpkid;
            }
        }
        
    }
    
}

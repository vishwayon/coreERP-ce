<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\reorderLevel;

use YaLinqo\Enumerable;

/**
 * Description of reorderLevelEventHandler
 *
 * @author Valli
 */
class ReorderLevelEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);    
        
        $this->bo->mat_level->getColumn("lead_time")->default=0;
        $this->bo->setTranColDefault('mat_level', 'lead_time', "0");
    }

    public function beforeSave($cn) {
        parent::beforeSave($cn);
    }

    public function onSave($cn, $tablename) {
        // Avoid saving the base document as it is used only as an anchor
        // Base document data cannot be changed from here
        // Save the material level entries made by the user
        
        if ($tablename == 'st.mat_level') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $as = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts('st.mat_level', \app\cwf\vsla\data\DataConnect::COMPANY_DB, \app\cwf\vsla\entity\ActionScript::TABLE_TYPE_MASTER_TRAN, 'material_id');
            $cmm = $as->getDeleteCmm();
            $cmm->setParamValue('pmaterial_id', $this->bo->material_id);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
            
            foreach ($this->bo->mat_level->Rows() as &$ref_dr) {                
                $cmm = $as->getInsertCmm();
                $detailpkid = md5($this->bo->material_id . ':' . $ref_dr['branch_id']);            
                $cmm->setParamValue('pmat_level_id', $detailpkid);
                $cmm->setParamValue('pbranch_id', $ref_dr['branch_id']);
                $cmm->setParamValue('pmaterial_id', $this->bo->material_id);
                $cmm->setParamValue('pmin_qty', $ref_dr['min_qty']);                
                $cmm->setParamValue('preorder_level', $ref_dr['reorder_level']);                
                $cmm->setParamValue('preorder_qty', $ref_dr['reorder_qty']);
                $cmm->setParamValue('pmax_qty', $ref_dr['max_qty']);
                $cmm->setParamValue('plead_time', $ref_dr['lead_time']);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                $ref_dr['mat_level_id'] = $detailpkid;
                $ref_dr['material_id'] = $this->bo->material_id;
            }
        }
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
        
    }
    
    public function resetLastUpdated($cn, $tablename, $primaryKey) {
        // Do nothing as this is only anchoring BO
    }

}

<?php

namespace app\core\st\service;

/**
 * Service Event Handler
 * @author Girish
 */
class ServiceEventHandler extends\app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        // This would always be true for a new document
        if ($this->bo->material_id == '' || $this->bo->material_id == -1) {
            $this->bo->annex_info->Value()->is_service = true;
        }

        // Copy uom to custom col uom_desc
        if ($this->bo->material_id > 0) {
            if (count($this->bo->uom->Rows()) > 0) {
                $this->bo->uom_desc = $this->bo->uom->Rows()[0]['uom_desc'];
            }
        }

        if ($this->bo->annex_info->Value()->war_info->war_days == -1) {
            $this->bo->annex_info->Value()->war_info->war_days = 0;
        }
    }

    public function onSave($cn, $tablename) {
        parent::onSave($cn, $tablename);

        if ($tablename == 'st.uom') {
            // Save the uom
            $ac = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts('st.uom', \app\cwf\vsla\data\DataConnect::COMPANY_DB, \app\cwf\vsla\entity\ActionScript::TABLE_TYPE_MASTER_CONTROL);
            $cnt = count($this->bo->uom->Rows());
            foreach ($this->bo->uom->Rows() as &$ref_uom_row) {
                if ($ref_uom_row['uom_id'] <= -1) {
                    $cmm = $ac->getInsertCmm();
                    $uompkid = \app\cwf\vsla\entity\EntityManager::getMastSeqID($this->bo->company_id, 'st.uom', $cn);
                } else {
                    $cmm = $ac->getUpdateCmm();
                    $uompkid = $ref_uom_row['uom_id'];
                }
                $cmm->setParamValue('puom_id', $uompkid);
                $cmm->setParamValue('pmaterial_id', $this->bo->material_id);
                $cmm->setParamValue('puom_desc', $ref_uom_row['uom_desc']);
                $cmm->setParamValue('puom_qty', $ref_uom_row['uom_qty']);
                $cmm->setParamValue('pis_base', $ref_uom_row['is_base']);
                $cmm->setParamValue('pis_su', $ref_uom_row['is_su']);
                $cmm->setParamValue('pis_discontinued', $ref_uom_row['is_discontinued']);
                $cmm->setParamValue('puom_type_id', $ref_uom_row['uom_type_id']);
                if (array_key_exists('in_kg', $ref_uom_row)) {
                    $cmm->setParamValue('pin_kg', $ref_uom_row['in_kg']);
                    $cmm->setParamValue('pin_ltr', $ref_uom_row['in_ltr']);
                }
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                $ref_uom_row['uom_id'] = $uompkid;
            }
        }
    }

}

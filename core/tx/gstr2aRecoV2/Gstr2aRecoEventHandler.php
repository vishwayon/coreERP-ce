<?php

namespace app\core\tx\gstr2aRecoV2;
/**
 * 
 * @author girishshenoy
 */
class Gstr2aRecoEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        if(intval($this->bo->annex_info->Value()->gstr2a_reco_info->gstr2a_id) > 0) {
            // Retreive b2b_data
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select b2b_data From tx.gstr2a Where gstre2a_id = :pid");
            $cmm->addParam("pid", $this->bo->annex_info->Value()->gstr2a_reco_info->gstr2a_id);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            $this->bo->b2b_data = json_decode($dt->Rows()[0]['b2b_data']);
        } else {
            $this->bo->b2b_data = new \stdClass();
        }
    }
    
    public function onSave($cn, $tablename) {
        // Todo
    }
}

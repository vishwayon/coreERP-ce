<?php

/**
 * LotController is used to manage Stock Lots
 * st.sl_lot, st.sl_lot_alloc
 *
 * @author girishshenoy
 */

namespace app\core\st\controllers;

class LotAllocController extends \app\cwf\vsla\base\WebController {

    public function actionLotBal($mat_id, $vch_id, $doc_date, $sloc_id, $lot_state_id) {
        $cmmMat = new \app\cwf\vsla\data\SqlCommand();
        $cmmMat->setCommandText("Select a.material_name, b.uom_desc 
                    From st.material a 
                    Inner Join st.uom b On a.material_id = b.material_id And b.is_base
                    Where a.material_id = :pmat_id");
        $cmmMat->addParam("pmat_id", $mat_id);
        $dtmat = \app\cwf\vsla\data\DataConnect::getData($cmmMat);

        $param = new \app\core\st\lotAlloc\LotHelperParam();
        $param->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable("branch_id");
        $param->mat_id = $mat_id;
        $param->sloc_id = $sloc_id;
        $param->to_date = $doc_date;
        $param->vch_id = $vch_id;
        $param->lot_state_id = $lot_state_id;
        $dt = \app\core\st\lotAlloc\LotAllocHelper::getLotBal($param);

        $result = array();
        if (count($dtmat->Rows()) > 0) {
            $result['mat_name'] = $dtmat->Rows()[0]['material_name'];
            $result['uom'] = $dtmat->Rows()[0]['uom_desc'];
        } else {
            $result['mat_name'] = 'Unknown';
            $result['uom'] = '';
        }
        $result['lot_bal'] = $dt;
        $result['status'] = 'OK';
        return json_encode($result);
    }

}

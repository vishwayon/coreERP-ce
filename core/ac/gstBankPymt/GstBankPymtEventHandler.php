<?php

namespace app\core\ac\gstBankPymt;

/**
 * GstPymtEventHandler
 * @author Girish
 */
class GstBankPymtEventHandler extends \app\core\ac\gstPymt\GstPymtEventHandler {

    public function beforeFetch(&$criteriaparam) {
        parent::beforeFetch($criteriaparam);
        $this->bo->vch_tran_id = $this->bo->voucher_id;
    }

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        if ($this->bo->voucher_id == "" or $this->bo->voucher_id == "-1") {
            $this->bo->annex_info->Value()->pymt_type = 0;
        } 
    }
}

<?php

namespace app\core\tx\gstr1;
/**
 * 
 * @author girishshenoy
 */
class Gstr1EventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        if($this->bo->gst_ret_id == -1 || $this->bo->gst_ret_id == '') {
            $this->bo->gst_ret_type_id = 101; //'GSTR1 - Return for Outward Supplies'
            $this->bo->gst_state_id = \app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gst_state_id'];
            $np = Gstr1Worker::getNextPeriod($this->bo->gst_state_id);
            $this->bo->ret_period = $np['ret_period'];
            $this->bo->ret_period_from = $np['ret_period_from'];
            $this->bo->ret_period_to = $np['ret_period_to'];
            if(array_key_exists('gt', $np)) {
                $this->bo->annex_info->Value()->gt = $np['gt'];
                $this->bo->annex_info->Value()->cur_gt = $np['cur_gt'];
            }
            $this->bo->ret_status = 0;
            $this->bo->ret_status_desc = 'Pre-process';
        } elseif ($this->bo->ret_status == 1) {
            $this->bo->ret_status_desc = 'Created';
        } elseif ($this->bo->ret_status == 2) {
            $this->bo->ret_status_desc = 'Uploaded';
        }
    }
}

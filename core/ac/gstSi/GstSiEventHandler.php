<?php

namespace app\core\ac\gstSi;

/**
 * GstSiEventHandler
 * @author girishshenoy
 */
class GstSiEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        // Create GL temp to view GL Distribution
        \app\core\ac\glDistribution\GLDistributionHelper::CreateGLTemp($this->bo);
        
        if($this->bo->voucher_id == -1 || $this->bo->voucher_id == '') {
            $this->bo->voucher_id = '';
            $this->bo->status = 0;
            $this->bo->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            // Defaults as readonly (This should be reverse charge supply)
            $this->bo->annex_info->Value()->gst_rc_info->apply_rc = true;            
        }
    }
}

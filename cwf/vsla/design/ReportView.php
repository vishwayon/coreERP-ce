<?php

namespace app\cwf\vsla\design;

include_once '../cwf/vsla/design/CommonTypes.php';
use app\cwf\vsla\design\RelationType;

class ReportView extends CwFrameworkType {
    public $reportParams = array();
    
    public function getType() {
        return self::REPORT_VIEW;
    }
    
    public $afterRefreshEvent = '';
    public $exportView = '';
}

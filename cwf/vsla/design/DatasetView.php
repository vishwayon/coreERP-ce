<?php

namespace app\cwf\vsla\design;

include_once '../cwf/vsla/design/CommonTypes.php';

/**
 * Description of DatasetView
 *
 * @author dev
 */
class DatasetView extends CwFrameworkType {

    public $reportParams = array();
    public $querytext = '';
    public $description = '';
    public $header = '';

    public function getType() {
        return self::DATASET_VIEW;
    }

    public $afterRefreshEvent = '';

}

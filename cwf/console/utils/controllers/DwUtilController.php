<?php

namespace app\cwf\console\utils\controllers;

/**
 * Implements actions for DataWarehouse Utils
 * to call: php yii utils/dw-util/pump-data /path/to/coreERP-dwbi/CoreDW/dw/dw_pump.xml
 *
 * @author girishshenoy
 */
class DwUtilController extends \yii\console\Controller {
    
    public function actionPumpData(string $xfile_path) {
        $outstream =fopen('php://memory','r+');
        try {
            $configReader = new \app\cwf\vsla\utils\ConfigReader();
            $dwPump = new \app\cwf\console\utils\workers\SqlPumpWorker($configReader, $outstream, $xfile_path);
            $dwPump->run();
            echo "DW Pump completed successfully\n";
        } catch (\Exception $ex) {
            fwrite($outstream, $ex->getMessage()."\n");
        }
        
        rewind($outstream);
        echo stream_get_contents($outstream);
    }
}

<?php

namespace app\core\st\controllers;

/**
 * BalUpdateUtilController 
 * used for reverse calculation and update of opening balance 
 * based on current balance (physical stock take)
 *
 * @author girishshenoy
 */
class BalUpdateUtilController extends \app\cwf\vsla\base\WebController {

    public function actionIndex($viewName = null, $viewParams = null) {
        $model = new \app\core\st\balUpdateUtil\ModelBalUpdateUtil();
        return $this->renderPartial('@app/core/st/balUpdateUtil/ViewBalUpdateUtil', ['model' => $model]);
    }

    public function actionGet($material_type_id, $stock_location_id, $as_on) {
        $model = new \app\core\st\balUpdateUtil\ModelBalUpdateUtil();
        $result = $model->get_data($material_type_id, $stock_location_id, $as_on);
        return json_encode($result);
    }

    public function actionUpdate() {
        $data = json_decode(\yii::$app->request->post('model'));
        $model = new \app\core\st\balUpdateUtil\ModelBalUpdateUtil();
        $result = $model->post_data($data);
        return json_encode($result);
    }

    public function actionDownload($material_type_id, $stock_location_id, $as_on) {
        $model = new \app\core\st\balUpdateUtil\ModelBalUpdateUtil();
        $result = $model->get_data($material_type_id, $stock_location_id, $as_on);
        $dt = $result['matbal'];

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select company_code || branch_code as code From sys.branch Where branch_id = {branch_id}");
        $dtBr = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $cc_bc = $dtBr->Rows()[0]['code'];

        $virtualPath = \Yii::$app->getUrlManager()->getBaseUrl() . '/reportcache/' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID() . '/';
        $pathName = \yii::getAlias('@webroot') . '/reportcache/' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID() . '/';
        \app\cwf\vsla\render\JReportHelper::createSessionPath();
        $fileName = 'stock_bal_'
                . $cc_bc
                . '_' . str_replace("-", "_", $as_on) . '.csv';

        $fhandle = fopen($pathName . $fileName, 'w');
        // Get Column names
        $dr = ['material_type', 'material_id', 'material_name', 'op_bal', 'receipts', 'issues', 'cl_bal', 'revised_cl_bal'];
        fputcsv($fhandle, $dr);
        // Get Row data
        foreach ($dt->Rows() as $row) {
            $datarow = [];
            foreach ($dr as $col) {
                $datarow[] = $row[$col];
            }
            fputcsv($fhandle, $datarow);
        }
        fclose($fhandle);

        return json_encode(
                ['filePath' => $virtualPath . $fileName,
                    'fileName' => $fileName
        ]);
    }

    public function actionUpload() {
        if (count($_FILES) == 1) {
            $model = new \app\core\st\balUpdateUtil\ModelBalUpdateUtil();
            $result = $model->get_data(\yii::$app->request->post('material_type_id'), \yii::$app->request->post('stock_location_id'), \yii::$app->request->post('as_on'));
            $dtmatbal = $result['matbal'];
            if (($handle = fopen($_FILES['fupload']['tmp_name'], "r")) !== FALSE) {
                $i = 0;
                $filedata = [];
                $mat_index = -1;
                $rcb_index = -1;
                while (($src = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if ($i == 0) {
                        // Column headers, get index
                        $mat_index = array_search('material_id', $src);
                        $rcb_index = array_search('revised_cl_bal', $src);
                        if(is_bool($rcb_index)) { // try alternate Qty column (for backward compatibility)
                            $rcb_index = array_search('Qty', $src);
                        }
                        if (is_bool($mat_index) || is_bool($rcb_index)) {
                            $result['message'] = 'Missing columns [material_id, revised_cl_bal]. Failed to load from file.';
                            break;
                        }
                    } else { // row data map to array
                        if (is_numeric($src[$rcb_index])) {
                            $filedata[$src[$mat_index]] = floatval($src[$rcb_index]);
                        }
                    }
                    $i++;
                }
                $row_count = 0;
                foreach ($dtmatbal->Rows() as &$matrow) {
                    if (array_key_exists($matrow['material_id'], $filedata)) {
                        $rcb = $filedata[$matrow['material_id']];
                        if ($rcb > -1) {
                            $matrow['revise'] = true;
                            $matrow['revised_cl_bal'] = $rcb;
                            $matrow['revised_op_bal'] = $rcb + $matrow['issues'] - $matrow['receipts'];
                            $row_count++;
                        }
                    }
                }
                $result['rows_found'] = $row_count;
                fclose($handle);
            }
            return json_encode($result);
        }
        return json_encode([
            'status' => 'FAIL'
        ]);
    }

}

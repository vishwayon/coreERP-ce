<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\controllers;

/**
 * Description of PurchRegController
 *
 * @author girishshenoy
 */
class PurchRegController extends \app\cwf\vsla\base\WebController {
    
    public function actionGetCsv() {
        $param = json_decode(\yii::$app->request->post('jParam'));
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select voucher_id, doc_date, supplier, bill_no, bill_date, gstin, gst_state, vat_type_code gst_type, 
                bt_amt taxable_amt, gst_rate, sgst_amt, cgst_amt, igst_amt
            from ap.fn_purchase_register_report(:pcompany_id, :pbranch_id, :psupplier_id, :pfrom_date, :pto_date, :pgst_state_id,
                :pgroup_path, :pinclude_non_gst)
            Order by voucher_id, doc_date;");
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', $param->pbranch_id);
        $cmm->addParam('psupplier_id', $param->psupplier_id);
        $cmm->addParam('pfrom_date', \app\cwf\vsla\utils\FormatHelper::GetDBDate($param->pfrom_date));
        $cmm->addParam('pto_date', \app\cwf\vsla\utils\FormatHelper::GetDBDate($param->pto_date));
        $cmm->addParam('pgst_state_id', $param->pgst_state_id);
        $cmm->addParam('pgroup_path', $param->pgroup_path);
        $cmm->addParam('pinclude_non_gst', $param->pinclude_non_gst == 0 ? false : true);
        $source = \app\cwf\vsla\data\DataConnect::getData($cmm);
        
        $virtualPath = \Yii::$app->getUrlManager()->getBaseUrl() . '/reportcache/' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID() . '/';
        $pathName = \yii::getAlias('@webroot') . '/reportcache/' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID() . '/';
        \app\cwf\vsla\render\JReportHelper::createSessionPath();
        
        $fileName = 'purch_reg_' . $param->pfrom_date .time() . '.csv';
        
        // prepare columns
        foreach($source->getColumns() as $col) {
            $cols[] = $col->columnName;
        }
        //open file
        $fhandle = fopen($pathName . $fileName, 'w');
        fputcsv($fhandle, $cols, ',', '"');
        foreach($source->Rows() as $dr) {
            fputcsv($fhandle, $dr, ',', '"');
        }
        fclose($fhandle);
        $fresult = [
            'status' => 'OK',
            'filePath' => $virtualPath . $fileName,
            'fileName' => $fileName,
        ];
        return json_encode($fresult);
    }
    
}

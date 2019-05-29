<?php

namespace app\core\ap\controllers;

class MainController extends \yii\web\Controller {

    public function actionDownloadCsv($bank_transfer_id) {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select a.debit_amt,now()::date as date,
                                b.supplier,b.annex_info->'bank_details'->>'account_no' as account_no,
                                b.annex_info->'bank_details'->>'bank_name' as bank_name,
                                b.annex_info->'bank_details'->>'ifsc_code' as ifsc_code
                                from ap.pymt_tran a
                                inner join ap.supplier b on a.account_id = b.supplier_id
                                where a.voucher_id=:pbank_transfer_id and a.debit_amt>0");
        $cmm->addParam('pbank_transfer_id', $bank_transfer_id);
        $dtBt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $virtualPath = \Yii::$app->getUrlManager()->getBaseUrl() . '/reportcache/' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID() . '/';
        $pathName = \yii::getAlias('@webroot') . '/reportcache/' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID() . '/';
        \app\cwf\vsla\render\JReportHelper::createSessionPath();
        $fileName = 'bank_xfer_' . $bank_transfer_id . '.csv';

        $fhandle = fopen($pathName . $fileName, 'w');
        // Column names
        $dr = ['Amount_payable', 'Date', 'Name', 'Account_no', 'Email_id', 'Narration', 'Account_to_be_debited', 'Ref_no', 'IFSC_code', 'Party_account_type'];
        fputcsv($fhandle, $dr);
        // Get Row data
        foreach ($dtBt->Rows() as $row) {
            $datarow = [];
            $datarow[0] = $row['debit_amt'];
            $datarow[1] = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($row['date']);
            $datarow[2] = $row['supplier'];
            $datarow[3] = $row['account_no'];
            $datarow[4] = '';
            $datarow[5] = 'Milk supplier payment as on '.\app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($row['date']);
            $datarow[6] = '';
            $datarow[7] = '';
            $datarow[8] = $row['ifsc_code'];
            $datarow[9] = '';
            fputcsv($fhandle, $datarow);
        }
        fclose($fhandle);

        return json_encode(
                ['filePath' => $virtualPath . $fileName,
                    'fileName' => $fileName
        ]);
    }

}

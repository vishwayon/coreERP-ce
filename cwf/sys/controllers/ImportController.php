<?php

namespace app\cwf\sys\controllers;

use app\cwf\sys\csvimport\ImportParser;
use app\cwf\sys\csvimport\ImportHelper;
use app\cwf\vsla\base\WebController;

class ImportController extends WebController {

    public function actionViewlist() {
        $milist = ImportParser::getImportList();
        return $this->renderPartial('viewlist', ['milist' => $milist]);
    }

    public function actionViewfields($mastername) {
        $fieldlist = ImportParser::getFieldList($mastername);
        $masterinfo = ImportParser::getMasterInfo($mastername);
        return $this->renderPartial('viewfields', ['fieldlist' => $fieldlist, 'minfo' => $masterinfo]);
    }

    public function actionGetfile($mastername) {
        $fp = \Yii::getAlias('@app/web/reportcache/' . $mastername . '.csv');
        $out = fopen($fp, 'w');
        $fieldHeader = ImportParser::getImportTemplate($mastername);
        fputcsv($out, $fieldHeader);
        fclose($out);
        return \yii::$app->response->sendFile($fp, $mastername . '.csv');
    }

    public function actionImportfile() {
        $bo = \yii::$app->request->getBodyParam('bo');
        $doc_id = \yii::$app->request->getBodyParam('doc_id');
        $branch_id = \yii::$app->request->getBodyParam('branch_id');
        $branch_name = \yii::$app->request->getBodyParam('branch_name');
        if ($branch_id == NULL || $branch_id == '') {
            $branch_id = -1;
        }
        $mastername = \yii::$app->request->getBodyParam('mastername');
        $dmfiles = \yii\web\UploadedFile::getInstancesByName('files');
        foreach ($dmfiles as $dmfile) {
            $fin = new \app\cwf\fwShell\models\DmFile($dmfile);
            $fin->SaveToFileStore($bo, $doc_id);
        }
        $fileresult = [];
        $fileid = -1;
        $dmfiles = \app\cwf\fwShell\models\DmFile::getDocs($bo, $doc_id);
        if ($dmfile != NULL) {
            foreach ($dmfiles->Rows() as $dmfile) {
                $fileresult[] = ['fileName' => $dmfile['file_name'], 'fileid' => $dmfile['dm_file_id']];
            }
            $fileid = $fileresult[0]['fileid'];
        }
        $fileInfo = \app\cwf\fwShell\models\DmFile::getDocInfo($fileid);
        $csvdata;
        if (count($fileInfo) > 0) {
            $csvdata = ImportHelper::getImportData($mastername, $fileInfo['filePath'], $branch_id);
        }
        return $this->renderPartial('viewData', ['csvdata' => $csvdata, 'mastername' => $mastername,
                    'fileid' => $fileid, 'brid' => $branch_id, 'branch_name' => $branch_name]);
    }

    public function actionImportdata($mastername, $fileid, $branch_id) {
        $fileInfo = \app\cwf\fwShell\models\DmFile::getDocInfo($fileid);
        $csvdata;
        if (count($fileInfo) > 0) {
            $csvdata = ImportHelper::getImportData($mastername, $fileInfo['filePath'], $branch_id);
        }
        $importResult = ImportHelper::importData($mastername, $csvdata, $branch_id);
        return $importResult;
    }

}

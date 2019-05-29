<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\fwShell\controllers;

/**
 * Description of DmfileController
 *
 * @author girish
 */
class DmfileController extends \app\cwf\vsla\base\WebController {
    
    public function actionUpload() {
        $bo = \yii::$app->request->getBodyParam('bo');
        $doc_id = \yii::$app->request->getBodyParam('doc_id');
        if($doc_id==-1 || $doc_id==''){
            $res[] = ['fileName'=>'','status'=>'Save the document first to attach a file.','fileid'=>-1];
            return json_encode($res);
        }
        $dmfiles = \yii\web\UploadedFile::getInstancesByName('files');
        $fileinfo = [];
        foreach($dmfiles as $dmfile) {
            if($dmfile->name!=NULL && $dmfile->name!=''){
                $fin = new \app\cwf\fwShell\models\DmFile($dmfile);
                $res = $fin->SaveToFileStore($bo, $doc_id);
                $fileinfo[] = ['fileName'=>$dmfile->name, 'status'=>$res, 'fileid'=>-1];
            }
        }
        $doclist = json_decode($this->actionDoclist($bo, $doc_id));
        
        foreach ($fileinfo as $file) {
            $found = false;
            foreach ($doclist as $doc) {
                if($file['fileName'] == $doc->fileName) {
                    $doc->status = $file['status'];
                    $found = true;
                }
            }
            if(!$found) {
                $doclist[] = $file;
            }
        }
        return json_encode($doclist);
    }
    
    public function actionDoclist($bo, $doc_id) {
        $fileresult = [];
        $dmfiles = \app\cwf\fwShell\models\DmFile::getDocs($bo, $doc_id);
        if($dmfiles != NULL) {
            foreach($dmfiles->Rows() as $dmfile) {
                $fileresult[] = ['fileName' => $dmfile['file_name'], 'fileid' => $dmfile['dm_file_id'], 'status' => 'saved'] ;
            }
        }
        return json_encode($fileresult);
    }
    
    public function actionDetachdoc($bo, $doc_id, $file_id) {
        \app\cwf\fwShell\models\DmFile::deleteDoc($bo, $doc_id, $file_id);
        return $this->actionDoclist($bo, $doc_id);
    }
    
    public function actionDownloadfile($file_id) {
        $fileInfo = \app\cwf\fwShell\models\DmFile::getDocInfo($file_id);
        if(count($fileInfo)>0) {
            return \yii::$app->response->sendFile($fileInfo['filePath'], $fileInfo['fileName']);
        }
        return 'Download 404';
    }
    
}

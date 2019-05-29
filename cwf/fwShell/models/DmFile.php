<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\fwShell\models;

/**
 * Description of DmFile
 *
 * @author girish
 */
class DmFile {
    /** @var \yii\web\UploadedFile */
    private $dmFile;
    
    public $fileName;
    public $dm_file_id;
    public $file_store;
    
    /** @param \yii\web\UploadedFile $dmFile */
    public function __construct($dmFile) {
        $this->dmFile = $dmFile;
    }
    
    public function SaveToFileStore($bo_id, $doc_id) {
        // Scan file for virus/trojan/malware
        if(\yii::$app->has('fileAVScan')) {
            $avscan = \yii::$app->get('fileAVScan');
            $result = $avscan->scanFile($this->dmFile->tempName);
            if($result['status'] != 'OK') {
                //throw new \Exception("Attachment failed. Infected file attached. \n".$result['reason']);
                return $result['reason'];
            }
        }

        // First check if a file with same name already attached
        $dt = self::checkDoc($bo_id, $doc_id, $this->dmFile->name);
        if(count($dt->Rows())>0) {
            self::deleteDoc($bo_id, $doc_id, $dt->Rows()[0]['dm_file_id']);
        }
        // save to db and copy to store
        $this->saveToDB();
        // Link to document
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * From sys.sp_dm_file_link(:pcompany_id, :pbusiness_object, :pref_id, :pdm_file_id);');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID());
        $cmm->addParam('pbusiness_object', $bo_id);
        $cmm->addParam('pref_id', $doc_id);
        $cmm->addParam('pdm_file_id', $this->dm_file_id);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
        return 'OK';
    }
    
    private function saveToDB() {
        // First commit to database
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * From sys.sp_dm_file_add(:pcompany_id, :pfile_name, :pchecksum, :pfile_path, :pdm_file_id, :pfile_store);');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID());
        $cmm->addParam('pfile_name', $this->dmFile->name);
        $cmm->addParam('pchecksum', md5($this->dmFile->name));
        $cmm->addParam('pfile_path', '');
        $cmm->addParam('pdm_file_id', -1, \app\cwf\vsla\data\SqlParamType::PARAM_INOUT);
        $cmm->addParam('pfile_store', '', \app\cwf\vsla\data\SqlParamType::PARAM_INOUT);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
        $this->dm_file_id = $cmm->getParamValue('pdm_file_id');
        $this->file_store = $cmm->getParamValue('pfile_store');
        
        // Next move to file storage area
        $filePath = \yii::$app->params['cwf_config']['dm']['path'] . 'C' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID();
        if(!file_exists($filePath)) {
            \yii\helpers\FileHelper::createDirectory($filePath);
        }
        $filePath .= DIRECTORY_SEPARATOR . $this->file_store . '.dmf';
        copy($this->dmFile->tempName, $filePath);
    }
    
    public static function getDocs($bo_id, $doc_id) {
        if($doc_id == -1 || $doc_id == '') {
            return NULL;
        }
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('SELECT * FROM sys.dm_file 
                                WHERE dm_file_id IN(
                                        SELECT dm_file_id FROM sys.dm_filelink 
                                                WHERE business_object = :pbusiness_object
                                                        AND ref_id = :pref_id
                                                        AND company_id = :pcompany_id
                                )');
        $cmm->addParam('pbusiness_object', $bo_id);
        $cmm->addParam('pref_id', $doc_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID());
        $dtdm = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dtdm;
    }
    
    public static function checkDoc($bo_id, $doc_id, $filename) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('SELECT * FROM sys.dm_file 
                                WHERE dm_file_id IN(
                                        SELECT dm_file_id FROM sys.dm_filelink 
                                                WHERE business_object = :pbusiness_object
                                                        AND ref_id = :pref_id
                                                        AND company_id = :pcompany_id
                                ) AND file_name = :pfile_name');
        $cmm->addParam('pbusiness_object', $bo_id);
        $cmm->addParam('pref_id', $doc_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID());
        $cmm->addParam('pfile_name', $filename);
        $dtdm = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dtdm;
    }
    
    public static function deleteDoc($bo_id, $doc_id, $file_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * From sys.sp_dm_file_delete(:pcompany_id, :pbusiness_object, :pref_id, :pdm_file_id);');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID());
        $cmm->addParam('pbusiness_object', $bo_id);
        $cmm->addParam('pref_id', $doc_id);
        $cmm->addParam('pdm_file_id', $file_id);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
    }
    
    public static function getDocInfo($file_id) {
        $filePath = '';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select file_name, file_store From sys.dm_file WHERE dm_file_id = :pdm_file_id;');
        $cmm->addParam('pdm_file_id', $file_id);
        $dtdm = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dtdm->Rows())==1) {
            $filePath = \yii::$app->params['cwf_config']['dm']['path'] . 'C' 
                            . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID();
            $filePath .= DIRECTORY_SEPARATOR . $dtdm->Rows()[0]['file_store'] . '.dmf';
            return ['filePath'=>$filePath, 'fileName'=>$dtdm->Rows()[0]['file_name']];
        }
        return [];
    }
}

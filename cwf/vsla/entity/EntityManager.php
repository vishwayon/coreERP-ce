<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of EntityManager
 *
 * @author girish
 */
namespace app\cwf\vsla\entity {
    class EntityManager {
        //put your code here
        private static $entityManager;
        
        private $entityScripts = null;
        

        /**
         * 
         * @return EntityManager
         */
        public static function getInstance() {
            if(self::$entityManager == null) {
                self::$entityManager = new EntityManager();
            }
            return self::$entityManager;
        }
        
        private function __construct() {
            $this->entityScripts = array();
        }

        /**
         * 
         * @param string $tableName
         * @return ActionScript
         */
        public function getActionScripts($tableName, $dbType=  \app\cwf\vsla\data\DataConnect::COMPANY_DB, $tableType = ActionScript::TABLE_TYPE_MASTER_CONTROL, $fKey = '', $rootFKey = '', $tranGroup = null) {
            $tName = (string)$tableName;
            if(!isset($this->entityScripts[$tName])) {
                $ac = new ActionScript($tName, $dbType, $tableType, $fKey, $rootFKey, $tranGroup);
                $this->entityScripts[$tName] = $ac;
            } else {
                $ac = $this->entityScripts[$tName];
            }
            return $ac;
        }
        
        
        public static function getMastSeqID($company_id, $mast_seq_type, \PDO $cn) {
            $id = -1;
            
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select * From sys.sp_get_mast_id(:pcompany_id, :pmast_seq_type, :pnew_mast_id)');
            $cmm->addParam('pcompany_id', $company_id);
            $cmm->addParam('pmast_seq_type', $mast_seq_type);
            $cmm->addParam('pnew_mast_id', $id, \app\cwf\vsla\data\SqlParamType::PARAM_INOUT);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
            $id = $cmm->getParamValue('pnew_mast_id');
            
//            $stmt = $cn->prepare('Select * From sys.sp_get_mast_id(:pcompany_id, :pmast_seq_type, :pnew_mast_id)');
//            $stmt->bindParam(':pcompany_id', $company_id);
//            $stmt->bindParam(':pmast_seq_type', $mast_seq_type);
//            $stmt->bindParam(':pnew_mast_id', $id);
//            $stmt->execute();
//            $output = $stmt->fetchAll();
//            $id = $output[0]['pnew_mast_id'];
            return $id;
        }
        
        public static function getDocSeqID($doc_type, $branch_id, $finyear, $tName, $cn, &$v_id) {
            $vch_id = '';
            if(self::validateEmpty($doc_type, $branch_id, $finyear, $tName)){                    
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('Select * From sys.sp_get_doc_id(:pdoc_type, :pbranch_id, :pfinyear, :pdoc_table, :pnew_doc_id, :pnew_v_id)');
                $cmm->addParam('pdoc_type', $doc_type);
                $cmm->addParam('pbranch_id', $branch_id);
                $cmm->addParam('pfinyear', $finyear);
                $cmm->addParam('pdoc_table', $tName);
                $cmm->addParam('pnew_doc_id', $vch_id, \app\cwf\vsla\data\SqlParamType::PARAM_INOUT);
                $cmm->addParam('pnew_v_id', -1, \app\cwf\vsla\data\SqlParamType::PARAM_INOUT);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                $vch_id = $cmm->getParamValue('pnew_doc_id');
                $v_id = $cmm->getParamValue('pnew_v_id');
            }
            return $vch_id;
        }
        
        public static function getfinyear($finyear_id) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select finyear_code From sys.finyear where finyear_id=:pfinyear_id');
            $cmm->addParam('pfinyear_id', $finyear_id);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            $finyear = $dt->Rows()[0]['finyear_code'];
            return $finyear;
        }
        
        private static function validateEmpty($doc_type, $branch_id, $finyear, $tName){
            if($doc_type=='')   {
                 throw new \Exception('Document type is Empty. Failed to generate doc sequence.');
            }
            if($branch_id==-1)   {
                 throw new \Exception('Branch not selected. Failed to generate doc sequence.');
            }
            if($finyear=='')   {
                 throw new \Exception('Finyear is Empty. Failed to generate doc sequence.');
            }
            if($tName=='')   {
                 throw new \Exception('Control table name is Empty. Failed to generate doc sequence.');
            }
            return true;
        }                
    }
}

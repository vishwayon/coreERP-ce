<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\cwf\sys\buildDocID;
use yii\base\Model;

class DocIdModel extends Model {
    
    public $company_code = '';
    public $doc_build_sql = 'doc_id := pdoc_type || left(pfinyear, 2) || pbranch_code || \'/\' || pv_id;';
    
    public function init() {
        parent::init();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select company_code From sys.company Where company_id='.\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $this->company_code = $dt->Rows()[0]['company_code'];
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select value From sys.settings Where key=\'doc_build_sql\'');
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows())) {
            $this->doc_build_sql = $dt->Rows()[0]['value'];
        }
    }
    
    public function rules()
    {
        return [
            // doc_build_sql required
            [['doc_build_sql'], 'required']
        ];
    }
    
    public function commitBuild() {
        // First update the sql
        $cn = \app\cwf\vsla\data\DataConnect::getCn(\app\cwf\vsla\data\DataConnect::COMPANY_DB);
        $cn->beginTransaction();
        try {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $sql = 'Create or Replace Function sys.build_doc_id(IN pdoc_type Varchar(4), IN pcompany_code Varchar(2), IN pbranch_code Varchar(2), 
                            IN pfinyear Varchar(4), IN pv_id BigInt, OUT doc_id varchar(20))
                    Returns Varchar(20) 
                    As
                    $BODY$
                    Begin
                            '.$this->doc_build_sql.'	
                    End
                    $BODY$
                     Language plpgsql;';
            $cmm->setCommandText($sql);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn, \app\cwf\vsla\data\DataConnect::COMPANY_DB);

            // Using sample variables to test sql
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $sql = 'Select * From sys.build_doc_id(:pdoc_type, :pcompany_code, :pbranch_code, :pfinyear, :pv_id)';
            $cmm->setCommandText($sql);
            $cmm->addParam('pdoc_type', 'BPV');
            $cmm->addParam('pcompany_code', $this->company_code);
            $cmm->addParam('pbranch_code', 'HO');
            $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
            $cmm->addParam('pv_id', 1234);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::COMPANY_DB, $cn);
            if(count($dt->Rows())==1 && $dt->Rows()[0]['doc_id']!='') {
                // Second update settings (for record purposes only)
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('Update sys.settings Set value=:pdoc_build_sql Where key=\'doc_build_sql\';');
                $cmm->addParam('pdoc_build_sql', $this->doc_build_sql);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn, \app\cwf\vsla\data\DataConnect::COMPANY_DB);

                $cn->commit();
            } else {
                throw new Exception('Sql did no produce any output. Tests failed. Please correct the SQL');
            }
        } catch (\Exception $ex) {
            if(isset($cn)) {
                $cn->rollBack();
            }
            throw $ex;
        }
    }
    
    
}


<?php

namespace app\core\tds\controllers;

use app\cwf\vsla\base\WebFormController;


class FormController extends WebFormController {

    public function actionCalculatereturn($return_quarter){           
        $from_date = new \DateTime();
        $to_date = new \DateTime();
        
        if ($return_quarter == 'Q4'){            
            $from_date->setDate(date("Y",strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))), 1, 1);
            
            $to_date->setDate(date("Y",strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))), 3, 31);
        }
        if ($return_quarter == 'Q1'){            
            $from_date->setDate(date("Y",strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'))), 4, 1);
            $to_date->setDate(date("Y",strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'))), 6, 30);
        }
        if ($return_quarter == 'Q2'){            
            $from_date->setDate(date("Y",strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'))), 7, 1);
            $to_date->setDate(date("Y",strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'))), 9, 30);
        }
        if ($return_quarter == 'Q3'){   
            $from_date->setDate(date("Y",strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'))), 10, 1);
            $to_date->setDate(date("Y",strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'))), 12, 31);
        }
                 
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from tds.fn_get_tds_payment_for_returns(:pcompany_id, :pbranch_id, :preturn_quarter, :pfrom_date, :pto_date)');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('preturn_quarter', $return_quarter);
        $cmm->addParam('pto_date', $to_date->format('Y-m-d'));
        $cmm->addParam('pfrom_date', $from_date->format('Y-m-d'));
        $dttdsreturn = \app\cwf\vsla\data\DataConnect::getData($cmm);   
        
        $result = array();
        $result['tds_return']=$dttdsreturn;
        $result['status']='ok';
        return json_encode($result);
    }   
    
    public function actionGenerateoutput($tds_return_id){           
        $worker = new \app\core\tds\tdsReturn\TDSReturnWorker();
        $worker->GenerateOutput($tds_return_id);
        $result = array();
        $result['tds_return']=null;
        $result['status']='ok';
        return json_encode($result);
    }  

    public function actionGetBillsForTdsPay($person_type_id, $payment_id){                  
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select a.bill_tds_tran_id, a.voucher_id, a.doc_date, a.supplier_id, a.supplier, 
                        a.tds_base_rate_amt, a.tds_ecess_amt, a.tds_surcharge_amt, a.tds_amt, a.bill_amt, a.branch_id
                from tds.fn_get_pending_bills_for_tds_payment(:pcompany_id, :pbranch_id, :pperson_type_id, :ppayment_id) a
                order by a.supplier, a.voucher_id');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0);
        $cmm->addParam('pperson_type_id', $person_type_id);
        $cmm->addParam('ppayment_id', $payment_id);
        $dtBill = \app\cwf\vsla\data\DataConnect::getData($cmm);   
        
        $result = array();
        $result['bill_bal']=$dtBill;
        $result['status']='ok';
        return json_encode($result);
    }
    
    public function actionGetTdsSecRate($person_type_id, $doc_date) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('SELECT a.person_type_id, a.section_id, b.section, a.base_rate_perc, a.ecess_perc, a.surcharge_perc, a.effective_from,
                                a.en_round_type
                            FROM tds.rate a
                            inner join tds.section b on a.section_id = b.section_id
                            inner Join tds.section_acc c on a.section_id=c.section_id
                            WHERE a.person_type_id = :pperson_type_id');
        $cmm->addParam('pperson_type_id', $person_type_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return json_encode($dt->Rows());
    }    
}

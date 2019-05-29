<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\pos\tday;

/**
 * This worker can be used to fetch 
 * various Txn.Day entry summaries
 * @author girish
 */
class TdayWorker {
    
    public function hasPreviousOpenDays($tsessionid) {
        // Todo: validate for prior open periods
        return false;
    }
    
    public function getTxnDayInfo($tsessionid) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select b.terminal, a.tday_date, c.full_user_name
                From pos.tday a 
                Inner Join pos.terminal b On a.terminal_id=b.terminal_id
                Inner Join sys.user c On a.user_id=c.user_id
                Where tday_session_id = :ptsessionid');
        $cmm->addParam('ptsessionid', $tsessionid);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt->Rows()[0];
    }
    
    public function getShortStock($tsessionid) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * From pos.fn_tday_validate_stock(:ptsessionid)');
        $cmm->addParam('ptsessionid', $tsessionid);
        $dtStBal = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dtStBal;
    }
    
    public function getSaleSummary($tsessionid) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("With net_sales
            As
            (   Select Sum(inv_amt) as inv_amt
                From pos.inv_control 
                Where tday_session_id=:ptsessionid
                    And status = 5 And doc_type Not In ('PSR', 'PIR')
                Union All
                Select Sum(-inv_amt)
                From pos.inv_control 
                Where tday_session_id=:ptsessionid
                    And status = 5 And doc_type In ('PSR', 'PIR')
                Union All
                Select Sum(invoice_amt) 
                From ar.invoice_control a
                Where (a.annex_info->'pos'->>'is_pos')::boolean 
                    And (a.annex_info->'pos'->>'tday_session_id')::uuid = :ptsessionid
                    And a.status = 5
            )
            Select Sum(inv_amt) as inv_amt From net_sales;");
        $cmm->addParam('ptsessionid', $tsessionid);
        $dtSaleTotal = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dtSaleTotal->Rows())==1) {
            return $dtSaleTotal->Rows()[0]['inv_amt'];
        }
        return 0.00;
    }
    
    public function getSaleDetail($tsessionid) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("
            Select 'POS Sales' as txn_type, Sum(inv_amt) as inv_amt
            From pos.inv_control 
            Where tday_session_id=:ptsessionid
                And status = 5 And doc_type Not In ('PSR', 'PIR')
            Union All
            Select 'POS Returns' as txn_type, Sum(-inv_amt)
            From pos.inv_control 
            Where tday_session_id=:ptsessionid
                And status = 5 And doc_type In ('PSR', 'PIR')
            Union All
            Select 'POS Labour Bills' as txn_type, Sum(invoice_amt) 
            From ar.invoice_control a
            Where (a.annex_info->'pos'->>'is_pos')::boolean 
                And (a.annex_info->'pos'->>'tday_session_id')::uuid = :ptsessionid
                And a.status = 5;");
        $cmm->addParam('ptsessionid', $tsessionid);
        $dtSaleDetail = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dtSaleDetail;
    }
    
    public function getSettleSummary($tsessionid) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * From pos.fn_tday_settle_summary(:ptsessionid)');
        $cmm->addParam('ptsessionid', $tsessionid);
        $dtSettle = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dtSettle;
    }
    
    public function getPendingDocList($tsessionid) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("With pending_doc
            As
            (   Select inv_id, doc_date, inv_amt 
                From pos.inv_control 
                Where status != 5 And tday_session_id=:ptsessionid
                Union All
                Select invoice_id, doc_date, invoice_amt
                From ar.invoice_control
                Where (annex_info->'pos'->>'is_pos')::boolean 
                    And (annex_info->'pos'->>'tday_session_id')::uuid = :ptsessionid
                    And status != 5
            )
            Select inv_id, doc_date, inv_amt
            From pending_doc;");
        $cmm->addParam('ptsessionid', $tsessionid);
        $dtPendingDoc = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dtPendingDoc;
    }

    public function closeTdayForHandover($tsessionid) {
        $result = [
            'status' => 'Fail',
            'msg' => 'Close for Handover failed'
        ];
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select user_id From pos.tday Where user_id=:puser_id And tday_session_id=:ptsessionid');
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $cmm->addParam('ptsessionid', $tsessionid);
        $dtinfo = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dtinfo->Rows())!=1) {
            $result['msg'] = 'Txn. day can be closed only by the user who started the Txn. Day';
            return $result;
        }
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("With pending_doc
            As 
            (   Select count(*) as rcount
                From pos.inv_control 
                Where status != 5 And tday_session_id=:ptsessionid
                Union All
                Select count(*)
                From ar.invoice_control
                Where status != 5 
                    And (annex_info->'pos'->>'is_pos')::boolean
                    And (annex_info->'pos'->>'tday_session_id')::uuid = :ptsessionid
            )
            Select Sum(rcount) as rcount
            From pending_doc;");
        $cmm->addParam('ptsessionid', $tsessionid);
        $dtPendingDoc = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dtPendingDoc->Rows())==1 && intval($dtPendingDoc->Rows()[0]['rcount'])>0) {
            $result['msg'] = 'Txn. day has '.$dtPendingDoc->Rows()[0]['rcount'].' pending Invoices for post. Failed to close day';
        } else {
            $cmmClose = new \app\cwf\vsla\data\SqlCommand();
            $cmmClose->setCommandText('Update pos.tday Set tday_status=3, end_time=current_timestamp(0)  Where tday_session_id=:ptsessionid');
            $cmmClose->addParam('ptsessionid', $tsessionid);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmmClose);
            $result['status'] = "OK";
        }
        return $result;
    }
    
    public static function getViewForSettle() {
        return \yii::$app->controller->renderPartial('@app/core/pos/tday/TdayEodStartView');
    }
}

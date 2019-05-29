<?php

namespace app\core\tx\gstIN;

class GstINWorker {

    public static function processGstnResponse($result) {
        $res['status'] = 'failed';
        if ($result != NULL) {
            if (property_exists($result, 'status_cd')) {
                if ($result->status_cd == 1) {
                    $res['status'] = 'success';
                    $res['txn'] = $result->header->txn;
                    $res['ip'] = $result->header->ip_address;
                } else {
                    $res['status'] = 'failed';
                }
            }
            if (property_exists($result, 'status_desc')) {
                $res['desc'] = $result->status_desc;
            }
            if (property_exists($result, 'error')) {
                if (property_exists($result->error, 'message')) {
                    $res['desc'] = $result->error->message;
                }
            }
        }
        return $res;
    }

    public static function storeGstnSession($result) {
//        $comboid = md5(\app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID() .
//                \app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gstin']);
        $comboid = md5(\app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gstin']);
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = "select * from sys.sp_gstn_session_add_update(:pcore_session, :pgstn_response, :pbranch_id)";
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('pcore_session', $comboid);
        $cmm->addParam('pgstn_response', $result);
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('branch_id'));
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
    }

    public static function removeGstnSession() {
        $comboid = md5(\app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gstin']);
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = "select * from sys.sp_gstn_end_session(:pcore_session)";
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('pcore_session', $comboid);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
    }

    public static function logGstnSession($result) {
//        $comboid = md5(\app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID() .
//                \app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gstin']);
        $comboid = md5(\app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gstin']);
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = "Insert into sys.gstn_session_log(core_session_id,session_info,branch_id) values (:pcore_session, :pgstn_response, :pbranch_id)";
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('pcore_session', $comboid);
        $cmm->addParam('pgstn_response', json_encode($result));
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('branch_id'));
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
    }

    public static function getGstnSession() {
//        $comboid = md5(\app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID() .
//                \app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gstin']);
        $comboid = md5(\app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gstin']);
        $res = new \stdClass();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = "select session_info,EXTRACT(EPOCH FROM (current_timestamp(0) - last_updated))<19801 as session_valid "
                . "from sys.gstn_session where core_session_id = :pcore_session";
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('pcore_session', $comboid);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            if ((bool) $dt->Rows()[0]['session_valid']) {
                $sinfo = json_decode($dt->Rows()[0]['session_info']);
                $res->username = $sinfo->header->gst_username;
                $res->statecd = $sinfo->header->state_cd;
                $res->txn = $sinfo->header->txn;
                $res->ipaddress = $sinfo->header->ip_address;
                $res->session_exists = true;
                return $res;
            }
        }
        $res->session_exists = false;
        return $res;
    }

    public static function getGstnApiInfo() {
        $apiaccess = new \stdClass();
        $cwfConfig = \yii::$app->params['cwf_config'];
        if (isset($cwfConfig['mastergst'])) {
            $apiaccess->useremail = $cwfConfig['mastergst']['useremail'];
            $apiaccess->clientid = $cwfConfig['mastergst']['clientid'];
            $apiaccess->clientsecret = $cwfConfig['mastergst']['clientsecret'];
            $apiaccess->ipaddress = $cwfConfig['mastergst']['ipaddress'];
            return $apiaccess;
        }
        return NULL;
    }

}

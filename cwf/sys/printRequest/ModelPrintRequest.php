<?php

namespace app\cwf\sys\printRequest;

class ModelPrintRequest {

    public $dt_printRequest;

    public function __construct() {
        $this->getData();
    }

    public function getData() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = "Select '' as user_name, doc_print_request_id, doc_id, requested_by_user_id, requested_on, false as approve, false as reject "
                . "from sys.doc_print_request where allowed_by_user_id is NULL and printed_on is NULL and closed = false";
        $cmm->setCommandText($cmmtext);
        $this->dt_printRequest = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = "Select user_id, full_user_name, email from sys.user";
        $cmm->setCommandText($cmmtext);
        $dt_user = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        $this->dt_printRequest->addColumn('doc_date_sort', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        foreach ($this->dt_printRequest->Rows() as &$row_req) {
            $row_req['doc_date_sort'] = strtotime($row_req['requested_on']);
            foreach ($dt_user->Rows() as $row_user) {
                if ($row_req['requested_by_user_id'] == $row_user['user_id']) {
                    $row_req['user_name'] = $row_user['full_user_name'];
                    break;
                }
            }
        }
    }

    public function setData($model) {
        $approved = '';
        $rejected = '';
        foreach ($model->dt_printRequest as $rw) {
            if ($rw->reject == TRUE) {
                if ($rejected == '') {
                    $rejected .= $rw->doc_print_request_id;
                } else {
                    $rejected .= ', ' . $rw->doc_print_request_id;
                }
            } else if ((bool) $rw->approve == TRUE) {
                if ($approved == '') {
                    $approved .= $rw->doc_print_request_id;
                } else {
                    $approved .= ', ' . $rw->doc_print_request_id;
                }
            }
        }
        $cn = \app\cwf\vsla\data\DataConnect::getCn(\app\cwf\vsla\data\DataConnect::COMPANY_DB);
        try {
            if ($approved != '') {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText("Update sys.doc_print_request 
                                set allowed_by_user_id=:puser_id, last_updated=now() 
                                where doc_print_request_id in (" . $approved . ")");
                $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
            }
            if ($rejected != '') {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText("Update sys.doc_print_request 
                                set allowed_by_user_id=:puser_id, closed=true, last_updated=now() 
                                where doc_print_request_id in (" . $rejected . ")");
                $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
            }
            $cn->commit();
            $cn = null;
        } catch (\Exception $ex) {
            if ($cn->inTransaction()) {
                $cn->rollBack();
                $cn = null;
            }
            return $ex->getMessage();
        }
    }

}

<?php

namespace app\cwf\sys\widgetRequest;

class ModelWidgetRequest {

    public $dt_widgetRequest;

    public function __construct() {
        $this->getData();
    }

    public function getData() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = "Select '' as user_name, a.widget_request_id, a.widget_id, a.requested_by_user_id, 
                        b.widget_name, a.request_date, a.subscribe, false as approve, false as reject  
                    from sys.widget_request a 
                    inner join sys.widget b on a.widget_id = b.widget_id
                    where a.request_closed=false and a.handled_on is NULL and a.handler_user_id is NULL";
        $cmm->setCommandText($cmmtext);
        $this->dt_widgetRequest = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = "Select user_id, full_user_name, email from sys.user";
        $cmm->setCommandText($cmmtext);
        $dt_user = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        $this->dt_widgetRequest->addColumn('doc_date_sort', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        foreach ($this->dt_widgetRequest->Rows() as &$row_req) {
            $row_req['doc_date_sort'] = strtotime($row_req['request_date']);
            foreach ($dt_user->Rows() as $row_user) {
                if ($row_req['requested_by_user_id'] == $row_user['user_id']) {
                    $row_req['user_name'] = $row_user['full_user_name'];
                    break;
                }
            }
        }
    }

    public function setData($model) {
        $union = '';
        try {
            $cn = \app\cwf\vsla\data\DataConnect::getCn(\app\cwf\vsla\data\DataConnect::COMPANY_DB);
            foreach ($model->dt_widgetRequest as $rw) {
                if ((bool) $rw->approve == TRUE && (bool) $rw->subscribe == TRUE) {
                    $cmm = new \app\cwf\vsla\data\SqlCommand();
                    $cmm->setCommandText('select * from sys.fn_update_user_widget(:pwidget_id ,:puser_id ) ');
                    $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
                    $cmm->addParam('pwidget_id', $rw->widget_id);
                    \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                } else if ($rw->approve == TRUE && (bool) $rw->subscribe == FALSE) {
                    $cmm = new \app\cwf\vsla\data\SqlCommand();
                    $cmm->setCommandText('delete from sys.user_widget_access where widget_id = :pwidget_id and user_id = :puser_id');
                    $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
                    $cmm->addParam('pwidget_id', $rw->widget_id);
                    \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                }
                if ($rw->reject == TRUE || $rw->approve == TRUE) {
                    if ($union == '') {
                        $union .= $rw->widget_request_id;
                    } else {
                        $union .= ', ' . $rw->widget_request_id;
                    }
                }
            }
            if ($union != '') {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText("Update sys.widget_request 
                                set handler_user_id = :puser_id, handled_on = now(), request_closed = true, last_updated = now() 
                                where widget_request_id in (" . $union . ")");
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

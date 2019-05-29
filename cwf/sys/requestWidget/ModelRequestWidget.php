<?php

namespace app\cwf\sys\requestWidget;

use app\cwf\vsla\data\DataConnect;
use app\cwf\vsla\security\SessionManager;

class ModelRequestWidget {

    public $dt_requestWidget, $dt_requestWidget_old;

    public function __construct() {
        $this->getData();
    }

    public function getData() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select widget_id, widget_name, false as subscribe, false as pending from sys.widget');
        $dtWidget = DataConnect::getData($cmm);

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select widget_id from sys.user_widget_access where user_id=:puser_id');
        $cmm->addParam('puser_id', SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $dtWidgetAC = DataConnect::getData($cmm);

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select widget_id, subscribe from sys.widget_request where requested_by_user_id=:puser_id and request_closed=false');
        $cmm->addParam('puser_id', SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $dtWidgetReq = DataConnect::getData($cmm);

        foreach ($dtWidget->Rows() as &$wrow) {
            foreach ($dtWidgetAC->Rows() as $arow) {
                if ((int) $wrow['widget_id'] == (int) $arow['widget_id']) {
                    $wrow['subscribe'] = true;
                    break;
                }
            }
            foreach ($dtWidgetReq->Rows() as $rrow) {
                if ((int) $wrow['widget_id'] == (int) $rrow['widget_id']) {
                    $wrow['subscribe'] = (bool) $rrow['subscribe'];
                    $wrow['pending'] = true;
                    break;
                }
            }
        }
        $this->dt_requestWidget_old = $dtWidget;
        $this->dt_requestWidget = $dtWidget;
    }

    public function setData($model) {
        foreach ($model->dt_requestWidget_old as $oldrow) {
            foreach ($model->dt_requestWidget as $newrow) {
                if ($oldrow->widget_id == $newrow->widget_id) {
                    if ($oldrow->subscribe != $newrow->subscribe) {
                        if ($newrow->subscribe == FALSE) {
                            $this->unsubscribe($newrow);
                        } else if ($oldrow->pending) {
                            if ($newrow->subscribe == FALSE) {
                                $this->closeRequest($newrow);
                            } else {
                                $this->updateRequest($newrow);
                            }
                        } else {
                            $this->addRequest($newrow);
                        }
                    }
                    break;
                }
            }
        }
    }

    private function addRequest($row) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Insert into sys.widget_request(widget_request_id, widget_id, requested_by_user_id, subscribe) values "
                . " ((select coalesce(max(widget_request_id),0)+1 from sys.widget_request), :pwidget_id, :puser_id, :psubscribe)");
        $cmm->addParam('pwidget_id', $row->widget_id);
        $cmm->addParam('puser_id', SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $cmm->addParam('psubscribe', $row->subscribe);
        DataConnect::exeCmm($cmm);
    }

    private function updateRequest($row) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Update sys.widget_request set subscribe = :psubscribe, last_updated = now() "
                . " where widget_id = :pwidget_id and requested_by_user_id = :puser_id and request_closed = false");
        $cmm->addParam('pwidget_id', $row->widget_id);
        $cmm->addParam('puser_id', SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $cmm->addParam('psubscribe', $row->subscribe);
        DataConnect::exeCmm($cmm);
    }

    private function closeRequest($row) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Update sys.widget_request set subscribe = :psubscribe, request_closed = true, last_updated = now() "
                . " where widget_id = :pwidget_id and requested_by_user_id = :puser_id and request_closed = false");
        $cmm->addParam('pwidget_id', $row->widget_id);
        $cmm->addParam('puser_id', SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $cmm->addParam('psubscribe', $row->subscribe);
        DataConnect::exeCmm($cmm);
    }
    
    private function unsubscribe($row){
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Delete from sys.user_widget_access where widget_id = :pwidget_id and user_id = :puser_id');
        $cmm->addParam('pwidget_id', $row->widget_id);
        $cmm->addParam('puser_id', SessionManager::getInstance()->getUserInfo()->getUser_ID());
        DataConnect::exeCmm($cmm);
    }

}

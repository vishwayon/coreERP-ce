<?php

namespace app\cwf\sys\fyAccess;

/**
 * Description of ModelFyAccess
 *
 * @author dev
 */
class ModelFyAccess {

    public $dt_usr;

    public function __construct() {
        $this->getData();
    }

    public function getData() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = "select user_id,full_user_name,clfy_access from sys.user where is_active=true";
        $cmm->setCommandText($cmmtext);
        $this->dt_usr = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
    }

    public function setData($model) {
        $approved = '';
        $rejected = '';
        foreach ($model->dt_usr as $rw) {
            if ((bool) $rw->clfy_access == TRUE) {
                if ($approved == '') {
                    $approved .= $rw->user_id;
                } else {
                    $approved .= ', ' . $rw->user_id;
                }
            } else {
                if ($rejected == '') {
                    $rejected .= $rw->user_id;
                } else {
                    $rejected .= ', ' . $rw->user_id;
                }
            }
        }
        $cn = \app\cwf\vsla\data\DataConnect::getCn(\app\cwf\vsla\data\DataConnect::MAIN_DB);
        try {
            if ($approved != '') {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText("Update sys.user 
                                set clfy_access=true, last_updated=now() 
                                where user_id in (" . $approved . ")");
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
            }
            if ($rejected != '') {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText("Update sys.user 
                                set clfy_access=false, last_updated=now() 
                                where user_id in (" . $rejected . ")");
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

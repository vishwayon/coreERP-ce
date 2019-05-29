<?php

namespace app\cwf\sys\auditTrail;

/**
 * Description of ModelAuditTrail
 *
 * @author dev
 */
class ModelAuditTrail {
    
    public static function getAuditTrail($vch_id){
        $res = [];
        $res['status'] = 'Document not found'; // for this branch or is not accessible
        $finyear = substr(\app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'), 0, 2);
        $comp_code = '';
        $br_code = '';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select company_code, branch_code from sys.branch where branch_id=:pbranch_id');
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $dtbr = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtbr->Rows()) > 0) {
            $br_code = (string) $dtbr->Rows()[0]['branch_code'];
            $comp_code = (string) $dtbr->Rows()[0]['company_code'];

            if (array_key_exists('docSeqCC', \yii::$app->params['cwf_config']) && \yii::$app->params['cwf_config']['docSeqCC']) {
                // check for VCH YY CP BR
                $vchRegex2 = '/^[A-Z0-9]{2,4}' . $finyear . $comp_code . $br_code . '/';
                $mtch2 = array();
                preg_match($vchRegex2, $vch_id, $mtch2);
                if (count($mtch2) > 0) {
                    $vch_type = substr($mtch2[0], 0, -6);
                    $res = self::getVchMenu($vch_type);
                    if ($res['status'] == 'OK') {
                        $res['qpid'] = $vch_id;
                    }
                    if (self::checkVch($res) == TRUE) {
                        $res['status'] = 'OK';
                    } else {
                        $res['status'] = 'Document not found';
                    }
                } else {
                    $vchRegex = '/^[A-Z0-9]{2,4}' . $finyear . $comp_code . '/';
                    $mtch = array();
                    preg_match($vchRegex, $vch_id, $mtch);
                    if (count($mtch) > 0) {
                        $brname = '';
                        $brdocid = str_replace($mtch[0], '', $vch_id);
                        $brcd = substr($brdocid, 0, 2);
                        $cmm = new \app\cwf\vsla\data\SqlCommand();
                        $cmm->setCommandText('select company_code, branch_name from sys.branch where branch_code=:pbranch_code');
                        $cmm->addParam('pbranch_code', $brcd);
                        $dtbr = \app\cwf\vsla\data\DataConnect::getData($cmm);
                        if (count($dtbr->Rows()) > 0) {
                            $res['status'] = 'Document belongs to <strong>' . $dtbr->Rows()[0]['branch_name'] . '</strong><br/>Kindly connect to ' . $dtbr->Rows()[0]['branch_name'] . ' and view.';
                        }
                    } else {
                        $res['status'] = 'Document not available in the current branch and financial year';
                    }
                }
            } else {
                // check for VCH YY BR
                $vchRegex = '/^[A-Z0-9]{2,4}' . $finyear . $br_code . '/';
                $mtch = array();
                preg_match($vchRegex, $vch_id, $mtch);
                if (count($mtch) > 0) {
                    $vch_type = substr($mtch[0], 0, -4);
                    $res = self::getVchMenu($vch_type);
                    if ($res['status'] == 'OK') {
                        $res['qpid'] = $vch_id;
                    }
                    if (self::checkVch($res) == TRUE) {
                        $res['status'] = 'OK';
                    } else {
                        $res['status'] = 'Document not found';
                    }
                } else {
                    $vchRegex = '/^[A-Z0-9]{2,4}' . $finyear . '/';
                    $mtch = array();
                    preg_match($vchRegex, $vch_id, $mtch);
                    if (count($mtch) > 0) {
                        $brname = '';
                        $brdocid = str_replace($mtch[0], '', $vch_id);
                        $brcd = substr($brdocid, 0, 2);
                        $cmm = new \app\cwf\vsla\data\SqlCommand();
                        $cmm->setCommandText('select company_code, branch_name from sys.branch where branch_code=:pbranch_code');
                        $cmm->addParam('pbranch_code', $brcd);
                        $dtbr = \app\cwf\vsla\data\DataConnect::getData($cmm);
                        if (count($dtbr->Rows()) > 0) {
                            $res['status'] = 'Document belongs to <strong>' . $dtbr->Rows()[0]['branch_name'] . '</strong><br/>Kindly connect to ' . $dtbr->Rows()[0]['branch_name'] . ' and view.';
                        }
                    } else {
                        $res['status'] = 'Document not available in the current branch and financial year';
                    }
                }
            }
        }
        return $res;
    }

    private static function getVchMenu($vch_type) {

        //check if doc seq exists in menu vch_type
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.menu where :pvch_type = any(vch_type)');
        $cmm->addParam('pvch_type', $vch_type);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $access_level = self::getAccessRights((string) $dt->Rows()[0]['bo_id']);
            if ($access_level > \app\cwf\vsla\security\AccessLevels::NOACCESS) {
                $res = self::getVchInfo($dt->Rows()[0]['link_path']);
                return $res;
            } else {
                return ['status' => 'Document not accessible in current branch'];
            }
        }

        //check if doc seq exists in income types
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select a.income_type_name,b.link_path,b.bo_id from ar.income_type a, sys.menu b where a.seq_type = :pvch_type and b.menu_name=:pgstinvmenu');
        $cmm->addParam('pvch_type', $vch_type);
        $cmm->addParam('pgstinvmenu', 'mnuGstInvoice');
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $access_level = self::getAccessRights((string) $dt->Rows()[0]['bo_id']);
            if ($access_level > \app\cwf\vsla\security\AccessLevels::NOACCESS) {
                $res = self::getVchInfo($dt->Rows()[0]['link_path']);
                return $res;
            } else {
                return ['status' => 'Document not accessible in current branch'];
            }
        }

        return ['status' => 'error'];
    }

    private static function getAccessRights($bo_id) {
        $access_level = \app\cwf\vsla\security\AccessLevels::NOACCESS;
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.get_menu_access_for_userv2(:puser_id, :pbranch_id, :pbo_id::uuid) as en_access_level');
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('pbo_id', $bo_id);
        $acdt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($acdt->Rows()) > 0) {
            $access_level = \app\cwf\vsla\security\AccessLevels::getLevel((int) $acdt->Rows()[0]['en_access_level']);
        }
        return $access_level;
    }

    private static function getVchInfo($itmlink_path) {
        $res = [];
        $res['status'] = 'error';
        if ($itmlink_path != '') {
            $posn = strpos($itmlink_path, '&');
            $modulepath = substr($itmlink_path, 0, $posn);
            $module = str_replace('form/collection', '', $modulepath);
            $res['qpRoute'] = $module;
            $cview = substr($itmlink_path, $posn);
            $collectionview = str_replace('&formName=', '', $cview);
            $collectionpath = $module . $collectionview;
            $collpath = \yii::getAlias('@app/' . $collectionpath . '.xml');
            $xcoll = simplexml_load_file($collpath);
            if (isset($xcoll->collectionView)) {
                $editView = (string) $xcoll->collectionView->attributes()->editView;
                $res['qpForm'] = $editView;
                $editviewpath = \yii::getAlias('@app/' . $module . $editView . '.xml');
                $xeditview = simplexml_load_file($editviewpath);
                if (isset($xeditview->formView)) {
                    $bo = (string) $xeditview->formView->attributes()->bindingBO;
                    $bopath = \yii::getAlias('@app/' . $module . $bo . '.xml');
                    $xbo = simplexml_load_file($bopath);
                    if (isset($xbo->businessObject->controlTable)) {
                        if (isset($xbo->businessObject->controlTable->tableName)) {
                            $res['ctr_table'] = (string) $xbo->businessObject->controlTable->tableName;
                        }
                        if (isset($xbo->businessObject->controlTable->primaryKey)) {
                            $res['ctr_key'] = (string) $xbo->businessObject->controlTable->primaryKey;
                        }
                    }
                    if (isset($xeditview->formView->jsEvents->afterLoadEvent)) {
                        $res['afterLoadEvent'] = (string) $xeditview->formView->jsEvents->afterLoadEvent;
                    }
                    if (isset($xeditview->formView->keyField)) {
                        $res['qpKey'] = (string) $xeditview->formView->keyField;
                    }
                    $res['status'] = 'OK';
                }
            }
            if (count($res) >= 6) {
                $res['status'] = 'OK';
            }
        }
        return $res;
    }

    private static function checkVch($res) {
//        if ($res['status'] !== 'OK') {
//            return FALSE;
//        }
//        $pkfield = $res['ctr_key'];
//        $ctable = $res['ctr_table'];
//        $pkid = $res['qpid'];
//        $sql = "Select $pkfield From $ctable Where $pkfield = :pkid";
//        $cmm = new \app\cwf\vsla\data\SqlCommand();
//        $cmm->setCommandText($sql);
//        $cmm->addParam("pkid", $pkid);
//        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
//        if (count($dt->Rows()) == 1) {
//            return TRUE;
//        }
//        return FALSE;
        return TRUE;
    }

}

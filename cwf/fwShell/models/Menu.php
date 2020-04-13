<?php

namespace app\cwf\fwShell\models;

class Menu {

    public $menuitems;
    public $smallmenu = '';
    private $dt;
    private $userinfo;

    public function __construct() {
        $this->userinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
        if ($this->userinfo === NULL) {
            return;
        } else if (!$this->userinfo->isAdmin() && !$this->userinfo->isOwner()) {
            if ($this->userinfo->getCompany_ID() === -1) {
                return;
            } else if ($this->userinfo->getSessionVariable('finyear_id') === -1) {
                return;
            }
        }
        $this->menuitems = array();
        $this->menuitems['items'] = array();
        $this->addDashboard();
        if ($this->userinfo->isAdmin() || $this->userinfo->isOwner()) {
            $this->GetMenuItems();
        } else {
            $this->GetMenuAccess();
        }
    }

    private function GetMenuItems() {
        $company_id = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('company_id');
        if ($this->userinfo->isOwner()) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select * from sys.menu_owner where is_hidden=false And (company_na = :pcompany_na OR :pcompany_na = false) Order By menu_key');
            if ($company_id == -1) {
                $cmm->addParam('pcompany_na', true);
            } else {
                $cmm->addParam('pcompany_na', false);
            }
            $this->dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        } elseif ($this->userinfo->isAdmin()) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select * from sys.menu_admin where is_hidden=false And (company_na = :pcompany_na OR :pcompany_na = false) Order By menu_key');
            if ($company_id == -1) {
                $cmm->addParam('pcompany_na', true);
            } else {
                $cmm->addParam('pcompany_na', false);
            }
            $this->dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        }

        if (count($this->dt->Rows()) > 0) {
            $this->setmenu('0', $this->menuitems);
//            if($this->userinfo->isAdmin()){
//                $this->setmenu('0', $this->menuitems);
//            }elseif ($this->userinfo->isOwner()){
//                $this->setmenu('-1', $this->menuitems);
//            }
        }
    }

    private function GetMenuAccess() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select *, 0 as pending_cnt from sys.get_menu_for_userv2(:puser_id, :pbranch_id)');
        $uid = $this->userinfo->getUser_ID();
        $cmm->addParam('puser_id', $uid);
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $acdt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($acdt->Rows()) > 0) {
            $this->dt = $acdt;
            if ($this->userinfo->isAdmin()) {
                $this->setmenu('0', $this->menuitems);
            } else {
                $this->setmenu('-1', $this->menuitems);
            }
        }
    }

    /*
     * Gets Menu Access Level for the requested bo id.
     */

    public function verifyMenuAccess(string $bo_id) {
        $access_level = -1;
        if ($this->userinfo->isAdmin() || $this->userinfo->isOwner() || $bo_id == "UserProfile" || $bo_id == "Feedback") {
            return 3;
        }
        $md5id = md5($bo_id);
        foreach ($this->dt->Rows() as $rw) {
            if (str_replace('-', '', (string) $rw['bo_id']) === (string) $md5id) {
                //if((string)$rw['bo_id']===(string)$boid){
                $access_level = (int) $rw['en_access_level'];
            }
        }
        return $access_level;
    }

    public static function verifyInitLogin() {
        $userinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
        if ($userinfo->isOwner()) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select count(*) as companies from sys.company where domain_id=:pdomain_id');
            $cmm->addParam('pdomain_id', \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('domain_id'));
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
            if (count($dt->Rows()) > 0) {
                if ((int) $dt->Rows()[0]['companies'] == 0) {
                    return true;
                }
            }
        }
        return false;
    }

    private function setmenu($parentkey, &$refparent) {
        $baseurl = \Yii::$app->urlManager->getBaseUrl();
        foreach ($this->dt->Rows() as $rw) {
            if ((string) $rw['parent_menu_id'] == $parentkey) {
                $mi = array();
                $mi['label'] = (string) $rw['menu_text'];
                $mi['label'] .= ' <span id="pc_' . $rw['menu_id'] . '" menu-name="' . $rw['menu_name'] . '" class="badge pull-right inbox-badge menu-cnt-badge" title="Pending Documents" style="display:none;"></span>';
                if ($parentkey == '-1') {
                    $ip = strtoupper($rw['menu_code']);
//                    $mi['label'] = '<span style="width:22px; height:22px; float:left; font-family:Arial Narrow; font-weight:bold;margin-right:10px;">'.$ip.'</span>'.$mi['label'];
                    $this->smallmenu .= '<div style="width:32px; height:32px;text-align: center;">'
                            . '<span style="font-family:Arial Narrow; font-weight:bold; color:#9d9d9d;">' . $ip . '</span></div>';
//                    $mi['label'] = '<img style="width: 22px; float: left; padding: 0 10px 10px 0;" src="../Images/'.$ip.'"/>'.$mi['label'];
//                    $this->smallmenu .= '<div><img style="width: 22px;padding: 5px;margin: 5px;" src="../Images/'.$ip.'"/></div>';                    
                }
                $mi['menu_id'] = (string) $rw['menu_id'];
                $mi['parentkey'] = (string) $rw['parent_menu_id'];
                if ($rw['link_path'] == '') {
                    $mi['url'] = '#';
                    if ($parentkey == '-1' || $parentkey == '0') {
                        $mi['options'] = ['class' => 'root-item abs'];
                    } else {
                        $mi['options'] = ['class' => 'root-item'];
                    }
                } else {
                    if (strpos($rw['link_path'], 'javascript:coreWebApp.rendercontents(') !== FALSE) {
                        $mi['url'] = \yii\helpers\Html::encode(str_replace('../', '@app/', $rw['link_path']));
                    } else {
                        $mi['url'] = \yii\helpers\Html::encode('javascript:coreWebApp.rendercontents(\'?r='
                                        . str_replace('../', '@app/', $rw['link_path']) . '&menuid=' . (string) $rw['menu_id'] . '\')');
                    }
                    $mi['options'] = ['class' => 'nonroot-item'];
                }
                $mi['bo_id'] = (int) $rw['bo_id'];
                $mi['menu_name'] = $rw['menu_name'];
                $mi['items'] = array();
                $this->setmenu((string) $mi['menu_id'], $mi);
                if ($refparent == NULL) {
                    array_push($this->menuitems['items'], $mi);
                } else {
                    array_push($refparent['items'], $mi);
                }
            }
        }
    }

    private function addDashboard() {
        $cmmtxt = 'select * from sys.user_dashboard where user_id=:puser_id';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($cmmtxt);
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        if (count($dt->Rows()) > 0) {
            $mi = array();
            $mi['label'] = 'Dashboard'; //'<span style="margin-left:14px;">Dashboards</span>';
//            $mi['icon'] = 'stats';
            $mi['menu_id'] = '0';
            $mi['parentkey'] = '-1';
            $mi['url'] = 'javascript:coreWebApp.rendercontents(\'?r=cwf/fwShell/main/dashboard&dbd=home\')';
            $mi['options'] = ['class' => 'nonroot-item'];
            $mi['bo_id'] = -1;
            array_push($this->menuitems['items'], $mi);
            $this->smallmenu .= '<div style="margin-bottom:-5px 0 5px 0;width:32px; height:32px;text-align: center;"><span class="glyphicon glyphicon-stats" '
                    . 'style="margin: 5px 0;color:whitesmoke;margin-bottom:12px; color:#9d9d9d;"/></div>';
        }
    }

    public static function getPendingCount() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select *, 0 as pending_cnt from sys.get_doc_for_user(:puser_id, :pbranch_id)');
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $acdt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($acdt->Rows()) > 0) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select company_code, branch_code from sys.branch where branch_id=:pbranch_id');
            $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $brdt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($brdt->Rows()) > 0) {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                if (array_key_exists('docSeqCC', \yii::$app->params['cwf_config']) && \yii::$app->params['cwf_config']['docSeqCC']) {
                    $cmmtext = "select count(*) as cnt,a.bo_id from 
                        (
                        select doc_id, branch_id, bo_id, user_id_created from sys.doc_created 
                        where user_id_created = :puser_id and branch_id=:pbranch_id 
                            and doc_id like concat('%',(left(:pfinyear, 2) || :pccode || :pbrcode),'%')
                        union 
                        select doc_id, branch_id, bo_id, user_id_to from sys.doc_wf
                        where user_id_to = :puser_id and branch_id=:pbranch_id
                            and doc_id like concat('%',(left(:pfinyear, 2) || :pccode || :pbrcode),'%')
                        )a group by bo_id";
                } else {
                    $cmmtext = "select count(*) as cnt,a.bo_id from 
                        (
                        select doc_id, branch_id, bo_id, user_id_created from sys.doc_created 
                        where user_id_created = :puser_id and branch_id=:pbranch_id 
                            and doc_id like concat('%',(left(:pfinyear, 2) || :pbrcode ),'%')
                        union 
                        select doc_id, branch_id, bo_id, user_id_to from sys.doc_wf
                        where user_id_to = :puser_id and branch_id=:pbranch_id
                            and doc_id like concat('%',(left(:pfinyear, 2) || :pbrcode ),'%')
                        )a group by bo_id";
                }
                $cmm->setCommandText($cmmtext);
                $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
                $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
                $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
                if (array_key_exists('docSeqCC', \yii::$app->params['cwf_config']) && \yii::$app->params['cwf_config']['docSeqCC']) {
                    $cmm->addParam('pccode', $brdt->Rows()[0]['company_code']);
                }
                $cmm->addParam('pbrcode', $brdt->Rows()[0]['branch_code']);
                $dtcnt = \app\cwf\vsla\data\DataConnect::getData($cmm);
                foreach ($acdt->Rows() as &$rw) {
                    if ($rw['count_class'] != '') {
                        $cnt = self::getCustomPendingCnt((string) $rw['menu_name'], (string) $rw['count_class']);
                        $rw['pending_cnt'] = $cnt;
                        if ($rw['parent_menu_id'] != -1) {
                            self::setpendingcnt($acdt, $rw['parent_menu_id'], $cnt);
                        }
                    } else {
                        if ($rw['bo_id'] == NULL || (int) $rw['menu_type'] != 1) {
                            continue;
                        }
                        foreach ($dtcnt->Rows() as $rwcnt) {
                            if (str_replace('-', '', $rw['bo_id']) == md5($rwcnt['bo_id'])) {
                                $rw['pending_cnt'] = (int) $rwcnt['cnt'];
                                if ($rw['parent_menu_id'] != -1) {
                                    self::setpendingcnt($acdt, $rw['parent_menu_id'], (int) $rwcnt['cnt']);
                                }
                                break;
                            }
                        }
                    }
                }
                return $acdt;
            }
            return json_encode(['There is an issue getting pending count.']);
        }
        return json_encode(['There is an issue getting pending count.']);
    }

    private static function setpendingcnt(\app\cwf\vsla\data\DataTable &$acdt, $parentid, $cnt) {
        foreach ($acdt->Rows() as &$rw) {
            if ($rw['menu_id'] == $parentid) {
                $rw['pending_cnt'] += (int) $cnt;
                if ($rw['parent_menu_id'] != -1) {
                    self::setpendingcnt($acdt, $rw['parent_menu_id'], $cnt);
                }
            }
        }
    }

    private static function getCustomPendingCnt($menuName, $className) {
        $cntclass = new $className();
        $methodName = $menuName . 'Count';
        if (method_exists($cntclass, $methodName)) {
            $cnt = $cntclass->$methodName();
            return intval($cnt);
        }
        return 0;
    }

}

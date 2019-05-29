<?php

namespace app\cwf\fwShell\controllers;

use app\cwf\vsla\base\WebController;

class MainController extends WebController {

    public function init() {
        parent::init();

        $this->ModulePath = '../cwf/' . $this->module->id;
        $path = '../cwf/' . $this->module->id . '/' . $this->id . '/Menu.xml';

        $this->XmlViewPath = $path;
    }

    public function actionHome() {
        $isMobile = \app\cwf\vsla\security\SessionManager::getInstance()->isMobile();
        if ($isMobile) {
            $mobmenu=new \app\cwf\fwShell\models\MenuMobile();
            return $this->render('MenuMobileView',['model'=>$mobmenu]);
        } else {
            $this->layout = "/main_menu.php";
            $menu = new \app\cwf\fwShell\models\Menu();
            return $this->render('MenuView', ['model' => $menu]);
        }
    }

    public function actionAbout() {
        return $this->renderPartial('About');
    }

    public function actionLookup($namedlookup, $displaymember, $valuemember) {
        $lookup = new \app\cwf\vsla\xmlbo\LookupInfo($namedlookup, $displaymember, $valuemember);
        \yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $lookup->Items;
    }

    public function actionLookup2($namedlookup, $displaymember, $valuemember, $filter, $term = '', $id = '', $page = 1, $page_limit = 20, $nodefault = false) {
        $query_id = NULL;
        if (isset($_GET['id'])) {
            $query_id = $_GET['id'];
        }
        
        $lookup = new \app\cwf\vsla\xmlbo\LookupInfo
                ($namedlookup, $displaymember, $valuemember, $filter, $query_id, $term, $nodefault);
        $return_arr = $lookup->Results;

        $ret = array();
        if (isset($_GET['id'])) {
            foreach ($lookup->Results as $resobj) {
                if ($resobj->id == $_GET['id']) {
                    $ret = $resobj;
                }
            }
        } else {
            $ret['results'] = array_slice($return_arr, ($page - 1) * $page_limit, $page_limit);
        }
        \yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $ret;
    }

    public function actionLookup3($namedlookup, $displaymember, $filter, $q = '') {
        if (isset($_GET['q']) && strlen($_GET['q']) > 0) {
            $qry = str_ireplace("'", "''", $_GET['q']);
            if (trim($filter) !== '') {
                $filter .= ' And ' . $displaymember . ' ilike \'' . $qry . '%\' limit 10';
            } else {
                $filter = $displaymember . ' ilike \'' . $qry . '%\' limit 10';
            }
        }

        $lookup = new \app\cwf\vsla\xmlbo\LookupInfo($namedlookup, $displaymember, NULL, $filter);
        \yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $lookup->Results;
    }

    public function getViewPath() {
        return $this->ModulePath . '/views';
        //parent::getViewPath();
    }

    public function actionIndex($viewName = '', $viewParams = '') {
        if (\app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->isAdmin() || \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->isOwner()) {
            return $this->runAction('home');
        }
        return $this->render('LogonSelectionView', ['model' => new \app\cwf\fwShell\models\LogonSelection()]);
    }

    public function actionSwitchsession() {
        //This request would have come without a session id. Hence an auto new session is created
        return $this->runAction('index');
    }

    public function actionBranchlist($company_id) {
        $model = new \app\cwf\fwShell\models\LogonSelection();
        $model->setCompanyInfo($company_id);
        return $model->getBranchList();
    }

    public function actionFinyearlist($branch_id) {
        $model = new \app\cwf\fwShell\models\LogonSelection();
        $model->setBranchInfo($branch_id);
        return $model->getFinyearList();
    }

    public function actionSelectFinyear($finyear_id) {
        $model = new \app\cwf\fwShell\models\LogonSelection();
        $model->SetFinYearInfo($finyear_id);
        if ($model->validateSelection($finyear_id)) {
            return json_encode(['status' => 'OK',
                'lnk' => "?r=cwf/fwShell/main/home&core-sessionid=" . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID()]);
        } else {
            return json_encode(['status' => 'ERROR', 'lnk' => '']);
        }
    }

    public function actionMenutree($role_id, $branch_id) {
        $params = array();
        $params['role_id'] = $role_id;
        $mnutree = new \app\cwf\fwShell\models\MenuTree($params);
        $mnutree->getBranchAccess($branch_id);
        return json_encode($mnutree->menu_items);
    }

    public function actionUseraccess($user_id, $branch_id) {
        $params = [];
        $params['user_id'] = $user_id;
        $uaccess = new \app\cwf\fwShell\models\UserAccess($params);
        $uaccess->getBranchAccess($branch_id);
        return json_encode($uaccess->menu_items);
    }

    public function actionDashboard($dbd) {
        if (\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id') == -1 || \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id') == -1) {
            return '';
        }
        $baseModPath = '';
        if ($this->module->module) {
            $baseModPath .= $this->module->module->id;
        }
        $dbdxml = '';
        if ($dbd === 'home') {
            $dbdxml = simplexml_load_string($this->getdbdHome());
        } else if ($dbd != ''){
            $dbdxml = simplexml_load_file('../' . $baseModPath . '/' . $dbd . '.xml');
        }
        if ($dbdxml == '') {
            return '';
        } else {
            $dbparser = new \app\cwf\vsla\dbd\dbdparser($dbdxml, $this->ModulePath);
            $dbrenderer = new \app\cwf\vsla\dbd\dbdrenderer($dbparser);
            if ($dbrenderer->dbdrender == '') {
                return '';
            } else {
                return $this->renderPartial('@app/cwf/vsla/base/DashboardView.php'
                                , ['dbdrenderer' => json_encode($dbrenderer)]);
            }
        }
    }

    public function actionTwigwidget($path, $model) {
        return $this->renderFile($path, ['model' => $model]);
    }

    private function getdbdHome() {
        $cmmtxt = 'select * from sys.user_dashboard where user_id=:puser_id';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($cmmtxt);
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        if (count($dt->Rows()) > 0) {
            return (string) $dt->Rows()[0]['dashboard_xml'];
        } else {
            return '';
        }
    }

    public function actionOpendocform($formParams) {
        $coremod = \yii::$app->getModule('core');
        foreach ($coremod->getModules() as $mod => $modSet) {
            $coremod->getModule($mod, TRUE);
        }
        $params = json_decode($formParams);
        if (isset($params->voucher_id)) {
            $docType = substr($params->voucher_id, 0, strpos($params->voucher_id, "/"));
            $docInfo = \app\cwf\vsla\base\DocManager::getMap($docType);
            return $this->run($docInfo->route, ['formName' => $docInfo->formName, 'formParams' => $formParams]);
        } else {
            return '';
        }
    }

    public function actionKeepalive() {
        return json_encode(['status' => 'OK']);
    }

    public function actionImportbalance() {
        return $this->renderPartial('@app/cwf/sys/importbalance/viewImportBalance.php');
    }

    public function actionGetimportbalance($importaccbal, $importinvbal) {
        $impbal = new \app\cwf\sys\importbalance\modelImportBalance();
        $impbal->importAccBal = $importaccbal;
        $impbal->importInvBal = $importinvbal;
        $impbal->importBalance();
        if ($impbal->errmsg == '') {
            $res = $this->renderFile('@app/cwf/sys/importbalance/checkbalance.twig', ['model' => $impbal]);
            return $res;
        }
        return json_encode($impbal->errmsg);
    }

    public function actionRoleUsersView() {
        return $this->renderPartial('@app/cwf/fwShell/views/RoleUsersView.php', ['title' => 'Send For Approval']);
    }

    public function actionTaskRoles($process_run_id) {
        $pmr = new \app\cwf\vsla\pm\PmArgs();
        $pmr->process_run_id = $process_run_id;
        $pm = new \app\cwf\vsla\pm\ProcessManager($pmr);
        $ut = $pm->getNextTask();
        return $this->actionRoleUsers($ut->roles);
    }

    public function actionRoleUsers($role_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = 'Select false as selected, a.user_id, a.full_user_name, a.email 
                From sys.user a
                Inner Join sys.user_branch_role b On a.user_id=b.user_id
                Where b.role_id = Any (:prole_id::BigInt[])
                    And b.branch_id = :pbranch_id
                    And a.is_active
                group by a.user_id, a.full_user_name, a.email
                Order By a.full_user_name';
        $cmm->setCommandText($sql);
        $cmm->addParam('prole_id', '{' . $role_id . '}');
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('branch_id'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $result = ['user_list' => $dt, 'doc_sender_comment' => ''];
        return json_encode($result);
    }

    public function actionAssignUsersView() {
        return $this->renderPartial('@app/cwf/fwShell/views/RoleUsersView.php', ['title' => 'Assign to User']);
    }

    public function actionAssignUsers($bo_id, $branch_id) {
        // Extract list of users who have edit rights to a document
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = 'Select false as selected, a.user_id, a.full_user_name, a.email 
                From sys.user a 
                Inner join sys.user_branch_role b on a.user_id = b.user_id
                Inner Join sys.role_access_level c On b.role_id = c.role_id
                where c.menu_id = (select menu_id from sys.menu where bo_id = md5(:pbo_id)::uuid)
                    And b.branch_id = :pbranch_id
                    And en_access_level in (2, 3)
                    And a.user_id != :puser_id
                Group by a.user_id, a.full_user_name, a.email
                Order By a.full_user_name';
        $cmm->setCommandText($sql);
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $cmm->addParam('pbo_id', $bo_id);
        $cmm->addParam('pbranch_id', $branch_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $result = ['user_list' => $dt, 'doc_sender_comment' => ''];
        return json_encode($result);
    }

    public function actionRejectToSender($doc_id) {
        $userinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
        $model = \app\cwf\vsla\workflow\DocWorkflow::getRejectTo($doc_id, $userinfo->getUser_ID());
        if (count($model) > 0) {
            return $this->renderPartial('@app/cwf/fwShell/views/RejectDocView.php', ['model' => $model]);
        } else {
            return ('<div>Cannot reject document that does not belong to workflow</div>');
        }
    }

    public function actionGetpendingcnt() {
        if (\app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('company_id') == -1 || \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('branch_id') == -1 || \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('finyear_id') == -1) {
            return json_encode(['status' => 'Error']);
        }
        return json_encode(\app\cwf\fwShell\models\Menu::getPendingCount());
    }

    public function actionGetprintaccess($bo_id, $doc_id, $doc_status) {
        $res_print = \app\cwf\vsla\security\AccessManager::check_print_access($bo_id, $doc_id, $doc_status);
        $req_print = \app\cwf\vsla\security\AccessManager::check_pending_print_request($doc_id);
        $res_export = \app\cwf\vsla\security\AccessManager::check_export_access($bo_id);
        $print_mail = \app\cwf\vsla\security\AccessManager::check_report_mail_access($bo_id);
        if ($req_print == TRUE && $res_print == FALSE) {
            $res_export = FALSE;
            $res_print = TRUE;
            $print_mail = FALSE;
        }
        return json_encode(['print_access' => $res_print, 'export_access' => $res_export, 'report_mail_access' => $print_mail]);
    }

}

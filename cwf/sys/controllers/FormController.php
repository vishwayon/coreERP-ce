<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of FormController
 *
 * @author girish
 */
namespace app\cwf\sys\controllers;

use app\cwf\vsla\base\WebFormController;


class FormController extends WebFormController {
    //put your code here
    public function actionConnectcompany() {
        return $this->renderPartial('@app/cwf/fwShell/views/LogonSelectionView',['model'=>new \app\cwf\fwShell\models\LogonSelection()]);
    }       
    
    public function actionFiscalmonth($formName) {
        // Find if fiscal months for connected finyear are created
        $finyear = \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear');
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select count(*) as fmcount From sys.fiscal_month Where finyear=:pfinyear;');
        $cmm->addParam('pfinyear', $finyear);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if($dt->Rows()[0]['fmcount']==0) {
            return $this->renderPartial('@app/cwf/sys/fiscalMonth/FiscalMonthWizView.twig', ['model' => ['finyear' => $finyear]]);
        } else {
            return $this->runAction('collection', ['formName' => $formName]);
        }
    }
    
    public function actionFiscalMonthCreate() {
        $finyear = \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear');
        $fm = new \app\cwf\sys\fiscalMonth\FiscalMonthCreator($finyear);
        $result = $fm->create();
        return json_encode($result);
    }
    
    public function actionFetchBranchAddr($branch_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "Select a.gst_state_id, 
                    a.gstin, Coalesce(b.state_name, '') gst_state,
                    a.branch_address as addr
                From sys.branch a
                Left Join tx.gst_state b On a.gst_state_id = b.gst_state_id
                Where a.branch_id = :pbranch_id 
                Limit 1";
        $cmm->setCommandText($sql);
        $cmm->addParam('pbranch_id', $branch_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows())==1) {
            return json_encode($dt->Rows()[0]);
        }
        return json_encode([]);
    }    
    
    public function actionFetchBranchJwAddr($branch_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "Select a.gst_state_id, 
                    a.gstin, coalesce(b.state_name, '') gst_state,
                    a.address as addr
                From sys.address a
                Left Join tx.gst_state b On a.gst_state_id = b.gst_state_id
                Where a.address_id = :pbranch_id 
                Limit 1";
        $cmm->setCommandText($sql);
        $cmm->addParam('pbranch_id', ($branch_id * -1));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows())==1) {
            return json_encode($dt->Rows()[0]);
        }
        return json_encode([]);
    }  

    public function actionUserAccessRights($rptOptions = "") {
        $viewOption = new \app\cwf\vsla\render\FormViewOptions();
        $viewOption->callingModulePath = '';
        $viewOption->xmlViewPath = '@app/cwf/sys/userAccessRights/UserAccessRightsView.xml';
        $design = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($viewOption->callingModulePath, $viewOption->xmlViewPath);
        $viewForRender = \app\cwf\vsla\render\ViewManager::getCompiledFormView($viewOption, $design);
        return $this->renderPartial('@app/cwf/sys/userAccessRights/UserAccessRightsView.php', ['viewForRender' => $viewForRender, 'xmlPath' => $viewOption->xmlViewPath, 'rptOptions' => $rptOptions]);
    }

    public function actionGetUserAccessRightsData() {
        $rptParams = \yii::$app->request->getBodyParams();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmText= "select a.user_id, user_name, full_user_name, email, mobile, is_active, 
                    is_admin, is_owner, b.branch_id, c.branch_name, b.role_id,d.role_name,
                    e.menu_id, f.menu_text, f.menu_type,
                    case f.menu_type
                         when 2 then 'Master'
                         when 1 then 'Document'
                         when 3 then 'Report'
                         when 4 then 'Utilities'
                         else ''
                    end as menu_type_text, e.en_access_level,
                    case e.en_access_level 
                         when 1 then 'ReadOnly'
                         when 2 then 'Dataentry'
                         when 3 then 'Authorise'
                         when 4 then 'Consolidated'
                         else 'NoAccess'
                    end as access_level, e.doc_stages
                from sys.user a
                inner join sys.user_branch_role b on a.user_id=b.user_id
                inner join sys.branch c on b.branch_id=c.branch_id
                inner join sys.role d on b.role_id=d.role_id
                inner join sys.role_access_level e on b.role_id=e.role_id
                inner join sys.menu f on e.menu_id=f.menu_id
                where  (b.branch_id= :pbranch_id or :pbranch_id=0) 
                and  (a.user_id= :puser_id or :puser_id=-99) 
                and  (d.role_id= :prole_id or :prole_id=0) 
                and  (f.menu_type= :pmenu_type or :pmenu_type=-2) 
                and  (f.menu_id= :pmenu_id or :pmenu_id=0) 
                and  a.is_active=true 
                ORDER BY branch_id, user_name, d.role_name, f.menu_type, f.menu_text";

        $cmm->setCommandText($cmmText);
        $cmm->addParam('pbranch_id', $rptParams['pbranch_id']);
        $cmm->addParam('puser_id', $rptParams['puser_id']);
        $cmm->addParam('prole_id', $rptParams['prole_id']);
        $cmm->addParam('pmenu_type', $rptParams['pmenu_type']);
        $cmm->addParam('pmenu_id', $rptParams['pmenu_id']);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $columns = [
             ['data' => 'branch_name', 'type' => 'string', 'title' => 'Branch'],
             ['data' => 'user_name', 'type' => 'string', 'title' => 'User'],
             ['data' => 'role_name', 'type' => 'string', 'title' => 'Role'],
             ['data' => 'menu_type_text', 'type' => 'string', 'title' => 'Menu Type'],
             ['data' => 'menu_text', 'type' => 'string', 'title' => 'Menu Text'],
             ['data' => 'access_level', 'type' => 'string', 'title' => 'Access Level'],
             ['data' => 'doc_stages', 'type' => 'string', 'title' => 'Doc Stage']
            ];

        $result = [
         'columns' => $columns,
         'data' => $dt->Rows()
        ];
        
        return json_encode($result);
    }
   
}

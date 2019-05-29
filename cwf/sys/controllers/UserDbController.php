<?php

namespace app\cwf\sys\controllers;

/**
 * Description of UserDbController
 *
 * @author dev
 */
class UserDbController extends \app\cwf\vsla\base\WebController {

    function init() {
        parent::init();
        $twigOptions = &\yii::$app->view->renderers['twig'];
        // Register yii classes that you plan to use in twig
        $twigOptions['globals'] = [
            'ScriptHelper' => '\app\cwf\vsla\utils\ScriptHelper'
        ];
    }
    
    public function actionViewDocs(){
        $userdb = $this->getModulePath().'/userDashboard/UserDb.twig';
        return $this->renderPartial($userdb);
    }

    public function actionGetAprs() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = "SELECT wf_ar_id, bo_id, doc_id, doc_date, wf_desc, user_from, user_to,
                        route, formname, formparams, added_on
                    FROM sys.wf_ar
                    where user_to = :puser_id and acted_on is null ";
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $cmm->setCommandText($cmmtext);
        $dt_apr = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $cmm2 = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext2 = "Select user_id, full_user_name, email from sys.user";
        $cmm2->setCommandText($cmmtext2);
        $dt_user = \app\cwf\vsla\data\DataConnect::getData($cmm2, \app\cwf\vsla\data\DataConnect::MAIN_DB);

        $dt_apr->addColumn('doc_date_sort', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        $dt_apr->addColumn('req_date_sort', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        $dt_apr->addColumn('bo_name', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_STRING, 0);
        foreach ($dt_apr->Rows() as &$row_apr) {
            $row_apr['doc_date_sort'] = strtotime($row_apr['doc_date']);
            $row_apr['req_date_sort'] = strtotime($row_apr['added_on']);
            foreach ($dt_user->Rows() as $row_user) {
                if ($row_apr['user_from'] == $row_user['user_id']) {
                    $row_apr['from_user'] = $row_user['full_user_name'];
                }
            }
        }
        $ar['apr_data']=$dt_apr;
        return json_encode($ar);
    }

    public function actionGetDocs() {
        $cmm1 = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext1 = "select a.doc_id, a.doc_sender_comment, a.user_id_from, a.doc_sent_on::date, a.doc_action, 
                a.user_id_to, a.doc_stage_id, a.doc_stage_id_from, a.branch_id, b.branch_name, a.bo_id,
                '' as from_user from sys.doc_wf a
                inner join sys.branch b on a.branch_id = b.branch_id 
                where a.user_id_to <> -1 and a.user_id_to = :puser_id ";
        $cmm1->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $cmm1->setCommandText($cmmtext1);
        $dt_pdocs = \app\cwf\vsla\data\DataConnect::getData($cmm1);
        $cmm2 = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext2 = "Select user_id, full_user_name, email from sys.user";
        $cmm2->setCommandText($cmmtext2);
        $dt_user = \app\cwf\vsla\data\DataConnect::getData($cmm2, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        $dt_pdocs->addColumn('doc_date_sort', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        foreach ($dt_pdocs->Rows() as &$row_doc) {
            $row_doc['doc_date_sort'] = strtotime($row_doc['doc_sent_on']);
            foreach ($dt_user->Rows() as $row_user) {
                if ($row_doc['user_id_from'] == $row_user['user_id']) {
                    $row_doc['from_user'] = $row_user['full_user_name'];
                }
            }
        }
        $dc['doc_data']=$dt_pdocs;
        return json_encode($dc);
    }

}

<?php

namespace app\cwf\sys\userDashboard;

/**
 * Description of UserDashboardEventHandler
 *
 * @author dev
 */
class UserDashboardEventHandler extends \app\cwf\vsla\dbd\WidgetEventHandlerBase {

    public function afterFetch($series_id, &$collection) {
        if ($series_id == 'pAprs') {
            $cmm2 = new \app\cwf\vsla\data\SqlCommand();
            $cmmtext2 = "Select user_id, full_user_name, email from sys.user";
            $cmm2->setCommandText($cmmtext2);
            $dt_user = \app\cwf\vsla\data\DataConnect::getData($cmm2, \app\cwf\vsla\data\DataConnect::MAIN_DB);
            $collection->addColumn('doc_date_sort', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
            foreach ($collection->Rows() as &$row_doc) {
                foreach ($dt_user->Rows() as $row_user) {
                    if ($row_doc['user_from'] == $row_user['user_id']) {
                        $row_doc['from_user'] = $row_user['full_user_name'];
                    }
                }
            }
        }
        if ($series_id == 'pDocs') {
            $cmm2 = new \app\cwf\vsla\data\SqlCommand();
            $cmmtext2 = "Select user_id, full_user_name, email from sys.user";
            $cmm2->setCommandText($cmmtext2);
            $dt_user = \app\cwf\vsla\data\DataConnect::getData($cmm2, \app\cwf\vsla\data\DataConnect::MAIN_DB);
            $collection->addColumn('doc_date_sort', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
            foreach ($collection->Rows() as &$row_doc) {
                $row_doc['doc_date_sort'] = strtotime($row_doc['doc_sent_on']);
                $row_doc['doc_action']= $this->actionDesc($row_doc['doc_action']);
                foreach ($dt_user->Rows() as $row_user) {
                    if ($row_doc['user_id_from'] == $row_user['user_id']) {
                        $row_doc['from_user'] = $row_user['full_user_name'];
                    }
                }
            }
        }
    }
    
    private function actionDesc($act){
        switch ($act) {
            case 'S':
                return 'Sent';
            case 'A':
                return 'Approved';
            case 'R':
                return 'Rejected';
            case 'P':
                return 'Posted';
            case 'U':
                return 'Unposted';
            case 'I':
                return 'Assigned';
            default:
                return '';
        }
    }

}
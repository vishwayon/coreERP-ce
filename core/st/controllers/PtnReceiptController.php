<?php

namespace app\core\st\controllers;

class PtnReceiptController extends \app\cwf\vsla\base\WebController {

    public function actionIndex($viewName = null, $viewParams = null) {
        $model = new \app\core\st\ptnReceipt\ModelPtnReceipt();
        return $this->renderPartial('@app/core/st/ptnReceipt/ViewPtnReceipt', ['model' => $model]);
    }

    public function actionGetdata($params) {
        $model = new \app\core\st\ptnReceipt\ModelPtnReceipt();
        $filter_array = array();
        parse_str($params, $filter_array);
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }

    public function actionSetdata() {
        $model = new \app\core\st\ptnReceipt\ModelPtnReceipt();
        $postData = json_decode(\Yii::$app->request->getRawBody());
        $model->setData($postData);
        $filter_array = array();
        $filter_array['status'] = $postData->status;
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata'] = $model;
        $result['brule'] = array();
        $result['status'] = '';
        if (count($model->brokenrules) == 0) {
            $result['status'] = 'OK';
        } else {
            $result['brule'] = $model->brokenrules;
        }
        return json_encode($result);
    }

    public function actionConfirmReceipt() {
        $stock_id = \yii::$app->request->post('stock_id');
        $received_on = \yii::$app->request->post('received_on');
        $reference = \yii::$app->request->post('reference');
        $dt_st_temp = json_decode(\yii::$app->request->post('st_temp'), true);

        $cn = \app\cwf\vsla\data\DataConnect::getCn(\app\cwf\vsla\data\DataConnect::COMPANY_DB);
        try {
            $cn->beginTransaction();

            $this->addUpdateTranExtn($cn, $dt_st_temp, $stock_id);
            // Post Stock receipt in park post
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('update st.stock_transfer_park_post
                                        set status = 5, 
                                            doc_date = :pdoc_date, 
                                            finyear = :pfinyear, 
                                            reference = :preference, 
                                            authorised_by = :pauthorised_by
                                         Where stock_id = :pstock_id;');
            $cmm->addParam('pstock_id', $stock_id);
            $cmm->addParam('pdoc_date', $received_on);
            $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
            $cmm->addParam('preference', $reference);
            $cmm->addParam('pauthorised_by', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getFullUserName());
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);

            $cn->commit();
            $cn = null;
            $result['status'] = 'OK';
        } catch (\Exception $ex) {
            if ($cn->inTransaction()) {
                $cn->rollBack();
                $cn = null;
            }
            throw new \Exception('Error posting/unposting ' . $stock_id . ' : ' . $ex);
        }

        return json_encode($result);
    }
    
    private function addUpdateTranExtn($cn, $dt_st_temp, $stock_id) {
        // Insert/Update receipt qty and stock location in stock_tran_extn table                
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from st.st_extn_add_update (:pstock_id, :pstock_tran_id, :preceipt_qty, :pshort_qty, :preceipt_sl_id);');
        $cmm->addParam('pstock_id', $stock_id);
        $cmm->addParam('pstock_tran_id', '');
        $cmm->addParam('preceipt_qty', 0);
        $cmm->addParam('pshort_qty', 0);
        $cmm->addParam('preceipt_sl_id', -1);
        foreach ($dt_st_temp as $dr) {
            $cmm->setParamValue('pstock_tran_id', $dr['stock_tran_id']);
            $cmm->setParamValue('preceipt_qty', $dr['receipt_qty']);
            $cmm->setParamValue('pshort_qty', $dr['short_qty']);
            $cmm->setParamValue('preceipt_sl_id', $dr['receipt_sl_id']);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
        }
    }
}

<?php

namespace app\core\st\controllers;

class StockTransferParkPostController extends \app\cwf\vsla\base\WebController {

    public function actionIndex($viewName = null, $viewParams = null) {
        $model = new \app\core\st\stockTransferParkPost\ModelStockTransferParkPost();
        return $this->renderPartial('@app/core/st/stockTransferParkPost/ViewStockTransferParkPost', ['model' => $model]);
    }

    public function actionGetdata($params) {
        $model = new \app\core\st\stockTransferParkPost\ModelStockTransferParkPost();
        $filter_array = array();
        parse_str($params, $filter_array);
        $model->setFilters($filter_array);
        $result = array();
        $result['jsondata'] = $model;
        return json_encode($result);
    }

    public function actionSetdata() {
        $model = new \app\core\st\stockTransferParkPost\ModelStockTransferParkPost();
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

    public function actionUpdateStockReceiptForQc() {
        $dt_st_temp = json_decode(\yii::$app->request->post('st_temp'), true);
        $stock_id = \yii::$app->request->post('stock_id');
        $received_on = \yii::$app->request->post('received_on');
        $result = array();
        $msg = '';
        $row_no = 0;
        foreach ($dt_st_temp as $dr) {
            $row_no += 1;
            if ($dr['receipt_qty'] == 0) {
                if ($msg == '') {
                    $msg = 'Row # ' . $row_no . ': Received Qty is required.';
                } else {
                    $msg .= '<br/>Row # ' . $row_no . ': Received Qty is required.';
                }
            }
        }
        // Validate QC status before request to qc
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select receipt_qty, stock_tran_id, short_qty, receipt_sl_id From st.stock_tran_extn Where stock_id = :pstock_id;');
        $cmm->addParam('pstock_id', $stock_id);
        $dtex = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtex->Rows()) > 0) {
            $msg .= 'Stock Transfer is already sent to QC';
        }

        if ($msg == '') {
            $cn = \app\cwf\vsla\data\DataConnect::getCn(\app\cwf\vsla\data\DataConnect::COMPANY_DB);
            try {
                $cn->beginTransaction();

                $this->addUpdateTranExtn($cn, $dt_st_temp, $stock_id);

                // Update received on in Stock Transfer Park Post
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('update st.stock_transfer_park_post
                                        set doc_date = :pdoc_date
                                         Where stock_id = :pstock_id;');
                $cmm->addParam('pdoc_date', $received_on);
                $cmm->addParam('pstock_id', $stock_id);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);

                // Insert rows for qc
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText("With qc_tran
                                    As
                                    (	Select x.stock_tran_id, x.receipt_qty, x.short_qty, x.receipt_sl_id, x.material_id
                                            From jsonb_to_recordset(:pcurrent_alloc::JsonB) as x (stock_tran_id varchar(50), receipt_qty numeric(18, 4) , short_qty numeric(18, 4),
                                                    receipt_sl_id bigint, material_id bigint)
                                    )
                                    Insert Into prod.qc_pending_doc(qc_pending_doc_id, tp_type_id, voucher_id, vch_tran_id, doc_date, branch_id, finyear, material_id, received_qty, uom_id)
                                    Select md5(b.stock_tran_id || 101::varchar)::uuid, 101, a.stock_id, b.stock_tran_id, a.doc_date, a.target_branch_id, a.finyear, b.material_id, b.receipt_qty, -1
                                    From st.stock_control a
                                    Inner Join qc_tran b On a.stock_id = :pstock_id
                                    Inner Join st.material c On b.material_id = c.material_id
                                    Where (c.annex_info->'qc_info'->>'has_qc')::Boolean
                                            And a.stock_id = :pstock_id;");
                $cmm->addParam('pstock_id', $stock_id);
                $cmm->addParam('pcurrent_alloc', json_encode($dt_st_temp));
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);

                $cn->commit();
                $cn = null;
                $result['status'] = 'OK';
            } catch (\Exception $ex) {
                if ($cn->inTransaction()) {
                    $cn->rollBack();
                    $cn = null;
                }
                throw new \Exception('Error request QC ' . $stock_id . ' : ' . $ex);
            }
        } else {
            $result['status'] = $msg;
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

    public function actionStParkPost() {
        $dt_st_temp = json_decode(\yii::$app->request->post('st_temp'), true);
        $stock_id = \yii::$app->request->post('stock_id');
        $received_on = \yii::$app->request->post('received_on');
        $reference = \yii::$app->request->post('reference');
        $st_str_qc_reqd = \yii::$app->request->post('st_str_qc_reqd') == 'true';
        $result = array();
        $qc_pending = false;
        // Validate excess qty allowed for material
        $msg = $this->validateExcessQty($stock_id, $dt_st_temp);
        if ($msg != '') {
            $result['status'] = $msg;
            return json_encode($result);
        }
        // Validate qc
        if ($st_str_qc_reqd) {
            $qc_pending = $this->validateQcTestCompleted($stock_id, $dt_st_temp);
        }
        if ($qc_pending) {
            $result['status'] = 'Cannot receive stock without completion of QC Test/Inspection';
            return json_encode($result);
        }
        if (!$qc_pending && $msg == '') {
            $cn = \app\cwf\vsla\data\DataConnect::getCn(\app\cwf\vsla\data\DataConnect::COMPANY_DB);
            try {
                $cn->beginTransaction();

                // Update received qty and target sl
                $this->addUpdateTranExtn($cn, $dt_st_temp, $stock_id);

                // make entry in st.stock_qc_tran
                $cmm_qc = new \app\cwf\vsla\data\SqlCommand();
                $cmm_qc->setCommandText("With qc_info
                                        As
                                        ( 
                                            select b.test_insp_id, a.test_insp_attr_id, c.test_insp_attr, a.test_desc, case when a.test_type_id = 1 then a.range_result::varchar else case when passed then pass_val else fail_val end end as result
                                            From prod.test_insp_tran a
                                            Inner join prod.test_insp_control b on a.test_insp_id = b.test_insp_id
                                            Inner join prod.test_insp_attr c on a.test_insp_attr_id = c.test_insp_attr_id
                                            Where b.annex_info->'doc_ref_info'->>'doc_ref_id' = :pdoc_id
                                                    And a.conducted = true
                                                    And a.test_insp_attr_id != 100
                                        ),
                                        test_info
                                        As
                                        (
                                            select d.test_insp_id, ('{\"data\": ' || json_agg(row_to_json(d)) || '}')::jsonb as ref_info
                                            From qc_info d
                                            group by d.test_insp_id
                                        ),
                                        test_result
                                        As (
                                            select test_insp_id, string_agg(info, '; ') ref_desc
                                            From (
                                                    Select a.test_insp_id, (test_insp_attr || ' : ' || result) info
                                                    From test_info a, jsonb_to_recordset(a.ref_info->'data') as x (test_insp_attr_id varchar(50), test_insp_attr varchar(50), test_desc varchar(50), result varchar(50))    
                                                ) a
                                                Group by test_insp_id
                                        )
                                        Insert into st.stock_tran_qc (stock_tran_qc_id, stock_id, stock_tran_id, 
                                            test_insp_id, test_insp_date, material_id, test_result_id, 
                                            accept_qty, reject_qty, lot_no, mfg_date, exp_date, best_before, 
                                            ref_info)
                                        Select (:pdoc_id|| ':' || ROW_NUMBER() over(order by a.annex_info->'doc_ref_info'->>'doc_ref_tran_id')), :pdoc_id, a.annex_info->'doc_ref_info'->>'doc_ref_tran_id',                                            
                                                a.test_insp_id, a.doc_date as test_insp_date, a.material_id, a.test_result_id, 
                                            Case When a.test_result_id In (1,2) Then (a.annex_info->>'tested_qty')::Numeric(18,3) Else 0 End accept_qty,
                                            Case When a.test_result_id Not In (1,2) Then (a.annex_info->>'tested_qty')::Numeric(18,3) Else 0 End reject_qty,
                                            coalesce(a.annex_info->'batch_info'->>'batch_no', '') as lot_no, 
                                            coalesce((a.annex_info->'batch_info'->>'mfg_date')::Date, doc_date) as mfg_date,
                                            coalesce((a.annex_info->'batch_info'->>'exp_date')::Date, '2099-12-31') as exp_date, 
                                            coalesce((a.annex_info->'batch_info'->>'best_before')::Date, '2099-12-31') as best_before,
                                            jsonb_set(('{\"data\": ' || json_agg(COALESCE(row_to_json(d), '{}')) || '}' )::jsonb, '{desc}', ('\"' || COALESCE(e.ref_desc, '') || '\"')::jsonb, true) ref_info
                                        from prod.test_insp_control a
                                        Inner Join prod.test_plan b ON a.test_plan_id = b.test_plan_id
                                        left join qc_info d on a.test_insp_id = d.test_insp_id
                                        left join test_result e on d.test_insp_id = e.test_insp_id
                                        Where a.annex_info->'doc_ref_info'->>'doc_ref_id' = :pdoc_id
                                            And a.status = 5
                                            And (b.annex_info->>'tp_type_id')::BigInt = 101
                                        Group By a.test_insp_id, a.doc_date, a.material_id, a.test_result_id, a.annex_info->'doc_ref_info'->>'doc_ref_tran_id',
                                            (a.annex_info->>'tested_qty')::Numeric(18,3),
                                            a.annex_info->'batch_info'->>'batch_no', 
                                            (a.annex_info->'batch_info'->>'mfg_date')::Date,
                                            (a.annex_info->'batch_info'->>'exp_date')::Date, 
                                            (a.annex_info->'batch_info'->>'best_before')::Date,
                                            e.ref_desc
                                        Order By sys.fn_sort_vch_tran(a.annex_info->'doc_ref_info'->>'doc_ref_tran_id')");
                $cmm_qc->addParam("pdoc_id", $stock_id);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm_qc, $cn);

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


                // Delete entries from qc_pending_doc and alloc                
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('Select * from prod.sp_qc_pending_doc_delete(:pstock_id);');
                $cmm->addParam('pstock_id', $stock_id);
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
        }
        return json_encode($result);
    }

    private function validateQcTestCompleted($stock_id, $dt_st_temp) {
        $qc_pending = false;

        // Validate Completion of Qc Tests                
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select a.material_id 
                From st.material a 
                inner join st.stock_tran b on a.material_id = b.material_id
                Where (annex_info->'qc_info'->>'has_qc')::Boolean
                        And b.stock_id = :pstock_id");
        $cmm->addParam("pstock_id", $stock_id);
        $dtqc = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtqc->Rows()) > 0) {
            $cmmqc = new \app\cwf\vsla\data\SqlCommand();
            $cmmqc->setCommandText("With st_tran
                                    As
                                    (	Select x.stock_tran_id, x.material_id, x.receipt_qty
                                        From jsonb_to_recordset(:pcurrent_alloc::JsonB) as x(stock_tran_id varchar(50), material_id bigint, receipt_qty Numeric(18,4))
                                    ), 
                                    qc_info
                                    As
                                    (	Select stock_tran_id, receipt_qty
                                        From st_tran a
                                        Inner Join st.material b On a.material_id = b.material_id
                                        Where (b.annex_info->'qc_info'->>'has_qc')::Boolean
                                        Union All
                                        Select annex_info->'doc_ref_info'->>'doc_ref_tran_id' doc_ref_tran_id,
                                            (annex_info->>'tested_qty')::Numeric(18,3) * -1
                                        from prod.test_insp_control
                                        Where annex_info->'doc_ref_info'->>'doc_ref_id' = :pstock_id
                                            And status = 5
                                    )
                                    Select stock_tran_id, Sum(receipt_qty) as qc_bal_qty
                                    From qc_info
                                    Group by stock_tran_id
                                    Having Sum(receipt_qty) > 0;");
            $current_alloc = [];
            foreach ($dt_st_temp as $dr) {
                $row = [];
                $row['stock_tran_id'] = $dr['stock_tran_id'];
                $row['material_id'] = $dr['material_id'];
                $row['receipt_qty'] = $dr['receipt_qty'];
                $current_alloc[] = $dr;
            }
            $cmmqc->addParam('pcurrent_alloc', json_encode($current_alloc));
            $cmmqc->addParam("pstock_id", $stock_id);
            $dt_test = \app\cwf\vsla\data\DataConnect::getData($cmmqc);
            if (count($dt_test->Rows()) > 0) {
                $qc_pending = true;
            }
        }
        return $qc_pending;
    }

    private function validateExcessQty($stock_id, $dt_st_temp) {
        $msg = '';
        foreach ($dt_st_temp as $dr) {
            if ($dr['short_qty'] < 0) {
                // Check if material allows excess qty
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText("select material_id, material_name, coalesce((annex_info->>'st_allow_excess')::Boolean, false) st_allow_excess, 
                                            coalesce((annex_info->>'st_excess_pcnt')::numeric, 0) st_excess_pcnt
                                        from st.material
                                        where material_id=:pmaterial_id");
                $cmm->addParam('pmaterial_id', $dr['material_id']);
                $dtae = \app\cwf\vsla\data\DataConnect::getData($cmm);

                if (count($dtae->Rows()) == 1 && !$dtae->Rows()[0]['st_allow_excess']) {
                    if ($msg == '') {
                        $msg .= 'Excess receipts not allowed for following stock items(s)';
                    }
                    $msg .= '</br>' . $dtae->Rows()[0]['material_name'];
                }

                // Set excess pent
                $excess_pcnt = 0;
                if (count($dtae->Rows()) == 1) {
                    $excess_pcnt = $dtae->Rows()[0]['st_excess_pcnt'];
                }

                // Check if excess is more than 10% of issued.
                // This is to prevent typo errors from the user
                if ((abs($dr['short_qty']) / $dr['issued_qty'] * 100) > $excess_pcnt) {
                    if ($msg == '') {
                        $msg .= 'Excess receipts not allowed for following stock items(s)';
                    }
                    $msg .= '</br>' . $dtae->Rows()[0]['material_name'] . " in excess of " . $dtae->Rows()[0]['st_excess_pcnt'] . "% not allowed";
                }
            }
        }
        return $msg;
    }

}

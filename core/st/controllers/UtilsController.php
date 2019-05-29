<?php

/**
 * UtilsController is used to manage Stock Utils
 * qc_status, etc.
 *
 * @author girishshenoy
 */

namespace app\core\st\controllers;

use yii\web\Controller;

class UtilsController extends Controller {

    public function actionGrnQcStatus() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select table_name from Information_schema.tables Where table_schema = 'prod' And table_name = 'test_insp_control'");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) == 0) {
            $result = array();
            $result['qc_info'] = [];
            $result['status'] = 'OK';
            return json_encode($result);
        }
        
        $sids = \yii::$app->request->post('sids');
        $dtmat = null;
        if($sids != null){
            $cmmMat = new \app\cwf\vsla\data\SqlCommand();
            $cmmMat->setCommandText("With test_insp
                As
                (   Select a.test_insp_id, a.annex_info->'doc_ref_info'->>'doc_ref_tran_id' doc_ref_tran_id
                    from prod.test_insp_control a
                    Inner Join prod.test_plan b On a.test_plan_id = b.test_plan_id
                    Where a.annex_info->'doc_ref_info'->>'doc_ref_id' = Any(:psids)
                        And a.status = 5
                        And (b.annex_info->>'tp_type_id')::BigInt = 101
                ),
                doc_tran
                As
                (   Select a.annex_info->'doc_ref_info'->>'doc_ref_id' doc_ref_id, 
                        a.annex_info->'doc_ref_info'->>'doc_ref_tran_id' doc_ref_tran_id,
                        (a.annex_info->>'tested_qty')::Numeric(18,3) as tested_qty
                    From prod.test_insp_control a
                    Inner Join test_insp b On a.test_insp_id = b.test_insp_id
                    Union All
                    Select a.stock_id doc_ref_id, stock_tran_id doc_ref_tran_id, -received_qty
                    From st.stock_tran a
                    Inner Join st.material b On a.material_id = b.material_id
                    Inner join st.stock_control c on a.stock_id = c.stock_id
                    Where (b.annex_info->'qc_info'->>'has_qc')::Boolean
                        And a.stock_id = Any(:psids)
                        And c.status != 5
                )
                Select a.doc_ref_id as stock_id, Case When Sum(a.tested_qty) >= 0 Then 'Clear' Else 'Pending' End as qc_status
                From doc_tran a
                Group by a.doc_ref_id");
            $qsids = "{" . implode(",", $sids) . "}";
            $cmmMat->addParam("psids", $qsids);
            $dtmat = \app\cwf\vsla\data\DataConnect::getData($cmmMat);
        }
        $result = array();
        $result['qc_info'] = $dtmat;
        $result['status'] = 'OK';
        return json_encode($result);
    }

    public function actionViewSpgBarcode() {
        return $this->renderPartial("@app/core/st/stockGstPurchase/StockGstPurchasePrintView.php");
    }

    public function actionGetSpgBcData($spg_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("
                  with spg_mat
                As
                (
                    select sl_no,
                        stock_id, stock_tran_id,
                        material_type_id,
                        material_id,
                        uom_id,
                    	received_qty
                    from st.stock_tran
                        where stock_id = :pstock_id
                ),
                spg_mat_bc
                As
                (
                     select 
                        spg->>'stock_id' as stock_id, 
                        spg->>'stock_tran_id' as stock_tran_id,
                        (spg->>'material_id')::bigint as material_id, 
                        (spg->>'labelcount')::bigint as labelcount
                    from st.stock_barcode_print , jsonb_array_elements(barcode_info) spg
                    where stock_barcode_print_id =(
                                    select stock_barcode_print_id 
                                    from st.stock_barcode_print 
                                    where stock_id = :pstock_id
                                    order by last_updated desc limit 1
                                    )
                )
                select a.sl_no,
                    a.stock_id, a.stock_tran_id,
                    c.material_code, 
                    a.material_type_id, b.material_type,
                    a.material_id, c.material_name,
                    a.uom_id, d.uom_desc, a.received_qty,
                    COALESCE(e.labelcount,a.received_qty) labelcount
                from spg_mat a
                    inner join st.material_type b on a.material_type_id = b.material_type_id
                    inner join st.material c on a.material_id = c.material_id
                    inner join st.uom d on a.uom_id = d.uom_id
                    left join spg_mat_bc e on a.material_id = e.material_id
                order by a.sl_no asc
                ");
        $cmm->addParam("pstock_id", $spg_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return json_encode($dt);
    }

    public function actionSetSpgBcData() {
        $spg_id = \yii::$app->request->post('spg_id');
        $spg_data = \yii::$app->request->post('spg_data');
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Insert into st.stock_barcode_print
                                (stock_barcode_print_id, stock_id, barcode_info)
                                values ((select coalesce(max(stock_barcode_print_id),0)+1 from st.stock_barcode_print),
                                :pstock_id, :pbarcode_info)
                                Returning stock_barcode_print_id");
        $cmm->addParam("pstock_id", $spg_id);
        $cmm->addParam("pbarcode_info", json_encode($spg_data));
        $dtResult = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $result = [];
        $result['stock_barcode_print_id'] = $dtResult->Rows()[0]['stock_barcode_print_id'];
        $result['status'] = 'OK';
        return json_encode($result);
    }

    public function actionPrintSpgBarcode() {
        
    }
    public function actionStockItemForSt($material_type_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select a.material_id, a.material_name, b.uom_id, b.uom_desc
                            from st.material a 
                            Inner Join st.uom b On a.material_id = b.material_id And b.is_base = True
                            where a.material_type_id = :pmt_id
                            order by a.material_name
                            ");        
        $cmm->addParam("pmt_id", $material_type_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $result = array();
        $result['mat_dt'] = $dt;
        $result['status'] = 'ok';
        return json_encode($result);
    }   

}

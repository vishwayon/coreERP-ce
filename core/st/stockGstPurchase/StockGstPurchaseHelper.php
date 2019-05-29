<?php

namespace app\core\st\stockGstPurchase;

/**
 * Helper calss for StockGstPurchase
 *
 * @author girishshenoy
 */
class StockGstPurchaseHelper {
    
    private static $qc = null;
    public static function hasQCModule() {
        if(self::$qc == null) {
            $cmmProd = new \app\cwf\vsla\data\SqlCommand();
            $cmmProd->setCommandText("Select * from information_schema.tables
                                      Where table_schema = 'prod'
                                            And table_name = 'test_insp_control'");
            $dtprod = \app\cwf\vsla\data\DataConnect::getData($cmmProd);
            if (count($dtprod->Rows()) > 0) {
                self::$qc = true;
            } else {
                self::$qc = false;
            }
        }
        return self::$qc;
    }
    
    public static function loadQcTestResult(\app\cwf\vsla\xmlbo\BoBase $bo, $doc_id_field = "stock_id") {
        // Fetch Inspection Results
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("With qc_info
                            As
                            ( 
                                select b.test_insp_id, a.test_insp_attr_id, c.test_insp_attr, a.test_desc, case when a.test_type_id = 1 then a.range_result::varchar else case when passed then pass_val else fail_val end end as result
                                From prod.test_insp_tran a
                                Inner join prod.test_insp_control b on a.test_insp_id = b.test_insp_id
                                Inner join prod.test_insp_attr c on a.test_insp_attr_id = c.test_insp_attr_id
                                Where (b.annex_info->'doc_ref_info'->>'doc_ref_id') = :pdoc_id
                                        And a.conducted = true
                                        And a.test_insp_attr_id != 100
                            ),                                
                            test_result
                            As
                            (
                                select d.test_insp_id, ('{\"data\": ' || json_agg(COALESCE(row_to_json(d), '{}')) || '}' )::jsonb ref_info, 
                                    ('{' || string_agg(('\"tia_'|| d.test_insp_attr_id ||'\": ' || d.result), ',') || '}') as tia_info, 
                                    ('\"' || string_agg((d.test_insp_attr || ' : ' || d.result), '; ') || '\"') desc_info
                                From qc_info d
                                group by d.test_insp_id
                            )
                            Select COALESCE(jsonb_set(jsonb_set(d.ref_info, '{desc}', d.desc_info::jsonb, true), '{tia_info}', d.tia_info::jsonb, true), '{}') ref_info,
                                    a.test_insp_id, a.doc_date as test_insp_date, a.material_id, a.test_result_id, 
                                a.annex_info->'doc_ref_info'->>'doc_ref_tran_id' doc_ref_tran_id,
                                Case When a.test_result_id In (1,2) Then (a.annex_info->>'tested_qty')::Numeric(18,3) Else 0 End accept_qty,
                                Case When a.test_result_id Not In (1,2) Then (a.annex_info->>'tested_qty')::Numeric(18,3) Else 0 End reject_qty,
                                coalesce(a.annex_info->'batch_info'->>'batch_no', '') as lot_no, 
                                coalesce((a.annex_info->'batch_info'->>'mfg_date')::Date, doc_date) as mfg_date,
                                coalesce((a.annex_info->'batch_info'->>'exp_date')::Date, '2099-12-31') as exp_date, 
                                coalesce((a.annex_info->'batch_info'->>'best_before')::Date, '2099-12-31') as best_before
                            from prod.test_insp_control a
                            Inner Join prod.test_plan b ON a.test_plan_id = b.test_plan_id
                            left join test_result d on a.test_insp_id = d.test_insp_id
                            Where a.annex_info->'doc_ref_info'->>'doc_ref_id' = :pdoc_id
                                And a.status = 5
                                And (b.annex_info->>'tp_type_id')::BigInt = 101
                            Order By sys.fn_sort_vch_tran(a.annex_info->'doc_ref_info'->>'doc_ref_tran_id')");
        $cmm->addParam("pdoc_id", $bo->$doc_id_field);
        $qc_result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $bo->stock_tran_qc->removeAll(); // Remove all existing rows
        foreach($qc_result->Rows() as $drqc) {
            // Fill rows from inspection
            $new_dr = $bo->stock_tran_qc->NewRow();
            $new_dr['stock_tran_qc_id'] = -1;
            $new_dr['stock_id'] = $bo->$doc_id_field;
            $new_dr['stock_tran_id'] = $drqc['doc_ref_tran_id'];
            $new_dr['test_insp_id'] = $drqc['test_insp_id'];
            $new_dr['test_insp_date'] = $drqc['test_insp_date'];
            $new_dr['material_id'] = $drqc['material_id'];
            $new_dr['test_result_id'] = $drqc['test_result_id'];
            $new_dr['accept_qty'] = $drqc['accept_qty'];
            $new_dr['reject_qty'] = $drqc['reject_qty'];
            $new_dr['lot_no'] = $drqc['lot_no'];
            $new_dr['mfg_date'] = $drqc['mfg_date'];
            $new_dr['exp_date'] = $drqc['exp_date'];
            $new_dr['best_before'] = $drqc['best_before'];
            $new_dr['ref_info'] = $drqc['ref_info'];
            $bo->stock_tran_qc->addRow($new_dr);
        }

        // Fetch if Everything was rejected
        $cmmqc = new \app\cwf\vsla\data\SqlCommand();
        $cmmqc->setCommandText("With qc_info
                As
                (	Select stock_tran_id, received_qty
                    From st.stock_tran a
                    Inner Join st.material b On a.material_id = b.material_id
                    Where a.stock_id = :pstock_id
                    Union All
                    Select annex_info->'doc_ref_info'->>'doc_ref_tran_id' doc_ref_tran_id,
                        Case When test_result_id Not In (1,2) Then (annex_info->>'tested_qty')::Numeric(18,3) * -1 Else 0 End
                    from prod.test_insp_control
                    Where annex_info->'doc_ref_info'->>'doc_ref_id' = :pstock_id
                        And status = 5
                )
                Select stock_tran_id, Sum(received_qty) as qc_bal_qty
                From qc_info
                Group by stock_tran_id
                Having Sum(received_qty) > 0;");
        $cmmqc->addParam("pstock_id", $bo->$doc_id_field);
        $dt_test = \app\cwf\vsla\data\DataConnect::getData($cmmqc);
        if (count($dt_test->Rows()) == 0) {
            $bo->vallow_close = true;
        } else {
            $bo->vallow_close = false;
        }
    }
    
    public static function validateQcTestCompleted(\app\cwf\vsla\xmlbo\BoBase $bo) {
        // Validate Completion of Qc Tests
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select material_id 
                From st.material 
                Where material_id = Any(:pmat_ids::BigInt[])
                    And (annex_info->'qc_info'->>'has_qc')::Boolean");
        $mat_ids = implode(",", $bo->stock_tran->select("material_id"));
        $cmm->addParam("pmat_ids", "{" . $mat_ids . "}");
        $dtqc = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtqc->Rows()) > 0) {
            $cmmqc = new \app\cwf\vsla\data\SqlCommand();
            $cmmqc->setCommandText("With qc_info
                    As
                    (	Select stock_tran_id, received_qty
                        From st.stock_tran a
                        Inner Join st.material b On a.material_id = b.material_id
                        Where a.stock_id = :pstock_id
                            And (b.annex_info->'qc_info'->>'has_qc')::Boolean
                        Union All
                        Select annex_info->'doc_ref_info'->>'doc_ref_tran_id' doc_ref_tran_id,
                            (annex_info->>'tested_qty')::Numeric(18,3) * -1
                        from prod.test_insp_control
                        Where annex_info->'doc_ref_info'->>'doc_ref_id' = :pstock_id
                            And status = 5
                    )
                    Select stock_tran_id, Sum(received_qty) as qc_bal_qty
                    From qc_info
                    Group by stock_tran_id
                    Having Sum(received_qty) > 0;");
            $cmmqc->addParam("pstock_id", $bo->stock_id);
            $dt_test = \app\cwf\vsla\data\DataConnect::getData($cmmqc);
            if (count($dt_test->Rows()) > 0) {
                $bo->addBRule('Cannot book purchase without completion of QC Test/Inspection');
            }
        }
    }
    
    public static function validateTsInfo(\app\cwf\vsla\xmlbo\BoBase $bo) {
        // Validate for TsInfo
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select material_id 
                From st.material 
                Where material_id = Any(:pmat_ids::BigInt[])
                    And (annex_info->'qc_info'->>'has_ts')::Boolean");
        $mat_ids = implode(",", $bo->stock_tran->select("material_id"));
        $cmm->addParam("pmat_ids", "{" . $mat_ids . "}");
        $dtqc = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtqc->Rows()) > 0) {
            if(!$bo->annex_info->Value()->ts_info->apply_ts) {
                $bo->addBRule('Document contains stock items that require Total Solids Information. Please check TS Info');
            } else {
                if($bo->annex_info->Value()->ts_info->ts_pcnt == 0) {
                    $bo->addBRule("Total Solids cannot be Zero. Please enter valid information");
                }
            }
        }
    }
}

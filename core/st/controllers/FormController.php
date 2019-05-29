<?php

namespace app\core\st\controllers;

use app\cwf\vsla\base\WebFormController;

class FormController extends WebFormController {

    public function actionUomschedulealloc() {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select uom_sch_id, uom_sch_desc from st.uom_sch '
                . 'where company_id=:pcompany_id order by uom_sch_desc');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $dtUoMSchedule = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = array();
        $result['uom_schedule'] = $dtUoMSchedule;
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionSelectuom($uom_sch_id) {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select uom_sch_id, uom_sch_item_id, uom_desc, '
                . 'uom_qty, is_base from st.uom_sch_item '
                . 'where uom_sch_id=:puom_sch_id order by uom_desc');
        $cmm->addParam('puom_sch_id', $uom_sch_id);
        $dtUoM = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $resultUoM = array();
        $resultUoM['uom'] = $dtUoM;
        $resultUoM['status'] = 'ok';
        return json_encode($resultUoM);
    }

    public function actionSelecttaxdetail($tax_schedule_id) {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select step_id, tax_detail_id, parent_tax_details, description, account_id, en_tax_type, 
                                    tax_perc, tax_on_perc, tax_on_min_amt, tax_on_max_amt, min_tax_amt, max_tax_amt
                                From st.tax_detail
                                where tax_schedule_id=:ptax_schedule_id');
        $cmm->addParam('ptax_schedule_id', $tax_schedule_id);
        $dtTaxDetail = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $resultTaxDetail = array();
        $resultTaxDetail['tax_detail'] = $dtTaxDetail;
        $resultTaxDetail['status'] = 'ok';
        return json_encode($resultTaxDetail);
    }

    public function actionGetwacrate($material_id, $source_branch_id, $to_date) {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * from st.fn_material_balance_wac(:pcompany_id, :pbranch_id, :pmaterial_id, :pfinyear, :pto_date)');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', $source_branch_id);
        $cmm->addParam('pmaterial_id', $material_id);
        $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
        $cmm->addParam('pto_date', $to_date);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = array();
        $result['wacrate'] = $dt;
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionGetmatcatinfo($mat_cat_id) {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select mat_cat_key_id, mat_cat_key from st.mat_cat_key where mat_cat_id =:pmat_cat_id');
        $cmm->addParam('pmat_cat_id', $mat_cat_id);
        $dtMatCatKey = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select mat_cat_attr_id, mat_cat_attr from st.mat_cat_attr where mat_cat_id =:pmat_cat_id');
        $cmm->addParam('pmat_cat_id', $mat_cat_id);
        $dtMatCatAttr = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $resultTaxDetail = array();
        $resultTaxDetail['mat_cat_key'] = $dtMatCatKey;
        $resultTaxDetail['mat_cat_attr'] = $dtMatCatAttr;
        $resultTaxDetail['status'] = 'ok';
        return json_encode($resultTaxDetail);
    }

    public function actionGetsobalance($stock_id, $customer_id) {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from crm.fn_so_bal_with_block_qty(:pcompany_id, :pbranch_id, :pvoucher_id, :pto_date) where customer_id =:pcustomer_id');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('pvoucher_id', $stock_id);
        $cmm->addParam('pto_date', date("Y-m-d", time()));
        $cmm->addParam('pcustomer_id', $customer_id);
        $dtsobal = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = array();
        $result['so_bal'] = $dtsobal;
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionGetMatInfo($barcode = '', $mat_id = -1, $vat_type_id = -1, $stock_loc_id = -1, $doc_date = null) {
        if ($barcode != '' || $mat_id != -1) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select * From st.sp_get_matInfo(:pbar_code, :pmat_id, :pvat_type_id, :pstock_loc_id, :pdoc_date, :pfinyear);");
            $cmm->addParam('pbar_code', $barcode);
            $cmm->addParam('pmat_id', $mat_id);
            $cmm->addParam('pvat_type_id', $vat_type_id);
            $cmm->addParam('pstock_loc_id', $stock_loc_id);
            $cmm->addParam('pdoc_date', $doc_date);
            $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('finyear'));
            $dtMatInfo = \app\cwf\vsla\data\DataConnect::getData($cmm);
            return json_encode($dtMatInfo->Rows()[0]);
        }
        return json_encode([]);
    }

    public function actionGetMatGstInfoSale($barcode = '', $mat_id = -1, $stock_loc_id = -1, $doc_date = null, $cust_id = -1) {
        if($barcode!= '' || $mat_id != -1) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select * From st.fn_mat_info_sale(:pbar_code, :pmat_id, :pstock_loc_id, :pdoc_date, :pfinyear, :pcust_id);");
            $cmm->addParam('pbar_code', $barcode);
            $cmm->addParam('pmat_id', $mat_id);
            $cmm->addParam('pstock_loc_id', $stock_loc_id);
            $cmm->addParam('pdoc_date', $doc_date);
            $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('finyear'));
            $cmm->addParam('pcust_id', $cust_id);
            $dtMatInfo = \app\cwf\vsla\data\DataConnect::getData($cmm);
            
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select stock_location_id, stock_location_name, mat_bal From st.fn_mat_sl_bal(:pbranch_id, :pmat_id, :pfinyear, :pas_on)");
            $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $cmm->addParam('pmat_id', $mat_id);
            $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
            $cmm->addParam('pas_on', $doc_date);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            $html = ''; // '<div>';
            foreach($dt->Rows() as $dr) {
                $html .= $dr['stock_location_name'].": ".\app\cwf\vsla\utils\FormatHelper::FormatQty($dr['mat_bal'])."\n";
                //$html .= "<span sl_id=".$dr['stock_location_id'].">".$dr['stock_location_name']." :</span><span>".$dr['mat_bal']."</span><br/>";
            }
            //$html .= '</div>';
            $result = $dtMatInfo->Rows()[0];
            $result['sl_mat_bal'] = $html;
            
            return json_encode($result);
        }
        return json_encode([]);
    }
    
    public function actionGetMatBalMany(array $mat_data, $stock_loc_id, $doc_date) {
        $mat_ids = implode(",", $mat_data);
        $Sql = "Select a.material_id, Coalesce(Sum(a.received_qty-a.issued_qty), 0.00) as bal_qty
                From st.stock_ledger a
                Where a.material_id = Any(:pmat_ids::BigInt[])
                    And a.doc_date <= :pdoc_date
                    And a.stock_location_id = :pstock_loc_id
                    And a.finyear = :pfinyear
                Group By a.material_id;";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($Sql);
        $cmm->addParam('pmat_ids', "{".$mat_ids."}");
        $cmm->addParam('pstock_loc_id', $stock_loc_id);
        $cmm->addParam('pdoc_date', $doc_date);
        $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('finyear'));
        $dtMatInfo = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return json_encode($dtMatInfo);
    }
    
    public function actionGetMatBalManySl() {        
        $mat_data = \yii::$app->request->post('mat_data');
        $doc_date = \yii::$app->request->post('doc_date');
        $Sql = "With mat_tran
                As
                (	Select x.material_id, x.stock_location_id
                        From jsonb_to_recordset(:pmat_data_alloc::JsonB) as x(material_id bigint, stock_location_id bigint)
                        Group By x.material_id, x.stock_location_id
                )
                Select a.material_id, a.stock_location_id, Coalesce(Sum(a.received_qty-a.issued_qty), 0.00) as bal_qty
                From st.stock_ledger a
                Inner join mat_tran b on a.material_id = b.material_id And a.stock_location_id = b.stock_location_id
                Where a.doc_date <= :pdoc_date
                    And a.finyear = :pfinyear
                Group By a.material_id, a.stock_location_id;";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($Sql);
        $cmm->addParam('pmat_data_alloc', json_encode($mat_data));
        $cmm->addParam('pdoc_date', $doc_date);
        $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('finyear'));
        $dtMatInfo = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return json_encode($dtMatInfo);
    }

    public function actionGetMatGstInfoPurch($barcode = '', $mat_id = -1, $stock_loc_id = -1, $doc_date = null, $cust_id = -1) {
        if($barcode!= '' || $mat_id != -1) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select * From st.fn_mat_info_purch(:pbar_code, :pmat_id, :pstock_loc_id, :pdoc_date, :pfinyear, :pcust_id);");
            $cmm->addParam('pbar_code', $barcode);
            $cmm->addParam('pmat_id', $mat_id);
            $cmm->addParam('pstock_loc_id', $stock_loc_id);
            $cmm->addParam('pdoc_date', $doc_date);
            $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('finyear'));
            $cmm->addParam('pcust_id', $cust_id);
            $dtMatInfo = \app\cwf\vsla\data\DataConnect::getData($cmm);
            return json_encode($dtMatInfo->Rows()[0]);
        }
        return json_encode([]);
    }
    
    public function actionGetMatGstInfoCc($barcode = '', $mat_id = -1, $stock_loc_id = -1, $doc_date = null) {
        if($doc_date == null) {
            $doc_date = \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('year_end');
        }
        if($barcode != '' || $mat_id != -1) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select * From st.fn_mat_info_cc(:pbar_code, :pmat_id, :pstock_loc_id, :pdoc_date, :pfinyear);");
            $cmm->addParam('pbar_code', $barcode);
            $cmm->addParam('pmat_id', $mat_id);
            $cmm->addParam('pstock_loc_id', $stock_loc_id);
            $cmm->addParam('pdoc_date', $doc_date);
            $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('finyear'));
            $dtMatInfo = \app\cwf\vsla\data\DataConnect::getData($cmm);
            
            
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select stock_location_id, stock_location_name, mat_bal From st.fn_mat_sl_bal(:pbranch_id, :pmat_id, :pfinyear, :pas_on)");
            $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $cmm->addParam('pmat_id', $mat_id);
            $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
            $cmm->addParam('pas_on', $doc_date);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            $html = ''; // '<div>';
            foreach($dt->Rows() as $dr) {
                $html .= $dr['stock_location_name'].": ".\app\cwf\vsla\utils\FormatHelper::FormatQty($dr['mat_bal'])."\n";
                //$html .= "<span sl_id=".$dr['stock_location_id'].">".$dr['stock_location_name']." :</span><span>".$dr['mat_bal']."</span><br/>";
            }
            //$html .= '</div>';
            $result = $dtMatInfo->Rows()[0];
            $result['sl_mat_bal'] = $html;
            
            return json_encode($result);
        }
        return json_encode([]);
    }
    
    public function actionGetMatInfoPurchase($barcode = '', $mat_id = -1, $vat_type_id = -1) {
        if ($barcode != '' || $mat_id != -1) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select * From st.sp_get_matInfo_purchase(:pbar_code, :pmat_id, :pvat_type_id);");
            $cmm->addParam('pbar_code', $barcode);
            $cmm->addParam('pmat_id', $mat_id);
            $cmm->addParam('pvat_type_id', $vat_type_id);
            $dtMatInfo = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dtMatInfo->Rows()) == 1) {
                return json_encode($dtMatInfo->Rows()[0]);
            }
        }
        return json_encode([]);
    }

    public function actionGetItemTaxInfo($tax_schedule_id) {
        // This will only return the first step of the tax schedule
        // as applicable for item level taxes
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select a.description as tax_schedule_desc, b.en_tax_type, b.tax_perc
                From tx.tax_schedule a 
                Inner Join tx.tax_detail b On a.tax_schedule_id = b.tax_schedule_id
                Where a.tax_schedule_id=:pts_id And b.step_id = 1;');
        $cmm->addParam('pts_id', $tax_schedule_id);
        $dtTax = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtTax->Rows()) == 1) {
            return json_encode($dtTax->Rows()[0]);
        }
        return json_encode([]);
    }

    public function actionGetMatDetail($material_id) {
        if ($material_id > 0) {
            $cmminfo = new \app\cwf\vsla\data\SqlCommand();
            $cmminfo->setCommandText("
            Select b.*, e.igst_pcnt as gst_pcnt, d.uom_desc, 0 as bal,null as balinfo 
            From (  Select a.material_id, a.material_name, a.material_code, a.material_type_id,
                                (a.annex_info->'sale_price'->'sp_calc'->>'fixed_pu')::numeric as Price,
                                (a.annex_info->'sale_price'->'sp_calc'->>'disc_pcnt')::numeric as Disc,
                                (a.annex_info->'sale_price'->>'tax_schedule_id')::bigint as TaxSch,
                                (a.annex_info->'supp_info'->>'mfg')::varchar as mfg,
                                (a.annex_info->'supp_info'->>'mfg_part_no')::varchar as mfg_part_no,
                                (a.annex_info->'gst_info'->>'hsn_sc_id')::BigInt as hsn_sc_id
                        From st.material a Where a.material_id=:pmaterial_id) b
            Left join tx.hsn_sc_rate c On b.hsn_sc_id = c.hsn_sc_id
            inner join st.uom d on b.material_id = d.material_id and d.is_base = true
            Left Join tx.gst_rate e On c.gst_rate_id = e.gst_rate_id");
            $cmminfo->addParam('pmaterial_id', $material_id);
            $dtinfo = \app\cwf\vsla\data\DataConnect::getData($cmminfo);

            $cmmbal = new \app\cwf\vsla\data\SqlCommand();
            $cmmbal->setCommandText("
                select a.*, b.stock_location_code, b.stock_location_name 
                From (  Select * 
                        from st.fn_material_balance_wac_detail(:pcompany_id,:pbranch_id,:pmaterial_id,0,:pfin_year,:pto_date)
                        Where balance_qty_base != 0
                     ) a
                inner join st.stock_location b on a.stock_location_id = b.stock_location_id");
            $cmmbal->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID());
            $cmmbal->addParam('pbranch_id', 0); // Display data for all branches
            $cmmbal->addParam('pmaterial_id', $material_id);
            $cmmbal->addParam('pfin_year', \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('finyear'));
            $cmmbal->addParam('pto_date', date("Y-m-d"));
            $dtbal = \app\cwf\vsla\data\DataConnect::getData($cmmbal);

            $matbal = 0.00;
            foreach ($dtbal->Rows() as $rw) {
                $matbal += floatval($rw['balance_qty_base']);
            }
            if (count($dtinfo->Rows()) > 0) {
                $dtinfo->Rows()[0]['bal'] = $matbal;
                $dtinfo->Rows()[0]['balinfo'] = $dtbal;
            }
            return json_encode($dtinfo->Rows()[0]);
        } else {
            return json_encode([]);
        }
    }

    public function actionWarInfoReqd($material_id){ 
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        
        $cmm->setCommandText("select annex_info->'war_info'->>'has_war' as has_war from st.material
                    where material_id =:pmaterial_id");
        
        $cmm->addParam('pmaterial_id', $material_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $has_war = 'false';
        
        if (count($dt->Rows()) > 0) {
            if($dt->Rows()[0]['has_war'] == true)
            {
                $has_war = 'true';
            }
            else{
                $has_war = 'false';
            }
        }

        $result = array();
        $result['has_war'] = $has_war;
        $result['status'] = 'ok';
        return json_encode($result);
    }
    
    public function actionSiForSr(string $origin_inv_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "With si_tran
                As
                (	Select a.stock_id, b.stock_tran_id, b.issued_qty
                        From st.stock_control a
                        Inner Join st.stock_tran b on a.stock_id=b.stock_id
                        Where a.status=5 
                            And a.stock_id = :porigin_inv_id
                        Union All
                        Select b.reference_id, b.reference_tran_id, -1 * b.received_qty
                        From st.stock_control a
                        Inner Join st.stock_tran b on a.stock_id=b.stock_id
                        Where a.doc_type = 'SRV' 
                            And (a.annex_info->'dcn_type' Is Null Or (a.annex_info->>'dcn_type')::Int = 0)
                                        And b.reference_id = :porigin_inv_id
                ),
                si_bal
                As
                (       Select a.stock_id, a.stock_tran_id, Sum(a.issued_qty) as bal_qty
                        From si_tran a
                        Group by a.stock_id, a.stock_tran_id
                        Having Sum(a.issued_qty) > 0
                )
                Select a.stock_id, a.stock_tran_id, a.bar_code, a.material_type_id, a.material_id, b.material_name, a.stock_location_id, a.uom_id, c.uom_desc, e.bal_qty, 
                        (d.bt_amt / a.issued_qty)::Numeric(18,3) as rate,
                        row_to_json(d.*) as gst_hsn_info
                From st.stock_tran a
                Inner Join st.material b On a.material_id = b.material_id
                Inner Join st.uom c On a.uom_id = c.uom_id
                Inner Join tx.gst_tax_tran d On a.stock_tran_id = d.gst_tax_tran_id
                Inner Join si_bal e On a.stock_tran_id = e.stock_tran_id
                Order by a.stock_tran_id";
        $cmm->setCommandText($sql);
        $cmm->addParam("porigin_inv_id", $origin_inv_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $result = [
            'status' => 'ok',
            'si_bal' => $dt
        ];
        return json_encode($result);
    }
    
    public function actionSpForPr(string $origin_inv_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "With sp_tran
                As
                (	Select a.stock_id, b.stock_tran_id, b.received_qty
                        From st.stock_control a
                        Inner Join st.stock_tran b on a.stock_id=b.stock_id
                        Where a.status=5 
                            And a.stock_id = :porigin_inv_id
                        Union All
                        Select b.reference_id, b.reference_tran_id, -1 * b.issued_qty
                        From st.stock_control a
                        Inner Join st.stock_tran b on a.stock_id=b.stock_id
                        Where a.doc_type = 'PRV' 
                            And (a.annex_info->'dcn_type' Is Null Or (a.annex_info->>'dcn_type')::Int = 0)
                                        And b.reference_id = :porigin_inv_id
                ),
                sp_bal
                As
                (       Select a.stock_id, a.stock_tran_id, Sum(a.received_qty) as bal_qty
                        From sp_tran a
                        Group by a.stock_id, a.stock_tran_id
                        Having Sum(a.received_qty) > 0
                )
                Select a.stock_id, a.stock_tran_id, a.material_id, b.material_name, a.stock_location_id, a.uom_id, c.uom_desc, e.bal_qty, 
                        (a.bt_amt / a.received_qty)::Numeric(18,3) as rate, a.in_lc, 
                        row_to_json(d.*) as gst_hsn_info, coalesce((f.annex_info->'gst_rc_info'->>'apply_rc')::boolean, false) apply_rc, 
                        coalesce((f.annex_info->'gst_rc_info'->>'rc_sec_id')::bigint, -1) rc_sec_id
                From st.stock_tran a
                inner join st.stock_control f on a.stock_id = f.stock_id
                Inner Join st.material b On a.material_id = b.material_id
                Inner Join st.uom c On a.uom_id = c.uom_id
                Inner Join tx.gst_tax_tran d On a.stock_tran_id = d.gst_tax_tran_id
                Inner Join sp_bal e On a.stock_tran_id = e.stock_tran_id
                Order by a.stock_tran_id";
        $cmm->setCommandText($sql);
        $cmm->addParam("porigin_inv_id", $origin_inv_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $result = [
            'status' => 'ok',
            'sp_bal' => $dt
        ];
        return json_encode($result);
    }
     
    public function actionStockTransferParkPostData($stock_id) {        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "select b.sl_no, b.material_type_id, c.material_type, 
                b.material_id, d.material_name, b.uom_id, e.uom_desc, b.issued_qty
                from st.stock_control a
                inner join st.stock_tran b on a.stock_id=b.stock_id
                inner join st.material_type c on b.material_type_id=c.material_type_id
                inner join st.material d on b.material_id=d.material_id
                inner join st.uom e on b.uom_id=e.uom_id
                where a.stock_id=:pstock_id";
        $cmm->setCommandText($sql);
        $cmm->addParam("pstock_id", $stock_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $result = [
            'status' => 'ok',
            'stpp_dt' => $dt
        ];
        return json_encode($result);
    } 
    
    public function actionGetSlName($sl_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "select stock_location_name from st.stock_location where stock_location_id = :psl_id";
        $cmm->setCommandText($sql);
        $cmm->addParam("psl_id", $sl_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $sl_name = '';
        if(count($dt->Rows())> 0 ){
            $sl_name = $dt->Rows()[0]['stock_location_name'];
        }
        $result = [
            'status' => 'ok',
            'sl_name' => $sl_name
        ];
        return json_encode($result);
    }     
    
    public function actionListMatType() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select false as select, material_type_id, material_type, material_type_code,
                                material_type_code || ' -' || material_type as material_code_with_type
                                from st.material_type
                                order by material_type_code, material_type");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $result = array();
        $result['sttype'] = $dt;
        $result['status'] = 'OK';
        return json_encode($result);
    }
    
     public function actionListMat($mt_id = 0) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select false as select, a.material_id, a.material_code, a.material_name, 
                                a.material_type_id, b.material_type,
                                a.material_code || ' -' || a.material_name as material_code_with_name
                                from st.material a
                                Inner Join st.material_type b On a.material_type_id = b.material_type_id
                                Where (b.material_type_id = :pmt_id Or :pmt_id = 0)
                                order by a.material_name");
        $cmm->addParam('pmt_id', $mt_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $result = array();
        $result['stmat'] = $dt;
        $result['status'] = 'OK';
        return json_encode($result);
    }
    
    public function actionListBranch() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select false as select, branch_id, branch_name, branch_description
                                from sys.branch
                                order by branch_name");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $result = array();
        $result['dt_br'] = $dt;
        $result['status'] = 'OK';
        return json_encode($result);
    }
    
    public function actionGetMatSlBal(int $mat_id, string $as_on) {
        $as_on = ($as_on == "" ? date("Y-m-d") : $as_on);
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select stock_location_id, stock_location_name, mat_bal From st.fn_mat_sl_bal(:pbranch_id, :pmat_id, :pfinyear, :pas_on)");
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('pmat_id', $mat_id);
        $cmm->addParam('pfinyear', \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
        $cmm->addParam('pas_on', $as_on);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $html = ''; // '<div>';
        foreach($dt->Rows() as $dr) {
            $html .= $dr['stock_location_name'].": ".\app\cwf\vsla\utils\FormatHelper::FormatQty($dr['mat_bal'])."\n";
            //$html .= "<span sl_id=".$dr['stock_location_id'].">".$dr['stock_location_name']." :</span><span>".$dr['mat_bal']."</span><br/>";
        }
        //$html .= '</div>';
        return $html;
    }
}

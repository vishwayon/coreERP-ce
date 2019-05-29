<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\matValueMonitor;

/**
 * Description of MatValMonController
 *
 * @author girishshenoy
 */
class MatValMonController extends \app\cwf\vsla\base\WebController {
    
     public function actionIndex($viewName = null, $viewParams = null) {
        return $this->renderPartial('@app/core/st/matValueMonitor/ViewMatValueMonitor');
    }
    
    public function actionFetchNegBal($mat_type_id) {
        $mat_type_id = $mat_type_id == -1 ? 0 : $mat_type_id;
        $result = [
            'status' => 'OK',
            'negstock' => $this->getNegMatBal($mat_type_id)
        ];
        return json_encode($result);
    }
    
    private function getNegMatBal($mat_type_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("With sl_bal
            As
            (	Select a.material_id, a.doc_date, Sum(a.received_qty-a.issued_qty) Over (Partition By a.material_id Order By a.doc_date) as daily_bal
                    From st.stock_ledger a
                    Inner Join st.material b On a.material_id = b.material_id
                    Where a.branch_id = {branch_id} 
                        And a.finyear = '{finyear}'
                        And (b.material_type_id = :pmat_type_id Or :pmat_type_id = 0)
            )
            Select a.material_id, c.material_type, b.material_code, b.material_name,
                    min(a.doc_date) as txn_date, sum(a.daily_bal) as neg_bal
            From sl_bal a
            Inner Join st.material b On a.material_id = b.material_id
            Inner Join st.material_type c On b.material_type_id = c.material_type_id
            Where a.daily_bal < 0
            Group By a.material_id, c.material_type, b.material_code, b.material_name
            Order by c.material_type, b.material_name;");
        $cmm->addParam("pmat_type_id", $mat_type_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    public function actionFetchWacCv($mat_type_id, $mat_id) {
        $result = [
            'status' => 'OK',
            'waccv' => $this->getWacCv($mat_type_id, $mat_id)
        ];
        return json_encode($result);
    }
    
    private function getWacCv($mat_type_id, $mat_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select a.material_id, c.material_type, b.material_code, b.material_name,
                    Sum(a.received_qty)::Numeric(18,3) as received_qty,
                    Sum(a.issued_qty)::Numeric(18,3) as issued_qty,
                    Avg(a.unit_rate_lc)::Numeric(18,3) as unit_rate_lc,
                    Avg(a.unit_rate_sl)::Numeric(18,3) as unit_rate_sl,
                    coalesce(max(wac_stddev), 0)::Numeric(18,3) as wac_stddev,
                    coalesce(avg(wac_cv), 0)::Numeric(18,3) as wac_cv
                From st.mat_wac_cv(:pbranch_id, :pfinyear, :pmat_type_id, :pmat_id) a
                Inner Join st.material b On a.material_id = b.material_id
                Inner Join st.material_type c On b.material_type_id = c.material_type_id
                Group by a.material_id, c.material_type, b.material_code, b.material_name");
        $cmm->addParam("pbranch_id", \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam("pfinyear", \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
        $cmm->addParam("pmat_type_id", $mat_type_id);
        $cmm->addParam("pmat_id", $mat_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
}

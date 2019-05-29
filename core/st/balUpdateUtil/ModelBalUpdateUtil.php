<?php

namespace app\core\st\balUpdateUtil;

/**
 * Description of ModelBalUpdateUtil
 *
 * @author girishshenoy
 */
class ModelBalUpdateUtil {
    
    public function get_data($mat_type_id, $sl_id, $as_on) {
        $sql = "With sl_sum
                As
                (	Select a.material_id, 
                        Sum(Case When a.is_opbl Then a.received_qty Else 0.0 End) as op_bal, 
                        Sum(Case When Not a.is_opbl Then a.received_qty Else 0.0 End) as receipts, 
                        Sum(Case When Not a.is_opbl Then a.issued_qty Else 0.0 End) as issues,
                        Sum(a.received_qty - a.issued_qty) as cl_bal
                    From st.stock_ledger a
                    Where a.finyear = :pfinyear
                        And a.doc_date <= :pas_on
                        And a.stock_location_id = :psl_id
                    Group by a.material_id
                )
                Select c.material_type, a.material_id, a.material_name, a.inventory_account_id,
                        Coalesce(b.op_bal, 0.0) as op_bal, 
                    Coalesce(b.receipts, 0.0) as receipts, 
                    Coalesce(b.issues, 0.0) as issues, 
                    Coalesce(b.cl_bal, 0.0) as cl_bal,
                    false as revise, 
                    -1.0 as revised_cl_bal, 
                    0.0 as revised_op_bal
                From st.material a 
                Left Join sl_sum b On a.material_id = b.material_id
                Inner Join st.material_type c On a.material_type_id = c.material_type_id
                Where c.material_type_id = :pmat_type_id
                Order by material_name";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($sql);
        $cmm->addParam("pfinyear", \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('finyear'));
        $cmm->addParam("psl_id", $sl_id);
        $cmm->addParam("pas_on", $as_on);
        $cmm->addParam("pmat_type_id", $mat_type_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $result = [
            'status' => 'OK',
            'mat_type_id' => $mat_type_id,
            'sl_id' => $sl_id,
            'as_on' => $as_on,
            'matbal' => $dt
        ];
        return $result;
    }
    
    public function post_data($data) {
        $result = [
            'status' => 'error',
            'brokenrules' => [],
            'updated' => 0
        ];
        $brokenrules = [];
        foreach($data->matbal as $dr) {
            if($dr->revise && $dr->revised_op_bal < 0) {
                $brokenrules[] = "Negative Op. Bal: ".$dr->material_name;
            }
        }
        if(count($brokenrules)>0) {
            $result['brokenrules'] = $brokenrules;
            return $result;
        }
        
        // Proceed if there are no errors
        $sql = "Select * From st.sp_mat_bal_update_util(:pcompany_id, :pbranch_id, :pfinyear, :pdoc_date, :pmaterial_id, 
            :pstock_location_id, :popbal_qty, :punit_rate_lc)";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($sql);
        $cmm->addParam("pfinyear", \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('finyear'));
        $cmm->addParam("pstock_location_id", $data->sl_id);
        $cmm->addParam("pbranch_id", \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('branch_id'));
        $year_begin = \DateTime::createFromFormat('Y-m-d', \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('year_begin'));
        $year_begin->sub(new \DateInterval('P1D'));
        $cmm->addParam("pdoc_date", $year_begin->format('Y-m-d'));
        $cmm->addParam("pcompany_id", \app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('company_id'));
        
        // Varying parameters
        $cmm->addParam("pmaterial_id", -1);
        $cmm->addParam("popbal_qty", -1);
        $cmm->addParam("punit_rate_lc", 0.0);
        
        $cn = \app\cwf\vsla\data\DataConnect::getCn(\app\cwf\vsla\data\DataConnect::COMPANY_DB);
        $cn->beginTransaction();
        $i = 0;
        foreach($data->matbal as $dr) {
            if($dr->revise && $dr->revised_op_bal >= 0 && $dr->revised_cl_bal >= 0) {
                // We update records in Stock Ledger
                $cmm->setParamValue("pmaterial_id", $dr->material_id);
                $cmm->setParamValue("popbal_qty", $dr->revised_op_bal);
                $cmm->setParamValue("punit_rate_lc", 0.0);
                
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                $i++;
            }
        }
        $cn->commit();
        $result['status'] = 'OK';
        $result['updated'] = $i;
        return $result;
    }
}

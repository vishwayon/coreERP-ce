<?php

namespace app\core\ap\blockSuppPymt;

use app\cwf\vsla\data\DataConnect;
use app\cwf\vsla\security\SessionManager;

class ModelBlockSuppPymt {

    public $filters;
    public $view_type_option;
    public $view_type_id;
    public $brokenrules = array();

    const VIEW_TYPE_UNBLOCKED = 0;
    const VIEW_TYPE_BLOCKED = 1;
    const VIEW_TYPE_ALL = 2;

    public function __construct() {
        $this->dt = array();
        $this->view_type_id = 0;
    }

    public function setFilters($filter) {
        $this->view_type_id = $filter['view_type_id'];
        $this->getData();
    }

    public function getData() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select min(ret_period_from) from_date, max(ret_period_to) to_date
                            from tx.gst_ret a
                            inner join tx.gst_state b on a.gst_state_id = b.gst_state_id
                            Where a.gst_ret_type_id = 102");
        $dtRet = DataConnect::getData($cmm);
        
        $from_date = SessionManager::getSessionVariable('year_begin');
        $to_date = SessionManager::getSessionVariable('year_end');
        
        if(count($dtRet->Rows()) > 0){
           $from_date =  $dtRet->Rows()[0]['from_date'];
           $to_date =  $dtRet->Rows()[0]['to_date'];
        }
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("with gstr2a
                                as (
                                    Select c.supplier_id, sum(a.base_amt) gstr2a_bt_amt, sum(a.sgst_amt+a.cgst_amt+a.igst_amt) gstr2a_gst_amt
                                    From tx.gstr2a a
                                    Inner Join tx.gst_ret b On a.gst_ret_id = b.gst_ret_id
                                    Inner join ap.supplier c on a.supp_gstin = (c.annex_info->'satutory_details'->>'gstin')::varchar
                                    Where a.voucher_id = ''
                                    Group By c.supplier_id
                                ),
                                prg
                                As (
                                        Select a.supplier_id, Sum(a.bt_amt) prg_bt_amt, Sum(a.sgst_amt + a.cgst_amt + a.igst_amt) prg_gst_amt	
                                    From ap.fn_purchase_register_report(:pcomp_id, :pbranch_id, 0, :pfrom_date, :pto_date, 0, 'All', false) a
                                    Where length(a.gstin) > 2
                                        And a.voucher_id Not In ( Select x.voucher_id From tx.gstr2a x Where x.voucher_id != '')
                                    Group By a.supplier_id
                                )

                                Select a.supplier_id, a.supplier, (a.annex_info->>'block_pymt')::boolean block_pymt, COALESCE(b.prg_bt_amt, 0) prg_bt_amt, COALESCE(b.prg_gst_amt, 0) prg_gst_amt, 
                                                COALESCE(c.gstr2a_bt_amt, 0) gstr2a_bt_amt, COALESCE(c.gstr2a_gst_amt, 0) gstr2a_gst_amt
                                from ap.supplier a 
                                left join prg b on a.supplier_id = b.supplier_id
                                left join gstr2a c on a.supplier_id = c.supplier_id
                                where case 
                                    when :pview_type_id = 0  then -- Unblocked
                                        COALESCE((a.annex_info->>'block_pymt')::boolean, false) = false		
                                    when :pview_type_id = 1  then -- Blocked
                                        COALESCE((a.annex_info->>'block_pymt')::boolean, false) = true
                                    Else
                                        (1=1)
                                    End
                                order by supplier;");
        
        $stateBrId = (\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id') * 1000000) + 500000 
                + intval(\app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gst_state_id']);
        $cmm->addParam('pview_type_id', $this->view_type_id);
        $cmm->addParam('pcomp_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', $stateBrId);
        $cmm->addParam('pfrom_date', $from_date);
        $cmm->addParam('pto_date', $to_date);
        $this->dt = DataConnect::getData($cmm);      
    }

    public function setData($model) {
        $this->validate($model);
        if (count($this->brokenrules) == 0) {
            $cn = DataConnect::getCn(DataConnect::COMPANY_DB);
            try {

                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText("update ap.supplier a
                                        set annex_info = jsonb_set(annex_info, '{block_pymt}', :pblock_pymt::jsonb, false)
                                        Where a.supplier_id = :psupplier_id;");
                $cmm->addParam('pblock_pymt', -1);
                $cmm->addParam('psupplier_id', -1);
                $cn->beginTransaction();
                for ($rowIndex = 0; $rowIndex < count($model->dt); $rowIndex++) {
                    if ($model->view_type_id == self::VIEW_TYPE_UNBLOCKED) {
                        if ($model->dt[$rowIndex]->block_pymt == true) {
                            $cmm->setParamValue('psupplier_id', $model->dt[$rowIndex]->supplier_id);
                            $cmm->setParamValue('pblock_pymt', $model->dt[$rowIndex]->block_pymt);
                            DataConnect::exeCmm($cmm, $cn);
                        }
                    }
                    if ($model->view_type_id == self::VIEW_TYPE_BLOCKED) {
                        if ($model->dt[$rowIndex]->block_pymt == false) {
                            $cmm->setParamValue('psupplier_id', $model->dt[$rowIndex]->supplier_id);
                            $cmm->setParamValue('pblock_pymt', $model->dt[$rowIndex]->block_pymt);
                            DataConnect::exeCmm($cmm, $cn);
                        }
                    }
                }
                $cn->commit();
                $cn = null;
            } catch (\Exception $ex) {
                if ($cn->inTransaction()) {
                    $cn->rollBack();
                    $cn = null;
                }
                return $ex->getMessage();
            }
        }
    }

    public function validate($model) {

//        $cmm = new \app\cwf\vsla\data\SqlCommand();
//        $cmm->setCommandText('select * from tx.gst_state');
//        $dtGstState = \app\cwf\vsla\data\DataConnect::getData($cmm);
//        $gstStates = $dtGstState->asArray('gst_state_code', 'gst_state_id');
//        
//        foreach($model->dt as &$row)
//        {
//            if ($row->selected == true) {                
//                if ($row->new_gstin == '') {
//                    array_push($this->brokenrules, \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ap/lookups/Supplier.xml', 'supplier', 'supplier_id', $row->supplier_id) . ' : GSTIN is required.');
//                }
//                $gstin = substr($row->new_gstin, 0, 2);
//                if(array_key_exists($gstin, $gstStates)){
//                    $row->new_gst_state_id = $gstStates[$gstin];                     
//                    if($row->new_gstin != $gstin){
//                        if(!preg_match("/^[0-9]{2}[a-zA-Z]{5}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[Z]{1}[0-9a-zA-Z]{1}$/", $row->new_gstin)){
//                            array_push($this->brokenrules, \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ap/lookups/Supplier.xml', 'supplier', 'supplier_id', $row->supplier_id) .' : Invalid GSTIN.');
//                        }        
//                    }  
//                }
//                else{
//                    array_push($this->brokenrules, \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ap/lookups/Supplier.xml', 'supplier', 'supplier_id', $row->supplier_id) .' : Invalid GSTIN.');
//                }          
//            }
//        }
    }

}

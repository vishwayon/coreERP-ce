<?php

namespace app\core\ap\suppGstinUpdate;

use app\cwf\vsla\data\DataConnect;
use app\cwf\vsla\security\SessionManager;

class ModelSuppGstinUpdate {

    public $filters;
    public $view_type_option;
    public $view_type_id;
    public $brokenrules = array();

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
        $cmm->setCommandText("select *, false as selected, -1 as new_gst_state_id, '' as new_gstin "
                . " from ap.fn_supp_gst_coll(:pview_type_id) order by supplier");
        $cmm->addParam('pview_type_id', $this->view_type_id);

        $this->dt = DataConnect::getData($cmm);
    }

    public function setData($model) {
        $this->validate($model);
        if (count($this->brokenrules) == 0) {
            $cn = DataConnect::getCn(DataConnect::COMPANY_DB);
            try {

                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('select * from ap.sp_supp_gst_update(:psupplier_id, :pnew_gst_state_id, :pnew_gstin)');
                $cmm->addParam('pnew_gst_state_id', -1);
                $cmm->addParam('psupplier_id', -1);
                $cmm->addParam('pnew_gstin', '');
                $cn->beginTransaction();
                for ($rowIndex = 0; $rowIndex < count($model->dt); $rowIndex++) {
                    if ($model->dt[$rowIndex]->selected == true) {
                        $cmm->setParamValue('psupplier_id', $model->dt[$rowIndex]->supplier_id);
                        $cmm->setParamValue('pnew_gst_state_id', $model->dt[$rowIndex]->new_gst_state_id);
                        $cmm->setParamValue('pnew_gstin', '"' . $model->dt[$rowIndex]->new_gstin . '"');
                        DataConnect::exeCmm($cmm, $cn);
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
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from tx.gst_state');
        $dtGstState = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $gstStates = $dtGstState->asArray('gst_state_code', 'gst_state_id');
        
        foreach($model->dt as &$row)
        {
            if ($row->selected == true) {                
                if ($row->new_gstin == '') {
                    array_push($this->brokenrules, \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ap/lookups/Supplier.xml', 'supplier', 'supplier_id', $row->supplier_id) . ' : GSTIN is required.');
                }
                $gstin = substr($row->new_gstin, 0, 2);
                if(array_key_exists($gstin, $gstStates)){
                    $row->new_gst_state_id = $gstStates[$gstin];                     
                    if($row->new_gstin != $gstin){
                        if(!preg_match("/^[0-9]{2}[a-zA-Z]{5}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[Z]{1}[0-9a-zA-Z]{1}$/", $row->new_gstin)){
                            array_push($this->brokenrules, \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ap/lookups/Supplier.xml', 'supplier', 'supplier_id', $row->supplier_id) .' : Invalid GSTIN.');
                        }        
                    }  
                }
                else{
                    array_push($this->brokenrules, \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/ap/lookups/Supplier.xml', 'supplier', 'supplier_id', $row->supplier_id) .' : Invalid GSTIN.');
                }          
            }
        }
    }
}

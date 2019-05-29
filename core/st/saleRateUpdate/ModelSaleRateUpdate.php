<?php

namespace app\core\st\saleRateUpdate;

use app\cwf\vsla\data\DataConnect;
use app\cwf\vsla\security\SessionManager;

class ModelSaleRateUpdate {

    public $salerateupdatedata;
    public $filters;
    public $material_type_id = -1;
    public $material_id = -1;
    public $price_type = '';
    public $brokenrules = array();
    public $dt;

    public function __construct() {
        $this->dt = array();
    }

    public function setFilters($filter) {
        $this->filters = $filter;
        if ($filter['material_type_id'] == '' && $filter['material_id'] == '') {
            array_push($this->brokenrules, 'Select Stock Type or Stock Item.');
        }
        elseif ($filter['material_type_id'] == '0' && $filter['material_id'] == '') {
            array_push($this->brokenrules, 'Select Stock Item.');
        }
        if (count($this->brokenrules) == 0) {
            $this->material_type_id = $filter['material_type_id'];
            $this->material_id = $filter['material_id'] == '' || $filter['material_id'] == -1 ? 0 : intval($filter['material_id']);
            $this->price_type = $filter['rate_type'];
            $this->getData();
        }
    }

    public function getData() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select *,false as select,sr_pu as msr_pu, disc_pcnt as mdisc_pcnt '
                . 'from st.fn_mat_coll_for_sale_rate(:pmaterial_type_id, :pmaterial_id, :pprice_type)');
        $cmm->addParam('pmaterial_type_id', $this->material_type_id);
        $cmm->addParam('pmaterial_id', $this->material_id);
        $cmm->addParam('pprice_type', $this->price_type);
        $this->dt = DataConnect::getData($cmm);
    }

    public function validate($model) {
//        for ($rowIndex=0;$rowIndex< count($model->dt);$rowIndex++) {
//            if ($model->dt[$rowIndex]->select == TRUE) {
//                if($model->dt[$rowIndex]->msr_pu != 0 && $model->dt[$rowIndex]->mdisc_pcnt != 0 ){
//                    array_push($this->brokenrules, $model->dt[$rowIndex]->material_name.' : Both New Price/Unit and New Disc cannot be greater than zero.');                        
//                }
//            }   
//        }
    }

    public function setData($model) {
        $this->validate($model);
        if (count($this->brokenrules) == 0) {
            $cn = DataConnect::getCn(DataConnect::COMPANY_DB);
            try {

                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('select * from st.sp_sale_rate_update(:pprice_type, :pmaterial_id, :psr_pu, :pdisc_pcnt)');
                $cmm->addParam('pprice_type', '');
                $cmm->addParam('pmaterial_id', -1);
                $cmm->addParam('psr_pu', 0);
                $cmm->addParam('pdisc_pcnt', 0);
                $cn->beginTransaction();

                for ($rowIndex = 0; $rowIndex < count($model->dt); $rowIndex++) {
                    if ($model->dt[$rowIndex]->select == TRUE) {
                        $cmm->setParamValue('pprice_type', $model->dt[$rowIndex]->price_type);
                        $cmm->setParamValue('pmaterial_id', $model->dt[$rowIndex]->material_id);
                        $cmm->setParamValue('psr_pu', $model->dt[$rowIndex]->msr_pu);
                        $cmm->setParamValue('pdisc_pcnt', $model->dt[$rowIndex]->mdisc_pcnt);
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
                throw $ex;
            }
        }
    }

}

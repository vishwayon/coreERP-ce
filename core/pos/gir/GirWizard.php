<?php

namespace app\core\pos\gir;

class GirWizard
    extends \app\cwf\vsla\xmlbo\WizardBase {
    
    public function setData($step, $data, $oldStepData) {
        $this->data=$oldStepData;
        switch ($step) {
            case 'SelectOriginInv':
                $this->setSelectOriginInv($data);
                break;
        }
        parent::setData($step, $data, $oldStepData);
    }
    
    private function setSelectOriginInv($data) {
        if($data->SelectOriginInv->origin_inv_id == '') {
            array_push($this->brokenrules, 'Please enter original invoice # to proceed.');
        } else {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select inv_id From pos.inv_control
                                    Where inv_id=:pinv_id And branch_id={branch_id} And status=5 And doc_type ='PIV'");
            $cmm->addParam('pinv_id', $data->SelectOriginInv->origin_inv_id);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($dt->Rows())!=1) {
                array_push($this->brokenrules, 'Could not find the Original Invoice information or the invoice does not belong to the connected branch.');
            }
        }
        
        $this->data['SelectOriginInv']=array();
        if(count($this->brokenrules)==0) {            
            $this->data['SelectOriginInv']['origin_inv_id'] = $data->SelectOriginInv->origin_inv_id;
        }
    }
}


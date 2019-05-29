<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\prn;
use YaLinqo\Enumerable;

/**
 * Description of PRNEventHandler
 *
 * @author Priyanka
 */
class PRNEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        // Set default stock location
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select stock_location_id, stock_location_name From st.stock_location Where branch_id={branch_id} And is_default_for_branch=true;');
        $dtsl = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dtsl->Rows())==1) {
            $this->bo->default_sl = $dtsl->Rows()[0]; 
        }

        // Create GL temp to view GL Distribution
        \app\core\ac\glDistribution\GLDistributionHelper::CreateGLTemp($this->bo);
            
        if ($this->bo->stock_id == "" or $this->bo->stock_id == "-1") {            
            $this->bo->vat_type_id = $this->bo->vat_type_id = $criteriaparam['formData']['SelectVatType']['vat_type_id'];
            $this->bo->stock_id = "";
            $this->bo->status = 0;
            $this->bo->fc_type_id = 0;
            $this->bo->exch_rate = 1;
        }      
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
    }

}

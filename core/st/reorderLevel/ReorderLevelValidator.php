<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\reorderLevel;

use YaLinqo\Enumerable;

/**
 * Description of Reorder Level
 *
 * @author Valli
 */
class ReorderLevelValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateReorderLevelEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    public function validateBusinessRules() {


        //Max Qty cannot be less than Min Qty
        $RowNo = 0;

        foreach ($this->bo->mat_level->Rows() as $row) {
            $RowNo++;
            if ($row['reorder_level'] < $row['min_qty']) {
                $this->bo->addBRule('Reorder level cannot be less than Min Qty - Row[' . $RowNo . '] - Branch ( '.\app\cwf\vsla\utils\LookupHelper::GetLookupText('../cwf/sys/lookups/Branch.xml', 'branch_name', 'branch_id', $row['branch_id']).' )');
            }
            if ($row['max_qty'] < $row['min_qty']) {
                $this->bo->addBRule('Max Qty cannot be less than Min Qty - Row[' . $RowNo . '] - Branch ( '.\app\cwf\vsla\utils\LookupHelper::GetLookupText('../cwf/sys/lookups/Branch.xml', 'branch_name', 'branch_id', $row['branch_id']).' )');
            }  
            if (($row['max_qty'] == $row['min_qty']) && $row['max_qty'] == 0) {
                $this->bo->addBRule('Max Qty cannot be less than Min Qty - Row[' . $RowNo . '] - Branch ( '.\app\cwf\vsla\utils\LookupHelper::GetLookupText('../cwf/sys/lookups/Branch.xml', 'branch_name', 'branch_id', $row['branch_id']).' )');
            } 
            if ($row['lead_time'] < 0) {
                $this->bo->addBRule('Lead time cannot be in negative - Row[' . $RowNo . '] - Branch ( '.\app\cwf\vsla\utils\LookupHelper::GetLookupText('../cwf/sys/lookups/Branch.xml', 'branch_name', 'branch_id', $row['branch_id']).' )');
            } 
         
        }
        
        $br_list = Enumerable::from($this->bo->mat_level->Rows())->groupBy('$a==>$a["branch_id"]')->toList();

        foreach ($br_list as $itm) {
            if (count($itm) > 1) {
                $this->bo->addBRule('Reorder Level Details : Duplicate Branch ('.\app\cwf\vsla\utils\LookupHelper::GetLookupText('../cwf/sys/lookups/Branch.xml', 'branch_name', 'branch_id', $itm[0]['branch_id']).') not allowed.');    
            }
        }
    }


}

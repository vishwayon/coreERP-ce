<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\materialType;

/**
 * Description of MaterialTypeEventHandler
 *
 * @author Shrishail
 */
class MaterialTypeEventHandler extends\app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        
        if ($this->bo->material_type_id == -1 || $this->bo->material_type_id == '') {
            $this->bo->rof_dec = 3;
        }
    }

}

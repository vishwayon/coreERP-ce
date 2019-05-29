<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\creditNote;

/**
 * Description of CreditNoteEventHandler
 *
 * @author Kaustubh
 */

class CreditNoteEventHandler extends \app\core\ap\bill\BillEventHandler {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
    }
}


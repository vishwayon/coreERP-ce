<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\debitNote;

/**
 * Description of DebitNoteEventHandler
 *
 * @author priyanka
 */
class DebitNoteEventHandler extends \app\core\ar\invoice\InvoiceEventHandler {
        
    public function afterFetch($criteriaparam) { 
        parent::afterFetch($criteriaparam);
        $this->bo->doc_type='DN';
    }
}

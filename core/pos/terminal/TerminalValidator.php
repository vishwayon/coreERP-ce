<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\pos\terminal;

/**
 * Description of TerminalValidator
 *
 * @author Girish Shenoy
 */
class TerminalValidator extends \app\cwf\vsla\xmlbo\ValidatorBase  {
    
    public function validateTerminalEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() {
        // Validate duplicate Terminal Code
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select terminal_code from pos.terminal where terminal_code ilike :pterminal_code '
                             . 'and terminal_id!=:pterminal_id and company_id=:pcompany_id');
        $cmm->addParam('pterminal_code', $this->bo->terminal_code);
        $cmm->addParam('pterminal_id', $this->bo->terminal_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $tc = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($tc->Rows())>0) {
            $this->bo->addBRule('POS Terminal Code already exists. Duplicate terminal code(s) not allowed.');
        }
        
        // Validate duplicate Terminal
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select terminal from pos.terminal where terminal ilike :pterminal '
                             . 'and terminal_id!=:pterminal_id and company_id=:pcompany_id');
        $cmm->addParam('pterminal', $this->bo->terminal);
        $cmm->addParam('pterminal_id', $this->bo->terminal_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('POS Terminal already exists. Duplicate terminal(s) not allowed.');
        }
    }
}

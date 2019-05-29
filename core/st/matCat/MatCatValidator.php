<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\core\st\matCat;

/**
 * Description of MatCatValidator
 *
 * @author priyanka
 */
class MatCatValidator  extends \app\cwf\vsla\xmlbo\ValidatorBase {
    //put your code here
    public function validateMatCatEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRule();
    }
    
    private function validateBusinessRule(){
        
        // Validate duplicate Material
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select mat_cat from st.mat_cat where mat_cat ilike :pmat_cat '
                             . 'and mat_cat_id!=:pmat_cat_id and company_id=:pcompany_id');
        $cmm->addParam('pmat_cat', $this->bo->mat_cat);
        $cmm->addParam('pmat_cat_id', $this->bo->mat_cat_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Stock Category already exists. Duplicate Stock Category not allowed.');
        }
    }
}

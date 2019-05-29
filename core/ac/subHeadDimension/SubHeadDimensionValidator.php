<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\subHeadDimension;

/**
 * Description of SubHeadDimensionValidator
 *
 * @author Shrishail
 */
class SubHeadDimensionValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateSubHeadDimensionEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    private function validateBusinessRules() {

        // Validate duplicate SubHead
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select sub_head_dim from ac.sub_head_dim where sub_head_dim ilike :psub_head_dim '
                . 'and sub_head_dim_id!=:psub_head_dim_id and company_id=:pcompany_id');
        $cmm->addParam('psub_head_dim', $this->bo->sub_head_dim);
        $cmm->addParam('psub_head_dim_id', $this->bo->sub_head_dim_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $this->bo->addBRule('Sub head dimension already exists. Duplicate sub head dimension not allowed.');
        }
//
//        // validate duplicate account
//        for ($i = 0; $i < count($this->bo->sub_head_dim_acc->Rows()); $i++) {
//            for ($j = $i + 1; $j < count($this->bo->sub_head_dim_acc->Rows()); $j++) {
//                if ($this->bo->sub_head_dim_acc->Rows()[$i]['account_id'] == $this->bo->sub_head_dim_acc->Rows()[$j]['account_id']) {
//                    $this->bo->addBRule('Duplicate account(s) not allowed in sub head dimension.');
//                    break;
//                }
//            }
//        }
        
//        foreach ($this->bo->sub_head_dim_acc->Rows() as $row) {
//            $cmm = new \app\cwf\vsla\data\SqlCommand();
//            $cmm->setCommandText('select a.sub_head_dim_id, a.sub_head_dim, c.account_head from ac.sub_head_dim a
//                                    inner join ac.sub_head_dim_acc b on a.sub_head_dim_id = b.sub_head_dim_id
//                                    Inner join ac.account_head c on b.account_id = c.account_id
//                                    where a.sub_head_dim_id != :psub_head_dim_id 
//                                            And b.account_id = :paccount_id');
//            $cmm->addParam('paccount_id', $row['account_id']);
//            $cmm->addParam('psub_head_dim_id', $this->bo->sub_head_dim_id);
//            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
//            if (count($result->Rows()) > 0) {
//                $this->bo->addBRule($result->Rows()[0]['account_head'] .' already associated with Sub Head Dimension '. $result->Rows()[0]['sub_head_dim'].'. Account cannot be associated with multiple Sub Head Dimension.');
//            }
//        }
    }

}

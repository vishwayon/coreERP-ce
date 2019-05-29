<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\segment;

/**
 * Description of SegmentValidator
 *
 * @author Priyanka
 */

class SegmentValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateSegmentEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
       $this->validateBusinessRules();
        
     }
     
    public function validateBusinessRules() {
        // Validate duplicate Segment
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select segment from ar.segment where segment ilike :psegment '
                          . 'and segment_id!=:psegment_id and company_id=:pcompany_id');
        $cmm->addParam('psegment', $this->bo->segment);
        $cmm->addParam('psegment_id', $this->bo->segment_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Segment already exists. Duplicate Segment not allowed.');
        } 
    }
     
    public function validateBeforeDelete() {
        parent::validateBeforeDelete();
    }
}
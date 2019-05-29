<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\skill;
use YaLinqo\Enumerable;

/**
 * Description of SkillValidator
 *
 * @author Valli
 */

class SkillValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateSkillEditForm() 
    {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules()
    {
        // Validate duplicate Skill

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select skill from hr.skill where skill ilike :pskill and skill_id!=:pskill_id');
        $cmm->addParam('pskill', $this->bo->skill);
        $cmm->addParam('pskill_id', $this->bo->skill_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0) {
            $this->bo->addBRule('Skill already exists. Duplicate Skill not allowed.');
        }
    }
}
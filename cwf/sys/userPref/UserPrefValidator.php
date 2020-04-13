<?php

namespace app\cwf\sys\userPref;

class UserPrefValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateUserPrefEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {
        $dg_ids = '';
        foreach ($this->bo->bo_temp->Rows() as $dr) {
            if ($dr['select']) {
                if ($dg_ids == '') {
                    $dg_ids = $dr['bo_id'];
                } else {
                    $dg_ids .= ',' . $dr['bo_id'];
                }
            }
        }

        $this->bo->pref_info->Value()->wf_auto_adv = "{" . $dg_ids . "}";
    }

}

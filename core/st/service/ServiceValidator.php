<?php

namespace app\core\st\service;

/**
 * ServiceValidator
 * @author Girish
 */
class ServiceValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateServiceEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    private function validateBusinessRules() {

        // Validate duplicate Service
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select material_name from st.material where material_name ilike :pmaterial_name '
                . 'and material_id!=:pmaterial_id and company_id=:pcompany_id');
        $cmm->addParam('pmaterial_name', $this->bo->material_name);
        $cmm->addParam('pmaterial_id', $this->bo->material_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $this->bo->addBRule('Stock Item/Service already exists. Duplicate Stock Item/Service not allowed.');
        }

        // Validate duplicate Material Code
        $value = \app\cwf\vsla\utils\SettingsHelper::GetKeyValue('st_MaterialCodeReqd');
        if ($value == '1') {
            if ($this->bo->material_code == '') {
                $this->bo->addBRule('Service Code is required');
            }

            if ($this->bo->material_code <> '') {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('Select material_code from st.material where material_code ilike :pmaterial_code '
                        . 'and material_id!=:pmaterial_id and company_id=:pcompany_id');
                $cmm->addParam('pmaterial_code', $this->bo->material_code);
                $cmm->addParam('pmaterial_id', $this->bo->material_id);
                $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
                $resultCode = \app\cwf\vsla\data\DataConnect::getData($cmm);
                if (count($resultCode->Rows()) > 0) {
                    $this->bo->addBRule('Stock Item/Service code already exists. Duplicate Stock Item/Service code not allowed.');
                }
            }
        }

        // Set the uom_desc to st.uom
        if ($this->bo->uom_desc == '') {
            $this->bo->addBRule('UoM required for Service also.');
        } else if (count($this->bo->uom->Rows()) > 0) {
            foreach($this->bo->uom->Rows() as &$ref_row){                
                // Uom exists therefore, update
                $ref_row['uom_desc'] = $this->bo->uom_desc;
            }
        } else {
            // Uom does not exist. Therefore add new
            $drUoM = $this->bo->uom->NewRow();
            $drUoM['uom_desc'] = $this->bo->uom_desc;
            $drUoM['uom_qty'] = 1;
            $drUoM['is_base'] = true;
            $drUoM['is_su'] = false;
            $drUoM['is_discontinued'] = false;
            $drUoM['uom_type_id'] = 101;
            $this->bo->uom->addRow($drUoM);
            
            // Uom does not exist. Therefore add new
            $drUoM = $this->bo->uom->NewRow();
            $drUoM['uom_desc'] = $this->bo->uom_desc;
            $drUoM['uom_qty'] = 1;
            $drUoM['is_base'] = true;
            $drUoM['is_su'] = false;
            $drUoM['is_discontinued'] = false;
            $drUoM['uom_type_id'] = 104;
            $this->bo->uom->addRow($drUoM);
            
            // Uom does not exist. Therefore add new
            $drUoM = $this->bo->uom->NewRow();
            $drUoM['uom_desc'] = $this->bo->uom_desc;
            $drUoM['uom_qty'] = 1;
            $drUoM['is_base'] = false;
            $drUoM['is_su'] = false;
            $drUoM['is_discontinued'] = false;
            $drUoM['uom_type_id'] = 103;
            $this->bo->uom->addRow($drUoM);
        }

        if($this->bo->inventory_account_id == -1) {
            // Set default account id to 0 as it is optional
            $this->bo->inventory_account_id = 0;
        }
        if ($this->bo->annex_info->Value()->war_info->has_war) {
            if (intval($this->bo->annex_info->Value()->war_info->war_days) <= 0) {
                $this->bo->addBRule('Warranty period (in days) required for Stock Items/Service with warranty');
            }
        }
    }

}

<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\material;

use YaLinqo\Enumerable;

/**
 * Description of MaterialValidator
 *
 * @author Shrishail
 */
class MaterialValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateMaterialEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {
        // Validate duplicate Material
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select material_name from st.material where material_name ilike :pmaterial_name '
                . 'and material_id!=:pmaterial_id and company_id=:pcompany_id');
        $cmm->addParam('pmaterial_name', $this->bo->material_name);
        $cmm->addParam('pmaterial_id', $this->bo->material_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $this->bo->addBRule('Stock Item already exists. Duplicate Stock Item not allowed.');
        }

        // Validate duplicate Material Code
        $value = \app\cwf\vsla\utils\SettingsHelper::GetKeyValue('st_MaterialCodeReqd');
        if ($value == '1') {
            if ($this->bo->material_code == '') {
                $this->bo->addBRule('Stock Item Code is required');
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
                    $this->bo->addBRule('Stock Item code already exists. Duplicate Stock Item code not allowed.');
                }
            }
        }

        // Atleast one Uom required
        if (count($this->bo->uom->Rows()) == 0) {
            $this->bo->addBRule('Each Stock Item should have at least one UoM.');
        } else if (count($this->bo->uom->Rows()) > 0) {
            // Should have only one base unit
            $checkCount = 0;
            foreach ($this->bo->uom->Rows() as $row) {
                if ($row['is_base'] == true) {
                    $checkCount++;
                }
            }
            if ($checkCount == 0) {
                $this->bo->addBRule('Each Stock Item should have one base unit');
            } else {
                $rowcnt = 0;
                foreach ($this->bo->uom->Rows() as $row) {
                    $rowcnt = $rowcnt + 1;
                    if ($row['is_base'] == true) {
                        if ($row['uom_qty'] <> 1) {
                            $this->bo->addBRule('UoM - Row[' . $rowcnt . '] : For base unit, UoM quantity should always be 1.');
                        }
                        if ($row['is_discontinued'] == true) {
                            $this->bo->addBRule('UoM - Row[' . $rowcnt . '] : Base unit cannot be discontinued.');
                        }
                    }
                }
            }
            // Should have only one Sales unit
            $su_cnt = 0;
            foreach ($this->bo->uom->Rows() as $row) {
                if ($row['is_su'] == true) {
                    $su_cnt++;
                }
            }

            if ($su_cnt == 0) {
//                $this->bo->addBRule('Each Stock should have one Sales Unit');
            } else if ($su_cnt > 1) {
                $this->bo->addBRule('Only one item is allowed as Sales Unit.');
            }

            // Validate uom_desc should not be same for uom_type
            $uom_type_lst = Enumerable::from($this->bo->uom->Rows())->groupBy('$a==>$a["uom_type_id"]')->ToArray();
            foreach ($uom_type_lst as $item) {
                $uom_lst = Enumerable::from($item)->groupBy('$a==>$a["uom_desc"]')->ToArray();
                foreach ($uom_lst as $itm) {
                    if (count($itm) > 1) {
                        $this->bo->addBRule('UoM - Duplicate UoM Description not allowed for same Type.');
                    }
                }
            }
        }

        // Opening Balance Can be Zero with Zero Value. It would be determine the value of issues accordingly

        if ($this->bo->annex_info->Value()->sale_price->price_type == "WAC") {
            if ($this->bo->annex_info->Value()->sale_price->wac_calc->markup_pcnt == 0 && $this->bo->annex_info->Value()->sale_price->wac_calc->markup_pu == 0) {
                $this->bo->addBRule('Sale Price - Weighted Avg. Cost Markup (%) or Weighted Avg. Cost Markup Per Unit is required.');
            }
            if ($this->bo->annex_info->Value()->sale_price->wac_calc->markup_pcnt != 0 && $this->bo->annex_info->Value()->sale_price->wac_calc->markup_pu != 0) {
                $this->bo->addBRule('Sale Price - Both Weighted Avg. Cost Markup (%) and Weighted Avg. Cost Markup Per Unit cannot be greater than zero.');
            }
        }
        if ($this->bo->annex_info->Value()->sale_price->price_type == "LP") {
            if ($this->bo->annex_info->Value()->sale_price->lp_calc->markup_pcnt == 0 && $this->bo->annex_info->Value()->sale_price->lp_calc->markup_pu == 0) {
                $this->bo->addBRule('Sale Price - Latest Purchase Cost Markup (%) or Latest Purchase Cost Markup Per Unit is required.');
            }
            if ($this->bo->annex_info->Value()->sale_price->lp_calc->markup_pcnt != 0 && $this->bo->annex_info->Value()->sale_price->lp_calc->markup_pu != 0) {
                $this->bo->addBRule('Sale Price - Both Latest Purchase Cost Markup (%) and Latest Purchase Cost Markup Per Unit cannot be greater than zero.');
            }
        }
        if ($this->bo->annex_info->Value()->war_info->has_war) {
            if (intval($this->bo->annex_info->Value()->war_info->war_days) <= 0) {
                $this->bo->addBRule('Warranty period (in days) required for Stock Items with warranty');
            }
        }

        // If excess qty allowed in ST excess% is required
        if ($this->bo->annex_info->Value()->st_allow_excess) {
            if (intval($this->bo->annex_info->Value()->st_excess_pcnt) <= 0) {
                $this->bo->addBRule('Excess % required for Stock Items');
            }
        }
    }

}

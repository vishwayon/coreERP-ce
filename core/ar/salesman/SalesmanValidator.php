<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\salesman;

/**
 * Description of salesmanValidator
 *
 * @author Priyanka
 */
class SalesmanValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateSalesmanEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {

        // Validate duplicate Material Type
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select salesman_name from ar.salesman where salesman_name ilike :psalesman_name '
                . 'and salesman_id!=:psalesman_id and company_id=:pcompany_id');
        $cmm->addParam('psalesman_name', $this->bo->salesman_name);
        $cmm->addParam('psalesman_id', $this->bo->salesman_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $this->bo->addBRule('Salesman already exists. Duplicate Salesman not allowed.');
        }

        if ($this->bo->user_id != -1) {
            // Validate duplicate User
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select salesman_name from ar.salesman where user_id=:puser_id '
                    . 'and salesman_id!=:psalesman_id and company_id=:pcompany_id');
            $cmm->addParam('puser_id', $this->bo->user_id);
            $cmm->addParam('psalesman_id', $this->bo->salesman_id);
            $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($result->Rows()) > 0) {
                $this->bo->addBRule('User already associated with Salesman ' . $result->Rows()[0]['salesman_name'] . '.');
            }
        }

        // Validate address and Parent for Independent Representative
        if ($this->bo->salesman_type == 1) {
            if ($this->bo->parent_salesman_id == -1) {
                $this->bo->addBRule('Parent Salesman is required.');
            }

            foreach ($this->bo->salesman_address_tran->Rows() as &$ref_row) {
                $ref_row['contact_person'] = $this->bo->salesman_name;
            }
            foreach ($this->bo->salesman_address_tran->Rows() as $row) {
                if ($row['contact_person'] == '') {
                    $this->bo->addBRule('Contact Person is required.');
                }
                if ($row['mobile'] == '') {
                    $this->bo->addBRule('Mobile No is required.');
                }
                if ($row['address'] == '') {
                    $this->bo->addBRule('Address is required.');
                }
            }
        }
    }

}

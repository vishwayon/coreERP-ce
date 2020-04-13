<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\docGroup;

use YaLinqo\Enumerable;

class DocGroupValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateDocGroupEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    private function validateBusinessRules() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select doc_group from sys.doc_group where doc_group ilike :pdoc_group and doc_group_id!=:pdoc_group_id');
        $cmm->addParam('pdoc_group', $this->bo->doc_group);
        $cmm->addParam('pdoc_group_id', $this->bo->doc_group_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $this->bo->addBRule('Doc Group already exists. Duplicate doc group not allowed.');
        }

        $doc_list = Enumerable::from($this->bo->doc_group_tran->Rows())->where('$a==>$a["bo_id"]!=\'\'')->groupBy('$a==>$a["bo_id"]')->toList();

        foreach ($doc_list as $itm) {
            if (count($itm) > 1) {
                $this->bo->addBRule('Duplicate Document Type (' . \app\cwf\vsla\utils\LookupHelper::GetLookupText('../cwf/sys/lookups/BOlist.xml', 'menu_text', 'bo_id', $itm[0]['bo_id']) . ') not allowed.');
            }
        }

        // validate if selected document already included in other doc_groups        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select distinct doc_group 
                                from sys.doc_group a 
                                inner join sys.doc_group_tran b on a.doc_group_id = b.doc_group_id
                                Where b.bo_id = :pbo_id
                                                And a.doc_group_id != :pdoc_group_id');
        $cmm->addParam('pbo_id', '');
        $cmm->addParam('pdoc_group_id', $this->bo->doc_group_id);
        $row_no = 0;
        foreach ($this->bo->doc_group_tran->Rows() as $dr) {
            $row_no++;
            if ($dr['bo_id'] == '') {                
                $this->bo->addBRule('Document(s) - Row['.$row_no  . '] is not valid document type.');
            } else {
                $cmm->setParamValue('pbo_id', $dr['bo_id']);
                $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
                if (count($result->Rows()) > 0) {
                    $this->bo->addBRule('Document Type ' . \app\cwf\vsla\utils\LookupHelper::GetLookupText('../cwf/sys/lookups/BoWithPath.xml', 'menu_text', 'bo_id', $dr['bo_id']) . ' already included in document group ' . $result->Rows()[0]['doc_group']);
                }
            }
        }
    }
}

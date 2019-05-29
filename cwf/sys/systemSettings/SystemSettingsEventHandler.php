<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\systemSettings;

/**
 * Description of SystemSettingsEventHandler
 *
 * @author Priyanka
 */
class SystemSettingsEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.settings');
        $this->bo->dtSettings = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $this->bo->company_id = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
        foreach ($this->bo->dtSettings->Rows() as $rowbr) {
            if ($rowbr['key'] == 'print_allow_post') {
                if ($rowbr['value'] > 0) {
                    $this->bo->print_allow_post_option = 1;
                    $this->bo->no_of_prints_post = $rowbr['value'];
                } else {
                    $this->bo->print_allow_post_option = $rowbr['value'];
                    $this->bo->no_of_prints_post = 0;
                }
            } else if ($rowbr['key'] == 'print_allow_unpost') {
                if ($rowbr['value'] > 0) {
                    $this->bo->print_allow_unpost_option = 1;
                    $this->bo->no_of_prints_unpost = $rowbr['value'];
                } else {
                    $this->bo->print_allow_unpost_option = $rowbr['value'];
                    $this->bo->no_of_prints_unpost = 0;
                }
            } else if ($rowbr['key'] == 'confirm_post') {
                if (strtolower($rowbr['value']) === 'true' || ($rowbr['value']) === '1') {
                    $this->bo->confirm_post = TRUE;
                } else {
                    $this->bo->confirm_post = FALSE;
                }
            } else if ($rowbr['key'] == 'ac_payc_limit') {
                if ($rowbr['value'] == "0") {
                    $this->bo[$rowbr['key']] = 0;
                }
                else{
                    $this->bo[$rowbr['key']] = $rowbr['value'];
                }
                    
            } else {
                $this->bo[$rowbr['key']] = $rowbr['value'];
                if ($rowbr['value'] == "0") {
                    $this->bo[$rowbr['key']] = false;
                }
                if ($rowbr['value'] == "1") {
                    $this->bo[$rowbr['key']] = true;
                }
            }
        }
    }

    public function onFetch($criteriaparam, $tablename) {
        parent::onFetch($criteriaparam, $tablename);
    }

    public function onSave($cn, $tablename) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("update sys.settings set value = :pvalue where key = :pkey;");
        if ($this->bo->print_allow_post_option == 1) {
            $this->bo->print_allow_post = $this->bo->no_of_prints_post;
        } else {
            $this->bo->print_allow_post = $this->bo->print_allow_post_option;
        }
        if ($this->bo->print_allow_unpost_option == 1) {
            $this->bo->print_allow_unpost = $this->bo->no_of_prints_unpost;
        } else {
            $this->bo->print_allow_unpost = $this->bo->print_allow_unpost_option;
        }
        foreach ($this->bo->dtSettings->Rows() as $rowbr) {
            if (isset($this->bo[$rowbr['key']])) {
                $cmm->addParam('pvalue', $this->bo[$rowbr['key']]);
            }
            $cmm->addParam('pkey', $rowbr['key']);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
        }
    }

    public function afterSave($cn) {
        parent::afterSave($cn);
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
    }

}

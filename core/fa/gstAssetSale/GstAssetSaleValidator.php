<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\gstAssetSale;

use YaLinqo\Enumerable;

/**
 * @author Priyanka
 */
class GstAssetSaleValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateGstAssetSaleEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    public function validateBusinessRules() {

        // validate cheque date if PDC true
        if ($this->bo->is_pdc) {
            if (strtotime($this->bo->cheque_date) <= strtotime($this->bo->doc_date)) {
                $this->bo->addBRule('Cheque date must be later than document date.');
            }
        }
        // validate gst state code with gstin
        $state_code = substr(\app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/tx/lookups/GstState.xml', 'gst_state_with_code', 'gst_state_id', $this->bo->annex_info->Value()->gst_output_info->customer_state_id), 0, 2);

        if (substr($this->bo->annex_info->Value()->gst_output_info->customer_gstin, 0, 2) != $state_code && $state_code != "98") {
            $this->bo->addBRule('GSTIN does not belong to GST State.');
        }

        if ($this->bo->annex_info->Value()->gst_output_info->customer_gstin != $state_code) {
            if (!preg_match("/^[0-9]{2}[a-zA-Z]{5}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[Z]{1}[0-9a-zA-Z]{1}$/", $this->bo->annex_info->Value()->gst_output_info->customer_gstin)) {
                $this->bo->addBRule('Invalid GSTIN.');
            }
        }


        $currency = '';
        $subCurrency = '';
        $currency_system = '';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.branch where branch_id=:pbranch_id');
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $dtbr = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtbr->Rows()) > 0) {
            $currency = $dtbr->Rows()[0]['currency'];
            $subCurrency = $dtbr->Rows()[0]['sub_currency'];
            $currency_system = $dtbr->Rows()[0]['currency_system'];
        }

        $val = sprintf("%." . \app\cwf\vsla\Math::$amtScale . "f", $this->bo->debit_amt);
        $this->bo->amt_in_words = \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);


        // If Depreciation calculated for particular period is not authorised then don't allow to make Asset Sale for this period and branch
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select count(*) as count from fa.ad_control where branch_id=:pbranch_id and :pdate <=(select max(dep_date_to) as max_dep_date from fa.ad_control where branch_id=:pbranch_id)');
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('pdate', $this->bo->doc_date);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if ($result->Rows()[0]['count'] > 0) {
                $this->bo->addBRule('Cannot make sale because depreciation for this period is already calculated.');
            }
        }

        if (count($this->bo->as_tran->Rows()) == 0) {
            $this->bo->addBRule('Sale Info should have atleast one record.');
        }

//        foreach ($this->bo->as_tran->Rows() as $row) {
//            $lastDepDate = $this->setLastDepDate($row['asset_item_id']);
//            $classinst = new \app\core\fa\assetDep\worker\AssetDepTemp($lastDepDate, $this->bo->doc_date, $this->bo->asset_dep_ledger);
//
//            $adWorker = new \app\core\fa\assetDep\worker\AssetDepWorker(\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'), \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'), \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
//            $adWorker->CalculateDepreciation($classinst, $row['asset_item_id']);
//        }
        // check account type for selected account.
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select account_type_id from ac.account_head where account_id=:paccount_id');
        $cmm->addParam('paccount_id', $this->bo->customer_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $acc_type_id = $dt->Rows()[0]['account_type_id'];

            if ($this->bo->en_sales_type == 0) {
                if ($acc_type_id != 2) {
                    $this->bo->addBRule('Please select Cash account.');
                }
            } else if ($this->bo->en_sales_type == 1) {
                if ($acc_type_id != 1) {
                    $this->bo->addBRule('Please select Bank account.');
                }
            } else if ($this->bo->en_sales_type == 2) {
                if ($acc_type_id != 7) {
                    $this->bo->addBRule('Please select Credit account.');
                }
            } else if ($this->bo->en_sales_type == 3) {
                if ($acc_type_id == 0 || $acc_type_id == 1 || $acc_type_id == 2 || $acc_type_id == 7 || $acc_type_id == 12 || $acc_type_id == 23 || $acc_type_id == 24 || $acc_type_id == 21 || $acc_type_id == 22 || $acc_type_id == 18 || $acc_type_id == 38) {
                    $this->bo->addBRule('Please select Journal account.');
                }
            }
        }
        
        $this->calDep();
    }

    private function setLastDepDate($asset_item_id) {
        $last_dep_date = NULL;
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("SELECT max(dep_date_to) + integer '1' as max_date  FROM fa.asset_dep_ledger where asset_item_id=:passet_item_id and voucher_id<>:pvoucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->as_id);
        $cmm->addParam('passet_item_id', $asset_item_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if ($result->Rows()[0]['max_date'] == '') {
                return $this->getPurchaseDate($asset_item_id);
            } else {
                return $result->Rows()[0]['max_date'];
            }
        }
        return $last_dep_date;
    }

    private function getPurchaseDate($asset_item_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("SELECT purchase_date  FROM fa.asset_item where asset_item_id=:passet_item_id");
        $cmm->addParam('passet_item_id', $asset_item_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if ($result->Rows()[0]['purchase_date'] == NULL) {
                throw new \Exception('Purchase Date not resolved for Asset Item: ' . (string) $asset_item_id);
            } else {
                return $result->Rows()[0]['purchase_date'];
            }
        }
    }

    public function validateBeforeUnpost() {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select count(*) as count from fa.ad_control where branch_id=:pbranch_id and :pdate <=(select max(dep_date_to) as max_dep_date from fa.ad_control where branch_id=:pbranch_id)');
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('pdate', $this->bo->doc_date);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if ($result->Rows()[0]['count'] > 0) {
                $this->bo->addBRule('Cannot unpost sale because depreciation for this period is already calculated.');
            }
        }
    }

    public function validateBeforePost() {
        // Compulsory method named. No implementation currently required
    }

    public function calDep() {     
        
        $rowcount = count($this->bo->asset_dep_ledger->Rows());
        for ($i = 0; $i <= $rowcount; $i++) {
            $this->bo->asset_dep_ledger->removeRow(0);
        }
        foreach ($this->bo->as_tran->Rows() as &$refrow) {
            $lastDepDate = $this->setLastDepDate($refrow['asset_item_id']);
            
            $refrow['last_dep_date'] = $lastDepDate;
            $classinst = new \app\core\fa\assetDep\worker\AssetDepTemp($refrow['last_dep_date'], $this->bo->doc_date, $this->bo->asset_dep_ledger);

            $adWorker = new \app\core\fa\assetDep\worker\AssetDepWorker(\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'), \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'), \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'));
            $adWorker->CalculateDepreciation($classinst, $refrow['asset_item_id'], $this->bo->as_id);
        }        
        
        $rowcount = count($this->bo->as_book_tran->Rows());
        for ($i = 0; $i <= $rowcount; $i++) {
            $this->bo->as_book_tran->removeRow(0);
        }
        foreach ($this->bo->asset_dep_ledger->Rows() as &$refadrow) {
            // Add row in asset book tran
            $newASBookRow = $this->bo->as_book_tran->newRow();
            $newASBookRow['asset_book_id'] = $refadrow['asset_book_id'];
            $newASBookRow['asset_class_id'] = $refadrow['asset_class_id'];
            $newASBookRow['asset_item_id'] = $refadrow['asset_item_id'];
            $newASBookRow['asset_name'] = $refadrow['asset_name'];
            $newASBookRow['asset_class'] = $refadrow['asset_class'];
            $newASBookRow['asset_book'] = $refadrow['asset_book'];
            $newASBookRow['dep_amt'] = $refadrow['dep_amt'];
            $newASBookRow['dep_date_from'] = $lastDepDate;
            $this->bo->as_book_tran->AddRow($newASBookRow);
            $refadrow['doc_date'] = $this->bo->doc_date;
        }

        //calculate Profit/Loss on sale
        foreach ($this->bo->as_book_tran->Rows() as &$refbook_row) {
            $refbook_row['acc_dep_amt'] = $this->getAccDepAmt($refbook_row['asset_item_id']);

            $sale_amt = round(Enumerable::from($this->bo->as_tran->Rows())->where('$a==>$a["asset_item_id"]==' . $refbook_row['asset_item_id'])->sum('$a==>$a["credit_amt"]'), \app\cwf\vsla\Math::$amtScale);
            $purchase_amt = round(Enumerable::from($this->bo->as_tran->Rows())->where('$a==>$a["asset_item_id"]==' . $refbook_row['asset_item_id'])->sum('$a==>$a["purchase_amt"]'), \app\cwf\vsla\Math::$amtScale);
            $refbook_row['profit_loss_amt'] = $sale_amt - ($purchase_amt - ($refbook_row['acc_dep_amt'] + $refbook_row['dep_amt']));
        }
    }

    private function getAccDepAmt($asset_item_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("SELECT coalesce(sum(dep_amt), 0) as acc_dep_amt FROM fa.asset_dep_ledger where asset_item_id=:passet_item_id and voucher_id<>:pvoucher_id");
        $cmm->addParam('passet_item_id', $asset_item_id);
        $cmm->addParam('pvoucher_id', $this->bo->as_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            return $result->Rows()[0]['acc_dep_amt'];
        }
    }

}

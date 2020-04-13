<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\company;

/**
 * Description of TDSPersonTypeEventHandler
 *
 * @author Ravindra
 */
class CompanyEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        $this->bo->branch_code = "";
        $this->bo->branch_name = "";
        $this->bo->branch_description = "";
        $this->bo->currency = "Rupees";
        $this->bo->sub_currency = "Paise";
        $this->bo->currency_displayed = "INR";
        $this->bo->currency_system = 1;
        $this->bo->branch_date_format = "dd/mm/yyyy";
        $this->bo->server_message = "";
        $this->bo->address_id = -1;
        $this->bo->finyear_code = "";
        $this->bo->fin_year_begin = date("Y-m-d", time());
        $this->bo->fin_year_end = date("Y-m-d", time());
        $this->bo->br_gst_state_id = -1;
        $this->bo->br_gstin = '';

        if ($this->bo->company_id == -1) {
            $this->bo->database = "";
            $this->bo->company_logo = "/cwf/vsla/assets/coreerp_logo.png";
            $this->bo->isnew = true;
        } else {
            $this->bo->isnew = false;
            // Fetch HO Branch Details
            // Set these session variables only to update branch defaults.
            // Do not persist user info to database
            \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->setSessionVariable('company_id', $this->bo->company_id);
            \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->setSessionVariable('companyDB', $this->bo->database);
            \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->setSessionVariable('company_short_name', $this->bo->company_short_name);

            $cnComp = \app\cwf\vsla\data\DataConnect::getCn(\app\cwf\vsla\data\DataConnect::COMPANY_DB);
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("select branch_code, currency, sub_currency, currency_displayed, currency_system, date_format from sys.branch
                                    where company_id=:pcompany_id
                                        And branch_is_ho = 'true'");
            $cmm->addParam('pcompany_id', $this->bo->company_id);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::COMPANY_DB, $cnComp);
            if (count($dt->Rows()) > 0) {
                $this->bo->branch_code = $dt->Rows()[0]['branch_code'];
                $this->bo->currency = $dt->Rows()[0]['currency'];
                $this->bo->sub_currency = $dt->Rows()[0]['sub_currency'];
                $this->bo->currency_displayed = $dt->Rows()[0]['currency_displayed'];
                $this->bo->currency_system = $dt->Rows()[0]['currency_system'];
                $this->bo->branch_date_format = $dt->Rows()[0]['date_format'];
            }
        }
    }

    public function afterSave($cn) {
        parent::afterSave($cn);
        if (!$this->bo->isnew) {
            // Update defaults in Branch Table
            // Set these session variables only to update branch defaults.
            // Do not persist user info to database
            \app\cwf\vsla\data\DataConnect::clearCompanyDB(); // required to force reset connection
            \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->setSessionVariable('company_id', $this->bo->company_id);
            \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->setSessionVariable('companyDB', $this->bo->database);
            \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->setSessionVariable('company_short_name', $this->bo->company_short_name);

            $cnComp = \app\cwf\vsla\data\DataConnect::getCn(\app\cwf\vsla\data\DataConnect::COMPANY_DB);
            $cnComp->beginTransaction();
            try {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('update sys.branch
                                        set currency=:pcurrency,
                                            sub_currency=:psub_currency,
                                            currency_displayed=:pcurrency_displayed,
                                            currency_system=:pcurrency_system,
                                            date_format=:pbranch_date_format
                                        where company_id=:pcompany_id');
                $cmm->addParam('pcurrency', $this->bo->currency);
                $cmm->addParam('psub_currency', $this->bo->sub_currency);
                $cmm->addParam('pcurrency_displayed', $this->bo->currency_displayed);
                $cmm->addParam('pcurrency_system', $this->bo->currency_system);
                $cmm->addParam('pbranch_date_format', $this->bo->branch_date_format);
                $cmm->addParam('pcompany_id', $this->bo->company_id);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cnComp);

                // Call the Tsql modifier to modify certain scripts for efficiency
                \app\cwf\vsla\utils\TsqlModifier::modifyFunctions(['user_time_zone' => $this->bo->user_time_zone, 'date_format' => $this->bo->branch_date_format], $cnComp);

                $cnComp->commit();
            } catch (Exception $ex) {
                if (isset($cnComp)) {
                    $cnComp->rollBack();
                }
                throw $ex;
            } finally {
                $cnComp = null;
            }
        }
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
        if ($this->bo->isnew) {
            // Create company db
            $outstream = fopen('php://memory', 'r+');

            fwrite($outstream, 'Company entry point created' . "\n");
            $companyInfo = array();
            $companyInfo['company_id'] = $this->bo->company_id;

            $maindbname = \app\cwf\vsla\data\DataConnect::getMainDB();
            $maindbname = str_replace('main_', '', $maindbname);
            $maindbname = str_replace('main', '', $maindbname);
            if ($maindbname == '') {
                $companyInfo['database'] = "db_" . (string) $this->bo->company_id;
            } else {
                $companyInfo['database'] = $maindbname . "_db_" . (string) $this->bo->company_id;
            }


            try {
                // The exceptions are shown to user as server messages. Therefore this exceptional code is written inside try catch.

                \app\cwf\console\installer\workers\DbCreator::StartCompanyCreation($companyInfo, $outstream);
                // Set these session variables only to complete branch and financial year.
                // Do not persist user info to database
                \app\cwf\vsla\data\DataConnect::clearCompanyDB(); // required to force reset connection
                \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->setSessionVariable('company_id', $this->bo->company_id);
                \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->setSessionVariable('companyDB', $companyInfo['database']);
                \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->setSessionVariable('company_short_name', $this->bo->company_short_name);


                fwrite($outstream, 'Proceeding to create branch entry point' . "\n");
                // create branch in company db    
                $branchinparam = array();
                $branchinparam['branch_id'] = -1;
                // Create instance of Branch BO
                $branchbopath = '../cwf/sys/branch/Branch.xml';
                $branchBo = new \app\cwf\vsla\xmlbo\XboBuilder($branchbopath);
                $branchboInst = $branchBo->buildBO($branchinparam);

                // Take json encode image of BOPropertyBag for Audit Trail
                $boiAT = json_encode($branchboInst->BOPropertyBag(), JSON_HEX_APOS);

                $branchboInst->branch_id = -1;
                $branchboInst->company_id = $this->bo->company_id;
                $branchboInst->branch_is_ho = true;
                $branchboInst->company_code = $this->bo->company_code;
                $branchboInst->branch_code = $this->bo->branch_code;
                $branchboInst->branch_name = $this->bo->branch_name;
                $branchboInst->branch_description = $this->bo->branch_description;
                $branchboInst->branch_address = $this->bo->company_address;
                $branchboInst->gst_state_id = $this->bo->br_gst_state_id;
                $branchboInst->gstin = $this->bo->br_gstin;
                $branchboInst->currency = $this->bo->currency;
                $branchboInst->sub_currency = $this->bo->sub_currency;
                $branchboInst->currency_displayed = $this->bo->currency_displayed;
                $branchboInst->number_format = '';
                $branchboInst->currency_system = $this->bo->currency_system;
                $branchboInst->date_format = $this->bo->branch_date_format;
                $branchboInst->has_access_rights = true;
                $branchboInst->has_work_flow = true;
                $branchboInst->company_group_id = -1;

                foreach($branchboInst->branch_tax_info->Rows() as &$br_row){
                    if($br_row['tax_info_type_id'] == ($this->bo->company_id * 1000000) + 7){
                        $br_row['branch_tax_info_desc'] = $this->bo->br_gstin;
                    }
                }
                
//                $branchboInst->branch_address_tran=$this->bo->branch_address_tran;

                $branchBo->saveBO($branchboInst, null);

                // Create Audit Trail entry of BO Image
                $branchBo->CreateLogEntry($branchboInst, $branchBo->logAction, $boiAT);

                fwrite($outstream, 'Branch entry point created successfully' . "\n");

                fwrite($outstream, 'Proceeding to create finyear entry point' . "\n");
                // create finyear in company db    
                $finyearinparam = array();
                $finyearinparam['finyear_id'] = -1;
                // Create instance of Finyear BO
                $finyearBo = new \app\cwf\vsla\xmlbo\XboBuilder('../cwf/sys/financialyear/FinancialYear.xml');
                $finyearboInst = $finyearBo->buildBO($finyearinparam);


                // Take json encode image of BOPropertyBag for Audit Trail
                $boiFYAT = json_encode($finyearboInst->BOPropertyBag(), JSON_HEX_APOS);


                $finyearboInst->finyear_id = -1;
                $finyearboInst->finyear_code = $this->bo->finyear_code;
                $finyearboInst->company_id = $this->bo->company_id;
                $finyearboInst->year_begin = $this->bo->fin_year_begin;
                $finyearboInst->year_end = $this->bo->fin_year_end;
                $finyearboInst->year_close = false;
                $finyearboInst->is_default_year = true;

                $finyearBo->saveBO($finyearboInst, null);

                // Create Audit Trail entry of BO Image
                $finyearBo->CreateLogEntry($finyearboInst, $finyearBo->logAction, $boiFYAT);

                fwrite($outstream, 'Finyear entry point created successfully' . "\n");

                fwrite($outstream, 'Updating database field in sys.company' . "\n");
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('update sys.company
                                        set database=:pdatabase
                                        where company_id=:pcompany_id');
                $cmm->addParam('pcompany_id', $this->bo->company_id);
                $cmm->addParam('pdatabase', $companyInfo['database']);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, null, \app\cwf\vsla\data\DataConnect::MAIN_DB);
                $this->bo->database = $companyInfo['database'];

                fwrite($outstream, 'Updated database field in sys.company' . "\n");

                // Call the Tsql modifier to modify certain scripts for efficiency
                \app\cwf\vsla\utils\TsqlModifier::modifyFunctions(['user_time_zone' => $this->bo->user_time_zone, 'date_format' => $this->bo->branch_date_format], null);
            } catch (\Exception $ex) {
                fwrite($outstream, $ex->getMessage() . "\n");
                rewind($outstream);
                $msg = stream_get_contents($outstream);
                \yii::error($msg);
            }
            rewind($outstream);
            $this->bo->server_message = stream_get_contents($outstream);
        }
    }

}

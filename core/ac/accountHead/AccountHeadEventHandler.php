<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\accountHead;

/**
 * Description of AccountHeadEventHandler
 *
 * @author Priyanka
 */
class AccountHeadEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        $this->bo->isnew = FALSE;
        if ($this->bo->account_id == -1) {
            $this->bo->isnew = true;
        }


        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select branch_name, branch_id from sys.branch order by branch_name');
        $dtbranch = \app\cwf\vsla\data\DataConnect::getData($cmm);

        if (count($this->bo->acc_bal_tran->Rows()) == 0) {
            $this->bo->acc_bal_tran->getColumn("branch_id")->default = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
            $this->bo->setTranColDefault('acc_bal_tran', 'branch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));

            $this->bo->acc_bal_tran->getColumn("company_id")->default = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
            $this->bo->setTranColDefault('acc_bal_tran', 'company_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));

            $finyear = \app\cwf\vsla\utils\SettingsHelper::GetKeyValue('ac_start_finyear');
            if ($finyear == '') {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('select * from sys.finyear 
                                    where year_begin = (select min(year_begin) from sys.finyear )');
                $dtfinyear = \app\cwf\vsla\data\DataConnect::getData($cmm);
                if (count($dtfinyear->Rows()) > 0) {
                    $finyear = $dtfinyear->Rows()[0]['finyear_code'];
                }
            }
            foreach ($dtbranch->Rows() as $rowbr) {
                $newRow = $this->bo->acc_bal_tran->NewRow();
                $newRow['account_balance_id'] = '';
                $newRow['finyear'] = $finyear;
                $newRow['account_id'] = -1;
                $newRow['company_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
                $newRow['branch_id'] = $rowbr['branch_id'];
                $newRow['branch_name'] = $rowbr['branch_name'];
                $newRow['debit_balance'] = 0;
                $newRow['credit_balance'] = 0;
                //$newRow['last_updated'] = '';                            
                $this->bo->acc_bal_tran->AddRow($newRow);
            }
        } else {
            foreach ($this->bo->acc_bal_tran->Rows() as &$refrow) {
                foreach ($dtbranch->Rows() as $rowbr) {
                    if ($refrow['branch_id'] == $rowbr['branch_id']) {
                        $refrow['branch_name'] = $rowbr['branch_name'];
                    }
                }
            }
        }

        // Fill Account Head Hidden tran

        $this->bo->acc_head_hidden_temp = new \app\cwf\vsla\data\DataTable();
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $scale = 0;
        $isUnique = false;
        $this->bo->acc_head_hidden_temp->addColumn('branch_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('varchar');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $this->bo->acc_head_hidden_temp->addColumn('branch_name', $phpType, $default, 100, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('bool');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $this->bo->acc_head_hidden_temp->addColumn('is_hidden', $phpType, $default, 0, $scale, $isUnique);

        foreach ($dtbranch->Rows() as $rowbr) {
            $newRow = $this->bo->acc_head_hidden_temp->NewRow();
            $newRow['branch_id'] = $rowbr['branch_id'];
            $newRow['branch_name'] = $rowbr['branch_name'];
            $newRow['is_hidden'] = True;
            foreach ($this->bo->acc_head_hidden->Rows() as $tranrow) {
                if ($rowbr['branch_id'] == $tranrow['branch_id']) {
                    $newRow['is_hidden'] = False;
                    break;
                }
            }
            $this->bo->acc_head_hidden_temp->AddRow($newRow);
        }
    }

    public function onFetch($criteriaparam, $tablename) {
        parent::onFetch($criteriaparam, $tablename);
        if ($tablename == 'ac.account_balance') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select * from ac.fn_get_account_balance(:paccount_id)');
            $cmm->addParam('paccount_id', $this->bo->account_id);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

            foreach ($dt->Rows() as $rowbr) {
                $newRow = $this->bo->acc_bal_tran->NewRow();
                $newRow['account_balance_id'] = $rowbr['account_balance_id'];
                $newRow['finyear'] = $rowbr['finyear'];
                $newRow['account_id'] = $rowbr['account_id'];
                $newRow['company_id'] = $rowbr['company_id'];
                $newRow['branch_id'] = $rowbr['branch_id'];
                $newRow['debit_balance'] = $rowbr['debit_balance'];
                $newRow['credit_balance'] = $rowbr['credit_balance'];
                $newRow['last_updated'] = $rowbr['last_updated'];
                $this->bo->acc_bal_tran->AddRow($newRow);
            }
        }
    }

    public function onSave($cn, $tablename) {
        parent::onSave($cn, $tablename);
        if ($this->bo->isnew) {
            // Insert records in account balance for new financial year
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("select * from ac.sp_account_balance_add(:pfinyear, :pcompany_id, :pbranch_id, :paccount_id, :pis_account)");
            $cmm->addParam('pfinyear', $this->bo->finyear_code);
            $cmm->addParam('pcompany_id', $this->bo->company_id);
            $cmm->addParam('pbranch_id', 0);
            $cmm->addParam('pis_account', true);
            $cmm->addParam('paccount_id', $this->bo->account_id);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
        }

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Update ac.account_balance "
                . "             set debit_balance=:pdebit_balance, "
                . "                 credit_balance=:pcredit_balance "
                . "             where account_balance_id = :paccount_balance_id "
                . "                 and account_id = :paccount_id");
        $cmm->addParam('paccount_balance_id', '');
        $cmm->addParam('pdebit_balance', 0);
        $cmm->addParam('pcredit_balance', 0);
        $cmm->addParam('paccount_id', $this->bo->account_id);
        foreach ($this->bo->acc_bal_tran->Rows() as $rowbr) {
            $cmm->setParamValue('paccount_balance_id', $this->bo->account_id . ':' . $rowbr['branch_id'] . ':' . $rowbr['finyear']);
            $cmm->setParamValue('pdebit_balance', $rowbr['debit_balance']);
            $cmm->setParamValue('pcredit_balance', $rowbr['credit_balance']);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
        }
    }

    public function afterSave($cn) {
        parent::afterSave($cn);
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
        $this->bo->isnew = FALSE;
    }

}

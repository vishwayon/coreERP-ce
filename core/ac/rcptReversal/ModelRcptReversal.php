<?php

namespace app\core\ac\rcptReversal;

use app\cwf\vsla\data\DataConnect;
use app\cwf\vsla\security\SessionManager;

class ModelRcptReversal {

    public $filters;
    public $brokenrules = array();
    public $bank_acc_id;
    public $find_vch_id;
    public $dtVch = [];
    public $dtTran = [];
    public $doc_id = '';
    public $doc_date = '';
    public $bank_acc = '';
    public $cust_name = '';
    public $settled_amt = 0;
    public $reversal_date;
    public $rev_remark = '';
    public $status = '';
    public $min_date = '';
    public $category = '';

    public function __construct() {
        
    }

    public function setFilters($filter) {
        $this->bank_acc_id = $filter['bank_acc_id'];
        $this->find_vch_id = $filter['find_vch_id'];
        if (strpos($this->find_vch_id, 'RCPT') !== 0 && strpos($this->find_vch_id, 'ACR') !== 0 && strpos($this->find_vch_id, 'BRV') !== 0) {
            $this->brokenrules[] = 'Reversal entries are allowed only for Customer Receipt, Advanced Customer Receipt, Bank Receipt';
        } else {
            if (strpos($this->find_vch_id, 'RCPT') === 0 || strpos($this->find_vch_id, 'ACR') === 0) {
                $this->category = 'A';
            } else if (strpos($this->find_vch_id, 'BRV') === 0) {
                $this->category = 'B';
            }
            $this->getData();
        }
    }

    public function getData() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from ac.sp_rcpt_reversal_collection(:pcompany_id, 0, :pvoucher_id, :paccount_id)');
        $cmm->addParam('pcompany_id', SessionManager::getInstance()->getUserInfo()->getSessionVariable('company_id'));
        $cmm->addParam('pvoucher_id', $this->find_vch_id);
        $cmm->addParam('paccount_id', $this->bank_acc_id);
        $rdt = DataConnect::getData($cmm);
        $rdt->addColumn('doc_date_sort', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        foreach ($rdt->Rows() as &$dr) {
            $dr['doc_date_sort'] = strtotime($dr['doc_date']);
        }
        $this->dtVch = $rdt;
        $this->min_date = SessionManager::getInstance()->getUserInfo()->getSessionVariable('year_begin');
        if (count($this->dtVch->Rows()) > 0) {
            if ($this->dtVch->Rows()[0]['vchstatus'] == 'OK') {
                $this->doc_id = $this->dtVch->Rows()[0]['voucher_id'];
                $this->doc_date = $this->dtVch->Rows()[0]['doc_date'];
                $this->bank_acc = $this->dtVch->Rows()[0]['account_head'];
                $this->cust_name = $this->dtVch->Rows()[0]['customer'];
                $this->settled_amt = $this->dtVch->Rows()[0]['settled_amt'];
                if (strtotime($this->doc_date) > strtotime($this->min_date)) {
                    $this->min_date = $this->doc_date;
                }
            } else if ($this->dtVch->Rows()[0]['vchstatus'] == 'Reversed') {
                $this->brokenrules[] = ' Voucher already reversed.';
            } else if ($this->dtVch->Rows()[0]['vchstatus'] == 'Notposted') {
                $this->brokenrules[] = ' Voucher not posted.';
            }
        } else {
            $this->brokenrules[] = ' Voucher not found.';
        }
    }

    public function setData($model) {
        if (strpos($model->find_vch_id, 'RCPT') === 0 || strpos($model->find_vch_id, 'ACR') === 0) {
            $model->category = 'A';
        } else if (strpos($model->find_vch_id, 'BRV') === 0) {
            $model->category = 'B';
        }
        $this->validate($model);
        if (count($this->brokenrules) == 0) {
            $cn = DataConnect::getCn(DataConnect::COMPANY_DB);
            try {
                $cn->beginTransaction();

                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('select * from ac.sp_rcpt_reversal_update(:pcompany_id, 0, :pvoucher_id, :paccount_id, :preversal_date, :preversal_remark, :pcategory, :pfinyear)');
                $cmm->addParam('pcompany_id', SessionManager::getInstance()->getUserInfo()->getSessionVariable('company_id'));
                $cmm->addParam('pvoucher_id', $model->doc_id);
                $cmm->addParam('paccount_id', $model->bank_acc_id);
                $cmm->addParam('preversal_date', $model->reversal_date);
                $cmm->addParam('preversal_remark', $model->rev_remark);
                $cmm->addParam('pcategory', $model->category);
                $cmm->addParam('pfinyear', SessionManager::getInstance()->getUserInfo()->getSessionVariable('finyear'));
                DataConnect::exeCmm($cmm, $cn);

                $cn->commit();
                $cn = null;
                $this->logAction($model);
                $this->status = 'OK';
            } catch (\Exception $ex) {
                if ($cn->inTransaction()) {
                    $cn->rollBack();
                    $cn = null;
                }
                $this->status = 'Server Error';
                return $ex->getMessage();
            }
        }
    }

    public function validate($model) {
        if (strpos($model->doc_id, 'RCPT') !== 0 && strpos($model->doc_id, 'ACR') !== 0 && strpos($model->doc_id, 'BRV') !== 0) {
            $this->brokenrules[] = 'Reversal entries are allowed only for Customer Receipt and Advanced Customer Receipt.';
        }
        if ($model->reversal_date == NULL) {
            $this->brokenrules[] = 'Reversal date is required.';
        } else if ($model->rev_remark == NULL) {
            $this->brokenrules[] = 'Remarks is required.';
        } else {
            if ($model->reversal_date < $model->doc_date) {
                $this->brokenrules[] = 'Reversal date can not be earlier than settlement date.';
            }
            if ($model->rev_remark == '') {
                $this->brokenrules[] = 'Reversal remarks are required.';
            }
        }
        if (strpos($model->doc_id, 'ACR') === 0) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select * from ac.rl_pl_alloc where rl_pl_id in (
                                    select rl_pl_id from ac.rl_pl where voucher_id = :pvoucher_id)');
            $cmm->addParam('pvoucher_id', $model->doc_id);
            $rdt = DataConnect::getData($cmm);
            if (count($rdt->Rows()) > 0) {
                $this->brokenrules[] = ' Advance has been utilised. Only non settled advances can be reversed.';
            }
        }
    }

    private function logAction($model) {
        $json_data = json_encode($model, JSON_HEX_APOS);
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('insert into sys.user_action_log (utility_name, user_id,machine_name,json_log)'
                . ' (select :putil, :puser_id, :pmachine_name, :pjson_log)');
        $cmm->addParam('putil', 'Reciept Reversal');
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $cmm->addParam('pmachine_name', gethostname());
        $cmm->addParam('pjson_log', $json_data);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
    }

}

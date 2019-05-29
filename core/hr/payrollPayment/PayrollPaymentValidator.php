<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\payrollPayment;
use YaLinqo\Enumerable;

/**
 * Description of PayrollPaymentValidator
 *
 * @author Priyanka
 */
class PayrollPaymentValidator extends \app\core\ac\base\VoucherBaseValidator {

    public function validatePayrollPaymentEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    protected function validateBusinessRules() {
        
        $this->bo->credit_amt = round(Enumerable::from($this->bo->pay_items_tran->Rows())->sum('$a==>$a["net_amt"]'), \app\cwf\vsla\Math::$amtScale);
        
        // validate cheque date if PDC true
        if ($this->bo->is_pdc) {
            if (strtotime($this->bo->cheque_date) <= strtotime($this->bo->doc_date)) {
                $this->bo->addBRule('Cheque date must be later than document date.');
            }
        }
        // set the row in vch_tran
        $rowcount = count($this->bo->vch_tran->Rows());
        for ($i = 0; $i <= $rowcount; $i++) {
            $this->bo->vch_tran->removeRow(0);
        }

        $newRow = $this->bo->vch_tran->NewRow();
        $newRow['sl_no'] = -1;
        $newRow['vch_tran_id'] = '';
        $newRow['branch_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id');
        $newRow['voucher_id'] = '';
        $newRow['dc'] = 'D';
        $newRow['account_id'] = $this->bo->liability_account_id;
        $newRow['debit_amt_fc'] = 0;
        $newRow['debit_amt'] = $this->bo->credit_amt;
        $newRow['credit_amt_fc'] = 0;
        $newRow['credit_amt'] = 0;
        $this->bo->vch_tran->AddRow($newRow);
        $this->bo->vch_caption = 'Employees Towards Payroll Payment';
//        parent::validateBusinessRules();
    }
    
    public function validateBeforeDelete() {
        parent::validateBeforeDelete();
    }
}

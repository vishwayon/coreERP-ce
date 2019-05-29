<?php

namespace app\core\hr\payrollGeneration\worker;

class PayrollGenTemp implements IPayrollGenWorker {

//    
    private $payDateFrom = null;
    private $payDateTo = null;
    private $payrollGroupId=null;
    private $dtPayrollTran=null;
    public $dtPayrollTranDetail=null;
    public $dtPayrollTranLoanDetail=null;
    public $dtPayrollTranGratuityDetail=null;
    public $dtPayrollTranDetailTemp=null;  
    public $dtPayrollCustomTran = null;
    
    
    public function __construct($paydatefrom, $paydateto, $payrollgroupid, $dtpayrolltran, $dtpayrollcustomtran) {
        $this->payDateFrom=$paydatefrom;
        $this->payDateTo=$paydateto;
        $this->payrollGroupId=$payrollgroupid;
        $this->dtPayrollTran= $dtpayrolltran; 
        $this->initpayrolltrandetail();
        $this->initpayrolltranloandetail();
        $this->initpayrolltrangratuitydetail();
        $this->dtPayrollTranDetailTemp=new \app\cwf\vsla\data\DataTable();
        $this->dtPayrollTranDetailTemp->cloneColumns($this->dtPayrollTranDetail);
        $this->dtPayrollCustomTran = $dtpayrollcustomtran;
        
    }

    public function PayDateFrom() {
        return $this->payDateFrom;
    }

    public function PayDateTo() {
        return $this->payDateTo;
    }

    public function PayrollGroupId() {
        return $this->payrollGroupId;
    }

    public function PayrollTran() {
        return $this->dtPayrollTran;
    }

    public function PayrollTranDetail() {
        return $this->dtPayrollTranDetail;
    }

    public function PayrollTranLoanDetail() {
        return $this->dtPayrollTranLoanDetail;
    }
    
    public function PayrollTranGratuityDetail() {
        return $this->dtPayrollTranGratuityDetail;
    }
        
    public function PayrollTranDetailTemp() {
        return $this->dtPayrollTranDetailTemp;
    }

    public function PayrollCustomTran() {
        return $this->dtPayrollCustomTran;
    }

    public function initpayrolltrandetail() {
        // Initialise payroll_tran_detail
        $this->dtPayrollTranDetail = new \app\cwf\vsla\data\DataTable();
        $this->dtPayrollTranDetail->addColumn('sl_no', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        $this->dtPayrollTranDetail->addColumn('employee_id', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, -1);
        $this->dtPayrollTranDetail->addColumn('employee_fullname', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_STRING, '');
        $this->dtPayrollTranDetail->addColumn('payhead_id', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, -1);
        $this->dtPayrollTranDetail->addColumn('payhead', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_STRING, '');
        $this->dtPayrollTranDetail->addColumn('payhead_type', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_STRING, '');
        $this->dtPayrollTranDetail->addColumn('emolument_amt', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $this->dtPayrollTranDetail->addColumn('deduction_amt', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $this->dtPayrollTranDetail->addColumn('emp_payplan_id', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, -1);
        $this->dtPayrollTranDetail->addColumn('total_days', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        $this->dtPayrollTranDetail->addColumn('pay_days', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        $this->dtPayrollTranDetail->addColumn('no_pay_days', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, '');
        $this->dtPayrollTranDetail->addColumn('incl_in_nopay', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        $this->dtPayrollTranDetail->addColumn('monthly_or_onetime', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 1);
//        $this->dtPayrollTranDetail->addColumn('calc_eff_from_date', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DATE, '');
//        $this->dtPayrollTranDetail->addColumn('calc_eff_to_date', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DATE, '');
        $this->dtPayrollTranDetail->addColumn('ot_hr', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $this->dtPayrollTranDetail->addColumn('ot_holiday_hr', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $this->dtPayrollTranDetail->addColumn('ot_special_hr', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $this->dtPayrollTranDetail->addColumn('ot_amt', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $this->dtPayrollTranDetail->addColumn('ot_holiday_amt', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $this->dtPayrollTranDetail->addColumn('ot_special_amt', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);

        $this->dtPayrollTranDetail->addColumn('pay_perc', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $this->dtPayrollTranDetail->addColumn('pay_on_perc', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $this->dtPayrollTranDetail->addColumn('pay_on_min_amt', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $this->dtPayrollTranDetail->addColumn('pay_on_max_amt', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $this->dtPayrollTranDetail->addColumn('min_pay_amt', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $this->dtPayrollTranDetail->addColumn('max_pay_amt', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $this->dtPayrollTranDetail->addColumn('en_round_type', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        $this->dtPayrollTranDetail->addColumn('step_id', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        $this->dtPayrollTranDetail->addColumn('en_pay_type', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        $this->dtPayrollTranDetail->addColumn('parent_details', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_STRING, '');
        $this->dtPayrollTranDetail->addColumn('employee_payplan_detail_id', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
    }

    public function initpayrolltranloandetail() {
        // Initialise payroll_tran_loan_detail
        $this->dtPayrollTranLoanDetail  = new \app\cwf\vsla\data\DataTable();
        $this->dtPayrollTranLoanDetail->addColumn('sl_no', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        $this->dtPayrollTranLoanDetail->addColumn('employee_id', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, -1);
        $this->dtPayrollTranLoanDetail->addColumn('employee_fullname', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_STRING, '');
        $this->dtPayrollTranLoanDetail->addColumn('payhead_id', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, -1);
        $this->dtPayrollTranLoanDetail->addColumn('payhead', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_STRING, '');
        $this->dtPayrollTranLoanDetail->addColumn('loan_id', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_STRING, '');
        $this->dtPayrollTranLoanDetail->addColumn('installment_principal', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $this->dtPayrollTranLoanDetail->addColumn('installment_interest', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $this->dtPayrollTranLoanDetail->addColumn('installment_amount', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
    }
        
    public function initpayrolltrangratuitydetail() {
        // Initialise payroll_tran_gratuity_detail        
        $this->dtPayrollTranGratuityDetail = new \app\cwf\vsla\data\DataTable();
        $this->dtPayrollTranGratuityDetail->addColumn('sl_no', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        $this->dtPayrollTranGratuityDetail->addColumn('slab_from_date', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DATE, '');
        $this->dtPayrollTranGratuityDetail->addColumn('slab_to_date', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DATE, '');
        $this->dtPayrollTranGratuityDetail->addColumn('slab_days', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, '');
        $this->dtPayrollTranGratuityDetail->addColumn('gratuity_days', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, '');
        $this->dtPayrollTranGratuityDetail->addColumn('gratuity_amt', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $this->dtPayrollTranGratuityDetail->addColumn('unpaid_days', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, '');
       
    }

}

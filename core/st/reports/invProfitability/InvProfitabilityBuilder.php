<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\reports\invProfitability;
use \YaLinqo\Enumerable;
/**
 * Description of InvProfitabilityBuilder
 *
 * @author priyanka
 */

class InvProfitabilityBuilder {
    //put your code here
    private $company_id = 0;
    private $branch_id=0;
    private $finyear='';
    private $fromDate='';
    private $toDate='';
    private $customer_id = 0;
    private $salesman_id=0;
    public $invResult = [];
    public $dtInv;
    
    
    public function __construct($company_id, $branch_id, $finYear, $fromDate, $toDate, $customer_id, $salesman_id) {
        $this->company_id = $company_id;
        $this->branch_id = $branch_id;
        $this->finyear = $finYear;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        $this->customer_id = $customer_id;
        $this->salesman_id = $salesman_id;
    }
    
    public function GenerateResult() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $query = "Select customer, salesman_name, voucher_id, doc_date, sale_amt, mat_cost, profit, gp
                    from st.fn_sip_sales_inv_report(:pcompany_id, :pbranch_id, :pfinyear, :pfrom_date, :pto_date, :pcustomer_id, :psalesman_id )
                  Order by customer";
        $cmm->setCommandText($query);
        $cmm->addParam('pcompany_id', $this->company_id);
        $cmm->addParam('pbranch_id', $this->branch_id);
        $cmm->addParam('pfinyear', $this->finyear);
        $cmm->addParam('pfrom_date', $this->fromDate);
        $cmm->addParam('pto_date', $this->toDate);
        $cmm->addParam('pcustomer_id', $this->customer_id);
        $cmm->addParam('psalesman_id', $this->salesman_id);
        
        $this->dtInv = \app\cwf\vsla\data\DataConnect::getData($cmm);   
    }
}

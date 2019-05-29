<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\reports\productProfitability;
use \YaLinqo\Enumerable;
/**
 * Description of ProductProfitabilityBuilder
 *
 * @author priyanka
 */

class ProductProfitabilityBuilder {
    //put your code here
    private $company_id = 0;
    private $branch_id=0;
    private $fromDate='';
    private $toDate='';
    public $dtMat;
    
    
    public function __construct($company_id, $branch_id, $fromDate, $toDate) {
        $this->company_id = $company_id;
        $this->branch_id = $branch_id;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }
    
    public function GenerateResult() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $query = "Select *
                    from st.fn_sales_product_profitability_report(:pcompany_id, :pbranch_id, :pfrom_date, :pto_date)
                  Order by material_name";
        $cmm->setCommandText($query);
        $cmm->addParam('pcompany_id', $this->company_id);
        $cmm->addParam('pbranch_id', $this->branch_id);
        $cmm->addParam('pfrom_date', $this->fromDate);
        $cmm->addParam('pto_date', $this->toDate);
        
        $this->dtMat = \app\cwf\vsla\data\DataConnect::getData($cmm);  
    }
}

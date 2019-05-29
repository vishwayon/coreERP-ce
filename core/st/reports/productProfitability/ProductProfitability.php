<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\reports\productProfitability;
use \YaLinqo\Enumerable;
/**
 * Description of ProductProfitability
 *
 * @author priyanka
 */
class ProductProfitability  extends \app\cwf\fwShell\base\ReportBase {
    //put your code here
    
    private $sppBuilder;
    
    public $fromDate;
    public $toDate;
    public $branchName;
    public $txn_ccy;
    
    public function onRequestReport($rptOption) {
        // Do param validations
    }
    public function getModel() {
        $uinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
        $this->sppBuilder = new ProductProfitabilityBuilder(
                $uinfo->getCompany_ID(), 
                $this->rptOption->rptParams['pbranch_id'],
                $this->rptOption->rptParams['pfrom_date'],
                $this->rptOption->rptParams['pto_date']
            );
        $this->fromDate = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($this->rptOption->rptParams['pfrom_date']);
        $this->toDate = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($this->rptOption->rptParams['pto_date']);
        $this->txn_ccy = $this->rptOption->rptParams['pcwf_txn_ccy'];
        if ($this->rptOption->rptParams['pbranch_id']==0) {
            $this->branchName = "Consolidated";
        } else {
            $this->branchName = \app\cwf\vsla\utils\LookupHelper::GetLookupText("../cwf/sys/lookups/Branch.xml", "branch_name", "branch_id", $this->rptOption->rptParams['pbranch_id']);
        }
        $this->sppBuilder->GenerateResult();
        return $this;
    }
    
    public function getMatGrp(){        
        $resu= Enumerable::from($this->sppBuilder->dtMat->Rows())->groupBy('$a==>$a["material_name"]')->toArray();         
        return $resu;
    }
    
    public function getResult(){
        return $this->sppBuilder->dtMat->Rows();
    }
       
    public function getSum($source, $field) {
        $result = 0;
        foreach($source as $item) {
            $result += floatval($item[$field]);
        }
        return $result;
    }
    
}
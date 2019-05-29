<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\reports\invProfitability;
use \YaLinqo\Enumerable;
/**
 * Description of InvProfitability
 *
 * @author priyanka
 */
class InvProfitability  extends \app\cwf\fwShell\base\ReportBase {
    //put your code here
    
    private $sipBuilder;
    
    public $fromDate;
    public $toDate;
    public $branchName;
    public $txn_ccy;
    
    public function onRequestReport($rptOption) {
        // Do param validations
    }
    public function getModel() {
        $uinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
        $this->sipBuilder = new InvProfitabilityBuilder(
                $uinfo->getCompany_ID(), 
                $this->rptOption->rptParams['pbranch_id'],
                $uinfo->getSessionVariable('finyear'),
                $this->rptOption->rptParams['pfrom_date'],
                $this->rptOption->rptParams['pto_date'],
                $this->rptOption->rptParams['pcustomer_id'],
                $this->rptOption->rptParams['psalesman_id']
            );
        $this->fromDate = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($this->rptOption->rptParams['pfrom_date']);
        $this->toDate = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($this->rptOption->rptParams['pto_date']);
        $this->txn_ccy = $this->rptOption->rptParams['pcwf_txn_ccy'];
        if ($this->rptOption->rptParams['pbranch_id']==0) {
            $this->branchName = "Consolidated";
        } else {
            $this->branchName = \app\cwf\vsla\utils\LookupHelper::GetLookupText("../cwf/sys/lookups/Branch.xml", "branch_name", "branch_id", $this->rptOption->rptParams['pbranch_id']);
        }
        $this->sipBuilder->GenerateResult();
        return $this;
    }
    
    public function getTotal(){
        return $this->sipBuilder->dtInv->Rows();
    }
    
    public function getCustGrp(){        
        $resu= Enumerable::from($this->sipBuilder->dtInv->Rows())->groupBy('$a==>$a["customer"]')->toArray();         
        return $resu;
    }
    
    public function getSum($source, $field) {
        $result = 0;
        foreach($source as $item) {
            $result += floatval($item[$field]);
        }
        return $result;
    }
    
}

//
//{% for item in model.getResult %}
//                    <tr>
//                        <td>{{item.customer }}</td>
//                        <td>{{item.salesman_name}}</td>
//                        <td>{{ item.voucher_id }}</td>
//                        <td>{{item.doc_date}}</td>
//                        <td class="datatable-col-right">{{item.sale_amt|number_format(2,'.',',')}}</td>
//                        <td class="datatable-col-right">{{item.mat_cost|number_format(2,'.',',')}}</td>
//                        <td class="datatable-col-right">{{item.profit|number_format(2,'.',',')}}</td>
//                        <td class="datatable-col-right">{{item.gp|number_format(2,'.',',')}}</td>
//                    </tr>
//                {% endfor %}
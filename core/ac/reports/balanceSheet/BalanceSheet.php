<?php

namespace app\core\ac\reports\balanceSheet;
use \YaLinqo\Enumerable;
/**
 * This is the data model for Balance Sheet.
 * This class calculates the balance sheet items and schedules
 * for twig presentation
 * 
 * @author girish
 */
class BalanceSheet extends \app\cwf\fwShell\base\ReportBase {
    
    private $bsBuilder;
    
    public $toDate;
    public $branchName;
    public $rptHeader;
    
    public function getModel() {
        $uinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
        
        if ($this->rptOption->rptParams['pbs_option'] < 4) {
            $this->bsBuilder = new BalanceSheetBuilder(
                    $uinfo->getCompany_ID(), 
                    $this->rptOption->rptParams['pbranch_id'],
                    $uinfo->getSessionVariable('finyear'),
                    $uinfo->getSessionVariable('year_begin'),
                    $this->rptOption->rptParams['pto_date']
                );
            $this->bsBuilder->bsOption = $this->rptOption->rptParams['pbs_option'];
            $this->toDate = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($this->rptOption->rptParams['pto_date']);
            if ($this->rptOption->rptParams['pbranch_id']==0) {
                $this->branchName = "Consolidated";
            } else {
                $this->branchName = \app\cwf\vsla\utils\LookupHelper::GetLookupText("../cwf/sys/lookups/Branch.xml", "branch_name", "branch_id", $this->rptOption->rptParams['pbranch_id']);
            }
            $this->bsBuilder->GenerateFinalAccounts();
        } else {
            $bsBuilder = new BalanceSheetByMonthBuilder(
                    $uinfo->getCompany_ID(), 
                    $this->rptOption->rptParams['pbranch_id'],
                    $uinfo->getSessionVariable('finyear'),
                    $uinfo->getSessionVariable('year_begin'),
                    $this->rptOption->rptParams['pto_date']);
            $bsBuilder->GenerateFinalAccounts();
            $bsResult = $bsBuilder->getBsResult();
            $bsResult->toDate = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($this->rptOption->rptParams['pto_date']);
            if ($this->rptOption->rptParams['pbranch_id']==0) {
                $bsResult->branchName = "Consolidated";
            } else {
                $bsResult->branchName = \app\cwf\vsla\utils\LookupHelper::GetLookupText("../cwf/sys/lookups/Branch.xml", "branch_name", "branch_id", $this->rptOption->rptParams['pbranch_id']);
            }
            $bsResult->showBs = true;
            $bsResult->showPnl = true;
            $bsResult->periodTot = $this->rptOption->rptParams['period_tot'];
            
            return $bsResult;
            //var_dump($bsResult);
        }
        if($this->rptOption->rptParams['pbs_option'] == 0){
            $this->rptHeader = 'Balance Sheet With P&L';
        }
        else if($this->rptOption->rptParams['pbs_option'] == 1){
            $this->rptHeader = 'Balance Sheet Only';
        }
        else if($this->rptOption->rptParams['pbs_option'] == 2){
            $this->rptHeader = 'Profit & Loss Only';
        }
        else if($this->rptOption->rptParams['pbs_option'] == 3){
            $this->rptHeader = 'Schedules Only';
        }
        else if($this->rptOption->rptParams['pbs_option'] == 4){
            $this->rptHeader = 'Monthly P&L';
        }
        return $this;
    }
    
    public function onRequestReport($rptOption) {
        if ($rptOption->rptParams['pbs_option'] == '4') {
            $rptOption->rptName = "BalanceSheetByMonth";
        }
    }
    
    public $bsAssets;
    public function getAssets() {
        if($this->bsAssets == null) {
            $this->bsAssets = Enumerable::from($this->bsBuilder->bsResult)->where('$a==>$a["bs_type"]=="A"')->toArray();        
            $this->setStyles($this->bsAssets);
        }
        return $this->bsAssets;
    }
    
    public $bsLiabs;
    public function getLiabs() {
        if($this->bsLiabs == null) {
            $this->bsLiabs = Enumerable::from($this->bsBuilder->bsResult)->where('$a==>$a["bs_type"]=="B"')->toArray();        
            $this->setStyles($this->bsLiabs);
        }
        return $this->bsLiabs;
    }
    
    public $bsIncome;
    public function getIncome() {
        if($this->bsIncome == null) {
            $this->bsIncome = Enumerable::from($this->bsBuilder->bsResult)->where('$a==>$a["bs_type"]=="C"')->toArray();        
            $this->setStyles($this->bsIncome);
        }
        return $this->bsIncome;
    }
    
    public $bsExpense;
    public function getExpense() {
        if($this->bsExpense == null) {
            $this->bsExpense = Enumerable::from($this->bsBuilder->bsResult)->where('$a==>$a["bs_type"]=="D"')->toArray();        
            $this->setStyles($this->bsExpense);
        }
        return $this->bsExpense;
    }
    
    public $bsSchedule;
    public function getSchedule() {
        if($this->bsSchedule == null) {
            $this->bsSchedule = Enumerable::from($this->bsBuilder->bsSchedule)->groupBy('$a==>$a["sch_no"]')->toArray();        
            foreach($this->bsSchedule as &$item) {
                $this->setStyles($item);
            }
        }
        return $this->bsSchedule;
    }
    
    private function setStyles(&$bsItems) {
        // set styles for html output
            foreach($bsItems as &$bsItem) {
                // Set Item name Style  
                $bsItem['item_name_style'] = '';
                $item_name_style = "";
                if(substr($bsItem['parent_key'], 0, 1) == "0" && $bsItem['account_id'] == -1) {
                    // Root items like Assets, Liabs, Income, Expense must be center and bold
                    $item_name_style .= "font-weight: bold; ";
                    $item_name_style .= "display: block; text-align: center; ";
                } else if (substr($bsItem['group_key'], 0, 1) == "B" && $bsItem['sch_no'] == 0 && $bsItem['account_id'] == -1) {
                    // This is a group with child items. So bold
                    $item_name_style .= "font-weight: bold; ";
                }
                if(ord(substr($bsItem['group_key'], 0, 1)) > ord("B")) {
                    // Groups/Accounts greater than group B should be indented
                    $item_name_style .= "padding-left: ".((string)((ord(substr($bsItem['group_key'], 0, 1))-ord("B"))*15))."pt; ";
                } else if ($bsItem["account_id"] != -1) {
                    // All accounts should be indented based on their group key
                    $item_name_style .= "padding-left: ".((string)((ord(substr($bsItem['group_key'], 0, 1))-ord("A"))*15))."pt; ";
                }
                $bsItem['item_name_style'] = $item_name_style;
                
                // Set Sch No Style
                $bsItem['sch_no_style'] = '';
                $sch_no_style = "";
                if($bsItem['sch_no'] == 0) {
                    $sch_no_style .= "visibility: hidden; ";
                }
                $bsItem['sch_no_style'] = $sch_no_style;
                
                // Set amt1 Style
                $bsItem['amt1_style'] = '';
                $amt1_style = "";
                if($bsItem['amt1'] == 0) {
                    $amt1_style .= "visibility: hidden; ";
                } 
                $bsItem['amt1_style'] = $amt1_style;
                
                // Set amt2 Style
                $bsItem['amt2_style'] = '';
                $amt2_style = "";
                if($bsItem['amt2'] == 0) {
                    $amt2_style .= "visibility: hidden; ";
                }
                if($bsItem['amt1'] != 0 && $bsItem['amt2'] != 0) {
                    // if amt1 is not zero, then the inner column (amt2) should have an underline
                    $amt2_style .= "border-bottom: 1pt solid; ";
                }
                $bsItem['amt2_style'] = $amt2_style;
            }
    }
    
    public function getSum($source, $field) {
        $result = 0;
        foreach($source as $item) {
            $result += floatval($item[$field]);
        }
        return $result;
    }
    
    public function showBs() {
        return $this->bsBuilder->bsOption == BalanceSheetBuilder::BS_WITH_PNL || $this->bsBuilder->bsOption == BalanceSheetBuilder::BS_ONLY;
    }
    
    public function showPnL() {
        return $this->bsBuilder->bsOption == BalanceSheetBuilder::BS_WITH_PNL || $this->bsBuilder->bsOption == BalanceSheetBuilder::PNL_ONLY;
    }
    
    public function showSch() {
        return $this->bsBuilder->bsOption == BalanceSheetBuilder::BS_WITH_PNL || $this->bsBuilder->bsOption == BalanceSheetBuilder::SCH_ONLY;
    }
    
    
}

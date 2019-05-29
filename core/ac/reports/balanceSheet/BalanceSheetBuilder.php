<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\reports\balanceSheet;
use \YaLinqo\Enumerable;

/**
 * Description of BalanceSheetBuilder
 *
 * @author girish
 */
class BalanceSheetBuilder {
    const BS_WITH_PNL = 0;
    const BS_ONLY = 1;
    const PNL_ONLY = 2;
    const SCH_ONLY = 3;
    
    private $newSchNo=0;
    private $dtAccounts;
    public $bsResult = [];
    public $bsSchedule = [];
    public $bsOption = self::BS_WITH_PNL;
    private $company_id = 0;
    private $branch_id=0;
    private $finyear='';
    private $fromDate='';
    private $toDate='';
    
    
    public function __construct($company_id, $branch_id, $finYear, $fromDate, $toDate) {
        $this->company_id = $company_id;
        $this->branch_id = $branch_id;
        $this->finyear = $finYear;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }
    
    public function GenerateFinalAccounts() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $query = "Select bs_type, parent_key, group_key, group_name, group_path, account_id, account_head, cl_bal_amt 
                  from ac.fn_bs_report(:pcompany_id, :pbranch_id, :pfinyear, :pfrom_date, :pto_date) 
                  Order by bs_type, group_path, account_head";
        $cmm->setCommandText($query);
        $cmm->addParam('pcompany_id', $this->company_id);
        $cmm->addParam('pbranch_id', $this->branch_id);
        $cmm->addParam('pfinyear', $this->finyear);
        $cmm->addParam('pfrom_date', $this->fromDate);
        $cmm->addParam('pto_date', $this->toDate);
        
        $this->dtAccounts = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $this->createBsGroup("0");
        
    }
    
    private function createBsGroup($parent_key) {
        \yii::trace('inside createBsGroup: '.$parent_key);
        $index=0;
        $total_amt = 0.00;
        $group_key = '';
        $item_name = '';
        $records_total = 0.00;
        $records_total1= 0.00;
        $accounts_total = 0.00;
        $accounts_total1 = 0.00;
        
        // Filtering the source on parent_key
        $acRows = Enumerable::from($this->dtAccounts->Rows())->where('$a==>$a["parent_key"]=="'.$parent_key.'"')->toArray();
        if(count($acRows)>0) {
            foreach ($acRows as $acRow) {
                // Checking whether the group is already added
                if(count(Enumerable::from($this->bsResult)->where('$a==>$a["group_key"]=="'.$acRow['group_key'].'"')->toArray())==0) {
                    $bsrow = $this->newResultRow();
                    $bsrow['v_id'] = count($this->bsResult) + 1;
                    $bsrow['bs_type'] = $acRow['bs_type'];
                    $bsrow['parent_key'] = $acRow['parent_key'];
                    $bsrow['group_key'] = $acRow['group_key'];
                    $bsrow['item_name'] = $acRow['group_name'];
                    $this->bsResult[] = $bsrow;
                    // get currently added index
                    $index = count($this->bsResult) - 1;
                    $group_key = $acRow['group_key'];
                    $item_name = $acRow['group_name'];
                    if(ord(substr($group_key, 0, 1)) < ord("C")) {
                        $records_total = $this->createBsGroup($group_key);
                        $accounts_total = $this->createBsAccount($group_key);
                    } else {
                        //Create schedules if the level is 'C' or greater
                        $this->newSchNo += 1;
                        $this->bsResult[$index]['sch_no'] = $this->newSchNo;
                        $records_total1 = $this->createBsSchGroup($group_key, $this->newSchNo, $item_name);
                        $accounts_total1 = $this->createBsSchAccount($group_key, $this->newSchNo, $item_name);
                    }
                    $total_amt += $records_total + $accounts_total + $records_total1 + $accounts_total1;
                    switch (substr($group_key, 0, 1)) {
                        case "B":
                            $this->bsResult[count($this->bsResult)-1]['amt1'] = $records_total + $accounts_total + $records_total1 + $accounts_total1;
                            break;
                        case "C":
                            $this->bsResult[count($this->bsResult)-1]['amt2'] = $records_total + $accounts_total + $records_total1 + $accounts_total1;
                            break;
                        case "D":
                            $this->bsResult[count($this->bsResult)-1]['amt3'] = $records_total + $accounts_total + $records_total1 + $accounts_total1;
                            break;
                    }
                }
            }
        } 
        return $total_amt;
    }
    
    private function createBsAccount($parent_key) {
        \yii::trace('inside createBsAccount: '.$parent_key);
        $amount = 0.00;
        $total_amount = 0.00;
        $drRows = Enumerable::from($this->dtAccounts->Rows())->where('$a==>$a["group_key"]=="'.$parent_key.'" && $a["account_head"]!="" && $a["cl_bal_amt"]!=0' )->toArray();
        if(count($drRows)>0) {
            foreach($drRows as $drBsAc) {
                $drNew = $this->newResultRow();
                $drNew['v_id'] = count($this->dtAccounts->Rows()) + 1;
                $drNew['bs_type'] = $drBsAc['bs_type'];
                $drNew['parent_key'] = $drBsAc['parent_key'];
                $drNew['group_key'] = $drBsAc['group_key'];
                $drNew['account_id'] = $drBsAc['account_id'];
                $drNew['item_name'] = $drBsAc['account_head'];
                $amount = $drBsAc['cl_bal_amt'];
                switch (substr($drBsAc['group_key'], 0, 1)) {
                    case "A":
                        $drNew['amt1'] = $amount;
                        break;
                    case "B":
                        $drNew['amt2'] = $amount;
                        break;
                    case "C":
                        $drNew['amt3'] = $amount;
                        break;
                }
                $this->bsResult[] = $drNew;
                $total_amount += $amount;
            }
        } 
        return $total_amount;  
    }
    
    private function createBsSchGroup($parent_key, $schNo, $schName) {
        \yii::trace('inside createBsSchGroup: '.$parent_key);
        $index = 0;
        $group_key = '';
        $item_name = '';
        $total_amount = 0.00;
        $records_total = 0.00;
        $accounts_total = 0.00;
        $drRows = Enumerable::from($this->dtAccounts->Rows())->where('$a==>$a["parent_key"]=="'.$parent_key.'"')->toArray();
        if(count($drRows)>0) {
            foreach($drRows as $drSchGrp) {
                //Checking whether the Group is Already Added
                $drSch = Enumerable::from($this->bsSchedule)->where('$a==>$a["group_key"]=="'.$drSchGrp['group_key'].'"')->toArray();
                if(count($drSch)==0) {
                    // adding new row to the resultset
                    $drNew = $this->newScheduleRow();
                    $drNew['v_id'] = count($this->bsSchedule) + 1;
                    $drNew['sch_no'] = $schNo;
                    $drNew['sch_name'] = $schName;
                    $drnew['bs_type'] = $drSchGrp['bs_type'];
                    $drNew['parent_key'] = $drSchGrp['parent_key'];
                    $drNew['group_key'] = $drSchGrp['group_key'];
                    $drNew['item_name'] = $drSchGrp['group_name'];
                    $this->bsSchedule[] = $drNew;
                    $index = count($this->bsSchedule)-1;
                    $group_key = $drSchGrp['group_key'];
                    $item_name = $drSchGrp['group_name'];
                    $records_total = $this->createBsSchGroup($group_key, $schNo, $item_name);
                    $accounts_total = $this->createBsSchAccount($group_key, $schNo, $schName);
                    $total_amount += $records_total + $accounts_total;
                    // Formatting
                    if($records_total != 0 || $accounts_total != 0 ) {
                        $this->bsSchedule[$index]['bold_item_name'] = TRUE;
                    }
                    switch (substr($this->bsSchedule[$index]['group_key'], 0, 1)) {
                        case "C":
                            $dr1 = $this->newScheduleRow();
                            $dr1['v_id'] = count($this->bsSchedule) + 1;
                            $dr1['parent_key'] = $parent_key;
                            $dr1['group_key'] = $group_key;
                            $dr1['item_name'] = "Total";
                            $dr1['amt1'] = $records_total + $accounts_total;
                            $this->bsSchedule[] = $dr1;
                            break;
                        case "D":
                            $this->bsSchedule[count($this->bsSchedule)-1]['amt1'] = $records_total + $accounts_total;
                            break;
                    }
                }                
            }
        } else {
            $accounts_total = $this->createBsSchAccount($parent_key, $schNo, $schName);
            $total_amount += $accounts_total;
            $drItems = Enumerable::from($this->bsSchedule)->where('$a==>$a["group_key"]=="'.$parent_key.'"')->toArray();
            if(count($drItems)==0) {
                $dr2 = $this->newScheduleRow();
                $dr2['v_id'] = count($this->bsSchedule) + 1;
                $dr2['sch_no'] = $schNo;
                $dr2['sch_name'] = $schName;
                $dr2['parent_key'] = $parent_key;
                $dr2['group_key'] = $parent_key;
                $dr2['item_name'] = "No Items for this Schedule";
                $dr2['amt1'] = 0;
                $this->bsSchedule[] = $dr2;
            }
        }
        \yii::trace('return createBsSchGroup: '.$parent_key.' total_amt: '.$total_amount);
        return $total_amount;
    }
    
    private function createBsSchAccount($parent_key, $schNo, $schName) {
        \yii::trace('inside createBsSchAccount: '.$parent_key);
        $amount = 0.00;
        $total_amount = 0.00;
        $drRows = Enumerable::from($this->dtAccounts->Rows())->where('$a==>$a["group_key"]=="'.$parent_key.'" && $a["account_head"]!="" && $a["cl_bal_amt"]!=0')->toArray();
        if (count($drRows)>0) {
            foreach($drRows as $drSchAcc) {
                $drSch = Enumerable::from($this->bsSchedule)->where('$a==>$a["account_id"]=='.$drSchAcc["account_id"])->toArray();
                if(count($drSch)==0) {
                    $drNew = $this->newScheduleRow();
                    $drNew['v_id'] = count($this->bsSchedule) + 1;
                    $drNew['sch_no'] = $schNo;
                    $drNew['sch_name'] = $schName;
                    $drNew['parent_key'] = $drSchAcc['parent_key'];
                    $drNew['group_key'] = $drSchAcc['group_key'];
                    $drNew['account_id'] = $drSchAcc['account_id'];
                    $drNew['item_name'] = $drSchAcc['account_head'];
                    $amount = $drSchAcc['cl_bal_amt'];
                    switch (substr($drNew['group_key'], 0, 1)) {
                        case "C":
                            $drNew['amt1'] = $amount;
                            break;
                        default :
                            $drNew['amt2'] = $amount;
                            break;
                    }
                    $this->bsSchedule[] = $drNew;
                    $total_amount += $amount;
                }
            }
        } else {
            $drSchAcc = Enumerable::from($this->dtAccounts->Rows())->where('$a==>$a["group_key"]=="'.$parent_key.'"')->toArray();
            if(count($drSchAcc)==0) {
                $drSch1 = Enumerable::from($this->bsSchedule)->where('$a==>$a["group_key"]=="'.$parent_key.'"')->toArray();
                if (count($drSch1)==0) {
                    $dr = $this->newScheduleRow();
                    $dr['v_id'] = count($this->bsSchedule)+1;
                    $dr['sch_no'] = $schNo;
                    $dr['sch_name'] = $schName;
                    $dr['parent_key'] = $parent_key;
                    $dr['group_key'] = $parent_key;
                    $dr['item_name'] = "No Items for this Schedule";
                    $dr['amt1'] = 0.00;
                    $this->bsSchedule[] = $dr;
                }
            }
        }
        return $total_amount;
    }
    
    private function newResultRow() {
        $newRow = [
            'v_id' => 0,
            'bs_type' => '',
            'sch' => 0,
            'sch_name' => '',
            'parent_key' => '',
            'group_key' => '',
            'account_id' => -1,
            'item_name' => '',
            'sch_no' => 0,
            'amt3' => 0.00,
            'amt2' => 0.00,
            'amt1' => 0.00
        ];
        return $newRow;
    }
    
    private function newScheduleRow() {
        $newRow = [
            'v_id' => 0,
            'bs_type' => '',
            'sch' => 0,
            'sch_name' => '',
            'parent_key' => '',
            'group_key' => '',
            'account_id' => -1,
            'item_name' => '',
            'sch_no' => 0,
            'amt3' => 0.00,
            'amt2' => 0.00,
            'amt1' => 0.00
        ];
        return $newRow;
    }
    
}

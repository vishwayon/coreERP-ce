<?php

namespace app\core\ac\reports\balanceSheet;

/**
 * Result Structure used for constructing BalanceSheetByMonth
 *
 * @author girishshenoy
 */
class BsResult {
    
    /**
     * This contains the months/quarters/half year periods.
     * Each element in the array represents a period
     * @var bsPeriod[] The resultant array of periods
     */
    public $bsPeriods = [];
    
    /**
     * This contains an array of each bs-head. Fields included are
     * bs_type : A/B/C/D - stands for Assets/Liabilities/Income/Expenses
     * parent_key : Parent key of the Account Group
     * group_key : Account Group key
     * group_name : Account Group Name
     * group_path : Account group path from the root
     * account_id : Account Head ID
     * account_head : Account Head [GL Account]
     * @var array A collection of the above structured array
     */
    public $bsHeads = [];
    
    /**
     * Gets sum of elements in the array
     * @param array $arr
     * @return float Returns sum of all numeric elements in the array
     */
    public function getSum($arr) {
        $amt = 0;
        foreach($arr as $k => $v) {
            $amt += $v;
        }
        return $amt;
    }
    
    public function getSumAbs($arr) {
        $amt = 0;
        foreach($arr as $k => $v) {
            $amt += abs($v);
        }
        return $amt;
    }
}

/**
 * Contains data for one period
 */
class bsPeriod {
    
    /** Mentions the Serial Number of the period. 
     *  While using, it should be in the order of serial no
     * @var int The Serial No of the column
     */
    public $sl_no = 0;
    
    /**
     *  A unique identifier for the period.
     *  Can also be used as heading
     * @var string The period identifier (heading)
     */
    public $id = '';
    
    /**
     * Contains amount for each account head
     * @var array An array of account_id => amount
     */
    public $acc_amts = [];
}

/**
 * Contains data for each Account Group/Account Head
 */
class bsHead {
    
}

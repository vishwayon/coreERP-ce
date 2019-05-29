<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\payrollGeneration\worker;

use YaLinqo\Enumerable;

/**
 * Description of TaxScheduleHelper
 *
 * @author priyanka
 */
class PayrollGenHelper {

    //put your code here
    const PERCENT_OF_AMOUNT = 0;
    const PROMPT_ON_PAYROLL_GENERATION = 3;
    const CUSTOM_ABSOLUTE_AMOUNT = 2;
    const DO_NOT_ROUND = 0;
    const ROUND_OFF_TENTH_DECIMAL = 1;
    const ROUND_OFF_WHOLE_DIGIT = 2;
    const ROUND_UP_WHOLE_DIGIT = 3;
    const ROUND_DOWN_WHOLE_DIGIT = 4;

    Public static function CalculatePayAmt($dtprtrandetailtemp, $drtrandetailitem) {
        $pay_amt = 0;
        $amt = 0;
        if ($drtrandetailitem['payhead_type'] == "E" || $drtrandetailitem['payhead_type'] == "C") {
            $amt = $drtrandetailitem['emolument_amt'];
        } else {
            $amt = $drtrandetailitem['deduction_amt'];
        }
        // Modify Base Tax if Tax Item is dependent on another tax detail
        $calculatedBase = self::ResolveBase($amt, $dtprtrandetailtemp, $drtrandetailitem);
        $pay_amt = self::CalculatePayItem($amt, $calculatedBase, $drtrandetailitem);
        return $pay_amt;
    }

    private static function ResolveBase($amt, $dtprtrandetailtemp, $drtrandetailitem) {
        $result_amt = 0;
        $parent_details = array();

        $parent_details = explode(',', $drtrandetailitem['parent_details']);
        if (count($parent_details) > 0) {
            // When parent Items are involved
            foreach ($parent_details as $employee_payplan_detail_id) {
                if ($employee_payplan_detail_id == 0) { // Base Amt
                    $result_amt = $amt;
                } else if ($employee_payplan_detail_id > 0) { // Parent Payhead
                    $drParent = Enumerable::from($dtprtrandetailtemp->Rows())->where('$a==>$a["employee_payplan_detail_id"]==' . $employee_payplan_detail_id)->toList();
                    if (count($drParent) == 1) {
                        if ($drParent[0]['payhead_type'] == "E" || $drtrandetailitem['payhead_type'] == "C") {
                            $result_amt += $drParent[0]['emolument_amt'];
                        } else {
                            $result_amt -= $drParent[0]['deduction_amt'];
                        }
                    } else {
                        throw new \Exception('Parent Payhead is not a part of the Pay Schedule. Failed to resolve Base Amt.');
                    }
                }
            }
        } else { // When there are not parents, the base is understood to be the parent
            $result_amt = $amt;
        }
        return $result_amt;
    }

    /*   This method calculates the Item Pay Amt(it inturn calls various methods for calculations) */

    private static function CalculatePayItem($amt, $base_amt, $drtrandetailitem) {
        //  ****     Resolve the Base Amt First
        $base_amt = self::CalculateBase($base_amt, $drtrandetailitem);

        //  ****    Calculate Tax Amt
        $pay_amt = self::CalculateAmt($amt, $base_amt, $drtrandetailitem);

        //  ****    Round Off Calculated Tax
        $pay_amt = self::RoundOff($pay_amt, $drtrandetailitem);

        return $pay_amt;
    }

    private static function CalculateBase($base_amt, $drtrandetailitem) {
        // ****     Apply Percentage
        $base_amt = $base_amt * $drtrandetailitem['pay_on_perc'] / 100;

        // ****     If max is mentioned, restrict base to max
        if ($drtrandetailitem['pay_on_max_amt'] > 0) {
            If ($base_amt > $drtrandetailitem['pay_on_max_amt']) {
                $base_amt = $drtrandetailitem['pay_on_max_amt'];
            }
        }

        // ****     If min is mentioned, ensure base is not less than min
        If ($base_amt < $drtrandetailitem['pay_on_min_amt']) {
            $base_amt = 0;
        }

        Return $base_amt;
    }

    /* This method calculates the Actual Tax */

    Private static function CalculateAmt($amt, $base_amt, $drtrandetailitem) {
        $pay_amt = 0;

        if ($drtrandetailitem['en_pay_type'] == self::CUSTOM_ABSOLUTE_AMOUNT) {
            $pay_amt = $amt;
        } else if ($drtrandetailitem['en_pay_type'] == self::PERCENT_OF_AMOUNT) {
            // ****     Apply Percentage
            $pay_amt = $base_amt * $drtrandetailitem['pay_perc'] / 100;

            // ****     If max is mentioned, restrict base to max
            if ($drtrandetailitem['max_pay_amt'] > 0) {
                If ($pay_amt > $drtrandetailitem['max_pay_amt']) {
                    $pay_amt = $drtrandetailitem['max_pay_amt'];
                }
            }

            // ****     If min is mentioned, ensure base is not less than min
            If ($pay_amt < $drtrandetailitem['min_pay_amt']) {
                $pay_amt = $drtrandetailitem['min_pay_amt'];
            }
        }
        return $pay_amt;
    }

    public static function RoundOff($pay_amt, $drtrandetailitem) {
        if ($drtrandetailitem['en_round_type'] == self::ROUND_OFF_TENTH_DECIMAL) {
            return round($pay_amt, 1);
        } else if ($drtrandetailitem['en_round_type'] == self::ROUND_OFF_WHOLE_DIGIT) {
            return round($pay_amt, 0);
        } else if ($drtrandetailitem['en_round_type'] == self::DO_NOT_ROUND) {
            return round($pay_amt, \app\cwf\vsla\Math::$amtScale);
        } else if ($drtrandetailitem['en_round_type'] == self::ROUND_UP_WHOLE_DIGIT) {
            return ceil($pay_amt);
        } else if ($drtrandetailitem['en_round_type'] == self::ROUND_DOWN_WHOLE_DIGIT) {
            return floor($pay_amt);
        } else {
            return round($pay_amt, \app\cwf\vsla\Math::$amtScale);
        }
    }

    public static function GetPromtAmt($drtrandetailitem, $dtpayrollcustomtran) {
        $lst = Enumerable::from($dtpayrollcustomtran)->where('$a==>$a["employee_id"]==' . $drtrandetailitem['employee_id'] . ' && $a["payhead_id"]==' . $drtrandetailitem['payhead_id'] . ' && $a["employee_payplan_detail_id"]==' . $drtrandetailitem['employee_payplan_detail_id'] )->toList();
        if (count($lst) == 1) {
            if ($drtrandetailitem['payhead_type'] == 'E' || $drtrandetailitem['payhead_type'] == "C") {
                return $lst[0]['emolument_amt'];
            }
            else {
                return $lst[0]['deduction_amt'];
            }
            return 0;
        }        
        return 0;
    }
}

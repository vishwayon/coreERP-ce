<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tx\taxSchedule\worker;
use YaLinqo\Enumerable;
/**
 * Description of TaxScheduleCalculator
 *
 * @author priyanka
 */
class TaxScheduleCalculator {
    //put your code here
    const PERCENT_OF_AMOUNT = 0;
    const CUSTOM_PERCENT_OF_AMOUNT = 1;
    const CUSTOM_ABSOLUTE_AMOUNT = 2;
    
    const DO_NOT_ROUND =0;
    const ROUND_OFF_TENTH_DECIMAL = 1;
    const ROUND_OFF_WHOLE_DIGIT = 2;
    const ROUND_UP_WHOLE_DIGIT = 3;
    const ROUND_DOWN_WHOLE_DIGIT = 4;
    
    
    public static function CalculateTax($tax_schedule_id, $base_amt, $qty, $tax_detail_temp, $isnew){
        $tax_applied_tran = TaxScheduleHelper::CreateTaxAppliedTran();
        
        // Get Tax Scheddule if not available
        if(count($tax_detail_temp) == 0){
           $tax_detail_temp = self::GetSchedule($tax_detail_temp, $tax_schedule_id);
        }
        else{
            if( $isnew == 1 || $tax_detail_temp[0]['tax_schedule_id'] != $tax_schedule_id){
                unset($tax_detail_temp);
                $tax_detail_temp = array();
                $tax_detail_temp = self::GetSchedule($tax_detail_temp, $tax_schedule_id);
            }
        }
        
        // Calculate Item Tax        
        $calculatedBase=0;
        foreach($tax_detail_temp as &$ref_detail_row){
            
            // Modify Base Tax if Tax Item is dependent on another tax detail
            $calculatedBase = self::ResolveBase($base_amt, $ref_detail_row, $tax_applied_tran);
            $tax_amt = self::CalculateTaxItem($calculatedBase, $qty, $ref_detail_row, $tax_schedule_id, $tax_applied_tran);
            
            $ref_detail_row['tax_amt']=$tax_amt;
            
            // Resolve Tax  
            $newRow = $tax_applied_tran->NewRow();
            $newRow['tax_schedule_id'] = $tax_schedule_id;          
            $newRow['tax_detail_id'] = $ref_detail_row['tax_detail_id'];            
            $newRow['step_id'] = $ref_detail_row['step_id'];           
            $newRow['account_id'] = $ref_detail_row['account_id'];          
            $newRow['parent_tax_details'] = $ref_detail_row['parent_tax_details'];            
            $newRow['description'] = $ref_detail_row['description'];  
            $newRow['en_tax_type'] = $ref_detail_row['en_tax_type'];            
            $newRow['en_round_type'] = $ref_detail_row['en_round_type'];
            $newRow['tax_perc'] = $ref_detail_row['tax_perc'];            
            $newRow['tax_on_perc'] = $ref_detail_row['tax_on_perc'];            
            $newRow['tax_on_min_amt'] = $ref_detail_row['tax_on_min_amt'];          
            $newRow['tax_on_max_amt'] = $ref_detail_row['tax_on_max_amt'];            
            $newRow['min_tax_amt'] = $ref_detail_row['min_tax_amt'];  
            $newRow['max_tax_amt'] = $ref_detail_row['max_tax_amt'];              
            $newRow['custom_rate'] = $ref_detail_row['custom_rate'];             
            $newRow['tax_amt'] = $tax_amt;        
            
            $tax_applied_tran->AddRow($newRow); 
        }
        return $tax_applied_tran;
    }
    
    private static function GetSchedule($tax_detail_temp, $tax_schedule_id){
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select step_id, tax_detail_id, parent_tax_details, description, account_id, en_tax_type, en_round_type,
                                    tax_perc, tax_on_perc, tax_on_min_amt, tax_on_max_amt, min_tax_amt, max_tax_amt
                                From tx.tax_detail
                                where tax_schedule_id=:ptax_schedule_id
                                order by step_id');
        $cmm->addParam('ptax_schedule_id', $tax_schedule_id);
        $dtTaxDetail = \app\cwf\vsla\data\DataConnect::getData($cmm);
        foreach($dtTaxDetail->Rows() as $row){
            $newRow = array();
            $newRow['tax_schedule_id'] = $tax_schedule_id;    
            $newRow['tax_detail_id'] = $row['tax_detail_id'];            
            $newRow['step_id'] = $row['step_id'];
            $newRow['account_id'] = $row['account_id'];            
            $newRow['parent_tax_details'] = $row['parent_tax_details'];            
            $newRow['description'] = $row['description'];  
            $newRow['en_tax_type'] = $row['en_tax_type'];            
            $newRow['en_round_type'] = $row['en_round_type'];
            $newRow['tax_perc'] = $row['tax_perc'];            
            $newRow['tax_on_perc'] = $row['tax_on_perc'];            
            $newRow['tax_on_min_amt'] = $row['tax_on_min_amt'];          
            $newRow['tax_on_max_amt'] = $row['tax_on_max_amt'];            
            $newRow['min_tax_amt'] = $row['min_tax_amt'];  
            $newRow['max_tax_amt'] = $row['max_tax_amt'];            
            $newRow['custom_rate'] = 0;                               
            $newRow['tax_amt'] = 0;  
            array_push($tax_detail_temp, $newRow);
        }
        
        return $tax_detail_temp;
    }
    private static function ResolveBase($base_amt, $detail_row, $tax_applied_tran){
        $result_amt = 0;
        $parent_tax_details = array();
        
        $parent_tax_details = explode(',', $detail_row['parent_tax_details']);
        if(count($parent_tax_details) > 0){
            // When parent Items are involved
            foreach($parent_tax_details as $tax_detail_id){
                if($tax_detail_id == 0){ // Base Amt
                    $result_amt += $base_amt;
                }
                else if ($tax_detail_id > 0){ // Parent Tax Amt
                    $drParentTax = Enumerable::from($tax_applied_tran->Rows())->where('$a==>$a["tax_detail_id"]=='.$tax_detail_id)->toList();  
                    if(count($drParentTax) == 1){
                        $result_amt += $drParentTax[0]['tax_amt'];
                    }
                    else{
                        throw new \Exception('Parent Tax Item is not a part of the Tax Schedule. Failed to resolve Base Amt.');
                    }
                }
            }
        }
        else{ // When there are not parents, the base is understood to be the parent
            $result_amt = $base_amt;
        }
        return $result_amt;
    }
    
   /*   This method calculates the Item Tax(it inturn calls various methods for calculations) */    
    private static function CalculateTaxItem($base_amt, $qty, $detail_row, $tax_schedule_id, $tax_applied_tran){
        //  ****     Resolve the Base Amt First
        $base_amt = self::CalculateBase($base_amt, $detail_row);
                
        //  ****    Calculate Tax Amt
        $tax_amt = self::CalculateTaxAmt($base_amt, $qty, $detail_row);
        
        //  ****    Round Off Calculated Tax
        $tax_amt = self::RoundOffTax($tax_amt, $detail_row);
        
        return $tax_amt;
    }
    
    private static function CalculateBase($base_amt, $detail_row){
        // ****     Apply Percentage
        $base_amt = $base_amt * $detail_row['tax_on_perc'] / 100;
        
        // ****     If max is mentioned, restrict base to max
        if($detail_row['tax_on_max_amt'] > 0){            
            If($base_amt > $detail_row['tax_on_max_amt']){
                $base_amt = $detail_row['tax_on_max_amt'];
            }
        }
        
        // ****     If min is mentioned, ensure base is not less than min
        If($base_amt < $detail_row['tax_on_min_amt']){
            $base_amt = 0;
        }
        
        Return $base_amt;
    }
    
    /*This method calculates the Actual Tax*/
    Private static function CalculateTaxAmt($base_amt, $Qty, $detail_row){
        $tax_amt =0;
        
        if($detail_row['en_tax_type'] == self::CUSTOM_PERCENT_OF_AMOUNT){    
            $tax_amt = $base_amt * $detail_row['custom_rate']  /100;
        }
        else if ($detail_row['en_tax_type'] == self::CUSTOM_ABSOLUTE_AMOUNT){ 
            $tax_amt = $detail_row['custom_rate'];
        }
        else{            
                    // ****     Apply Percentage
            $tax_amt = $base_amt * $detail_row['tax_perc'] / 100;

            // ****     If max is mentioned, restrict base to max
            if($detail_row['max_tax_amt'] > 0){            
                If($tax_amt > $detail_row['max_tax_amt']){
                    $tax_amt = $detail_row['max_tax_amt'];
                }
            }

            // ****     If min is mentioned, ensure base is not less than min
            If($tax_amt < $detail_row['min_tax_amt']){
                $tax_amt = $detail_row['min_tax_amt'];
            }

        }
        return $tax_amt;
    }
    
    
   private static function RoundOffTax($tax_amt, $detail_row){
       if($detail_row['en_round_type'] == self::ROUND_OFF_TENTH_DECIMAL){
           return round($tax_amt, 1);
       }
       else if ($detail_row['en_round_type'] == self::ROUND_OFF_WHOLE_DIGIT) {
           return round($tax_amt, 0);
       }
       else if($detail_row['en_round_type'] == self::DO_NOT_ROUND){
           return round($tax_amt, \app\cwf\vsla\Math::$amtScale);
       }
       else if($detail_row['en_round_type'] == self::ROUND_UP_WHOLE_DIGIT){
           return ceil($tax_amt);
       }
       else if($detail_row['en_round_type'] == self::ROUND_DOWN_WHOLE_DIGIT){
           return floor($tax_amt);
       }
       else{
           return round($tax_amt, \app\cwf\vsla\Math::$amtScale);
       }
   }
   
   
}

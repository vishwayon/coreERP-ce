<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


namespace app\cwf\vsla\utils;


class AmtInWords {
   
    const CURRENCY_SYSTEM_lAKHS = 1;    
    const CURRENCY_SYSTEM_MILLIONS = 2;
    
    public static function GetAmtInWords($value, $currency, $subCurrency, $currency_system){
        
        if($currency_system == self::CURRENCY_SYSTEM_lAKHS){
            return self::AmountInLakhs($value, $currency, $subCurrency);
        }
        else if($currency_system == self::CURRENCY_SYSTEM_MILLIONS){
            return self::AmountInMillions((string)$value, $currency, $subCurrency);
        }
        else{
            
        }
    } 
    
    private static function AmountInLakhs($value, $currency, $subCurrency){
        
        $strVal="";
        $strInt=0;
        $decVal=0;
        $decStr="";
        $numOut="";
        $decIn="";
        $numberText="";
        $strInt=  floor($value);
        $strVal=(string)$strInt;
        $decVal=sprintf ("%.".\app\cwf\vsla\Math::$amtScale."f", round(($value- $strInt), \app\cwf\vsla\Math::$amtScale) );
        $decStr=(string)$decVal;
        $numOut=Self::NumCon($strVal);
        $numberText=$numOut;
        $left=""; 
        $mid="";
        $right="";
        $decimalPlaces=  \app\cwf\vsla\Math::$amtScale;
        if($decVal > 0){
            $decIn =  substr($decStr, strlen($decStr)-$decimalPlaces, $decimalPlaces);
            $numOut=Self::NumCon($decIn);
            if($numberText != ""){
             $numberText= $currency . " " . $numberText . " And "  . $numOut . " " . $subCurrency . " Only ";
             $left=substr($numberText, 0, 1);
             $mid= substr($numberText, 1, strlen($numberText)-1);
             $numberText= strtoupper($left) . strtolower($mid);
            } 
            else{
                $numberText= $numOut . " " . $subCurrency . " only ";
                $left = substr($numberText, 0, 1);
                $mid=substr($numberText, 1, strlen($numberText)-1);
                $numberText= strtoupper($left) . strtolower($mid);
            }               
        }
        else{
                $numberText= $currency . " " . $numberText . " only ";
                $left = substr($numberText, 0, 1);
                $mid=substr($numberText, 1, strlen($numberText)-1);
                $numberText= strtoupper($left) . strtolower($mid);            
        }
        return $numberText;
    }
    
    private static function AmountInMillions($amt, $currency, $subCurrency){
        $inWords="";
        $intNum="";
        $decNum=0;
        $qtr1="";
        $patDot=false;
        $decimalPlaces=\app\cwf\vsla\Math::$amtScale;
        $patDot=strpos($amt, '.');
        if($patDot == false){
            $intNum=$amt;
        }
        else{
            $intNum=substr($amt, 0, strpos($amt, '.'));
            $decNum=(int)substr($amt, strpos($amt, '.')+1, \app\cwf\vsla\Math::$amtScale);                       
        }
        
        if($decNum!=0){
            $inWords= $currency . " " . self::NumConInMillion($intNum) . " And " . self::NumConInMillion((string)$decNum) . $subCurrency . " Only";
        }
        else {
            $inWords= $currency . " " . self::NumConInMillion($intNum). " Only ";
        }
                
        return $inWords;
    }
    
    private static function NumCon($number){
       $numbStr=$number;
       $count=0;
       $pos1=0;
       $pos2=0;
       $pos3=0;
       $pos4=0;
       $pos5=0;
       $pos6=0;
       $pos7=0;
       $pos8=0;
       $pos9=0;
       $pos10=0;
       $pos11=0;
       $strTens="";
       $strUnit="";
       $inWords="";
       
       $count=(int) strlen($numbStr);
       if($count==1){
           $numbStr = "0000000000". $numbStr;
       }
       if($count==2){
           $numbStr = "000000000". $numbStr;
       }
       if($count==3){
           $numbStr = "00000000". $numbStr;
       }
       if($count==4){
           $numbStr = "0000000". $numbStr;
       }
       if($count==5){
           $numbStr = "000000". $numbStr;
       }
       if($count==6){
           $numbStr = "00000". $numbStr;
       }
       if($count==7){
           $numbStr = "0000". $numbStr;
       }
       if($count==8){
           $numbStr = "000". $numbStr;
       }
       if($count==9){
           $numbStr = "00". $numbStr;
       }
       if($count==10){
           $numbStr = "0". $numbStr;
       }
       
       $pos1 = (int)substr($numbStr, 10, 1);
       $pos2 = (int)substr($numbStr, 9, 1);
       $pos3 = (int)substr($numbStr, 8, 1);
       $pos4 = (int)substr($numbStr, 7, 1);
       $pos5 = (int)substr($numbStr, 6, 1);
       $pos6 = (int)substr($numbStr, 5, 1);
       $pos7 = (int)substr($numbStr, 4, 1);
       $pos8 = (int)substr($numbStr, 3, 1);
       $pos9 = (int)substr($numbStr, 2, 1);
       $pos10 = (int)substr($numbStr, 1, 1);
       $pos11 = (int)substr($numbStr, 0, 1);
       
       if($pos11 > 0){
            $strUnit=self::AmtInUnit($pos11);
            $inWords = $strUnit . "Thousand ";
       } 
       
       if($pos11 > 0  && $pos10 >0 ){
            $strUnit=self::AmtInUnit($pos11);
            $inWords = $strUnit . "Thousand ";
            $strUnit=self::AmtInUnit($pos10);
            $inWords = $inWords . $strUnit . "Hundred ";
       } 
       
       if($pos11 == 0 && $pos10 >0 ){
            $strUnit=self::AmtInUnit($pos10);
            $inWords = $inWords . $strUnit . "Hundred ";
       } 
       
       if($pos9 > 0 && $pos8 > 0){
            $strTens=self::AmtInTens($pos9, $pos8);
            $inWords = $inWords . $strTens . "Crores ";
       }  
       
       if($pos9 > 0 && $pos8 == 0){
            $strTens=self::AmtInTens($pos9, $pos8);
            $inWords = $inWords . $strTens . "Crores ";
       } 
       
       if($pos9 == 0 && $pos8 > 0){
            $strUnit=self::AmtInUnit($pos8);
            if($pos8 != 1){
                $inWords = $inWords . $strUnit . "Crores ";           
            }
            else{
                $inWords = $inWords . $strUnit . "Crore ";           
            }
       } 
       
       if($pos9 == 0 && $pos8 == 0){
            if($inWords != ""){
                $inWords = $inWords . "Crores ";           
            }
            else{
                $inWords = "";           
            }
       }    
              
        $strTens=self::AmtInTens($pos7, $pos6);
        if($pos7 > 0){
            $inWords = $inWords . $strTens . "Lakhs ";          
        }
        
        if($pos7 == 0 && $pos6 == 1){
            $inWords = $inWords . $strTens . "Lakh ";
        }  
        
        if($pos7 == 0 && $pos6 > 1){
            $inWords = $inWords . $strTens . "Lakhs ";
        }            
              
        $strTens=self::AmtInTens($pos5, $pos4);        
        if($pos5 != 0 || $pos4 != 0){
            $inWords = $inWords . $strTens . "Thousand ";
        }  
        else{
            $inWords = $inWords . $strTens ;
        }  
        
        if($pos3 > 0){
            $strUnit= self::AmtInUnit($pos3);
            $inWords = $inWords . $strUnit . "Hundred ";
        }         
        if(($pos2 > 0 && $pos1 > 0) || ($pos2 > 0 && $pos1 == 0)){
            $strTens=self::AmtInTens($pos2, $pos1);
            $inWords = $inWords . $strTens ;
        }       
        if($pos2 == 0 && $pos1 > 0){
            $strUnit=self::AmtInUnit($pos1);
            $inWords = $inWords . $strUnit ;
        } 
        
        $inWords = rtrim($inWords);
        return $inWords;
    }
    
    private static function AmtInUnit($pos){
        $strAmtInUnit="";
        if($pos==0){
            $strAmtInUnit="";
        }
        if($pos==1){
            $strAmtInUnit="One ";
        }
        if($pos==2){
            $strAmtInUnit="Two ";
        }
        if($pos==3){
            $strAmtInUnit="Three ";
        }
        if($pos==4){
            $strAmtInUnit="Four ";
        }
        if($pos==5){
            $strAmtInUnit="Five ";
        }
        if($pos==6){
            $strAmtInUnit="Six ";
        }
        if($pos==7){
            $strAmtInUnit="Seven ";
        }
        if($pos==8){
            $strAmtInUnit="Eight ";
        }
        if($pos==9){
            $strAmtInUnit="Nine ";
        }
        return $strAmtInUnit;
    }
    
    private static function AmtInTens($pos2, $pos1){
        $unit = "";
        $stAmtInTens = "";
        
        if($pos2 == 0){
         $unit = self::AmtInUnit($pos1);
         $strAmtInTens = $unit;
        }  
        if($pos2 == 1){
         $unit = self::AmtInTeens($pos1);
         $strAmtInTens = $unit;
        }  
        if($pos2 == 2){
         $unit = self::AmtInUnit($pos1);
         $strAmtInTens = "Twenty " . strtolower($unit);
        }   
        if($pos2 == 3){
         $unit = self::AmtInUnit($pos1);
         $strAmtInTens = "Thirty " . strtolower($unit);
        }   
        if($pos2 == 4){
         $unit = self::AmtInUnit($pos1);
         $strAmtInTens = "Forty " . strtolower($unit);
        }   
        if($pos2 == 5){
         $unit = self::AmtInUnit($pos1);
         $strAmtInTens = "Fifty " . strtolower($unit);
        }   
        if($pos2 == 6){
         $unit = self::AmtInUnit($pos1);
         $strAmtInTens = "Sixty " . strtolower($unit);
        }   
        if($pos2 == 7){
         $unit = self::AmtInUnit($pos1);
         $strAmtInTens = "Seventy " . strtolower($unit);
        }   
        if($pos2 == 8){
         $unit = self::AmtInUnit($pos1);
         $strAmtInTens = "Eighty " . strtolower($unit);
        }   
        if($pos2 == 9){
         $unit = self::AmtInUnit($pos1);
         $strAmtInTens = "Ninety " . strtolower($unit);
        }     
        return $strAmtInTens;
    }
    
    private static function AmtInTeens($pos1){
        $strAmtInTeens = "";
        if($pos1 == 10 || $pos1 == 0){
            $strAmtInTeens = "Ten ";
        }
        if($pos1 == 11 || $pos1 == 1){
            $strAmtInTeens = "Eleven ";
        }
        if($pos1 == 12|| $pos1 == 2){
            $strAmtInTeens = "Twelve ";
        }
        if($pos1 == 13 || $pos1 == 3){
            $strAmtInTeens = "Thirteen ";
        }
        if($pos1 == 14 || $pos1 == 4){
            $strAmtInTeens = "Fourteen ";
        }
        if($pos1 == 15 || $pos1 == 5){
            $strAmtInTeens = "Fifteen ";
        }
        if($pos1 == 16 || $pos1 == 6){
            $strAmtInTeens = "Sixteen ";
        }
        if($pos1 == 17 || $pos1 == 7){
            $strAmtInTeens = "Seventeen ";
        }
        if($pos1 == 18 || $pos1 == 8){
            $strAmtInTeens = "Eighteen ";
        }
        if($pos1 == 19 || $pos1 == 9){
            $strAmtInTeens = "Nineteen ";
        }
        
        return $strAmtInTeens;
    }
    
    private static function NumConInMillion($number){
        $count=0;
        $inWords="";
        $curPos=0;
        $loops = 0;
        $bit=false;
        $qtr1="";
        
        $count=(int)strlen($number);
        
        if(($count % 3) == 0){
            $loops = (int)($count / 3);            
        }
        else{
            $loops = (int)((($count) - ($count % 3)) / 3);
            $loops = (int)$loops+1;            
        }
        
        while ($loops > 0) {
           if($count % 3 > 0 && $bit == false) {
               $qtr1 = substr($number, $curPos, $count % 3);
               $curPos= (int) ($curPos + ($count % 3));
               $bit=true;
           }
           else{
               $qtr1 = substr($number, $curPos, 3);
               $curPos= (int) ($curPos + 3);
           }
           
           if((float) $qtr1 > 0){
               if(($loops % 2) == 0){
                   $inWords = $inWords . self::AmountUptoHundred($qtr1) . " Thousand ";
               }
               else{
                   if($loops == 1){
                      $inWords = $inWords . self::AmountUptoHundred($qtr1);
                   }
                   else{
                       $inWords = $inWords . self::AmountUptoHundred($qtr1) . " Million ";
                   }
               }
           }
           $loops = (int) $loops - 1;
        }
        return $inWords;
    }
    
    private static function AmountUptoHundred($orgNum){
        $count=0;
        $strTeens="";
        $strTens="";
        $strUnits="";
        $strHundred="";
        $inWords="";
        $count=(int)  strlen($orgNum);
        
        // **** For Units
        if($count == 1){
            $strUnits = self::AmtInUnit((int)$orgNum);
            $inWords = $strUnits;
        }
        
        if($count == 2){
            
            // **** For Teens
            if(((int)$orgNum) < 20){
                $strTeens = self::AmtInTeens((int)$orgNum);
                $inWords = $strUnits;            
            }
            else{
                // **** For Tens
                $strUnits = self::AmtInUnit((int)substr($orgNum, 1, 1));
                $strTens = self::AmtInTens(((int)substr($orgNum, 0, 1)), 0);
                $inWords = $strTens . " " . $strUnits;
            }
        }
        
        // **** For Hundreds
        if($count == 3){
            
            // **** For Units
            if(((int)substr($orgNum, 1, 2)) < 10){
                $strUnits = self::AmtInUnit((int)substr($orgNum, 1, 2));
            }
            
            // **** For Teens
            if((((int)substr($orgNum, 1, 2)) > 9) && (((int)substr($orgNum, 1, 2)) < 20)){
                $strTens = self::AmtInTeens((int)substr($orgNum, 1, 2));
            }
            
            // **** For Tens
            if(((int)substr($orgNum, 1, 2)) > 19){
                $strUnits = self::AmtInUnit((int)substr($orgNum, 2, 1));
                $strTens = self::AmtInTens(((int)substr($orgNum, 1, 1)), 0);
            }
            
            $strHundred = self::AmtInUnit((int)substr($orgNum, 0, 1));
            if(((int)substr($orgNum, 0, 1)) > 0){
               $inWords = $strHundred . "Hundred " . $strTens . $strUnits ;
            }
            else{
                $inWords = $strTens . $strUnits ;
            }
        }
        
        return $inWords;
    }
}
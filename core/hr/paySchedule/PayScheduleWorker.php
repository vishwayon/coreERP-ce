<?php

namespace app\core\pr\paySchedule;

class PayScheduleWorker{
        
//    public  static function CalcOTRateonSelectionChanged($pay_schedule_detail_tran){   
//        $ot_rate = 0;
//        $ot_holiday_rate = 0;
//        $ot_special_rate = 0;
//        $cmm = new \app\cwf\vsla\data\SqlCommand();
//        $cmm->setCommandText('Select payhead_id, incl_in_ot, ot_rate_percent, ot_holiday_rate_percent, ot_special_rate_percent
//                                from pr.payhead where payhead_id = :ppayhead_id');        
//        $cmm->addParam('ppayhead_id', -1);
//        foreach($pay_schedule_detail_tran as $row){
//            $cmm->setParamValue('ppayhead_id', $row['payhead_id']);
//            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
//            
//            if(count($result->Rows()) > 0){
//                if($result->Rows()[0]['incl_in_ot']){
//                    $ot_rate = $ot_rate +($row['amt'] * $result->Rows()[0]['ot_rate_percent'] /100) ;
//                    $ot_holiday_rate = $ot_holiday_rate +($row['amt'] * $result->Rows()[0]['ot_holiday_rate_percent'] /100) ;
//                    $ot_special_rate = $ot_special_rate +($row['amt'] * $result->Rows()[0]['ot_special_rate_percent'] /100) ;
//                }
//            }
//        }
//        
//        $result = array();
//        $result['ot_rate']=$ot_rate;
//        $result['ot_holiday_rate'] = $ot_holiday_rate;
//        $result['ot_special_rate'] = $ot_special_rate;
//        return $result;
//    }
}
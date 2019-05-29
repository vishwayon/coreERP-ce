<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of AttendanceWorker
 *
 * @author valli
 */
namespace app\core\hr\attendance;

class AttendanceWorker {
    //put your code here
    public static function CalculateOvertime($attendancedate,$inhrs,$inmins,$outhrs,$outmins){        
                
        $in_time = date_time_set(new \DateTime($attendancedate), $inhrs, $inmins);
        $out_time = date_time_set(new \DateTime($attendancedate), $outhrs, $outmins);
        $total_time_worked = $out_time->diff($in_time);
        $total_working_hours=0;        
        $normal_overtime=0;
        $sp_ot_hours=0; 
            
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select  holiday_id, holiday_date from hr.holiday_list where holiday_date=:pattendance_date');
        $cmm->addParam('pattendance_date', $attendancedate );
        $dtholidaylist = \app\cwf\vsla\data\DataConnect::getData($cmm);   
        
        $cmm=null;
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select weeklyoff_id, day_of_week, working_hours from hr.weeklyoff where day_of_week=:pdayofweek and overtime_type='Special OT'");
        $cmm->addParam('pdayofweek', date("l", strtotime($attendancedate)));
        $dtweeklyoff = \app\cwf\vsla\data\DataConnect::getData($cmm);              
               
        if(count($dtholidaylist->Rows()) > 0){// If the attendance Day is in holiday list
            $sp_ot_hours = ($total_time_worked->h * 60) + ($total_time_worked->i); 
        }
        else if(count($dtweeklyoff->Rows()) > 0){// If Day does not belong to holiday list then check whether in weekly off list
            $sp_ot_hours = (($total_time_worked->h * 60) + ($total_time_worked->i)) - ($dtweeklyoff->Rows()[0]['working_hours'] * 60);
            if($sp_ot_hours < 0){
                $sp_ot_hours = 0;
            }
        }
        else{  // If the Day does not belong to either Holiday List or weekly off list      
            $total_working_hours = 8;
            $sp_ot_starttime = 21;
            $sp_ot_starttime_date = date_time_set(new \DateTime($attendancedate), $sp_ot_starttime, 0);
            if (($total_time_worked->h >= $total_working_hours))
            {
                if($total_time_worked->i > 0){
                    
                }
                if ($out_time->getTimestamp() > $sp_ot_starttime_date->getTimestamp())// If worked after start of Special OT time (9 pm)
                {
                    $sp_ot_working_hours = $out_time->diff($sp_ot_starttime_date);
                    $sp_ot_hours = ($sp_ot_working_hours->h * 60) + $sp_ot_working_hours->i ;
                    $normal_overtime = (($total_time_worked->h * 60 ) + ($total_time_worked->i))- (($total_working_hours * 60) + $sp_ot_hours);
                }
                else
                {            
                    $normal_overtime= (($total_time_worked->h - $total_working_hours) * 60)  + $total_time_worked->i; 
                }
            }        
        }
           
        $result = array();         
        $result['overtime'] =  $normal_overtime;
        $result['ot_special'] =  $sp_ot_hours;
        $result['status']='ok';
        return $result;
    }
}

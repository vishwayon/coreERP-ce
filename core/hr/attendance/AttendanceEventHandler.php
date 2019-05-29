<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\attendance;

/**
 * Description of AttendanceEventHandler
 *
 * @author Valli
 */

class AttendanceEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        if ($this->bo->attendance_id ==-1)
        {
            $this->bo->in_hrs=0;
            $this->bo->in_mins=0;  
            $this->bo->out_hrs=0;
            $this->bo->out_mins=0;    
            if(strtotime($this->bo->attendance_date) > strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))){
                $this->bo->attendance_date= \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end');
            } 
            $this->bo->finyear = \app\cwf\vsla\security\SessionManager::getSessionVariable('finyear');
        }
        else
        {
            $this->bo->in_hrs=substr($this->bo->in_time,0,strpos($this->bo->in_time,":"));
            $this->bo->in_mins=substr($this->bo->in_time,strpos($this->bo->in_time,":")+1,strlen($this->bo->in_time));  
            $this->bo->out_hrs=substr($this->bo->out_time,0,strpos($this->bo->out_time,":"));
            $this->bo->out_mins=substr($this->bo->out_time,strpos($this->bo->out_time,":")+1,strlen($this->bo->out_time));   
        }
    }

    public function beforeSave($cn) {            
        parent::beforeSave($cn);
        $str_in_mins = (string)$this->bo->in_mins;
        $str_out_mins = (string)$this->bo->out_mins;
        if(strlen($str_in_mins) == 1){
            $str_in_mins = str_pad($str_in_mins, 2, '0', STR_PAD_LEFT);
        }
        if(strlen($str_out_mins) == 1){
            $str_out_mins = str_pad($str_out_mins, 2, '0', STR_PAD_LEFT);
        }
        $this->bo->in_time=$this->bo->in_hrs.":".$str_in_mins;
        $this->bo->out_time=$this->bo->out_hrs.":".$str_out_mins;
    }
    
    
}

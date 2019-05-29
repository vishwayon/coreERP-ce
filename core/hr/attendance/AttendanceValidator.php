<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\attendance;

use YaLinqo\Enumerable;

/**
 * Description of AttendanceValidator
 *
 * @author Valli
 */
class AttendanceValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {

    public function validateAttendanceEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);

        // conduct business rule validations
        $this->validateBusinessRules();
    }

    private function validateBusinessRules() {
        // Validate duplicate attendance exists for employee

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        if ($this->bo->attendance_id == -1) {
            $cmm->setCommandText('Select count(*) as rec_count from hr.attendance where attendance_date=:pattendance_date and employee_id=:pemployee_id');
        } else {
            $cmm->setCommandText('Select count(*) as rec_count from hr.attendance where attendance_id!=:pattendance_id and attendance_date=:pattendance_date and employee_id=:pemployee_id');
            $cmm->addParam('pattendance_id', $this->bo->attendance_id);
        }
        $cmm->addParam('pattendance_date', $this->bo->attendance_date);
        $cmm->addParam('pemployee_id', $this->bo->employee_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            if ((int) $result->Rows()[0]['rec_count'] > 0) {
                $this->bo->addBRule('Attendance already exists for this employee. Duplicate not allowed.');
            }
        }

        if ($this->bo->in_hrs <= 0 || $this->bo->in_hrs > 23) {
            $this->bo->addBRule('Incorrect In Time Hrs. Enter Between 0-23');
        }

        if ($this->bo->in_mins < 0 || $this->bo->in_mins > 59) {
            $this->bo->addBRule('Incorrect In Time Mins. Enter Between 0-59');
        }

        if ($this->bo->out_hrs <= 0 || $this->bo->out_hrs > 23) {
            $this->bo->addBRule('Incorrect Out Time Hrs. Enter Between 0-23');
        }

        if ($this->bo->out_mins < 0 || $this->bo->out_mins > 59) {
            $this->bo->addBRule('Incorrect Out Time Mins. Enter Between 0-59');
        }

        if (((int)$this->bo->out_hrs <= (int)$this->bo->in_hrs) && ((int)$this->bo->out_mins <= (int)$this->bo->in_mins)) {
            $this->bo->addBRule('Incorrect Out Time. Out Time Hrs cannot be same or less than In Time Hrs. ');
        }
        $result = AttendanceWorker::CalculateOvertime($this->bo->attendance_date, $this->bo->in_hrs, $this->bo->in_mins, $this->bo->out_hrs, $this->bo->out_mins);

        $this->bo->overtime = $result['overtime'];
        $this->bo->ot_special = $result['ot_special'];
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select count(*) as rec_count from hr.payroll_tran a
                            inner join hr.payroll_control b on a.payroll_id = b.payroll_id
                            where employee_id=:pemployee_id And :pattendance_date between b.pay_from_date and b.pay_to_date ');
        $cmm->addParam('pattendance_date', $this->bo->attendance_date);
        $cmm->addParam('pemployee_id', $this->bo->employee_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows()) > 0){
            if($result->Rows()[0]['rec_count'] > 0){
                $this->bo->addBRule('Payroll already generated for the month. Cannot edit Attendance.');
            }
        }
    }
    
    public function validateBeforeDelete() {
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select count(*) as rec_count from hr.payroll_tran a
                            inner join hr.payroll_control b on a.payroll_id = b.payroll_id
                            where employee_id=:pemployee_id And :pattendance_date between b.pay_from_date and b.pay_to_date ');
        $cmm->addParam('pattendance_date', $this->bo->attendance_date);
        $cmm->addParam('pemployee_id', $this->bo->employee_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows()) > 0){
            if($result->Rows()[0]['rec_count'] > 0){
                $this->bo->addBRule('Payroll already generated for the month. Cannot delete Attendance.');
            }
        }
    }
}

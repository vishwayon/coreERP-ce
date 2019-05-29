<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\hr\gratuity;

/**
 * Description of GratuityEventHandler
 *
 * @author Valli
 */
class GratuityEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);

        if ($this->bo->gratuity_id == "" or $this->bo->gratuity_id == "-1") {
            $this->bo->gratuity_id = "";
            $this->bo->status = 0;
            $this->bo->employee_id = $criteriaparam['formData']['SelectEmployee']['employee_id'];
            $this->bo->gratuity_from_date = worker\GratuityWorker::GetEmployeeJoiningDate($this->bo->employee_id);
        } else {
            $this->bo->two_years_wages_amt = worker\GratuityWorker::GetTwoYearsWagesAmt($this->bo->employee_id, $this->bo->gratuity_to_date);
        }

        $emp_service_years = worker\GratuityWorker::GetContinousServiceYearforGratuity($this->bo->employee_id, $this->bo->gratuity_from_date, $this->bo->gratuity_to_date);

        if (($emp_service_years['service_year']) > 1) {
            $this->bo->gratuity_note = "Employee Completed his One Year";
        } else {
            $this->bo->gratuity_note = "Employee has not Completed his One Year of Service. Hence Not Entitled for Gratuity... ";
        }
    }

    public function beforeSave($cn) {
        parent::beforeSave($cn);
    }

}

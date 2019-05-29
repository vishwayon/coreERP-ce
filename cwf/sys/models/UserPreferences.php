<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\models;
/**
 * Description of UserSettings
 *
 * @author girish
 */
class UserPreferences extends \yii\base\Model {
    
    public $company_id=-1;
    public $branch_id=-1;
    public $finyear_id=-1;
    public $finyear;
    
    public $company_detail;
    public $branch_detail;
    public $finyear_detail;
    
    
    
    public function prepareforRender() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select company_id, company_name from sys.company;');
        $this->company_detail = \app\cwf\vsla\data\DataConnect::getData($cmm)->asArray('company_id', 'company_name');
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select branch_id, company_id, branch_name from sys.branch;');
        $this->branch_detail = \app\cwf\vsla\data\DataConnect::getData($cmm)->asArray('branch_id', 'branch_name');
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select finyear_id, company_id, \'Fin Year \' || year_begin || \' \' || year_end as year_desc from sys.finyear;');
        $this->finyear_detail = \app\cwf\vsla\data\DataConnect::getData($cmm)->asArray('finyear_id', 'year_desc');
    }
    
}

<?php

/**
 * Description of BankBalance
 *
 * @author dev
 */

namespace app\core\ac\dashboard\BankBalance;

use \YaLinqo\Enumerable;

class BankBalance {
    
    var $rptOption;
    
    public function __construct() {
        $rptOption = new \app\cwf\vsla\render\RptOption();
        $rptOption->rptParams['pcompany_id'] = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID();
        $rptOption->rptParams['pbranch_id'] = 0;
        $rptOption->rptParams['pmaterial_id'] = 0;
        $rptOption->rptParams['pfinyear'] = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('finyear');
        $rptOption->rptParams['pfrom_date'] = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('year_begin');
        $rptOption->rptParams['pto_date'] = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('year_end');
        $this->rptOption = $rptOption;
    }
    
    public function init() {
        $this->getOpeningStock();
        $this->getMovementValue();
        $this->getClosingStock();
    }
    
    public $openingStockValue=0;
    private function getOpeningStock() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select Sum(balance_qty_base * rate) as op_st_val From st.fn_material_balance_wac(:pcompany_id, :pbranch_id, :pmaterial_id, :pfinyear, :pto_date)');
        $cmm->addParam('pcompany_id', $this->rptOption->rptParams['pcompany_id']);
        $cmm->addParam('pbranch_id', $this->rptOption->rptParams['pbranch_id']);
        $cmm->addParam('pmaterial_id', $this->rptOption->rptParams['pmaterial_id']);
        $cmm->addParam('pfinyear', $this->rptOption->rptParams['pfinyear']);
        // Calculate 1 day prior to from date
        $fromDate = new \DateTime($this->rptOption->rptParams['pfrom_date']);
        $fromDate->sub(new \DateInterval('P1D'));
        $cmm->addParam('pto_date', $fromDate->format('Y-m-d'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows())==1) {
            $this->openingStockValue = $dt->Rows()[0]['op_st_val'];
        }
    }
    
    public $closingStockValue=0;
    private function getClosingStock() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select Sum(balance_qty_base * rate) as cl_st_val From st.fn_material_balance_wac(:pcompany_id, :pbranch_id, :pmaterial_id, :pfinyear, :pto_date)');
        $cmm->addParam('pcompany_id', $this->rptOption->rptParams['pcompany_id']);
        $cmm->addParam('pbranch_id', $this->rptOption->rptParams['pbranch_id']);
        $cmm->addParam('pmaterial_id', $this->rptOption->rptParams['pmaterial_id']);
        $cmm->addParam('pfinyear', $this->rptOption->rptParams['pfinyear']);
        $cmm->addParam('pto_date', $this->rptOption->rptParams['pto_date']);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows())==1) {
            $this->closingStockValue = $dt->Rows()[0]['cl_st_val'];
        }
    }
    
    // Receipts
    public $purchases=[];
    public $stockTransferIn=0;
    public $adjustments=[];
    // Issues
    public $consumption=[];
    private function getMovementValue() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $query = 'Select stock_movement_type_id, stock_movement_type, Sum(mat_value) as mat_value'.
                 ' From st.fn_stock_move_by_type_value(:pcompany_id, :pbranch_id, :pmaterial_id, :pfinyear, :pfrom_date, :pto_date)'.
                 ' Group by stock_movement_type_id, stock_movement_type'.
                 ' Order by stock_movement_type';
        $cmm->setCommandText($query);
        $cmm->addParam('pcompany_id', $this->rptOption->rptParams['pcompany_id']);
        $cmm->addParam('pbranch_id', $this->rptOption->rptParams['pbranch_id']);
        $cmm->addParam('pmaterial_id', $this->rptOption->rptParams['pmaterial_id']);
        $cmm->addParam('pfinyear', $this->rptOption->rptParams['pfinyear']);
        $cmm->addParam('pfrom_date', $this->rptOption->rptParams['pfrom_date']);
        $cmm->addParam('pto_date', $this->rptOption->rptParams['pto_date']);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        
        // Purchases (1,3)
        $this->purchases = Enumerable::from($dt->Rows())->where('$a==>$a["stock_movement_type_id"]==1 Or $a["stock_movement_type_id"]==3')->toArray();
        //Stock Transfer In (9)
        $this->stockTransferIn = Enumerable::from($dt->Rows())->where('$a==>$a["stock_movement_type_id"]==9')->sum('$a==>$a["mat_value"]');
        //Adjustment (4)
        $this->adjustments = Enumerable::from($dt->Rows())->where('$a==>$a["stock_movement_type_id"]==4')->toArray();
        
        //Consumption - Everything else
        $this->consumption = Enumerable::from($dt->Rows())->where('$a==>$a["stock_movement_type_id"]!=1 And $a["stock_movement_type_id"]!=3 And $a["stock_movement_type_id"]!=9 And $a["stock_movement_type_id"]!=4')->toArray();
        
    }
    
    public function getSum($source, $field) {
        $result = 0;
        foreach($source as $item) {
            $result += floatval($item[$field]);
        }
        return $result;
    }
    
}


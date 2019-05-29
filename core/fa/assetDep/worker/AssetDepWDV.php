<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\assetDep\worker;

class AssetDepWDV extends AssetDepBase{
    
    const UNKNOWN = -1;
    const YEARRATE_PARTPERIOD = 0;
    const BALANCERATE_PERIOD = 1;
    
    PRIVATE static $wdvCalcMethod = self::BALANCERATE_PERIOD;
    private $dtDepOpBal=null;
    
    public function __construct($parent_worker) {
        parent::__construct($parent_worker, AssetDepBase::WRITTEN_DOWN_VALUE_METHOD);
        
//        if(self::$wdvCalcMethod == self::UNKNOWN){
//            $cmm=new \app\cwf\vsla\data\SqlCommand();
//            $cmm->setCommandText('select year_begins, year_ends from sys.finyear where company_id=:pcompany_id and finyear_code=:pfinyear_code');
//            $cmm->addParam('pcompany_id', $this->parent_worker->company_id);
//            $cmm->addParam('pfinyear_code', $this->parent_worker->finyear);
//            $dtbr= \app\cwf\vsla\data\DataConnect::getData($cmm);            
//        }
    }
    
    public function Initialise($asset_book_id, $from_date, $to_date) {
        parent::Initialise($asset_book_id, $from_date, $to_date);
        
         if(self::$wdvCalcMethod == self::YEARRATE_PARTPERIOD){
            $this->dtDepOpBal= new \app\cwf\vsla\data\DataTable();
            
            $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8');
            $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
            $scale = 0;
            $isUnique = false;
            $this->dtDepOpBal->addColumn('asset_item_id', $phpType, $default, 0, $scale, $isUnique);

            $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('numeric');
            $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
            $scale = 0;
            $isUnique = false;
            $this->dtDepOpBal->addColumn('dep_amt_op_bal', $phpType, $default, 0, $scale, $isUnique);
            
            $cmm=new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select * from fa.sp_dep_wdv_op_bal(:pcompany_id, :pbranch_id, :passet_book_id, :pfinyear');
            $cmm->addParam('pcompany_id', $this->parent_worker->company_id);
            $cmm->addParam('pbranch_id', $this->parent_worker->branch_id);
            $cmm->addParam('passet_book_id', $asset_book_id);
            $cmm->addParam('pfinyear', $this->parent_worker->finyear);
            $this->dtDepOpBal= \app\cwf\vsla\data\DataConnect::getData($cmm);
         }
    }
    
    public function GetDepAmt($asset_item_row) {
        parent::GetDepAmt($asset_item_row);
        $dep_amt=0;
        $dep_rate =  parent::GetDepRate($asset_item_row['asset_class_id']);
        $dep_days = parent::GetDepDays($asset_item_row);
        
        if(self::$wdvCalcMethod == self::YEARRATE_PARTPERIOD){
            // Here we calculate depreciation on Purchase Amt reduced by the Depreciation upto the begining of Fin Year.
            $base_amt=$asset_item_row['purchase_amt'] - $this->GetDepAmtOpBal($asset_item_row['asset_item_id']);
            $gross_dep_amt= $base_amt * $dep_rate / 100;
            $dep_amt= round(($gross_dep_amt/ parent::$days_in_year) * $dep_days, \app\cwf\vsla\Math::$amtScale);        
        }
        if(self::$wdvCalcMethod == self::BALANCERATE_PERIOD){
            // Here we calculate depreciation on Balance Amt (would result in a smaller amt of dep over the same depriod.
            $gross_dep_amt= $asset_item_row['balance_amt'] * $dep_rate / 100;
            $dep_amt= round(($gross_dep_amt/ parent::$days_in_year) * $dep_days, \app\cwf\vsla\Math::$amtScale);        
        }
        return $dep_amt;
    }
    
    private function GetDepAmtOpBal($asset_item_id){
        $dr;
        $count=0;
        foreach($this->dtDepOpBal->Rows() as $row){
            if($row['asset_item_id'] == $asset_item_id){
                $count=$count+1;
                if($count==1){
                    $dr=$row;
                }
            }
        }
        
        if($count==1){
            return (float)$dr[dep_amt_op_bal];
        }
        
        return 0;
    }
}
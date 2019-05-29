<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\assetDep\worker;

class AssetClassBookTable{
        public $asset_class_id;
        public $asset_class;
        public $dep_account_id;
        public $acc_dep_account_id;
        public $asset_book_id;
        public $en_dep_method;
        public $dep_rate;        
    }

class AssetDepBase{    
    
    const NOT_APPLICABLE = 0;
    const STRAIGHT_LINE_METHOD = 1;
    const WRITTEN_DOWN_VALUE_METHOD = 2;
    
    protected $parent_worker = null;
    protected $dep_method= self::NOT_APPLICABLE;
    protected $asset_book_id =-1;
    protected $from_date, $to_date;
    
    protected static $days_in_year=-1;
    
    public function __construct($parent_worker, $dep_method) {
        $this->parent_worker=$parent_worker;
        $this->dep_method=$dep_method;
        
        if(self::$days_in_year==-1){
            $cmm=new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select year_begin, year_end from sys.finyear where company_id=:pcompany_id and finyear_code=:pfinyear_code');
            $cmm->addParam('pcompany_id', $this->parent_worker->company_id);
            $cmm->addParam('pfinyear_code', $this->parent_worker->finyear);
            $dtbr= \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($dtbr->Rows())>0){
                $todate=new \DateTime($dtbr->Rows()[0]['year_end']);
                $fromdate=new \DateTime($dtbr->Rows()[0]['year_begin']);
                $datediff = $todate->diff($fromdate);
                self::$days_in_year= $datediff->days+1;
            }
        }
    }
    
    public function Initialise($asset_book_id, $from_date, $to_date){
        $this->asset_book_id=$asset_book_id;
        $this->from_date=$from_date;
        $this->to_date=$to_date;
    }
    
    public function GetDepAmt($asset_item_row){
        
    }
    
    protected function GetDepRate($asset_class_id){        
        $dr;
        $count=0;
        foreach($this->parent_worker->dtAssetClassBook->Rows() as $row){
            if($row['asset_class_id']==$asset_class_id && $row['asset_book_id'] == $this->asset_book_id && $row['en_dep_method'] == $this->dep_method){                
                $count=$count+1;
                if($count==1){                    
                    $dr=$row;
                }
            }
        }
        
        if($count==1){
            return (float)$dr['dep_rate'];
        }
        if($count==0){
            return 0;
        }
        throw new \Exception("Multiple depreciation rates found for the method " . $this->dep_method . " for Asset Class ID ". $asset_class_id . " in Asset Book ID " . $this->asset_book_id);;
    }
    
    protected function GetDepDays($asset_item_row){
        if(strtotime($asset_item_row['use_start_date']) <= strtotime($this->from_date)){   
            $todate=new \DateTime($this->to_date);
            $fromdate=new \DateTime($this->from_date);
            $datediff = $todate->diff($fromdate);
            return $datediff->days+1;
        }
        if(strtotime($asset_item_row['use_start_date']) > strtotime($this->from_date) && strtotime($asset_item_row['use_start_date']) <= strtotime($this->to_date)){                          
            $todate=new \DateTime($this->to_date);
            $fromdate=new \DateTime($asset_item_row['use_start_date']);
            $datediff = $todate->diff($fromdate);
            return $datediff->days+1;
        }
        return 0;
    }
}
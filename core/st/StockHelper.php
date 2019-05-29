<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\core\st;
/**
 * Description of StockHelper
 *
 * @author priyanka
 */
class StockHelper {
    //put your code here
    
    public static function GetBaseQty($uom_id, $qty){
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from st.sp_get_base_qty(:puom_id, :pqty)');
        $cmm->addParam('puom_id', $uom_id);
        $cmm->addParam('pqty', $qty);
        $dt= \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows())>0){
            return $dt->Rows([0]['pbase_qty']);
        }
        return 0;
    }
}

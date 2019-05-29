<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\glDistribution;
/**
 * Description of GLDistributionHelper
 *
 * @author priyanka
 */
class GLDistributionHelper {
    //put your code here
    
    public static function CreateGLTemp($bo){        
        // Create temp teble for GL Temp
        $bo->gl_temp = new \app\cwf\vsla\data\DataTable();
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->gl_temp->addColumn('branch_id', $phpType, $default, 0, 0, false);
        $bo->gl_temp->addColumn('account_id', $phpType, $default, 0, 0, false);
        $bo->gl_temp->addColumn('index', $phpType, $default, 0, 0, false);
        
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('varchar');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);            
        $bo->gl_temp->addColumn('branch_code', $phpType, $default, 4, 0, false);
        $bo->gl_temp->addColumn('account_head', $phpType, $default, 250, 0, false);
        $bo->gl_temp->addColumn('dc', $phpType, $default, 1, 0, false);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('numeric');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->gl_temp->addColumn('debit_amt', $phpType, $default, 0, 4, false);
        $bo->gl_temp->addColumn('debit_amt_fc', $phpType, $default, 0, 4, false);
        $bo->gl_temp->addColumn('credit_amt', $phpType, $default, 0, 4, false);
        $bo->gl_temp->addColumn('credit_amt_fc', $phpType, $default, 0, 4, false);
        
        foreach($bo->gl_temp->getColumns() as $col) {
           $cols[] = ['columnName' => $col->columnName, 'default' => $col->default ];
        }
        $bo->setTranMetaData('gl_temp', $cols);
    }
    
    
}

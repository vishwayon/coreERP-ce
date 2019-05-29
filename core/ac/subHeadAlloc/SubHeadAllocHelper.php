<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ac\subHeadAlloc;

/**
 * Description of SubHeadAllocHelper
 *
 * @author priyanka
 */
class SubHeadAllocHelper {

    //put your code here

    public static function CreateAllocTemp($bo) {
        // Create temp teble for SubHead Ledger Alloc
        $bo->sub_head_ledger_temp = new \app\cwf\vsla\data\DataTable();
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $scale = 0;
        $isUnique = false;
        $bo->sub_head_ledger_temp->addColumn('branch_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $bo->sub_head_ledger_temp->addColumn('company_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $bo->sub_head_ledger_temp->addColumn('account_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $bo->sub_head_ledger_temp->addColumn('fc_type_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $bo->sub_head_ledger_temp->addColumn('sub_head_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);


        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('varchar');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->sub_head_ledger_temp->addColumn('voucher_id', $phpType, $default, 50, $scale, $isUnique);
        $bo->sub_head_ledger_temp->addColumn('sub_head_ledger_id', $phpType, $default, 500, $scale, $isUnique);
        $bo->sub_head_ledger_temp->addColumn('narration', $phpType, $default, 500, $scale, $isUnique);
        $bo->sub_head_ledger_temp->addColumn('finyear', $phpType, $default, 4, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('numeric');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->sub_head_ledger_temp->addColumn('exch_rate', $phpType, $default, 0, 6, $isUnique);
        $bo->sub_head_ledger_temp->addColumn('debit_amt', $phpType, $default, 0, 4, $isUnique);
        $bo->sub_head_ledger_temp->addColumn('debit_amt_fc', $phpType, $default, 0, 4, $isUnique);
        $bo->sub_head_ledger_temp->addColumn('credit_amt', $phpType, $default, 0, 4, $isUnique);
        $bo->sub_head_ledger_temp->addColumn('credit_amt_fc', $phpType, $default, 0, 4, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('date');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->sub_head_ledger_temp->addColumn('doc_date', $phpType, $default, 0, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('bool');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->sub_head_ledger_temp->addColumn('not_by_alloc', $phpType, $default, 0, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('smallint');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->sub_head_ledger_temp->addColumn('status', $phpType, $default, 0, $scale, $isUnique);

        foreach ($bo->sub_head_ledger_temp->getColumns() as $col) {
            $cols[] = ['columnName' => $col->columnName, 'default' => $col->default];
        }
        $bo->setTranMetaData('sub_head_ledger_temp', $cols);
    }

    public static function IsDetailReqd($account_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        
//        $cmm->setCommandText('select count(a.sub_head_id) as sub_head_cnt From ac.sub_head a
//                                inner join ac.account_head b on a.sub_head_dim_id = b.sub_head_dim_id
//                                where b.account_id = :paccount_id');
        
        $cmm->setCommandText('select is_ref_ledger, sub_head_dim_id from ac.account_head 
                                where account_id = :paccount_id
                                        and (is_ref_ledger = true or sub_head_dim_id != -1)');
        
        $cmm->addParam('paccount_id', $account_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $is_sub_head_reqd = 'false';
        $is_ref_ledger = 'false';
        $sub_head_dim_id = -1;

        if (count($dt->Rows()) > 0) {
            $is_sub_head_reqd = 'true';
            $sub_head_dim_id = $dt->Rows()[0]['sub_head_dim_id'];
            if($dt->Rows()[0]['is_ref_ledger'] == true)
            {
                $is_ref_ledger = 'true';
            }
            else{
                $is_ref_ledger = 'false';
            }
        }

        $result = array();
        $result['is_detail_reqd'] = $is_sub_head_reqd;
        $result['is_ref_ledger'] = $is_ref_ledger;
        $result['sub_head_dim_id'] = $sub_head_dim_id;
        $result['status'] = 'ok';

        return $result;
    }

    public static function CreateRefAllocTemp($bo) {
        // Create temp teble for SubHead Ledger Alloc
        $bo->ref_ledger_alloc_temp = new \app\cwf\vsla\data\DataTable();
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $scale = 0;
        $isUnique = false;
        $bo->ref_ledger_alloc_temp->addColumn('branch_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $bo->ref_ledger_alloc_temp->addColumn('account_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('varchar');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->ref_ledger_alloc_temp->addColumn('affect_voucher_id', $phpType, $default, 50, $scale, $isUnique);
        $bo->ref_ledger_alloc_temp->addColumn('affect_vch_tran_id', $phpType, $default, 50, $scale, $isUnique);
        $bo->ref_ledger_alloc_temp->addColumn('ref_ledger_id', $phpType, $default, 500, $scale, $isUnique);
        $bo->ref_ledger_alloc_temp->addColumn('ref_ledger_alloc_id', $phpType, $default, 500, $scale, $isUnique);
        $bo->ref_ledger_alloc_temp->addColumn('ref_no', $phpType, $default, 50, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('numeric');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->ref_ledger_alloc_temp->addColumn('net_debit_amt', $phpType, $default, 0, 4, $isUnique);
        $bo->ref_ledger_alloc_temp->addColumn('net_credit_amt', $phpType, $default, 0, 4, $isUnique);
        $bo->ref_ledger_alloc_temp->addColumn('balance', $phpType, $default, 0, 4, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('date');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->ref_ledger_alloc_temp->addColumn('affect_doc_date', $phpType, $default, 0, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('smallint');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->ref_ledger_alloc_temp->addColumn('status', $phpType, $default, 0, $scale, $isUnique);

        foreach ($bo->ref_ledger_alloc_temp->getColumns() as $col) {
            $cols[] = ['columnName' => $col->columnName, 'default' => $col->default];
        }
        $bo->setTranMetaData('ref_ledger_alloc_temp', $cols);
    }
    
}

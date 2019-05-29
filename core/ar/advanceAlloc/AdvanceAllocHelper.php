<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\advanceAlloc;
/**
 * Description of AdvanceAllocHelper
 *
 * @author priyanka
 */
class AdvanceAllocHelper {
    //put your code here
    public static function CreateAllocTemp($bo){        
        // Create temp teble for Receivable Ledger Alloc
        $bo->receivable_ledger_temp = new \app\cwf\vsla\data\DataTable();
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $scale = 0;
        $isUnique = false;
        $bo->receivable_ledger_temp->addColumn('branch_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $bo->receivable_ledger_temp->addColumn('account_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $bo->receivable_ledger_temp->addColumn('fc_type_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);

        
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('varchar');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);            
        $bo->receivable_ledger_temp->addColumn('voucher_id', $phpType, $default, 50, $scale, $isUnique);
        $bo->receivable_ledger_temp->addColumn('vch_tran_id', $phpType, $default, 50, $scale, $isUnique);
        $bo->receivable_ledger_temp->addColumn('rl_pl_id', $phpType, $default, 500, $scale, $isUnique);
        $bo->receivable_ledger_temp->addColumn('narration', $phpType, $default, 500, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('numeric');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->receivable_ledger_temp->addColumn('exch_rate', $phpType, $default, 0, 6, $isUnique);
        $bo->receivable_ledger_temp->addColumn('balance', $phpType, $default, 0, 4, $isUnique);
        $bo->receivable_ledger_temp->addColumn('balance_fc', $phpType, $default, 0, 4, $isUnique);
        $bo->receivable_ledger_temp->addColumn('debit_amt', $phpType, $default, 0, 4, $isUnique);
        $bo->receivable_ledger_temp->addColumn('debit_amt_fc', $phpType, $default, 0, 4, $isUnique);
        
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('date');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->receivable_ledger_temp->addColumn('doc_date', $phpType, $default, 0, $scale, $isUnique);
        
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('bool');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->receivable_ledger_temp->addColumn('is_select', $phpType, $default, 0, $scale, $isUnique);
        foreach($bo->receivable_ledger_temp->getColumns() as $col) {
           $cols[] = ['columnName' => $col->columnName, 'default' => $col->default ];
        }
        $bo->setTranMetaData('receivable_ledger_temp', $cols);
    }
    
    
    public static function ValidateAdvance($bo, $account_id, $voucher_id){
        if(count($bo->receivable_ledger_alloc_tran->Rows())>0){
            if($account_id != $bo->receivable_ledger_alloc_tran->Rows()[0]['account_id']){
                $bo->addBRule('Advance settlement does not belong to the selected customer. Kindly resettle advances.');
            }
        }
        $date_changed=false;
        foreach($bo->receivable_ledger_alloc_tran->Rows() as $row){
            if(strtotime($row['adv_ref_date']) > strtotime($bo->doc_date)){
               $date_changed=true;
            }
        }
        if($date_changed == true){
            $bo->addBRule('Advance settlement does not belong to the document period. Kindly resettle advances.'); 
        }
        
        
        foreach($bo->receivable_ledger_alloc_tran->Rows() as &$refpl_alloc_row){
            if($bo->fc_type_id == 0){
                $refpl_alloc_row['debit_amt_fc'] = 0;
            }
            else{
                $refpl_alloc_row['debit_amt'] = round($refpl_alloc_row['debit_amt_fc'] * $bo->exch_rate, \app\cwf\vsla\Math::$amtScale);
            }
            $refpl_alloc_row['exch_rate'] = $bo->exch_rate;
            $refpl_alloc_row['doc_date'] = $bo->doc_date;
            $refpl_alloc_row['net_debit_amt'] = round($refpl_alloc_row['debit_amt'], \app\cwf\vsla\Math::$amtScale) + round($refpl_alloc_row['write_off_amt'], \app\cwf\vsla\Math::$amtScale) + round($refpl_alloc_row['debit_exch_diff'], \app\cwf\vsla\Math::$amtScale);
            $refpl_alloc_row['net_debit_amt_fc'] = round($refpl_alloc_row['debit_amt_fc'], \app\cwf\vsla\Math::$amtScale) + round($refpl_alloc_row['write_off_amt_fc'], \app\cwf\vsla\Math::$amtScale);
        }
        
        if($bo->fc_type_id == 0) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('With rl_tran
                As
                (   Select x.rl_pl_id, -x.debit_amt as alloc_amt
                    From jsonb_to_recordset(:pcurrent_alloc::JsonB) as x(rl_pl_id uuid, debit_amt Numeric(18,4))
                ),
                rl_settle
                As
                (	-- All origins
                    Select a.rl_pl_id, (a.credit_amt-a.debit_amt) as balance_amt
                    From ac.rl_pl a
                    Inner Join rl_tran b On a.rl_pl_id = b.rl_pl_id
                    Union All -- All allocs without the current voucher
                    Select b.rl_pl_id, -(b.debit_amt-b.credit_amt) 
                    From ac.rl_pl_alloc b 
                    Inner Join rl_tran c On b.rl_pl_id = c.rl_pl_id
                    Where b.voucher_id != :pvoucher_id
                    Union All -- allocations in current voucher
                    Select a.rl_pl_id, a.alloc_amt
                    From rl_tran a
                )
                Select a.rl_pl_id, b.voucher_id, Sum(a.balance_amt)
                From rl_settle a 
                Inner Join ac.rl_pl b On a.rl_pl_id = b.rl_pl_id
                Group by a.rl_pl_id, b.voucher_id
                Having Sum(a.balance_amt) < 0;');
            $cmm->addParam('pvoucher_id', $voucher_id);
            $current_alloc = $bo->receivable_ledger_alloc_tran->select(['rl_pl_id', 'debit_amt']);
            $cmm->addParam('pcurrent_alloc', json_encode($current_alloc));
            $dtExcess = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($dtExcess->Rows())>0) {
                $bo->addBRule('Advance settlement(s) exceed balance available for ['.$dtExcess->Rows()[0]['voucher_id'].']. Kindly resettle advances.');
            }
        } else {
            // Todo: Validate the FC amounts only
        }
    }
    
    public static function GetAdvAllocDetailsOnEdit($bo, $voucher_id){
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select a.rl_pl_id, a.voucher_id, a.doc_date
                                from ac.rl_pl a
                                inner join ac.rl_pl_alloc b on a.rl_pl_id = b.rl_pl_id
                                Where b.voucher_id =:pvoucher_id');
        $cmm->addParam('pvoucher_id', $voucher_id);
        $resultTemplate = \app\cwf\vsla\data\DataConnect::getData($cmm);
        foreach ($bo->receivable_ledger_alloc_tran->Rows() as &$refpl_row){
            foreach ($resultTemplate->Rows() as $row){
                if($refpl_row['rl_pl_id'] == $row['rl_pl_id']){
                    $refpl_row['adv_ref_id']= $row['voucher_id']; 
                    $refpl_row['doc_date']= $row['doc_date'];     
                    $refpl_row['adv_ref_date']= $row['doc_date'];                                                        
                }
            }
        }
    }

    public static function GetUnsettledAdvAmt($customer_id, $doc_date) {        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select COALESCE(sum(balance), 0.00) as balance from ar.fn_receivable_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc)');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('paccount_id', $customer_id);
        $cmm->addParam('pto_date', $doc_date);
        $cmm->addParam('pvoucher_id', '');
        $cmm->addParam('pdc', 'C');
        $dtRLBalance = \app\cwf\vsla\data\DataConnect::getData($cmm); 
        if(count($dtRLBalance->Rows())==1){
            return $dtRLBalance->Rows()[0]['balance'];
        }
        return 0.00;
    }
}

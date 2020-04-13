<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\fiscalMonth;

/**
 * Description of FiscalMonthEventHandler
 *
 * @author Girish Shenoy
 */
class FiscalMonthEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
              
        // Create temp teble for doc_group Temp
        $this->bo->doc_group_temp = new \app\cwf\vsla\data\DataTable();
        
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('varchar');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);            
        $this->bo->doc_group_temp->addColumn('bo_id', $phpType, $default, 500, 0, false);
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('boolean');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $this->bo->doc_group_temp->addColumn('select', $phpType, $default, 0, 4, false);
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('bigint');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $this->bo->doc_group_temp->addColumn('doc_group_id', $phpType, $default, 0, 4, false);
        
        foreach($this->bo->doc_group_temp->getColumns() as $col) {
           $cols[] = ['columnName' => $col->columnName, 'default' => $col->default ];
        }
        $this->bo->setTranMetaData('doc_group_temp', $cols);
        
        // Fill doc_group_temp table
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select false as select, doc_group_id, doc_group
                                from sys.doc_group
                                order by doc_group");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        foreach ($dt->Rows() as $row) {
            $newRow = $this->bo->doc_group_temp->NewRow();
            $newRow['doc_group_id'] = $row['doc_group_id'];
            $newRow['doc_group'] = $row['doc_group'];
            $newRow['select'] = $row['select'];              
            $this->bo->doc_group_temp->AddRow($newRow);
        }
        
        if($this->bo->fiscal_month_id == -1){
            
        }
        else{
            $str = str_replace('{', '' , $this->bo->annex_info->Value()->doc_group_ids);
            $dg_ids = str_replace('}', '' , $str);
            $doc_grp_ids = explode(',', $dg_ids);
            $a= $doc_grp_ids;
            foreach($doc_grp_ids as $dr){
                foreach($this->bo->doc_group_temp->Rows() as &$ref_dg_row){
                    if(intval($dr) == $ref_dg_row['doc_group_id']){
                        $ref_dg_row['select'] = true;
                    }
                }
            }
        }
    }
}

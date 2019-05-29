<?php

namespace app\core\st\material;
use YaLinqo\Enumerable;

/**
 * Description of MaterialEventHandler
 * @author Girish
 */
class MaterialEventHandler extends\app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        
        $this->bo->setTranColDefault('annex_info.supp_info.pref_supp', 'lead_days', 0);
        
        $this->bo->stock_ledger_temp = new \app\cwf\vsla\data\DataTable(); 
        $this->bo->stock_ledger_temp->cloneColumns($this->bo->stock_ledger);
        $this->fillStockLedgerTemp();
        
        // Create temp table for Material UoM schedule
        $this->bo->material_uom_schedule_temp = new \app\cwf\vsla\data\DataTable();
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $scale = 0;
        $isUnique = false;
        $this->bo->material_uom_schedule_temp->addColumn('uom_sch_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('varchar');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);            
        $this->bo->material_uom_schedule_temp->addColumn('uom_sch_desc', $phpType, $default, 50, $scale, $isUnique);
        
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('checkbox');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);            
        $this->bo->material_uom_schedule_temp->addColumn('is_select', $phpType, $default, 0, $scale, $isUnique);
       
        foreach($this->bo->material_uom_schedule_temp->getColumns() as $col) {
           $cols[] = ['columnName' => $col->columnName, 'default' => $col->default ];
        }
        $this->bo->setTranMetaData('material_uom_schedule_temp', $cols);
        
        if($this->bo->annex_info->Value()->war_info->war_days == -1) {
            $this->bo->annex_info->Value()->war_info->war_days = 0;
        }
        
        if($this->bo->material_id == -1){
            // Fill uom table with default values for each uom_type
            // Base Unit
            $newRow = $this->bo->uom->NewRow();
            $newRow['uom_id'] = -2;
            $newRow['material_id'] = -1;
            $newRow['uom_desc'] = '';
            $newRow['uom_qty'] = 1;
            $newRow['is_base'] = true;
            $newRow['is_su'] = false;
            $newRow['is_discontinued'] = false;
            $newRow['uom_type_id'] = 101;
            $this->bo->uom->AddRow($newRow);
            
            // WIP: Purchase Unit
            $newRow = $this->bo->uom->NewRow();
            $newRow['uom_id'] = -3;
            $newRow['material_id'] = -1;
            $newRow['uom_desc'] = '';
            $newRow['uom_qty'] = 1;
            $newRow['is_base'] = false;
            $newRow['is_su'] = false;
            $newRow['is_discontinued'] = false;
            $newRow['uom_type_id'] = 103;
            $this->bo->uom->AddRow($newRow); 
            
            // WIP: Sale Unit
            $newRow = $this->bo->uom->NewRow();
            $newRow['uom_id'] = -4;
            $newRow['material_id'] = -1;
            $newRow['uom_desc'] = '';
            $newRow['uom_qty'] = 1;
            $newRow['is_base'] = false;
            $newRow['is_su'] = TRUE;
            $newRow['is_discontinued'] = false;
            $newRow['uom_type_id'] = 104;
            $this->bo->uom->AddRow($newRow); 
        }
    }
    
    public function beforeFetch(&$criteriaparam) {
        parent::beforeFetch($criteriaparam);
        // Fetch first fin year of the system
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select finyear_code, year_begin - '1 day'::interval as min_year_begin from sys.finyear where year_begin = (select min(year_begin) from sys.finyear)");
   
        $dt =  \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows()) == 1){
            $this->bo->first_fin_year = $dt->Rows()[0]['finyear_code'];
            $this->bo->min_year_begin  = $dt->Rows()[0]['min_year_begin'];
        }   
        
    }

    private function fillStockLedgerTemp(){     
        $rowcount=count($this->bo->stock_ledger_temp->Rows());
        for ($i=0; $i<=$rowcount;$i++) { 
            $this->bo->stock_ledger_temp->removeRow(0);
        }
       
        foreach ($this->bo->stock_ledger->Rows() as $row) {
            $newRow = $this->bo->stock_ledger_temp->NewRow();
            $newRow['is_allow_edit'] = $row['is_allow_edit'];
            $newRow['stock_ledger_id'] = $row['stock_ledger_id'];
            $newRow['company_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
            $newRow['branch_id'] =  $row['branch_id'];
            $newRow['voucher_id'] = $row['voucher_id'];
            $newRow['vch_tran_id'] = $row['vch_tran_id'];
            $newRow['doc_date'] = $row['doc_date'];
            $newRow['material_id'] = $row['material_id'];
            $newRow['stock_location_id'] = $row['stock_location_id'];
            $newRow['received_qty'] = $row['received_qty'];
            $newRow['unit_rate_lc'] = $row['unit_rate_lc'];
            $newRow['item_amt'] =round($row['unit_rate_lc'] * $row['received_qty'], \app\cwf\vsla\Math::$amtScale);
            $newRow['narration'] = $row['narration'];
            $newRow['is_opbl'] = $row['is_opbl'];
            $this->bo->stock_ledger_temp->AddRow($newRow);
        }   
    }
    
    public function onFetch($criteriaparam, $tablename) {
        parent::onFetch($criteriaparam, $tablename);
                           
        
        if($tablename=='st.stock_ledger'){
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select true as is_allow_edit, a.stock_ledger_id, a.company_id, a.branch_id, a.voucher_id, a.vch_tran_id, a.doc_date, a.material_id, 
                                        a.stock_location_id, a.received_qty, a.unit_rate_lc, a.is_opbl, a.narration
                                from st.stock_ledger a
                                where a.is_opbl=true and a.material_id=:pmaterial_id and finyear=:pfinyear and doc_date=:pdoc_date;');
          
            $cmm->addParam('pmaterial_id', $this->bo->material_id);  
            $cmm->addParam('pfinyear', $this->bo->first_fin_year);
            $cmm->addParam('pdoc_date', $this->bo->min_year_begin);   
            $dtTran=  \app\cwf\vsla\data\DataConnect::getData($cmm);
            
            foreach ($dtTran->Rows() as $row) {
                $newRow = $this->bo->stock_ledger->NewRow();
                $newRow['is_allow_edit'] = $row['is_allow_edit'];
                $newRow['stock_ledger_id'] = $row['stock_ledger_id'];
                $newRow['company_id'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id');
                $newRow['branch_id'] =  $row['branch_id'];
                $newRow['voucher_id'] = $row['voucher_id'];
                $newRow['vch_tran_id'] = $row['vch_tran_id'];
                $newRow['doc_date'] = $row['doc_date'];
                $newRow['material_id'] = $row['material_id'];
                $newRow['stock_location_id'] = $row['stock_location_id'];
                $newRow['received_qty'] = $row['received_qty'];
                $newRow['unit_rate_lc'] = $row['unit_rate_lc'];
                $newRow['item_amt'] =round($row['unit_rate_lc'] * $row['received_qty'], \app\cwf\vsla\Math::$amtScale);
                $newRow['narration'] = $row['narration'];
                $newRow['is_opbl'] = $row['is_opbl'];
                $this->bo->stock_ledger->AddRow($newRow);
            }   
        }
    }
    
    public function onSave($cn, $tablename){
        parent::onSave($cn, $tablename);  
        
        if($tablename=='st.uom'){
                        
            // Save new uom items
            $ac = \app\cwf\vsla\entity\EntityManager::getInstance()->getActionScripts('st.uom',  \app\cwf\vsla\data\DataConnect::COMPANY_DB, \app\cwf\vsla\entity\ActionScript::TABLE_TYPE_MASTER_CONTROL);
            $cnt = count($this->bo->uom->Rows());
            foreach($this->bo->uom->Rows() as &$ref_uom_row)
            {
                if($ref_uom_row['uom_id'] <= -1){
                    $cmm = $ac->getInsertCmm();
                    $uompkid = \app\cwf\vsla\entity\EntityManager::getMastSeqID($this->bo->company_id, 'st.uom', $cn);
                }
                else{
                    $cmm = $ac->getUpdateCmm();
                    $uompkid = $ref_uom_row['uom_id'];
                }
                $cmm->setParamValue('puom_id', $uompkid);
                $cmm->setParamValue('pmaterial_id', $this->bo->material_id);
                $cmm->setParamValue('puom_desc', $ref_uom_row['uom_desc']);
                $cmm->setParamValue('puom_qty', $ref_uom_row['uom_qty']);
                $cmm->setParamValue('pis_base', $ref_uom_row['is_base']);
                $cmm->setParamValue('pis_su', $ref_uom_row['is_su']);
                $cmm->setParamValue('pis_discontinued', $ref_uom_row['is_discontinued']);
                $cmm->setParamValue('puom_type_id', $ref_uom_row['uom_type_id']);
                if(array_key_exists('in_kg', $ref_uom_row)) {
                    $cmm->setParamValue('pin_kg', $ref_uom_row['in_kg']);
                    $cmm->setParamValue('pin_ltr', $ref_uom_row['in_ltr']);
                }
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn); 
                $ref_uom_row['uom_id'] = $uompkid;
            }
        }
        
        
        if($tablename=='st.stock_ledger'){
            
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('DELETE FROM st.stock_ledger '
                                 . 'WHERE stock_ledger_id=:pstock_ledger_id ');
            
            foreach($this->bo->stock_ledger_temp->Rows() as $temprow){
                $deletedrow=true;
                foreach($this->bo->stock_ledger->Rows() as $row){
                     if($row['stock_ledger_id']==$temprow['stock_ledger_id']){
                         $deletedrow=false;
                         break;
                     }
                     $deletedrow=TRUE;
                 }
                 if($deletedrow){                    
                    $cmm->addParam('pstock_ledger_id', $temprow['stock_ledger_id']);
                    \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn); 
                }
            }
            
            $uom_base = Enumerable::from($this->bo->uom->Rows())->where('$a==>$a["is_base"]==true')->toList(); 
            $base_uom_id = -1;
            $base_uom_qty = 0;
            if(count($uom_base) == 1){
                $base_uom_id = $uom_base[0]['uom_id'];
                $base_uom_qty = $uom_base[0]['uom_qty'];
            }
            //Add rows to ac.rl_pl
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select * from st.sp_material_opbl_ref_add_update(:pstock_ledger_id, :pcompany_id, :pbranch_id, :pfinyear, 
                                    :pdoc_date, :pmaterial_id, :pstock_location_id, :puom_id, :puom_qty,
                                    :preceived_qty, :punit_rate_lc, :pnarration, :paccount_id)');
            $v_id = -1;
            
            foreach($this->bo->stock_ledger->Rows() as &$ref_row){ 
                $voucher_id=$ref_row['voucher_id'];
                $sl_id=$ref_row['stock_ledger_id'];
                
                $cmm->addParam('pstock_ledger_id', $sl_id, \app\cwf\vsla\data\SqlParamType::PARAM_INOUT);                   
                
                $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
                $cmm->addParam('pbranch_id', $ref_row['branch_id']);
                $cmm->addParam('pfinyear', $this->bo->first_fin_year);
                $cmm->addParam('pdoc_date', $this->bo->min_year_begin);
                $cmm->addParam('pmaterial_id', $this->bo->material_id);
                $cmm->addParam('pstock_location_id', $ref_row['stock_location_id']);
                $cmm->addParam('puom_id', $base_uom_id);
                $cmm->addParam('puom_qty', $ref_row['received_qty']);
                $cmm->addParam('preceived_qty', $ref_row['received_qty']);
                $cmm->addParam('punit_rate_lc', $ref_row['unit_rate_lc']);
                $cmm->addParam('pnarration', 'Opening Balance');
                $cmm->addParam('paccount_id', $this->bo->inventory_account_id);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
                $ref_row['stock_ledger_id'] = $cmm->getParamValue('pstock_ledger_id');
                $ref_row['voucher_id'] = $voucher_id;
            }
        }
    }
    
    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
        $this->fillStockLedgerTemp(); 
        $this->updateSphinxIndex();
    }
    
    private function updateSphinxIndex() {
        // We update the sphinx index if it is available
        $params = \yii::$app->params;
        if(isset(\yii::$app->params['cwf_config']['sphinxSearch'])) {
            $config = \yii::$app->params['cwf_config']['sphinxSearch'];
            // Open Connection
            $mysqli = new \mysqli($config['server'], $config['user'], $config['pass'], '', $config['port']);
            if ( \mysqli_connect_errno() ) {
                die ( "sphinxSearch connect failed: " . mysqli_connect_error());
            }
            // Prepare input parameters
            $pid = $this->bo->material_id;
            $pmaterial_name = $mysqli->escape_string($this->bo->material_name);
            $pmaterial_code = $mysqli->escape_string($this->bo->material_code);
            $pmaterial_desc = $mysqli->escape_string($this->bo->material_desc);
            $pmaterial_type = ''; //$this->bo->material_type;
            $pmfg = $mysqli->escape_string($this->bo->annex_info->Value()->supp_info->mfg);
            $pmfg_part_no = $mysqli->escape_string($this->bo->annex_info->Value()->supp_info->mfg_part_no);
            $pmfg_part_desc = $mysqli->escape_string($this->bo->annex_info->Value()->supp_info->mfg_part_desc);
            $pmat_cats = ''; // $this->bo->annex_info->Value()->mat_cat_info->mat_cat_id;
            // Get Mat Category Keys applied
            $cat_keys = [];
            foreach ($this->bo->annex_info->Value()->mat_cat_info->mat_cat_keys as $mat_cat_item) {
                if($mat_cat_item->mat_cat_key_value == true) {
                    $cat_keys[] = $mat_cat_item->mat_cat_key;
                }
            }
            $pmat_cat_keys = $mysqli->escape_string(implode(', ', $cat_keys));
            // Get mat attribute values
            $cat_attrs = [];
            foreach ($this->bo->annex_info->Value()->mat_cat_info->mat_cat_attrs as $mat_attr_item) {
                $cat_attrs[] = $mat_attr_item->mat_cat_attr_value;
            }
            $pmat_cat_attr_values = $mysqli->escape_string(implode(', ', $cat_attrs));
            $pcompany_id = $this->bo->company_id;          
            

            $sql = "Select * from stock where id=".$this->bo->material_id." and company_id=".$this->bo->company_id.";";
            $inrep = $mysqli->query($sql);
            if($inrep) {
                $count = count($inrep->fetch_array());
                if($count==0) {
                    $sql = 'Insert ';
                } else {
                    $sql = 'Replace ';
                }
                # This structure is well defined in yii/config/sphinx.conf. If the fields are modified, ensure that the structure is also updated  
                $sql .= "into stock(id, material_name, material_code, material_desc, material_type, mfg, mfg_part_no, mfg_part_desc, mat_cat, mat_cat_keys, mat_cat_attr_values, company_id) 
                        values($pid, '$pmaterial_name', '$pmaterial_code', '$pmaterial_desc', '$pmaterial_type', '$pmfg', '$pmfg_part_no', 
                        '$pmfg_part_desc', '$pmat_cats', '$pmat_cat_keys', '$pmat_cat_attr_values', $pcompany_id)";
                $mysqli->query($sql);
                if ( \mysqli_connect_error() ) {
                    die ( "sphinxSearch connect failed: " . mysqli_connect_error());
                }
            }
            mysqli_close($mysqli);
            
        }
    }
}
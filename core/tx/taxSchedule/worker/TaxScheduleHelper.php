<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tx\taxSchedule\worker;

use YaLinqo\Enumerable;

/**
 * Description of TaxScheduleHelper
 *
 * @author priyanka
 */
class TaxScheduleHelper {

    public static function CreateTaxDetailTemp($bo) {
        // Create temp table for Material UoM schedule
        $bo->tax_detail_temp = new \app\cwf\vsla\data\DataTable();
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $scale = 0;
        $isUnique = false;
        $bo->tax_detail_temp->addColumn('tax_schedule_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $bo->tax_detail_temp->addColumn('tax_detail_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $bo->tax_detail_temp->addColumn('step_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $bo->tax_detail_temp->addColumn('account_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('varchar');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->tax_detail_temp->addColumn('parent_tax_details', $phpType, $default, 500, $scale, $isUnique);
        $bo->tax_detail_temp->addColumn('description', $phpType, $default, 120, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int2');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->tax_detail_temp->addColumn('en_tax_type', $phpType, $default, 0, $scale, $isUnique);
        $bo->tax_detail_temp->addColumn('en_round_type', $phpType, $default, 0, $scale, $isUnique);

        $scale = 4;
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('numeric');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->tax_detail_temp->addColumn('tax_perc', $phpType, $default, 0, $scale, $isUnique);
        $bo->tax_detail_temp->addColumn('tax_on_perc', $phpType, $default, 0, $scale, $isUnique);
        $bo->tax_detail_temp->addColumn('tax_on_min_amt', $phpType, $default, 0, $scale, $isUnique);
        $bo->tax_detail_temp->addColumn('tax_on_max_amt', $phpType, $default, 0, $scale, $isUnique);
        $bo->tax_detail_temp->addColumn('min_tax_amt', $phpType, $default, 0, $scale, $isUnique);
        $bo->tax_detail_temp->addColumn('max_tax_amt', $phpType, $default, 0, $scale, $isUnique);
        $bo->tax_detail_temp->addColumn('custom_rate', $phpType, $default, 0, $scale, $isUnique);
        $bo->tax_detail_temp->addColumn('tax_amt', $phpType, $default, 0, $scale, $isUnique);
        foreach ($bo->tax_detail_temp->getColumns() as $col) {
            $cols[] = ['columnName' => $col->columnName, 'default' => $col->default];
        }
        $bo->setTranMetaData('tax_detail_temp', $cols);
    }

    public static function CreateTaxAppliedTran() {
        // Create temp table for Material UoM schedule
        $tax_applied_tran = new \app\cwf\vsla\data\DataTable();
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $scale = 0;
        $isUnique = false;
        $tax_applied_tran->addColumn('tax_schedule_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $tax_applied_tran->addColumn('tax_detail_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $tax_applied_tran->addColumn('step_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $tax_applied_tran->addColumn('account_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('varchar');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $tax_applied_tran->addColumn('description', $phpType, $default, 120, $scale, $isUnique);

        $scale = 4;
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('numeric');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $tax_applied_tran->addColumn('custom_rate', $phpType, $default, 0, $scale, $isUnique);
        $tax_applied_tran->addColumn('tax_amt', $phpType, $default, 0, $scale, $isUnique);
        $tax_applied_tran->addColumn('tax_amt_fc', $phpType, $default, 0, $scale, $isUnique);
        return $tax_applied_tran;
    }

    public static function CalculateTaxOnSave($bo) {

        if (count($bo->tax_tran->Rows()) > 0) {
            $bo->tax_schedule_id = $bo->tax_tran->Rows()[0]['tax_schedule_id'];

            // Fetch Tax Details remaining fields
            $rowcount = count($bo->tax_detail_temp->Rows());
            for ($i = 0; $i <= $rowcount; $i++) {
                $bo->tax_detail_temp->removeRow(0);
            }
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select step_id, tax_detail_id, parent_tax_details, description, account_id, en_tax_type, en_round_type,
                                        tax_perc, tax_on_perc, tax_on_min_amt, tax_on_max_amt, min_tax_amt, max_tax_amt
                                    From tx.tax_detail
                                    where tax_schedule_id=:ptax_schedule_id');
            $cmm->addParam('ptax_schedule_id', $bo->tax_schedule_id);
            $dtTaxDetail = \app\cwf\vsla\data\DataConnect::getData($cmm);

            foreach ($bo->tax_tran->Rows() as $row) {
                $drTax = Enumerable::from($dtTaxDetail->Rows())->where('$a==>$a["tax_detail_id"]==' . $row['tax_detail_id'])->toList();

                // Resolve Tax  
                $newRow = $bo->tax_detail_temp->NewRow();
                $newRow['tax_schedule_id'] = $bo->tax_schedule_id;
                $newRow['tax_detail_id'] = $row['tax_detail_id'];
                $newRow['step_id'] = $row['step_id'];
                $newRow['account_id'] = $row['account_id'];
                $newRow['description'] = $row['description'];
                $newRow['custom_rate'] = $row['custom_rate'];
                $newRow['tax_amt'] = round($row['tax_amt'], \app\cwf\vsla\Math::$amtScale);

                if (count($drTax) > 0) {
                    $newRow['parent_tax_details'] = $drTax[0]['parent_tax_details'];
                    $newRow['en_tax_type'] = $drTax[0]['en_tax_type'];
                    $newRow['en_round_type'] = $drTax[0]['en_round_type'];
                    $newRow['tax_perc'] = $drTax[0]['tax_perc'];
                    $newRow['tax_on_perc'] = $drTax[0]['tax_on_perc'];
                    $newRow['tax_on_min_amt'] = $drTax[0]['tax_on_min_amt'];
                    $newRow['tax_on_max_amt'] = $drTax[0]['tax_on_max_amt'];
                    $newRow['min_tax_amt'] = $drTax[0]['min_tax_amt'];
                    $newRow['max_tax_amt'] = $drTax[0]['max_tax_amt'];
                }
                $bo->tax_detail_temp->AddRow($newRow);
            }
        }

        $dtTaxApplied = TaxScheduleCalculator::CalculateTax($bo->tax_schedule_id, $bo->before_tax_amt, 0, $bo->tax_detail_temp->Rows(), 0);

        foreach ($bo->tax_tran->Rows() as &$reftax_row_new) {
            $t = Enumerable::from($dtTaxApplied->Rows())->where('$a==>$a["tax_detail_id"]==' . $reftax_row_new['tax_detail_id'])->toList();
            if (count($t) > 0) {
                $reftax_row_new['tax_amt'] = round($t[0]['tax_amt'], \app\cwf\vsla\Math::$amtScale);
                if ($bo->fc_type_id != 0) {
                    $reftax_row_new['tax_amt_fc'] = round($reftax_row_new['tax_amt'] / $bo->exch_rate, \app\cwf\vsla\Math::$amtScale);
                }
            }
        }
        $bo->tax_amt = round(Enumerable::from($bo->tax_tran->Rows())->sum('$a==>$a["tax_amt"]'), \app\cwf\vsla\Math::$amtScale);
        $bo->tax_amt_fc = round(Enumerable::from($bo->tax_tran->Rows())->sum('$a==>$a["tax_amt_fc"]'), \app\cwf\vsla\Math::$amtScale);
    }

    public static function GetTaxDetailsOnEdit($bo) {
        if (count($bo->tax_tran->Rows()) > 0) {
            $bo->tax_schedule_id = $bo->tax_tran->Rows()[0]['tax_schedule_id'];
            $bo->tax_schedule_name = \app\cwf\vsla\utils\LookupHelper::GetLookupText('../core/tx/lookups/TaxSchedule.xml', 'tax_schedule', 'tax_schedule_id', $bo->tax_schedule_id);


            // Fetch Tax Details remaining fields

            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select step_id, tax_detail_id, parent_tax_details, description, account_id, en_tax_type, en_round_type,
                                        tax_perc, tax_on_perc, tax_on_min_amt, tax_on_max_amt, min_tax_amt, max_tax_amt
                                    From tx.tax_detail
                                    where tax_schedule_id=:ptax_schedule_id');
            $cmm->addParam('ptax_schedule_id', $bo->tax_schedule_id);
            $dtTaxDetail = \app\cwf\vsla\data\DataConnect::getData($cmm);

            foreach ($bo->tax_tran->Rows() as $row) {
                $drTax = Enumerable::from($dtTaxDetail->Rows())->where('$a==>$a["tax_detail_id"]==' . $row['tax_detail_id'])->toList();

                // Resolve Tax  
                $newRow = $bo->tax_detail_temp->NewRow();
                $newRow['tax_schedule_id'] = $bo->tax_schedule_id;
                $newRow['tax_detail_id'] = $row['tax_detail_id'];
                $newRow['step_id'] = $row['step_id'];
                $newRow['account_id'] = $row['account_id'];
                $newRow['description'] = $row['description'];
                $newRow['custom_rate'] = $row['custom_rate'];
                $newRow['tax_amt'] = $row['tax_amt'];

                if (count($drTax) > 0) {
                    $newRow['parent_tax_details'] = $drTax[0]['parent_tax_details'];
                    $newRow['en_tax_type'] = $drTax[0]['en_tax_type'];
                    $newRow['en_round_type'] = $drTax[0]['en_round_type'];
                    $newRow['tax_perc'] = $drTax[0]['tax_perc'];
                    $newRow['tax_on_perc'] = $drTax[0]['tax_on_perc'];
                    $newRow['tax_on_min_amt'] = $drTax[0]['tax_on_min_amt'];
                    $newRow['tax_on_max_amt'] = $drTax[0]['tax_on_max_amt'];
                    $newRow['min_tax_amt'] = $drTax[0]['min_tax_amt'];
                    $newRow['max_tax_amt'] = $drTax[0]['max_tax_amt'];
                }
                $bo->tax_detail_temp->AddRow($newRow);
            }
        }
    }

    public static function ValidateTax($bo) {
        $cnt = 0;
        foreach ($bo->tax_tran->Rows() as &$reftax_row) {
            $cnt +=1;
            if ($reftax_row['supplier_paid'] == true) {
                $reftax_row['account_affected_id'] = -1;
            } else {
                if ($reftax_row['include_in_lc'] == false) {
                    $bo->addBRule('Tax - Row[' . $cnt . '] : If Tax Item is not paid by supplier, Include In LC should be true');
                }
                if ($reftax_row['account_affected_id'] == -1) {
                    $bo->addBRule('Tax - Row[' . $cnt . '] : Liability Account is required');
                }
            }
            if ($bo->fc_type_id == 0) {
                $reftax_row['tax_amt_fc'] = 0;
            }
        }
    }

    public static function GetTaxDefaultsOnNew($bo) {
        $dtTaxApplied = TaxScheduleCalculator::CalculateTax($bo->tax_schedule_id, $bo->before_tax_amt, 0, $bo->tax_detail_temp->Rows(), 0);

        // Fetch Tax Details remaining fields
        $rowcount = count($bo->tax_tran->Rows());
        for ($i = 0; $i <= $rowcount; $i++) {
            $bo->tax_tran->removeRow(0);
        }
        foreach ($dtTaxApplied->Rows() as $row) {
            // Resolve Tax  
            $newRow = $bo->tax_tran->NewRow();
            $newRow['tax_schedule_id'] = $bo->tax_schedule_id;
            $newRow['tax_detail_id'] = $row['tax_detail_id'];
            $newRow['step_id'] = $row['step_id'];
            $newRow['account_id'] = $row['account_id'];
            $newRow['description'] = $row['description'];
            $newRow['custom_rate'] = $row['custom_rate'];
            $newRow['supplier_paid'] = true;
            $newRow['tax_amt'] = round($row['tax_amt'], \app\cwf\vsla\Math::$amtScale);

            $bo->tax_tran->AddRow($newRow);

            // Resolve Tax  
            $newRow = $bo->tax_detail_temp->NewRow();
            $newRow['tax_schedule_id'] = $bo->tax_schedule_id;
            $newRow['tax_detail_id'] = $row['tax_detail_id'];
            $newRow['step_id'] = $row['step_id'];
            $newRow['account_id'] = $row['account_id'];
            $newRow['description'] = $row['description'];
            $newRow['custom_rate'] = $row['custom_rate'];
            $newRow['tax_amt'] = $row['tax_amt'];

            $newRow['parent_tax_details'] = $row['parent_tax_details'];
            $newRow['en_tax_type'] = $row['en_tax_type'];
            $newRow['en_round_type'] = $row['en_round_type'];
            $newRow['tax_perc'] = $row['tax_perc'];
            $newRow['tax_on_perc'] = $row['tax_on_perc'];
            $newRow['tax_on_min_amt'] = $row['tax_on_min_amt'];
            $newRow['tax_on_max_amt'] = $row['tax_on_max_amt'];
            $newRow['min_tax_amt'] = $row['min_tax_amt'];
            $newRow['max_tax_amt'] = $row['max_tax_amt'];
            $bo->tax_detail_temp->AddRow($newRow);
        }
    }

}

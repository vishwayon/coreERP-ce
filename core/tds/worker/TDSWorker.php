<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tds\worker;

/**
 * Description of TDSWorker
 *
 * @author priyanka
 */
class TDSWorker {

    const DO_NOT_ROUND = 0;
    const ROUND_OFF_TENTH_DECIMAL = 1;
    const ROUND_OFF_WHOLE_DIGIT = 2;
    const ROUND_UP_WHOLE_DIGIT = 3;
    const ROUND_DOWN_WHOLE_DIGIT = 4;

    public static function TDSInfoExists($supplier_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select is_tds_applied from ap.supplier_tax_info where supplier_id=:psupplier_id');
        $cmm->addParam('psupplier_id', $supplier_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);

        if (count($result->Rows()) > 0) {
            if ($result->Rows()[0]['is_tds_applied']) {
                return TRUE;
            }
        }
        return false;
    }

    public static function SuppTDSRateInfo($person_type_id, $section_id, $doc_date) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('SELECT a.person_type_id, a.section_id, a.base_rate_perc, a.ecess_perc, a.surcharge_perc, a.effective_from,
                                a.en_round_type
                            FROM tds.rate a
                            WHERE a.person_type_id = :pperson_type_id 
                                    AND a.section_id = :psection_id 
                                    and a.effective_from = (Select max(b.effective_from) as effective_from from tds.rate b
                                                            where b.effective_from <= :pdoc_date And b.person_type_id = :pperson_type_id 
                                                                    AND b.section_id = :psection_id )
                            ');
        $cmm->addParam('pperson_type_id', $person_type_id);
        $cmm->addParam('psection_id', $section_id);
        $cmm->addParam('pdoc_date', $doc_date);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $result;
    }

    public static function SuppTDSInfo($supplier_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select tds_person_type_id, tds_section_id, is_tds_applied, COALESCE(b.section, '') as section, COALESCE(c.person_type_desc, '') as person_type  
                from ap.supplier_tax_info a
                left join tds.section b on a.tds_section_id = b.section_id
                left join tds.person_type c on a.tds_person_type_id = c.person_type_id
                where supplier_id=:psupplier_id");
        $cmm->addParam('psupplier_id', $supplier_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $result;
    }

    public static function ClearTDS($bo) {
        $rowcount = count($bo->bill_tds_tran->Rows());
        for ($i = 0; $i <= $rowcount; $i++) {
            $bo->bill_tds_tran->removeRow(0);
        }
    }

    public static function ClearTDSInfo($bo) {
        $bo->btt_tds_base_rate_perc = 0;
        $bo->btt_tds_base_rate_amt = 0;
        $bo->btt_tds_base_rate_amt_fc = 0;
        $bo->btt_tds_ecess_perc = 0;
        $bo->btt_tds_ecess_amt = 0;
        $bo->btt_tds_ecess_amt_fc = 0;
        $bo->btt_tds_surcharge_perc = 0;
        $bo->btt_tds_surcharge_amt = 0;
        $bo->btt_tds_surcharge_amt_fc = 0;
    }

    //put your code here
    public static function GetRowsInTDSTran($bo, $supplier_id, $total_amt, $total_amt_fc, $bill_amt, $bill_amt_fc) {

        $rowcount = count($bo->bill_tds_tran->Rows());
        for ($i = 0; $i <= $rowcount; $i++) {
            $bo->bill_tds_tran->removeRow(0);
        }
        //Get data from ap.supplier_tax_info for selected supplier
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('SELECT tds_person_type_id, tds_section_id, is_tds_applied FROM ap.supplier_tax_info WHERE supplier_id=:psupplier_id');
        $cmm->addParam('psupplier_id', $supplier_id);
        $resultFromSupplierTaxInfo = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($resultFromSupplierTaxInfo->Rows()) > 0) {
            if ($resultFromSupplierTaxInfo->Rows()[0]['is_tds_applied']) {
                //Add rows to bill_tds_tran
                //Get rows from ap.tds_rate for selected supplier
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('SELECT a.person_type_id, a.section_id, a.base_rate_perc, a.ecess_perc, a.surcharge_perc, a.effective_from,
                                            a.en_round_type
                                        FROM tds.rate a
                                        WHERE a.person_type_id = :pperson_type_id 
                                                AND a.section_id = :psection_id 
                                                and a.effective_from = (Select max(b.effective_from) as effective_from from tds.rate b
                                                                        where b.effective_from <= :pdoc_date And b.person_type_id = :pperson_type_id 
                                                                                AND b.section_id = :psection_id )
                                        ');
                $cmm->addParam('pperson_type_id', $resultFromSupplierTaxInfo->Rows()[0]['tds_person_type_id']);
                $cmm->addParam('psection_id', $resultFromSupplierTaxInfo->Rows()[0]['tds_section_id']);
                $cmm->addParam('pdoc_date', $bo->doc_date);

                $resultFromTDSRate = \app\cwf\vsla\data\DataConnect::getData($cmm);

                if (count($resultFromTDSRate->Rows()) == 1) {
                    $rowTDSRate = $resultFromTDSRate->Rows()[0];
                    $newRow = $bo->bill_tds_tran->NewRow();
                    $newRow['bill_id'] = '';
                    $newRow['bill_tds_tran_id'] = '';
                    $newRow['person_type_id'] = $rowTDSRate['person_type_id'];
                    $newRow['section_id'] = $rowTDSRate['section_id'];
                    $newRow['doc_date'] = $bo->doc_date;
                    $newRow['company_id'] = $bo->company_id;
                    $newRow['branch_id'] = $bo->branch_id;
                    $newRow['supplier_id'] = $supplier_id;
                    $newRow['bill_amt'] = $bill_amt;
                    $newRow['amt_for_tds'] = $total_amt;
                    $newRow['tds_base_rate_perc'] = $rowTDSRate['base_rate_perc'];
                    $newRow['tds_base_rate_amt'] = self::RoundOffAmt((($total_amt * $rowTDSRate['base_rate_perc']) / 100), $rowTDSRate['en_round_type']);
                    $newRow['tds_ecess_perc'] = $rowTDSRate['ecess_perc'];
                    $newRow['tds_ecess_amt'] = self::RoundOffAmt((($total_amt * $rowTDSRate['ecess_perc']) / 100), $rowTDSRate['en_round_type']);
                    $newRow['tds_surcharge_perc'] = $rowTDSRate['surcharge_perc'];
                    $newRow['tds_surcharge_amt'] = self::RoundOffAmt((($total_amt * $rowTDSRate['surcharge_perc']) / 100), $rowTDSRate['en_round_type']);

                    if ($bo->fc_type_id != 0) {
                        $newRow['tds_base_rate_amt_fc'] = self::RoundOffAmt((($total_amt_fc * $rowTDSRate['base_rate_perc']) / 100), $rowTDSRate['en_round_type']);
                        $newRow['tds_ecess_amt_fc'] = self::RoundOffAmt((($total_amt_fc * $rowTDSRate['ecess_perc']) / 100), $rowTDSRate['en_round_type']);
                        $newRow['tds_surcharge_amt_fc'] = self::RoundOffAmt((($total_amt_fc * $rowTDSRate['surcharge_perc']) / 100), $rowTDSRate['en_round_type']);
                        $newRow['bill_amt_fc'] = $bill_amt_fc;
                    }

                    $bo->bill_tds_tran->AddRow($newRow);
                } else {
                    $bo->addBRule('Falied to apply tds as the rate for combination of Person Type and section was not found');
                }
            }
        }
    }

    public static function CalculateTds($bo, $supplier_id, $total_amt, $total_amt_fc, $bill_amt, $bill_amt_fc) {
        if ($total_amt > 0) {
            //Add rows to bill_tds_tran
            //Get rows from ap.tds_rate for selected supplier
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('SELECT a.person_type_id, a.section_id, a.base_rate_perc, a.ecess_perc, a.surcharge_perc, a.effective_from,
                                            a.en_round_type
                                        FROM tds.rate a
                                        WHERE a.person_type_id = :pperson_type_id 
                                                AND a.section_id = :psection_id 
                                                and a.effective_from = (Select max(b.effective_from) as effective_from from tds.rate b
                                                                        where b.effective_from <= :pdoc_date And b.person_type_id = :pperson_type_id 
                                                                                AND b.section_id = :psection_id )
                                        ');
            $cmm->addParam('pperson_type_id', $bo->btt_person_type_id);
            $cmm->addParam('psection_id', $bo->btt_section_id);
            $cmm->addParam('pdoc_date', $bo->doc_date);

            $resultFromTDSRate = \app\cwf\vsla\data\DataConnect::getData($cmm);

            if (count($resultFromTDSRate->Rows()) == 1) {
                $rowTDSRate = $resultFromTDSRate->Rows()[0];

                $bo->btt_person_type_id = $rowTDSRate['person_type_id'];
                $bo->btt_section_id = $rowTDSRate['section_id'];
                $bo->btt_doc_date = $bo->doc_date;
                $bo->btt_company_id = $bo->company_id;
                $bo->btt_branch_id = $bo->branch_id;
                $bo->btt_supplier_id = $supplier_id;
                $bo->btt_bill_amt = $bill_amt;
                $bo->btt_amt_for_tds = $total_amt;
                $bo->btt_tds_base_rate_perc = $rowTDSRate['base_rate_perc'];
                $bo->btt_tds_base_rate_amt = self::RoundOffAmt((($total_amt * $rowTDSRate['base_rate_perc']) / 100), $rowTDSRate['en_round_type']);
                $bo->btt_tds_ecess_perc = $rowTDSRate['ecess_perc'];
                $bo->btt_tds_ecess_amt = self::RoundOffAmt((($total_amt * $rowTDSRate['ecess_perc']) / 100), $rowTDSRate['en_round_type']);
                $bo->btt_tds_surcharge_perc = $rowTDSRate['surcharge_perc'];
                $bo->btt_tds_surcharge_amt = self::RoundOffAmt((($total_amt * $rowTDSRate['surcharge_perc']) / 100), $rowTDSRate['en_round_type']);

                if ($bo->fc_type_id != 0) {
                    $bo->btt_tds_base_rate_amt_fc = self::RoundOffAmt((($total_amt_fc * $rowTDSRate['base_rate_perc']) / 100), $rowTDSRate['en_round_type']);
                    $bo->btt_tds_ecess_amt_fc = self::RoundOffAmt((($total_amt_fc * $rowTDSRate['ecess_perc']) / 100), $rowTDSRate['en_round_type']);
                    $bo->btt_tds_surcharge_amt_fc = self::RoundOffAmt((($total_amt_fc * $rowTDSRate['surcharge_perc']) / 100), $rowTDSRate['en_round_type']);
                    $bo->btt_bill_amt_fc = $bill_amt_fc;
                }
            } else {
                $bo->addBRule('Falied to apply tds as the rate for combination of Person Type and section was not found');
            }
        }
        else{
            $bo->btt_tds_base_rate_amt = 0;
            $bo->btt_tds_base_rate_amt_fc = 0;
            $bo->btt_tds_ecess_amt = 0;
            $bo->btt_tds_ecess_amt_fc = 0;
            $bo->btt_tds_surcharge_amt = 0;
            $bo->btt_tds_surcharge_amt_fc = 0;
        }
    }

    public static function ValidateTDSOnUnpost($bo, $voucher_id) {
        //Get data from ap.supplier_tax_info for selected supplier
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("SELECT payment_id, voucher_id as cnt from tds.bill_tds_tran where voucher_id = :pvoucher_id and payment_id !=''");
        $cmm->addParam('pvoucher_id', $voucher_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($result->Rows()) > 0) {
            $msgstr = '';
            foreach ($result->Rows() as $row) {
                if ($msgstr == '') {
                    $msgstr = $row['payment_id'];
                } else {
                    $msgstr = $msgstr . ', ' . $row['payment_id'];
                }
            }
            $bo->addBRule('Cannot Unpost as Payaments(s) - ' . $msgstr . ' are already made.');
        }
    }

    private static function RoundOffAmt($tds_amt, $en_round_type) {
        if ($en_round_type == self::ROUND_OFF_TENTH_DECIMAL) {
            return round($tds_amt, 1);
        } else if ($en_round_type == self::ROUND_OFF_WHOLE_DIGIT) {
            return round($tds_amt, 0);
        } else if ($en_round_type == self::DO_NOT_ROUND) {
            return round($tds_amt, \app\cwf\vsla\Math::$amtScale);
        } else if ($en_round_type == self::ROUND_UP_WHOLE_DIGIT) {
            return ceil($tds_amt);
        } else if ($en_round_type == self::ROUND_DOWN_WHOLE_DIGIT) {
            return floor($tds_amt);
        } else {
            return round($tds_amt, \app\cwf\vsla\Math::$amtScale);
        }
    }

}

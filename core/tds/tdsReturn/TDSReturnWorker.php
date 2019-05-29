<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\core\tds\tdsReturn;
use YaLinqo\Enumerable;
/**
 * Description of TDSReturnWorker
 *
 * @author priyanka
 */
class TDSReturnWorker {
    //put your code here
    private $file_header = null;
    private $batch_header = null;
    private $challan_detail = null;
    private $deductee_detail = null;
    private $challan_detail_array=null;
    private $challan_detail_array=null;
    private $deductee_name = '';
    public function GenerateOutput($tds_return_id){
        $this->CreateFileHeader();
        $this->CreateBatchHeader();
//        $this->CreateChallanDetail();
//        $this->CreateDeducteeDetail();
        
        // Fill Data
        $this->FillFileHeader($tds_return_id);
        $this->FillBatchHeader($tds_return_id);
        $this->FillChallanDetail($tds_return_id);
    }
    
    private function CreateFileHeader(){
        $this->file_header = array();
        $this->file_header['1_line_no'] = 1;
        $this->file_header['2_record_type'] = 'FH';
        $this->file_header['3_file_type'] = 'NS1';
        $this->file_header['4_upload_type'] = 'R';
        $this->file_header['5_file_creation_date'] = '';
        $this->file_header['6_file_sequence_no'] = 1;
        $this->file_header['7_uploader_type']='D';
        $this->file_header['8_deductor_tan']='';
        $this->file_header['9_total_bachtes']=0;
        $this->file_header['10_return_preperation_untility'] = 'NSDLRPU4.2';
        $this->file_header['11_recod_hash'] = '';
        $this->file_header['12_fvu_hash'] = '';
        $this->file_header['13_file_hash'] = '';
        $this->file_header['14_sam_version'] = '';
        $this->file_header['15_sam_hash'] = '';
        $this->file_header['16_scm_version'] = '';
        $this->file_header['17_scm_hash'] = '';
        $this->file_header['18_consolidated_file_hash'] = '';
    }
    
    private function CreateBatchHeader(){
        $this->batch_header = array();
        $this->batch_header['1_line_no'] = 2;
        $this->batch_header['2_record_type'] = 'BH';
        $this->batch_header['3_batch_number'] = 1;
        $this->batch_header['4_challan_count'] = 0;
        $this->batch_header['5_form_number'] = '26Q';
        $this->batch_header['6_transaction_type'] = '';
        $this->batch_header['7_batch_updation_indicator'] = '';
        $this->batch_header['8_original_token_no'] = '';
        $this->batch_header['9_previous_stmt_token_no'] = 0;
        $this->batch_header['10_current_token_no'] = '';
        $this->batch_header['11_token_date'] = '';
        $this->batch_header['12_last_deductor_tan'] = '';
        $this->batch_header['13_deductor_tan'] = '';
        $this->batch_header['14_tin_receipt_no'] = '';
        $this->batch_header['15_deductor_pan'] = '';
        $this->batch_header['16_assessment_yr'] = 0;
        $this->batch_header['17_fin_yr'] = 0;
        $this->batch_header['18_period'] = '';
        $this->batch_header['19_deductor'] = '';
        $this->batch_header['20_deductor_branch'] = '';
        $this->batch_header['21_deductor_address1'] = '';
        $this->batch_header['22_deductor_address2'] = '';
        $this->batch_header['23_deductor_address3'] = '';
        $this->batch_header['24_deductor_address4'] = '';
        $this->batch_header['25_deductor_address5'] = '';
        $this->batch_header['26_deductor_state'] = 0;
        $this->batch_header['27_deductor_pincode'] = 0;
        $this->batch_header['28_deductor_email'] = '';
        $this->batch_header['29_deductor_std_code'] = 0;
        $this->batch_header['30_deductor_telephone'] = 0;
        $this->batch_header['31_change_of_address_of_deductor'] = '';
        $this->batch_header['32_deductor_type'] = '';
        $this->batch_header['33_deductee_name'] = '';
        $this->batch_header['34_deductee_designation'] = '';
        $this->batch_header['35_deductee_address1'] = '';
        $this->batch_header['36_deductee_address2'] = '';
        $this->batch_header['37_deductee_address3'] = '';
        $this->batch_header['38_deductee_address4'] = '';
        $this->batch_header['39_deductee_address5'] = '';
        $this->batch_header['40_deductee_state'] = 0;
        $this->batch_header['41_deductee_pin'] = 0;
        $this->batch_header['42_deductee_email'] = '';
        $this->batch_header['43_deductee_mobile'] = '';
        $this->batch_header['44_deductee_std_code'] = 0;
        $this->batch_header['45_deductee_telephone'] = 0;
        $this->batch_header['46_change_of_address_of_deductee'] = '';
        $this->batch_header['47_batch_total_amt'] = 0;
        $this->batch_header['48_unmatched_challan_cnt'] = '';
        $this->batch_header['49_salary_details_count'] = '';
        $this->batch_header['50_batch_gross_total'] = '';
        $this->batch_header['51_ao_approval'] = 'N';
        $this->batch_header['52_filed_regular_stmt_for_26q'] = 'Y';
        $this->batch_header['53_last_deductor_type'] = '';
        $this->batch_header['54_state_name'] = '';
        $this->batch_header['55_pao_code'] = '';
        $this->batch_header['56_ddo_code'] = '';
        $this->batch_header['57_ministry_name'] = '';
        $this->batch_header['58_ministry_name_other'] = '';
        $this->batch_header['59_deductee_pan'] = '';
        $this->batch_header['60_pao_registration_no'] = '';
        $this->batch_header['61_ddo_registration_no'] = '';
        $this->batch_header['62_deductor_std_alternate'] = '';
        $this->batch_header['63_deductor_telephone_alternate'] = '';
        $this->batch_header['64_deductor_email_alternate'] = '';
        $this->batch_header['65_deductee_std_alternate'] = '';
        $this->batch_header['66_deductee_telephone_alternate'] = '';
        $this->batch_header['67_deductee_email_alternate'] = '';
        $this->batch_header['68_ain'] = '';
        $this->batch_header['69_record_hash'] = '';
    }
    
    private function CreateChallanDetail(){
        $this->challan_detail = array();
        $this->challan_detail['1_line_no'] = 0;
        $this->challan_detail['2_record_type'] = 'CD';
        $this->challan_detail['3_batch_number'] = 1;
        $this->challan_detail['4_challan_detail_record_no'] = 0;
        $this->challan_detail['5_deductee_cnt'] = 0;
        $this->challan_detail['6_nil_challan_indicator'] = 'N';
        $this->challan_detail['7_challan_updation_indicator'] = '';
        $this->challan_detail['8_filler_3'] = '';
        $this->challan_detail['9_filler_4'] = '';
        $this->challan_detail['10_filler_5'] = '';
        $this->challan_detail['11_last_bank_challan_no'] = '';
        $this->challan_detail['12_bank_challan_no'] = '';
        $this->challan_detail['13_last_transfer_voucher_no'] = '';
        $this->challan_detail['14_ddo_serial_no'] = 0;
        $this->challan_detail['15_last_bank_branch_code'] = '';
        $this->challan_detail['16_bank_branch_code'] = 0;
        $this->challan_detail['17_last_challan_date'] = '' ;
        $this->challan_detail['18_challan_date'] = '';
        $this->challan_detail['19_filler_6'] = '';
        $this->challan_detail['20_filler_7'] = '';
        $this->challan_detail['21_section'] = '';
        $this->challan_detail['22_oltas_tds_income_tax'] = 0;
        $this->challan_detail['23_oltas_tds_surchage'] = 0;
        $this->challan_detail['24_oltas_tds_cess'] = 0;
        $this->challan_detail['25_oltas_interest_amt'] = 0;
        $this->challan_detail['26_oltas_other_amt'] = 0;
        $this->challan_detail['27_total_deposit_amt'] = 0 ;
        $this->challan_detail['28_last_total_deposit_amt'] = 0;
        $this->challan_detail['29_total_tax_deposit_amt'] = 0;
        $this->challan_detail['30_tds_income_tax'] = 0;
        $this->challan_detail['31_tds_surchage'] = 0;
        $this->challan_detail['32_tds_cess'] = 0;
        $this->challan_detail['33_total_income_tax_deducted'] = 0;
        $this->challan_detail['34_interest_amt'] = 0;
        $this->challan_detail['35_other_amt'] = 0;
        $this->challan_detail['36_cheque_no'] = '';
        $this->challan_detail['37_book_or_cash'] = '';
        $this->challan_detail['38_remarks'] = '';
        $this->challan_detail['39_fee'] = '';
        $this->challan_detail['40_challan_minor_head'] = '';
        $this->challan_detail['41_record_hash'] = '';
    }
    
    private function CreateDeducteeDetail(){
        $this->deductee_detail = array();
        $this->deductee_detail['1_line_no'] = 0;
        $this->deductee_detail['2_record_type'] = 'DD';
        $this->deductee_detail['3_batch_number'] = 1;
        $this->deductee_detail['4_challan_detail_record_no'] = 0;
        $this->deductee_detail['5_deductee_detail_record_no'] = 0;
        $this->deductee_detail['6_mode'] = 'O';
        $this->deductee_detail['7_employee_serial_no'] = '';
        $this->deductee_detail['8_deductee_code'] = '';
        $this->deductee_detail['9_party_pan'] = '';
        $this->deductee_detail['10_deductee_pan'] = '';
        $this->deductee_detail['11_last_deductee_ref_no'] = '';
        $this->deductee_detail['12_deductee_ref_no'] = '';
        $this->deductee_detail['13_deductee_name'] = '';
        $this->deductee_detail['14_tds_income_tax'] = 0;
        $this->deductee_detail['15_tds_surcharge'] = 0;
        $this->deductee_detail['16_tds_ecess'] = 0;
        $this->deductee_detail['17_total_tax_deducted'] = 0;
        $this->deductee_detail['18_last_total_tax_deducted'] = 0;
        $this->deductee_detail['19_total_tax_deposited'] = 0;
        $this->deductee_detail['20_last_total_tax_deposited'] = 0;
        $this->deductee_detail['21_total_purchase_value'] = 0;
        $this->deductee_detail['22_payment_amt'] = 0;
        $this->deductee_detail['23_payment_date'] = '';
        $this->deductee_detail['24_deduction_date'] = '';
        $this->deductee_detail['25_deposit_date'] = '';
        $this->deductee_detail['26_tax_deduction_rate'] = 0;
        $this->deductee_detail['27_grossing_up_indicator'] = '';
        $this->deductee_detail['28_book_or_cash'] = '';
        $this->deductee_detail['29_tax_deduction_furnishing_date'] = '';
        $this->deductee_detail['30_remarks1'] = '';
        $this->deductee_detail['31_remarks2'] = '';
        $this->deductee_detail['32_remarks3'] = '';
        $this->deductee_detail['33_section_code'] = '';
        $this->deductee_detail['34_certificate_no'] = '';
        $this->deductee_detail['35_filler1'] = '';
        $this->deductee_detail['36_filler2'] = '';
        $this->deductee_detail['37_filler3'] = '';
        $this->deductee_detail['38_filler4'] = '';
        $this->deductee_detail['39_record_hash'] = '';
    }
    
    private function FillFileHeader($tds_return_id){        
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select a.doc_date, (select tan from tds.deductor_info) as tan
                                from tds.tds_return_control a
                                where a.voucher_id = :ptds_return_id');
        $cmm->addParam('ptds_return_id', $tds_return_id);
        $dtfh= \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dtfh->Rows())>0){
            $this->file_header['5_file_creation_date'] = date('dmY', strtotime($dtfh->Rows()[0]['doc_date']));
            $this->file_header['8_deductor_tan'] = strtoupper($dtfh->Rows()[0]['tan']);
        }        
    }
    
    private function FillBatchHeader($tds_return_id){        
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select a.*, b.deductor_code from tds.deductor_info a inner Join tds.deductor_type b on a.deductor_type_id=b.deductor_type_id');
        $dt= \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows())>0){
            $this->batch_header['13_deductor_tan'] = strtoupper($dt->Rows()[0]['tan']);
            $this->batch_header['15_deductor_pan'] = strtoupper($dt->Rows()[0]['pan']);
            $this->batch_header['19_deductor'] = $dt->Rows()[0]['deductor_name'];
            $this->batch_header['20_deductor_branch'] = $dt->Rows()[0]['branch_division'];            
            $this->batch_header['21_deductor_address1'] = $dt->Rows()[0]['flat_no'];
            $this->batch_header['22_deductor_address2'] = $dt->Rows()[0]['building_premises'];
            $this->batch_header['23_deductor_address3'] = $dt->Rows()[0]['area_location'];
            $this->batch_header['24_deductor_address4'] = $dt->Rows()[0]['road_street_lane'];
            $this->batch_header['25_deductor_address5'] = $dt->Rows()[0]['town_city_district'];            
            $this->batch_header['26_deductor_state'] = $dt->Rows()[0]['state_id'];
            $this->batch_header['27_deductor_pincode'] = $dt->Rows()[0]['pin_code'];
            $this->batch_header['28_deductor_email'] = $dt->Rows()[0]['email'];
            $this->batch_header['29_deductor_std_code'] = $dt->Rows()[0]['std_code'];
            $this->batch_header['30_deductor_telephone'] = $dt->Rows()[0]['telephone_no'];
            $this->batch_header['32_deductor_type'] = $dt->Rows()[0]['deductor_code'];
            $this->batch_header['33_deductee_name'] = $dt->Rows()[0]['p_deductor_name'];
            $this->batch_header['34_deductee_designation'] = $dt->Rows()[0]['p_designation'];           
            $this->batch_header['35_deductee_address1'] = $dt->Rows()[0]['p_flat_no'];
            $this->batch_header['36_deductee_address2'] = $dt->Rows()[0]['p_building_premises'];
            $this->batch_header['37_deductee_address3'] = $dt->Rows()[0]['p_area_location'];
            $this->batch_header['38_deductee_address4'] = $dt->Rows()[0]['p_road_street_lane'];
            $this->batch_header['39_deductee_address5'] = $dt->Rows()[0]['p_town_city_district'];            
            $this->batch_header['40_deductee_state'] = $dt->Rows()[0]['p_state_id'];
            $this->batch_header['41_deductee_pin'] = $dt->Rows()[0]['p_pin_code'];
            $this->batch_header['42_deductee_email'] = $dt->Rows()[0]['p_email'];  
            $this->batch_header['43_deductee_mobile'] = $dt->Rows()[0]['p_mobile_no']; 
            $this->batch_header['44_deductee_std_code'] = $dt->Rows()[0]['p_std_code'];
            $this->batch_header['45_deductee_telephone'] = $dt->Rows()[0]['p_telephone_no']; 
                    
            if($dt->Rows()[0]['deductor_type_id'] == 2 || $dt->Rows()[0]['deductor_type_id'] == 4 ||
                    $dt->Rows()[0]['deductor_type_id'] == 6 || $dt->Rows()[0]['deductor_type_id'] == 8){
                    $this->batch_header['54_state_name'] = $dt->Rows()[0]['state_id'];
            }
            
            if($dt->Rows()[0]['std_code_alternate'] != '' && $dt->Rows()[0]['telephone_no_alternate'] != ''){                
                $this->batch_header['62_deductor_std_alternate'] = $dt->Rows()[0]['std_code_alternate'];
                $this->batch_header['63_deductor_telephone_alternate'] = $dt->Rows()[0]['telephone_no_alternate'];
            }            
            
            $this->batch_header['64_deductor_email_alternate'] = $dt->Rows()[0]['email_alternate'];
            if($dt->Rows()[0]['p_std_code_alternate'] != '' && $dt->Rows()[0]['p_telephone_no_alternate'] != ''){                
                $this->batch_header['65_deductee_std_alternate'] = $dt->Rows()[0]['p_std_code_alternate'];
                $this->batch_header['66_deductee_telephone_alternate'] = $dt->Rows()[0]['p_telephone_no_alternate'];
            } 
            
            $this->batch_header['67_deductee_email_alternate'] =  $dt->Rows()[0]['p_email_alternate'];
        } 
        
               
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select a.prev_quarter_token_no, a.finyear, b.year_begin, b.year_end, a.return_quarter, a.is_address_changed, a.is_deductee_address_changed,
                                    a.amt
                                from tds.tds_return_control a
                                Inner Join sys.finyear b on a.finyear=b.finyear_code
                                where a.voucher_id = :ptds_return_id');
        $cmm->addParam('ptds_return_id', $tds_return_id);
        $dt_return= \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt_return->Rows())>0){
            $this->batch_header['9_previous_stmt_token_no'] = $dt_return->Rows()[0]['prev_quarter_token_no'];
            
            $assessment_yr= date('Y', strtotime('+1 year',  strtotime($dt_return->Rows()[0]['year_begin'])));
            $assessment_yr= $assessment_yr.date('y', strtotime('+1 year',  strtotime($dt_return->Rows()[0]['year_end'])));
            $financial_yr= date('Y', strtotime($dt_return->Rows()[0]['year_begin']));
            $financial_yr= $financial_yr.date('y', strtotime($dt_return->Rows()[0]['year_end']));
            
            
            $this->batch_header['16_assessment_yr'] = $assessment_yr;            
            $this->batch_header['17_fin_yr'] = $financial_yr;
            $this->batch_header['18_period'] = $dt_return->Rows()[0]['return_quarter'];
            if($dt_return->Rows()[0]['is_address_changed'] == true){
                $this->batch_header['31_change_of_address_of_deductor'] = 'Y';
            }
            else{
                $this->batch_header['31_change_of_address_of_deductor'] = 'N';
            }
            
            if($dt_return->Rows()[0]['is_deductee_address_changed'] == true){
                $this->batch_header['46_change_of_address_of_deductee'] = 'Y';
            }
            else{
                $this->batch_header['46_change_of_address_of_deductee'] = 'N';
            }
            
            $this->batch_header['47_batch_total_amt'] = $dt_return->Rows()[0]['amt'];
            
        }
    }
    
    private function FillChallanDetail($tds_return_id){
        $this->challan_detail_array = array();
        $challan_detail =  null;
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select a.sl_no, count(d.bill_tds_tran_id) as deductee_count, c.challan_bsr, c.challan_serial, c.doc_date as payment_date, a.payment_id,
                                    sum(d.tds_base_rate_amt) as tds_base_rate_amt, sum(d.tds_ecess_amt) as tds_ecess_amt, sum(d.tds_surcharge_amt) as tds_surcharge_amt, 
                                    sum(c.tds_total_amt) as tds_total_amt, sum(interest_amt) as interest_amt, sum(c.penalty_amt) as penalty_amt, sum(c.amt) as amt
                            from tds.tds_return_challan_tran a
                            Inner Join tds.tds_payment_control c on a.payment_id = c.voucher_id
                            Inner join tds.bill_tds_tran d on a.payment_id = d.payment_id 
                            where a.voucher_id = :ptds_return_id                                
                            group by a.sl_no, c.challan_bsr, c.challan_serial, c.doc_date, a.payment_id');
        $cmm->addParam('ptds_return_id', $tds_return_id);
        $dtcd= \app\cwf\vsla\data\DataConnect::getData($cmm);
        $running_seq_no=2;
        foreach($dtcd->Rows() as $challan_row){
            $running_seq_no = $running_seq_no + 1;
            $challan_detail = array();
            $challan_detail['1_line_no'] = $running_seq_no;
            $challan_detail['2_record_type'] = 'CD';
            $challan_detail['3_batch_number'] = 1;
            $challan_detail['4_challan_detail_record_no'] = $challan_row['sl_no'];
            $challan_detail['5_deductee_cnt'] =  $challan_row['deductee_count'];
            $challan_detail['6_nil_challan_indicator'] = 'N';
            $challan_detail['7_challan_updation_indicator'] = '';
            $challan_detail['8_filler_3'] = '';
            $challan_detail['9_filler_4'] = '';
            $challan_detail['10_filler_5'] = '';
            $challan_detail['11_last_bank_challan_no'] = '';
            $challan_detail['12_bank_challan_no'] = $challan_row['challan_bsr'];
            $challan_detail['13_last_transfer_voucher_no'] = '';
            $challan_detail['14_ddo_serial_no'] = $challan_row['challan_serial'];
            $challan_detail['15_last_bank_branch_code'] = '';
            $challan_detail['16_bank_branch_code'] = 0;
            $challan_detail['17_last_challan_date'] = '' ;
            $challan_detail['18_challan_date'] = date('dmY', strtotime($challan_row['payment_date']));;
            $challan_detail['19_filler_6'] = '';
            $challan_detail['20_filler_7'] = '';
            $challan_detail['21_section'] = '';
            $challan_detail['22_oltas_tds_income_tax'] =  $challan_row['tds_base_rate_amt'];
            $challan_detail['23_oltas_tds_surchage'] =  $challan_row['tds_surcharge_amt'];
            $challan_detail['24_oltas_tds_cess'] =  $challan_row['tds_ecess_amt'];
            $challan_detail['25_oltas_interest_amt'] =  $challan_row['interest_amt'];
            $challan_detail['26_oltas_other_amt'] =  $challan_row['penalty_amt'];
            $challan_detail['27_total_deposit_amt'] =  $challan_row['tds_total_amt'];
            $challan_detail['28_last_total_deposit_amt'] = '';
            $challan_detail['29_total_tax_deposit_amt'] = $challan_row['amt'];
            $challan_detail['30_tds_income_tax'] = $challan_row['tds_base_rate_amt'];
            $challan_detail['31_tds_surchage'] = $challan_row['tds_surcharge_amt'];
            $challan_detail['32_tds_cess'] = $challan_row['tds_ecess_amt'];
            $challan_detail['33_total_income_tax_deducted'] = $challan_row['tds_total_amt'];
            $challan_detail['34_interest_amt'] = $challan_row['interest_amt'];
            $challan_detail['35_other_amt'] =  $challan_row['penalty_amt'] ;
            $challan_detail['36_cheque_no'] = '';
            $challan_detail['37_book_or_cash'] = 'N';
            $challan_detail['38_remarks'] = '';
            $challan_detail['39_fee'] = 0;
            $challan_detail['40_challan_minor_head'] = '';
            $challan_detail['41_record_hash'] = '';
            
            // Fill Deductee Detail for Challan
            $challan_detail['deductee_detail'] = array();
            $dtdd = $this->FillDeducteeDetail($challan_row['payment_id']);
            $deductee_detail = null;
            foreach($dtdd->Rows() as $dd_row){
                $running_seq_no = $running_seq_no + 1;
                $deductee_detail = array();
                $deductee_detail['1_line_no'] = $running_seq_no;
                $deductee_detail['2_record_type'] = 'DD';
                $deductee_detail['3_batch_number'] = 1;
                $deductee_detail['4_challan_detail_record_no'] =  $challan_row['sl_no'];
                $deductee_detail['5_deductee_detail_record_no'] =  $dd_row['row_number'];
                $deductee_detail['6_mode'] = 'O';
                $deductee_detail['7_employee_serial_no'] = '';
                $deductee_detail['8_deductee_code'] = '';
                $deductee_detail['9_party_pan'] = '';
                $deductee_detail['10_deductee_pan'] = '';
                $deductee_detail['11_last_deductee_ref_no'] = '';
                $deductee_detail['12_deductee_ref_no'] = '';
                $deductee_detail['13_deductee_name'] = $this->deductee_name;
                $deductee_detail['14_tds_income_tax'] = $dd_row['tds_base_rate_amt'];
                $deductee_detail['15_tds_surcharge'] = $dd_row['tds_surcharge_amt'];
                $deductee_detail['16_tds_ecess'] = $dd_row['tds_ecess_amt'];
                $deductee_detail['17_total_tax_deducted'] = $dd_row['tds_total_amt'];
                $deductee_detail['18_last_total_tax_deducted'] = 0;
                $deductee_detail['19_total_tax_deposited'] = $dd_row['tds_total_amt'];
                $deductee_detail['20_last_total_tax_deposited'] = 0;
                $deductee_detail['21_total_purchase_value'] = 0;
                $deductee_detail['22_payment_amt'] = 0;
                $deductee_detail['23_payment_date'] = '';
                $deductee_detail['24_deduction_date'] = '';
                $deductee_detail['25_deposit_date'] = '';
                $deductee_detail['26_tax_deduction_rate'] = 0;
                $deductee_detail['27_grossing_up_indicator'] = '';
                $deductee_detail['28_book_or_cash'] = '';
                $deductee_detail['29_tax_deduction_furnishing_date'] = '';
                $deductee_detail['30_remarks1'] = '';
                $deductee_detail['31_remarks2'] = '';
                $deductee_detail['32_remarks3'] = '';
                $deductee_detail['33_section_code'] = '';
                $deductee_detail['34_certificate_no'] = '';
                $deductee_detail['35_filler1'] = '';
                $deductee_detail['36_filler2'] = '';
                $deductee_detail['37_filler3'] = '';
                $deductee_detail['38_filler4'] = '';
                $deductee_detail['39_record_hash'] = '';
            }
            array_push($this->challan_detail_array, $challan_detail);
            
//            $running_seq_no = $running_seq_no + $challan_row['deductee_count'];
        }
    }
    
    private function FillDeducteeDetail($payment_id){
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select row_number() over (ORDER BY d.payment_id), d.payment_date, d.payment_id, d.voucher_id,
                                sum(d.tds_base_rate_amt) as tds_base_rate_amt, sum(tds_ecess_amt) as tds_ecess_amt, sum(tds_surcharge_amt) as tds_surcharge_amt, 
                                sum(d.tds_base_rate_amt + tds_ecess_amt + tds_surcharge_amt) as tds_total_amt
                            from tds.tds_return_challan_tran a
                            Inner Join tds.tds_payment_control c on a.payment_id = c.voucher_id
                            Inner join tds.bill_tds_tran d on a.payment_id = d.payment_id 
                            where a.payment_id = :ppayment_id   
                            group by a.sl_no, c.challan_bsr, c.challan_serial, c.doc_date, d.payment_date, d.payment_id, d.voucher_id');
        $cmm->addParam('ppayment_id', $payment_id);
        $dtdd= \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dtdd;       
    }
}

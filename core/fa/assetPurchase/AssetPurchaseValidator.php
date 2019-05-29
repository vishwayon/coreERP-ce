<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\fa\assetPurchase;
use YaLinqo\Enumerable;
/**
 * Description of AssetPurchaseValidator
 *
 * @author girish
 */
class AssetPurchaseValidator extends \app\cwf\vsla\xmlbo\ValidatorBase {
    
    public function validateAssetPurchaseEditForm() {
        // conduct default form validations
        $formView = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($this->modulePath, $this->formName);
        $this->validateUsingForm($this->bo, $formView);
        
        // conduct business rule validations
        $this->validateBusinessRules();
    }
    
    private function validateBusinessRules() {                        
        if(count($this->bo->ap_tran->Rows())==0){             
            $this->bo->addBRule('Atleast one row in Account Info is required.');
        }
        
        $rowno=0;
        foreach ($this->bo->ap_lc_tran->Rows() as $lc_row) {
            if($lc_row['supplier_paid'] == FALSE){
                if ($lc_row['account_affected_id'] == -1){
                    $this->bo->addBRule('Landed Cost - Row['.$rowno.'] : Liability Account is required');
                }
            }
        } 
        
        \app\core\tx\taxSchedule\worker\TaxScheduleHelper::CalculateTaxOnSave($this->bo);

        $rowcnt=0;
        foreach ($this->bo->ap_tran->Rows() as $row) {            
            $rowcnt=$rowcnt+1;
            if($row['asset_qty'] == 0){                
                $this->bo->addBRule('Account Info - Row['. $rowcnt. '] : Asset Qty is required');
            } 
       }
       
       
        foreach ($this->bo->ap_lc_tran->Rows() as &$reflc_row) {
            if($reflc_row['supplier_paid'] == true){
                $reflc_row['account_affected_id'] = -1;
            }
        }

        // Validate Tax
        \app\core\tx\taxSchedule\worker\TaxScheduleHelper::ValidateTax($this->bo);

       
       // If depreciation document for the period is created then don't allow to make purchase        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("SELECT max(dep_date_to) as max_date  FROM fa.ad_control where company_id=:pcompany_id And branch_id=:pbranch_id");
        $cmm->addParam('pcompany_id', $this->bo->company_id);
        $cmm->addParam('pbranch_id', $this->bo->branch_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0){
            if(strtotime($result->Rows()[0]['max_date']) >= strtotime($this->bo->doc_date)){
                $this->bo->addBRule('Asset Purchase is not allowed because Depreciation upto '. $result->Rows()[0]['max_date'] . ' is calculated.');
            }
        }  
        
        
        //  Validate duplicate bill no for a supplier
        if ($this->bo->bill_no != 'BNR'){
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select bill_no, ap_id from fa.ap_control where account_id=:paccount_id and bill_no ilike :pbill_no and ap_id!=:pap_id');
            $cmm->addParam('pap_id', $this->bo->ap_id);
            $cmm->addParam('paccount_id', $this->bo->account_id);
            $cmm->addParam('pbill_no', $this->bo->bill_no);
            $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if(count($result->Rows())>0) {
                $this->bo->addBRule('Bill No already used for the selected Supplier Account in ('.$result->Rows()[0]['ap_id'].'). Duplicate Bill No not allowed.');
            }      
            else{
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('Select bill_no, voucher_id from ac.rl_pl where account_id=:paccount_id and bill_no ilike :pbill_no and voucher_id!=:pvoucher_id');
                $cmm->addParam('pvoucher_id', $this->bo->ap_id);
                $cmm->addParam('paccount_id', $this->bo->account_id);
                $cmm->addParam('pbill_no', $this->bo->bill_no);
                $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
                if(count($result->Rows())>0) {
                    $this->bo->addBRule('Bill No already used for the selected Ledger Account in ('.$result->Rows()[0]['voucher_id'].'). Duplicate Bill No not allowed.');
                } 
            }       
        }
        
        //Calculate Gross Total
        $this->bo->gross_credit_amt = round(Enumerable::from($this->bo->ap_tran->Rows())->sum('$a==>$a["purchase_amt"]'), \app\cwf\vsla\Math::$amtScale);
        
        //Calculate Discount Amount
        if ($this->bo->disc_is_value == true){     
            $this->bo->disc_pcnt = 0;
        }
        else{    
            $this->bo->disc_amt = round(($this->bo->gross_credit_amt * $this->bo->disc_pcnt)/100, \app\cwf\vsla\Math::$amtScale); 
        }

        $this->bo->misc_taxable_amt =  round(Enumerable::from($this->bo->ap_lc_tran->Rows())->where('$a==>$a["is_taxable"] == true && $a["supplier_paid"] == true')->sum('$a==>$a["debit_amt"]'), \app\cwf\vsla\Math::$amtScale);
        
        //Calculate before_tax_amt
        $this->bo->before_tax_amt = round($this->bo->gross_credit_amt - $this->bo->disc_amt + $this->bo->round_off_amt + $this->bo->misc_taxable_amt, \app\cwf\vsla\Math::$amtScale);
        
        $this->bo->tax_amt =  round(Enumerable::from($this->bo->tax_tran->Rows())->where('$a==>$a["supplier_paid"] == true')->sum('$a==>$a["tax_amt"]'), \app\cwf\vsla\Math::$amtScale);
       
        $this->bo->misc_non_taxable_amt =  round(Enumerable::from($this->bo->ap_lc_tran->Rows())->where('$a==>$a["is_taxable"] == false && $a["supplier_paid"] == true' )->sum('$a==>$a["debit_amt"]'), \app\cwf\vsla\Math::$amtScale);
        
        //Calculate Total Amt
        $this->bo->credit_amt = round($this->bo->before_tax_amt + $this->bo->tax_amt + $this->bo->misc_non_taxable_amt, \app\cwf\vsla\Math::$amtScale);
        
        $this->bo->total_purchase_amt = round($this->bo->gross_credit_amt - $this->bo->disc_amt + $this->bo->round_off_amt + $this->bo->tax_amt + $this->bo->lc_amt, \app\cwf\vsla\Math::$amtScale);
        
        //Calculate Net Amt
        $this->bo->net_credit_amt = round($this->bo->credit_amt, \app\cwf\vsla\Math::$amtScale);

        // check account type for selected account.
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select account_type_id from ac.account_head where account_id=:paccount_id');
        $cmm->addParam('paccount_id', $this->bo->account_id);
        $dt= \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows())>0){
            $acc_type_id=$dt->Rows()[0]['account_type_id'];
            
            if($this->bo->en_purchase_type==0){
                if($acc_type_id !=2){
                    $this->bo->addBRule('Please select Cash account.'); 
                }
            }
            else if($this->bo->en_purchase_type==1){
                if($acc_type_id !=1 ){
                    $this->bo->addBRule('Please select Bank account.'); 
                }
            }
            else if($this->bo->en_purchase_type==2){
                if($acc_type_id !=12){
                    $this->bo->addBRule('Please select Credit account.'); 
                }
            }
            else if($this->bo->en_purchase_type==3){
                if($acc_type_id == 0 || $acc_type_id == 1 || $acc_type_id == 2 || $acc_type_id == 7 || $acc_type_id == 12 || $acc_type_id == 23
                         || $acc_type_id == 24 || $acc_type_id == 21 || $acc_type_id == 22 || $acc_type_id == 18 || $acc_type_id == 38){
                    $this->bo->addBRule('Please select Journal account.'); 
                }
            }
        }
        
        $currency='';
        $subCurrency='';
        $currency_system='';
        $cmm=new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.branch where branch_id=:pbranch_id');
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $dtbr= \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dtbr->Rows())>0){
            $currency=$dtbr->Rows()[0]['currency'];
            $subCurrency=$dtbr->Rows()[0]['sub_currency'];
            $currency_system=$dtbr->Rows()[0]['currency_system'];
        }
        
        $val=sprintf ("%.".\app\cwf\vsla\Math::$amtScale."f", $this->bo->credit_amt);
        $this->bo->amt_in_words=  \app\cwf\vsla\utils\AmtInWords::GetAmtInWords($val, $currency, $subCurrency, $currency_system);     
        
    }
     
    public function validateBeforeUnpost() {
        // If depreciation document for the period is created then don't allow to unpost Asset Purchase       
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select voucher_id from fa.asset_dep_ledger
                                where asset_item_id in (Select asset_item_id from fa.asset_item_ledger where voucher_id=:pvoucher_id)
                                group by voucher_id");
        $cmm->addParam('pvoucher_id', $this->bo->ap_id);
        $result = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($result->Rows())>0){
            $msgstr='';
            foreach($result->Rows() as $row){
                if($msgstr == ''){
                    $msgstr = $row['voucher_id'];
                }
                else{
                    $msgstr = $msgstr . ', '. $row['voucher_id'];
                }
            }
            $this->bo->addBRule('Cannot Unpost as depreciation doc(s) - '. $msgstr . ' are already generated.');
        }  
        
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select count(*) as rec_count From fa.asset_item_ledger Where en_asset_tran_type<>0  
			And asset_item_id In (Select asset_item_id From fa.asset_item Where voucher_ID=:pvoucher_id)");
        $cmm->addParam('pvoucher_id', $this->bo->ap_id);
        $resultapitem = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($resultapitem->Rows())>0){
            if($resultapitem->Rows()[0]['rec_count'] > 0){
                $this->bo->addBRule('The Asset Items created from this document have been used in other documents. Unpost failed.');
            }
        }       
    }
    
    public function validateBeforePost() {
        // Compulsory method named. No implementation currently required
    }
}

<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\custToSupp;

/**
 * Description of SupplierEventHandler
 *
 * @author Vaishali
 */
class CustToSuppEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        $this->bo->supp_type_id = (\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id') * 1000000) + 1;
        $this->bo->supp_control_account_id = -1;
        $this->bo->supp_pay_term_id = -1;
    }

    public function beforeSave($cn) {
        parent::beforeSave($cn);
    }

    public function onSave($cn, $tablename) {
        if ($tablename == 'ar.customer') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select * from ar.sp_cust_to_supp_update(:pcustomer_id, :pcontrol_account_id, :psupp_type_id, :ppay_term_id)');
            $cmm->addParam('pcustomer_id', $this->bo->customer_id);
            $cmm->addParam('pcontrol_account_id', $this->bo->supp_control_account_id);
            $cmm->addParam('psupp_type_id', $this->bo->supp_type_id);
            $cmm->addParam('ppay_term_id', $this->bo->supp_pay_term_id);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
        }
    }
    
    public function resetLastUpdated($cn, $tablename, $primaryKey) {
        // Do nothing as this is only anchoring BO
    }

    public function afterCommit($generatedKeys) {
        $suppinparam = array();
        $suppinparam['supplier_id'] = $this->bo->supplier_id;
        // Create instance of Branch BO
        $suppbopath = '../core/ar/supplier/supplier.xml';
        $suppBo = new \app\cwf\vsla\xmlbo\XboBuilder($suppbopath);
        $suppboInst = $suppBo->buildBO($suppinparam);
        
        $suppboInst->supplier_id = $this->bo->supplier_id;
        $suppboInst->annex_info->Value()->has_kyc_docs = $this->bo->has_kyc_docs;
        $suppboInst->annex_info->Value()->is_overridden = $this->bo->annex_info->Value()->is_overridden;
        $suppboInst->annex_info->Value()->supp_type_id = $this->bo->supp_type_id;
        $suppboInst->annex_info->Value()->satutory_details->vat_no = $this->bo->annex_info->Value()->tax_info->vtin;
        $suppboInst->annex_info->Value()->tan = $this->bo->annex_info->Value()->tax_info->tan;
        $suppboInst->annex_info->Value()->satutory_details->gst_reg_name = $this->bo->annex_info->Value()->tax_info->gst_reg_name;
        $suppboInst->annex_info->Value()->satutory_details->pan = $this->bo->annex_info->Value()->tax_info->pan;    
        $suppboInst->annex_info->Value()->satutory_details->gst_state_id = $this->bo->annex_info->Value()->tax_info->gst_state_id;
        $suppboInst->annex_info->Value()->satutory_details->gstin = $this->bo->annex_info->Value()->tax_info->gstin;  
        $suppboInst->annex_info->Value()->satutory_details->stin = $this->bo->annex_info->Value()->tax_info->service_tax_no; 
        $suppboInst->annex_info->Value()->satutory_details->ctin = $this->bo->annex_info->Value()->tax_info->cst_no;
        $suppboInst->annex_info->Value()->satutory_details->diff_gst_name = $this->bo->annex_info->Value()->tax_info->diff_gst_name;

        $suppBo->saveBO($suppboInst, null);
    }
}

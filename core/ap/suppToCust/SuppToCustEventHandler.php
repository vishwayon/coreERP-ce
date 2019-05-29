<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\suppToCust;

/**
 * Description of SupplierEventHandler
 *
 * @author Vaishali
 */
class SuppToCustEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        $this->bo->cust_salesman_id = -1;
        $this->bo->cust_control_account_id = -1;
        $this->bo->cust_segment_id = -1;
        $this->bo->cust_pay_term_id = -1;
    }

    public function beforeSave($cn) {
        parent::beforeSave($cn);
    }

    public function onSave($cn, $tablename) {
        if ($tablename == 'ap.supplier') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select * from ap.sp_supp_to_cust_update(:psupplier_id, :psalesman_id, :pcontrol_account_id, :psegment_id, :ppay_term_id)');
            $cmm->addParam('psupplier_id', $this->bo->supplier_id);
            $cmm->addParam('psalesman_id', $this->bo->cust_salesman_id);
            $cmm->addParam('pcontrol_account_id', $this->bo->cust_control_account_id);
            $cmm->addParam('psegment_id', $this->bo->cust_segment_id);
            $cmm->addParam('ppay_term_id', $this->bo->cust_pay_term_id);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
        }
    }

    public function resetLastUpdated($cn, $tablename, $primaryKey) {
        // Do nothing as this is only anchoring BO
    }

    public function afterCommit($generatedKeys) {
        $custinparam = array();
        $custinparam['customer_id'] = $this->bo->supplier_id;
        // Create instance of Branch BO
        $custbopath = '../core/ar/customer/Customer.xml';
        $custBo = new \app\cwf\vsla\xmlbo\XboBuilder($custbopath);
        $custboInst = $custBo->buildBO($custinparam);

        // Take json encode image of BOPropertyBag for Audit Trail
//            $boiAT = json_encode($custboInst->BOPropertyBag(), JSON_HEX_APOS);

        $custboInst->customer_id = $this->bo->supplier_id;
        $custboInst->salesman_id = $this->bo->cust_salesman_id;
        $custboInst->annex_info->Value()->has_kyc_docs = $this->bo->has_kyc_docs;
        $custboInst->annex_info->Value()->segment_id = $this->bo->cust_segment_id;
        $custboInst->annex_info->Value()->tax_info->ctin = $this->bo->annex_info->Value()->satutory_details->cst_no;
        $custboInst->annex_info->Value()->tax_info->tan = $this->bo->annex_info->Value()->satutory_details->tan;
        $custboInst->annex_info->Value()->tax_info->gstin = $this->bo->annex_info->Value()->satutory_details->gstin;        
        $custboInst->annex_info->Value()->tax_info->gst_state_id = $this->bo->annex_info->Value()->satutory_details->gst_state_id;
        $custboInst->annex_info->Value()->tax_info->gst_reg_name = $this->bo->annex_info->Value()->satutory_details->gst_reg_name;
        $custboInst->annex_info->Value()->tax_info->vtin = $this->bo->annex_info->Value()->satutory_details->vat_no;
        $custboInst->annex_info->Value()->tax_info->dup_pan = false;        
        $custboInst->annex_info->Value()->tax_info->pan = $this->bo->annex_info->Value()->satutory_details->pan;
        $custboInst->annex_info->Value()->tax_info->diff_gst_name = $this->bo->annex_info->Value()->satutory_details->diff_gst_name;
        $custboInst->annex_info->Value()->tax_info->stin = $this->bo->annex_info->Value()->satutory_details->service_tax_no;
        $custboInst->annex_info->Value()->tax_info->dup_gstin = false;
        $custboInst->annex_info->Value()->is_overridden = $this->bo->annex_info->Value()->is_overridden;

        $custBo->saveBO($custboInst, null);
    }
}

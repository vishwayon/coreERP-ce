<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ap\suppCust;

use YaLinqo\Enumerable;

/**
 * Description of suppCustEventHandler
 *
 * @author Valli
 */
class SuppCustEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {

    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);
        $this->bo->customer_id = -1;

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select customer_id From ap.supp_cust Where supplier_id=:psupplier_id');
        $cmm->addParam('psupplier_id', $this->bo->supplier_id);
        $dtsl = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dtsl->Rows()) == 1) {
            $this->bo->customer_id = $dtsl->Rows()[0]['customer_id'];
        }
    }

    public function beforeSave($cn) {
        parent::beforeSave($cn);
    }

    public function onSave($cn, $tablename) {
        // Avoid saving the base document as it is used only as an anchor
        // Base document data cannot be changed from here
        // Save the supplier customer entries made by the user

        if ($tablename == 'ap.supplier') {

            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select * from ap.sp_supp_cust_update(:psupplier_id, :pcustomer_id)');
            $cmm->addParam('psupplier_id', $this->bo->supplier_id);
            $cmm->addParam('pcustomer_id', $this->bo->customer_id);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm, $cn);
        }
    }

    public function resetLastUpdated($cn, $tablename, $primaryKey) {
        // Do nothing as this is only anchoring BO
    }

    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
    }

}

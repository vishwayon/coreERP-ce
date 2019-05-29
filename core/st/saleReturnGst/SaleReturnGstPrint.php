<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\saleReturnGst;

/**
 *
 * @author Priyanka
 */
class SaleReturnGstPrint extends \app\cwf\fwShell\base\ReportBase {

    public function onRequestReport($rptOption) {
        parent::onRequestReport($rptOption);

        $rptOption->rptParams["prpt_caption"] = '';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select (a.annex_info->>'dcn_type')::Int as dcn_type, 
                                Case (a.annex_info->>'dcn_type')::Int
                                    When 1 Then 'Rate Adjustment (Dr)' 
                                    When 2 Then 'Post Sale Discount (Cr)'
                                    Else 'Sale Return' End As dcn_type_desc 
                            From st.stock_control a
                            Where stock_id = :pstock_id;");
        $cmm->addParam('pstock_id', $rptOption->rptParams["pstock_id"]);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $rptOption->rptParams["prpt_caption"] = 'Sale Return';
        if (count($dt->Rows()) == 1) {
            switch ($dt->Rows()[0]['dcn_type']) {
                case 0:
                    $rptOption->rptParams["prpt_caption"] = 'Sale Return';
                    $rptOption->rptParams["psr_caption"] = '';
                    break;
                case 1:
                    $rptOption->rptParams["prpt_caption"] = 'Debit Note';
                    $rptOption->rptParams["psr_caption"] = 'Rate Adjustment';
                    break;
                case 2:
                    $rptOption->rptParams["prpt_caption"] = 'Credit Note';
                    $rptOption->rptParams["psr_caption"] = 'Post Sale Discount';
                    break;
            }
        }
        return $rptOption;
    }

}

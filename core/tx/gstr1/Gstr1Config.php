<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tx\gstr1;

/**
 * Description of Gstr1Config
 *
 * @author girishshenoy
 */
class Gstr1Config {
    public static function getControlTables() {
        return [
            'core' => [
                'GstInvoice' => [
                    'friendlyName' => 'GST Invoice',
                    'tableName' => 'ar.invoice_control',
                    'id' => 'invoice_id',
                    'vat_type_id' => 'vat_type_id',
                    'doc_type' => 'CIV'
                ],
                'StockGstInvoice' => [
                    'friendlyName' => 'GST Stock Invoice',
                    'tableName' => 'st.stock_control',
                    'id' => 'stock_id',
                    'vat_type_id' => 'vat_type_id',
                    'doc_type' => 'SIV'
                ],
                'GstInv' => [
                    'friendlyName' => 'POS GST Invoice',
                    'tableName' => 'pos.inv_control',
                    'id' => 'inv_id',
                    'vat_type_id' => 'vat_type_id',
                    'doc_type' => 'PIV'
                ]
            ],
            'ag' => [
                
            ],
            'crm' => [
                
            ]
        ];
    }
}

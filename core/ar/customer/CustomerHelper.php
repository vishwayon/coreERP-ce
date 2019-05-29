<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\ar\customer;

/**
 * Description of CustomerHelper
 *
 * @author priyanka
 */
class CustomerHelper {
    //put your code here
    public static function CreateCustAddrTemp($bo){        
        // Create temp teble for Customer Address
        // create temp table for customer address
        $bo->cust_address_temp = new \app\cwf\vsla\data\DataTable();
        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $scale = 0;
        $isUnique = false;
        $bo->cust_address_temp->addColumn('customer_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $bo->cust_address_temp->addColumn('address_type_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('varchar');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->cust_address_temp->addColumn('cust_address', $phpType, $default, 500, $scale, $isUnique);
        $bo->cust_address_temp->addColumn('address_type', $phpType, $default, 50, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('boolean');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $bo->cust_address_temp->addColumn('is_select', $phpType, $default, FALSE, $scale, $isUnique);
               
        foreach($bo->cust_address_temp->getColumns() as $col) {
           $cols[] = ['columnName' => $col->columnName, 'default' => $col->default ];
        }
        $bo->setTranMetaData('cust_address_temp', $cols);
    }
    
    public static function FetchCustAddr($customer_id){
        $cust_addr = '';
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select a.customer_id, a.address_id, 
                                b.address || E'\n' || b.city || case when b.pin = '' then '' else ' - ' end  
                                    || b.pin || case when b.state = '' then '' else E'\n' end  || b.state || case when b.country = '' then '' else E'\n' end || b.country as billing_address,
                                a.shipping_address_id, 
                                c.address || E'\n' || c.city || case when c.pin = '' then '' else ' - ' end  || c.pin || E'\n' || c.country ||  case when c.state = '' then '' else ', ' end  || c.state as shipping_address
                            from ar.customer a
                            Inner join sys.address b on a.address_id = b.address_id
                            left join sys.address c on a.shipping_address_id = c.address_id
                            where a.customer_id = :pcustomer_id");
        $cmm->addParam('pcustomer_id', $customer_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $cust_dt = new \app\cwf\vsla\data\DataTable();


        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $scale = 0;
        $isUnique = false;
        $cust_dt->addColumn('customer_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);
        $cust_dt->addColumn('address_type_id', \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('int8'), $default, 0, $scale, $isUnique);

        $phpType = \app\cwf\vsla\data\DataAdapter::getDBtoPHPDataType('varchar');
        $default = \app\cwf\vsla\data\DataAdapter::getPHPDataTypeDefault($phpType);
        $cust_dt->addColumn('cust_address', $phpType, $default, 500, $scale, $isUnique);
        $cust_dt->addColumn('address_type', $phpType, $default, 50, $scale, $isUnique);

        if (count($dt->Rows()) > 0) {
            $newRow = $cust_dt->NewRow();
            $newRow['customer_id'] = $customer_id;
            $newRow['cust_address'] = $dt->Rows()[0]['billing_address'];
            $newRow['address_type'] = 'Billing Address';
            $newRow['address_type_id'] = (\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id') *1000000) + 3;
            $cust_dt->AddRow($newRow);

            $newRow = $cust_dt->NewRow();
            $newRow['customer_id'] = $customer_id;
            $newRow['cust_address'] = $dt->Rows()[0]['shipping_address'];
            $newRow['address_type'] = 'Shipping Address';
            $newRow['address_type_id'] = (\app\cwf\vsla\security\SessionManager::getSessionVariable('company_id') *1000000) + 2;
            $cust_dt->AddRow($newRow);
        }        
        
        $result = array();
        $result['dt_address'] = $cust_dt;
        $result['status'] = 'ok';
        return $result;
    }
    
    public static function getCustAddr($customer_id){
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "Select a.annex_info->'tax_info'->>'gst_state_id' as gst_state_id, 
                    c.gst_state_code || ' - ' || c.state_name as gst_state,
                    a.annex_info->'tax_info'->>'gstin' as gstin, b.pin, b.city,
                                b.address || E'\n' || b.city || case when b.pin = '' then '' else ' - ' end  
                                    || b.pin || case when b.state = '' then '' else E'\n' end  || b.state || case when b.country = '' then '' else E'\n' end || b.country as addr
                From ar.customer a
                Inner Join sys.address b On a.address_id = b.address_id
                Inner Join tx.gst_state c On (a.annex_info->'tax_info'->>'gst_state_id')::BigInt = c.gst_state_id
                Where a.customer_id = :pcust_id 
                Limit 1";
        $cmm->setCommandText($sql);
        $cmm->addParam('pcust_id', $customer_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    public static function GetIncomeTypeHsnGstInfo($account_id, $doc_type='', $income_type_id=-1){
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "With row_data
                As
                (	Select b.hsn_sc_code, b.hsn_sc_type, d.*
                        From ar.income_type_tran a
                        inner join tx.hsn_sc b On a.hsn_sc_id = b.hsn_sc_id
                        Inner Join tx.hsn_sc_rate c On b.hsn_sc_id = c.hsn_sc_id
                        Inner Join tx.gst_rate d On c.gst_rate_id = d.gst_rate_id
                        inner join ar.income_type e on a.income_type_id = e.income_type_id
                        Where case when :pincome_type_id != -1 then a.income_type_id = :pincome_type_id
                                Else e.seq_type = :pdoc_type End
                                and a.account_id = :paccount_id
                 )
                 Select row_to_json(r) as gst_hsn_info
                 From row_data r;";
        $cmm->setCommandText($sql);
        $cmm->addParam('paccount_id', $account_id);
        $cmm->addParam('pdoc_type', $doc_type);
        $cmm->addParam('pincome_type_id', $income_type_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
}

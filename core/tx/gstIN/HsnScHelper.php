<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tx\gstIN;

use YaLinqo\Enumerable;

/**
 * Description of HsnScHelper
 *
 * @author priyanka
 */
class HsnScHelper {
    
    public static function GetGstHSNInfo($hsn_sc_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "With row_data
                As
                (	Select a.hsn_sc_code, a.hsn_sc_type, c.*
                        From tx.hsn_sc a
                        Inner Join tx.hsn_sc_rate b On a.hsn_sc_id = b.hsn_sc_id
                        Inner Join tx.gst_rate c On b.gst_rate_id = c.gst_rate_id
                        Where a.hsn_sc_id = :phsn_sc_id
                 )
                 Select row_to_json(r) as gst_hsn_info
                 From row_data r;";
        $cmm->setCommandText($sql);
        $cmm->addParam('phsn_sc_id', $hsn_sc_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
    
    public static function GetGstHSNList() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "Select a.hsn_sc_code, a.hsn_sc_type, a.hsn_sc_desc, c.*
                From tx.hsn_sc a
                Inner Join tx.hsn_sc_rate b On a.hsn_sc_id = b.hsn_sc_id
                Inner Join tx.gst_rate c On b.gst_rate_id = c.gst_rate_id
                Order by a.hsn_sc_code;";
        $cmm->setCommandText($sql);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt;
    }
}

<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\auditTrail;

class AuditTrailParser {

    private $vXml;
    private $fParams;
    private $result;
    private $colspan;
    private $formUrl;
    private $valTran;

    function __construct($viewX, $formParams, $formUrl) {
        $this->fParams = $formParams;
        $this->formUrl = $formUrl;
        $this->vXml = $viewX->formView;
        $this->result = null;
        $this->colspan = 1;
        $this->valTran = '';

        $this->getAuditTrailData();
    }

    public function bindAuditTrailData() {
        if ($this->result == NULL) {
            return 'Unsaved new master/document can not have audit trail.';
        }
        $data = '<style>
                    table#thelist2 {
                        font-family: arial, sans-serif;
                        border-collapse: collapse;
                        width: 100%;
                    }
                    table#thelist2 td, table#thelist2 th {
                        border: 1px solid #dddddd;
                        text-align: left;
                        padding: 8px;
                    }
                    table#thelist2 th {
                        border-bottom: 1px solid black;
                    }
                    </style>';
        $data .= '<table id="thelist2" class="row-border">';
        $data .= '<tr><th></th>';

        //create columns              
        foreach ($this->result->Rows() as $rw) {
            $data .= '<th>' . $rw['user_name'] . '</th>';
        }
        $data .= '</tr><th>Property</th>';
        foreach ($this->result->Rows() as $rw) {
            $date = $rw['last_updated'];
            $d = \app\cwf\vsla\utils\FormatHelper::FormatDateWithTimeForDisplay($date);
            $data .= '<th>' . $d . '</th>';
        }
        $data .= '</tr>';

        //Create rows with Property names
        foreach ($this->vXml->controlSection->dataBinding->children() as $name => $fld) {
            if ($name === 'field' && (string) $fld->attributes()->type != 'Hidden' && (string) $fld->attributes()->control != 'Label') {
                $data .= '<tr><td><font color="#800000">' . (string) $fld->attributes()->label . '</font></td>';

                for ($row = 0; $row < count($this->result->Rows()); $row++) {
                    $obj = $this->result->Rows()[$row]['json_log'];
                    $property = json_decode($obj, true);

                    $va = $this->recursiveFind($property, (string) $fld->attributes()->id);
                    if ($va === NULL) {
                        $data .= '<td>' . $va . '</td>';
                    } else {
                        if ((string) $fld->attributes()->control == 'SmartCombo') {
                            if ($va != -1) {
                                $lookup = \app\cwf\vsla\utils\LookupHelper::GetLookupText((string) $fld->lookup->namedLookup, (string) $fld->lookup->displayMember, (string) $fld->lookup->valueMember, $va);
                                ;
                            } else {
                                $lookup = '';
                            }
                            $data .= '<td>' . $lookup . '</td>';
                        } else if ((string) $fld->attributes()->control == 'SimpleCombo') {
                            foreach ($fld->options->option as $opt) {
                                if ((string) $opt->attributes()->value == $va) {
                                    $data .= '<td>' . (string) $opt . '</td>';
                                }
                            }
                        } else if ((string) $fld->attributes()->type == 'date') {
                            $data .= '<td>' . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($va) . '</td>';
                        } else {
                            $data .= '<td>' . $va . '</td>';
                        }
                    }
                }
                $data .= '</tr>';
            }
        }
        if ($this->vXml['type'] == 'Document') {
            $data .= '<tr><td><font color="#800000">Status</font></td>';

            for ($row = 0; $row < count($this->result->Rows()); $row++) {
                $obj = $this->result->Rows()[$row]['json_log'];
                $property = json_decode($obj, true);

                $va = $this->recursiveFind($property, 'status');
                if ($va === NULL) {
                    $data .= '<td>' . $va . '</td>';
                } else {
                    if ($va == 0) {
                        $data .= '<td>Saved/Unposted</td>';
                    } else if ($va == 5) {
                        $data .= '<td>Posted</td>';
                    }
                }
            }
            $data .= '</tr>';
        }

        $data .= $this->bindAuditTran();
        $data .= '</table>';
        return $data;
    }

    public function bindAuditTran() {
        //Create rows with Property names
        $dataTran = '';
        $count = 1;
        foreach ($this->vXml->controlSection->dataBinding->children() as $name => $fld) {
            if ($name === 'tranSection') {
                //$dataTran.='<tr><td colspan=' . $this->colspan . '><b>' . $fld->attributes()->label . '</b></td>';
                $dataTran .= '<tr><td><b>' . $fld->attributes()->label . '</b></td></tr>';

                foreach ($fld->children() as $child => $fldTran) {
                    $maxcnt = $this->FindMaxRowsInTran($fldTran->attributes()->dataProperty);
                }

                for ($r = 1; $r <= $maxcnt; $r++) {
                    //$dataTran.='<tr><td colspan=' . $this->colspan . '>Row ' . $r . '</td></tr>';
                    $dataTran .= '<tr><td>Row ' . $r . '</td></tr>';

                    foreach ($fld->children() as $child => $fldTran1) {
                        foreach ($fldTran1->children() as $nameTran => $fldTran) {
                            if ($nameTran === 'field' && (string) $fldTran->attributes()->type != 'Hidden') {
                                $dataTran .= '<tr><td style="padding: 5px 5px 5px 25px; margin:5px;"><font color="#800000">' . $fldTran->attributes()->label . '</font></td>';

                                //---------------
                                for ($row = 0; $row < count($this->result->Rows()); $row++) {
                                    $obj = $this->result->Rows()[$row]['json_log'];
                                    $property = json_decode($obj, true);

                                    $value = $this->recursiveFindForRowNo($property, (string) $fldTran->attributes()->id, $r);
                                    if ($value === NULL) {
                                        $dataTran .= '<td></td>';
                                    } else {
                                        if ((string) $fldTran->attributes()->control == 'SmartCombo') {
                                            if ($value != -1 && $value != '') {
                                                $lookup = \app\cwf\vsla\utils\LookupHelper::GetLookupText((string) $fldTran->lookup->namedLookup, (string) $fldTran->lookup->displayMember, (string) $fldTran->lookup->valueMember, $value);
                                            } else {
                                                $lookup = '';
                                            }
                                            $dataTran .= '<td>' . $lookup . '</td>';
                                        } else if ((string) $fldTran->attributes()->control == 'SimpleCombo') {
                                            foreach ($fldTran->options->option as $opt) {
                                                if ((string) $opt->attributes()->value == $value) {
                                                    $dataTran .= '<td>' . (string) $opt . '</td>';
                                                }
                                            }
                                        } else if ((string) $fldTran->attributes()->type == 'date') {
                                            $dataTran .= '<td>' . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($value) . '</td>';
                                        } else {
                                            $dataTran .= '<td>' . $value . '</td>';
                                        }
                                    }
                                }
                                $dataTran .= '</tr>';
                            }
                        }
                    }
                }
            }
        }
        return $dataTran;
    }

    private function FindMaxRowsInTran($nameTran) {
        $maxCnt = 0;
        for ($row = 0; $row < count($this->result->Rows()); $row++) {
            $obj1 = $this->result->Rows()[$row]['json_log'];
            $property1 = json_decode($obj1, true);
            foreach ($property1 as $a => $b) {
                if ($a == $nameTran) {
                    if (is_array($b)) {
                        $rowCnt = count($b);
                        if ($maxCnt < $rowCnt) {
                            $maxCnt = $rowCnt;
                        }
                    }
                }
            }
        }
        return $maxCnt;
    }

    private function recursiveFind(array $array, $id) {
        $iterator = new \RecursiveArrayIterator($array);
        $recursive = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($recursive as $key => $value) {
            if ($key === $id) {
                return $value;
            }
        }
    }

    function recursiveFindForRowNo(array $array, $id, $no) {
        $iterator = new \RecursiveArrayIterator($array);

        while ($iterator->valid()) {

            if ($iterator->hasChildren()) {
                // print all children
                if (count($iterator->getChildren()) >= $no) {
                    if (array_key_exists($no - 1, $iterator->current())) {
                        $var = $iterator->getChildren()[$no - 1];
                        if (is_array($var)) {
                            foreach ($var as $key => $value) {
                                if ($key === $id) {
                                    return $value;
                                }
                            }
                        }
                    }
                }
            }
            $iterator->next();
        }
    }

    public function getAuditTrailData() {

        // Get connection type for master or document from BO xml
        $cnType = \app\cwf\vsla\data\DataConnect::COMPANY_DB;
        $BOX = simplexml_load_file('../' . $this->formUrl . '/' . $this->vXml['bindingBO'] . '.xml');

        if ($BOX->businessObject->connectionType) {
            if ($BOX->businessObject->connectionType->mainDB) {
                $cnType = \app\cwf\vsla\data\DataConnect::MAIN_DB;
            }
        }

        $param = json_decode($this->fParams, true);
        foreach ($param as $key => $value) {
            $master_id = $value;
        }

        if ($master_id == -1 || $master_id == '') {
            $this->result = NULL;
        } else {
            $table = $this->vXml['id'];
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            if ($this->vXml['type'] == 'Master') {
                $cmm->setCommandText('Select last_updated, user_name, json_log from aud.' . $table . ' where master_id = :pmaster_id order by last_updated desc');
                $cmm->addParam('pmaster_id', $master_id);
            } else if ($this->vXml['type'] == 'Document') {
                $cmm->setCommandText('Select last_updated, user_name, json_log from aud.' . $table . ' where voucher_id = :pvoucher_id order by last_updated desc');
                $cmm->addParam('pvoucher_id', $master_id);
            }
            $result = \app\cwf\vsla\data\DataConnect::getAuditData($cmm, $cnType);
            $this->result = $result;
        }
    }

    public function setHeader() {
        $header = '<div id="collheader" class="row cformheader">
            <h3 class="col-md-5">' . $this->vXml->header . ' Audit Trail</h3>
             <div class="col-md-7" style="padding-right:10px;">
                  <button id="cmdclose" class="btn btn-info formoptions" name="close-button" 
                            onclick="$(\'#detailsat\').hide();$(\'#details\').show();" 
                            style="background-color:lightgrey;border-color:lightgrey;color:black;margin-top:4px;">
                    <span class="glyphicon glyphicon-remove" aria-hidden="true"></span>
                         Close
                  </button>
             </div>
        </div>';
        echo $header;
    }

}

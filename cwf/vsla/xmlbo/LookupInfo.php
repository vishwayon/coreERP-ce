<?php

namespace app\cwf\vsla\xmlbo {

    use app\cwf\vsla\data\SqlCommand;
    use app\cwf\vsla\data\DataConnect;

    class LookupInfo {

        public $namedLookup;
        public $displayMember;
        public $valueMember;
        public $ifilter = '';
        public $filter = '';
        public $Items = Array();
        public $Results = Array();
        public $id = NULL;
        public $term = '';
        public $aliasField = '';
        public $noDefault = FALSE;

        public function __construct($namedlookup, $displaymember, $valuemember, $filter = '', $id = NULL, $term = '', $nodefault = FALSE) {
            $this->namedLookup = \Yii::getAlias($namedlookup);
            $this->displayMember = $displaymember;
            $this->valueMember = $valuemember;
            $this->ifilter = $filter;
            $this->term = str_ireplace("'", "''", $term);
            if (isset($id)) {
                $this->id = $id;
            }
            $this->noDefault = $nodefault;
            $this->getData();
        }

        private function setFilter() {
            if (strlen($this->term) > 0) {
                $this->filter = " (replace(" . $this->displayMember . ",' ','') ilike '%" . $this->term . "%'";
                $this->filter .= " Or " . $this->displayMember . " ilike '%" . $this->term . "%')";
            }
            if ($this->aliasField != '') {
                if (trim($this->ifilter) !== '') {
                    if ($this->filter !== '') {
                        $this->filter = $this->ifilter . ' And (' . $this->filter . ' Or ';
                    } else {
                        $this->filter = $this->ifilter . ' And (';
                    }
                    $this->filter .= $this->aliasField . ' ilike \'%' . $this->term . '%\')';
                } else {
                    if ($this->filter !== '') {
                        $this->filter .= ' Or ' . $this->aliasField . ' ilike \'%' . $this->term . '%\'';
                    } else {
                        $this->filter = $this->aliasField . ' ilike \'%' . $this->term . '%\'';
                    }
                }
            } else {
                if (trim($this->ifilter) !== '') {
                    if ($this->filter !== '') {
                        $this->filter = $this->ifilter . ' And ' . $this->filter;
                    } else {
                        $this->filter = $this->ifilter;
                    }
                } else {
                    //$this->filter = $this->ifilter;
                }
            }
            if (isset($this->id) && is_numeric($this->id) && $this->id != -1) {
                $this->filter = $this->valueMember . ' = ' . $this->id;
            } else if (isset($this->id) && $this->id != '') {
                $this->filter = $this->valueMember . ' = \'' . $this->id . '\'';
            }
        }

        private function getData() {
            $cwFramework = simplexml_load_file($this->namedLookup);
            $sxeSO = $cwFramework->lookup;
            if ($sxeSO->connectionType->mainDB) {
                $cntype = DataConnect::MAIN_DB;
            } else {
                $cntype = DataConnect::COMPANY_DB;
            }

            if (isset($sxeSO->aliasField) && strlen((string) $sxeSO->aliasField->attributes()['id']) > 0) {
                $this->aliasField = (string) $sxeSO->aliasField->attributes()['id'];
            }
            $this->setFilter();

            $cmmSO = \app\cwf\vsla\data\SqlParser::getSql($sxeSO->sql);
            $cmmtext = $cmmSO->getCommandText();
            if ($this->filter != '') {
                $cmmtext = 'select * from(' . $cmmSO->getCommandText() . ')temp where ' . $this->filter;
            }
            $cmmSO->setCommandText($cmmtext);
            $dtSO = DataConnect::getData($cmmSO, $cntype);

            $padField = '';
            if (isset($sxeSO->padField)) {
                $padField = (string) $sxeSO->padField["id"];
            }
            if ($this->valueMember != NULL && $this->noDefault == FALSE) {
                if ($padField == '') {
                    $itm = new LookupItem();
                    $itm->id = -1;
                    $itm->text = 'Select an option';
                    array_push($this->Results, $itm);
                }
            }

            foreach ($dtSO->Rows() as $rw) {
                if ($this->valueMember != NULL) {
                    if ($padField != '') {
                        $this->Items[$rw[$this->valueMember]] = '<span style="padding-left: ' . $rw[$padField] . 'px">' . $rw[$this->displayMember] . '</span>';
                    } else {
                        $this->Items[$rw[$this->valueMember]] = $rw[$this->displayMember];
                    }
                    $itm = new LookupItem();
                    $itm->id = $rw[$this->valueMember];
                    if ($padField != '') {
                        $itm->text = '<span style="padding-left: ' . $rw[$padField] . 'px">' . $rw[$this->displayMember] . '</span>';
                    } else {
                        $itm->text = $rw[$this->displayMember];
                    }
                    array_push($this->Results, $itm);
                } else {
                    array_push($this->Items, $rw[$this->displayMember]);
                    array_push($this->Results, $rw[$this->displayMember]);
                }
            }
        }

        public function initData($id) {
            if (array_key_exists($id, $this->Items)) {
                $res = $this->Items[$id];
                return $res;
            }
            return '';
        }

    }

    class LookupItem {

        public $id;
        public $text;

    }

    class PagedLookup {

        public $Total;
        public $Results = Array();

    }

}

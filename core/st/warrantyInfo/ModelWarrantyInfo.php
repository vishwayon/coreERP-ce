<?php

namespace app\core\st\warrantyInfo;

use app\cwf\vsla\data\DataConnect;
use app\cwf\vsla\security\SessionManager;

class ModelWarrantyInfo {

    public $material_id;
    public $material_type_id;
    public $wrdata;
    public $brokenrules = array();

    public function __construct() {
        $this->material_id = -1;
        $this->material_type_id = -1;
        $this->wrdata = array();
    }

    public function setFilters($filter) {
        if (!is_numeric($filter['material_id']) || $filter['material_id'] == -1) {
            $this->material_id = 0;
        } else {
            $this->material_id = $filter['material_id'];
        }
        if (!is_numeric($filter['material_type_id']) || $filter['material_type_id'] == -1) {
            $this->material_type_id = 0;
        } else {
            $this->material_type_id = $filter['material_type_id'];
        }
        $this->getData();
    }

    public function getData() {

        $cmmtext = "select * from (
                        select 
                        a.stock_id, b.doc_date,
                        a.material_id, c.material_name,
                        c.material_type_id, d.material_type,
                        a.mfg_date, a.mfg_serial,
                        (current_date - b.doc_date) as agedays,
                        age(current_date, b.doc_date) as age
                        from st.stock_tran_war a
                        left join st.stock_control b on a.stock_id = b.stock_id
                        left join st.material c on a.material_id = c.material_id
                        left join st.material_type d on c.material_type_id = d.material_type_id
                        where a.stock_id like 'SI%' 
                                and (a.material_id = :pmaterial_id or :pmaterial_id = 0)
                                and (c.material_type_id = :pmaterial_type_id or :pmaterial_type_id = 0)

                        union all

                        select 
                        a.stock_id, b.doc_date,
                        a.material_id, c.material_name,
                        c.material_type_id, d.material_type,
                        a.mfg_date, a.mfg_serial,
                        (current_date - b.doc_date) as agedays,
                        age(current_date, b.doc_date) as age
                        from st.stock_tran_war a
                        left join pos.inv_control b on a.stock_id = b.inv_id
                        left join st.material c on a.material_id = c.material_id
                        left join st.material_type d on c.material_type_id = d.material_type_id
                        where a.stock_id like 'PI%'
                                and (a.material_id = :pmaterial_id or :pmaterial_id = 0)
                                and (c.material_type_id = :pmaterial_type_id or :pmaterial_type_id = 0)
                        ) wr order by doc_date, stock_id;";

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('pmaterial_id', $this->material_id);
        $cmm->addParam('pmaterial_type_id', $this->material_type_id);
        $wrdt = DataConnect::getData($cmm);
        $wrdt->addColumn('doc_date_sort', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        foreach ($wrdt->Rows() as &$dr) {
            $dr['doc_date_sort'] = strtotime($dr['doc_date']);
        }
        $this->wrdata = $this->structData($wrdt);
    }

    function structData(\app\cwf\vsla\data\DataTable $dtCollection) {
        foreach ($dtCollection->getColumns() as $column) {
            $col = [];
            $d = [];
            if ($column->columnName == 'doc_date') {
                $d['_'] = $column->columnName;
                $d['display'] = $column->columnName . '.display';
                $d['sort'] = $column->columnName . '.sort';
                $d['filter'] = $column->columnName . '.display';
                $col['data'] = $d;
                $col['type'] = 'num';
                $col['title'] = 'Date';
                $rawdata['columns'][] = $col;
            } else if ($column->columnName == 'stock_id') {
                $d['_'] = $column->columnName;
                $d['display'] = $column->columnName . '.display';
                $d['sort'] = $column->columnName . '.sort';
                $d['filter'] = $column->columnName . '.display';
                $col['data'] = $d;
                $col['type'] = 'num';
                $col['title'] = 'Doc ID';
                $rawdata['columns'][] = $col;
            } else if ($column->columnName == 'material_name' || $column->columnName == 'material_type' ||
                    $column->columnName == 'mfg_serial' || $column->columnName == 'age') {
                $d['_'] = $column->columnName;
                $d['display'] = $column->columnName . '.display';
                $d['sort'] = $column->columnName . '.sort';
                $d['filter'] = $column->columnName . '.sort';
                $col['data'] = $d;
                if ($column->columnName == 'material_name') {
                    $col['title'] = 'Material';
                } else if ($column->columnName == 'material_type') {
                    $col['title'] = 'Type';
                } else if ($column->columnName == 'mfg_serial') {
                    $col['title'] = 'Serial#';
                } else if ($column->columnName == 'age') {
                    $col['title'] = 'Age';
                    $col['type'] = 'num';
                }
                $col['className'] = "datatable-col-left";
                $rawdata['columns'][] = $col;
            }
        }

        $rawdata['data'] = [];
        foreach ($dtCollection->Rows() as $datarow) {
            $datarow;
            foreach ($datarow as $field => $value) {
                $val = [];
                if ($field == 'doc_date') {
                    $val['display'] = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($value);
                    $val['sort'] = strtotime($value);
                    $datarow[$field] = $val;
                } else if ($field == 'stock_id') {
                    $val['display'] = $value;
                    if (strrpos($value, '/') > 0) {
                        $val['sort'] = intval(substr($value, strrpos($value, '/') + 1));
                    } else {
                        $vchno = intval(substr($value, -5));
                        if ($vchno > 0) {
                            $val['sort'] = $vchno;
                        } else {
                            $val['sort'] = $value;
                        }
                    }
                    $val['filter'] = $value;
                    $datarow[$field] = $val;
                } else if ($field == 'material_name' || $field == 'material_type' ||
                        $field == 'mfg_serial') {
                    $val['display'] = $value;
                    $val['sort'] = $value;
                    $datarow[$field] = $val;
                } else if ($field == 'age') {
                    $val['display'] = $value;
                    $val['sort'] = $datarow['agedays'];
                    $datarow[$field] = $val;
                }
            }
            $rawdata['data'][] = $datarow;
        }
        return $rawdata;
    }

}

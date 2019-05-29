<?php

namespace app\core\st\lotAlloc;
use YaLinqo\Enumerable;

/**
 * LotAllocHelper can be used to prefill or display 
 * lot balances to the user
 * @author girishshenoy
 */
class LotAllocHelper {
    
    const ANY = 0;
    const FOR_STOCK_TRANSF = 1;
    const FOR_INV = 2;

    private static function setDefault(LotHelperParam $param) {
        $param->branch_id == -1 ? $param->branch_id = \app\cwf\vsla\security\SessionManager::getSessionVariable("branch_id") : '';
        if (!isset($param->to_date) || $param->to_date = '') {
            $param->to_date = date('Y-m-d');
        }
    }

    public static function getLotBal(LotHelperParam $param) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select sl_lot_id, test_insp_id, test_insp_date, lot_no,
                    bal_qty, mfg_date, exp_date
                From st.fn_sl_lot_bal(:pbranch_id, :pmat_id, :psloc_id, :pto_date, :pvch_id)
                where (lot_state_id = :plot_state_id or :plot_state_id = 0)
                Order By mfg_date");
        $cmm->addParam("pbranch_id", $param->branch_id);
        $cmm->addParam("pmat_id", $param->mat_id);
        $cmm->addParam("psloc_id", $param->sloc_id);
        $cmm->addParam("pto_date", $param->to_date);
        $cmm->addParam("pvch_id", $param->vch_id);
        $cmm->addParam("plot_state_id", $param->lot_state_id);
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }

    public static function getLotBalMany(LotHelperParam $param) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select sl_lot_id, test_insp_id, test_insp_date, lot_no,
                    bal_qty, mfg_date, exp_date, material_id, stock_location_id
                From st.fn_sl_lot_bal_many(:pbranch_id, :pmat_tran, :pto_date, :pvch_id)
                where (lot_state_id = :plot_state_id or :plot_state_id = 0)
                Order By mfg_date");
        $cmm->addParam("pbranch_id", $param->branch_id);
        $cmm->addParam("pmat_tran", json_encode($param->mat_tran));
        $cmm->addParam("pto_date", $param->to_date);
        $cmm->addParam("pvch_id", $param->vch_id);
        $cmm->addParam("plot_state_id", $param->lot_state_id);
        return \app\cwf\vsla\data\DataConnect::getData($cmm);
    }

    /**
     * Source must contain the following columns
     * [material_id, stock_location_id, uom_id, issued_qty]
     * It should also contain child table sl_lot_alloc (target to be populated)
     */
    public static function doLotAlloc($branch_id, $sloc_id, $as_on, $vch_id, \app\cwf\vsla\data\DataTable $source) {
        if (!self::validSource($source)) {
            return;
        }
        $matids = $source->asArray("material_id", 'material_id');
        $param = new LotHelperParam();
        $param->branch_id = $branch_id;
        $param->to_date = $as_on;
        $param->vch_id = $vch_id;
        $param->sloc_id = $sloc_id;
        foreach ($matids as $matid) {
            $param->mat_id = $matid;
            $dt_alloc = self::getLotBal($param);
            $dt_alloc->addColumn("alloc_qty", \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
            foreach ($source->Rows() as &$sdr) {
                if ($sdr['material_id'] == $matid) {
                    $issued_qty = $sdr['issued_qty'];
                    // First do allocations
                    foreach ($dt_alloc->Rows() as &$dal) {
                        if ($issued_qty > 0) {
                            $alloc_qty = 0;
                            if ($dal['bal_qty'] < $issued_qty) {
                                $alloc_qty = $dal['bal_qty'];
                            } else {
                                $alloc_qty = $issued_qty;
                            }
                            $dal['alloc_qty'] = $alloc_qty;
                            $issued_qty -= $alloc_qty;
                        }
                    }
                    // Transfer allocations to source row
                    foreach ($dt_alloc->Rows() as &$allocated) {
                        if ($allocated['alloc_qty'] > 0) {
                            $nr = $sdr['sl_lot_alloc']->newRow();
                            $nr['sl_lot_id'] = $allocated['sl_lot_id'];
                            $nr['material_id'] = $matid;
                            $nr['lot_issue_qty'] = $allocated['alloc_qty'];
                            $nr['vch_date'] = $as_on;
                            $sdr['sl_lot_alloc']->addRow($nr);
                            // reduce balance in allocated
                            $allocated['bal_qty'] -= $allocated['alloc_qty'];
                            $allocated['alloc_qty'] = 0;
                        }
                    }
                }
            }
        }
    }

    /**
     * Source must contain the following columns
     * [material_id, stock_location_id, uom_id, issued_qty]
     * It should also contain child table sl_lot_alloc (target to be populated)
     */
    public static function doLotAllocMany($branch_id, $as_on, $vch_id, \app\cwf\vsla\data\DataTable $source) {
        if (!self::validSource($source)) {
            return;
        }
        $mat_tran = $source->select(['material_id', 'stock_location_id']);
        $param = new LotHelperParam();
        $param->branch_id = $branch_id;
        $param->to_date = $as_on;
        $param->vch_id = $vch_id;
        $param->mat_tran = $mat_tran;
        $dt_alloc = self::getLotBalMany($param);
        $dt_alloc->addColumn("alloc_qty", \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $matids = $source->asArray("material_id", 'material_id');
        foreach ($matids as $matid) {
            foreach ($source->Rows() as &$sdr) {
                if ($sdr['material_id'] == $matid) {
                    $issued_qty = $sdr['issued_qty'];
                    // First do allocations
                    foreach ($dt_alloc->Rows() as &$dal) {
                        if ($issued_qty > 0) {
                            if ($sdr['material_id'] == $dal['material_id'] && $sdr['stock_location_id'] == $dal['stock_location_id']) {
                                $alloc_qty = 0;
                                if ($dal['bal_qty'] < $issued_qty) {
                                    $alloc_qty = $dal['bal_qty'];
                                } else {
                                    $alloc_qty = $issued_qty;
                                }
                                $dal['alloc_qty'] = $alloc_qty;
                                $issued_qty -= $alloc_qty;
                            }
                        }
                    }
                    // Transfer allocations to source row
                    foreach ($dt_alloc->Rows() as &$allocated) {
                        if ($allocated['alloc_qty'] > 0) {
                            $nr = $sdr['sl_lot_alloc']->newRow();
                            $nr['sl_lot_id'] = $allocated['sl_lot_id'];
                            $nr['material_id'] = $matid;
                            $nr['lot_issue_qty'] = $allocated['alloc_qty'];
                            $nr['vch_date'] = $as_on;
                            $sdr['sl_lot_alloc']->addRow($nr);
                            // reduce balance in allocated
                            $allocated['bal_qty'] -= $allocated['alloc_qty'];
                            $allocated['alloc_qty'] = 0;
                        }
                    }
                }
            }
        }
    }

    private static function validSource(\app\cwf\vsla\data\DataTable $source) {
        return true;
    }

    private static $require_branch_lot_alloc;

    private static function requireLotAlloc() {
        if (!isset(self::$require_branch_lot_alloc)) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("Select (annex_info->>'has_lot_alloc')::Boolean has_lot_alloc
                From sys.branch
                Where branch_id = {branch_id}");
            $dt_lot_alloc = \app\cwf\vsla\data\DataConnect::getData($cmm);

            if (count($dt_lot_alloc->Rows()) == 1) {
                self::$require_branch_lot_alloc = $dt_lot_alloc->Rows()[0]['has_lot_alloc'];
            }
        }
        return self::$require_branch_lot_alloc;
    }

    /**
     * Validates the issued_qty Vs. Alloc_qty where qc is true
     * @param \app\cwf\vsla\xmlbo\BoBase $bo
     * @param \app\cwf\vsla\data\DataTable $source
     */
    public static function validateQcMatAlloc(\app\cwf\vsla\xmlbo\BoBase $bo, \app\cwf\vsla\data\DataTable $source, $for = self::ANY) {
        if (!self::requireLotAlloc()) {
            return;
        }
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "Select a.material_id, (a.annex_info->'qc_info'->>'has_qc')::Boolean has_qc
                From st.material a
                Where a.material_id = Any(:pmat_ids::BigInt[])
                    And (a.annex_info->'qc_info'->>'has_qc')::Boolean\n";
        if($for == self::FOR_STOCK_TRANSF) {
            $sql .= "And a.material_id Not In (Select x.material_id From st.allow_neg x Where x.branch_id = :pbranch_id Where x.in_st)";
            $cmm->addParam('pbranch_id', $bo->branch_id);
        } elseif ($for == self::FOR_INV) {
            $sql .= "And a.material_id Not In (Select x.material_id From st.allow_neg x Where x.branch_id = :pbranch_id Where x.in_inv)";
            $cmm->addParam('pbranch_id', $bo->branch_id);
        }
        
        $cmm->setCommandText($sql);
        $mat_ids = $source->select("material_id");
        $cmm->addParam('pmat_ids', "{" . implode(",", $mat_ids) . "}");
        $dt_qc = \app\cwf\vsla\data\DataConnect::getData($cmm);
        foreach ($source->Rows() as $drs) {
            foreach ($dt_qc->Rows() as $drqc) {
                if ($drs['material_id'] == $drqc['material_id']) {
                    $alloc_qty = "0";
                    foreach ($drs['sl_lot_alloc']->Rows() as $dralloc) {
                        $alloc_qty = bcadd($alloc_qty, $dralloc['lot_issue_qty'], 3);
                    }
                    if (bccomp($alloc_qty, $drs['issued_qty'], 3) != 0) {
                        $bo->addBRule("Sl# " . $drs['sl_no'] . ": Stock Lot Allocations do not match issued qty");
                    }
                }
            }
        }
    }
    /**
     * Validates the issued_qty Vs. Alloc_qty where qc is true
     * @param \app\cwf\vsla\xmlbo\BoBase $bo
     * @param \app\cwf\vsla\data\DataTable $source
     */
    public static function validateQcMatAllocV2(\app\cwf\vsla\xmlbo\BoBase $bo, $source) {
        if (!self::requireLotAlloc()) {
            return;
        }
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("With sl_alloc
                As
                (	Select x.material_id, x.issued_qty as alloc_qty
                        From jsonb_to_recordset(:pcurrent_alloc::JsonB) as x(sl_lot_id uuid, material_id BigInt, issued_qty Numeric(18,3))
                )
                Select a.material_id, (a.annex_info->'qc_info'->>'has_qc')::Boolean has_qc
                From st.material a
                inner join sl_alloc b on a.material_id = b.material_id
                Where (a.annex_info->'qc_info'->>'has_qc')::Boolean
                ");
        $cmm->addParam('pcurrent_alloc', json_encode($source));
        $dt_qc = \app\cwf\vsla\data\DataConnect::getData($cmm);
        foreach ($source as $drs) {
            foreach ($dt_qc->Rows() as $drqc) {
                if ($drs['material_id'] == $drqc['material_id']) {
                    $alloc_qty = "0";
                    foreach ($drs['sl_lot_alloc']->Rows() as $dralloc) {
                        $alloc_qty = bcadd($alloc_qty, $dralloc['lot_issue_qty'], 3);
                    }
                    if (bccomp($alloc_qty, $drs['issued_qty'], 3) != 0) {
                        $bo->addBRule("Sl# " . $drs['sl_no'] . ": Stock Lot Allocations do not match issued qty");
                    }
                }
            }
        }
    }

    /**
     * Validates for Excess Lot Issues and doc_date Vs. sl_lot date
     * @param \app\cwf\vsla\xmlbo\BoBase $bo
     * @param \app\cwf\vsla\data\DataTable $source
     */
    public static function validateSlLotAlloc(\app\cwf\vsla\xmlbo\BoBase $bo, \app\cwf\vsla\data\DataTable $source) {
        // return; // temp code to allow back dated entries
        // Validate Lot Alloc dates and balance
        // Set doc date in Lot alloc        
        foreach ($source->Rows() as &$refmat_tran) {
            foreach ($refmat_tran['sl_lot_alloc']->Rows() as &$ref_alloc_tran) {
                $ref_alloc_tran['vch_date'] = $bo->doc_date;
                if ($ref_alloc_tran['sl_id'] == '') {
                    // Ensure that blanks are converted to null for uuid
                    // This field will be updated by trigger on posting
//                    $ref_alloc_tran['sl_id'] = null;
                }
            }
        }
        
        if (!self::requireLotAlloc()) {
            return;
        }
        $sl_lot_ids = [];
        foreach ($source->Rows() as $mat_tran) {
            $sl_lot_ids = array_merge($sl_lot_ids, $mat_tran['sl_lot_alloc']->select(['sl_lot_id', 'material_id', 'lot_issue_qty']));
        }
        if (count($sl_lot_ids) > 0) {
            $cmmsl = new \app\cwf\vsla\data\SqlCommand();
            $cmmsl->setCommandText("With sl_alloc
                As
                (	Select x.sl_lot_id, x.material_id, -x.lot_issue_qty as alloc_qty
                        From jsonb_to_recordset(:pcurrent_alloc::JsonB) as x(sl_lot_id uuid, material_id BigInt, lot_issue_qty Numeric(18,3))
                ),
                lot_settle
                As
                (	-- All origins
                    Select a.sl_lot_id, a.lot_qty as bal_qty
                    From st.sl_lot a
                    Inner Join st.stock_ledger b On a.sl_id = b.stock_ledger_id
                    Inner Join sl_alloc c On a.sl_lot_id = c.sl_lot_id
                    Where b.doc_date <= :pdoc_date
                    Group By a.sl_lot_id, a.lot_qty
                    Union All -- All allocs without the current voucher
                    Select a.sl_lot_id, -a.lot_issue_qty
                    From st.sl_lot_alloc a
                    Inner Join sl_alloc c On a.sl_lot_id = c.sl_lot_id
                    Where a.voucher_id != :pvch_id
                    Group By a.sl_lot_id, a.lot_issue_qty
                    Union All -- allocations in current voucher
                    Select sl_lot_id, alloc_qty
                    From sl_alloc
                )
                Select a.sl_lot_id, b.test_insp_id, b.lot_no, Sum(a.bal_qty) as bal_qty
                From lot_settle a
                Inner Join st.sl_lot b On a.sl_lot_id = b.sl_lot_id
                Group by a.sl_lot_id, b.test_insp_id, b.lot_no
                Having Sum(a.bal_qty) < 0;");
            $cmmsl->addParam("pdoc_date", $bo->doc_date);
            $cmmsl->addParam("pvch_id", $bo['__doc_id']);
            $cmmsl->addParam('pcurrent_alloc', json_encode($sl_lot_ids));
            $dtslExcs = \app\cwf\vsla\data\DataConnect::getData($cmmsl);
            if (count($dtslExcs->Rows()) > 0) {
//                $bo->addBRule('Excess Lot Allocations for ['. implode(',', $dtslExcs->select('test_insp_id')) .']');
                
                foreach ($source->Rows() as $mat_tran) {
                    foreach ($dtslExcs->Rows() as $drex) {
                        $dr = Enumerable::from($mat_tran['sl_lot_alloc']->Rows())->where('$a==>$a["sl_lot_id"]=="' . $drex['sl_lot_id'] . '" ')->toList();
                        if (count($dr) !== 0) {
                            $bo->addBRule('Excess lot allocations for Sl# ' . $mat_tran['sl_no'] . ' [Insp# ' . $drex['test_insp_id'] . ' lot# ' . $drex['lot_no'] . '].');
                        }
                    }
                }
            }
        }
    }

    public static function getQcMat($mat_array) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select material_id, (annex_info->'qc_info'->>'has_qc')::Boolean as has_qc
                              From st.material 
                              Where material_id = Any(:pmat_ids::BigInt[])
                              And (annex_info->'qc_info'->>'has_qc')::Boolean");
        $mat_ids = implode(",", $mat_array);
        $cmm->addParam("pmat_ids", "{" . $mat_ids . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt->asArray("material_id", "has_qc");
    }

    public static function getTsMat($mat_array) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select material_id, (annex_info->'qc_info'->>'has_ts')::Boolean as has_ts
                              From st.material 
                              Where material_id = Any(:pmat_ids::BigInt[])
                              And (annex_info->'qc_info'->>'has_ts')::Boolean");
        $mat_ids = implode(",", $mat_array);
        $cmm->addParam("pmat_ids", "{" . $mat_ids . "}");
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt->asArray("material_id", "has_ts");
    }

    public static function validateOnUnpost(\app\cwf\vsla\xmlbo\BoBase $bo, $vch_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select a.voucher_id, a.vch_date 
                From st.sl_lot_alloc a
                Inner Join st.sl_lot b On a.sl_lot_id = b.sl_lot_id
                Inner Join st.stock_ledger c On b.sl_id = c.stock_ledger_id
                Where c.voucher_id = :pvch_id");
        $cmm->addParam("pvch_id", $vch_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $msg = '';
            $i = 0;
            foreach ($dt->Rows() as $dr) {
                $i++;
                if (strlen($msg) > 0) {
                    $msg .= ",";
                }
                $msg .= " ref: " . $dr['voucher_id'] . " dt: " . \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($dr['vch_date']);

                if ($i > 5) {
                    break;
                }
            }
            $bo->addBRule("Unpost not allowed. Document referenced in " . $msg);
        }
    }

    /**
     * 
     * @param \app\cwf\vsla\data\DataTable $sl_lot_alloc
     */
    public static function getTsLot($sl_lot_alloc) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("With sl_lot_alloc
                                As
                                (	Select x.sl_lot_id, x.material_id, x.lot_issue_qty
                                        From jsonb_to_recordset(:psl_lot_alloc::JsonB) as x(sl_lot_id uuid, material_id bigint, lot_issue_qty Numeric(18,4), batch_bal_qty Numeric(18,4))
                                ),
                                sl_lot
                                As
                                (
                                    select b.ref_info, a.sl_lot_id, a.material_id, a.lot_issue_qty
                                    from sl_lot_alloc a
                                    inner join st.sl_lot b on a.sl_lot_id = b.sl_lot_id
                                )
                                Select a.sl_lot_id, a.material_id, sum(a.fat) fat, sum(a.snf) snf, a.lot_issue_qty, 
                                    COALESCE((b.annex_info->'prod_info'->>'sg_clr_mod')::numeric, 0) sg_clr_mod, c.in_kg, c.in_ltr
                                From 
                                    (select case when COALESCE((supp->>'test_insp_attr_id')::numeric, 0) = 101 then (supp->>'result')::numeric Else 0 End snf,
                                        case when COALESCE((supp->>'test_insp_attr_id')::numeric, 0) = 102 then (supp->>'result')::numeric Else 0 End fat, b.ref_info, *
                                    from sl_lot b, jsonb_array_elements(b.ref_info->'data') supp
                                ) a
                                Inner join st.material b on a.material_id = b.material_id
                                Inner join st.uom c on a.material_id = c.material_id And c.uom_type_id = 101
                                Group By a.sl_lot_id, a.material_id, a.lot_issue_qty, COALESCE((b.annex_info->'prod_info'->>'sg_clr_mod')::numeric, 0), c.in_kg, c.in_ltr");

        $cmm->addParam('psl_lot_alloc', json_encode($sl_lot_alloc));
        $dtSlLot = \app\cwf\vsla\data\DataConnect::getData($cmm);


        $dtSlLot->addColumn('clr', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $dtSlLot->addColumn('lot_issue_qty_kg', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $dtSlLot->addColumn('kg_fat', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);
        $dtSlLot->addColumn('kg_snf', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_DECIMAL, 0);

        foreach ($dtSlLot->Rows() as &$ref_dr) {
//            $ref_dr['clr'] = (4 * $ref_dr['snf']) - $ref_dr['fat'] - $ref_dr['sg_clr_mod'];  
//            $ref_dr['lot_issue_qty_kg'] = $ref_dr['lot_issue_qty'] * (1 + $ref_dr['clr'] / 1000);
            $ref_dr['lot_issue_qty_kg'] = $ref_dr['lot_issue_qty'] * $ref_dr['in_kg'];
            $ref_dr['kg_fat'] = $ref_dr['fat'] * $ref_dr['lot_issue_qty_kg'] / 100;
            $ref_dr['kg_snf'] = $ref_dr['snf'] * $ref_dr['lot_issue_qty_kg'] / 100;
        }
        return $dtSlLot;
    }

}

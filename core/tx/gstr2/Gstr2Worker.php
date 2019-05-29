<?php

namespace app\core\tx\gstr2;

/**
 * Gstr2Worker
 * @author girishshenoy
 */
class Gstr2Worker {

    public static function getNextPeriod($gst_state_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select max(ret_period_to) as ret_period_to
                From tx.gst_ret 
                Where gst_ret_type_id = 102
                    And gst_state_id = :pgst_state_id");
        $cmm->addParam('pgst_state_id', $gst_state_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $last_period = '2017-06-30';
        if (count($dt->Rows()) == 1) {
            if (isset($dt->Rows()[0]['ret_period_to'])) {
                $last_period = $dt->Rows()[0]['ret_period_to'];
            }
        }
        $next_period_from = date_add(new \DateTime($last_period), new \DateInterval('P1D'));
        $result = [];
        $result['ret_period'] = $next_period_from->format('mY');
        $result['ret_period_from'] = $next_period_from->format('Y-m-d');
        $result['ret_period_to'] = date_sub(date_add($next_period_from, new \DateInterval('P1M')), new \DateInterval('P1D'))->format('Y-m-d');
        return $result;
    }

    public static function getPendingDocData($dataParams) {
        $provider = new \app\core\providers\Gstr2Provider();

        // for ag module
        if (file_exists(\yii::getAlias('@app/ag/pub/providers/Gstr1Provider.php')) && array_key_exists('ag', \yii::$app->modules)) {
            $agProvider = new \app\ag\pub\providers\Gstr2Provider();
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'abp_control'");
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dt->Rows()) > 0) {
                $provider->docList = array_merge($provider->docList, $agProvider->docList);
            }
        }

        $option = new Gstr2ProviderOption();
        $option->gst_state_id = $dataParams->gst_state_id;
        $option->ret_period_from = $dataParams->ret_period_from;
        $option->ret_period_to = $dataParams->ret_period_to;
        return $provider->preProcessPendingDocs($option);
    }

    public static function getSummaryData($dataParams) {


        $provider = new \app\core\providers\Gstr2Provider();
        // for ag module
        if (file_exists(\yii::getAlias('@app/ag/pub/providers/Gstr2Provider.php'))) {
            $agProvider = new \app\ag\pub\providers\Gstr2Provider();
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'abp_control'");
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dt->Rows()) > 0) {
                $provider->docList = array_merge($provider->docList, $agProvider->docList);
            }
        }

        $option = new Gstr2ProviderOption();
        $option->gst_state_id = $dataParams->gst_state_id;
        $option->ret_period_from = $dataParams->ret_period_from;
        $option->ret_period_to = $dataParams->ret_period_to;
        $result['return_period'] = $dataParams->ret_period_to;
        $result['gstin'] = \app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gstin'];
        $result['company_name'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_name');
        $result['b2b'] = $provider->getB2B_summary($option);
        $result['b2c_93_rs'] = $provider->getB2C93_summary($option, 'rs');
        $result['b2c_93'] = $provider->getB2C93_summary($option, '');
        $result['b2c_94'] = $provider->getB2C94_summary($option);
        $result['imp_ovs'] = $provider->getIMP_ovs_summary($option);
        $result['imp_sez'] = $provider->getIMP_sez_summary($option);
        $result['cdn'] = $provider->getCDNR_summary($option);
        $result['nil_rc94'] = $provider->getB2C94_exmpt_summary($option);
        $result['nil'] = $provider->getCP_NIL_EXEMP_summary($option);
        $result['txi'] = []; // $provider->getAT_summary($option);
        $result['txpd'] = []; //$provider->getATADJ_summary($option);
        $result['hsnsum'] = $provider->getHSN_summary($option);
        return $result;
    }

    public static function getDetailData($dataParams) {
        $provider = new \app\core\providers\Gstr2Provider();
        // for ag module
        if (file_exists(\yii::getAlias('@app/ag/pub/providers/Gstr2Provider.php'))) {
            $agProvider = new \app\ag\pub\providers\Gstr2Provider();
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'abp_control'");
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dt->Rows()) > 0) {
                $provider->docList = array_merge($provider->docList, $agProvider->docList);
            }
        }

        $option = new Gstr2ProviderOption();
        $option->gst_state_id = $dataParams->gst_state_id;
        $option->ret_period_from = $dataParams->ret_period_from;
        $option->ret_period_to = $dataParams->ret_period_to;
        $option->gst_ret_id = $dataParams->gst_ret_id;
        
        $result['fp'] = $dataParams->ret_period;
        $result['gstin'] = \app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gstin'];
        $result['version'] = "GST2.0";
        $result['hash'] = "hash";

        $result['b2b'] = self::fillB2B_data($provider, $option);
        $result['b2bur'] = self::fillB2B_ur_data($provider, $option);
        $result['imp_s'] = self::fillIMP_s_data($provider, $option);
        $result['nil_supplies'] = self::fillCP_NIL_EXEMP_detail($provider, $option);
        $result['hsnsum'] = self::fillHSN_detail($provider, $option);

        return $result;
    }

    private static function fillB2B_data(\app\core\providers\Gstr2Provider $provider, Gstr2ProviderOption $option) {
        // First fetch the linked 2a response file
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "Select jdata From tx.gstr_resp Where gstr_resp_id = 
                (Select gstr_resp_id From tx.gstr2a_reco Where gst_ret_id = :pgst_ret_id)";
        $cmm->setCommandText($sql);
        $cmm->addParam('pgst_ret_id', $option->gst_ret_id);
        $dt_gstr2a_data = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt_gstr2a_data->Rows()) != 1) {
            throw new \Exception("Missing GSTR2A Download. Failed to generate JSON");
        }
        $gstr2a_data = json_decode($dt_gstr2a_data->Rows()[0]['jdata']);
        
        // Second Fetch gstr2a_reco file
        $sqlReco = "Select jdata From tx.gstr2a_reco Where gst_ret_id = :pgst_ret_id";
        $cmmReco = new \app\cwf\vsla\data\SqlCommand();
        $cmmReco->setCommandText($sqlReco);
        $cmmReco->addParam('pgst_ret_id', $option->gst_ret_id);
        $dt_reco_data = \app\cwf\vsla\data\DataConnect::getData($cmmReco);
        if(count($dt_gstr2a_data->Rows()) != 1) {
            throw new \Exception("Missing GSTR2A Reco. Failed to generate JSON");
        }
        $reco_data = json_decode($dt_reco_data->Rows()[0]['jdata']);
        
        // Flag Accepted Invoices
        foreach($gstr2a_data->b2b as &$g2a_ctin) {
            unset($g2a_ctin->cfs);
            foreach($g2a_ctin->inv as &$inv) {
                foreach($reco_data->reco_data->gstr2a_match as $reco_match_inv) {
                    if($inv->chksum == $reco_match_inv->gstr2a->chksum 
                            && $g2a_ctin->ctin == $reco_match_inv->gstin) {
                        if($reco_match_inv->gstr2a->flag == "A") {
                            $inv->flag = "A";
                        } elseif ($reco_match_inv->gstr2a->flag == "AM") { 
                            // do not flag the record. It would default to Add Missing
                        } else {
                            $inv->flag = "P";
                        }
                        foreach($inv->itms as &$itm) {
                            $itm->itc = new \stdClass();
                            if(floatval($reco_match_inv->b2b->itc_amt) > 0) { // temp code to avoid errors
                                $itm->itc->elg = $reco_match_inv->b2b->itc_type;
                                isset($itm->itm_det->samt) ? $itm->itc->tx_s = $itm->itm_det->samt : '';
                                isset($itm->itm_det->camt) ? $itm->itc->tx_c = $itm->itm_det->camt : '';
                                isset($itm->itm_det->iamt) ? $itm->itc->tx_i = $itm->itm_det->iamt : '';
                                isset($itm->itm_det->csamt) ? $itm->itc->tx_cs = $itm->itm_det->csamt : '';
                            } else {
                                $itm->itc->elg = "no";
                            }
                        }
                        break;
                    }
                }
            }
        }
        
        // Flag Pending Invoices
        foreach($gstr2a_data->b2b as &$g2a_ctin) {
            unset($g2a_ctin->cfs);
            foreach($g2a_ctin->inv as &$invm) {
                if(!isset($invm->flag)) {
                    foreach($reco_data->reco_data->gstr2a_unmatched as $unmatched_ctin) {
                        foreach($unmatched_ctin->unmatched_inv2a as $reco_unmatch_inv) {
                            if($invm->chksum == $reco_unmatch_inv->chksum 
                                    && $g2a_ctin->ctin == $unmatched_ctin->ctin) {
                                if($reco_match_inv->gstr2a->flag == "A") {
                                    $invm->flag = "A";
                                } elseif ($reco_match_inv->gstr2a->flag == "AM") { 
                                    // do not flag the record. It would default to Add Missing
                                } else {
                                    $invm->flag = "P";
                                }
                                foreach($invm->itms as &$itm) {
                                    $itm->itc = new \stdClass();
                                    if(floatval($reco_match_inv->b2b->itc_amt) > 0) { // temp code to avoid errors
                                        $itm->itc->elg = $reco_match_inv->b2b->itc_type;
                                        isset($itm->itm_det->samt) ? $itm->itc->tx_s = $itm->itm_det->samt : '';
                                        isset($itm->itm_det->camt) ? $itm->itc->tx_c = $itm->itm_det->camt : '';
                                        isset($itm->itm_det->iamt) ? $itm->itc->tx_i = $itm->itm_det->iamt : '';
                                        isset($itm->itm_det->csamt) ? $itm->itc->tx_cs = $itm->itm_det->csamt : '';
                                    } else {
                                        $itm->itc->elg = "no";
                                    }
                                }
                                break;
                            }
                        }
                    }
                }
            }
        }
        return $gstr2a_data->b2b;
    }

    private static function fillB2B_ur_data(\app\core\providers\Gstr2Provider $provider, Gstr2ProviderOption $option) {
        // Pull data from provider For b2b_ur Self Invoices
        $dtB2B_ur = $provider->getB2B_ur_detail($option);
        $b2b_ur_data = [];
        $ctin_info = new \stdClass();
        $siGroups = $dtB2B_ur->asArray('voucher_id', ['doc_date', 'inv_amt', 'supplier_state_id', 'supplier_name', 'vat_type_id']);
        foreach ($siGroups as $siGroup => $si) {
            $inv_info = new \stdClass();
            $inv_info->inum = $siGroup;
            $inv_info->idt = (new \DateTime($si[0]['doc_date']))->format('d-m-Y');
            $inv_info->val = floatval($si[0]['inv_amt']);
            $inv_info->pos = \app\cwf\vsla\utils\LookupHelper::GetLookupText("../core/tx/lookups/GstState.xml", 'gst_state_code', 'gst_state_id', $option->gst_state_id);
            $inv_info->rchrg = "N";
            $inv_info->sup_name = $si[0]['supplier_name'];
            if ($si[0]['vat_type_id'] == 401) {
                $inv_info->sply_ty = "INTRA";
            } else {
                $inv_info->sply_ty = "INTER";
            }
            $inv_info->itms = [];
            $gttItems = $dtB2B_ur->findRows('voucher_id', $siGroup);
            foreach ($gttItems as $gttItem) {
                $gtt_info = new \stdClass();
                $gtt_info->num = $gttItem['sl_no'];
                $gtt_info->itm_det = new \stdClass();
                $gtt_info->itm_det->rt = floatval($gttItem['gst_pcnt']);
                $gtt_info->itm_det->txval = floatval($gttItem['bt_amt']);
                if ($si[0]['vat_type_id'] == 401) {
                    $gtt_info->itm_det->samt = floatval($gttItem['sgst_amt']);
                    $gtt_info->itm_det->camt = floatval($gttItem['cgst_amt']);
                } else {
                    $gtt_info->itm_det->iamt = floatval($gttItem['igst_amt']);
                }
                $gtt_info->itm_det->csamt = floatval($gttItem['cess_amt']);
                $gtt_info->itc = new \stdClass();
                if((floatval($gttItem['cgst_itc_amt']) + floatval($gttItem['sgst_itc_amt']) + floatval($gttItem['igst_itc_amt']) + floatval($gttItem['cess_itc_amt'])) > 0) {
                    $gtt_info->itc->elg = $gttItem['itc_type'];
                    if ($si[0]['vat_type_id'] == 401) {
                        $gtt_info->itc->tx_c = floatval($gttItem['cgst_itc_amt']);
                        $gtt_info->itc->tx_s = floatval($gttItem['sgst_itc_amt']);
                    } else {
                        $gtt_info->itc->tx_i = floatval($gttItem['igst_itc_amt']);
                    }
                    $gtt_info->itc->tx_cs = floatval($gttItem['cess_itc_amt']);
                } else {
                    $gtt_info->itc->elg = 'no';
                }
                $inv_info->itms[] = $gtt_info;
            }
            $ctin_info->inv[] = $inv_info;
        }
        $b2b_ur_data[] = $ctin_info;
        return $b2b_ur_data;
    }

    private static function fillIMP_s_data(\app\core\providers\Gstr2Provider $provider, Gstr2ProviderOption $option) {
        // Pull data from provider For b2b_ur Self Invoices
        $dtIMP_s = $provider->getIMP_s_detail($option);
        $imp_s_data = [];
        $siGroups = $dtIMP_s->asArray('voucher_id', ['doc_date', 'inv_amt', 'supplier_state_id', 'supplier_name', 'vat_type_id']);
        foreach ($siGroups as $siGroup => $si) {
            $inv_info = new \stdClass();
            $inv_info->inum = $siGroup;
            $inv_info->idt = (new \DateTime($si[0]['doc_date']))->format('d-m-Y');
            $inv_info->ival = floatval($si[0]['inv_amt']);
            $inv_info->pos = \app\cwf\vsla\utils\LookupHelper::GetLookupText("../core/tx/lookups/GstState.xml", 'gst_state_code', 'gst_state_id', $option->gst_state_id);
            $inv_info->itms = [];
            $gttItems = $dtIMP_s->findRows('voucher_id', $siGroup);
            foreach ($gttItems as $gttItem) {
                $gtt_info = new \stdClass();
                $gtt_info->num = $gttItem['sl_no'];
                $gtt_info->rt = floatval($gttItem['gst_pcnt']);
                $gtt_info->txval = floatval($gttItem['bt_amt']);
                $gtt_info->iamt = floatval($gttItem['igst_amt']);
                $gtt_info->csamt = floatval($gttItem['cess_amt']);
                $gtt_info->elg = $gttItem['itc_type'];
                $gtt_info->tx_i = floatval($gttItem['igst_itc_amt']);
                $gtt_info->tx_cs = floatval($gttItem['cess_itc_amt']);
                $inv_info->itms[] = $gtt_info;
            }
            $imp_s_data[] = $inv_info;
        }
        return $imp_s_data;
    }

    private static function fillCP_NIL_EXEMP_detail(\app\core\providers\Gstr2Provider $provider, Gstr2ProviderOption $option) {
        $result = $provider->getCP_NIL_EXEMP_summary($option);
        $nil_supplies = new \stdClass();
        $nil_supplies->intra = new \stdClass();
        $nil_supplies->intra->cpddr = floatval($result['cp']);
        $nil_supplies->intra->exptdsply = floatval($result['exemp']);
        $nil_supplies->intra->ngsply = floatval($result['non_gst']);
        $nil_supplies->intra->nilsply = floatval(0.00);

        // Get 9(4) Exempt Summary
        $dt_exmpt = $provider->getB2C94_exmpt_summary($option);
        $amt = 0.00;
        foreach ($dt_exmpt->Rows() as $dr) {
            $amt += floatval($dr['bt_amt']);
        }
        $nil_supplies->intra->exptdsply += $amt;
        return $nil_supplies;
    }
    
    private static function fillHSN_detail(\app\core\providers\Gstr2Provider $provider, Gstr2ProviderOption $option) {
        $dtHSN = $provider->getHSN_summary($option);
        $hsn_info = new \stdClass();
        $hsn_info->det = [];
        foreach($dtHSN->Rows() as $drHsn) {
            $hsn_item_info = new \stdClass();
            $hsn_item_info->num = $drHsn['sl_no'];
            $hsn_item_info->hsn_sc = $drHsn['hsn_sc_code'];
            $hsn_item_info->uqc = $drHsn['hsn_sc_uom'];
            $hsn_item_info->qty = floatval($drHsn['hsn_qty_tot']);
            $hsn_item_info->val = floatval($drHsn['inv_amt_tot']);
            $hsn_item_info->txval = floatval($drHsn['bt_amt_tot']);
            $hsn_item_info->samt = floatval($drHsn['sgst_amt_tot']);
            $hsn_item_info->camt = floatval($drHsn['cgst_amt_tot']);
            $hsn_item_info->iamt = floatval($drHsn['igst_amt_tot']);
            $hsn_item_info->csamt = floatval($drHsn['cess_amt_tot']);
            $hsn_info->det[] = $hsn_item_info;
        }
        return $hsn_info;
    }

    public static function getB2BDataforReco($dataParams) {
        $provider = new \app\core\providers\Gstr2Provider();
        // for ag module
        if (file_exists(\yii::getAlias('@app/ag/pub/providers/Gstr2Provider.php'))) {
            $agProvider = new \app\ag\pub\providers\Gstr2Provider();
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'abp_control'");
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dt->Rows()) > 0) {
                $provider->docList = array_merge($provider->docList, $agProvider->docList);
            }
        }

        $option = new Gstr2ProviderOption();
        $option->gst_state_id = $dataParams->gst_state_id;
        $option->ret_period_from = $dataParams->ret_period_from;
        $option->ret_period_to = $dataParams->ret_period_to;

        return $provider->getB2B_detail($option);
    }

}

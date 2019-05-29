<?php

namespace app\core\tx\gstr1;

/**
 * Description of Gstr1Worker
 * @author girishshenoy
 */
class Gstr1Worker {

    public static function getNextPeriod($gst_state_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select max(ret_period_to) as ret_period_to
                From tx.gst_ret 
                Where gst_ret_type_id = 101
                    And gst_state_id = :pgst_state_id");
        $cmm->addParam('pgst_state_id', $gst_state_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $last_period = '2017-06-30';
        if (count($dt->Rows()) == 1) {
            if (isset($dt->Rows()[0]['ret_period_to'])) {
                $last_period = $dt->Rows()[0]['ret_period_to'];

                $cmmPP = new \app\cwf\vsla\data\SqlCommand();
                $cmmPP->setCommandText("Select *
                        From tx.gst_ret 
                        Where gst_ret_type_id = 101
                            And gst_state_id = :pgst_state_id
                            And ret_period_to = :pprev_ret_period");
                $cmmPP->addParam('pgst_state_id', $gst_state_id);
                $cmmPP->addParam('pprev_ret_period', $last_period);
                $dtPP = \app\cwf\vsla\data\DataConnect::getData($cmmPP);
            }
        }
        $next_period_from = date_add(new \DateTime($last_period), new \DateInterval('P1D'));
        $result = [];
        $result['ret_period'] = $next_period_from->format('mY');
        $result['ret_period_from'] = $next_period_from->format('Y-m-d');
        $result['ret_period_to'] = date_sub(date_add($next_period_from, new \DateInterval('P1M')), new \DateInterval('P1D'))->format('Y-m-d');
        if (isset($dtPP)) {
            $annex_info = \GuzzleHttp\json_decode($dtPP->Rows()[0]['annex_info']);
            $result['gt'] = $annex_info->gt;
            $result['cur_gt'] = $annex_info->cur_gt;
        }
        return $result;
    }

    public static function getPendingDocData($dataParams) {
        $provider = new \app\core\providers\Gstr1Provider();
        // for ag module
        if (file_exists(\yii::getAlias('@app/ag/pub/providers/Gstr1Provider.php')) && array_key_exists('ag', \yii::$app->modules)) {
            $agProvider = new \app\ag\pub\providers\Gstr1Provider();
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'invoice_control'");
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dt->Rows()) > 0) {
                $provider->docList = array_merge($provider->docList, $agProvider->docList);
            }
        }

        $option = new Gstr1ProviderOption();
        $option->gst_state_id = $dataParams->gst_state_id;
        $option->ret_period_from = $dataParams->ret_period_from;
        $option->ret_period_to = $dataParams->ret_period_to;
        $dt_pending = $provider->preProcessPendingDocs($option);
        $result = [
            'pending' => $dt_pending->Rows(),
            'si' => []
        ];
        return $result;
    }

    public static function getSummaryData($dataParams) {
        $provider = new \app\core\providers\Gstr1Provider();
        // for ag module
        if (file_exists(\yii::getAlias('@app/ag/pub/providers/Gstr1Provider.php')) && array_key_exists('ag', \yii::$app->modules)) {
            $agProvider = new \app\ag\pub\providers\Gstr1Provider();
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'invoice_control'");
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dt->Rows()) > 0) {
                $provider->docList = array_merge($provider->docList, $agProvider->docList);
                $provider->audList = array_merge($provider->audList, $agProvider->audList);
            }
        }

        $option = new Gstr1ProviderOption();
        $option->gst_state_id = $dataParams->gst_state_id;
        $option->ret_period_from = $dataParams->ret_period_from;
        $option->ret_period_to = $dataParams->ret_period_to;
        $result['return_period'] = $dataParams->ret_period_to;
        $result['gstin'] = \app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gstin'];
        $result['company_name'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_name');
        $result['cy_pm_turnover'] = $dataParams->cur_gt;
        $result['b2b'] = $provider->getB2B_summary($option);
        $result['b2cl'] = $provider->getB2CL_summary($option);
        $result['b2cs'] = $provider->getB2CS_summary($option);
        $result['exp_ex'] = $provider->getEXP_ex_summary($option);
        $result['exp_sez'] = $provider->getEXP_sez_summary($option);
        $result['exemp'] = $provider->getEXEMP_summary($option);
        $result['cdnr'] = $provider->getCDNR_summary($option);
        $result['cdnur'] = $provider->getCDNUR_summary($option);
        $result['at'] = $provider->getAT_summary($option);
        $result['atadj'] = $provider->getATADJ_summary($option);
        $result['hsn'] = $provider->getHSN_summary($option);
        $result['doc_issue'] = $provider->getDOC_count($option);
        return $result;
    }

    public static function getDetailData($dataParams) {
        $provider = new \app\core\providers\Gstr1Provider();
        // for ag module
        if (file_exists(\yii::getAlias('@app/ag/pub/providers/Gstr1Provider.php'))) {
            $agProvider = new \app\ag\pub\providers\Gstr1Provider();
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'invoice_control'");
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dt->Rows()) > 0) {
                $provider->docList = array_merge($provider->docList, $agProvider->docList);
                $provider->audList = array_merge($provider->audList, $agProvider->audList);
            }
        }

        $option = new Gstr1ProviderOption();
        $option->gst_state_id = $dataParams->gst_state_id;
        $option->ret_period_from = $dataParams->ret_period_from;
        $option->ret_period_to = $dataParams->ret_period_to;
        $result['fp'] = $dataParams->ret_period;
        $result['gstin'] = \app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gstin'];
        $result['gt'] = floatval($dataParams->gt);
        $result['cur_gt'] = floatval($dataParams->cur_gt);
        $result['version'] = "GST2.2.1";
        $result['hash'] = "hash";

        $result['b2b'] = self::fillB2B_SEZ_data($provider, $option);
        $result['b2cl'] = self::fillB2CL_data($provider, $option);
        $result['b2cs'] = self::fillB2CS_data($provider, $option);
        $result['exp'] = self::fillExp_data($provider, $option);
        $result['cdnr'] = self::fillCDNR_data($provider, $option);
        $nil_info = self::fillEXEMPT_data($provider, $option);
        if(isset($nil_info->inv)) {
            $result['nil'] = $nil_info;
        }
        $result['at'] = self::fillAT_data($provider, $option);
        $result['txpd'] = self::fillATAJ_data($provider, $option);
        $result['hsn'] = self::fillHSN_data($provider, $option, $result);
        $result['doc_issue'] = self::fillDoc_issue($provider, $option);
        return $result;
    }
    
    public static function getRawDetailData(Gstr1ProviderOption $option, int $detail_type) {
        $provider = new \app\core\providers\Gstr1Provider();
        // for ag module
        if (file_exists(\yii::getAlias('@app/ag/pub/providers/Gstr1Provider.php'))) {
            $agProvider = new \app\ag\pub\providers\Gstr1Provider();
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText("SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'invoice_control'");
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dt->Rows()) > 0) {
                $provider->docList = array_merge($provider->docList, $agProvider->docList);
                $provider->audList = array_merge($provider->audList, $agProvider->audList);
            }
        }
        
        if($detail_type == 4) {
            $result['b2b'] = $provider->getB2B_raw_detail($option);
        } elseif($detail_type == 7) {
            $result['b2cs'] = $provider->getB2CS_raw_detail($option);
        } elseif ($detail_type == 8) {
            $result['exemp'] = $provider->getEXEMP_raw_detail($option);
        }
        //$result['b2cl'] = $provider->getB2CL_detail($option);
        //$result['exp'] = $provider->getEXP_ex_detail($option);
        
        
        return $result;
    }

    private static function fillB2B_SEZ_data(\app\core\providers\Gstr1Provider $provider, Gstr1ProviderOption $option) {
        // Pull data from provider For B2B and SEZ
        $dtB2B = $provider->getB2B_detail($option);
        $b2b_data = [];
        $ctinGroups = $dtB2B->asArray('customer_gstin', ['voucher_id', 'doc_date', 'inv_amt', 'customer_state_id', 'inv_type', 'vat_type_id']);
        foreach ($ctinGroups as $ctinGroup => $invs) {
            $ctin_info = new \stdClass();
            $ctin_info->ctin = $ctinGroup;
            $ctin_info->inv = [];
            $invs = self::unique_id($invs, 'voucher_id');
            foreach ($invs as $inv) {
                $inv_info = new \stdClass();
                $inv_info->inum = $inv['voucher_id'];
                $inv_info->idt = (new \DateTime($inv['doc_date']))->format('d-m-Y');
                $inv_info->val = floatval($inv['inv_amt']);
                if ($inv['customer_state_id'] == 98) {
                    // This is SEZ Supply
                    $inv_info->pos = substr($ctinGroup, 0, 2);
                    if ($inv['vat_type_id'] == 303) {
                        $inv_info->inv_typ = "DE"; // Deemed Exports
                    } else if ($inv['vat_type_id'] == 304) {
                        $inv_info->inv_typ = "SEWP"; // Sez with payment
                    } else if ($inv['vat_type_id'] == 305) {
                        $inv_info->inv_typ = "SEWOP"; // Sez without payment
                    }
                } else {
                    // This is DTA and export Supply
                    if ($inv['customer_state_id'] == 99) {
                        $inv_info->pos = '97';
                    } else {
                        $inv_info->pos = \app\cwf\vsla\utils\LookupHelper::GetLookupText("../core/tx/lookups/GstState.xml", 'gst_state_code', 'gst_state_id', $inv['customer_state_id']);
                    }
                    $inv_info->inv_typ = 'R';
                }
                $inv_info->rchrg = 'N';
                //$inv_info->cfs = "N"; -- No longer required since ver. 2.2
                $inv_info->itms = [];
                $gttItems = $dtB2B->findRows('voucher_id', $inv['voucher_id']);
                foreach ($gttItems as $gttItem) {
                    $gtt_info = new \stdClass();
                    $gtt_info->num = $gttItem['sl_no'];
                    $gtt_info->itm_det = new \stdClass();
                    $gtt_info->itm_det->rt = floatval($gttItem['gst_pcnt']);
                    $gtt_info->itm_det->txval = floatval($gttItem['bt_amt']);
                    $gtt_info->itm_det->samt = floatval($gttItem['sgst_amt']);
                    $gtt_info->itm_det->camt = floatval($gttItem['cgst_amt']);
                    $gtt_info->itm_det->iamt = floatval($gttItem['igst_amt']);
                    $gtt_info->itm_det->csamt = floatval($gttItem['cess_amt']);
                    $inv_info->itms[] = $gtt_info;
                }
                $ctin_info->inv[] = $inv_info;
            }
            $b2b_data[] = $ctin_info;
        }
        
        // fetch sez data (The entire logic is repeated to concat sez data)
        $dtB2BSeZ = $provider->getB2B_SEZ_detail($option);
        $ctinGroups = $dtB2BSeZ->asArray('customer_gstin', ['voucher_id', 'doc_date', 'inv_amt', 'customer_state_id', 'inv_type', 'vat_type_id']);
        foreach ($ctinGroups as $ctinGroup => $invs) {
            $ctin_info = new \stdClass();
            $ctin_info->ctin = $ctinGroup;
            $ctin_info->inv = [];
            $invs = self::unique_id($invs, 'voucher_id');
            foreach ($invs as $inv) {
                $inv_info = new \stdClass();
                $inv_info->inum = $inv['voucher_id'];
                $inv_info->idt = (new \DateTime($inv['doc_date']))->format('d-m-Y');
                $inv_info->val = floatval($inv['inv_amt']);
                if ($inv['customer_state_id'] == 98) {
                    // This is SEZ Supply
                    $inv_info->pos = substr($ctinGroup, 0, 2);
                    if ($inv['vat_type_id'] == 303) {
                        $inv_info->inv_typ = "DE"; // Deemed Exports
                    } else if ($inv['vat_type_id'] == 304) {
                        $inv_info->inv_typ = "SEWP"; // Sez with payment
                    } else if ($inv['vat_type_id'] == 305) {
                        $inv_info->inv_typ = "SEWOP"; // Sez without payment
                    }
                } else {
                    // This is DTA and export Supply
                    if ($inv['customer_state_id'] == 99) {
                        $inv_info->pos = '97';
                    } else {
                        $inv_info->pos = \app\cwf\vsla\utils\LookupHelper::GetLookupText("../core/tx/lookups/GstState.xml", 'gst_state_code', 'gst_state_id', $inv['customer_state_id']);
                    }
                    $inv_info->inv_typ = 'R';
                }
                $inv_info->rchrg = 'N';
                //$inv_info->cfs = "N"; -- No longer required since ver. 2.2
                $inv_info->itms = [];
                $gttItems = $dtB2BSeZ->findRows('voucher_id', $inv['voucher_id']);
                foreach ($gttItems as $gttItem) {
                    $gtt_info = new \stdClass();
                    $gtt_info->num = $gttItem['sl_no'];
                    $gtt_info->itm_det = new \stdClass();
                    $gtt_info->itm_det->rt = floatval($gttItem['gst_pcnt']);
                    $gtt_info->itm_det->txval = floatval($gttItem['bt_amt']);
                    $gtt_info->itm_det->samt = floatval($gttItem['sgst_amt']);
                    $gtt_info->itm_det->camt = floatval($gttItem['cgst_amt']);
                    $gtt_info->itm_det->iamt = floatval($gttItem['igst_amt']);
                    $gtt_info->itm_det->csamt = floatval($gttItem['cess_amt']);
                    $inv_info->itms[] = $gtt_info;
                }
                $ctin_info->inv[] = $inv_info;
            }
            $b2b_data[] = $ctin_info;
        }
        return $b2b_data;
    }

    private static function fillB2CL_data(\app\core\providers\Gstr1Provider $provider, Gstr1ProviderOption $option) {
        // Pull data from provider
        $dtB2CL = $provider->getB2CL_detail($option);
        $b2cl_data = [];
        $posGroups = $dtB2CL->asArray('customer_state_id', ['voucher_id', 'doc_date', 'inv_amt', 'inv_type']);
        foreach ($posGroups as $posGroup => $invs) {
            $pos_info = new \stdClass();
            if ($posGroup == 99) {
                $pos_info->pos = '97';
            } else {
                $pos_info->pos = \app\cwf\vsla\utils\LookupHelper::GetLookupText("../core/tx/lookups/GstState.xml", 'gst_state_code', 'gst_state_id', $posGroup);
            }
            $pos_info->inv = [];
            $invs = self::unique_id($invs, 'voucher_id');
            foreach ($invs as $inv) {
                $inv_info = new \stdClass();
                $inv_info->inum = $inv['voucher_id'];
                $inv_info->idt = (new \DateTime($inv['doc_date']))->format('d-m-Y');
                $inv_info->val = floatval($inv['inv_amt']);
                $inv_info->itms = [];
                $gttItems = $dtB2CL->findRows('voucher_id', $inv['voucher_id']);
                foreach ($gttItems as $gttItem) {
                    $gtt_info = new \stdClass();
                    $gtt_info->num = $gttItem['sl_no'];
                    $gtt_info->itm_det = new \stdClass();
                    $gtt_info->itm_det->rt = floatval($gttItem['gst_pcnt']);
                    $gtt_info->itm_det->txval = floatval($gttItem['bt_amt']);
                    $gtt_info->itm_det->iamt = floatval($gttItem['igst_amt']);
                    $gtt_info->itm_det->csamt = floatval($gttItem['cess_amt']);
                    $inv_info->itms[] = $gtt_info;
                }
                $pos_info->inv[] = $inv_info;
            }
            $b2cl_data[] = $pos_info;
        }
        return $b2cl_data;
    }

    private static function fillB2CS_data(\app\core\providers\Gstr1Provider $provider, Gstr1ProviderOption $option) {
        // Pull data from provider
        $dtB2CS = $provider->getB2CS_detail($option);
        $b2cs_data = [];
        foreach ($dtB2CS->Rows() as $drB2cs) {
            $b2cs_info = new \stdClass();
            $b2cs_info->sply_ty = $drB2cs['vat_type_id'] == 301 ? "INTRA" : "INTER";
            $b2cs_info->txval = floatval($drB2cs['bt_amt_tot']);
            $b2cs_info->typ = "OE";
            if ($drB2cs["customer_state_id"] == 99) {
                $b2cs_info->pos = '97';
            } else {
                $b2cs_info->pos = \app\cwf\vsla\utils\LookupHelper::GetLookupText("../core/tx/lookups/GstState.xml", 'gst_state_code', 'gst_state_id', $drB2cs["customer_state_id"]);
            }
            $b2cs_info->rt = floatval($drB2cs['gst_pcnt']);
            $b2cs_info->iamt = floatval($drB2cs['igst_amt_tot']);
            $b2cs_info->camt = floatval($drB2cs['cgst_amt_tot']);
            $b2cs_info->samt = floatval($drB2cs['sgst_amt_tot']);
            $b2cs_info->csamt = floatval($drB2cs['cess_amt_tot']);
            if ($b2cs_info->pos == '99') {
                $b2cs_info->pos = '97';
            }
            $b2cs_data[] = $b2cs_info;
        }
        return $b2cs_data;
    }

    private static function fillExp_data(\app\core\providers\Gstr1Provider $provider, Gstr1ProviderOption $option) {
        // Warning: Missing data elements are Shipping Port/Bill/Date
        // Pull data from provider
        $dtExp = $provider->getEXP_ex_detail($option);
        $exp_data = [];
        $expTypeGroups = $dtExp->asArray('vat_type_id', ['voucher_id', 'doc_date', 'inv_amt']);
        foreach ($expTypeGroups as $expGroup => $invs) {
            $exp_type_info = new \stdClass();
            $exp_type_info->exp_typ = $expGroup == 306 ? 'WPAY' : 'WOPAY';
            $exp_type_info->inv = [];
            $invs = self::unique_id($invs, 'voucher_id');
            foreach ($invs as $inv) {
                $inv_info = new \stdClass();
                $inv_info->inum = $inv['voucher_id'];
                $inv_info->idt = (new \DateTime($inv['doc_date']))->format('d-m-Y');
                $inv_info->val = floatval($inv['inv_amt']);
                $inv_info->itms = [];
                $gttItems = $dtExp->findRows('voucher_id', $inv['voucher_id']);
                foreach ($gttItems as $gttItem) {
                    $gtt_info = new \stdClass();
                    $gtt_info->rt = floatval($gttItem['gst_pcnt']);
                    $gtt_info->txval = floatval($gttItem['bt_amt']);
                    $gtt_info->iamt = floatval($gttItem['igst_amt']);
                    $gtt_info->csamt = floatval(0);
                    $inv_info->itms[] = $gtt_info;
                }
                $exp_type_info->inv[] = $inv_info;
            }
            $exp_data[] = $exp_type_info;
        }
        return $exp_data;
    }

    private static function fillCDNR_data(\app\core\providers\Gstr1Provider $provider, Gstr1ProviderOption $option) {
        // Pull data from provider For Credit Notes to Registered Persons
        $dtCdnr = $provider->getCDNR_detail($option);
        $cdnr_data = [];
        $ctinGroups = $dtCdnr->asArray('customer_gstin', ['voucher_id', 'doc_date', 'inv_amt', 'origin_inv_id', 'origin_inv_date']);
        foreach ($ctinGroups as $ctinGroup => $invs) {
            $ctin_info = new \stdClass();
            $ctin_info->ctin = $ctinGroup;
            $ctin_info->nt = [];
            $invs = self::unique_id($invs, 'voucher_id');
            foreach ($invs as $inv) {
                $inv_info = new \stdClass();
                $inv_info->ntty = "C";
                $inv_info->nt_num = $inv['voucher_id'];
                $inv_info->nt_dt = (new \DateTime($inv['doc_date']))->format('d-m-Y');
                //$inv_info->rsn = "01-Sales Return"; // This is discontinued in current version of returns
                $inv_info->p_gst = "N";
                $inv_info->inum = $inv['origin_inv_id'];
                $inv_info->idt = (new \DateTime($inv['origin_inv_date']))->format('d-m-Y');
                $inv_info->val = floatval($inv['inv_amt']);
                $inv_info->itms = [];
                $gttItems = $dtCdnr->findRows('voucher_id', $inv['voucher_id']);
                foreach ($gttItems as $gttItem) {
                    $gtt_info = new \stdClass();
                    $gtt_info->num = $gttItem['sl_no'];
                    $gtt_info->itm_det = new \stdClass();
                    $gtt_info->itm_det->rt = floatval($gttItem['gst_pcnt']);
                    $gtt_info->itm_det->txval = floatval($gttItem['bt_amt']);
                    $gtt_info->itm_det->samt = floatval($gttItem['sgst_amt']);
                    $gtt_info->itm_det->camt = floatval($gttItem['cgst_amt']);
                    $gtt_info->itm_det->iamt = floatval($gttItem['igst_amt']);
                    $gtt_info->itm_det->csamt = floatval($gttItem['cess_amt']);
                    $inv_info->itms[] = $gtt_info;
                }
                $ctin_info->nt[] = $inv_info;
            }
            $cdnr_data[] = $ctin_info;
        }
        return $cdnr_data;
    }
    
    private static function fillEXEMPT_data(\app\core\providers\Gstr1Provider $provider, Gstr1ProviderOption $option) {
        // Pull data from provider
        $dtExempt = $provider->getEXEMP_summary($option);
        $nil_info = new \stdClass();
        $nil_data = [];
        foreach ($dtExempt->Rows() as $drExempt) {
            $exmp_info = new \stdClass();
            if($drExempt['supply_type'] == 'Local Supply' && $drExempt['gstin_status'] == 'Registered Person') {
                $exmp_info->sply_ty =  "INTRAB2B";
            } elseif($drExempt['supply_type'] == 'Local Supply' && $drExempt['gstin_status'] == 'Unregistered Person') {
                $exmp_info->sply_ty =  "INTRAB2C";
            } elseif($drExempt['supply_type'] == 'Inter-State Supply' && $drExempt['gstin_status'] == 'Registered Person') {
                $exmp_info->sply_ty =  "INTRB2B";
            } elseif($drExempt['supply_type'] == 'Inter-State Supply' && $drExempt['gstin_status'] == 'Unregistered Person') {
                $exmp_info->sply_ty =  "INTRB2C";
            } 
            $exmp_info->nil_amt = floatval($drExempt['nil_amt_tot']);
            $exmp_info->expt_amt = floatval($drExempt['exempt_amt_tot']);
            $exmp_info->ngsup_amt = 0.00;
            $nil_data[] = $exmp_info;
        }
        if(count($nil_data)>0) {
            $nil_info->inv = $nil_data;
        }
        return $nil_info;
    }

    public static function fillAT_data(\app\core\providers\Gstr1Provider $provider, Gstr1ProviderOption $option) {
        // Pull data from provider
        $dtAt = $provider->getAT_detail($option);
        $at_data = [];
        $posGroups = $dtAt->asArray('gst_state_id', ['gst_rate_id', 'gst_pcnt', 'bt_amt_tot', 'sgst_amt_tot', 'cgst_amt_tot', 'igst_amt_tot']);
        foreach ($posGroups as $posGroup => $gstRates) {
            $pos_info = new \stdClass();
            if($posGroup==99){
                $pos_info->pos = '97';
            }else{
                $pos_info->pos = \app\cwf\vsla\utils\LookupHelper::GetLookupText("../core/tx/lookups/GstState.xml", 'gst_state_code', 'gst_state_id', $posGroup);
            }            
            $pos_info->sply_ty = $option->gst_state_id == $posGroup ? "INTRA" : "INTER";
            $pos_info->itms = [];
            foreach ($gstRates as $gstRate) {
                $item_info = new \stdClass();
                $item_info->rt = floatval($gstRate['gst_pcnt']);
                $item_info->ad_amt = floatval($gstRate['bt_amt_tot']);
                $item_info->iamt = floatval($gstRate['igst_amt_tot']);
                $item_info->camt = floatval($gstRate['cgst_amt_tot']);
                $item_info->samt = floatval($gstRate['sgst_amt_tot']);
                $pos_info->itms[] = $item_info;
            }
            $at_data[] = $pos_info;
        }
        return $at_data;
    }

    private static function fillATAJ_data(\app\core\providers\Gstr1Provider $provider, Gstr1ProviderOption $option) {
        // Pull data from provider
        $dtAtAJ = $provider->getATADJ_Detail($option);
        $ataj_data = [];
        $posGroups = $dtAtAJ->asArray('gst_state_id', ['gst_rate_id', 'gst_pcnt', 'bt_settl_amt', 'sgst_settl_amt', 'cgst_settl_amt', 'igst_settl_amt']);
        foreach ($posGroups as $posGroup => $gstRates) {
            $pos_info = new \stdClass();
            if($posGroup==99){
                $pos_info->pos = '97';
            }else{
                $pos_info->pos = \app\cwf\vsla\utils\LookupHelper::GetLookupText("../core/tx/lookups/GstState.xml", 'gst_state_code', 'gst_state_id', $posGroup);
            }  
            $pos_info->sply_ty = $option->gst_state_id == $posGroup ? "INTRA" : "INTER";
            $pos_info->itms = [];
            foreach ($gstRates as $gstRate) {
                $item_info = new \stdClass();
                $item_info->rt = floatval($gstRate['gst_pcnt']);
                $item_info->ad_amt = floatval($gstRate['bt_settl_amt']);
                $item_info->iamt = floatval($gstRate['igst_settl_amt']);
                $item_info->camt = floatval($gstRate['cgst_settl_amt']);
                $item_info->samt = floatval($gstRate['sgst_settl_amt']);
                $pos_info->itms[] = $item_info;
            }
            $ataj_data[] = $pos_info;
        }
        return $ataj_data;
    }

    private static function fillDoc_issue(\app\core\providers\Gstr1Provider $provider, Gstr1ProviderOption $option) {
        $DocList = $provider->getDOC_count($option);
        $doc_issue_info = new \stdClass();
        $doc_issue_info->doc_det = [];
        foreach ($DocList as $doc) {
            $doc_info = new \stdClass();
            $doc_info->doc_num = $doc->sl_no;
            $doc_info->docs = [];
            $i = 1;
            foreach ($doc->doc_list as $drItem) {
                $docItem_info = new \stdClass();
                $docItem_info->num = $i;
                $docItem_info->from = $drItem['doc_min'];
                $docItem_info->to = $drItem['doc_max'];
                $docItem_info->totnum = $drItem['doc_count'];
                $docItem_info->cancel = 0;
                $docItem_info->net_issue = $drItem['doc_count'];
                $doc_info->docs[] = $docItem_info;
                $i++;
            }
            $doc_issue_info->doc_det[] = $doc_info;
        }
        return $doc_issue_info;
    }

    private static function fillHSN_data(\app\core\providers\Gstr1Provider $provider, Gstr1ProviderOption $option) {
        $dtHSN = $provider->getHSN_summary($option);
        $hsn_info = new \stdClass();
        $hsn_info->data = [];
        foreach ($dtHSN->Rows() as $drHsn) {
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
            $hsn_info->data[] = $hsn_item_info;
        }
        return $hsn_info;
    }

    private static function unique_id($array, $key) {
        $temp_array = array();
        $i = 0;
        $key_array = array();

        foreach ($array as $val) {
            if (!in_array($val[$key], $key_array)) {
                $key_array[$i] = $val[$key];
                $temp_array[$i] = $val;
            }
            $i++;
        }
        return $temp_array;
    }

    public static function is_ctin_valid($ctin) {
        $is_valid = true;
        if (strlen($ctin) != 15) {
            $is_valid = false;
        }
        if ($is_valid && !preg_match("/^[0-9]{2}[a-zA-Z]{5}[0-9]{4}[a-zA-Z]{1}[1-9A-Za-z]{1}[Z]{1}[0-9a-zA-Z]{1}$/", $ctin)) {
            $is_valid = false;
        } 
        return $is_valid;
    }

}
<?php

namespace app\core\tx\ewb;

include_once '../core/tx/ewb/CodeHelper.php';

class EwbWorker {

    const ewb_version = '1.0.0618';
    const genMode = 'Excel';

    public static function getJsonFile($dataParams) {
        $data = EwbWorker::getJsonData($dataParams);
        if (count($data['brules']) == 0) {
            $jfile = EwbWorker::createFile($dataParams->doc_id, $data['data']);
            return $jfile;
        } else {
            return ['status' => 'ERROR', 'brules' => $data['brules']];
        }
    }

    public static function getJsonData($dataParams) {
        $doc_id = $dataParams->doc_id;
        $doc_type = $dataParams->doc_type;
        if ($doc_id != '' && $doc_type != '') {
            $res = [];
            $data = EwbWorker::getDocData($doc_id, $doc_type);
            if (count($data['brules']) == 0) {
                $res['version'] = EwbWorker::ewb_version;
                $res['billLists'] = $data['bills'];
            }
            return ['data' => $res, 'brules' => $data['brules']];
        } else {
            $brules[] = 'Doc ID and Doc type are required.';
            return ['status' => 'ERROR', 'brules' => $brules];
        }
    }

    private static function createFile($doc_id, $data) {
        $virtualPath = \Yii::$app->getUrlManager()->getBaseUrl() . '/reportcache/' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID() . '/';
        $pathName = \yii::getAlias('@webroot') . '/reportcache/' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID() . '/';
        \app\cwf\vsla\render\JReportHelper::createSessionPath();
        $fileName = 'ewb_' . $doc_id . '_' . time() . '.json';
        $fhandle = fopen($pathName . $fileName, 'w');
        fwrite($fhandle, json_encode($data, JSON_UNESCAPED_SLASHES));
        return ['status' => 'OK', 'filePath' => $virtualPath . $fileName, 'fileName' => $fileName];
    }

    public static function updateEwbInDoc($dataParams) {
        $doc_id = $dataParams->doc_id;
        $doc_type = $dataParams->doc_type;
        $ewb_no = $dataParams->ewb_no;
        $cmdtext = '';
        switch ($doc_type) {
            case 'SIV':
                $cmdtext = "update st.stock_control
                                set annex_info = jsonb_set(annex_info, '{ewb_no}', ('\"" . $ewb_no . "\"')::jsonb, true)
                                where stock_id = :pdoc_id";
                break;
            case 'INVG':
                $cmdtext = "update pub.invoice_control
                                set annex_info = jsonb_set(annex_info, '{ewb_no}', ('\"" . $ewb_no . "\"')::jsonb, true)
                                where voucher_id = :pdoc_id";
                break;
        }
        if ($cmdtext != '') {
            $cmd = new \app\cwf\vsla\data\SqlCommand();
            $cmd->setCommandText($cmdtext);
            $cmd->addParam('pdoc_id', $doc_id);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmd);
        }
    }

    private static function getDocData($doc_id, $doc_type) {
        $bill = [];
        $bills = [];
        $brules = [];
        $cmdtext = '';
        switch ($doc_type) {
            case 'SIV':
                $cmdtext = "select a.stock_id,a.doc_date,a.doc_type,a.branch_id,a.account_id,
                    b.branch_address::varchar as fromaddr,b.gst_state_id as fromstate,b.gstin as fromgstin,
                    b.city as fromcity,b.pin as frompin,e.customer,a.customer_address::varchar as toaddr1,
                    COALESCE((a.annex_info->'gst_output_info'->>'ship_consign_addr')::varchar,'') as toaddr2,
                    COALESCE((a.annex_info->'gst_output_info'->>'customer_gstin')::varchar,'') as togstin,
                    COALESCE((a.annex_info->'gst_output_info'->>'customer_state_id')::bigint,0) as tostate,
                    COALESCE((a.annex_info->'gst_output_info'->>'customer_city')::varchar,'') as tocity1,
                    COALESCE((a.annex_info->'gst_output_info'->>'ship_consign_city')::varchar,'') as tocity2,
                    COALESCE((a.annex_info->'gst_output_info'->>'customer_pin')::varchar,'') as topin1,
                    COALESCE((a.annex_info->'gst_output_info'->>'ship_consign_pin')::varchar,'') as topin2,
                    a.stock_id, sum(c.sgst_amt) as sgst_amt, sum(c.cgst_amt) as cgst_amt, 
                    sum(c.igst_amt) as igst_amt, sum(c.cess_amt) as cess_amt,
                    COALESCE((a.annex_info->>'bt_amt_tot')::numeric, 0) as bt_amt, a.total_amt as tot_amt
                    from st.stock_control a
                    inner join sys.branch b on a.branch_id = b.branch_id
                    inner join st.stock_tran d on a.stock_id = d.stock_id
                    inner join tx.gst_tax_tran c on d.stock_tran_id = c.gst_tax_tran_id
                    inner join ar.customer e on a.account_id = e.customer_id
                    where a.stock_id = :pdoc_id
                    group by a.stock_id,a.annex_info,b.branch_address,b.gst_state_id,b.gstin,b.city,b.pin,a.customer_address,e.customer";
                break;

            case 'INVG':
                $cmdtext = "select a.voucher_id,a.doc_date,a.doc_type,a.branch_id,a.customer_id,
                    b.branch_address::varchar as fromaddr,b.gst_state_id as fromstate,b.gstin as fromgstin,
                    b.city as fromcity,b.pin as frompin,e.customer,a.invoice_address::varchar as toaddr1,
                    COALESCE((a.annex_info->'gst_output_info'->>'ship_consign_addr')::varchar,'') as toaddr2,
                    COALESCE((a.annex_info->'gst_output_info'->>'customer_gstin')::varchar,'') as togstin,
                    COALESCE((a.annex_info->'gst_output_info'->>'customer_state_id')::bigint,0) as tostate,
                    COALESCE((a.annex_info->'gst_output_info'->>'customer_city')::varchar,'') as tocity1,
                    COALESCE((a.annex_info->'gst_output_info'->>'ship_consign_city')::varchar,'') as tocity2,
                    COALESCE((a.annex_info->'gst_output_info'->>'customer_pin')::varchar,'') as topin1,
                    COALESCE((a.annex_info->'gst_output_info'->>'ship_consign_pin')::varchar,'') as topin2,
                    sum(c.sgst_amt) as sgst_amt, sum(c.cgst_amt) as cgst_amt, 
                    sum(c.igst_amt) as igst_amt, sum(c.cess_amt) as cess_amt,a.net_debit_amt as tot_amt,
                    COALESCE(a.ro_amt, 0) as bt_amt
                    from pub.invoice_control a
                    inner join sys.branch b on a.branch_id = b.branch_id
                    inner join pub.invoice_task_tran d on a.voucher_id = d.voucher_id
                    inner join tx.gst_tax_tran c on d.vch_tran_id = c.gst_tax_tran_id
                    inner join ar.customer e on a.customer_id = e.customer_id
                    where a.voucher_id = :pdoc_id
                    group by a.voucher_id,a.annex_info,b.branch_address,b.gst_state_id,b.gstin,e.customer,b.city,b.pin,a.invoice_address";
                break;
        }
        if ($cmdtext != '') {
            $cmd = new \app\cwf\vsla\data\SqlCommand();
            $cmd->setCommandText($cmdtext);
            $cmd->addParam('pdoc_id', $doc_id);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmd);

            if (count($dt->Rows()) > 0) {
                $brules = EwbWorker::validateRec($dt->Rows()[0]);
                if (count($brules) == 0) {
                    //$bill['genMode'] = EwbWorker::genMode;
                    $bill['userGstin'] = $dt->Rows()[0]['fromgstin'];

                    //supply info
                    $bill['supplyType'] = SupplyType::OUTWARD;
                    $bill['subSupplyType'] = SubSupplyType::SUPPLY;

                    //doc info
                    $bill['docType'] = DocType::TAX_INVOICE;
                    $bill['docNo'] = $doc_id;
                    $bill['docDate'] = (new \DateTime($dt->Rows()[0]['doc_date']))->format('d/m/Y');

                    //company branch info
                    $bill['fromGstin'] = $dt->Rows()[0]['fromgstin'];
                    $bill['fromTrdName'] = \app\cwf\vsla\security\SessionManager::getSessionVariable('company_name');
                    $bill['fromAddr1'] = preg_replace('/\s+/', ' ', trim($dt->Rows()[0]['fromaddr']));
                    $bill['fromAddr2'] = '';
                    $bill['fromPlace'] = $dt->Rows()[0]['fromcity'];
                    $bill['fromPincode'] = intval($dt->Rows()[0]['frompin']);
                    $bill['fromStateCode'] = intval($dt->Rows()[0]['fromstate']);
                    $bill['actualFromStateCode'] = intval($dt->Rows()[0]['fromstate']);

                    //customer info
                    $bill['toGstin'] = $dt->Rows()[0]['togstin'];
                    $bill['toTrdName'] = $dt->Rows()[0]['customer'];
                    $bill['toAddr1'] = preg_replace('/\s+/', ' ', trim(($dt->Rows()[0]['toaddr2'] == '' ? $dt->Rows()[0]['toaddr1'] : $dt->Rows()[0]['toaddr2'])));
                    $bill['toAddr2'] = '';
                    $bill['toPlace'] = ($dt->Rows()[0]['tocity2'] == '' ? $dt->Rows()[0]['tocity1'] : $dt->Rows()[0]['tocity2']);
                    $bill['toPincode'] = ($dt->Rows()[0]['topin2'] == '' ? intval($dt->Rows()[0]['topin1']) : intval($dt->Rows()[0]['topin2']));
                    $bill['toStateCode'] = intval($dt->Rows()[0]['tostate']);
                    $bill['actualToStateCode'] = intval($dt->Rows()[0]['tostate']);

                    //amounts
                    $bill['totalValue'] = floatval($dt->Rows()[0]['bt_amt']);
                    $bill['cgstValue'] = floatval($dt->Rows()[0]['cgst_amt']);
                    $bill['sgstValue'] = floatval($dt->Rows()[0]['sgst_amt']);
                    $bill['igstValue'] = floatval($dt->Rows()[0]['igst_amt']);
                    $bill['cessValue'] = floatval($dt->Rows()[0]['cess_amt']);
                    $bill['totInvValue'] = floatval($dt->Rows()[0]['tot_amt']);

                    //transport info
                    $bill['transMode'] = TransportationMode::ROAD;
                    $bill['transDistance'] = 0000; //-----------pending-------
                    $bill['transporterName'] = ''; //-----------pending-------
                    $bill['transporterId'] = ''; //-----------pending-------
                    $bill['transDocNo'] = ''; //-----------pending-------
                    $bill['transDocDate'] = ''; //-----------pending-------
                    $bill['vehicleNo'] = ''; //-----------pending-------
                    $bill['vehicleType'] = VehicleType::REGULAR;

                    $tranres = EwbWorker::getItemData($doc_id, $doc_type);

                    $bill['mainHsnCode'] = $tranres['mainHsn'];
                    $bill['itemList'] = $tranres['items'];
                }
            } else {
                $brules[] = 'Document info not found';
            }
        } else {
            $brules[] = 'Document type not found';
        }
        $bills[] = $bill;
        return ['bills' => $bills, 'brules' => $brules];
    }

    private static function getItemData($doc_id, $doc_type) {
        $mainHsnCode = 0;
        $items = [];
        $cmdtext = '';
        switch ($doc_type) {
            case 'SIV':
                $cmdtext = "select a.stock_id,a.stock_tran_id,a.sl_no,a.material_id,c.material_name,a.issued_qty,
                                b.hsn_sc_code,COALESCE(g.uom_code,'NOS') as ewb_uom,b.bt_amt, b.sgst_pcnt, b.cgst_pcnt, b.igst_pcnt, b.cess_pcnt
                                from st.stock_tran a
                                inner join tx.gst_tax_tran b on a.stock_tran_id = b.gst_tax_tran_id
                                inner join st.material c on a.material_id = c.material_id
                                Inner Join tx.hsn_sc d On b.hsn_sc_code = d.hsn_sc_code
                                Inner Join tx.hsn_sc_rate e On d.hsn_sc_id = e.hsn_sc_id
                                left Join tx.hsn_sc_uom f On e.hsn_sc_uom_id = f.hsn_sc_uom_id
                                left Join tx.ewb_uom g on f.uom_desc = g.uom_desc
                                where a.stock_id = :pdoc_id";
                break;
            case 'INVG':
                $cmdtext = "select a.voucher_id,a.vch_tran_id,a.sl_no,a.hsn_sc_id,a.detailed_desc as material_name,a.order_qty as issued_qty,
                                b.hsn_sc_code,COALESCE(g.uom_code,'NOS') as ewb_uom,b.bt_amt, b.sgst_pcnt, b.cgst_pcnt, b.igst_pcnt, b.cess_pcnt
                                from pub.invoice_task_tran a
                                inner join tx.gst_tax_tran b on a.vch_tran_id = b.gst_tax_tran_id
                                Inner Join tx.hsn_sc d On b.hsn_sc_code = d.hsn_sc_code
                                Inner Join tx.hsn_sc_rate e On d.hsn_sc_id = e.hsn_sc_id
                                left Join tx.hsn_sc_uom f On e.hsn_sc_uom_id = f.hsn_sc_uom_id
                                left Join tx.ewb_uom g on f.uom_desc = g.uom_desc
                                where a.voucher_id = :pdoc_id";
                break;
        }
        if ($cmdtext != '') {
            $cmd = new \app\cwf\vsla\data\SqlCommand();
            $cmd->setCommandText($cmdtext);
            $cmd->addParam('pdoc_id', $doc_id);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmd);
            if (count($dt->Rows()) > 0) {
                $mainHsnCode = intval($dt->Rows()[0]['hsn_sc_code']);
                $item = [];
                foreach ($dt->Rows() as $rw) {
                    $item['itemNo'] = intval($rw['sl_no']);
                    $item['productName'] = $rw['material_name'];
                    $item['productDesc'] = $rw['material_name'];
                    $item['hsnCode'] = intval($rw['hsn_sc_code']);
                    $item['quantity'] = intval($rw['issued_qty']);
                    $item['qtyUnit'] = $rw['ewb_uom'];
                    $item['taxableAmount'] = floatval($rw['bt_amt']);
                    $item['sgstRate'] = floatval($rw['sgst_pcnt']);
                    $item['cgstRate'] = floatval($rw['cgst_pcnt']);
                    $item['igstRate'] = floatval($rw['igst_pcnt']);
                    $item['cessRate'] = floatval($rw['cess_pcnt']);
                    $items[] = $item;
                }
            }
        }
        return ['items' => $items, 'mainHsn' => $mainHsnCode];
    }

    private static function validateRec($row) {
        $brules = [];

        if ($row['fromgstin'] == '') {
            $brules[] = 'Invalid or missing Branch GSTIN';
        }
        if ($row['fromaddr'] == '') {
            $brules[] = 'Invalid or missing Branch address';
        }
        if ($row['fromcity'] == '') {
            $brules[] = 'Invalid or missing Branch city';
        }
        if ($row['frompin'] == '') {
            $brules[] = 'Invalid or missing Branch pin';
        }
        if ($row['fromstate'] == '') {
            $brules[] = 'Invalid or missing Branch GSTIN state';
        }
        if ($row['togstin'] == '') {
            $brules[] = 'Invalid or missing Customer GSTIN';
        }
        if ($row['toaddr1'] == '' && $row['toaddr2'] == '') {
            $brules[] = 'Invalid or missing Customer address';
        }
        if ($row['tocity1'] == '' && $row['tocity2'] == '') {
            $brules[] = 'Invalid or missing Customer city';
        }
        if ($row['topin1'] == '' && $row['topin2'] == '') {
            $brules[] = 'Invalid or missing Customer pin';
        }
        if ($row['tostate'] == '') {
            $brules[] = 'Invalid or missing Customer GSTIN state';
        }
        return $brules;
    }

}

<?php

namespace app\core\ac\controllers;

use YaLinqo\Enumerable;
use app\cwf\vsla\base\WebFormController;

class FormController extends WebFormController {

    public function actionGldistribution($table_name, $voucher_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select a.*, b.account_head, c.branch_code from ac.sp_gl_post_data(:ptable_name, :pvoucher_id) a
                            inner join ac.account_head b on a.account_id= b.account_id
                            inner join sys.branch c on a.branch_id = c.branch_id ');
        $cmm->addParam('ptable_name', $table_name);
        $cmm->addParam('pvoucher_id', $voucher_id);
        $dtGL = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = array();
        $result['gl_distribution'] = $dtGL;
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionDetailrequired($account_id) {
        $result = \app\core\ac\subHeadAlloc\SubHeadAllocHelper::IsDetailReqd($account_id);
        return json_encode($result);
    }

    public function actionRefalloc($voucher_id, $doc_date, $account_id, $dc, $branch_id) {

        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * 
            from ac.fn_ref_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc)
            Order By doc_date, voucher_id');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', $branch_id);
        $cmm->addParam('paccount_id', $account_id);
        $cmm->addParam('pto_date', $doc_date);
        $cmm->addParam('pvoucher_id', $voucher_id);
        $cmm->addParam('pdc', $dc);
        $dtRLBalance = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result = array();
        $result['rl_balance'] = $dtRLBalance;
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionGetRc94($gst_state_id, $doc_date, $voucher_id) {

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

        $option = new \app\core\tx\gstr2\Gstr2ProviderOption();
        $option->gst_state_id = \app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gst_state_id'];

        $from_date = date("Y-m-01", strtotime($doc_date));
        $to_date = date("Y-m-t", strtotime($doc_date));

        $option->ret_period_from = $from_date;
        $option->ret_period_to = $to_date;

        $dt = new \app\cwf\vsla\data\DataTable();
        $dt = $provider->getB2C94_summary($option);
        $dt->addColumn('hsn_sc_id', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, -1);
        $dt->addColumn('hsn_sc_desc', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_STRING, '');
        $dt->addColumn('account_id', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, -1);
        $dt->addColumn('account_head', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_STRING, '');
        $dt->addColumn('branch_id', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, -1);
        $dt->addColumn('branch_name', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_STRING, '');
        $dt->addColumn('apply_itc', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_BOOL, false);

        // Fetch HSN Sc ID
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("With b2c_94_tran
            As
            (	Select x.gst_tax_tran_id
                    From jsonb_to_recordset(:pcurrent_rec::JsonB) as x(gst_tax_tran_id varchar)
            )
            Select a.hsn_sc_id, b.gst_tax_tran_id, (a.hsn_sc_code || '-' || a.hsn_sc_desc) as hsn_sc_desc, b.apply_itc
            From tx.hsn_sc a 
            inner join tx.gst_tax_tran b on a.hsn_sc_code = b.hsn_sc_code
            Inner Join b2c_94_tran c On b.gst_tax_tran_id = c.gst_tax_tran_id
            Where ((b.gst_tax_tran_id not in (select y.ref_tran_id from ac.si_tran y)) 
                       OR (b.gst_tax_tran_id in (select y.ref_tran_id from ac.si_tran y where y.voucher_id = :pvoucher_id)))");
        $current_alloc = $dt->select(['gst_tax_tran_id']);
        $cmm->addParam('pcurrent_rec', json_encode($current_alloc));
        $cmm->addParam('pvoucher_id', $voucher_id);
        $dthsn = \app\cwf\vsla\data\DataConnect::getData($cmm);

        foreach ($dt->Rows() as &$dr) {
            $drhsn = Enumerable::from($dthsn->Rows())->where('$a==>$a["gst_tax_tran_id"] == "' . $dr['gst_tax_tran_id'] . '"')->toList();
            if (count($drhsn) == 1) {
                $dr['hsn_sc_id'] = $drhsn[0]['hsn_sc_id'];
                $dr['hsn_sc_desc'] = $drhsn[0]['hsn_sc_desc'];
                $dr['apply_itc'] = $drhsn[0]['apply_itc'];
            }
        }

        for ($i = count($dt->Rows()) - 1; $i >= 0; $i--) {
            if ($dt->Rows()[$i]['hsn_sc_id'] == -1) {
                $dt->removeRow($i);
            }
        }

        // Fetch Account ID and Branch ID
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmText = "With doc_tran
            As
            (	Select x.gst_tax_tran_id
                    From jsonb_to_recordset(:pcurrent_rec::JsonB) as x(gst_tax_tran_id varchar)
            )
            Select a.branch_id, b.account_id, c.gst_tax_tran_id, d.account_head, e.branch_name
            From ac.vch_control a 
            inner join ac.vch_tran b on a.voucher_id = b.voucher_id
            Inner Join doc_tran c On b.vch_tran_id = c.gst_tax_tran_id
            inner join ac.account_head d on b.account_id = d.account_id
            inner join sys.branch e on b.branch_id = e.branch_id
            Where b.branch_id = :pbranch_id 
                    And (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = :pgst_state_id
            union All
            Select a.branch_id, b.account_id, c.gst_tax_tran_id, d.account_head, e.branch_name
            From ap.bill_control a 
            inner join ap.bill_tran b on a.bill_id = b.bill_id
            Inner Join doc_tran c On b.bill_tran_id = c.gst_tax_tran_id
            inner join ac.account_head d on b.account_id = d.account_id
            inner join sys.branch e on a.branch_id = e.branch_id
            Where a.branch_id = :pbranch_id 
                    And (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = :pgst_state_id  
            Union All
            Select a.branch_id, (Select cast(value as bigint) from sys.settings where key='bb_purchase_account'), c.gst_tax_tran_id, d.account_head, e.branch_name
            From st.stock_control a 
            inner join st.inv_bb b on a.stock_id = b.inv_id
            Inner Join doc_tran c On b.inv_bb_id = c.gst_tax_tran_id
            inner join ac.account_head d on (Select cast(value as bigint) from sys.settings where key='bb_purchase_account') = d.account_id
            inner join sys.branch e on a.branch_id = e.branch_id
            Where a.branch_id = :pbranch_id 
                    And (a.annex_info->'gst_output_info'->>'customer_state_id')::bigint = :pgst_state_id
            Union All
            Select a.branch_id, (Select cast(value as bigint) from sys.settings where key='bb_purchase_account'), c.gst_tax_tran_id, d.account_head, e.branch_name
            From pos.inv_control a 
            inner join pos.inv_bb b on a.inv_id = b.inv_id
            Inner Join doc_tran c On b.inv_bb_id = c.gst_tax_tran_id
            inner join ac.account_head d on (Select cast(value as bigint) from sys.settings where key='bb_purchase_account') = d.account_id
            inner join sys.branch e on a.branch_id = e.branch_id
            Where a.branch_id = :pbranch_id 
                    And (a.annex_info->'gst_output_info'->>'cust_state_id')::bigint = :pgst_state_id";

        $cmm1 = new \app\cwf\vsla\data\SqlCommand();
        $cmm1->setCommandText("SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'abp_control'");
        $dt1 = \app\cwf\vsla\data\DataConnect::getData($cmm1);
        if (count($dt1->Rows()) > 0) {
            $cmmText .= " union All
            Select a.branch_id, b.account_id, c.gst_tax_tran_id, d.account_head, e.branch_name
            From pub.abp_control a 
            inner join pub.abp_tran b on a.voucher_id = b.voucher_id
            Inner Join doc_tran c On b.vch_tran_id = c.gst_tax_tran_id
            inner join ac.account_head d on b.account_id = d.account_id
            inner join sys.branch e on a.branch_id = e.branch_id
            Where a.branch_id = :pbranch_id 
                    And (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = :pgst_state_id";
        }
        $cmm->setCommandText($cmmText);
        $current_alloc = $dt->select(['gst_tax_tran_id']);
        $cmm->addParam('pcurrent_rec', json_encode($current_alloc));
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('pgst_state_id', $gst_state_id);
        $dtTran = \app\cwf\vsla\data\DataConnect::getData($cmm);

        foreach ($dt->Rows() as &$dr) {
            $drtran = Enumerable::from($dtTran->Rows())->where('$a==>$a["gst_tax_tran_id"] == "' . $dr['gst_tax_tran_id'] . '"')->toList();
            if (count($drtran) == 1) {
                $dr['account_head'] = $drtran[0]['account_head'];
                $dr['account_id'] = $drtran[0]['account_id'];
                $dr['branch_id'] = $drtran[0]['branch_id'];
                $dr['branch_name'] = $drtran[0]['branch_name'];
            }
        }

        for ($i = count($dt->Rows()) - 1; $i >= 0; $i--) {
            if ($dt->Rows()[$i]['branch_id'] == -1) {
                $dt->removeRow($i);
            }
        }

        $result = array();
        $result['b2c_94'] = $dt;
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionGetRc93($gst_state_id, $doc_date, $voucher_id) {

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

        $option = new \app\core\tx\gstr2\Gstr2ProviderOption();
        $option->gst_state_id = \app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gst_state_id'];

        $from_date = date("Y-m-01", strtotime($doc_date));
        $to_date = date("Y-m-t", strtotime($doc_date));

        $option->ret_period_from = $from_date;
        $option->ret_period_to = $to_date;

        $dt = new \app\cwf\vsla\data\DataTable();
        $dt = $provider->getB2C93_summary($option);
        $dt->addColumn('hsn_sc_id', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, -1);
        $dt->addColumn('hsn_sc_desc', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_STRING, '');
        $dt->addColumn('account_id', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, -1);
        $dt->addColumn('account_head', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_STRING, '');
        $dt->addColumn('branch_id', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, -1);
        $dt->addColumn('branch_name', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_STRING, '');
        $dt->addColumn('apply_itc', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_BOOL, false);

        // Fetch HSN Sc ID
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("With b2c_94_tran
            As
            (	Select x.gst_tax_tran_id
                    From jsonb_to_recordset(:pcurrent_rec::JsonB) as x(gst_tax_tran_id varchar)
            )
            Select a.hsn_sc_id, b.gst_tax_tran_id, (a.hsn_sc_code || '-' || a.hsn_sc_desc) as hsn_sc_desc, b.apply_itc
            From tx.hsn_sc a 
            inner join tx.gst_tax_tran b on a.hsn_sc_code = b.hsn_sc_code
            Inner Join b2c_94_tran c On b.gst_tax_tran_id = c.gst_tax_tran_id
            Where ((b.gst_tax_tran_id not in (select y.ref_tran_id from ac.si_tran y)) 
                       OR (b.gst_tax_tran_id in (select y.ref_tran_id from ac.si_tran y where y.voucher_id = :pvoucher_id)))");
        $current_alloc = $dt->select(['gst_tax_tran_id']);
        $cmm->addParam('pcurrent_rec', json_encode($current_alloc));
        $cmm->addParam('pvoucher_id', $voucher_id);
        $dthsn = \app\cwf\vsla\data\DataConnect::getData($cmm);

        foreach ($dt->Rows() as &$dr) {
            $drhsn = Enumerable::from($dthsn->Rows())->where('$a==>$a["gst_tax_tran_id"] == "' . $dr['gst_tax_tran_id'] . '"')->toList();
            if (count($drhsn) == 1) {
                $dr['hsn_sc_id'] = $drhsn[0]['hsn_sc_id'];
                $dr['hsn_sc_desc'] = $drhsn[0]['hsn_sc_desc'];
                $dr['apply_itc'] = $drhsn[0]['apply_itc'];
            }
        }

        for ($i = count($dt->Rows()) - 1; $i >= 0; $i--) {
            if ($dt->Rows()[$i]['hsn_sc_id'] == -1) {
                $dt->removeRow($i);
            }
        }

        // Fetch Account ID and Branch ID
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmText = "With doc_tran
            As
            (	Select x.gst_tax_tran_id
                    From jsonb_to_recordset(:pcurrent_rec::JsonB) as x(gst_tax_tran_id varchar)
            )
            Select a.branch_id, b.account_id, c.gst_tax_tran_id, d.account_head, e.branch_name
            From ac.vch_control a 
            inner join ac.vch_tran b on a.voucher_id = b.voucher_id
            Inner Join doc_tran c On b.vch_tran_id = c.gst_tax_tran_id
            inner join ac.account_head d on b.account_id = d.account_id
            inner join sys.branch e on b.branch_id = e.branch_id
            Where b.branch_id = :pbranch_id 
                    And (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = :pgst_state_id
                    And length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2
            union All
            Select a.branch_id, b.account_id, c.gst_tax_tran_id, d.account_head, e.branch_name
            From ap.bill_control a 
            inner join ap.bill_tran b on a.bill_id = b.bill_id
            Inner Join doc_tran c On b.bill_tran_id = c.gst_tax_tran_id
            inner join ac.account_head d on b.account_id = d.account_id
            inner join sys.branch e on a.branch_id = e.branch_id
            Where a.branch_id = :pbranch_id 
                    And (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = :pgst_state_id
                    And length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2
            Union All
            Select a.branch_id, (Select cast(value as bigint) from sys.settings where key='bb_purchase_account'), c.gst_tax_tran_id, d.account_head, e.branch_name
            From st.stock_control a 
            inner join st.inv_bb b on a.stock_id = b.inv_id
            Inner Join doc_tran c On b.inv_bb_id = c.gst_tax_tran_id
            inner join ac.account_head d on (Select cast(value as bigint) from sys.settings where key='bb_purchase_account') = d.account_id
            inner join sys.branch e on a.branch_id = e.branch_id
            Where a.branch_id = :pbranch_id 
                    And (a.annex_info->'gst_output_info'->>'customer_state_id')::bigint = :pgst_state_id
                    And length((a.annex_info->'gst_output_info'->>'customer_gstin')::varchar) = 2
            Union All
            Select a.branch_id, (Select cast(value as bigint) from sys.settings where key='bb_purchase_account'), c.gst_tax_tran_id, d.account_head, e.branch_name
            From pos.inv_control a 
            inner join pos.inv_bb b on a.inv_id = b.inv_id
            Inner Join doc_tran c On b.inv_bb_id = c.gst_tax_tran_id
            inner join ac.account_head d on (Select cast(value as bigint) from sys.settings where key='bb_purchase_account') = d.account_id
            inner join sys.branch e on a.branch_id = e.branch_id
            Where a.branch_id = :pbranch_id 
                    And (a.annex_info->'gst_output_info'->>'cust_state_id')::bigint = :pgst_state_id
                    And length((a.annex_info->'gst_output_info'->>'cust_gstin')::varchar) = 2";

        $cmm1 = new \app\cwf\vsla\data\SqlCommand();
        $cmm1->setCommandText("SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'abp_control'");
        $dt1 = \app\cwf\vsla\data\DataConnect::getData($cmm1);
        if (count($dt1->Rows()) > 0) {
            $cmmText .= " union All
            Select a.branch_id, b.account_id, c.gst_tax_tran_id, d.account_head, e.branch_name
            From pub.abp_control a 
            inner join pub.abp_tran b on a.voucher_id = b.voucher_id
            Inner Join doc_tran c On b.vch_tran_id = c.gst_tax_tran_id
            inner join ac.account_head d on b.account_id = d.account_id
            inner join sys.branch e on a.branch_id = e.branch_id
            Where a.branch_id = :pbranch_id 
                    And (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = :pgst_state_id
                    And length((a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar) = 2";
        }
        $cmm->setCommandText($cmmText);
        $current_alloc = $dt->select(['gst_tax_tran_id']);
        $cmm->addParam('pcurrent_rec', json_encode($current_alloc));
        $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
        $cmm->addParam('pgst_state_id', $gst_state_id);
        $dtTran = \app\cwf\vsla\data\DataConnect::getData($cmm);

        foreach ($dt->Rows() as &$dr) {
            $drtran = Enumerable::from($dtTran->Rows())->where('$a==>$a["gst_tax_tran_id"] == "' . $dr['gst_tax_tran_id'] . '"')->toList();
            if (count($drtran) == 1) {
                $dr['account_head'] = $drtran[0]['account_head'];
                $dr['account_id'] = $drtran[0]['account_id'];
                $dr['branch_id'] = $drtran[0]['branch_id'];
                $dr['branch_name'] = $drtran[0]['branch_name'];
            }
        }

        for ($i = count($dt->Rows()) - 1; $i >= 0; $i--) {
            if ($dt->Rows()[$i]['branch_id'] == -1) {
                $dt->removeRow($i);
            }
        }

        $result = array();
        $result['b2c_94'] = $dt;
        $result['status'] = 'ok';
        return json_encode($result);
    }

    public function actionPdc($rptOptions = "") {
        $viewOption = new \app\cwf\vsla\render\FormViewOptions();
        $viewOption->callingModulePath = '';
        $viewOption->xmlViewPath = '@app/core/ac/utilities/pdc/PdcView.xml';
        $design = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($viewOption->callingModulePath, $viewOption->xmlViewPath);
        $viewForRender = \app\cwf\vsla\render\ViewManager::getCompiledFormView($viewOption, $design);
        return $this->renderPartial('@app/core/ac/utilities/pdc/PdcView.php', ['viewForRender' => $viewForRender, 'xmlPath' => $viewOption->xmlViewPath, 'rptOptions' => $rptOptions]);
    }

    public function actionGetPdcData() {
        $rptParams = \yii::$app->request->getBodyParams();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmText = 'select * from ac.fn_pdc_report(:pcompany_id, :pbranch_id, :pdoc_type, :pto_date)';
        $cmm->setCommandText($cmmText);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID());
        $cmm->addParam('pbranch_id', $rptParams['pbranch_id']);
        $cmm->addParam('pdoc_type', $rptParams['pdoc_type']);
        $cmm->addParam('pto_date', \app\cwf\vsla\utils\FormatHelper::GetDBDate($rptParams['pto_date']));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $columns = [
            ['data' => 'branch_name', 'type' => 'string', 'title' => 'Branch'],
            ['data' => 'doc_type_desc', 'type' => 'string', 'title' => 'Document'],
            ['data' => 'doc_date', 'type' => 'string', 'title' => 'Doc Date'],
            ['data' => 'voucher_id', 'type' => 'string', 'title' => 'Voucher #'],
            ['data' => 'vch_caption', 'type' => 'string', 'title' => 'Account'],
            ['data' => 'amt', 'type' => 'num', 'title' => 'Amount', 'className' => 'datatable-col-right'],
            ['data' => 'cheque_number', 'type' => 'string', 'title' => 'Cheque #'],
            ['data' => 'cheque_date', 'type' => 'string', 'title' => 'Cheque Date']
        ];
        foreach ($dt->Rows() as &$dr) {
            $dr['amt'] = \app\cwf\vsla\utils\FormatHelper::FormatAmt($dr['amt']);
            $dr['doc_date'] = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($dr['doc_date']);
            $dr['cheque_date'] = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($dr['cheque_date']);
        }

        $result = [
            'columns' => $columns,
            'data' => $dt->Rows()
        ];
        return json_encode($result);
    }

    public function actionRefLedgerAlloc($voucher_id, $doc_date, $account_id, $dc, $branch_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * 
            from ac.fn_ref_ledger_balance(:pcompany_id, :pbranch_id, :paccount_id, :pto_date, :pvoucher_id, :pdc)
            Order By doc_date, voucher_id');
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', $branch_id);
        $cmm->addParam('paccount_id', $account_id);
        $cmm->addParam('pto_date', $doc_date);
        $cmm->addParam('pvoucher_id', $voucher_id);
        $cmm->addParam('pdc', $dc);
        $dtRefBal = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $cmmAcc = new \app\cwf\vsla\data\SqlCommand();
        $cmmAcc->setCommandText("Select a.account_head
                    From ac.account_head a 
                    Where a.account_id = :paccount_id");
        $cmmAcc->addParam("paccount_id", $account_id);
        $dtAcc = \app\cwf\vsla\data\DataConnect::getData($cmmAcc);

        $result = array();
        if (count($dtAcc->Rows()) > 0) {
            $result['acc_head'] = $dtAcc->Rows()[0]['account_head'];
        } else {
            $result['acc_head'] = 'Unknown';
        }
        $result['ref_bal'] = $dtRefBal;
        $result['status'] = 'ok';
        return json_encode($result);
    }

}

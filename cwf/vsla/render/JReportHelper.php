<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\render;

use app\cwf\vsla\data\DataConnect;

/**
 * Description of JReportHelper
 *
 * @author girish
 */
class JReportHelper {

    const OUTPUT_HTML = 'html_file';
    const OUTPUT_PDF = 'pdf_file';
    const OUTPUT_DOC = 'ms_doc_file';
    const OUTPUT_XLS = 'ms_xls_file';
    const OUTPUT_ODT = 'open_doc_file';
    const OUTPUT_ODS = 'open_calc_file';
    const OUTPUT_HTML_SINGLE_FILE = 'html_single_file';

    private $config = [];
    public static $reportHost = 'http://localhost:8080';

    public function __construct($config = []) {
        $this->config = array_merge($config, [
            'baseUrl' => \Yii::$app->getUrlManager()->getBaseUrl(),
            'sessionID' => \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID(),
            'renderTo' => 'web',
        ]);
    }

    /*
     * 
     */

    public function renderReport(Array $params, $outputType) {
        // DataConnect::getCn is called to ensure that a Company db connection is available 
        // before processing starts
        $cn = DataConnect::getCn(DataConnect::COMPANY_DB);
        // Create an Xml Request for JReport Server
        $requestXml = new \SimpleXMLElement('<rptOptions></rptOptions>');
        $rptParams = $requestXml->addChild("rptParams");

        // Set the Server Values
        $configReader = new \app\cwf\vsla\utils\ConfigReader();
        $requestXml->addChild('dbServer', $configReader->dbInfo->dbServer);
        $requestXml->addChild('dbName', DataConnect::getCompanyDB());
        $requestXml->addChild('dbUser', $configReader->dbInfo->suName);
        $requestXml->addChild('dbPass', htmlspecialchars($configReader->dbInfo->suPass));
        $requestXml->addChild('dbPort', $configReader->dbInfo->port);

        // Set Output Type
        $requestXml->addChild('outputType', $outputType);

        $requestXml->addChild('serverUrl', $this->config['baseUrl']);
        $requestXml->addChild('sessionID', $this->config['sessionID']);
        if (array_key_exists('reqtime', $params)) {
            $requestXml->addChild('viewerID', $params['reqtime']);
            $viewer_id = $params['reqtime'];
        } else {
            $viewer_id = time();
            $requestXml->addChild('viewerID', $viewer_id);
        }

        // loop and get the params
        if (array_key_exists('_csrf', $params)) {
            $pramXml = $rptParams->addChild('param', $params['_csrf']);
            $pramXml->addAttribute('name', '_csrf');
        }

        // Set rptOptions
        $rptOptions = new RptOption();
        if (array_key_exists('xmlPath', $params)) {
            $cwFramework = simplexml_load_file(\yii::getAlias($params['xmlPath']));
            $viewX = $cwFramework->reportView;

            $rptOptions->rptPath = $viewX['rptPath'];
            $rptOptions->rptName = $viewX['rptName'];
            $rptOptions->rptParams = array();
            $rptOptions->brokenRules = array();
            $branch_id = -1;
            $rptCompanyInfoPrefix = "";
            // Add Data export options
            $rptOptions->rptParams['pcwf_data_only'] = FALSE;
            if(array_key_exists('pcwf_data_only', $params)){
                $rptOptions->rptParams['pcwf_data_only'] = $params['pcwf_data_only'];
            }
            // Add params from report xml to optionxml
            foreach ($viewX->controlSection->dataBinding->children() as $name => $fld) {
                if ($name === 'param') {
                    if ($fld->session) {
                        $rptOptions->rptParams[(string) $fld['id']] = \app\cwf\vsla\security\SessionManager::getSessionVariable((string) $fld->session);
                    }
                    if ($fld->text) {
                        $rptOptions->rptParams[(string) $fld['id']] = (string) $fld->text;
                    }
                    if ($fld->dateFormat) {
                        $rptOptions->rptParams[(string) $fld['id']] = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForReport();
                    }
                    if ($fld->numberFormat) {
                        $rptOptions->rptParams[(string) $fld['id']] = \app\cwf\vsla\utils\FormatHelper::GetNumberFormat();
                    }
                    if ($fld->currentDate) {
                        $rptOptions->rptParams[(string) $fld['id']] = date("Y-m-d", time());
                    }
                }
                if ($name === 'field') {
                    if (array_key_exists((string) $fld['id'], $params)) {
                        if ($fld['type'] == 'date') {
                            // This converts to date syntax for postgreSQL
                            $rptOptions->rptParams[(string) $fld['id']] = \app\cwf\vsla\utils\FormatHelper::GetDBDate($params[(string) $fld['id']]);
                        } elseif ($fld['type'] == 'array') {
                            // This converts to array syntax for postgreSQL
                            $rptOptions->rptParams[(string)$fld['id']] = '{'.$params[(string) $fld['id']].'}';
                        } else {
                            $rptOptions->rptParams[(string) $fld['id']] = $params[(string) $fld['id']];
                        }
                    } else {
                        if ($fld->DefaultValue != NULL) {
                            $rptOptions->rptParams[(string) $fld['id']] = $fld->attributes()['defaultValue'];
                        }
                    }
                }
                if ($fld['id'] == 'pbranch_id') {
                    $branch_id = $rptOptions->rptParams['pbranch_id'];
                }
                if ($name === 'rptCompanyInfoPrefix') {
                    $rptCompanyInfoPrefix = (string) $fld;
                }
            }

            // get physical path for the corresponding ReportEventHandler
            $fileName = \yii::getAlias(str_replace('.xml', '.php', $params['xmlPath']));
            if (file_exists($fileName)) { // Since it is optional, verify if it exists
                // get namespace
                $reportClass = str_replace('.xml', '', $params['xmlPath']);
                $reportClass = str_replace("/", "\\", $reportClass);
                $reportClass = str_replace('@', '\\', $reportClass);
                $reportClassInstance = new $reportClass();
                $reportClassInstance->initialise((string) $viewX['id']);
                $reportClassInstance->onRequestReport($rptOptions);
                if (count($rptOptions->brokenRules) > 0) {

                    $rpterr = '<div style="margin-top:15px;">
                           <span class="glyphicon glyphicon-exclamation-sign" style="margin-right:5px;color:#a94442;"></span>
                           <strong  style="color:#a94442;">Missing report options</strong>
                           <div style="margin: 10px 0 0 30px;">';
                    for ($i = 0; $i < count($rptOptions->brokenRules); $i++) {
                        $rpterr .= '<li>' . $rptOptions->brokenRules[$i] . '</li>';
                    }
                    $rpterr .= '</div></div>';
                    return ['status' => 'FAILED', 'msg' => $rpterr];
                }
            }
            if (array_key_exists('pbranch_id', $rptOptions->rptParams)) {
                $branch_id = $rptOptions->rptParams['pbranch_id'];
            }

            self::getPresetValues($branch_id, $rptOptions);
            self::getCustomCodePath($rptOptions);
            if ($rptCompanyInfoPrefix != '') {
                self::getCompanyDefaults($rptCompanyInfoPrefix, $rptOptions);
            }
        }

        // Add params from report xml to optionxml
        $requestXml->addChild('rptPath', $rptOptions->rptPath);
        $requestXml->addChild('rptName', $rptOptions->rptName);
        foreach ($rptOptions->rptParams as $name => $param) {
            $pramXml = $rptParams->addChild('param');
            self::addCData($pramXml, $param);
            $pramXml->addAttribute('name', $name);
        }
        // Set Debug Info
        if (YII_ENV_DEV) {
            $pramXml = $rptParams->addChild('param', 'true');
            $pramXml->addAttribute('name', 'debug-report');
        } else {
            $pramXml = $rptParams->addChild('param', 'false');
            $pramXml->addAttribute('name', 'debug-report');
        }

        // Set the print settings
        $printSettings = $requestXml->addChild("printSettings");
        if ($outputType == 'html_file') {
            $printParam = $printSettings->addChild('param', 'true');
            $printParam->addAttribute('name', 'html_no_margin');

            $printParam = $printSettings->addChild('param', 'true');
            $printParam->addAttribute('name', 'html_in_point');
        }
        
        if (array_key_exists('max_pages', $params)) {
            $printParam = $printSettings->addChild('param', $params['max_pages']);
            $printParam->addAttribute('name', 'max_pages');
        } else {
            $printParam = $printSettings->addChild('param', 0); // Unlimited pages
            $printParam->addAttribute('name', 'max_pages');
        }

        $content = (string) $requestXml->asXML();
        $client = new \GuzzleHttp\Client();

        try {
            $resp = $client->post(self::$reportHost . '/CoreJSReportServer/RenderReport?reqtime=' . time(), ['body' => $content]);
            $result = $resp->getBody();
            if ($outputType == self::OUTPUT_HTML) {
                $rptResult = $this->modifyHtmlRef($result);
                \yii::$app->cache->set('rpt-' . $viewer_id, serialize($rptResult)); //cache this for future printing
                return ['status' => 'OK', 'msg' => '', 'result' => $rptResult];
            } else {
                $rptResult = $this->modifyExportRef($result);
                return ['status' => 'OK', 'msg' => '', 'result' => $rptResult];
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $msg = $e->getMessage();
            if ($e->hasResponse()) {
                $msg .= "\n" . $e->getResponse()->getBody();
            }
            throw new \Exception($msg);
        } catch (\Exception $ex) {
            $msg = $ex->getMessage();
            throw new \Exception($msg);
        }
    }

    public static function getPresetValues($branch_id, $rptOption) {
        if ($branch_id == '') {
            $branch_id = '-1';
        }
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('SELECT * FROM sys.fn_report_defaults(:pbranch_id, :pcompany_id)');
        $cmm->addParam('pbranch_id', $branch_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID());
        $dtPreset = \app\cwf\vsla\data\DataConnect::getData($cmm);

        if (count($dtPreset->Rows()) == 0) {
            throw new \Exception("Report Default parameters were not fetched from database. Failed to generate report.");
        } else {
            foreach ($dtPreset->getColumns() as $col) {
                switch ($col->columnName) {
                    case "company_name":
                        $rptOption->rptParams['pcwf_company_name'] = $dtPreset->Rows()[0][$col->columnName];
                        break;
                    case "branch_name":
                        $rptOption->rptParams['pcwf_branch_name'] = $dtPreset->Rows()[0][$col->columnName];
                        break;
                    case "branch_address":
                        $rptOption->rptParams['pcwf_branch_address'] = $dtPreset->Rows()[0][$col->columnName];
                        break;
                    case "currency_displayed":
                        $rptOption->rptParams['pcwf_txn_ccy'] = $dtPreset->Rows()[0][$col->columnName];
                        break;
                    case "company_logo":
                        $rptOption->rptParams['pcwf_company_logo'] = \yii::$app->basePath . $dtPreset->Rows()[0][$col->columnName];
                        break;
                    case "header_template":
                        $rptOption->rptParams['pcwf_header_template'] = $dtPreset->Rows()[0][$col->columnName];
                        break;
                    default:
                        break;
                }
            }
        }
        // Add other default parameters
        $rptOption->rptParams['pcwf_date_format'] = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForReport();
        $rptOption->rptParams['pcwf_ccy_format'] = \app\cwf\vsla\utils\FormatHelper::GetNumberFormat();
        $rptOption->rptParams['pcwf_amt_format'] = \app\cwf\vsla\utils\FormatHelper::GetAmtFormat();
        $rptOption->rptParams['pcwf_qty_format'] = \app\cwf\vsla\utils\FormatHelper::GetQtyFormat();
        $rptOption->rptParams['pcwf_rate_format'] = \app\cwf\vsla\utils\FormatHelper::GetRateFormat();
        $rptOption->rptParams['pcwf_fc_rate_format'] = \app\cwf\vsla\utils\FormatHelper::GetFCRateFormat();
        if (\app\cwf\vsla\security\SessionManager::getCCYSystem() == 'l') {
            $rptOption->rptParams['pcwf_locale'] = "en-in";
        } else {
            $rptOption->rptParams['pcwf_locale'] = "en-us";
        }
        $rptOption->rptParams['pcwf_base_path'] = \yii::$app->basePath;

        // Add Track Report And CoreERP Version
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * From sys.fn_track_report(:preport_path)');
        $cmm->addParam('preport_path', $rptOption->rptPath . '/' . $rptOption->rptName . '.jrxml');
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        if (count($dt->Rows()) == 1) {
            $rptOption->rptParams['pcwf_coreerp_ver'] = 'CoreERP Ver ' . \yii::$app->params['coreerp-ver'] . ' [' . $dt->Rows()[0]['fn_track_report'] . ']';
        } else {
            $rptOption->rptParams['pcwf_coreerp_ver'] = 'CoreERP Ver ' . \yii::$app->params['coreerp-ver'];
        }
    }

    public static function getCustomCodePath(RptOption $rptOption) {
        // resolve custom code path
        if (array_key_exists('customCode', \yii::$app->params['cwf_config'])) {
            if (substr(\yii::$app->params['cwf_config']['customCode']['path'], 0, 1) == '/') {
                $customCodePath = \yii::$app->params['cwf_config']['customCode']['path'] . DIRECTORY_SEPARATOR . 'C' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID();
            } else {
                $customCodePath = DIRECTORY_SEPARATOR . \yii::$app->params['cwf_config']['customCode']['path'] . DIRECTORY_SEPARATOR . 'C' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID();
            }
        } else {
            $customCodePath = DIRECTORY_SEPARATOR . 'customCode' . DIRECTORY_SEPARATOR . 'C' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID();
        }
        $rptOption->rptParams['pcwf_custom_code_path'] = $customCodePath;

        // Get Custom Report information
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * From sys.rpt_option Where rpt_name=:prpt_name and rpt_path=:prpt_path');
        $cmm->addParam('prpt_name', $rptOption->rptName);
        $cmm->addParam('prpt_path', $rptOption->rptPath);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        if (count($dt->Rows()) == 1) {
            if ($dt->Rows()[0]['rpt_replace_path'] != '') {
                // Replace the report path. This should be relative to customCode path
                if (substr($dt->Rows()[0]['rpt_replace_path'], 0, 1) == '/') {
                    $rptOption->rptPath = $customCodePath . $dt->Rows()[0]['rpt_replace_path'];
                } else {
                    $rptOption->rptPath = $customCodePath . DIRECTORY_SEPARATOR . $dt->Rows()[0]['rpt_replace_path'];
                }
            }
            // Template and letterhead logo are mutually exclusive. Both cannot exist together
            if ($dt->Rows()[0]['rpt_header_template'] != '') {
                // use template if template exists
                $rptOption->rptParams['pcwf_header_template'] = $dt->Rows()[0]['rpt_header_template'];
            } else if ($dt->Rows()[0]['rpt_header_image'] != '') {
                // use default custom template and set letterhead logo
                $rptOption->rptParams['pcwf_header_template'] = 'cwf/report-templates/custom-header-template.jrxml';
                // Image path always requires complete path
                if (substr($dt->Rows()[0]['rpt_header_image'], 0, 1) == '/') {
                    $rptOption->rptParams['pcwf_company_logo'] = \yii::$app->basePath . $customCodePath . $dt->Rows()[0]['rpt_header_image'];
                } else {
                    $rptOption->rptParams['pcwf_company_logo'] = \yii::$app->basePath . $customCodePath . DIRECTORY_SEPARATOR . $dt->Rows()[0]['rpt_header_image'];
                }
            }
        }
    }

    public static function getCompanyDefaults($rptCompanyInfoPrefix, $rptOption) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("SELECT * FROM sys.rpt_company_info where key ilike '" . $rptCompanyInfoPrefix . "%' ");
        $dtPreset = \app\cwf\vsla\data\DataConnect::getData($cmm);

        if (count($dtPreset->Rows()) > 0) {
            foreach ($dtPreset->Rows() as $row) {
                $rptOption->rptParams['p' . $row['key']] = $row['value'];
            }
        }
    }

    public static function requestPrint($doc_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = 'Select doc_print_request_id, doc_id, requested_by_user_id from sys.doc_print_request '
                . 'where doc_id = :pdoc_id and requested_by_user_id = :puser_id and closed = false';
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('pdoc_id', $doc_id);
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $dt = DataConnect::getData($cmm);
        if (count($dt->Rows()) == 0) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmmtext = 'Insert into sys.doc_print_request(doc_print_request_id, doc_id, requested_by_user_id) values '
                    . '((select COALESCE(max(doc_print_request_id), 0) + 1 from sys.doc_print_request), :pdoc_id, :puser_id)';
            $cmm->setCommandText($cmmtext);
            $cmm->addParam('pdoc_id', $doc_id);
            $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
            DataConnect::exeCmm($cmm);
        }
    }

    public static function authorizePrintRequest($request_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = 'Update sys.doc_print_request set allowed_by_user_id=:puser_id, last_updated=now() where doc_print_request_id = :preq_id';
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $cmm->addParam('preq_id', $request_id);
        DataConnect::exeCmm($cmm);
    }

    public static function logPrintRequestCompletion($doc_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = 'Update sys.doc_print_request set printed_on=now(), last_updated=now(), closed = true '
                . 'where doc_id = :pdoc_id and requested_by_user_id=:puser_id and printed_on is NULL';
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $cmm->addParam('pdoc_id', $doc_id);
        DataConnect::exeCmm($cmm);
    }

    private function modifyHtmlRef($rptInfojson) {
        $rptInfo = json_decode($rptInfojson);

        $rptResult = new rptResult();
        $rptResult->ReportRenderedPath = $rptInfo->ReportRenderedPath;
        $rptResult->PageCount = $rptInfo->PageCount;
        $rptResult->Orientation = $rptInfo->Orientation == "0" ? "portrait" : "landscape";
        $rptResult->PageHeight = round(floatval($rptInfo->PageHeight) / 72, 2);
        $rptResult->PageWidth = round((floatval($rptInfo->PageWidth) + 2.88) / 72, 2);
        $rptResult->MarginTop = round(floatval($rptInfo->MarginTop) / 72, 2);
        $rptResult->MarginRight = round(floatval($rptInfo->MarginRight) / 72, 2);
        $rptResult->MarginBottom = round(floatval($rptInfo->MarginBottom) / 72, 2);
        $rptResult->MarginLeft = round(floatval($rptInfo->MarginLeft) / 72, 2);



        // Create style for rendering. Right and bottom margins are purposefully ignored
        $rptResult->PageStyle = '<style>
                                    
                                    .form-print-wrapper {
                                        border: 1px solid #d1d8dd;
                                        border-top: none;
                                    }
                                    
                                    .print-preview-wrapper {
                                        padding: 30px 0px 5px;
                                        background-color: #f5f7fa;
                                        height: 100%;
                                        overflow: auto;
                                    }
                                    
                                    @media screen { 
                                        .print-format {
                                            background-color: white;
                                            box-shadow: 0px 0px 9px rgba(0,0,0,0.5);
                                            width: ' . $rptResult->PageWidth . 'in;
                                            height: ' . $rptResult->PageHeight . 'in;
                                            padding-top: ' . $rptResult->MarginTop . 'in;
                                            padding-left: ' . $rptResult->MarginLeft . 'in;
                                            padding-right: ' . $rptResult->MarginRight . 'in;
                                            margin: auto;
                                            margin-bottom: 20px;
                                            -webkit-print-color-adjust:exact;
                                            box-sizing: border-box;
                                        }
                                    }
                                    
                                    @media print {
                                        @page {
                                            size: ' . $rptResult->Orientation . ';
                                        }
                                        .print-format {
                                            width: ' . $rptResult->PageWidth . 'in;
                                            height: ' . $rptResult->PageHeight . 'in;
                                            padding-top: ' . $rptResult->MarginTop . 'in;
                                            padding-left: ' . $rptResult->MarginLeft . 'in;
                                            padding-right: ' . $rptResult->MarginRight . 'in;
                                            orientation: ' . $rptResult->Orientation . '; 
                                            margin-bottom: 20px;                                                
                                            -webkit-print-color-adjust:exact;
                                            box-sizing: border-box;
                                        }
                                    }
                                </style>';



        $pageCount = intval($rptInfo->PageCount);
        if ($this->config['renderTo'] == 'web') {
            // Outputs the http scheme path
            $basePath = \Yii::$app->getUrlManager()->hostInfo . \Yii::$app->getUrlManager()->getBaseUrl() . $rptInfo->ReportRenderedPath;
        } else {
            // Outputs the physical base path
            $basePath = \Yii::$app->getBasePath() . $rptInfo->ReportRenderedPath;
        }
        for ($pageIndex = 0; $pageIndex <= $pageCount - 1; $pageIndex++) {
            $rptResult->Data['Page' . $pageIndex] = $basePath . "/page_" . ((string) $pageIndex) . ".html";
        }
        return $rptResult;
    }

    private function modifyExportRef($rptInfojson) {
        $rptInfo = json_decode($rptInfojson);
        $rptResult = new rptResult();

        if ($this->config['renderTo'] == 'web') {
            // Outputs the http scheme path
            $rptResult->ReportRenderedPath = \Yii::$app->getUrlManager()->hostInfo . \Yii::$app->getUrlManager()->getBaseUrl() . $rptInfo->ReportRenderedPath;
        } else {
            // Outputs the physical base path
            $rptResult->ReportRenderedPath = \Yii::$app->getBasePath() . $rptInfo->ReportRenderedPath;
        }
        return $rptResult;
    }

    private static function addCData($pnode, $cdata_text) {
        $node = dom_import_simplexml($pnode);
        $no = $node->ownerDocument;
        $node->appendChild($no->createCDATASection($cdata_text));
    }

    public static function store($report_result) {
        $src_pre = \Yii::$app->getUrlManager()->hostInfo . \Yii::$app->getUrlManager()->getBaseUrl();
        $src = $report_result['result']->ReportRenderedPath;
        $src = str_replace($src_pre, '', $src);

        if (!file_exists(\yii::getAlias('@runtime/attachments'))) {
            mkdir(\yii::getAlias('@runtime/attachments'));
        }
        $src_name = substr($src, strrpos($src, '/'), strlen($src) - 1);
        $extn = substr($src_name, strrpos($src_name, '.'), strlen($src_name) - 1);
        $src_name = substr($src_name, 0, strrpos($src_name, '.'));
        $src_name .= '_' . date('Y_m_d_H_i_s') . $extn;
        $res = \copy(\yii::getAlias('@app') . '/web' . $src, \yii::getAlias('@runtime/attachments') . $src_name);
        if ($res) {
            return \yii::getAlias('@runtime/attachments') . $src_name;
        } else {
            return FALSE;
        }
    }

    public static function createSessionPath() {
        $pathName = \yii::getAlias('@webroot') . '/reportcache/' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID() . '/';
        if (!file_exists($pathName)) {
            $client = new \GuzzleHttp\Client();
            $resp = $client->get(self::$reportHost . '/CoreJSReportServer/CreateSessionPath?session_id=' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID());
            $result = $resp->getBody();
        }
    }

    public static function getMailOptions(Array $params) {
        $rptOptions = new RptOption();
        if (array_key_exists('xmlPath', $params)) {
            $cwFramework = \simplexml_load_file(\yii::getAlias($params['xmlPath']));
            $viewX = $cwFramework->reportView;
            $rptOptions->rptPath = $viewX['rptPath'];
            $rptOptions->rptName = $viewX['rptName'];
            $rptOptions->rptParams = [];
            $rptOptions->brokenRules = [];
            $branch_id = -1;
            $rptCompanyInfoPrefix = "";
            // Add params from report xml to optionxml
            foreach ($viewX->controlSection->dataBinding->children() as $name => $fld) {
                if ($name === 'param') {
                    if ($fld->session) {
                        $rptOptions->rptParams[(string) $fld['id']] = \app\cwf\vsla\security\SessionManager::getSessionVariable((string) $fld->session);
                    }
                    if ($fld->text) {
                        $rptOptions->rptParams[(string) $fld['id']] = (string) $fld->text;
                    }
                    if ($fld->dateFormat) {
                        $rptOptions->rptParams[(string) $fld['id']] = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForReport();
                    }
                    if ($fld->numberFormat) {
                        $rptOptions->rptParams[(string) $fld['id']] = \app\cwf\vsla\utils\FormatHelper::GetNumberFormat();
                    }
                    if ($fld->currentDate) {
                        $rptOptions->rptParams[(string) $fld['id']] = date("Y-m-d", time());
                    }
                }
                if ($name === 'field') {
                    if (array_key_exists((string) $fld['id'], $params)) {
                        if ($fld['type'] == 'date') {
                            $rptOptions->rptParams[(string) $fld['id']] = \app\cwf\vsla\utils\FormatHelper::GetDBDate($params[(string) $fld['id']]);
                        } else {
                            $rptOptions->rptParams[(string) $fld['id']] = $params[(string) $fld['id']];
                        }
                    } else {
                        if ($fld->DefaultValue != NULL) {
                            $rptOptions->rptParams[(string) $fld['id']] = $fld->attributes()['defaultValue'];
                        }
                    }
                }
                if ($fld['id'] == 'pbranch_id') {
                    $branch_id = $rptOptions->rptParams['pbranch_id'];
                }
                if ($name === 'rptCompanyInfoPrefix') {
                    $rptCompanyInfoPrefix = (string) $fld;
                }
            }
            // get physical path for the corresponding ReportEventHandler
            $fileName = \yii::getAlias(str_replace('.xml', '.php', $params['xmlPath']));
            if (file_exists($fileName)) { // Since it is optional, verify if it exists
                // get namespace
                $reportClass = str_replace('.xml', '', $params['xmlPath']);
                $reportClass = str_replace("/", "\\", $reportClass);
                $reportClass = str_replace('@', '\\', $reportClass);
                $reportClassInstance = new $reportClass();
                $reportClassInstance->initialise((string) $viewX['id']);
                return $reportClassInstance->onRequestMailReport($rptOptions);
            }
        }
        return [];
    }

}

class rptResult {

    public $ReportRenderedPath = '';
    public $PageCount = '';
    public $PageWidth = 0;
    public $PageHeight = 0;
    public $MarginLeft = 0;
    public $MarginTop = 0;
    public $MarginBottom = 0;
    public $MarginRight = 0;
    public $Orientation = 0;
    public $PageStyle = '';
    public $Data = array();

}

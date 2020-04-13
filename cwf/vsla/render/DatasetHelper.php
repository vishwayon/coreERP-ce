<?php

namespace app\cwf\vsla\render;

use app\cwf\vsla\data\DataConnect;

/**
 * Description of DatasetHelper
 *
 * @author dev
 */
class DatasetHelper {

    private $config = [];

    public function __construct($config = []) {
        $this->config = array_merge($config, [
            'baseUrl' => \Yii::$app->getUrlManager()->getBaseUrl(),
            'sessionID' => \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID(),
            'renderTo' => 'web',
        ]);
    }

    public function getDataset(Array $params) {
        // DataConnect::getCn is called to ensure that a Company db connection is available 
        // before processing starts
        $cn = DataConnect::getCn(DataConnect::COMPANY_DB);

        // Set rptOptions
        $rptOptions = new RptOption();
        if (array_key_exists('xmlPath', $params)) {
            $cwFramework = simplexml_load_file(\yii::getAlias($params['xmlPath']), 'SimpleXMLElement', LIBXML_NOCDATA);
            $viewX = $cwFramework->datasetView;
            $rptOptions->rptPath = $viewX['rptPath'];
            $rptOptions->rptName = $viewX['rptName'];
            $rptOptions->rptParams = array();
            $rptOptions->brokenRules = array();
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
                            // This converts to date syntax for postgreSQL
                            $rptOptions->rptParams[(string) $fld['id']] = \app\cwf\vsla\utils\FormatHelper::GetDBDate($params[(string) $fld['id']]);
                        } elseif ($fld['type'] == 'array') {
                            // This converts to array syntax for postgreSQL
                            $rptOptions->rptParams[(string) $fld['id']] = '{' . $params[(string) $fld['id']] . '}';
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

//            self::getCustomCodePath($rptOptions);
            if ($rptCompanyInfoPrefix != '') {
                self::getCompanyDefaults($rptCompanyInfoPrefix, $rptOptions);
            }
        }

        try {
            $cmd = new \app\cwf\vsla\data\SqlCommand();
            $cmd->setCommandText((string) $viewX->query);
            foreach ($rptOptions->rptParams as $name => $param) {
                $cmd->addParam($name, $param);
            }
            $dsResult = DataConnect::getData($cmd);
            return ['status' => 'OK', 'msg' => '', 'filename' => (string) $viewX->filename, 'dsresult' => $dsResult];
        } catch (\Exception $ex) {
            $msg = $ex->getMessage();
            throw new \Exception($msg);
        }
    }

    // to be implemented 
//    public static function getCustomCodePath(RptOption $rptOption) {
//        // resolve custom code path
//        if (array_key_exists('customCode', \yii::$app->params['cwf_config'])) {
//            if (substr(\yii::$app->params['cwf_config']['customCode']['path'], 0, 1) == '/') {
//                $customCodePath = \yii::$app->params['cwf_config']['customCode']['path'] . DIRECTORY_SEPARATOR . 'C' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID();
//            } else {
//                $customCodePath = DIRECTORY_SEPARATOR . \yii::$app->params['cwf_config']['customCode']['path'] . DIRECTORY_SEPARATOR . 'C' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID();
//            }
//        } else {
//            $customCodePath = DIRECTORY_SEPARATOR . 'customCode' . DIRECTORY_SEPARATOR . 'C' . \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID();
//        }
//        $rptOption->rptParams['pcwf_custom_code_path'] = $customCodePath;
//
//        // Get Custom Report information
//        $cmm = new \app\cwf\vsla\data\SqlCommand();
//        $cmm->setCommandText('Select * From sys.rpt_option Where rpt_name=:prpt_name and rpt_path=:prpt_path');
//        $cmm->addParam('prpt_name', $rptOption->rptName);
//        $cmm->addParam('prpt_path', $rptOption->rptPath);
//        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
//
//        if (count($dt->Rows()) == 1) {
//            if ($dt->Rows()[0]['rpt_replace_path'] != '') {
//                // Replace the report path. This should be relative to customCode path
//                if (substr($dt->Rows()[0]['rpt_replace_path'], 0, 1) == '/') {
//                    $rptOption->rptPath = $customCodePath . $dt->Rows()[0]['rpt_replace_path'];
//                } else {
//                    $rptOption->rptPath = $customCodePath . DIRECTORY_SEPARATOR . $dt->Rows()[0]['rpt_replace_path'];
//                }
//            }
//        }
//    }

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

    private static function addCData($pnode, $cdata_text) {
        $node = dom_import_simplexml($pnode);
        $no = $node->ownerDocument;
        $node->appendChild($no->createCDATASection($cdata_text));
    }

}

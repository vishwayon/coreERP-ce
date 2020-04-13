<?php

namespace app\cwf\fwShell\controllers;

use app\cwf\vsla\base\WebController;
use app\cwf\vsla\data\DataConnect;

include('../cwf/vsla/utils/simple_html_dom.php');

/**
 * Description of TwigReportController
 *
 * @author dev
 */
class TwigReportController extends WebController {

    protected $ModulePath = '';
    protected $XmlViewPath = '';
    protected $path = '';
    protected $viewer_id;

    public function init() {
        parent::init();
        $this->XmlViewPath = \yii::$app->request->get('xmlPath') . '.xml';
        $twigOptions = &\yii::$app->view->renderers['twig'];
        // Register yii classes that you plan to use in twig
        $twigOptions['globals'] = [
            'formatHelper' => ['class' => \app\cwf\vsla\utils\FormatHelper::class],
            'ScriptHelper' => ['class' => \app\cwf\vsla\utils\ScriptHelper::class]
        ];
    }

    public function behaviors() {
        return [
            'verbs' => [
                'class' => \yii\filters\VerbFilter::className(),
                'actions' => [
                    'Render' => ['post'],
                ],
            ],
        ];
    }

    public function getViewPath() {
        return '../' . $this->ModulePath . '/views';
        //parent::getViewPath();
    }

    public function actionRenderpdf() {
        $req = \Yii::$app->request;
        return $this->renderreport($req->bodyParams, 'pdf_file');
    }

    public function actionRenderhtml() {
        $req = \Yii::$app->request;
        return $this->renderreport($req->bodyParams, 'html_file');
    }

    public function actionRenderhtmlinline() {
        $req = \Yii::$app->request;
        return $this->renderreport($req->bodyParams, 'html_file_inline');
    }

    public function actionVchreport() {
        $req = \Yii::$app->request;
        $vchrptparams = json_decode($req->bodyParams['rptparams']);
        return $this->renderreport((array) $vchrptparams, 'html_file');
    }

    public function actionViewer($xmlPath) {
        $viewOption = new \app\cwf\vsla\render\FormViewOptions();
        $viewOption->callingModulePath = '';
        $viewOption->xmlViewPath = $this->XmlViewPath;
        $design = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($viewOption->callingModulePath, $viewOption->xmlViewPath);
        $viewOption->accessLevel = \app\cwf\vsla\security\AccessManager::verifyAccess($design->id);
        $viewForRender = \app\cwf\vsla\render\ViewManager::getCompiledFormView($viewOption, $design);
        $viewurl = str_replace('@app', '?r=', $this->getModulePath() . "/" . $this->id);
        return $this->renderPartial('@app/cwf/fwShell/views/TwigreportViewer', ['viewForRender' => $viewForRender, 'xmlPath' => $this->XmlViewPath, 'viewerurl' => $viewurl]);
    }

    private function renderreport($params, $outtype) {
        $cn = DataConnect::getCn(DataConnect::COMPANY_DB);

        // Set rptOptions
        $rptOptions = new \app\cwf\vsla\render\RptOption();

        if (array_key_exists('xmlPath', $params)) {

            $cwFramework = simplexml_load_file(\yii::getAlias($params['xmlPath']));
            $viewX = $cwFramework->reportView;

            $rptOptions->rptPath = $viewX['rptPath'];
            $rptOptions->rptName = $viewX['rptName'];
            $rptOptions->rptParams = array();
            $rptOptions->brokenRules = array();
            $branch_id = -1;
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
            }

            $fileName = str_replace('.xml', '', $params['xmlPath']);
            $reportClass1 = '\\app' . str_replace("/", "\\", str_replace('../cwf/..', '', str_replace('.xml', '', $params['xmlPath'])));
            $fileName = str_replace("@app", '\\app', $fileName);
            $reportClass = str_replace("/", "\\", $fileName);
            $reportClassInstance = new $reportClass();
            $reportClassInstance->initialise((string) $viewX['id']);
            $reportClassInstance->rptOption = $rptOptions;
            $reportClassInstance->onRequestReport($rptOptions);
            if (count($reportClassInstance->rptOption->brokenRules) > 0) {
                \Yii::$app->response->headers->add('Output-Type', 'text/html');
                $rpterr = '<div style="margin-top:15px;">
                           <span class="glyphicon glyphicon-exclamation-sign" style="margin-right:5px;color:#a94442;"></span>
                           <strong  style="color:#a94442;">Missing report options</strong>
                           <div style="margin: 10px 0 0 30px;">';
                for ($i = 0; $i < count($reportClassInstance->rptOption->brokenRules); $i++) {
                    $rpterr .= '<li>' . $reportClassInstance->rptOption->brokenRules[$i] . '</li>';
                }
                $rpterr .= '</div></div>';
                echo $rpterr;
//                echo "<h3>Invalid parameter values</h3>";
//                echo "<text style='font-size:12px;'>Kindly correct the following to generate the report.</text><br><br>";
//                echo implode("<br>",$reportClassInstance->rptOption->brokenRules);
                return;
            } else {
                $this->GetPresetValues($branch_id, $viewX, $reportClassInstance->rptOption);
                $rptModel = $reportClassInstance->getModel();
                $pathstr = '..' . ((string) $reportClassInstance->rptOption->rptPath) . '/' . ((string) $reportClassInstance->rptOption->rptName) . '.twig';
                if (file_exists($pathstr)) {
                    $res = $this->renderFile($pathstr, ['model' => $rptModel]);
                    return $res;
                }
                return;
            }
        }
        $cn = null;
    }

    private function GetPresetValues($branch_id, $viewX, $rptOptions) {
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
                        $rptOptions->rptParams['pcwf_company_name'] = $dtPreset->Rows()[0][$col->columnName];
                        break;
                    case "branch_name":
                        $rptOptions->rptParams['pcwf_branch_name'] = $dtPreset->Rows()[0][$col->columnName];
                        break;
                    case "branch_address":
                        $rptOptions->rptParams['pcwf_branch_address'] = $dtPreset->Rows()[0][$col->columnName];
                        break;
                    case "currency_displayed":
                        $rptOptions->rptParams['pcwf_txn_ccy'] = $dtPreset->Rows()[0][$col->columnName];
                        break;
                    case "company_logo":
                        $rptOptions->rptParams['pcwf_company_logo'] = \yii::$app->basePath . $dtPreset->Rows()[0][$col->columnName];
                        break;
                    case "header_template":
                        $rptOptions->rptParams['pcwf_header_template'] = $dtPreset->Rows()[0][$col->columnName];
                        break;
                    default:
                        break;
                }
            }
        }
        // Add other default parameters
        $rptOptions->rptParams['pcwf_date_format'] = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForReport();
        $rptOptions->rptParams['pcwf_ccy_format'] = \app\cwf\vsla\utils\FormatHelper::GetNumberFormat();
        $rptOptions->rptParams['pcwf_amt_format'] = \app\cwf\vsla\utils\FormatHelper::GetAmtFormat();
        $rptOptions->rptParams['pcwf_qty_format'] = \app\cwf\vsla\utils\FormatHelper::GetQtyFormat();
        $rptOptions->rptParams['pcwf_rate_format'] = \app\cwf\vsla\utils\FormatHelper::GetRateFormat();
        $rptOptions->rptParams['pcwf_fc_rate_format'] = \app\cwf\vsla\utils\FormatHelper::GetFCRateFormat();
        $rptOptions->rptParams['pcwf_base_path'] = \yii::$app->basePath;

        // Add Track Report And CoreERP Version
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * From sys.fn_track_report(:preport_path)');
        $cmm->addParam('preport_path', $rptOptions->rptPath . '/' . $rptOptions->rptName . '.jrxml');
        $dt = DataConnect::getData($cmm, DataConnect::MAIN_DB);
        if (count($dt->Rows()) == 1) {
            $rptOptions->rptParams['pcwf_coreerp_ver'] = 'CoreERP Ver ' . \yii::$app->params['coreerp-ver'] . ' [' . $dt->Rows()[0]['fn_track_report'] . ']';
        } else {
            $rptOptions->rptParams['pcwf_coreerp_ver'] = 'CoreERP Ver ' . \yii::$app->params['coreerp-ver'];
        }
    }

}

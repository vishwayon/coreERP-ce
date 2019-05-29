<?php


namespace app\cwf\fwShell\controllers;

use app\cwf\vsla\base\WebController;
use app\cwf\vsla\data\DataConnect;
include('../cwf/vsla/utils/simple_html_dom.php');

class ReportController extends WebController {
    
    protected $ModulePath = '';
    protected $XmlViewPath = '';
    protected $path='';
    public function init() {
        parent::init();
        $xmlPath = \yii::$app->request->get('xmlPath');
        $baseModPath = '';
        if($this->module->module) {
            $baseModPath .= $this->module->module->id;
        }
        $this->ModulePath = $baseModPath.'/'.$this->module->id;
        $this->path = '../'.$baseModPath.'/'.$xmlPath;
        $this->XmlViewPath = $this->path.'.xml';
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
        return '../'.$this->ModulePath.'/views';
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
    
    public function actionVchreport(){
        $req = \Yii::$app->request;
        $vchrptparams=  json_decode($req->bodyParams['rptparams']);
        return $this->renderreport((array)$vchrptparams, 'html_file');
    }
    
    public function actionViewer($xmlPath) {
        $viewOption = new \app\cwf\vsla\render\FormViewOptions();
        $viewOption->callingModulePath = '';
        $viewOption->xmlViewPath = $this->XmlViewPath;
        $design = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($viewOption->callingModulePath, $viewOption->xmlViewPath);
        $viewOption->accessLevel = \app\cwf\vsla\security\AccessManager::verifyAccess($design->id);
        $viewForRender = \app\cwf\vsla\render\ViewManager::getCompiledFormView($viewOption, $design);
        return $this->renderPartial('ReportViewer',['viewForRender' => $viewForRender, 'xmlPath' => $this->XmlViewPath]);
    }
    
    private function renderreport($params,$outtype){
        $cn = DataConnect::getCn(DataConnect::COMPANY_DB);
        $outputType=$outtype;
        
        // Start processing the request
        $OptionXml = new \SimpleXMLElement('<rptOptions></rptOptions>');
        $rptParams = $OptionXml->addChild("rptParams");
        // Set the Server Values
        $configReader=  new \app\cwf\vsla\utils\ConfigReader();
        
        $OptionXml->addChild('dbServer',  $configReader->dbInfo->dbServer);
        $OptionXml->addChild('dbName', DataConnect::getCompanyDB());
        $OptionXml->addChild('dbUser', $configReader->dbInfo->suName);        
        $OptionXml->addChild('dbPass', $configReader->dbInfo->suPass);
        
        //$OptionXml->addChild('reqtime', time());
        $OptionXml->addChild('outputType', $outputType);
        
        $OptionXml->addChild('serverUrl', \Yii::$app->getUrlManager()->getBaseUrl());
        $OptionXml->addChild('sessionID', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID());
        if(array_key_exists('reqtime',$params)){ 
            $OptionXml->addChild('viewerID', $params['reqtime']);
        } else {
            $OptionXml->addChild('viewerID', time());
        }
        
        
        // loop and get the params
        if(array_key_exists('_csrf',$params)){ 
            $pramXml = $rptParams->addChild('param',$params['_csrf']);      
            $pramXml->addAttribute('name', '_csrf');
        }
        
        
        // Set rptOptions
        $rptOptions= new RptOption();
        
        if(array_key_exists('xmlPath',$params)){
            
            $cwFramework=simplexml_load_file($params['xmlPath']);
            $viewX = $cwFramework->reportView;
            
            $rptOptions->rptPath= $viewX['rptPath'];
            $rptOptions->rptName=$viewX['rptName'];
            $rptOptions->rptParams=array();    
            $rptOptions->brokenRules=array();
            $branch_id=-1;
            // Add params from report xml to optionxml
             foreach($viewX->controlSection->dataBinding->children() as $name => $fld) {
                if($name==='param'){
                    if($fld->session){                            
                        $rptOptions->rptParams[(string)$fld['id']]= \app\cwf\vsla\security\SessionManager::getSessionVariable((string)$fld->session);
                    }
                    if($fld->text){                         
                        $rptOptions->rptParams[(string)$fld['id']]= (string)$fld->text; 
                    } 
                    if($fld->dateFormat){                        
                        $rptOptions->rptParams[(string)$fld['id']]=  \app\cwf\vsla\utils\FormatHelper::GetDateFormatForReport();
                    }
                    if($fld->numberFormat){                        
                        $rptOptions->rptParams[(string)$fld['id']]=  \app\cwf\vsla\utils\FormatHelper::GetNumberFormat();
                    }
                    if($fld->currentDate){                        
                        $rptOptions->rptParams[(string)$fld['id']]=  date("Y-m-d", time());
                    }
                }
                if($name==='field'){
                    if(array_key_exists((string)$fld['id'], $params)){
                        if($fld['type'] == 'date'){
                            $rptOptions->rptParams[(string)$fld['id']]=  \app\cwf\vsla\utils\FormatHelper::GetDBDate($params[(string)$fld['id']]);
                        }
                        else{
                            $rptOptions->rptParams[(string)$fld['id']]=  $params[(string)$fld['id']];                            
                        }
                    }
                    else{
                        if($fld->DefaultValue!=NULL){     
                            $rptOptions->rptParams[(string)$fld['id']]= $fld->attributes()['defaultValue'];      
                        }
                    }
                }
                if($fld['id']=='pbranch_id'){
                    $branch_id=$rptOptions->rptParams['pbranch_id'];
                }
             }             
             
            $this->GetPresetValues($branch_id, $viewX, $rptOptions);
            
            $fileName= str_replace('.xml', '.php', $params['xmlPath']);
            $reportClass= '\\app'.str_replace("/", "\\", str_replace('../cwf/..', '', str_replace('.xml', '', $params['xmlPath'])));
            //$reportClass1 =  '\\app'.str_replace("/", "\\",(string)$viewX['rptPath']).'\\'.(string)$viewX['id'];
//        $fileName= substr($this->module->basePath, 0, strpos($this->module->basePath, "/cwf/fwShell")) . str_replace('\\', '/', (string)$viewX['rptPath'].'\\'.(string)$viewX['id'].'.php');
            if(file_exists($fileName)){
            $reportClassInstance = new $reportClass();
            $reportClassInstance->initialise($viewX['id']);
            $Method = 'onRequestReport' ;
            $reportClassInstance->$Method($rptOptions);
            if(count($rptOptions->brokenRules)>0){
                \Yii::$app->response->headers->add('Output-Type', 'text/html');
                    $rpterr = '<div style="margin-top:15px;">
                           <span class="glyphicon glyphicon-exclamation-sign" style="margin-right:5px;color:#a94442;"></span>
                           <strong  style="color:#a94442;">Missing report options</strong>
                           <div style="margin: 10px 0 0 30px;">';                            
                    for($i=0;$i<count($rptOptions->brokenRules);$i++){
                        $rpterr .= '<li>'.$rptOptions->brokenRules[$i].'</li>';
                    }
                    $rpterr .= '</div></div>';
                    echo $rpterr;
//                echo "<h4>Invalid parameter values</h4>";
//                echo "<text style='font-size:12px;'>Kindly correct the following to generate the report.</text><br><br>";
//                echo implode("<br>",$rptOptions->brokenRules);
                return; 
            }        
        }
        
        }
        
         
        // Add params from report xml to optionxml
        $OptionXml->addChild('rptPath', $rptOptions->rptPath);
        $OptionXml->addChild('rptName', $rptOptions->rptName);
        foreach ($rptOptions->rptParams as $name=>$param) {                                        
            $pramXml = $rptParams->addChild('param', $param);                            
            $pramXml->addAttribute('name',$name);
        }
        
        $content = (string)$OptionXml->asXML();
        $client = new \GuzzleHttp\Client();
        $req = $client->createRequest('POST');
        
        try {
            $resp = $client->post('http://localhost:8080/CoreReportServer/RenderReport?reqtime='.time(), ['body' => $content]);
            $result = $resp->getBody();
            if($outputType=='pdf'){
                \Yii::$app->response->headers->add('Content-Type', 'application/pdf');
                \Yii::$app->response->headers->add('Content-Disposition', 'inline; filename="document_'.time().'.pdf"');
                \Yii::$app->response->format = 'raw';
            }
            else if ($outputType=='html') {
                \Yii::$app->response->headers->add('Output-Type', 'text/html');                
            } else if ($outputType == 'html_file' || $outputType == 'pdf_file') {
                \Yii::$app->response->headers->add('Output-Type', 'application/json');
                if($outputType == 'html_file') {
                    $rptResult = $this->modifyHtmlRef($result);
                    return json_encode($rptResult);
                } elseif ($outputType == 'pdf_file') {
                    $rptResult = $this->modifyPdfRef($result);
                    return json_encode($rptResult);
                }
            }
            $cn = null;
            return $result;
            
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            \Yii::$app->response->headers->add('Output-Type', 'text/html');
            echo "<h1>Exception Occurred</h1>\n";
            echo $e->getMessage();
            if ($e->hasResponse()) {
                echo $e->getResponse() . "\n";
            }
        } catch(\Exception $ex) {
            \Yii::$app->response->headers->add('Output-Type', 'text/html');
            echo "<h1>Exception Occurred</h1>\n";
            echo $ex->getMessage();
        }
    }
    
    private function GetPresetValues($branch_id, $viewX, $rptOptions){
        if($branch_id==''){
            $branch_id='-1';
        }
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('SELECT * FROM sys.fn_report_defaults(:pbranch_id, :pcompany_id)');
        $cmm->addParam('pbranch_id', $branch_id);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $dtPreset = \app\cwf\vsla\data\DataConnect::getData($cmm);
        
        if(count($dtPreset->Rows())>0){
            foreach($viewX->controlSection->dataBinding->children() as $name => $fld) {
                if($fld->preset){                    
                    if((string)$fld['id'] == 'pcompany_logo_physical'){
                        $rptOptions->rptParams[(string)$fld['id']]=  \yii::$app->basePath . $dtPreset->Rows()[0]['company_logo'];  
                    }
                    elseif ((string)$fld['id'] == 'pcompany_logo_url') {
                        $rptOptions->rptParams[(string)$fld['id']]=  ".." . $dtPreset->Rows()[0]['company_logo'];  
                    }
                    else{
                        $rptOptions->rptParams[(string)$fld['id']]= $dtPreset->Rows()[0][substr((string)$fld['id'], 1)];  
                    }
                }
            }
        }
    }
    
    private function modifyHtmlRef($rptInfojson) {
        $rptInfo = json_decode($rptInfojson);
        
        $rptResult = new rptResult();
        $rptResult->ReportRenderedPath = $rptInfo->ReportRenderedPath;
        $rptResult->PageCount = $rptInfo->PageCount;
        $rptResult->Orientation = $rptInfo->Orientation == "1" ? "portrait" : "landscape";
        $rptResult->PageHeight = round(floatval($rptInfo->PageHeight)/72, 2);
        $rptResult->PageWidth = round((floatval($rptInfo->PageWidth)+2.88)/72, 2);
        $rptResult->MarginTop = round(floatval($rptInfo->MarginTop)/72, 2);
        $rptResult->MarginRight = round(floatval($rptInfo->MarginRight)/72, 2);
        $rptResult->MarginBottom = round(floatval($rptInfo->MarginBottom)/72, 2);
        $rptResult->MarginLeft = round(floatval($rptInfo->MarginLeft)/72, 2);
        $rptResult->Orientation = $rptInfo->Orientation == "1" ? "portrait" : "landscape";
        
        
                
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
                                            width: '.($rptInfo->Orientation == "1" ? $rptResult->PageWidth : $rptResult->PageHeight).'in;
                                            height: '.($rptInfo->Orientation == "1" ? $rptResult->PageHeight : $rptResult->PageWidth).'in;
                                            padding-top: '.$rptResult->MarginTop.'in;
                                            padding-left: '.$rptResult->MarginLeft.'in;
                                            margin: auto;
                                            margin-bottom: 20px;
                                            -webkit-print-color-adjust:exact;
                                            box-sizing: border-box;
                                        }
                                    }
                                    
                                    @media print {
                                        .print-format {
                                            width: '.$rptResult->PageWidth.'in;
                                            height: '.$rptResult->PageHeight.'in;
                                            padding-top: '.$rptResult->MarginTop.'in;
                                            padding-left: '.$rptResult->MarginLeft.'in;
                                            orientation: '.$rptResult->Orientation.';  
                                            -webkit-print-color-adjust:exact;
                                            box-sizing: border-box;
                                        }
                                    }
                                </style>';
        
        $rptpath = \Yii::$app->basePath.'/web'.$rptInfo->ReportRenderedPath;
        $pageCount = intval($rptInfo->PageCount);
        for($i=1;$i<=$pageCount;$i++) {
            
            $sourceFile = $rptpath."page_".$i.".html";
            $html = file_get_html($sourceFile);
            $pagehtml = '';
            
            // read style sheets and change to table id and add to page
            foreach($html->find('link') as $link) {
                $cssName= $link->href;
                $cssfile = fopen($rptpath.$cssName, "r");
                $csscontents = fread($cssfile, filesize($rptpath.$cssName));
                fclose($cssfile);
                $newcss = str_replace(".style-", "#t".$i." .style-", $csscontents);
                // create page for json render
                $pagehtml .= '<style>'.$newcss.'</style>';
            }
            // Modify image links
            foreach($html->find('img') as $img) {
                $imgName= $img->src;
                $img->src = \Yii::$app->getUrlManager()->hostInfo.\Yii::$app->getUrlManager()->getBaseUrl().$rptInfo->ReportRenderedPath.$imgName;
            }
            
            // Get Table
            foreach($html->find('table') as $htmltable) {
                $htmltable->id = "t".$i;                
                $pagehtml .= (string)$htmltable;
            }
            $rptResult->Data['Page'.$i] = $pagehtml;
        }
        return $rptResult;
    }
    
    private function modifyPdfRef($rptInfojson) {
        $rptInfo = json_decode($rptInfojson);
        
        $rptResult = new rptResult();
        $rptResult->ReportRenderedPath = \Yii::$app->getUrlManager()->hostInfo.\Yii::$app->getUrlManager()->getBaseUrl().$rptInfo->ReportRenderedPath;
        
        return $rptResult;
    }
}

class rptResult {
    public $ReportRenderedPath='';
    public $PageCount='';
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
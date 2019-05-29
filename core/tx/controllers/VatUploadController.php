<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\tx\controllers;

/**
 * Description of VatUploadController
 *
 * @author girishshenoy
 */
class VatUploadController extends \app\cwf\vsla\base\WebController {
    
    public function actionShowUpload($rptOptions = "") {
        $viewOption = new \app\cwf\vsla\render\FormViewOptions();
        $viewOption->callingModulePath = '';
        $viewOption->xmlViewPath = '@app/core/tx/vatUpload/VatUploadView.xml';
        $design = \app\cwf\vsla\xml\CwfXmlLoader::loadFile($viewOption->callingModulePath, $viewOption->xmlViewPath);
        //$viewOption->accessLevel = \app\cwf\vsla\security\AccessManager::verifyAccess($design->id);
        $viewForRender = \app\cwf\vsla\render\ViewManager::getCompiledFormView($viewOption, $design);
        return $this->renderPartial('@app/core/tx/vatUpload/VatUploadView.php', ['viewForRender' => $viewForRender, 'xmlPath' => $viewOption->xmlViewPath, 'rptOptions' => $rptOptions]);
    }
    
    public function actionGenerateData() {
        $formData = \yii::$app->request->getBodyParams();
        $from_date = \app\cwf\vsla\utils\FormatHelper::GetDBDate($formData['pfrom_date']);
        $d = new \DateTime($from_date);
        
        $options = [
            'RetPerdEnd' => $formData['RetPerdEnd'],
            'from_date' => \app\cwf\vsla\utils\FormatHelper::GetDBDate($formData['pfrom_date']),
            'to_date' => \app\cwf\vsla\utils\FormatHelper::GetDBDate($formData['pto_date']),
            'TinNo' => $formData['VatTin'],
            'Period' => intval($d->format('m'))
        ];
        $vuw = new \app\core\tx\vatUpload\VatUploadWorker();
        if($formData['rpt_type'] == 'sales') {
            $result = $vuw->getXML_localSale($options);
        } elseif ($formData['rpt_type'] == 'saleRet') {
            $result = $vuw->getXML_localSaleReturn($options);
        } elseif($formData['rpt_type'] == 'salesInterState') {
             $result = $vuw->getXML_interstateSale($options);
        } elseif($formData['rpt_type'] == 'saleRetInterState') {
             $result = $vuw->getXML_interstateSaleReturn($options);
        } elseif($formData['rpt_type'] == 'purchase') {
             $result = $vuw->getXML_localPurchase($options);
        } elseif($formData['rpt_type'] == 'purchaseInterState') {
             $result = $vuw->getXML_interstatePurchase($options);
        }
        
        $fileName = '/reportcache/'.\app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID().'/VatUpload_'. str_replace('-', '_', $formData['pfrom_date']).'.xml';
        $filepath = \yii::getAlias('@webroot'.$fileName);
        $result->asXML($filepath);
        
        return json_encode(['fileName' => $fileName]);
    }
    
}

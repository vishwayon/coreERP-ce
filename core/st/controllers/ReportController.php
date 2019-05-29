<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\core\st\controllers;

/**
 * Description of ReportController
 *
 * @author girish
 */
class ReportController extends \app\cwf\vsla\base\WebController {
    //put your code here
    public function actionStockcons() {
        $rptOption = new \app\cwf\vsla\render\RptOption();
        $rptOption->rptParams['company_id'] = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID();
        $rptOption->rptParams['branch_id'] = 0;
        $rptOption->rptParams['material_id'] = 0;
        $rptOption->rptParams['finyear'] = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('finyear');
        $rptOption->rptParams['from_date'] = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('year_begin');
        $rptOption->rptParams['to_date'] = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('year_end');
        
        
        $stcons = new \app\core\st\reports\cogc\StockConsumptionAnalysis($rptOption);
        $stcons->initialise();
        return $this->renderFile('@app/core/st/reports/cogc/StockConsumptionAnalysis.twig', ['model' => $stcons]);        
    }
}

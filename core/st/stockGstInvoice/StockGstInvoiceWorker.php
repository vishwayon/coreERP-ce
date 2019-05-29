<?php

namespace app\core\st\stockGstInvoice;

/**
 * StockGstInvoiceWorker
 * @author girish
 */
class StockGstInvoiceWorker {

    public static function getViewForWar() {
        return \yii::$app->controller->renderPartial('@app/core/st/stockInvoice/WarInfoView');
    }

}

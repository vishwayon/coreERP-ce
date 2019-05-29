<?php

namespace app\cwf\vsla\render;

/**
 * Description of MobViewHelper
 *
 * @author dev
 */
class MobViewHelper {

    public static function output_COLLECTION_VIEW(\app\cwf\vsla\design\CollectionDesignView $collectionView) {
        $collection = '<div id="collheader" class="row cformheader">' .
                MobCollectionHelper::getHeader($collectionView) .
                '</div>
                        <div id="collectiondata" name="collectiondata" style="margin-top: 10px;">' .
                '</div>';
        if (property_exists($collectionView, 'clientJsCode')) {
            foreach ($collectionView->clientJsCode as $clientjscode) {
                if ($clientjscode != '') {
                    $collection .= '<script src="' . \app\cwf\vsla\utils\ScriptHelper::registerScript($clientjscode) . '"></script>';
                }
            }
        }
        return $collection;
    }

}

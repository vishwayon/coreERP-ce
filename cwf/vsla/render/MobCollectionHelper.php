<?php

namespace app\cwf\vsla\render;

use app\cwf\vsla\design;
use app\cwf\vsla\security\AccessLevels;
use app\cwf\vsla\security\SessionManager;

/**
 * Description of MobCollectionHelper
 *
 * @author dev
 */
class MobCollectionHelper extends CollectionHelper {

    public static function getHeader(design\CollectionDesignView $design) {
        if ($design->option->accessLevel <= 0) {
            // User does not have access to this document.
            return '<h3>' . $design->header . '</h3>';
        }
        $headerHtml = '<div class="col-md-1 col-xs-1" style="padding-left: 0; padding-right:0;">
            <button id="mob-menu-back" class="btn btn-default" 
            style="background-color:lightgrey;border-color:lightgrey;color:black;"
             onclick="$(\'#contentholder\').hide(); $(\'#mobile-menu-view\').show();">
        <span id="mob-menu-bk-ic" class="glyphicon glyphicon-arrow-left" style="font-size: 14px;margin-left:-3px;"></span>
    </button></div>'.
                '<h3 class="col-md-6 col-xs-8" style="text-align:center;">' . $design->header . '</h3>
            <div class="col-md-4 col-xs-3 cformheaderbuttons" style="padding-left:0;">
                <div id="qp" 
                    qp-route="' . \yii\helpers\Html::encode(str_replace('@app/', '', $design->option->callingModulePath)) . '" 
                    qp-formName="' . \yii\helpers\Html::encode($design->editView) . '" 
                    qp-collName="' . \yii\helpers\Html::encode($design->option->xmlViewPath) . '"
                    qp-keyField="' . $design->keyField .
                ($design->newDocEnabled == TRUE && $design->newDocParam->haswizard() == TRUE ? '"
                    qp-wizPath="' . $design->newDocParam->wizardPath . '"
                    qp-wizStep="' . $design->newDocParam->wizardStep : '') . '"
                    qp-doctype="' . \yii\helpers\Html::encode($design->newDocEnabled ? $design->newDocParam->docType : '') . '"
                    qp-bizobj="' . \yii\helpers\Html::encode($design->type) . '"
                    hidden></div>';
        // Display the Refresh Button
        if ($design->type == \app\cwf\vsla\design\BusinessObject::TYPE_MASTER) {
            $headerHtml .= '<button class="btn btn-sm btn-default" id="collrefresh"
                    style="float: right;" 
                    onclick="coreWebApp.getmcollection()" 
                    type="button">
                    <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>
                </button>';
        }
        // Display New Button where access level permits
        if ($design->newDocEnabled && ($design->option->accessLevel === AccessLevels::DATAENTRY ||
                $design->option->accessLevel === AccessLevels::AUTHORIZE)) {
            if ($design->newDocParam->haswizard()) {
                $headerHtml .= '<button id="cmd_addnew" name="cmd_addnew" class="btn btn-sm btn-default"
                    style="" 
                    type="button" onclick="coreWebApp.collectionView.getWiz()">
                    <span class="glyphicon glyphicon-plus" aria-hidden="true"></span>
                </button>';
            } else {
                if ($design->type == \app\cwf\vsla\design\BusinessObject::TYPE_DOCUMENT) {
                    if ($design->option->firstStageAllowed) {
                        $headerHtml .= '<button id="cmd_addnew" name="cmd_addnew" class="btn btn-sm btn-default"
                            style="" 
                            type="button" onclick="coreWebApp.collectionView.getDoc(\'-1\',\'' . $design->afterLoadEvent . '\')">
                            <span class="glyphicon glyphicon-plus" aria-hidden="true"></span>
                        </button>';
                    }
                } else {
                    $headerHtml .= '<button id="cmd_addnew" name="cmd_addnew" class="btn btn-sm btn-default"
                        style="" 
                        type="button" onclick="coreWebApp.collectionView.getDoc(\'-1\',\'' . $design->afterLoadEvent . '\')">
                        <span class="glyphicon glyphicon-plus" aria-hidden="true"></span>
                    </button>';
                }
            }
        }
        if ($design->collectionSection->detailViewExists()) {
            $headerHtml .= self::getCollectionDetails($design->collectionSection->detailView);
        }
        return $headerHtml . '</div>';
    }

    public static function getCollection(\app\cwf\vsla\design\CollectionDesignView $design, $filters) {
        $collection = '';
        $dtCollection = NULL;
        if ($design->option->accessLevel <= AccessLevels::NOACCESS) {
            return '<span>Requested data is not accessible to this user.</span>';
        } else {
            if ($filters == NULL && $design->type == design\BusinessObject::TYPE_DOCUMENT) {
                $filters = ['docstatus' => 0,
                    'from_date' => \app\cwf\vsla\utils\FormatHelper::GetDateValue(SessionManager::getSessionVariable('year_begin')),
                    'to_date' => \app\cwf\vsla\utils\FormatHelper::GetDateValue(SessionManager::getSessionVariable('year_end'))];
            }
            $dtCollection = self::getData($design, $filters);
//            $collection = '<h2>' . $design->header . '</h2>';
            $collection .= '<ul id="ul-' . $design->id . '" class="list-group">';
            foreach ($dtCollection->Rows() as $datarow) {
                $clink = 'coreWebApp.collectionView.getDoc(\''
                        . (is_array($datarow[$design->keyField]) ?
                        $datarow[$design->keyField]['display'] : $datarow[$design->keyField]) . '\',\'' . $design->afterLoadEvent . '\')';
                $col_item = '<li class="list-group-item" style="display: table; width: 100%;" onclick=' . $clink . '>';
                $col_item .= '<div class="col-xs-12" style="padding: 0;">';
                foreach ($design->collectionSection->displayFields as $column) {
                    foreach ($datarow as $field => $value) {
                        if ($column->columnName == $field) {
                            if ($column->format == 'Amount' || $column->format == 'Number' ||
                                    $column->format == 'Qty' || $column->format == 'Rate' ||
                                    $column->format == 'FC') {
                                $col_item .= '<span class="col-xs-4" style="padding: 5px;text-align:right;">' . $value . '</span>';
                            } else {
                                $col_item .= '<span class="col-xs-4" style="padding: 5px;">' . $value . '</span>';
                            }
                        }
                    }
                }
                $col_item .= '</div>';
                $col_item .= '</li>';
                $collection .= $col_item;
            }
            $collection .= '</ul>';
        }
        return $collection;
    }

}

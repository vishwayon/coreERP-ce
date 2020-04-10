<?php

namespace app\cwf\vsla;

use yii\web\AssetBundle;

/**
 * @author Girish Shenoy
 */
class VslaAsset extends AssetBundle {

    // Set the Source Path for all assets to be published
    public $sourcePath = '@vendor/cwf-assets';
    // Set the css files
    public $css = [
        // JQuery Section
        'jquery-ui-1.12.1/jquery-ui.min.css',
        'jquery-tree/jquery.tree.min.css',
        // Knockout and Form Dependencies
        'select2/select2.min.css',
        'typeahead/tt-typeahead.css',
        'datepicker/css/bootstrap-datepicker3.min.css',
        'toggle/bootstrap-toggle.min.css',
        // DataTable and Collection
        'dataTable/css/jquery.dataTables.min.css',
        'dataTable/css/scroller.dataTables.min.css',
        'dataTable/css/fixedColumns.dataTables.min.css',
        // Toast Messages
        'izitoast/iziToast.min.css',
        'bootstrap3-dialog/css/bootstrap-dialog.min.css',
        // Treegrid for Hierarchical display
        'treegrid/jquery.treegrid.css',
        'treegrid/yandex.min.css',
    ];
    // Set the java script files to be published
    public $js = [
        // JQuery Section
        'jquery-ui-1.12.1/jquery-ui.min.js',
        'jquery-form-validator/jquery.form-validator-mod.min.js',
        'jquery-tree/jquery.tree.min.js',
        // Moment for Timezone
        'moment/moment.min.js',
        'moment/moment-timezone-with-data.min.js',
        // Knockout and Form Dependencies
        'select2/select2.min.js',
        'typeahead/typeahead.bundle-mod.min.js',
        'datepicker/js/bootstrap-datepicker.js',
        'numeric/numericInput.js',
        'toggle/bootstrap-toggle.min.js',
        'knockout/knockout-3.3.0.js',
        'knockout/knockout.mapping-2.4.1.js',
        // DataTable and Collection
        'dataTable/js/jquery.dataTables.min.js',
        'dataTable/js/dataTables.scroller.min.js',
        'dataTable/js/dataTables.fixedColumns.min.js',
        // Toast Messages
        'izitoast/iziToast.min.js',
        'bootstrap3-dialog/js/bootstrap-dialog.min.js',
        // Treegrid for Hierarchical display
        'treegrid/jquery.treegrid.min.js',
        'treegrid/jquery.treegrid.bootstrap3.js',
        // Graph Libraries
        'flot/jquery.flot.min.js',
        'flot/jquery.flot.categories.min.js',
        'flot/jquery.flot.pie.min.js',
        'flot/jquery.flot.stack.min.js',
        'flot/jquery.flot.orderBars.js',
        // Table Export Libraries
        'tableExport/tableExport.js',
        'tableExport/jquery.base64.js',
        'tableExport/jspdf/libs/sprintf.js',
        'tableExport/jspdf/jspdf.js',
        'tableExport/jspdf/libs/base64.js',
    ];

}

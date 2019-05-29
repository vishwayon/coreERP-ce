<?php

use yii\helpers\Html;
use yii\helpers\BaseHtml;
use yii\bootstrap\ActiveForm;
use app\cwf\vsla\utils\FormatHelper;
?>

<div id="contentholder" class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="ibheader" class="row cformheader">
            <h3>Import Balance</h3>
        </div>
        <br/>
        <div class='col-md-12'>
            <span>
                Import balance from previous year to the current year (<?= app\cwf\vsla\security\SessionManager::getSessionVariable('finyear'); ?>)
            </span>
        </div>
        <br/>
        <div id="ibform" class="row" style="margin-left: 0;margin-top: 10px;">
            <form class="form-horizontal required" id="impbal" name ="impbal" 
                  target="balances" method="GET" action="?r=cwf%2FfwShell%2Fmain%2Fgetimportbalance" style="margin-left: 10px;">
                <input type="hidden" id="_csrf" name="_csrf" value="<?= \Yii::$app->request->csrfToken ?>">
                <div class="col-md-2" style="margin-top: 0px;">
                    <?= Html::checkbox('importaccbal', NULL, ['label' => '<span> Account balance</span>',
                        'labelOptions' => ['style' => 'padding-top:5px;']]);
                    ?>
                </div>
                <div class="col-md-2" style="margin-top: 0px;">
<?= Html::checkbox('importinvbal', NULL, ['label' => '<span> Inventory balance</span>',
    'labelOptions' => ['style' => 'padding-top:5px;']]);
?>
                </div>
                <div class="col-md-2" style="margin-top: 0px;">
<?= Html::button('Import', ['class' => 'btn btn-primary', 'name' => 'impbal-button', 'onclick' => 'importbaldata()',
    'id' => 'impbal-button', 'style' => 'line-height:1.2;padding: 3px 12px;'])
?>
                </div>
            </form>
        </div>
        <div id="balances" class="row" style="margin-top: 10px;margin-left: 0px;margin-right: 0px;display: none;">
        </div>
    </div>
    <script type="text/javascript">
        function importbaldata() {
            var res = {};
            $.each($('#impbal').serializeArray(), function () {
                res[this.name] = this.value;
            });
            $('#impbal input[type=checkbox]:not(:checked)').each(
                    function () {
                        res[this.name] = '0';
                    });
            res['reqtime'] = new Date().getTime();
            $.ajax({
                url: $('#impbal').attr('action'),
                type: 'GET',
                data: res,
                beforeSend: function () {
                    coreWebApp.startloading();
                },
                complete: function () {
                    coreWebApp.stoploading();
                },
                success: function (resultdata) {
                    $('#balances').html(resultdata);
                    $('#balances').show();
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                    coreWebApp.stoploading();
                }
            });
            return false;
        }
    </script>
</div>
<div id="details" class="view-min-width view-window2" style="display: none;">
</div>
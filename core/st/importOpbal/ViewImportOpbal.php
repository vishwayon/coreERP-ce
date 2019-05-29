<?php
use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$form_date_format = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForHtml();
$viewerurl='?r=core%2Fst%2Fimport-opbal%2Fgetdata'; 
$purl='?r=core%2Fst%2Fimport-opbal%2Fsetdata';
?>
<div id="contentholder"  class="view-min-width view-window1">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row">
            <h3>Import Stock Balance</h3>
        </div>
        <div id="collfilter" class="row">
            <form class="form-horizontal required" id="importopbal" name ="importopbal" 
                target="importopbaldata" method="GET" action="<?= $viewerurl?>" style="margin-left: 10px;">
                <input type="hidden" id="_csrf" name="_csrf" value="<?=\Yii::$app->request->csrfToken?>">

                <div id="importopbaldata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;">
                    <div id="divbrules" name="divbrules" style="display: none;" class="row">
                        <ul id="brules" name="brules" style="color: #a94442;"></ul>
                    </div>        
                    <div id="divmsg" name="divmsg" style="display: none;" class="row">
                        <label id="msg" name="msg"></label>
                    </div>
                </div>

                <div class="col-md-3 form-group" style="margin-top: 0px;">
                    <label class="control-label" for="prev_year_desc">From</label>
                    <input id="prev_year_desc" class="textbox form-control" type="string" readonly="true" value="<?= $model->prev_year_desc ?>" name="prev_year_desc">                   
                </div>
                <div class="col-md-3 form-group required" style="margin-top: 0px;">
                    <label class="control-label" for="current_year_desc">To</label>
                    <input id="current_year_desc" class="textbox form-control" type="string" readonly="true" value="<?= $model->current_year_desc ?>" name="current_year_desc">                   
                </div>
            </form>           
            <div class="col-md-2 form-group" style="margin-top: 15px; padding-left: 0px; padding-right: 0px; margin-bottom: 5px;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default" id="cmdupdateopbl"
                        onclick="setopbaldata()">
                  <span class="glyphicon glyphicon-floppy-disk"></span> Import         
                </button>
            </div>
        </div>
        <div class="row" style="margin-top: 10px;margin-left: 0px;margin-right: 0px;">
            <label class="control-label" style="font-weight: normal;">Note : This wizard will import Stock opening balance from previous financial year to the currently connected financial year for all branches.</label>
        </div>
    </div>
</div>
    <div id="details" class="view-min-width view-window2" style="display: none;">
    </div>
<script type="text/javascript">
//    applysmartcontrols();
    function setopbaldata() {
        var req = {
            url : '?r=core%2Fst%2Fimport-opbal%2Fsetdata',
            type: 'GET',
            success : function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                $('#brules').html('');
                if(jsonResult.status !== 'Ok') {
                    coreWebApp.toastmsg('warning','Import Failed','',false);
                    var brules=jsonResult.jsondata.brokenrules;
                    var litems='<strong>Broken Rules</strong>';
                    for(var i=0;i<brules.length;i++){
                        litems+="<li>"+brules[i]+"</li>";
                    }
                    $('#brules').append(litems);
                    $('#divbrules').show();
                    $('#importopbaldata').show();
                }else{ 
                    $('#msg').text("Stock Balance imported successfully.")
                    $('#divmsg').show();
                    $('#importopbaldata').show();
                }
            }
            
        };        
        coreWebApp.utils.getData(req); 
    }
    
    function importopbaldata(){
        var res = { };
        $.each($('#importopbal').serializeArray(), function() {
            res[this.name] = this.value;
        });
        coreWebApp.getJsonData('importopbal','GET');
        $('#importopbaldata').show();
    }
</script>

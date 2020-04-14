<div id="contents" style="padding: 5px;margin:5px;">
<style type="text/css">
   .form-horizontal .control-label{text-align: left;}
   .smartcombo{padding: 0;}
   .select2-container .select2-choice{border:0;height: 100%;}
   .editRow{}
   .datetime{}
   .fc-x-rate{}
   .form-control{margin-top: 0;}
   caption{padding:0;}
   .table thead tr {border-bottom: 1px solid lightgray;}
</style>
<?php 
    use yii\helpers\Html;
    use yii\widgets\ActiveForm;
    $baseurl=  \Yii::$app->urlManager->getBaseUrl();
    $wizrender=new app\cwf\vsla\ui\wizardrenderer($wizparser,$step);
    $form = ActiveForm::begin([
            'id' => 'wiz-form',
        ]); 
?>
    <input type="hidden" name="formName" id="formName" value="<?= $formName?>" />
    <input type="hidden" name="formParams" id="formParams" value="<?= serialize($wizparser->stepData) ?>" />
    <input type="hidden" name="step" id="step" value="<?=$step?>"/>
    <input type="hidden" name="nextstep" id="nextstep" value="<?=$wizparser->nextStep?>"/>
    <input type="hidden" name="prevstep" id="prevstep" value="<?=$wizparser->prevStep?>"/>
    <input type="hidden" name="wizlink" id="wizlink" value="<?='?r='.str_replace('@app/', '', $wizparser->modulePath).'/form/wizard'?>"/>   
    <div>
        <?= $wizrender->getHeder();?>
        <div>
            <ul id="brokenrules" name="brokenrules" data-bind="foreach: brule" style="color: #a94442;display:none;"></ul>
        </div>
        <?= $wizrender->getForm();?>
    </div>
<?php ActiveForm::end(); ?>
    <script type="text/javascript">
        var oldStepData=<?=  json_encode($wizparser->stepData)?>;
        var stepdata=<?= json_encode($wizparser->xsteps[$step]->stepWizData)?>;
        var brule=<?= json_encode($wizparser->codeBehind->brokenrules)?>;
        var currdata=<?= isset($currentdata)?json_encode($currentdata):'null'?>;
        var renderEvents = <?= json_encode(array_values($wizrender->renderEvents)) ?>;
        var temp;
        function bindWiz() {
            if(brule.length>0 && currdata!=null){
                temp=ko.mapping.fromJS(currdata);
                ko.applyBindings(temp,$('#wiz-form')[0]);
            }else{
                temp=ko.mapping.fromJS(stepdata);
                ko.applyBindings(temp,$('#wiz-form')[0]);
            }
            coreWebApp.setwiz();
            var args = {
                oldStepData: oldStepData,
                stepdata: stepdata,
                currdata: currdata,
                model: temp
            };
            renderEvents.forEach(e => {
                var f = new Function("args",  "return " + e + "(args);");
                f(args);
            });
        }
        setTimeout(bindWiz, 100);
    </script>
</div>


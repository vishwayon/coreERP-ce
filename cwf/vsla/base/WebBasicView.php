<div id="contents" style="padding: 5px;margin:5px;">
    <?php
    use yii\helpers\Html;
    use yii\widgets\ActiveForm;
    ?>

    <style type="text/css">
       .form-horizontal .control-label{text-align: left;}
       .smartcombo{padding: 0;}
       .select2-container .select2-choice{border:0;height: 100%;}
       .editRow{}
       .datetime{}
       .fc-x-rate{}
       .ui-widget-content .ui-state-active{border:0;}
    </style>

    <?php $form = ActiveForm::begin([
            'id' => 'custom-form',
            'options' => ['style'=>'display: none', 'data-bind'=>'visible: true'],//'class' => 'form-inline','role'=>'form'],
            'fieldConfig' => [],
        ]); ?>
    <input type="hidden" name="bindingBO" id="bindingBO" value="<?php //echo '?r='.$viewParser->modulePath.'&bo='.$viewParser->bindingBO ?>" />
    <input type="hidden" name="formParams" id="formParams" value="<?php //echo Html::encode($viewParser->formParams) ?>" />
    <input type="hidden" name="formName" id="formName" value="<?php //echo Html::encode($formName) ?>" />
    
    <div>
       <ul id="brokenrules" style="color: #a94442;"></ul>
    </div>
    <div>
       <?php
       echo $renderer->render();
       ?>

       <div class="form-group">
           <div style="text-align:center;">
               <button id="cmdsaveb" class="btn btn-primary" style="font-size:12px;" 
                        name="bsave-button" type="submit" style="display: none;">
                        <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Save
                </button>

           </div>
       </div>
    </div>
    <script type="text/javascript">
        var custdata=<?= json_encode($renderer)?>;
        //var temp=ko.mapping.fromJS(custdata);
        coreWebApp.ModelBo=ko.mapping.fromJS(custdata);
        applysmartcontrols($(form)[0]);
        ko.applyBindings(coreWebApp.ModelBo,$('#custom-form')[0]);
        //ko.applyBindings(temp,$('#custom-form')[0]);
        $('#divtree').tree({});
        $('#branch_id').on("change", function(e) {
            
        });
    </script>
    </div>
        <?php   ActiveForm::end();  ?>
    </div>

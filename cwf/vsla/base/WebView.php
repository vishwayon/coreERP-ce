<div id="contents" style="padding: 5px 5px 0 5px;margin:5px 5px 0 5px;">
    <?php
    use yii\widgets\ActiveForm;
    ?>

    <style type="text/css">
       .form-horizontal .control-label{text-align: left;}
       .smartcombo{padding: 0;}
       .select2-container .select2-choice{border:0;height: 100%;}
       .editRow{}
       .datetime{}
       .fc-x-rate{}
    </style>

    <script type="text/javascript">
        var formafterload = '<?=$viewForRender->formafterload?>';
    </script>
    <?php $form = ActiveForm::begin([
            'id' => 'bo-form',
            'options' => ['style'=>'display: none', 'data-bind'=>'visible: true', 'autocomplete'=>'off'],//'class' => 'form-inline','role'=>'form'],
            'fieldConfig' => [],
        ]); 
    echo $viewForRender->getForm();
         ActiveForm::end(); ?>
    
    <div id="cdmfile" style="display:none;">
        <?=$viewForRender->getDMFileForm(); ?>
    </div>
</div>
<div id="contents" style="margin-top: 10px;padding-left: 20px;" class=" col-md-5">
<strong>Please select a financial year to proceed</strong><br/><br/>
<?php
        $form = yii\widgets\ActiveForm::begin([
        'id' => 'finyearlist',
        'options' => ['class' => 'form-horizontal'],
            'fieldConfig' => [
//                'template' => "{label}\n<div class=\"col-lg-3\">{input}</div>\n<div class=\"col-lg-8\">{error}</div>",
//                'labelOptions' => ['class' => 'col-lg-1 control-label'],
            ],
        ]); 
        ?>

    <input type="hidden" id="selectedid" name="selectedid" value="-1">
    <div class="list-group">
        <?php
            foreach ($model->finyears as $id => $name) {
                $fy=<<<fy
                        <a href="#" class="list-group-item" onclick="coreWebApp.selectid({$id});">
                            <h4 class="list-group-item-heading" style="font-weight:bold;">{$name['code']}</h4>
                            <p class="list-group-item-text">
                               Financial year starting on <strong>{$name['starts']}</strong> and ending on <strong>{$name['ends']}</strong>
                            </p>
                        </a>
fy;
                echo $fy;
            }
            if(count($model->finyears)===0){
                echo 'No financial years available.';
            }

        ?>
    </div>
    <?php yii\widgets\ActiveForm::end() ?>
</div>
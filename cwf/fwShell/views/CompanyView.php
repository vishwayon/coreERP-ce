<div id="contents col-md-6" style="margin-top: 10px;padding-left: 20px;" class=" col-md-4">
<strong>Please select a company to proceed</strong><br/><br/>
<?php
        $form = yii\widgets\ActiveForm::begin([
        'id' => 'companylist',
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
            foreach ($model->companies as $id => $name) {
                $linkitem=<<<lnkitm
                        <a href="#" class="list-group-item" onclick="coreWebApp.selectid({$id});">
                            <h4 class="list-group-item-heading" style="font-weight:bold;">{$name['company_short_name']}</h4>
                            <p class="list-group-item-text">{$name['company_name']}</br>
                                {$name['company_address']}
                            </p>
                        </a>
lnkitm;
                echo $linkitem;
            }
            if(count($model->companies)===0){
                echo 'No companies available.';
            }
        ?>
    </div>
    <?php yii\widgets\ActiveForm::end() ?>
</div>
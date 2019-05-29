<div id="contents" style="margin-top: 10px;padding-left: 20px;" class=" col-md-5">
<strong>Please select a branch to proceed</strong><br/><br/>
<?php
        $form = yii\widgets\ActiveForm::begin([
        'id' => 'branchlist',
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
            foreach ($model->branchdetails as $id => $name) {
                $fy=<<<fy
                        <a href="#" class="list-group-item" onclick="coreWebApp.selectid({$id});">
                            <h4 class="list-group-item-heading" style="font-weight:bold;">{$name['branch_name']}</h4>
                            <p class="list-group-item-text">
                                {$name['branch_address']}
                            </p>
                        </a>
fy;
                echo $fy;
            }
            if(count($model->branchdetails)===0){
                echo 'No branch available.';
            }

        ?>
    </div>
    <?php yii\widgets\ActiveForm::end() ?>
</div>
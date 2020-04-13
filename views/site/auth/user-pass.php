<?php
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use yii\captcha\Captcha;

$this->title = 'Login';
?>
<div class="site-login col-sm-9" style="margin-top: 10px;padding-left: 20px;">
    <div style="margin-left: 10px;"><h1><?= Html::encode($this->title) ?></h1></div>

    <?php $form = ActiveForm::begin([
        'id' => 'user-pass',
        'action' => '?r=site/login',
        'method' => 'POST',
        'options' => ['class' => 'form-horizontal'],
        'fieldConfig' => [
            'template' => "{label}\n<div class=\"col-sm-4\">{input}</div>\n<div class=\"col-sm-7\">{error}</div>",
            'labelOptions' => ['class' => 'col-sm-2 control-label'],
        ],
    ]); ?>

    <div class="row">
        <h5>Welcome <b><?= Html::encode($model->full_user_name) ?></b>, please enter your password</h5>
    </div>
    <?= $form->field($model, 'password')->passwordInput() ?>

    <div class="form-group" style="padding-top:20px;" >
        <div class="col-sm-offset-3 col-sm-2">
            <?= Html::submitButton('Login', ['class' => 'btn btn-primary', 'name' => 'login-button']) ?>
        </div>
    </div>
    
    <?php ActiveForm::end(); ?>
        
        
    <?php 
        if(isset($model->msg) && $model->msg!==''){
            echo '<div style="padding: 20px;"><span style="color:red; font-size: medium; line-height: 1.5;">'.$model->msg.'</span></div>';
        }
        
        echo '</div>
                <div class="row col-sm-12" style="bottom:20px; display: block; height: auto;position: absolute;">
                    <span id="siteseal" style="padding: 20px;margin: 10px;">
                        <script type="text/javascript" src="https://seal.godaddy.com/getSeal?sealID=qUA3NGyn2slBN23r8CuqZNqgMlaVYTJKn29KseMf8nEj9JrXuHTSTUsyL8yO"></script>
                    </span>
                </div>';
    ?>
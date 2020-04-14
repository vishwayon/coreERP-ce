<?php
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
use yii\captcha\Captcha;

$this->title = 'Login';
?>
<div class="site-login col-sm-9" style="margin-top: 10px;padding-left: 20px;">
    <div style="margin-left: 10px;"><h1><?= Html::encode($this->title) ?></h1></div>

    <?php $form = ActiveForm::begin([
        'id' => 'auth-user',
        'action' => '?r=site/auth-user',
        'options' => ['class' => 'form-horizontal', 'autocomplete' => 'off'],
        'fieldConfig' => [
            'template' => "{label}\n<div class=\"col-sm-4\">{input}</div>\n<div class=\"col-sm-offset-3 col-sm-7\">{error}</div>",
            'labelOptions' => ['class' => 'col-sm-2 control-label'],
        ],
    ]); ?>

    <?= $form->field($model, 'username') ?>
    <?= $form->field($model, 'verifyCode')->widget(\yii\captcha\Captcha::className(), [
        'template' => '<div>{input}</div><div class="col-sm-12" style="padding-top: 5px;">{image}</div>',
    ]) ?>

    <div class="form-group" >
        <div class="col-md-offset-3 col-md-3">
            <?= Html::submitButton('Proceed to Login', ['class' => 'btn btn-primary', 'name' => 'login-button']) ?>
        </div>
        <div class="col-md-3" style="padding-top:20px;">
            <?= Html::a('Forgot your Password?',['site/forgotpassword'],
                    ['style'=>'text-align:right;margin-top:10px;']) ?>
        </div>
    </div>
    
    <?php ActiveForm::end(); ?>
        
        
    <?php 
        if(isset($model->msg) && $model->msg!==''){
            echo '<div style="padding: 20px;"><span style="color:red; font-size: medium; line-height: 1.5;">'.$model->msg.'</span></div>';
        }
        
        echo '</div>
                <div class="row col-md-12" style="bottom:20px; display: block; height: auto;position: absolute;">
                    <span id="siteseal" style="padding: 20px;margin: 10px;">
                        <script type="text/javascript" src="https://seal.godaddy.com/getSeal?sealID=qUA3NGyn2slBN23r8CuqZNqgMlaVYTJKn29KseMf8nEj9JrXuHTSTUsyL8yO"></script>
                    </span>
                </div>';
    ?>
    
    
    <div class="row">
        <div class="col-sm-5" style="margin-top: 40pt;">
        <?= yii\authclient\widgets\AuthChoice::widget([
            'baseAuthUrl' => ['site/auth'],
            'popupMode' => false,
            'options'=>['style'=>'']
       ]) ?>
        <style type="text/css">
            .auth-client{overflow: hidden;}
        </style>
        </div>
    </div>
    
    

<?php
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

/* @var $this yii\web\View */
/* @var $form yii\bootstrap\ActiveForm */
/* @var $model app\models\LoginForm */

$this->title = 'Login';
$this->params['breadcrumbs'][] = $this->title;
$mdetect = new Mobile_Detect;
$is_mobile = ($mdetect->isMobile() && !$mdetect->isTablet());
?>
<div class="site-login col-md-9" style="margin-top: 10px;padding-left: 20px;">
    <div style="margin-left: 10px;"><h1><?= Html::encode($this->title) ?></h1></div>

<!--    <p>Please fill out the following fields to login:</p>-->

    <?php $form = ActiveForm::begin([
        'id' => 'login-form',
        'options' => ['class' => 'form-horizontal'],
        'fieldConfig' => [
            'template' => "{label}\n<div class=\"col-md-3\">{input}</div>\n<div class=\"col-md-8\">{error}</div>",
            'labelOptions' => ['class' => 'col-md-1 control-label'],
        ],
    ]); ?>

    <?= $form->field($model, 'username') ?>

    <?= $form->field($model, 'password')->passwordInput() ?>
    <?= Html::a('Forgot your Password?',['site/forgotpassword'],
            ['class'=>'col-md-4','style'=>'text-align:right;padding-right:25px;margin-top:10px;']) ?>
    <?= $form->field($model, 'rememberMe', [])->hiddenInput()->label(FALSE) ?>

    <div class="form-group">
        <div class="col-md-offset-1 col-md-11">
            <?= Html::submitButton('Login', ['class' => 'btn btn-primary', 'name' => 'login-button']) ?>
        </div>
    </div>
    


    <?php ActiveForm::end(); ?>
        <br>
        <?= yii\authclient\widgets\AuthChoice::widget([
            'baseAuthUrl' => ['site/auth'],
            'popupMode' => false,
            'options'=>['style'=>'']
       ]) ?>
        <style type="text/css">
            .auth-client{overflow: hidden;}
        </style>
    <?php 
        if(isset($model->msg) && $model->msg!==''){
            echo '<div style="padding: 20px;"><span style="color:red; font-size: medium; line-height: 1.5;">'.$model->msg.'</span></div>';
        }
        
    if($is_mobile){
        echo '    <div class="row col-md-12">
        <span id="siteseal" style="">
            <script type="text/javascript" src="https://seal.godaddy.com/getSeal?sealID=qUA3NGyn2slBN23r8CuqZNqgMlaVYTJKn29KseMf8nEj9JrXuHTSTUsyL8yO"></script>
        </span>
    </div></div>';
    }else{
        echo '</div>
                <div class="row col-md-12" style="bottom:20px; display: block; height: auto;position: absolute;">
                    <span id="siteseal" style="padding: 20px;margin: 10px;">
                        <script type="text/javascript" src="https://seal.godaddy.com/getSeal?sealID=qUA3NGyn2slBN23r8CuqZNqgMlaVYTJKn29KseMf8nEj9JrXuHTSTUsyL8yO"></script>
                    </span>
                </div>';
    }
    

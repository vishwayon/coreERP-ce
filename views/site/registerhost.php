<?php
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

/* @var $this yii\web\View */
/* @var $form yii\bootstrap\ActiveForm */
/* @var $model app\models\LoginForm */

$this->title = 'Register Host';

?>
<div class="site-login col-md-9" style="margin-top: 10px;padding-left: 20px;">
    <div style="margin-left: 10px;"><h1><?= Html::encode($this->title) ?></h1></div>

    

    <?php $form = ActiveForm::begin([
        'id' => 'register-host',
        'options' => ['class' => 'form-horizontal'],
        'fieldConfig' => [
            'template' => "{label}\n<div class=\"col-md-4\">{input}</div>\n<div class=\"col-md-8\">{error}</div>",
            'labelOptions' => ['class' => 'col-md-2 control-label'],
        ],
    ]); ?>

    <?= $form->field($model, 'mac_name') ?>
    <?= $form->field($model, 'mac_id')->textInput(['id' => 'mac_id']) ?>
    <?= $form->field($model, 'platform')->textInput(['id' => 'platform']) ?>
    <?= $form->field($model, 'user_agent') ?>

    <?= $form->field($model, 'pass')->passwordInput() ?>

    <div class="form-group">
        <div class="col-md-offset-1 col-md-11">
            <button type="button" class="btn btn-primary" name="register-button" onclick="register_click()">Register</button>
            
        </div>
    </div>
    


    <?php ActiveForm::end(); ?>
        
    <?php 
        if(isset($model->msg) && $model->msg!==''){
            echo '<div style="padding: 20px;"><span style="color:red; font-size: medium; line-height: 1.5;">'.$model->msg.'</span></div>';
        }
    ?>
    <script type="text/javascript">
        function register_click() {
            var req = indexedDB.open("coreERP");
            req.onupgradeneeded = function() {
                // This is called only the first time
                var db = req.result;
                var store = db.createObjectStore("auth-key", { keyPath: "keyId" });
            }
            
            req.onsuccess = function() {
                var db = req.result;
                var txn = db.transaction(["auth-key"], "readwrite");
                var store = txn.objectStore("auth-key");
                store.put({ keyId: "mac_id", keyVal: $('#mac_id').val() });
                store.put({ keyId: "platform", keyVal: navigator.platform });
            }
        }
    </script>
    

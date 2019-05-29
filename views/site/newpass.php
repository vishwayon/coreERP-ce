<?php
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;
?>
<div class="row">
    <div class="col-lg-9"  style="margin-top: 10px;padding-left: 20px;">
    <h3 style="padding-left: 0px;">Reset Password</h3>
        <?php $form = ActiveForm::begin(['id' => 'pwreset-form',
        'options' => ['class' => 'form-horizontal'],
        'fieldConfig' => [
            'template' => "{label}\n<div class=\"col-lg-3\">{input}</div>\n<div class=\"col-lg-8\">{error}</div>",
            'labelOptions' => ['class' => 'col-lg-1 control-label'],
        ]]); ?>
            <?= $form->field($model,'id')->hiddenInput()->label('')?>
            <?= $form->field($model, 'passkey')->label('New Password')->passwordInput() ?>
            <?= $form->field($model, 'passkey2')->label('Confirm Password')->passwordInput() ?>
            <div style='color: #a94442;'>
            <?php 
                if(count($model->error)>0){
                    echo '<br/><p style="margin-left:5px;">Please fix the following issues:</p><ul>';
                    foreach($model->error as $err){
                        echo '<li>'.$err.'</li>';
                    }                
                    echo '</ul>';
                }
            ?>
            </div>
            <div class="form-group">
                <div class="col-lg-offset-1 col-lg-11">
                    <?= Html::submitButton('Reset Password', 
                        ['class' => 'btn btn-primary', 'name' => 'contact-button']) ?>
                </div>
            </div>
        <?php ActiveForm::end(); ?>
    </div>
</div>
<script type="text/javascript">
//$('body').on('beforeSubmit', ‘form#pwreset-form', function () {
//     var form = $(this);
//     // return false if form still have some validation errors
//     if (form.find('.has-error').length) {
//          return false;
//     }
//     // submit form
//     $.ajax({
//          url: form.attr('action'),
//          type: 'post',
//          data: form.serialize(),
//          success: function (response) {
//               // do something with response
//          }
//     });
//     return false;
//});
</script>
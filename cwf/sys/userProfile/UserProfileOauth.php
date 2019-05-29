<?php

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

?>
<div class="row" style="width:100%;margin:0;">
    <style scoped>
        h4{ margin: 2px;}
        .auth-clients{margin:0; padding:0;}
        #brokenrules{margin:0;}
    </style>
    <div class="row" style="margin: 10px;">
        <div style="padding: 5px 10px; background-color: lightgray; border-radius: 5px; margin-bottom: 15px;">
            <h4><i class="fa fa-user-plus" aria-hidden="true"></i> Single Sign-on</h4>
        </div>
        <?= \app\cwf\vsla\security\AuthChoice::widget([
                'baseAuthUrl' => ['usersettings/oauth'],
           ]) ?>    
    </div>
    <div class="row" style="margin: 10px;">
        <div style="padding: 5px 10px; background-color: lightgray; border-radius: 5px;">
            <h4><i class="fa fa-key" aria-hidden="true"></i>
             Change Password</h4>
        </div>
        <div id="divbrule" style="margin-top:15px;color:maroon;display: none;">
            <ul id="brokenrules"></ul>
        </div>
        <div class="col-md-4" style="margin: 10px 0 5px 15px;">
            <?php $form = ActiveForm::begin([
                'id' => 'change-pass-form'
                ]); 
            ?>
            <?= $form->field($userPass, 'full_user_name')->textInput(['readonly' => true]) ?>
            <?= $form->field($userPass, 'password')->passwordInput() ?>
            <div class="form-group field-userpasswordmodel-new_password required">
                <label class="control-label" for="userpasswordmodel-new_password">New Password</label>
                <input type="password" id="userpasswordmodel-new_password" class="form-control" name="UserPasswordModel[new_password]" value="" data-bind="value: pwd">
                <p class="help-block help-block-error"></p>
            </div>

            <div class="form-group field-userpasswordmodel-confirm_password required">
                <label class="control-label" for="userpasswordmodel-confirm_password">Confirm Password</label>
                <input type="password" id="userpasswordmodel-confirm_password" class="form-control" name="UserPasswordModel[confirm_password]" value="" data-bind="value: pwd_repeat">
                <p class="help-block help-block-error"></p>
            </div>

            <?= Html::button('Change Password', ['class' => 'btn btn-primary col-3', 'name' => 'btn-change-pass', 'onclick'=>'core_sys.user_profile.submitChangePass()']) ?>
            <?php ActiveForm::end(); ?>
        </div>
        <div class="col-md-1"></div>
        <div class="col-md-4" id="pwd_validator" style="margin-top:30px;" data-bind="visible: visibility()">
            <ul class="list-group">
                <li class="list-group-item">
                    <span class="glyphicon" data-bind="css: min_8char() ? 'glyphicon-ok' : ''" style="padding-right: 3px;"></span>
                    Between 8-14 characters
                </li>
                <li class="list-group-item">
                    <span class="glyphicon" data-bind="css: min_1upper() ? 'glyphicon-ok' : ''" style="padding-right: 3px;"></span>
                    Minimum 1 uppercase character
                </li>
                <li class="list-group-item">
                    <span class="glyphicon" data-bind="css: min_1lower() ? 'glyphicon-ok' : ''" style="padding-right: 3px;"></span>
                    Minimum 1 lowercase character
                </li>
                <li class="list-group-item">
                    <span class="glyphicon" data-bind="css: min_1numb() ? 'glyphicon-ok' : ''" style="padding-right: 3px;"></span>
                    Minimum 1 number
                </li> 
                <li class="list-group-item">
                    <span class="glyphicon" data-bind="css: min_1splchar() ? 'glyphicon-ok' : ''" style="padding-right: 3px;"></span>
                    Minimum 1 special char [!@#$%^&*]
                </li>
                <li class="list-group-item">
                    <span class="glyphicon" data-bind="css: repeat() ? 'glyphicon-ok' : ''" style="padding-right: 3px;"></span>
                    Password Matched
                </li>
            </ul>
        </div>
    </div>
    <script src="<?= \app\cwf\vsla\utils\ScriptHelper::registerScript('@app/cwf/sys/userProfile/userProfile_clientcode.js') ?>"></script>
</div>

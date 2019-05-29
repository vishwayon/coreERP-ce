
<div class="site-login col-md-12" style="margin-top: 10px;padding-left: 20px;">
    <h3 style="padding-left: 0px;">Change Password</h3>
    <form id="pwreset-form" class="form-horizontal" method="post" action="?r=site/pwd-force-change" style="<?= $redirect ? 'display: none;' : '' ?>">
        <input type="hidden" id="_csrf" name="_csrf" value="<?=\Yii::$app->request->csrfToken?>">
        <input type="hidden" id="token" name="token" value="<?=$token?>">
        <br/><p>Your password has expired. Kindly enter a <b>new</b> password <br/>
        <div class="col-md-6">
            <div class="form-group field-pwd required" style="margin-top: 20px;">
                <label class="col-md-4 control-label" for="pwd">New Password</label>
                <div class="col-md-8">
                    <input id="pwd" data-validation="required"
                           data-validation-error-msg="Kindly enter new password."
                           class="form-control" type="password" name="pwd"
                           data-bind="value: pwd">
                </div>
                <div class="col-md-8">
                    <span class="form-error"></span>
                </div>
            </div>
            <div class="form-group field-pwd_repeat required" style="margin-top: 20px;">
                <label class="col-md-4 control-label" for="pwd">Repeat Password</label>
                <div class="col-md-8">
                    <input id="pwd_repeat" data-validation="required"
                           data-validation-error-msg="Repeat new password."
                           class="form-control" type="password" name="pwd_repeat"
                           data-bind="value: pwd_repeat">
                </div>
                <div class="col-md-8">
                    <span class="form-error"></span>
                </div>
            </div>
            <div class="row col-md-12">
                <div class="form-group" style="margin-top: 20px;">
                    <div class="col-md-offset-3 col-md-2">
                        <button class="btn btn-primary" name="submit-button" 
                                type="submit" onclick="$.validate();"
                                data-bind="enable: isValid">Submit</button>
                    </div>
                </div>
                <br><span style="font-size: medium; color: red;"><b><?=$msg?> </b></span><br><br>
            </div>
        </div>
        <div class="col-md-6">
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
    </form>
    <?= $this->registerJsFile(app\cwf\vsla\utils\ScriptHelper::registerScript('@app/views/site/csite.js'), ['depends' => 'app\assets\AppAsset']) ?>
    
    <div class="col-md-12" style="<?= $redirect != TRUE ? 'display: none;' : '' ?>">
        <br><span style="font-size: medium; color: red;"><b><?=$msg?> </b>. Click <a href="<?=  yii\helpers\Url::home() ?>">here</a> to login with new password.</span><br><br>
    </div>
    
</div>

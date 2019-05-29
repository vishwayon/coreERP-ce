<div class="site-login col-md-9" style="margin-top: 10px;padding-left: 20px;">
    <h3 style="padding-left: 0px;">Reset Password</h3>
    <form id="pwreset-form" class="form-horizontal" method="post" action="?r=site/reqresetpass">
    <input type="hidden" id="_csrf" name="_csrf" value="<?=\Yii::$app->request->csrfToken?>">
        <br/><p>Please enter your username.<br/>
    <div class="form-group field-username required" style="margin-top: 20px;">
        <label class="col-lg-1 control-label" for="username">Username</label>
        <div class="col-lg-3">
            <input id="username" data-validation="required"
                   data-validation-error-msg="Kindly enter your username."
                   class="form-control" type="text" name="username">
        </div>
        <div class="col-lg-8">
            <span class="form-error"></span>
        </div>
    </div>
    <div class="form-group" style="margin-top: 20px;">
        <div class="col-lg-offset-1 col-lg-11">
            <button class="btn btn-primary" name="reset-button" 
                    type="submit" onclick="$.validate();">Request reset link</button>
        </div>
    </div>
    <br/>A password reset link will be sent to your email address mentioned in account information.</p>
    </form>
</div>

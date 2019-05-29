<div class="site-login col-md-9" style="margin-top: 10px;padding-left: 20px;">
    <h3 style="padding-left: 0px;">2-Step Authentication</h3>
    <form id="pwreset-form" class="form-horizontal" method="post" action="?r=site/otp">
    <input type="hidden" id="_csrf" name="_csrf" value="<?=\Yii::$app->request->csrfToken?>">
    <input type="hidden" id="token" name="token" value="<?=$token?>">
    <input type="hidden" id="email" name="email" value="<?=$email?>">
    <br/><p>An OTP was sent to your registered email <b><?=$email?></b><br/>
    <div class="form-group field-otp required" style="margin-top: 20px;">
        <label class="col-lg-1 control-label" for="otp">OTP</label>
        <div class="col-lg-3">
            <input id="otp" data-validation="required"
                   data-validation-error-msg="Kindly enter your OTP."
                   class="form-control" type="text" name="otp">
        </div>
        <div class="col-lg-8">
            <span class="form-error"></span>
        </div>
    </div>
    <div class="form-group" style="margin-top: 20px;">
        <div class="col-lg-offset-1 col-lg-11">
            <button class="btn btn-primary" name="submit-button" 
                    type="submit" onclick="$.validate();">Submit OTP</button>
        </div>
    </div>
    <br><span style="font-size: medium; color: red;"><b><?=$msg?> </b></span><br><br>The OTP is valid only for 10 minutes
    </form>
</div>

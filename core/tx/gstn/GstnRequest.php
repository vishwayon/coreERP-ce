<div id="contentholder" class="view-min-width view-window2">
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row cformheader">
            <h3>GSTN Authentication</h3>
        </div>
        <script type="application/javascript" src="<?php echo \app\cwf\vsla\utils\ScriptHelper::registerScript('@app/core/tx/gstn/gstn_clientcode.js') ?>"></script>
        <div class="row" id="rptrow1" style="<?= ($res->session_exists ? "display:none;" : "") ?>">
            <div> 
                <form class="col-md-12" id="reqotp" name ="reqotp">
                    <input type="hidden" id="_csrf" name="_csrf" value="<?= Yii::$app->request->csrfToken ?>">
                    <input type="hidden" name="reqtime" id="reqtime" value="<?php echo time() ?>" />
                    <div class="form-group col-md-2">
                        <label class="control-label" for="username">GSTN Username</label>
                        <input id="username" class="textbox form-control" name="username" type="text">
                    </div>
                    <div class="form-group col-md-2">
                        <label class="control-label" for="statecd">GSTN State Code</label>
                        <input id="statecd" class="textbox form-control" name="statecd" type="text" readonly="true"
                               value="<?= (app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gst_state_code']) ?>">
                    </div>
                    <a id="cmd_reqotp" name="cmd_reqotp" class="btn btn-sm btn-default col-md-1" 
                       style="margin-left: 20px;margin-top: 20px;" onclick="gstn_req.gstn_req_otp_click();">
                        Request OTP
                    </a>
                    <div id="div_res_reqotp" class="form-group col-md-4" style="margin-left: 30px;margin-top: 30px;display: none;">
                        <label id="res_reqotp" style="color: brown;"></label>
                    </div>
                </form>
            </div>
        </div>
        <div class="row" id="rptrow2" style="display: none;">
            <div> 
                <form class="col-md-12" id="reqtoken" name ="reqtoken">
                    <input type="hidden" id="_csrf" name="_csrf" value="<?= Yii::$app->request->csrfToken ?>">
                    <input type="hidden" name="reqtime" id="reqtime" value="<?php echo time() ?>" />
                    <input type="hidden" id="gstn_username" name="gstn_username" value="">
                    <input type="hidden" id="gstn_statecd" name="gstn_statecd" value="">
                    <input type="hidden" id="gstn_txn" name="gstn_txn" value="">
                    <input type="hidden" id="gstn_ip" name="gstn_ip" value="">
                    <div class="form-group col-md-1">
                        <label class="control-label" for="gstn_otp">GSTN OTP</label>
                        <input id="gstn_otp" class="textbox form-control" name="gstn_otp" type="text">
                    </div>                   
                    <a id="cmd_reqtoken" name="cmd_reqtoken" class="btn btn-sm btn-default col-md-2" 
                       style="margin-left: 20px;margin-top: 20px;" onclick="gstn_req.gstn_req_token_click();">
                        Request Auth Token
                    </a>
                    <div id="div_res_reqtoken" class="form-group col-md-4" style="margin-left: 30px;margin-top: 30px;display: none;">
                        <label id="res_reqtoken" style="color: brown;"></label>
                    </div>
                </form>
            </div>
        </div>        
        <div class="row" id="rptrow3" style="margin:0; <?= ($res->session_exists ? "" : "display:none;") ?>">
            <div class="form-group col-md-2">
                <label class="control-label" for="username">GSTN Username</label>
                <input id="username" class="textbox form-control" name="username" type="text" readonly="true" value="<?= ($res->session_exists ? $res->username : '') ?>">
            </div>
            <div class="form-group col-md-2">
                <label class="control-label" for="statecd">GSTN State Code</label>
                <input id="statecd" class="textbox form-control" name="statecd" type="text" readonly="true" value="<?= ($res->session_exists ? $res->statecd : '') ?>">
            </div>
            <div id="div_res_reqtoken" class="form-group col-md-4" style="margin-left: 30px;margin-top: 30px;">
                <label id="res_reqtoken" style="color: darkgreen;">Already authenticated.</label>
            </div>
        </div>
        <div class="row" id="rptrow4" style="<?= ($res->session_exists ? "" : "display:none;") ?>">
            <div> 
                <form class="col-md-12" id="refreshtoken" name ="refreshtoken">
                    <input type="hidden" id="_csrf" name="_csrf" value="<?= Yii::$app->request->csrfToken ?>">
                    <input type="hidden" name="reqtime" id="reqtime" value="<?php echo time() ?>" />
                    <input type="hidden" id="gstn_username" name="gstn_username" value="<?= property_exists($res, 'username') ? $res->username : '' ?>">
                    <input type="hidden" id="gstn_statecd" name="gstn_statecd" value="<?= property_exists($res, 'statecd') ? $res->statecd : '' ?>">
                    <input type="hidden" id="gstn_txn" name="gstn_txn" value="<?= property_exists($res, 'txn') ? $res->txn : '' ?>">
                    <input type="hidden" id="gstn_ip" name="gstn_ip" value="<?= property_exists($res, 'ipaddress') ? $res->ipaddress : '' ?>">                  
                    <a id="cmd_refreshtoken" name="cmd_reqtoken" class="btn btn-sm btn-default col-md-2" 
                       style="margin-left: 20px;margin-top: 20px;" onclick="gstn_req.gstn_refresh_token_click();">
                        Refresh Auth Token
                    </a>
                    <div id="div_res_refreshtoken" class="form-group col-md-4" style="margin-left: 30px;margin-top: 30px;display: none;">
                        <label id="res_refreshtoken" style="color: brown;"></label>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
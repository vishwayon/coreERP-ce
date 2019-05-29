<div class="row" id="rptrow7" style="margin:0;border-top: 1px solid grey;">
    <div style="margin-top:5px;"> 
        <form class="col-md-12" id="reqgstr2a" name ="reqgstr2a" style="margin-left:0;">
            <input type="hidden" id="_csrf" name="_csrf" value="<?= Yii::$app->request->csrfToken ?>">
            <input type="hidden" name="reqtime" id="reqtime" value="<?php echo time() ?>" />
            <input type="hidden" id="gstn_txn" name="gstn_txn" value="<?= ($res->session_exists ? $res->txn : '') ?>">
            <input type="hidden" id="username" class="textbox form-control" name="username" type="text" readonly="true" 
                   value="<?= ($res->session_exists ? $res->username : '') ?>">
            <input type="hidden" id="statecd" class="textbox form-control" name="statecd" type="text" readonly="true" 
                   value="<?= (app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gst_state_code']) ?>">
            <input type="hidden" id="gstin" class="textbox form-control" name="gstin" type="text" readonly="true"
                   value="<?= (app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gstin']) ?>">
            <input type="hidden" id="retperiod" class="textbox form-control" name="retperiod" 
                   type="text" data-bind="value:ret_period" disabled="true">
            <a id="cmd_reqgstr2a" name="cmd_reqotp" class="btn btn-sm btn-default col-md-3" 
               style="margin: 0 25px 0 25px;<?= ($res->session_exists ? "" : "display:none;") ?>" 
               onclick="core_tx.gstr2aReco.gstr2a_req_resp_click();">
                GSTN Request/View GSTR2A
            </a>
            <!-- All additional fields for the user to set extracted from xml and rendered -->
            <input type="file" id="gstr_resp_file" name="gstr_resp_file" class="btn btn-sm btn-default col-md-3"
                   accept=".json"/>
            <a id="cmd_recgstr2a" class="btn btn-sm btn-default col-md-2" style="margin-left: 20px;" onclick="core_tx.gstr2aReco.upload_2a_click();">
                Reconcile Selected File
            </a>
            <div id="div_res_reqgstr2a" class="form-group col-md-4" style="margin-left: 30px;display: none;">
                <label id="res_reqgstr2a" style="color: brown;"></label>
            </div>
            <a id="btn_reco_print" class="btn btn-sm btn-default col-md-1" onclick="core_tx.gstr2aReco.printClick();" style="margin-left: 15px;display: none;width:100px;">Print Summary</a>
        </form>
    </div>
</div>    


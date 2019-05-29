<div class="row" id="rptrow2" style="margin:5px;border-top: 1px solid grey;">
    <div style="margin-top:5px;">
        <div class="col-md-12" id="fileupload" name ="fileupload">
            <!-- This is the csrf field --> 
            <input type="hidden" id="_csrf" name="_csrf" value="<?= Yii::$app->request->csrfToken ?>">
            <!-- The following are hidden compulsory fields, extracted from xml and rendered -->
            <input type="hidden" name="reqtime" id="reqtime" value="<?php echo time() ?>" />
            <!-- All additional fields for the user to set extracted from xml and rendered -->
            <input type="file" id="gstr_resp_file" name="gstr_resp_file" class="btn btn-sm btn-default col-md-3"
                   accept=".json"/>
            <a class="btn btn-sm btn-default col-md-2" style="margin-left: 20px;" onclick="core_tx.gstr2aReco.upload_2a_click();">
                Reconcile Selected File
            </a>
        </div>
    </div>
</div>

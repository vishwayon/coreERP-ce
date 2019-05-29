<style>#cboformbodyin{border:none;}</style>
<div id="contents" style="margin: 10px 10px 10px; padding-right: 5px;">
    <script type="application/javascript" src="<?php echo \app\cwf\vsla\utils\ScriptHelper::registerScript('@app/core/tx/vatUpload/vu_clientcode.js') ?>"></script>
    <?php $viewerurl = '?r=core/tx/VatUpload'; ?>
    <div class="row" id="rptrow1">
        
    </div>
    <div class="row" id="rptrow2">
        <div> 
            <form class="col-md-10" id="rptOptions" name ="rptOptions" method="POST" action="<?= (string) $viewerurl ?>" target="rptContainer" >
                <!-- This is the csrf field --> 
                <input type="hidden" id="_csrf" name="_csrf" value="<?= Yii::$app->request->csrfToken ?>">
                <!-- The following are hidden compulsory fields, extracted from xml and rendered -->
                <input type="hidden" name="reqtime" id="reqtime" value="<?php echo time() ?>" />
                <!-- All additional fields for the user to set extracted from xml and rendered -->
                <?php
                    echo $viewForRender->getForm();
                ?>
            </form>
            <div class=" col-md-2 form-group" style="margin-top: 15px; padding-left: 0px; padding-right: 0px; margin-bottom: 5px;">
                <div style="white-space: nowrap"></div>
                <button class="btn btn-sm btn-default"
                        onclick="tx_vu.generate_click();">
                  Generate Data          
                </button>
            </div>
        </div>
    </div>
    <div id="rptRoot" style="padding: 5px;">
        <div><textarea id="fileName"></textarea> </div>
    </div>
    <script type="text/javascript" >
        window.tx_vu.applySmartControls();
    </script>
</div>



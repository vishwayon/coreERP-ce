<div id="contentholder"  class="view-min-width view-window1">
    <style>#cboformbodyin{border:none;}</style>
    <div id="contents" style="margin: 10px 10px 10px; padding-right: 5px;">
        <script type="application/javascript" src="<?php echo \app\cwf\vsla\utils\ScriptHelper::registerScript('@app/core/ac/utilities/pdc/Pdc_clientcode.js') ?>"></script>
        <?php $viewerurl = '?r=core/ac/utilities/pdc'; ?>
        <div id="collheader" class="row">
            <h3>Post Dated Cheque Details</h3>
        </div>
        <div id="collfilter" class="row" id="rptrow2">
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
                            onclick="ac_utilities_pdc.generate_click();">
                        Refresh          
                    </button>
                </div>
            </div>
        </div>
        <div id="collectiondata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;overflow-x: auto">            

        </div>
        <script type="text/javascript" >
            window.ac_utilities_pdc.applySmartControls();
        </script>
    </div>
</div>
<div id="details" class="view-min-width view-window2" style="display: none;">
</div>
<style>#cboformbodyin{border:none;}</style>
<div id="contents" style="margin: 10px 10px 10px; background-color: white;padding: 20px;border-radius: 20px;">
    <script type="application/javascript" src="<?php echo \app\cwf\vsla\utils\ScriptHelper::registerScript('@app/cwf/fwShell/views/dataset_clientcode.js') ?>"></script>
    <?php $viewerurl = '?r=cwf%2FfwShell%2Fdataset'; ?>
    <div class="row" id="rptrow1" style="margin-bottom: 20px;">
        <h3 class="col-md-6" style="margin-top: 2px" id="rptCaption"><?= $viewForRender->getHeader() ?></h3>
        <div class="btn-group" role="group" style="float: right; margin-right: 20px;">
            <input type="hidden" id="modelData" value="<?php echo \yii\helpers\BaseHtml::encode($rptOptions) ?>"/>
            <i id="afterRefreshEventHandler" value="<?php echo $viewForRender->getProperty('afterRefreshEvent') ?>" />
            <button  id="btnrefresh" class="btn btn-default" onclick="cwf_dataset.downloadClick();">
                <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span> Download
            </button>
        </div>
    </div>
    <div class="row" id="rptrow5" class="col-md-12" style="">
        <h5 id="ds_desc" style="margin-left: 50px;"><?= $viewForRender->getProperty('description') ?></h5>
    </div>
    <div class="row" id="rptrow2" style="margin-top: 20px;">
        <div> 
            <form class="col-md-12" id="rptOptions" name ="rptOptions" method="POST" action="<?= (string) $viewerurl ?>" target="rptContainer" >
                <!-- This is the csrf field --> 
                <input type="hidden" id="_csrf" name="_csrf" value="<?= Yii::$app->request->csrfToken ?>">
                <!-- The following are hidden compulsory fields, extracted from xml and rendered --> 
                <input type="hidden" name="xmlPath" id="xmlPath" value="<?php echo $xmlPath ?>" />
                <input type="hidden" name="reqtime" id="reqtime" value="<?php echo time() ?>" />
                <!-- All additional fields for the user to set extracted from xml and rendered --> 
                <?php
                echo $viewForRender->getForm();
                ?>
            </form>
        </div>
    </div>
    <div id="rptRoot" style="padding: 5px;">

    </div>
    <script type="text/javascript" >
        window.cwf_dataset.bindModel();
    </script>
</div>

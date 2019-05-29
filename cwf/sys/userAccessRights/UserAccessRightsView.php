<div id="contentholder"  class="view-min-width view-window1">
    <style>#cboformbodyin{border:none;}</style>
    <div id="contents" style="margin: 10px 10px 10px; padding-right: 5px;">
        <script type="application/javascript" src="<?php echo \app\cwf\vsla\utils\ScriptHelper::registerScript('@app/cwf/sys/userAccessRights/UserAccessRights_clientcode.js') ?>"></script>
        <?php $viewerurl = '?r=cwf/sys/userAccessRights'; ?>
        <div id="collheader" class="row">
            <h3>User Access Rights</h3>
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
                            onclick="core_sys.user_access_rights.generate_click();">
                        Refresh          
                    </button>
                    <button id="btnprint" class="btn btn-default" disabled="" onclick="coreWebApp.showPrint();">
                            <span class="glyphicon glyphicon-print" aria-hidden="true"></span> Print
                    </button>
                </div>
            </div>
        </div>
        <div id="collectiondata" class="row" style="display: none;margin-top: 10px;margin-left: 0px;margin-right: 0px;overflow-x: auto">            
        <div id="divprintdata" name="divprintdata" style="display:none;" printurl="?r=cwf%2FfwShell%2Fjreport">
            
        <input type="hidden" id="divp__csrf" name="divp__csrf" value="YWhHVUZ0b3UmICViKzs1GBQuJGEzMT8lLD4JJjFAWAETIDITDy4KAA==">
        <input type="hidden" id="rpt_nondoc" name="rpt_nondoc" value="rpt_nondoc">
        <div style="margin-top:2px;">
            <input type="radio" name="printact" style="margin-left:10px;" value="print" onclick="printoptchange()" checked=""><span> Print</span>
        </div>
        <div style="margin-top:10px;">
            <input type="radio" name="printact" style="margin-left:10px;" value="export" onclick="printoptchange()"><span> Export</span>            
            <div id="expopt" style="display: none; margin: 5px 0 5px 32px;">
                <select id="btn-export-option" style="margin-left:10px;width:127px;">
                    <option value="pdf" selected="">PDF</option>
                    <option value="ms-doc">MS-Word/docx</option>
                    <option value="ms-xls">MS-Excel/xlsx</option>
                    <option value="open-doc">Open-Writer/odt</option>
                    <option value="open-calc">Open-Calc/ods</option>
                </select>
                <input type="checkbox" id="cwf_data_only" name="cwf_data_only" value="true">
                <span style="margin: 0 10px 0 0;">Data Only</span>
            </div>
        </div>
        
        
        <div id="divprintdata" name="divprintdata" style="display:none;" printurl="?r=cwf%2FfwShell%2Fjreport">
            <input type="hidden" id="divp__csrf" name="divp__csrf" value="YW1NajYzT3omJS9dW3wVFxQrLl5Ddh8qLDsDGUEHeA4TJTgsf2kqDw==">
            <input type="hidden" id="rpt_nondoc" name="rpt_nondoc" value="rpt_nondoc">
            <div style="margin-top:2px;">
                <input type="radio" name="printact" style="margin-left:10px;" value="print" onclick="printoptchange()" checked=""><span> Print</span>
            </div>        
            <div style="margin-top:10px;">
                <a href="#" id="btn-print-cancel" onclick="$('#divprintdata').dialog('destroy');" name="btn-print-cancel" class="btn btn-default" style="float: left; padding: 3px 6px; width: 70px;"><span>Cancel</span></a>
                <a href="#" id="btn-print-ok" onclick="cwf_jrpt.printDialogSubmit()" name="btn-print-ok" class="btn btn-default" style="float: right; padding: 3px 6px; width: 50px;"><span>OK</span></a>
            </div>
        </div>
 
        
        
        
        <div id="mailopt" style="margin-top:10px;">
            <input type="radio" name="printact" style="margin-left:10px;" value="email" onclick="printoptchange()"><span id="emailrptlbl"> Email</span>
            <!--div id="mailopts" style="display: none;">
                <span style="margin-left:32px;">Send to</span><input id="mail_send_to" style="width:70%;margin-left:13px;margin-top:5px;"/>
                <span style="margin-left:32px;">CC to</span><input id="mail_cc_to" style="width:70%;margin-left:25px;margin-top:5px;"/>
                <span style="margin-left:32px;">Subject</span><input id="mail_subject" style="width:70%;margin-left:14px;margin-top:5px;"/>
            </div-->
        </div>
        <script type="text/javascript">function printoptchange() {
                if ($('input[name=printact]:checked').val() == "email") {
                    $("#expopt").hide();
                    //$("#mailopts").show();
                    //cwf_jrpt.emailClick();
                } else if ($('input[name=printact]:checked').val() == "export") {
                    $("#expopt").show();
                    $("#mailopts").hide();
                } else {
                    $("#expopt").hide();
                    $("#mailopts").hide();
                }
            }</script>
        <div style="margin-top:10px;">
            <a href="#" id="btn-print-cancel" onclick="$('#divprintdata').dialog('destroy');" name="btn-print-cancel" class="btn btn-default" style="float: left; padding: 3px 6px; width: 70px;"><span>Cancel</span></a>
            <a href="#" id="btn-print-ok" onclick="cwf_jrpt.printDialogSubmit()" name="btn-print-ok" class="btn btn-default" style="float: right; padding: 3px 6px; width: 50px;"><span>OK</span></a>
        </div>
    </div>
        </div>
        <script type="text/javascript" >
            window.core_sys.user_access_rights.applySmartControls();
        </script>
    </div>
</div>
<div id="details" class="view-min-width view-window2" style="display: none;">
</div>
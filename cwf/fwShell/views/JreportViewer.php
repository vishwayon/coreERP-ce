<style>#cboformbodyin{border:none;}</style>
<div id="contents" style="margin: 10px 10px 10px; padding-right: 5px;">
    <script type="application/javascript" src="<?php echo \app\cwf\vsla\utils\ScriptHelper::registerScript('@app/cwf/fwShell/views/jrpt_clientcode.js') ?>"></script>
    <?php $viewerurl = '?r=cwf%2FfwShell%2Fjreport'; ?>
    <div class="row" id="rptrow1">
        <button  id="btnoptions" onclick="cwf_jrpt.expandOptions();" class="col-md-1 btn btn-default" style="margin-left: 15px">Options</button>
        <h3 class="col-md-6" style="margin-top: 2px" id="rptCaption"><?= $viewForRender->getHeader() ?></h3>
        <h6 class="col-md-6" style="margin-top: 6px; color: red; display: none;" id="print-limit-msg">Warning! Partial report rendered</h6>
        <div class="btn-group" role="group" style="float: right; margin-right: 20px;">
            <input type="hidden" id="modelData" value="<?php echo \yii\helpers\BaseHtml::encode($rptOptions) ?>"/>
            <i id="afterRefreshEventHandler" value="<?php echo $viewForRender->getProperty('afterRefreshEvent') ?>" />
            <button  id="btnrefresh" class="btn btn-default" onclick="cwf_jrpt.refreshClick();">
                <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span> Refresh
            </button>
            <button id="btnprint" class="btn btn-default" disabled onclick="coreWebApp.showPrint();">
                <span class="glyphicon glyphicon-print" aria-hidden="true"></span> Print
            </button>
            <!--            <button id="btnprint" class="btn btn-default" disabled onclick="cwf_jrpt.printClick();">
                            <span class="glyphicon glyphicon-print" aria-hidden="true"></span> Print
                        </button>
                        <div class="btn-group" role="group">
                            <button id="btnprintoptions" type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-right">
                                <li id="lisubscr" style="border-bottom: 1px solid lightgray;display:none;">
                                    <a id="btnsubscr" type="button" onclick="cwf_jrpt.enableSubscr();">Subscribe</a>
                                </li>
                                <li>
                                    <a href="#" onclick="cwf_jrpt.exportClick($('#export-select').val());">Export 
                                        <select class="form-control" id="export-select" style="margin-top: 5pt;">
                                            <option value="pdf">PDF</option>
                                            <option value="ms-doc">MS-Word/docx</option>
                                            <option value="ms-xls">MS-Excel/xlsx</option>
                                            <option value="open-doc">Open-Writer/odt</option>
                                            <option value="open-calc">Open-Calc/ods</option>
                                        </select></a>
                                </li>                    
                            </ul>
                        </div>-->
        </div>
    </div>
    <div class="row" id="rptrow2">
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
        window.cwf_jrpt.bindModel();
    </script>
    <div id="divprintdata" name="divprintdata" style="display:none;" printurl="?r=cwf%2FfwShell%2Fjreport">
        <input type="hidden" id="divp__csrf" name="divp__csrf" value="<?= \Yii::$app->request->csrfToken ?>">
        <input type="hidden" id="rpt_nondoc" name="rpt_nondoc" value="rpt_nondoc">
        <div style="margin-top:2px;">
            <input type="radio" name="printact" style="margin-left:10px;" value="print" onclick="printoptchange()" checked><span> Print</span>
        </div>
        <div style="margin-top:10px;">
            <input type="radio" name="printact" style="margin-left:10px;" value="export" onclick="printoptchange()"><span> Export</span>            
            <div id="expopt" style="display: none; margin: 5px 0 5px 32px;">
                <select id="btn-export-option" style="margin-left:10px;width:127px;">
                    <option value="pdf" selected>PDF</option>
                    <option value="ms-doc">MS-Word/docx</option>
                    <option value="ms-xls">MS-Excel/xlsx</option>
                    <option value="open-doc">Open-Writer/odt</option>
                    <option value="open-calc">Open-Calc/ods</option>
                </select>
                <input type="checkbox" id="cwf_data_only" name="cwf_data_only" value="true">
                <span style="margin: 0 10px 0 0;">Data Only</span>
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
            <a href="#" id="btn-print-cancel" onclick="$('#divprintdata').dialog('destroy');" name="btn-print-cancel" class="btn btn-default" 
               style="float: left; padding: 3px 6px; width: 70px;"><span>Cancel</span></a>
            <a href="#" id="btn-print-ok" onclick="cwf_jrpt.printDialogSubmit()" name="btn-print-ok" class="btn btn-default" 
               style="float: right; padding: 3px 6px; width: 50px;"><span>OK</span></a>
        </div>
    </div>
    <div id="divmaildata" name="divmaildata" style="display:none;" class="col-md-12" printurl="?r=cwf%2FfwShell%2Fjreport">
        <div class="col-md-12">
            <span class="col-md-3" style="margin-top: 5px;">Send to</span>
            <input id="mail_send_to" class="col-md-9" style="margin-bottom: 5px;"/>
            <span class="col-md-3" style="margin-top: 5px; display: none;">CC to</span>
            <input id="mail_cc_to" class="col-md-9" style="margin-bottom: 5px; display: none;"/>
            <span class="col-md-3" style="margin-top: 5px;">Subject</span>
            <input id="mail_subject" class="col-md-9" style="margin-bottom: 5px;"/>
            <span class="col-md-3" style="margin-top: 5px;">Message</span>
            <textarea rows="3" id="mail_body" class="col-md-9" style="margin-bottom: 5px;"/>
        </div>
        <div class="col-md-12">
            <a href="#" id="btn-mail-cancel" onclick="$('#divmaildata').dialog('destroy');" name="btn-mail-cancel" class="btn btn-default" 
               style="float: left; padding: 3px 6px; width: 70px;"><span>Cancel</span></a>
            <a href="#" id="btn-mail-ok" onclick="cwf_jrpt.docPrintMail();" name="btn-mail-ok" class="btn btn-default" 
               style="float: right; padding: 3px 6px; width: 50px;"><span>OK</span></a>
        </div>
    </div>
</div>

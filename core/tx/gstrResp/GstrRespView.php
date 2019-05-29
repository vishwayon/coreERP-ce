<div id="contentholder" class="view-min-width view-window2">
    <div id="contents" style="padding: 5px;margin:5px;">
        <script type="application/javascript" src="<?php echo \app\cwf\vsla\utils\ScriptHelper::registerScript('@app/core/tx/gstrResp/gr_clientcode.js') ?>"></script>
        <?php $viewerurl = '?r=core/tx/gst-return/response-view'; ?>
        <div id="collheader" class="row cformheader">
            <h3>GSTR Response</h3>
        </div>
        <div class="row" id="rptrow1">

        </div>
        <div class="row" id="rptrow2">
            <div> 
                <form class="col-md-12" id="fileupload" name ="fileupload">
                    <!-- This is the csrf field --> 
                    <input type="hidden" id="_csrf" name="_csrf" value="<?= Yii::$app->request->csrfToken ?>">
                    <!-- The following are hidden compulsory fields, extracted from xml and rendered -->
                    <input type="hidden" name="reqtime" id="reqtime" value="<?php echo time() ?>" />
                    <!-- All additional fields for the user to set extracted from xml and rendered -->
                    <span class="col-md-2 btn-sm" style="padding: 10px;">Select GSTR response file</span>
                    <input type="file" id="gstr_resp_file" name="gstr_resp_file" class="btn btn-sm btn-default col-md-5"
                           accept=".json"/>
                    <a class="btn btn-sm btn-default col-md-2" style="margin-left: 20px;margin-top: 5px;" onclick="tx_gr.gstr_resp_view_click();">
                        View Response
                    </a>
                </form>
            </div>
        </div>
        <div id="rptRoot" style="margin: 5px; border-top: 1px solid gray;">
            <form>
                <div class="row">
                    <div>
                        <h5 style="color: teal;">B2B Errors</h5>
                        <table class="table table-hover table-condensed">
                            <thead>
                                <tr>
                                    <th class="col-md-1">Supplier GSTIN</th>
                                    <th class="col-md-1">Document Dt.</th>
                                    <th class="col-md-1">Document #</th>
                                    <th class="col-md-1">POS</th>
                                    <th class="col-md-1">Rev. Chg.</th>
                                    <th class="col-md-1">Type</th>
                                    <th class="col-md-1">Taxable Value</th>
                                    <th class="col-md-1">Error Code</th>
                                    <th class="col-md-3">Error Message</th>
                                </tr>
                            </thead>
                            <tbody data-bind="template: { name: 'templ_b2b_list', foreach: dt.b2b }">

                            </tbody>
                            <tfoot>
                                <tr data-bind="visible: dt.b2b().length == 0">
                                    <td colspan="6">
                                        <span>** No Errors **</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <script type="text/html" id="templ_b2b_list">
                        <tr>
                            <td class="col-md-1" data-bind="text: supp_ctin"></td>
                            <td class="col-md-1" style="text-align: center" data-bind="text: coreWebApp.formatDate(inv_dt())"></td>
                            <td class="col-md-1" data-bind="text: inv_num"></td>
                            <td class="col-md-1" style="text-align: center" data-bind="text: pos"></td>
                            <td class="col-md-1" style="text-align: center" data-bind="text: rchrg"></td>
                            <td class="col-md-1" style="text-align: center" data-bind="text: inv_typ"></td>
                            <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(taxable_val(), 2)"></td>
                            <td class="col-md-1" style="text-align: center" data-bind="text: error_cd"></td>
                            <td class="col-md-3" data-bind="text: error_msg"></td>
                        </tr>
                        </script>
                    </div>
                    <div class="row">
                        <div>
                            <h5 style="color: teal;">B2CS Errors</h5>
                            <table class="table table-hover table-condensed">
                                <thead>
                                    <tr>
                                        <th class="col-md-1">POS</th>
                                        <th class="col-md-1">Supply Type</th>
                                        <th class="col-md-1">Type</th>
                                        <th class="col-md-1">Rate</th>
                                        <th class="col-md-1">Taxable Value</th>
                                        <th class="col-md-1">Error Code</th>
                                        <th class="col-md-3">Error Message</th>
                                    </tr>
                                </thead>
                                <tbody data-bind="template: { name: 'templ_b2cs_list', foreach: dt.b2cs }">

                                </tbody>
                                <tfoot>
                                    <tr data-bind="visible: dt.b2cs().length == 0">
                                        <td colspan="6">
                                            <span>** No Errors **</span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <script type="text/html" id="templ_b2cs_list">
                            <tr>
                            <td class="col-md-1" style="text-align: center" data-bind="text: pos"></td>
                            <td class="col-md-1" style="text-align: center" data-bind="text: sply_ty"></td>
                            <td class="col-md-1" style="text-align: center" data-bind="text: typ"></td>
                            <td class="col-md-1" style="text-align: center" data-bind="text: coreWebApp.formatNumber(rt(), 2)"></td>
                            <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(txval(), 2)"></td>
                            <td class="col-md-1" style="text-align: center" data-bind="text: error_cd"></td>
                            <td class="col-md-3" data-bind="text: error_msg"></td>
                            </tr>
                            </script>
                        </div>
                        <div class="row">
                        <div>
                            <h5 style="color: teal;">AT Errors</h5>
                            <table class="table table-hover table-condensed">
                                <thead>
                                    <tr>
                                        <th class="col-md-1">POS</th>
                                        <th class="col-md-1">Supply Type</th>
                                        <th class="col-md-1">Rate</th>
                                        <th class="col-md-1">Advance Amt.</th>
                                        <th class="col-md-1">Error Code</th>
                                        <th class="col-md-3">Error Message</th>
                                    </tr>
                                </thead>
                                <tbody data-bind="template: { name: 'templ_at_list', foreach: dt.at }">

                                </tbody>
                                <tfoot>
                                    <tr data-bind="visible: dt.at().length == 0">
                                        <td colspan="6">
                                            <span>** No Errors **</span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <script type="text/html" id="templ_at_list">
                            <tr>
                            <td class="col-md-1" style="text-align: center"data-bind="text: pos"></td>
                            <td class="col-md-1" style="text-align: center" data-bind="text: sply_ty"></td>
                            <td class="col-md-1" style="text-align: center" data-bind="text: coreWebApp.formatNumber(rt(), 2)"></td>
                            <td class="col-md-1" style="text-align: center" data-bind="text: coreWebApp.formatNumber(ad_amt(), 2)"></td>
                            <td class="col-md-1" style="text-align: center" data-bind="text: error_cd"></td>
                            <td class="col-md-3" data-bind="text: error_msg"></td>
                            </tr>
                            </script>
                        </div>

                        <div class="row">
                            <div>
                                <h5 style="color: teal;">CDNR Errors</h5>
                                <table class="table table-hover table-condensed">
                                        <thead>
                                            <tr>
                                                <th class="col-md-1">GSTIN</th>
                                                <th class="col-md-1">Document Dt.</th>
                                                <th class="col-md-1">Document #</th>
                                                <th class="col-md-1">Refund Dt.</th>
                                                <th class="col-md-1">Refund #</th>
                                                <th class="col-md-1">Error Code</th>
                                                <th class="col-md-3">Error Message</th>
                                            </tr>
                                        </thead>
                                        <tbody data-bind="template: { name: 'templ_cdnr_list', foreach: dt.cdnr }">

                                        </tbody>
                                        <tfoot>
                                            <tr data-bind="visible: dt.cdnr().length == 0">
                                                <td colspan="6">
                                                    <span>** No Errors **</span>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                            </div>
                            <script type="text/html" id="templ_cdnr_list">
                                <tr>
                                    <td class="col-md-1" data-bind="text: supp_ctin"></td>
                                    <td class="col-md-1" style="text-align: center" data-bind="text: coreWebApp.formatDate(nt_dt())"></td>
                                    <td class="col-md-1" data-bind="text: nt_num"></td>
                                    <td class="col-md-1" style="text-align: center" data-bind="text: coreWebApp.formatDate(nt_idt())"></td>
                                    <td class="col-md-1" data-bind="text: nt_inum"></td>
                                    <td class="col-md-1" style="text-align: center" data-bind="text: error_cd"></td>
                                    <td class="col-md-3" data-bind="text: error_msg"></td>
                                </tr>
                                </script>
                            </div>
                        </form>
                    </div>
                    <script type="text/javascript" >

                    </script>
                </div>
            </div>
<div id="contentholder" class="view-min-width view-window2">
    <div id="contents" style="padding: 5px;margin:5px;">
        <script type="application/javascript" src="<?php echo \app\cwf\vsla\utils\ScriptHelper::registerScript('@app/core/tx/gstrResp/gr_clientcode.js') ?>"></script>
        <?php $viewerurl = '?r=core/tx/gst-return/response-view'; ?>
        <div id="collheader" class="row cformheader">
            <h3>GSTR-2A Response</h3>
        </div>
        <div class="row" id="rptrow1" style="margin:0;<?= ($res->session_exists ? "":"display:none;") ?>">
            <div> 
                <form class="col-md-12" id="reqgstr2a" name ="reqgstr2a" style="margin-left:0;">
                    <input type="hidden" id="_csrf" name="_csrf" value="<?= Yii::$app->request->csrfToken ?>">
                    <input type="hidden" name="reqtime" id="reqtime" value="<?php echo time() ?>" />
                    <input type="hidden" id="gstn_txn" name="gstn_txn" value="<?= ($res->session_exists ? $res->txn:'')?>">
                    <div class="form-group col-md-2">
                        <label class="control-label" for="username">GSTN Username</label>
                        <input id="username" class="textbox form-control" name="username" type="text" readonly="true" value="<?= ($res->session_exists ? $res->username:'')?>">
                    </div>
                    <div class="form-group col-md-2">
                        <label class="control-label" for="statecd">GSTN State Code</label>
                        <input id="statecd" class="textbox form-control" name="statecd" type="text" readonly="true" 
                            value="<?= (app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gst_state_code']) ?>">
                    </div>
                    <div class="form-group col-md-2">
                        <label class="control-label" for="gstin">GSTIN</label>
                        <input id="gstin" class="textbox form-control" name="gstin" type="text" readonly="true"
                               value="<?= (app\cwf\vsla\security\SessionManager::getBranchGstInfo()['gstin']) ?>">
                    </div>
                    <div class="form-group col-md-2">
                        <label class="control-label" for="retperiod">Return Period</label>
                        <input id="retperiod" class="textbox form-control" name="retperiod" type="text">
                    </div>
                    <a id="cmd_reqgstr2a" name="cmd_reqotp" class="btn btn-sm btn-default col-md-2" 
                       style="margin-left: 5px;margin-top: 20px;" onclick="tx_gr.gstr2a_req_resp_click();">
                        Request GSTR2A
                    </a>
                    <div id="div_res_reqgstr2a" class="form-group col-md-4" style="margin-left: 30px;display: none;">
                        <label id="res_reqgstr2a" style="color: brown;"></label>
                    </div>
                </form>
            </div><div class="col-md-11" style="border-top:1px solid grey; margin:5px 0 10px 5px;"></div>
        </div>
        <div class="row" id="rptrow2" style="margin:0;">
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
                    <a class="btn btn-sm btn-default col-md-2" style="margin-left: 20px;margin-top: 5px;" onclick="tx_gr.gstr2a_resp_view_click();">
                        View Response
                    </a>
                </form>
            </div>
        </div>
        <div id="rptRoot" style="margin: 5px; border-top: 1px solid gray;">
            <form>
                <div class="row">
                    <div>
                        <h5 style="color: teal;">B2B</h5>
                        <table class="table table-hover table-condensed">
                            <thead>
                                <tr>
                                    <th class="col-md-1">Supplier GSTIN</th>
                                    <th class="col-md-1">Supplier</th>
                                    <th class="col-md-1">Doc Date</th>
                                    <th class="col-md-1">Document #</th>
                                    <th class="col-md-1">POS</th>
                                    <th class="col-md-1">Rev. Chg.</th>
                                    <th class="col-md-1">Type</th>
                                    <th class="col-md-1">Invoice Value</th>
                                    <th class="col-md-1">Taxable Value</th>
                                    <th class="col-md-1">Tax Rate</th>
                                    <th class="col-md-1">SGST</th>
                                    <th class="col-md-1">CGST</th>
                                    <th class="col-md-1">IGST</th>
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
                            <td class="col-md-1" data-bind="text: supp_ctin, style: {backgroundColor: supp_name()=='---NOT-FOUND---'?'lightgoldenrodyellow':'white'} "></td>
                            <td class="col-md-1" data-bind="text: supp_name"></td>
                            <td class="col-md-1" style="text-align: center" data-bind="text: coreWebApp.formatDate(inv_dt())"></td>
                            <td class="col-md-1" data-bind="text: inv_num"></td>
                            <td class="col-md-1" style="text-align: center" data-bind="text: pos"></td>
                            <td class="col-md-1" style="text-align: center" data-bind="text: rchrg"></td>
                            <td class="col-md-1" style="text-align: center" data-bind="text: inv_typ"></td>
                            <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(inv_val(), 2)"></td>
                            <td class="col-md-1" style="text-align: right;" data-bind="text: coreWebApp.formatNumber(taxable_val(), 2)"></td>
                            <td class="col-md-1" style="text-align: right;" data-bind="text: rt()"></td>
                            <td class="col-md-1" style="text-align: right;" data-bind="text: sgst()==0 ? '': coreWebApp.formatNumber(sgst(), 2)"></td>
                            <td class="col-md-1" style="text-align: right;" data-bind="text: cgst()==0 ? '':coreWebApp.formatNumber(cgst(), 2)"></td>
                            <td class="col-md-1" style="text-align: right;" data-bind="text: igst()==0 ? '':coreWebApp.formatNumber(igst(), 2)"></td>
                        </tr>
                        </script>
                    </div>
                </form>
            </div>
            <script type="text/javascript" >

            </script>
        </div>
    </div>
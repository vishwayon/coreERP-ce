// Declare core_tx Namespace
typeof window.core_tx == 'undefined' ? window.core_tx = {} : '';
window.core_tx.gstr2aReco = {};

(function (gstr2aReco) {

    function afterload() {
        coreWebApp.ModelBo.docSecurity.allowSave(false);
    }
    gstr2aReco.afterload = afterload;

    function upload2a_click() {
        if ($('#gstr_resp_file').val() == '') {
            coreWebApp.toastmsg('error', 'File upload', 'Select a file to proceed');
            return;
        }
        $('#btn_reco_print').hide();
        $('#cmd_uploadgstr2a').attr('disabled', 'true');
        var fd = new FormData(document.getElementById('bo-form'));
        fd.append("label", "WEBUPLOAD");
        $.ajax({
            url: '?r=core/tx/gstr2a/upload2a',
            type: "POST",
            data: fd,
            enctype: 'multipart/form-data',
            processData: false, // tell jQuery not to process the data
            contentType: false, // tell jQuery not to set contentType
            dataType: 'json',
            success: function (result) {
                if (result.status != 'OK') {
                    coreWebApp.toastmsg('msg', result.status, result.msg);
                } else {
                    coreWebApp.toastmsg('msg', 'Success', result.status);
                }
                $('#cmd_uploadgstr2a').removeAttr('disabled');
            }
        });
    }
    gstr2aReco.upload2a_click = upload2a_click;

    function view_gstr2a_reco_click() {
        $('#btn_reco_print').hide();
        $('#cmd_view_gstr2a_reco').attr('disabled', 'true');
        $.ajax({
            url: '?r=core/tx/gstr2a/get-gstr2a-reco-data',
            type: "GET",
            data: {
                gst_ret_id: $('#gst_ret_id').val()
            },
            dataType: 'json',
            success: function (result) {
                var gstr_model = new function () {
                    self = this;
                };
                gstr_model.reco_data = ko.mapping.fromJS(result.reco_data);
                gstr_model.saved_data = ko.mapping.fromJS(result.saved_data);
                gstr_model.unmatched_only = ko.observable(false);
                gstr_model.unmatched_only.subscribe(display_unmatched_only);
                gstr_model.matched_only = ko.observable(false);
                gstr_model.matched_only.subscribe(display_matched_only);
                gstr2aReco.gstr_model = gstr_model; // ko.mapping.fromJS(result.dt);
                get_match_view();
                $('#btn_reco_print').show();
                $('#cmd_view_gstr2a_reco').removeAttr('disabled');
            }
        });
    }
    gstr2aReco.view_gstr2a_reco_click = view_gstr2a_reco_click;

    function get_match_view() {
        $.ajax({
            url: '?r=core/tx/gstr2a/get-gstr2a-match-view',
            method: 'GET',
            success: function (html) {
                $('#div_gstr2a_reco').html(html);
                ko.cleanNode($('#div_gstr2a_reco')[0]);
                ko.applyBindings(gstr2aReco.gstr_model, $('#div_gstr2a_reco')[0]);
            }
        });
    }
    gstr2aReco.get_match_view = get_match_view;

    function taxable_val(item) {
        var tot = 0.00;
        item.itms().forEach(i => {
            tot += parseFloat(i.itm_det.txval());
        });
        return tot;
    }
    gstr2aReco.taxable_val = taxable_val;

    function tax_total(item) {
        var tot = 0.00;
        item.itms().forEach(i => {
            if (typeof i.itm_det.iamt != 'undefined') {
                tot += parseFloat(i.itm_det.iamt());
            }
            if (typeof i.itm_det.samt != 'undefined') {
                tot += parseFloat(i.itm_det.samt());
            }
            if (typeof i.itm_det.camt != 'undefined') {
                tot += parseFloat(i.itm_det.camt());
            }
        });
        return tot;
    }
    gstr2aReco.tax_total = tax_total;

    function get_inv_total(data) {
        var tot = 0.00;
        data().forEach(gi => {
            tot += parseFloat(gi.inv2a.val());
        });
        return tot;
    }
    gstr2aReco.get_inv_total = get_inv_total;

    function get_inv_tax_total(data) {
        var tot = 0.00;
        data().forEach(gi => {
            tot += tax_total(gi.inv2a);
        });
        return tot;
    }
    gstr2aReco.get_inv_tax_total = get_inv_tax_total;

    function get_gstr2a_match_inv_total(data) {
        var tot = 0.00;
        data().forEach(gi => {
            tot += parseFloat(gi.gstr2a.val());
        });
        return tot;
    }
    gstr2aReco.get_gstr2a_match_inv_total = get_gstr2a_match_inv_total;

    function get_gstr2a_match_bt_total(data) {
        var tot = 0.00;
        data().forEach(gi => {
            gi.gstr2a.itms().forEach(itm => {
                tot += parseFloat(itm.itm_det.txval());
            });
        });
        return tot;
    }
    gstr2aReco.get_gstr2a_match_bt_total = get_gstr2a_match_bt_total;

    function get_gstr2a_match_inv_tax_total(data) {
        var tot = 0.00;
        data().forEach(gi => {
            tot += tax_total(gi.gstr2a);
        });
        return tot;
    }
    gstr2aReco.get_gstr2a_match_inv_tax_total = get_gstr2a_match_inv_tax_total;

    function get_gstr2a_match_itc_total(data) {
        var tot = 0.00;
        data().forEach(inv => {
            tot += parseFloat(inv.b2b.itc_amt());
        });
        return tot;
    }
    gstr2aReco.get_gstr2a_match_itc_total = get_gstr2a_match_itc_total;

    function get_col_total(col_name, data) {
        var total = new Number(0.00);
        data().forEach(function (row) {
            total += parseFloat(row[col_name]());
        });
        return total;
    }
    gstr2aReco.get_col_total = get_col_total;

    function gstr2a_req_resp_click() {
        $('#btn_reco_print').hide();
        $('#cmd_reqgstr2a').attr('disabled', 'true');
        $('#cmd_reqgstr2a').hide();
        $('#div_res_reqgstr2a').show();
        $('#res_reqgstr2a').html('Request sent. Awaiting response...');
        $('#gstr_resp_file').hide();
        $('#cmd_recgstr2a').hide();
        var fd = {
            username: $('#username').val(),
            statecd: $('#statecd').val(),
            gstin: $('#gstin').val(),
            retperiod: $('#retperiod').val(),
            txn: $('#gstn_txn').val(),
            recfrom: $('#ret_period_from').val(),
            recto: $('#ret_period_to').val()
        };
        $.ajax({
            url: '?r=core/tx/gst-return/gstn-gstr2a-reco',
            type: "GET",
            data: {'data': JSON.stringify(fd), 'reqtime': new Date().getTime()},
            dataType: 'json',
            success: function (result) {
                if (result.status == 'OK') {
                    var gstr_model = new function () {
                        self = this;
                    };
                    gstr_model.data = ko.mapping.fromJS(result);
                    gstr_model.data.flagOptions = buildFlagOptions();
                    coreWebApp.ModelBo.annex_info.gstr2a_reco_info.gstr_resp_id(result.gstr_resp_id);
                    gstr2aReco.gstr_model = gstr_model; // ko.mapping.fromJS(result.dt);
                    get_match_view();

                    $('#cmd_reqgstr2a').removeAttr('disabled');
                    $('#div_res_reqgstr2a').hide();
                    $('#cmd_reqgstr2a').show();
                    $('#btn_reco_print').show();
                } else {
                    $('#cmd_reqgstr2a').show();
                    $('#res_reqgstr2a').html(result.message + '<br/><br/>' + result.error.message);
                    $('#div_res_reqgstr2a').show();
                    $('#cmd_reqgstr2a').removeAttr('disabled');
                }
                $('#gstr_resp_file').show();
                $('#cmd_recgstr2a').show();
            },
            error: function (data) {
                $('#cmd_reqgstr2a').show();
                $('#res_reqgstr2a').html('Request failed.');
                $('#div_res_reqgstr2a').show();
                $('#cmd_reqgstr2a').removeAttr('disabled');
                $('#gstr_resp_file').show();
                $('#cmd_recgstr2a').show();
            }
        });
    }
    gstr2aReco.gstr2a_req_resp_click = gstr2a_req_resp_click;

    function printClick() {
        var pwin = window.open('');
        var htmldoc = $('<html></html>');
        var head = $('<head>' + document.head.innerHTML + '</head>');
        htmldoc.append(head);
        // This should be a simple parent div to ensure that it does not take printer page space
        var rptParent = $($('#div_gstr2a_reco').html());
        rptParent.css('margin-left', '10px');
        rptParent.find('#btn_reco_print').css('visibility', 'collapse');
        var body = $('<body></body>');
        body.attr('onload', 'pageLoaded()');
        body.append(rptParent);
        htmldoc.append(body);
        var script = pwin.document.createElement('script');
        script.type = 'text/javascript';
        script.text = 'function pageLoaded() { window.print(); window.close(); }';
        htmldoc.append(script);
        pwin.document.write(htmldoc.html());
        pwin.document.close();
        //pwin.close();
    }
    gstr2aReco.printClick = printClick;

    function manual_match_click(row) {
        var g2a_item = null;
        var b2b_item = null;
        row.unmatched_inv2a().forEach(itm => {
            if (itm.select()) {
                g2a_item = itm;
            }
        });
        row.missing_b2b().forEach(itm => {
            if (itm.select()) {
                b2b_item = itm;
            }
        });
        var msg = "Proceed with manual match for bill# " + g2a_item.inum() + " with Document# " + b2b_item.voucher_id() + "?";
        var res = coreWebApp.customprompt('warning', msg, function () {
            g2a_item.select(false);
            b2b_item.select(false);
            g2a_item.doc_date = ko.observable(b2b_item.doc_date());
            g2a_item.cfs() == "Y" ? g2a_item.flag("A") : g2a_item.flag("AM");
            g2a_item.voucher_id(b2b_item.voucher_id());
            g2a_item.matched_by = ko.observable("user");
            b2b_item.show(false);

            var gstr2a_match = core_tx.gstr2aReco.gstr_model.data.reco_data.gstr2a_match;
            var matched_item = {
                gstin: ko.observable(row.ctin()),
                supplier: ko.observable(row.supplier()),
                gstr2a: g2a_item,
                b2b: b2b_item
            };
            gstr2a_match.push(matched_item);

            row.unmatched_inv2a.remove(g2a_item);
            row.missing_b2b.remove(b2b_item);
        });
    }
    gstr2aReco.manual_match_click = manual_match_click;

    function save_reco() {
        var model = core_tx.gstr2aReco.gstr_model.reco_data;
        var mdata = ko.mapping.toJS(model);
        $.ajax({
            url: '?r=core/tx/gstr2a/gstr2a-reco-save',
            type: "POST",
            data: {
                _csrf: $('#_csrf').val(),
                gst_ret_id: coreWebApp.ModelBo.gst_ret_id(),
                mdata: JSON.stringify(mdata),
                reqtime: new Date().getTime()
            },
            dataType: 'json',
            success: function (result) {
                if (result.status == "OK") {
                    coreWebApp.toastmsg('success', 'Save Status', 'Successfully saved');
                    view_gstr2a_reco_click();
                } else {
                    coreWebApp.toastmsg('error', 'Save Status', 'Failed to save saved</br>' + result.msg);
                }
            }
        });
    }
    gstr2aReco.save_reco = save_reco;

    function display_unmatched_only() {
        if ($('#chkunmtchonly').is(":checked")) {
            $('#chkmtchonly').prop('checked', false);
            $('[row-type="gstr2a_missed"]').show();
            $('[row-type="prg_missed"]').show();
            $('[row-type="matched"]').hide();
        } else {
            $('[row-type="gstr2a_missed"]').show();
            $('[row-type="prg_missed"]').show();
            $('[row-type="matched"]').show();
        }

    }

    function display_matched_only() {
        if ($('#chkmtchonly').is(":checked")) {
            $('#chkunmtchonly').prop('checked', false);
            $('[row-type="gstr2a_missed"]').hide();
            $('[row-type="prg_missed"]').hide();
            $('[row-type="matched"]').show();
        } else {
            $('[row-type="gstr2a_missed"]').show();
            $('[row-type="prg_missed"]').show();
            $('[row-type="matched"]').show();
        }
    }

    function user_match_click(item) {
        var gstin_info;
        $.each(core_tx.gstr2aReco.gstr_model.reco_data(), function (idx, idt) {
            $.each(idt.prg_missed(), function (midx, midt) {
                if (midt.voucher_id() == item.voucher_id()) {
                    gstin_info = idt;
                    return;
                }
            });
        });
        if (gstin_info !== undefined) {
            $.each(gstin_info.gstr2a_missed(), function (id, it) {
                it.select = ko.observable(false);
            });
            gstin_info.match_for = item;
            BootstrapDialog.show({
                title: 'Manual Match',
                message: $($('#dialog-match-tmpl').html()),
                buttons: [{
                        label: 'Update',
                        action: function (dialog) {
                            gstr2aReco.user_match_update(gstin_info);
                            dialog.close();
                        }
                    }],
                onshown: function (dialogRef) {
                    // todo: load events
                    ko.applyBindings(gstin_info, $('#dialog-match')[0]);
                }
            });
        }

    }
    gstr2aReco.user_match_click = user_match_click;

    function user_unmatch_click(item) {
        msg = 'Do you really want to unmatch ' + item.voucher_id() + ' associated to ' + item.bill_no() + '?';
        coreWebApp.customprompt('warning', msg, function () {
            $.ajax({
                url: '?r=core/tx/gstr2a/unmatch-gstr2a',
                type: "POST",
                data: {
                    _csrf: $('#_csrf').val(),
                    gst_ret_id: coreWebApp.ModelBo.gst_ret_id(),
                    bill_no: item.bill_no(),
                    voucher_id: item.voucher_id(),
                    reqtime: new Date().getTime()
                },
                dataType: 'json',
                success: function (result) {
                    if (result.status == "OK") {
                        coreWebApp.toastmsg('success', 'Unmatch Status', 'Successfully unmatched.');
                        view_gstr2a_reco_click();
                    } else {
                        coreWebApp.toastmsg('error', 'Unmatch Status', 'Failed to unmatch</br>' + result.msg);
                    }
                }
            });
        });
    }
    gstr2aReco.user_unmatch_click = user_unmatch_click;

    function user_match_update(gstin_info) {
        var selItem = [];
        $.each(gstin_info.gstr2a_missed(), function (id, it) {
            if (it.select()) {
                selItem.push(it);
            }
        });
        $.each(selItem, function (id, it) {
            gstin_info.matched.push({
                prg_bill: gstin_info.match_for,
                gstr2a_bill: it,
                match_by: 'U'
            });
            gstin_info.prg_missed.splice(gstin_info.prg_missed.indexOf(gstin_info.match_for), 1);
            gstin_info.gstr2a_missed.splice(gstin_info.gstr2a_missed.indexOf(it), 1);
        });
    }
    gstr2aReco.user_match_update = user_match_update;

}(window.core_tx.gstr2aReco));



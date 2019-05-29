typeof window.core_st == 'undefined' ? window.core_st = {} : '';
window.core_st.core_stocktransferparkpost = {};

(function (core_stocktransferparkpost) {

    function display_stpp(row) {
        var opts = {
            stock_id: row.stock_id()
        }
        opts.module = 'core/st';
        opts.alloc_view = '/stockTransferParkPost/StockTransferParkPost';
        opts.call_init = display_stpp_init;
        opts.call_update = display_stpp_update;
        coreWebApp.showAllocV3(opts);
    }
    core_stocktransferparkpost.display_stpp = display_stpp;

    function display_stpp_init(opts, after_init) {
        $.ajax({
            url: '?r=core/st/form/stock-transfer-park-post-data',
            type: 'GET',
            data: {
                stock_id: opts.stock_id
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    var disp_stpp_alloc = new function () {
                        self = this;
                    };
                    disp_stpp_alloc.stpp_temp = build_stpp_temp();

                    for (var p = 0; p < jsonResult['stpp_dt'].length; p++) {
                        var stpp_row = jsonResult['stpp_dt'][p];
                        var nr = disp_stpp_alloc.stpp_temp.addNewRow();
                        nr.sl_no(stpp_row['sl_no']);
                        nr.material_type(stpp_row['material_type']);
                        nr.material_name(stpp_row['material_name']);
                        nr.uom_desc(stpp_row['uom_desc']);
                        nr.issued_qty(stpp_row['issued_qty']);
                        disp_stpp_alloc.stpp_temp.push(nr);
                    }
                    opts.model = disp_stpp_alloc;
                    $('#stpp-loading').hide();
                    after_init(); //callback handler as the ajax call is in diff thread
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
    }
    core_stocktransferparkpost.display_stpp_init = display_stpp_init;

    function build_stpp_temp() {
        var stpp_temp = ko.observableArray();
        stpp_temp.addNewRow = function () {
            var cobj = new Object();
            cobj.sl_no = ko.observable(0);
            cobj.material_type = ko.observable('');
            cobj.material_name = ko.observable('');
            cobj.uom_desc = ko.observable('');
            cobj.issued_qty = ko.observable(0);
            return cobj;
        };
        return stpp_temp;
    }
    core_stocktransferparkpost.build_stpp_temp = build_stpp_temp;

    function display_stpp_update(opts) {
        delete opts.model;
        return true;
    }
    core_stocktransferparkpost.display_stpp_update = display_stpp_update;

    function post_stpp(row) {
        opts = {
            stock_id: row.stock_id(),
            doc_type: 'ST',
            for_receipt: true,
            receipt_posted: row.posted()
        };
        var lnk = '?r=/core/st/form&formName=stockTransfer/StockTransferReceiptEditForm&formParams=' + JSON.stringify(opts);
        coreWebApp.rendercontents(lnk, 'details', 'contentholder', 'core_stocktransfer.after_load');
    }
    core_stocktransferparkpost.post_stpp = post_stpp;

    function post_st_park_post() {
        var st_row = coreWebApp.ModelBo.stock_tran().find(function (itm) {
            return itm.receipt_sl_id() == -1;
        });
        if (st_row != undefined) {
            coreWebApp.toastmsg('warning', 'Missing Target Stock Location', 'Target Stock location is required in row # ' + st_row.sl_no(), false);
            return;
        }

        var st_row1 = coreWebApp.ModelBo.stock_tran().find(function (itm) {
            return itm.receipt_qty() == 0;
        });
        if (st_row1 != undefined) {
            coreWebApp.toastmsg('warning', 'Confirm Receipt', 'Received Qty is required in row # ' + st_row1.sl_no(), false);
            return;
        }

        if (coreWebApp.ModelBo.st_received_on() < coreWebApp.ModelBo.doc_date()) {
            coreWebApp.toastmsg('warning', 'Confirm Receipt', 'Received On cannot be less than document date.', false);
            return;
        }

        var dtm = moment(new Date());
        var current_date = dtm.format(('YYYY-MM-DD'));
        if (coreWebApp.ModelBo.st_received_on() > current_date) {
            coreWebApp.toastmsg('warning', 'Confirm Receipt', 'Received On cannot be greater than current date.', false);
            return;
        }
        var res = coreWebApp.customprompt('warning', 'Are You sure? Please check the date of receipt and Quantity properly before confirmation.', function () {
            var received_on = coreWebApp.ModelBo.st_received_on();
            var stock_id = coreWebApp.ModelBo.stock_id();
            var reference = coreWebApp.ModelBo.st_reference();

            var st_temp = [];
            coreWebApp.ModelBo.stock_tran().forEach(st_tran => {
                var cobj = {
                    issued_qty: st_tran.issued_qty(),
                    receipt_qty: st_tran.receipt_qty(),
                    short_qty: st_tran.short_qty(),
                    receipt_sl_id: st_tran.receipt_sl_id(),
                    stock_tran_id: st_tran.stock_tran_id(),
                    material_id: st_tran.material_id()
                };
                st_temp.push(cobj);
            });
            $.ajax({
                url: '?r=core/st/stock-transfer-park-post/st-park-post',
                type: 'POST',
                dataType: 'json',
                data: {stock_id: stock_id, received_on: received_on, reference: reference, st_temp: ko.mapping.toJSON(st_temp), st_str_qc_reqd: coreWebApp.ModelBo.st_str_qc_reqd()},
                success: function (result) {
                    if (result.status === 'OK') {
                        coreWebApp.toastmsg('success', 'Successfully posted', '', false);
                        $('#btn_receipt').hide();
                        $('#btn_apply_tsl').hide();
                    } else {
                        coreWebApp.toastmsg('warning', 'Missing data', result.status, false);
                    }
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                }
            });
        });
    }
    core_stocktransferparkpost.post_st_park_post = post_st_park_post;

    function apply_target_sl() {
        if (coreWebApp.ModelBo.annex_info.target_sl_id() == -1) {
            coreWebApp.toastmsg('warning', 'Missing Target Stock Loc', 'Target Stock Location is required', false);
            return;
        }
        var sl_name = '';
        $.ajax({
            url: '?r=core/st/form/get-sl-name',
            type: 'GET',
            dataType: 'json',
            data: {sl_id: coreWebApp.ModelBo.annex_info.target_sl_id()},
            success: function (result) {
                if (result.status === 'ok') {
                    sl_name = result.sl_name;
                }
                coreWebApp.ModelBo.stock_tran().forEach(st_tran => {
                    if (st_tran.receipt_sl_id() == -1) {
                        st_tran.receipt_sl_id(coreWebApp.ModelBo.annex_info.target_sl_id());
                        coreWebApp.trigger_change('receipt_sl_id', coreWebApp.ModelBo.annex_info.target_sl_id(), sl_name);
                    }
                });
            },
            error: function (data) {
            }
        });
    }
    core_stocktransferparkpost.apply_target_sl = apply_target_sl;

    function request_qc() {
        var st_row = coreWebApp.ModelBo.stock_tran().find(function (itm) {
            return itm.receipt_sl_id() == -1;
        });
        if (st_row != undefined) {
            coreWebApp.toastmsg('warning', 'Missing Target Stock Location', 'Target Stock location is required in row # ' + st_row.sl_no(), false);
            return;
        }
        var rl_row = coreWebApp.ModelBo.stock_tran().find(function (itm) {
            return itm.receipt_qty() == 0;
        });
        if (rl_row != undefined) {
            coreWebApp.toastmsg('warning', 'Request QC', 'Received Qty is required in row # ' + rl_row.sl_no(), false);
            return;
        }
        var res = coreWebApp.customprompt('warning', 'After QC request receipt qty is not allowed to change. Are you sure you want to request for QC?', function () {
            var stock_id = coreWebApp.ModelBo.stock_id();
            var st_temp = ko.observableArray();
            coreWebApp.ModelBo.stock_tran().forEach(st_tran => {
                var cobj = new Object();
                cobj.receipt_qty = st_tran.receipt_qty();
                cobj.short_qty = st_tran.short_qty();
                cobj.receipt_sl_id = st_tran.receipt_sl_id();
                cobj.stock_tran_id = st_tran.stock_tran_id();
                cobj.material_id = st_tran.material_id();
                st_temp.push(cobj);
            });
            $.ajax({
                url: '?r=core/st/stock-transfer-park-post/update-stock-receipt-for-qc',
                type: 'POST',
                dataType: 'json',
                data: {stock_id: stock_id, received_on: coreWebApp.ModelBo.st_received_on(), st_temp: ko.mapping.toJSON(st_temp())},
                success: function (result) {
                    if (result.status === 'OK') {
                        coreWebApp.toastmsg('success', 'Requested for QC', '', false);
                        $('#btn_req_qc').hide();
                        coreWebApp.ModelBo.qc_requested(true);
                    } else {
                        coreWebApp.toastmsg('warning', 'Request QC Error', result.status, false);
                    }
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                }
            });
        });
    }
    core_stocktransferparkpost.request_qc = request_qc;

    function short_qty_calc(row) {
        if (stop_calc) {
            return;
        }
        stop_calc = true;
        var issued_qty = Number.parseFloat(row.issued_qty());
        var receipt_qty = Number.parseFloat(row.receipt_qty());
        row.short_qty((receipt_qty - issued_qty).toFixed(3));
        stop_calc = false;
    }
    core_stocktransferparkpost.short_qty_calc = short_qty_calc;

    function req_qc_enable(dataItem) {
        if (coreWebApp.ModelBo.qc_requested()) {
            return false;
        }
        if (coreWebApp.ModelBo.st_str_qc_reqd()) {
            var st_row = coreWebApp.ModelBo.stock_tran().find(function (itm) {
                return itm.has_qc() == true;
            });
            if (st_row != undefined) {
                return true;
            }
        }
        return false;
    }
    core_stocktransferparkpost.req_qc_enable = req_qc_enable;

    function receipt_qty_enable(dataItem) {
        if (coreWebApp.ModelBo.st_str_qc_reqd() && coreWebApp.ModelBo.qc_requested()) {
            return false;
        }
        return true;
    }
    core_stocktransferparkpost.receipt_qty_enable = receipt_qty_enable;

    function fetch_avl_qty(row) {
        var bar_code = row.bar_code();
        var mat_id = row.material_id();
        var sl_id = parseInt(row.receipt_sl_id());
        $.ajax({
            url: '?r=core/st/form/get-mat-gst-info-cc',
            type: 'GET',
            dataType: 'json',
            data: {bar_code: bar_code, mat_id: mat_id, stock_loc_id: sl_id, doc_date: coreWebApp.ModelBo.doc_date()},
            success: function (result) {
                if (typeof result.mat_id !== 'undefined') {
                    row.bal_qty("Avl: " + parseFloat(result.bal_qty).toFixed());
                    if (parseFloat(result.bal_qty) > 0) {
                        row.has_bal(true);
                    } else {
                        row.has_bal(false);
                    }
                } else {
                    coreWebApp.toastmsg('warning', 'Missing data', 'Data not found for selected material', false);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
            }
        });
    }
    core_stocktransferparkpost.fetch_avl_qty = fetch_avl_qty;
}(window.core_st.core_stocktransferparkpost));

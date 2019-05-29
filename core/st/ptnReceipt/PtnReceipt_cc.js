// Declare core_st Namespace
typeof window.ptnr == 'undefined' ? window.ptnr = {} : '';
(function (ptnr) {

    function post_stpp(row) {
        opts = {
            stock_id: row.stock_id(),
            doc_type: 'ST',
            for_receipt: true,
            receipt_posted: row.posted()
        };
        var lnk = '?r=/core/st/form&formName=ptn/PtnReceiptEditForm&formParams=' + JSON.stringify(opts);
        coreWebApp.rendercontents(lnk, 'details', 'contentholder', 'core_ptn.after_load');
    }
    ptnr.post_stpp = post_stpp;

    function confirm_receipt() {
//        var st_row = coreWebApp.ModelBo.stock_tran().find(function (itm) {
//            return itm.receipt_sl_id() == -1;
//        });
//        if (st_row != undefined) {
//            coreWebApp.toastmsg('warning', 'Missing Target Stock Location', 'Target Stock location is required in row # ' + st_row.sl_no(), false);
//            return;
//        }
//
//        var st_row1 = coreWebApp.ModelBo.stock_tran().find(function (itm) {
//            return itm.receipt_qty() == 0;
//        });
//        if (st_row1 != undefined) {
//            coreWebApp.toastmsg('warning', 'Confirm Receipt', 'Received Qty is required in row # ' + st_row1.sl_no(), false);
//            return;
//        }
//
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

        var received_on = coreWebApp.ModelBo.st_received_on();
        var stock_id = coreWebApp.ModelBo.stock_id();
        var reference = '';

        var st_temp = [];
        coreWebApp.ModelBo.stock_tran().forEach(st_tran => {
            var cobj = {
                issued_qty: st_tran.issued_qty(),
                receipt_qty: st_tran.issued_qty(),
                short_qty: 0,
                receipt_sl_id: st_tran.target_stock_location_id(),
                stock_tran_id: st_tran.stock_tran_id(),
                material_id: st_tran.material_id()
            };
            st_temp.push(cobj);
        });
        $.ajax({
            url: '?r=core/st/ptn-receipt/confirm-receipt',
            type: 'POST',
            dataType: 'json',
            data: {stock_id: stock_id, received_on: received_on, reference: reference, st_temp: ko.mapping.toJSON(st_temp)},
            success: function (result) {
                if (result.status === 'OK') {
                    coreWebApp.toastmsg('success', 'Successfully posted', '', false);
                    $('#btn_receipt').hide();
                } else {
                    coreWebApp.toastmsg('warning', 'Missing data', result.status, false);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
            }
        });
    }
    ptnr.confirm_receipt = confirm_receipt;

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
    ptnr.fetch_avl_qty = fetch_avl_qty;
}(window.ptnr));

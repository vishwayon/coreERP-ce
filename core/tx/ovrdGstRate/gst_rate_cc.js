typeof window.core_tx == 'undefined' ? window.core_tx = {} : '';
window.core_tx.gst_rate = {};

(function (gst_rate) {
    
    // returns selected select_gst_rate
    function select_gst_rate(opts) {
        opts.module = 'core/tx';
        opts.alloc_view = 'ovrdGstRate/GstRateSelect';
        opts.call_init = gst_rate_select_init;
        opts.call_update = gst_rate_select_update;
        coreWebApp.showAllocV2(opts);
    }
    gst_rate.select_gst_rate = select_gst_rate;

    function gst_rate_select_init(opts, after_init) {
        $.ajax({
            url: '?r=core/tx/form/get-gst-rates',
            type: 'GET',
            dataType: 'json',
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var gr_sel = new function () {
                    self = this;
                };
                gr_sel.gr_temp = resultdata;
                gr_sel.gr_temp.forEach(itm => {
                    itm.select = ko.observable(false);
                    if (opts.row.gtt_gst_rate_id() == itm.gst_rate_id) {
                        itm.select(true);
                    }
                    itm.select.subscribe(on_item_select, itm);
                });
                opts.model = gr_sel;
                $('#gst-loading').hide();

                after_init(); // We will not do standard init.
                var tbl = $('#gr_temp').DataTable({
                    data: gr_sel.gr_temp,
                    order: [],
                    columns: [
                        {data: "select", title: "Select", width: "10%",
                            createdCell: function (td, cellData, rowData, row, col) {
                                $(td).html('<input type="checkbox" data-bind="checked: select">');
                                ko.applyBindings(rowData, $(td)[0]);
                                $(td).css('text-align', 'center');
                            }},
                        {data: "gst_rate_desc", title: "GST Tax Name", width: "60%"},
                        {data: "igst_pcnt", title: "Rate", className: "dt-right", width: "30%"}
                    ],
                    deferRender: true,
                    scrollY: '200px',
                    scrollCollapse: true,
                    scroller: true,
                });
                var l = $('#gr_temp_length');
                if (l !== 'undefined') {
                    l.hide();
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Gst Rates', 'Failed with errors on server', false);
            }
        });

    }
    gst_rate.gst_rate_select_init = gst_rate_select_init;

    function gst_rate_select_update(opts) {
        $.each(opts.model.gr_temp, function (idx, itm) {
            if (itm.select()) {
                itm.hsn_sc_code = opts.row.gtt_hsn_sc_code();
                itm.hsn_sc_type = opts.row.gtt_hsn_sc_type();
                opts.gst_hsn_info = itm;
                core_tx.gst.item_gtt_reset(opts);
                return true;
            }
        });
        return true;
    }
    gst_rate.gst_rate_select_update = gst_rate_select_update;
    
    function on_item_select(is_sel) {
        if (is_sel) {
            var p = this;
            ko.utils.arrayForEach(self.gr_temp, function (item) {
                if (p.gst_rate_id != item.gst_rate_id) {
                    item.select(false);
                }
                else{
                    item.select(true);
                }
            });
        }
    }

}(window.core_tx.gst_rate));

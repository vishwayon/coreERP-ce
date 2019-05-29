typeof window.core_tx == 'undefined' ? window.core_tx = {} : '';
window.core_tx.hsn = {};

(function (hsn) {
    
    // returns selected select_gst_rate
    function select_hsn(opts) {
        opts.module = 'core/tx';
        opts.alloc_view = 'ovrdHsnSc/HsnScSelect';
        opts.call_init = hsn_select_init;
        opts.call_update = hsn_select_update;
        coreWebApp.showAllocV2(opts);
    }
    hsn.select_hsn = select_hsn;

    function hsn_select_init(opts, after_init) {
        $.ajax({
            url: '?r=core/tx/form/get-hsn-list',
            type: 'GET',
            dataType: 'json',
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var hsn_sel = new function () {
                    self = this;
                };
                hsn_sel.hsn_temp = resultdata;
                hsn_sel.hsn_temp.forEach(itm => {
                    itm.select = ko.observable(false);
                    itm.select.subscribe(on_item_select, itm);
                });
                opts.model = hsn_sel;
                $('#hsn-loading').hide();

                after_init(); // We will not do standard init.
                var tbl = $('#hsn_temp').DataTable({
                    data: hsn_sel.hsn_temp,
                    order: [],
                    columns: [
                        {data: "select", title: "Select", width: "10%",
                            createdCell: function (td, cellData, rowData, row, col) {
                                $(td).html('<input type="checkbox" data-bind="checked: select">');
                                ko.applyBindings(rowData, $(td)[0]);
                                $(td).css('text-align', 'center');
                            }
                        },
                        {data: "hsn_sc_code", title: "HSN/SC", width: "20%"},
                        {data: "hsn_sc_desc", title: "Description", width: "60%", 
                            createdCell: function (td, cellData, rowData, row, col) {
                                $(td).html('<textarea style="width: 500px; border: none; resize: none;">'+cellData+'</textarea>');
                            }
                        },
                        {data: "igst_pcnt", title: "GST@", width: "10%", className: "dt-right",
                            render: function (cellData) { 
                                return coreWebApp.formatNumber(cellData, 2) + '%';
                            }
                        }
                    ],
                    deferRender: true,
                    scrollY: '200px',
                    scrollCollapse: true,
                    scroller: true
                });
                var l = $('#hsn_temp_length');
                if (l !== 'undefined') {
                    l.hide();
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'HSN List', 'Failed with errors on server', false);
            }
        });

    }
    hsn.hsn_select_init = hsn_select_init;

    function hsn_select_update(opts) {
        $.each(opts.model.hsn_temp, function (idx, itm) {
            if (itm.select()) {
                debugger;
                opts.gst_hsn_info = itm;
                core_tx.gst.item_gtt_reset(opts);
                return true;
            }
        });
        return true;
    }
    hsn.gst_rate_select_update = hsn_select_update;
    
    function on_item_select(is_sel) {
        if (is_sel) {
            var p = this;
            ko.utils.arrayForEach(self.hsn_temp, function (item) {
                if (p.hsn_sc_code != item.hsn_sc_code) {
                    item.select(false);
                }
                else{
                    item.select(true);
                }
            });
        }
    }

}(window.core_tx.hsn));

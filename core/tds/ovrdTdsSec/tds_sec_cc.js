typeof window.core_tds == 'undefined' ? window.core_tds = {} : '';
window.core_tds.tds_sec = {};

(function (tds_sec) {

    // returns selected select_tds_sec
    function select_tds_sec(opts) {
        opts.module = 'core/tds';
        opts.alloc_view = 'ovrdTdsSec/TdsSecSelect';
        opts.call_init = tds_sec_select_init;
        opts.call_update = tds_sec_select_update;
        coreWebApp.showAllocV2(opts);
    }
    tds_sec.select_tds_sec = select_tds_sec;

    function tds_sec_select_init(opts, after_init) {
        $.ajax({
            url: '?r=core/tds/form/get-tds-sec-rate',
            type: 'GET',
            dataType: 'json',
            data: {
                person_type_id: opts.person_type_id,
                doc_date: opts.doc_date
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var ts_sel = new function () {
                    self = this;
                };
                ts_sel.ts_temp = resultdata;
                ts_sel.ts_temp.forEach(itm => {
                    itm.select = ko.observable(false);
                    if (opts.row.btt_section_id() == itm.section_id) {
                        itm.select(true);
                    }
                    itm.select.subscribe(on_item_select, itm);
                });
                opts.model = ts_sel;
                $('#gst-loading').hide();

                after_init(); // We will not do standard init.
                var tbl = $('#ts_temp').DataTable({
                    data: ts_sel.ts_temp,
                    order: [],
                    columns: [
                        {data: "select", title: "Select", width: "10%",
                            createdCell: function (td, cellData, rowData, row, col) {
                                $(td).html('<input type="checkbox" data-bind="checked: select">');
                                ko.applyBindings(rowData, $(td)[0]);
                                $(td).css('text-align', 'center');
                            }},
                        {data: "section", title: "Section", width: "20%"},
                        {data: "base_rate_perc", title: "TDS %", className: "dt-right", width: "20%"},
                        {data: "ecess_perc", title: "E-cess %", className: "dt-right", width: "20%"},
                        {data: "surcharge_perc", title: "Surch. %", className: "dt-right", width: "20%"},
                        {data: "effective_from", title: "Effe. From",
                            render: function (cellData) {
                                return coreWebApp.formatDate(cellData);
                            }, width: "20%"
                        }
                    ],
                    deferRender: true,
                    scrollY: '200px',
                    scrollCollapse: true,
                    scroller: true,
                });
                var l = $('#ts_temp_length');
                if (l !== 'undefined') {
                    l.hide();
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'TDS Section', 'Failed with errors on server', false);
            }
        });

    }
    tds_sec.tds_sec_select_init = tds_sec_select_init;

    function tds_sec_select_update(opts) {
        $.each(opts.model.ts_temp, function (idx, itm) {
            if (itm.select()) {
                opts.row.btt_section_id(itm.section_id);
                coreWebApp.trigger_change('btt_section_id', itm.section_id, itm.section);
                opts.row.btt_tds_base_rate_perc(itm.base_rate_perc);
                opts.row.btt_tds_base_rate_amt(0);
                opts.row.btt_tds_base_rate_amt_fc(0);
                opts.row.btt_tds_ecess_perc(itm.ecess_perc);
                opts.row.btt_tds_ecess_amt(0);
                opts.row.btt_tds_ecess_amt_fc(0);
                opts.row.btt_tds_surcharge_perc(itm.surcharge_perc);
                opts.row.btt_tds_surcharge_amt(0);
                opts.row.btt_tds_surcharge_amt_fc(0);
                return true;
            }
        });
        return true;
    }
    tds_sec.tds_sec_select_update = tds_sec_select_update;

    function on_item_select(is_sel) {
        if (is_sel) {
            var p = this;
            ko.utils.arrayForEach(self.ts_temp, function (item) {
                if (p.section_id != item.section_id) {
                    item.select(false);
                } else {
                    item.select(true);
                }
            });
        }
    }

}(window.core_tds.tds_sec));

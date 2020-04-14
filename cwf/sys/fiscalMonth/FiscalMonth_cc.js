typeof window.core_sys == 'undefined' ? window.core_sys = {} : '';
window.core_sys.fm = {};

(function (fm) {
    function enable_sel(dataItem) {
        return coreWebApp.ModelBo.month_close() == false;
    }
    fm.enable_sel = enable_sel;
    
    function after_load(opts, after_init) {
    }
    fm.after_load = after_load;
}(window.core_sys.fm)); 

window.core_sys.doc_group_sel = {};
(function (doc_group_sel) {
    function sel_doc_group_ids(opts) {
        opts.module = 'cwf/sys';
        opts.alloc_view = 'fiscalMonth/DocGroupSel';
        opts.call_init = doc_group_sel_init;
        opts.call_update = doc_group_sel_update;
        coreWebApp.showAllocV2(opts);
    }
    doc_group_sel.sel_doc_group_ids = sel_doc_group_ids;

    function doc_group_sel_init(opts, after_init) {
        $.ajax({
            url: '?r=cwf/sys/form/list-doc-group',
            type: 'GET',
            dataType: 'json',
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var doc_group_sel = new function () {
                    self = this;
                };

                doc_group_sel.doc_group_temp = ko.mapping.fromJS(resultdata.dt_doc_group);
                doc_group_sel.doc_group_temp().forEach(itm => {
                    if (opts.doc_group_ids.indexOf(itm.doc_group_id()) > 0) {
                        itm.select(true);
                    }

                });
                opts.model = doc_group_sel;
                $('#doc-group-loading').hide();

                after_init(); // We will not do standard init.
                var tbl = $('#doc_group_temp').DataTable({
                    data: doc_group_sel.doc_group_temp(),
                    order: [],
                    columns: [
                        {data: "select", title: "Select", width: "5%",
                            createdCell: function (td, cellData, rowData, row, col) {
                                $(td).html('<input type="checkbox" data-bind="checked: select">');
                                ko.applyBindings(rowData, $(td)[0]);
                                $(td).css('text-align', 'center');
                            }},
                        {data: "doc_group", title: "Document Group", width: "25%"}
                    ],
                    deferRender: true,
                    scrollY: '200px',
                    scrollCollapse: true,
                    scroller: true,
                });
                var l = $('#doc_group_temp_length');
                if (l !== 'undefined') {
                    l.hide();
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'User Select', 'Failed with errors on server', false);
            }
        });

    }
    doc_group_sel.doc_group_sel_init = doc_group_sel_init;

    function doc_group_sel_update(opts) {
        is_valid = false;
        opts.model.doc_group_temp().forEach(itm => {
            if (itm.select()) {
                is_valid = true;
            }
        });

        // Return without updating when validations fail
        if (!is_valid) {
            coreWebApp.toastmsg('warning', 'Select User(s)', 'Select atleast one doc_group.');
            return false;
        }
        var vals = [];
        opts.model.doc_group_temp().forEach(function (x) {
            if (x.select()) {
                vals.push(x.doc_group_id());
            }
        });
        coreWebApp.ModelBo.annex_info.doc_group_ids("{" + vals.toString() + "}");
        return true;
    }
    doc_group_sel.doc_group_sel_update = doc_group_sel_update;

}(window.core_sys.doc_group_sel));
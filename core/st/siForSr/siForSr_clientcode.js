typeof window.core_st === 'undefined' ? window.core_st = {} : '';
window.core_st.si_for_sr = {};

(function (si_for_sr) {
    
    function alloc_qty_enable(row) {
        return row.si_sel();
    }
    si_for_sr.alloc_qty_enable = alloc_qty_enable;
    
    function si_sel_ui(opts) {
        opts.module = 'core/st';
        opts.alloc_view = 'siForSr/SiForSr';
        opts.call_init = si_sel_init;
        opts.call_update = si_sel_update;
        coreWebApp.showAllocV2(opts);
    }
    si_for_sr.si_sel_ui = si_sel_ui;


    function si_sel_init(opts, after_init) {
        $.ajax({
            url: '?r=core/st/form/si-for-sr',
            type: 'GET',
            data: {
                origin_inv_id: opts.origin_inv_id
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    var sel_si_alloc = new function () {
                        self = this;
                    };
                    sel_si_alloc.si_temp = build_si_temp();
                    for (var p = 0; p < jsonResult['si_bal'].length; p++) {
                        var bal_row = jsonResult['si_bal'][p];
                        var nr = sel_si_alloc.si_temp.addNewRow();
                        nr.si_id(bal_row['stock_id']);
                        nr.si_tran_id(bal_row['stock_tran_id']);
                        nr.bar_code(bal_row['bar_code']);
                        nr.material_type_id(bal_row['material_type_id']);
                        nr.material_id(bal_row['material_id']);
                        nr.material_name(bal_row['material_name']);
                        nr.stock_location_id(bal_row['stock_location_id']);
                        nr.uom_id(bal_row['uom_id']);
                        nr.uom_desc(bal_row['uom_desc']);
                        nr.bal_qty(bal_row['bal_qty']);
                        nr.rate(bal_row['rate']);
                        nr.gst_hsn_info = bal_row['gst_hsn_info'];
                        // todo: match ui items with display
                        for (var a = 0; a < opts.stock_tran().length; ++a) {
                            var src = opts.stock_tran()[a];
                            if(src.reference_tran_id() == nr.si_tran_id()) {
                                nr.si_sel(true);
                                nr.alloc_qty(src.received_qty());
                            }
                        }
                        sel_si_alloc.si_temp.push(nr);
                    }
                    opts.model = sel_si_alloc;
                    $('#si-loading').hide();
                    after_init(); //callback handler as the ajax call is in diff thread
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
    }
    si_for_sr.si_sel_init = si_sel_init;

    function build_si_temp() {
        var si_temp = ko.observableArray();
        si_temp.addNewRow = function () {
            var cobj = new Object();
            cobj.si_id = ko.observable('');
            cobj.si_tran_id = ko.observable('');
            cobj.bar_code=ko.observable('');
            cobj.material_type_id = ko.observable(-1);
            cobj.material_id = ko.observable(-1);
            cobj.material_name = ko.observable('');
            cobj.stock_location_id = ko.observable(-1);
            cobj.uom_id = ko.observable(-1);
            cobj.uom_desc = ko.observable('');
            cobj.bal_qty = ko.observable(0);
            cobj.rate = ko.observable(0);
            cobj.si_sel = ko.observable(false);
            cobj.alloc_qty = ko.observable(0);
            cobj.updated = ko.observable(false);
            cobj.gst_hsn_info = '';
            return cobj;
        };
        return si_temp;
    }
    si_for_sr.build_si_temp = build_si_temp;

    function si_sel_update(opts) {
        // Validate line items for excess allocation
        var is_valid = true;
        ko.utils.arrayForEach(opts.model.si_temp(), function(r) {
            if (parseFloat(r.alloc_qty()) > parseFloat(r.bal_qty())) {
                coreWebApp.toastmsg('warning', 'Inv Selection', 'Selection Qty cannot exceed Invoice Quantity for ['+r.si_tran_id()+']');
                is_valid = false;
                return;
            };
        });
        // Return without updating when validations fail
        if(!is_valid) {
            return false;
        }
        // Update the tran
        // Step 1: For po line items previously selected, and currently also selected update the rows
        // Do not modify items that do not have a PO reference
        // To unselect or remove a item, the user can always use the delete tran available in the document ui.
        for (var p = 0; p < opts.model.si_temp().length; ++p) {
            var src = opts.model.si_temp()[p];
            if (src.si_sel() && parseFloat(src.alloc_qty()) > 0) {
                var stran = find_si_ref(opts.stock_tran, src.si_tran_id());
                if(stran) {
                    stran.received_qty(parseFloat(src.alloc_qty()).toFixed(3));
                    src.updated(true);
                    typeof opts.tran_item_calc_callback != 'undefined' ? opts.tran_item_calc_callback(stran) : '' ;
                }
            }
        }
        
        // Step 2: Insert newly added items
        for (var p = 0; p < opts.model.si_temp().length; ++p) {
            var src = opts.model.si_temp()[p];
            if (!src.updated() && src.si_sel() && parseFloat(src.alloc_qty()) > 0) {
                var newStran = coreWebApp.ModelBo.addNewRow('stock_tran', coreWebApp.ModelBo, true, false);
                newStran.stock_tran_id(-1);
                newStran.bar_code(src.bar_code());
                newStran.material_type_id(src.material_type_id());
                newStran.material_id(src.material_id());
                newStran.stock_location_id(src.stock_location_id());
                newStran.uom_id(src.uom_id());
                newStran.received_qty(src.alloc_qty());
                newStran.other_amt(src.rate());
                if(opts.update_rate) {
                    newStran.rate(src.rate());
                } else {
                    newStran.rate(0.00);
                }
                newStran.bt_amt((parseFloat(newStran.received_qty()) * parseFloat(newStran.rate())).toFixed(2));
                newStran.reference_id(src.si_id());
                newStran.reference_tran_id(src.si_tran_id());
                gstOpts = {
                    txn_type: core_tx.gst.TXN_SALE,
                    origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                    target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(),
                    is_ctp: false,
                    gst_hsn_info: JSON.parse(src.gst_hsn_info),
                    row: newStran
                };
                core_tx.gst.item_gtt_reset(gstOpts);
                typeof opts.tran_add_callback != 'undefined' ? opts.tran_add_callback(newStran) : '' ;
                typeof opts.fetch_mat_callback != 'undefined' ? opts.fetch_mat_callback(newStran) : '' ;
                
                coreWebApp.afterNewRowAdded(newStran);
            }
        }
        var i = 1;
        opts.stock_tran().forEach(itm => {
           itm.sl_no(i++); 
        });
        opts.stock_tran.valueHasMutated();
        delete opts.model; // remove the temporary model created
        // Refresh smart combos
        $('[id=material_type_id],[id=material_id],[id=stock_location_id],[id=uom_id]').each(function() {
            $(this).trigger('change');
        });
        return true;
    }
    si_for_sr.si_sel_update = si_sel_update;
    
    function find_si_ref(stock_tran, si_tran_id) {
        for (var p = 0; p < stock_tran().length; ++p) {
            if(stock_tran()[p].reference_tran_id() == si_tran_id) {
                return stock_tran()[p];
            }
        }
        return false;
    }
    
    function si_sel_click(row) {
        if(row.si_sel()) {
            row.alloc_qty(row.bal_qty());
        } else {
            row.alloc_qty(0.00);
        }
    }
    si_for_sr.si_sel_click = si_sel_click;
    
}(window.core_st.si_for_sr));
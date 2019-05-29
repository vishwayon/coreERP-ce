// Declare core_tx Namespace
typeof window.core_tx == 'undefined' ? window.core_tx = {} : '';

(function (core_tx) {

    function recalculate_tax() {
        calculate_tax(0, coreWebApp.ModelBo.before_tax_amt());
    }
    core_tx.recalculate_tax = recalculate_tax;

    function get_tax_detail() {
        calculate_tax(1, coreWebApp.ModelBo.before_tax_amt());
    }
    core_tx.get_tax_detail = get_tax_detail;

    function calculate_tax($isnew, $before_tax_amt) {
        if (coreWebApp.ModelBo.tax_schedule_id() == -1 || coreWebApp.ModelBo.tax_schedule_id() == null) {
            return 'Tax Details', 'Select Tax Schedule to get details';
        } else {
            $.ajax({
                url: '?r=core%2Ftx%2Fform%2Fcalculatetax',
                type: 'GET',
                data: {'tax_schedule_id': coreWebApp.ModelBo.tax_schedule_id(), 'base_amt': $before_tax_amt,
                    'qty': 0, 'tax_detail_temp': ko.mapping.toJSON(coreWebApp.ModelBo.tax_detail_temp()), 'isnew': $isnew},
                complete: function () {
                    coreWebApp.stoploading();
                },
                success: function (resultdata) {
                    var jsonResult = $.parseJSON(resultdata);
                    if (jsonResult['status'] === 'ok') {
                        //remove all Tax Details 
                        coreWebApp.ModelBo.tax_detail_temp.removeAll();
                        //update Tax Detail
                        for (var p = 0; p < jsonResult['tax_applied'].length; ++p)
                        {
                            var r1 = coreWebApp.ModelBo.addNewRow('tax_detail_temp', coreWebApp.ModelBo);
                            r1.tax_schedule_id(jsonResult['tax_applied'][p]['tax_schedule_id']);
                            r1.tax_detail_id(jsonResult['tax_applied'][p]['tax_detail_id']);
                            r1.parent_tax_details(jsonResult['tax_applied'][p]['parent_tax_details']);
                            r1.description(jsonResult['tax_applied'][p]['description']);
                            r1.step_id(jsonResult['tax_applied'][p]['step_id']);
                            r1.account_id(jsonResult['tax_applied'][p]['account_id']);
                            r1.en_tax_type(jsonResult['tax_applied'][p]['en_tax_type']);
                            r1.en_round_type(jsonResult['tax_applied'][p]['en_round_type']);
                            r1.tax_perc(jsonResult['tax_applied'][p]['tax_perc']);
                            r1.tax_on_perc(jsonResult['tax_applied'][p]['tax_on_perc']);
                            r1.tax_on_min_amt(jsonResult['tax_applied'][p]['tax_on_min_amt']);
                            r1.tax_on_max_amt(jsonResult['tax_applied'][p]['tax_on_max_amt']);
                            r1.min_tax_amt(jsonResult['tax_applied'][p]['min_tax_amt']);
                            r1.max_tax_amt(jsonResult['tax_applied'][p]['max_tax_amt']);
                            r1.tax_amt(jsonResult['tax_applied'][p]['tax_amt']);
                            r1.custom_rate(jsonResult['tax_applied'][p]['custom_rate']);
                        }
                        console.log('tax: ' + jsonResult['tax_schedule_name']);
                        coreWebApp.ModelBo.tax_schedule_name(jsonResult['tax_schedule_name']);
                        coreWebApp.ModelBo.tax_detail_temp.valueHasMutated();
                        $('#tax_detail_temp').children().find('[name=en_tax_type]').each(function () {
                            $(this).attr('disabled', 'disabled')
                        });
//                            applysmartcontrols();
                    }
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
                }
            });
            return 'OK';
        }
    }
    function custom_rate_enable(dataItem) {
        if (typeof dataItem.en_tax_type == 'undefined')
            return;
        if (dataItem.en_tax_type() == 1 || dataItem.en_tax_type() == 2) {
            return true;
        } else {
            return false;
        }
    }
    core_tx.custom_rate_enable = custom_rate_enable;

    function tax_schedule_enable(dataItem) {
        if (typeof dataItem.enable_tax_schedule == 'undefined')
            return true;
        return dataItem.enable_tax_schedule;
    }
    core_tx.tax_schedule_enable = tax_schedule_enable;

    function SelectTax() {
        coreWebApp.showAlloc('core/tx', '/taxSchedule/worker/TaxScheduleSelector', 'core_tx.tax_schedule_init', 'core_tx.tax_schedule_update', 'core_tx.CancelAllocUpdate');
    }
    core_tx.SelectTax = SelectTax;

    function CancelAllocUpdate() {
    }
    core_tx.CancelAllocUpdate = CancelAllocUpdate;

    function ClearTax() {
        //remove all Tax Details 
        coreWebApp.ModelBo.tax_schedule_id(-1);
        coreWebApp.ModelBo.tax_schedule_name('');
        coreWebApp.ModelBo.tax_tran.removeAll();
        coreWebApp.ModelBo.tax_detail_temp.removeAll();
    }
    core_tx.ClearTax = ClearTax;

    function tax_schedule_init() {
    }
    core_tx.tax_schedule_init = tax_schedule_init;

    //function to update uom of material
    function tax_schedule_update() {
        if (coreWebApp.ModelBo.tax_schedule_id() == -1 || coreWebApp.ModelBo.tax_schedule_id() == null) {
            return 'Tax Details', 'Select Tax Schedule to get details';
        }
        $.ajax({
            url: '?r=core%2Ftx%2Fform%2Fcalculatetax',
            type: 'GET',
            data: {'tax_schedule_id': coreWebApp.ModelBo.tax_schedule_id(), 'base_amt': coreWebApp.ModelBo.before_tax_amt(),
                'qty': 0, 'tax_detail_temp': ko.mapping.toJSON(coreWebApp.ModelBo.tax_detail_temp(), {ignore: ['__el']}), 'isnew': 0},
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    //remove all Tax Details 
                    coreWebApp.ModelBo.tax_tran.removeAll();
                    //update Tax Detail
                    for (var p = 0; p < jsonResult['tax_applied'].length; p++)
                    {
                        var r1 = coreWebApp.ModelBo.addNewRow('tax_tran', coreWebApp.ModelBo, true);
                        coreWebApp.afterNewRowAdded();
                        r1.tax_schedule_id(jsonResult['tax_applied'][p]['tax_schedule_id']);
                        r1.tax_detail_id(jsonResult['tax_applied'][p]['tax_detail_id']);
                        r1.description(jsonResult['tax_applied'][p]['description']);
                        r1.step_id(jsonResult['tax_applied'][p]['step_id']);
                        r1.account_id(jsonResult['tax_applied'][p]['account_id']);
                        r1.supplier_paid(true);
                        r1.tax_amt(jsonResult['tax_applied'][p]['tax_amt']);
                        r1.custom_rate(jsonResult['tax_applied'][p]['custom_rate']);
                    }

                    coreWebApp.ModelBo.tax_schedule_name(jsonResult['tax_schedule_name']);
                    coreWebApp.ModelBo.tax_tran.valueHasMutated();
                    //applysmartcontrols();
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
        return 'OK';
    }
    core_tx.tax_schedule_update = tax_schedule_update;

    function tax_liability_acc_enable(dataItem) {
        if (typeof dataItem.supplier_paid == 'undefined')
            return;
        if (dataItem.supplier_paid() == false) {
            return true;
        } else {
            return false;
        }
    }
    core_tx.tax_liability_acc_enable = tax_liability_acc_enable;

    function select_clear_tax_enable(dataItem) {
        if (typeof dataItem.status == 'undefined')
            return;
        if (dataItem.status() == 5) {
            return false;
        } else {
            return true;
        }
    }
    core_tx.select_clear_tax_enable = select_clear_tax_enable;


    function tx_tax_amt_changed(dataItem) {
        if (coreWebApp.ModelBo.fc_type_id() != 0) {
            if (coreWebApp.ModelBo.exch_rate() == 0) {
                dataItem.tax_amt_fc(0);
            } else {
                dataItem.tax_amt_fc((parseFloat(dataItem.tax_amt()) / coreWebApp.ModelBo.exch_rate()).toFixed(2));
            }
        }
    }
    core_tx.tx_tax_amt_changed = tx_tax_amt_changed;


    function enable_visible_fc(dataItem) {
        if (parseFloat(coreWebApp.ModelBo.fc_type_id()) != 0) {
            return true;
        } else {
            return false;
        }
    }
    core_tx.enable_visible_fc = enable_visible_fc;

    function disable_hide_fc() {
        if (parseFloat(coreWebApp.ModelBo.fc_type_id()) != 0) {
            return false;
        } else {
            return true;
        }
    }
    core_tx.disable_hide_fc = disable_hide_fc;

    function tax_schedule_filter(fltr, datacontext) {
        if (coreWebApp.ModelBo.applicable_to_supplier() == true && coreWebApp.ModelBo.applicable_to_customer() == false) {
            fltr = ' applicable_to_supplier = true';
        }
        if (coreWebApp.ModelBo.applicable_to_supplier() == false && coreWebApp.ModelBo.applicable_to_customer() == true) {
            fltr = ' applicable_to_customer = true';
        }
        if (typeof coreWebApp.ModelBo.tax_type_id == 'undefined')
            return fltr;
        fltr += ' and tax_type_id = ' + coreWebApp.ModelBo.tax_type_id();
        return fltr;
    }
    core_tx.tax_schedule_filter = tax_schedule_filter;

}(window.core_tx));

// GST Methods and utils that are part of tx
window.core_tx.gst = {};
(function (gst) {
    // Transaction Types
    gst.TXN_PURCH = 'PURCH';
    gst.TXN_SALE = 'SALE';
    
    // GST Types
    gst.SALE_SGST_CGST = 301;
    gst.SALE_IGST = 302;
    gst.SALE_DEEMED_EXPORT = 303;
    gst.SALE_SEZ_WP = 304;
    gst.SALE_SEZ_WOP = 305;
    gst.SALE_EXPORT_WP = 306;
    gst.SALE_EXPORT_WOP = 307;
    
    gst.PURCH_SGST_CGST = 401;
    gst.PURCH_IGST = 402;
    gst.PURCH_IMPORT = 403;
    gst.PURCH_COMPOS = 404;
    gst.PURCH_SEZ = 405;
    
    gst.SEZ_WITHOUT_GST = coreWebApp.branch_gst_info.gst_sez_wop;
    gst.EXP_WITHOUT_GST = coreWebApp.branch_gst_info.gst_exp_wop;
    
    //  opts - Options to contain {
    //      txn_type: gst constants for txn_type
    //      origin_gst_state_id: The origin gst state id 
    //      target_gst_state_id: The billto/customer/supplier target state
    //      gst_hsn_info: The gst_hsn record object received for the selected material/service
    //      row: The row that the user is currently editing. Must contain all gtt_* fields from tx.gst_tax_tran
    //  }
    function item_gtt_reset(opts) {
        // Fill all non changing objects
        opts.row.gtt_hsn_sc_code(opts.gst_hsn_info.hsn_sc_code);
        opts.row.gtt_hsn_sc_type(opts.gst_hsn_info.hsn_sc_type);
        opts.row.gtt_gst_rate_id(opts.gst_hsn_info.gst_rate_id);
        // fill accounts
        opts.row.gtt_sgst_itc_account_id(opts.gst_hsn_info.sgst_itc_account_id);
        opts.row.gtt_cgst_itc_account_id(opts.gst_hsn_info.cgst_itc_account_id);
        opts.row.gtt_igst_itc_account_id(opts.gst_hsn_info.igst_itc_account_id);
        opts.row.gtt_cess_itc_account_id(opts.gst_hsn_info.cess_itc_account_id);
        opts.row.gtt_sgst_account_id(opts.gst_hsn_info.sgst_account_id);
        opts.row.gtt_cgst_account_id(opts.gst_hsn_info.cgst_account_id);
        opts.row.gtt_igst_account_id(opts.gst_hsn_info.igst_account_id);
        opts.row.gtt_cess_account_id(opts.gst_hsn_info.cess_account_id);
        // Determine the Vat Type
        gst.get_vat_type(opts);
        
        // Fill rate based on case
        if(opts.vat_type_id == gst.SALE_SGST_CGST || opts.vat_type_id == gst.PURCH_SGST_CGST) {
            // fill rates
            opts.row.gtt_sgst_pcnt(opts.gst_hsn_info.sgst_pcnt);
            opts.row.gtt_cgst_pcnt(opts.gst_hsn_info.cgst_pcnt);
            opts.row.gtt_igst_pcnt(0.00);
            opts.row.gtt_cess_pcnt(opts.gst_hsn_info.cess_pcnt);
            
        } else if (opts.vat_type_id == gst.SALE_IGST || opts.vat_type_id == gst.SALE_SEZ_WP
                || opts.vat_type_id == gst.SALE_EXPORT_WP || opts.vat_type_id == gst.SALE_DEEMED_EXPORT
                || opts.vat_type_id == gst.PURCH_IGST || opts.vat_type_id == gst.PURCH_IMPORT
                || opts.vat_type_id == gst.PURCH_SEZ) {
            opts.row.gtt_sgst_pcnt(0.00);
            opts.row.gtt_cgst_pcnt(0.00);
            opts.row.gtt_igst_pcnt(opts.gst_hsn_info.igst_pcnt);
            opts.row.gtt_cess_pcnt(opts.gst_hsn_info.cess_pcnt);
            
        } else if (opts.vat_type_id == gst.SALE_EXPORT_WOP || opts.vat_type_id == gst.SALE_SEZ_WOP
                || opts.vat_type_id == gst.PURCH_COMPOS) {
            // Do nothing as it is at nil rate
            opts.row.gtt_sgst_pcnt(0.00);
            opts.row.gtt_cgst_pcnt(0.00);
            opts.row.gtt_igst_pcnt(0.00);
            opts.row.gtt_cess_pcnt(0.00);
        }
    }
    gst.item_gtt_reset = item_gtt_reset;
    
    //  opts to contain {
    //      bt_amt: before tax amount. All calcs will be done on this base figure
    //      row: Row to be updated (must have tx.gst_tax_tran structure)
    //  }
    function item_gtt_calc(opts) {
        if(opts.row.gtt_tax_amt_ov()) {
            // Supress calculatios as the user has overridden taxes
        } else {
            opts.row.gtt_sgst_amt((opts.bt_amt * parseFloat(opts.row.gtt_sgst_pcnt()) / 100).toFixed(2));
            opts.row.gtt_cgst_amt((opts.bt_amt * parseFloat(opts.row.gtt_cgst_pcnt()) / 100).toFixed(2));
            opts.row.gtt_igst_amt((opts.bt_amt * parseFloat(opts.row.gtt_igst_pcnt()) / 100).toFixed(2));
            opts.row.gtt_cess_amt((opts.bt_amt * parseFloat(opts.row.gtt_cess_pcnt()) / 100).toFixed(2));
            // Lastly update bt_amt to ensure item_calc
            opts.row.gtt_bt_amt(opts.bt_amt);
        }
    }
    gst.item_gtt_calc = item_gtt_calc;
    
    // This method is used only in Advance as it is considered to be inclusive of gst.
    //  opts to contain {
    //      bt_amt: before tax amount. All calcs will be done on this base figure
    //      row: Row to be updated (must have tx.gst_tax_tran structure)
    //  }
    function item_gtt_calc_inverse(opts) {
        if(opts.row.gtt_tax_amt_ov()) {
            // Supress calculatios as the user has overridden taxes
        } else {
            var tot_tax_pcnt = 100 + parseFloat(opts.row.gtt_sgst_pcnt()) + parseFloat(opts.row.gtt_cgst_pcnt()) 
                    + parseFloat(opts.row.gtt_igst_pcnt()) + parseFloat(opts.row.gtt_cess_pcnt());
            opts.row.gtt_sgst_amt((opts.bt_amt * parseFloat(opts.row.gtt_sgst_pcnt()) / tot_tax_pcnt).toFixed(2));
            opts.row.gtt_cgst_amt((opts.bt_amt * parseFloat(opts.row.gtt_cgst_pcnt()) / tot_tax_pcnt).toFixed(2));
            opts.row.gtt_igst_amt((opts.bt_amt * parseFloat(opts.row.gtt_igst_pcnt()) / tot_tax_pcnt).toFixed(2));
            opts.row.gtt_cess_amt((opts.bt_amt * parseFloat(opts.row.gtt_cess_pcnt()) / tot_tax_pcnt).toFixed(2));
            var rc_amt = opts.bt_amt - (parseFloat(opts.row.gtt_sgst_amt()) + parseFloat(opts.row.gtt_cgst_amt())
                + parseFloat(opts.row.gtt_igst_amt()) + parseFloat(opts.row.gtt_cess_amt()));
            // Lastly update bt_amt to ensure item_calc
            opts.row.gtt_bt_amt(rc_amt.toFixed(2)); 
        }
    }
    gst.item_gtt_calc_inverse = item_gtt_calc_inverse;
    
    //  opts - Options to contain {
    //      txn_type: gst constants for txn_type
    //      origin_gst_state_id: The origin gst state id 
    //      target_gst_state_id: The billto/customer/supplier target state
    //  } 
    //  sets the vat_type_id in opts
    function get_vat_type(opts) {
        if(opts.txn_type == gst.TXN_SALE) {
            if(opts.target_gst_state_id == 99) { // Export Sale
                opts.vat_type_id = gst.EXP_WITHOUT_GST ? gst.SALE_EXPORT_WOP : gst.SALE_EXPORT_WP;
            } else if (opts.target_gst_state_id == 98) { // SEZ Sale
                opts.vat_type_id = gst.SEZ_WITHOUT_GST ? gst.SALE_SEZ_WOP : gst.SALE_SEZ_WP;
            } else if (opts.origin_gst_state_id == opts.target_gst_state_id) { // Local Sale
                opts.vat_type_id = gst.SALE_SGST_CGST;
            } else if (opts.origin_gst_state_id != opts.target_gst_state_id) { // Inter State Sale
                opts.vat_type_id = gst.SALE_IGST;
            } else { // Fallback to cause errors
                opts.vat_type_id = -1; 
            }
        } else if(opts.txn_type == gst.TXN_PURCH) {
            if(typeof opts.is_ctp == 'undefined') { // Temporary Code to fix all purchases
                alert('Supplier Composition Status missing in Options. Contact software support');
            }
            if(opts.origin_gst_state_id == 99) { // Purchase Import
                opts.vat_type_id = gst.PURCH_IMPORT;
            } else if(opts.origin_gst_state_id == 98) { 
                opts.vat_type_id = gst.PURCH_SEZ;
            } else if (opts.origin_gst_state_id == opts.target_gst_state_id) {
                opts.vat_type_id = gst.PURCH_SGST_CGST;
                if (opts.is_ctp) {
                    opts.vat_type_id = gst.PURCH_COMPOS;
                }
            } else if (opts.origin_gst_state_id != opts.target_gst_state_id) {
                opts.vat_type_id = gst.PURCH_IGST;
                if (opts.is_ctp) {
                    alert('Inter State purchase from Composition Taxable person/Unregisterd person is not allowed.');
                    opts.vat_type_id = -1; // Generate error as Interstate purchase from CTP is not allowed
                }
            } else { // Fallback to cause errors
                opts.vat_type_id = -1;
            }
        }
    }
    gst.get_vat_type = get_vat_type;
    
    //  opts - Options to contain {
    //      txn_type: gst constants for txn_type
    //      origin_gst_state_id: The origin gst state id 
    //      target_gst_state_id: The billto/customer/supplier target state
    //      tran: Any tran table containing array of rows. Must contain all gtt_* fields from tx.gst_tax_tran
    //      call_back: the call back method after gst rate reset
    //  }
    function reapply_gtt(opts) {
        var gst_rates = [];
        if(typeof opts.tran.length != 'undefined') {
            opts.tran().forEach(function (x) {
                gst_rates.push(x.gtt_gst_rate_id());
            });
        } else {
            gst_rates.push(opts.tran.gtt_gst_rate_id());
        }
        $.ajax({
            url: '?r=core/tx/form/get-gst-rates',
            type: 'GET',
            dataType: 'json',
            data: { gst_rates: gst_rates },
            success: function(result) {
                gst.get_vat_type(opts);
                if(typeof opts.tran.length != 'undefined') {
                    opts.tran().forEach(function (row) {
                        result.forEach(function (gst_rate) {
                            if(gst_rate.gst_rate_id == row.gtt_gst_rate_id()) {
                                // Fill rate based on case
                                if(opts.vat_type_id == gst.SALE_SGST_CGST || opts.vat_type_id == gst.PURCH_SGST_CGST) {
                                    // fill rates
                                    row.gtt_sgst_pcnt(gst_rate.sgst_pcnt);
                                    row.gtt_cgst_pcnt(gst_rate.cgst_pcnt);
                                    row.gtt_igst_pcnt(0.00);
                                    row.gtt_cess_pcnt(gst_rate.cess_pcnt);

                                } else if (opts.vat_type_id == gst.SALE_IGST || opts.vat_type_id == gst.SALE_DEEMED_EXPORT
                                        || opts.vat_type_id == gst.SALE_EXPORT_WP || opts.vat_type_id == gst.SALE_SEZ_WP
                                        || opts.vat_type_id == gst.PURCH_IGST || opts.vat_type_id == gst.PURCH_IMPORT
                                        || opts.vat_type_id == gst.PURCH_SEZ) {
                                    row.gtt_sgst_pcnt(0.00);
                                    row.gtt_cgst_pcnt(0.00);
                                    row.gtt_igst_pcnt(gst_rate.igst_pcnt);
                                    row.gtt_cess_pcnt(gst_rate.cess_pcnt);

                                } else if (opts.vat_type_id == gst.SALE_EXPORT_WOP || opts.vat_type_id == gst.SALE_SEZ_WOP
                                        || opts.vat_type_id == gst.PURCH_COMPOS) {
                                    // Do nothing as it is at zero rate
                                    row.gtt_sgst_pcnt(0.00);
                                    row.gtt_cgst_pcnt(0.00);
                                    row.gtt_igst_pcnt(0.00);
                                    row.gtt_cess_pcnt(0.00);
                                }
                            }
                        });
                        gst.item_gtt_calc({ bt_amt: parseFloat(row.gtt_bt_amt()), row: row });
                    });
                } else {
                    row = opts.tran;
                    result.forEach(function (gst_rate) {
                        if(gst_rate.gst_rate_id == row.gtt_gst_rate_id()) {
                            // Fill rate based on case
                            if(opts.vat_type_id == gst.SALE_SGST_CGST || opts.vat_type_id == gst.PURCH_SGST_CGST) {
                                // fill rates
                                row.gtt_sgst_pcnt(gst_rate.sgst_pcnt);
                                row.gtt_cgst_pcnt(gst_rate.cgst_pcnt);
                                row.gtt_igst_pcnt(0.00);
                                row.gtt_cess_pcnt(gst_rate.cess_pcnt);
                            } else if (opts.vat_type_id == gst.SALE_IGST || opts.vat_type_id == gst.SALE_DEEMED_EXPORT
                                    || opts.vat_type_id == gst.SALE_EXPORT_WP || opts.vat_type_id == gst.SALE_SEZ_WP
                                    || opts.vat_type_id == gst.PURCH_IGST || opts.vat_type_id == gst.PURCH_IMPORT
                                    || opts.vat_type_id == gst.PURCH_SEZ) {
                                row.gtt_sgst_pcnt(0.00);
                                row.gtt_cgst_pcnt(0.00);
                                row.gtt_igst_pcnt(gst_rate.igst_pcnt);
                                row.gtt_cess_pcnt(gst_rate.cess_pcnt);
                            } else if (opts.vat_type_id == gst.SALE_EXPORT_WOP || opts.vat_type_id == gst.SALE_SEZ_WOP
                                    || opts.vat_type_id == gst.PURCH_COMPOS) {
                                // Do nothing as it is at zero rate
                                row.gtt_sgst_pcnt(0.00);
                                row.gtt_cgst_pcnt(0.00);
                                row.gtt_igst_pcnt(0.00);
                                row.gtt_cess_pcnt(0.00);
                            }
                        }
                    });
                    gst.item_gtt_calc({ bt_amt: parseFloat(row.gtt_bt_amt()), row: row });
                }
                if(typeof opts.call_back != 'undefined'){
                    opts.call_back(opts);
                }
            },
            error: function(data) {
                coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
            }
        });
    }
    gst.reapply_gtt = reapply_gtt;
    
    function tax_desc(row) {
        if(parseFloat(row.gtt_igst_pcnt())>0) {
            return "IGST @ " + coreWebApp.formatNumber((parseFloat(row.gtt_igst_pcnt()) + parseFloat(row.gtt_sgst_pcnt()) + parseFloat(row.gtt_cgst_pcnt())), 2) + " %";
        } else {
            return "SGST/CGST @ " + coreWebApp.formatNumber((parseFloat(row.gtt_igst_pcnt()) + parseFloat(row.gtt_sgst_pcnt()) + parseFloat(row.gtt_cgst_pcnt())), 2) + " %";
        }
    }
    gst.tax_desc = tax_desc;
    
    function ovrd_gst_rate(row) {
        
    }
    gst.ovrd_gst_rate = ovrd_gst_rate;
    
}(window.core_tx.gst));

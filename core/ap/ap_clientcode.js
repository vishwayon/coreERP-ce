// Declare core_ap Namespace
typeof window.core_ap == 'undefined' ? window.core_ap = {} : '';

(function (core_ap) {

    function supp_name_enable(dataItem) {
        return coreWebApp.ModelBo.annex_info.is_overridden();
    }
    core_ap.supp_name_enable = supp_name_enable;

    function gst_reg_name_enable(dataItem) {
        return coreWebApp.ModelBo.annex_info.satutory_details.diff_gst_name();
    }
    core_ap.gst_reg_name_enable = gst_reg_name_enable;

    function supplier_desc_changed(dataItem) {
        if (coreWebApp.ModelBo.annex_info.is_overridden() == false) {
            coreWebApp.ModelBo.supplier_name(coreWebApp.ModelBo.supplier());
        } else {
            if (coreWebApp.ModelBo.supplier_name() == '') {
                coreWebApp.ModelBo.supplier_name(coreWebApp.ModelBo.supplier());
            }
        }
    }
    core_ap.supplier_desc_changed = supplier_desc_changed;

    function ap_enable_visible_fc(dataItem) {
        if (parseFloat(coreWebApp.ModelBo.fc_type_id()) != 0) {
            return true;
        } else {
            return false;
        }
    }
    core_ap.ap_enable_visible_fc = ap_enable_visible_fc;

    function ap_enable_visible_local(dataItem) {
        if (parseFloat(coreWebApp.ModelBo.fc_type_id()) == 0) {
            return true;
        } else {
            return false;
        }
    }
    core_ap.ap_enable_visible_local = ap_enable_visible_local;

    function supplier_tds_enable(dataItem) {
        if (dataItem.is_tds_applied()) {
            return true;
        } else {
            return false;
        }
    }
    core_ap.supplier_tds_enable = supplier_tds_enable;

    function tds_person_type_update(row) {
        row.tds_section_id(-1);
        row.base_rate_perc(0);
        row.ecess_perc(0);
        row.surcharge_perc(0);
    }
    core_ap.tds_person_type_update = tds_person_type_update;

    function fetch_tds_rate(row) {
        if (row.tds_section_id() != -1 && row.tds_person_type_id() != -1) {
            $.ajax({
                url: '?r=core/ap/form/fetch-tds-rate',
                type: 'GET',
                data: {
                    person_type_id: row.tds_person_type_id(), section_id: row.tds_section_id()
                },
                complete: function () {
                    coreWebApp.stoploading();
                },
                success: function (resultdata) {
                    var jsonResult = $.parseJSON(resultdata);
                    if (jsonResult['status'] === 'ok') {
                        row.base_rate_perc(jsonResult['base_rate_perc']);
                        row.ecess_perc(jsonResult['ecess_perc']);
                        row.surcharge_perc(jsonResult['surcharge_perc']);
                    }
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
                }
            });
        }
    }
    core_ap.fetch_tds_rate = fetch_tds_rate

    function tds_section_filter(fltr) {
        fltr = "person_type_id = " + coreWebApp.ModelBo.supplier_tax_info_tran()[0].tds_person_type_id();
        return fltr;
    }
    core_ap.tds_section_filter = tds_section_filter;

    function supplier_st_enable(dataItem) {
        if (dataItem.is_st_applied()) {
            return true;
        } else {
            return false;
        }
    }
    core_ap.supplier_st_enable = supplier_st_enable;


    function ap_alloc_fc_tran_changed(dataItem) {
        if (coreWebApp.ModelBo.fc_type_id() != 0) {
            dataItem.alloc_amt((parseFloat(dataItem.alloc_amt_fc()) * parseFloat(coreWebApp.ModelBo.exch_rate())).toFixed(2));
        } else {
            dataItem.alloc_amt_fc(0);
        }
    }
    core_ap.ap_alloc_fc_tran_changed = ap_alloc_fc_tran_changed;

    function ap_calc_credit_amt_total(dataItem) {
        if (dataItem.doc_type() == 'SP') {
            if (coreWebApp.ModelBo.fc_type_id() != 0) {
                dataItem.credit_amt_total(dataItem.total_amt());
                dataItem.credit_amt_total_fc(0);
            } else {
                dataItem.credit_amt_total(dataItem.total_amt());
                dataItem.credit_amt_total_fc(dataItem.total_amt_fc());
            }
        } else if (dataItem.doc_type() == 'BL') {
            var total = 0;
            var total_fc = 0;
            ko.utils.arrayForEach(dataItem.bill_tran(), function (item) {
                total += parseFloat(item.debit_amt());
                total_fc += parseFloat(item.debit_amt_fc());
            });
            if (coreWebApp.ModelBo.fc_type_id() != 0) {
                dataItem.credit_amt_total(total.toFixed(2));
                dataItem.credit_amt_total_fc(0);
            } else {
                dataItem.credit_amt_total(total.toFixed(2));
                dataItem.credit_amt_total_fc(total_fc.toFixed(2));
            }
        }
    }
    core_ap.ap_calc_credit_amt_total = ap_calc_credit_amt_total;

    function adv_alloc_ui(opts) {
        opts.module = 'core/ap';
        if (parseFloat(opts.fc_type_id) !== 0) {
            opts.alloc_view = 'advanceAlloc/AdvAllocFC';
        } else {
            opts.alloc_view = 'advanceAlloc/AdvAlloc';
        }
        opts.call_init = adv_alloc_init;
        opts.call_update = adv_alloc_update;
        coreWebApp.showAllocV2(opts);
    }
    core_ap.adv_alloc_ui = adv_alloc_ui;

    function adv_alloc_init(opts, after_init) {
        $.ajax({
            url: '?r=core/ap/form/advancealloc',
            type: 'GET',
            data: {
                voucher_id: opts.voucher_id, doc_date: opts.doc_date,
                account_id: opts.account_id, fc_type_id: opts.fc_type_id,
                exch_rate: opts.exch_rate, dc: opts.dc
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    var ap_alloc = new function () {
                        self = this;
                        this.credit_amt_total = ko.observable(opts.credit_amt_total);
                        this.credit_amt_total_fc = ko.observable(opts.credit_amt_total_fc);
                        this.total_amt = ko.pureComputed(function () {
                            var total = new Number();
                            ko.utils.arrayForEach(self.pl_temp(), function (item) {
                                total += parseFloat(item.alloc_amt());
                            });
                            return total.toFixed(2);
                        });
                        this.total_amt_fc = ko.pureComputed(function () {
                            var total_fc = new Number();
                            ko.utils.arrayForEach(self.pl_temp(), function (item) {
                                total_fc += parseFloat(item.alloc_amt_fc());
                            });
                            return total_fc.toFixed(2);
                        });
                        this.balance_total = ko.pureComputed(function () {
                            var balance_total = parseFloat(self.credit_amt_total()) - parseFloat(self.total_amt());
                            return balance_total.toFixed(2);
                        });
                        this.balance_total_fc = ko.pureComputed(function () {
                            var balance_total_fc = parseFloat(self.credit_amt_total_fc()) - parseFloat(self.total_amt_fc());
                            return balance_total_fc.toFixed(2);
                        });
                    };
                    ap_alloc.pl_temp = build_pl_temp();

                    for (var p = 0; p < jsonResult['pl_balance'].length; p++) {
                        var bal_row = jsonResult['pl_balance'][p];
                        var nr = ap_alloc.pl_temp.addNewRow();
                        nr.rl_pl_id(bal_row['rl_pl_id']);
                        nr.voucher_id(bal_row['voucher_id']);
                        nr.vch_tran_id(bal_row['vch_tran_id']);
                        nr.branch_id(bal_row['branch_id']);
                        nr.account_id(bal_row['account_id']);
                        nr.exch_rate(bal_row['exch_rate']);
                        nr.fc_type_id(bal_row['fc_type_id']);
                        nr.balance(bal_row['balance']);
                        nr.balance_fc(bal_row['balance_fc']);
                        nr.narration(bal_row['narration']);
                        nr.doc_date(bal_row['doc_date']);
                        for (var a = 0; a < opts.pl_tran().length; ++a) {
                            var plt = opts.pl_tran()[a];
                            if (plt.rl_pl_id() === bal_row['rl_pl_id']) {
                                if (opts.dc == 'C') {
                                    nr.alloc_amt(plt.debit_amt());
                                    nr.alloc_amt_fc(plt.debit_amt_fc());
                                } else {
                                    nr.alloc_amt(plt.credit_amt());
                                    nr.alloc_amt_fc(plt.credit_amt_fc());
                                }
                            }
                        }
                        ap_alloc.pl_temp.push(nr);
                    }
                    opts.model = ap_alloc;
                    $('#adv-alloc-loading').hide();
                    after_init(); //callback handler as the ajax call is in diff thread
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
    }
    core_ap.adv_alloc_init = adv_alloc_init;

    function build_pl_temp() {
        var pl_temp = ko.observableArray();
        pl_temp.addNewRow = function () {
            var cobj = new Object();
            cobj.branch_id = ko.observable(0);
            cobj.account_id = ko.observable(0);
            cobj.fc_type_id = ko.observable(0);

            cobj.voucher_id = ko.observable('');
            cobj.vch_tran_id = ko.observable('');
            cobj.rl_pl_id = ko.observable('');
            cobj.narration = ko.observable('');

            cobj.exch_rate = ko.observable(1);
            cobj.balance = ko.observable(0);
            cobj.balance_fc = ko.observable(0);
            cobj.alloc_amt = ko.observable(0);
            cobj.alloc_amt_fc = ko.observable(0);
            cobj.doc_date = ko.observable('1970-01-01');
            cobj.is_select = ko.observable(false);
            return cobj;
        };
        return pl_temp;
    }
    core_ap.build_pl_temp = build_pl_temp;

    function adv_alloc_update(opts) {
        var is_valid = true;
        if (opts.fc_type_id !== 0) {
            // validate balance fc
            if (opts.model.balance_total_fc() < 0) {
                coreWebApp.toastmsg('warning', 'Allocations', 'Settlements cannot exceed Document Amount');
                return false;
            }
        } else {
            // validate balance
            if (opts.model.balance_total() < 0) {
                coreWebApp.toastmsg('warning', 'Allocations', 'Settlements cannot exceed Document Amount');
                return false;
            }
        }
        // Validate line items for excess allocation
        var is_valid = true;
        ko.utils.arrayForEach(opts.model.pl_temp(), function (r) {
            if (parseFloat(r.alloc_amt()) > parseFloat(r.balance())) {
                coreWebApp.toastmsg('warning', 'Allocations', 'Settlement cannot exceed advance balance for [' + r.voucher_id() + ']');
                is_valid = false;
                return;
            }
            ;
        });
        // Return without updating when validations fail
        if (!is_valid) {
            return false;
        }

        // clear existing alloc
        opts.pl_tran.removeAll();
        for (var p = 0; p < opts.model.pl_temp().length; ++p) {
            var plt = opts.model.pl_temp()[p];
            if (parseFloat(plt.alloc_amt()) > 0 || parseFloat(plt.alloc_amt_fc()) > 0) {
                var nr = coreWebApp.ModelBo.addNewRow('payable_ledger_alloc_tran', coreWebApp.ModelBo);
                nr.rl_pl_id(plt['rl_pl_id']());
                nr.adv_ref_id(plt.voucher_id());
                nr.voucher_id(plt.voucher_id());
                nr.vch_tran_id(plt.vch_tran_id());
                nr.branch_id(plt.branch_id());
                nr.account_id(plt.account_id());
                nr.exch_rate(plt.exch_rate());
                if (opts.dc == 'C') {
                    nr.debit_amt(plt.alloc_amt());
                    nr.debit_amt_fc(plt.alloc_amt_fc());
                } else {
                    nr.credit_amt(plt.alloc_amt());
                    nr.credit_amt_fc(plt.alloc_amt_fc());
                }
                nr.doc_date(plt.doc_date());
                nr.adv_ref_date(plt.doc_date());
                if (opts.dc == 'C') {
                    nr.net_debit_amt(nr.debit_amt() + nr.credit_exch_diff());
                    nr.net_debit_amt_fc(nr.debit_amt_fc() + nr.write_off_amt_fc());
                } else {
                    nr.net_credit_amt(nr.credit_amt() + nr.write_off_amt() + nr.credit_exch_diff());
                    nr.net_credit_amt_fc(nr.credit_amt_fc() + nr.write_off_amt_fc());
                }
            }
        }
        opts.pl_tran.valueHasMutated();
        delete opts.model; // remove the temporary model created
        return true;
    }
    core_ap.adv_alloc_update = adv_alloc_update;

    function clearAdvalloc() {
        coreWebApp.ModelBo.payable_ledger_alloc_tran.removeAll();
    }
    core_ap.clearAdvalloc = clearAdvalloc;

    function cancelAllocUpdate() {
        coreWebApp.ModelBo.payable_ledger_temp.removeAll();
    }
    core_ap.cancelAllocUpdate = cancelAllocUpdate;

    function SuppOPBL_fc_tran_changed(dataItem) {
        if (dataItem.fc_type_id() == 0) {
            dataItem.exch_rate(1);
            dataItem.debit_amt_fc(0);
            dataItem.credit_amt_fc(0);
        } else {
            dataItem.debit_amt((parseFloat(dataItem.debit_amt_fc()) * dataItem.exch_rate()).toFixed(2));
            dataItem.credit_amt((parseFloat(dataItem.credit_amt_fc()) * dataItem.exch_rate()).toFixed(2));
        }
    }
    core_ap.SuppOPBL_fc_tran_changed = SuppOPBL_fc_tran_changed;


    function suppOPBL_enable_visible_fc(dataItem) {
        if (typeof dataItem.fc_type_id == 'undefined')
            return;
        if (parseFloat(dataItem.fc_type_id()) != 0) {
            return true;
        } else {
            return false;
        }
    }
    core_ap.suppOPBL_enable_visible_fc = suppOPBL_enable_visible_fc;

    function suppOPBL_enable_visible_local(dataItem) {
        if (typeof dataItem.fc_type_id == 'undefined')
            return;
        if (parseFloat(dataItem.fc_type_id()) == 0) {
            return true;
        } else {
            return false;
        }
    }
    core_ap.suppOPBL_enable_visible_local = suppOPBL_enable_visible_local;

    function msmeda_enable(dataItem) {
        if (typeof coreWebApp.ModelBo.annex_info.msmeda == 'undefined')
            return;
        if (coreWebApp.ModelBo.annex_info.msmeda.is_msmeda_registered()) {
            return true;
        } else {
            return false;
        }
    }
    core_ap.msmeda_enable = msmeda_enable;

    function visible_unstl_adv(dataItem) {
        return coreWebApp.ModelBo.status() != 5;
    }
    core_ap.visible_unstl_adv = visible_unstl_adv;

    // opts structure {
    //      supp_id: Supplier id
    // } 
    // modifies opts to return
    // result object {
    //      addr: Contains the selected address
    //      gst_state: contains the gst_state with code
    //      gst_state_id: Contains the gst-state
    //      gstin: Contains the GSTIN
    //  }
    function select_address(opts) {
        opts.module = 'core/ap';
        opts.alloc_view = 'addrSelect/AddrSelect';
        opts.call_init = addr_select_init;
        opts.call_update = addr_select_update;
        coreWebApp.showAllocV2(opts);
    }
    core_ap.select_address = select_address;

    function addr_select_init(opts, after_init) {
        $.ajax({
            url: '?r=core/ap/form/list-supp-addr',
            type: 'GET',
            dataType: 'json',
            data: {
                supplier_id: opts.supp_id
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var addr_sel = new function () {
                    self = this;
                };
                addr_sel.addr_temp = ko.mapping.fromJS(resultdata);
                opts.model = addr_sel;
                $('#addr-loading').hide();
                after_init(); //callback handler as the ajax call is in diff thread
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Supplier Address', 'Failed with errors on server', false);
            }
        });

    }
    core_ap.addr_select_init = addr_select_init;

    function addr_select_update(opts) {
        opts.model.addr_temp().forEach(function (x) {
            if (x.select()) {
                opts.result = new function () {};
                opts.result.addr = x.addr();
                opts.result.gst_state_id = x.gst_state_id();
                opts.result.gst_state = x.gst_state();
                opts.result.gstin = x.gstin();
                opts.result.is_ctp = x.is_ctp();
            }
        });
        return true;
    }
    core_ap.addr_select_update = addr_select_update;

    // opts structure {
    //      supp_id: Supplier id
    // } 
    // modifies opts to return
    // result object {
    //      addr: Contains the selected address
    //      gst_state: contains the gst_state with code
    //      gst_state_id: Contains the gst-state
    //      gstin: Contains the GSTIN
    //  }
    function get_address(opts) {
        $.ajax({
            url: '?r=core/ap/form/fetch-supp-addr',
            type: 'GET',
            dataType: 'json',
            data: {
                supplier_id: opts.supp_id
            },
            success: function (result) {
                if (typeof result.gst_state_id != 'undefined') {
                    opts.result = new function () {};
                    opts.result.addr = result.addr;
                    opts.result.gst_state_id = result.gst_state_id;
                    opts.result.gst_state = result.gst_state;
                    opts.result.gstin = result.gstin;
                    opts.result.is_ctp = result.is_ctp;
                }
                if (typeof opts.after_update != 'undefined') {
                    opts.after_update(opts);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Supplier Address', 'Failed with errors on server', false);
            }
        });
    }
    core_ap.get_address = get_address;

    function enable_recodate() {
        return coreWebApp.ModelBo.collected();
    }
    core_ap.enable_recodate = enable_recodate;

    function display_ctp_msg() {
        return coreWebApp.ModelBo.annex_info.satutory_details.is_ctp();
    }
    core_ap.display_ctp_msg = display_ctp_msg;

    function pymt_account_combo_filter(fltr) {
        if (coreWebApp.ModelBo.pymt_type() == 0) {
            fltr = ' account_type_id in(1, 2)';
        }
        if (coreWebApp.ModelBo.pymt_type() == 1) {
            fltr = ' account_type_id not in (0, 1, 2, 7, 12, 45, 46, 47)';
        }
        return fltr;
    }
    core_ap.pymt_account_combo_filter = pymt_account_combo_filter;
    
    function rpt_supp_type_filter(fltr, dataItem) {
        if (parseInt($('#psupp_type_id').val()) !== -1 && parseInt($('#psupp_type_id').val()) !== 0) {
            fltr = ' (supp_type_id = ' + $('#psupp_type_id').val() + ' Or supplier_id = 0)';
        }
        return fltr;
    }
    core_ap.rpt_supp_type_filter = rpt_supp_type_filter;
    
    function supp_type_enable(dataItem) {
        if ((coreWebApp.ModelBo.supplier_id() == -1) || (coreWebApp.ModelBo.supplier_id() == 'undefined') ){
            return true;
        } 
        return false;
    }
    core_ap.supp_type_enable = supp_type_enable;


}(window.core_ap));

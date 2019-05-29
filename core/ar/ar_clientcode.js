// Declare core_ar Namespace
window.core_ar = {};
(function (core_ar) {

    function cust_name_enable(dataItem) {
        return coreWebApp.ModelBo.annex_info.is_overridden();
    }
    core_ar.cust_name_enable = cust_name_enable;

    function gst_reg_name_enable(dataItem) {
        return coreWebApp.ModelBo.annex_info.tax_info.diff_gst_name();
    }
    core_ar.gst_reg_name_enable = gst_reg_name_enable;

    function customer_desc_changed(dataItem) {
        if (coreWebApp.ModelBo.annex_info.is_overridden() == false) {
            coreWebApp.ModelBo.customer_name(coreWebApp.ModelBo.customer());
        } else {
            if (coreWebApp.ModelBo.customer_name() == '') {
                coreWebApp.ModelBo.customer_name(coreWebApp.ModelBo.customer());
            }
        }
    }
    core_ar.customer_desc_changed = customer_desc_changed;

    function parent_sm_combo_filter(fltr) {
        fltr = ' salesman_type = 2 ';
        return fltr;
    }
    core_ar.parent_sm_combo_filter = parent_sm_combo_filter;

    function cust_tax(customer_id) {
        $.ajax({
            url: '?r=core/ar/form/fetchcusttax',
            type: 'GET',
            data: {'customer_id': customer_id},
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    coreWebApp.ModelBo.salesman_id(parseInt(jsonResult['salesman_id']));
                }
                coreWebApp.applySmartCombo($('#salesman_id'));
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
        return 'OK';
    }
    core_ar.cust_tax = cust_tax;

    function cust_salesman(customer_id) {
        $.ajax({
            url: '?r=core/ar/form/fetchcustsalesman',
            type: 'GET',
            dataType: 'json',
            data: {'customer_id': customer_id},
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (result) {
                if (result.status === 'ok') {
                    coreWebApp.lookupCache.add('salesman_id', result.salesman_id, result.salesman_name);
                    coreWebApp.ModelBo.salesman_id(result.salesman_id);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
        return 'OK';
    }
    core_ar.cust_salesman = cust_salesman;

    function cust_addr_ui(opts) {
        opts.module = 'core/ar';
        opts.alloc_view = '/customer/SelectAddress';
        opts.call_init = cust_addr_init;
        opts.call_update = cust_addr_update;
        coreWebApp.showAllocV2(opts);
    }
    core_ar.cust_addr_ui = cust_addr_ui;

    function cust_addr_init(opts, after_init) {
        $.ajax({
            url: '?r=core/ar/form/fetchcustaddrcollect',
            type: 'GET',
            data: {'customer_id': opts.customer_id},
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    var addr_info = new function () {
                        self = this;
                    };
                    addr_info.cust_addr_temp = build_cust_addr_temp();
                    for (var p = 0; p < jsonResult['dt_address'].length; p++)
                    {
                        var bal_row = jsonResult['dt_address'][p];
                        var nr = addr_info.cust_addr_temp.addNewRow();
                        nr.customer_id(bal_row['customer_id']);
                        nr.is_select(false);
                        nr.address_type(bal_row['address_type']);
                        nr.address_type_id(bal_row['address_type_id']);
                        nr.cust_address(bal_row['cust_address']);
                        addr_info.cust_addr_temp.push(nr);
                    }
                    ;

                    opts.model = addr_info;
                    after_init();
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
    }
    core_ar.cust_addr_init = cust_addr_init;

    function build_cust_addr_temp() {
        var cust_addr_temp = ko.observableArray();
        cust_addr_temp.addNewRow = function () {
            var cobj = new Object();
            cobj.address_type_id = ko.observable(0);
            cobj.customer_id = ko.observable(0);

            cobj.cust_address = ko.observable('');
            cobj.address_type = ko.observable('');

            cobj.is_select = ko.observable(false);
            return cobj;
        };
        return cust_addr_temp;
    }
    core_ar.build_cust_addr_temp = build_cust_addr_temp;

    function cust_addr_update(opts) {
        var checkCount = 0;
        for (var d = 0; d < opts.model.cust_addr_temp().length; d++) {
            if (opts.model.cust_addr_temp()[d].is_select()) {
                checkCount++;
            }
        }
        if (checkCount === 0) {
            return 'Select any one address.';
        } else if (checkCount > 1) {
            return 'Only one address is allowed.';
        } else {
            for (var p = 0; p < opts.model.cust_addr_temp().length; ++p)
            {
                var cst = opts.model.cust_addr_temp()[p];
                if (cst['is_select']() == true) {
                    opts.cust_billing_addr(cst['cust_address']());
                }
            }
        }
        delete opts.model; // remove the temporary model created
        return true;
    }
    core_ar.cust_addr_update = cust_addr_update;

    function get_cust_billing_addr(opts) {
        $.ajax({
            url: '?r=core/ar/form/fetchcustaddrcollect',
            type: 'GET',
            data: {'customer_id': opts.customer_id},
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    for (var p = 0; p < jsonResult['dt_address'].length; p++)
                    {
                        var bal_row = jsonResult['dt_address'][p];
                        if (bal_row['address_type_id'] == opts.addr_type_id) {
                            opts.cust_billing_addr(bal_row['cust_address']);
                        }
                    }
                    ;
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
        return 'OK';
    }
    core_ar.get_cust_billing_addr = get_cust_billing_addr;

    function cust_credit_limit(customer_id, voucher_id) {
        $.ajax({
            url: '?r=core/ar/form/fetchcustcreditlimit',
            type: 'GET',
            data: {'customer_id': customer_id, 'voucher_id': voucher_id},
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    coreWebApp.ModelBo.cust_billed_amt(jsonResult['billed_amt']);
                    coreWebApp.ModelBo.cust_not_billed_amt(jsonResult['not_billed_amt']);
                    coreWebApp.ModelBo.credit_limit(jsonResult['credit_limit']);
                    coreWebApp.ModelBo.balance_credit(jsonResult['balance_credit']);
                    if (jsonResult['credit_limit_type'] == 1) {
                        $('#infinite_limit').parent().show();
                    } else {
                        $('#infinite_limit').parent().hide();
                    }
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
        return 'OK';
    }
    core_ar.cust_credit_limit = cust_credit_limit;

    function income_type_enable(dataItem) {
        console.log('income_type_enable')
        if (coreWebApp.ModelBo.is_system_created() == false) {
            return true;
        } else {
            return false;
        }
    }
    core_ar.income_type_enable = income_type_enable;



    function copy_billing(dataItem) {
        coreWebApp.ModelBo.customer_shipping_address_tran()[0]['address'](coreWebApp.ModelBo.customer_address_tran()[0]['address']());
        coreWebApp.ModelBo.customer_shipping_address_tran()[0]['city'](coreWebApp.ModelBo.customer_address_tran()[0]['city']());
        coreWebApp.ModelBo.customer_shipping_address_tran()[0]['pin'](coreWebApp.ModelBo.customer_address_tran()[0]['pin']());
        coreWebApp.ModelBo.customer_shipping_address_tran()[0]['state'](coreWebApp.ModelBo.customer_address_tran()[0]['state']());
        coreWebApp.ModelBo.customer_shipping_address_tran()[0]['country'](coreWebApp.ModelBo.customer_address_tran()[0]['country']());
    }
    core_ar.copy_billing = copy_billing;


    function rcpt_account_combo_filter(fltr) {
        if (coreWebApp.ModelBo.rcpt_type() == 0) {
            fltr = ' account_type_id in(1, 2)';
        }
        if (coreWebApp.ModelBo.rcpt_type() == 1) {
            fltr = ' account_type_id not in (0, 1, 2, 7, 12, 45, 46, 47)';
        }
        if (coreWebApp.ModelBo.rcpt_type() == 2) {
            fltr = ' account_type_id = 12';
        }
        if (coreWebApp.ModelBo.rcpt_type() == 3) {
            fltr = ' account_type_id = 7';
        }
        return fltr;
    }
    core_ar.rcpt_account_combo_filter = rcpt_account_combo_filter;

    function acr_target_branch_enable(dataItem) {
        if (dataItem.is_inter_branch()) {
            return true;
        } else {
            return false;
        }
    }
    core_ar.acr_target_branch_enable = acr_target_branch_enable;


    function ar_enable_visible_fc(dataItem) {
        if (parseFloat(coreWebApp.ModelBo.fc_type_id()) != 0) {
            return true;
        } else {
            return false;
        }
    }
    core_ar.ar_enable_visible_fc = ar_enable_visible_fc;

    function ar_enable_visible_local(dataItem) {
        if (parseFloat(coreWebApp.ModelBo.fc_type_id()) == 0) {
            return true;
        } else {
            return false;
        }
    }
    core_ar.ar_enable_visible_local = ar_enable_visible_local;

    function adv_rcpt_fc_changed(dataItem) {
        var exch_rate = parseFloat(dataItem.exch_rate());
        var fc_type_id = parseFloat(dataItem.fc_type_id());

        if (fc_type_id == 0) {
            dataItem.debit_amt_fc(0);
        } else {
            dataItem.debit_amt((parseFloat(dataItem.debit_amt_fc()) * exch_rate).toFixed(2));
        }
    }
    core_ar.adv_rcpt_fc_changed = adv_rcpt_fc_changed;

    function inv_alloc_fc_tran_changed(dataItem) {
        console.log('inv_alloc_fc_tran_changed');
        if (coreWebApp.ModelBo.fc_type_id() != 0) {
            dataItem.alloc_amt((parseFloat(dataItem.alloc_amt_fc()) * parseFloat(coreWebApp.ModelBo.exch_rate())).toFixed(2));
        } else {
            dataItem.alloc_amt_fc(0);
        }

    }
    core_ar.inv_alloc_fc_tran_changed = inv_alloc_fc_tran_changed;

    function clearAdvalloc() {
        coreWebApp.ModelBo.receivable_ledger_alloc_tran.removeAll();
    }
    core_ar.clearAdvalloc = clearAdvalloc;


    function adv_alloc_ui(opts) {
        opts.module = 'core/ar';
        if (parseFloat(opts.fc_type_id) !== 0) {
            opts.alloc_view = 'advanceAlloc/AdvAllocFC';
        } else {
            opts.alloc_view = 'advanceAlloc/AdvAlloc';
        }
        opts.call_init = adv_alloc_init;
        opts.call_update = adv_alloc_update;
        coreWebApp.showAllocV2(opts);
    }
    core_ar.adv_alloc_ui = adv_alloc_ui;


    function adv_alloc_init(opts, after_init) {
        $.ajax({
            url: '?r=core/ar/form/advancealloc',
            type: 'GET',
            data: {
                voucher_id: opts.voucher_id, doc_date: opts.doc_date,
                account_id: opts.account_id, fc_type_id: opts.fc_type_id,
                exch_rate: opts.exch_rate, dc: opts.dc, branch_id: opts.branch_id
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    var ar_alloc = new function () {
                        self = this;
                        this.debit_amt_total = ko.observable(opts.debit_amt_total);
                        this.debit_amt_total_fc = ko.observable(opts.debit_amt_total_fc);
                        this.total_amt = ko.pureComputed(function () {
                            var total = new Number();
                            ko.utils.arrayForEach(self.rl_temp(), function (item) {
                                total += parseFloat(item.alloc_amt());
                            });
                            return total.toFixed(2);
                        });
                        this.total_amt_fc = ko.pureComputed(function () {
                            var total_fc = new Number();
                            ko.utils.arrayForEach(self.rl_temp(), function (item) {
                                total_fc += parseFloat(item.alloc_amt_fc());
                            });
                            return total_fc.toFixed(2);
                        });
                        this.balance_total = ko.pureComputed(function () {
                            var balance_total = parseFloat(self.debit_amt_total()) - parseFloat(self.total_amt());
                            return balance_total.toFixed(2);
                        });
                        this.balance_total_fc = ko.pureComputed(function () {
                            var balance_total_fc = parseFloat(self.debit_amt_total_fc()) - parseFloat(self.total_amt_fc());
                            return balance_total_fc.toFixed(2);
                        });
                    };
                    ar_alloc.rl_temp = build_rl_temp();
                    for (var p = 0; p < jsonResult['rl_balance'].length; p++) {
                        var bal_row = jsonResult['rl_balance'][p];
                        var nr = ar_alloc.rl_temp.addNewRow();
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
                        for (var a = 0; a < opts.rl_tran().length; ++a) {
                            var rlt = opts.rl_tran()[a];
                            if (rlt.rl_pl_id() === bal_row['rl_pl_id']) {
                                if (opts.dc == 'D') {
                                    nr.alloc_amt(rlt.credit_amt());
                                    nr.alloc_amt_fc(rlt.credit_amt_fc());
                                } else {
                                    nr.alloc_amt(rlt.debit_amt());
                                    nr.alloc_amt_fc(rlt.debit_amt_fc());
                                }
                            }
                        }
                        ar_alloc.rl_temp.push(nr);
                    }
                    opts.model = ar_alloc;
                    $('#adv-alloc-loading').hide();
                    after_init(); //callback handler as the ajax call is in diff thread
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
    }
    core_ar.adv_alloc_init = adv_alloc_init;

    function build_rl_temp() {
        var rl_temp = ko.observableArray();
        rl_temp.addNewRow = function () {
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
        return rl_temp;
    }
    core_ar.build_rl_temp = build_rl_temp;

    function adv_alloc_update(opts) {
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
        ko.utils.arrayForEach(opts.model.rl_temp(), function (r) {
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
        opts.rl_tran.removeAll();
        for (var p = 0; p < opts.model.rl_temp().length; ++p) {
            var rlt = opts.model.rl_temp()[p];
            if (parseFloat(rlt.alloc_amt()) > 0 || parseFloat(rlt.alloc_amt_fc()) > 0) {
                var nr = coreWebApp.ModelBo.addNewRow('receivable_ledger_alloc_tran', coreWebApp.ModelBo);
                nr.rl_pl_id(rlt['rl_pl_id']());
                nr.adv_ref_id(rlt.voucher_id());
                nr.voucher_id(rlt.voucher_id());
                nr.vch_tran_id(rlt.vch_tran_id());
                nr.branch_id(rlt.branch_id());
                nr.account_id(rlt.account_id());
                nr.exch_rate(rlt.exch_rate());
                if (opts.dc == 'D') {
                    nr.credit_amt(rlt.alloc_amt());
                    nr.credit_amt_fc(rlt.alloc_amt_fc());
                } else {
                    nr.debit_amt(rlt.alloc_amt());
                    nr.debit_amt_fc(rlt.alloc_amt_fc());
                }
                nr.doc_date(rlt.doc_date());
                nr.adv_ref_date(rlt.doc_date());
                if (opts.dc == 'D') {
                    nr.net_credit_amt(nr.credit_amt() + nr.debit_exch_diff());
                    nr.net_credit_amt_fc(nr.credit_amt_fc() + nr.write_off_amt_fc());
                } else {
                    nr.net_debit_amt(nr.debit_amt() + nr.write_off_amt() + nr.debit_exch_diff());
                    nr.net_debit_amt_fc(nr.debit_amt_fc() + nr.write_off_amt_fc());
                }
            }
        }
        opts.rl_tran.valueHasMutated();
        delete opts.model; // remove the temporary model created
        return true;
    }
    core_ar.adv_alloc_update = adv_alloc_update;

    function CustOPBL_fc_tran_changed(dataItem) {
        if (dataItem.fc_type_id() == 0) {
            dataItem.exch_rate(1);
            dataItem.debit_amt_fc(0);
            dataItem.credit_amt_fc(0);
        } else {
            dataItem.debit_amt((parseFloat(dataItem.debit_amt_fc()) * dataItem.exch_rate()).toFixed(2));
            dataItem.credit_amt((parseFloat(dataItem.credit_amt_fc()) * dataItem.exch_rate()).toFixed(2));
        }
    }
    core_ar.CustOPBL_fc_tran_changed = CustOPBL_fc_tran_changed;

    function CustOPBL_enable_visible_fc(dataItem) {
        if (typeof dataItem.fc_type_id == 'undefined')
            return;
        if (parseFloat(dataItem.fc_type_id()) != 0) {
            return true;
        } else {
            return false;
        }
    }
    core_ar.CustOPBL_enable_visible_fc = CustOPBL_enable_visible_fc;


    function CustOPBL_enable_visible_local(dataItem) {
        if (typeof dataItem.fc_type_id == 'undefined')
            return;
        if (parseFloat(dataItem.fc_type_id()) == 0) {
            return true;
        } else {
            return false;
        }
    }
    core_ar.CustOPBL_enable_visible_local = CustOPBL_enable_visible_local;

    function adv_rcpt_total_calc() {
        coreWebApp.ModelBo.total_amt((parseFloat(coreWebApp.ModelBo.tds_amt()) + parseFloat(coreWebApp.ModelBo.debit_amt())).toFixed(2));
    }
    core_ar.adv_rcpt_total_calc = adv_rcpt_total_calc;

    // opts structure {
    //      cust_id: Customer id
    // } 
    // modifies opts to return
    // result object {
    //      addr: Contains the selected address
    //      gst_state: Contains gst_state name with code
    //      gst_state_id: Contains the gst-state
    //      gstin: Contains the GSTIN
    //  }
    function select_address(opts) {
        opts.module = 'core/ar';
        opts.alloc_view = 'addrSelect/AddrSelect';
        opts.call_init = addr_select_init;
        opts.call_update = addr_select_update;
        coreWebApp.showAllocV2(opts);
    }
    core_ar.select_address = select_address;

    function addr_select_init(opts, after_init) {
        $.ajax({
            url: '?r=core/ar/form/list-cust-addr',
            type: 'GET',
            dataType: 'json',
            data: {
                customer_id: opts.cust_id
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
                coreWebApp.toastmsg('error', 'Customer Address', 'Failed with errors on server', false);
            }
        });
    }
    core_ar.addr_select_init = addr_select_init;

    function addr_select_update(opts) {
        opts.model.addr_temp().forEach(function (x) {
            if (x.select()) {
                opts.result = new function () {};
                opts.result.addr = x.addr();
                opts.result.gst_state_id = x.gst_state_id();
                opts.result.gst_state = x.gst_state();
                opts.result.gstin = x.gstin();
                opts.result.city = x.city();
                opts.result.pin = x.pin();
            }
        });
        return true;
    }
    core_ar.addr_select_update = addr_select_update;

    // opts structure {
    //      supp_id: Supplier id
    // } 
    // modifies opts to return
    // result object {
    //      addr: Contains the selected address
    //      gst_state: Contains the gst_state with code
    //      gst_state_id: Contains the gst-state
    //      gstin: Contains the GSTIN
    //  }
    function get_address(opts) {
        $.ajax({
            url: '?r=core/ar/form/fetch-cust-addr',
            type: 'GET',
            dataType: 'json',
            data: {
                customer_id: opts.cust_id
            },
            success: function (result) {
                if (typeof result.gst_state_id != 'undefined') {
                    opts.result = new function () {};
                    opts.result.addr = result.addr;
                    opts.result.gst_state_id = result.gst_state_id;
                    opts.result.gst_state = result.gst_state;
                    opts.result.gstin = result.gstin;
                    opts.result.city = result.city;
                    opts.result.pin = result.pin;
                }
                if (typeof opts.after_update != 'undefined') {
                    opts.after_update(opts);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Customer Address', 'Failed with errors on server', false);
            }
        });
    }
    core_ar.get_address = get_address;

    function enable_recodate() {
        return coreWebApp.ModelBo.collected();
    }
    core_ar.enable_recodate = enable_recodate;

}(window.core_ar));

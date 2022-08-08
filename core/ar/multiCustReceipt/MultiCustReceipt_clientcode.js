// Declare prod Namespace
typeof window.mcr == 'undefined' ? window.mcr = {} : '';
(function (mcr) {
    mcr.cust_id = -1;
    mcr.sl_no = 1;

    function after_load() {
        $('#cmd_addnew_mcr_summary_tran').detach();
        mcr.sl_no = coreWebApp.ModelBo.mcr_summary_tran().length;
        if (coreWebApp.ModelBo.voucher_id() == "") {
            total_calc();
            ko.utils.arrayForEach(coreWebApp.ModelBo.rcpt_adv_tran(), function (row) {
                mcr.fetch_cust_info(row);
            });
        }
    }
    mcr.after_load = after_load;

    function visible_balance(dataItem) {
        //applysmartcontrols(); 
        console.log('visible_balance');
        if (coreWebApp.ModelBo.status() == 5) {
            return false;
        } else {
            return true;
        }
    }

    mcr.visible_balance = visible_balance;

    function visible_balance_fc(dataItem) {
        //applysmartcontrols(); 
        if (coreWebApp.ModelBo.status() == 5) {
            return false;
        } else {
            if (parseFloat(coreWebApp.ModelBo.fc_type_id()) != 0) {
                return true;
            } else {
                return false;
            }
        }
    }

    mcr.visible_balance_fc = visible_balance_fc;
    function enable_inter_branch(dataItem) {
        //applysmartcontrols(); 
        if (coreWebApp.ModelBo.voucher_id() == "") {
            return true;
        } else {
            return false;
        }
    }

    mcr.enable_inter_branch = enable_inter_branch;


    function enable_gst_tds(dataItem) {
        return !dataItem.is_opbl();
    }
    mcr.enable_gst_tds = enable_gst_tds;

    function fc_changed(dataItem) {
        console.log('fc_changed');
        var exch_rate = parseFloat(dataItem.exch_rate());
        var fc_type_id = parseFloat(dataItem.fc_type_id());
        if (fc_type_id == 0) {
            dataItem.net_settled_fc(0);
        } else {
            dataItem.net_settled((parseFloat(dataItem.net_settled_fc()) * coreWebApp.ModelBo.exch_rate()).toFixed(2));
        }

        ko.utils.arrayForEach(dataItem.receivable_ledger_alloc_tran(), function (a) {
            if (fc_type_id == 0) {
                a.credit_amt_fc(0);
                a.write_off_amt_fc(0);
                a.tds_amt_fc(0);
                a.gst_tds_amt_fc(0);
                a.other_exp_fc(0);
            } else {
                a.credit_amt((parseFloat(a.credit_amt_fc()) * exch_rate).toFixed(2));
                a.write_off_amt((parseFloat(a.write_off_amt_fc()) * exch_rate).toFixed(2));
                a.tds_amt((parseFloat(a.tds_amt_fc()) * exch_rate).toFixed(2));
                a.gst_tds_amt((parseFloat(a.gst_tds_amt_fc()) * exch_rate).toFixed(2));
                a.other_exp((parseFloat(a.other_exp_fc()) * exch_rate).toFixed(2));
                a.net_credit_amt((parseFloat(a.credit_amt()) + (parseFloat(a.write_off_amt()) + parseFloat(a.tds_amt()) +
                        parseFloat(a.gst_tds_amt()) + parseFloat(a.other_exp())) + parseFloat(a.credit_exch_diff())).toFixed(2));
            }
        });
        total_calc();
    }
    mcr.fc_changed = fc_changed;

    function amount_changed(dataItem) {
        if (coreWebApp.ModelBo.fc_type_id() == 0) {
            dataItem.net_credit_amt((parseFloat(dataItem.credit_amt()) + (parseFloat(dataItem.write_off_amt()) + parseFloat(dataItem.tds_amt()) +
                    parseFloat(dataItem.gst_tds_amt()) + parseFloat(dataItem.other_exp())) + parseFloat(dataItem.credit_exch_diff())).toFixed(2));
            dataItem.net_credit_amt_fc(0);
        } else if (coreWebApp.ModelBo.fc_type_id() != 0) {
            dataItem.credit_amt((parseFloat(dataItem.credit_amt_fc()) * coreWebApp.ModelBo.exch_rate()).toFixed(2));
            dataItem.net_credit_amt_fc((parseFloat(dataItem.credit_amt_fc()) + (parseFloat(dataItem.write_off_amt_fc()) + parseFloat(dataItem.tds_amt_fc()) +
                    parseFloat(dataItem.gst_tds_amt_fc()) + parseFloat(dataItem.other_exp_fc()))).toFixed(2));
            dataItem.net_credit_amt((parseFloat(dataItem.credit_amt()) + (parseFloat(dataItem.write_off_amt()) + parseFloat(dataItem.tds_amt()) +
                    parseFloat(dataItem.gst_tds_amt()) + parseFloat(dataItem.other_exp())) + parseFloat(dataItem.credit_exch_diff())).toFixed(2));
        }
        total_calc();
    }
    mcr.amount_changed = amount_changed;

    function net_settled_changed(dataItem) {
        if (coreWebApp.ModelBo.fc_type_id() == 0) {
            dataItem.net_settled_fc(0);
        } else if (coreWebApp.ModelBo.fc_type_id() != 0) {
            dataItem.net_settled((parseFloat(dataItem.net_settled_fc()) * coreWebApp.ModelBo.exch_rate()).toFixed(2));
        }
    }
    mcr.net_settled_changed = net_settled_changed;

    function disc_changed(dataItem) {
        console.log('disc_changed');
        if (coreWebApp.ModelBo.fc_type_id() == 0) {
            var credit = (parseFloat(dataItem.net_credit_amt()) - (parseFloat(dataItem.write_off_amt()) + parseFloat(dataItem.tds_amt()) +
                    parseFloat(dataItem.gst_tds_amt()) + parseFloat(dataItem.other_exp()))).toFixed(2);
            if (credit < 0) {
                dataItem.credit_amt(0);
            } else {
                dataItem.credit_amt(credit);
            }
            dataItem.credit_amt_fc(0);
        } else if (coreWebApp.ModelBo.fc_type_id() != 0) {
            var credit_fc = (parseFloat(dataItem.net_credit_amt_fc()) - (parseFloat(dataItem.write_off_amt_fc()) + parseFloat(dataItem.tds_amt_fc()) +
                    parseFloat(dataItem.gst_tds_amt_fc()) + parseFloat(dataItem.other_exp_fc()))).toFixed(2);
            dataItem.write_off_amt((parseFloat(dataItem.write_off_amt_fc()) * coreWebApp.ModelBo.exch_rate()).toFixed(2));
            dataItem.tds_amt((parseFloat(dataItem.tds_amt_fc()) * coreWebApp.ModelBo.exch_rate()).toFixed(2));
            dataItem.gst_tds_amt((parseFloat(dataItem.gst_tds_amt_fc()) * coreWebApp.ModelBo.exch_rate()).toFixed(2));
            dataItem.other_exp((parseFloat(dataItem.other_exp_fc()) * coreWebApp.ModelBo.exch_rate()).toFixed(2));
            if (credit_fc < 0) {
                dataItem.credit_amt_fc(0);
            } else {
                dataItem.credit_amt_fc(credit_fc);
            }
            dataItem.credit_amt((parseFloat(dataItem.credit_amt_fc()) * coreWebApp.ModelBo.exch_rate()).toFixed(2));

            if (dataItem.credit_amt_fc() < 0) {
                dataItem.credit_amt_fc(0);
            }

            if (dataItem.credit_amt() < 0) {
                dataItem.credit_amt(0);
            }

        }
        total_calc();
    }
    mcr.disc_changed = disc_changed;

    function total_calc() {
        var item_amt_tot = new Number(0.00);
        var item_amt_tot_fc = new Number(0.00);
        var net_credit_amt_tot = new Number(0.00);
        var net_credit_amt_tot_fc = new Number(0.00);
        var adv_amt_tot = new Number(0.00);
        var adv_amt_tot_fc = new Number(0.00);
        var other_adj_tot = new Number(0.00);
        var other_adj_tot_fc = new Number(0.00);
        var gst_tds_amt = new Number(0.00);
        var gst_tds_amt_fc = new Number(0.00);
        var cust_tot = new Number(0.00);
        var cust_tot_fc = new Number(0.00);

        ko.utils.arrayForEach(coreWebApp.ModelBo.mcr_summary_tran(), function (cust_row) {
            cust_tot = new Number(0.00);
            cust_tot_fc = new Number(0.00);
            ko.utils.arrayForEach(coreWebApp.ModelBo.receivable_ledger_alloc_tran(), function (rl_row) {
                if (cust_row.account_id() == rl_row.account_id()) {
                    cust_tot += Number.parseFloat(rl_row.credit_amt());
                    cust_tot_fc += Number.parseFloat(rl_row.credit_amt_fc());
                }
            });
            cust_row.receivable_amt(cust_tot.toFixed(2));
        });
        // Total each item
        ko.utils.arrayForEach(coreWebApp.ModelBo.receivable_ledger_alloc_tran(), function (row) {
            item_amt_tot += Number.parseFloat(row.credit_amt());
            item_amt_tot_fc += Number.parseFloat(row.credit_amt_fc());
            gst_tds_amt += Number.parseFloat(row.gst_tds_amt());
            gst_tds_amt_fc += Number.parseFloat(row.gst_tds_amt_fc());
            net_credit_amt_tot += Number.parseFloat(row.net_credit_amt());
            net_credit_amt_tot_fc += Number.parseFloat(row.net_credit_amt_fc());
        });

        ko.utils.arrayForEach(coreWebApp.ModelBo.rcpt_tran(), function (row) {
            other_adj_tot += Number.parseFloat(row.credit_amt());
            other_adj_tot_fc += Number.parseFloat(row.credit_amt_fc());
        });

        ko.utils.arrayForEach(coreWebApp.ModelBo.rcpt_adv_tran(), function (row) {
            adv_amt_tot += Number.parseFloat(row.adv_amt());
            adv_amt_tot_fc += Number.parseFloat(row.adv_amt_fc());
        });

        net_credit_amt_tot += parseFloat(adv_amt_tot.toFixed(2));
        net_credit_amt_tot_fc += parseFloat(adv_amt_tot_fc.toFixed(2));

        net_credit_amt_tot += other_adj_tot;
        net_credit_amt_tot_fc += other_adj_tot_fc;

        coreWebApp.ModelBo.credit_amt_total(item_amt_tot.toFixed(2));
        coreWebApp.ModelBo.credit_amt_total_fc(item_amt_tot_fc.toFixed(2));
        coreWebApp.ModelBo.annex_info.other_adj(other_adj_tot.toFixed(2));
        coreWebApp.ModelBo.annex_info.other_adj_fc(other_adj_tot_fc.toFixed(2));

        coreWebApp.ModelBo.adv_amt(adv_amt_tot.toFixed(2));
        coreWebApp.ModelBo.adv_amt_fc(adv_amt_tot_fc.toFixed(2));
        coreWebApp.ModelBo.annex_info.gst_tds_amt(gst_tds_amt.toFixed(2));
        coreWebApp.ModelBo.annex_info.gst_tds_amt_fc(gst_tds_amt_fc.toFixed(2));
        coreWebApp.ModelBo.debit_amt(net_credit_amt_tot.toFixed(2));
        coreWebApp.ModelBo.debit_amt_fc(net_credit_amt_tot_fc.toFixed(2));
    }
    mcr.total_calc = total_calc;

    function select_inv(row) {
        var opts = {
            voucher_id: coreWebApp.ModelBo.voucher_id(),
            doc_date: coreWebApp.ModelBo.doc_date(),
            branch_id: coreWebApp.ModelBo.is_inter_branch() ? 0 : coreWebApp.ModelBo.branch_id(),
            fc_type_id: coreWebApp.ModelBo.fc_type_id(),
            customer_account_id: row.account_id(),
            rl_tran: coreWebApp.ModelBo.receivable_ledger_alloc_tran,
            after_update: select_inv_after_update
        };
        opts.module = 'core/ar';
        opts.alloc_view = 'multiCustReceipt/SelectInvoice';
        opts.call_init = select_inv_init;
        opts.call_update = select_inv_alloc_update;
        coreWebApp.showAllocV2(opts);
    }
    mcr.select_inv = select_inv;

    function pr_sel_click(row) {
        console.log(row.is_select);
    }
    mcr.pr_sel_click = pr_sel_click;

    function select_inv_after_update() {
        total_calc();
    }

    // function to set default values for Tax Detail
    function select_inv_init(opts, after_init) {
        var sel_inv = new function () {
            self = this;
        };
        sel_inv.inv_temp = {};
        sel_inv.voucher_id = opts.voucher_id;
        sel_inv.doc_date = opts.doc_date;
        sel_inv.customer_account_id = opts.customer_account_id;
        sel_inv.branch_id = coreWebApp.ModelBo.is_inter_branch() ? 0 : coreWebApp.ModelBo.branch_id();
        sel_inv.fc_type_id = opts.fc_type_id;
        sel_inv.rla_tran = opts.receivable_ledger_alloc_tran;
        sel_inv.item_tot = ko.observable(0);
        sel_inv.select_all = ko.observable(false);
        opts.model = sel_inv;
        mcr.get_detail();
        $('#sele_inv-loading').hide();
    }
    mcr.select_inv_init = select_inv_init;

    function get_detail() {
        var cust_acc_id = self.customer_account_id;
        $('#sele_inv-loading').show();
        $.ajax({
            url: '?r=core/ar/form/select-inv-in-rcpt',
            type: 'GET',
            dataType: 'json',
            data: {
                voucher_id: self.voucher_id,
                doc_date: self.doc_date,
                account_id: cust_acc_id,
                branch_id: self.branch_id,
                fc_type_id: self.fc_type_id
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (jsonResult) {
                if (jsonResult.status === 'ok') {
                    self.inv_temp = jsonResult.inv_balance;

                    self.inv_temp.forEach(itm => {
                        itm.is_select = ko.observable(false);
                        itm.is_select.subscribe(inv_sel_click, itm);
                        itm.alloc_amt = ko.observable(0);
                        itm.alloc_amt.subscribe(function () {
                            do_inv_calc(self);
                        });
                    });
                    self.inv_temp.forEach(itm => {
                        coreWebApp.ModelBo.receivable_ledger_alloc_tran().forEach(rl_tran => {
                            if (rl_tran.rl_pl_id() == itm.rl_pl_id) {
                                itm.is_select(true);
                                itm.alloc_amt(rl_tran.credit_amt());
                            }
                        });
                    });

                    $('#sele_inv-loading').hide();
                    // Using a datatable to render data
                    if (coreWebApp.ModelBo.fc_type_id() != 0) {
                        $('#inv_temp-cont').width(($('#inv_temp-cont').width() + 200).toString() + "px");
                    }
                    if ($.fn.dataTable.isDataTable('#inv_temp')) {
                        var t = $('#inv_temp').DataTable();
                        t.destroy();
                    }
                    var tbl = $('#inv_temp').DataTable({
                        data: self.inv_temp,
                        order: [],
                        columns: [
                            {data: "is_select", title: "...",
                                createdCell: function (td, cellData, rowData, row, col) {
                                    $(td).html('<input type="checkbox" data-bind="checked: is_select">');
                                    ko.applyBindings(rowData, $(td)[0]);
                                    $(td).css('text-align', 'center');
                                }
                            },
                            {data: "branch_name", title: "Branch"},
                            {data: "voucher_id", title: "Document #"},
                            {data: "doc_date", title: "Doc Dt.",
                                render: function (cellData) {
                                    return coreWebApp.formatDate(cellData);
                                }
                            },
                            {data: "over_due", title: "Overdue", className: "dt-right",
                                render: function (cellData) {
                                    return coreWebApp.formatNumber(cellData, 2);
                                }
                            },
                            {data: "not_due", title: "Not Due", className: "dt-right",
                                render: function (cellData) {
                                    return coreWebApp.formatNumber(cellData, 2);
                                }
                            },
                            {data: "due_date", title: "Due Date", className: "dt-center",
                                render: function (cellData) {
                                    return coreWebApp.formatDate(cellData);
                                }
                            },
                            {data: "alloc_amt", title: "Received Amount", width: "15%",
                                createdCell: function (td, cellData, rowData, row, col) {
                                    $(td).html('<input type="textbox" data-bind="numericValue: alloc_amt, enable: is_select" class="textbox form-control">');
                                    ko.applyBindings(rowData, $(td)[0]);
                                }
                            },
                            {data: "fc_type", title: "FC Type", visible: coreWebApp.ModelBo.fc_type_id() != 0},
                            {data: "over_due_fc", title: "Overdue FC", visible: coreWebApp.ModelBo.fc_type_id() != 0,
                                render: function (cellData) {
                                    return coreWebApp.formatNumber(cellData, 2);
                                }
                            },
                            {data: "not_due_fc", title: "Not Due FC", visible: coreWebApp.ModelBo.fc_type_id() != 0,
                                render: function (cellData) {
                                    return coreWebApp.formatNumber(cellData, 2);
                                }
                            }
                        ],
                        deferRender: true,
                        scrollY: '200px',
                        scrollCollapse: true,
                        scroller: true,
                    });
                    var l = $('#inv_temp_length');
                    if (l !== 'undefined') {
                        l.hide();
                    }
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
    }
    mcr.get_detail = get_detail;

    function do_inv_calc(self) {
        var tot = 0;
        self.inv_temp.forEach(itm => {
            if (itm.is_select()) {
                tot += parseFloat(itm.alloc_amt());
            }
        });
        self.item_tot(tot);
    }

    function inv_sel_click() {
        if (this.is_select()) {
            this.alloc_amt(parseFloat(this.over_due) + parseFloat(this.not_due));
        } else {
            this.alloc_amt(0.00);
        }
    }

    function inv_check_all(model) {
        if (model.select_all()) {
            for (var p = 0; p < model.inv_temp.length; ++p) {
                model.inv_temp[p].is_select(true);
            }
        } else {
            for (var p = 0; p < model.inv_temp.length; ++p) {
                model.inv_temp[p].is_select(false);
            }
        }
    }
    mcr.inv_check_all = inv_check_all;

    //function to update tax detail pop up fields to tax_detail_tran
    function select_inv_alloc_update(opts) {
        // Validate line items for excess allocation
        var is_valid = true;
        opts.model.inv_temp.forEach(poc => {
            if (poc.is_select()) {
                if (parseFloat(poc.alloc_amt()) > (parseFloat(poc.over_due) + parseFloat(poc.not_due))) {
                    coreWebApp.toastmsg('warning', 'Select Invoices', 'Received amount cannot be greater than Balance for [' + poc.voucher_id + ']');
                    is_valid = false;
                    return;
                }
            }
        });

        var cust_bal = new Number(0.0);
        opts.model.inv_temp.forEach(poc => {
            cust_bal = cust_bal + (parseFloat(poc.over_due) + parseFloat(poc.not_due));
        });
        // Return without updating when validations fail
        if (!is_valid) {
            return false;
        }

        // Update customer balance based on interbranch status
        coreWebApp.ModelBo.mcr_summary_tran().forEach(mcr_row => {
            if (mcr_row.account_id() == opts.customer_account_id) {
                mcr_row.balance(cust_bal);
            }
        });

        coreWebApp.ModelBo.receivable_ledger_alloc_tran.remove(function (rt) {
            return opts.customer_account_id == rt.account_id();
        });
        opts.model.inv_temp.forEach(rlt => {
            if (rlt.is_select() && parseFloat(rlt['alloc_amt']()) > 0) {
                var nr = coreWebApp.ModelBo.addNewRow('receivable_ledger_alloc_tran', coreWebApp.ModelBo, true);
                nr.branch_id(rlt['branch_id']);
                nr.invoice_id(rlt['voucher_id']);
                nr.doc_date(opts.doc_date);
                nr.invoice_date(rlt['doc_date']);
                nr.account_id(rlt['account_id']);
                coreWebApp.trigger_change('account_id', rlt['account_id'], rlt['account_head']);
                nr.balance(parseFloat(rlt['over_due']) + parseFloat(rlt['not_due']));
                nr.balance_fc(parseFloat(rlt['over_due_fc']) + parseFloat(rlt['not_due_fc']));
                nr.credit_amt(parseFloat(rlt['alloc_amt']()));
                nr.credit_amt_fc(0);
                nr.net_credit_amt(parseFloat(rlt['alloc_amt']()));
                nr.net_credit_amt_fc(0);
                nr.write_off_amt(0);
                nr.write_off_amt_fc(0);
                nr.rl_pl_id(rlt['rl_pl_id']);
                nr.is_opbl(rlt['is_opbl']);
                coreWebApp.afterNewRowAdded(false);
            }
        });
        delete opts.model; // remove the temporary model created
        return true;
    }
    mcr.select_inv_alloc_update = select_inv_alloc_update;

    function rl_tran_delete() {
        total_calc();
    }
    mcr.rl_tran_delete = rl_tran_delete;

    function fetch_cust_info(row) {
        opts = {
            cust_id: row.account_id(),
            tran_row: row,
            after_update: fetch_cust_info_after_update
        };
        core_ar.get_address(opts);
    }
    mcr.fetch_cust_info = fetch_cust_info;

    function fetch_cust_info_after_update(opts) {
        if (typeof opts.result != 'undefined') {
            opts.tran_row.customer_state_id(opts.result.gst_state_id);
        } else {
            opts.tran_row.customer_state_id(-1);
        }
        // update vat_type
        if (opts.tran_row.customer_state_id() != -1) {
            var gstOpts = {
                txn_type: core_tx.gst.TXN_SALE,
                origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                target_gst_state_id: opts.tran_row.customer_state_id()
            };
            core_tx.gst.get_vat_type(gstOpts);
            opts.tran_row.vat_type_id(gstOpts.vat_type_id);
            gstOpts.tran = opts.tran_row;
            gstOpts.call_back = total_calc;
            core_tx.gst.reapply_gtt(gstOpts);
        }
    }
    mcr.fetch_cust_info_after_update = fetch_cust_info_after_update;

    function select_hsn(row) {
        opts = {
            txn_type: core_tx.gst.TXN_SALE,
            origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
            target_gst_state_id: row.customer_state_id(),
            row: row,
            after_update: redo_item_calc
        };
        core_tx.hsn.select_hsn(opts);
    }
    mcr.select_hsn = select_hsn;

    function redo_item_calc() {
        // This is a simple method that redoes all tran and total calcs
        coreWebApp.ModelBo.rcpt_adv_tran().forEach(function (x) {
            adv_item_calc(x);
        });
    }
    mcr.redo_item_calc = redo_item_calc;

    function adv_item_calc(row) {
        if (row.customer_state_id() != -1) {
            var bt_amt = Number.parseFloat(row.adv_amt());
            core_tx.gst.item_gtt_calc_inverse({
                bt_amt: bt_amt,
                row: row
            });
        }
        mcr.total_calc();
    }
    mcr.adv_item_calc = adv_item_calc;

    function target_branch_enable(dataItem) {
        if (coreWebApp.ModelBo.is_inter_branch()) {
            return true;
        } else {
            return false;
        }
    }
    mcr.target_branch_enable = target_branch_enable;


    function mcr_summary_tran_before_delete(pr, prop, rw) {
        mcr.cust_id = rw.account_id();
        return true;
    }
    mcr.mcr_summary_tran_before_delete = mcr_summary_tran_before_delete;


    function mcr_summary_tran_after_delete() {
        coreWebApp.ModelBo.receivable_ledger_alloc_tran.remove(function (pt) {
            return mcr.cust_id == pt.account_id();
        });

        mcr.cust_id = -1;
        
        mcr.sl_no = 0;
        coreWebApp.ModelBo.mcr_summary_tran().forEach(function (row) {
            mcr.sl_no += 1;
            row.sl_no(mcr.sl_no);
        });
        
        total_calc();
    }
    mcr.mcr_summary_tran_after_delete = mcr_summary_tran_after_delete;

    function visible_tran() {
        return false;
    }
    mcr.visible_tran = visible_tran;

    function select_cust(row) {
        var opts = {
        };
        opts.module = 'core/ar';
        opts.alloc_view = 'multiCustReceipt/SelectCust';
        opts.call_init = select_cust_init;
        opts.call_update = select_cust_alloc_update;
        coreWebApp.showAllocV2(opts);
    }
    mcr.select_cust = select_cust;

    // function to set default values for Tax Detail
    function select_cust_init(opts, after_init) {
        var sel_cust = new function () {
            self = this;
        };
        sel_cust.cust_id = ko.observable(-1);
        opts.model = sel_cust;
    }
    mcr.select_cust_init = select_cust_init;

    //function to update tax detail pop up fields to tax_detail_tran
    function select_cust_alloc_update(opts) {
        if (opts.model.cust_id() == -1) {
            coreWebApp.toastmsg('warning', 'Select Customer', 'Select Customer');
            is_valid = false;
            return;
        }

        // Validate line items for excess allocation
        var is_valid = true;
        coreWebApp.ModelBo.mcr_summary_tran().forEach(poc => {
            if (poc.account_id() == opts.model.cust_id()) {
                coreWebApp.toastmsg('warning', 'Select Customer', 'Customer already selected in current Multi Customer Receipt');
                is_valid = false;
                return;
            }
        });
        // Return without updating when validations fail
        if (!is_valid) {
            return false;
        }

        // get balance for selected customer
        $.ajax({
            url: '?r=core/ar/form/select-inv-in-rcpt',
            type: 'GET',
            dataType: 'json',
            data: {
                voucher_id: coreWebApp.ModelBo.voucher_id,
                doc_date: coreWebApp.ModelBo.doc_date,
                account_id: opts.model.cust_id(),
                branch_id: coreWebApp.ModelBo.branch_id,
                fc_type_id: coreWebApp.ModelBo.fc_type_id
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (jsonResult) {
                if (jsonResult.status === 'ok') {
                    var cust_balance = new Number(0.00);
                    jsonResult.inv_balance.forEach(itm => {
                        cust_balance = cust_balance + parseFloat(itm.over_due) + parseFloat(itm.not_due);
                    });

                    var nr = coreWebApp.ModelBo.addNewRow('mcr_summary_tran', coreWebApp.ModelBo, true);
                    mcr.sl_no = mcr.sl_no + 1;
                    nr.sl_no(mcr.sl_no);
                    nr.voucher_id('');
                    nr.vch_tran_id('');
                    nr.account_id(opts.model.cust_id());
                    nr.receivable_amt(0);
                    nr.balance(cust_balance);

                    coreWebApp.afterNewRowAdded(false);
                    delete opts.model; // remove the temporary model created 
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
        return true;
    }
    mcr.select_cust_alloc_update = select_cust_alloc_update;

    function ib_branch_filter(fltr) {
        fltr += " gst_state_id = " + coreWebApp.branch_gst_info.gst_state_id;
        return fltr;
    }
    mcr.ib_branch_filter = ib_branch_filter;

}(window.mcr));

// GST Methods and utils that are part of tx
window.mcr.mcr_wiz = {};
(function (mcr_wiz) {

    function select_cust_init(args) {
        $('#tbl-SelectCust').DataTable({
            data: args.model.SelectCust(),
            order: [],
            columns: [
                {data: "is_select", title: "...",
                    createdCell: function (td, cellData, rowData, row, col) {
                        $(td).html('<input type="checkbox" data-bind="checked: is_select">');
                        ko.applyBindings(rowData, $(td)[0]);
                        $(td).css('text-align', 'center');
                    }
                },
                {data: "account_head", title: "Customer"},
                {data: "over_due", title: "Overdue", className: "dt-right",
                    render: function (cellData) {
                        return coreWebApp.formatNumber(cellData(), 2);
                    }
                },
                {data: "not_due", title: "Not Due", className: "dt-right",
                    render: function (cellData) {
                        return coreWebApp.formatNumber(cellData(), 2);
                    }
                },
                {data: "credit_amt", title: "Net Receivable Amt",
                    createdCell: function (td, cellData, rowData, row, col) {
                        $(td).html('<input type="textbox" data-bind="numericValue: credit_amt, enable: is_select" class="textbox form-control">');
                        ko.applyBindings(rowData, $(td)[0]);
                    }
                }
            ],
            deferRender: true,
            scrollY: '400px',
            scrollCollapse: true,
            scroller: true,
        });
    }
    mcr_wiz.select_cust_init = select_cust_init;

}(window.mcr.mcr_wiz));

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

window.core_customereceipt = {};
(function (core_customereceipt) {

    function test_afterload_wiz() {
        console.log('test afterload for wizard');
        if (coreWebApp.ModelBo.status() == 5) {
            $('#seleInv').detach();
            $('#seleBill').detach();
        }
        $('#bo-form').children().find("[id=cmd_addnew_receivable_ledger_alloc_tran]").each(function (e, i) {
            $(this).detach();
        });
        if (coreWebApp.ModelBo.voucher_id() == '') {
            core_customereceipt.fetch_cust_info();
        }
        total_calc();
    }
    core_customereceipt.test_afterload_wiz = test_afterload_wiz;

    function visible_balance(dataItem) {
        //applysmartcontrols(); 
        if (coreWebApp.ModelBo.status() == 5) {
            return false;
        } else {
            return true;
        }
    }
    core_customereceipt.visible_balance = visible_balance;

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
    core_customereceipt.visible_balance_fc = visible_balance_fc;

    function enable_inter_branch(dataItem) {
        //applysmartcontrols(); 
        if (coreWebApp.ModelBo.voucher_id() == "") {
            return true;
//        } else {
            return false;
        }
    }
    core_customereceipt.enable_inter_branch = enable_inter_branch;

    function enable_gst_tds(dataItem) {
        return !dataItem.is_opbl();
    }
    core_customereceipt.enable_gst_tds = enable_gst_tds;

    function visible_gst_tds_fc(dataItem) {
        if (!dataItem.is_opbl() && coreWebApp.ModelBo.fc_type_id() == 0) {
            return true;
        }
        return false;
    }
    core_customereceipt.visible_gst_tds_fc = visible_gst_tds_fc;

    function disable_multi_settle(dataItem) {
        return !coreWebApp.ModelBo.annex_info.is_multi_settl();
    }
    core_customereceipt.disable_multi_settle = disable_multi_settle;

    function multi_settle(dataItem) {
        console.log("multi_settle");
        if (coreWebApp.ModelBo.annex_info.is_multi_settl()) {
            coreWebApp.ModelBo.rcpt_type(1);
            coreWebApp.ModelBo.account_id(-1);
            coreWebApp.trigger_change('account_id', -1, "");
            coreWebApp.ModelBo.net_settled(0);
        } else {
            coreWebApp.ModelBo.rcpt_sel_acc_tran.removeAll();
            core_customereceipt.total_calc();
        }
    }
    core_customereceipt.multi_settle = multi_settle;

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
                a.net_credit_amt((parseFloat(a.credit_amt()) + (parseFloat(a.write_off_amt()) + parseFloat(a.tds_amt()) + parseFloat(a.gst_tds_amt()) +
                        parseFloat(a.other_exp())) + parseFloat(a.credit_exch_diff())).toFixed(2));
            }
        });
        total_calc();
    }
    core_customereceipt.fc_changed = fc_changed;

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
    core_customereceipt.amount_changed = amount_changed;

    function net_settled_changed(dataItem) {
        if (coreWebApp.ModelBo.fc_type_id() == 0) {
            dataItem.net_settled_fc(0);
        } else if (coreWebApp.ModelBo.fc_type_id() != 0) {
            dataItem.net_settled((parseFloat(dataItem.net_settled_fc()) * coreWebApp.ModelBo.exch_rate()).toFixed(2));
        }
    }
    core_customereceipt.net_settled_changed = net_settled_changed;

    function disc_changed(dataItem) {
        console.log('disc_changed');
        if (coreWebApp.ModelBo.fc_type_id() == 0) {
            var net_credit_amt = parseFloat(dataItem.net_credit_amt());
            var write_off_amt = parseFloat(dataItem.write_off_amt());
            var tds_amt = parseFloat(dataItem.tds_amt());
            var gst_tds_amt = parseFloat(dataItem.gst_tds_amt());
            var other_exp = parseFloat(dataItem.other_exp());
            var credit = (net_credit_amt - (write_off_amt + tds_amt + gst_tds_amt + other_exp)).toFixed(2);
            if (credit < 0) {
                dataItem.credit_amt(0);
            } else {
                dataItem.credit_amt(credit);
            }
            dataItem.credit_amt_fc(0);
        } else if (coreWebApp.ModelBo.fc_type_id() != 0) {
            var credit_fc = (parseFloat(dataItem.net_credit_amt_fc()) - (parseFloat(dataItem.write_off_amt_fc()) + parseFloat(dataItem.tds_amt_fc()) + parseFloat(dataItem.gst_tds_amt_fc()) + parseFloat(dataItem.other_exp_fc()))).toFixed(2);
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
    core_customereceipt.disc_changed = disc_changed;

    function total_calc() {
        var item_amt_tot = new Number(0.00);
        var item_amt_tot_fc = new Number(0.00);
        var write_off_amt = new Number(0.00);
        var write_off_amt_fc = new Number(0.00);
        var tds_amt = new Number(0.00);
        var tds_amt_fc = new Number(0.00);
        var other_exp = new Number(0.00);
        var other_exp_fc = new Number(0.00);
        var net_credit_amt_tot = new Number(0.00);
        var net_credit_amt_tot_fc = new Number(0.00);
        var credit_amt_tot = new Number(0.00);
        var credit_amt_tot_fc = new Number(0.00);
        var other_adj_tot = new Number(0.00);
        var other_adj_tot_fc = new Number(0.00);
        var adv_amt_tot = new Number(0.00);
        var adv_amt_tot_fc = new Number(0.00);
        var sel_amt_tot = new Number(0.00);
        var sel_amt_tot_fc = new Number(0.00);
        var gst_tds_amt = new Number(0.00);
        var gst_tds_amt_fc = new Number(0.00);

        // Total each item
        ko.utils.arrayForEach(coreWebApp.ModelBo.receivable_ledger_alloc_tran(), function (row) {
            item_amt_tot += Number.parseFloat(row.credit_amt());
            item_amt_tot_fc += Number.parseFloat(row.credit_amt_fc());
            write_off_amt += Number.parseFloat(row.write_off_amt());
            write_off_amt_fc += Number.parseFloat(row.write_off_amt_fc());
            tds_amt += Number.parseFloat(row.tds_amt());
            tds_amt_fc += Number.parseFloat(row.tds_amt_fc());
            gst_tds_amt += Number.parseFloat(row.gst_tds_amt());
            gst_tds_amt_fc += Number.parseFloat(row.gst_tds_amt_fc());
            other_exp += Number.parseFloat(row.other_exp());
            other_exp_fc += Number.parseFloat(row.other_exp_fc());
            net_credit_amt_tot += Number.parseFloat(row.net_credit_amt());
            net_credit_amt_tot_fc += Number.parseFloat(row.net_credit_amt_fc());
        });

        ko.utils.arrayForEach(coreWebApp.ModelBo.rcpt_adv_tran(), function (row) {
            adv_amt_tot += Number.parseFloat(row.adv_amt());
            adv_amt_tot_fc += Number.parseFloat(row.adv_amt_fc());
        });

        coreWebApp.ModelBo.adv_amt(adv_amt_tot.toFixed(2));
        coreWebApp.ModelBo.adv_amt_fc(adv_amt_tot_fc.toFixed(2));

        ko.utils.arrayForEach(coreWebApp.ModelBo.rcpt_tran(), function (row) {
            other_adj_tot += Number.parseFloat(row.credit_amt());
            other_adj_tot_fc += Number.parseFloat(row.credit_amt_fc());
        });

        net_credit_amt_tot += parseFloat(coreWebApp.ModelBo.adv_amt());
        net_credit_amt_tot_fc += parseFloat(coreWebApp.ModelBo.adv_amt_fc());

        net_credit_amt_tot += other_adj_tot;
        net_credit_amt_tot_fc += other_adj_tot_fc;

        coreWebApp.ModelBo.credit_amt_total(item_amt_tot.toFixed(2));
        coreWebApp.ModelBo.credit_amt_total_fc(item_amt_tot_fc.toFixed(2));
        coreWebApp.ModelBo.write_off_amt_total(write_off_amt.toFixed(2));
        coreWebApp.ModelBo.write_off_amt_total_fc(write_off_amt_fc.toFixed(2));

        coreWebApp.ModelBo.annex_info.other_adj(other_adj_tot.toFixed(2));
        coreWebApp.ModelBo.annex_info.other_adj_fc(other_adj_tot_fc.toFixed(2));

        coreWebApp.ModelBo.tds_amt(tds_amt.toFixed(2));
        coreWebApp.ModelBo.tds_amt_fc(tds_amt_fc.toFixed(2));
        coreWebApp.ModelBo.annex_info.gst_tds_amt(gst_tds_amt.toFixed(2));
        coreWebApp.ModelBo.annex_info.gst_tds_amt_fc(gst_tds_amt_fc.toFixed(2));
        coreWebApp.ModelBo.other_exp_total(other_exp.toFixed(2));
        coreWebApp.ModelBo.other_exp_total_fc(other_exp_fc.toFixed(2));
        coreWebApp.ModelBo.debit_amt(net_credit_amt_tot.toFixed(2));
        coreWebApp.ModelBo.debit_amt_fc(net_credit_amt_tot_fc.toFixed(2));

        ko.utils.arrayForEach(coreWebApp.ModelBo.payable_ledger_alloc_tran(), function (row) {
            credit_amt_tot += Number.parseFloat(row.debit_amt());
            credit_amt_tot_fc += Number.parseFloat(row.debit_amt_fc());
        });

        coreWebApp.ModelBo.credit_amt(credit_amt_tot.toFixed(2));
        coreWebApp.ModelBo.credit_amt_fc(credit_amt_tot_fc.toFixed(2));

        if (coreWebApp.ModelBo.annex_info.is_multi_settl()) {
            ko.utils.arrayForEach(coreWebApp.ModelBo.rcpt_sel_acc_tran(), function (row) {
                sel_amt_tot += Number.parseFloat(row.sel_amt());
                sel_amt_tot_fc += Number.parseFloat(row.sel_amt_fc());
            });
            coreWebApp.ModelBo.net_settled(sel_amt_tot.toFixed(2));
            coreWebApp.ModelBo.net_settled_fc(sel_amt_tot_fc.toFixed(2));

        }

    }
    core_customereceipt.total_calc = total_calc;

    function SelectInvoice() {
        var opts = {
            voucher_id: coreWebApp.ModelBo.voucher_id(),
            doc_date: coreWebApp.ModelBo.doc_date(),
            customer_account_id: coreWebApp.ModelBo.customer_account_id(),
            branch_id: coreWebApp.ModelBo.is_inter_branch() ? 0 : coreWebApp.ModelBo.branch_id(),
            fc_type_id: coreWebApp.ModelBo.fc_type_id(),
            rl_tran: coreWebApp.ModelBo.receivable_ledger_alloc_tran,
            after_update: select_inv_after_update
        };
        opts.module = 'core/ar';
        opts.alloc_view = 'customerReceipt/SelectInvoice';
        opts.call_init = select_inv_init;
        opts.call_update = select_inv_alloc_update;
        coreWebApp.showAllocV2(opts);
    }
    core_customereceipt.SelectInvoice = SelectInvoice;

    function pr_sel_click(row) {
        console.log(row.is_select);
    }
    core_customereceipt.pr_sel_click = pr_sel_click;

    function select_inv_after_update() {
        total_calc();
    }

    // function to set default values for Tax Detail
    function select_inv_init(opts, after_init) {
        $.ajax({
            url: '?r=core/ar/form/select-inv-in-rcpt',
            type: 'GET',
            dataType: 'json',
            data: {
                voucher_id: opts.voucher_id,
                doc_date: opts.doc_date,
                account_id: opts.customer_account_id,
                branch_id: opts.branch_id,
                fc_type_id: opts.fc_type_id
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (jsonResult) {
                if (jsonResult.status === 'ok') {
                    var sel_inv = new function () {
                        self = this;
                    };
                    sel_inv.inv_temp = jsonResult.inv_balance;
                    sel_inv.item_tot = ko.observable(0);
                    sel_inv.inv_temp.forEach(itm => {
                        itm.is_select = ko.observable(false);
                        coreWebApp.ModelBo.receivable_ledger_alloc_tran().forEach(rl_tran => {
                            if (rl_tran.rl_pl_id() == itm.rl_pl_id) {
                                itm.is_select(true);
                            }
                        });
                        itm.is_select.subscribe(function () {
                            do_inv_calc(self);
                        });
                    });
                    opts.model = sel_inv;
                    do_inv_calc(self);
                    $('#sele_inv-loading').hide();
                    after_init();
                    // Using a datatable to render data
                    if (coreWebApp.ModelBo.fc_type_id() != 0) {
                        $('#inv_temp-cont').width(($('#inv_temp-cont').width() + 200).toString() + "px");
                    }
                    var tbl = $('#inv_temp').DataTable({
                        data: sel_inv.inv_temp,
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
    core_customereceipt.select_inv_init = select_inv_init;


    function do_inv_calc(self) {
        var tot = 0;
        self.inv_temp.forEach(itm => {
            if (itm.is_select()) {
                tot += (parseFloat(itm.over_due) + parseFloat(itm.not_due));
            }
        });
        self.item_tot(tot);
    }

    //function to update tax detail pop up fields to tax_detail_tran
    function select_inv_alloc_update(opts) {
        opts.model.inv_temp.forEach(rlt => {
            if (rlt.is_select()) {
                var row_exists = false;
                for (var q = 0; q < coreWebApp.ModelBo.receivable_ledger_alloc_tran().length; q++) {
                    if (rlt['rl_pl_id'] == coreWebApp.ModelBo.receivable_ledger_alloc_tran()[q]['rl_pl_id']()) {
                        row_exists = true;
                        break;
                    }
                }
                if (row_exists == false) {
                    var nr = coreWebApp.ModelBo.addNewRow('receivable_ledger_alloc_tran', coreWebApp.ModelBo, true);
                    nr.branch_id(rlt['branch_id']);
                    nr.invoice_id(rlt['voucher_id']);
                    nr.doc_date(opts.doc_date);
                    nr.invoice_date(rlt['doc_date']);
                    nr.account_id(rlt['account_id']);
                    nr.balance(parseFloat(rlt['over_due']) + parseFloat(rlt['not_due']));
                    nr.balance_fc(parseFloat(rlt['over_due_fc']) + parseFloat(rlt['not_due_fc']));
                    nr.credit_amt(parseFloat(rlt['over_due']) + parseFloat(rlt['not_due']));
                    nr.credit_amt_fc(parseFloat(rlt['over_due_fc']) + parseFloat(rlt['not_due_fc']));
                    nr.net_credit_amt(parseFloat(rlt['over_due']) + parseFloat(rlt['not_due']));
                    nr.net_credit_amt_fc(parseFloat(rlt['over_due_fc']) + parseFloat(rlt['not_due_fc']));
                    nr.write_off_amt(0);
                    nr.write_off_amt_fc(0);
                    nr.rl_pl_id(rlt['rl_pl_id']);
                    nr.is_opbl(rlt['is_opbl']);
                    coreWebApp.afterNewRowAdded(false);
                }
            }
        });
        delete opts.model; // remove the temporary model created
        return true;
    }
    core_customereceipt.select_inv_alloc_update = select_inv_alloc_update;

    function rl_tran_delete() {
        total_calc();
    }
    core_customereceipt.rl_tran_delete = rl_tran_delete;

    function pl_enable_visible(dataItem) {
        if (coreWebApp.ModelBo.rcpt_type() == 2) {
            $('#bo-form').children().find("[id=cmd_addnew_payable_ledger_alloc_tran]").each(function (e, i) {
                $(this).hide();
            });
            return true;
        } else {
            return false;
        }
    }
    core_customereceipt.pl_enable_visible = pl_enable_visible;

    function pl_enable_visible_fc(dataItem) {
        if (parseFloat(coreWebApp.ModelBo.fc_type_id()) != 0 && coreWebApp.ModelBo.rcpt_type() == 2) {
            $('#bo-form').children().find("[id=cmd_addnew_payable_ledger_alloc_tran]").each(function (e, i) {
                $(this).hide();
            });
            return true;
        } else {
            return false;
        }
    }
    core_customereceipt.pl_enable_visible_fc = pl_enable_visible_fc;

    function SelectBill() {
        var opts = {
            voucher_id: coreWebApp.ModelBo.voucher_id(),
            supplier_account_id: coreWebApp.ModelBo.account_id(),
            is_inter_branch: coreWebApp.ModelBo.is_inter_branch(),
            fc_type_id: coreWebApp.ModelBo.fc_type_id(),
            doc_date: coreWebApp.ModelBo.doc_date(),
            pl_tran: coreWebApp.ModelBo.payable_ledger_alloc_tran,
            after_update: select_bill_after_update
        };
        opts.module = 'core/ap';
        opts.alloc_view = '/supplierPayment/SelectBill';
        opts.call_init = core_pymt.select_bill_init;
        opts.call_update = core_pymt.select_bill_update;
        coreWebApp.showAllocV2(opts);
    }
    core_customereceipt.SelectBill = SelectBill;

    function select_bill_after_update() {
        total_calc();
    }


    function enable_sub_head(dataItem) {
        if (parseFloat(coreWebApp.ModelBo.rcpt_type()) == 1) {
            return true;
        } else {
            return false;
        }
    }
    core_customereceipt.enable_sub_head = enable_sub_head;

    function sub_head_alloc_click() {
        if (coreWebApp.ModelBo.account_id() === -1) {
            coreWebApp.toastmsg('warning', 'Details Click Error', 'Select Account to add Details.', false);
            return;
        } else {
            var opts = {
                voucher_id: coreWebApp.ModelBo.voucher_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                account_id: coreWebApp.ModelBo.account_id(),
                branch_id: coreWebApp.ModelBo.branch_id(),
                fc_type_id: coreWebApp.ModelBo.fc_type_id(),
                exch_rate: coreWebApp.ModelBo.exch_rate(),
                debit_amt_total: coreWebApp.ModelBo.net_settled(),
                debit_amt_total_fc: 0,
                sl_tran: coreWebApp.ModelBo.shl_head_tran, // The observable array is sent 
                ref_ledger_tran: coreWebApp.ModelBo.rla_head_tran, // The observable array is sent  
                dc: 'D',
                sl_no: 0,
                ref_no: coreWebApp.ModelBo.ref_no(),
                ref_desc: coreWebApp.ModelBo.ref_desc(),
                row: coreWebApp.ModelBo,
                shl_tran_name: 'shl_head_tran',
                rla_tran_name: 'rla_head_tran',
                after_update: sub_head_alloc_after_update
            };
            core_ac.sub_head_alloc_ui(opts);
        }
    }
    core_customereceipt.sub_head_alloc_click = sub_head_alloc_click;


    function sub_head_alloc_after_update() {
        total_calc();
    }

    function fetch_cust_info(dataItem) {
        opts = {
            cust_id: coreWebApp.ModelBo.customer_account_id(),
            after_update: fetch_cust_info_after_update
        };
        core_ar.get_address(opts);
    }
    core_customereceipt.fetch_cust_info = fetch_cust_info;

    function fetch_cust_info_after_update(opts) {
        if (typeof opts.result != 'undefined') {
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(opts.result.gst_state_id);
        } else {
            coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(-1);
        }
        // update vat_type
        if (coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id() != -1) {
            var gstOpts = {
                txn_type: core_tx.gst.TXN_SALE,
                origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id()
            };
            core_tx.gst.get_vat_type(gstOpts);
            coreWebApp.ModelBo.annex_info.gst_output_info.vat_type_id(gstOpts.vat_type_id);

            // Set HSN info
            if (coreWebApp.ModelBo.adv_gst_hsn_info() != "") {
                coreWebApp.ModelBo.rcpt_adv_tran().forEach(adv_row => {
                    core_tx.gst.item_gtt_reset({
                        txn_type: core_tx.gst.TXN_SALE,
                        origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
                        target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(),
                        gst_hsn_info: $.parseJSON(coreWebApp.ModelBo.adv_gst_hsn_info()),
                        row: adv_row
                    });
                });
            }
        }
    }
    core_customereceipt.fetch_cust_info_after_update = fetch_cust_info_after_update;

    function select_hsn(row) {
        opts = {
            txn_type: core_tx.gst.TXN_SALE,
            origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
            target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(),
            row: row,
            after_update: redo_item_calc
        };
        core_tx.hsn.select_hsn(opts);
    }
    core_customereceipt.select_hsn = select_hsn;

    function redo_item_calc() {
        // This is a simple method that redoes all tran and total calcs
        coreWebApp.ModelBo.rcpt_adv_tran().forEach(function (x) {
            adv_item_calc(x);
        });
    }
    core_customereceipt.redo_item_calc = redo_item_calc;

    function adv_item_calc(row) {
        if (coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id() != -1) {
            var bt_amt = Number.parseFloat(row.adv_amt());
            core_tx.gst.item_gtt_calc_inverse({
                bt_amt: bt_amt,
                row: row
            });
        }
        core_customereceipt.total_calc();
    }
    core_customereceipt.adv_item_calc = adv_item_calc;

    function target_branch_enable(dataItem) {
        if (coreWebApp.ModelBo.is_inter_branch()) {
            return true;
        } else {
            return false;
        }
    }
    core_customereceipt.target_branch_enable = target_branch_enable;

    function adv_tran_add(row) {
        core_tx.gst.item_gtt_reset({
            txn_type: core_tx.gst.TXN_SALE,
            origin_gst_state_id: coreWebApp.branch_gst_info.gst_state_id,
            target_gst_state_id: coreWebApp.ModelBo.annex_info.gst_output_info.customer_state_id(),
            gst_hsn_info: $.parseJSON(coreWebApp.ModelBo.adv_gst_hsn_info()),
            row: row
        });
    }
    core_customereceipt.adv_tran_add = adv_tran_add;

    function sel_acc_allow_add(dataItem) {
        if (coreWebApp.ModelBo.rcpt_type() == 1 && coreWebApp.ModelBo.annex_info.is_multi_settl()) {
            return true;
        }
        coreWebApp.toastmsg('message', 'Settlement Account', 'Settlement accounts allowed only for Settlement Type Journal with Multi Settlement true', false);
        return false;
    }
    core_customereceipt.sel_acc_allow_add = sel_acc_allow_add;

    function sub_head_alloc_tran_click(row) {
        if (row['account_id']() === -1) {
            coreWebApp.toastmsg('warning', 'Details Click Error', 'Select Account to add Details.', false);
            return;
        } else {
            var opts = {

                voucher_id: coreWebApp.ModelBo.voucher_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                account_id: row['account_id'](),
                branch_id: coreWebApp.ModelBo.branch_id(),
                fc_type_id: coreWebApp.ModelBo.fc_type_id(),
                exch_rate: coreWebApp.ModelBo.exch_rate(),
                debit_amt_total: row['sel_amt'](),
                debit_amt_total_fc: row['sel_amt'](),
                sl_tran: row['sub_head_ledger_tran'], // The observable array is sent 
                ref_ledger_tran: row['ref_ledger_alloc_tran'], // The observable array is sent  
                dc: 'D',
                sl_no: row['sl_no'](),
                ref_no: row['ref_no'](),
                ref_desc: row['ref_desc'](),
                row: row,
                shl_tran_name: 'sub_head_ledger_tran',
                rla_tran_name: 'ref_ledger_alloc_tran',
                after_update: sub_head_alloc_after_update
            };
            debugger;
            core_ac.sub_head_alloc_ui(opts);
        }
    }
    core_customereceipt.sub_head_alloc_tran_click = sub_head_alloc_tran_click;



    function pymt_amount_changed(dataItem) {
        if (coreWebApp.ModelBo.fc_type_id() == 0) {
            dataItem.net_debit_amt(parseFloat(parseFloat(dataItem.debit_amt()) + parseFloat(dataItem.write_off_amt())).toFixed(2));
            dataItem.net_debit_amt_fc(0);
        } else if (coreWebApp.ModelBo.fc_type_id() != 0) {
            dataItem.debit_amt((parseFloat(dataItem.debit_amt_fc()) * coreWebApp.ModelBo.exch_rate()).toFixed(2));
            dataItem.net_debit_amt_fc(parseFloat(parseFloat(dataItem.debit_amt_fc()) + parseFloat(dataItem.write_off_amt_fc())).toFixed(2));
            dataItem.net_debit_amt(parseFloat(parseFloat(dataItem.debit_amt()) + parseFloat(dataItem.write_off_amt()) + parseFloat(dataItem.debit_exch_diff())).toFixed(2));
        }
        total_calc();
    }
    core_customereceipt.pymt_amount_changed = pymt_amount_changed;


    function pymt_dis_changed(dataItem) {
        console.log('pymt_dis_changed');
        if (coreWebApp.ModelBo.fc_type_id() == 0) {
            dataItem.debit_amt(parseFloat(parseFloat(dataItem.net_debit_amt()) - parseFloat(dataItem.write_off_amt())).toFixed(2));
            dataItem.debit_amt_fc(0);
        } else if (coreWebApp.ModelBo.fc_type_id() != 0) {
            dataItem.write_off_amt(parseFloat(parseFloat(dataItem.write_off_amt_fc()) * coreWebApp.ModelBo.exch_rate()).toFixed(2));
            dataItem.debit_amt_fc(parseFloat(parseFloat(dataItem.net_debit_amt_fc()) - parseFloat(dataItem.write_off_amt_fc())).toFixed(2));
            dataItem.debit_amt(parseFloat(parseFloat(dataItem.debit_amt_fc()) * coreWebApp.ModelBo.exch_rate()).toFixed(2));

            if (dataItem.debit_amt_fc() < 0) {
                dataItem.debit_amt_fc(0);
            }

            if (dataItem.debit_amt() < 0) {
                dataItem.debit_amt(0);
            }
        }
        total_calc();
    }
    core_customereceipt.pymt_dis_changed = pymt_dis_changed;

}(window.core_customereceipt));


// Select Vch wizard method to render datatable
window.core_customereceipt.rcpt_wiz = {};
(function (rcpt_wiz) {

    function select_vch_init(args) {
        $('#tbl-SelectVch').DataTable({
            data: args.model.SelectVch(),
            order: [],
            columns: [
                {data: "selected", title: "...",
                    createdCell: function (td, cellData, rowData, row, col) {
                        $(td).html('<input type="checkbox" data-bind="checked: selected">');
                        ko.applyBindings(rowData, $(td)[0]);
                        $(td).css('text-align', 'center');
                    }
                },
                {data: "voucher_id", title: "Document #"},
                {data: "doc_date", title: "Doc Dt.",
                    render: function (cellData) {
                        return coreWebApp.formatDate(cellData());
                    }
                },
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
                {data: "due_date", title: "Due Date", className: "dt-center",
                    render: function (cellData) {
                        return coreWebApp.formatDate(cellData());
                    }
                },
                {data: "fc_type", title: "FC Type"},
                {data: "over_due_fc", title: "Overdue FC", className: "dt-right",
                    render: function (cellData) {
                        return coreWebApp.formatNumber(cellData(), 2);
                    }
                },
                {data: "not_due_fc", title: "Not Due FC", className: "dt-right",
                    render: function (cellData) {
                        return coreWebApp.formatNumber(cellData(), 2);
                    }
                }
            ],
            deferRender: true,
            scrollY: '400px',
            scrollCollapse: true,
            scroller: true,
        });
    }
    rcpt_wiz.select_vch_init = select_vch_init;


    function select_cust_init(args) {
        $('#tbl-SelectCustomer').DataTable({
            data: args.model.SelectCustomer(),
            order: [],
            columns: [
                {data: "selected", title: "...",
                    createdCell: function (td, cellData, rowData, row, col) {
                        $(td).html('<input type="checkbox" data-bind="checked: selected">');
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
                        $(td).html('<input type="textbox" data-bind="numericValue: credit_amt, enable: selected" class="textbox form-control">');
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
    rcpt_wiz.select_cust_init = select_cust_init;

}(window.core_customereceipt.rcpt_wiz));

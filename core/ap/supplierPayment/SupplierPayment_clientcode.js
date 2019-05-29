// Declare core_ap Namespace
window.core_pymt = {};
(function (core_pymt) {
    function visible_balance(dataItem) {
        //applysmartcontrols(); 
        if (coreWebApp.ModelBo.status() == 5) {
            return false;
        } else {
            return true;
        }
    }

    core_pymt.visible_balance = visible_balance;

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

    core_pymt.visible_balance_fc = visible_balance_fc;
    
     function enable_inter_branch(dataItem) {
        //applysmartcontrols(); 
        if (coreWebApp.ModelBo.voucher_id() == "") {
            return true;
        } else {
            return false;
        }
    }

    core_pymt.enable_inter_branch = enable_inter_branch;

    function enable_chk_details() {    
        return !coreWebApp.ModelBo.annex_info.is_bt();
    }
    core_pymt.enable_chk_details = enable_chk_details;
    
    function bt_changed(dataItem) {
        if (coreWebApp.ModelBo.annex_info.is_bt()) {
            coreWebApp.ModelBo.supplier_detail('');
            coreWebApp.ModelBo.cheque_number('');
            coreWebApp.ModelBo.cheque_date('1970-01-01');
            coreWebApp.ModelBo.is_pdc(false);
            coreWebApp.ModelBo.is_ac_payee(false);
        }     
        else
        {
          coreWebApp.ModelBo.supplier_detail(coreWebApp.ModelBo.supplier());
          coreWebApp.ModelBo.is_ac_payee(true);
        }
    }
    core_pymt.bt_changed = bt_changed;

    function pymt_afterload_wiz() {
        console.log('test afterload for wizard');  
        $('#cmd_addnew_pl_alloc_tran').detach();
        $('#cmd_addnew_receivable_ledger_alloc_tran').detach();
        $('#cmd_addnew_payable_ledger_alloc_tran').detach();
        if (coreWebApp.ModelBo.status() == 5) {
            $('#seleBill').hide();
        }
        if (coreWebApp.ModelBo.voucher_id() == '') {
            total_calc();
        }
    }
    core_pymt.pymt_afterload_wiz = pymt_afterload_wiz;  

    function pymt_fc_changed(dataItem) {
        var exch_rate = parseFloat(dataItem.exch_rate());
        var fc_type_id = parseFloat(dataItem.fc_type_id());
        ko.utils.arrayForEach(dataItem.pl_alloc_tran(), function (a) {
            if (fc_type_id == 0) {
                a.debit_amt_fc(0);
                a.write_off_amt_fc(0);
                a.net_debit_amt_fc(0);
            } else {
                a.debit_amt((parseFloat(a.credit_amt_fc()) * exch_rate).toFixed(2));
                a.write_off_amt((parseFloat(a.write_off_amt_fc()) * exch_rate).toFixed(2));
                a.net_debit_amt((parseFloat(a.debit_amt()) + parseFloat(a.write_off_amt()) + parseFloat(a.credit_exch_diff())).toFixed(2));
            }
        });
        total_calc();
    }

    core_pymt.pymt_fc_changed = pymt_fc_changed;


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
    core_pymt.pymt_amount_changed = pymt_amount_changed;
    
    function rec_amount_changed(dataItem) {
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
    core_pymt.rec_amount_changed = rec_amount_changed;

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
    core_pymt.pymt_dis_changed = pymt_dis_changed;

    function SelectBill() {
        var opts = {
            voucher_id: coreWebApp.ModelBo.voucher_id(),
            supplier_account_id: coreWebApp.ModelBo.supplier_account_id(),
            is_inter_branch: coreWebApp.ModelBo.is_inter_branch(),
            fc_type_id: coreWebApp.ModelBo.fc_type_id(),
            doc_date: coreWebApp.ModelBo.doc_date(),
            pl_tran: coreWebApp.ModelBo.pl_alloc_tran,
            after_update: select_bill_after_update
        };
        opts.module = 'core/ap';
        opts.alloc_view = '/supplierPayment/SelectBill';
        opts.call_init = select_bill_init;
        opts.call_update = select_bill_update;
        coreWebApp.showAllocV2(opts);
    }
    core_pymt.SelectBill = SelectBill;

    // function to set default values for Tax Detail
    function select_bill_init(opts, after_init) {
        $.ajax({
            url: '?r=core/ap/form/selectbillinpymt',
            type: 'GET',
            dataType: 'json',
            data: {'voucher_id': opts.voucher_id, 'account_id': opts.supplier_account_id,
                'fc_type_id': opts.fc_type_id, 'is_inter_branch': opts.is_inter_branch, 'doc_date': opts.doc_date},
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (jsonResult) {
                if (jsonResult.status === 'ok') {

                    var sel_bill = new function () {
                        self = this;
                    };
                    sel_bill.bill_temp = jsonResult.bill_balance;

                    sel_bill.bill_temp.forEach(itm => {
                        itm.is_select = ko.observable(false);
                        coreWebApp.ModelBo.pl_alloc_tran().forEach(pl_tran => {
                            if (pl_tran.rl_pl_id() == itm.rl_pl_id) {
                                itm.is_select(true);
                            }
                        });
                    });
                    opts.model = sel_bill;
                    $('#sele_bill-loading').hide();
                    after_init();
                    // Using a datatable to render data
                    if (coreWebApp.ModelBo.fc_type_id() != 0) {
                        $('#bill_temp-cont').width(($('#bill_temp-cont').width() + 200).toString() + "px");
                    }
                    var tbl = $('#bill_temp').DataTable({
                        data: sel_bill.bill_temp,
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
                            {data: "bill_no", title: "Bill No"},
                            {data: "bill_date", title: "Bill Dt.", className: "dt-center",
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
                    var l = $('#bill_temp_length');
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
    core_pymt.select_bill_init = select_bill_init;

    //function to update tax detail pop up fields to tax_detail_tran
    function select_bill_update(opts) {
        opts.model.bill_temp.forEach(plt => {
            if (plt.is_select()) {
                var row_exists = false;
                for (var q = 0; q < coreWebApp.ModelBo.pl_alloc_tran().length; q++) {
                    if (plt['rl_pl_id'] == coreWebApp.ModelBo.pl_alloc_tran()[q]['rl_pl_id']()) {
                        row_exists = true;
                        break;
                    }
                }
                if (row_exists == false) {
                    var nr = coreWebApp.ModelBo.addNewRow('pl_alloc_tran', coreWebApp.ModelBo, true);
                    nr.branch_id(plt['branch_id']);
                    nr.bill_id(plt['voucher_id']);
                    nr.vch_doc_date(plt['doc_date']);
                    nr.doc_date(plt['doc_date']);
                    nr.bill_no(plt['bill_no']);
                    nr.bill_date(plt['bill_date']);
                    nr.account_id(plt['account_id']);
                    nr.balance(parseFloat(plt['over_due']) + parseFloat(plt['not_due']));
                    nr.balance_fc(parseFloat(plt['over_due_fc']) + parseFloat(plt['not_due_fc']));
                    nr.debit_amt(parseFloat(plt['over_due']) + parseFloat(plt['not_due']));
                    nr.debit_amt_fc(parseFloat(plt['over_due_fc']) + parseFloat(plt['not_due_fc']));
                    nr.net_debit_amt(parseFloat(plt['over_due']) + parseFloat(plt['not_due']));
                    nr.net_debit_amt_fc(parseFloat(plt['over_due_fc']) + parseFloat(plt['not_due_fc']));
                    nr.write_off_amt(0);
                    nr.write_off_amt_fc(0);
                    nr.rl_pl_id(plt['rl_pl_id']);
                    coreWebApp.afterNewRowAdded(false);
                }
            }
        });
        delete opts.model; // remove the temporary model created
        return true;
    }
    core_pymt.select_bill_update = select_bill_update;

    function select_bill_after_update() {
        total_calc();
    }

    function total_calc() {
        var net_payable_amt_tot = new Number(0.00);
        var net_payable_amt_tot_fc = new Number(0.00);
        var net_receivable_amt_tot = new Number(0.00);
        var net_receivable_amt_tot_fc = new Number(0.00);
        var net_credit_amt_tot = new Number(0.00);
        var net_credit_amt_tot_fc = new Number(0.00);
        var other_adj_tot = new Number(0.00);
        var other_adj_tot_fc = new Number(0.00);
        var adv_tot = new Number(0.00);
        var adv_tot_fc = new Number(0.00);
        
         // Total each item
        ko.utils.arrayForEach(coreWebApp.ModelBo.pl_alloc_tran(), function (row) {
            net_payable_amt_tot += Number.parseFloat(row.net_debit_amt());
            net_payable_amt_tot_fc += Number.parseFloat(row.net_debit_amt_fc());
        });

        ko.utils.arrayForEach(coreWebApp.ModelBo.receivable_ledger_alloc_tran(), function (row) {
            net_receivable_amt_tot += Number.parseFloat(row.net_credit_amt());
            net_receivable_amt_tot_fc += Number.parseFloat(row.net_credit_amt_fc());
        });
        ko.utils.arrayForEach(coreWebApp.ModelBo.payable_ledger_alloc_tran(), function (row) {
            adv_tot += Number.parseFloat(row.credit_amt());
            adv_tot_fc += Number.parseFloat(row.credit_amt_fc());
        });

        ko.utils.arrayForEach(coreWebApp.ModelBo.pymt_tran(), function (row) {
            other_adj_tot += Number.parseFloat(row.debit_amt());
            other_adj_tot_fc += Number.parseFloat(row.debit_amt_fc());
        });
//
//        net_payable_amt_tot += other_adj_tot;
//        net_payable_amt_tot_fc += other_adj_tot_fc;
//        
        net_credit_amt_tot = net_payable_amt_tot + other_adj_tot - adv_tot - net_receivable_amt_tot;
        net_credit_amt_tot_fc = net_payable_amt_tot_fc + other_adj_tot_fc - adv_tot_fc - net_receivable_amt_tot_fc;

        coreWebApp.ModelBo.annex_info.other_adj(other_adj_tot.toFixed(2));
        coreWebApp.ModelBo.annex_info.other_adj_fc(other_adj_tot_fc.toFixed(2));
        coreWebApp.ModelBo.credit_amt(net_credit_amt_tot.toFixed(2));
        coreWebApp.ModelBo.credit_amt_fc(net_credit_amt_tot_fc.toFixed(2));    
        coreWebApp.ModelBo.annex_info.payable_amt(net_payable_amt_tot.toFixed(2));
        coreWebApp.ModelBo.annex_info.payable_amt_fc(net_payable_amt_tot_fc.toFixed(2));
        coreWebApp.ModelBo.annex_info.receivable_amt(net_receivable_amt_tot.toFixed(2));
        coreWebApp.ModelBo.annex_info.receivable_amt_fc(net_receivable_amt_tot_fc.toFixed(2));
        coreWebApp.ModelBo.annex_info.supp_adv_amt(adv_tot.toFixed(2));
        coreWebApp.ModelBo.annex_info.supp_adv_amt_fc(adv_tot_fc.toFixed(2));

    }
    core_pymt.total_calc = total_calc;

    function pl_tran_delete() {
        total_calc();
    }
    core_pymt.pl_tran_delete = pl_tran_delete;
    
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
                debit_amt_total: coreWebApp.ModelBo.credit_amt(),
                debit_amt_total_fc: 0,
                sl_tran: coreWebApp.ModelBo.shl_head_tran, // The observable array is sent 
                ref_ledger_tran: coreWebApp.ModelBo.rla_head_tran, // The observable array is sent  
                dc: 'C',
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
    core_pymt.sub_head_alloc_click = sub_head_alloc_click;

    function sub_head_alloc_after_update() {
    }
    
    function SelectInvoice() {
        var opts = {
            voucher_id: coreWebApp.ModelBo.voucher_id(),
            to_date : coreWebApp.ModelBo.doc_date(),
            account_id: coreWebApp.ModelBo.annex_info.customer_id(),
            branch_id: coreWebApp.ModelBo.branch_id(),
            fc_type_id: coreWebApp.ModelBo.fc_type_id(),
            is_inter_branch: coreWebApp.ModelBo.is_inter_branch(),
            rl_tran: coreWebApp.ModelBo.receivable_ledger_alloc_tran,
            after_update: select_inv_after_update
        };
        opts.module = 'core/ap';
        opts.alloc_view = '/supplierPayment/SelectInvoice';
        opts.call_init = select_inv_init;
        opts.call_update = select_inv_alloc_update;
        coreWebApp.showAllocV2(opts);
    }
    core_pymt.SelectInvoice = SelectInvoice;
    
    function select_inv_after_update() {
        total_calc();
    }

    // function to set default values for Tax Detail
    function select_inv_init(opts, after_init) {
        $.ajax({
            url: '?r=core/ap/form/select-inv-in-rcpt',
            type: 'GET',
            dataType: 'json',
            data: {
                voucher_id: opts.voucher_id,
                to_date: opts.to_date,
                account_id: opts.account_id,
                branch_id: opts.branch_id,
                fc_type_id: opts.fc_type_id,
                is_inter_branch: opts.is_inter_branch, 
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
    core_pymt.select_inv_init = select_inv_init;
    
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
    core_pymt.select_inv_alloc_update = select_inv_alloc_update;    
 
    function pl_tran_delete() {
        total_calc();
    }
    core_pymt.pl_tran_delete = pl_tran_delete;
    
    function adv_alloc_click() {
        if (coreWebApp.ModelBo.supplier_account_id() === -1) {
            coreWebApp.toastmsg('warning', 'Advance Click Error', 'Select Supplier to view advance.', false);
            return;
        } else {
            var opts = {
                voucher_id: coreWebApp.ModelBo.voucher_id(),
                doc_date: coreWebApp.ModelBo.doc_date(),
                account_id: coreWebApp.ModelBo.supplier_account_id(),
                fc_type_id: coreWebApp.ModelBo.fc_type_id(),
                exch_rate: coreWebApp.ModelBo.exch_rate(),
                credit_amt_total: coreWebApp.ModelBo.annex_info.payable_amt(),
                credit_amt_total_fc: coreWebApp.ModelBo.annex_info.payable_amt_fc(),
                pl_tran: coreWebApp.ModelBo.payable_ledger_alloc_tran, // The observable array is sent  
                dc: 'D',
                after_update: adv_alloc_after_update
            };
            core_ap.adv_alloc_ui(opts);
        }
    }
    core_pymt.adv_alloc_click = adv_alloc_click;

    function adv_alloc_after_update() {
        total_calc();
    }

}(window.core_pymt));

// GST Methods and utils that are part of tx
window.core_pymt.pymt_wiz = {};
(function (pymt_wiz) {
    
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
                {data: "bill_no", title: "Bill No"},
                {data: "bill_date", title: "Bill Dt.", className: "dt-center",
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
    pymt_wiz.select_vch_init = select_vch_init;
    
}(window.core_pymt.pymt_wiz));
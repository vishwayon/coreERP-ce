// Declare core_ap Namespace
window.msp = {};
(function (msp) {
    function visible_balance(dataItem) {
        //applysmartcontrols(); 
        if (coreWebApp.ModelBo.status() == 5) {
            return false;
        } else {
            return true;
        }
    }

    msp.visible_balance = visible_balance;

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

    msp.visible_balance_fc = visible_balance_fc;

    function enable_inter_branch(dataItem) {
        //applysmartcontrols(); 
        if (coreWebApp.ModelBo.voucher_id() == "") {
            return true;
        } else {
            return false;
        }
    }

    msp.enable_inter_branch = enable_inter_branch;

    function afterload() {
        $('#cmd_addnew_payable_ledger_alloc_tran').hide();
        if (coreWebApp.ModelBo.status() == 5) {
            $('#seleBill').hide();
        }
        total_calc();
    }
    msp.afterload = afterload;

    function pymt_fc_changed(dataItem) {
        var exch_rate = parseFloat(dataItem.exch_rate());
        var fc_type_id = parseFloat(dataItem.fc_type_id());
        ko.utils.arrayForEach(dataItem.payable_ledger_alloc_tran(), function (a) {
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

    msp.pymt_fc_changed = pymt_fc_changed;


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
    msp.pymt_amount_changed = pymt_amount_changed;


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
    msp.pymt_dis_changed = pymt_dis_changed;


    function SelectBill() {

        var opts = {
            voucher_id: coreWebApp.ModelBo.voucher_id(),
            supplier_account_id: coreWebApp.ModelBo.supplier_account_id(),
            is_inter_branch: coreWebApp.ModelBo.is_inter_branch(),
            fc_type_id: coreWebApp.ModelBo.fc_type_id(),
            doc_date: coreWebApp.ModelBo.doc_date(),
            pl_tran: coreWebApp.ModelBo.payable_ledger_alloc_tran,
            after_update: select_bill_after_update
        };
        opts.module = 'core/ap';
        opts.alloc_view = '/multiSuppPayment/SelectBill';
        opts.call_init = select_bill_init;
        opts.call_update = select_bill_update;
        coreWebApp.showAllocV2(opts);
    }
    msp.SelectBill = SelectBill;

    // function to set default values for Tax Detail
    function select_bill_init(opts, after_init) {
        var sel_bill = new function () {
            self = this;
        };
        sel_bill.bill_temp = {};
        sel_bill.voucher_id = opts.voucher_id;
        sel_bill.doc_date = opts.doc_date;
        sel_bill.supplier_account_id = ko.observable(-1);
        sel_bill.branch_id = opts.branch_id;
        sel_bill.is_inter_branch = opts.is_inter_branch;
        sel_bill.fc_type_id = opts.fc_type_id;
        sel_bill.pla_tran = opts.payable_ledger_alloc_tran;
        opts.model = sel_bill;
//        mcr.get_detail();
        $('#sele_bill-loading').hide();
    }    
    msp.select_bill_init = select_bill_init;

    function get_detail() {
        $.ajax({
            url: '?r=core/ap/form/selectbillinpymt',
            type: 'GET',
            dataType: 'json',
            data: {'voucher_id': self.voucher_id, 'account_id': self.supplier_account_id,
                'fc_type_id': self.fc_type_id, 'is_inter_branch': self.is_inter_branch, 'doc_date': self.doc_date},
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (jsonResult) {
                if (jsonResult.status === 'ok') {

                    self.bill_temp = jsonResult.bill_balance;

                    self.bill_temp.forEach(itm => {
                        itm.is_select = ko.observable(false);
                        coreWebApp.ModelBo.payable_ledger_alloc_tran().forEach(pl_tran => {
                            if (pl_tran.rl_pl_id() == itm.rl_pl_id) {
                                itm.is_select(true);
                            }
                        });
                    });
                    $('#sele_bill-loading').hide();
                    // Using a datatable to render data
                    if (coreWebApp.ModelBo.fc_type_id() != 0) {
                        $('#bill_temp-cont').width(($('#bill_temp-cont').width() + 200).toString() + "px");
                    }
                    if ($.fn.dataTable.isDataTable('#bill_temp')) {
                        var t = $('#bill_temp').DataTable();
                        t.destroy();
                    }
                    var tbl = $('#bill_temp').DataTable({
                        data: self.bill_temp,
                        order: [],
                        columns: [
                            {data: "is_select", title: "...",
                                createdCell: function (td, cellData, rowData, row, col) {
                                    $(td).html('<input type="checkbox" data-bind="checked: is_select">');
                                    ko.applyBindings(rowData, $(td)[0]);
                                    $(td).css('text-align', 'center');
                                }
                            },
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
    msp.get_detail = get_detail;

    //function to update tax detail pop up fields to tax_detail_tran
    function select_bill_update(opts) {
        opts.model.bill_temp.forEach(plt => {
            if (plt.is_select()) {
                var row_exists = false;
                for (var q = 0; q < coreWebApp.ModelBo.payable_ledger_alloc_tran().length; q++) {
                    if (plt['rl_pl_id'] == coreWebApp.ModelBo.payable_ledger_alloc_tran()[q]['rl_pl_id']()) {
                        row_exists = true;
                        break;
                    }
                }
                if (row_exists == false) {
                    var nr = coreWebApp.ModelBo.addNewRow('payable_ledger_alloc_tran', coreWebApp.ModelBo, true);
                    nr.branch_id(plt['branch_id']);
                    nr.bill_id(plt['voucher_id']);
                    nr.doc_date(plt['doc_date']);
                    nr.bill_no(plt['bill_no']);
                    nr.bill_date(plt['bill_date']);
                    coreWebApp.lookupCache.add('account_id', plt['account_id'], plt['account_head']);
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
    msp.select_bill_update = select_bill_update;

    function select_bill_after_update() {
        total_calc();
    }

    function total_calc() {
        var net_credit_amt_tot = new Number(0.00);
        var net_credit_amt_tot_fc = new Number(0.00);
        var other_adj_tot = new Number(0.00);
        var other_adj_tot_fc = new Number(0.00);

        // Total each item
        ko.utils.arrayForEach(coreWebApp.ModelBo.payable_ledger_alloc_tran(), function (row) {
            net_credit_amt_tot += Number.parseFloat(row.net_debit_amt());
            net_credit_amt_tot_fc += Number.parseFloat(row.net_debit_amt_fc());
        });

        ko.utils.arrayForEach(coreWebApp.ModelBo.pymt_tran(), function (row) {
            other_adj_tot += Number.parseFloat(row.debit_amt());
            other_adj_tot_fc += Number.parseFloat(row.debit_amt_fc());
        });

        net_credit_amt_tot += other_adj_tot;
        net_credit_amt_tot_fc += other_adj_tot_fc;


        coreWebApp.ModelBo.annex_info.other_adj(other_adj_tot.toFixed(2));
        coreWebApp.ModelBo.annex_info.other_adj_fc(other_adj_tot_fc.toFixed(2));
        coreWebApp.ModelBo.credit_amt(net_credit_amt_tot.toFixed(2));
        coreWebApp.ModelBo.credit_amt_fc(net_credit_amt_tot_fc.toFixed(2));

    }
    msp.total_calc = total_calc;

    function pl_tran_delete() {
        total_calc();
    }
    msp.pl_tran_delete = pl_tran_delete;

}(window.msp));
// Declare core_st Namespace
//typeof window.core_ar == 'undefined' ? window.core_ar = {} : '';
window.tds_pay = {};
(function (tds_pay) {   
    tds_pay.sl_no = 0;
    stop_calc = false;
    function afterload() {
        $('#cmd_addnew_bill_tds_tran').hide();
        tds_pay.sl_no = coreWebApp.ModelBo.bill_tds_tran().length;
        if(coreWebApp.ModelBo.voucher_id() == ''){
            tds_pay.total_calc();
        }
    }
    tds_pay.afterload = afterload;  
    
    function total_calc() {
        console.log('total_calc');
        tds_pay.sl_no = 0;
        var tds_amt_tot = new Number(0.00);
        // Total each invoice item
        ko.utils.arrayForEach(coreWebApp.ModelBo.bill_tds_tran(), function (row) {
            tds_pay.sl_no += 1;
            tds_amt_tot += Number.parseFloat(row.tds_amt());
        });
        coreWebApp.ModelBo.tds_total_amt(tds_amt_tot.toFixed(2));
        coreWebApp.ModelBo.amt((Number.parseFloat(coreWebApp.ModelBo.tds_total_amt()) + Number.parseFloat(coreWebApp.ModelBo.interest_amt()) + Number.parseFloat(coreWebApp.ModelBo.penalty_amt())).toFixed(2));
    }
    tds_pay.total_calc = total_calc;

    function tds_tran_delete() {
        tds_pay.total_calc();
    }
    tds_pay.tds_tran_delete = tds_tran_delete;    

    function select_bill() {
        var opts = {
            voucher_id: coreWebApp.ModelBo.voucher_id(),
            person_type_id: coreWebApp.ModelBo.annex_info.person_type_id(),
            bill_tds_tran: coreWebApp.ModelBo.bill_tds_tran, // The observable array is sent   
            after_update: select_bill_after_update
        };

        opts.module = 'core/tds';
        opts.alloc_view = 'tdsPayment/SelectBill';
        opts.call_init = select_bill_init;
        opts.call_update = select_bill_update;
        coreWebApp.showAllocV2(opts);
    }
    tds_pay.select_bill = select_bill;

    function select_bill_after_update() {
        total_calc();
    }

    function select_bill_init(opts, after_init) {
        $.ajax({
            url: '?r=core/tds/form/get-bills-for-tds-pay',
            type: 'GET',
            dataType: 'json',
            data: {
                person_type_id: opts.person_type_id, payment_id: opts.voucher_id, 
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (jsonResult) {
                if (jsonResult['status'] === 'ok') {
                    // Using a datatable to render data
                    if ($.fn.dataTable.isDataTable('#bill_temp')) {
                        var t = $('#bill_temp').DataTable();
                        t.destroy(true);
                        var p = $('#bill_temp-cont');
                        p.append('<table id="bill_temp" class="table table-hover table-condensed dataTable no-footer"></table>');
                    }
                    var bill_alloc = new function () {
                        self = this;
                    };
                    bill_alloc.bill_temp =jsonResult.bill_bal;
                    
                    bill_alloc.bill_temp.forEach(br => {
                        br.is_select = ko.observable(false);
                        for (var a = 0; a < opts.bill_tds_tran().length; ++a) {
                            var poc = opts.bill_tds_tran()[a];
                            if (poc.voucher_id() == br.voucher_id) {
                                br.is_select(true);
                            }
                        }
                    });
                    
                    $('#sele-bill-loading').hide();
                    if ($.fn.dataTable.isDataTable('#bill_temp')) {
                        var t = $('#bill_temp').DataTable();
                        t.destroy();
                    }
                    var tbl = $('#bill_temp').DataTable({
                        data: self.bill_temp,
                        order: [],
                        columns: [
                            {data: "is_select", title: "...", width: "5%",
                                createdCell: function (td, cellData, rowData, row, col) {
                                    $(td).html('<input type="checkbox" data-bind="checked: is_select">');
                                    ko.applyBindings(rowData, $(td)[0]);
                                    $(td).css('text-align', 'center');
                                }
                            },
                            {data: "supplier", title: "Supplier", width: "20%"},
                            {data: "voucher_id", title: "Voucher No", width: "20%"},
                            {data: "doc_date", title: "Date", width: "10%",
                                render: function (cellData) {
                                    return coreWebApp.formatDate(cellData);
                                }
                            },
                            {data: "bill_amt", title: "Bill Amt", className: "dt-right", width: "15%",
                                render: function (cellData) {
                                    return coreWebApp.formatNumber(cellData, 2);
                                }
                            },
                            {data: "tds_amt", title: "TDS Amt", className: "dt-right", width: "15%",
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
                    
                    opts.model = bill_alloc;
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
    }
    tds_pay.select_bill_init = select_bill_init;

    function build_bill_temp() {
        var bill_temp = ko.observableArray();
        bill_temp.addNewRow = function () {
            var cobj = new Object();
            cobj.doc_date = ko.observable('1970-01-01');
            cobj.branch_id = ko.observable(-1);
            cobj.bill_amt = ko.observable(0);
            cobj.tds_amt = ko.observable(0);
            cobj.voucher_id = ko.observable('');
            cobj.supplier = ko.observable('');
            cobj.supplier_id = ko.observable(-1);
            cobj.is_select = ko.observable(false);
            return cobj;
        };
        return bill_temp;
    }
    tds_pay.build_bill_temp = build_bill_temp;

    function select_bill_update(opts) {
        // clear existing alloc
        for (var p = 0; p < opts.model.bill_temp.length; ++p) {
            var rlt = opts.model.bill_temp[p];
            if (rlt.is_select() == true) {
                var row_exists = false;
                for (var q = 0; q < coreWebApp.ModelBo.bill_tds_tran().length; q++) {
                    if (rlt['voucher_id'] == coreWebApp.ModelBo.bill_tds_tran()[q]['voucher_id']()) {
                        row_exists = true;
                        break;
                    }
                }
                tds_pay.sl_no += 1;
                if (row_exists == false) {
                    var nr = coreWebApp.ModelBo.addNewRow('bill_tds_tran', coreWebApp.ModelBo, true);
                    nr.bill_tds_tran_id('');
                    nr.voucher_id(rlt['voucher_id']);
                    nr.supplier(rlt['supplier']);
                    nr.supplier_id(rlt['supplier_id']);
                    nr.doc_date(rlt['doc_date']);
                    nr.bill_amt(rlt['bill_amt']);
                    nr.tds_amt(rlt['tds_amt']);
                    nr.branch_id(rlt['branch_id']);
                    nr.company_id(coreWebApp.ModelBo.company_id());
                    coreWebApp.afterNewRowAdded(false);
                    total_calc();
                }
            }
        }
        opts.bill_tds_tran.valueHasMutated();
        delete opts.model; // remove the temporary model created
        return true;
    }
    tds_pay.select_bill_update = select_bill_update;
    
}(window.tds_pay));


// GST Methods and utils that are part of tx
window.tds_pay.tds_pay_wiz = {};
(function (tds_pay_wiz) {
    
    function select_bill_init(args) {
        $('#tbl-SelectBill').DataTable({
            data: args.model.SelectBill(),
            order: [],
            columns: [
                {data: "selected", title: "...", width: "5%",
                    createdCell: function (td, cellData, rowData, row, col) {
                        $(td).html('<input type="checkbox" data-bind="checked: selected">');
                        ko.applyBindings(rowData, $(td)[0]);
                        $(td).css('text-align', 'center');
                    }
                },
                {data: "supplier", title: "Supplier", width: "20%"},
                {data: "voucher_id", title: "Voucher No", width: "20%"},
                {data: "doc_date", title: "Deduction Date", width: "10%",
                    render: function (cellData) {
                        return coreWebApp.formatDate(cellData());
                    }
                },
                {data: "bill_amt", title: "Bill Amt", className: "dt-right", width: "15%",
                    render: function (cellData) {
                        return coreWebApp.formatNumber(cellData(), 2);
                    }
                },
                {data: "tds_amt", title: "TDS Amt", className: "dt-right", width: "15%",
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
    tds_pay_wiz.select_bill_init = select_bill_init;
    
}(window.tds_pay.tds_pay_wiz));
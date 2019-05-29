// Declare core_ap Namespace
window.core_bank_trs = {};
(function (core_pymt) {
    
    core_bank_trs.sl_no = 1;
     
    function afterload_wiz() {
        core_bank_trs.sl_no = coreWebApp.ModelBo.pymt_tran().length;
        $('#cmd_addnew_pymt_tran').detach();        
        if (coreWebApp.ModelBo.status() == 5) {
            $('#seleBill').hide();
        }
        total_calc();
    }
    core_bank_trs.afterload_wiz = afterload_wiz;
      
    function pymt_tran_add(row) {
        core_bank_trs.sl_no += 1;
        row.sl_no(core_bank_trs.sl_no);
    }
    core_bank_trs.pymt_tran_add = pymt_tran_add;
    
    
    function after_tran_delete() {
        core_bank_trs.sl_no = 0;
        coreWebApp.ModelBo.pymt_tran().forEach(function (row) {
            core_bank_trs.sl_no += 1;
            row.sl_no(core_bank_trs.sl_no);
        });
        total_calc();
    }
    core_bank_trs.after_tran_delete = after_tran_delete;

    function SelectBill() {
        var opts = {
            pay_cycle_id: coreWebApp.ModelBo.annex_info.pay_cycle_id(),
            bank_account_id: coreWebApp.ModelBo.annex_info.bank_account_id(),
            pymt_tran_temp: coreWebApp.ModelBo.pymt_tran,
            after_update: select_bill_after_update
        };
        opts.module = 'core/ap';
        opts.alloc_view = '/bankTransfer/SelectBill';
        opts.call_init = select_bill_init;
        opts.call_update = select_bill_update;
        coreWebApp.showAllocV2(opts);
    }
    core_bank_trs.SelectBill = SelectBill;


    function select_bill_init(opts, after_init) {
        debugger;
        $.ajax({
            url: '?r=core/ap/form/selectbillforpaycycle',
            type: 'GET',
            dataType: 'json',
            data: {'pay_cycle_id': opts.pay_cycle_id, 'bank_account_id': opts.bank_account_id, 'voucher_id' : coreWebApp.ModelBo.voucher_id()},
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (jsonResult) {
                if (jsonResult.status === 'ok') {

                    var sel_bill = new function () {
                        self = this;
                    };
                    sel_bill.bill_temp = jsonResult.bill_tran;

                    sel_bill.bill_temp.forEach(itm => {
                        itm.is_select = ko.observable(false);
                        coreWebApp.ModelBo.pymt_tran().forEach(pymt_tran_temp => {
                            if (pymt_tran_temp.reference_id() == itm.voucher_id) {
                                itm.is_select(true);
                            }
                        });
                    });
                    opts.model = sel_bill;
                    $('#sele_bill-loading').hide();
                    after_init();
                   
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
                            {data: "voucher_id", title: "Document #"},
                            {data: "doc_date", title: "Doc Dt.",
                                render: function (cellData) {
                                    return coreWebApp.formatDate(cellData);
                                }
                            },
                            {data: "supplier", title: "Supplier"},
                            {data: "credit_amt", title: "Amount", className: "dt-right",
                                render: function (cellData) {
                                    return coreWebApp.formatNumber(cellData, 2);
                                }
                            },
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
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server.', false);
            }
        });
    }
    core_bank_trs.select_bill_init = select_bill_init;


    function select_bill_update(opts) {
        opts.model.bill_temp.forEach(plt => {
            if (plt.is_select()) {
                var row_exists = false;
                for (var q = 0; q < coreWebApp.ModelBo.pymt_tran().length; q++) {
                    if (plt['voucher_id'] == coreWebApp.ModelBo.pymt_tran()[q]['reference_id']()) {
                        row_exists = true;
                        break;
                    }
                }
                if (row_exists == false) {
                    var nr = coreWebApp.ModelBo.addNewRow('pymt_tran', coreWebApp.ModelBo, true);
                    nr.reference_id(plt['voucher_id']);
                    nr.vch_date(plt['doc_date']);
                    nr.account_id(plt['supplier_account_id']);
                    nr.debit_amt(parseFloat(plt['credit_amt']));
                    coreWebApp.afterNewRowAdded(false);
                }
            }
        });
        delete opts.model; // remove the temporary model created
        return true;
    }
    core_bank_trs.select_bill_update = select_bill_update;
        
    function select_bill_after_update() {
        total_calc();
    }

    function total_calc() {
        core_bank_trs.sl_no = 0;
        coreWebApp.ModelBo.pymt_tran().forEach(function (row) {
            core_bank_trs.sl_no += 1;
            row.sl_no(core_bank_trs.sl_no);
        });
        
        var net_credit_amt_tot = new Number(0.00);
        var net_credit_amt_tot_fc = new Number(0.00);
        // Total each item
        ko.utils.arrayForEach(coreWebApp.ModelBo.pymt_tran(), function (row) {
            net_credit_amt_tot += Number.parseFloat(row.debit_amt());
        });
        coreWebApp.ModelBo.credit_amt(net_credit_amt_tot.toFixed(2));
    }
    core_bank_trs.total_calc = total_calc;
    
}(window.core_bank_trs));


// GST Methods and utils that are part of tx
window.core_bank_trs.pymt_wiz = {};
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
                {data: "supplier", title: "Supplier"},
                {data: "credit_amt", title: "Amount", className: "dt-right",
                    render: function (cellData) {
                        return coreWebApp.formatNumber(cellData(), 2);
                    }
                },
            ],
            deferRender: true,
            scrollY: '400px',
            scrollCollapse: true,
            scroller: true,
        });
    }
    pymt_wiz.select_vch_init = select_vch_init;
    
 function file_download() {
        $.ajax({
            url: '?r=core/ap/main/download-csv',
            method: 'GET',
            dataType: 'json',
            data: {
                bank_transfer_id: $('#voucher_id').val(),
                reqtime: new Date().getTime()
            },
            success: function (jdata) {
                var link = document.createElement('a');
                link.setAttribute("href", jdata.filePath);
                link.setAttribute("id", "file_link");
                link.setAttribute("download", jdata.fileName);
                var cnt = document.getElementById('content-root');
                cnt.appendChild(link);
                link.click();
            }
        });
    }
    pymt_wiz.file_download = file_download;
    
    function file_btn_visible(){
        return (coreWebApp.ModelBo.status()==5);
    }
    pymt_wiz.file_btn_visible=file_btn_visible;

}(window.core_bank_trs.pymt_wiz));
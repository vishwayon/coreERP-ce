// Declare Trial Balance inside modulelevel ac//
window.core_ac_tb = {};
    
(function (core_ac_tb) {
    
    // We try to bind the Account Head in each page with 
    // accountClick handler
    function afterPageRefresh(page) {
        page.on('dblclick', '.click_link', core_ac_tb.accountClick);
    }
    core_ac_tb.afterPageRefresh = afterPageRefresh;
    
    function accountClick() {
        var ac_id = this.id.replace("acc_", "");
        var rptOptions = { 
            pbranch_id: $('#pbranch_id').val(),
            pfrom_date: coreWebApp.unformatDate($('#pfrom_date').val()),
            pto_date: coreWebApp.unformatDate($('#pto_date').val()),
            paccount_id: ac_id,
            pcategory: 'Any',
            pshow_narration: true,
            pshow_cheque_details: true,
            pdisplay_fc_amount: false
        };
        $.ajax({
            url: '?r=cwf/fwShell/jreport/view-report',
            type: 'POST',
            data: { xmlPath: '../core/ac/reports/generalLedger/GeneralLedger', rptOptions: JSON.stringify(rptOptions) },
            success: function (resultdata) {
                $('#content-root').html(resultdata);
//                applysmartcontrols();
            },
            error: function (data) {
                toastmsg('error','Server Error',data.responseText,true);
                stoploading();
            }
        });
    }
    core_ac_tb.accountClick = accountClick;
    
}(window.core_ac_tb));



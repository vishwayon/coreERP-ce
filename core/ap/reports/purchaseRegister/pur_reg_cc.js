typeof window.core_ap == 'undefined' ? window.core_ap = {} : '';
window.core_ap.pur_reg = {};

(function (pur_reg) {

    function btn_raw_export_click() {
        var dataParams = {
            pbranch_id: $('#pbranch_id').val(),
            pfrom_date: $('#pfrom_date').val(),
            pto_date: $('#pto_date').val(),
            psupplier_id: $('#psupplier_id').val(),
            pgst_state_id: $('#pgst_state_id').val(),
            pgroup_path: $('#pgroup_path').val(),
            pinclude_non_gst: $('#pinclude_non_gst').is(':checked')
        };
        $.ajax({
            url: '?r=core/ap/purch-reg/get-csv',
            method: 'POST',
            data: { jParam: JSON.stringify(dataParams) },
            dataType: 'json',
            success: function(jdata) {
                var link = document.createElement('a');
                link.setAttribute("href", jdata.filePath);
                link.setAttribute("id", "gstr2_file_link");
                link.setAttribute("download", jdata.fileName);
                var cnt = document.getElementById('content-root');
                cnt.appendChild(link);
                link.click();
            }
        });
    }
    pur_reg.btn_raw_export_click = btn_raw_export_click;

}(window.core_ap.pur_reg));
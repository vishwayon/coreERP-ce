typeof window.core_tx == 'undefined' ? window.core_tx = {} : '';
window.core_tx.gstr1_detail = {};
(function (gstr1_detail) {

    function get_data() {
        $('#lbl_gd').show();
        $('[id^="detail_data_"]').hide();
        $.ajax({
            url: '?r=core/tx/gst-return/get-gstr1-detail-data',
            method: 'GET',
            data: {gst_ret_id: $('#gst_ret_id').val(), detail_type: $('#detail_type').val()},
            dataType: 'json',
            success: function (result) {
                $('#lbl_gd').hide();
                switch ($('#detail_type').val()) {
                    case "4":
                        $('#detail_data_b2b').show();
                        ko.cleanNode($('#detail_data_b2b')[0]);
                        ko.applyBindings(result, $('#detail_data_b2b')[0]);
                        break;
                    case "7":
                        $('#detail_data_b2cs').show();
                        ko.cleanNode($('#detail_data_b2cs')[0]);
                        ko.applyBindings(result, $('#detail_data_b2cs')[0]);
                        break;
                    case "8":
                        $('#detail_data_exemp').show();
                        ko.cleanNode($('#detail_data_exemp')[0]);
                        ko.applyBindings(result, $('#detail_data_exemp')[0]);
                        break;
                }
                
            }
        });
    }
    gstr1_detail.get_data = get_data;
    
    function get_gstr1_detail_csv() {
        $.ajax({
            url: '?r=core/tx/gst-return/get-gstr1-detail-csv',
            method: 'GET',
            dataType: 'json',
            data: {gst_ret_id: $('#gst_ret_id').val(), detail_type: $('#detail_type').val()},
            success: function (jdata) {
                if(jdata.status == 'OK') {
                    // Always download the file
                    var link = document.createElement('a');
                    link.setAttribute("href", jdata.filePath);
                    link.setAttribute("id", "gstr1_file_link");
                    link.setAttribute("download", jdata.fileName);
                    var cnt = document.getElementById('content-root');
                    cnt.appendChild(link);
                    link.click();
                } else {
                    alert('Download Failed. Contact support');
                }
                
            }
        });
    }
    gstr1_detail.get_gstr1_detail_csv = get_gstr1_detail_csv;
    
    function get_col_total(col_name, data) {
        var total = new Number(0.00);
        data.forEach(function (row) {
            if(Array.isArray(col_name)) {
                $.each(col_name, function (id, col) {
                   total += parseFloat(row[col]); 
                });
            } else {
                total += parseFloat(row[col_name]);
            }
        });
        return total;
    }
    gstr1_detail.get_col_total = get_col_total;

}(window.core_tx.gstr1_detail));



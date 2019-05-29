//create and bind temp namespace
typeof window.core_st == 'undefined' ? window.core_st = {} : '';
window.core_st.mat_val_mon = {};
(function (mat_val_mon) {
    function get_data() {
        if($('#report_type').val() == 2 && $('#material_type_id').val() <= 0 ) {
            coreWebApp.toastmsg('warning', 'Missing Inputs', 'Stock Type required for pre-process');
            return;
        }
        var vurl = '';
        debugger;
        if($('#report_type').val() == 1) {
            vurl = '?r=core/st/mat-val-mon/fetch-neg-bal';
        } else if($('#report_type').val() == 2) {
            coreWebApp.toastmsg('warning', 'Wac Co-variance', 'This process has a large overhead <br/>and will consume considerable server resources<br/>Please wait for some time before the results populate');
            vurl = '?r=core/st/mat-val-mon/fetch-wac-cv';
        }
        $.ajax({
            url: vurl,
            type: 'GET',
            dataType: 'json',
            data: { 
                mat_type_id: $('#material_type_id').val(),
                mat_id: 0,
                reqtime: new Date().getTime()
            },
            beforeSend: function () {
                coreWebApp.startloading();
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                process_result(resultdata);
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                coreWebApp.stoploading();
            }
        });
    }
    mat_val_mon.get_data = get_data;
    
    function process_result(resultdata) {
        $('#div-negstock').hide();
        ko.cleanNode($('#div-negstock')[0]);
        $('#div-waccv').hide();
        ko.cleanNode($('#div-waccv')[0]);
        
        mat_val_mon.model = new function() {
            self = this;
        };
        if(typeof resultdata['negstock'] != 'undefined') {
            mat_val_mon.model.negstock = ko.mapping.fromJS(resultdata['negstock']);
            if ($.fn.dataTable.isDataTable('#negstock')) {
                var t = $('#negstock').DataTable();
                t.destroy();
            }
            $('#div-negstock').show();
            ko.cleanNode($('#div-negstock')[0]);
            ko.applyBindings(mat_val_mon.model, $('#div-negstock')[0]);
            coreWebApp.initCollection('negstock');
        } else if(typeof resultdata['waccv'] != 'undefined') {
            mat_val_mon.model.waccv = ko.mapping.fromJS(resultdata['waccv']);
            if ($.fn.dataTable.isDataTable('#waccv')) {
                var t = $('#waccv').DataTable();
                t.destroy();
            }
            $('#div-waccv').show();
            ko.cleanNode($('#div-waccv')[0]);
            ko.applyBindings(mat_val_mon.model, $('#div-waccv')[0]);
            coreWebApp.initCollection('waccv');
        }
        
    }

} (window.core_st.mat_val_mon));


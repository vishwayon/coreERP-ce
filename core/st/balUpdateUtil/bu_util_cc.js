//create and bind temp namespace
typeof window.core_st == 'undefined' ? window.core_st = {} : '';
window.core_st.bu_util = {};
(function (bu_util) {
    function get_data() {
        $('#brules').html('');
        if($('#material_type_id').val() <= 0 || $('#stock_location_id').val() <= 0) {
            coreWebApp.toastmsg('warning', 'Missing Inputs', 'Stock Type/Location required for pre-process');
            return;
        }
        $.ajax({
            url: '?r=core/st/bal-update-util/get',
            type: 'GET',
            dataType: 'json',
            data: { 
                material_type_id: $('#material_type_id').val(), 
                stock_location_id: $('#stock_location_id').val(),
                as_on: coreWebApp.unformatDate($('#as_on').val()),
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
    bu_util.get_data = get_data;
    
    function process_result(resultdata) {
        bu_util.model = new function() {
            self = this;
        };
        bu_util.model.mat_type_id = ko.observable(resultdata['mat_type_id']);
        bu_util.model.sl_id = ko.observable(resultdata['sl_id']);
        bu_util.model.as_on = ko.observable(resultdata['as_on']);
        bu_util.model.matbal = ko.mapping.fromJS(resultdata['matbal']);
        bu_util.model.matbal().forEach(itm => {
            itm.revised_cl_bal.subscribe(function(newValue) {
                // Do a reverse calculation
                var newOp = parseFloat(this.revised_cl_bal()) + parseFloat(this.issues()) - parseFloat(this.receipts());
                if (newOp < 0) {
                   coreWebApp.toastmsg('warning', 'Warning', 'Opening Balance Negative');
                }
                this.revised_op_bal(newOp.toFixed(3));
            }, itm); 
        });
        if ($.fn.dataTable.isDataTable('#vch_tran')) {
            var t = $('#vch_tran').DataTable();
            t.destroy();
        }
        $('#matbal').show();
        ko.cleanNode($('#matbal')[0]);
        ko.applyBindings(bu_util.model, $('#matbal')[0]);
        bu_util.ToggleUpdate();
        coreWebApp.initCollection('vch_tran');
    }

    function post_data() {
        var data = ko.mapping.toJSON(bu_util.model);
        $.ajax({
            url: '?r=core/st/bal-update-util/update',
            type: 'POST',
            data: {
                model: data
            },
            dataType: 'json',
            beforeSend: function () {
                coreWebApp.startloading();
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (jsonResult) {
                $('#brules').html('');
                if (jsonResult.brokenrules.length > 0) {
                    coreWebApp.toastmsg('warning', 'Save Failed', '', false);
                    var brules = jsonResult.brokenrules;
                    var litems = '<strong>Broken Rules</strong>';
                    for (var i = 0; i < brules.length; i++) {
                        litems += "<li>" + brules[i] + "</li>";
                    }
                    $('#brules').append(litems);
                    $('#divbrules').show();
                } else {
                    coreWebApp.toastmsg('message', 'Success', 'Successfully updated '+jsonResult.updated+' record(s)');
                    // refresh the data
                    bu_util.get_data();
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Server Error', data.responseText, true);
                coreWebApp.stoploading();
            }
        });
        return false;
    }
    bu_util.post_data = post_data;


    function calTS() {
    }
    bu_util.CalTS = calTS;

    function getTimestamp(ctr) {
        var dateval = $(ctr).val();
        var unfdate = coreWebApp.unformatDate(dateval);
        var ts = new Date(unfdate).getTime();
        return ts;
    }
    bu_util.GetTimestamp = getTimestamp;

    function toggleUpdate() {
        $('#cmdupdate').hide();
        $('#filebuttons').hide();
        if (bu_util.model.matbal().length > 0) {
            $('#cmdupdate').show();
            $('#filebuttons').show();
        }
    }
    bu_util.ToggleUpdate = toggleUpdate;
    
    function file_download() {
        $.ajax({
            url: '?r=core/st/bal-update-util/download',
            method: 'GET',
            dataType: 'json',
            data: { 
                material_type_id: $('#material_type_id').val(), 
                stock_location_id: $('#stock_location_id').val(),
                as_on: coreWebApp.unformatDate($('#as_on').val()),
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
    bu_util.file_download = file_download;
    
    function file_upload() {
        if($('#fupload').val()==''){
            coreWebApp.toastmsg('error', 'File upload', 'Select a file to proceed');
            return;
        }
        $('#cmdupdate').attr('disabled','true');
        var fd = new FormData(document.getElementById('upload-form'));
        fd.append("material_type_id", $('#material_type_id').val()); 
        fd.append("stock_location_id", $('#stock_location_id').val());
        fd.append("as_on", coreWebApp.unformatDate($('#as_on').val()));
        fd.append("_csrf", $('#_csrf').val());
        fd.append("label", "WEBUPLOAD");
        $.ajax({
            url: '?r=core/st/bal-update-util/upload',
            type: "POST",
            data: fd,
            enctype: 'multipart/form-data',
            processData: false, // tell jQuery not to process the data
            contentType: false, // tell jQuery not to set contentType
            dataType: 'json',
            success: function (result) {
                if(typeof result.message !== 'undefined') {
                    coreWebApp.toastmsg('error', 'File upload', result.message);
                } else if(typeof result.rows_found !== 'undefined') {
                    coreWebApp.toastmsg('message', 'File upload', result.rows_found+' rows populated with revised balance from file' );
                }
                process_result(result);
                $('#cmdupdate').removeAttr('disabled');
            }
        });
    }
    bu_util.file_upload = file_upload;

} (window.core_st.bu_util));


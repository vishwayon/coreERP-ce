window.gstn_req = {};

(function (gstn_req) {

    function gstn_req_otp_click() {
        $('#cmd_reqotp').hide();
        $('#div_res_reqotp').show();
        $('#res_reqotp').html('Requesting OTP. Please wait ... ');
        var fd = $('#reqotp').serialize();
        $.ajax({
            url: '?r=core/tx/gst-return/gstn-req-otp',
            type: "POST",
            data: fd,
            dataType: 'json',
            success: function (result) {
                if (result.status == 'success') {
                    $('#div_res_reqotp').hide();
                    $('#gstn_txn').val(result.txn);
                    $('#gstn_ip').val(result.ip);
                    $('#gstn_username').val($('#username').val());
                    $('#gstn_statecd').val($('#statecd').val());
                    $('#username').attr('readonly', true);
                    $('#statecd').attr('readonly', true);
                    $('#cmd_reqotp').hide();
                    $('#rptrow2').show();
                } else {
                    $('#res_reqotp').html('Request failed with error : ' + result.desc);
                    $('#div_res_reqotp').show();
                }
            },
            error: function (data) {
                $('#cmd_reqotp').show();
                $('#res_reqotp').html('Request failed.');
                $('#div_res_reqotp').show();
            }
        });
    }
    gstn_req.gstn_req_otp_click = gstn_req_otp_click;

    function gstn_req_token_click() {
        $('#cmd_reqtoken').hide();
        $('#div_res_reqtoken').show();
        $('#res_reqtoken').html('Authenticating token. Please wait ... ');
        var fd = $('#reqtoken').serialize();
        $.ajax({
            url: '?r=core/tx/gst-return/gstn-auth-token',
            type: "POST",
            data: fd,
            dataType: 'json',
            success: function (result) {
                if (result.status == 'success') {
                    $('#cmd_reqtoken').hide();
                    $('#gstn_otp').attr('readonly', true);
                    $('#res_reqtoken').html('Authentication successful.').css('color', 'darkgreen');
                    $('#div_res_reqtoken').show();
                } else {
                    $('#res_reqtoken').html('Request failed with error : ' + result.desc);
                    $('#div_res_reqtoken').show();
                }
            },
            error: function (data) {
                $('#cmd_reqtoken').show();
                $('#res_reqtoken').html('Request failed.');
                $('#div_res_reqtoken').show();
            }
        });
    }
    gstn_req.gstn_req_token_click = gstn_req_token_click;
    
    function gstn_refresh_token_click() {
        $('#cmd_refreshtoken').hide();
        $('#div_res_refreshtoken').show();
        $('#res_refreshtoken').html('Refreshing token. Please wait ... ');
        var fd = $('#refreshtoken').serialize();
        $.ajax({
            url: '?r=core/tx/gst-return/gstn-refresh-token',
            type: "POST",
            data: fd,
            dataType: 'json',
            success: function (result) {
                if (result.status == 'success') {
                    $('#cmd_refreshtoken').hide();
                    $('#res_refreshtoken').html('Resfresh token successful.').css('color', 'darkgreen');
                    $('#div_res_refreshtoken').show();
                } else {
                    $('#res_refreshtoken').html('Request failed with error : ' + result.desc);
                    $('#div_res_refreshtoken').show();
                }
            },
            error: function (data) {
                $('#cmd_refreshtoken').show();
                $('#res_refreshtoken').html('Request failed.');
                $('#div_res_refreshtoken').show();
            }
        });
    }
    gstn_req.gstn_refresh_token_click = gstn_refresh_token_click;

}(window.gstn_req));
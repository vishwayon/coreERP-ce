var cwfclient = cwfclient || {};
cwfclient.ui = cwfclient.ui || {};
cwfclient.server = cwfclient.server || {};

// ** ui: manages the client ui for all rendering activities
cwfclient.ui.startloading = function() {
    $("#overlay").css("display", "block");
    $(function() {
        var docHeight = $(document).height();
        $("body").append("<div id='overlay2'><span class='Centerer'></span>"+
                 "<img class='Centered' src='../cwf/vsla/assets/loading.gif' alt='Loading....' /></div>");
        $("#overlay2").height(docHeight);
     });
};

cwfclient.ui.stoploading = function() {
    $("#overlay2").remove();
};

cwfclient.ui.toastMsg = function toastMsg(ttype, ttitle, tmsg, tforcestop){
    toastr.options = {
                        "closeButton": false,
                        "debug": false,
                        "newestOnTop": false,
                        "progressBar": false,
                        "positionClass": "toast-top-center",
                        "preventDuplicates": false,
                        "onclick": null,
                        "showDuration": "100",
                        "hideDuration": "1000",
                        "timeOut":  tforcestop ? "0" : "1000",
                        "extendedTimeOut": "0",
                        "showEasing": "swing",
                        "hideEasing": "linear",
                        "showMethod": "fadeIn",
                        "hideMethod": "fadeOut"
                        //,"tapToDismiss": false
                      };
    $('#toastrOptions').text('Command: toastr["'
            + ttype
            + '"]("'
            + tmsg
            + (ttitle ? '", "' + ttitle : '')
            + '")\n\ntoastr.options = '
            + JSON.stringify(toastr.options, null, 2)
            );
    var $toast = toastr[ttype](tmsg, ttitle);
    $toastlast = $toast;
    if(typeof $toast === 'undefined'){
        return;
    }
};

cwfclient.ui.menuClick = function(route){
    var sessionid = $('#sessionid').val();
    $.ajax({
        url: route,
        type: 'GET',
        data:{ reqtime: Date.now(), sessionid: sessionid},
        beforeSend:function(){cwfclient.ui.startloading();},
        complete:function(){cwfclient.ui.stoploading();},
        success: function (resultdata) {
            $('#content-root').html(resultdata);
            if($('#bo-form').length!==0){
                coreWebApp.GetModel('#bo-form');
                var htcontents=$('#content-root').height();
                $('#cboformbody').height(htcontents-80);
            } else if ($('#wiz-form').length!==0){
                coreWebApp.applysmartcontrols($('#wiz-form'));
                $('#details').height($('#wiz-form').height()+60);
            } else if ($('#custom-form').length!==0){
                $('#custom-form').height($('#content-root').height()-60);
                $('#details').height($('#custom-form').height());
                if($('#dataTables_scrollBody').length!==0){
                    $('#dataTables_scrollBody').height($('#custom-form').height()-65);
                    $('#dataTables_scrollBody').width($('#custom-form').width());
                }
            }else if ($('#rptOptions').length!==0){
                coreWebApp.applysmartcontrols($('#rptOptions'));
            }else if ($('.tree').length!==0){
                coreWebApp.applysmartcontrols($('#thelist'));
            }
            if($('#cDashboard').length!==0 && $('#cDashboard').is(':visible')){
                coreWebApp.makechart(mydiv);
            }
        },
        error: function (data) {
            cwfclient.ui.toastMsg('error','Server Error', data.responseText, true);
            cwfclient.ui.stoploading();
        }
    });
};
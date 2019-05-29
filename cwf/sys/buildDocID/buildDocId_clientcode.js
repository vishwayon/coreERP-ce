typeof window.core_sys === 'undefined' ? window.core_sys = {} : '';
window.core_sys.build_docid = {};

(function (build_docid) {
    function submitChange() {
        var formdata = $('#build-docid-form').serialize();
        $.ajax({
            url: '?r=cwf/sys/main/build-doc-id-update',
            type: 'POST',
            dataType: 'json',
            data: formdata,
            beforeSend: function () {
                $('#brokenrules').html('');
                coreWebApp.startloading();
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = resultdata;
                if (jsonResult['status'] === 'OK') {
                    coreWebApp.toastmsg('info', 'Change Status', 'Doc Build sql successfully changed', false);
                    return;
                } else {
                    coreWebApp.toastmsg('warning', 'Change Status', 'Failed to change. Review errors and fix', false);
                    var brules = jsonResult['errors'];
                    var litems = '<strong>Broken Rules</strong><div style="margin-top:5px;">';
                    for (var i = 0; i < brules.length; i++) {
                        litems += "<li>" + brules[i] + "</li>";
                    }
                    litems += '</div>';
                    $('#brokenrules').append(litems);
                    $('#divbrule').show();
                }
            }
        });
    }
    build_docid.submitChange = submitChange;
}(window.core_sys.build_docid));






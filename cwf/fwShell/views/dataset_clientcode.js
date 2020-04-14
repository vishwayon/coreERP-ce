window.cwf_dataset = {};

(function (cwf_dataset) {
    cwf_dataset.rptInfo;
    cwf_dataset.afterRefreshEvent = new Function();

    function downloadClick() {
        var url = $('#rptOptions').attr('action') + '/get-dataset';
        var data = $('#rptOptions').serialize();
        data = data.replace(/=on/g, '=1');
        data = data.replace(/=True/g, '=1');
        $('#rptOptions input[type=checkbox]:not(:checked)').each(
                function () {
                    data += '&' + this.name + '=0';
                });
        $('#rptRoot').show();
        $('#rptRoot').html('');
        var contentHeight = $('#content-root').height();

        var afterEventHandler = $('#afterRefreshEventHandler').attr('value');
        if (afterEventHandler != '' && afterEventHandler != null) {
            cwf_dataset.afterRefreshEvent = new Function("page", '{ window.' + afterEventHandler + '(page); }');
        } else {
            cwf_dataset.afterRefreshEvent = new Function();
        }

        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: data,
            beforeSend: function () {
                coreWebApp.startloading();
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata, status, jqXHR) {
                debugger;
                if(resultdata.status == 'OK') {
                    // Always download the file
                    var link = document.createElement('a');
                    link.setAttribute("href", resultdata.filePath);
                    link.setAttribute("id", "data_file_link");
                    link.setAttribute("download", resultdata.fileName);
                    var cnt = document.getElementById('content-root');
                    cnt.appendChild(link);
                    link.click();
                } else {
                    alert('Download Failed. Contact support');
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Status', 'Failed to fetch data', false);
                $('#rptRoot').html(data.responseText);
            }
        });
        adjustHeight();
        return false;
    }
    cwf_dataset.downloadClick = downloadClick;

    function adjustHeight() {
        r1 = parseInt($('#rptrow1').height());
        r2 = 0;
        cntht = parseInt($('#content-root').height());
        $('#rptParent').height(cntht - r1 - r2 - 25);
    }
    cwf_dataset.adjustHeight = adjustHeight;

    function bindModel() {
        var rawdata = $('#modelData').val();
        if (rawdata != '' && rawdata != null) {
            var data = $.parseJSON(rawdata);
            cwf_dataset.Model = ko.mapping.fromJS(data);
            ko.applyBindings(cwf_dataset.Model, $('#rptOptions')[0]);
            refreshClick();
        } else {
            cwf_dataset.applySmartControls();
        }
    }
    cwf_dataset.bindModel = bindModel;

    function applySmartControls() {
        $('#rptOptions').find('input').each(function () {
            if ($(this).hasClass('smartcombo')) {
                coreWebApp.applySmartCombo(this);
            } else if ($(this).hasClass('multiselect')) {
                coreWebApp.applySmartMultiCombo(this);
            } else if ($(this).hasClass('datetime')) {
                coreWebApp.applyDatepicker(this);
            } else if ($(this).attr('type') == 'decimal') {
                coreWebApp.applyNumber(this);
            }
        });
    }
    cwf_dataset.applySmartControls = applySmartControls;

}(window.cwf_dataset));



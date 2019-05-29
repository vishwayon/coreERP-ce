window.tx_vu = {};

(function (tx_vu) {
    
    function generate_click() {
        $.ajax({
           url: '?r=core/tx/vat-upload/generate-data',
           type: 'POST',
           data: $('#rptOptions').serialize(),
           dataType: 'json',
           success: function (result) {
               alert(result.fileName);
               $('#fileName').val(result.fileName);
           }
        });
    }
    tx_vu.generate_click = generate_click;
    
    function applySmartControls() {
        $('#rptOptions').find('input').each(function () {
            if ($(this).hasClass('smartcombo')) {
                coreWebApp.applySmartCombo(this);
            } else if ($(this).hasClass('datetime')) {
                coreWebApp.applyDatepicker(this);
            } else if ($(this).attr('type') == 'decimal') {
                coreWebApp.applyNumber(this);
            }
        });
    }
    tx_vu.applySmartControls = applySmartControls;

} (window.tx_vu));



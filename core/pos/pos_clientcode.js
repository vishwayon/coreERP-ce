window.core_pos = {};
(function (core_pos) {
    
    function filter_stock_loc() {
        var branch_id = coreWebApp.ModelBo.branch_id();
        return ' branch_id = ' + branch_id;
    }
    core_pos.filter_stock_loc = filter_stock_loc;
    
    function tday_terminal_allow_select() {
        return coreWebApp.ModelBo.tday_id() === -1;
    }
    core_pos.tday_terminal_allow_select = tday_terminal_allow_select;
    
    function tday_eod_enable() {
        return parseInt(coreWebApp.ModelBo.tday_id()) !== -1;
    }
    core_pos.tday_eod_enable = tday_eod_enable;
    
    function tday_eod_start() {
        $.ajax({
            url: '?r=core/pos/form/eod-start',
            method: 'GET',
            dataType: 'json',
            data: { tsessionid: coreWebApp.ModelBo.tday_session_id() },
            success: function(data) {
                var jsonResult = data;
                coreWebApp.ModelBo.eod_data = ko.mapping.fromJS(jsonResult);
                coreWebApp.ModelBo.show_eod_data(true);
            }
        });
    }
    core_pos.tday_eod_start = tday_eod_start;
    
    function tday_eod_start_handover() {
        // todo: show confirmation message
        $.ajax({
            url: '?r=core/pos/form/eod-start-handover',
            method: 'GET',
            dataType: 'json',
            data: { tsessionid: coreWebApp.ModelBo.tday_session_id() },
            success: function(data) {
                if(data.status == "OK") {
                    coreWebApp.toastmsg('message', 'Txn. Day Status', 'Closed Transaction Day for Handover');
                    coreWebApp.closeDetail(true);
                } else {
                    coreWebApp.toastmsg('message', 'Txn. Day Status', data.msg);
                }
            }
        });
    }
    core_pos.tday_eod_start_handover = tday_eod_start_handover; 
    
    
    
}(window.core_pos));



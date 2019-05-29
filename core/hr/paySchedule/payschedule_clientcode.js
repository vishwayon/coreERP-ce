// Declare core_payschedule Namespace
window.core_payschedule = {};
(function (core_payschedule) {

    function payhead_new_combo_filter(fltr, datacontext) {
        fltr = " payhead_type= '" + datacontext.payhead_type() + "'";
        return fltr;
    }

    core_payschedule.payhead_new_combo_filter = payhead_new_combo_filter;

    function payshcedule_afterload() {
        $('#note1').parent().hide();
        if (coreWebApp.ModelBo.pay_schedule_id() != -1) {
            is_payplan_created();
        }
    }

    core_payschedule.payshcedule_afterload = payshcedule_afterload;

    function is_payplan_created() {
        $.ajax({
            url: '?r=core/hr/form/ispayplancreated',
            type: 'GET',
            data: {'pay_schedule_id': coreWebApp.ModelBo.pay_schedule_id()},
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    if (jsonResult['payplan_created'] == true) {
                        $('#cmdsave').prop("disabled", true);
                        console.log(jsonResult['msg']);
                        $('#note1').parent().show();
                    }
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
        return 'OK';
    }

    core_payschedule.is_payplan_created = is_payplan_created;

    function control_enable(dataItem) {
        if (typeof dataItem.en_pay_type == 'undefined')
            return;
        if (dataItem.en_pay_type() == 0) {
            return true;
        }
        else {
            dataItem.pay_perc(0);
            dataItem.min_pay_amt(0);
            dataItem.pay_on_min_amt(0);
            dataItem.max_pay_amt(0);
            dataItem.pay_on_max_amt(0);
            return false;
        }
    }

    core_payschedule.control_enable = control_enable;

    function amt_enable(dataItem) {
        if (typeof dataItem.en_pay_type == 'undefined')
            return;
        if (dataItem.en_pay_type() == 2) {
            return true;
        }
        else {
            dataItem.amt(0);
            return false;
        }
    }

    core_payschedule.amt_enable = amt_enable;

// Methods to add/edit Emoluments Starts
    function PayScheduleDetailEmoRemove() {
        var rowused = false;
        var cnt = coreWebApp.ModelBo.pay_schedule_detail_emo_tran().length;
        for (var d = cnt; d >= 0; d--) {
            for (var a = 0; a < coreWebApp.ModelBo.pay_schedule_detail_emo_tran().length; a++) {
                var r = coreWebApp.ModelBo.pay_schedule_detail_emo_tran()[a];
                if (r.parent_pay_schedule_details().includes('step:' + coreWebApp.ModelBo.pay_schedule_detail_emo_tran()[d - 1]['step_id']())) {
                    rowused = true;
                    break;
                }
            }
            for (var b = 0; b < coreWebApp.ModelBo.pay_schedule_detail_ded_tran().length; b++) {
                var r = coreWebApp.ModelBo.pay_schedule_detail_ded_tran()[b];
                if (r.parent_pay_schedule_details().includes('step:' + coreWebApp.ModelBo.pay_schedule_detail_emo_tran()[d - 1]['step_id']())) {
                    rowused = true;
                    break;
                }
            }
            for (var b = 0; b < coreWebApp.ModelBo.pay_schedule_detail_cc_tran().length; b++) {
                var r = coreWebApp.ModelBo.pay_schedule_detail_cc_tran()[b];
                if (r.parent_pay_schedule_details().includes('step:' + coreWebApp.ModelBo.pay_schedule_detail_emo_tran()[d - 1]['step_id']())) {
                    rowused = true;
                    break;
                }
            }
            if (!rowused) {
                var rl = coreWebApp.ModelBo.pay_schedule_detail_emo_tran;
                rl.remove(coreWebApp.ModelBo.pay_schedule_detail_emo_tran()[d - 1]);
            }
            else {
                coreWebApp.toastmsg('warning', 'Remove', 'This step is used in other steps. Cannot remove', false)
            }
            break;
        }

        console.log('PayScheduleDetailEmoRemove');
    }

    core_payschedule.PayScheduleDetailEmoRemove = PayScheduleDetailEmoRemove;


    function PayScheduleEmoNew() {
        var parent_pay_sch_temp = ko.observableArray();
        for (var d = 0; d < coreWebApp.ModelBo.pay_schedule_detail_emo_tran().length; d++) {
            var r = coreWebApp.ModelBo.pay_schedule_detail_emo_tran()[d];
            var r1 = {pay_schedule_detail_id: r.pay_schedule_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: false};
            parent_pay_sch_temp.push(r1);
        }
        var rw = {parent_pay_schedule_details: '', payhead_type: 'E', payhead_id: -1, description: '', step_id: -1, en_pay_type: -1, en_round_type: -1, pay_perc: 0, pay_on_perc: 100,
            amt: 0, min_pay_amt: 0, pay_on_min_amt: 0, max_pay_amt: 0, pay_on_max_amt: 0, do_not_display: false, pay_schedule_detail_temp: parent_pay_sch_temp};

        coreWebApp.showAlloc('core/hr', '/paySchedule/PayScheduleNew', 'core_payschedule.pay_schedule_detail_alloc_init', 'core_payschedule.pay_schedule_detail_emo_update', 'core_payschedule.cancelAllocUpdate', rw);
    }

    core_payschedule.PayScheduleEmoNew = PayScheduleEmoNew;

    //function to update pay detail pop up fields to pay_schedule_detail_tran
    function pay_schedule_detail_emo_update(ctr, dataItem) {
        if (dataItem[0]['en_pay_type']() == 0 && dataItem[0]['pay_perc']() == 0) {
            return 'For Percent Of Amount, Percentage cannot be 0.';
        }

        if (dataItem[0]['pay_on_perc']() > 100) {
            return 'Pay On Percentage cannot be greater than 100.';
        }
        if (dataItem[0]['payhead_id']() == -1) {
            return 'Please select Pay Head.';
        }
        if (dataItem[0]['en_pay_type']() == -1) {
            return 'Please select Caluculation Type.';
        }
        if (dataItem[0]['en_round_type']() == -1) {
            return 'Please select Round Of.';
        }
        else {
            var ptd = '';
            for (var d = 0; d < dataItem[0]['pay_schedule_detail_temp']().length; d++) {
                if (dataItem[0]['pay_schedule_detail_temp']()[d]['is_select']()) {
                    if (ptd.length > 0) {
                        ptd += ',';
                    }
                    ptd += 'step:' + dataItem[0]['pay_schedule_detail_temp']()[d]['step_id']();
                }
            }
            if (dataItem[0]['step_id']() == -1) {
                var r = coreWebApp.ModelBo.addNewRow('pay_schedule_detail_emo_tran', coreWebApp.ModelBo);

                r.parent_pay_schedule_details(ptd);
                r.description(dataItem[0]['description']());
                r.payhead_id(dataItem[0]['payhead_id']());
                r.payhead_type(dataItem[0]['payhead_type']());
                r.en_pay_type(dataItem[0]['en_pay_type']());
                r.en_round_type(dataItem[0]['en_round_type']());
                r.pay_perc(dataItem[0]['pay_perc']());
                r.pay_on_perc(dataItem[0]['pay_on_perc']());
                r.min_pay_amt(dataItem[0]['min_pay_amt']());
                r.pay_on_min_amt(dataItem[0]['pay_on_min_amt']());
                r.max_pay_amt(dataItem[0]['max_pay_amt']());
                r.pay_on_max_amt(dataItem[0]['pay_on_max_amt']());
                r.amt(dataItem[0]['amt']());
                r.do_not_display(dataItem[0]['do_not_display']())

                var count = coreWebApp.ModelBo.pay_schedule_detail_emo_tran().length;
                r.step_id(count + 1000);
                coreWebApp.ModelBo.pay_schedule_detail_emo_tran.valueHasMutated();
                return 'OK';
            }
            else {
                for (var d = 0; d < coreWebApp.ModelBo.pay_schedule_detail_emo_tran().length; d++) {
                    var r = coreWebApp.ModelBo.pay_schedule_detail_emo_tran()[d];

                    if (r.step_id() == dataItem[0]['step_id']()) {
                        r.parent_pay_schedule_details(ptd);
                        r.description(dataItem[0]['description']());
                        r.payhead_id(dataItem[0]['payhead_id']());
                        r.payhead_type(dataItem[0]['payhead_type']());
                        r.en_pay_type(dataItem[0]['en_pay_type']());
                        r.en_round_type(dataItem[0]['en_round_type']());
                        r.pay_perc(dataItem[0]['pay_perc']());
                        r.pay_on_perc(dataItem[0]['pay_on_perc']());
                        r.min_pay_amt(dataItem[0]['min_pay_amt']());
                        r.pay_on_min_amt(dataItem[0]['pay_on_min_amt']());
                        r.max_pay_amt(dataItem[0]['max_pay_amt']());
                        r.pay_on_max_amt(dataItem[0]['pay_on_max_amt']());
                        r.amt(dataItem[0]['amt']());
                        r.do_not_display(dataItem[0]['do_not_display']());
                    }
                }
                return 'OK';
            }
        }
    }

    core_payschedule.pay_schedule_detail_emo_update = pay_schedule_detail_emo_update;

    function emo_edit_method(pr, prop, rw) {
        console.log('emo_edit_method');
        if (typeof rw.pay_schedule_detail_temp == 'undefined') {
            rw.pay_schedule_detail_temp = ko.observableArray();
        }
        rw.pay_schedule_detail_temp.removeAll();
        for (var d = 0; d < coreWebApp.ModelBo.pay_schedule_detail_emo_tran().length; d++) {
            var r = coreWebApp.ModelBo.pay_schedule_detail_emo_tran()[d];
            arr = new Array();
            arr = rw.parent_pay_schedule_details().split(",");
            if (r.step_id() < rw.step_id()) {
                var r = coreWebApp.ModelBo.pay_schedule_detail_emo_tran()[d];
                var is_select = false;

                for (var a = 0; a < arr.length; a++) {
                    if (arr[a] === 'step:' + r.step_id()) {
                        is_select = true;
                    }
                }

                var r1 = {pay_schedule_detail_id: r.pay_schedule_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: is_select};
                rw.pay_schedule_detail_temp.push(r1);
            }
        }
        coreWebApp.showAlloc('core/hr', '/paySchedule/PayScheduleNew', 'core_payschedule.pay_schedule_detail_alloc_init', 'core_payschedule.pay_schedule_detail_emo_update', 'core_payschedule.cancelAllocUpdate', rw);
    }

    core_payschedule.emo_edit_method = emo_edit_method;

// Methods to add/edit Emoluments Ends

// Methods to add/edit Deductions Starts
    function PayScheduleDetailDedRemove() {
        var rowused = false;
        var cnt = coreWebApp.ModelBo.pay_schedule_detail_ded_tran().length;
        for (var d = cnt; d >= 0; d--) {
            for (var b = 0; b < coreWebApp.ModelBo.pay_schedule_detail_ded_tran().length; b++) {
                var r = coreWebApp.ModelBo.pay_schedule_detail_ded_tran()[b];
                if (r.parent_pay_schedule_details().includes('step:' + coreWebApp.ModelBo.pay_schedule_detail_ded_tran()[d - 1]['step_id']())) {
                    rowused = true;
                    break;
                }
            }
            for (var b = 0; b < coreWebApp.ModelBo.pay_schedule_detail_cc_tran().length; b++) {
                var r = coreWebApp.ModelBo.pay_schedule_detail_cc_tran()[b];
                if (r.parent_pay_schedule_details().includes('step:' + coreWebApp.ModelBo.pay_schedule_detail_ded_tran()[d - 1]['step_id']())) {
                    rowused = true;
                    break;
                }
            }
            if (!rowused) {
                var rl = coreWebApp.ModelBo.pay_schedule_detail_ded_tran;
                rl.remove(coreWebApp.ModelBo.pay_schedule_detail_ded_tran()[d - 1]);
            }
            else {
                coreWebApp.toastmsg('warning', 'Remove', 'This step is used in other steps. Cannot remove', false)
            }
            break;
        }

        console.log('PayScheduleDetailDedRemove');
    }

    core_payschedule.PayScheduleDetailDedRemove = PayScheduleDetailDedRemove;

    function PayScheduleDedNew() {
        var parent_pay_sch_temp = ko.observableArray();
        for (var d = 0; d < coreWebApp.ModelBo.pay_schedule_detail_emo_tran().length; d++) {
            var r = coreWebApp.ModelBo.pay_schedule_detail_emo_tran()[d];
            var r1 = {pay_schedule_detail_id: r.pay_schedule_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: false};
            parent_pay_sch_temp.push(r1);
        }
        for (var d = 0; d < coreWebApp.ModelBo.pay_schedule_detail_ded_tran().length; d++) {
            var r = coreWebApp.ModelBo.pay_schedule_detail_ded_tran()[d];
            var r1 = {pay_schedule_detail_id: r.pay_schedule_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: false};
            parent_pay_sch_temp.push(r1);
        }
        var rw = {parent_pay_schedule_details: '', payhead_type: 'D', payhead_id: -1, description: '', step_id: -1, en_pay_type: -1, en_round_type: -1, pay_perc: 0, pay_on_perc: 100,
            amt: 0, min_pay_amt: 0, pay_on_min_amt: 0, max_pay_amt: 0, pay_on_max_amt: 0, do_not_display: false, pay_schedule_detail_temp: parent_pay_sch_temp};

        coreWebApp.showAlloc('core/hr', '/paySchedule/PayScheduleNew', 'core_payschedule.pay_schedule_detail_alloc_init', 'core_payschedule.pay_schedule_detail_ded_update', 'core_payschedule.cancelAllocUpdate', rw);
    }

    core_payschedule.PayScheduleDedNew = PayScheduleDedNew;

    //function to update pay detail pop up fields to pay_schedule_detail_tran
    function pay_schedule_detail_ded_update(ctr, dataItem) {
        if (dataItem[0]['en_pay_type']() == 0 && dataItem[0]['pay_perc']() == 0) {
            return 'For Percent Of Amount, Percentage cannot be 0.';
        }

        if (dataItem[0]['pay_on_perc']() > 100) {
            return 'Pay On Percentage cannot be greater than 100.';
        }
        if (dataItem[0]['payhead_id']() == -1) {
            return 'Please select Pay Head.';
        }
        if (dataItem[0]['en_pay_type']() == -1) {
            return 'Please select Caluculation Type.';
        }
        if (dataItem[0]['en_round_type']() == -1) {
            return 'Please select Round Of.';
        }
        else {
            var ptd = '';
            for (var d = 0; d < dataItem[0]['pay_schedule_detail_temp']().length; d++) {
                if (dataItem[0]['pay_schedule_detail_temp']()[d]['is_select']()) {
                    if (ptd.length > 0) {
                        ptd += ',';
                    }
                    ptd += 'step:' + dataItem[0]['pay_schedule_detail_temp']()[d]['step_id']();
                }
            }
            if (dataItem[0]['step_id']() == -1) {
                var r = coreWebApp.ModelBo.addNewRow('pay_schedule_detail_ded_tran', coreWebApp.ModelBo);

                r.parent_pay_schedule_details(ptd);
                r.description(dataItem[0]['description']());
                r.payhead_id(dataItem[0]['payhead_id']());
                r.payhead_type(dataItem[0]['payhead_type']());
                r.en_pay_type(dataItem[0]['en_pay_type']());
                r.en_round_type(dataItem[0]['en_round_type']());
                r.pay_perc(dataItem[0]['pay_perc']());
                r.pay_on_perc(dataItem[0]['pay_on_perc']());
                r.min_pay_amt(dataItem[0]['min_pay_amt']());
                r.pay_on_min_amt(dataItem[0]['pay_on_min_amt']());
                r.max_pay_amt(dataItem[0]['max_pay_amt']());
                r.pay_on_max_amt(dataItem[0]['pay_on_max_amt']());
                r.amt(dataItem[0]['amt']());
                r.do_not_display(dataItem[0]['do_not_display']())

                var count = coreWebApp.ModelBo.pay_schedule_detail_ded_tran().length;
                r.step_id(count + 2000);
                coreWebApp.ModelBo.pay_schedule_detail_ded_tran.valueHasMutated();
                return 'OK';
            }
            else {
                for (var d = 0; d < coreWebApp.ModelBo.pay_schedule_detail_ded_tran().length; d++) {
                    var r = coreWebApp.ModelBo.pay_schedule_detail_ded_tran()[d];

                    if (r.step_id() == dataItem[0]['step_id']()) {
                        r.parent_pay_schedule_details(ptd);
                        r.description(dataItem[0]['description']());
                        r.payhead_id(dataItem[0]['payhead_id']());
                        r.payhead_type(dataItem[0]['payhead_type']());
                        r.en_pay_type(dataItem[0]['en_pay_type']());
                        r.en_round_type(dataItem[0]['en_round_type']());
                        r.pay_perc(dataItem[0]['pay_perc']());
                        r.pay_on_perc(dataItem[0]['pay_on_perc']());
                        r.min_pay_amt(dataItem[0]['min_pay_amt']());
                        r.pay_on_min_amt(dataItem[0]['pay_on_min_amt']());
                        r.max_pay_amt(dataItem[0]['max_pay_amt']());
                        r.pay_on_max_amt(dataItem[0]['pay_on_max_amt']());
                        r.amt(dataItem[0]['amt']());
                        r.do_not_display(dataItem[0]['do_not_display']());
                    }
                }
                return 'OK';
            }
        }
    }

    core_payschedule.pay_schedule_detail_ded_update = pay_schedule_detail_ded_update;

    function ded_edit_method(pr, prop, rw) {
        console.log('ded_edit_method');
        if (typeof rw.pay_schedule_detail_temp == 'undefined') {
            rw.pay_schedule_detail_temp = ko.observableArray();
        }
        rw.pay_schedule_detail_temp.removeAll();
        for (var d = 0; d < coreWebApp.ModelBo.pay_schedule_detail_emo_tran().length; d++) {
            var r = coreWebApp.ModelBo.pay_schedule_detail_emo_tran()[d];
            arr = new Array();
            arr = rw.parent_pay_schedule_details().split(",");
            if (r.step_id() < rw.step_id()) {
                var r = coreWebApp.ModelBo.pay_schedule_detail_emo_tran()[d];
                var is_select = false;

                for (var a = 0; a < arr.length; a++) {
                    if (arr[a] === 'step:' + r.step_id()) {
                        is_select = true;
                    }
                }

                var r1 = {pay_schedule_detail_id: r.pay_schedule_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: is_select};
                rw.pay_schedule_detail_temp.push(r1);
            }
        }
        for (var d = 0; d < coreWebApp.ModelBo.pay_schedule_detail_ded_tran().length; d++) {
            var r = coreWebApp.ModelBo.pay_schedule_detail_ded_tran()[d];
            arr = new Array();
            arr = rw.parent_pay_schedule_details().split(",");
            if (r.step_id() < rw.step_id()) {
                var r = coreWebApp.ModelBo.pay_schedule_detail_ded_tran()[d];
                var is_select = false;

                for (var a = 0; a < arr.length; a++) {
                    if (arr[a] === 'step:' + r.step_id()) {
                        is_select = true;
                    }
                }

                var r1 = {pay_schedule_detail_id: r.pay_schedule_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: is_select};
                rw.pay_schedule_detail_temp.push(r1);
            }
        }
        coreWebApp.showAlloc('core/hr', '/paySchedule/PayScheduleNew', 'core_payschedule.pay_schedule_detail_alloc_init', 'core_payschedule.pay_schedule_detail_ded_update', 'core_payschedule.cancelAllocUpdate', rw);
    }

    core_payschedule.ded_edit_method = ded_edit_method;

// Methods to add/edit Deductions Ends

// Methods to add/edit Company Contribution Starts
    function PayScheduleDetailCcRemove() {
        var rowused = false;
        var cnt = coreWebApp.ModelBo.pay_schedule_detail_cc_tran().length;
        for (var d = cnt; d >= 0; d--) {
            for (var b = 0; b < coreWebApp.ModelBo.pay_schedule_detail_cc_tran().length; b++) {
                var r = coreWebApp.ModelBo.pay_schedule_detail_cc_tran()[b];
                if (r.parent_pay_schedule_details().includes('step:' + coreWebApp.ModelBo.pay_schedule_detail_cc_tran()[d - 1]['step_id']())) {
                    rowused = true;
                    break;
                }
            }
            if (!rowused) {
                var rl = coreWebApp.ModelBo.pay_schedule_detail_cc_tran;
                rl.remove(coreWebApp.ModelBo.pay_schedule_detail_cc_tran()[d - 1]);
            }
            else {
                coreWebApp.toastmsg('warning', 'Remove', 'This step is used in other steps. Cannot remove', false)
            }
            break;
        }

        console.log('PayScheduleDetailCcRemove');
    }

    core_payschedule.PayScheduleDetailCcRemove = PayScheduleDetailCcRemove;

    function PayScheduleCcNew() {
        var parent_pay_sch_temp = ko.observableArray();
        for (var d = 0; d < coreWebApp.ModelBo.pay_schedule_detail_emo_tran().length; d++) {
            var r = coreWebApp.ModelBo.pay_schedule_detail_emo_tran()[d];
            var r1 = {pay_schedule_detail_id: r.pay_schedule_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: false};
            parent_pay_sch_temp.push(r1);
        }
        for (var d = 0; d < coreWebApp.ModelBo.pay_schedule_detail_ded_tran().length; d++) {
            var r = coreWebApp.ModelBo.pay_schedule_detail_ded_tran()[d];
            var r1 = {pay_schedule_detail_id: r.pay_schedule_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: false};
            parent_pay_sch_temp.push(r1);
        }
        for (var d = 0; d < coreWebApp.ModelBo.pay_schedule_detail_cc_tran().length; d++) {
            var r = coreWebApp.ModelBo.pay_schedule_detail_cc_tran()[d];
            var r1 = {pay_schedule_detail_id: r.pay_schedule_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: false};
            parent_pay_sch_temp.push(r1);
        }
        var rw = {parent_pay_schedule_details: '', payhead_type: 'C', payhead_id: -1, description: '', step_id: -1, en_pay_type: -1, en_round_type: -1, pay_perc: 0, pay_on_perc: 100,
            amt: 0, min_pay_amt: 0, pay_on_min_amt: 0, max_pay_amt: 0, pay_on_max_amt: 0, do_not_display: false, pay_schedule_detail_temp: parent_pay_sch_temp};

        coreWebApp.showAlloc('core/hr', '/paySchedule/PayScheduleNew', 'core_payschedule.pay_schedule_detail_alloc_init', 'core_payschedule.pay_schedule_detail_cc_update', 'core_payschedule.cancelAllocUpdate', rw);
    }

    core_payschedule.PayScheduleCcNew = PayScheduleCcNew;

    //function to update pay detail pop up fields to pay_schedule_detail_tran
    function pay_schedule_detail_cc_update(ctr, dataItem) {
        if (dataItem[0]['en_pay_type']() == 0 && dataItem[0]['pay_perc']() == 0) {
            return 'For Percent Of Amount, Percentage cannot be 0.';
        }

        if (dataItem[0]['pay_on_perc']() > 100) {
            return 'Pay On Percentage cannot be greater than 100.';
        }
        if (dataItem[0]['payhead_id']() == -1) {
            return 'Please select Pay Head.';
        }
        if (dataItem[0]['en_pay_type']() == -1) {
            return 'Please select Caluculation Type.';
        }
        if (dataItem[0]['en_round_type']() == -1) {
            return 'Please select Round Of.';
        }
        else {
            var ptd = '';
            for (var d = 0; d < dataItem[0]['pay_schedule_detail_temp']().length; d++) {
                if (dataItem[0]['pay_schedule_detail_temp']()[d]['is_select']()) {
                    if (ptd.length > 0) {
                        ptd += ',';
                    }
                    ptd += 'step:' + dataItem[0]['pay_schedule_detail_temp']()[d]['step_id']();
                }
            }
            if (dataItem[0]['step_id']() == -1) {
                var r = coreWebApp.ModelBo.addNewRow('pay_schedule_detail_cc_tran', coreWebApp.ModelBo);

                r.parent_pay_schedule_details(ptd);
                r.description(dataItem[0]['description']());
                r.payhead_id(dataItem[0]['payhead_id']());
                r.payhead_type(dataItem[0]['payhead_type']());
                r.en_pay_type(dataItem[0]['en_pay_type']());
                r.en_round_type(dataItem[0]['en_round_type']());
                r.pay_perc(dataItem[0]['pay_perc']());
                r.pay_on_perc(dataItem[0]['pay_on_perc']());
                r.min_pay_amt(dataItem[0]['min_pay_amt']());
                r.pay_on_min_amt(dataItem[0]['pay_on_min_amt']());
                r.max_pay_amt(dataItem[0]['max_pay_amt']());
                r.pay_on_max_amt(dataItem[0]['pay_on_max_amt']());
                r.amt(dataItem[0]['amt']());
                r.do_not_display(dataItem[0]['do_not_display']())

                var count = coreWebApp.ModelBo.pay_schedule_detail_cc_tran().length;
                r.step_id(count + 3000);
                coreWebApp.ModelBo.pay_schedule_detail_cc_tran.valueHasMutated();
                return 'OK';
            }
            else {
                for (var d = 0; d < coreWebApp.ModelBo.pay_schedule_detail_cc_tran().length; d++) {
                    var r = coreWebApp.ModelBo.pay_schedule_detail_cc_tran()[d];

                    if (r.step_id() == dataItem[0]['step_id']()) {
                        r.parent_pay_schedule_details(ptd);
                        r.description(dataItem[0]['description']());
                        r.payhead_id(dataItem[0]['payhead_id']());
                        r.payhead_type(dataItem[0]['payhead_type']());
                        r.en_pay_type(dataItem[0]['en_pay_type']());
                        r.en_round_type(dataItem[0]['en_round_type']());
                        r.pay_perc(dataItem[0]['pay_perc']());
                        r.pay_on_perc(dataItem[0]['pay_on_perc']());
                        r.min_pay_amt(dataItem[0]['min_pay_amt']());
                        r.pay_on_min_amt(dataItem[0]['pay_on_min_amt']());
                        r.max_pay_amt(dataItem[0]['max_pay_amt']());
                        r.pay_on_max_amt(dataItem[0]['pay_on_max_amt']());
                        r.amt(dataItem[0]['amt']());
                        r.do_not_display(dataItem[0]['do_not_display']());
                    }
                }
                return 'OK';
            }
        }
    }

    core_payschedule.pay_schedule_detail_cc_update = pay_schedule_detail_cc_update;

    function cc_edit_method(pr, prop, rw) {
        console.log('cc_edit_method');
        if (typeof rw.pay_schedule_detail_temp == 'undefined') {
            rw.pay_schedule_detail_temp = ko.observableArray();
        }
        rw.pay_schedule_detail_temp.removeAll();
        for (var d = 0; d < coreWebApp.ModelBo.pay_schedule_detail_emo_tran().length; d++) {
            var r = coreWebApp.ModelBo.pay_schedule_detail_emo_tran()[d];
            arr = new Array();
            arr = rw.parent_pay_schedule_details().split(",");
            if (r.step_id() < rw.step_id()) {
                var r = coreWebApp.ModelBo.pay_schedule_detail_emo_tran()[d];
                var is_select = false;

                for (var a = 0; a < arr.length; a++) {
                    if (arr[a] === 'step:' + r.step_id()) {
                        is_select = true;
                    }
                }

                var r1 = {pay_schedule_detail_id: r.pay_schedule_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: is_select};
                rw.pay_schedule_detail_temp.push(r1);
            }
        }
        for (var d = 0; d < coreWebApp.ModelBo.pay_schedule_detail_ded_tran().length; d++) {
            var r = coreWebApp.ModelBo.pay_schedule_detail_ded_tran()[d];
            arr = new Array();
            arr = rw.parent_pay_schedule_details().split(",");
            if (r.step_id() < rw.step_id()) {
                var r = coreWebApp.ModelBo.pay_schedule_detail_ded_tran()[d];
                var is_select = false;

                for (var a = 0; a < arr.length; a++) {
                    if (arr[a] === 'step:' + r.step_id()) {
                        is_select = true;
                    }
                }

                var r1 = {pay_schedule_detail_id: r.pay_schedule_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: is_select};
                rw.pay_schedule_detail_temp.push(r1);
            }
        }
        for (var d = 0; d < coreWebApp.ModelBo.pay_schedule_detail_cc_tran().length; d++) {
            var r = coreWebApp.ModelBo.pay_schedule_detail_cc_tran()[d];
            arr = new Array();
            arr = rw.parent_pay_schedule_details().split(",");
            if (r.step_id() < rw.step_id()) {
                var r = coreWebApp.ModelBo.pay_schedule_detail_cc_tran()[d];
                var is_select = false;

                for (var a = 0; a < arr.length; a++) {
                    if (arr[a] === 'step:' + r.step_id()) {
                        is_select = true;
                    }
                }

                var r1 = {pay_schedule_detail_id: r.pay_schedule_detail_id(), step_id: r.step_id(), payhead_id: r.payhead_id(), is_select: is_select};
                rw.pay_schedule_detail_temp.push(r1);
            }
        }
        coreWebApp.showAlloc('core/hr', '/paySchedule/PayScheduleNew', 'core_payschedule.pay_schedule_detail_alloc_init', 'core_payschedule.pay_schedule_detail_cc_update', 'core_payschedule.cancelAllocUpdate', rw);
    }

    core_payschedule.cc_edit_method = cc_edit_method;

// Methods to add/edit Company contribution Ends

    // function to set default values for Pay Detail
    function pay_schedule_detail_alloc_init() {
    }

    core_payschedule.pay_schedule_detail_alloc_init = pay_schedule_detail_alloc_init;

    function CopyPaySchedule() {
        coreWebApp.showAlloc('core/hr', '/paySchedule/SelectPayScheduleForCopy', 'core_payschedule.copy_pay_schedule_init', 'core_payschedule.copy_pay_schedule_update', 'core_payschedule.cancelAllocUpdate');
    }
    core_payschedule.CopyPaySchedule = CopyPaySchedule;

    function copy_pay_schedule_init() {
    }

    core_payschedule.copy_pay_schedule_init = copy_pay_schedule_init;

    function cancelAllocUpdate() {
    }
    core_payschedule.cancelAllocUpdate = cancelAllocUpdate;
    
    function get_pay_schedule_detail() {
        if (coreWebApp.ModelBo.pay_schedule_copy_id() == -1 || coreWebApp.ModelBo.pay_schedule_copy_id() == null) {
            return 'Pay Schedule Details', 'Select Pay Schedule Schedule to get details';
        }
        else {
            $.ajax({
                url: '?r=core/hr/form/getpayscheduledetails',
                type: 'GET',
                data: {'pay_schedule_id': coreWebApp.ModelBo.pay_schedule_copy_id()},
                complete: function () {
                    coreWebApp.stoploading();
                },
                success: function (resultdata) {
                    var jsonResult = $.parseJSON(resultdata);
                    if (jsonResult['status'] === 'ok') {
                        //remove all Pay Schedule Details 
                        coreWebApp.ModelBo.detail_temp_for_copy.removeAll();

                        //update Pay Schedule Detail
                        for (var p = 0; p < jsonResult['pay_detail'].length; ++p)
                        {
                            var r = coreWebApp.ModelBo.addNewRow('detail_temp_for_copy', coreWebApp.ModelBo);
                            r.step_id(jsonResult['pay_detail'][p]['step_id']);
                            r.parent_pay_schedule_details(jsonResult['pay_detail'][p]['parent_pay_schedule_details']);
                            r.description(jsonResult['pay_detail'][p]['description']);
                            r.payhead_id(jsonResult['pay_detail'][p]['payhead_id']);
                            r.en_pay_type(jsonResult['pay_detail'][p]['en_pay_type']);
                            r.en_round_type(jsonResult['pay_detail'][p]['en_round_type']);
                            r.pay_perc(jsonResult['pay_detail'][p]['pay_perc']);
                            r.pay_on_perc(jsonResult['pay_detail'][p]['pay_on_perc']);
                            r.min_pay_amt(jsonResult['pay_detail'][p]['min_pay_amt']);
                            r.pay_on_min_amt(jsonResult['pay_detail'][p]['pay_on_min_amt']);
                            r.max_pay_amt(jsonResult['pay_detail'][p]['max_pay_amt']);
                            r.pay_on_max_amt(jsonResult['pay_detail'][p]['pay_on_max_amt']);
                            r.amt(jsonResult['pay_detail'][p]['amt']);
                            r.do_not_display(jsonResult['pay_detail'][p]['do_not_display']);
                            coreWebApp.ModelBo.detail_temp_for_copy.valueHasMutated();
                        }
//                            applysmartcontrols();
                    }
                },
                error: function (data) {
                    coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
                }
            });
            return 'OK';
        }
    }

    core_payschedule.get_pay_schedule_detail = get_pay_schedule_detail;

    //function to update Schedule Detail
    function copy_pay_schedule_update() {
        if (coreWebApp.ModelBo.pay_schedule_copy_id() == -1 || coreWebApp.ModelBo.pay_schedule_copy_id() == null) {
            return 'Pay Schedule Details', 'Select Pay Schedule Schedule to get details';
        }

        $.ajax({
            url: '?r=core/hr/form/getpayscheduledetails',
            type: 'GET',
            data: {'pay_schedule_id': coreWebApp.ModelBo.pay_schedule_copy_id()},
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] === 'ok') {
                    //remove all Pay Schedule Details 
                    coreWebApp.ModelBo.pay_schedule_detail_emo_tran.removeAll();
                    coreWebApp.ModelBo.pay_schedule_detail_ded_tran.removeAll();
                    coreWebApp.ModelBo.pay_schedule_detail_cc_tran.removeAll();

                    //update Pay Schedule Detail
                    for (var p = 0; p < jsonResult['pay_detail'].length; ++p)
                    {
                        if(jsonResult['pay_detail'][p]['payhead_type'] == 'E'){
                            var r = coreWebApp.ModelBo.addNewRow('pay_schedule_detail_emo_tran', coreWebApp.ModelBo);
                            r.step_id(jsonResult['pay_detail'][p]['step_id']);
                            r.parent_pay_schedule_details(jsonResult['pay_detail'][p]['parent_pay_schedule_details']);
                            r.description(jsonResult['pay_detail'][p]['description']);
                            r.payhead_id(jsonResult['pay_detail'][p]['payhead_id']);
                            r.en_pay_type(jsonResult['pay_detail'][p]['en_pay_type']);
                            r.en_round_type(jsonResult['pay_detail'][p]['en_round_type']);
                            r.pay_perc(jsonResult['pay_detail'][p]['pay_perc']);
                            r.pay_on_perc(jsonResult['pay_detail'][p]['pay_on_perc']);
                            r.min_pay_amt(jsonResult['pay_detail'][p]['min_pay_amt']);
                            r.pay_on_min_amt(jsonResult['pay_detail'][p]['pay_on_min_amt']);
                            r.max_pay_amt(jsonResult['pay_detail'][p]['max_pay_amt']);
                            r.pay_on_max_amt(jsonResult['pay_detail'][p]['pay_on_max_amt']);
                            r.amt(jsonResult['pay_detail'][p]['amt']);
                            r.do_not_display(jsonResult['pay_detail'][p]['do_not_display']);
                            r.payhead_type(jsonResult['pay_detail'][p]['payhead_type']); 
                        }
                        if(jsonResult['pay_detail'][p]['payhead_type'] == 'D'){
                            var r = coreWebApp.ModelBo.addNewRow('pay_schedule_detail_ded_tran', coreWebApp.ModelBo);
                            r.step_id(jsonResult['pay_detail'][p]['step_id']);
                            r.parent_pay_schedule_details(jsonResult['pay_detail'][p]['parent_pay_schedule_details']);
                            r.description(jsonResult['pay_detail'][p]['description']);
                            r.payhead_id(jsonResult['pay_detail'][p]['payhead_id']);
                            r.en_pay_type(jsonResult['pay_detail'][p]['en_pay_type']);
                            r.en_round_type(jsonResult['pay_detail'][p]['en_round_type']);
                            r.pay_perc(jsonResult['pay_detail'][p]['pay_perc']);
                            r.pay_on_perc(jsonResult['pay_detail'][p]['pay_on_perc']);
                            r.min_pay_amt(jsonResult['pay_detail'][p]['min_pay_amt']);
                            r.pay_on_min_amt(jsonResult['pay_detail'][p]['pay_on_min_amt']);
                            r.max_pay_amt(jsonResult['pay_detail'][p]['max_pay_amt']);
                            r.pay_on_max_amt(jsonResult['pay_detail'][p]['pay_on_max_amt']);
                            r.amt(jsonResult['pay_detail'][p]['amt']);
                            r.do_not_display(jsonResult['pay_detail'][p]['do_not_display']);
                            r.payhead_type(jsonResult['pay_detail'][p]['payhead_type']); 
                        }
                        if(jsonResult['pay_detail'][p]['payhead_type'] == 'C'){
                            var r = coreWebApp.ModelBo.addNewRow('pay_schedule_detail_cc_tran', coreWebApp.ModelBo);
                            r.step_id(jsonResult['pay_detail'][p]['step_id']);
                            r.parent_pay_schedule_details(jsonResult['pay_detail'][p]['parent_pay_schedule_details']);
                            r.description(jsonResult['pay_detail'][p]['description']);
                            r.payhead_id(jsonResult['pay_detail'][p]['payhead_id']);
                            r.en_pay_type(jsonResult['pay_detail'][p]['en_pay_type']);
                            r.en_round_type(jsonResult['pay_detail'][p]['en_round_type']);
                            r.pay_perc(jsonResult['pay_detail'][p]['pay_perc']);
                            r.pay_on_perc(jsonResult['pay_detail'][p]['pay_on_perc']);
                            r.min_pay_amt(jsonResult['pay_detail'][p]['min_pay_amt']);
                            r.pay_on_min_amt(jsonResult['pay_detail'][p]['pay_on_min_amt']);
                            r.max_pay_amt(jsonResult['pay_detail'][p]['max_pay_amt']);
                            r.pay_on_max_amt(jsonResult['pay_detail'][p]['pay_on_max_amt']);
                            r.amt(jsonResult['pay_detail'][p]['amt']);
                            r.do_not_display(jsonResult['pay_detail'][p]['do_not_display']);
                            r.payhead_type(jsonResult['pay_detail'][p]['payhead_type']); 
                        }
                    }

                    if (jsonResult['pay_detail'].length > 0) {
                        coreWebApp.ModelBo.ot_rate(jsonResult['pay_detail'][0]['ot_rate']);
                        coreWebApp.ModelBo.ot_holiday_rate(jsonResult['pay_detail'][0]['ot_holiday_rate']);
                        coreWebApp.ModelBo.ot_special_rate(jsonResult['pay_detail'][0]['ot_special_rate']);
                    }
//                    applysmartcontrols();
                    coreWebApp.ModelBo.pay_schedule_detail_emo_tran.valueHasMutated();
                    coreWebApp.ModelBo.pay_schedule_detail_ded_tran.valueHasMutated();
                    coreWebApp.ModelBo.pay_schedule_detail_cc_tran.valueHasMutated();
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Filter', 'Failed with errors on server', false);
            }
        });
        return 'OK';
    }

    core_payschedule.copy_pay_schedule_update = copy_pay_schedule_update;

    function cancelAllocUpdate() {
    }
    core_payschedule.cancelAllocUpdate = cancelAllocUpdate;


}(window.core_payschedule));

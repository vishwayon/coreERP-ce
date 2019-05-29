/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

window.core_attendance = {};

(function (core_attendance) {  
    
    function calculate_overtime(dataItem) {
        console.log('calculate_overtime');
        $.ajax({
            url: '?r=core%2Fhr%2Fform%2Fcalculateovertime',
            type: 'GET',
            data: {'attendancedate': coreWebApp.ModelBo.attendance_date,'inhrs' : coreWebApp.ModelBo.in_hrs,
                     'inmins' : coreWebApp.ModelBo.in_mins,'outhrs' : coreWebApp.ModelBo.out_hrs, 'outmins' : coreWebApp.ModelBo.out_mins},

            complete:function(){coreWebApp.stoploading();},
            success: function (resultdata) {
                console.log('calculate_overtime success');
                var jsonResult = $.parseJSON(resultdata);
                if(jsonResult['status'] === 'ok'){
                    console.log('calculate_overtime success - ok');
                    coreWebApp.ModelBo.overtime(parseFloat(jsonResult['overtime'])); 
                    coreWebApp.ModelBo.ot_special(parseFloat(jsonResult['ot_special']));
                }
//                applysmartcontrols();
            },
            error: function (data) {
                coreWebApp.toastmsg('error','Overtime','Overtime Failed with errors on server',false);
            }
        });
    }    
    
    core_attendance.calculate_overtime = calculate_overtime;
    
}(window.core_attendance));


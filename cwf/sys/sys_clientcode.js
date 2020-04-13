typeof window.core_sys === 'undefined' ? window.core_sys = {} : '';

(function (core_sys) {

    function no_of_prints_post_enable(dataItem) {
        if (dataItem.print_allow_post_option() == 1) {
            return true;
        } else {
            dataItem.no_of_prints_post(0);
            return false;
        }
    }
    core_sys.no_of_prints_post_enable = no_of_prints_post_enable;

    function no_of_prints_unpost_enable(dataItem) {
        if (dataItem.print_allow_unpost_option() == 1) {
            return true;
        } else {
            dataItem.no_of_prints_unpost(0);
            return false;
        }
    }
    core_sys.no_of_prints_unpost_enable = no_of_prints_unpost_enable;

    function branch_code_enable(dataItem) {
        if (coreWebApp.ModelBo.branch_id() == -1) {
            return true;
        } else {
            return false;
        }
    }
    core_sys.branch_code_enable = branch_code_enable;

    function sys_afterload() {
        $('#cmdclose').hide();
    }
    core_sys.sys_afterload = sys_afterload;

    function fy_enable_visible(dataItem) {
        if (coreWebApp.ModelBo.finyear_id() == -1) {
            return true;
        } else {
            return false;
        }
    }
    core_sys.fy_enable_visible = fy_enable_visible;

    function get_branch_access_levels() {
        if (coreWebApp.ModelBo.branch_id() === null || coreWebApp.ModelBo.role_id() === null) {
            coreWebApp.toastmsg('error', 'Get Access Levels', 'Select Branch to proceed', true);
            return;
        }
        coreWebApp.ModelBo.menu_items(coreWebApp.ModelBo.menuItems[coreWebApp.ModelBo.branch_id()]());
        $('#divtree').tree({
            onCheck:
                    {
                        ancestors: 'check',
                        descendants: 'check'
                    },
            onUncheck: {
                ancestors: 'uncheck',
                descendants: 'uncheck'
            },
            dnd: false
        });
        $('#details').height($('#bo-form').height() + 60);
    }
    core_sys.get_branch_access_levels = get_branch_access_levels;

    function get_access_levels_old() {
        if (coreWebApp.ModelBo.role_id() === null) {
            coreWebApp.toastmsg('error', 'Get Access Levels', 'Error fetching access levels.', true);
            return;
        }
        coreWebApp.ModelBo.menu_items(coreWebApp.ModelBo.menuItems());
        $('#divtree').tree({
            onCheck:
                    {
                        ancestors: 'check',
                        descendants: 'check'
                    },
            onUncheck: {
                ancestors: 'uncheck',
                descendants: 'uncheck'
            },
            dnd: false
        });
        $('#details').height($('#bo-form').height() + 60);
    }
    core_sys.get_access_levels_old = get_access_levels_old;

    function get_access_levels() {
        coreWebApp.ModelBo.menu_items(coreWebApp.ModelBo.menuItems());
        $('#divtree').tree({
            onCheck:
                    {
                        ancestors: 'check',
                        descendants: 'check'
                    },
            onUncheck: {
                ancestors: 'uncheck',
                descendants: 'uncheck'
            },
            dnd: false
        });
    }
    core_sys.get_access_levels = get_access_levels;

    function check_menuitem(menuitem) {
        if (menuitem.selected()) {
            menuitem.access_level(menuitem.access_levels()[1].val());
        } else {
            menuitem.access_level(menuitem.access_levels()[0].val());
        }
        return true;
    }
    core_sys.check_menuitem = check_menuitem;

    function check_all() {
        var selall = $('#checkall').is(':checked');
        if (selall) {
            $('#divtree').find('[id$=-chk]').filter(':visible:not(:checked)').click();
        } else {
            $('#divtree').find('[id$=-chk]').filter(':visible').click();
        }
    }
    core_sys.check_all = check_all;

    function sys_enable_feedback_close(dataItem) {
        if (dataItem.is_closed()) {
            return true;
        } else {
            return false;
        }
    }
    core_sys.sys_enable_feedback_close = sys_enable_feedback_close;

    function check_useraccess(menuitem) {
        var tn = menuitem.menu_name() + '-opts';
        //$('#'+tn).toggle();        
        return true;
    }
    core_sys.check_useraccess = check_useraccess;

    function get_branch_user_access_level() {
        if (coreWebApp.ModelBo.branch_id() === null || coreWebApp.ModelBo.user_id() === null) {
            coreWebApp.toastmsg('error', 'Get Access Levels', 'Select Branch to proceed', false);
            return;
        }
        coreWebApp.ModelBo.menu_items(coreWebApp.ModelBo.menuItems[coreWebApp.ModelBo.branch_id()]());
        $('#divtree').tree({
            onCheck:
                    {
                        ancestors: 'check',
                        descendants: 'check'
                    },
            onUncheck: {
                ancestors: 'uncheck',
                descendants: 'uncheck'
            },
            dnd: false
        });
        $('#details').height($('#bo-form').height() + 60);
//        $.ajax({
//            url: '?r=cwf%2FfwShell%2Fmain%2Fuseraccess',
//            type: 'GET',
//            data: {'user_id': coreWebApp.ModelBo.user_id(), 'branch_id': coreWebApp.ModelBo.branch_id()},
//            success: function (resultdata) {   
//                coreWebApp.ModelBo.menuItems[coreWebApp.ModelBo.branch_id()]=JSON.parse(resultdata);
//                coreWebApp.ModelBo.menu_items(coreWebApp.ModelBo.menuItems[coreWebApp.ModelBo.branch_id()]);
//                $('#divtree').tree({
//                 onCheck: 
//                     {
//                         ancestors: 'check',
//                         descendants: 'check'
//                     },
//                 onUncheck: {
//                         ancestors: 'uncheck',
//                         descendants: 'uncheck'
//                     },
//                 dnd: false
//                 });
//                 $('#details').height($('#bo-form').height()+60);
//            },
//            error: function (data) {
//                coreWebApp.toastmsg('error','Get Access Levels','Failed with errors on server',false);
//            }
//        });
    }
    core_sys.get_branch_user_access_level = get_branch_user_access_level;

    function selectRoleUser() {
        if (typeof coreWebApp.ModelBo.selected_user() == 'undefined')
            return;
//        ko.utils.arrayForEach(coreWebApp.ModelBo.selected_user(), function(e){
//            e.selected(true);
//        });
        coreWebApp.ModelBo.selected_user().selected(true);
    }
    core_sys.selectRoleUser = selectRoleUser;

    function removeRoleUser() {
        if (typeof coreWebApp.ModelBo.remove_user() == 'undefined')
            return;
//         ko.utils.arrayForEach(coreWebApp.ModelBo.remove_user(), function(e){
//            e.selected(false);
//        });
        coreWebApp.ModelBo.remove_user().selected(false);
    }
    core_sys.removeRoleUser = removeRoleUser;

    function handlestages(dataitem, ele) {
        if (dataitem.selected() && dataitem.access_level() === 2 && dataitem.is_staged()) {
            $(ele).parent().css('margin-bottom', '20px');
            return true;
        } else {
            $(ele).parent().css('margin-bottom', '0');
            return false;
        }
    }
    core_sys.handlestages = handlestages;

    // opts structure {
    //      supp_id: Supplier id
    // } 
    // modifies opts to return
    // result object {
    //      addr: Contains the selected address
    //      gst_state_id: Contains the gst-state
    //      gstin: Contains the GSTIN
    //  }
    function get_branch_address(opts) {
        $.ajax({
            url: '?r=cwf/sys/form/fetch-branch-addr',
            type: 'GET',
            dataType: 'json',
            data: {
                branch_id: opts.branch_id
            },
            success: function (result) {
                if (typeof result.gst_state_id != 'undefined') {
                    opts.result = new function () {};
                    opts.result.addr = result.addr;
                    opts.result.gst_state_id = result.gst_state_id;
                    opts.result.gst_state = result.gst_state;
                    opts.result.gstin = result.gstin;
                }
                if (typeof opts.after_update != 'undefined') {
                    opts.after_update(opts);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Branch Address', 'Failed with errors on server', false);
            }
        });
    }
    core_sys.get_branch_address = get_branch_address;

    // opts structure {
    //      supp_id: Supplier id
    // } 
    // modifies opts to return
    // result object {
    //      addr: Contains the selected address
    //      gst_state_id: Contains the gst-state
    //      gstin: Contains the GSTIN
    //  }
    function get_branch_jw_address(opts) {
        $.ajax({
            url: '?r=cwf/sys/form/fetch-branch-jw-addr',
            type: 'GET',
            dataType: 'json',
            data: {
                branch_id: opts.branch_id
            },
            success: function (result) {
                if (typeof result.gst_state_id != 'undefined') {
                    opts.result = new function () {};
                    opts.result.addr = result.addr;
                    opts.result.gst_state_id = result.gst_state_id;
                    opts.result.gst_state = result.gst_state;
                    opts.result.gstin = result.gstin;
                }
                if (typeof opts.after_update != 'undefined') {
                    opts.after_update(opts);
                }
            },
            error: function (data) {
                coreWebApp.toastmsg('error', 'Branch Address (JW)', 'Failed with errors on server', false);
            }
        });
    }
    core_sys.get_branch_jw_address = get_branch_jw_address;
    
    function en_otp_req_type_enable() {
        return coreWebApp.ModelBo.user_attr.otp_req();
    }
    core_sys.en_otp_req_type_enable = en_otp_req_type_enable;

    function fm_enable_sel(dataItem) {
        return coreWebApp.ModelBo.month_close() == false;
    }
    core_sys.fm_enable_sel = fm_enable_sel;
    
    function get_role_user_branch() {
        if (parseInt(coreWebApp.ModelBo.role_id()) !== -1) {
            $('#get_user_branch').text('Checking ...');
            $('#get_user_branch').attr('disabled', true);
            $.ajax({
                url: '?r=cwf/fwShell/main/get-role-user-branch',
                type: 'GET',
                dataType: 'json',
                data: {role_id: coreWebApp.ModelBo.role_id()},
                success: function (json_result) {
                    var msg = '<div><table class="table"><tbody>';
                    if (json_result.status === 'OK') {
                        msg += '<tr style="border:none;"><td class="col-md-5" style="text-align:center;font-weight: bold;border: none;">User</td><td class="col-md-5" style="text-align:center;font-weight: bold;border: none;">Branch</td></tr>';
                        msg += '</tbody></table><table id="tbl_rbu" class="table table-condensed"><tbody>';
                        var cinfo = json_result.rub_list;
                        var prev_user = '';
                        $.each(cinfo, function (i, v) {
                            var c_user = (v.full_user_name === prev_user ? '' : v.full_user_name);
                            prev_user = v.full_user_name;
                            msg += '<tr><td class="col-md-5">' + c_user + '</td><td class="col-md-5">' + v.branch_name + '</td></tr>';
                        });
                    } else {
                        msg += 'Role not used';
                    }
                    msg += '</tbody></table></div>';

                    var dialog = BootstrapDialog.show({
                        title: 'Role usage',
                        message: $(msg),
                        buttons: [{
                                label: 'OK',
                                action: function (di) {
                                    di.close();
                                }
                            }]
                    });
                },
                error: function () {
                    coreWebApp.toastmsg('warning', 'Role usage', 'Unable to connect to server!');
                },
                complete: function () {
                    $('#get_user_branch').text('Role Use info');
                    $('#get_user_branch').attr('disabled', false);
                }
            });
        } else {
            coreWebApp.toastmsg('message', 'Role usage details', 'Role not saved yet !');
        }
    }
    core_sys.get_role_user_branch = get_role_user_branch;
    
}(window.core_sys));

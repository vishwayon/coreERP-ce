typeof window.core_sys === 'undefined' ? window.core_sys = {} : '';
window.core_sys.user_access_rights = {};

(function (user_access_rights) {
    
    function menu_filter(fltr, dataItem) {
        var mtype = parseInt($('#pmenu_type').val());
        if (mtype == -1 || mtype == -2) {
            // No filter required. Display everything
            fltr = '';
        } else {
            // Always include 0 for parent menus and all
            fltr = '(menu_type In (0, ' + mtype + '))';
        }
        return fltr;
    }
    user_access_rights.menu_filter = menu_filter;
    
    function rpt_role_user_filter(fltr, dataItem) {
        if (parseInt($('#puser_id').val()) !== -1 && parseInt($('#pbranch_id').val()) !== -1) {
            fltr = ' role_id in (select 0 as role_id union all select distinct role_id from sys.user_branch_role where ( user_id = ' + $('#puser_id').val() + ' or '  + $('#puser_id').val() + ' = -99 ) and (branch_id = ' + $('#pbranch_id').val() + ' or ' + $('#pbranch_id').val() + '=0 ))';
        }
        return fltr;
    }
    user_access_rights.rpt_role_user_filter = rpt_role_user_filter;
    
    function generate_click() {
        $.ajax({
            url: '?r=cwf/sys/form/get-user-access-rights-data',
            type: 'POST',
            data: $('#rptOptions').serialize(),
            dataType: 'json',
            beforeSend: function () {
                coreWebApp.startloading();
            },
            complete: function () {
                coreWebApp.stoploading();
            },
            success: function (rawdata) {
                if ($.fn.dataTable.isDataTable('#thelist')) {
                    var t = $('#thelist').DataTable();
                    t.destroy(true);
                }
                var p = $('#collectiondata');
                p.show();
                p.append('<table id="thelist" class="row-border hover"></table>');

                $('#contents').height($('#content-root').height() * 0.965);
                var tbl = $('#thelist').DataTable({
                    data: rawdata.data,
                    columns: rawdata.columns,
                    deferRender: true,
                    scrollY: coreWebApp.getscrollheight() + 'px',
                    scrollCollapse: true,
                    scroller: true,
                    scrollX:'auto'
                });
                $('.dataTables_scrollBody').height(coreWebApp.getscrollheight());
                $('.dataTables_scrollBody').css('background', 'white');
                var l = $('#thelist_length');
                if (l !== 'undefined') {
                    l.hide();
                }
                $('.dataTables_empty').text('No data to display');

                // Add event listener for opening and closing details
                $('#thelist tbody').on('click', 'td.details-control', function () {
                    var tr = $(this).closest('tr');
                    var row = tbl.row(tr);

                    if (row.child.isShown()) {
                        row.child.hide();
                        tr.removeClass('shown');
                    } else {
                        thelistdetail(row, tr);
                    }
                });
            }
        });
    }
    user_access_rights.generate_click = generate_click;
    
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
    user_access_rights.applySmartControls = applySmartControls;
    
   
} (window.core_sys.user_access_rights));



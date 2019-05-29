window.ac_utilities_pdc = {};

(function (ac_utilities_pdc) {
    
    function generate_click() {
        $.ajax({
            url: '?r=core/ac/form/get-pdc-data',
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
    ac_utilities_pdc.generate_click = generate_click;
    
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
    ac_utilities_pdc.applySmartControls = applySmartControls;

} (window.ac_utilities_pdc));



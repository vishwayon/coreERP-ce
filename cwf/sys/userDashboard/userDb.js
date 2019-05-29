typeof window.user_db == 'undefined' ? window.user_db = {} : null;
window.user_db.udocs = {};

(function (udocs) {
    function userdb_pageload() {
        var croot = $('#content-root');
        croot.find('.auto-height-content-root').each(function (i, el) {
            var div = $(el);
            div.css({
                "height": (croot.outerHeight() - (div.offset().top - croot.offset().top)) + "px"
            });
        });
        $('.auto-height-parent').each(function (i, el) {
            var div = $(el);
            var pdiv = div.parent();
            if (typeof pdiv !== 'undefined') {
                div.css({
                    "height": (pdiv.outerHeight() - (div.offset().top - pdiv.offset().top + (div.outerHeight(true) - div.outerHeight()))) + "px"
                });
            }
        });
        udocs.getAprData();
        udocs.getDocData();
    }
    udocs.userdb_pageload = userdb_pageload;

    function getAprData() {
        ko.cleanNode($('#div_apr_data')[0]);
        $.ajax({
            url: '?r=/cwf/sys/user-db/get-aprs',
            type: 'GET',
            success: function (result) {
                var jdata = $.parseJSON(result);
                if (jdata.apr_data.length > 0) {
                    if ($.fn.dataTable.isDataTable('#tbl_apr_data')) {
                        var t = $('#tbl_apr_data').DataTable();
                        t.destroy();
                    }
                    tl = $('#tbl_apr_data').DataTable();
                    udocs.AprModel = ko.mapping.fromJS(jdata);
                    ko.cleanNode($('#div_apr_data')[0]);
                    ko.applyBindings(udocs.AprModel, $('#div_apr_data')[0]);
                    var data_ht = $('#div_apr_data').height();
                    if (data_ht < 150) {
                        $('#div_apr_data').height('150px');
                    }
                }
            }
        });
    }
    udocs.getAprData = getAprData;

    function getDocData() {
        ko.cleanNode($('#div_doc_data')[0]);
        $.ajax({
            url: '?r=/cwf/sys/user-db/get-docs',
            type: 'GET',
            success: function (result) {
                var jdata = $.parseJSON(result);
                if ($.fn.dataTable.isDataTable('#tbl_doc_data')) {
                    var t = $('#tbl_doc_data').DataTable();
                    t.destroy();
                }
                tl = $('#tbl_doc_data').DataTable();
                udocs.DocModel = ko.mapping.fromJS(jdata);
                ko.cleanNode($('#div_doc_data')[0]);
                ko.applyBindings(udocs.DocModel, $('#div_doc_data')[0]);
                var data_ht = $('#div_doc_data').height();
                if (data_ht < 200) {
                    $('#div_doc_data').height('250px');
                }
            }
        });
    }
    udocs.getDocData = getDocData;

    function calTS() {
        $('[data-bind="dateValue: doc_date"]').each(function () {
            var temp = wf_pending.GetTimestamp(this);
            $(this).attr('data-order', temp);
        });
    }
    udocs.CalTS = calTS;

}(window.user_db.udocs));


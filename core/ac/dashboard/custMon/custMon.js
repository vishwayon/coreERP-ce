typeof window.ac_db == 'undefined' ? window.ac_db = {} : null;
window.ac_db.custMon = {};

(function (custMon) {
    function overview_pageload() {
        // Fix ui size
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

        // Get Turnover data
        $.ajax({
            url: '?r=/core/ac/custMon/tov-data',
            type: 'GET',
            success: function (result) {
                var jdata = $.parseJSON(result);
                var dataSeries = [];
                for (i = 0; i < jdata.tov_by_month.length; i++) {
                    var item = jdata.tov_by_month[i];
                    dataSeries.push([item.month_name, item.tov_amt]);
                }

                $.plot(
                        '#div_tov_ph',
                        [dataSeries],
                        {
                            series: {
                                bars: {
                                    show: true,
                                    barWidth: 0.6,
                                    align: "center",
                                    lineWidth: .5,
                                    fill: true,
                                    fillColor: {colors: [{opacity: 0.8}, {opacity: 0.1}]}
                                }
                            },
                            xaxis: {
                                mode: "categories",
                                tickLength: 0
                            },
                            yaxis: {
                                tickFormatter: function (v) {
                                    return coreWebApp.formatNumber(v, 0);
                                }
                            },
                            grid: {
                                borderWidth: {top: 0, right: 0, bottom: 1, left: 1},
                                borderColor: "silver",
                                minBorderMargin: 4,
                                hoverable: true,
                            }
                        }
                );
                $('#py_tov').html(coreWebApp.formatNumber(jdata.py_tov, 0));
                $('#cy_tov').html(coreWebApp.formatNumber(jdata.cy_tov, 0));
                $('#cm_tov').html(coreWebApp.formatNumber(jdata.cm_tov, 0));

                $("#div_tov_ph").bind("plothover", function (event, pos, item) {
                    if (item) {
                        var index = item.datapoint[0];
                        var ds = item.series.data[index];
                        var amt = coreWebApp.formatNumber(ds[1], 0);
                        $("#tooltip").html('<span style="margin-bottom: 5px;">' + ds[0] + '</span></br><strong>' + amt + "</strong>")
                                .css({top: item.pageY - 30, left: item.pageX - 20})
                                .fadeIn(200);
                    } else {
                        $("#tooltip").hide();
                    }
                }
                );
            }
        });



        // Get Receivable Outstanding
        $.ajax({
            url: '?r=/core/ac/custMon/rec-os-data',
            type: 'GET',
            success: function (result) {
                //debugger;
                var jdata = $.parseJSON(result);
                // Not Due Vs. Overdue
                var dataSeries = [{label: 'Not Due', data: jdata.not_due_tot, color: "rgba(26, 179, 148, 0.8)"}, {label: 'Overdue', data: jdata.overdue_tot, color: "rgba(150, 0, 0, 0.8)"}];
                $.plot('#div_os_ph', dataSeries, {
                    series: {
                        pie: {
                            show: true,
                            radius: 9 / 10,
                            label: {
                                show: true,
                                radius: 2 / 3,
                                formatter: labelFormatter,
                                threshold: 0.1
                            },
                            fill: true,
                            fillColor: {colors: [{opacity: 1}, {opacity: 0.3}]}
                        }
                    },
                    legend: {
                        show: false
                    }
                });
                $('#os_tot').html(coreWebApp.formatNumber(jdata.os_tot, 0));
                $('#os_not_due_tot').html(coreWebApp.formatNumber(jdata.not_due_tot, 0));
                $('#os_overdue_tot').html(coreWebApp.formatNumber(jdata.overdue_tot, 0));


                // By Branch
                var not_due_bybr = [];
                var overdue_bybr = [];
                for (i = 0; i < jdata.by_branch.length; i++) {
                    var br_data = jdata.by_branch[i];
                    not_due_bybr.push([br_data.branch_code, br_data.not_due, br_data.branch_name]);
                    overdue_bybr.push([br_data.branch_code, br_data.overdue, br_data.branch_name]);
                }
                var stackData = [
                    {label: 'Not Due', data: not_due_bybr, color: "rgb(26, 179, 148)"},
                    {label: 'Overdue', data: overdue_bybr, color: "rgb(150, 0, 0)"}
                ];
                $.plot('#div_os_br_ph', stackData, {
                    series: {
                        stack: true,
                        bars: {
                            show: true,
                            barWidth: 0.6,
                            align: "center",
                            lineWidth: .5,
                            fill: true,
                            fillColor: {colors: [{opacity: 1}, {opacity: 0.3}]}
                        }
                    },
                    xaxis: {
                        mode: "categories",
                        tickLength: 0
                    },
                    yaxis: {
                        tickFormatter: function (v) {
                            return coreWebApp.formatNumber(v, 0);
                        }
                    },
                    grid: {
                        borderWidth: {top: 0, right: 0, bottom: 1, left: 1},
                        borderColor: "silver",
                        minBorderMargin: 4,
                        hoverable: true,
                    },
                    legend: {
                        show: false
                    }
                });

                $("#div_os_br_ph").bind("plothover", function (event, pos, item) {
                    if (item) {
                        var index = item.datapoint[0];
                        var ds = item.series.data[index];
                        var amt = coreWebApp.formatNumber(ds[1], 0);
                        $("#tooltip").html('<span style="margin-bottom: 5px;">' + ds[2] + "[" + item.series.label + ']</span></br><strong>' + amt + "</strong>")
                                .css({top: item.pageY - 30, left: item.pageX - 20})
                                .fadeIn(200);
                    } else {
                        $("#tooltip").hide();
                    }
                }
                );
            }
        });

        // fetch top customer data
        custMon.cust_sort_opt_click('tov');
        // fetch kpi(s)
        custMon.rec_kpi();

    }
    custMon.overview_pageload = overview_pageload;


    function cust_sort_opt_click(opt) {
        ko.cleanNode($('#div_cust_data')[0]);
        $.ajax({
            url: '?r=/core/ac/custMon/top-customer',
            type: 'GET',
            data: {sort: opt},
            success: function (result) {
                var jdata = $.parseJSON(result);
                custMon.CustModel = ko.mapping.fromJS(jdata);
                ko.applyBindings(custMon.CustModel, $('#div_cust_data')[0]);
                var data_ht = parseInt($('#div_cust_data').height());
                var htt = parseInt($('#div_cust').height())-parseInt($('#div_cust_head').height())-10;
                if(data_ht < htt ) {
                    $('#div_cust_data').height(htt);
                }
            }
        });
    }
    custMon.cust_sort_opt_click = cust_sort_opt_click;


    function rec_kpi() {
        $.ajax({
            url: '?r=/core/ac/custMon/rec-kpi',
            type: 'GET',
            success: function (result) {
                var jdata = $.parseJSON(result);
                $('#today_tov').html(coreWebApp.formatNumber(jdata.tov_amt, 0));
                $('#avr_coll_period').html(jdata.avr_coll_period + '<small>&nbsp;Days</small>');
                $('#rec_tov_ratio').html(jdata.rec_tov_ratio + '<small>&nbsp;times</small>');
                $('#bad_os').html(coreWebApp.formatNumber(jdata.bad_os, 0));
            }
        });
    }
    custMon.rec_kpi = rec_kpi;

    function cust_detail_click(cdata) {
        var cust_id = cdata.customer_id();
        var cont = $('#cust_detail_cont_' + cust_id);
        if (!cont.hasClass('hidden')) {
            cont.empty();
            cont.addClass('hidden');
            $('#cust_name_' + cust_id).css({"font-size": "100%"});
            return;
        }

        $.ajax({
            url: '?r=/core/ac/custMon/cust-detail-view',
            type: 'GET',
            data: {cust_id: cust_id},
            success: function (result) {
                cont.html(result);
                cont.children().find('#cust_opt').first().attr('cust_id', cust_id);
                cust_detail_get_data(cdata);
                cont.removeClass('hidden');
                $('#cust_name_' + cust_id).css({"font-size": "130%"});
                cont.children('div').css({
                    "border-top": "1px dotted silver",
                    "padding-top": "5px"});
                var li = cont.parents('li').first();
                var pd = li.parent('ul').parent('div');
                var newpos = li.offset().top - $('#div_cust_head').offset().top - $('#div_cust_head').height() - 10;
                var oldpos = pd.scrollTop();
                pd.scrollTop(oldpos + newpos);
            }
        });
    }
    custMon.cust_detail_click = cust_detail_click;

    function cust_detail_get_data(cdata) {
        var cust_id = cdata.customer_id();
        var cont = $('#cust_detail_cont_' + cust_id);
        $.ajax({
            url: '?r=/core/ac/custMon/cust-detail',
            type: 'GET',
            data: {cust_id: cust_id},
            success: function (result) {
                var jdata = $.parseJSON(result);
                cont.find('#cust_det_tov_cy').html(coreWebApp.formatNumber(jdata.cust_det_tov_cy, 0));
                cont.find('#cust_det_tov_pp').html(coreWebApp.formatNumber(jdata.cust_det_tov_pp, 0));
                cont.find('#cust_det_tov_py').html(coreWebApp.formatNumber(jdata.cust_det_tov_py, 0));
                cont.find('#cust_det_os').html(coreWebApp.formatNumber(jdata.cust_det_os, 0));
                cont.find('#cust_det_avg_coll').html(coreWebApp.formatNumber(jdata.cust_det_avg_coll, 2));
                cont.find('#cust_det_pay_term').html(jdata.cust_det_pay_term);
                cont.find('#cust_det_overdue').html(coreWebApp.formatNumber(jdata.cust_det_overdue, 0));
                cont.find('#cust_det_credit_limit').html(coreWebApp.formatNumber(jdata.cust_det_credit_limit, 0));
                cont.find('#cust_det_room_avl').html('N.A.');

                // fill os data
                var os_tbody = cont.find('#cust_det_os_list tbody');
                $.each(jdata.os_data, function (i, item) {
                    var tr = '<tr>';
                    tr += '<td>' + coreWebApp.formatDate(item.doc_date) + '</td>';
                    tr += '<td>' + item.voucher_id + '</td>';
                    tr += '<td class="datatable-col-right">' + (item.overdue_days < 0 ? 'N.A.' : item.overdue_days) + '</td>';
                    tr += '<td class="datatable-col-right">' + (item.overdue_days <= 0 ? coreWebApp.formatNumber(item.not_due, 0) : '') + '</td>';
                    tr += '<td class="datatable-col-right">' + (item.overdue_days > 0 && item.overdue_days < 30 ? coreWebApp.formatNumber(item.overdue, 0) : '') + '</td>';
                    tr += '<td class="datatable-col-right">' + (item.overdue_days >= 30 && item.overdue_days <= 60 ? coreWebApp.formatNumber(item.overdue, 0) : '') + '</td>';
                    tr += '<td class="datatable-col-right">' + (item.overdue_days > 60 && item.overdue_days <= 90 ? coreWebApp.formatNumber(item.overdue, 0) : '') + '</td>';
                    tr += '<td class="datatable-col-right">' + (item.overdue_days > 90 && item.overdue_days <= 180 ? coreWebApp.formatNumber(item.overdue, 0) : '') + '</td>';
                    tr += '<td class="datatable-col-right">' + (item.overdue_days > 180 ? coreWebApp.formatNumber(item.overdue, 0) : '') + '</td>';
                    os_tbody.append(tr + '</tr>');
                });

                var inv_tbody = cont.find('#cust_det_inv_list tbody');
                $.each(jdata.inv_collect_data, function (i, item) {
                    var tr = '<tr>';
                    tr += '<td>' + (item.category == 'A' ? coreWebApp.formatDate(item.doc_date) : '') + '</td>';
                    tr += '<td>' + item.voucher_id + '</td>';
                    tr += '<td>' + (item.category == 'A' ? '' : coreWebApp.formatDate(item.bill_date)) + '</td>';
                    tr += '<td class="datatable-col-right">' + coreWebApp.formatNumber(item.debit_amt, 0) + '</td>';
                    tr += '<td class="datatable-col-right">' + coreWebApp.formatNumber(item.credit_amt, 0) + '</td>';
                    inv_tbody.append(tr + '</tr>');
                });
                cont.find('#cust_det_inv_cont').hide();
            }
        });

    }
    custMon.cust_detail_get_data = cust_detail_get_data;

    function cust_detail_opt_click(el, opt) {
        var div = $(el).parent();
        var cust_id = div.attr('cust_id');
        var cont = $('#cust_detail_cont_' + cust_id);
        if (opt == 'inv') {
            cont.find('#cust_det_os_cont').hide();
            cont.find('#cust_det_inv_cont').show('slow');
        } else if (opt == 'orders') {
            cont.find('#cust_det_os_cont').hide();
            cont.find('#cust_det_inv_cont').hide();
        } else {
            cont.find('#cust_det_os_cont').show('slow');
            cont.find('#cust_det_inv_cont').hide();
        }
    }
    custMon.cust_detail_opt_click = cust_detail_opt_click;

    function labelFormatter(label, series) {
        return "<div style='font-size:8pt; text-align:center; padding:2px; color:white;'>" + label + "<br/>" + Math.round(series.percent) + "%</div>";
    }
    custMon.labelFormatter = labelFormatter;

    function dbexport(extyp) {
        debugger;
        var cr = $('#content-root');
        var w = cr.width();
        var h = cr.height();

        html2canvas($('#content-root'), {
            onrendered: function (canvas) {
                var imgData = canvas.toDataURL("image/png", 1.0);
                var doc = new jsPDF({
                    orientation: 'l',
                    unit: 'pt',
                    format: [h, w]
                });
                doc.addImage(imgData, 'PNG', 10, 10);
                doc.save('cut-mon-db.pdf');
            }
        });
    }
    custMon.dbexport = dbexport;

}(window.ac_db.custMon));



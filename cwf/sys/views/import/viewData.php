<?php
$sid = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID();
?>
<div>
    <div id="contents" style="padding: 5px;margin:5px;">
        <div class="row" style="margin: 10px 0;">
            <div class="col-md-6">
                <span style="font-size: large; font-weight: bold;"><?= $mastername ?></span>
            </div>
        </div>
        <div class="row" style="margin: 10px 0;">
            <div class="col-md-3">
                <span>Total records: <?= count($csvdata['importData']['data']) ?></span>
            </div>
            <div id="diverr" class="col-md-3" style="display: none; color: maroon;">
                <span>Records with error(s): <?= count($csvdata['dataValid']) ?></span>
            </div>
        </div>
        <div id="diverrlog" class="row col-md-12" style="margin: 10px 0; display: none;"></div>
        <div>
            <table id="tblimportdata" class="table table-condensed" style="width: 100%;"></table>
        </div>
        <div id="divimpcmd" class="row" style="margin: 10px 0; display: none;">
            <div class="col-md-3" style="margin: 20px 0;">
                <a class="btn btn-primary" id="cmdimport" onclick='importdata()'>
                    Import <?= $mastername ?> data for <?= count($csvdata['importData']['data']) ?> records in <?= $brid == -1 ? '' : $branch_name ?></a>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    var dt = <?= json_encode($csvdata['importData']['data']) ?>;
    var hdr = <?= json_encode($csvdata['importData']['headers']) ?>;
    var errcnt = <?= count($csvdata['dataValid']) ?>;
    var errs = <?= json_encode($csvdata['dataValid']) ?>;
    var brid = <?= $brid ?>;
    var colarr = [];
    if (errcnt > 0) {
        $('#diverrlog').html('');
        $('#diverr').show();
        var cDiv = $('<div/>');
        var cHdr = '<strong>Import data issues</strong>';
        var cList = $('<ul/>');
        $.each(errs, function (i) {
            var li = $('<li/>').text(errs[i]).appendTo(cList);
        });
        cHdr += cList.html();
        $(cHdr).appendTo(cDiv);
        $('#diverrlog').append(cDiv);
        $('#diverrlog').show();
        $('#divimpcmd').hide();
    } else {
        if (dt.length > 0) {
            $('#diverrlog').html('<strong>All records found valid for import.</strong>');
            $('#diverrlog').show();
            $('#divimpcmd').show();
        }
    }
    colarr.push({'title': '#', 'data': 'Index', 'className': 'dt-center'});
    for (cnt = 0; cnt < hdr.length; cnt++) {
        colarr.push({'title': hdr[cnt], 'data': hdr[cnt], 'className': 'dt-center'});
    }
    for (cnt = 0; cnt < dt.length; cnt++) {
        dt[cnt].Index = cnt + 1;
    }
    $('#tblimportdata').DataTable({data: dt, scrollY: '70vh', scrollX: true,
        scrollCollapse: true,
        paging: false,
        columns: colarr,
        rowCallback: function (row, data, index) {
            if (data['Is Valid']) {
                $('td:eq(' + (hdr.length - 1) + ')', row).html('<span style="color:green;">Yes</span>');
            } else {
                $('td:eq(' + (hdr.length - 1) + ')', row).html('<span style="color:maroon;">No</span>');
                $(row).css('color', 'maroon');
            }
            if (data['Action'] == -1) {
                $('td:eq(' + (hdr.length) + ')', row).html('<span style="color:green;">Insert</span>');
            } else if (data['Action'] == -99) {
                $('td:eq(' + (hdr.length) + ')', row).html('<span style="color:maroon;">Can not process</span>');
            } else {
                $('td:eq(' + (hdr.length) + ')', row).html('<span style="color:darkgreen;">Update</span>');
            }
        }});

    function importdata() {
        $.ajax({
            url: "?r=cwf/sys/import/importdata",
            data: {mastername: '<?= $mastername ?>', fileid: <?= $fileid ?>, 'core-sessionid': '<?= $sid ?>', 'branch_id': brid},
            beforeSend: function () {
                $('#btnuploadfile').attr('disabled', 'disabled');
                $('#btnuploadfile span').text('Uploading..');
            }
        }).done(function (data) {
            $('#cmdimport').hide();
            $('#divimpcmd').html('');
            $('#divimpcmd').html(importsummary(data));
        });
        return false;
    }

    function importsummary(data) {
        var dta = JSON.parse(data);
        var divsummary = $('<span/>');
        var summarytext = $('<strong>' + dta.saved + ' of ' + dta.total + ' records saved successfully.' + '</strong>');
        $(divsummary).append(summarytext);
        var berrs = 0;
        $.each(dta.issues, function (i) {
            berrs++;
        });
        if (berrs > 0) {
            var cDiv = $('<div/>');
            var cHdr = '<strong>Import data issues</strong>';
            var cList = $('<ul/>');
            $.each(dta.issues, function (index, val) {
                var recid = parseInt(index) + 1;
                var li = $('<li/>').text('Record #' + recid + ' ' + dta.issues[index]).appendTo(cList);
            });
            cHdr += cList.html();
            $(cHdr).appendTo(cDiv);
            $(cDiv).css('margin-top', '5px');
            $(divsummary).append(cDiv);
        }
        return $(divsummary).html();
    }

</script>
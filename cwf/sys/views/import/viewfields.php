<?php
$sid = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID();
?>
<style>
    .smartcombo{padding: 0;}
    .select2-container .select2-choice{border:0;height: 100%;}
</style>
<div>
    <div id="contents" style="padding: 5px;margin:5px;">
        <div class="row" style="margin: 10px 0;">
            <div class="col-md-6">
                Available fields in <span style="font-size: large; font-weight: bold;"><?= $minfo->name ?></span>
            </div>
        </div>
        <div>
            <table id="flddata" class="table table-condensed" style="width: 100%;"></table>
        </div>
        <div class="row">
            <div class="col-md-3" style="margin: 20px 0 0 10px;">
                <a class="btn btn-primary" 
                   href="?r=cwf/sys/import/getfile&mastername=<?= $minfo->name ?>&core-sessionid=<?= $sid ?>">Get <?= $minfo->name ?> Import template</a>
            </div>
            <div id="cmdbranch" class="col-md-3" style="display: none;">                
                <div class="form-group col-md-12">
                    <label class="control-label" for="branch_id">Branch</label>
                    <div class="form-control">                
                        <input id="branch_id" class="smartcombo form-control" type="SmartCombo" 
                               data-valuemember="branch_id" data-displaymember="branch_name" 
                               data-namedlookup="../cwf/sys/lookups/Branch.xml" 
                               data-validation="smart-combo" data-validation-error-msg="Please select Branch" 
                               name="branch_id" notyetsmart=true data-Filter="">
                    </div>
                </div>
            </div>
            <div class="col-md-3" style="margin: 20px 0;">
                <a class="btn btn-primary" href="#" onclick="getimpdata();">Import <?= $minfo->name ?> data</a>
            </div>
        </div>
        <div class="row">
            <div id="multiselect" class="col-md-6" style="margin-left: 20px;">            
            </div>
        </div>
    </div>
    <div id="frmfileupload" style="display: none;">
        <form id="fileupload" method="POST" enctype="multipart/form-data" onsubmit="return importfile();">
            <input type="hidden" name="_csrf" value="<?= Yii::$app->request->getCsrfToken() ?>" />
            <table id="attachedFileList" role="presentation" class="table table-hover table-condensed"><tbody class="files"></tbody></table>
            <table id="attachingFileList" role="presentation" class="table table-hover table-condensed"><tbody class="files"></tbody></table>
            <div class="row fileupload-buttonbar" style="text-align: center;">
                <div class="" style="">
                    <button id="btnuploadfile" name="btnuploadfile" type="submit" class="btn btn-primary start" style="float:right;display:none;">
                        <i class="glyphicon glyphicon-upload"></i>
                        <span>Upload</span>
                    </button>
                    <span class="btn btn-success fileinput-button" style="margin-right:10px;">
                        <span>Select File</span>
                        <input type="file" name="files[]" id="cfile">
                    </span>                
                </div>           
            </div>  
            <script type="text/javascript">
                $("#cfile").bind("change", function handleFileSelect(e) {
                    if (!e.target.files || !window.FileReader)
                        return;
                    $("#attachingFileList").empty();
                    for (var i = 0; i < e.target.files.length; i++) {
                        var flist = $("#attachedFileList").text();
                        var umsg = "to be uploaded";
                        if (flist.indexOf(e.target.files[i].name) != -1) {
                            umsg = "will replace current file";
                        }
                        var filestr = "<tr><td><span>" + e.target.files[i].name + "</span>" +
                                "<span style=\"font-style:italic;float:right;\">" + umsg + "</span></td></tr>";
                        $("#attachingFileList").append(filestr);
                    }
                    $("#btnuploadfile").show();
                });
            </script>
        </form>
    </div>
</div>
<script type="text/javascript">
    var mastname = '<?= $minfo->name ?>';
    var tbldata = <?= json_encode($fieldlist) ?>;
    var isBranch = <?= $minfo->forBranch == TRUE ? 'true' : 'false' ?>;
    var tblcol = [{'title': 'Name', 'data': 'cname'},
        {'title': 'Data Type', 'data': 'phpDataType', 'className': 'dt-center'},
        {'title': 'Length', 'data': 'length', 'className': 'dt-right'},
        {'title': 'Required', 'data': 'isOptional', 'className': 'dt-center'}];
    var tbl = $('#flddata').DataTable({
        columns: tblcol,
        data: tbldata, //scrollY:'70%',
        scrollCollapse: true,
        paging: false,
        rowCallback: function (row, data, index) {
            if (data.isOptional == false) {
                $('td:eq(3)', row).html('<div style="text-align: center;">Yes</div>');
            } else {
                $('td:eq(3)', row).html('<div style="text-align: center;">No</div>');
            }
            if (data.phpDataType != 'string') {
                $('td:eq(2)', row).html('');
            }
            if (data.lookup != null || data.options != null) {
                $('td:eq(1)', row).html('<div style="text-align: center;">select from list<sup><span style="color:red"> *</span></sup>' + '</div>');
            }
        }
    });
    $('#flddata thead').addClass('dataTables_scrollHead');
    $('.dataTables_info').css('margin-left', '10px');

    for (cnt = 0; cnt < tbldata.length; cnt++) {
        if (tbldata[cnt].lookup != null) {
            var cDiv = $('<div/>');
            var cHdr = '<h5 style="margin:0;"><strong><span style="color:red;">* </span>' + tbldata[cnt].cname + '</strong></h5>';
            var cList = $('<ul/>');
            $.each(tbldata[cnt].lookup, function (i) {
                var li = $('<li/>').text(tbldata[cnt].lookup[i]).appendTo(cList);
            });
            cHdr += cList.html();
            $(cHdr).appendTo(cDiv);
            $('#multiselect').append(cDiv);
        }
        if (tbldata[cnt].options != null) {
            var cDiv = $('<div/>');
            var cHdr = '<h5 style="margin:0;"><strong><span style="color:red;">* </span>' + tbldata[cnt].cname + '</strong></h5>';
            var cList = $('<ul/>');
            $.each(tbldata[cnt].options, function (i) {
                var li = $('<li/>').text(tbldata[cnt].options[i]).appendTo(cList);
            });
            cHdr += cList.html();
            $(cHdr).appendTo(cDiv);
            $('#multiselect').append(cDiv);
            //$('#multiselect').append('<div><h4><sup><span style="color:red">* </span></sup>'+tbldata[cnt].cname+'</h4><ul>'+tbldata[cnt].options+'</ul></div>');
        }
    }
    setbranch();

    function setbranch() {
        if (isBranch == true) {
            $('#cmdbranch').show();
            coreWebApp.applySmartCombo($('#branch_id'));
            $('#cmdbranch').find('.form-control').css('border', 'none');
            $('#cmdbranch').find('.select2-container').css('border', '1px solid lightgrey');
            $('#cmdbranch').find('.select2-container').css('margin', '-2px 0 0 -5px');
        }
    }

    function getimpdata() {
        var dlg = $('#frmfileupload').dialog({
            title: 'Upload <?= $minfo->name ?> data',
            modal: true,
            //width: '350px',height: '250px',
            create: function (event, ui) {},
            close: function (event, ui) {
                $(this).dialog("destroy");
            },
            open: function () {
                $(this).closest(".ui-dialog")
                        .find(".ui-dialog-titlebar-close")
                        .removeClass("ui-dialog-titlebar-close")
                                .html("<span class='ui-button-icon-primary ui-icon-closethick' style='font-weight:bold;'>X</span>")
                                .css('float', 'right').height('25px');
            }
        });
        dlg.dialog("open").prev().css('background', 'white');
        $(".ui-dialog .ui-dialog-titlebar").css('padding', '0');
        $(".ui-dialog").css('z-index', '9999');
        $(".ui-widget-header").css('border', 'none');
        $(".ui-widget-header").css('border-bottom', '1px solid teal');
        $(".ui-widget-header").css('border-radius', '0');
        $(".ui-dialog .ui-dialog-title").css('line-height', '30px');
        $(".ui-dialog .ui-dialog-title").css('font-weight', 'normal');
        $(".ui-dialog .ui-dialog-title").css('font-size', '15px');
        $(".ui-dialog .ui-dialog-title").css('color', 'teal');
        $(".ui-dialog .ui-dialog-title").css('width', '70%');
        $(".ui-dialog .ui-dialog-title").css('margin-left', '15px');
        $(".ui-dialog button").addClass('btn');
        $("#frmfileupload").css('min-height', '0');
    }

    function importfile() {
        var fd = new FormData(document.getElementById('fileupload'));
        fd.append('bo', '<?= $minfo->bo ?>');
        fd.append('mastername', '<?= $minfo->name ?>');
        fd.append('doc_id', 'DataImportFile_' + new Date().getTime());
        fd.append('branch_id', $('#branch_id').val());
        fd.append('branch_name', $('#branch_id').select2('data').text);

        fd.append("label", "WEBUPLOAD");
        $.ajax({
            url: '?r=cwf/sys/import/importfile',
            type: "POST",
            data: fd,
            enctype: 'multipart/form-data',
            processData: false, // tell jQuery not to process the data
            contentType: false, // tell jQuery not to set contentType
            beforeSend: function () {
                var xhr = new window.XMLHttpRequest();
                //Upload progress
                xhr.upload.addEventListener("progress", function (evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = evt.loaded / evt.total;
                        //Do something with upload progress
                        console.log(percentComplete);
                    }
                }, false);
                $('#btnuploadfile').attr('disabled', 'disabled');
                $('#btnuploadfile span').text('Uploading..');
            }
        }).done(function (data) {
            $('#details').html(data);
            $('#frmfileupload').dialog("destroy");
        });
        return false;
    }
</script>
<div id="contentholder" logonselect="true" class="view-min-width view-window1" 
     style="min-height: 90%;padding-top: 15px;">
    <div class="col-sm-4">
        <div id="companyinfo" class="list-group">
            <?= $model->getCompanyList() ?>
        </div>
    </div>
    <div class="col-sm-4">
        <div id="branchinfo" class="list-group">
        </div>
    </div>
    <div class="col-sm-4">
        <div id="finyearinfo" class="list-group">
        </div>
    </div>
</div>
<script type="text/javascript">
    function getBranchList(ctr, company_id) {
        $('#companyinfo a').removeClass('active');
        $('#branchinfo a').removeClass('active');
        $('#finyearinfo a').removeClass('active');
        $('#branchinfo').html('');
        $('#finyearinfo').html('');
        $(ctr).addClass('active');
        var req = {
            url: '?r=cwf/fwShell/main/branchlist',
            type: 'GET',
            data: {'company_id': company_id},
            success: function (resultdata) {
                $('#branchinfo').html(resultdata);
                rejust('branchinfo');
            }
        };
        coreWebApp.utils.getData(req);
    }

    function getFinyearList(ctr, branch_id) {
        $('#branchinfo a').removeClass('active');
        $('#finyearinfo a').removeClass('active');
        $('#finyearinfo').html('');
        $(ctr).addClass('active');
        var req = {
            url: '?r=cwf/fwShell/main/finyearlist',
            type: 'GET',
            data: {'branch_id': branch_id},
            success: function (resultdata) {
                $('#finyearinfo').html(resultdata);
                rejust('finyearinfo');
            }
        };
        coreWebApp.utils.getData(req);
    }

    function rejust(nxt) {
        if (($('#contentholder').height() - 40) < ($('#' + nxt).parent().height())) {
            $('#c' + nxt).height($('#contentholder').height() - 50);
        }
    }

    function postFinYear(ctr, finyear_id) {
        $('#finyearinfo a').removeClass('active');
        $(ctr).addClass('active');
        var req = {
            url: '?r=cwf/fwShell/main/select-finyear',
            type: 'GET',
            data: {'finyear_id': finyear_id},
            success: function (resultdata) {
                var jsonResult = $.parseJSON(resultdata);
                if (jsonResult['status'] != 'OK') {
                    coreWebApp.toastmsg('error', 'Status', 'Requested finyear and branch does not belong to the selected company.', true);
                } else {
                    window.location.replace(jsonResult['lnk']);
                }
            }
        };
        coreWebApp.utils.getData(req);
    }

    function search_branch() {
        var brname = $('#srbrname').val().toLowerCase();
        if (brname.length <= 2) {
            $('.lsbrname').each(function () {$(this).parent().show();});
            return;
        } else {
            $('.lsbrname').each(
                    function () {
                        if((($(this).text()).toLowerCase()).indexOf(brname)>=0){
                            $(this).parent().show();
                        }else{
                            $(this).parent().hide();
                        }
                    });
        }
    }
</script>

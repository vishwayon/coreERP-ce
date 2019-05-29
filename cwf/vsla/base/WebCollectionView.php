<div id="contentholder" class="view-min-width view-window2">
    <style>
        .form-control{margin-top: 5px;}
        #header-fixed { 
            position: fixed; 
            top: 0px; display:none;
        }
    </style>
    <div id="contents" style="padding: 5px;margin:5px;">
        <?=$viewForRender->getCollectionView()?>
    </div>
</div>
<div id="details" class="view-min-width view-window2" style="display: none;">
</div>
<div id="detailsat" class="view-min-width view-window2" style="display: none;">
</div>
<script type="text/javascript">
        $('#contents').find('input').each(function () {
            if($(this).hasClass('smartcombo')) {
                coreWebApp.applySmartCombo(this);
            } else if($(this).hasClass('datetime')) {
                coreWebApp.applyDatepicker(this);
            } else if($(this).attr('type') == 'decimal') {
                coreWebApp.applyNumber(this);
            }
        });
        coreWebApp.collectionView.fetch();
</script>
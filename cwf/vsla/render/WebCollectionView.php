<div id="contentholder" class="view-window"
     style="background-color: white; border-radius: 5px; margin: 20px 0 0 20px; width: 95%;">
    <style>
        .form-control{margin-top: 5px;}
        #header-fixed { 
            position: fixed; 
            top: 0px; display:none;
        }
    </style>
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row cformheader">
            <?= $viewForRender->getHeader() ?>
        </div>
        <div id="collfilter" class="row" id="headerfilter">
           <?= $viewForRender->getFilterOptions() ?>
        </div>
        <div id="collectiondata" name="collectiondata" style="margin-top: 10px;">
           <?= $viewForRender->getCollectionData(null) ?>
        </div>
    </div>
</div>
<div id="details"  class="view-window" style="display: none;
     background-color: white; border-radius: 5px; margin: 20px 0 0 20px; width: 95%;">
</div>
<script type="text/javascript">
        applysmartcontrols($('#thelist'));
        coreWebApp.initCollection('thelist');
</script>
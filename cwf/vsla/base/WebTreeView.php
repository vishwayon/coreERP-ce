<div id="contentholder" class="view-min-width view-window2">
    <style>
        .form-control{margin-top: 5px;}
        #header-fixed { 
            position: fixed; 
            top: 0px; display:none;
        }
    </style>
    <input type="hidden" id="qp" qp-bizobj="tree"/>
    <div id="contents" style="padding: 5px;margin:5px;">
        <div id="collheader" class="row cformheader">
            <?= $treeviewrenderer->renderheader() ?>
        </div>
        <div id="collectiondata" name="collectiondata" style="margin-top: 10px;">
           <?= $treeviewrenderer->rendercollectiondata() ?>
        </div>
    </div>
    <script type="text/javascript">
        //applysmartcontrols();
        //$('#thelist').treegrid();
    </script>
</div>
<div id="details" class="view-min-width view-window2" style="display: none;">
</div>
<div id="detailsat" class="view-min-width view-window2" style="display: none;">
</div>
<script type="text/javascript">
        function closedetail(){
            $('#details').html('');
            $('#details').hide();
            $('#contentholder').show();
            $('#collrefresh').click();
            return false;
        }
</script>
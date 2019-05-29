<div id="contentholder">
    <div id="contents" style="padding: 5px;margin:5px;">
        <?php
        echo json_decode($dbdrenderer)->dbdrender;
        ?>
        <script type="text/javascript">
            var plotData =<?= json_encode($dbdrenderer) ?>;
        </script>
        <style>
            .dataTables_scrollHead { background-color: whitesmoke; color: grey;}
        </style>
    </div>
</div>
<div id="details" class="view-min-width view-window2" style="display: none;">
</div>
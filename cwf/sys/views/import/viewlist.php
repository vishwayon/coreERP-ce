<div>
    <div id="contentholder" class="view-min-width view-window1">
        <div id="contents" style="padding: 5px;margin:5px;">
            <div class="row" style="margin: 10px 0;">
                <div class="col-md-6">
                    <span>Masters available for import</span>
                </div>
            </div>
            <div class="row">
                <ul>
                    <?php
                    foreach ($milist as $mitem) {
                        echo '<li><a class="btn" href="javascript:coreWebApp.rendercontents(\'?r=cwf/sys/import/viewfields&mastername=' . $mitem->name . '\',\'details\',\'contentholder\');">' . $mitem->name . '</a></li>';
                    }
                    ?>
                </ul>    
            </div>
        </div>
    </div>
    <div id="details" class="view-min-width view-window1" style="display: none;"></div>
</div>
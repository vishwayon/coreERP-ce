<?php $this->beginContent('@app/views/layouts/main.php'); ?>
<div id="workspace" class="row">
    <div class="col-sm-2" id="mysidemenu" style="padding-right: 0px;overflow: auto; background-color: #2c383b;">
        <?php echo $content; ?>
    </div>
    <div class="col-sm-10" id="content-root" 
         style="height: 100%;overflow: auto;background-color: #f0f0f0;padding:0;">
        <div id="contentholder" style="display: none;">
        </div>
        <div id="details" class="view-min-width view-window2" style="display: none;">
        </div>
    </div>
    <div id="doc-comment" class="col-sm-2" >

    </div>
</div>
<?php
$this->endContent();

<?php
use yii\helpers\Html;
use app\cwf\vsla\utils\FormatHelper;

$viewerurl='?r=core%2Fsys%2Faudit-trail%2Fgetdata'; 
 
?>


<div id="contents"  style="padding: 5px 5px 0 5px;margin:5px 5px 0 5px;overflow: hidden; height: calc(100% - 10px);" class="col-md-12">
    <div id="custom-form" name="custom-form" class="col-md-12" style="padding: 0;">
        <div>
             <?= $auditTrailParser->setHeader() ?>
        </div>

        <div id="collfilter" class="row">
            <form class="form-horizontal required" id="auditTrail" name ="auditTrail" 
                target="auditdata" method="GET" action="<?= $viewerurl?>" style="margin-left: 10px;">
                <input type="hidden" id="_csrf" name="_csrf" value="<?=\Yii::$app->request->csrfToken?>">
            </form>
        </div> 

        <div id="auditdata" name="auditdata" style="margin-top: 0px;">
            <div id="dataTables_scrollBody" class="dataTables_scrollBody" style="overflow-y: hidden;overflow-x: auto;">
                <?= $auditTrailParser->bindAuditTrailData() ?>
            </div>
        </div>  
  
    </div>
</div>


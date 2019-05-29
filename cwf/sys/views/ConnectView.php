<div id="contents col-md-6" style="margin-top: 10px;padding-left: 20px;" class=" col-md-6">
<?php
$formname=$model->formname;
$listname=$model->listname;
$msg='';
if($formname==='companylist'){
    $msg='Please select a company to proceed';
}else{
    $msg='Please select a financial year to proceed';
}
?>
    <strong><?= $msg?></strong><br/><br/>
<?php
        
        $baseurl=  \Yii::$app->urlManager->getBaseUrl();
        $form = yii\widgets\ActiveForm::begin([
        'id' =>$formname,
        'options' => ['class' => 'form-horizontal'],
            'fieldConfig' => [ ],
        ]); 
        ?>
    <input type="hidden" id="selectedid" name="selectedid" value="-1">
    <input type="hidden" id="formname" name="formName" value=<?= $formname ?>>
    <div class="list-group">
        <?php
            foreach ($model->$listname as $id => $name) {
                if($formname==='companylist'){
                    $mylink='coreWebApp.rendercontents(\''. $baseurl.
                        '?r=cwf/sys/form/connectfinyear&companyid='. $id.'\')';
                    $linkitem=<<<lnkitm
                        <a href="#" class="list-group-item" onclick="{$mylink}">
                            <h4 class="list-group-item-heading" style="font-weight:bold;">{$name['company_short_name']}</h4>
                            <p class="list-group-item-text">{$name['company_name']}</br>
                                {$name['company_address']}
                            </p>
                        </a>
lnkitm;
                    echo $linkitem;
                }elseif($formname==='finyearlist'){
                    $fy=<<<fy
                        <a href="#" class="list-group-item" onclick="coreWebApp.selectid({$id});">
                            <h4 class="list-group-item-heading" style="font-weight:bold;">{$name['code']}</h4>
                            <p class="list-group-item-text">
                               Financial year starting on <strong>{$name['starts']}</strong> and ending on <strong>{$name['ends']}</strong>
                            </p>
                        </a>
fy;
                    echo $fy;
                }
                
                
            }
            if(count($model->$listname)===0){
                echo 'No data available.';
            }
        ?>
    </div>
    <?php yii\widgets\ActiveForm::end() ?>
</div>
<?php
use yii\helpers\Html;
use app\assets\AppAsset;

/* @var $this \yii\web\View */
/* @var $content string */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
AppAsset::register($this);
?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" style="min-height: 100%;height: 100%;">
    <head>
        <meta charset="<?= Yii::$app->charset ?>"/>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
        <meta http-equiv="Pragma" content="no-cache" />
        <meta http-equiv="Expires" content="0" />
        <?= Html::csrfMetaTags() ?>
        <title><?= Html::encode('coreERP-Print') ?></title>
        <?php $this->head() ?>
    </head>

    <body>
    <?php $this->beginBody() ?>
        <div>
        <?php 
          $companyname='';
          $branchname='';
          $dateformat='';
          $finyear='';
          $user_id=-1;
          $sessionid = app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID();  
          if(isset($sessionid) && $sessionid!==NULL){
                $companyname=app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('company_short_name');
                $branchname=app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('branch_name');
                $dateformat = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('date_format');
                $finyear = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('finyear');     
                $user_id = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID();    
          }
          ?>       
        <input type="hidden" id="sessionid" name="sessionid" value="<?=Html::encode($sessionid)?>" >  
        <input type="hidden" id="dateformat" name="dateformat" value="<?= $dateformat!==null?Html::encode($dateformat):''?>" >
        <input type="hidden" id="reqtime" name="reqtime" value="<?php echo $reqtime ?>"/>
    </div>        
        <div id="rptRoot">

        </div>
    <?php $this->endBody() ?>
    <?php $this->endPage() ?>
        <script src="<?php echo \app\cwf\vsla\utils\ScriptHelper::registerScript('@app/cwf/fwShell/views/jrpt_clientcode.js') ?>"></script>
        <script type="text/javascript">
            $(document).ready(function() {
                $.ajax({
                    url: '?r=/cwf/fwShell/jreport/print-data',
                    data: { reqtime: $('#reqtime').val(), 'core-sessionid': $('#sessionid').val() },
                    type: 'GET',
                    dataType: 'json',
                    success: function (result) {
                        cwf_jrpt.rptInfo = result;
                        var pageData = cwf_jrpt.rptInfo.Data;
                        var rptParent = $('<div class="print-preview-wrapper" id="rptParent" name="rptParent"></div>');
                        rptParent.append(cwf_jrpt.rptInfo.PageStyle);
                        for(i=0;i<cwf_jrpt.rptInfo.PageCount;i++) {
                            var rptPage = $('<div class="print-format" id="rptPage'+i+'"></div>');
                            rptParent.append(rptPage);
                        }
                        $('#rptRoot').append(rptParent);
                        //$('#rptParent').height(contentHeight - $('#rptrow1').height() - 65);
                        for(i=0;i<cwf_jrpt.rptInfo.PageCount;i++) {
                            var prop = 'Page'+i;
                            var rptPageid = '#rptPage'+i;
                            var htmllink = pageData[prop];
                            cwf_jrpt.getpage(rptPageid, htmllink, i);
                        }
                    }
                });


            });
        </script>
    </body>
</html>

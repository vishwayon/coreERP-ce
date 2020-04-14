<?php

use yii\helpers\Html;
use yii\bootstrap\Nav;
use yii\bootstrap\NavBar;
use yii\widgets\Breadcrumbs;
use app\assets\AppAsset;

/* @var $this \yii\web\View */
/* @var $content string */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
AppAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" style="min-height: 100%;height: 100%;">
    <head>
        <meta charset="<?= Yii::$app->charset ?>"/>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
        <meta http-equiv="Pragma" content="no-cache" />
        <meta http-equiv="Expires" content="0" />
        <?= Html::csrfMetaTags() ?>
        <title><?= Html::encode(\app\cwf\vsla\security\SessionManager::getTitle()) ?></title>
        <?php $this->head() ?>
    </head>
    <body>

        <?php $this->beginBody() ?>
        <div>
            <?php
            $companyname = '';
            $branchname = '';
            $dateformat = 'dd/mm/yyyy'; //Set to default
            $finyear = '';
            $user_id = -1;
            $full_user_name = '';
            $sessionid = null;
            $user_time_zone = 'UTC';
            $ccy_system = 'm';
            $confirm_post = 0;
            $branch_gst_info = [];

            if (app\cwf\vsla\security\SessionManager::getAuthStatus()) {
                $full_user_name = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getFullUserName();
                $sessionid = app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSession_ID();
                $user_id = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID();
            }

            if (app\cwf\vsla\security\SessionManager::getSessionCreated()) {
                $companyname = app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('company_short_name');
                $branchname = app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('branch_name');
                $dateformat = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('date_format');
                $finyear = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('finyear');
                $user_time_zone = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('user_time_zone');
                $ccy_system = \app\cwf\vsla\security\SessionManager::getCCYSystem();
                $confirm_post = \app\cwf\vsla\security\AccessManager::check_confirm_post();
                $branch_gst_info = app\cwf\vsla\security\SessionManager::getBranchGstInfo();
            }
            ?>       
            <input type="hidden" id="sessionid" name="sessionid" value="<?= Html::encode($sessionid) ?>" >  
            <input type="hidden" id="dateformat" name="dateformat" value="<?= Html::encode($dateformat) ?>" >
            <input type="hidden" id="usertimezone" name="usertimezone" value="<?= Html::encode($user_time_zone) ?>" >
            <input type="hidden" id="ccysystem" name="ccysystem" value="<?= Html::encode($ccy_system) ?>" >
            <input type="hidden" id="confirm_post" name="confirm_post" value="<?= Html::encode($confirm_post) ?>" >
            <input type="hidden" id="branch_gst_info" name="branch_gst_info" value="<?= Html::encode(json_encode($branch_gst_info)) ?>">
        </div>
        <div class="wrap">
            <?php
                NavBar::begin([
                    'brandLabel' => '<div class="headerlogo">'
                    . '<small id="sfver" class="hidden-sm hidden-xs" style="font-size: 50%; position: relative;left: 160px;top: 26px; color: #9d9d9d;">'
                    . \yii::$app->params['coreerp-ver'] . '</small> </div>',
                    'brandUrl' => null,
                    'options' => [
                        'class' => 'navbar-inverse navbar-fixed-top',
                        'style' => 'height:50px;'
                    ],
                    'innerContainerOptions' => [
                        'class' => 'container-fluid'
                    ],
                ]);
                echo '<span id="cname">';
                $searchDoc = '';
                if ($companyname != '' && $companyname !== NULL) {
                    if (\app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->isAdmin() || \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->isOwner()) {
                        echo Html::a(
                                $companyname . '<small>'
                                . $branchname
                                . ($finyear === '' ? '' : (' (FY: ' . $finyear . ')')) . '</small>', '#', ['class' => 'navbar-brand',
                            'style' => 'margin-left: 20px; font-weight: normal; '
                            . 'padding-left: 0px; padding-top: 15px; padding-bottom: 0px;']);
                    } else {
                        echo Html::a(
                                $companyname . '<br/><small>'
                                . $branchname
                                . ($finyear === '' ? '' : (' (FY: ' . $finyear . ')')) . '</small>', '?r=/cwf/fwShell/main/switchsession', ['class' => 'navbar-brand col-lg-4',
                            'style' => 'margin-left: 20px; font-weight: normal; '
                            . 'padding-left: 0px; padding-top: 5px; padding-bottom: 0px;']);
                    }
                }
                echo '</span>';
                if ($companyname != '' && $companyname !== NULL) {
                    $searchInput = Html::input('text', 'srchVchId', '', ['id' => 'srchVchId', 'class' => 'hidden-xs form-control', 'placeholder' => 'Search Document', 'style' => 'margin-top:0 !important']);
                    $searchBtn = Html::button('<i class="glyphicon glyphicon-search" aria-hidden="true"></i>', ['class' => 'btn btn-default', 'style' => 'padding: 1px 5px;', 'onclick' => 'coreWebApp.searchVoucher()']);
                    $searchInput .= Html::tag('span', $searchBtn, ['class' => 'input-group-btn', 'style' => '']);
                    $searchDiv = Html::tag('div', $searchInput, ['class' => 'input-group', 'style' => '']);
                    $searchDoc = Html::tag('div', $searchDiv, ['class' => 'navbar-brand input-group', 'style' => 'margin-left:0px;']);
                    //echo $searchDoc;
                }
                echo Nav::widget([
                    'options' => ['class' => 'navbar-nav navbar-right', 'id' => 'headernav'],
                    'encodeLabels' => false,
                    'items' => [
                        ['label' => '<span class="glyphicon glyphicon-home"></span>',
                            'url' => ['/cwf/fwShell/main/home',
                                'core-sessionid' => $sessionid],
                            'options' => ['data-toggle' => 'tooltip',
                                'data-placement' => 'tooltip', 'title' => 'Home', 'class' => 'hidden-sm'],
                            'visible' => (isset($sessionid) && $sessionid !== NULL)],
                        ['label' => '<span class="glyphicon glyphicon-info-sign"></span>',
                            'url' => ['/site/about'],
                            'options' => ['data-toggle' => 'tooltip',
                                'data-placement' => 'tooltip', 'title' => 'About', 'class' => 'hidden-sm'],
                            'linkOptions' => ['id' => 'myabout', 'name' => 'myabout']],
                        ['label' => '<span class="glyphicon glyphicon-phone-alt"></span>',
                            'url' => ['/site/contact'],
                            'options' => ['data-toggle' => 'tooltip',
                                'data-placement' => 'tooltip', 'title' => 'Support', 'class' => 'hidden-sm'],
                            'linkOptions' => ['id' => 'mycontact', 'name' => 'mycontact']],
                        ['label' => '<span class="glyphicon glyphicon-user"></span><span class="hidden-sm"> ' . mb_strimwidth($full_user_name, 0, 10) . '</span>',
                            'url' => ['#'],
                            'options' => ['data-toggle' => 'tooltip',
                                'data-placement' => 'tooltip', 'title' => $full_user_name, 'class' => 'hidden-sm'],
                            'linkOptions' => ['id' => 'myprofile', 'name' => 'myprofile'],
                            'visible' => (isset($sessionid) && $sessionid !== NULL)],'<li title="Search Document By ID">'.$searchDoc.'</li>',
                        app\cwf\vsla\security\SessionManager::getAuthStatus() ?
//                                ['label' => '<span class="glyphicon glyphicon-log-out"></span>',
//                            //'url' => ['/site/logout', 'core-sessionid' => $sessionid],
//                            'options' => ['data-toggle' => 'tooltip',
//                                'data-placement' => 'tooltip', 'title' => 'Logout'],
//                            'linkOptions' => ['data-method' => 'post', 'id' => 'mainlogout', 'name' => 'mainlogout','onclick' => 'coreWebApp.applogout()']] :
                                '<li title="Logout" data-toggle="tooltip" data-placement="tooltip">'
                                . '<a id="mainlogout" name="mainlogout" onclick="coreWebApp.applogout()">'
                                . '<span class="hidden-xs glyphicon glyphicon-log-out"></span></a></li>' :
                                ['label' => '<span class="glyphicon glyphicon-log-in"></span>',
                            'options' => ['data-toggle' => 'tooltip',
                                'data-placement' => 'tooltip', 'title' => 'Login', 'class' => 'visible-xs'],
                            'url' => yii\helpers\Url::home(),
                            'linkOptions' => ['id' => 'mainlogin', 'name' => 'mainlogin']
                                ],
                    ],
                ]);
                NavBar::end();
                echo '<div id="maincontainer" class="container mycontainer" 
                        style="min-height: 100%;box-sizing: border-box; 
                        height: 100%; margin-left: 0px; padding-top: 48px; width:100%;">';
                echo $content;
                echo '</div>';
            ?>
            <div id="overlay" style="display: none;">
            </div>
        </div>

        <?php $this->endBody() ?>
        <?php $this->endPage() ?>
        <script type="text/javascript">
            $(document).ready(function () {
                var wrapht = parseInt($('.wrap').height());
                var navht = parseInt($('#headernav').height());
                $('.mycontainer').height(wrapht - navht - 1);
                $('#content-root').height(wrapht - navht - 1);
                $('#mysidemenu').height(wrapht - navht - 1);
                $('#mycontact').click(function (event) {
                    if (typeof $('#sessionid').val() != 'undefined' && $('#sessionid').val() != '') {
                        event.preventDefault();
                        $('#content-root').html("<div id='details' style='display: none;" +
                                "background-color: white; border-radius: 5px; margin: 20px auto; min-width: 1024px; width: 95%;'>" +
                                "</div>");
                        coreWebApp.rendercontents('?r=/cwf/sys/form&formName=feedback/FeedbackEditForm&formParams={"feedback_id": -1}', 'details');
                    } else {
                        //            coreWebApp.rendercontents('?r=/site/contact','maincontainer');
                    }

                });
                $('#myprofile').click(function (event) {
                    event.preventDefault();
                    $('#content-root').html("<div id='details' style='display: none;" +
                            "background-color: white; border-radius: 5px; margin: 20px auto; min-width: 1024px; width: 95%;'>" +
                            "</div>");
                    coreWebApp.rendercontents('?r=/cwf/sys/usersettings/authsettings&formName=userProfile/UserProfileEditForm&formParams={"user_id": <?= $user_id ?> }', 'details');
                });
                $('#myabout').click(function (event) {
                    if (typeof $('#sessionid').val() != 'undefined' && $('#sessionid').val() != '') {
                        event.preventDefault();
                        coreWebApp.rendercontents('?r=/cwf/fwShell/main/about', 'content-root');
                    }
                });

                // Set Splitter
                //  var contentroot = $('#content-root');
                //  $('#workspace').height(contentroot.height()).split({
                //        orientation: 'vertical',
                //        limit: 5,
                //        position: '15%'
                //  });

                // Setup the Session id
                var sid = $('#sessionid').val();
                coreWebApp.ajaxSetup(sid);
                coreWebApp.docReady();
                // check if session is alive
                $(window).focus(function() {
                    // try to keep the sesion alive
                    if (sid != '') {
                        coreWebApp.keepAlive();
                    }
                });
<?php
if ($user_id != -1) {      
    echo 'setInterval(coreWebApp.getPendingStatus, 100000);';
}
if ($finyear != NULL && $finyear != '') {
    echo 'coreWebApp.getPendingStatus();';
}
?>

            });
        </script>
    </body>
</html>

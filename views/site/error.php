<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $name string */
/* @var $message string */
/* @var $exception Exception */

?>
<div class="site-error">

    <h3><?= Html::encode($this->title) ?></h3>

    <div style="text-align: left;">
        <span class="alert alert-info" style="margin-left: 20px;"><?= $message ?></span>
    </div>

</div>

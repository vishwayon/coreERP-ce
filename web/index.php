<?php

// comment out the following two lines when deployed to production
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');
// Test Environment tag. Uncomment only when in test
//defined('YII_ENV_TEST') or define('YII_ENV_TEST', true);

require(__DIR__ . '/../vendor/autoload.php');
require(__DIR__ . '/../vendor/yiisoft/yii2/Yii.php');

$config = require(__DIR__ . '/../config/web.php');

// The following event handler ensures that unauthenticated users cannot access secure pages.
\yii\base\Event::on(\yii\web\Application::className(), \yii\web\Application::EVENT_BEFORE_REQUEST, function($event) {
    Yii::trace('App init event fired');
    $request = $event->sender->getRequest();
    \app\cwf\vsla\security\SessionManager::createUserSessionForCore($request);
});

(new yii\web\Application($config))->run();

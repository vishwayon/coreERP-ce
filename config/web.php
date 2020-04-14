<?php

$params = require(__DIR__ . '/params.php');

$config = [
    'id' => 'app',
    'name' => 'CoreERP',
    'basePath' => dirname(__DIR__),
    'timeZone' => 'UTC',
    'modules' => [
        'cwf' => 'app\cwf\CwfModule'
    ],
    'bootstrap' => ['log'],
    'aliases' => [
        '@Socket' => '@vendor/Socket',
        '@quahog' => '@vendor/quahog',
    ],
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
            'directoryLevel' => 0
        ],
        'view' => [
            'class' => 'yii\web\View',
            'renderers' => [
                'twig' => [
                    'class' => 'yii\twig\ViewRenderer',
                    'cachePath' => '@runtime/Twig/cache',
                    'options' => [
                        'auto_reload' => true
                    ]
                ]
            ]
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'assetManager' => [
            'linkAssets' => true,
        ],
        'errorHandler' => [
            'class' => 'app\cwf\vsla\utils\ErrorHandler',
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            'viewPath' => '@backend/mail',
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => false,
            'transport' => [
                'class' => 'Swift_SmtpTransport',
                'encryption' => 'tls'
            ],
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                'file' => [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
                'email' => [
                    'class' => 'app\cwf\vsla\utils\EmailTarget',
                    'mailer' => 'mailer',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ]
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = 'yii\debug\Module';


    $config['modules']['debug'] = ['class' => 'yii\debug\Module', 'allowedIPs' => ['127.0.0.1', '::1']];
}

$cwf_config = require(__DIR__ . '/cwfconfig.php');
if (isset($cwf_config['rootModules'])) {
    // load additional modules
    foreach ($cwf_config['rootModules'] as $mod => $modVal) {
        $config['modules'][$mod] = $modVal;
    }
}
// set mailer configurations
if (isset($cwf_config['mailer'])) {
    $config['components']['mailer']['transport'] = array_merge($config['components']['mailer']['transport'], $cwf_config['mailer']);
}
// set components
if (isset($cwf_config['components'])) {
    $config['components'] = array_merge($config['components'], $cwf_config['components']);
}
$config['params']['cwf_config'] = $cwf_config;

// Override yii classmap for custom objects
Yii::$classMap['yii\helpers\Json'] = '@app/cwf/vsla/extendYii/Json.php';

return $config;

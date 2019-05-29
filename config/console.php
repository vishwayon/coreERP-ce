<?php

Yii::setAlias('@tests', dirname(__DIR__) . '/tests');

//require(__DIR__ . '/consoleAutoload.php');

$params = require(__DIR__ . '/params.php');
$db = [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=localhost;dbname=yii2basic',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8',
];

$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'timezone' => date_default_timezone_get(),
    'modules' => [
        'installer' => 'app\cwf\console\installer\InstallerModule',
        'mailer' => 'app\cwf\console\mailer\MailerModule',
        'utils' => 'app\cwf\console\utils\UtilsModule'
        ],
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],        
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => false,
            'transport' => [
                                'class' => 'Swift_SmtpTransport',
                                'encryption' => 'tls',
                            ],
        ],        
        'db' => $db,
    ],
    
    'params' => $params,
];

$cwf_config = require(__DIR__ . '/cwfconfig.php');
if(isset($cwf_config['rootModules'])) {
    // load additional modules
    foreach($cwf_config['rootModules'] as $mod => $modVal) {
        $config['modules'][$mod] = $modVal;
    }    
}
// set mailer configurations
if(isset($cwf_config['mailer'])) {
    $config['components']['mailer']['transport'] = array_merge($config['components']['mailer']['transport'], $cwf_config['mailer']);
}
$config['params']['cwf_config'] = $cwf_config;

return $config;
<?php

/**
 * This is sampleconfig
 * Copy this file to CoreWebApp/config and change the required values
 * @author girish
 */
$cwf_config = [
    'dbInfo' => [
        'dbServer' => '127.0.0.1',
        'dbMain' => 'main',
        'suName' => 'coreadmin',
        'suPass' => 'password'
    ],
    'pgInfo' => [
        'pgUser' => 'postgres',
        'pgPass' => 'password'
    ],
    'rootModules' => [
        'core' => 'app\core\CoreModule'
    ],
    'dbBackup' => [
        'compress' => 'singleFile',
        'path' => '/path/to/dbbackup/'
    ],
    'mailer' => [
        'host' => 'smtp.mydomain.com',
        'username' => 'sender@mydomain.com',
        'password' => 'pasword',
        'port' => '587'
    ],
    'mastergst' => [
        'useremail'=>'api.access@mydomain.com',
        'clientid'=>'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'clientsecret'=>'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'ipaddress'=>'123.456.789.123'
    ],
    'exceptionMail' => [
        'to' => 'support@mydomain.com',
        'from' => 'noreply@mydomain.com'
    ],
    'dm' => [
        'path' => '/opt/filestore/'
    ],
    'reportServer' => [
        'reportHost' => 'http://127.0.0.1:8080'
    ],
    'docSeqCC' => true,
    'components' => [
        'request' => [
            // !!! insert a secret key in the following  - this is required by cookie validation
            'cookieValidationKey' => 'cookie-validation-key',
        ],
        'wfEventListner' => [
            'class' => 'app\cwf\vsla\workflow\WfEventListnerBase'
        ],
        'fileAVScan' => [
            'class' => 'app\cwf\vsla\utils\ClamScan',
            'tmpPath' => '/pathtoscan/'  //Ensure that daemon or www-data has access to this folder. also include user: clamav in the daemon/www-data group 
        ],
        'authClientCollection' => [
            'class' => 'yii\authclient\Collection',
            'clients' => [
            ],
        ]
    ]
];

// Change the default route from site/index to anything else based on host
if(array_key_exists('HTTP_HOST', $_SERVER) && $_SERVER['HTTP_HOST'] == 'something') {
    $config['defaultRoute'] = 'module';
}

return $cwf_config;

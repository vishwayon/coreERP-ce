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
    'restrictIP' => true,
    'exceptionMail' => [
        'to' => 'support@mydomain.com',
        'from' => 'noreply@mydomain.com'
    ],
    'dm' => [
        'path' => '/opt/filestore/'
    ],
    'customCode' => [
        'path' => '/opt/customCode/'
    ],
    'docSeqCC' => true,
    'sphinxSearch' => [
        // Install sphinx search: $ sudo apt-get install sphinxsearch 
        // Ensure that sphinx service can be started by using 
        // $ sudo /etc/init.d/sphinxsearch start
        // Then stop service and restart using following config file
        // $ sudo searchd --config /path/to/coreERP/CoreWebApp/config/sphinx.conf        
        'server' => '127.0.0.1',
        'port' => 9306,
        'user' => '',
        'pass' => ''
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following  - this is required by cookie validation
            'cookieValidationKey' => 'cookie-validation-key',
        ],
        'restrictIP' => [
            'class' => 'app\cwf\vsla\security\RestrictIP'
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
                'google' => [
                    'class' => 'yii\authclient\clients\GoogleOAuth',
                    // !!! Provide google client id and client secret after registering with google oauth api
                    'clientId' => 'google-app-client-id',
                    'clientSecret' => 'client-secret',
                ],
            ],
        ],
        'userAuth' => [
            'class' => 'app\cwf\vsla\security\adldap\LdapAuth',
        ],
        /*
        // Experimental: The following component would enable apcu for in-memory userinfo management
        // To enable: pecl install apcu
        'apcCache' => [
            'class' => 'yii\caching\ApcCache',
            'useApcu' => true,
        ],*/
        /*
         * This can be used to enable LdapAuth
        'userAuth' => [
            'class' => 'app\cwf\vsla\security\adldap\LdapAuth',
        ] */
        'ldap' => [
            'class'=>'Edvlerblog\Ldap',
            'options'=> [
                    'ad_port'      => 389,
                    'domain_controllers'    => array('AdServerName1','AdServerName2'),
                    'account_suffix' =>  '@test.lan', // optional - required to extract list of all users
                    'base_dn' => "DC=test,DC=lan", // optional - required to extract list of all users
                    // for basic functionality this could be a standard, non privileged domain user (required)
                    'admin_username' => 'ActiveDirectoryUser',
                    'admin_password' => 'StrongPassword'
                ],
                //Connect on Adldap instance creation (default). If you don't want to set password via main.php you can
                //set autoConnect => false and set the admin_username and admin_password with
            //\Yii::$app->ldap->connect('admin_username', 'admin_password');
            //See function connect() in https://github.com/Adldap2/Adldap2/blob/v5.2/src/Adldap.php

            'autoConnect' => true
        ]
    ]
];

// Change the default route from site/index to anything else based on host
if(array_key_exists('HTTP_HOST', $_SERVER) && $_SERVER['HTTP_HOST'] == 'something') {
    $config['defaultRoute'] = 'module';
}

return $cwf_config;
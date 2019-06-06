<?php

namespace app\core;

use yii\base\Module;

class CoreModule extends Module {
    
    public function init() {
        parent::init();
        
        $this->modules = [
            'ac' => [
                'class' => 'app\core\ac\AcModule',
                'basePath' => '@app/core/ac',
                'defaultRoute' => 'bo'
            ],
            'ap' => [
                'class' => 'app\core\ap\ApModule',
                'basePath' => '@app/core/ap',
                'defaultRoute' => 'bo'
            ],
            'ar' => [
                'class' => 'app\core\ar\ArModule',
                'basePath' => '@app/core/ar',
                'defaultRoute' => 'bo'
            ],
            'tds' => [
                'class' => 'app\core\tds\TDSModule',
                'basePath' => '@app/core/tds',
                'defaultRoute' => 'bo'
            ],
            'st' => [
                'class' => 'app\core\st\StModule',
                'basePath' => '@app/core/st',
                'defaultRoute' => 'bo'
            ],
            'tx' => [
                'class' => 'app\core\tx\TxModule',
                'basePath' => '@app/core/tx',
                'defaultRoute' => 'bo'
            ],
            'pos' => [
                'class' => 'app\core\pos\PosModule',
                'basePath' => '@app/core/pos',
                'defaultRoute' => 'bo'
            ],
            'fa' => [
                'class' => 'app\core\fa\FaModule',
                'basePath' => '@app/core/fa',
                'defaultRoute' => 'bo'
            ],
            'hr' => [
                'class' => 'app\core\hr\HrModule',
                'basePath' => '@app/core/hr',
                'defaultRoute' => 'bo'
            ]
        ];
    }
    
}

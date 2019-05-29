<?php

namespace app\cwf;

use yii\base\Module;

class CwfModule extends Module {
    
    public function init() {
        parent::init();
        
        $this->modules = [
            'fwShell' => [
                'class' => 'app\cwf\fwShell\FwShellModule',
                'basePath' => '@app/cwf/fwShell',
                'defaultRoute' => 'main'
            ],
            'sys' => [
                'class' => 'app\cwf\sys\SysModule',
                'basePath' => '@app/cwf/sys',
                'defaultRoute' => 'bo'
            ]
        ];
    }
    
}

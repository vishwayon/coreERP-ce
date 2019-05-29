<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\assets;

use yii\web\AssetBundle;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class AppAsset extends AssetBundle
{
    // Set the Source Path for all assets to be published
    public $sourcePath = '@app/cwf/vsla/assets';
    
    // Include in main.php only the required assets
    public $css = [
        'site.css',
        'coreWebApp.css',
        'theme-teal.css'
    ];
    public $js = [
        'cwfclient.js',
	'coreWebApp.js'
    ];
    
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap\BootstrapPluginAsset',
        'app\cwf\vsla\VslaAsset'
    ];
}

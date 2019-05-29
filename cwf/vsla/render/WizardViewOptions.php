<?php
namespace app\cwf\vsla\render;
/**
 * Description of WizardViewOptions
 *
 * @author dev
 */
use app\cwf\vsla\security\AccessLevels;
class WizardViewOptions {
    public $xmlViewPath = '';
    public $callingModulePath = '';
    public $params = array(); 
    public $filters = ''; 
    public $accessLevel =  AccessLevels::AUTHORIZE;
}

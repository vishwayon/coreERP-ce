<?php
/**
 * Description of WizardView
 *
 * @author dev
 */

namespace app\cwf\vsla\design;
include_once '../cwf/vsla/design/CommonTypes.php';

class WizardView extends CwFrameworkType {
    public $id = '';
    public $name = '';
    public $codeBehind;
    public $wizardStep = array();
    public $postWizard = '';
    public $currentStep,$nextStep,$prevStep;
    public $stepData;
    public $clientJsCodes;
    public $formParams;
    public $xrootview;
    public $modulePath;
    
    public function getType() {
        return self::WIZARD_VIEW;
    }
}
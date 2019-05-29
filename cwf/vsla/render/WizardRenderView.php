<?php
namespace app\cwf\vsla\render;
/**
 * Description of WizardRenderView
 *
 * @author dev
 */
class WizardRenderView {
    /** @var type Description*/
    private $design;
    private $option;
    
    public function __construct(\app\cwf\vsla\design\WizardView $wizardView, \app\cwf\vsla\render\WizardViewOptions $wOptions) {
        $this->design = $wizardView;
        $this->option = $wOptions;
        $this->design->option = $wOptions;
        WizardHelper::wizData($this->design);
    }
    
    public function getHeader() {
        return WizardHelper::wizHeader($this->design);
    }
    
    public function getWizardView() {
        return OutputHelper::output_CwFrameworkType($this->design);
    }
}

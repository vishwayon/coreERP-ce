<?php

namespace app\cwf\vsla\render;

class FormRenderView {

    /** @var \app\cwf\vsla\design\FormView */
    protected $design;

    /** @var FormViewOptions */
    protected $option;
    public $formafterload;

    public function __construct(\app\cwf\vsla\design\CwFrameworkType $design, FormViewOptions $option) {
        $this->design = $design;
        $this->option = $option;
        if (property_exists($design, 'afterLoadEvent')) {
            $this->formafterload = $design->afterLoadEvent;
        }
    }

    public function getHeader() {
        return $this->design->header;
    }

    public function getForm() {
        $this->design->formName = $this->option->xmlViewPath;
        $this->design->formParams = $this->option->params;
        $this->design->accessLevel = $this->option->accessLevel;
        return OutputHelper::output_CwFrameworkType($this->design);
    }

    public function getDMFileForm() {
        $this->design->formName = $this->option->xmlViewPath;
        $this->design->formParams = $this->option->params;
        $this->design->accessLevel = $this->option->accessLevel;
        if ($this->design->dmFilesEnabled) {
            return FormHelper::output_DMFile_form($this->design->dmFiles);
        } else {
            return '';
        }
    }

    public function getProperty($propName) {
        return $this->design->$propName;
    }

}

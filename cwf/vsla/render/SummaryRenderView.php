<?php

namespace app\cwf\vsla\render;

/**
 * Description of SummaryRenderView
 *
 * @author dev
 */
class SummaryRenderView extends FormRenderView {

    public function getForm() {
        $this->design->formName = $this->option->xmlViewPath;
        $this->design->formParams = $this->option->params;
        $this->design->accessLevel = $this->option->accessLevel;
        return OutputHelper::output_SUMMARY_VIEW($this->design);
    }

}

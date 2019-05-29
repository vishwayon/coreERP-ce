<?php

namespace app\cwf\vsla\design;

class AllocView extends FormView {
    public $width = '800px';
    public function getType() {
        return self::ALLOC_VIEW;
    }
}
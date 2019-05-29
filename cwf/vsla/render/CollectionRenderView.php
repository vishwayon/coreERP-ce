<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\render;
use app\cwf\vsla\security\SessionManager;
use yii\helpers\Html;
include_once '../cwf/fwShell/models/MenuTree.php';


/**
 * Description of CollectionRenderView
 *
 * @author girish
 */
class CollectionRenderView {
    /** @var \app\cwf\vsla\design\CollectionDesignView */
    private $design;
    /** @var CollectionViewOptions */
    private $option;
    
    
    public function __construct(\app\cwf\vsla\design\CollectionDesignView $design, CollectionViewOptions $option) {
        $this->design = $design;
        $this->option = $option;
        $this->design->option = $option;
    }
    
    public function getCollectionView() {
        return OutputHelper::output_CwFrameworkType($this->design);
    }
    
}

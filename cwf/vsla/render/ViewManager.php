<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\render;

/**
 * Description of ViewManager
 *
 * @author girish
 */
class ViewManager {

    /** constructs a filled collection view ready for rendering
     * @param \app\cwf\vsla\render\CollectionViewOptions $viewOption
     * @return \app\cwf\vsla\render\CollectionRenderView
     */
    public static function getCompiledCollectionView(CollectionViewOptions $viewOption, $design) {
        $render = new CollectionRenderView($design, $viewOption);
        return $render;
    }

    public static function getCompiledCollectionDataView(CollectionViewOptions $viewOption, $filters, $design) {
        $design->option = $viewOption;
        return CollectionHelper::getCollection($design, $filters);
    }

    public static function getCompiledFormView(FormViewOptions $viewOption, $design) {
        $render = new FormRenderView($design, $viewOption);
        return $render;
    }

    public static function getCompiledAllocView(FormViewOptions $viewOption, $design) {
        $viewOption->accessLevel = 3;
        $render = new FormRenderView($design, $viewOption);
        return $render;
    }

    public static function getCompiledSummaryView(FormViewOptions $viewOption, $design) {
        $render = new SummaryRenderView($design, $viewOption);
        return $render;
    }

}

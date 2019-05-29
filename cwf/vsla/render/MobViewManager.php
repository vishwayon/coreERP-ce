<?php

namespace app\cwf\vsla\render;

/**
 * Description of MobViewManager
 *
 * @author dev
 */
class MobViewManager {

    public static function getCompiledCollectionView(CollectionViewOptions $viewOption, $design) {
        $design->option = $viewOption;
        $render = MobViewHelper::output_COLLECTION_VIEW($design, $viewOption);
        return $render;
    }

    public static function getCompiledCollectionDataView(CollectionViewOptions $viewOption, $filters, $design) {
        $design->option = $viewOption;
        $render = MobCollectionHelper::getCollection($design, $filters);
        return $render;
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

}

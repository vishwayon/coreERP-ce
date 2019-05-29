<?php

namespace app\cwf\sys\csvimport;

class ImportBase {
    
    /** @var ImportItem **/
    protected $masterItem;
    
    public function __construct(ImportItem $mItem) {
        $this->masterItem = $mItem;
    }
    
    public function getBO() {
        $xBo = new \app\cwf\vsla\xmlbo\XboBuilder($this->masterItem->boPath);
        $inParam = [ $xBo->boparser->bometa->controlTable->primaryKey => '-1', 'docType' => ''];
        $boInst = $xBo->buildBO($inParam);
        $sdata = serialize($boInst);
        \yii::$app->cache->set($boInst['__instanceid'], $sdata);
        return $boInst;
    }
    
    public function setBO($dataRow) {
        $xBo = new \app\cwf\vsla\xmlbo\XboBuilder($this->masterItem->boPath);
        $inParam = [ $xBo->boparser->bometa->controlTable->primaryKey => '-1', 'docType' => ''];
        $boInst = $xBo->buildBO($inParam);
    }
    
    public function validate() {
        
    }
    
    public function save() {
        
    }
}

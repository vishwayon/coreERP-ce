<?php

namespace app\cwf\vsla\dbd {
    include_once 'dbdparts.php';

    class dbdparser {

        public $dbdxml;

        /** @var cWidget[] * */
        public $widgets;
        public $modulepath;

        public function __construct($xwidget, $modulepath) {
            $this->dbdxml = $xwidget;
            $this->modulepath = $modulepath;
            if ($xwidget != NULL && $xwidget != '') {
                $this->init();
            } else {
                $this->getUserWidgets();
            }
        }

        private function init() {
            $cnt = 0;
            foreach ($this->dbdxml->widgets->widget as $xwidget) {
                $cwidget = $this->getWidget($xwidget);
                if ($cwidget) {
                    $cwidget->srno = $cnt;
                    $cnt++;
                    $this->widgets[$cwidget->id] = $cwidget;
                }
            }
            foreach ($this->dbdxml->widgets->customWidget as $xcwidget) {
                $cwidget = $this->getCustomWidget($xcwidget);
                if ($cwidget) {
                    $cwidget->srno = $cnt;
                    $cnt++;
                    $this->widgets[$cwidget->id] = $cwidget;
                }
            }
            foreach ($this->dbdxml->widgets->twigWidget as $xcwidget) {
                $cwidget = $this->getTwigWidget($xcwidget);
                if ($cwidget) {
                    $cwidget->srno = $cnt;
                    $cnt++;
                    $this->widgets[$cwidget->id] = $cwidget;
                }
            }
        }

        private function getUserWidgets() {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select a.widget_id,b.widget_name,b.widget_path, 
                                    b.widget_type,b.widget_size from sys.user_widget_access a 
                                    inner join sys.widget b on a.widget_id = b.widget_id 
                                     where a.user_id = :puser_id');
            $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
            $dt_widgets = \app\cwf\vsla\data\DataConnect::getData($cmm);
            $cnt = 0;
            $temp = [];
            foreach ($dt_widgets->Rows() as $wdgt) {
                $cwidget = new cWidget();
                //$cwidget->srno = $cnt++;
                $cwidget->id = (string) $wdgt['widget_id'];
                $cwidget->name = (string) $wdgt['widget_name'];
                $cwidget->path = (string) $wdgt['widget_path'];
                $cwidget->size = (string) $wdgt['widget_size'];
                switch ($wdgt['widget_type']) {
                    case 'normal':
                        $cwidget->widget = $this->getChart($cwidget->path);
                        $cwidget->seriesCount = count($cwidget->widget->series);
                        $this->getWidgetProperties($cwidget);
                        break;
                    case 'twig':
                        $this->getWidgetProperties($cwidget);
                        $temppath = $cwidget->path . '.xml';
                        $xroot = simplexml_load_file($temppath);
                        $cwidget->path = (string) $xroot->widgetPath;
                        $cwidget->isTwigWidget = TRUE;
                        break;
                    case 'custom':
                        $this->getWidgetProperties($cwidget);
                        $cwidget->isCustomWidget = TRUE;
                        break;
                    default :
                        break;
                }
                $temp[$cwidget->id] = $cwidget;
            }
            foreach ($temp as &$twidget) {
                if ($twidget->size != 'full') {
                    $twidget->srno = $cnt++;
                    $this->widgets[$twidget->id] = $twidget;
                }
            }
            foreach ($temp as &$twidget) {
                if ($twidget->size == 'full') {
                    $twidget->srno = $cnt++;
                    $this->widgets[$twidget->id] = $twidget;
                }
            }
        }

        private function getWidgetProperties(&$cwidget) {
            $temppath = $cwidget->path . '.xml';
            $xroot = simplexml_load_file($temppath);
            if (isset($xroot->clientJsCode)) {
                $cwidget->clientJsCode = '@app/' . (string) $xroot->clientJsCode;
            }
            if (isset($xroot->widgetMethod)) {
                $cwidget->widgetMethod = (string) $xroot->widgetMethod;
            }
            if (isset($xroot->text)) {
                $cwidget->customText = (string) $xroot->text;
            }
        }

        private function getWidget($wxml) {
            $cwidget = new cWidget();
            $cwidget->id = (string) $wxml['id'];
            $cwidget->path = (string) $wxml->path;
            $cwidget->size = (string) $wxml->size;
            $cwidget->widget = $this->getChart($cwidget->path);
            $cwidget->seriesCount = count($cwidget->widget->series);
            if (isset($wxml->clientJsCode)) {
                $cwidget->clientJsCode = '@app/' . (string) $wxml->clientJsCode;
            }
            if (isset($wxml->widgetMethod)) {
                $cwidget->widgetMethod = (string) $wxml->widgetMethod;
            }
            return $cwidget;
        }

        private function getChart($path) {
            $chart = new cChart();
            $temppath = $path . '.xml';
            $xroot = simplexml_load_file($temppath);
            $chart->id = (string) $xroot['id'];
            $chart->title = (string) $xroot->title;
            $chart->widgetType = (string) $xroot->widgetType;
            $chart->codeBehindClassName = '';
            if ($xroot->codeBehind) {
                $chart->codeBehindClassName = (string) $xroot->codeBehind->className;
            }
            $chart->series = array();
            foreach ($xroot->series as $srs) {
                $chart->series[((string) $srs['id'])] = $this->getSeries($srs);
            }
            return $chart;
        }

        private function getSeries($xroot) {
            $series = new cSeries();
            $series->id = (string) $xroot['id'];
            $series->seriesType = (string) $xroot->seriesType;
            $series->label = (string) $xroot->label;
            $series->sql = $xroot->sql;
            $series->xField = (string) $xroot->xField;
            $series->yField = (string) $xroot->yField;
            if ($series->seriesType == 'grid') {
                foreach ($xroot->displayFields->displayField as $dfld) {
                    $temp = new displayField();
                    $temp->columnName = (string) $dfld['columnName'];
                    $temp->displayName = (string) $dfld['displayName'];
                    $temp->format = isset($dfld['format']) ? (string) $dfld['format'] : null;
                    $series->displayFields[$temp->columnName] = $temp;
                }
            }

            if (isset($xroot->options)) {
                foreach ($xroot->options->children() as $opt) {
                    $series->options[$opt->getName()] = (string) $opt;
                }
            }
            return $series;
        }

        private function getCustomWidget($wxml) {
            $cwidget = new cWidget();
            $cwidget->id = (string) $wxml['id'];
            $cwidget->path = '';
            $cwidget->title = (string) $wxml->title;
            $cwidget->size = (string) $wxml->size;
            if (isset($wxml->clientJsCode)) {
                $cwidget->clientJsCode = '@app/' . $this->modulepath . '/' . (string) $wxml->clientJsCode;
            }
            if (isset($wxml->widgetMethod)) {
                $cwidget->widgetMethod = (string) $wxml->widgetMethod;
            }
            $cwidget->isCustomWidget = TRUE;
            $cwidget->customText = isset($wxml->text) ? (string) $wxml->text : '';
            return $cwidget;
        }

        private function getTwigWidget($wxml) {
            $cwidget = new cWidget();
            $temppath = (string) $wxml->path . '.xml';
            $xroot = simplexml_load_file($temppath);
            $cwidget->id = (string) $wxml['id'];
            $cwidget->title = (string) $wxml->title;
            $cwidget->path = (string) $xroot->widgetPath;
            $cwidget->size = (string) $wxml->size;
            if (isset($wxml->clientJsCode)) {
                $cwidget->clientJsCode = '@app/' . $this->modulepath . '/' . (string) $wxml->clientJsCode;
            }
            if (isset($wxml->widgetMethod)) {
                $cwidget->widgetMethod = (string) $wxml->widgetMethod;
            }
            $cwidget->isTwigWidget = TRUE;
            $cwidget->customText = isset($wxml->text) ? (string) $wxml->text : '';
            return $cwidget;
        }

    }

}


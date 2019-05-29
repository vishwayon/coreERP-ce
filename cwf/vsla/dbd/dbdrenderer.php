<?php

namespace app\cwf\vsla\dbd {

    //include 'dbdparts.php';

    use app\cwf\vsla\data\DataConnect;
    use app\cwf\vsla\utils\FormatHelper;

    class dbdrenderer {

        /** @var dbdparser * */
        public $dbdparser;

        /** @var plot[] * */
        public $plots = array();
        public $dbdrender = '';

        public function __construct($parser) {
            $this->dbdparser = $parser;
            $this->init();
        }

        private function init() {
            if (count($this->dbdparser->widgets) == 0) {
                $this->dbdrender = '';
            } else {
                $this->dbdrender = '<div id="cDashboard">';
                $cnt = 0;
                foreach ($this->dbdparser->widgets as $cwidget) {
                    if ($cwidget->isCustomWidget) {
                        $this->setCustomWidget($cwidget, $cnt);
                    } else if ($cwidget->isTwigWidget) {
                        $this->setTwigWidget($cwidget, $cnt);
                    } else {
                        $this->setWidget($cwidget, $cnt);
                    }
                    $cnt++;
                }
                $this->dbdrender .= '</div>';
            }
        }

        private function setWidget(cWidget $cwidget, $cnt) {
            $headericon = '';
            $myplot = new plot();
            $myplot->placeholder = 'widget_' . $cwidget->id;
            $headertyle = 'dbwidgetheadcolorinverse';
            if ($cwidget->widget->widgetType == NULL) {
                $myplot->plotType = 'chart';
                $myplot->data = $this->setData($cwidget);
                $headericon = "fa fa-bar-chart fa-fw";
            } else if ($cwidget->widget->widgetType == 'grid') {
                $myplot->plotType = 'grid';
                $myplot->plotid = 'thelist_' . $cnt;
                $myplot->data = $this->setGrid($cwidget, $cnt);
                $headericon = "fa fa-table fa-fw";
//                $headertyle = 'dbwidgetheadcolor';
            } else if ($cwidget->widget->widgetType == 'pie') {
                $myplot->plotType = 'pie';
                $myplot->data = $this->setPie($cwidget);
                $myplot->options = ['series' => ['pie' => ['show' => true]]];
                $headericon = "fa fa-pie-chart fa-fw";
            } else if ($cwidget->widget->widgetType == 'stack') {
                $myplot->plotType = 'stack';
                $myplot->data = $this->setStack($cwidget);
                $myplot->options = ['series' =>
                    ['stack' => true,
                        'bars' => ['show' => true, 'barWidth' => 0.6]]];
                $headericon = "fa fa-bar-chart fa-fw";
            }
            $csize = 'col-md-6';
            if (isset($cwidget->size) && (string) $cwidget->size === 'full') {
                $csize = 'col-md-12';
                $myplot->size = 'full';
            }

            $this->dbdrender .= '<div class="' . ($csize) . '">'
                    . '<div class="col-md-12 dbwidgetmain"><div class="col-md-12 nopadding dbwidgetheaderdiv ' . $headertyle . '">'
                    . '<div class="col-md-11 nopadding"><h4 class="dbwidgetheader" style="margin: 0 0 5px 0;padding: 0px;">'
                    . '<span class="' . $headericon . '" style="margin-right:5px;font-size:14px;"></span>'
                    . $cwidget->widget->title . '</h4></div>'
                    . '<div class="col-md-1 nopadding"><button class="btn ' . $headertyle . '" title="Fullscreen" '
                    . 'style="float:right;padding:0;float: right;line-height:12px;font-size:12px;" onclick="coreWebApp.maxWidget(\'widget_'
                    . $cwidget->id . '\',\'' . $cwidget->widget->title . '\',\'' . $cwidget->widget->widgetType . '\''
                    . ($cwidget->widget->widgetType == 'grid' ? ',\'' . $myplot->plotid . '\'' : '') . ');">'
                    . '<span class="glyphicon glyphicon-resize-full" aria-hidden="true"></span></button></div>'
                    . '</div>';
            if ($cwidget->widget->widgetType == 'grid') {
                $this->dbdrender .= '<div class="col-md-12" id="widget_' . $cwidget->id
                        . ((isset($cwidget->widgetMethod) && $cwidget->widgetMethod !== '') ?
                        ('" widgetMethod="' . $cwidget->widgetMethod) : '')
                        . '" placeholder="widget_' . $cwidget->id
                        . '"></div></div>';
            } else {
                $this->dbdrender .= '<div style="padding:10px;"><div class="col-md-12" style="margin: 10px 0;" id="widget_' . $cwidget->id
                        . ((isset($cwidget->widgetMethod) && $cwidget->widgetMethod !== '') ?
                        ('" widgetMethod="' . $cwidget->widgetMethod) : '')
                        . '" placeholder="widget_' . $cwidget->id
                        . '"></div></div></div>';
            }

            if (isset($cwidget->clientJsCode) && $cwidget->clientJsCode != '') {
                $this->dbdrender .= '<script src="' . \app\cwf\vsla\utils\ScriptHelper::registerScript($cwidget->clientJsCode) . '"></script>';
            }
            $this->dbdrender .= '</div>';
            array_push($this->plots, $myplot);
        }

        private function setCustomWidget(cWidget $cwidget, $cnt) {
            $myplot = new plot();
            $myplot->isCustom = TRUE;
            $myplot->placeholder = 'widget_' . $cwidget->id;
            $csize = 'col-md-6';
            if (isset($cwidget->size) && (string) $cwidget->size === 'full') {
                $csize = 'col-md-12';
                $myplot->size = 'full';
            }
            $ctitle = '';
            if ($cwidget->widget != NULL) {
                $ctitle = $cwidget->widget->title;
            }
            $this->dbdrender .= '<div class="' . ($csize) . '">'
                    . '<div class="col-md-12 dbwidgetmain"><div class="col-md-12" '
                    . 'style="display: table-cell; vertical-align: middle;border-bottom:1px solid #c2c2c2;padding:2px 5px 0;box-shadow: 0 -2px 0 rgba(0, 0, 0, 0.05) inset;">'
                    . '<div class="col-md-11 nopadding"><h4 class="dbwidgetheader" style="margin: 0 0 5px 0;padding: 0px;">'
                    . '<span class="fa fa-table fa-fw" style="margin-right:5px;font-size:14px;"></span>'
                    . $cwidget->title . '</h4></div>'
                    . '<div class="col-md-1 nopadding"><button class="btn" title="Fullscreen" '
                    . 'style="float:right;padding:0;background-color: white;float: right;" onclick="coreWebApp.maxWidget2(\'widget_'
                    . $cwidget->id . '\',\'' . $cwidget->title . '\');">'
                    . '<span class="glyphicon glyphicon-resize-full" aria-hidden="true" style="color:darkgray;"></span></button></div>'
                    . '</div>';
            $this->dbdrender .= '<div class="col-md-12" id="widget_' . $cwidget->id
                    . '" placeholder="widget_' . $cwidget->id . '" customwidget="true">'
                    . $cwidget->customText . '</div></div>';
            if (isset($cwidget->clientJsCode) && $cwidget->clientJsCode != '') {
                $this->dbdrender .= '<script src="' . \app\cwf\vsla\utils\ScriptHelper::registerScript($cwidget->clientJsCode) . '"></script>';
            }
            $this->dbdrender .= '</div>';
            array_push($this->plots, $myplot);
        }

        private function setTwigWidget(cWidget $cwidget, $cnt) {
            $myplot = new plot();
            $myplot->isTwig = TRUE;
            $myplot->placeholder = 'widget_' . $cwidget->id;
            $csize = 'col-md-6';

            if (isset($cwidget->size) && (string) $cwidget->size === 'full') {
                $csize = 'col-md-12';
                $myplot->size = 'full';
            }
            // create report class instance
            // create model
            $widgetClass = '\\app' . str_replace("/", "\\", $cwidget->path);
            $widgetClassInstance = new $widgetClass();
            $widgetClassInstance->init();
            // get twig file
            $widgetTwigPath = '@app' . $cwidget->path . '.twig';
//            // render (twig, model)
            $twigrpt = \Yii::$app->runAction('cwf/fwShell/main/twigwidget', ['path' => $widgetTwigPath, 'model' => $widgetClassInstance]);
            $cwidget->customText = $twigrpt;
//            $ctrl = new \app\cwf\fwShell\controllers\MainController();
//            $cwidget->customText = $ctrl->actionTwigwidget($widgetTwigPath, $widgetClassInstance);

            $this->dbdrender .= '<div class="' . ($csize) . '">'
                    . '<div class="col-md-12 dbwidgetmain"><div class="col-md-12" '
                    . 'style="display: table-cell; vertical-align: middle;border-bottom:1px solid #c2c2c2;padding:2px 5px 0;box-shadow: 0 -2px 0 rgba(0, 0, 0, 0.05) inset;">'
                    . '<div class="col-md-11 nopadding"><h4 class="dbwidgetheader" style="margin: 0 0 5px 0;padding: 0px;">'
                    . '<span class="fa fa-table fa-fw" style="margin-right:5px;font-size:14px;"></span>'
                    . $cwidget->title . '</h4></div>'
                    . '<div class="col-md-1 nopadding"><button class="btn" title="Fullscreen" '
                    . 'style="float:right;padding:0;background-color: white;float: right;" onclick="coreWebApp.maxWidget2(\'widget_'
                    . $cwidget->id . '\',\'' . $cwidget->title . '\');">'
                    . '<span class="glyphicon glyphicon-resize-full" aria-hidden="true" style="color:darkgray;"></span></button></div>'
                    . '</div>';
            $this->dbdrender .= '<div class="col-md-12" id="widget_' . $cwidget->id
                    . '" placeholder="widget_' . $cwidget->id . '" customwidget="true">'
                    . $cwidget->customText . '</div></div>';

            if (isset($cwidget->clientJsCode) && $cwidget->clientJsCode != '') {
                $this->dbdrender .= '<script src="' . \app\cwf\vsla\utils\ScriptHelper::registerScript($cwidget->clientJsCode) . '"></script>';
            }
            $this->dbdrender .= '</div>';
            array_push($this->plots, $myplot);
        }

        private function setData(cWidget $cwidget) {
            $allseries = array();
            $seriesdt = array();
            $maxdenominator = 1;
            $maxdesc = '';
            foreach ($cwidget->widget->series as $series) {
                $dt = $this->getData($series->sql, $series->id, $cwidget->widget->codeBehindClassName);
                $seriesdt[$series->id] = $dt;
                if (count($dt->Rows()) > 0) {
                    $ymax = \YaLinqo\Enumerable::from($dt->Rows())->max('$a==>$a["' . $series->yField . '"]');
                    $ydenominator = $this->getDenominator($ymax);
                    if ($ydenominator[1] > $maxdenominator) {
                        $maxdenominator = $ydenominator[1];
                        $maxdesc = $ydenominator[0];
                    }
                }
            }

            foreach ($cwidget->widget->series as $series) {
                $seriesdata = array();
                $data = array();
                $tempdt = $seriesdt[$series->id];
                foreach ($tempdt->Rows() as $row) {
                    $data[$row[$series->xField]] = ((double) $row[$series->yField]) / $maxdenominator;
                }
                $seriesdata['label'] = $series->label . ($maxdenominator != 1 ? (' (in ' . $maxdesc . ')') : '');
                $seriesdata['data'] = $data;
                $seriesdata[$series->seriesType] = $this->setSeriesOptions($series);
                if ($cwidget->seriesCount == 1) {
                    $seriesdata[$series->seriesType]['align'] = 'center';
                }
                if (array_key_exists('color', $seriesdata[$series->seriesType])) {
                    $seriesdata['color'] = $seriesdata[$series->seriesType]['color'];
                }
                array_push($allseries, $seriesdata);
            }
            return $allseries;
        }

        private function getDenominator($maxval) {
            if ($maxval <= 1000) {
                return ['', 1];
            } else if ($maxval > 1000 && $maxval <= 1000000) {
                return ['Thousands', 1000];
            } else if ($maxval > 1000000 && $maxval <= 1000000000) {
                return ['Millions', 1000000];
            } else if ($maxval > 1000000000) {
                return ['Billions', 1000000000];
            }
        }

        private function getData($sql, $series_id, $codeBehindClassName) {
            if ((string) $sql == '') {
                return '';
            }
            $cmm = \app\cwf\vsla\data\SqlParser::getSql($sql);
            $eventhandlerbaseinst = null;
            if ($codeBehindClassName != '') {
                $eventhandlerbaseinst = new $codeBehindClassName();
            }

            if ($eventhandlerbaseinst != NULL) {
                $eventhandlerbaseinst->initialise();
                $eventhandlerbaseinst->beforeFetch($series_id, $cmm->getParams());
            }

            $collection = DataConnect::getData($cmm);

            if ($eventhandlerbaseinst != NULL) {
                $eventhandlerbaseinst->afterFetch($series_id, $collection);
            }

            return $collection;
        }

        private function setSeriesOptions(cSeries $series) {
            $seriesoptions = array();
            $seriesoptions['show'] = true;
            if ($series->seriesType == 'bars') {
                $seriesoptions['barWidth'] = 0.15;
            }
            //$seriesoptions['align']='center';
            if ($series->options) {
                foreach ($series->options as $key => $value) {
                    $seriesoptions[$key] = $value;
                }
            }
            return $seriesoptions;
        }

        private function setPie($cwidget) {
            $seriesdata = array();
            $data = array();
            foreach ($cwidget->widget->series as $series) {
                $dt = $this->getData($series->sql, $series->id, $cwidget->widget->codeBehindClassName);
                foreach ($dt->Rows() as $row) {
                    array_push($data, ["label" => $row[$series->xField],
                        "data" => (double) $row[$series->yField]]);
                }
                $seriesdata['data'] = $data;
            }
            return $seriesdata;
        }

        private function setStack($cwidget) {
            $allseries = array();
            foreach ($cwidget->widget->series as $series) {
                $seriesdata = array();
                $data = array();
                $dt = $this->getData($series->sql, $series->id, $cwidget->widget->codeBehindClassName);
                foreach ($dt->Rows() as $row) {
                    $data[$row[$series->xField]] = (double) $row[$series->yField];
                }
                $seriesdata['data'] = $data;
                $seriesdata[$series->seriesType] = $this->setSeriesOptions($series);
                array_push($allseries, $seriesdata);
            }
            return $allseries;
        }

        private function setGrid($cwidget, $cnt) {
            $ser = reset($cwidget->widget->series);
            $cdt = $this->getData($ser->sql, $ser->id, $cwidget->widget->codeBehindClassName);

            $collectiondata = '<table id="thelist_' . $cnt . '" class="row-border hover thelist"><thead id="dataheader"><tr>';

            foreach ($ser->displayFields as $colDef) {
                foreach ($cdt->getColumns() as $value) {
                    if ($colDef->columnName == $value->columnName) {
                        if ($colDef->format == "Amount" || $colDef->format == "Number") {
                            $collectiondata .= "<th style='text-align: center;'>" . $colDef->displayName . "</th>";
                        } else {
                            $collectiondata .= "<th>" . $colDef->displayName . "</th>";
                        }
                    }
                }
            }

            $collectiondata .= '</tr></thead><tbody>';
            foreach ($cdt->Rows() as $rw) {
                $collectiondata .= "<tr>";
                foreach ($ser->displayFields as $colDef) {
                    foreach ($rw as $k => $v) {
                        if ($colDef->columnName == $k) {
                            if ($colDef->format != null) {
                                // apply formats if available
                                if ($colDef->format == "Amount") {
                                    $collectiondata .= '<td style="text-align: right;">' . FormatHelper::FormatAmt($v) . "</td>";
                                }
                                if ($colDef->format == "Number") {
                                    $collectiondata .= '<td style="text-align: right;">' . FormatHelper::FormatNumber($v) . "</td>";
                                }
                                if ($colDef->format == "Date") {
                                    if ($v == '1970-01-01') {
                                        $collectiondata .= '<td style="text-align: left;" data-order="' . strtotime($v) . '"></td>';
                                    } else {
                                        $collectiondata .= '<td style="text-align: left;" data-order="' . strtotime($v) . '">' . FormatHelper::FormatDateForDisplay($v) . "</td>";
                                    }
                                }
                            } else {
                                $collectiondata .= "<td>$v</td>";
                            }
                        }
                    }
                }
                $collectiondata .= '</tr>';
            }
            $collectiondata .= '</tbody></table><table id="header-fixed"></table>';
            return $collectiondata;
        }

    }

}

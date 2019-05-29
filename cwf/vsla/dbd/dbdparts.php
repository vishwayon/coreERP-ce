<?php
namespace app\cwf\vsla\dbd;

    class dashboard{
        /** @var cWidget[] **/
        public $widgets;
    }
    
    class cWidget{
        public $srno;
        public $chart_id;
        public $title;
        public $path;
        public $size;
        /** @var cChart **/
        public $widget;
        public $seriesCount=0;
        public $widgetType;
        public $clientJsCode;
        public $widgetMethod;
        public $isCustomWidget=false;        
        public $isTwigWidget=false;
        public $customText;
    }
    
    class cChart{
        public $id;
        /** @var cSeries[] **/
        public $series;
    }
    
    class cSeries{
        public $id;
        public $label;
        public $seriesType;
        public $sql;
        public $xField;
        public $yField;
        /** @var displayField[] **/
        public $displayFields;
        public $options=array();
    }
    
    class plot{
        public $placeholder;
        public $plotid = '';
        /** @var plotdata **/
        public $data;
        public $options=array();
        public $plotType;
        public $widgetMethod;
        public $isCustom = false;
        public $isTwig = false;
        public $size;
    }

    class plotdata{
        public $color;
        public $label;
        public $data;
        public $lines;
        public $bars;
        public $points;
        public $xaxis;
        public $yaxis;
        public $clickable;
        public $hoverable;
        public $shadowSize;
        public $highlightColor;
    }
    
    class pointdata{
        public $x;
        public $y;
    }
    
    class seriesOptions{
        public $show;
        public $lineWidth;
        public $fill;
        public $fillColor;
    }
    
    class seriesType{
        const LINES="lines";
        const BARS="bars";
        const POINTS="points";
        const PIE="pie";
    }
    
    class displayField{
        public $displayName;
        public $columnName;
        public $format;
    }

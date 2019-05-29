<?php

namespace app\cwf\vsla\ui {

    /*
      //    class ensectiontype extends SplEnum{
      //        const __default=1;
      //        const controlsection=1;
      //        const transection=2;
      //    }
      //
      //    class eneditMode extends SplEnum{
      //        const __default=2;
      //        const add=1;
      //        const edit=2;
      //        const delete=4;
      //        const viewonly=8;
      //    }
      //
      //    class eninputtype extends SplEnum{
      //        const textbox=0;
      //        const datepicker=1;
      //        const numerictextbox=2;
      //        const radiobutton=3;
      //        const checkbox=4;
      //        const simplecombo=5;
      //        const Smartcombo=51;
      //    }
      //
      //    class endisplaytype extends SqlEnum{
      //        const inline=0;
      //        const tabs=1;
      //    }
     */

    class viewpartsection {

        public $header;
        public $sectionType;
        public $dataProperty;
        public $dataRelation = null;
        public $editMode;
        public $addRowEvent;

        /** @var viewpartfield[]  */
        public $fields = array();

        /** @var viewpartsection[] */
        public $sections = array();
        public $fc_fields = array();
        public $options;
        public $mdata_events;

    }

    class viewpartfield {

        public $id;
        public $label;
        public $type;
        public $inputType;
        public $editMode;
        public $options;
        public $htmlInputType;
        public $optional;
        public $maxLength;
        public $calculated;
        public $formula;
        public $size;
        public $isCustom;
        public $defaultValue;
        public $mdata_events;
        public $nolabel = false;

        function gethtmlinputtype() {
            $result;
            switch ($this->inputtype) {
                case 1://eninputtype::datepicker:
                    $result = 'text';
                    break;
                case 3://eninputtype::radiobutton:
                    $result = 'radio';
                    break;
                case 4://eninputtype::checkbox:
                    $result = 'checkbox';
                    break;
                case 5://eninputtype::simplecombo:
                    $result = 'select';
                    break;
                case 6:
                    $result = 'blank';
                    break;
                default:
                    $result = 'text';
                    break;
            }
            return $result;
        }

    }

    class viewpartsmartcombofield extends viewpartfield {

        public $dataMemmber;
        public $displayMember;
        public $namedLookup;

    }

    class viewpartsimplecombofield extends viewpartfield {

        public $items = array();

    }

    class viewpartFCfield extends viewpartsmartcombofield {

        public $exchRateField;

    }

    class column {

        public $header;
        public $dataProperty;

    }

    class smartcombocolumn extends column {

        public $valueMember;
        public $displayMember;
        public $namedLookup;

    }

    class group {

        public $header;
        public $field;

    }

    class viewpartdatagrid {

        public $columns = array();
        public $groups = array();
        public $displayType;

    }

    class wizStep {

        public $id;
        public $header;
        public $xroot;
        public $path;

        /** @var wizSection[] * */
        public $wizSection;
        public $stepWizData;
        public $final = false;
        public $finalPath;
        public $newparams = NULL;

    }

    class wizSection {

        public $id;
        public $sql;

        /** @var sqlParam[] * */
        public $sqlParams;
        public $header;
        public $wizData;
        public $wizType;
        public $bindMethod = '';
        public $renderEvents = [];
        public $cnType;
        public $keyField;

        /** @var wizDisplayField[] * */
        public $fields;

    }

    class wizDisplayField {

        public $columnName;
        public $displayName;
        public $editMode;
        public $fieldName;
        public $size;
        public $sizenum;
        public $defaultValue;

        /** @var viewpartfield * */
        public $viewField;

    }

    class wizSqlParam {

        public $name;
        public $step;
        public $property;

    }

    class mDataEvent {

        public $field_id;
        public $event;
        public $method;

    }

}

<?php

namespace app\cwf\vsla\ui {

    include_once '../cwf/vsla/design/CommonTypes.php';

    use app\cwf\vsla\data\SqlCommand;
    use app\cwf\vsla\data\DataConnect;
    use app\cwf\vsla\security\AccessManager;

    class treeviewparser {

        public $header;
        public $xtreeview;
        public $collectionName;
        public $parentEditForm, $childEditForm;
        public $dtcollection, $dtparent, $dtchildren;
        public $displayFields, $parentDisplayFields, $childDisplayFields;
        public $editNotAllowedField, $parentEditNotAllowedField, $childEditNotAllowedField;
        public $keyField, $parentKeyField, $childKeyField;
        public $relationKeyField, $parentParentKey, $parentChildKey;
        public $parent_access_level = -1, $child_access_level = -1;
        public $parent_bo_id = '', $child_bo_id = '';
        public $keyDocType;
        public $sql;
        public $parentisNewAllowed, $childisNewAllowed;
        private $parentcollviewparser;
        private $isExtended = FALSE;
        public $searchbox = NULL;
        private $connectionType = DataConnect::COMPANY_DB;
        public $formType;

        function __construct($rootview, $viewname, $modulePath, $filters) {
            $this->xtreeview = $rootview;
            $this->collectionName = $viewname;
            $this->header = (string) $rootview->header;
            if ($filters !== null) {
                $this->filters = $filters;
            } else {
                if (isset($this->xtreeview['type']) && (string) $this->xtreeview['type'] === 'Document') {
                    $this->filters = array();
                    $this->filters['docstatus'] = 0;
                }
            }
            $this->modulePath = '/' . $modulePath . '/form';
            if (isset($this->xtreeview['extends'])) {
                $this->isExtended = TRUE;
                $parentview = simplexml_load_file(((string) $this->xtreeview['extends']) . '.xml');
                $this->parentcollviewparser = new collectionviewparser($parentview, $viewname, $modulePath, $filters);
            }
            if ($this->xtreeview->collectionSection->connectionType) {
                if ($this->xtreeview->collectionSection->connectionType->mainDB) {
                    $this->connectionType = DataConnect::MAIN_DB;
                }
            }
            if (isset($this->xtreeview['type'])) {
                $this->formType = (string) $this->xtreeview['type'];
            } else {
                $this->formType = 'Master';
            }

            if (isset($this->xtreeview->search)) {
                $this->setSearchbox();
            }
            if (isset($this->xtreeview->relationKeyField)) {
                $this->relationKeyField = (string) $this->xtreeview->relationKeyField;
            }
            if (isset($this->xtreeview->parentSection->parentKeyField)) {
                $this->parentParentKey = (string) $this->xtreeview->parentSection->parentKeyField;
            }
            if (isset($this->xtreeview->parentSection->childKeyField)) {
                $this->parentChildKey = (string) $this->xtreeview->parentSection->childKeyField;
            }
            if (isset($this->xtreeview->parentSection['editView'])) {
                $this->parentEditForm = (string) $this->xtreeview->parentSection['editView'];
                if (isset($this->xtreeview->parentSection->editNotAllowed)) {
                    $this->parentEditNotAllowedField = (string) $this->xtreeview->parentSection->editNotAllowed['field'];
                }
            }
            if (isset($this->xtreeview->childSection['editView'])) {
                $this->childEditForm = (string) $this->xtreeview->childSection['editView'];
                if (isset($this->xtreeview->childSection->editNotAllowed)) {
                    $this->parentEditNotAllowedField = (string) $this->xtreeview->childSection->editNotAllowed['field'];
                }
            }
            $this->get_formoptions($modulePath, 'parent');
            $this->get_formoptions($modulePath, 'child');

            $this->initsect('parent');
            if (isset($this->xtreeview->childSection)) {
                $this->initsect('child');
            }
        }

        function setSearchbox() {
            $xfield = $this->xtreeview->search;
            $field = new \app\cwf\vsla\design\FormField();
            $fattrs = $xfield->attributes();
            $field->id = (string) $fattrs->id;
            $field->label = (string) $fattrs->label;
            $field->type = (string) $fattrs->type;
            $field->control = (string) $fattrs->control;
            if (isset($fattrs->size)) {
                $field->size = (string) $fattrs->size;
            }
            $xlookup = $xfield->lookup;
            $lookup = new \app\cwf\vsla\design\FieldLookupType();
            $lookup->valueMember = (string) $xlookup->valueMember;
            $lookup->displayMember = (string) $xlookup->displayMember;
            $lookup->namedLookup = (string) $xlookup->namedLookup;
            if (isset($xlookup->filter)) {
                $lookup->filter = (string) $xlookup->filter;
            }
            if (isset($xlookup->filterEvent)) {
                $lookup->filterEvent = (string) $xlookup->filterEvent;
            }
            $field->lookup = $lookup;
            $this->searchbox = $field;
        }

        function initsect($sect) {
            $this->setCollection($sect);
            $this->setDisplayFields($sect);
            $this->setKeyField($sect);
            $this->setEditNotAllowedField();
        }

        function setCollection($sect) {
            $sectdef = $sect . 'Section';
            $sectdt = 'dt' . strtolower($sect);
            $sectsql = strtolower($sect) . 'sql';
            if ($this->isExtended) {
                if (isset($this->xtreeview->$sectdef->Sql['method'])) {
                    if ((string) $this->xtreeview->$sectdef->Sql['method'] === 'Extends') {
                        $this->$sectsql = $this->parentcollviewparser->$sectsql;
                    } else if ((string) $this->xtreeview->$sectdef->Sql['method'] === 'Overrides') {
                        $this->$sectsql = $this->xtreeview->$sectdef->$sectsql;
                    }
                } else {
                    $this->$sectsql = $this->xtreeview->$sectdef->sql;
                }
            } else {
                $this->$sectsql = $this->xtreeview->$sectdef->sql;
                $this->$sectdt = $this->getCollection($this->$sectsql);
            }
        }

        function getCollection($sql) {
            $cmm = \app\cwf\vsla\data\SqlParser::getSql($sql);
            $cmm->setCommandText($cmm->getCommandText());
            $collection = DataConnect::getData($cmm, $this->connectionType);
            return $collection;
        }

        function setDisplayFields($sect) {
            $sectdef = $sect . 'Section';
            $sectfld = strtolower($sect) . 'DisplayFields';
            if ($this->isExtended) {
                if (isset($this->xtreeview->$sectdef->displayFields['method'])) {
                    if ((string) $this->xtreeview->$sectdef->displayFields['method'] === 'Extends') {
                        $this->$sectfld = $this->parentcollviewparser->displayFields;
                        $this->append_simplexml($this->$sectfld, $this->xtreeview->$sectdef->displayFields);
                    } else if ((string) $this->xtreeview->$sectdef->displayFields['method'] === 'Overrides') {
                        $this->$sectfld = $this->xtreeview->$sectdef->displayFields;
                    }
                } else {
                    $this->$sectfld = $this->parentcollviewparser->displayFields;
                }
            } else {
                $this->$sectfld = $this->xtreeview->$sectdef->displayFields;
            }
        }

        function setEditNotAllowedField() {
            if ($this->isExtended && isset($this->parentcollviewparser)) {
                if (isset($this->parentcollviewparser->editNotAllowedField)) {
                    $this->editNotAllowedField = $this->parentcollviewparser->editNotAllowedField;
                }
            } else {
                if (isset($this->xtreeview->collectionSection->editNotAllowed)) {
                    $this->editNotAllowedField = (string) $this->xtreeview->collectionSection->editNotAllowed['field'];
                }
            }
        }

        function setKeyField($sect) {
            $sectdef = $sect . 'Section';
            $sectfld = strtolower($sect) . 'KeyField';
            if ($this->isExtended && isset($this->parentcollviewparser)) {
                $this->$sectfld = $this->parentcollviewparser->keyField;
            } else {
                $this->$sectfld = (string) $this->xtreeview->$sectdef->keyField['id'];
            }
        }

        function append_simplexml(&$simplexml_to, $simplexml_from) {
            foreach ($simplexml_from->children() as $simplexml_child) {
                $simplexml_temp = $simplexml_to->addChild($simplexml_child->getName(), (string) $simplexml_child);
                foreach ($simplexml_child->attributes() as $attr_key => $attr_value) {
                    $simplexml_temp->addAttribute($attr_key, $attr_value);
                }
                $this->append_simplexml($simplexml_temp, $simplexml_child);
            }
        }

        function get_formoptions($mpath, $sect) {
            $frm = strtolower($sect) . 'EditForm';
            $sectfld = strtolower($sect) . 'KeyField';
            $secnew = strtolower($sect) . 'isNewAllowed';
            $secAL = strtolower($sect) . '_access_level';
            $secboid = strtolower($sect) . '_bo_id';
            $secparam = strtolower($sect) . 'NewParams';
            $lbl = strtolower($sect) . 'Label';
            if (isset($this->$frm)) {
                $cwFramework = simplexml_load_file('../' . $mpath . '/' . $this->$frm . '.xml');
                $boxml = $cwFramework->formView;
                $this->$secboid = (string) $boxml['id'];
                $this->$secAL = AccessManager::verifyAccess($this->$secboid);
                if (isset($boxml->keyField)) {
                    $this->$sectfld = (string) $boxml->KeyField;
                }
                if (isset($boxml->newDocEnabled)) {
                    $this->$secnew = TRUE;
                    $this->$lbl = isset($boxml->newDocEnabled['label']) ?
                            (string) $boxml->newDocEnabled['label'] : '';
                    foreach ($boxml->newDocEnabled->param as $param) {
                        $this->$secparam[(string) $param['name']] = (string) $param;
                    }
                } else {
                    $this->$secnew = FALSE;
                }
            } else {
                $this->$secnew = FALSE;
            }
        }

    }

}
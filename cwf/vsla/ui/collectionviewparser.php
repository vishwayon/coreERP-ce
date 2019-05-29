<?php

namespace app\cwf\vsla\ui {

    use app\cwf\vsla\data\DataConnect;
    use app\cwf\vsla\security\SessionManager;
    use app\cwf\vsla\security\AccessManager;

    class collectionviewparser {

        public $header;
        public $xCollectionView;
        public $collectionName;
        public $xEditForm, $modulePath;
        public $dtCollection;
        public $displayFields;
        public $editNotAllowedField;
        public $keyField;
        public $keyDocType;
        public $isNewAllowed;
        public $newType = 'normal';
        public $wizPath, $wizStep;
        public $access_level = -1;
        public $newParams;
        public $filters;
        public $sql;
        private $parentcollviewparser;
        private $isExtended = FALSE;
        private $connectionType = DataConnect::COMPANY_DB;
        public $formType, $bo_id = '';

        function __construct($rootview, $viewname, $modulePath, $filters) {
            $this->xCollectionView = $rootview->collectionView;
            $this->collectionName = $viewname;
            $this->header = (string) $this->xCollectionView->header;
            if ($filters !== null) {
                $this->filters = $filters;
            } else {
                if (isset($this->xCollectionView['type']) && (string) $this->xCollectionView['type'] === 'Document') {
                    $this->filters = array();
                    $this->filters['docstatus'] = 0;
                    $this->filters['from_date'] = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(SessionManager::getSessionVariable('year_begin'));
                    $this->filters['to_date'] = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(SessionManager::getSessionVariable('year_end'));
                }
            }
            $this->modulePath = '/' . $modulePath . '/form';
            if (isset($this->xCollectionView['extends'])) {
                $this->isExtended = TRUE;
                $parentview = simplexml_load_file(((string) $this->xCollectionView['extends']) . '.xml');
                $this->parentcollviewparser = new collectionviewparser($parentview, $viewname, $modulePath, $filters);
            }
            if ($this->xCollectionView->collectionSection->connectionType) {
                if ($this->xCollectionView->collectionSection->connectionType->mainDB) {
                    $this->connectionType = DataConnect::MAIN_DB;
                }
            }
            if (isset($this->xCollectionView['type'])) {
                $this->formType = (string) $this->xCollectionView['type'];
            } else {
                $this->formType = 'Master';
            }
            $this->xEditForm = (string) $this->xCollectionView['editView'];
            $this->get_formoptions($modulePath);
            $this->initialise();
        }

        function initialise() {
            $this->setCollection();
            $this->setDisplayFields();
            $this->setEditNotAllowedField();
        }

        function setCollection() {
            if ($this->isExtended) {
                if (isset($this->xCollectionView->collectionSection->sql['method'])) {
                    if ((string) $this->xCollectionView->collectionSection->sql['method'] === 'extends') {
                        $this->sql = $this->parentcollviewparser->sql;
                    } else if ((string) $this->xCollectionView->collectionSection->sql['method'] === 'Overrides') {
                        $this->sql = $this->xCollectionView->collectionSection->sql;
                    }
                } else {
                    $this->sql = $this->xCollectionView->collectionSection->sql;
                }
            } else {
                $this->sql = $this->xCollectionView->collectionSection->sql;
                $this->dtCollection = $this->getCollection($this->sql, $this->filters);
            }
        }

        function getCollection($sql, $filters) {
            $cmm = \app\cwf\vsla\data\SqlParser::getSql($sql);
            $cmm->setCommandText($this->buildCollectionQuery($cmm->getCommandText(), $filters));
            $collection = DataConnect::getData($cmm, $this->connectionType);
            return $collection;
        }

        function setDisplayFields() {
            if ($this->isExtended) {
                if (isset($this->xCollectionView->collectionSection->displayFields['method'])) {
                    if ((string) $this->xCollectionView->collectionSection->displayFields['method'] === 'extends') {
                        $this->displayFields = $this->parentcollviewparser->displayFields;
                        $this->append_simplexml($this->displayFields, $this->xCollectionView->collectionSection->displayFields);
                    } else if ((string) $this->xCollectionView->collectionSection->displayFields['method'] === 'overrides') {
                        $this->displayFields = $this->xCollectionView->collectionSection->displayFields;
                    }
                } else {
                    $this->displayFields = $this->parentcollviewparser->displayFields;
                }
            } else {
                $this->displayFields = $this->xCollectionView->collectionSection->displayFields;
            }
        }

        function setEditNotAllowedField() {
            if ($this->isExtended && isset($this->parentcollviewparser)) {
                if (isset($this->parentcollviewparser->editNotAllowedField)) {
                    $this->editNotAllowedField = $this->parentcollviewparser->editNotAllowedField;
                }
            } else {
                if (isset($this->xCollectionView->collectionSection->editNotAllowed)) {
                    $this->editNotAllowedField = (string) $this->xCollectionView->collectionSection->editNotAllowed['field'];
                }
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

        function get_formoptions($mpath) {
            $cwFramework = simplexml_load_file('../' . $mpath . '/' . $this->xEditForm . '.xml');
            $boxml = $cwFramework->formView;
            $this->bo_id = (string) $boxml['id'];
            $this->access_level = AccessManager::verifyAccess($this->bo_id);
            if (isset($boxml->keyField)) {
                $this->keyField = (string) $boxml->keyField;
            }
            if (isset($boxml->newDocEnabled)) {
                $this->isNewAllowed = TRUE;
                if ($boxml->newDocEnabled['wizard']) {
                    $this->newType = 'wizard';
                    $this->wizPath = (string) $boxml->newDocEnabled['wizard'];
                    $this->wizStep = (string) $boxml->newDocEnabled['step'];
                }
                $this->newParams['DocType'] = isset($boxml->newDocEnabled->docType) ?
                        (string) $boxml->newDocEnabled->docType : '';
                foreach ($boxml->newDocEnabled->param as $param) {
                    $this->newParams[(string) $param['name']] = (string) $param;
                }
            } else {
                $this->isNewAllowed = FALSE;
            }
            if ($this->isNewAllowed === TRUE) {
                if (isset($this->newParams)) {
                    if (isset($this->newParams['DocType']) && $this->newParams['DocType'] !== null) {
                        $this->keyDocType = (string) $this->newParams['DocType'];
                    }
                }
            }
        }

        function buildCollectionQuery($sql, $filters) {
            if ($filters === NULL || !is_array($filters) || count($filters) === 0) {
                return $sql;
            } else {
                $strfilter = 'Select * from ( ' . $sql . ' ) a where ';
                $filter_count = 0;
                $year_begin = SessionManager::getSessionVariable('year_begin');
                $year_end = SessionManager::getSessionVariable('year_end');
                foreach ($filters as $key => $value) {
                    if ($key === '_csrf' || trim($value) === '')
                        continue;
                    if ($filter_count > 0) {
                        $strfilter .= ' and ';
                    }
                    switch ($key) {
                        case 'docstatus':
                            if ($value === '5') {
                                $strfilter .= ' status=5';
                            } else if ($value === '-1') {
                                $strfilter .= ' status>=-1';
                            } else {
                                $strfilter .= ' status=0';
                            }
                            break;
                        case 'from_date':
                            if (strtotime(\app\cwf\vsla\utils\FormatHelper::GetDBDate($value)) >= strtotime($year_begin) && strtotime(\app\cwf\vsla\utils\FormatHelper::GetDBDate($value)) <= strtotime($year_end)) {
                                $strfilter .= ' doc_date >= \'' . \app\cwf\vsla\utils\FormatHelper::GetDBDate($value) . '\'';
                            } else {
                                $strfilter .= ' doc_date >= \'' . $year_begin . '\'';
                            }
                            break;
                        case 'to_date':
                            if (strtotime(\app\cwf\vsla\utils\FormatHelper::GetDBDate($value)) >= strtotime($year_begin) && strtotime(\app\cwf\vsla\utils\FormatHelper::GetDBDate($value)) <= strtotime($year_end)) {
                                $strfilter .= ' doc_date <= \'' . \app\cwf\vsla\utils\FormatHelper::GetDBDate($value) . '\'';
                            } else {
                                $strfilter .= ' doc_date <= \'' . $year_end . '\'';
                            }
                            break;
                        case 'voucher_id':
                            $strfilter .= ' ' . $this->keyField . '= \'' . trim($value) . '\'';
                            break;
                        default:
                            break;
                    }
                    $filter_count++;
                }
                return $strfilter;
            }
        }

    }

}

<?php

namespace app\cwf\vsla\render;

use app\cwf\vsla\design;
use app\cwf\vsla\security\AccessLevels;
use app\cwf\vsla\security\SessionManager;
use yii\helpers\Html;

include_once '../cwf/fwShell/models/MenuTree.php';

class CollectionHelper {

    const STATUS_FILTER_All = 0;
    const STATUS_FILTER_AwaitMyAction = 1;
    const STATUS_FILTER_ParticipatedIn = 2;
    const STATUS_FILTER_StartedByMe = 3;
    const STATUS_FILTER_Pending = 4;
    const STATUS_FILTER_Posted = 5;

    public static function getDefaultFilter() {
        $defaultFilter = array();

        $docStatus = new \app\cwf\vsla\design\FormField();
        $docStatus->control = \app\cwf\vsla\design\ControlType::SIMPLE_COMBO;
        $docStatus->id = 'docstatus';
        $docStatus->label = 'Status';
        $docStatus->options->defaultValue = 0;
        $docStatus->options->choices[0] = 'Pending post';
        $docStatus->options->choices[5] = 'Posted';
        $docStatus->options->choices[-1] = 'All';
        $docStatus->type = \app\cwf\vsla\design\FieldType::INT;
        array_push($defaultFilter, $docStatus);

        $fromDate = new \app\cwf\vsla\design\FormField();
        $fromDate->control = \app\cwf\vsla\design\ControlType::DATE;
        $fromDate->id = 'from_date';
        $fromDate->label = 'From Date';
        $fromDate->range = 'finYear';
        $fromDate->type = \app\cwf\vsla\design\FieldType::DATE;
        array_push($defaultFilter, $fromDate);

        $toDate = new \app\cwf\vsla\design\FormField();
        $toDate->control = \app\cwf\vsla\design\ControlType::DATE;
        $toDate->id = 'to_date';
        $toDate->label = 'To Date';
        $toDate->range = 'finYear';
        $toDate->type = \app\cwf\vsla\design\FieldType::DATE;
        array_push($defaultFilter, $toDate);

        $vchID = new \app\cwf\vsla\design\FormField();
        $vchID->control = \app\cwf\vsla\design\ControlType::TEXT_BOX;
        $vchID->id = 'voucher_id';
        $vchID->type = \app\cwf\vsla\design\FieldType::STRING;
        array_push($defaultFilter, $vchID);

        return $defaultFilter;
    }

    public static function getHeader(design\CollectionDesignView $design) {
        if ($design->option->accessLevel <= 0) {
            // User does not have access to this document.
            return '<h3>' . $design->header . '</h3>';
        }
        $headerHtml = '<h3 class="col-sm-8">' . $design->header . '</h3>
            <div class="col-sm-4 cformheaderbuttons">
                <div id="qp" 
                    qp-route="' . \yii\helpers\Html::encode(str_replace('@app/', '', $design->option->callingModulePath)) . '" 
                    qp-formName="' . \yii\helpers\Html::encode($design->editView) . '" 
                    qp-collName="' . \yii\helpers\Html::encode($design->option->xmlViewPath) . '"
                    qp-keyField="' . $design->keyField .
                ($design->newDocEnabled == TRUE && $design->newDocParam->haswizard() == TRUE ? '"
                    qp-wizPath="' . $design->newDocParam->wizardPath . '"
                    qp-wizStep="' . $design->newDocParam->wizardStep : '') . '"
                    qp-doctype="' . \yii\helpers\Html::encode($design->newDocEnabled ? $design->newDocParam->docType : '') . '"
                    qp-bizobj="' . \yii\helpers\Html::encode($design->type) . '"
                    hidden></div>';
        // Display the Refresh Button
        if ($design->type == \app\cwf\vsla\design\BusinessObject::TYPE_MASTER) {
            $headerHtml .= '<button class="btn btn-sm btn-default" id="collrefresh"
                    style="float: right;" 
                    onclick="coreWebApp.collectionView.fetch(true)" 
                    type="button">
                    <i id="collrefresh_image" class="glyphicon glyphicon-refresh" style="font-size:14px"></i>
                </button>';
        }
        // Display New Button where access level permits
        if ($design->newDocEnabled && ($design->option->accessLevel === AccessLevels::DATAENTRY ||
                $design->option->accessLevel === AccessLevels::AUTHORIZE)) {
            if ($design->newDocParam->haswizard()) {
                $headerHtml .= '<button id="cmd_addnew" name="cmd_addnew" class="btn btn-sm btn-default"
                    style="float: right; margin-right: 15px;" 
                    type="button" onclick="coreWebApp.collectionView.getWiz()">
                    <span class="glyphicon glyphicon-plus" aria-hidden="true"></span> New
                </button>';
            } else {
                if ($design->type == \app\cwf\vsla\design\BusinessObject::TYPE_DOCUMENT) {
                    if ($design->option->firstStageAllowed) {
                        $headerHtml .= '<button id="cmd_addnew" name="cmd_addnew" class="btn btn-sm btn-default"
                            style="float: right; margin-right: 15px;" 
                            type="button" onclick="coreWebApp.collectionView.getDoc(\'-1\',\'' . $design->afterLoadEvent . '\')">
                            <span class="glyphicon glyphicon-plus" aria-hidden="true"></span> New
                        </button>';
                    }
                } else {
                    $headerHtml .= '<button id="cmd_addnew" name="cmd_addnew" class="btn btn-sm btn-default"
                        style="float: right; margin-right: 15px;" 
                        type="button" onclick="coreWebApp.collectionView.getDoc(\'-1\',\'' . $design->afterLoadEvent . '\')">
                        <span class="glyphicon glyphicon-plus" aria-hidden="true"></span> New
                    </button>';
                }
            }
        }
        if ($design->collectionSection->detailViewExists()) {
            $headerHtml .= self::getCollectionDetails($design->collectionSection->detailView);
        }
        return $headerHtml . '</div>';
    }

    public static function getFilter(design\CollectionDesignView $design, $filters = '') {
        $status_options = array();
        $status_options[self::STATUS_FILTER_All] = 'All';
        $status_options[self::STATUS_FILTER_AwaitMyAction] = 'Awaiting My Action';
        $status_options[self::STATUS_FILTER_ParticipatedIn] = 'Participated In';
        $status_options[self::STATUS_FILTER_StartedByMe] = 'Started By Me';
        $status_options[self::STATUS_FILTER_Pending] = 'Pending Post';
        $status_options[self::STATUS_FILTER_Posted] = 'Posted';

        if ($design->option->accessLevel <= AccessLevels::NOACCESS) {
            return '<span style="margin:15px;">Access Denied. You do not have access to this collection.</span>';
        } else if ($design->type == \app\cwf\vsla\design\BusinessObject::TYPE_DOCUMENT) {
            $filterstring = '<form class="col-sm-10 form-horizontal" id="collectionfilter" name ="collectionfilter" 
                        target="collectiondata" style="padding-right:0px;">
                    <div>   
                        <input type="hidden" id="_csrf" name="_csrf" value="' . \Yii::$app->request->csrfToken . '">';

            $dateFormat = (string) SessionManager::getSessionVariable('date_format');
            $filterstring .= '<div class=" col-sm-3 form-group" style="margin-top: 0px;">' .
                    '<label class="control-label" for="docstatus">Status</label>';
            $filterstring .= Html::dropDownList('docstatus', self::STATUS_FILTER_AwaitMyAction, $status_options, ['class' => 'form-control', 'id' => 'docstatus']);
            $filterstring .= '</div><div class=" col-sm-3 form-group" style="margin-top: 0px;">' .
                    '<label class="control-label" for="from_date">From</label>';

            // Default the dates to current 2 months. Ensures, not too much data is pulled on refresh
            $to_date = date_format(date_add(new \DateTime(), new \DateInterval('P3D')), "Y-m-d");
            $from_date = date_format(date_sub(new \DateTime($to_date), new \DateInterval('P2M')), "Y-m-01");
            if (strtotime($from_date) < strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'))) {
                $from_date = \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin');
            }   
            
            if (strtotime($from_date) > strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))) {
                $year_end = new \DateTime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
                $from_date = date_format(date_sub($year_end, new \DateInterval('P2M')), "Y-m-01");
            }

            $filterstring .= Html::input('DateTime', 'from_date', \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($from_date), ['class' => ' datetime form-control ',
                        'type' => 'DateTime',
                        'data-validation-format' => $dateFormat === null ? 'yyyy-mm-dd' : $dateFormat,
                        'data-validation' => 'date',
                        'data-validation-optional' => 'true',
                        'start_date' => \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(
                                \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin')),
                        'end_date' => \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(
                                \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end')),
                        'name' => 'from_date', 'id' => 'from_date']
            );
            $filterstring .= '</div><div class=" col-sm-3 form-group" style="margin-top: 0px;">' .
                    '<label class="control-label" for="to_date">To</label>';

            if (strtotime($to_date) > strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'))) {
                $to_date = \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end');
            }
            $filterstring .= Html::input('DateTime', 'to_date', \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($to_date), ['class' => ' datetime form-control ',
                        'type' => 'DateTime',
                        'data-validation-format' => $dateFormat === null ? 'yyyy-mm-dd' : $dateFormat,
                        'data-validation-optional' => 'true',
                        'data-validation' => 'date',
                        'start_date' => \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(
                                \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin')),
                        'end_date' => \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(
                                \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end')),
                        'name' => 'to_date', 'id' => 'to_date']
            );
            $filterstring .= '</div><div class=" col-sm-3 form-group" style="margin-top: 0px;">' .
                    '<label class="control-label" for="voucher_id">Voucher ID</label>';
            $filterstring .= Html::input('text', 'voucher_id', '', ['class' => 'form-control', 'id' => 'voucher_id']);
            $filterstring .= '</div></div>';
            if ($filters != '') {
                $filterstring .= '<div id="cfilters" name="cfilters">' . $filters . '</div>';
            }
            $filterstring .= '</form>
        <div class=" col-sm-2 form-group" style="margin-top: 24px; padding-left: 0px; 
                padding-right: 0px; margin-bottom: 5px;">
            <div style="white-space: nowrap"></div>
            <button class="btn btn-sm btn-default" id="collrefresh" style="font-size:10px; padding:3px 6px;"
                    onclick="coreWebApp.collectionView.fetch(true)">
              <i id="collrefresh_image" class="glyphicon glyphicon-refresh" style="font-size:14px"></i>          
            </button>';
            if ($filters != '') {
                $filterstring .= '<button class="btn btn-sm btn-default" id="fltrtoggle" style="font-size:10px; padding:3px 6px; margin-left:5px;"
                    onclick="$(\'#cfilters\').toggle();"><span class="glyphicon glyphicon-filter" aria-hidden="true"></span>          
            </button>';
            }
            $filterstring .= '</div>';
            return $filterstring;
        }
    }

    /**
     * Generates JSON data for display in collection/list of Master/Document
     * @param \app\cwf\vsla\design\CollectionDesignView $design Design information of the view
     * @param array $filters An Array of filters
     * @return string Returns JSON encoded data extracted directly from db-server
     */
    public static function getCollection(design\CollectionDesignView $design, array $filters) {
        if ($design->option->accessLevel <= AccessLevels::NOACCESS) {
            if (array_key_exists('forapi', $filters)) {
                return ['fail-msg' => 'Requested data is not accessible to this user.'];
            }
            return '<span>Requested data is not accessible to this user.</span>';
        } else {
            if ($design->type == design\BusinessObject::TYPE_DOCUMENT) {
                $fdefaults = ['docstatus' => 0,
                    'from_date' => SessionManager::getSessionVariable('year_begin'),
                    'to_date' => SessionManager::getSessionVariable('year_end')];
                // This would ensure that the default parameters are created and available
                $filters = array_merge($fdefaults, $filters);
                \yii::info($filters, 'filters');
            }
            
            $dtCollection = self::getData($design, $filters);
            // Add Wf Columns
            self::setWfDisplayFields($design, $filters);

            // api resultsets with minimum data
            if (array_key_exists('forapi', $filters)) {
                $cols = [];
                foreach ($design->collectionSection->displayFields as $df) {
                    $cols[] = $df->columnName;
                }
                return '{"cols":' . json_encode($cols) . ',"data":' . $dtCollection->Rows()[0]['raw_data'] . '}';
            }
            // Prepare result
            $jsonResult = '{ "cols": ' . json_encode($design->collectionSection->displayFields);
            $jsonResult .= ', "def": ' . json_encode(['al' => $design->option->accessLevel, 'keyField' => $design->keyField, 'afterLoad' => $design->afterLoadEvent]);
            $jsonResult .= ', "data": ' . ($dtCollection->Rows()[0]['raw_data'] == null ? json_encode([]) : $dtCollection->Rows()[0]['raw_data']) . '}';
            return $jsonResult;
        }
    }

    public static function getCollectionDetails(design\CollectionDetailView $detailView) {
        switch ($detailView->viewType) {
            case design\CollectionDetailType::PARTIALACTION:
                $res = '<script type="text/javascript">';
                $res .= 'function thelistdetail(row, tr) { return ' . $detailView->partialActionPath . '(row,tr); }';
                $res .= '</script>';
                return $res;
            case design\CollectionDetailType::PARTIALVIEW:
                $res = '<script type="text/javascript">';
                $res .= 'function thelistdetail(row, tr) { rowdata = row.data(); row.child( "';
                $res .= '<table cellspacing=\'0\' border=\'0\' style=\'margin-left:50px;\'>';
                $res .= self::getDetailView($detailView);
                $res .= '</table>".show(); tr.addClass("shown");}';
                $res .= '</script>';
                return $res;
            case design\CollectionDetailType::TRANVIEW:
                $res = '<script type="text/javascript">';
                $res .= 'function thelistdetail(row, tr) {rowdata = row.data(); row.child( "';
                $res .= '<table cellspacing=\'0\' border=\'0\' style=\'margin-left:50px;\'>';
                foreach ($detailView->tranView as $tran) {
                    $res .= self::output_TYPE_DISPLAYFIELD($tran);
                }
                $res .= '</table>").show(); tr.addClass("shown");}';
                $res .= '</script>';
                return $res;
            default:
                return '';
        }
    }

    private static function output_TYPE_DISPLAYFIELD(design\DisplayFieldType $displayField) {
//        $fld = '<tr><td style=\"font-size: 10px;\"><strong>'. $displayField->displayName. '</strong></td><td>"+rowdata.'.$displayField->columnName.'+"</td>';
        $formatfld = '';
        if ($displayField->format == 'Amount' || $displayField->format == 'Number' ||
                $displayField->format == 'Qty' || $displayField->format == 'Rate' ||
                $displayField->format == 'FC') {
            $scale = 0;
            switch ($displayField->scale) {
                case design\FieldScale::FC :
                    $scale = \app\cwf\vsla\Math::$fcScale;
                    break;
                case design\FieldScale::QTY :
                    $scale = \app\cwf\vsla\Math::$qtyScale;
                    break;
                case design\FieldScale::RATE :
                    $scale = \app\cwf\vsla\Math::$rateScale;
                    break;
                default :
                    $scale = \app\cwf\vsla\Math::$amtScale;
                    break;
            }
            $formatfld .= 'coreWebApp.formatNumber(rowdata.' . $displayField->columnName . ',' . $scale . ')';
        } else if ($displayField->format == 'Date' || $displayField->format == 'Datetime') {
            $formatfld .= 'coreWebApp.formatDate(rowdata.' . $displayField->columnName . ')';
        } else {
            $formatfld .= 'rowdata.' . $displayField->columnName;
        }
        $fld = '<tr><td style=\"font-size: 10px;\"><strong>' . $displayField->displayName . '</strong></td><td>"+' . $formatfld . '+"</td>';
        return $fld;
    }

    private static function getDetailView(design\CollectionDetailView $detailView) {
        $res = '';
        if ($detailView->viewType == design\CollectionDetailType::PARTIALVIEW) {
            $viewPath = $detailView->partialViewPath;
            if (strpos($viewPath, '.xml') !== false) {
                
            } else if (strpos($viewPath, '.php') !== false) {
                
            }
        }
        return $res;
    }

    private static function setWfDisplayFields(design\CollectionDesignView $design, $filters) {
        // Create PK Link
        foreach ($design->collectionSection->displayFields as &$df) {
            if ($df->columnName == $design->keyField) {
                $df->format = 'Link';
                break;
            }
        }
        // Add Wf Columns
        if ($design->type == design\BusinessObject::TYPE_DOCUMENT) {
            if ($filters['docstatus'] != self::STATUS_FILTER_Posted && $filters['docstatus'] != self::STATUS_FILTER_All) {
                $wfdisplay = new \app\cwf\vsla\design\DisplayFieldType();
                $wfdisplay->columnName = 'from_user';
                if ($filters['docstatus'] == self::STATUS_FILTER_ParticipatedIn 
                        || $filters['docstatus'] == self::STATUS_FILTER_Pending
                        || $filters['docstatus'] == self::STATUS_FILTER_StartedByMe) {
                    $wfdisplay->displayName = "Current User";
                } else {
                    $wfdisplay->displayName = "From";
                }
                $design->collectionSection->displayFields[] = $wfdisplay;

                $wfdisplay = new \app\cwf\vsla\design\DisplayFieldType();
                $wfdisplay->columnName = 'doc_sent_on';
                if ($filters['docstatus'] == self::STATUS_FILTER_ParticipatedIn || $filters['docstatus'] == self::STATUS_FILTER_Pending) {
                    $wfdisplay->displayName = "Time Since";
                } else {
                    $wfdisplay->displayName = "Sent Time";
                }
                $wfdisplay->format = 'Datetime';
                $design->collectionSection->displayFields[] = $wfdisplay;
            } elseif ($filters['docstatus'] == self::STATUS_FILTER_All) {
                $wfdisplay = new \app\cwf\vsla\design\DisplayFieldType();
                $wfdisplay->columnName = 'status';
                $wfdisplay->displayName = "Status";
                $wfdisplay->format = "Status";
                $design->collectionSection->displayFields[] = $wfdisplay;
            }
        }
    }

    public static function getData(design\CollectionDesignView $design, $filters) {
        $cmm = self::getSql($design->collectionSection->sql);
        if ($design->ovrrideClass != '' && $design->ovrrideMethod != '') {
            $ovrClass = $design->ovrrideClass;
            $ovrMethod = $design->ovrrideMethod;
            $cmtext = $cmm->getCommandText();
            $ovrrideClass = new $ovrClass();
            $ovrrideClass->$ovrMethod($cmtext, $filters);
            $cmm->setCommandText($cmtext);
        }
        if($design->type == design\BusinessObject::TYPE_DOCUMENT) {
            $cmm->setCommandText(self::buildCollectionQuery($cmm->getCommandText(), $filters, $design->keyField));
        } else {
            // This is not a document, hence ignore filters
            $cmm->setCommandText(self::buildCollectionQuery($cmm->getCommandText(), NULL, $design->keyField));
        }
        $collection = \app\cwf\vsla\data\DataConnect::getData($cmm, $design->collectionSection->connectionType);
        return $collection;
    }

    private static function buildCollectionQuery($sql, $filters, $keyField) {
        if ($filters === NULL) {
            $finalSql = "With mast_data
                As
                ( $sql 
                )
                Select json_agg(r) raw_data
                From mast_data r";
            return $finalSql;
        } else {
            $userInfo = SessionManager::getInstance()->getUserInfo();
            $year_begin = SessionManager::getSessionVariable('year_begin');
            $year_end = SessionManager::getSessionVariable('year_end');
            $qCond = [];
            foreach ($filters as $key => $value) {
                switch ($key) {
                    case 'docstatus':
                        if ($value == self::STATUS_FILTER_AwaitMyAction) {
                            $qCond[] = 'a.status In (1, 3)';
                        } elseif ($value == self::STATUS_FILTER_ParticipatedIn) {
                            $qCond[] = 'a.status = 3 And Exists (Select b.doc_id From sys.doc_wf_history b Where a.' . $keyField . '=b.doc_id And (b.user_id_to=' . $userInfo->getUser_ID() . ' Or b.user_id_from=' . $userInfo->getUser_ID() . '))';
                        } elseif ($value == self::STATUS_FILTER_StartedByMe) {
                            $qCond[] = 'a.status In (1, 3)';
                        } elseif ($value == self::STATUS_FILTER_Pending) {
                            $qCond[] = 'a.status!=5';
                        } elseif ($value == self::STATUS_FILTER_Posted) {
                            $qCond[] = 'a.status=5';
                        } elseif ($value == self::STATUS_FILTER_All) {
                            // Do nothing
                        }
                        break;
                    case 'from_date':
                        if (strtotime($value) >= strtotime($year_begin) && strtotime($value) <= strtotime($year_end)) {
                            $qCond[] = "a.doc_date >= '$value'";
                        } else {
                            $qCond[] = "a.doc_date >= '$year_begin'";
                        }
                        break;
                    case 'to_date':
                        if (strtotime($value) >= strtotime($year_begin) && strtotime($value) <= strtotime($year_end)) {
                            $qCond[] = "a.doc_date <= '$value'";
                        } else {
                            $qCond[] = "a.doc_date <= '$year_end'";
                        }
                        break;
                    case 'voucher_id':
                        $value = trim($value);
                        if (strlen($value)>0) {
                            $qCond[] = "$keyField = '".str_replace("'", "", $value)."'";
                        }
                        break;
                    default:
                        break;
                }
            }
            $finalSql = "With doc_data
                As
                (   Select a.* 
                    From ($sql) a \n".
                    (count($qCond) > 0 ? "Where " . implode(" and ", $qCond) . "\n" : "")
                ."),
                wf_data
                As
                ( " . self::getWfSql($filters['docstatus'], $keyField) . " )
                Select json_agg(wf_data) raw_data
                From wf_data";
            \yii::info($finalSql, 'finalSql');
            return $finalSql;
        }
    }

    public static function getSql($sql) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText((string) $sql->command);
        if (isset($sql->params)) {
            foreach ($sql->params as $param) {
                $paramval = ReportHelper::output_paramvalue_param($param);
                $cmm->addParam($param->id, $paramval);
            }
        }
        return $cmm;
    }

    /**
     * Returns second part of the query with Wf information
     * @param int $qStatus One of the STATUS_FILTER_*
     */
    private static function getWfSql(int $qStatus, $keyField) {
        $userInfo = SessionManager::getInstance()->getUserInfo();
        switch ($qStatus) {
            case self::STATUS_FILTER_AwaitMyAction:
                return "Select a.*, 
                        c.full_user_name from_user, 
                        b.doc_sent_on doc_sent_on
                    From doc_data a
                    Inner Join sys.doc_wf b On a.$keyField=b.doc_id
                    Inner Join sys.user c On b.user_id_from = c.user_id
                    Where b.user_id_to = " . $userInfo->getUser_ID() . "
                    Union All
                    Select a.*, 
                        entered_by from_user, 
                        entered_on doc_sent_on
                    From doc_data a
                    Inner Join sys.doc_es b On a.$keyField=b.voucher_id And a.status = 1 and b.entered_user='" .$userInfo->getUserName()."'";
            case self::STATUS_FILTER_StartedByMe:
                return "Select a.*, 
                        c.full_user_name from_user, 
                        b.doc_sent_on doc_sent_on
                    From doc_data a
                    Inner Join sys.doc_wf b On a.$keyField=b.doc_id
                    Inner Join sys.user c On b.user_id_to = c.user_id
                    Inner Join sys.doc_es d On a.$keyField=d.voucher_id
                    Where d.entered_user='" . str_replace("'", "", $userInfo->getUserName()) . "'";
            case self::STATUS_FILTER_ParticipatedIn:
                return "Select a.*, 
                        Coalesce(c.full_user_name, 'Me') from_user, 
                        Coalesce(b.doc_sent_on, current_timestamp(0))::timestamp doc_sent_on
                    From doc_data a
                    Left Join sys.doc_wf b On a.$keyField=b.doc_id
                    Left Join sys.user c On b.user_id_from = c.user_id";
            case self::STATUS_FILTER_Pending:
                return "Select a.*, 
                        Case When c.full_user_name Is Null Then d.entered_by Else c.full_user_name End from_user, 
                        Case When b.doc_sent_on Is Null Then d.entered_on Else b.doc_sent_on End doc_sent_on
                    From doc_data a
                    Left Join sys.doc_wf b On a.$keyField = b.doc_id
                    Left Join sys.user c On b.user_id_to = c.user_id
                    Left Join sys.doc_es d On a.$keyField = d.voucher_id";
            case self::STATUS_FILTER_All:
                return "Select a.*
                    From doc_data a";
            default:
                return "Select a.* From doc_data a";
        }
    }

}

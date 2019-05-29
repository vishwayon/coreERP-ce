<?php

namespace app\cwf\vsla\ui;

use yii\helpers\Html;
use yii\widgets\LinkPager;
use yii\data\Pagination;
use app\cwf\vsla\security\SessionManager;
use app\cwf\vsla\ui\CustomLink;
use app\cwf\vsla\security\AccessLevels;
use app\cwf\vsla\utils\FormatHelper;

include getcwd() . '/../cwf/fwShell/models/MenuTree.php';

class collectionviewrenderer {

    /** @var collectionviewparser * */
    public $collectionviewparser;
    var $status_options = array();
    var $theurl, $clicklink, $dateFormat, $pgurlmanager, $keyType, $keyField;
    public $collectionurl;

    function __construct($collectionparser) {
        $this->collectionviewparser = $collectionparser;
        $this->init();
    }

    function init() {
        $this->keyField = (string) $this->collectionviewparser->keyField;
        $this->keyType = NULL;
        \Yii::$app->setHomeUrl('cwf/fwShell');
        $viewerurl = \Yii::$app->urlManager->parseRequest(\Yii::$app->request);
        $this->collectionurl = '?r=' . $viewerurl[0] . '&formName=' . $this->collectionviewparser->collectionName;
        $viewerurl = str_replace('collection', 'filter-collection', $viewerurl);
        $this->theurl = '?r=' . $viewerurl[0] . '&formName=' . $this->collectionviewparser->collectionName;


        if ($this->collectionviewparser->isNewAllowed === TRUE) {
            if (isset($this->collectionviewparser->newParams)) {
                if (isset($this->collectionviewparser->newParams['DocType']) && $this->collectionviewparser->newParams['DocType'] !== null) {
                    $this->keyType = (string) $this->collectionviewparser->newParams['DocType'];
                }
            }
        }
        if ($this->keyType === NULL) {
            $this->clicklink = Html::encode('coreWebApp.rendercontents(\'?r=' . $this->collectionviewparser->modulePath .
                            '&formName=' . $this->collectionviewparser->xEditForm .
                            '&formParams=' . '{"' . $this->keyField . '": -1}' . '\',\'details\',\'contentholder\')');
        } else {
            $this->clicklink = Html::encode('coreWebApp.rendercontents(\'?r=' . $this->collectionviewparser->modulePath .
                            '&formName=' . $this->collectionviewparser->xEditForm .
                            '&formParams=' . '{"' . $this->keyField . '": -1,"doc_type":"' . $this->keyType . '"}' . '\',\'details\',\'contentholder\')');
        }

        if ($this->collectionviewparser->newType === 'wizard') {
            $this->clicklink = Html::encode('coreWebApp.rendercontents(\'?r=' . $this->collectionviewparser->modulePath .
                            '/wizard&formName=' . $this->collectionviewparser->wizPath .
                            '&step=' . $this->collectionviewparser->wizStep . '\',\'details\',\'contentholder\')');
        }

        $this->status_options[0] = 'Pending post';
        $this->status_options[5] = 'Posted';
        $this->status_options[-1] = 'All';
    }

    function renderheader() {
        if ($this->collectionviewparser->access_level <= 0) {
            return '';
        } else {
            $headerstring = <<<hdr
            <h3 class="col-md-6">
                {$this->collectionviewparser->header}
            </h3><div class="col-md-4 cformheaderbuttons">
hdr;
            if ($this->collectionviewparser->formType == 'Master') {
                $headerstring .= <<<filter
                <button class="btn btn-sm btn-default" id="collrefresh"
                    style="float: right;" 
                    posturl={$this->collectionurl}
                    onclick="coreWebApp.getfilteredcollectionwrapper()"
                    type="button">
                <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>
            </button>
filter;
            }

            if ($this->collectionviewparser->isNewAllowed === TRUE && ($this->collectionviewparser->access_level === AccessLevels::DATAENTRY ||
                    $this->collectionviewparser->access_level === AccessLevels::AUTHORIZE)) {
                $headerstring .= <<<filter
            <button id="cmd_addnew" name="cmd_addnew" class="btn btn-sm btn-default"
                    style="float: right; margin-right: 15px;" 
                    type="button" onclick="{$this->clicklink}">
                <span class="glyphicon glyphicon-plus" aria-hidden="true"></span> New
            </button>
filter;
            }
            return $headerstring . '</div>';
        }
    }

    function renderfilter() {
        if ($this->collectionviewparser->access_level <= 0) {
            return '';
        } else {
            $filterstring = <<<filter
        <form class="col-md-10 form-horizontal" id="collectionfilter" name ="collectionfilter" 
         target="collectiondata" style="padding-right:0px;">
    <div>
        <input type="hidden" id="_csrf" name="_csrf" value="
filter;
            $filterstring .= \Yii::$app->request->csrfToken;
            $filterstring .= '">';

            if ($this->collectionviewparser->formType == 'Document') {
                $this->dateFormat = (string) SessionManager::getSessionVariable('date_format');
                $filterstring .= '<div class=" col-md-3 form-group" style="margin-top: 0px;">' .
                        '<label class="control-label" for="docstatus">Status</label>';
                $filterstring .= Html::dropDownList('docstatus', 0, $this->status_options, ['class' => 'form-control', 'id' => 'docstatus']);
                $filterstring .= '</div><div class=" col-md-3 form-group" style="margin-top: 0px;">' .
                        '<label class="control-label" for="from_date">From</label>';
                $filterstring .= Html::input('DateTime', 'from_date', \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin')), ['class' => ' datetime form-control ',
                            'type' => 'DateTime',
                            'data-validation-format' => $this->dateFormat === null ? 'yyyy-mm-dd' : $this->dateFormat,
                            'data-validation' => 'date',
                            'data-validation-optional' => 'true',
                            'start_date' => \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin')),
                            'end_date' => \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end')),
                            'name' => 'from_date', 'id' => 'from_date']
                );
                $filterstring .= '</div><div class=" col-md-3 form-group" style="margin-top: 0px;">' .
                        '<label class="control-label" for="to_date">To</label>';
                $filterstring .= Html::input('DateTime', 'to_date', \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end')), ['class' => ' datetime form-control ',
                            'type' => 'DateTime',
                            'data-validation-format' => $this->dateFormat === null ? 'yyyy-mm-dd' : $this->dateFormat,
                            'data-validation-optional' => 'true',
                            'data-validation' => 'date',
                            'start_date' => \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin')),
                            'end_date' => \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_end')),
                            'name' => 'to_date', 'id' => 'to_date']
                );
                $filterstring .= '</div><div class=" col-md-3 form-group" style="margin-top: 0px;">' .
                        '<label class="control-label" for="voucher_id">Voucher ID</label>';
                $filterstring .= Html::input('text', 'voucher_id', '', ['class' => 'form-control', 'id' => 'voucher_id']);
                $filterstring .= <<<filter
                    </div></div>
        </form>
        <div class=" col-md-2 form-group" style="margin-top: 24px; padding-left: 0px; 
                padding-right: 0px; margin-bottom: 5px;">
            <div style="white-space: nowrap"></div>
            <button class="btn btn-sm btn-default" id="collrefresh" style="font-size:10px; padding:3px 6px;"
                    posturl={$this->theurl}
                    onclick="coreWebApp.getfilteredcollectionwrapper()">
              <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>          
            </button>
filter;

                /*            if($this->collectionviewparser->isNewAllowed===TRUE
                  && ($this->collectionviewparser->access_level===AccessLevels::DATAENTRY ||
                  $this->collectionviewparser->access_level===AccessLevels::AUTHORIZE)){
                  $filterstring.=<<<filter
                  <button class="btn btn-sm btn-default"  id="cmd_addnew"
                  style="float: right;"
                  type="button" onclick="{$this->clicklink}">
                  <span class="glyphicon glyphicon-plus" aria-hidden="true"></span>
                  </button>
                  filter;
                  } */
            }
            $filterstring .= '</div>';
            return $filterstring;
        }
    }

    function rendercollectionheader() {
        $collectionheader = '<table id="thelisthead" class="table table-hover"><thead id="collheader"><tr>';
        $collectionheader .= '<th></th></tr></thead></table>';
        return $collectionheader;
    }

    function rendercollectiondata($dtCollection) {
        if ($dtCollection === null) {
            $dtCollection = $this->collectionviewparser->dtCollection;
        }

        if ($this->collectionviewparser->access_level <= 0) {
            $res = '<span>Requested data is not accessible to this user.</span>';
            return $res;
        } else {
            $collectiondata = '<table id="thelist" class="row-border hover"><thead id="dataheader"><tr>';

            foreach ($this->collectionviewparser->displayFields->displayField as $colDef) {
                foreach ($this->collectionviewparser->dtCollection->getColumns() as $value) {
                    if ((string) $colDef['columnName'] == $value->columnName) {
                        if ((string) $colDef['format'] == "Amount" || (string) $colDef['format'] == "Number") {
                            $collectiondata .= "<th style='text-align: center;border:none;'>" . $colDef['displayName'] . "</th>";
                        } else {
                            $collectiondata .= "<th style='border:none;'>" . $colDef['displayName'] . "</th>";
                        }
                    }
                }
            }

            $collectiondata .= '<th style="border:none;"></th><th style="border:none;"></th></tr></thead><tbody>';
            foreach ($dtCollection->Rows() as $rw) {
                $collectiondata .= "<tr>";
                foreach ($this->collectionviewparser->displayFields->displayField as $colDef) {
                    foreach ($rw as $k => $v) {
                        if ((string) $colDef['columnName'] == $k) {
                            if ($colDef['format'] != null) {
                                // apply formats if available
                                if ((string) $colDef['format'] == "Amount") {
                                    $collectiondata .= '<td style="text-align: right;">' . FormatHelper::FormatAmt($v) . "</td>";
                                }
                                if ((string) $colDef['format'] == "Number") {
                                    $collectiondata .= '<td style="text-align: right;">' . FormatHelper::FormatNumber($v) . "</td>";
                                }
                                if ((string) $colDef['format'] == "Date") {
                                    $collectiondata .= '<td style="text-align: left;" data-order="' . strtotime($v) . '">' . FormatHelper::FormatDateForDisplay($v) . "</td>";
                                }
                            } else {
                                if ((string) $colDef['columnName'] == $this->keyField) {
                                    $collectiondata .= '<td data-order="' . substr($v, strrpos($v, '/') + 1) . '">' . $v . '</td>';
                                } else {
                                    $collectiondata .= "<td>$v</td>";
                                }
                            }
                        }
                    }
                }
                $collectiondata .= "<td align='center'>";

                if ($this->keyType === NULL || $this->keyType == '') {
                    $options = ['onclick' => 'coreWebApp.rendercontents(\'?r=' . $this->collectionviewparser->modulePath .
                        '&formName=' . $this->collectionviewparser->xEditForm .
                        '&formParams=' . '{"' . $this->keyField . '":' . $rw[$this->keyField] . '}' . '\',\'details\',\'contentholder\')'];
                } else {
                    $options = ['onclick' => 'coreWebApp.rendercontents(\'?r=' . $this->collectionviewparser->modulePath .
                        '&formName=' . $this->collectionviewparser->xEditForm .
                        '&formParams=' . '{"' . $this->keyField . '":"' . $rw[$this->keyField] . '"}' . '\',\'details\',\'contentholder\')'];
                }

                if ($this->collectionviewparser->editNotAllowedField === NULL) {
                    if ($this->collectionviewparser->access_level === AccessLevels::DATAENTRY) {
                        $collectiondata .= Html::a('Edit', '#', $options);
                    } else if ($this->collectionviewparser->access_level === AccessLevels::AUTHORIZE) {
                        $collectiondata .= Html::a('Edit', '#', $options);
                    } else if ($this->collectionviewparser->access_level === AccessLevels::READONLY) {
                        $collectiondata .= Html::a('View', '#', $options);
                    }
                } else {
                    if (!$rw[$this->collectionviewparser->editNotAllowedField]) {
                        if ($this->collectionviewparser->access_level === AccessLevels::DATAENTRY) {
                            $collectiondata .= Html::a('Edit', '#', $options);
                        } else if ($this->collectionviewparser->access_level === AccessLevels::AUTHORIZE) {
                            $collectiondata .= Html::a('Edit', '#', $options);
                        } else if ($this->collectionviewparser->access_level === AccessLevels::READONLY) {
                            $collectiondata .= Html::a('View', '#', $options);
                        }
                    }
                }
                $collectiondata .= '</td><td>';
                $editFormurl = $this->collectionviewparser->modulePath;
                $editFormurl = str_replace('/form', '', $editFormurl);
                $auditoptions = ['onclick' => 'coreWebApp.rendercontents(\'?r=/cwf/sys/main/audittrail' .
                    '&formName=' . $editFormurl . '/' . $this->collectionviewparser->xEditForm .
                    '&formParams=' . '{"' . $this->keyField . '":"' . $rw[$this->keyField] . '"}' .
                    '&formUrl=' . $editFormurl . '\',\'details\',\'contentholder\')'];

                $collectiondata .= Html::a('View AT', '#', $auditoptions);

                $collectiondata .= '</td></tr>';
            }
            $collectiondata .= '</tbody></table><table id="header-fixed"></table>';

            return $collectiondata;
        }
    }

}

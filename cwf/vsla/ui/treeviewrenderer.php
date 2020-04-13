<?php

namespace app\cwf\vsla\ui;

use yii\helpers\Html;
use app\cwf\vsla\security\SessionManager;
use app\cwf\vsla\security\AccessLevels;
use app\cwf\vsla\utils\FormatHelper;

include getcwd() . '/../cwf/fwShell/models/MenuTree.php';

class treeviewrenderer {

    public $treeviewparser;
    var $status_options = array();
    var $theurl, $parentclicklink, $childclicklink, $dateformat, $pgurlmanager, $keyType, $keyField;
    var $rowcnt = 0;
    public $collectionurl;

    function __construct($treeparser) {
        $this->treeviewparser = $treeparser;
        $this->init();
    }

    function init() {
        $this->keyField = (string) $this->treeviewparser->keyField;
        $this->keyType = NULL;
        \Yii::$app->setHomeUrl('cwf/fwShell');
        $baseurl = \Yii::$app->urlManager->getBaseUrl();
        $viewerurl = \Yii::$app->urlManager->parseRequest(\Yii::$app->request);
        $this->collectionurl = '?r=' . $viewerurl[0] . '&formName=' . $this->treeviewparser->collectionName;
        $viewerurl = str_replace('collection', 'filter-collection', $viewerurl);
        $this->theurl = '?r=' . $viewerurl[0] . '&formName=' . $this->treeviewparser->collectionName;

//        if($this->treeviewparser->isNewAllowed===TRUE){
//            if(isset($this->treeviewparser->newparams)){
//                if(isset($this->treeviewparser->newparams['DocType'])
//                        && $this->treeviewparser->newparams['DocType']!==null){
//                    $this->keyType=(string)$this->treeviewparser->newparams['DocType'];
//                }
//            }
//        }

        if (isset($this->treeviewparser->parentEditForm)) {
            $this->parentclicklink = Html::encode('coreWebApp.rendercontents(\'?r=' . $this->treeviewparser->modulePath .
                            '&formName=' . $this->treeviewparser->parentEditForm .
                            '&formParams=' . '{"' . $this->treeviewparser->parentKeyField . '": -1}' . '\',\'details\',\'contentholder\')');
        }
        if (isset($this->treeviewparser->childEditForm)) {
            $this->childclicklink = Html::encode('coreWebApp.rendercontents(\'?r=' . $this->treeviewparser->modulePath .
                            '&formName=' . $this->treeviewparser->childEditForm .
                            '&formParams=' . '{"' . $this->treeviewparser->childKeyField . '": -1}' . '\',\'details\',\'contentholder\')');
        }
    }

    function renderheader() {
        $headerstring = <<<hdr
            <h3 class="col-sm-8">
                {$this->treeviewparser->header}
            </h3>{$this->addinfo()}<div class="col-sm-4 cformheaderbuttons">
hdr;
        if ($this->treeviewparser->formType == 'Master') {
            $headerstring .= <<<filter
                <button class="btn btn-sm btn-default" id="collrefresh"
                    style="float: right; margin-right: 15px;" 
                    type="button" 
                    posturl={$this->collectionurl}
                    onclick="coreWebApp.getfilteredcollectionwrapper()"
                >
                <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>
            </button>
filter;
            if ($this->treeviewparser->parentisNewAllowed === TRUE && ($this->treeviewparser->parent_access_level === AccessLevels::DATAENTRY ||
                    $this->treeviewparser->parent_access_level === AccessLevels::AUTHORIZE)) {
                $headerstring .= <<<filter
                <button class="btn btn-sm btn-default" id="collnewparent"
                    style="float: right; margin-right: 15px;" 
                    type="button" onclick="{$this->parentclicklink}">
                <span class="glyphicon glyphicon-plus" aria-hidden="true"> {$this->treeviewparser->parentLabel}</span>
            </button>
filter;
            }
            if ($this->treeviewparser->childisNewAllowed === TRUE && ($this->treeviewparser->child_access_level === AccessLevels::DATAENTRY ||
                    $this->treeviewparser->child_access_level === AccessLevels::AUTHORIZE)) {
                $headerstring .= <<<filter
                <button class="btn btn-sm btn-default" id="collnewchild"
                    style="float: right; margin-right: 15px;" 
                    type="button" onclick="{$this->childclicklink}">
                <span class="glyphicon glyphicon-plus" aria-hidden="true"> {$this->treeviewparser->childLabel}</span>
            </button>
filter;
            }
        }
        $headerstring .= '<input type="hidden" id="viewer" name="viewer" value="treeviewflag">';
        return $headerstring . '</div>';
    }

    function rendercollectiondata() {
        $collectiondata = $this->addSearchbox();
        $collectiondata .= '<div id="thelistdiv" style="overflow-y:auto;">';
        $collectiondata .= '<table id="thelist" class="table tree row-border hover">';
        $collectiondata .= '<thead id="dataheadertree"><tr>';
        $colcount = 0;
        if (isset($this->treeviewparser->childDisplayFields)) {
            if (count($this->treeviewparser->parentDisplayFields->displayFields) >= count($this->treeviewparser->childDisplayFields->displayFields)) {
                $colcount = count($this->treeviewparser->parentDisplayFields->displayFields);
            } else {
                $colcount = count($this->treeviewparser->childDisplayFields->displayFields);
            }
        }
        for ($i = 0; $i < $colcount; $i++) {
            $collectiondata .= '<th></th>';
        }

        $collectiondata .= '<th></th></tr></thead><tbody>';
        $collectiondata .= $this->addnode(-1, '0', 1, $this->rowcnt);
        //$collectiondata.='</table>';
        $collectiondata .= '</tbody></table><table id="header-fixed"></table>';
        $collectiondata .= '</div>';
        if ($this->treeviewparser->clientCode !== NULL && trim($this->treeviewparser->clientCode) !== '') {
            \Yii::trace(\yii\helpers\VarDumper::dumpAsString($this->treeviewparser->clientCode));
            $collectiondata .= '<script src="' . \app\cwf\vsla\utils\ScriptHelper::registerScript('@app/' . $this->treeviewparser->clientCode) . '"></script>';
        }
        return $collectiondata;
    }

    function addnode($parentid, $parentkey, $parentcnt, &$rowcnt) {
        $rowdata = '';
        if ($this->treeviewparser->child_access_level > AccessLevels::NOACCESS) {
            if (isset($this->treeviewparser->dtchild)) {
                foreach ($this->treeviewparser->dtchild->Rows() as $rw) {
                    if ($parentid !== -1) {
                        if ($rw[$this->treeviewparser->relationKeyField] === $parentid) {
                            $rowcnt++;
                            $rowdata .= $this->addchild('child', $rw, $parentkey, $parentcnt, $rowcnt, false);
                        }
                    }
                }
            }
        }
        foreach ($this->treeviewparser->dtparent->Rows() as $rw) {
            if ($rw[$this->treeviewparser->parentParentKey] === $parentkey) {
                $rowcnt++;
                $rowdata .= $this->addchild('parent', $rw, $parentkey, $parentcnt, $rowcnt, true);
                $rowdata .= $this->addnode($rw[$this->treeviewparser->relationKeyField], $rw[$this->treeviewparser->parentChildKey], $rowcnt, $rowcnt);
            }
        }
        return $rowdata;
    }

    function addchild($sect, $rw, $parentkey, $parentcnt, $rowcnt, $isgroup) {
        $def = $sect . 'DisplayFields';
        $alwd = $sect . 'EditNotAllowedField';
        $frm = $sect . 'EditForm';
        $kfld = $sect . 'KeyField';
        $aclvl = $sect . '_access_level';
        $boid = $sect . '_bo_id';
        if ($parentkey === '0') {
            $fielddata = '<tr class="treegrid-' . $rowcnt;
        } else {
            $fielddata = '<tr class="treegrid-' . $rowcnt . ' treegrid-parent-' . $parentcnt;
        }
        if ($isgroup && isset($this->treeviewparser->dtchild)) {
            $fielddata .= '" style="font-weight:bold;">';
        } else {
            $fielddata .= '">';
        }
        foreach ($this->treeviewparser->$def->displayField as $colDef) {
            foreach ($rw as $k => $v) {
                if ((string) $colDef['columnName'] == $k) {
                    if ($colDef['format'] != null) {
                        if ((string) $colDef['format'] == "Amount") {
                            $fielddata .= '<td style="text-align: right;">' . FormatHelper::FormatAmt($v) . "</td>";
                        }
                        if ((string) $colDef['format'] == "Number") {
                            $fielddata .= '<td style="text-align: right;">' . FormatHelper::FormatNumber($v) . "</td>";
                        }
                        if ((string) $colDef['format'] == "Date") {
                            $fielddata .= '<td style="text-align: left;">' . FormatHelper::FormatDateForDisplay($v) . "</td>";
                        }
                    } else {
                        $fielddata .= "<td>$v</td>";
                    }
                }
            }
        }
        $fielddata .= "<td align='center'>";
        $baseurl = \Yii::$app->urlManager->getBaseUrl();
        if (isset($this->treeviewparser->$frm)) {
            $options = ['onclick' => 'coreWebApp.rendercontents(\'?r=' . $this->treeviewparser->modulePath .
                '&formName=' . $this->treeviewparser->$frm .
                '&formParams=' . '{"' . $this->treeviewparser->$kfld . '":'
                . $rw[$this->treeviewparser->$kfld] . '}' . '\',\'details\',\'contentholder\')'];
            if ($this->treeviewparser->$alwd === NULL) {
                $fielddata .= $this->resolveEditLink($this->treeviewparser->$aclvl, $options);
                //Html::a('Edit','#',$options);
            } else {
                if (!$rw[$this->treeviewparser->$alwd]) {
                    $fielddata .= $this->resolveEditLink($this->treeviewparser->$aclvl, $options);
                    //Html::a('Edit','#',$options);
                }
            }
            $fielddata .= '</td>';
        }
        $fielddata .= '</tr>';
        return $fielddata;
    }

    protected function resolveEditLink($access_level, $options) {
        $options ['style'] = 'border:none;padding-left:5px;padding-right:5px;background-color:white;';
        switch ($access_level) {
            case AccessLevels::DATAENTRY :
                $options ['title'] = 'Edit';
                $options ['id'] = 'cedit';
                return Html::button('<i class="glyphicon glyphicon-pencil"></i>', $options);
            case AccessLevels::AUTHORIZE :
                $options ['title'] = 'Edit';
                $options ['id'] = 'cedit';
                return Html::button('<i class="glyphicon glyphicon-pencil"></i>', $options);
            case AccessLevels::READONLY :
                $options ['title'] = 'View';
                $options ['id'] = 'cview';
                return Html::button('<i class="glyphicon glyphicon-eye-open"></i>', $options);
            default :
                return '';
        }
    }

    private function addSearchbox() {
        if ($this->treeviewparser->searchbox == NULL) {
            return '';
        }
        $searchrender = '<div id="divsearch" class="row" style="margin:0 1px;border-bottom:1px solid lightgrey;padding: 5px 0 5px;background-color:whitesmoke;">
                            <button style="width:70px;float:right;margin-left:-15px;padding:3px;background-color:grey;color:white;" type="button" 
                                    onclick="coreWebApp.findInTree($(\'#' . $this->treeviewparser->searchbox->id . '\'))" class=btn btn-default">Find</button>
                            <div style="width:370px;float:right;margin-top:5px;" class=" field-' . $this->treeviewparser->searchbox->id . '">
                                <input type="text" id="' . $this->treeviewparser->searchbox->id . '" class="smartcombo col-sm-11" name="account_id"
                                 data-NamedLookup="' . $this->treeviewparser->searchbox->lookup->namedLookup . '" 
                                 data-DisplayMember="' . $this->treeviewparser->searchbox->lookup->displayMember . '" 
                                 data-ValueMember="' . $this->treeviewparser->searchbox->lookup->valueMember . '" 
                                 filterevent="' . $this->treeviewparser->searchbox->lookup->filterEvent . '" 
                                 data-filter="' . $this->treeviewparser->searchbox->lookup->filter . '" notyetsmart="true" tabindex="0" style="border:1px solid darkgrey;border-radius:5px;">                                
                             </div>
                            <label style="width:100px;float:right;margin-left:50px;margin-top:10px;">' . $this->treeviewparser->searchbox->label . '</label>                             
                        </div>';
        return $searchrender;
    }

    private function addinfo() {
        $at = '<div id="qp" 
                    qp-route="' . \yii\helpers\Html::encode(str_replace('@app/', '', $this->treeviewparser->modulePath)) . '" 
                    qp-formName="' . \yii\helpers\Html::encode($this->treeviewparser->childEditForm) . '" 
                    qp-keyField="' . $this->treeviewparser->childKeyField .
                '" hidden></div>';
        return '';
    }

}

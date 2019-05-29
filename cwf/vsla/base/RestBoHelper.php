<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\base;

/**
 * Description of RestBoHelper
 *
 * @author girish
 */
class RestBoHelper {
    
    /* This is the cache expiry value in seconds
     * Modify this to provide longer cache expiry
     */
    const CACHE_DURATION = 3600; // persist for 60 minutes

    public function actionFetch(RestBoHelperOption $helperOption) {
        $result = array();
        $helperOption->modulePath = \yii::getAlias($helperOption->modulePath);

        $xBo = new \app\cwf\vsla\xmlbo\XboBuilder($helperOption->modulePath . DIRECTORY_SEPARATOR . $helperOption->bo_id . '.xml');
        $boInst = $xBo->buildBO($helperOption->inParam);
        if ($boInst instanceof \app\cwf\vsla\xmlbo\DocBo) {
            \app\cwf\vsla\security\AccessManager::applySecurity($boInst);
            $xBo->callEventHandlerMethod('afterApplySecurity');
            if ($xBo->access_level > 1) {
                $boInst->setAllowArchive(true);
            }
        } else {
            // For Master Bo
            if ($xBo->access_level > 1) {
                $boInst->setAllowSave(true);
                $uact = \app\cwf\vsla\security\AccessManager::verifyUnpostDelete($boInst['__bo']);
                $pri_key = $xBo->boparser->bometa->controlTable->primaryKey;
                $instid = $boInst[$pri_key];
                if ($instid != -1 && $instid != '') {
                    $boInst->setAllowDelete($uact->allow_delete);
                    $boInst->setAllowAuditTrail($uact->allow_audit_trail);
                } else {
                    $boInst->setAllowDelete(FALSE);
                }
            }
        }

        if ($xBo->access_level == -1 || $xBo->access_level == 0) {
            $result['status'] = 'NOACCESS';
        } else {
            // prepare response
            $result['boData'] = $boInst->BOPropertyBag();
            $result['docSecurity'] = $boInst->getDocSecurity();

            if ($boInst instanceof \app\cwf\vsla\xmlbo\DocBo) {
                $result['docStageInfo'] = $boInst->getDocStageInfo();
                $result['docComments'] = \app\cwf\vsla\workflow\DocWorkflow::getWfHistory($boInst['__doc_id']);
                $result['docArchiveStatus'] = \app\cwf\vsla\workflow\DocWorkflow::getArchiveStatus($boInst['__doc_id']);
            } else {
                $result['docStageInfo'] = [];
                $result['docComments'] = [];
            }

            // Include Tran meta data information
            $tranMetas = array();
            foreach ($boInst->getAllTranMetaData() as $key => $val) {
                $tranMetas[] = ['tranName' => $key, 'tranMeta' => $val];
            }
            // Fetch Pre-lookup text
            if (isset($helperOption->formName) && $helperOption->formName != '') {
                $xmlViewPath = $helperOption->modulePath . DIRECTORY_SEPARATOR . $helperOption->formName . '.xml';
                $xView = simplexml_load_file($xmlViewPath);
                $preLookup = new \app\cwf\vsla\ui\PreLookup();
                $preLookup->init($boInst, $xView);
                $result['preLookupData'] = $preLookup->getPreLookupData();
            } else {
                $result['preLookupData'] = [];
            }

            $fileresult = [];
            if (\app\cwf\vsla\security\SessionManager::getInstance()->getSessionVariable('company_id') != -1) {
                // Fetch attachement list
                $dmfiles = \app\cwf\fwShell\models\DmFile::getDocs($boInst->BOPropertyBag()['__bo'], $boInst['__doc_id']);
                if ($dmfiles != NULL) {
                    foreach ($dmfiles->Rows() as $dmfile) {
                        $fileresult[] = ['fileName' => $dmfile['file_name'], 'fileid' => $dmfile['dm_file_id']];
                    }
                }
            }

            // if document is archived, prevent further modifications
            if ($boInst instanceof \app\cwf\vsla\xmlbo\DocBo) {
                if ($result['docArchiveStatus'] == TRUE) {
                    $result['docSecurity']['allowSave'] = FALSE;
                    $result['docSecurity']['allowDelete'] = FALSE;
                    $result['docSecurity']['allowPost'] = FALSE;
                    $result['docSecurity']['allowUnpost'] = FALSE;
                    $result['docSecurity']['allowApprove'] = FALSE;
                    $result['docSecurity']['allowReject'] = FALSE;
                    $result['docSecurity']['allowSend'] = FALSE;
                }
            }

            $result['dmfiles'] = $fileresult;
            $result['tranMetaData'] = $tranMetas;
            $result['status'] = 'OK';
        }
        // Persist the BO     
        $sdata = serialize($boInst);
        \yii::$app->cache->set($boInst['__instanceid'], $sdata, self::CACHE_DURATION);
        return $result;
    }

    public function actionSave(RestBoHelperOption $helperOption) {
        $result = new SaveResult();
        $helperOption->modulePath = \yii::getAlias($helperOption->modulePath); //Resolve alias to physical path

        \yii::beginProfile('XboBuilder');
        $xBo = new \app\cwf\vsla\xmlbo\XboBuilder($helperOption->modulePath . DIRECTORY_SEPARATOR . $helperOption->bo_id . '.xml');
        \yii::endProfile('XboBuilder');

        \yii::beginProfile('buildBO');
        // This is the cached instance id (from fetch). use it to reconstruct boinst
        $cbo_iid = $helperOption->postData->{'__instanceid'};
        $cbo_data = \yii::$app->cache->get($cbo_iid);
        \yii::$app->cache->delete($cbo_iid);
        $boInst = unserialize($cbo_data);
        if (!$boInst) {
            $boInst = $xBo->buildBO($helperOption->inParam);
            if (!$xBo->isNewDocument($boInst)) {
                // If not new document, try to match last_updated
                if (strtotime($boInst->last_updated) != strtotime($helperOption->postData->last_updated)) {
                    // Do not allow save if the timestamps do not match
                    $boInst->addBRule('Timestamp mismatch. Please re-open to edit');
                    $result->SaveStatus = "FAILED";
                    $result->BrokenRules = $boInst->getBRules();
                    return $result;
                }
            }
        }
        $xBo->bindEventHandler($boInst);
        \yii::endProfile('buildBO');

        if ($xBo->access_level == -1 || $xBo->access_level == 0) {
            $result->SaveStatus = 'NOACCESS';
        } else {
            \yii::beginProfile('reverseBind');
            // Take json encode image of BOPropertyBag for Audit Trail
            $boiAT = json_encode($boInst->BOPropertyBag(), JSON_HEX_APOS);
            // resolve the view path
            $xmlViewPath = $helperOption->modulePath . DIRECTORY_SEPARATOR . $helperOption->formName . '.xml';
            // Reverse bind the BO
            if ($boInst instanceof \app\cwf\vsla\xmlbo\DocBo && $boInst->status != \app\cwf\vsla\xmlbo\DocBo::STATUS_POSTED) {
                $jsonMapper = new JsonUtil($boInst, $helperOption->inParam, $xmlViewPath);
                $jsonMapper->reverseBind($helperOption->postData);
            } elseif ($boInst instanceof \app\cwf\vsla\xmlbo\MastBo) {
                $jsonMapper = new JsonUtil($boInst, $helperOption->inParam, $xmlViewPath);
                $jsonMapper->reverseBind($helperOption->postData);
            }
            \yii::endProfile('reverseBind');

            \yii::beginProfile('validateBO-viewparser');
            // validate the BO
            $viewParser = \app\cwf\vsla\ui\ViewParserFactory::getParser($xmlViewPath, "", $helperOption->inParam);

            $validatorClass = $helperOption->moduleNamespace . '\\' . str_replace("/", "\\", $helperOption->bo_id) . 'Validator';
            \yii::endProfile('validateBO-viewparser');
            \yii::beginProfile('validateBO-init');
            $validatorInstance = new $validatorClass();
            $validatorInstance->initialise($boInst, $helperOption->formName, $xmlViewPath, $xBo->boparser, $helperOption->modulePath, $helperOption->postData, $helperOption->action);
            $validateMethod = 'validate' . substr($helperOption->formName, strpos($helperOption->formName, "/") + 1);
            if ($xBo->boparser->bometa->type == \app\cwf\vsla\xmlbo\BoType::DOCUMENT) {
                if ($boInst->status != \app\cwf\vsla\xmlbo\DocBo::STATUS_POSTED) {
                    // Document is not posted. Therefore call validate before save.
                    $validatorInstance->$validateMethod();
                }
            } else {
                // This is a master. Therefore always validate
                $validatorInstance->$validateMethod();
            }

            \yii::endProfile('validateBO-init');
            \yii::beginProfile('validateBO');
            $wfOption = null;
            if ($xBo->boparser->bometa->type == \app\cwf\vsla\xmlbo\BoType::DOCUMENT) {
                $wfOption = new \app\cwf\vsla\workflow\WfOption();
                $wfOption->doc_action = $helperOption->action;
                $wfOption->user_id_to = $helperOption->postData->__wf_user_id_to;
                $wfOption->doc_sender_comment = $helperOption->postData->__wf_doc_sender_comment;
                $wfOption->next_stage_id = $helperOption->postData->__wf_next_stage_id;
                $wfOption->edit_view = $xmlViewPath;

                if ($helperOption->action == \app\cwf\vsla\workflow\DocWorkflow::WF_UNPOST && $boInst->status === \app\cwf\vsla\xmlbo\DocBo::STATUS_POSTED) {
                    // validate the BO before unpost                       
                    if ($validatorInstance->docFiscalMonthClosed($boInst->getDocDate())) {
                        $boInst->addBRule('Document belongs to a closed fiscal month. Unpost not allowed.');
                    }
                    $validatorInstance->validateBeforeUnpost();
                } elseif ($helperOption->action == \app\cwf\vsla\workflow\DocWorkflow::WF_POST && $boInst->status != \app\cwf\vsla\xmlbo\DocBo::STATUS_POSTED) {
                    // validate the BO before Post        
                    $validatorInstance->validateBeforePost();
                } else {
                    if (count($xBo->boparser->bometa->docStageInfo) > 0) {
                        if ($wfOption->next_stage_id != '') {
                            $validatorInstance->validateBeforeStage($wfOption);
                        }
                    }
                }
            }

            \yii::endProfile('validateBO');
            if (count($boInst->getBRules()) == 0) {
                if (count($boInst->getWarnings()) == 0 || $helperOption->saveOnWarn == true) {
                    // Do actual save job on server
                    \yii::beginProfile('saveBO');
                    $xBo->saveBO($boInst, $wfOption);
                    \yii::endProfile('saveBO');
                    // Return the result
                    $result->SaveStatus = "OK";
                    if ($boInst instanceof \app\cwf\vsla\xmlbo\DocBo) {
                        \app\cwf\vsla\security\AccessManager::applySecurity($boInst);
                        $xBo->callEventHandlerMethod('afterApplySecurity');
                    } else {
                        // For Master Bo
                        if ($xBo->access_level > 0) {
                            $boInst->setAllowSave(true);
                            $uact = \app\cwf\vsla\security\AccessManager::verifyUnpostDelete($boInst['__bo']);
                            $boInst->setAllowDelete($uact->allow_delete);
                        }
                    }
                    // reset the instance id(to avoid duplicate submits) and return propertyBag
                    $boInst->__instanceid = uniqid();
                    $result->BOPropertyBag = $boInst->BOPropertyBag();
                    $result->docSecurity = $boInst->getDocSecurity();
                    if ($boInst instanceof \app\cwf\vsla\xmlbo\DocBo) {
                        $result->docStageInfo = $boInst->getDocStageInfo();
                        $result->docComments = \app\cwf\vsla\workflow\DocWorkflow::getWfHistory($boInst['__doc_id']);
                        $result->docArchiveStatus = \app\cwf\vsla\workflow\DocWorkflow::getArchiveStatus($boInst['__doc_id']);
                    }
                    // Fetch Pre-lookup text
                    \yii::beginProfile('PreLookup');
                    if (isset($helperOption->formName) && $helperOption->formName != '') {
                        $xmlViewPath = $helperOption->modulePath . '/' . $helperOption->formName . '.xml';
                        $xView = simplexml_load_file($xmlViewPath);
                        $preLookup = new \app\cwf\vsla\ui\PreLookup();
                        $preLookup->init($boInst, $xView);
                        $result->preLookupData = $preLookup->getPreLookupData();
                    } else {
                        $result->preLookupData = [];
                    }
                    \yii::endProfile('PreLookup');
                    $this->rebuildParams($helperOption->inParam, $boInst);
                    $result->Params = $helperOption->inParam;

                    // Create warning table entry for saving with warnings
                    if ($helperOption->saveOnWarn == true) {
                        $xBo->CreateWarningEntry($boInst, $xBo->logAction, $boiAT);
                        $boInst->resetWarnings();
                    }

                    // Create Audit Trail entry of BO Image
                    $xBo->CreateLogEntry($boInst, $xBo->logAction, $boiAT);

                    // Persist the BO
                    $sdata = serialize($boInst);
                    \yii::$app->cache->set($boInst->__instanceid, $sdata, self::CACHE_DURATION);
                } else {
                    $result->SaveStatus = "WARNING";
                    $result->Warnings = $boInst->getWarnings();
                    if (isset($cbo_data)) {
                        \yii::$app->cache->set($cbo_iid, $cbo_data, self::CACHE_DURATION);
                    }
                }
            } else {
                $result->SaveStatus = "FAILED";
                $result->BrokenRules = $boInst->getBRules();
                if (isset($cbo_data)) {
                    \yii::$app->cache->set($cbo_iid, $cbo_data, self::CACHE_DURATION);
                }
            }
        }
        \yii::endProfile('actionSave');
        return $result;
    }

    public function actionDelete(RestBoHelperOption $helperOption) {
        $result = new SaveResult();
        $helperOption->modulePath = \yii::getAlias($helperOption->modulePath); //Resolve alias to physical path        

        $xBo = new \app\cwf\vsla\xmlbo\XboBuilder($helperOption->modulePath . DIRECTORY_SEPARATOR . $helperOption->bo_id . '.xml');
        $boInst = $xBo->buildBO($helperOption->inParam);
        if ($xBo->access_level == -1 || $xBo->access_level == 0) {
            $result->SaveStatus = 'NOACCESS';
        } else {
            // Take json encode image of BOPropertyBag for Audit Trail
            $boiAT = json_encode($boInst->BOPropertyBag(), JSON_HEX_APOS);

            $validatorClass = $helperOption->moduleNamespace . '\\' . str_replace("/", "\\", $helperOption->bo_id) . 'Validator';
            $validatorInstance = new $validatorClass();
            $validatorInstance->initialise($boInst, $helperOption->formName, '', $xBo->boparser);
            $validatorInstance->validateBeforeDelete();

            $result = new SaveResult();
            if (count($boInst->getBRules()) == 0) {
                // Do actual delete job on server
                if ($xBo->Delete($boInst)) {
                    $result->SaveStatus = "OK";

                    // Create Audit Trail entry of BO Image
                    $xBo->CreateLogEntry($boInst, $xBo->logAction, $boiAT);
                }
            } else {
                $result->SaveStatus = "FAILED";
                $result->BrokenRules = $boInst->getBRules();
            }
        }
        return $result;
    }

    public function actionArchive(RestBoHelperOption $helperOption, $action, $msg) {
        $result = new SaveResult();
        $helperOption->modulePath = \yii::getAlias($helperOption->modulePath); //Resolve alias to physical path        

        $xBo = new \app\cwf\vsla\xmlbo\XboBuilder($helperOption->modulePath . DIRECTORY_SEPARATOR . $helperOption->bo_id . '.xml');
        $boInst = $xBo->buildBO($helperOption->inParam);
        if ($xBo->access_level == -1 || $xBo->access_level == 0) {
            $result->SaveStatus = 'NOACCESS';
        } else {
            // Take json encode image of BOPropertyBag for Audit Trail
            $boiAT = json_encode($boInst->BOPropertyBag(), JSON_HEX_APOS);

            $validatorClass = $helperOption->moduleNamespace . '\\' . str_replace("/", "\\", $helperOption->bo_id) . 'Validator';
            $validatorInstance = new $validatorClass();
            $validatorInstance->initialise($boInst, $helperOption->formName, '', $xBo->boparser);
            $validatorInstance->validateBeforeArchive($action);

            $result = new SaveResult();
            if (count($boInst->getBRules()) == 0) {
                // Do actual delete job on server
                if ($xBo->Archive($boInst, $helperOption, $action, $msg)) {
                    $result->SaveStatus = "OK";

                    // Create Audit Trail entry of BO Image
                    $xBo->CreateLogEntry($boInst, $xBo->logAction, $boiAT);
                }
            } else {
                $result->SaveStatus = "FAILED";
                $result->BrokenRules = $boInst->getBRules();
            }
        }
        return $result;
    }

    private function rebuildParams(&$inParam, $boInst) {
        foreach (array_keys($inParam) as $paramKey) {
            if (array_key_exists($paramKey, $boInst->BOPropertyBag())) {
                $inParam[$paramKey] = $boInst->$paramKey;
            }
        }
    }

}

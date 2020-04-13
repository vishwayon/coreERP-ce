<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\security;

/**
 * Description of SessionManager
 *
 * @author girish
 */
class SessionManager {

    //put your code here
    private static $instance = null;
    private $userInfo = null;

    /**
     * @param AuthInfo $authInfo
     */
    private function __construct($authInfo) {
        $this->userInfo = UserInfo::AuthLogin($authInfo);
        if ($this->userInfo->getAuthStatus() && $this->userInfo->getSessionCreated()) {
            \Yii::$app->cache->cachePath = '@runtime/cache/sid' . (string) $this->userInfo->getSession_ID();
            \yii::$app->cache->init();
        }
    }

    /**
     * 
     * @param AuthInfo $authInfo
     * @return SessionManager
     */
    public static function getInstance($authInfo = null) {
        if (!is_null($authInfo)) {
            // If authifo is available, always create a new session
            self::$instance = new SessionManager($authInfo);
        }
        return self::$instance;
    }

    public static function hasInstance() {
        if (self::$instance == null) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    public static function getAuthStatus() {
        if (self::$instance == null) {
            return FALSE;
        } else {
            return self::$instance->getUserInfo()->getAuthStatus();
        }
    }

    public static function getSessionCreated() {
        if (self::$instance == null) {
            return FALSE;
        } else {
            return self::$instance->getUserInfo()->getSessionCreated();
        }
    }

    public static function getSessionVariable($key) {
        if (self::$instance == null) {
            return null;
        } else {
            return self::getInstance()->getUserInfo()->getSessionVariable($key);
        }
    }

    private static $user_time_zone = null;

    /*
     * Returns an instance of DateTimeZone preferred as set in user_time_zone
     * If nothing is set, yii->app->config timeZone is returned
     */

    public static function getUserTimeZone() {
        if (self::$user_time_zone == null) {
            $utz = self::getSessionVariable('user_time_zone');
            if ($utz != null) {
                self::$user_time_zone = new \DateTimeZone($utz);
            }
        }
        return self::$user_time_zone != null ? self::$user_time_zone : new \DateTimeZone(\yii::$app->timeZone);
    }

    private static $ccy_system = null;

    public static function getCCYSystem() {
        if (self::$ccy_system == null) {
            $ccy = self::getSessionVariable('currency_system');
            if ($ccy != null) {
                self::$ccy_system = $ccy;
            }
        }
        return self::$ccy_system == 1 ? 'l' : 'm';
    }

    /**
     * Gets the Authenticated user info
     * @return UserInfo
     */
    public function getUserInfo() {
        return $this->userInfo;
    }

    public function isMobile() {
        return FALSE;
    }

    /**
     * Handles the specified request.
     * @param Request $request the request to be handled 
     * This method should be called in index.php as yii application EVENT_BEFORE_REQUEST
     * 
     * \yii\base\Event::on(\yii\web\Application::className(), \yii\web\Application::EVENT_BEFORE_REQUEST, function($event) {
     *      $request = $event->sender->getRequest();
     *      
     * }
     *      */
    public static function createUserSessionForCore($request) {
        // create a session with whatever information is available is available
        // An auth_id is compulsory. This comes from the cookie        
        $authInfo = new \app\cwf\vsla\security\AuthInfo();
        // First get auth_id (This is in the php session)
        if (\yii::$app->session->has('authid')) {
            $authInfo->auth_id = \yii::$app->session['authid'];
        }
        // See if the header has session id (This is used by ajax calls)
        if ($request->getHeaders()->has('core-sessionid')) {
            $authInfo->session_id = $request->getHeaders()->get('core-sessionid');
        } else {
            // See if query param has session id (This is used by browswe requests)
            $qp = $request->queryParams;
            if (array_key_exists('core-sessionid', $qp)) {
                $authInfo->session_id = $request->queryParams['core-sessionid'];
            }
        }
        if ($authInfo->auth_id != '') {
            // Auth info would contain core-session id if it is available
            self::getInstance($authInfo);
        }
        \yii::$app->session->close();
    }

    /**
     * Handles the specified request.
     * @param Request $request the request to be handled 
     * This method should be called in index.php as yii application EVENT_BEFORE_REQUEST
     * 
     * \yii\base\Event::on(\yii\web\Application::className(), \yii\web\Application::EVENT_BEFORE_REQUEST, function($event) {
     *      $request = $event->sender->getRequest();
     *      
     * }
     *      */
    public static function createUserSessionForCoreAPI($request) {
        // create a session with whatever information is available is available
        // An auth_id is compulsory. This comes from the header        
        $authInfo = new \app\cwf\vsla\security\AuthInfo();
        // First get auth_id (This is in the php session)
        if ($request->getHeaders()->has('auth-id')) {
            $authInfo->auth_id = $request->getHeaders()->get('auth-id');
        }
        // See if the header has session id (This is used by ajax calls)
        if ($request->getHeaders()->has('core-sessionid')) {
            $authInfo->session_id = $request->getHeaders()->get('core-sessionid');
        } else {
            // See if query param has session id (This is used by browswe requests)
            $qp = $request->queryParams;
            if (array_key_exists('core-sessionid', $qp)) {
                $authInfo->session_id = $request->queryParams['core-sessionid'];
            }
        }
        if ($authInfo->auth_id != '') {
            // Auth info would contain core-session id if it is available
            self::getInstance($authInfo);
        }
    }

    public static function getTitle() {
        $branch_id = self::getSessionVariable('branch_id');
        $title = 'coreERP';
        if ($branch_id != null && $branch_id != -1) {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select company_code || branch_code as cc_code From sys.branch Where branch_id=:pbranch_id');
            $cmm->addParam('pbranch_id', $branch_id);
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
            if (count($dt->Rows()) == 1) {
                $title .= '-' . $dt->Rows()[0]['cc_code'];
            }
            $finyear = self::getSessionVariable('finyear');
            if ($finyear != null && $finyear != '') {
                $title .= "[" . $finyear . "]";
            }
        }
        return $title;
    }

    private static $branch_gst_state_info = null;

    /**
     * Returns an Array of Connected Branch GST Information
     * 
     * @return array Following is returned<br>
     * 'gstin' => Branch GSTIN<br>
     * 'gst_state_id' => Branch GST State ID<br>
     * 'gst_state_code' => Branch GST State Code<br>
     * 'state_name' => Branch State Name<br>
     * 'gst_state' => gst_state_code - state_name
     */
    public static function getBranchGstInfo(): array {
        if (self::$branch_gst_state_info == null) {
            $branch_id = self::getSessionVariable('branch_id');
            if ($branch_id != -1) {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText("Select a.gstin, a.gst_state_id, b.gst_state_code, b.state_name, 
                                        b.gst_state_code || '-' || b.state_name as gst_state,
                                        coalesce((a.annex_info->>'gst_sez_wop')::Boolean, false) gst_sez_wop,
                                        coalesce((a.annex_info->>'gst_exp_wop')::Boolean, false) gst_exp_wop
                                    From sys.branch a
                                    Inner Join tx.gst_state b On a.gst_state_id = b.gst_state_id
                                    Where a.branch_id=:pbranch_id");
                $cmm->addParam('pbranch_id', $branch_id);
                $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
                if (count($dt->Rows()) == 1) {
                    self::$branch_gst_state_info = $dt->Rows()[0];
                }
            }
        }
        return self::$branch_gst_state_info;
    }

}

<?php

namespace app\cwf\sys\subscription;

class modelSubscription {

    public $subscription_id = -1;
    public $user_id = -1;
    public $report_path = '';
    public $report_name = '';
    public $report_options = NULL;
    public $schedule_info = NULL;
    public $is_active = false;
    public $user_mail = '';
    public $user_active = false;
    public $user_name = '';

    public function __construct($subscr_id) {
        if ($subscr_id != null && $subscr_id != -1) {
            $this->subscription_id = $subscr_id;
            $this->fetchDetails();
        }
    }

    private function fetchDetails() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * from sys.subscription where subscription_id=:psubscription_id');
        $cmm->addParam('psubscription_id', $this->subscription_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        if (count($dt->Rows()) == 1) {
            $this->user_id = (int) $dt->Rows()[0]['user_id'];
            $this->report_path = $dt->Rows()[0]['report_path'];
            $this->report_options = $dt->Rows()[0]['report_options'];
            $this->is_active = (\boolval($dt->Rows()[0]['is_active']));
            $this->report_name = $dt->Rows()[0]['sub_name'];
            $this->get_userinfo();
            $company_id = (int) $dt->Rows()[0]['company_id'];
            $logonselection = new \app\cwf\fwShell\models\LogonSelection();
            $logonselection->setCompanyInfo($company_id);
            $logonselection->setDefault();
        }
    }

    private function validate() {
        $brules = [];
        if ($this->report_path == NULL || $this->report_path == '') {
            $brules[] = 'Report path can not be null';
        }
        if ($this->report_options == NULL) {
            $brules[] = 'Report options can not be null';
        }
        if ($this->schedule_info == NULL) {
            $brules[] = 'Schedule info can not be null';
        }
        return $brules;
    }

    public function addUpdate() {
        $brules = $this->validate();
        if (count($brules) == 0) {
            if ($this->subscription_id == -1) {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('Select * From sys.sp_get_mast_id(:pcompany_id, :pmast_seq_type, :pnew_mast_id)');
                $cmm->addParam(':pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
                $cmm->addParam(':pmast_seq_type', 'sys.subscription');
                $cmm->addParam(':pnew_mast_id', -1, \app\cwf\vsla\data\SqlParamType::PARAM_INOUT);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, null, \app\cwf\vsla\data\DataConnect::MAIN_DB);
                $this->subscription_id = $cmm->getParamValue(':pnew_mast_id');
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('Insert into sys.subscription( subscription_id, company_id, user_id, 
                                    report_path, report_options, schedule_info, sub_name) 
                                values (:psubscription_id, :pcompany_id, :puser_id, :preport_path, 
                                    :preport_options, :pschedule_info, :psub_name)');
                $cmm->addParam(':psubscription_id', $this->subscription_id);
                $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
                if ($this->user_id == -1) {
                    $cmm->addParam(':puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
                } else {
                    $cmm->addParam(':puser_id', $this->user_id);
                }
                $cmm->addParam(':preport_path', $this->report_path);
                $cmm->addParam(':preport_options', json_encode($this->report_options));
                $cmm->addParam(':pschedule_info', json_encode($this->schedule_info));
                $cmm->addParam(':psub_name', $this->report_name);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, null, \app\cwf\vsla\data\DataConnect::MAIN_DB);
                subscriptionHelper::addJob($this);
                return 'OK';
            } else {
                $cmm = new \app\cwf\vsla\data\SqlCommand();
                $cmm->setCommandText('Update sys.subscription set report_options = :preport_options, schedule_info = :pschedule_info where subscription_id = :psubscription_id');
                $cmm->addParam(':preport_options', json_encode($this->report_options));
                $cmm->addParam(':pschedule_info', json_encode($this->schedule_info));
                $cmm->addParam(':psubscription_id', $this->subscription_id);
                \app\cwf\vsla\data\DataConnect::exeCmm($cmm, null, \app\cwf\vsla\data\DataConnect::MAIN_DB);
                return 'OK';
            }
        } else {
            return $brules;
        }
    }

    public function remove() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Update sys.subscription set is_active = false where subscription_id=:psubscription_id');
        $cmm->addParam('psubscription_id', $this->subscription_id);
        \app\cwf\vsla\data\DataConnect::exeCmm($cmm, null, \app\cwf\vsla\data\DataConnect::MAIN_DB);
    }

    public function exec() {
        if (!$this->is_active || !$this->user_active) {
            return 'Subscription is not active.';
        }
        $params = [];
        $param = json_decode($this->report_options);
        if (json_last_error() === JSON_ERROR_NONE) {
            
        } elseif (is_array($this->report_options)) {
            $param = $this->report_options;
        }
        foreach ($param as $key => $value) {
            $params[$key] = subscriptionHelper::resolveValue($value);
        }
//        $param = [];
//        $param['_csrf'] = 'TkhiY3NBeUoKPz0sJwYoLTw.NwUmKkA9BCojIR4RNAx9AVZQMQQ6AA==';
//        $param['paccount_id'] = -99;
//        $param['pbranch_id'] = 1000001;
//        $param['pcategory'] = 'Bank';
//        $param['pdisplay_fc_amount'] = 0;
//        $param['pfrom_date'] = '01/04/2016';
//        $param['pshow_cheque_details'] = 1;
//        $param['pshow_narration'] = 1;
//        $param['pto_date'] = '01/12/2016';
//        $param['reqtime'] = '1480495413';
//        $param['xmlPath'] = '@app/core/ac/reports/generalLedger/GeneralLedger.xml';
        $params['xmlPath'] = $this->report_path;
        $outputType = \app\cwf\vsla\render\JReportHelper::OUTPUT_PDF;
        $jr = new \app\cwf\vsla\render\JReportHelper();
        $jrResult = $jr->renderReport($params, $outputType);
        $res = $this->store($jrResult);
        if ($res) {
            $src = $jrResult['result']->ReportRenderedPath;
            $src_name = substr($src, strrpos($src, '/'), strlen($src) - 1);
            $atch = \yii::getAlias('@runtime/attachments') . '/' . $src_name;
            $jrResult['attached_path'] = $atch;
            $this->mail($atch);
        }
        if ($jrResult['status'] == 'OK') {
            \Yii::$app->response->headers->add('Output-Type', 'application/json');
            return json_encode($jrResult['result']);
        } else {
            \Yii::$app->response->headers->add('Output-Type', 'text/html');
            return $jrResult['msg'];
        }
    }

    private function store($report_result) {
        $src = $report_result['result']->ReportRenderedPath;
        if (!file_exists(\yii::getAlias('@runtime/attachments'))) {
            mkdir(\yii::getAlias('@runtime/attachments'));
        }
        $src_name = substr($src, strrpos($src, '/'), strlen($src) - 1);
        return \copy($src, \yii::getAlias('@runtime/attachments') . '/' . $src_name);
    }

    private function mail($path) {
        $body = '   ' . $this->report_name . ' dated '
                . (new \DateTime())->format('d M,Y H:i:s');
        $subject = 'CoreERP report';
        $from = \yii::$app->params['cwf_config']['mailer']['username'];
        \app\cwf\vsla\utils\MailHelper::SendMailAttachment($this->user_mail, $from, $body, $subject, '', '', '', $path);
    }

    private function get_userinfo() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * from sys.user where user_id=:puser_id');
        $cmm->addParam('puser_id', $this->user_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        if (count($dt->Rows()) == 1) {
            if (\app\cwf\vsla\utils\MailHelper::mailid_valid($dt->Rows()[0]['email'])) {
                $this->user_mail = $dt->Rows()[0]['email'];
                $this->user_name = $dt->Rows()[0]['full_user_name'];
                $this->user_active = \boolval($dt->Rows()[0]['is_active']);
            }
        }
    }
}

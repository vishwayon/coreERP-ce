<?php

namespace app\cwf\sys\wfApproval;

/**
 * Description of ModelWfApproval
 *
 * @author dev
 */
class ModelWfApproval {

    const PENDING = 0;
    const APPROVED = 1;
    const REJECTED = 2;
    const CREDITLIMIT = 'CL';
    const INVOICEOVERDUE = 'IO';

    public $wf_ar_id = -1;
    public $bo_id = '';
    public $branch_id = -1;
    public $doc_id = '';
    public $doc_date;
    public $wf_desc = '';
    public $user_from;
    public $route = '';
    public $formname = '';
    public $formparams = '';
    public $wf_comment = '';
    public $added_on;
    public $wf_approved = false;
    public $status = 'ERROR';
    public $dt_request;
    public $brokenrules = array();
    public $cl_val = 0.00;
    public $io_val = 0.00;
    public $cl_user_to = -1;
    public $io_user_to = -1;

    public function __construct() {
        
    }

    public static function create($params) {
        $model = new ModelWfApproval();
        if (key_exists('bo_id', $params) && $params['bo_id'] != '') {
            $model->bo_id = $params['bo_id'];
        } else {
            $model->brokenrules[] = 'Document type is missing.';
        }
        if (key_exists('doc_id', $params) && $params['doc_id'] != '') {
            $model->doc_id = $params['doc_id'];
        } else {
            $model->brokenrules[] = 'Document ID is missing.';
        }
        if (key_exists('doc_date', $params) && $params['doc_date'] != '') {
            $model->doc_date = $params['doc_date'];
        } else {
            $model->brokenrules[] = 'Document date is missing.';
        }
        if (key_exists('route', $params) && $params['route'] != '') {
            $model->route = $params['route'];
        } else {
            $model->brokenrules[] = 'Document route is missing.';
        }
        if (key_exists('formname', $params) && $params['formname'] != '') {
            $model->formname = $params['formname'];
        } else {
            $model->brokenrules[] = 'Document form name is missing.';
        }
        if (key_exists('formparams', $params) && $params['formparams'] != '') {
            $model->formparams = $params['formparams'];
        } else {
            $model->brokenrules[] = 'Document form params is missing.';
        }
        if (key_exists('wf_ar_id', $params)) {
            $model->wf_ar_id = $params['wf_ar_id'];
        }
        if (key_exists('io_user_to', $params)) {
            $model->io_user_to = floatval($params['io_user_to']);
        }
        if (key_exists('cl_user_to', $params)) {
            $model->cl_user_to = floatval($params['cl_user_to']);
        }
        $model->wf_desc = $params['wf_desc'];
        $model->user_from = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID();
        return $model;
    }
    
    public function getData() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = "SELECT a.wf_ar_id, a.bo_id, a.branch_id, b.branch_name, a.doc_id, a.doc_date, a.wf_desc, a.apr_type, a.user_from,
                        a.user_to, a.route, a.formname, a.formparams, a.added_on,'' as from_user
                    FROM sys.wf_ar a
                    left join sys.branch b on a.branch_id = b.branch_id
                    where a.user_to = :puser_id and a.acted_on is null";
        $cmm->addParam('puser_id', \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $cmm->setCommandText($cmmtext);
        $this->dt_request = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $cmm2 = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext2 = "Select user_id, full_user_name, email from sys.user";
        $cmm2->setCommandText($cmmtext2);
        $dt_user = \app\cwf\vsla\data\DataConnect::getData($cmm2, \app\cwf\vsla\data\DataConnect::MAIN_DB);

        $this->dt_request->addColumn('doc_date_sort', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        $this->dt_request->addColumn('req_date_sort', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_INT, 0);
        $this->dt_request->addColumn('bo_name', \app\cwf\vsla\data\DataAdapter::PHPDATA_TYPE_STRING, 0);
        foreach ($this->dt_request->Rows() as &$row_req) {
            $row_req['doc_date_sort'] = strtotime($row_req['doc_date']);
            $row_req['req_date_sort'] = strtotime($row_req['added_on']);
            foreach ($dt_user->Rows() as $row_user) {
                if ($row_req['user_from'] == $row_user['user_id']) {
                    $row_req['from_user'] = $row_user['full_user_name'];
                }
            }
        }
    }

    public function addWfApproval() {
        if ($this->cl_user_to != -1) {
            $cmmtext = 'Insert into sys.wf_ar (wf_ar_id, bo_id, branch_id,
                        doc_id, doc_date, wf_desc, user_from, user_to, route, formname, formparams, apr_type)
                    values ((select COALESCE(max(wf_ar_id), 0) + 1 from sys.wf_ar), :pbo_id, :pbranch_id,
                        :pdoc_id, :pdoc_date, :pwf_desc, :puser_from, :puser_to, :proute, :pformname, :pformparams, :papr_type)';
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText($cmmtext);
            $cmm->addParam('pbo_id', $this->bo_id);
            $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $cmm->addParam('pdoc_id', $this->doc_id);
            $cmm->addParam('pdoc_date', $this->doc_date);
            $cmm->addParam('pwf_desc', 'Approve credit limit');
            $cmm->addParam('puser_from', $this->user_from);
            $cmm->addParam('puser_to', $this->cl_user_to);
            $cmm->addParam('proute', $this->route);
            $cmm->addParam('pformname', $this->formname);
            $cmm->addParam('pformparams', $this->formparams);
            $cmm->addParam('papr_type', 'CL');
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm);

            // Push entry in Approval mail
            $this->sendMail('CL');
        }
        if ($this->io_user_to != -1) {
            $cmmtext = 'Insert into sys.wf_ar (wf_ar_id, bo_id, branch_id,
                        doc_id, doc_date, wf_desc, user_from, user_to, route, formname, formparams, apr_type)
                    values ((select COALESCE(max(wf_ar_id), 0) + 1 from sys.wf_ar), :pbo_id, :pbranch_id,
                        :pdoc_id, :pdoc_date, :pwf_desc, :puser_from, :puser_to, :proute, :pformname, :pformparams, :papr_type)';
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText($cmmtext);
            $cmm->addParam('pbo_id', $this->bo_id);
            $cmm->addParam('pbranch_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('branch_id'));
            $cmm->addParam('pdoc_id', $this->doc_id);
            $cmm->addParam('pdoc_date', $this->doc_date);
            $cmm->addParam('pwf_desc', 'Approve overdue invoice');
            $cmm->addParam('puser_from', $this->user_from);
            $cmm->addParam('puser_to', $this->io_user_to);
            $cmm->addParam('proute', $this->route);
            $cmm->addParam('pformname', $this->formname);
            $cmm->addParam('pformparams', $this->formparams);
            $cmm->addParam('papr_type', 'IO');
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm);

            // Push entry in Approval mail
            $this->sendMail('IO');
        }
        $this->status = 'OK';
    }

    private function sendMail($apr_type) {
        $mail_to = '';
        $mail_from = '';
        $subject = '';
        $user_to = -1;
        $cmmt = '';
        if ($apr_type == 'CL') {
            $user_to = $this->cl_user_to;
            $cmmt = 'Approve credit limit override';
        } else if ($apr_type == 'IO') {
            $user_to = $this->io_user_to;
            $cmmt = 'Approve overdue invoice override';
        }

        // Get User To emailID        
        $cmmtext1 = "Select email, full_user_name from sys.user where user_id = :puser_id";
        $cmm1 = new \app\cwf\vsla\data\SqlCommand();
        $cmm1->setCommandText($cmmtext1);
        $cmm1->addParam('puser_id', $user_to);
        $dt1 = \app\cwf\vsla\data\DataConnect::getData($cmm1, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        if (count($dt1->Rows()) > 0) {
            $mail_to = $dt1->Rows()[0]['email'];
        }

        // Get User From emailID        
        $cmmtext2 = "Select email, full_user_name from sys.user where user_id = :puser_id";
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText($cmmtext2);
        $cmm->addParam('puser_id', $this->user_from);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);

        $body = '<html><head>Dear Sir/Madam,</head><body>' . '<p> Your approval is required for </p><p><br/><strong>' . $this->bo_id . ' # ' . $this->doc_id . ' </strong></p>';
        $body .= '<p>' . $cmmt . '</p><br/>';
        $body .= 'Kindly check the System -> Approvals Menu to proceed.';
        $cc = '';
        $bcc = '';
        $reply_to = '';

        if (count($dt->Rows()) > 0) {
            $body = $body . ' ' . '<br/><br/>Regards,<br/> ' . $dt->Rows()[0]['full_user_name'] . ' <br/><br/>';
        }
        $body = $body . ' </body></html>';
        $subject = "Approval request for " . $this->doc_id;

        \app\cwf\vsla\utils\MailHelper::SendMail($mail_to, $mail_from, $body, $subject, $cc, $bcc, $reply_to);
    }

    public function closeWfApproval() {
        if ($this->wf_ar_id == '') {
            $this->brokenrules[] = 'Approval request not found.';
            return;
        }
        $this->validateBeforeClosing();
        if (count($this->brokenrules) > 0) {
            return;
        } else {
            $appr = 0;
            if ($this->wf_approved) {
                $appr = self::APPROVED;
            } else {
                $appr = self::REJECTED;
            }
            $cmmtext = 'Update sys.wf_ar
                    set wf_comment = :pwf_comment ,wf_approved = :pwf_approved,
                    acted_on = current_timestamp(0),last_updated = current_timestamp(0)
                    where wf_ar_id =  :pwf_ar_id and acted_on is null';
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText($cmmtext);
            $cmm->addParam('pwf_ar_id', $this->wf_ar_id);
            $cmm->addParam('pwf_comment', $this->wf_comment);
            $cmm->addParam('pwf_approved', $appr);
            \app\cwf\vsla\data\DataConnect::exeCmm($cmm);
            $this->status = 'OK';
        }
    }

    private function validateBeforeClosing() {
        if ($this->doc_id == '') {
            $this->brokenrules[] = 'Doc ID cannot be empty';
        }
        if ($this->wf_approved === FALSE && $this->wf_comment == '') {
            $this->brokenrules[] = 'Rejection remark is required.';
        }
    }

}

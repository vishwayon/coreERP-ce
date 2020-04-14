<?php

namespace app\cwf\sys\pendingDocs;

class ModelPendingDocs {

    public $dt_request;
    public $from_user_id = -1;
    public $to_user_id = -1;
    public $doc_bo_id = '';
    public $branch_id = -1;
    public $doc_action_id = 'W';
    public $brokenrules = array();

    public function __construct() {
        
    }

    public function setFilters($filter) {
        $this->branch_id = $filter['branch_id'] == '' ? -1 : (int) $filter['branch_id'];
        $this->doc_bo_id = $filter['doc_bo_id'] == -1 ? '' : $filter['doc_bo_id'];
        $this->from_user_id = $filter['from_user_id'] == '' ? -1 : (int) $filter['from_user_id'];
        $this->to_user_id = $filter['to_user_id'] == '' ? -1 : (int) $filter['to_user_id'];
        $this->doc_action_id = $filter['doc_action_id'] == '' ? 'W' : $filter['doc_action_id'];
        $this->from_date = $filter['from_date'];
        $this->to_date = $filter['to_date'];
    }

    public function getData() {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = '';
        if ($this->doc_action_id == 'O') {
            $cmmtext = "select a.doc_id, a1.doc_date, 'Saved' as doc_sender_comment, a.user_id_created as user_id_from, a.last_updated::date as doc_sent_on,
                            'O' as doc_action,-1 as user_id_to, 'saved' as doc_stage_id, 'new' as doc_stage_id_from,
                            a.branch_id, b.branch_name, a.bo_id, c.menu_text,
                            '' as from_user,'' as to_user from sys.doc_created a
                    inner join sys.doc_es a1 on a.doc_id = a1.voucher_id 
                    inner join sys.branch b on a.branch_id = b.branch_id 
                    inner join sys.menu c on md5(a.bo_id)::uuid = c.bo_id
                    where 
                    (:pdoc_action_id = 'O') and
                    (a1.doc_date between :pfrom_date and :pto_date) and
                    (case when :pbranch_id <> -1 then (a.branch_id = :pbranch_id or :pbranch_id=0) else :pbranch_id = -1 end) and 
                    (case when :pbo_id <> '' then a.bo_id = :pbo_id else :pbo_id = '' end) and                 
                    (case when :puser_id_from <> -1 then (user_id_created = :puser_id_from or :puser_id_from=-99) else :puser_id_from = -1 end) and                 
                    (:puser_id_to = -1)";
        } else {
            $cmmtext = "select a.doc_id, a.doc_date, a.doc_sender_comment, a.user_id_from, a.doc_sent_on::date, a.doc_action, 
                            a.user_id_to, a.doc_stage_id, a.doc_stage_id_from, a.branch_id, b.branch_name, a.bo_id, c.menu_text,
                            '' as from_user,'' as to_user from sys.doc_wf a                
                    inner join sys.branch b on a.branch_id = b.branch_id 
                    inner join sys.menu c on md5(a.bo_id)::uuid = c.bo_id
                    where 
                    a.doc_action != 'P' and
                    (a.doc_date between :pfrom_date and :pto_date) and
                    (case when :pdoc_action_id <> 'W' then a.doc_action = :pdoc_action_id else :pdoc_action_id = 'W' end) and
                    (case when :pbranch_id <> -1 then (a.branch_id = :pbranch_id or :pbranch_id=0) else :pbranch_id = -1 end) and 
                    (case when :pbo_id <> '' then a.bo_id = :pbo_id else :pbo_id = '' end) and                 
                    (case when :puser_id_from <> -1 then (user_id_from = :puser_id_from or :puser_id_from=-99) else :puser_id_from = -1 end) and                 
                    (case when :puser_id_to <> -1 then (user_id_to = :puser_id_to or :puser_id_to=-99) else :puser_id_to = -1 end) ";
        }
        $cmm->setCommandText($cmmtext);
        $cmm->addParam('puser_id_from', $this->from_user_id);
        $cmm->addParam('puser_id_to', $this->to_user_id);
        $cmm->addParam('pbo_id', $this->doc_bo_id);
        $cmm->addParam('pbranch_id', $this->branch_id);
        $cmm->addParam('pdoc_action_id', $this->doc_action_id);
        $cmm->addParam('pfrom_date', \app\cwf\vsla\utils\FormatHelper::GetDBDate($this->from_date));
        $cmm->addParam('pto_date', \app\cwf\vsla\utils\FormatHelper::GetDBDate($this->to_date));
        $this->dt_request = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmmtext = "Select user_id, full_user_name, email from sys.user";
        $cmm->setCommandText($cmmtext);
        $dt_user = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        foreach ($this->dt_request->Rows() as &$row_req) {
            //$row_req['doc_date_sort'] = strtotime($row_req['doc_sent_on']);
            $row_req['doc_sent_on'] = ['display' => \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($row_req['doc_sent_on']),
                  'sort' => strtotime($row_req['doc_sent_on'])];
            $row_req['doc_date'] = ['display' => \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay($row_req['doc_date']),
                   'sort' => strtotime($row_req['doc_date'])];
            foreach ($dt_user->Rows() as $row_user) {
                if ($row_req['user_id_from'] == $row_user['user_id']) {
                    $row_req['from_user'] = $row_user['full_user_name'];
                }
                if ($row_req['user_id_to'] == $row_user['user_id']) {
                    $row_req['to_user'] = $row_user['full_user_name'];
                }
            }
        }
    }

}

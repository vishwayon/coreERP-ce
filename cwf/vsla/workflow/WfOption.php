<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\vsla\workflow;

/**
 * Description of WfOption
 *
 * @author girish
 */
class WfOption {
    public $doc_id = '';
    public $doc_date;
    public $branch_id = -1;
    public $bo_id = '';
    public $edit_view = '';
    public $doc_name = '';
    public $doc_sender_comment = '';
    public $user_id_from = -1;
    public $doc_sent_on = '';
    public $doc_action = '';
    public $user_id_to = -1;
    public $next_stage_id = '';
    public $doc_stage_id_from = '';
}

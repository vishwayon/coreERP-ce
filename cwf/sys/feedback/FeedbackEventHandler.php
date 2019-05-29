<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\cwf\sys\feedback;

/**
 * Description of AccountHeadEventHandler
 *
 * @author Priyanka
 */
class FeedbackEventHandler extends \app\cwf\vsla\xmlbo\EventHandlerBase {
    
    public function afterFetch($criteriaparam) {
        parent::afterFetch($criteriaparam);        
        if($this->bo->feedback_id=='' or $this->bo->feedback_id=='-1'){
              $this->bo->user_id = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getUser_ID();
              $this->bo->username = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getFullUserName();
        }
    }
    
    public function afterCommit($generatedKeys) {
        parent::afterCommit($generatedKeys);
        if(!$this->bo->is_closed){
            $mail_to= 'priyanka.ninawe@coreerp.com';
            $mail_from = '';
            $body = '<html><head>Dear Sir/Madam,</head><body>' . '<p>Feedback #: '. $this->bo->feedback_id . '<BR/> ' . $this->bo->feedback .' </p> <BR/>Priority: ';
            $body = $body . (string)\app\cwf\vsla\utils\LookupHelper::GetLookupText('../cwf/sys/lookups/Priority.xml', 'description', 'priority_id', $this->bo->priority_id);
            $body = $body . '<BR/> Category: ' . (string)\app\cwf\vsla\utils\LookupHelper::GetLookupText('../cwf/sys/lookups/Category.xml', 'category', 'category_id', $this->bo->category_id);
            
            $subject = "Feedback #: " .$generatedKeys['feedback_id'] .' - '. (string)$this->bo->menu;
            $cc = '';
            $bcc = '';
            $reply_to = '';
                    
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select full_user_name, email from sys.user where user_id =:puser_id');
            $cmm->addParam('puser_id', $this->bo->user_id);               
            $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
            
            if(count($dt->Rows()) > 0)
            {                
                $mail_from = $dt->Rows()[0]['email'];
                $body = $body . ' ' . '<BR/><BR/>Regards,<BR/> ' . $dt->Rows()[0]['full_user_name'] . ' <BR/><BR/>';
            }
            $body = $body . ' </body></html>';
                    
            \app\cwf\vsla\utils\MailHelper::SendMail($mail_to, $mail_from, $body, $subject, $cc, $bcc, $reply_to) ;
        }
    }
}

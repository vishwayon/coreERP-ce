<?php

namespace app\cwf\fwShell\models;

class MenuMobile {

    public $menuitems;
    private $dt;
    private $userinfo;

    public function __construct() {
        if (!\app\cwf\vsla\security\SessionManager::getInstance()->isMobile()) {
            return '<span>This works only on mobile devices.</span>';
        }
        $this->userinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
        if ($this->userinfo === NULL) {
            return;
        } else if (!$this->userinfo->isAdmin() && !$this->userinfo->isOwner()) {
            if ($this->userinfo->getCompany_ID() === -1) {
                return;
            } else if ($this->userinfo->getSessionVariable('finyear_id') === -1) {
                return;
            }
        }
        $this->GetMenuItems();
    }

    private function GetMenuItems() {
        $company_id = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getSessionVariable('company_id');
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.menu_mob where is_hidden=false Order By menu_key');        
        $this->dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($this->dt->Rows()) > 0) {
            $this->setmenu('0', $this->menuitems);
        }else{
            $this->menuitems ='<span>No items available for mobile access.</span>';
        }
    }

    //<a href="#" class="list-group-item">First item</a>

    private function setmenu($parentkey, &$refparent) {
        $baseurl = \Yii::$app->urlManager->getBaseUrl();
        $mitem = '';
        foreach ($this->dt->Rows() as $rw) {
            $mitem .= '<a href="';
            if ($rw['link_path'] == '') {
                $mitem .= '#';
            } else {
                if (strpos($rw['link_path'], 'javascript:coreWebApp.rendercontents(') !== FALSE) {
                    $mitem .= \yii\helpers\Html::encode($rw['link_path']);
                } else {
                    $mitem .= \yii\helpers\Html::encode('javascript:coreWebApp.rendercontents(\'?r='
                                    . $rw['link_path'] . '&menuid=' . (string) $rw['menu_mob_id'] . '\')');
                }
            }
            $mitem .= '" class="list-group-item">';
            $mitem .= (string) $rw['menu_text'];
            $mitem .= '</a>';
        }
        $this->menuitems = $mitem;
    }

}

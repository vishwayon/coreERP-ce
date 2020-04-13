<?php

namespace app\cwf\fwShell\models;

use app\cwf\vsla\data\DataConnect;

class LogonSelection {

    private $userinfo;

    function __construct() {
        $this->userinfo = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo();
    }

    public function getCompanyList() {
        if ($this->userinfo === NULL) {
            return 'User not authenticated. Failed to get company list.';
        }
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        if ($this->userinfo->isOwner()) {
            $cmmtext = 'select company_id, company_name, company_short_name, company_address, database from sys.company
                        where domain_id = :pdomain_id order by company_name asc';
            $cmm->addParam('pdomain_id', $this->userinfo->getSessionVariable('domain_id'));
        } else if ($this->userinfo->isAdmin()) {
            $cmmtext = 'select company_id, company_name, company_short_name, company_address, database from sys.company
                      order by company_name asc';
        } else {
            $cmmtext = 'select a.company_id, b.company_name, b.company_short_name, b.company_address, b.database from sys.user_to_company a '
                    . 'inner Join sys.company b on a.company_id=b.company_id '
                    . 'where (a.user_id=:puserid or :puserid=0) group by a.company_id, b.company_name, b.company_short_name, b.company_address '
                    . ', b.database order by b.company_name asc';
            $cmm->addParam('puserid', $this->userinfo->getUser_ID());
        }

        $cmm->setCommandText($cmmtext);
        $dtcompany = DataConnect::getData($cmm, DataConnect::MAIN_DB);

        $linkitem = '';
        if (count($dtcompany->Rows()) === 0) {
            $linkitem = '<span>You do not have access to any company.<br> Please contact system administrator for granting access.<span>';
        } else {
            $linkitem = '<span>Select Company</span><br><br>'
                    . '<div id="ccompanyinfo" style="overflow: auto;">';
            foreach ($dtcompany->Rows() as $rw) {
                if ($rw['database'] == '') {
                    $linkitem .= <<<lnkitm
                        <a href="javascript:void(0);" class="list-group-item">
                            <h4 class="list-group-item-heading" style="font-weight:bold;">{$rw['company_short_name']}</h4>
                            <p class="list-group-item-text">{$rw['company_name']}</br>
                                <span style="color:RED;">Connectivity issues. Please contact support.</span>
                            </p>
                        </a>
lnkitm;
                } else {
                    $linkitem .= <<<lnkitm
                        <a href="#" class="list-group-item" onclick="getBranchList(this,{$rw['company_id']});">
                            <h4 class="list-group-item-heading" style="font-weight:bold;">{$rw['company_short_name']}</h4>
                            <p class="list-group-item-text">{$rw['company_name']}</br>
                                {$rw['company_address']}
                            </p>
                        </a>
lnkitm;
                }
            }
            $linkitem .= '</div>';
        }
        return $linkitem;
    }

    public function setCompanyInfo($companyid) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * from sys.company where company_id=:pcompany_id');
        $cmm->addParam('pcompany_id', $companyid);
        $dtCompany = DataConnect::getData($cmm, DataConnect::MAIN_DB);
        if (count($dtCompany->Rows()) == 1) {
            $rw = $dtCompany->Rows()[0];
            $this->userinfo->setSessionVariable('company_id', $rw['company_id']);
            $this->userinfo->setSessionVariable('companyDB', $rw['database']);
            $this->userinfo->setSessionVariable('company_name', $rw['company_name']);
            $this->userinfo->setSessionVariable('company_short_name', $rw['company_short_name']);
            $this->userinfo->setSessionVariable('user_time_zone', $rw['user_time_zone']);
            $this->userinfo->persistSessionVariables();
        }
    }

    public function setBranchInfo($brid) {
        if ($this->userinfo->getSessionVariable('companyDB') != '') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select * from sys.branch where branch_id=:pbranch_id');
            $cmm->addParam('pbranch_id', $brid);
            $dtBranch = DataConnect::getData($cmm);
            if (count($dtBranch->Rows()) == 1) {
                $rw = $dtBranch->Rows()[0];
                $this->userinfo->setSessionVariable('branch_id', $rw['branch_id']);
                $this->userinfo->setSessionVariable('branch_name', $rw['branch_name']);
                $this->userinfo->setSessionVariable('date_format', $rw['date_format']);
                $this->userinfo->setSessionVariable('currency_system', $rw['currency_system']);
                $this->userinfo->persistSessionVariables();
            }
        }
    }

    public function getBranchList() {
        if ($this->userinfo->getSessionVariable('companyDB') == '') {
            return 'Company not selected. Failed to retrieve branch list.';
        }
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        if ($this->userinfo->isOwner() || $this->userinfo->isAdmin()) {
            $cmmtext = 'select a.branch_id, a.branch_name, a.branch_address, a.date_format, a.branch_code '
                    . 'from  sys.branch a order by branch_name asc';
        } else {
            $cmmtext = 'Select * From sys.branch Where branch_id in (
                        Select branch_id From sys.user_branch_role Where user_id = :puserid) order by branch_name asc';
//            $cmmtext = 'select a.branch_id, a.branch_name, a.branch_address, a.date_format '
//                    . 'from sys.sp_get_branch_for_user(:puserid) a order by branch_name asc';
            $cmm->addParam('puserid', $this->userinfo->getUser_ID());
        }
        $cmm->setCommandText($cmmtext);
        $dtbranch = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $br = '';
        $br_search = FALSE;
        if (\app\cwf\vsla\utils\SettingsHelper::HasKey('logon_branch_search')) {
            if (strtolower(\app\cwf\vsla\utils\SettingsHelper::GetKeyValue('logon_branch_search')) == 'true') {
                $br_search = TRUE;
            }
        }
        if (count($dtbranch->Rows()) === 0) {
            $br = 'No branch available.';
        } else if ($br_search == TRUE) {
            $br = '<div class="col-sm-12 nopadding">'
                    . '<span class="col-sm-9 nopadding" style="padding-top:10px;">Select Branch</span>'
                    . '<input id="srbrname" type="text" class="col-sm-9 nopadding" style="margin-bottom:10px;padding:5px;" onkeyup="search_branch()"/>'
                    . '</div><br><br>'
                    . '<div id="cbranchinfo" style="overflow: auto;" class="col-sm-12 nopadding">';
            foreach ($dtbranch->Rows() as $rw) {
                $br .= <<<br
                        <a href="#" class="list-group-item" onclick="getFinyearList(this,{$rw['branch_id']});">
                            <h4 class="list-group-item-heading lsbrname" style="font-weight:bold;">{$rw['branch_name']} <span style="font-weight:normal !important;font-size:small;">({$rw['branch_code']})</span></h4>
                            <p class="list-group-item-text">
                                {$rw['branch_address']}
                            </p>
                        </a>
br;
            }
            $br .= '</div>';
        } else {
            $br = '<div class="col-sm-12 nopadding">'
                    . '<span class="col-sm-9 nopadding">Select Branch</span>'
                    . '</div><br><br>'
                    . '<div id="cbranchinfo" style="overflow: auto;" class="col-sm-12 nopadding">';
            foreach ($dtbranch->Rows() as $rw) {
                $br .= <<<br
                        <a href="#" class="list-group-item" onclick="getFinyearList(this,{$rw['branch_id']});">
                            <h4 class="list-group-item-heading" style="font-weight:bold;">{$rw['branch_name']} <span style="font-weight:normal !important;font-size:small;">({$rw['branch_code']})</span></h4>
                            <p class="list-group-item-text">
                                {$rw['branch_address']}
                            </p>
                        </a>
br;
            }
            $br .= '</div>';
        }
        return $br;
    }

    function getFinyearList() {
        if ($this->userinfo->getSessionVariable('companyDB') === '') {
            return '';
        }

        $cmmusr = new \app\cwf\vsla\data\SqlCommand();
        $cmmusr->setCommandText('select finyear_id from sys.user_to_clfinyear where user_id=:puser_id');
        $cmmusr->addParam('puser_id', $this->userinfo->getUser_ID());
        $dtusr = DataConnect::getData($cmmusr);
        $fy2 = [];

        $cmmtxt = new \app\cwf\vsla\data\SqlCommand();
        $cmmtxt->setCommandText('select * from sys.finyear order by year_begin desc');
        $dtfinyear = \app\cwf\vsla\data\DataConnect::getData($cmmtxt);
        if (count($dtfinyear->Rows()) > 0) {
            foreach ($dtfinyear->Rows() as $rw) {
                if ($rw['year_close']) {
                    foreach ($dtusr->Rows() as $rwu) {
                        if ($rw['finyear_id'] === $rwu['finyear_id']) {
                            $fy2[] = $rw;
                            break;
                        }
                    }
                } else {
                    $fy2[] = $rw;
                }
            }
        }

        $fy = '';
        if (count($fy2) === 0) {
            $fy = 'No financial years available.';
        } else {
            $sessionid = $this->userinfo->getSession_ID();
            $fy = '<span>Select Financial Year</span><br><br>'
                    . '<input type="hidden" id="logoncsrf" value="' . \Yii::$app->request->csrfToken . '">'
                    . '<div id="cfinyearinfo" style="overflow: auto;">';
            foreach ($fy2 as $rw) {
                $fy .= '<a href="#" onclick="postFinYear(this, ' . $rw['finyear_id'] . ')" class="list-group-item" >
                            <div class="col-sm-12" style="padding:0;">
                                <h4 class="list-group-item-heading col-sm-6" style="font-weight:bold;padding:0;">' . $rw['finyear_code'] . '</h4>' .
                        ($rw['year_close'] == "true" ? ('<span class="glyphicon glyphicon-lock" style="color:darkgray;float:right;" aria-hidden="true">') : '')
                        . '</span>
                            </div>
                            <p class="list-group-item-text">
                               Financial year starting on <strong>' . $this->formatdate($rw['year_begin']) . '</strong> and ending on <strong>' .
                        $this->formatdate($rw['year_end']) . '</strong>
                            </p>
                        </a>';
            }
            $fy .= '</div>';
        }
        return $fy;
    }

    function formatdate($date) {
        $tmp = new \DateTime($date);
        return $tmp->format('d M, Y');
    }

    //onclick="postFinYear(this,{$rw['finyear_id']});
    function SetFinYearInfo($fyid) {
        if ($this->userinfo->getSessionVariable('companyDB') != '') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('Select * from sys.finyear where finyear_id=:pfinyear_id');
            $cmm->addParam('pfinyear_id', $fyid);
            $dtFinyear = DataConnect::getData($cmm);
            if (count($dtFinyear->Rows()) == 1) {
                $rw = $dtFinyear->Rows()[0];
                $this->userinfo->setSessionVariable('finyear_id', $rw['finyear_id']);
                $this->userinfo->setSessionVariable('finyear', $rw['finyear_code']);
                $this->userinfo->setSessionVariable('year_begin', $rw['year_begin']);
                $this->userinfo->setSessionVariable('year_end', $rw['year_end']);
                $this->userinfo->persistSessionVariables();
            }
        }
    }

    function validateSelection($fyid) {
        if ($this->userinfo->getSessionVariable('companyDB') != '' && $this->userinfo->getSessionVariable('branch_id') != '') {
            $cmm = new \app\cwf\vsla\data\SqlCommand();
            $cmm->setCommandText('select finyear_id from sys.finyear a 
                                    inner join sys.branch b on a.company_id = b.company_id
                                    where a.company_id = :pcompany_id and b.branch_id = :pbranch_id and a.finyear_id = :pfinyear_id');
            $cmm->addParam('pbranch_id', $this->userinfo->getSessionVariable('branch_id'));
            $cmm->addParam('pcompany_id', $this->userinfo->getSessionVariable('company_id'));
            $cmm->addParam('pfinyear_id', $fyid);
            $dtbr = DataConnect::getData($cmm);
            if (count($dtbr->Rows()) == 1) {
                return true;
            }
        }
        return false;
    }

    function setDefault() {
        if ($this->userinfo === NULL) {
            return FALSE;
        }
        if ($this->userinfo->isAdmin() || $this->userinfo->isOwner()) {
            return FALSE;
        }
        if ($this->userinfo->getCompany_ID() == -1) {
            return FALSE;
        }
        $branch_id = -1;
        $fy_id = -1;
        //set branch
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.branch where branch_is_ho=true');
        $dt = DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $branch_id = (int) $dt->Rows()[0]['branch_id'];
            $this->setBranchInfo($branch_id);
        }
        //set finyear
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select * from sys.finyear where current_date>=year_begin and current_date<=year_end');
        $dt = DataConnect::getData($cmm);
        if (count($dt->Rows()) > 0) {
            $fy_id = (int) $dt->Rows()[0]['finyear_id'];
            $this->SetFinYearInfo($fy_id);
        }
        return TRUE;
    }

}

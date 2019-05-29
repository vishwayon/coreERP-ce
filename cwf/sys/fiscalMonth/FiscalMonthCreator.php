<?php

namespace app\cwf\sys\fiscalMonth;

class FiscalMonthCreator {
    private $finyear = '';
    
    public function __construct($finYear) {
        $this->finyear = $finYear;
    }
    
    public function create() : array {
        $company_id = \app\cwf\vsla\security\SessionManager::getInstance()->getUserInfo()->getCompany_ID();
        // Validate finyear
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select finyear_id, year_begin, year_end From sys.finyear Where finyear_code=:pfinyear And company_id=:pcompany_id');
        $cmm->addParam('pfinyear', $this->finyear);
        $cmm->addParam('pcompany_id', $company_id);
        $dtfinyear = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dtfinyear->Rows())!=1) {
            return ['status' => 'Fail', 'errMsg' => 'Selected Financial Year not found in collection.'];
        }
        // Validate if already created
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select count(*) as fmcount From sys.fiscal_month Where finyear=:pfinyear;');
        $cmm->addParam('pfinyear', $this->finyear);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(intval($dt->Rows()[0]['fmcount'])>0) {
            return ['status' => 'Fail', 'errMsg' => 'Fiscal Months already exist for selected Financial Year ('.$this->finyear.')'];
        }
                
        
        // Proceeed to create        
        $date_from = new \DateTime($dtfinyear->Rows()[0]['year_begin']);
        $date_to = new \DateTime();
        $date_to->setDate(intval($date_from->format('Y')), intval($date_from->format('m')) + 1, 1);
        $date_to->sub(new \DateInterval('P1D'));
        $sql = 'Insert Into sys.fiscal_month(fiscal_month_id, company_id, finyear, fiscal_month_desc, month_begin, month_end, month_close, last_updated)
                Values(:pid, :pcompany_id, :pfinyear, :pfiscal_month_desc, :pmonth_begin, :pmonth_end, :pmonth_close, current_timestamp(0))';
        $cn = \app\cwf\vsla\data\DataConnect::getCn(\app\cwf\vsla\data\DataConnect::COMPANY_DB);
        try {            
            $cn->beginTransaction();
            $cmmFiscal = $cn->prepare($sql);
            while (TRUE) {
                $id = \app\cwf\vsla\entity\EntityManager::getMastSeqID($company_id, 'sys.fiscal_month', $cn);
                $cmmFiscal->execute([
                    'pid' => $id,
                    'pcompany_id' => $company_id,
                    'pfinyear' => $this->finyear,
                    'pfiscal_month_desc' => $date_from->format('M Y'),
                    'pmonth_begin' => $date_from->format('Y-m-d'),
                    'pmonth_end' => $date_to->format('Y-m-d'),
                    'pmonth_close' => 0
                ]);
                $date_from = new \DateTime($date_to->format('Y-m-d'));
                $date_from->add(new \DateInterval('P1D'));
                $date_to = new \DateTime();
                $date_to->setDate(intval($date_from->format('Y')), intval($date_from->format('m')) + 1, 1);
                $date_to->sub(new \DateInterval('P1D'));
                if($date_from->getTimestamp() > strtotime($dtfinyear->Rows()[0]['year_end'])) {
                    break;
                }
            }
            $cn->commit();
        } catch (\Exception $ex) {
            if($cn!=null & $cn->inTransaction()) {
                $cn->rollBack();
            }
            return ['status' => 'Fail', 'errMsg' => $ex->getMessage()];
        }
        return ['status' => 'OK'];
    }   
    
}


<?php

namespace app\core\tx\gstr1Detail;
use \YaLinqo\Enumerable;
/**
 * This is the data model for Gstr1Detail.
 * This class calculates the balance sheet items and schedules
 * for twig presentation
 * 
 * @author girish
 */
class Gstr1Detail {
    
    public function get_data(int $gst_ret_id, int $detail_type) {
        $dataParams = \app\core\tx\gstr1Detail\Gstr1Detail::getRetOption($gst_ret_id);
        $gstr_data = \app\core\tx\gstr1\Gstr1Worker::getRawDetailData($dataParams, $detail_type);
        
        // b2b data
        if($detail_type == 4) {
            $this->setBranch($gstr_data['b2b']);
            $dt = $gstr_data['b2b'];
            usort($dt->Rows(), array('app\core\tx\gstr1Detail\Gstr1Detail', "sortByDate"));
        } elseif ($detail_type == 7) {
            // b2cs data
            $this->setBranch($gstr_data['b2cs']);
            $dt = $gstr_data['b2cs'];
            usort($dt->Rows(), array('app\core\tx\gstr1Detail\Gstr1Detail', "sortByDate"));
        } elseif ($detail_type == 8) {
            // exemp data
            $this->setBranch($gstr_data['exemp']);
            $dt = $gstr_data['exemp'];
            usort($dt->Rows(), array('app\core\tx\gstr1Detail\Gstr1Detail', "sortByDate"));
        }
        return $gstr_data;
    }
    
    static function sortByDate($a, $b) {
        return strtotime($a['doc_date']) > strtotime($b['doc_date'])  ? 1 : -1;
    }
    
    private function setBranch(\app\cwf\vsla\data\DataTable $sourceData) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select branch_id, branch_code, branch_name From sys.branch");
        $dtBr = \app\cwf\vsla\data\DataConnect::getData($cmm);
        foreach($dtBr->Rows() as $drBr) {
            $regp = '/'.$drBr['branch_code'].'[0-9]{5}/';
            foreach($sourceData->Rows() as &$sdr) {
                if(preg_match($regp, $sdr['voucher_id'])) {
                    $sdr['branch_id'] = $drBr['branch_id'];
                    $sdr['branch_name'] = $drBr['branch_name'];
                }
            }
        }
    }
    
    private static function getRetOption(int $gst_ret_id) {
        $option = new \app\core\tx\gstr1\Gstr1ProviderOption();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select gst_state_id, ret_period_from, ret_period_to From tx.gst_ret Where gst_ret_id = :pgr_id");
        $cmm->addParam("pgr_id", $gst_ret_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if(count($dt->Rows())==1) {
            $option->gst_state_id = $dt->Rows()[0]['gst_state_id'];
            $option->ret_period_from = $dt->Rows()[0]['ret_period_from'];
            $option->ret_period_to = $dt->Rows()[0]['ret_period_to'];
        }
        return $option;
    }
    
    
}

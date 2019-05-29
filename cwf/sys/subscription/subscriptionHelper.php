<?php

namespace app\cwf\sys\subscription;

use app\cwf\vsla\security\SessionManager;
use app\cwf\vsla\utils\FormatHelper;

/**
 * Description of subscriptionHelper
 *
 * @author dev
 */
class subscriptionHelper {

    public static $quartzHost = 'http://localhost:8080';

    public static function getSubscription($reportpath) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('Select * from sys.subscription where repor_path=:preport_path and user_id=:puser_id');
        $cmm->addParam('preport_path', $reportpath);
        $cmm->addParam('puser_id', SessionManager::getInstance()->getUserInfo()->getUser_ID());
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm, \app\cwf\vsla\data\DataConnect::MAIN_DB);
        if (count($dt->Rows()) == 1) {
            $subscription_id = (int) $dt->Rows()[0]['subscription_id'];
            return new modelSubscription($subscription_id);
        }
        return NULL;
    }

    public static function createSubscription($data) {
        if (count($data) > 0) {
            $subscr = new modelSubscription(NULL);
            if (array_key_exists('xmlPath', $data) && ($data['xmlPath'] != NULL || $data['xmlPath'] != '')) {
                $subscr->report_path = $data['xmlPath'];
            }
            if (array_key_exists('user_id', $data) && ($data['user_id'] == NULL || $data['user_id'] == -1)) {
                $subscr->user_id = SessionManager::getInstance()->getUserInfo()->getUser_ID();
            } else {
                $subscr->user_id = (int) $data['user_id'];
            }
            if (array_key_exists('rpt_name', $data) && ($data['rpt_name'] != NULL || $data['rpt_name'] != '')) {
                $subscr->report_name = $data['rpt_name'];
            }
            foreach ($data as $key => $value) {
                if (strpos($key, 'subscr_') === 0) {
                    $prop = str_replace('subscr_', '', $key);
                    $subscr->report_options[$prop] = $value;
                }
                if (strpos($key, 'sch_') === 0) {
                    $prop = str_replace('sch_', '', $key);
                    $subscr->schedule_info[$prop] = $value;
                }
            }
            $subscr->schedule_info['cron_expression'] = self::createSchedule((object) $subscr->schedule_info);
            return $subscr;
        } else {
            return NULL;
        }
    }

    public static function resolveValue($val) {
        switch ($val) {
            case 'year_begin':
                return FormatHelper::FormatDateForDisplay(SessionManager::getSessionVariable('year_begin'));
            case 'year_end':
                return FormatHelper::FormatDateForDisplay(SessionManager::getSessionVariable('year_end'));
            case 'current_date':
                return FormatHelper::FormatDateForDisplay((new \DateTime())->format('Y-m-d'));
            case 'last_lapsed_month_begin':
                return FormatHelper::FormatDateForDisplay((new \DateTime('first day of last month'))->format('Y-m-d'));
            case 'last_lapsed_month_end':
                return FormatHelper::FormatDateForDisplay((new \DateTime('last day of last month'))->format('Y-m-d'));
            case 'current_month_begin':
                return FormatHelper::FormatDateForDisplay((new \DateTime('first day of this month'))->format('Y-m-d'));
            case 'current_month_end':
                return FormatHelper::FormatDateForDisplay((new \DateTime('last day of this month'))->format('Y-m-d'));
            case 'current_finyear':
                return SessionManager::getSessionVariable('fin_year');
            default:
                return $val;
        }
    }

    public static function createSchedule($data) {
        $cron = '';
        $simple = '';
        $min = 0;
        $hr = 0;
        $weekly_on = NULL;
        $monthly_on = NULL;
        //$only_on = NULL; *to be implemented
        if ($data->hr != NULL) {
            $hr = (int) $data->hr;
        } else {
            $data->hr = 0;
        }
        if ($data->repeatn == 'daily') {
            $cron = '0 ' . $min . ' ' . $hr . ' 1/1 * ? *';
        } else if ($data->repeatn == 'weekly') {
            if ($data->wday != NULL) {
                $weekly_on = $data->wday;
            }
            $cron = '0 ' . $min . ' ' . $hr . ' ? * ' . $weekly_on . ' *';
        } else if ($data->repeatn == 'monthly') {
            if ($data->monthly_on != NULL) {
                switch ($data->monthly_on) {
                    case 'first':
                        $monthly_on = '1';
                        break;
                    case 'last':
                        $monthly_on = 'L';
                        break;
                    default:
                        $monthly_on = $data->day;
                        break;
                }
            }
            $cron = '0 ' . $min . ' ' . $hr . ' ' . $monthly_on . ' * ?';
        }
        return $cron;
    }

    public static function addJob(modelSubscription $subscr) {
        $cronex = $subscr->schedule_info['cron_expression'];
        $subscrid = $subscr->subscription_id;
        $strjob = '<quartz>
                        <addJob>
                                <job>
                                    <company_id>' . SessionManager::getSessionVariable('company_id') . '</company_id>
                                     <subs_id>' . $subscrid . '</subs_id>
                                </job>
                                <trigger>
                                        <name>' . $subscrid . '</name>
                                        <startTime>0</startTime>
                                        <cron>
                                              <expression>' . $cronex . '</expression>  
                                        </cron>
                                </trigger>
                        </addJob>
                </quartz>';
        $xquartz = new \SimpleXMLElement($strjob);
        $content = (string) $xquartz->asXML();
        $client = new \GuzzleHttp\Client();

        try {
            $resp = $client->post(self::$quartzHost . '/CoreQuartzServer/QuartzManager?reqtime=' . time(), ['body' => $content]);
            $result = $resp->getBody();
            return ['status' => 'OK', 'msg' => '', 'result' => $result];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $msg = $e->getMessage();
            if ($e->hasResponse()) {
                $msg .= "\n" . $e->getResponse()->getBody();
            }
            throw new \Exception($msg);
        } catch (\Exception $ex) {
            $msg = $ex->getMessage();
            throw new \Exception($msg);
        }
    }

}

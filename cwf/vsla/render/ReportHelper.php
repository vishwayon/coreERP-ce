<?php

namespace app\cwf\vsla\render;

use app\cwf\vsla\design;

class ReportHelper {

    public static function output_subscription_schedule() {
        $sch = '</div><div id="schOptions" class="row" style="display:none;margin-top:10px;">
	<strong>Schedule</strong>
        <div class="row">
            <div class="form-group col-md-4">
		<span>Time</span>
		<input id="hr" name="sch_hr" data-bind="value:sch.hr" style="width:50px;margin-right:5px;"><span>Hr</span>
            </div>
	</div>
	<div class="row">
                <div class="row" style="margin-bottom:7px;">
                    <input data-bind="checked:sch.repeatn" name="sch_repeatn" value="daily" type="radio">
                    <span>Daily</span>
                </div>
                <div class="row" style="margin-bottom:3px;">
                    <input data-bind="checked:sch.repeatn" name="sch_repeatn" value="weekly" type="radio">
                    <span>Weekly</span>
                            <input name="sch_wday" type="checkbox" data-bind="checked:sch.mon" value="Mon" style="margin-left:20px;"><label style="margin-left:5px;">Mon</label>
                            <input name="sch_wday" type="checkbox" data-bind="checked:sch.tue" value="Tue" style="margin-left:20px;"><label style="margin-left:5px;">Tue</label>
                            <input name="sch_wday" type="checkbox" data-bind="checked:sch.wed" value="Wed" style="margin-left:20px;"><label style="margin-left:5px;">Wed</label>
                            <input name="sch_wday" type="checkbox" data-bind="checked:sch.thu" value="Thu" style="margin-left:20px;"><label style="margin-left:5px;">Thu</label>
                            <input name="sch_wday" type="checkbox" data-bind="checked:sch.fri" value="Fri" style="margin-left:20px;"><label style="margin-left:5px;">Fri</label>
                            <input name="sch_wday" type="checkbox" data-bind="checked:sch.sat" value="Sat" style="margin-left:20px;"><label style="margin-left:5px;">Sat</label>
                            <input name="sch_wday" type="checkbox" data-bind="checked:sch.sun" value="Sun" style="margin-left:20px;"><label style="margin-left:5px;">Sun</label>	            
                </div>
                <div class="row" style="margin-bottom:3px;">
                    <input data-bind="checked:sch.repeatn" name="sch_repeatn" value="monthly" type="radio">
                    <span>Monthly</span>
                        <select id="sch_monthly_on" name="sch_monthly_on" class="simplecombo " style="width:90px;display:table-cell;margin:0 20px;" 
                                tabindex="0" onchange="cwf_jrpt.showSchday()">		                      
                            <option value="first">First</option>                       
                            <option value="last">Last</option>
                            <option value="specific">Specific</option>
                        </select>
                    <span id="labelday" style="display:none;">day</span>
                        <input id="day" name="sch_day" data-bind="value:sch.day,visible:($(\'#sch_monthly_on\').val()==\'specific\')" style="width:50px;display:none;">         
                </div>
                <div class="row" style="display:none;">
                    <input data-bind="checked:sch.repeatn" name="sch_repeatn" value="once" type="radio">
                        <span>Only on</span>
                        <input id="onlyon_date" class=" datetime" style="margin-left:20px;" name="sch_onlyon_date" tabindex="0" data-bind="dateValue: sch.onlyon" type="DateTime">
                </div>
            </div>    
        </div></div>';
        return $sch;
    }

    public static function output_dateoptions() {
        $options = new \app\cwf\vsla\design\FieldOptionType();
        $options->defaultValue = 'current_date';
        $options->choices[-1] = 'Select an option';
        $options->choices['year_begin'] = 'Year Begin';
        $options->choices['year_end'] = 'Year End';
        $options->choices['current_date'] = 'Current Date';
        $options->choices['last_lapsed_month_begin'] = 'Last lapsed month begin';
        $options->choices['last_lapsed_month_end'] = 'Last lapsed month end';
        $options->choices['current_month_begin'] = 'Current month Begin';
        $options->choices['current_month_end'] = 'Current month end';
        $options->choices['specific'] = 'Select date';
        return $options;
    }

    public static function output_paramvalue(\app\cwf\vsla\design\FormField $field) {
        $returnValue = NULL;
        $param = $field->value;
        switch ($param->getType()) {
            case design\IReportParamItem::TYPE_CURRENT_DATE :
                if ($field->range == 'finYear') {
                    $yearEnd = strtotime(\app\cwf\vsla\security\SessionManager::getSessionVariable(design\BaseParamSession::SESSION_YEAR_END));
                    if (time() > $yearEnd) {
                        $returnValue = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(date("Y-m-d", $yearEnd));
                    } else {
                        $returnValue = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(date("Y-m-d", time()));
                    }
                } else {
                    $returnValue = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(date("Y-m-d", time()));
                }
                break;
            case design\IReportParamItem::TYPE_SESSION :
                $returnValue = self::output_paramvalue_session($param);
                break;
            case design\IReportParamItem::TYPE_PRESET :
                $returnValue = self::output_PresetValues($param);
                break;
            case design\IReportParamItem::TYPE_TEXT :
                $returnValue = $param->text;
                break;
            case design\IReportParamItem::TYPE_DATE_FORMAT :
                $returnValue = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForReport();
                break;
            case design\IReportParamItem::TYPE_NUMBER_FORMAT :
                $returnValue = \app\cwf\vsla\utils\FormatHelper::GetNumberFormat();
                break;
        }
        return $returnValue;
    }

    public static function output_paramvalue_param(design\IReportParamItem $param) {
        $returnValue = NULL;
        switch ($param->getType()) {
            case design\IReportParamItem::TYPE_CURRENT_DATE :
                $returnValue = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(date("Y-m-d", time()));
                break;
            case design\IReportParamItem::TYPE_SESSION :
                $returnValue = self::output_paramvalue_session($param);
                break;
            case design\IReportParamItem::TYPE_PRESET :
                $returnValue = self::output_PresetValues($param);
                break;
            case design\IReportParamItem::TYPE_TEXT :
                $returnValue = $param->text;
                break;
            case design\IReportParamItem::TYPE_DATE_FORMAT :
                $returnValue = \app\cwf\vsla\utils\FormatHelper::GetDateFormatForReport();
                break;
            case design\IReportParamItem::TYPE_NUMBER_FORMAT :
                $returnValue = \app\cwf\vsla\utils\FormatHelper::GetNumberFormat();
                break;
        }
        return $returnValue;
    }

    private static function output_paramvalue_session(design\BaseParamSession $param) {
        $returnValue = NULL;
        switch ($param->sessionType) {
            case design\BaseParamSession::SESSION_YEAR_BEGIN :
                $returnValue = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(
                                \app\cwf\vsla\security\SessionManager::getSessionVariable($param->sessionType));
                break;
            case design\BaseParamSession::SESSION_YEAR_END :
                $returnValue = \app\cwf\vsla\utils\FormatHelper::FormatDateForDisplay(
                                \app\cwf\vsla\security\SessionManager::getSessionVariable($param->sessionType));
                break;
            default :
                $returnValue = \app\cwf\vsla\security\SessionManager::getSessionVariable($param->sessionType);
                break;
        }
        return $returnValue;
    }

    private static function output_PresetValues(design\ReportParamPreset $param) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('SELECT * FROM sys.fn_report_defaults(:pbranch_id, :pcompany_id)');
        $cmm->addParam('pbranch_id', -1);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $dtPreset = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $returnVal = NULL;

        if (count($dtPreset->Rows()) > 0) {
            switch ($param->id) {
                case 'pcompany_logo_physical' :
                    $returnVal = \yii::$app->basePath . $dtPreset->Rows()[0]['company_logo'];
                    break;
                case 'pcompany_logo_url' :
                    $returnVal = ".." . $dtPreset->Rows()[0]['company_logo'];
                    break;
                default :
                    $returnVal = $dtPreset->Rows()[0][substr($param->id, 1)];
                    break;
            }
        }
        return $returnVal;
    }

}

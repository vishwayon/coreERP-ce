<?php

namespace app\core\ac\dashboard\custMon;

class CustMonController extends \app\cwf\vsla\base\WebController {

    function init() {
        parent::init();
        $twigOptions = &\yii::$app->view->renderers['twig'];
        // Register yii classes that you plan to use in twig
        $twigOptions['globals'] = [
            'ScriptHelper' => ['class' => \app\cwf\vsla\utils\ScriptHelper::class]
        ];
    }

    public function actionOverview() {
        $overview = $this->getModulePath() . '/dashboard/custMon/Overview.twig';
        return $this->renderPartial($overview);
    }

    public function actionTovData() {
        // Fetch turnover for p/y, c/y and last 6 months
        $result = [];
        // Previous year data
        $di = new \DateTime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
        $py_begin = date_sub(new \DateTime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin')), new \DateInterval('P1Y'));
        $py_end = date_sub(new \DateTime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin')), new \DateInterval('P1D'));
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        // Sql to select values from dw
        //$sql = "Select coalesce(Sum((b.value->>'tov_amt')::Numeric(18,4)), 0) as tov_amt
        //        From dw.company_stat a, jsonb_array_elements(stat->'tov_stat') b
        //        Where a.company_stat_key='tov_stat'
        //           And (b.value->>'start_period')::Date Between :pfrom_date And :pto_date";         
        $sql = "select coalesce(sum(bt_amt), 0) as tov_amt from ar.fn_business_turnover(:pcompany_id, :pbranch_id, 0, :pcustomer_id, :pfrom_date, :pto_date)";
        $cmm->setCommandText($sql);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0);
        $cmm->addParam('pcustomer_id', 0);
        $cmm->addParam('pfrom_date', $py_begin->format('Y-m-d'));
        $cmm->addParam('pto_date', $py_end->format('Y-m-d'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) == 1) {
            $result['py_tov'] = $dt->Rows()[0]['tov_amt'];
        } else {
            $result['py_tov'] = 0;
        }

        // Current Year Data
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        // Sql to select values from dw
        //$sql = "Select coalesce(Sum((b.value->>'tov_amt')::Numeric(18,4)), 0) as tov_amt
        //        From dw.company_stat a, jsonb_array_elements(stat->'tov_stat') b
        //        Where a.company_stat_key='tov_stat'
        //            And (b.value->>'start_period')::Date Between :pfrom_date And :pto_date";

        $sql = "select coalesce(sum(bt_amt), 0) as tov_amt from ar.fn_business_turnover(:pcompany_id, :pbranch_id, 0, :pcustomer_id, :pfrom_date, :pto_date)";
        $cmm->setCommandText($sql);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0);
        $cmm->addParam('pcustomer_id', 0);
        $cmm->addParam('pfrom_date', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
        $cmm->addParam('pto_date', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_end'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) == 1) {
            $result['cy_tov'] = $dt->Rows()[0]['tov_amt'];
        } else {
            $result['cy_tov'] = 0;
        }

        // Current Month Data
        $today = new \DateTime();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("select coalesce(sum(bt_amt), 0) as tov_amt from ar.fn_business_turnover(:pcompany_id, :pbranch_id, 0, :pcustomer_id, :pfrom_date, :pto_date)");
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0);
        $cmm->addParam('pcustomer_id', 0);
        $cmm->addParam('pfrom_date', $today->format('Y-m-01'));
        $cmm->addParam('pto_date', $today->format('Y-m-d'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) == 1) {
            $result['cm_tov'] = $dt->Rows()[0]['tov_amt'];
        } else {
            $result['cm_tov'] = 0;
        }

        // Monthly data for last 6 completed months        
        $end_date = date_sub(new \DateTime($today->format('Y-m-01')), new \DateInterval('P1D'));
        $start_date = date_sub(new \DateTime($end_date->format('Y-m-d')), new \DateInterval('P6M'));
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        // Sql to select data from dw
        //$sql = "Select date_part('month', (b.value->>'start_period')::Date) as month_id, 
        //            to_char((b.value->>'start_period')::Date, 'Mon-YY') as month_name, 
        //            coalesce(Sum((b.value->>'tov_amt')::Numeric(18,4)), 0) as tov_amt
        //        From dw.company_stat a, jsonb_array_elements(stat->'tov_stat') b
        //        Where a.company_stat_key='tov_stat'
        //            And (b.value->>'start_period')::Date Between :pfrom_date And :pto_date
        //        Group By date_part('month', (b.value->>'start_period')::Date), to_char((b.value->>'start_period')::Date, 'Mon-YY')
        //        Order By date_part('month', (b.value->>'start_period')::Date)";
        $sql = "Select to_char(doc_date, 'Mon-YY') as month_name, Sum(bt_amt) as tov_amt 
                From ar.fn_business_turnover(:pcompany_id, :pbranch_id, 0, :pcustomer_id, :pfrom_date, :pto_date)
                Group by to_char(doc_date, 'Mon-YY'), cast(date_trunc('month', doc_date) as date)
                Order by cast(date_trunc('month', doc_date) as date)";
        $cmm->setCommandText($sql);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0);
        $cmm->addParam('pcustomer_id', 0);
        $cmm->addParam('pfrom_date', $start_date->format('Y-m-01'));
        $cmm->addParam('pto_date', $end_date->format('Y-m-d'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $result['tov_by_month'] = $dt->Rows();
        return json_encode($result);
    }

    public function actionRecOsData() {
        // Fetches the Receivable Outstandings
        // sum(not_due)
        $today = new \DateTime();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = 'With ar
                as 
                (	select 	branch_id,
                                Sum(not_due) as not_due,
                                --65000 as not_due,
                                Sum(overdue) as overdue
                        from ar.fn_customer_overdue(:pcompany_id, :pbranch_id, :pcustomer_id, :pto_date, \'\', \'D\')
                        Group by branch_id
                ) 
                Select a.branch_id, b.branch_code, b.branch_name,
                        a.not_due, a.overdue
                From ar a
                Inner Join sys.branch b On a.branch_id=b.branch_id
                Order by b.branch_code;';
        $cmm->setCommandText($sql);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0);
        $cmm->addParam('pcustomer_id', 0);
        $cmm->addParam('pto_date', $today->format('Y-m-d'));

        $result = [];
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        // calculate totals
        $not_due_tot = 0.0;
        $overdue_tot = 0.0;
        foreach ($dt->Rows() as $dr) {
            $not_due_tot += $dr['not_due'];
            $overdue_tot += $dr['overdue'];
        }
        $result['os_tot'] = $not_due_tot + $overdue_tot;
        $result['not_due_tot'] = $not_due_tot;
        $result['overdue_tot'] = $overdue_tot;
        $result['by_branch'] = $dt->Rows();
        return json_encode($result);
    }

    public function actionTopCustomer($sort = 'tov', $count = 20) {
        // Top customer data
        $result = [];
        $to_date = new \DateTime("now");
        $from_date = date_sub(new \DateTime($to_date->format('Y-m-d')), new \DateInterval('P1Y'));
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        if ($sort == 'os') {
            // Total Outstanding
            $sql = 'Select account_id as customer_id, account_head as customer, Sum(overdue + not_due) as gross_amt 
                    From ar.fn_customer_overdue(:pcompany_id, :pbranch_id, :pcustomer_id, :pto_date, \'\', \'D\')
                    Group by account_id, account_head
                    Order By Sum(overdue + not_due) desc';
            $cmm->setCommandText($sql);
            $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
            $cmm->addParam('pbranch_id', 0);
            $cmm->addParam('pcustomer_id', 0);
            $cmm->addParam('pto_date', $to_date->format('Y-m-d'));
        } elseif ($sort == 'overdue') {
            // Total Overdue
            $sql = 'Select account_id as customer_id, account_head as customer, Sum(overdue) as gross_amt 
                    From ar.fn_customer_overdue(:pcompany_id, :pbranch_id, :pcustomer_id, :pto_date, \'\', \'D\')
                    Group by account_id, account_head
                    Order By Sum(overdue) desc';
            $cmm->setCommandText($sql);
            $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
            $cmm->addParam('pbranch_id', 0);
            $cmm->addParam('pcustomer_id', 0);
            $cmm->addParam('pto_date', $to_date->format('Y-m-d'));
        } else {
            // Total Turnover
            $sql = 'Select customer_id, customer, Sum(invoice_amt) as gross_amt 
                    From ar.fn_business_turnover(:pcompany_id, :pbranch_id, 0, :pcustomer_id, :pfrom_date, :pto_date)
                    Group by customer_id, customer
                    Order By Sum(invoice_amt) desc';
            $cmm->setCommandText($sql);
            $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
            $cmm->addParam('pbranch_id', 0);
            $cmm->addParam('pcustomer_id', 0);
            $cmm->addParam('pfrom_date', $from_date->format('Y-m-d'));
            $cmm->addParam('pto_date', $to_date->format('Y-m-d'));
        }
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $raw_data = $dt->Rows();
        // loop and set the ranks and %
        $tot_value = 0.0;
        foreach ($raw_data as $dr) {
            $tot_value += $dr['gross_amt'];
        }
        $rank = 1;
        $cum_part = 0;
        foreach ($raw_data as &$drow) {
            $drow['rank'] = $rank;
            if ($tot_value > 0) {
                $drow['pcnt_of_tot'] = round($drow['gross_amt'] / $tot_value * 100, 2);
            } else {
                $drow['pcnt_of_tot'] = round($drow['gross_amt'], 2);
            }
            $cum_part += $drow['pcnt_of_tot'];
            $drow['cum_part'] = $cum_part;
            $rank += 1;
        }
        $result['cust_data'] = $raw_data;
        return json_encode($result);
    }

    public function actionRecKpi() {
        // This would return the following kpi(s)
        // 1. Average Collection Period - avr_coll_period
        // 2. Debtor Turnover Ratio - rec_tov_ratio
        // 3. Overdues > 180 days - bad_os
        // 4. Todays Turnover

        $result = [];

        $today = new \DateTime();
        $end_date = date_sub(new \DateTime($today->format('Y-m-01')), new \DateInterval('P1D'));
        $start_date = date_sub(new \DateTime($end_date->format('Y-m-d')), new \DateInterval('P1Y'));
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = "With ar_stmt
                As
                (   Select debit_amt,
                        (debit_amt - credit_amt) * (:pto_date::date - (Case category When 'A' Then doc_date Else bill_date End)) as day_amt
                    From ar.fn_stmt_of_ac_br_report_detailed(:pcompany_id, 0, 0, :pto_date, 0::smallint)
                    Order by doc_date
                )
                Select Case When Sum(debit_amt) > 0 Then Sum(day_amt)/Sum(debit_amt) Else 0.00 End as avg_days
                From ar_stmt";
        $cmm->setCommandText($sql);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pto_date', $end_date->format('Y-m-d'));
        $dt_avg_days = \app\cwf\vsla\data\DataConnect::getData($cmm);

        if (count($dt_avg_days->Rows()) == 1) {
            $result['avr_coll_period'] = round($dt_avg_days->Rows()[0]['avg_days'], 1);
            if ($result['avr_coll_period'] == 0) {
                $result['rec_tov_ratio'] = 'Not Available';
            } else {
                $result['rec_tov_ratio'] = round(365 / $result['avr_coll_period'], 1);
            }
        } else {
            $result['rec_tov_ratio'] = 'Not Available';
            $result['avr_coll_period'] = 'Not Available';
        }

        // Get receivables ageing > 180 days
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = 'Select Coalesce(Sum(overdue), 0.0) as gross_amt 
                    From ar.fn_customer_overdue(:pcompany_id, :pbranch_id, :pcustomer_id, :pto_date, \'\', \'D\')
                    Where overdue_days >= 180';
        $cmm->setCommandText($sql);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0);
        $cmm->addParam('pcustomer_id', 0);
        $cmm->addParam('pto_date', $end_date->format('Y-m-d'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) == 1) {
            $result['bad_os'] = (float) $dt->Rows()[0]['gross_amt'];
        } else {
            $result['bad_os'] = 0.0;
        }

        // Todays Turnover
        $cmm_tov = new \app\cwf\vsla\data\SqlCommand();
        $cmm_tov->setCommandText("Select Coalesce(Sum(bt_amt), 0) as tov_amt 
                From ar.fn_business_turnover(:pcompany_id, :pbranch_id, 0, :pcustomer_id, :pfrom_date, :pto_date)");
        $cmm_tov->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm_tov->addParam('pbranch_id', 0);
        $cmm_tov->addParam('pcustomer_id', 0);
        $cmm_tov->addParam('pfrom_date', $today->format('Y-m-d'));
        $cmm_tov->addParam('pto_date', $today->format('Y-m-d'));
        $dt_tov = \app\cwf\vsla\data\DataConnect::getData($cmm_tov);
        if (count($dt_tov->Rows()) == 1) {
            $result['tov_amt'] = (float) $dt_tov->Rows()[0]['tov_amt'];
        } else {
            $result['tov_amt'] = 0.0;
        }

        return json_encode($result);
    }

    public function actionCustDetailView() {
        $custView = $this->getModulePath() . '/dashboard/custMon/CustDetail.twig';
        return $this->renderPartial($custView);
    }

    public function actionCustDetail($cust_id) {
        // gets the customer data details
        $result = [];

        // fetch Business (current, previous, previous year)
        // Fetch Current Period Tov
        $today = new \DateTime();
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select Coalesce(Sum(invoice_amt), 0) as tov_amt 
                From ar.fn_business_turnover(:pcompany_id, :pbranch_id, 0, :pcustomer_id, :pfrom_date, :pto_date)");
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0);
        $cmm->addParam('pcustomer_id', $cust_id);
        $cmm->addParam('pfrom_date', \app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin'));
        $cmm->addParam('pto_date', $today->format('Y-m-d'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $result['cust_det_tov_cy'] = (float) $dt->Rows()[0]['tov_amt'];

        // Fetch Previous period TOV
        $py_todate = date_sub(new \DateTime($today->format('Y-m-d')), new \DateInterval('P1Y'));
        $py_fromdate = date_sub(new \DateTime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin')), new \DateInterval('P1Y'));
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select Coalesce(Sum(invoice_amt), 0) as tov_amt 
                From ar.fn_business_turnover(:pcompany_id, :pbranch_id, 0, :pcustomer_id, :pfrom_date, :pto_date)");
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0);
        $cmm->addParam('pcustomer_id', $cust_id);
        $cmm->addParam('pfrom_date', $py_fromdate->format('Y-m-d'));
        $cmm->addParam('pto_date', $py_todate->format('Y-m-d'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $result['cust_det_tov_pp'] = (float) $dt->Rows()[0]['tov_amt'];

        // Fetch Previous Year TOV
        $py_begin = date_sub(new \DateTime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin')), new \DateInterval('P1Y'));
        $py_end = date_sub(new \DateTime(\app\cwf\vsla\security\SessionManager::getSessionVariable('year_begin')), new \DateInterval('P1D'));
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select Coalesce(Sum(invoice_amt), 0) as tov_amt 
                From ar.fn_business_turnover(:pcompany_id, :pbranch_id, 0, :pcustomer_id, :pfrom_date, :pto_date)");
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0);
        $cmm->addParam('pcustomer_id', $cust_id);
        $cmm->addParam('pfrom_date', $py_begin->format('Y-m-d'));
        $cmm->addParam('pto_date', $py_end->format('Y-m-d'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $result['cust_det_tov_py'] = (float) $dt->Rows()[0]['tov_amt'];

        // Fetch Outstanding Information
        // Current Outstanding & overdue
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = 'Select Coalesce(Sum(overdue + not_due), 0) as gross_amt, Coalesce(Sum(overdue), 0) as overdue
                    From ar.fn_customer_overdue(:pcompany_id, :pbranch_id, :pcustomer_id, :pto_date, \'\', \'D\')';
        $cmm->setCommandText($sql);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0);
        $cmm->addParam('pcustomer_id', $cust_id);
        $cmm->addParam('pto_date', $today->format('Y-m-d'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $result['cust_det_os'] = (float) $dt->Rows()[0]['gross_amt'];
        $result['cust_det_overdue'] = (float) $dt->Rows()[0]['overdue'];

        // Avg. Collection Period
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("With ar_stmt
                As
                (   Select debit_amt,
                        (debit_amt - credit_amt) * (:pto_date::date - (Case category When 'A' Then doc_date Else bill_date End)) as day_amt
                    From ar.fn_stmt_of_ac_br_report_detailed(:pcompany_id, :pbranch_id, :pcustomer_id, :pto_date, 0::smallint)
                    Order by doc_date
                )
                Select Case When Sum(debit_amt) > 0 Then Sum(day_amt)/Sum(debit_amt) Else 0.00 End as avg_days
                From ar_stmt");
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0);
        $cmm->addParam('pcustomer_id', $cust_id);
        $cmm->addParam('pto_date', $today->format('Y-m-d'));
        $dt_avg_days = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt_avg_days->Rows()) == 1) {
            $result['cust_det_avg_coll'] = round($dt_avg_days->Rows()[0]['avg_days'], 2);
        } else {
            $result['cust_det_avg_coll'] = 0;
        }

        // CreditLimit, PayTerms and Room Available
        // Fetch customer name and credit limit
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select customer, credit_limit, credit_limit_type from ar.customer where customer_id = :pcustomer_id');
        $cmm->addParam('pcustomer_id', $cust_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) == 1) {
            $result['cust_det_credit_limit'] = $dt->Rows()[0]['credit_limit'];
        }

        // Fetch Pay Term
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText('select pay_term from ac.pay_term
                                where pay_term_id = (select pay_term_id from ar.customer where customer_id = :pcustomer_id)');
        $cmm->addParam('pcustomer_id', $cust_id);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        if (count($dt->Rows()) == 1) {
            $result['cust_det_pay_term'] = $dt->Rows()[0]['pay_term'];
        }

        //Get Customer os
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $sql = 'Select a.doc_date, a.voucher_id, a.due_date, a.not_due, a.overdue, a.overdue_days, b.branch_name 
                    From ar.fn_customer_overdue(:pcompany_id, :pbranch_id, :pcustomer_id, :pto_date, \'\', \'D\') a
                    Inner Join sys.branch b On a.branch_id=b.branch_id
                    Order by doc_date';
        $cmm->setCommandText($sql);
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0);
        $cmm->addParam('pcustomer_id', $cust_id);
        $cmm->addParam('pto_date', $today->format('Y-m-d'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result['os_data'] = $dt->Rows();

        // Get invoice info of cy
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("Select category, rl_pl_id, doc_date, case when settle_id = '' then voucher_id else settle_id end as voucher_id, bill_date, debit_amt, credit_amt 
                FROM ar.fn_stmt_of_ac_br_report_Detailed(:pcompany_id, :pbranch_id, :pcustomer_id, :pto_date, 0::smallint)
                Order By doc_date desc, rl_pl_id, category");
        $cmm->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $cmm->addParam('pbranch_id', 0);
        $cmm->addParam('pcustomer_id', $cust_id);
        $cmm->addParam('pto_date', $today->format('Y-m-d'));
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);

        $result['inv_collect_data'] = $dt->Rows();

        return json_encode($result);
    }

}

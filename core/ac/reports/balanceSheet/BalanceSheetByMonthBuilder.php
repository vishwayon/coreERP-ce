<?php

namespace app\core\ac\reports\balanceSheet;
use app\core\ac\reports\balanceSheet\BsResult;

/**
 * Builds the Balance Sheet By Month/Quarter/HalfYear
 *
 * @author girishshenoy
 */
class BalanceSheetByMonthBuilder {
    
    /**
     * DataTable containing the result of the function ac.fn_bs_by_month
     * @var \app\cwf\vsla\data\DataTable  
     */
    private $raw_data;
    
    /**
     * DataTable containing the account heads
     * @var \app\cwf\vsla\data\DataTable
     */
    private $acc_heads;
    
    private $company_id = 0;
    private $branch_id=0;
    private $finyear='';
    private $fromDate='';
    private $toDate='';
    
    private $bsResult;
    
    public function __construct($company_id, $branch_id, $finYear, $fromDate, $toDate) {
        $this->company_id = $company_id;
        $this->branch_id = $branch_id;
        $this->finyear = $finYear;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }
    
    public function GenerateFinalAccounts() {
        // Step 1: Fetch Account Heads
        $cmmAcHead = new \app\cwf\vsla\data\SqlCommand();
        $cmmAcHead->setCommandText("Select group_id, account_id, account_head
                From ac.account_head
                Where company_id = :pcompany_id And account_type_id not in (0,7,12)
                Order by group_id, account_head");
        $cmmAcHead->addParam('pcompany_id', \app\cwf\vsla\security\SessionManager::getSessionVariable('company_id'));
        $this->acc_heads = \app\cwf\vsla\data\DataConnect::getData($cmmAcHead);
        
        // Step 3: Fetch Balance Sheet
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $query = "Select sl_no, month_name, bs_type, parent_key, group_key, group_name, group_path, account_id, account_head, cl_bal_amt 
                  from ac.fn_bs_by_month(:pcompany_id, :pbranch_id, :pfinyear, :pfrom_date, :pto_date)
                  Order by sl_no, bs_type, group_path, account_head";
        $cmm->setCommandText($query);
        $cmm->addParam('pcompany_id', $this->company_id);
        $cmm->addParam('pbranch_id', $this->branch_id);
        $cmm->addParam('pfinyear', $this->finyear);
        $cmm->addParam('pfrom_date', $this->fromDate);
        $cmm->addParam('pto_date', $this->toDate);
        
        $this->raw_data = \app\cwf\vsla\data\DataConnect::getData($cmm);
        $this->createBSPeriods();
        
        // Create Groups
        $this->createBSGroups();
        $this->createPLGroups();
        $this->createGP_NP();
    }
    
    /**
     * Returns the well-formed BalanceSheet result
     * @return BsResult An instance of BsResult
     */
    public function getBsResult() {
        return $this->bsResult;
    }
    
    private function createBSPeriods() {
        $this->bsResult = new BsResult();
        $raw_periods = $this->raw_data->asArray('sl_no', 'month_name');
        foreach($raw_periods as $k => $v) {
            $bsp = new bsPeriod();
            $bsp->sl_no = $k;
            $bsp->id = $v;
            $this->bsResult->bsPeriods[$bsp->sl_no] = $bsp;
        }
        $raw_amounts = $this->raw_data->asArray('sl_no', ['account_id', 'cl_bal_amt']);
        foreach($raw_amounts as $k => $v) {
            $bsp = $this->bsResult->bsPeriods[$k];
            $bsp->acc_amts = $v;
        }
        $raw_heads = $this->raw_data->asArray('sl_no', ['bs_type', 'parent_key', 'group_key', 'group_name', 'group_path', 'account_id', 'account_head']);
        $this->bsResult->bsHeads = $raw_heads[1];
    }
    
    /**
     * Filters the acc_groups and creates Balance Sheet Group data.
     * Fills bsResult with Balance Sheet Groups only
     */
    private function createBSGroups() {
        $periods = $this->raw_data->asArray('sl_no', 'sl_no');
        // First Create Assets
        $assetGroups = $this->getGroups("A001");
        foreach($assetGroups as &$ag) {
            $ag['accounts'] = $this->acc_heads->findRows('group_id', $ag['group_id'], ['account_id', 'account_head']);
            foreach($ag['accounts'] as &$ag_acc) {
                $acc_bal_amts = $this->raw_data->findRows('account_id', $ag_acc['account_id'], ['sl_no', 'cl_bal_amt']);
                $period_amts = [];
                foreach($periods as $pd) {
                    $period_amts[$pd] = $this->getMatch($acc_bal_amts, $pd);
                }
                $ag_acc['period_amts'] = $period_amts;
            }
            // calculate period totals (vertical totals)
            $period_tots = [];
            foreach($periods as $pd) {
                $period_tot = 0.00;
                foreach($ag['accounts'] as $ag_ac) {
                    $period_tot += $ag_ac['period_amts'][$pd];
                }
                $period_tots[$pd] = $period_tot;
            }
            $ag['period_tots'] = $period_tots;
        }
        $this->bsResult->assetGroups = $assetGroups;
        
        // Create Owner's Fund
        $ownerGroups = $this->getGroups("A002");
        foreach($ownerGroups as &$og) {
            $og['accounts'] = $this->acc_heads->findRows('group_id', $og['group_id'], ['account_id', 'account_head']);
            foreach($og['accounts'] as &$og_acc) {
                $acc_bal_amts = $this->raw_data->findRows('account_id', $og_acc['account_id'], ['sl_no', 'cl_bal_amt']);
                $period_amts = [];
                foreach($periods as $pd) {
                    $period_amts[$pd] = $this->getMatch($acc_bal_amts, $pd);
                }
                $og_acc['period_amts'] = $period_amts;
            }
            // calculate period totals (vertical totals)
            $period_tots = [];
            foreach($periods as $pd) {
                $period_tot = 0.00;
                foreach($og['accounts'] as $ag_ac) {
                    $period_tot += $ag_ac['period_amts'][$pd];
                }
                $period_tots[$pd] = $period_tot;
            }
            $og['period_tots'] = $period_tots;
        }
        $this->bsResult->ownerFundGroups = $ownerGroups;
        
        // Create Liabilities
        $liabGroups = $this->getGroups("A003");
        foreach($liabGroups as &$lg) {
            $lg['accounts'] = $this->acc_heads->findRows('group_id', $lg['group_id'], ['account_id', 'account_head']);
            foreach($lg['accounts'] as &$lg_acc) {
                $acc_bal_amts = $this->raw_data->findRows('account_id', $lg_acc['account_id'], ['sl_no', 'cl_bal_amt']);
                $period_amts = [];
                foreach($periods as $pd) {
                    $period_amts[$pd] = $this->getMatch($acc_bal_amts, $pd);
                }
                $lg_acc['period_amts'] = $period_amts;
            }
            // calculate period totals (vertical totals)
            $period_tots = [];
            foreach($periods as $pd) {
                $period_tot = 0.00;
                foreach($lg['accounts'] as $ag_ac) {
                    $period_tot += $ag_ac['period_amts'][$pd];
                }
                $period_tots[$pd] = $period_tot;
            }
            $lg['period_tots'] = $period_tots;
        }
        $this->bsResult->liabilityGroups = $liabGroups;
    }
    
    private function createPLGroups() {
        $periods = $this->raw_data->asArray('sl_no', 'sl_no');
        // Create Income
        $incomeGroups = $this->getGroups("A004");
        foreach($incomeGroups as &$ig) {
            $ig['accounts'] = $this->acc_heads->findRows('group_id', $ig['group_id'], ['account_id', 'account_head']);
            foreach($ig['accounts'] as &$ig_acc) {
                $acc_bal_amts = $this->raw_data->findRows('account_id', $ig_acc['account_id'], ['sl_no', 'cl_bal_amt']);
                $period_amts = [];
                foreach($periods as $pd) {
                    $period_amts[$pd] = $this->getMatch($acc_bal_amts, $pd);
                }
                $ig_acc['period_amts'] = $period_amts;
            }
            // calculate period totals (vertical totals)
            $period_tots = [];
            foreach($periods as $pd) {
                $period_tot = 0.00;
                foreach($ig['accounts'] as $ag_ac) {
                    $period_tot += $ag_ac['period_amts'][$pd];
                }
                $period_tots[$pd] = $period_tot;
            }
            $ig['period_tots'] = $period_tots;
        }
        $this->bsResult->incomeGroups = $incomeGroups;
        
        // Create COGC Groups
        $cogcGroups = $this->getGroups("A005");
        // Create Opening Stock Group
        $opst[] = [
                'parent_key' => 'A005',
                'group_key' => 'B0',
                'group_id' => 101,
                'group_name' => 'Opening Stock',
                'path_info' => '{A005,B0}',
                'key_path' => '{A005,B0}',
                'cycle' => false,
                'group_path' => 'A005/B0'
            ];
        array_splice($cogcGroups, 1, 0, $opst);
                
        // Create Closing Stock Group
        $cogcGroups[] = [
                'parent_key' => 'A005',
                'group_key' => 'B999',
                'group_id' => 102,
                'group_name' => 'Closing Stock',
                'path_info' => '{A005,B999}',
                'key_path' => '{A005,B999}',
                'cycle' => false,
                'group_path' => 'A005/B999'
            ];
        foreach($cogcGroups as &$cg) {
            if ($cg['group_id'] == 101 || $cg['group_id'] == 102) { // Find Closing stock group
                $cg['accounts'] = $this->getInvAcc($cg['group_id']);
            } else {
            $cg['accounts'] = $this->acc_heads->findRows('group_id', $cg['group_id'], ['account_id', 'account_head']);
            }
            foreach($cg['accounts'] as &$cg_acc) {
                $acc_bal_amts = $this->raw_data->findRows('account_id', $cg_acc['account_id'], ['sl_no', 'cl_bal_amt']);
                $period_amts = [];
                foreach($periods as $pd) {
                    $period_amts[$pd] = $this->getMatch($acc_bal_amts, $pd);
                }
                $cg_acc['period_amts'] = $period_amts;
            }
            // calculate period totals (vertical totals)
            $period_tots = [];
            foreach($periods as $pd) {
                $period_tot = 0.00;
                foreach($cg['accounts'] as $ag_ac) {
                    $period_tot += $ag_ac['period_amts'][$pd];
                }
                $period_tots[$pd] = $period_tot;
            }
            $cg['period_tots'] = $period_tots;
        }
        $this->bsResult->cogcGroups = $cogcGroups;
        
        // Create Expenses
        $expGroups = $this->getGroups("A006");
        foreach($expGroups as &$eg) {
            $eg['accounts'] = $this->acc_heads->findRows('group_id', $eg['group_id'], ['account_id', 'account_head']);
            foreach($eg['accounts'] as &$eg_acc) {
                $acc_bal_amts = $this->raw_data->findRows('account_id', $eg_acc['account_id'], ['sl_no', 'cl_bal_amt']);
                $period_amts = [];
                foreach($periods as $pd) {
                    $period_amts[$pd] = $this->getMatch($acc_bal_amts, $pd);
                }
                $eg_acc['period_amts'] = $period_amts;
            }
            // calculate period totals (vertical totals)
            $period_tots = [];
            foreach($periods as $pd) {
                $period_tot = 0.00;
                foreach($eg['accounts'] as $ag_ac) {
                    $period_tot += $ag_ac['period_amts'][$pd];
                }
                $period_tots[$pd] = $period_tot;
            }
            $eg['period_tots'] = $period_tots;
        }
        $this->bsResult->expenseGroups = $expGroups;
    }
    
    private function createGP_NP() {
        $periods = $this->raw_data->asArray('sl_no', 'sl_no');
        $gp_amt = [];
        $np_amt = [];
        foreach($periods as $pd) {
            $amt = 0.00;
            foreach($this->bsResult->incomeGroups as $ig) {
                $amt += $ig['period_tots'][$pd];
            }
            foreach($this->bsResult->cogcGroups as $cg) {
                $amt -= $cg['period_tots'][$pd];
            }
            $gp_amt[$pd] = $amt;
            foreach($this->bsResult->expenseGroups as $eg) {
                $amt -= $eg['period_tots'][$pd];
            }
            $np_amt[$pd] = $amt;
        }
        $this->bsResult->gp = $gp_amt;
        $this->bsResult->np = $np_amt;
    }
    
    /**
     * Finds rows matching the group_key. Generic filtering function.
     * This array contains a collection of all groups belonging to the parent group_key
     * @param string $pk group_key value like 'A001'
     * @return array An array of groups with all child groups
     */
    private function getGroups(string $pk) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        $cmm->setCommandText("With Recursive grp_parent
            As
            (	Select parent_key, group_key, group_id, 1 as level, group_name, 
                            array[group_key::text] as path_info, array[group_key::text] as key_path, false as cycle
                    From ac.account_group
                    Where parent_key = '0' -- menu_id = (Select menu_id From sys.menu Where menu_name = 'mnuSd')
                            --And not is_hidden
                    Union All
                    Select a.parent_key, a.group_key, a.group_id, b.level + 1, a.group_name, --rpad('', b.level * 2, '>') || a.menu_text, 
                            b.path_info||a.group_key::text, b.key_path||a.group_key::text, a.group_key = Any(b.path_info)
                    From ac.account_group a
                    Inner Join grp_parent b On a.parent_key = b.group_key
                    Where not cycle
            )
            Select *, array_to_string(key_path, '/') group_path
            From grp_parent
            Where left(array_to_string(key_path, '/'), 4) = :ppk
            Order by path_info");
        $cmm->addParam('ppk', $pk);
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt->Rows();
    }
    
    /**
     * Gets Inventory Accounts associated to Stock
     * @return array Returns [account_id, account_head]
     */
    private function getInvAcc($grp_id) {
        $cmm = new \app\cwf\vsla\data\SqlCommand();
        if ($grp_id == 101) {
            $cmm->setCommandText("Select b.account_id * -1 account_id, b.account_head
                    From st.material a
                    Inner Join ac.account_head b On a.inventory_account_id = b.account_id
                    Where ((a.annex_info->>'is_service')::Boolean Is Null Or (a.annex_info->>'is_service')::Boolean = false)
                    Group by b.account_id, b.account_head");
        } elseif ($grp_id == 102) {
            $cmm->setCommandText("Select b.account_id * -2 account_id, b.account_head
                    From st.material a
                    Inner Join ac.account_head b On a.inventory_account_id = b.account_id
                    Where ((a.annex_info->>'is_service')::Boolean Is Null Or (a.annex_info->>'is_service')::Boolean = false)
                    Group by b.account_id, b.account_head");
        }
        $dt = \app\cwf\vsla\data\DataConnect::getData($cmm);
        return $dt->Rows();
    }
    
    /**
     * Gets matching period amt or returns 0.00
     */
    private function getMatch(array $period_amts, int $sl_no) {
        foreach($period_amts as $pd) {
            if ($pd['sl_no'] == $sl_no) {
                return $pd['cl_bal_amt'];
            }
        } 
        return 0.00;
    }
}

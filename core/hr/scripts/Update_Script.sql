/* 
* To update vch_type in menu table for the purpose of systemwide document search - 16 Aug, 2018
Update sys.menu
set vch_type = '{PRL}'
where menu_name = 'mnuPayrollGeneration';

Update sys.menu
set vch_type = '{GR}'
where menu_name = 'mnuGratuity';

Update sys.menu
set vch_type = '{LN}'
where menu_name = 'mnuLoan';

Update sys.menu
set vch_type = '{FS}'
where menu_name = 'mnuFinalSettlement';

Update sys.menu
set vch_type = '{PPT}'
where menu_name = 'mnuPayrollPayment';
 */


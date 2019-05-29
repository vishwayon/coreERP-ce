/* 
* To update vch_type in menu table for the purpose of systemwide document search - 16 Aug, 2018
Update sys.menu
set vch_type = '{PIR}'
where menu_name = 'mnuPosGstInvRet';

Update sys.menu
set vch_type = '{PIV}'
where menu_name = 'mnuPosGstInv';

Update sys.menu
set vch_type = '{PI}'
where menu_name = 'mnuPosInv';

Update sys.menu
set vch_type = '{PSR}'
where menu_name = 'mnuPosInvRet';
 */


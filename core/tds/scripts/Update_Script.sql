update tds.tds_payment_control
set annex_info = jsonb_set(annex_info, '{is_pdc}', 'false'::jsonb, true)
Where annex_info->>'is_pdc' is Null;

?==?
/*
* To update vch_type in menu table for the purpose of systemwide document search - 16 Aug, 2018
Update sys.menu
set vch_type = '{TDPY}'
where menu_name = 'mnuTDSPayment';

Update sys.menu
set vch_type = '{TDR}'
where menu_name = 'mnuTDSReturn';
*/
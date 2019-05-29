update fa.ap_control
set annex_info = jsonb_set(annex_info, '{is_pdc}', 'false'::jsonb, true)
Where annex_info->>'is_pdc' is Null
	And doc_type = 'AP2';

?==?

/*
* To update vch_type in menu table for the purpose of systemwide document search - 16 Aug, 2018
Update sys.menu
set vch_type = '{AP}'
where menu_name = 'mnuAssetPurchase';

Update sys.menu
set vch_type = '{AD}'
where menu_name = 'mnuAssetDep';

Update sys.menu
set vch_type = '{AS}'
where menu_name = 'mnuAssetSale';

Update sys.menu
set vch_type = '{AP2}'
where menu_name = 'mnuGstAssetPurchase';

Update sys.menu
set vch_type = '{AS2}'
where menu_name = 'mnuGstAssetSale';
*/
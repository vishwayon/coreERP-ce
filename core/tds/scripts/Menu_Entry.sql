INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, menu_code)
Select (select max(menu_id) + 1 from sys.menu), -1, sys.sp_get_menu_key(''), 'mnuTaxes', 'Taxes', 0, null, false, current_timestamp(0), '', 'tax'
Where not exists (select 1 from sys.menu as a where a.menu_name='mnuTaxes');

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTaxes'), sys.sp_get_menu_key('mnuTaxes'), 'mnuTxMasters', 'Masters', 0, null, false, current_timestamp(0), ''
Where not exists (select 1 from sys.menu as a where a.menu_name='mnuTxMasters');

?==?
--  *************** Masters ***************
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxMasters'), sys.sp_get_menu_key('mnuTxMasters'), 'mnuPersonType', 'Person Type', 2, md5('PersonType')::uuid, false, current_timestamp(0), 'core/tds/form/collection&formName=personType/PersonTypeCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxMasters'), sys.sp_get_menu_key('mnuTxMasters'), 'mnuSection', 'Section', 2, md5('Section')::uuid, false, current_timestamp(0), 'core/tds/form/collection&formName=section/SectionCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxMasters'), sys.sp_get_menu_key('mnuTxMasters'), 'mnuRate', 'Rate', 2, md5('Rate')::uuid, false, current_timestamp(0), 'core/tds/form/collection&formName=rate/RateCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxMasters'), sys.sp_get_menu_key('mnuTxMasters'), 'mnuDeductorInfo', 'Deductor Info', 2, md5('DeductorInfo')::uuid, false, current_timestamp(0), 'core/tds/form/collection&formName=deductorInfo/DeductorInfoCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTaxes'), sys.sp_get_menu_key('mnuTaxes'), 'mnuTxDocuments', 'Documents', 0, null, false, current_timestamp(0), ''
Where not exists (select 1 from sys.menu as a where a.menu_name='mnuTxDocuments');

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxDocuments'), sys.sp_get_menu_key('mnuTxDocuments'), 'mnuTDSPayment', 'TDS Payment', 1, md5('TDSPayment')::uuid, false, current_timestamp(0), 'core/tds/form/collection&formName=tdsPayment/TDSPaymentCollectionView', '{TDPY}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxDocuments'), sys.sp_get_menu_key('mnuTxDocuments'), 'mnuTDSReturn', 'TDS Return', 1, md5('TDSReturn')::uuid, false, current_timestamp(0), 'core/tds/form/collection&formName=tdsReturn/TDSReturnCollectionView', '{TDR}';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTaxes'), sys.sp_get_menu_key('mnuTaxes'), 'mnuTxUtils', 'Utilities', 0, null, false, current_timestamp(0), ''
Where not exists (select 1 from sys.menu as a where a.menu_name='mnuTxUtils');


?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxUtils'), sys.sp_get_menu_key('mnuTxUtils'), 'mnuTDSChallanInfo', 'TDS Challan Info', 4, null, false, current_timestamp(0), 'core/tds/tds-challan-info'; 

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTaxes'), sys.sp_get_menu_key('mnuTaxes'), 'mnuTxReports', 'Reports', 0, null, false, current_timestamp(0), '';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxReports'), sys.sp_get_menu_key('mnuTxReports'), 'mnuTDSDeducted', 'TDS Deducted', 3, md5('TDSDeducted')::uuid, false, current_timestamp, 'cwf/fwShell/jreport/viewer&xmlPath=../core/tds/reports/tdsDeducted/TDSDeducted';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxReports'), sys.sp_get_menu_key('mnuTxReports'), 'mnuTDSPayments', 'TDS Payments', 3, md5('TDSPayments')::uuid, false, current_timestamp, 'cwf/fwShell/jreport/viewer&xmlPath=../core/tds/reports/tdsPayments/TDSPayments';

?==?

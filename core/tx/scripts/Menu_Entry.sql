INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, menu_code)
Select (select max(menu_id) + 1 from sys.menu), -1, sys.sp_get_menu_key(''), 'mnuTaxes', 'Taxes', 0, null, false, current_timestamp(0), '', 'tax'
Where not exists (select 1 from sys.menu as a where a.menu_name='mnuTaxes');

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTaxes'), sys.sp_get_menu_key('mnuTaxes'), 'mnuTxMasters', 'Masters', 0, null, false, current_timestamp(0), ''
Where not exists (select 1 from sys.menu as a where a.menu_name='mnuTxMasters');

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxMasters'), sys.sp_get_menu_key('mnuTxMasters'), 'mnuTaxType', 'Tax Type', 2, md5('TaxType')::uuid, true, current_timestamp(0), 'core/tx/form/collection&formName=taxType/TaxTypeCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxMasters'), sys.sp_get_menu_key('mnuTxMasters'), 'mnuTaxSchedule', 'Tax Schedule', 2, md5('TaxSchedule')::uuid,false, current_timestamp(0), 'core/tx/form/collection&formName=taxSchedule/TaxScheduleCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxMasters'), sys.sp_get_menu_key('mnuTxMasters'), 'mnuVatType', 'VAT Types', 2, md5('VatType')::uuid,false, current_timestamp(0), 'core/tx/form/collection&formName=vatType/VatTypeCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxUtils'), sys.sp_get_menu_key('mnuTxUtils'), 'mnuVatUpload', 'VAT Upload', 2, md5('VatUpload')::uuid,false, current_timestamp(0), 'core/tx/vat-upload/show-upload'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxUtils'), sys.sp_get_menu_key('mnuTxUtils'), 'mnuGstrOne', 'GST Return GSTR1', 2, md5('Gstr1')::uuid,false, current_timestamp(0), 'core/tx/form/collection&formName=gstr1/Gstr1CollectionView';

?==?
Insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxUtils'), sys.sp_get_menu_key('mnuTxUtils'), 'mnuGstrOneDetail', 'GST Return GSTR1 Detail', 3, md5('Gstr1Detail')::uuid, false, current_timestamp(0), 'core/tx/gst-return/get-gstr1-detail-view';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxUtils'), sys.sp_get_menu_key('mnuTxUtils'), 'mnuGstrTwo', 'GST Return GSTR2', 2, md5('Gstr2')::uuid,false, current_timestamp(0), 'core/tx/form/collection&formName=gstr2/Gstr2CollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxUtils'), sys.sp_get_menu_key('mnuTxUtils'), 'mnuGstr2aReco', 'GSTR2A Reco', 2, md5('Gstr2aReco')::uuid,false, current_timestamp(0), 'core/tx/form/collection&formName=gstr2aReco/Gstr2aRecoCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxUtils'), sys.sp_get_menu_key('mnuTxUtils'), 'mnuGstr2aRecoV2', 'GSTR2A Reco V2', 2, md5('Gstr2aRecoV2')::uuid,false, current_timestamp(0), 'core/tx/form/collection&formName=gstr2aRecoV2/Gstr2aRecoCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxUtils'), sys.sp_get_menu_key('mnuTxUtils'), 'mnuGstr2ExpReco', 'GSTR2 Expense Reco', 3, md5('Gstr2ExpReco')::uuid,false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/tx/gstr2/Gstr2ExpReco'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxUtils'), sys.sp_get_menu_key('mnuTxUtils'), 'mnuGstrRespView', 'View GSTR Response', 3, md5('GstrResp')::uuid,false, current_timestamp(0), 'core/tx/gst-return/gstr-resp-view';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxMasters'), sys.sp_get_menu_key('mnuTxMasters'), 'mnuGSTRate', 'GST Rate', 2, md5('GSTRate')::uuid, false, current_timestamp(0), 'core/tx/form/collection&formName=gstRate/GSTRateCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxMasters'), sys.sp_get_menu_key('mnuTxMasters'), 'mnuHSNRate', 'HSN Rate', 2, md5('HSNRate')::uuid, false, current_timestamp(0), 'core/tx/form/collection&formName=hsnRate/HSNRateCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxUtils'), sys.sp_get_menu_key('mnuTxUtils'), 'mnuGstr2aRespView', 'View GSTR2A Response', 3, md5('Gstr2aResp')::uuid,false, current_timestamp(0), 'core/tx/gst-return/gstr2a-resp-view';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuTxUtils'), sys.sp_get_menu_key('mnuTxUtils'), 'mnuGstnReq', 'GSTN Authenticate', 3, md5('GstnReq')::uuid,false, current_timestamp(0), 'core/tx/gst-return/gstn-req-view';

?==?

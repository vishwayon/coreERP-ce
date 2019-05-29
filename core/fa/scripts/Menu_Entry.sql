--  *************** Top Level Menu ***************
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, menu_code)
Select (select max(menu_id) + 1 from sys.menu), -1, sys.sp_get_menu_key(''), 'mnuFixedAsset', 'Fixed Asset', 0, null, false, current_timestamp(0), '', 'fa';

?==?
--  *************** Documents *************** 
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuFixedAsset'), sys.sp_get_menu_key('mnuFixedAsset'), 'mnuFaDocuments', 'Documents', 0, null, false, current_timestamp(0), ''; 

--Deprecated
--INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
--Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuFaDocuments'), sys.sp_get_menu_key('mnuFaDocuments'), 'mnuAssetPurchase', 'Asset Purchase', 1, md5('AssetPurchase')::uuid,false, current_timestamp(0), 'core/fa/form/collection&formName=assetPurchase/AssetPurchaseCollectionView', '{AP}'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuFaDocuments'), sys.sp_get_menu_key('mnuFaDocuments'), 'mnuGstAssetPurchase', 'GST Asset Purchase', 1, md5('GstAssetPurchase')::uuid,false, current_timestamp(0), 'core/fa/form/collection&formName=gstAssetPurchase/GstAssetPurchaseCollectionView', '{AP2}'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuFaDocuments'), sys.sp_get_menu_key('mnuFaDocuments'), 'mnuAssetDep', 'Asset Depreciation', 1, md5('AssetDep')::uuid,false, current_timestamp(0), 'core/fa/form/collection&formName=assetDep/AssetDepCollectionView', '{AD}'; 

--Deprecated
--INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
--Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuFaDocuments'), sys.sp_get_menu_key('mnuFaDocuments'), 'mnuAssetSale', 'Asset Sale', 1, md5('AssetSale')::uuid,false, current_timestamp(0), 'core/fa/form/collection&formName=assetSale/AssetSaleCollectionView', '{AS}'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuFaDocuments'), sys.sp_get_menu_key('mnuFaDocuments'), 'mnuGstAssetSale', 'GST Asset Sale', 1, md5('GstAssetSale')::uuid,false, current_timestamp(0), 'core/fa/form/collection&formName=gstAssetSale/GstAssetSaleCollectionView', '{AS2}'; 

?==?
-- ******************** First Level Menu for Reports ***********************
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuFixedAsset'), sys.sp_get_menu_key('mnuFixedAsset'), 'mnuFaReports', 'Reports', 0, null, false, current_timestamp(0), '';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuFaReports'), sys.sp_get_menu_key('mnuFaReports'), 'mnuAssetRegister', 'Asset Register', 3, md5('AssetRegister')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/fa/reports/assetRegister/AssetRegister';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuFaReports'), sys.sp_get_menu_key('mnuFaReports'), 'mnuAssetDepRegister', 'Asset Dep Register', 3, md5('AssetDepRegister')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/fa/reports/assetDepRegister/AssetDepRegister';

?==?

--  *************** First Level Menu Masters ***************
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuFixedAsset'), sys.sp_get_menu_key('mnuFixedAsset'), 'mnuFaMasters', 'Masters', 0, null, false, current_timestamp(0), ''; 

?==?
--  *************** Masters ***************
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuFaMasters'), sys.sp_get_menu_key('mnuFaMasters'), 'mnuAssetBook', 'Asset Book', 2, md5('AssetBook')::uuid,false, current_timestamp(0), 'core/fa/form/collection&formName=assetBook/AssetBookCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuFaMasters'), sys.sp_get_menu_key('mnuFaMasters'), 'mnuAssetClass', 'Asset Class', 2, md5('AssetClass')::uuid,false, current_timestamp(0), 'core/fa/form/collection&formName=assetClass/AssetClassCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuFaMasters'), sys.sp_get_menu_key('mnuFaMasters'), 'mnuAssetLocation', 'Asset Location', 2, md5('AssetLocation')::uuid,false, current_timestamp(0), 'core/fa/form/collection&formName=assetLocation/AssetLocationCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuFaMasters'), sys.sp_get_menu_key('mnuFaMasters'), 'mnuSubClass', 'Sub Class', 2, md5('SubClass')::uuid,false, current_timestamp(0), 'core/fa/form/collection&formName=subClass/SubClassCollectionView'; 

?==?
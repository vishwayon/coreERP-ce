INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, menu_code)
Select (select max(menu_id) + 1 from sys.menu), -1, sys.sp_get_menu_key(''), 'mnuPOS', 'Point Of Sale', 0, null, false, current_timestamp(0), '', 'pos';

?==?
-- ******************** Masters ***********************
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuPOS'), sys.sp_get_menu_key('mnuPOS'), 'mnuPosMasters', 'Masters', 0, null, false, current_timestamp(0), ''; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuPosMasters'), sys.sp_get_menu_key('mnuPosMasters'), 'mnuTerminal', 'Terminal', 2, md5('Terminal')::uuid,false, current_timestamp(0), 'core/pos/form/collection&formName=terminal/TerminalCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuPosMasters'), sys.sp_get_menu_key('mnuPosMasters'), 'mnuTday', 'Txn. Day', 2, md5('Tday')::uuid,false, current_timestamp(0), 'core/pos/form/collection&formName=tday/TdayCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuPosMasters'), sys.sp_get_menu_key('mnuPosMasters'), 'mnuCcMac', 'Credit Card Mac', 2, md5('CcMac')::uuid,false, current_timestamp(0), 'core/pos/form/collection&formName=ccMac/CcMacCollectionView'; 

?==?
-- ******************** Documents ***********************
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuPOS'), sys.sp_get_menu_key('mnuPOS'), 'mnuPosDocuments', 'Documents', 0, null, false, current_timestamp(0), '';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, menu_code, is_staged, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuPosDocuments'), sys.sp_get_menu_key('mnuPosDocuments'), 'mnuPosInv', 'Invoice', 1, md5('Inv')::uuid, false, current_timestamp(0), 'core/pos/form/collection&formName=inv/InvCollectionView', 'pos', false, '{PI}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, menu_code, is_staged, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuPosDocuments'), sys.sp_get_menu_key('mnuPosDocuments'), 'mnuPosGstInv', 'GST Invoice', 1, md5('GstInv')::uuid, false, current_timestamp(0), 'core/pos/form/collection&formName=gstInv/GstInvCollectionView', 'pos', false, '{PIV}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, menu_code, is_staged, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuPosDocuments'), sys.sp_get_menu_key('mnuPosDocuments'), 'mnuPosGstInvRet', 'GST Sale Return', 1, md5('GstInvRet')::uuid, false, current_timestamp(0), 'core/pos/form/collection&formName=gir/GstInvRetCollectionView', 'pos', false, '{PIR}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, menu_code, is_staged, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuPosDocuments'), sys.sp_get_menu_key('mnuPosDocuments'), 'mnuPosInvRet', 'Sales Return', 1, md5('InvRet')::uuid, false, current_timestamp(0), 'core/pos/form/collection&formName=ir/InvRetCollectionView', 'pos', false, '{PSR}';

?==?
-- ******************** Reports ***********************
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuPOS'), sys.sp_get_menu_key('mnuPOS'), 'mnuPosReports', 'Reports', 0, null, false, current_timestamp(0), '';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuPosReports'), sys.sp_get_menu_key('mnuPosReports'), 'mnuPosDailySaleSummary', 'Daily Sale Summary', 3,  md5('DailySaleSummary')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/pos/reports/dailySaleSummary/DailySaleSummary';

?==?
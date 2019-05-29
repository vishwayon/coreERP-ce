Insert Into sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, company_na)
Select (select COALESCE(max(menu_id), 0) + 1 from sys.menu_admin), 0, sys.sp_get_admin_menu_key(''), 'mnuAdminSystem', 'Admin', 0, null, false, current_timestamp(0), '', true
Where not exists (select 1 from sys.menu_admin as a where a.menu_name='mnuAdminSystem');

?==?
INSERT INTO sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, company_na)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminUser', 'User', 0, md5('User')::uuid, false, current_timestamp(0), 'cwf/sys/form/collection&formName=user/UserCollectionView', false;

?==?
insert into sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, company_na)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminRestrictIP', 'Restrict IP', 2, md5('RestrictIP')::uuid, false, current_timestamp(0),'cwf/sys/form/collection&formName=restrictIP/RestrictIPCollectionView', true;

?==?
INSERT INTO sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, company_na)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'),'mnuAdminUserCompanyAssociation', 'User Company Association', 0, md5('UserCompanyAssociation')::uuid, false, current_timestamp(0),'cwf/sys/form/collection&formName=userCompanyAssociation/UserCompanyAssociationCollectionView', false;

?==?
INSERT INTO sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, company_na)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminCompany', 'Company', 0, md5('Company')::uuid, false, current_timestamp(0), 'cwf/sys/form/collection&formName=company/CompanyCollectionView', true; 

?==?
INSERT INTO sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, company_na)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'),'mnuAdminConnectToCompany', 'Connect To Company', 0, NULL, false, current_timestamp(0),'cwf/sys/form/connectcompany', true;

?==?
INSERT INTO sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminBranch', 'Branch', 0, md5('Branch')::uuid, false, current_timestamp(0), 'cwf/sys/form/collection&formName=branch/BranchCollectionView';

?==?
INSERT INTO sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminFinancialYear', 'Financial Year', 0, md5('FinancialYear')::uuid, false, current_timestamp(0), 'cwf/sys/form/collection&formName=financialyear/FinancialYearCollectionView';

?==?
INSERT INTO sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminFiscalMonth', 'Fiscal Month', 0, md5('FiscalMonth')::uuid, false, current_timestamp(0), 'cwf/sys/form/fiscalmonth&formName=fiscalMonth/FiscalMonthCollectionView';

?==?
INSERT INTO sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminFeedback', 'Support/Issue', 0, md5('Feedback')::uuid, false, current_timestamp(0), 'cwf/sys/form/collection&formName=adminFeedback/AdminFeedbackCollectionView';

?==?
INSERT INTO sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, company_na)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminNewUser', 'Admin User', 0, md5('AdminUser')::uuid, false, current_timestamp(0), 'cwf/sys/form/collection&formName=adminUser/AdminUserCollectionView', false;

?==?
INSERT INTO sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminUserAccessLevel', 'User Access Level', 0, NULL, false, current_timestamp(0), 'cwf/sys/form/collection&formName=userAccessLevel/UserAccessLevelCollectionView';

?==?
INSERT INTO sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminUserToLedger', 'User To Ledger', 0, NULL, false, current_timestamp(0), 'cwf/sys/form/collection&formName=userToLedger/UserToLedgerCollectionView';

?==?
insert into sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminSystemSettings', 'System Settings', 2, null, false, current_timestamp(0), 'javascript:coreWebApp.rendercontents(''?r=/cwf/sys/form&formName=systemSettings/SystemSettingsEditForm&formParams={"company_id": -1,"doc_type":""}'',"","","core_sys.sys_afterload")';

?==?
insert into sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminBuildDocID', 'Customise Doc ID', 2, null, false, current_timestamp(0), 'javascript:coreWebApp.rendercontents(''?r=cwf/sys/main/build-doc-id'',"","","")';

?==?
INSERT INTO sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminEntityExtn', 'Entity Extension', 0, NULL, false, current_timestamp(0), 'cwf/sys/form/collection&formName=entityExtn/EntityExtnCollectionView';

?==?
insert INTO sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminRole', 'Role', 0, NULL, false, current_timestamp(0), 'cwf/sys/form/collection&formName=role/RoleCollectionView';

?==?
insert INTO sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminRemoteServer', 'Remote Server', 0, NULL, false, current_timestamp(0), 'cwf/sys/form/collection&formName=remoteServer/RemoteServerCollectionView';

?==?
INSERT INTO sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminBusinessUnit', 'Business Unit', 0, md5('BusinessUnit')::uuid, false, current_timestamp(0), 'cwf/sys/form/collection&formName=businessUnit/BusinessUnitCollectionView';

?==?
INSERT INTO sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminUserClfyAccess', 'Closed Finyear Access', 2, md5('ClosedFinyearAccess')::uuid, false, current_timestamp(0),'cwf/sys/form/collection&formName=closedFinyearAccess/ClosedFinyearAccessCollectionView';

?==?

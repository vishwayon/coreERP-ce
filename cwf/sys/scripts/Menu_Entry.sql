-- Top Level Menu
Insert Into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, menu_code)
Select (select COALESCE(max(menu_id), 0) + 1 from sys.menu), -1, sys.sp_get_menu_key(''), 'mnuSystem', 'System', 0, null, false, current_timestamp(0), '', 'sys';

?==?
-- ******************** First level System ***********************
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuSystem'), sys.sp_get_menu_key('mnuSystem'), 'mnuFeedback', 'Support/Issue', 2, md5('Feedback')::uuid, false, current_timestamp(0),'cwf/sys/form/collection&formName=feedback/FeedbackCollectionView';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuSystem'), sys.sp_get_menu_key('mnuSystem'), 'mnuUserRole', 'Role', 2, md5('Role')::uuid, false, current_timestamp(0), 'cwf/sys/form/collection&formName=role/RoleCollectionView';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuSystem'), sys.sp_get_menu_key('mnuSystem'), 'mnuRestrictIP', 'Restrict IP', 2, md5('RestrictIP')::uuid, false, current_timestamp(0),'cwf/sys/form/collection&formName=restrictIP/RestrictIPCollectionView';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuSystem'), sys.sp_get_menu_key('mnuSystem'), 'mnuPrintRequest', 'Print Request', 4, null, false, current_timestamp(0),'cwf/sys/print-request';

?==?
insert INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuSystem'), sys.sp_get_menu_key('mnuSystem'), 'mnuWidgetRequest', 'Widget Request', 4, NULL, false, current_timestamp(0), 'cwf/sys/widget/requestlist';

?==?
insert INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuSystem'), sys.sp_get_menu_key('mnuSystem'), 'mnuWFReassign', 'Workflow reassign', 4, NULL, false, current_timestamp(0), 'cwf/sys/reassign';

?==?
insert INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuSystem'), sys.sp_get_menu_key('mnuSystem'), 'mnuPendingDocs', 'Pending Documents', 4, NULL, false, current_timestamp(0), 'cwf/sys/pending-docs';

?==?
insert INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, count_class)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuSystem'), sys.sp_get_menu_key('mnuSystem'), 'mnuWfApproval', 'Approval', 4, NULL, false, current_timestamp(0), 'cwf/sys/wf-approval', '\app\cwf\sys\wfApproval\WfApprovalHelper';

?==?
Insert Into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, menu_code)
Select (select COALESCE(max(menu_id), 0) + 1 from sys.menu), -1, sys.sp_get_menu_key(''), 'mnuDashboard', 'Dashboard', 0, null, false, current_timestamp(0), '', '';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuDashboard'), sys.sp_get_menu_key('mnuDashboard'), 'mnuSysDocMon', 'Documents', 3, null, false, current_timestamp(0), 'cwf/fwShell/main/dashboard&dbd=fwShell/dashboard/home';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuSystem'), sys.sp_get_menu_key('mnuSystem'), 'mnuUserAccessRights', 'User Access Rights', 4, md5('UserAccessRights')::uuid, false, current_timestamp(0), 'cwf/sys/form/user-access-rights';

?==?
insert INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuSystem'), sys.sp_get_menu_key('mnuSystem'), 'mnuWFPullDoc', 'Pull Document', 4, NULL, false, current_timestamp(0), 'cwf/sys/reassign/pull-doc';

?==?
insert INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuSystem'), sys.sp_get_menu_key('mnuSystem'), 'mnuAttView', 'Audit Trail', 4, NULL, false, current_timestamp(0), 'cwf/sys/main/view-audit-trail';

?==?
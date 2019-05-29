Insert Into sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, company_na)
Select (select COALESCE(max(menu_id), 0) + 1 from sys.menu_admin), 0, sys.sp_get_admin_menu_key(''), 'mnuAdminSystem', 'Admin', 0, null, false, current_timestamp(0), '', true
Where not exists (select 1 from sys.menu_admin as a where a.menu_name='mnuAdminSystem');

?==?
INSERT INTO sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, company_na)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminPersonType', 'Person Type', 0, md5('AdminPersonType')::uuid, false, current_timestamp(0), 'core/tds/form/collection&formName=adminPersonType/AdminPersonTypeCollectionView', true;

?==?
INSERT INTO sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, company_na)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminSection', 'Section', 0, md5('AdminSection')::uuid, false, current_timestamp(0), 'core/tds/form/collection&formName=adminSection/AdminSectionCollectionView', true;

?==?
INSERT INTO sys.menu_admin(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, company_na)
Select (select max(menu_id) + 1 from sys.menu_admin), sys.fn_get_admin_menu_id_by_name('mnuAdminSystem'), sys.sp_get_admin_menu_key('mnuAdminSystem'), 'mnuAdminRate', 'Rate', 0, md5('AdminRate')::uuid, false, current_timestamp(0), 'core/tds/form/collection&formName=adminRate/AdminRateCollectionView', true;

?==?


INSERT INTO sys.menu_mob(menu_mob_id, parent_menu_mob_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, menu_code)
Select (select COALESCE(max(menu_mob_id), 0) + 1  from sys.menu_mob), -1, sys.sp_get_menu_key(''), 'mnu_WfApproval_mob', 'Approval', 2, NULL, false, current_timestamp(0), 'cwf/sys/wf-approval/mob','';

?==?
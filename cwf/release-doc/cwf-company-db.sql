Update sys.menu
set count_class =  '\app\cwf\sys\wfApproval\WfApprovalHelper'
where menu_name = 'mnuWfApproval';

?==?
Alter TABLE sys.menu
add column vch_type varchar[] default '{}';

?==?
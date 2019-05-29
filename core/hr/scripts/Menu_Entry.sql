
--  *************** Top Level Menu ***************
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, menu_code)
Select (select max(menu_id) + 1 from sys.menu), -1, sys.sp_get_menu_key(''), 'mnuHR', 'HR And Payroll', 0, null, false, current_timestamp(0), '', 'hr';

?==?
--  *************** First Level Menu Masters ***************
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHR'), sys.sp_get_menu_key('mnuHR'), 'mnuHrMasters', 'Masters', 0, null, false, current_timestamp(0), ''; 

?==?
--  *************** Masters ***************
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHrMasters'), sys.sp_get_menu_key('mnuHrMasters'), 'mnuEmployee', 'Employee', 2, md5('Employee')::uuid,false, current_timestamp(0), 'core/hr/form/collection&formName=employee/EmployeeCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHrMasters'), sys.sp_get_menu_key('mnuHrMasters'), 'mnuSkill', 'Skill', 2, md5('Skill')::uuid,false, current_timestamp(0), 'core/hr/form/collection&formName=skill/SkillCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHrMasters'), sys.sp_get_menu_key('mnuHrMasters'), 'mnuEmployeeOrg', 'Employee Org Detail', 2, md5('EmployeeOrg')::uuid,false, current_timestamp(0), 'core/hr/form/collection&formName=employeeOrg/EmployeeOrgCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHrMasters'), sys.sp_get_menu_key('mnuHrMasters'), 'mnuHolidayList', 'Holiday List', 2, md5('HolidayList')::uuid,false, current_timestamp(0), 'core/hr/form/collection&formName=holidayList/HolidayListCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHrMasters'), sys.sp_get_menu_key('mnuHrMasters'), 'mnuGrade', 'Grade', 2, md5('Grade')::uuid,false, current_timestamp(0), 'core/hr/form/collection&formName=grade/GradeCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHrMasters'), sys.sp_get_menu_key('mnuHrMasters'), 'mnuDesignation', 'Designation', 2, md5('Designation')::uuid,false, current_timestamp(0), 'core/hr/form/collection&formName=designation/DesignationCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHrMasters'), sys.sp_get_menu_key('mnuHrMasters'), 'mnuLeaveType', 'Leave Type', 2, md5('LeaveType')::uuid,false, current_timestamp(0), 'core/hr/form/collection&formName=leaveType/LeaveTypeCollectionView';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHrMasters'), sys.sp_get_menu_key('mnuHrMasters'), 'mnuLeave', 'Leave', 2, md5('Leave')::uuid,false, current_timestamp(0), 'core/hr/form/collection&formName=leave/LeaveCollectionView';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHrMasters'), sys.sp_get_menu_key('mnuHrMasters'), 'mnuattendance', 'Attendance', 2, md5('Attendance')::uuid,false, current_timestamp(0), 'core/hr/form/collection&formName=attendance/AttendanceCollectionView';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHrMasters'), sys.sp_get_menu_key('mnuHrMasters'), 'mnuPayhead', 'Payhead', 2, md5('Payhead')::uuid,false, current_timestamp(0), 'core/hr/form/collection&formName=payhead/PayheadCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHrMasters'), sys.sp_get_menu_key('mnuHrMasters'), 'mnuEmployeePayplan', 'Employee Payplan', 2, md5('EmployeePayplan')::uuid,false, current_timestamp(0), 'core/hr/form/collection&formName=employeePayplan/EmployeePayplanCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHrMasters'), sys.sp_get_menu_key('mnuHrMasters'), 'mnuPaySchedule', 'Pay Schedule', 2, md5('PaySchedule')::uuid,false, current_timestamp(0), 'core/hr/form/collection&formName=paySchedule/PayScheduleCollectionView';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHrMasters'), sys.sp_get_menu_key('mnuHrMasters'), 'mnuPayrollGroup', 'Payroll Group', 2, md5('PayrollGroup')::uuid,false, current_timestamp(0), 'core/hr/form/collection&formName=payrollgroup/PayrollGroupCollectionView'; 

?==?
--  *************** Documents *************** 

INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHR'), sys.sp_get_menu_key('mnuHR'), 'mnuHrDocuments', 'Documents', 0, null, false, current_timestamp(0), ''
Where not exists (select 1 from sys.menu as a where a.menu_name='mnuHrDocuments');

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHrDocuments'), sys.sp_get_menu_key('mnuHrDocuments'), 'mnuPayrollGeneration', 'Payroll Generation', 1, md5('PayrollGeneration')::uuid,false, current_timestamp(0), 'core/hr/form/collection&formName=payrollGeneration/PayrollGenerationCollectionView', '{PRL}'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHrDocuments'), sys.sp_get_menu_key('mnuHrDocuments'), 'mnuGratuity', 'Gratuity', 1, md5('Gratuity')::uuid,false, current_timestamp(0), 'core/hr/form/collection&formName=gratuity/GratuityCollectionView', '{GR}'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHrDocuments'), sys.sp_get_menu_key('mnuHrDocuments'), 'mnuLoan', 'Loan', 1, md5('Loan')::uuid,false, current_timestamp(0), 'core/hr/form/collection&formName=loan/LoanCollectionView', '{LN}'; 

?==?

INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHrDocuments'), sys.sp_get_menu_key('mnuHrDocuments'), 'mnuFinalSettlement', 'Final Settlement', 1, md5('FinalSettlement')::uuid,false, current_timestamp(0), 'core/hr/form/collection&formName=finalSettlement/FinalSettlementCollectionView', '{FS}'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHrDocuments'), sys.sp_get_menu_key('mnuHrDocuments'), 'mnuPayrollPayment', 'Payroll Payment', 1, md5('PayrollPayment')::uuid,false, current_timestamp(0), 'core/hr/form/collection&formName=payrollPayment/PayrollPaymentCollectionView', '{PPT}'; 

?==?

insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHR'), sys.sp_get_menu_key('mnuHR'), 'mnuHRReports', 'Reports', 0, null, false, current_timestamp(0), '';

?==?

insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHRReports'), sys.sp_get_menu_key('mnuHRReports'), 'mnuPaySlip', 'Pay Slip', 3,  md5('PaySlip')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/hr/reports/paySlip/PaySlip';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuHRReports'), sys.sp_get_menu_key('mnuHRReports'), 'mnuPayrollSheet', 'Payroll Sheet', 3,  md5('PayrollSheet')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/hr/reports/payrollSheet/PayrollSheet';

?==?
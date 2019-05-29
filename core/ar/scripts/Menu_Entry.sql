-- Top Level Menu
Insert Into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, menu_code)
Select (select COALESCE(max(menu_id), 0) + 1 from sys.menu), -1, sys.sp_get_menu_key(''), 'mnuAccountsReceivable', 'Accounts Receivable', 0, null, false, current_timestamp(0), '', 'ar';

?==?
--  *************** First Level Menu Documents ***************
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAccountsReceivable'), sys.sp_get_menu_key('mnuAccountsReceivable'), 'mnuArDocuments', 'Documents', 0, null, false, current_timestamp(0), ''; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArDocuments'), sys.sp_get_menu_key('mnuArDocuments'), 'mnuInvoice', 'Invoice', 1, md5('Invoice')::uuid, false, current_timestamp(0), 'core/ar/form/collection&formName=invoice/InvoiceCollectionView', '{INV}'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArDocuments'), sys.sp_get_menu_key('mnuArDocuments'), 'mnuGstInvoice', 'GST Invoice', 1, md5('GstInvoice')::uuid, false, current_timestamp(0), 'core/ar/form/collection&formName=gstInvoice/GstInvoiceCollectionView', '{INV2}'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArDocuments'), sys.sp_get_menu_key('mnuArDocuments'), 'mnuDebitNote', 'Debit Note', 1, md5('DebitNote')::uuid, false, current_timestamp(0), 'core/ar/form/collection&formName=debitNote/DebitNoteCollectionView', '{DN}'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArDocuments'), sys.sp_get_menu_key('mnuArDocuments'), 'mnuCustomerReceipt', 'Customer Receipt', 1, md5('CustomerReceipt')::uuid, false, current_timestamp(0), 'core/ar/form/collection&formName=customerReceipt/CustomerReceiptCollectionView', '{RCPT}'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArDocuments'), sys.sp_get_menu_key('mnuArDocuments'), 'mnuCustomerReceiptIB', 'Customer Receipt - IB', 4, null, true, current_timestamp(0), 'javascript:coreWebApp.rendercontents(''?r=/core/ar/form/wizard&formName=customerReceipt/CustomerReceiptIBWizard&step=SelectCustomerIB'', ''details'', ''contentholder'')'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArDocuments'), sys.sp_get_menu_key('mnuArDocuments'), 'mnuMultiCustReceipt', 'Multi Customer Receipt', 1, md5('MultiCustReceipt')::uuid, false, current_timestamp(0), 'core/ar/form/collection&formName=multiCustReceipt/MultiCustReceiptCollectionView', '{MCR}'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArDocuments'), sys.sp_get_menu_key('mnuArDocuments'), 'mnuAdvanceCustomerReceipt', 'Advance Customer Receipt', 1, md5('AdvanceCustomerReceipt')::uuid, false, current_timestamp(0), 'core/ar/form/collection&formName=advanceCustomerReceipt/AdvanceCustomerReceiptCollectionView', '{ACR}'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArDocuments'), sys.sp_get_menu_key('mnuArDocuments'), 'mnuCustomerRefund', 'Customer Refund', 1, md5('CustomerRefund')::uuid, false, current_timestamp(0), 'core/ar/form/collection&formName=customerRefund/CustomerRefundCollectionView', '{CREF}'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArDocuments'), sys.sp_get_menu_key('mnuArDocuments'), 'mnuCustBalTransfer', 'Customer Balance Transfer', 1, md5('CustBalTransfer')::uuid, false, current_timestamp(0), 'core/ar/form/collection&formName=custBalTransfer/CustBalTransferCollectionView', '{CBT}'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArDocuments'), sys.sp_get_menu_key('mnuArDocuments'), 'mnuCustManualSet', 'Customer Manual Settlement', 2, md5('CustManualSet')::uuid, false, current_timestamp(0), 'javascript:coreWebApp.rendercontents(''?r=/core/ar/form/wizard&formName=custManualSet/CustManualSetWizard&step=SelectCustomer'', ''details'', ''contentholder'')'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArDocuments'), sys.sp_get_menu_key('mnuArDocuments'), 'mnuGstCreditNote', 'GST Sale Return/Dr/Cr Note', 1, md5('GstCreditNote')::uuid, false, current_timestamp(0), 'core/ar/form/collection&formName=gstCreditNote/GstCreditNoteCollectionView',  '{CN2}'; 

?==?
-- ******************** First Level Menu for Reports ***********************
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAccountsReceivable'), sys.sp_get_menu_key('mnuAccountsReceivable'), 'mnuArReports', 'Reports', 0, null, false, current_timestamp(0), '';

?==?
-- ******************** Reports ***********************
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArReports'), sys.sp_get_menu_key('mnuArReports'), 'mnuStatementOfAccountsReceivable', 'Statement of Accounts', 3, md5('StmtOfAcBillsReceivable')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ar/reports/stmtOfAccounts/StmtOfAcBillsReceivable';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArReports'), sys.sp_get_menu_key('mnuArReports'), 'mnuReceivableLedger', 'Receivable Ledger', 3, md5('ReceivableLedger')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ar/reports/receivableLedger/ReceivableLedger';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArReports'), sys.sp_get_menu_key('mnuArReports'), 'mnuArTrialBalance', 'Receivable Balance', 3,  md5('ArTrialBalance')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ar/reports/trialBalance/TrialBalance';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArReports'), sys.sp_get_menu_key('mnuArReports'), 'mnuArCustomerCreditLimit', 'Customer Credit Limit', 3,  md5('CustomerCreditLimit')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ar/reports/customerCreditLimit/CustomerCreditLimit';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArReports'), sys.sp_get_menu_key('mnuArReports'), 'mnuArCustomerOverdue', 'Customer Overdue', 3,  md5('CustomerOverdue')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ar/reports/customerOverdue/CustomerOverdue';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArReports'), sys.sp_get_menu_key('mnuArReports'), 'mnuCustomerDueBySalesman', 'Due By Salesman', 3, md5('CustomerDueBySalesman')::uuid, false, current_timestamp, 'cwf/fwShell/jreport/viewer&xmlPath=../core/ar/reports/customerDueBySalesman/CustomerDueBySalesman';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArReports'), sys.sp_get_menu_key('mnuArReports'), 'mnuDueBySalesmanRestricted', 'Due By Salesman (Restricted)', 3, md5('DueBySalesmanRestricted')::uuid, false, current_timestamp, 'cwf/fwShell/jreport/viewer&xmlPath=../core/ar/reports/dueBySalesmanRestricted/DueBySalesmanRestricted';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArReports'), sys.sp_get_menu_key('mnuArReports'), 'mnuArBusinessTurnover', 'Business Turnover', 3, md5('ArBusinessTurnover')::uuid, false, current_timestamp, 'cwf/fwShell/jreport/viewer&xmlPath=../core/ar/reports/businessTurnover/BusinessTurnover';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArReports'), sys.sp_get_menu_key('mnuArReports'), 'mnuTDSWithheld', 'TDS Withheld', 3, md5('TDSWithheld')::uuid, false, current_timestamp, 'cwf/fwShell/jreport/viewer&xmlPath=../core/ar/reports/tdsWithheld/TDSWithheld';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArReports'), sys.sp_get_menu_key('mnuArReports'), 'mnuTDSRecoStmt', 'TDS Reconciliation', 3, md5('TDSRecoStmt')::uuid, false, current_timestamp, 'cwf/fwShell/jreport/viewer&xmlPath=../core/ar/reports/tdsRecoStmt/TDSRecoStmt';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArReports'), sys.sp_get_menu_key('mnuArReports'), 'mnuArSalesRegister', 'Sales Register', 3, md5('ArSalesRegister')::uuid, false, current_timestamp, 'cwf/fwShell/jreport/viewer&xmlPath=../core/ar/reports/salesRegister/SalesRegister';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArReports'), sys.sp_get_menu_key('mnuArReports'), 'mnuSalesmanColl', 'Salesman Collection', 3, md5('SalesmanColl')::uuid, false, current_timestamp, 'cwf/fwShell/jreport/viewer&xmlPath=../core/ar/reports/salesmanColl/SalesmanColl';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArReports'), sys.sp_get_menu_key('mnuArReports'), 'mnuCustomerSettlement', 'Customer Settlement', 3, md5('CustomerSettlement')::uuid, false, current_timestamp, 'cwf/fwShell/jreport/viewer&xmlPath=../core/ar/reports/customerSettlement/CustomerSettlement';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArReports'), sys.sp_get_menu_key('mnuArReports'), 'mnuOverdueBillsInterest', 'Overdue Interest', 3,  md5('OverdueBillsInterest')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ar/reports/overdueBillsInterest/OverdueBillsInterest';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArReports'), sys.sp_get_menu_key('mnuArReports'), 'mnuInvDispatch', 'Invoice Pending Dispatch', 3, md5('InvDispatch')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ar/reports/invDispatch/InvDispatch';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArReports'), sys.sp_get_menu_key('mnuArReports'), 'mnuRLTransStatement', 'RL Transaction Statement', 3, md5('RLTransStatement')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ar/reports/rLTransStatement/RLTransStatement';

?==?
--  *************** First Level Menu Masters ***************
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAccountsReceivable'), sys.sp_get_menu_key('mnuAccountsReceivable'), 'mnuArMasters', 'Masters', 0, null, false, current_timestamp(0), ''; 

?==?
--  *************** Masters ***************
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArMasters'), sys.sp_get_menu_key('mnuArMasters'), 'mnuCustomer', 'Customer', 2, md5('Customer')::uuid, false, current_timestamp(0), 'core/ar/form/collection&formName=customer/CustomerCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArMasters'), sys.sp_get_menu_key('mnuArMasters'), 'mnuIncomeType', 'Income Type', 2, md5('IncomeType')::uuid, false, current_timestamp(0), 'core/ar/form/collection&formName=incomeType/IncomeTypeCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArMasters'), sys.sp_get_menu_key('mnuArMasters'), 'mnuCustomerOPBLRef', 'Customer OPBL Ref', 2, md5('CustomerOPBLRef')::uuid, false, current_timestamp(0), 'core/ar/form/collection&formName=customerOPBLRef/CustomerOPBLRefCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArMasters'), sys.sp_get_menu_key('mnuArMasters'), 'mnuArSalesman', 'Salesman', 2, md5('Salesman')::uuid, false, current_timestamp, 'core/ar/form/collection&formName=salesman/SalesmanCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArMasters'), sys.sp_get_menu_key('mnuArMasters'), 'mnuArSegment', 'Segment', 2, md5('Segment')::uuid, false, current_timestamp, 'core/ar/form/collection&formName=segment/SegmentCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArMasters'), sys.sp_get_menu_key('mnuArMasters'), 'mnuArPayTerm', 'Pay Term', 2, md5('CustPayTerm')::uuid, false, current_timestamp(0), 'core/ar/form/collection&formName=custPayTerm/CustPayTermCollectionView';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAccountsReceivable'), sys.sp_get_menu_key('mnuAccountsReceivable'), 'mnuArUtils', 'Utilities', 0, null, false, current_timestamp(0), ''; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArUtils'), sys.sp_get_menu_key('mnuArUtils'), 'mnuInvoiceDispatched', 'Invoice Dispatched', 4, null, false, current_timestamp, 'core/ar/invoice-dispatched'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArUtils'), sys.sp_get_menu_key('mnuArUtils'), 'mnuTDSReco', 'TDS Reconcilation', 4, null, false, current_timestamp(0), 'core/ar/tds-reco'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArUtils'), sys.sp_get_menu_key('mnuArUtils'), 'mnuCustGstinUpdate', 'Customer GST Update', 4, null, false, current_timestamp(0), 'core/ar/cust-gstin-update'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuArUtils'), sys.sp_get_menu_key('mnuArUtils'), 'mnuCustInfoUpdate', 'Update Customer Info', 4, null, false, current_timestamp, 'core/ar/cust-info-update'; 

?==?
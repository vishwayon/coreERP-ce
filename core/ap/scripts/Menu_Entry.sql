--  *************** Top Level Menu ***************
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, menu_code)
Select (select max(menu_id) + 1 from sys.menu), -1, sys.sp_get_menu_key(''), 'mnuAccountsPayable', 'Accounts Payable', 0, null, false, current_timestamp(0), '', 'ap';

?==?
--  *************** First Level Menu for Documents ***************
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAccountsPayable'), sys.sp_get_menu_key('mnuAccountsPayable'), 'mnuApDocuments', 'Documents', 0, null, false, current_timestamp(0), '';

?==?
--  *************** Documents ***************
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApDocuments'), sys.sp_get_menu_key('mnuApDocuments'), 'mnuBill', 'Bill', 1, md5('Bill')::uuid, true, current_timestamp(0), 'core/ap/form/collection&formName=bill/BillCollectionView', '{BL}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApDocuments'), sys.sp_get_menu_key('mnuApDocuments'), 'mnuGstBill', 'GST Bill', 1, md5('GstBill')::uuid, false, current_timestamp(0), 'core/ap/form/collection&formName=gstBill/GstBillCollectionView', '{BL2}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApDocuments'), sys.sp_get_menu_key('mnuApDocuments'), 'mnuAdvanceSupplierPayment', 'Advance Supplier Payment', 1, md5('AdvanceSupplierPayment')::uuid, false, current_timestamp(0), 'core/ap/form/collection&formName=advanceSupplierPayment/AdvanceSupplierPaymentCollectionView', '{ASP}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, count_class)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApDocuments'), sys.sp_get_menu_key('mnuApDocuments'), 'mnuAdvForPO', 'Advance Requested', 4, null, false, current_timestamp(0), 
        'javascript:coreWebApp.rendercontents(''?r=/core/ap/form/wizard&formName=advanceSupplierPayment/AdvanceSupplierPaymentWizard&step=SelectPO'', ''details'', ''contentholder'')', '\app\core\ap\advanceSupplierPayment\AdvSuppHelper'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApDocuments'), sys.sp_get_menu_key('mnuApDocuments'), 'mnuSupplierPayment', 'Supplier Payment', 1, md5('SupplierPayment')::uuid, false, current_timestamp(0), 'core/ap/form/collection&formName=supplierPayment/SupplierPaymentCollectionView', '{PYMT}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApDocuments'), sys.sp_get_menu_key('mnuApDocuments'), 'mnuMultiSuppPayment', 'Multi Supplier Payment', 1, md5('MultiSuppPayment')::uuid, false, current_timestamp(0), 'core/ap/form/collection&formName=multiSuppPayment/MultiSuppPaymentCollectionView', '{MCP}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApDocuments'), sys.sp_get_menu_key('mnuApDocuments'), 'mnuSupplierPaymentIB', 'Supplier Payment - IB', 4, null, false, current_timestamp(0), 'javascript:coreWebApp.rendercontents(''?r=/core/ap/form/wizard&formName=supplierPayment/SupplierPaymentIBWizard&step=SelectSupplierIB'', ''details'', ''contentholder'')'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApDocuments'), sys.sp_get_menu_key('mnuApDocuments'), 'mnuSupplierReceipt', 'Supplier Receipt', 1, md5('SupplierReceipt')::uuid, false, current_timestamp(0), 'core/ap/form/collection&formName=supplierReceipt/SupplierReceiptCollectionView', '{SREC}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApDocuments'), sys.sp_get_menu_key('mnuApDocuments'), 'mnuSuppBalTransfer', 'Supplier Balance Transfer', 1, md5('SuppBalTransfer')::uuid, false, current_timestamp(0), 'core/ap/form/collection&formName=suppBalTransfer/SuppBalTransferCollectionView', '{SBT}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApDocuments'), sys.sp_get_menu_key('mnuApDocuments'), 'mnuCreditNote', 'Credit Note', 1, md5('CreditNote')::uuid, false, current_timestamp(0), 'core/ap/form/collection&formName=creditNote/CreditNoteCollectionView', '{CN}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApDocuments'), sys.sp_get_menu_key('mnuApDocuments'), 'mnuSuppManualSet', 'Supplier Manual Settlement', 2, md5('SuppManualSet')::uuid, false, current_timestamp(0), 'javascript:coreWebApp.rendercontents(''?r=/core/ap/form/wizard&formName=suppManualSet/SuppManualSetWizard&step=SelectSupplier'', ''details'', ''contentholder'')'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApDocuments'), sys.sp_get_menu_key('mnuApDocuments'), 'mnuGstDebitNote', 'GST Purchase Return/Dr/Cr Note', 1, md5('GstDebitNote')::uuid, false, current_timestamp(0), 'core/ap/form/collection&formName=gstDebitNote/GstDebitNoteCollectionView', '{DN2}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApDocuments'), sys.sp_get_menu_key('mnuApDocuments'), 'mnuBankTransfer', 'Bank Transfer', 1, md5('BankTransfer')::uuid, false, current_timestamp(0), 'core/ap/form/collection&formName=bankTransfer/BankTransferCollectionView', '{BT}';

?==?
-- ******************** First Level Menu for Reports ***********************
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAccountsPayable'), sys.sp_get_menu_key('mnuAccountsPayable'), 'mnuApReports', 'Reports', 0, null, false, current_timestamp(0), '';

?==?
-- ******************** Reports ***********************
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApReports'), sys.sp_get_menu_key('mnuApReports'), 'mnuStatementOfAccounts', 'Statement of Accounts', 3, md5('StmtOfAcBillsPayable')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ap/reports/stmtOfAccounts/StmtOfAcBillsPayable';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApReports'), sys.sp_get_menu_key('mnuApReports'), 'mnuPayableLedger', 'Payable Ledger', 3, md5('PayableLedger')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ap/reports/payableLedger/PayableLedger';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApReports'), sys.sp_get_menu_key('mnuApReports'), 'mnuApTrialBalance', 'Payable Balance', 3,  md5('ApTrialBalance')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ap/reports/trialBalance/TrialBalance';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApReports'), sys.sp_get_menu_key('mnuApReports'), 'mnuSupplierOverdue', 'Supplier Overdue', 3,  md5('SupplierOverdue')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ap/reports/supplierOverdue/SupplierOverdue';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApReports'), sys.sp_get_menu_key('mnuApReports'), 'mnuApPurchaseRegister', 'Purchase Register', 3,  md5('ApPurchaseRegister')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ap/reports/purchaseRegister/PurchaseRegister';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApReports'), sys.sp_get_menu_key('mnuApReports'), 'mnuPLTransStatement', 'PL Transaction Statement', 3, md5('PLTransStatement')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ap/reports/pLTransStatement/PLTransStatement';

?==?
--  *************** First Level Menu Masters ***************
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAccountsPayable'), sys.sp_get_menu_key('mnuAccountsPayable'), 'mnuApMasters', 'Masters', 0, null, false, current_timestamp(0), ''; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApMasters'), sys.sp_get_menu_key('mnuApMasters'), 'mnuSuppType', 'Supplier Type', 2, md5('SuppType')::uuid, false, current_timestamp(0), 'core/ap/form/collection&formName=supplierType/SupplierTypeCollectionView'; 

?==?
--  *************** Masters ***************
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApMasters'), sys.sp_get_menu_key('mnuApMasters'), 'mnuSupplier', 'Supplier', 2, md5('Supplier')::uuid, false, current_timestamp(0), 'core/ap/form/collection&formName=supplier/SupplierCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApMasters'), sys.sp_get_menu_key('mnuApMasters'), 'mnuSupplierOPBLRef', 'Supplier OPBL Ref', 2, md5('SupplierOPBLRef')::uuid, false, current_timestamp(0), 'core/ap/form/collection&formName=supplierOPBLRef/SupplierOPBLRefCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApMasters'), sys.sp_get_menu_key('mnuApMasters'), 'mnuApPayTerm', 'Pay Term', 2, md5('SuppPayTerm')::uuid, false, current_timestamp(0), 'core/ap/form/collection&formName=suppPayTerm/SuppPayTermCollectionView';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApMasters'), sys.sp_get_menu_key('mnuApMasters'), 'mnuSuppToCust', 'Extend Supplier To Customer', 2, md5('SuppToCust')::uuid, false, current_timestamp(0), 'core/ap/form/collection&formName=suppToCust/SuppToCustCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApMasters'), sys.sp_get_menu_key('mnuApMasters'), 'mnuAdvType', 'Advance Type', 2, md5('AdvanceType')::uuid, false, current_timestamp(0), 'core/ap/form/collection&formName=advanceType/AdvanceTypeCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApMasters'), sys.sp_get_menu_key('mnuApMasters'), 'mnupaycycle', 'Pay Cycle', 2, md5('PayCycle')::uuid, false, current_timestamp(0), 'core/ap/form/collection&formName=payCycle/PayCycleCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuApMasters'), sys.sp_get_menu_key('mnuApMasters'), 'mnuSuppCust', 'Supplier To Customer', 2, md5('SuppCust')::uuid, false, current_timestamp(0), 'core/ap/form/collection&formName=suppCust/SuppCustCollectionView'; 

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAccountsPayable'), sys.sp_get_menu_key('mnuAccountsPayable'), 'mnuAccountsPayableUtils', 'Utilities', 0, null, false, current_timestamp, '';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAccountsPayableUtils'), sys.sp_get_menu_key('mnuAccountsPayableUtils'), 'mnuBillUpdate', 'Update Bill No', 4, null, false, current_timestamp, 'core/ap/bill-no-edit'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAccountsPayableUtils'), sys.sp_get_menu_key('mnuAccountsPayableUtils'), 'mnuSuppGstinUpdate', 'Supplier GST Update', 4, null, false, current_timestamp, 'core/ap/supp-gstin-update'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAccountsPayableUtils'), sys.sp_get_menu_key('mnuAccountsPayableUtils'), 'mnuBlockSuppPymt', 'Block Supplier Payment', 4, null, false, current_timestamp, 'core/ap/block-supp-pymt'; 

?==?
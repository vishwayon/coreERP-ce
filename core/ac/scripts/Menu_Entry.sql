-- Top Level Menu
Insert Into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, menu_code)
Select (select COALESCE(max(menu_id), 0) + 1 from sys.menu), -1, sys.sp_get_menu_key(''), 'mnuFinancialAccounting', 'Financial Accounting', 0, null, false, current_timestamp(0), '', 'ac';
?==?

-- ******************** First Level Menu for Documents ***********************
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuFinancialAccounting'), sys.sp_get_menu_key('mnuFinancialAccounting'), 'mnuAcDocuments', 'Documents', 0, null, false, current_timestamp(0), '';

?==?
-- ******************** Documents ***********************
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcDocuments'), sys.sp_get_menu_key('mnuAcDocuments'), 'mnuBankPayment', 'Bank Payment', 1, md5('BankPayment')::uuid, false, current_timestamp(0), 'core/ac/form/collection&formName=bankPayment/BankPaymentCollectionView', '{BPV}';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcDocuments'), sys.sp_get_menu_key('mnuAcDocuments'), 'mnuBankReceipt', 'Bank Receipt', 1, md5('BankReceipt')::uuid, false, current_timestamp(0), 'core/ac/form/collection&formName=bankReceipt/BankReceiptCollectionView', '{BRV}';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcDocuments'), sys.sp_get_menu_key('mnuAcDocuments'), 'mnuCashPayment', 'Cash Payment', 1, md5('CashPayment')::uuid, false, current_timestamp(0), 'core/ac/form/collection&formName=cashPayment/CashPaymentCollectionView', '{CPV}';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcDocuments'), sys.sp_get_menu_key('mnuAcDocuments'), 'mnuGstPymt', 'GST Payment Voucher', 1, md5('GstPymt')::uuid, false, current_timestamp(0), 'core/ac/form/collection&formName=gstPymt/GstPymtCollectionView', '{PAYV}';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcDocuments'), sys.sp_get_menu_key('mnuAcDocuments'), 'mnuGstSi', 'GST Self Invoice', 1, md5('GstSi')::uuid, false, current_timestamp(0), 'core/ac/form/collection&formName=gstSi/GstSiCollectionView', '{SIRC}';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcDocuments'), sys.sp_get_menu_key('mnuAcDocuments'), 'mnuGstCashPymt', 'GST Cash Payment', 1, md5('GstCashPymt')::uuid, false, current_timestamp(0), 'core/ac/form/collection&formName=gstCashPymt/GstCashPymtCollectionView', '{PAYC}';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcDocuments'), sys.sp_get_menu_key('mnuAcDocuments'), 'mnuGstBankPymt', 'GST Bank Payment', 1, md5('GstBankPymt')::uuid, false, current_timestamp(0), 'core/ac/form/collection&formName=gstBankPymt/GstBankPymtCollectionView', '{PAYB}';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcDocuments'), sys.sp_get_menu_key('mnuAcDocuments'), 'mnuCashReceipt', 'Cash Receipt', 1, md5('CashReceipt')::uuid, false, current_timestamp(0), 'core/ac/form/collection&formName=cashReceipt/CashReceiptCollectionView', '{CRV}';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcDocuments'), sys.sp_get_menu_key('mnuAcDocuments'), 'mnuContraVoucher', 'Contra Voucher', 1, md5('ContraVoucher')::uuid, false, current_timestamp(0), 'core/ac/form/collection&formName=contraVoucher/ContraVoucherCollectionView', '{CRV}';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcDocuments'), sys.sp_get_menu_key('mnuAcDocuments'), 'mnuJournalVoucher', 'Journal Voucher',  1, md5('JournalVoucher')::uuid, false, current_timestamp(0), 'core/ac/form/collection&formName=journalVoucher/JournalVoucherCollectionView', '{JV}';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcDocuments'), sys.sp_get_menu_key('mnuAcDocuments'), 'mnuMCJ', 'Monthly Closing Journal',  1, md5('MCJ')::uuid, false, current_timestamp(0), 'core/ac/form/collection&formName=mcj/MCJCollectionView', '{MCJ}';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcDocuments'), sys.sp_get_menu_key('mnuAcDocuments'), 'mnuSaj', 'Sub Head Adjustment Journal',  1, md5('Saj')::uuid, false, current_timestamp(0), 'core/ac/form/collection&formName=saj/SajCollectionView', '{SAJ}';

?==?
-- ******************** First Level Menu for Reports ***********************
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuFinancialAccounting'), sys.sp_get_menu_key('mnuFinancialAccounting'), 'mnuAcReports', 'Reports', 0, null, false, current_timestamp(0), '';

?==?
-- ******************** Reports ***********************
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcReports'), sys.sp_get_menu_key('mnuAcReports'), 'mnuGeneralLedger', 'Ledgers', 3,  md5('GeneralLedger')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ac/reports/generalLedger/GeneralLedger';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcReports'), sys.sp_get_menu_key('mnuAcReports'), 'mnuGeneralLedgerRestricted', 'Ledgers (Restricted)', 3,  md5('GeneralLedgerRestricted')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ac/reports/generalLedgerRestricted/GeneralLedgerRestricted';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcReports'), sys.sp_get_menu_key('mnuAcReports'), 'mnuTrialBalance', 'Trial Balance', 3,  md5('TrialBalance')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ac/reports/trialBalance/TrialBalance';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcReports'), sys.sp_get_menu_key('mnuAcReports'), 'mnuBankRecoReport', 'Bank Reconciliation', 3,  md5('BankRecoReport')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ac/reports/bankReco/BankRecoReport';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcReports'), sys.sp_get_menu_key('mnuAcReports'), 'mnuDaybook', 'Daybook', 3,  md5('Daybook')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ac/reports/daybook/Daybook';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcReports'), sys.sp_get_menu_key('mnuAcReports'), 'mnuBalanceSheet', 'Balance Sheet', 3, md5('BalanceSheet')::uuid, false, current_timestamp(0), 'cwf/fwShell/twig-report/viewer&xmlPath=@app/core/ac/reports/balanceSheet/BalanceSheet';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcReports'), sys.sp_get_menu_key('mnuAcReports'), 'mnuTaxPayable', 'Tax Payable', 3,  md5('TaxPayable')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ac/reports/taxPayable/TaxPayable';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcReports'), sys.sp_get_menu_key('mnuAcReports'), 'mnuTaxCredit', 'Tax Credit', 3,  md5('TaxCredit')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ac/reports/taxCredit/TaxCredit';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcReports'), sys.sp_get_menu_key('mnuAcReports'), 'mnuRefLedger', 'Reference Ledger', 3,  md5('RefLedger')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ac/reports/refLedger/RefLedger';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcReports'), sys.sp_get_menu_key('mnuAcReports'), 'mnuSubHeadLedger', 'Sub Head Ledger', 3,  md5('SubHeadLedger')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ac/reports/subHeadLedger/SubHeadLedger';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcReports'), sys.sp_get_menu_key('mnuAcReports'), 'mnuTransStatement', 'Transaction Statement', 3,  md5('TransStatement')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/ac/reports/transStatement/TransStatement';

?==?
-- ******************** First Level Menu for Utilities ***********************
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuFinancialAccounting'), sys.sp_get_menu_key('mnuFinancialAccounting'), 'mnuAcUtils', 'Utilities', 0, null, false, current_timestamp(0), '';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcUtils'), sys.sp_get_menu_key('mnuAcUtils'), 'mnuBankReco', 'Bank Reconcilation', 4, null, false, current_timestamp(0), 'core/ac/bank-reco'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcUtils'), sys.sp_get_menu_key('mnuAcUtils'), 'mnuGlReco', 'GL Reconcilation', 4, null, false, current_timestamp(0), 'core/ac/gl-reco'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcUtils'), sys.sp_get_menu_key('mnuAcUtils'), 'mnuImportBalance', 'Import Balance', 4, null, false, current_timestamp(0), 'cwf/fwShell/main/importbalance'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcUtils'), sys.sp_get_menu_key('mnuAcUtils'), 'mnuPymtReversal', 'Payment Reversal', 4, null, false, current_timestamp(0), 'core/ac/pymt-reversal'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcUtils'), sys.sp_get_menu_key('mnuAcUtils'), 'mnuRcptReversal', 'Receipt Reversal', 4, null, false, current_timestamp(0), 'core/ac/rcpt-reversal'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcUtils'), sys.sp_get_menu_key('mnuAcUtils'), 'mnuPdc', 'PDC', 3, null, false, current_timestamp(0), 'core/ac/form/pdc'; 

?==?
-- ******************** First Level Menu for Masters ***********************
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuFinancialAccounting'), sys.sp_get_menu_key('mnuFinancialAccounting'), 'mnuAcMasters', 'Masters', 0, null, false, current_timestamp(0), '';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
SELECT (SELECT max(menu_id) + 1 FROM sys.menu), sys.fn_get_menu_id_by_name('mnuAcMasters'), sys.sp_get_menu_key('mnuAcMasters'), 'mnuAccountGroup', 'Account Group', 2, md5('AccountGroup')::uuid, false, current_timestamp(0), 'core/ac/form/tree&formName=accountGroup/AccountGroupTreeView';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcMasters'), sys.sp_get_menu_key('mnuAcMasters'), 'mnuChartOfAccounts', 'Chart Of Accounts', 2, md5('AccountHead')::uuid, false, current_timestamp(0), 'core/ac/form/tree&formName=chartOfAccounts/chartOfAccountsTree';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcMasters'), sys.sp_get_menu_key('mnuAcMasters'), 'mnuFCType', 'Foreign Currency Type', 2, md5('FCType')::uuid, false, current_timestamp(0), 'core/ac/form/collection&formName=fcType/FCTypeCollectionView';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcMasters'), sys.sp_get_menu_key('mnuAcMasters'), 'mnuSubHead', 'Sub Head', 2, md5('SubHead')::uuid, false, current_timestamp(0), 'core/ac/form/tree&formName=subHead/SubHeadTreeView';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcMasters'), sys.sp_get_menu_key('mnuAcMasters'), 'mnuSubHeadOpbl', 'Sub Head OPBL', 2, md5('SubHeadOpbl')::uuid, false, current_timestamp(0), 'core/ac/form/collection&formName=subHeadOpbl/SubHeadOpblCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcMasters'), sys.sp_get_menu_key('mnuAcMasters'), 'mnuSubHeadDimension', 'Sub Head Dimension', 2, md5('SubHeadDimension')::uuid, false, current_timestamp(0), 'core/ac/form/collection&formName=subHeadDimension/SubHeadDimensionCollectionView';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcMasters'), sys.sp_get_menu_key('mnuAcMasters'), 'mnuIBAccount', 'Inter Branch Account', 2, md5('IBAccount')::uuid, false, current_timestamp(0), 'core/ac/form/collection&formName=iBAccount/IBAccountCollectionView';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuAcMasters'), sys.sp_get_menu_key('mnuAcMasters'), 'mnuCashAccLimit', 'Cash Account Limit', 2, md5('CashAccLimit')::uuid, false, current_timestamp(0), 'core/ac/form/collection&formName=cashAccLimit/CashAccLimitCollectionView';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuDashboard'), sys.sp_get_menu_key('mnuDashboard'), 'mnuAcCustMon', 'Customer Monitor', 3, md5('AcCustomerMonitor')::uuid, false, current_timestamp(0), 'core/ac/custMon/overview';

?==?

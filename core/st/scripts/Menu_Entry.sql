INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, menu_code)
Select (select max(menu_id) + 1 from sys.menu), -1, sys.sp_get_menu_key(''), 'mnuStoresAndInventory', 'Stocks And Inventory', 0, null, false, current_timestamp(0), '', 'st';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStoresAndInventory'), sys.sp_get_menu_key('mnuStoresAndInventory'), 'mnuStMasters', 'Masters', 0, null, false, current_timestamp(0), ''; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStMasters'), sys.sp_get_menu_key('mnuStMasters'), 'mnuUoMSchedule', 'UoM Schedule', 2, md5('UoMSchedule')::uuid,false, current_timestamp(0), 'core/st/form/collection&formName=uoMSchedule/UoMScheduleCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStMasters'), sys.sp_get_menu_key('mnuStMasters'), 'mnuMaterialType', 'Stock Type', 2, md5('MaterialType')::uuid,false, current_timestamp(0), 'core/st/form/collection&formName=materialType/MaterialTypeCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStMasters'), sys.sp_get_menu_key('mnuStMasters'), 'mnuMfgr', 'Manufacturer', 2, md5('Mfg')::uuid,false, current_timestamp(0), 'core/st/form/collection&formName=mfg/MfgCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStMasters'), sys.sp_get_menu_key('mnuStMasters'), 'mnuHsn', 'HS Codes', 2, md5('Hsn')::uuid,false, current_timestamp(0), 'core/st/form/collection&formName=hsn/HsnCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStMasters'), sys.sp_get_menu_key('mnuStMasters'), 'mnuMaterial', 'Stock Item', 2, md5('Material')::uuid,false, current_timestamp(0), 'core/st/form/collection&formName=material/MaterialCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStMasters'), sys.sp_get_menu_key('mnuStMasters'), 'mnuService', 'Service Item', 2, md5('Service')::uuid,false, current_timestamp(0), 'core/st/form/collection&formName=service/ServiceCollectionView'; 


?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStMasters'), sys.sp_get_menu_key('mnuStMasters'), 'mnuStockLocation', 'Stock Location', 2, md5('StockLocation')::uuid,false, current_timestamp(0), 'core/st/form/collection&formName=stockLocation/StockLocationCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStMasters'), sys.sp_get_menu_key('mnuStMasters'), 'mnuLcType', 'Landed Cost Type', 2, md5('LcType')::uuid,false, current_timestamp(0), 'core/st/form/collection&formName=lcType/LcTypeCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStMasters'), sys.sp_get_menu_key('mnuStMasters'), 'mnuMatCat', 'Stock Item Categories', 2, md5('MatCat')::uuid,false, current_timestamp(0), 'core/st/form/collection&formName=matCat/MatCatCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStMasters'), sys.sp_get_menu_key('mnuStMasters'), 'mnuConsType', 'Consumption Type', 2, md5('ConsType')::uuid,false, current_timestamp(0), 'core/st/form/collection&formName=consType/ConsTypeCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStMasters'), sys.sp_get_menu_key('mnuStMasters'), 'mnuReorderlevel', 'Reorder Level', 2, md5('ReorderLevel')::uuid,false, current_timestamp(0), 'core/st/form/collection&formName=reorderLevel/ReorderLevelCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStMasters'), sys.sp_get_menu_key('mnuStMasters'), 'mnuSrr', 'Sales Return Reason', 2, md5('Srr')::uuid,false, current_timestamp(0), 'core/st/form/collection&formName=srr/SrrCollectionView'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStoresAndInventory'), sys.sp_get_menu_key('mnuStoresAndInventory'), 'mnuStDocuments', 'Documents', 0, null, false, current_timestamp(0), '';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, menu_code, is_staged, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStDocuments'), sys.sp_get_menu_key('mnuStDocuments'), 'mnuStockGstPurchase', 'GST Stock Purchase', 1, md5('StockGstPurchase')::uuid, false, current_timestamp(0), 'core/st/form/collection&formName=stockGstPurchase/StockGstPurchaseCollectionView', '', true, '{SPG}';


?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStDocuments'), sys.sp_get_menu_key('mnuStDocuments'), 'mnuStockConsumption', 'Stock Consumption', 1, md5('StockConsumption')::uuid, false, current_timestamp(0), 'core/st/form/collection&formName=stockConsumption/StockConsumptionCollectionView', '{SC}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStDocuments'), sys.sp_get_menu_key('mnuStDocuments'), 'mnuJobWorkReceipt', 'Job Work Receipt', 1, md5('JobWorkReceipt')::uuid, false, current_timestamp(0), 'core/st/form/collection&formName=jobWorkReceipt/JobWorkReceiptCollectionView', '{JWR}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStDocuments'), sys.sp_get_menu_key('mnuStDocuments'), 'mnuStockTransfer', 'Stock Transfer', 1, md5('StockTransfer')::uuid, false, current_timestamp(0), 'core/st/form/collection&formName=stockTransfer/StockTransferCollectionView', '{ST}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStDocuments'), sys.sp_get_menu_key('mnuStDocuments'), 'mnuStockAdjustmentNote', 'Stock Adjustment Note', 1, md5('StockAdjustmentNote')::uuid, false, current_timestamp(0), 'core/st/form/collection&formName=stockAdjustmentNote/StockAdjustmentNoteCollectionView', '{SAN}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStDocuments'), sys.sp_get_menu_key('mnuStDocuments'), 'mnuPurchaseReturn', 'Purchase Return', 1, md5('PurchaseReturn')::uuid, false, current_timestamp(0), 'core/st/form/collection&formName=purchaseReturn/PurchaseReturnCollectionView', '{PR}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, menu_code, is_staged, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStDocuments'), sys.sp_get_menu_key('mnuStDocuments'), 'mnuStockGstInvoice', 'Gst Stock Invoice', 1, md5('StockGstInvoice')::uuid, false, current_timestamp(0), 'core/st/form/collection&formName=stockGstInvoice/StockGstInvoiceCollectionView', '', true, '{SIV}';


?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStDocuments'), sys.sp_get_menu_key('mnuStDocuments'), 'mnuSalesReturn', 'Sales Return', 1, md5('SalesReturn')::uuid, false, current_timestamp(0), 'core/st/form/collection&formName=salesReturn/SalesReturnCollectionView', '{SR}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStDocuments'), sys.sp_get_menu_key('mnuStDocuments'), 'mnuSaleReturnGst', 'GST Sale Return/Dr/Cr Note', 1, md5('SaleReturnGst')::uuid, false, current_timestamp(0), 'core/st/form/collection&formName=saleReturnGst/SaleReturnGstCollectionView', '{SRV}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStDocuments'), sys.sp_get_menu_key('mnuStDocuments'), 'mnuLocationTransferNote', 'Location Transfer Note', 1, md5('LocationTransferNote')::uuid, false, current_timestamp(0), 'core/st/form/collection&formName=locationTransferNote/LocationTransferNoteCollectionView', '{LTN}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStDocuments'), sys.sp_get_menu_key('mnuStDocuments'), 'mnuPurchaseReturnNote', 'Purchase Return Note', 1, md5('PurchaseReturnNote')::uuid, false, current_timestamp(0), 'core/st/form/collection&formName=prn/PRNCollectionView', '{PRN}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStDocuments'), sys.sp_get_menu_key('mnuStDocuments'), 'mnuSalesReturnNote', 'Sales Return Note', 1, md5('SalesReturnNote')::uuid, false, current_timestamp(0), 'core/st/form/collection&formName=srn/SRNCollectionView', '{SRN}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStDocuments'), sys.sp_get_menu_key('mnuStDocuments'), 'mnuPurchaseReturnGst', 'GST Purchase Return/Dr/Cr Note', 1, md5('PurchaseReturnGst')::uuid, false, current_timestamp(0), 'core/st/form/collection&formName=purchaseReturnGst/PurchaseReturnGstCollectionView', '{PRV}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, count_class)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStDocuments'), sys.sp_get_menu_key('mnuStDocuments'), 'mnuSpgForPrv', 'SPG for PRV', 4, null, false, current_timestamp(0), 
	'javascript:coreWebApp.rendercontents(''?r=/core/st/form/wizard&formName=purchaseReturnGst/SpgForPrvWizard&step=SelectStockPurchase'', ''details'', ''contentholder'');', '\app\core\st\purchaseReturnGst\PurchaseReturnGstHelper'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStDocuments'), sys.sp_get_menu_key('mnuStDocuments'), 'mnuMatConversionNote', 'Material Conversion Note', 1, md5('MatConversionNote')::uuid, false, current_timestamp(0), 'core/st/form/collection&formName=matConversionNote/MatConversionNoteCollectionView', '{MCN}';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, is_staged, vch_type)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStDocuments'), sys.sp_get_menu_key('mnuStDocuments'), 'mnuMatRetGatePass', 'Returnable Gate Pass', 1, md5('MatRetGatePass')::uuid, false, current_timestamp(0), 'core/st/form/collection&formName=mrgp/MrgpCollectionView', true, '{RGP}'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path, is_staged, vch_type)
SELECT (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStDocuments'), sys.sp_get_menu_key('mnuStDocuments'), 'mnuMrtn', 'Material Return Note', 1, md5('Mrtn')::uuid, false, current_timestamp(0), 'core/st/form/collection&formName=mrtn/MrtnCollectionView', true, '{LTN}';

?==?
-- ******************** First Level Menu for Utilities ***********************
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStoresAndInventory'), sys.sp_get_menu_key('mnuStoresAndInventory'), 'mnuStUtils', 'Utilities', 0, null, false, current_timestamp(0), '';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStUtils'), sys.sp_get_menu_key('mnuStUtils'), 'mnuSTParkPost', 'Stock Transfer/Receipt', 4, null, false, current_timestamp(0), 'core/st/stock-transfer-park-post'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key,menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStUtils'), sys.sp_get_menu_key('mnuStUtils'), 'mnuSaleRateUpdate', 'Sale Rate Update', 4, null, false, current_timestamp, 'core/st/sale-rate-update';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStUtils'), sys.sp_get_menu_key('mnuStUtils'), 'mnuBalUpdateUtil', 'Stock Balance Update', 4, null, false, current_timestamp, 'core/st/bal-update-util'; 

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStUtils'), sys.sp_get_menu_key('mnuStUtils'), 'mnuStMatValMon', 'Stock Value Monitor', 4, null, false, current_timestamp, 'core/st/mat-val-mon'; 

-- ******************** First Level Menu for Reports *************************
?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStoresAndInventory'), sys.sp_get_menu_key('mnuStoresAndInventory'), 'mnuStReports', 'Reports', 0, null, false, current_timestamp(0), '';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStReports'), sys.sp_get_menu_key('mnuStReports'), 'mnuStockLedger', 'Stock Ledger', 3,  md5('StockLedger')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/st/reports/stockLedger/StockLedger';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStReports'), sys.sp_get_menu_key('mnuStReports'), 'mnuPurchaseRegister', 'Purchase Register', 3,  md5('PurchaseRegister')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/st/reports/purchaseRegister/PurchaseRegister';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStReports'), sys.sp_get_menu_key('mnuStReports'), 'mnuSalesRegister', 'Sales Register', 3,  md5('SalesRegister')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/st/reports/salesRegister/SalesRegister';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStReports'), sys.sp_get_menu_key('mnuStReports'), 'mnuSalesAnalysis', 'Sales Analysis', 3,  md5('SalesAnalysis')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/st/reports/salesAnalysis/SalesAnalysis';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStReports'), sys.sp_get_menu_key('mnuStReports'), 'mnuPurchaseAnalysis', 'Purchase Analysis', 3,  md5('PurchaseAnalysis')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/st/reports/purchaseAnalysis/PurchaseAnalysis';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStReports'), sys.sp_get_menu_key('mnuStReports'), 'mnuStockBalance', 'Stock Balance', 3,  md5('StockBalance')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/st/reports/stockBalance/StockBalance';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStReports'), sys.sp_get_menu_key('mnuStReports'), 'mnuStBusinessTurnover', 'Business Turnover', 3, md5('StBusinessTurnover')::uuid, false, current_timestamp, 'cwf/fwShell/jreport/viewer&xmlPath=../core/st/reports/businessTurnover/BusinessTurnover';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStReports'), sys.sp_get_menu_key('mnuStReports'), 'mnuStockConsumptionAnalysis', 'Consumption Analysis', 3, md5('StockConsumptionAnalysis')::uuid, false, current_timestamp(0), 'core/st/twig-report/viewer&xmlPath=@app/core/st/reports/cogc/StockConsumptionAnalysis';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStReports'), sys.sp_get_menu_key('mnuStReports'), 'mnuInvProfitability', 'Invoice Profitability', 3, md5('InvProfitability')::uuid, false, current_timestamp(0), 'core/st/twig-report/viewer&xmlPath=@app/core/st/reports/invProfitability/InvProfitability';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStReports'), sys.sp_get_menu_key('mnuStReports'), 'mnuProductProfitability', 'Product Profitability', 3, md5('ProductProfitability')::uuid, false, current_timestamp(0), 'core/st/twig-report/viewer&xmlPath=@app/core/st/reports/productProfitability/ProductProfitability';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStReports'), sys.sp_get_menu_key('mnuStReports'), 'mnuPurchaseVsSales', 'Purchase Vs Sales', 3,  md5('PurchaseVsSales')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/st/reports/purchaseVsSales/PurchaseVsSales';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStReports'), sys.sp_get_menu_key('mnuStReports'), 'mnuStSalesReturnList', 'Sales Return List', 3, md5('SalesReturnList')::uuid, false, current_timestamp, 'cwf/fwShell/jreport/viewer&xmlPath=../core/st/reports/salesReturnList/SalesReturnList';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStReports'), sys.sp_get_menu_key('mnuStReports'), 'mnuStockTransferList', 'Stock Transfer List', 3, md5('StockTransferList')::uuid, false, current_timestamp, 'cwf/fwShell/jreport/viewer&xmlPath=../core/st/reports/stockTransferList/StockTransferList';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStReports'), sys.sp_get_menu_key('mnuStReports'), 'mnuStockInTransit', 'Stock In Transit', 3, md5('StockInTransit')::uuid, false, current_timestamp, 'cwf/fwShell/jreport/viewer&xmlPath=../core/st/reports/stockInTransit/StockInTransit';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStReports'), sys.sp_get_menu_key('mnuStReports'), 'mnuStockConsumpByType', 'Stock Consumption By Issue Type', 3, md5('StockConsumpByType')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/st/reports/stockConsumpByType/StockConsumpByType';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStReports'), sys.sp_get_menu_key('mnuStReports'), 'mnuStockMoveType', 'Stock Movement Analysis', 3, md5('StockMoveType')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/st/reports/stockMoveType/StockMoveType';

?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStReports'), sys.sp_get_menu_key('mnuStReports'), 'mnuMrgp', 'Material Return GatePass ', 3, md5('Mrgp')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/st/reports/mrgp/Mrgp';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key,menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStUtils'), sys.sp_get_menu_key('mnuStUtils'), 'mnuWarrantyInfo', 'Warranty Info', 4, null, false, current_timestamp, 'core/st/warranty-info';


-- ******************** First Level Menu for Analysis *************************
?==?
insert into sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStoresAndInventory'), sys.sp_get_menu_key('mnuStoresAndInventory'), 'mnuStAnalysis', 'Analysis', 0, null, false, current_timestamp(0), '';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStAnalysis'), sys.sp_get_menu_key('mnuStAnalysis'), 'mnustockAvail', 'Stock Availability', 3,  md5('StockAvail')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/st/reports/stockAnalysis/stockAvail/StockAvail';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStAnalysis'), sys.sp_get_menu_key('mnuStAnalysis'), 'mnuStockReorder', 'Stock Reorder', 3,  md5('StockReorder')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/st/reports/stockAnalysis/stockReorder/StockReorder';

?==?
INSERT INTO sys.menu(menu_id, parent_menu_id, menu_key, menu_name, menu_text, menu_type, bo_id, is_hidden, last_updated, link_path)
Select (select max(menu_id) + 1 from sys.menu), sys.fn_get_menu_id_by_name('mnuStAnalysis'), sys.sp_get_menu_key('mnuStAnalysis'), 'mnuLatPurPrice', 'Latest Purchase Price', 3, md5('LatPurPrice')::uuid, false, current_timestamp(0), 'cwf/fwShell/jreport/viewer&xmlPath=../core/st/reports/stockAnalysis/latPurPrice/LatPurPrice'; 

?==?


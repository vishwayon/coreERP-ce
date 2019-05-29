CREATE OR REPLACE FUNCTION fa.fn_asset_item_ledger_for_post(IN ptable_name  Varchar(150), IN pvoucher_id  Varchar(50), pen_asset_tran_type smallint=0)
  RETURNS TABLE(asset_item_id bigint, asset_book_id bigint, asset_tran_amt numeric(18,4)) AS
$BODY$ 
BEGIN
	DROP TABLE IF EXISTS asset_item_temp;
	create temp TABLE  asset_item_temp
	(	
		asset_item_id BigInt,
		asset_book_id BigInt,
		asset_tran_amt Numeric(18,4)
	)
	on commit drop;
	--	**********************************
	--	This Function is called by the Stored Proc FixedAsset.spAssetItemLedgerPost
	--	Include your case statement in this function and call the Stored Proc from the trigger for execution
	--	**********************************
	if ptable_name='fa.ap_control' then
		Insert Into asset_item_temp(asset_item_id, asset_book_id, asset_tran_amt)
		Select a.asset_item_id, b.asset_book_id, a.purchase_amt
		From fa.asset_item a
		Inner Join fa.asset_class_book b On a.asset_class_id=b.asset_class_id
		Where a.voucher_id=pvoucher_id;
	End if;
	if ptable_name='fa.as_control' And pen_asset_tran_type = 2 then
		Insert Into asset_item_temp(asset_item_id, asset_book_id, asset_tran_amt)
		Select a.asset_item_id, a.asset_book_id, b.credit_amt
		From fa.as_book_tran a
		Inner Join fa.as_tran b On a.as_id=b.as_id and a.asset_item_id=b.asset_item_id
		Inner Join fa.as_control c On a.as_id=c.as_id
		Where c.as_id=pvoucher_id;
	End if;
	if ptable_name='fa.as_control' And pen_asset_tran_type = 1 then
		Insert Into asset_item_temp(asset_item_id, asset_book_id, asset_tran_amt)
		Select a.asset_item_id, a.asset_book_id, a.profit_loss_amt
		From fa.as_book_tran a
		Inner Join fa.as_tran b On a.as_id=b.as_id and a.asset_item_id=b.asset_item_id
		Inner Join fa.as_control c On a.as_id=c.as_id
		Where c.as_id=pvoucher_id;
	End if;

	return query 
	select a.asset_item_id, a.asset_book_id, a.asset_tran_amt
	from asset_item_temp a;

--RETURN
END;
$BODY$
  LANGUAGE plpgsql;

?==?
create function fa.sp_dep_wdv_op_bal(pcompany_id BigInt, pbranch_id BigInt, passet_book_id bigint, pfinyear varchar(4))
RETURNS TABLE  
(
	asset_item_id bigint,
	dep_amt_op_bal numeric(18,4)
)
AS
$BODY$ 

declare vYearBegin date; vStrLen smallint = 0; vLastIndex smallInt = 0; vIndex SmallInt = 0; vIntLen SmallInt =0 ; vMonth Varchar(4)=''; vIsMonth boolean = false;
	vDelimitCount SmallInt = 0; vActualVoucherID Varchar(50) = ''; vPrefix Varchar(3)=''; vMonthCOde varchar(2) = '';
Begin	
	DROP TABLE IF EXISTS wdv_op_bal;	
	create temp TABLE  wdv_op_bal
	(	
		asset_item_id bigint,
		dep_amt_op_bal numeric(18,4)
	);

	Select year_begin into vYearBegin from sys.finyear
	where company_id=pcomapny_id and finyear_code= pfinyear;

	if vYearBegins is null then
		RAISE EXCEPTION 'Year not found in sys.finyear';
	End If;

	Insert into wdv_op_bal(asset_item_id, dep_amt_op_bal)
	select asset_item_id, coalesce(sum(dep_amt), 0) 
	from fa.asset_dep_ledger
	where branch_id=pbranch_id
		And asset_book_id=passet_book_id
		And dep_date_to < vYearBegin
	group by asset_item_id;

	return query 
	select a.asset_item_id, a.dep_amt_op_bal
	from wdv_op_bal a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION fa.sp_dep_asset_class_book()
  RETURNS TABLE
(
    asset_class_id bigint, 
    asset_class varchar(250), 
    dep_account_id bigint, 
    dep_account varchar(250), 
    acc_dep_account_id bigint, 
    acc_dep_account varchar(250), 
    asset_book_id bigint, 
    en_dep_method smallint, 
    dep_rate numeric(18,4)
) AS
$BODY$ 
Begin	
	DROP TABLE IF EXISTS asset_class_book;	
	create temp TABLE  asset_class_book
	(	
		asset_class_id bigint,
		asset_class varchar(250),
		dep_account_id bigint,
		dep_account varchar(250),
		acc_dep_account_id bigint,
		acc_dep_account varchar(250),
		asset_book_id bigint,
		en_dep_method smallint,
		dep_rate numeric(18,4)
	);
	

	Insert into asset_class_book(asset_class_id, asset_class, dep_account_id, dep_account, acc_dep_account_id, acc_dep_account, asset_book_id, en_dep_method, dep_rate)
	select a.asset_class_id, a.asset_class, a.dep_account_id, c.account_head, a.acc_dep_account_id, d.account_head, b.asset_book_id, b.en_dep_method, b.dep_rate
	from fa.asset_class a
	inner join fa.asset_class_book b on a.asset_class_id=b.asset_class_id
	inner join ac.account_head c on a.dep_account_id=c.account_id
	inner join ac.account_head d on a.acc_dep_account_id=d.account_id;
	

	return query 
	select a.asset_class_id, a.asset_class, a.dep_account_id, a.dep_account, a.acc_dep_account_id, a.acc_dep_account, a.asset_book_id, a.en_dep_method, a.dep_rate
	from asset_class_book a;
END;
$BODY$
  LANGUAGE plpgsql;


?==?
Drop  function IF EXISTS fa.sp_dep_asset_items(pbranch_id bigint, passet_book_id bigint, passet_class_id bigint, passet_item_id bigint, 
                                                 pfrom_date date, pto_date date);

?==?                                                 
create or replace function fa.sp_dep_asset_items(pbranch_id bigint, passet_book_id bigint, passet_class_id bigint, passet_item_id bigint, 
                                                 pfrom_date date, pto_date date, pas_id character varying)
RETURNS TABLE  
(
	branch_id bigint, 
	asset_item_id bigint,	
	asset_name varchar(250),
	asset_class_id bigint,
	asset_class varchar(250),
	asset_book_id bigint,
	asset_book varchar(50),
	use_start_date date,
	purchase_amt numeric(18,4),
	dep_amt numeric(18,4),
	balance_amt numeric(18,4)	
)
AS
$BODY$ 
declare vAssetBook varchar(50)='';
Begin	
	select a.asset_book_desc into vAssetBook 
	from fa.asset_book a
	where a.asset_book_id=passet_book_id ;
	
	DROP TABLE IF EXISTS asset_class_items;	
	create temp TABLE  asset_class_items
	(	
		branch_id bigint, 
		asset_item_id bigint,		
		asset_name varchar(250),
		asset_class_id bigint,
		asset_class varchar(250),
		asset_book_id bigint,
		asset_book varchar(50),
		use_start_date date,
		purchase_amt numeric(18,4),
		dep_amt numeric(18,4),
		balance_amt numeric(18,4)
	);
	

	Insert into asset_class_items(branch_id, asset_item_id, asset_name, asset_class_id, asset_class, asset_book_id, asset_book, use_start_date, purchase_amt, dep_amt, balance_amt)
	select a.branch_id, a.asset_item_id, a.asset_name, a.asset_class_id, c.asset_class, passet_book_id, vAssetBook, a.use_start_date, a.purchase_amt, b.dep_amt, b.balance_amt
	from fa.asset_item a
	inner join (	select a.asset_item_id, a.asset_name, coalesce(sum(a.dep_amt), 0) as dep_amt, coalesce(sum(a.balance_amt), 0) as balance_amt
			From( -- Extract the value of the asset before To Date
				Select a.asset_item_id, b.asset_name, coalesce(sum(a.asset_tran_amt), 0) as balance_amt, 0 as dep_amt
				from fa.asset_item_ledger a
				inner join fa.asset_item b on a.asset_item_id=b.asset_item_id
				where a.en_asset_tran_type = 0
					And b.use_start_date <= pto_date
					And b.branch_id=pbranch_id
					And (b.asset_class_id = passet_class_id or passet_class_id =0)
					And a.asset_book_id=passet_book_id
					And (b.asset_item_id=passet_item_id or passet_item_id=0)
				Group by a.asset_item_id, b.asset_name
				Union All
				Select b.asset_item_id, b.asset_name, -coalesce(sum(a.dep_amt), 0), coalesce(sum(a.dep_amt), 0) as dep_amt
				from fa.asset_dep_ledger a
				inner join fa.asset_item b on a.asset_item_id=b.asset_item_id
				where a.dep_date_from < pfrom_date
					And b.branch_id=pbranch_id
					And (a.asset_class_id = passet_class_id or passet_class_id =0)
					And a.asset_book_id=passet_book_id
					And (b.asset_item_id=passet_item_id or passet_item_id=0)
    				And a.voucher_id != pas_id
				Group by b.asset_item_id, b.asset_name
			) a				
			Group by a.asset_item_id, a.asset_name
		) b on a.asset_item_id=b.asset_item_id
	inner join fa.asset_class c on a.asset_class_id=c.asset_class_id
	where a.branch_id=pbranch_id
		And a.disposed_on is null;

	return query 
	select a.branch_id, a.asset_item_id, a.asset_name, a.asset_class_id, a.asset_class, a.asset_book_id, a.asset_book, a.use_start_date, a.purchase_amt, a.dep_amt, a.balance_amt
	from asset_class_items a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
create or replace function fa.fn_ap_acc_info_for_gl_post(pvoucher_id varchar(50))
RETURNS TABLE  
(	index int4 , 
	company_id bigint,
	branch_id bigint,
	dc char(1),
	account_id bigint,
	debit_amt_fc numeric(18,4),
	credit_amt_fc numeric(18,4),
	debit_amt numeric(18,4),
	credit_amt numeric(18,4),
	remarks varchar(100)
)
AS
$BODY$ 
	Declare vCompany_ID bigint =-1; vBranch_ID bigint = -1; vAccount_ID bigint =-1; vAssetAccount_ID bigint =-1;
	vRoundOffAcc_ID bigint = -1;
Begin	
	-- This function is used by the Posting Trigger to get information on the AP
	DROP TABLE IF EXISTS ap_vch_detail;	
	create temp TABLE  ap_vch_detail
	(	
		index serial, 
		company_id bigint,
		branch_id bigint,
		dc char(1),
		account_id bigint,
		debit_amt_fc numeric(18,4),
		credit_amt_fc numeric(18,4),
		debit_amt numeric(18,4),
		credit_amt numeric(18,4),
		remarks varchar(100)
	);
    
    -- Fetch control values
	Select a.company_id, a.branch_id, a.account_id into vCompany_ID, vBranch_ID, vAccount_ID
	From fa.ap_control a
	where ap_id=pvoucher_id;
    
        -- Step 1: Fetch Account Debits for Fixed Asset Item
        Insert Into ap_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
                                  debit_amt, credit_amt, remarks)
        Select vCompany_ID, vBranch_ID, 'D', a.asset_account_id, 0, 0, 
            sum(b.bt_amt + Case When (c.apply_itc = false And c.is_rc = false) Then c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt Else 0 End), 0, 'Asset Item Amt'
        From fa.asset_class a
        Inner Join fa.ap_tran b on a.asset_class_id=b.asset_class_id
        Inner Join tx.gst_tax_tran c On b.ap_tran_id = c.gst_tax_tran_id
        where b.ap_id=pvoucher_id
        group by a.asset_account_id;

        -- Step 2: Fetch ITC if any
        Insert Into ap_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
                                  debit_amt, credit_amt, remarks)
        Select vCompany_ID, vBranch_ID, 'D', a.account_id, 0, 0, 
            sum(a.tax_amt), 0, 'GST ITC'
        From tx.fn_gtt_itc_info(pvoucher_ID, '', '{-1}'::BigInt[]) a
        group by a.account_id
        Having sum(a.tax_amt) > 0;

        -- Step 3: Fetch Credit to Liability/cash/bank
        Insert Into ap_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
                                  debit_amt, credit_amt, remarks)
        Select vCompany_ID, vBranch_ID, 'C', a.account_id, 0, a.credit_amt_fc, 0, a.credit_amt, 'Control Total Amt'
        From fa.ap_control a
        Where a.ap_id=pvoucher_ID;

        -- ****		Step 4: Fetch Round Off Information (Credit)
        Select cast(value as bigint) into vRoundOffAcc_ID from sys.settings where key='fa_round_off_account';

        -- *****	Step 3: Insert Round off
        Insert into ap_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
        Select a.company_id, a.branch_id, 'D', vRoundOffAcc_ID, 
                case when a.round_off_amt_fc < 0 Then 0 Else -a.round_off_amt_fc End, case when a.round_off_amt_fc > 0 Then a.round_off_amt_fc Else 0 End, 
                case when a.round_off_amt > 0 Then a.round_off_amt Else 0 End, case when a.round_off_amt < 0 Then -a.round_off_amt Else 0 End, 'Round Off'
        From fa.ap_control a
        Where a.ap_id=pvoucher_ID
                And (a.round_off_amt_fc != 0 or a.round_off_amt != 0);

        return query 
        select a.index, a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt, a.remarks
        from ap_vch_detail a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
create or replace function fa.fn_ad_acc_info_for_gl_post(pvoucher_id varchar(50))
RETURNS TABLE  
(	index int4 , 
	company_id bigint,
	branch_id bigint,
	dc char(1),
	account_id bigint,
	debit_amt_fc numeric(18,4),
	credit_amt_fc numeric(18,4),
	debit_amt numeric(18,4),
	credit_amt numeric(18,4)
)
AS
$BODY$ 
	Declare vAssetBook_ID bigint =0; 
Begin	
	-- This function is used by the Posting Trigger to get information on the AP
	DROP TABLE IF EXISTS ad_vch_detail;	
	create temp TABLE  ad_vch_detail
	(	
		index serial, 
		company_id bigint,
		branch_id bigint,
		dc char(1),
		account_id bigint,
		debit_amt_fc numeric(18,4),
		credit_amt_fc numeric(18,4),
		debit_amt numeric(18,4),
		credit_amt numeric(18,4)
	);

	-- Fetch Accounting Asset Book	
	Select asset_book_id  into vAssetBook_ID from fa.asset_book where is_accounting_asset_book= true;

	-- Fill Depreciation Data
	Insert into ad_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 'D', b.dep_account_id, 0, 0, sum(dep_amt), 0
	from fa.ad_control a
	Inner join fa.ad_tran b on a.ad_id=b.ad_id
	where a.ad_id = pvoucher_id 
		and b.asset_book_id = vAssetBook_ID
	group by a.company_id, a.branch_id, b.dep_account_id
	having sum(b.dep_amt) <> 0;

	-- Fill Accumulated Depreciation data
	Insert into ad_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 'C', b.acc_dep_account_id, 0, 0, 0, sum(dep_amt)
	from fa.ad_control a
	Inner join fa.ad_tran b on a.ad_id=b.ad_id
	where a.ad_id = pvoucher_id 
		and b.asset_book_id = vAssetBook_ID
	group by a.company_id, a.branch_id, b.acc_dep_account_id
	having sum(b.dep_amt) <> 0;
	
	return query 
	select a.index, a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
	from ad_vch_detail a;
END;
$BODY$
LANGUAGE plpgsql; 
?==?

-- Function for asset ledger report
CREATE FUNCTION fa.fn_al_report(IN pcompany_id bigint, IN pbranch_id bigint, IN pas_on date, IN passet_class_id bigint)
RETURNS TABLE
(
  voucher_date date,
  asset_item_id bigint,
  voucher_id varchar(50), 
  asset_tran_amt numeric,
  asset_class_id bigint, 
  dc varchar(10),
  asset_name varchar(250),
  asset_book_desc varchar(50),
  asset_class_code varchar(4),
  asset_class varchar(250),
  asset_qty  bigint,
  purchase_amt numeric,
  rate numeric
)AS
$BODY$
BEGIN
        DROP TABLE IF EXISTS al_report_temp;
	CREATE temp TABLE  al_report_temp
	(	
		  voucher_date date,
		  asset_item_id bigint,
		  voucher_id varchar(50), 
		  asset_tran_amt numeric,
		  asset_class_id bigint, 
		  dc varchar(10),
		  asset_name varchar(250),
		  asset_book_desc varchar(50),
		  asset_class_code varchar(4),
		  asset_class varchar(250),
		  asset_qty  bigint,
		  purchase_amt numeric,
		  rate numeric
	)
	on commit drop;

	insert into al_report_temp (voucher_date, asset_item_id, voucher_id, asset_tran_amt, asset_class_id, dc,
		  asset_name, asset_book_desc, asset_class_code, asset_class, asset_qty, purchase_amt, rate)
	SELECT a.voucher_date, a.asset_item_id, a.voucher_id, a.asset_tran_amt, b.asset_class_id,
		   case when a.en_asset_tran_type = 0 then 'Receipt' Else 'Issue' End as dc,
		   b.asset_name, c.asset_book_desc, d.asset_class_code, d.asset_class,
		   coalesce(e.asset_qty,0) as asset_qty , coalesce(e.purchase_amt,0) as purchase_amt, 
		   sys.fn_handle_zero_divide(coalesce(e.purchase_amt,0),coalesce(e.asset_qty,0)) as rate 
	FROM fa.asset_item_ledger a 
	INNER JOIN fa.asset_item b ON a.asset_item_id = b.asset_item_id
	INNER JOIN fa.asset_book c ON a.asset_book_id = c.asset_book_id
	INNER JOIN fa.asset_class d ON b.asset_class_id = d.asset_class_id
	LEFT JOIN fa.ap_tran e ON b.voucher_tran_id = e.ap_tran_id
	Where a.en_asset_tran_type In (0,2) And a.voucher_date <= pas_on
	And (b.branch_id In (Select * from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_id=0)
	And (b.asset_class_id = passet_class_id or passet_class_id = 0);
		  
	RETURN query
	SELECT a.voucher_date, a.asset_item_id, a.voucher_id, a.asset_tran_amt, a.asset_class_id, a.dc, 
	a.asset_name, a.asset_book_desc, a.asset_class_code, a.asset_class, a.asset_qty, a.purchase_amt, a.rate from al_report_temp a;
END;
$BODY$
 LANGUAGE plpgsql;

?==?
Drop FUNCTION if exists fa.fn_asset_register(In pcompany_id bigint, In pbranch_id bigint, In passet_class_id bigint, IN pyear varchar(4), In ppurchase_date date);

?==?
-- Function for asset register report
CREATE or replace FUNCTION fa.fn_asset_register(In pcompany_id bigint, In pbranch_id bigint, In passet_class_id bigint, IN pyear varchar(4), In pfrom_date date, In pto_date date)      
RETURNS TABLE  
(      
    asset_item_id bigint,    
    company_id bigint,      
    branch_id bigint,    
    asset_class_id bigint,    
    asset_class varchar(250),  
    asset_class_code character varying,      
    asset_code varchar(50),      
    asset_name varchar(250),       
    purchase_date date,      
    use_start_date date,      
    purchase_amt numeric,    
    asset_qty bigint,      
    asset_location_id bigint,      
    asset_location varchar(150),  
    asset_location_code character varying,
    ap_id Varchar(50),
    voucher_id varchar(50),
    dep_date_from date,
    dep_date_to date,
    dep_amt numeric,   
    total_dep_amt numeric,  
    profit_loss_amt numeric,  
    credit_amt numeric,
    supplier_id bigint,
    supplier character varying,
    bill_no character varying,
    bill_date date
)      
AS
$BODY$ 
    declare vYearBegins Date; vYearEnds Date;  
BEGIN  

    select year_begin into vYearBegins from sys.finyear where finyear_code=pyear;   
    select year_end into vYearEnds from sys.finyear where finyear_code=pyear;   
	
    DROP TABLE IF EXISTS asset_register_temp;   
    CREATE temp TABLE asset_register_temp
    (	
        asset_item_id bigint,    
        company_id bigint,      
        branch_id bigint,    
        asset_class_id bigint,    
        asset_class varchar(250),
        asset_class_code character varying,            
        asset_code varchar(50),      
        asset_name varchar(250),       
        purchase_date date,      
        use_start_date date,      
        purchase_amt numeric(18,4),    
        asset_qty bigint,      
        asset_location_id bigint,      
        asset_location varchar(150),  
    	asset_location_code character varying,
        ap_id Varchar(50),
        voucher_id varchar(50),
        dep_date_from date,
        dep_date_to date,
        dep_amt numeric(18,4),   
        total_dep_amt numeric(18,4),  
        profit_loss_amt numeric(18,4),  
        credit_amt numeric(18,4),
        supplier_id bigint,
        supplier character varying,
        bill_no character varying,
        bill_date date
    );

    DROP TABLE IF EXISTS asset_ledger_temp;
    CREATE temp TABLE asset_ledger_temp 
    (
            asset_item_id bigint primary key
    );

    INSERT INTO asset_ledger_temp (asset_item_id) 
    Select x.asset_item_id from fa.asset_item_ledger x 
    where en_asset_tran_type=2 and voucher_date < vYearBegins
    group by x.asset_item_id; 

    INSERT INTO asset_register_temp(asset_item_id, company_id, branch_id, asset_class_id, asset_class, asset_class_code, asset_code,      
            asset_name, purchase_date, use_start_date, purchase_amt, asset_qty, asset_location_id, asset_location, asset_location_code,
            ap_id, voucher_id, dep_date_from, dep_date_to, dep_amt, total_dep_amt, profit_loss_amt, credit_amt)  
    SELECT a.asset_item_id, a.company_id, a.branch_id, a.asset_class_id, b.asset_class, b.asset_class_code, a.asset_code,  
    a.asset_name, a.purchase_date, a.use_start_date, a.purchase_amt, a.asset_qty, a.asset_location_id, c.asset_location, c.asset_location_code, 
    a.voucher_id as ap_id, d.voucher_id, d.dep_date_from, d.dep_date_to, d.dep_amt, 0,0,0  
    FROM fa.asset_item a  
    inner join fa.asset_class b On a.asset_class_id=b.asset_class_id  
    inner join fa.asset_location c On a.asset_location_id= c.asset_location_id   
    left join fa.asset_dep_ledger d On a.asset_item_id=d.asset_item_id
    WHERE (a.branch_id In(Select * from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_id=0)   
        and (a.company_id = pcompany_id or pcompany_id = 0)   
        and (a.asset_class_id = passet_class_id OR passet_class_id = 0)  
        and a.purchase_date between pfrom_date and pto_date
        and a.asset_item_id not in  (Select x.asset_item_id from asset_ledger_temp x);

    DROP TABLE IF EXISTS asset_dep_temp;
    CREATE temp TABLE asset_dep_temp 
    (
            asset_item_id bigint primary key,
            dep_amt numeric(18,4)   
    );

    INSERT INTO asset_dep_temp(asset_item_id, dep_amt)
    SELECT x.asset_item_id, SUM(x.dep_amt) as dep_amt 
    FROM fa.asset_dep_ledger x 
    GROUP BY x.asset_item_id;

    -- Retrieving the total of Dep Amount till date
    DROP TABLE IF EXISTS total_dep_amt_temp;
    CREATE temp TABLE total_dep_amt_temp  
    (  
            asset_item_id bigint primary key,  
            dep_amt_till_date numeric(18,4)  
    );

    insert into total_dep_amt_temp(asset_item_id, dep_amt_till_date)  
    select a.asset_item_id, d.dep_amt
    from fa.asset_item a    
    inner join fa.asset_class b On a.asset_class_id=b.asset_class_id  
    inner join fa.asset_location c On a.asset_location_id= c.asset_location_id  
    inner join asset_dep_temp d On a.asset_item_id=d.asset_item_id   
    WHERE (a.branch_id In(Select * from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_ID=0)   
        and (a.company_id = pcompany_id or pcompany_id=0)   
        and (a.asset_class_id=passet_class_id OR passet_class_id = 0) 
        AND a.asset_item_id Not In (Select x.asset_item_id from asset_ledger_temp x);   

    ---updating the result set with the above dep amount    
    update asset_register_temp a 
    set total_dep_amt=b.dep_amt_till_date  
    from total_dep_amt_temp b
    where a.asset_item_id=b.asset_item_id;  

    --updating the result set with the Sale(Credit) amount  
    update asset_register_temp a    
            set  credit_amt=b.asset_tran_amt  
    from fa.asset_item_ledger b    
    where b.en_asset_tran_type=2 
        and b.voucher_date between vYearBegins and vYearEnds
        And a.asset_item_id=b.asset_item_id;  

    --updating the result set with the ProfitLoss amount  
    update asset_register_temp a   
    set profit_loss_amt=b.asset_tran_amt  
    from fa.asset_item_ledger b   
    where b.en_asset_tran_type=1 
        and b.voucher_date between vYearBegins and vYearEnds
        And a.asset_item_id=b.asset_item_id;  
  
    --Update supplier, bill_no, bill_date for purchase 
    update asset_register_temp a
    set supplier_id = b.account_id,
    bill_no = b.bill_no,
    bill_date = b.bill_date,
    supplier = c.account_head
    from fa.ap_control b   
    inner join ac.account_head c on b.account_id = c.account_id
    where a.ap_id = b.ap_id; 
 
    RETURN query
    select a.asset_item_id, a.company_id, a.branch_id, a.asset_class_id, a.asset_class, a.asset_class_code, a.asset_code, a.asset_name,       
            a.purchase_date, a.use_start_date, a.purchase_amt, a.asset_qty, a.asset_location_id, a.asset_location, a.asset_location_code, a.ap_id,  
            COALESCE(a.voucher_id, ''), a.dep_date_from, a.dep_date_to, a.dep_amt, a.total_dep_amt, a.profit_loss_amt, a.credit_amt,
            a.supplier_id, a.supplier, a.bill_no, a.bill_date
    from asset_register_temp a; 	  
END;  
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION fa.fn_ad_report(IN pad_id character varying)
  RETURNS TABLE
  (  
	ad_id varchar(50), 
	ad_tran_id varchar(50),
	doc_date date, 
	narration varchar(500), 
	status smallint, 
	amt_in_words varchar(250), 
	dep_date_from date, 
	dep_date_to date, 
	doc_type varchar(4),
	asset_class varchar(250),
	asset_book_desc varchar(50), 
	dep_accountn_code varchar(300), 
	acc_dep_accountn_code varchar(300),
	dep_amt numeric(18,4),
	entered_by varchar(100),
	posted_by varchar(100)
) AS
$BODY$
BEGIN 
	--Temp Table
	DROP TABLE IF EXISTS ad_report_temp;
	CREATE temp TABLE ad_report_temp
	(
		ad_id varchar(50), 
		ad_tran_id varchar(50),
		doc_date date, 
		narration varchar(500), 
		status smallint, 
		amt_in_words varchar(250), 
		dep_date_from date, 
		dep_date_to date, 
		doc_type varchar(4),
		asset_class varchar(250),
		asset_book_desc varchar(50), 
		dep_accountn_code varchar(300), 
		acc_dep_accountn_code varchar(300),
		dep_amt numeric(18,4),
		entered_by varchar(100),
		posted_by varchar(100)
	);

	INSERT INTO ad_report_temp(ad_id, ad_tran_id, doc_date, narration, status, amt_in_words, dep_date_from, dep_date_to, doc_type,asset_class, asset_book_desc, 
				dep_accountn_code, acc_dep_accountn_code, dep_amt, entered_by, posted_by)
	SELECT  a.ad_id, b.ad_tran_id, a.doc_date, a.narration, a.status, a.amt_in_words, a.dep_date_from, a.dep_date_to, a.doc_type, 
		c.asset_class, e.asset_book_desc, f.account_head as dep_accountn_code, g.account_head as acc_dep_accountn_code,
		b.dep_amt, d.entered_by, d.posted_by
	FROM fa.ad_control a     
	INNER JOIN fa.ad_tran b ON a.ad_id = b.ad_id  
	INNER JOIN sys.doc_es d ON a.ad_id = d.voucher_id
	LEFT JOIN fa.asset_class c ON b.asset_class_id=c.asset_class_id 
	LEFT JOIN fa.asset_book e ON b.asset_book_id=e.asset_book_id
	LEFT JOIN ac.account_head f ON b.dep_account_id=f.account_id
	LEFT JOIN ac.account_head g ON b.acc_dep_account_id=g.account_id
	WHERE a.ad_id=pad_id;

	return query 
	select a.ad_id, a.ad_tran_id, a.doc_date, a.narration, a.status, a.amt_in_words, a.dep_date_from, a.dep_date_to, a.doc_type,a.asset_class, 
		a.asset_book_desc, a.dep_accountn_code, a.acc_dep_accountn_code, a.dep_amt, a.entered_by, a.posted_by
	from ad_report_temp a;
END;
$BODY$
  LANGUAGE plpgsql;


?==?
-- Function for asset purchase document print report
CREATE OR REPLACE FUNCTION fa.fn_ap_report(IN pvoucher_id character varying)
RETURNS TABLE
(

	ap_id varchar(50),
	en_purchase_type smallint,
	doc_date date,
	bill_date date,
	bill_no varchar(50),
	net_credit_amt numeric(18,4), 
	narration varchar(500),
	credit_amt numeric(18,4),
	gross_credit_amt numeric(18,4),
	disc_pcnt numeric(18,4),
	disc_is_value boolean,
	disc_amt numeric(18,4),
	round_off_amt numeric(18,4),
	advance_amt numeric(18,4),
	amt_in_words varchar(250),
	remarks varchar(500),
	status smallint,
	fc_type_id bigint,
	exch_rate numeric(18,6),
	account_head varchar(250),
	ap_tran_id varchar(50),
	sl_no smallint,
	asset_class_id bigint,
	asset_code varchar(50),
	asset_name varchar(250),
	use_start_date date,
	purchase_amt_fc numeric(18,4),
	purchase_amt numeric(18,4),
	asset_location_id bigint,
	asset_qty bigint,
	asset_class varchar(250),
	asset_location varchar(150),
	entered_by varchar(100),
	posted_by varchar(100)
) AS
$BODY$
Begin 
	DROP TABLE IF EXISTS ap_report_temp;
	CREATE temp TABLE  ap_report_temp
	(
		ap_id varchar(50),
		en_purchase_type smallint,
		doc_date date,
		bill_date date,
		bill_no varchar(50),
		net_credit_amt numeric(18,4), 
		narration varchar(500),
		credit_amt numeric(18,4),
		gross_credit_amt numeric(18,4),
		disc_pcnt numeric(18,4),
		disc_is_value boolean,
		disc_amt numeric(18,4),
		round_off_amt numeric(18,4),
		advance_amt numeric(18,4),
		amt_in_words varchar(250),
		remarks varchar(500),
		status smallint,
		fc_type_id bigint,
		exch_rate numeric(18,6),
		account_head varchar(250),
		ap_tran_id varchar(50),
		sl_no smallint,
		asset_class_id bigint,
		asset_code varchar(50),
		asset_name varchar(250),
		use_start_date date,
		purchase_amt_fc numeric(18,4),
		purchase_amt numeric(18,4),
		asset_location_id bigint,
		asset_qty bigint,
		asset_class varchar(250),
		asset_location varchar(150),
		entered_by varchar(100),
		posted_by varchar(100)
	 );

	INSERT INTO ap_report_temp (ap_id, en_purchase_type, doc_date, bill_date, bill_no, net_credit_amt, narration, credit_amt, gross_credit_amt,
		disc_pcnt, disc_is_value, disc_amt, round_off_amt, advance_amt, amt_in_words, remarks, status, fc_type_id, exch_rate, 
		account_head, ap_tran_id, sl_no, asset_class_id, asset_code, asset_name, use_start_date, purchase_amt_fc, purchase_amt, 
		asset_location_id, asset_qty, asset_class, asset_location, entered_by, posted_by)
	SELECT a.ap_id, a.en_purchase_type, a.doc_date, a.bill_date, a.bill_no, a.net_credit_amt, a.narration, a.credit_amt, a.gross_credit_amt,
		a.disc_pcnt, a.disc_is_value, a.disc_amt, a.round_off_amt, a.advance_amt, a.amt_in_words, a.remarks, a.status, a.fc_type_id, a.exch_rate, 
		f.account_head, b.ap_tran_id, b.sl_no, b.asset_class_id, b.asset_code, b.asset_name, b.use_start_date, b.purchase_amt_fc, b.purchase_amt, 
		b.asset_location_id, b.asset_qty, c.asset_class, d.asset_location, e.entered_by, e.posted_by
	FROM fa.ap_control a
	INNER JOIN fa.ap_tran b ON a.ap_id = b.ap_id
	INNER JOIN fa.asset_class c ON b.asset_class_id = c.asset_class_id
	INNER JOIN fa.asset_location d ON b.asset_location_id = d.asset_location_id
	INNER JOIN sys.doc_es e ON a.ap_id = e.voucher_id
	Inner JOIN ac.account_head f ON a.account_id = f.account_id
	WHERE a.ap_id = pvoucher_id;

	RETURN query
	SELECT a.ap_id, a.en_purchase_type, a.doc_date, a.bill_date, a.bill_no, a.net_credit_amt, a.narration, a.credit_amt, a.gross_credit_amt,
		a.disc_pcnt, a.disc_is_value, a.disc_amt, a.round_off_amt, a.advance_amt, a.amt_in_words, a.remarks, a.status, a.fc_type_id, a.exch_rate, 
		a.account_head, a.ap_tran_id, a.sl_no, a.asset_class_id, a.asset_code, a.asset_name, a.use_start_date, a.purchase_amt_fc, a.purchase_amt, 
		a.asset_location_id, a.asset_qty, a.asset_class, a.asset_location, a.entered_by, a.posted_by
	FROM ap_report_temp a;

END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION fa.fn_ap_lc_tran_report(IN pap_id character varying)
  RETURNS TABLE
(
	ap_id varchar(50),
	ap_lc_tran_id varchar(50),
	account_head_affected varchar(250),
	debit_amt_fc numeric(18,4), 
	exch_rate numeric(18,6),
	debit_amt numeric(18,4),
	bill_no varchar(50),
	bill_date date,
	supplier_paid boolean	
) AS
$BODY$
Begin 
	DROP TABLE IF EXISTS ap_lc_tran_temp;
	CREATE temp TABLE  ap_lc_tran_temp
	(
		ap_id varchar(50),
		ap_lc_tran_id varchar(50),
		account_head_affected varchar(250),
		debit_amt_fc numeric(18,4), 
		exch_rate numeric(18,6),
		debit_amt numeric(18,4),
		bill_no varchar(50),
		bill_date date,
		supplier_paid boolean	
	 );

	INSERT INTO ap_lc_tran_temp(ap_id, ap_lc_tran_id, account_head_affected, debit_amt_fc, exch_rate, debit_amt, bill_no, bill_date, supplier_paid)
	SELECT a.ap_id, a.ap_lc_tran_id, b.account_head AS account_head_affected, a.debit_amt_fc, a.exch_rate, a.debit_amt, a.bill_no, 
		a.bill_date, a.supplier_paid
	FROM fa.ap_lc_tran a
	left JOIN ac.account_head b ON a.account_affected_id = b.account_id
	WHERE a.ap_id = pap_id;

	RETURN query
	SELECT a.ap_id, a.ap_lc_tran_id, a.account_head_affected, a.debit_amt_fc, a.exch_rate, a.debit_amt, a.bill_no, a.bill_date, a.supplier_paid
	FROM ap_lc_tran_temp a;
		
END;
$BODY$
  LANGUAGE plpgsql;
?==?

-- Function for asset dep register report
CREATE OR REPLACE FUNCTION fa.fn_asset_dep_register(In pcompany_id bigint,in pbranch_id bigint, In passet_book_id bigint,In pyear varchar(4))    
RETURNS TABLE
(    
	asset_item_id bigint,  
	company_id bigint,    
	branch_id bigint,  
	asset_class_id bigint,  
	voucher_id varchar,
	asset_book_id bigint,
	finyear varchar,
	asset_book varchar,
	asset_class varchar,    
	asset_code varchar,
	asset_name varchar,     
	purchase_date date,    
	use_start_date date,    
	purchase_amt numeric,   
	asset_qty bigint,    
	asset_location_id bigint,    
	asset_location varchar,
	dep_date_from date,
	dep_date_to date,
	dep_amt numeric,
	profit_loss_amt numeric,
	credit_amt numeric,
	previous_years_dep_amt numeric  
)   
AS
$BODY$ 
	declare vYearBegins Date;
BEGIN
		
	select year_begin into vYearBegins from sys.finyear where finyear_code=pyear; 

	DROP TABLE IF EXISTS asset_dep_register_temp;   
        CREATE temp TABLE asset_dep_register_temp
	(	
		asset_item_id bigint,  
		company_id bigint,    
		branch_id bigint,  
		asset_class_id bigint,  
		voucher_id varchar(50),
		asset_book_id bigint,
		finyear varchar(4),
		asset_book varchar(50),
		asset_class varchar(250),    
		asset_code varchar(20),
		asset_name varchar(250),     
		purchase_date date,    
		use_start_date date,    
		purchase_amt numeric(18,4),   
		asset_qty bigint,    
		asset_location_id bigint,    
		asset_location varchar(150),
		dep_date_from date,
		dep_date_to date,
		dep_amt numeric(18,4),
		profit_loss_amt numeric(18,4),
		credit_amt numeric(18,4),
		previous_years_dep_amt numeric(18,4)
	)
	on commit drop;
	
	---retrieving the Aseet Dep for the particular FinYear
	INSERT INTO asset_dep_register_temp(asset_item_id, company_id, branch_id, asset_class_id, voucher_id, asset_book_id,
		finyear, asset_book, asset_class, asset_code, asset_name, purchase_date, use_start_date, purchase_amt, asset_qty,    
		asset_location_id, asset_location, dep_date_from, dep_date_to, dep_amt, profit_loss_amt, credit_amt, previous_years_dep_amt)
	select a.asset_item_id, a.company_id, a.branch_id, a.asset_class_id, a.voucher_id, a.asset_book_id, a.finyear, d.asset_book_desc, c.asset_class,
		b.asset_code, b.asset_name, b.purchase_date, b.use_start_date, b.purchase_amt, b.asset_qty, b.asset_location_id,
		e.asset_location, a.dep_date_from, a.dep_date_to, a.dep_amt, coalesce(f.profit_loss_amt,0), coalesce(g.credit_amt,0),0
        from fa.asset_dep_ledger a
		inner join fa.asset_item b On a.asset_item_id=b.asset_item_id
		inner join fa.asset_class c on a.asset_class_id=c.asset_class_id
		inner join fa.asset_book d on  a.asset_book_id=d.asset_book_id
		inner join fa.asset_location e on b.asset_location_id=e.asset_location_id
		left join fa.as_book_tran f on a.voucher_id=f.as_id and a.asset_item_id=f.asset_item_id
		left join fa.as_tran g on  a.voucher_id=g.as_id and a.asset_item_id=g.asset_item_id
	WHERE (a.branch_id In(Select * from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_id=0)
		and (a.company_id = pcompany_id or pcompany_id=0) 
		and (a.asset_book_id=passet_book_id OR passet_book_id = 0)	
		and a.finyear=pyear
		and a.doc_date>=vYearBegins;
			 
	----retrieving the total of Dep Amount for the Previous Finacial Years
	DROP TABLE IF EXISTS previous_year_temp;
	CREATE temp TABLE previous_year_temp 
	(
		asset_item_id bigint,
		previous_years_amt numeric(18,4)
	)
	on commit drop;
	
	insert into previous_year_temp(asset_item_id, previous_years_amt)
	select a.asset_item_id,sum(a.dep_amt)
	from fa.asset_dep_ledger a
	       inner join fa.asset_item b On a.asset_item_id=b.asset_item_id
	       inner join fa.asset_class c on a.asset_class_id=c.asset_class_id
	       inner join fa.asset_book d on  a.asset_book_id=d.asset_book_id
	       inner join fa.asset_location e on b.asset_location_id=e.asset_location_id
	       left join fa.as_book_tran f on a.voucher_id=f.as_id and a.asset_item_id=f.asset_item_id
	       left join fa.as_tran g on  a.voucher_id=g.as_id and a.asset_item_id=g.asset_item_id
	where a.dep_date_from<vYearBegins
			 and (a.branch_id In(Select * from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_id=0)
			 and (a.company_id = pcompany_id or pcompany_id=0) 
			 and (a.asset_book_id=passet_book_id OR passet_book_id = 0)
	group by a.asset_item_id;

	---updatind the result set with the above previous years dep amount

	update asset_dep_register_temp   
		set  previous_years_dep_amt=b.previous_years_amt
	from previous_year_temp b 
	where asset_dep_register_temp.asset_item_id=b.asset_item_id;

        RETURN query
        select a.asset_item_id, a.company_id, a.branch_id, a.asset_class_id, a.voucher_id, a.asset_book_id,
		a.finyear, a.asset_book, a.asset_class, a.asset_code, a.asset_name, a.purchase_date, a.use_start_date, a.purchase_amt, a.asset_qty,    
		a.asset_location_id, a.asset_location, a.dep_date_from, a.dep_date_to, a.dep_amt, a.profit_loss_amt, a.credit_amt, a.previous_years_dep_amt
        from asset_dep_register_temp a;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
create OR REPLACE function fa.asset_item_for_asset_sale(pcompany_id bigint, pbranch_id bigint, passet_class_id bigint, pvoucher_id varchar(50), pto_date date)
RETURNS TABLE  
(
	asset_item_id bigint,
	asset_class_id bigint,
	asset_code varchar(50),
	asset_name varchar(250),	
	purchase_amt numeric(18,4),
	dep_amt numeric(18,4),
	asset_qty numeric(18,4),
	asset_location_id bigint,
	purchase_date date
)
AS
$BODY$  
Begin	
	DROP TABLE IF EXISTS asset_item_as;	
	create temp TABLE  asset_item_as
	(	
		asset_item_id bigint,
		asset_class_id bigint,
		asset_code varchar(50),
		asset_name varchar(250),	
		purchase_amt numeric(18,4),
		dep_amt numeric(18,4),
		asset_qty numeric(18,4),
		asset_location_id bigint,
		purchase_date date
	);

	Insert into asset_item_as(asset_item_id, asset_class_id, asset_code, asset_name, purchase_amt, dep_amt, asset_qty, asset_location_id, purchase_date)
	Select a.asset_item_id, a.asset_class_id, a.asset_code, a.asset_name, a.purchase_amt, coalesce(sum(b.dep_amt), 0) as dep_amt, a.asset_qty, a.asset_location_id, a.purchase_date
	From fa.asset_item a
	left Join fa.asset_dep_ledger b on b.asset_class_id=a.asset_class_id and b.asset_item_id=a.asset_item_id
	where a.branch_id=pbranch_id
		and a.company_id=pcompany_id
		and a.asset_class_id=passet_class_id
		and a.asset_item_id not in (Select a.asset_item_id from fa.as_tran a where a.as_id<>pvoucher_id)
		and a.use_start_date <=pto_date
	group by a.asset_item_id, a.asset_class_id, a.asset_code, a.asset_name, a.purchase_amt, a.asset_qty, a.asset_location_id, a.purchase_date;


	return query 
	select a.asset_item_id, a.asset_class_id, a.asset_code, a.asset_name, a.purchase_amt, a.dep_amt, a.asset_qty, a.asset_location_id, a.purchase_date
	from asset_item_as a;
END;
$BODY$
LANGUAGE plpgsql;


?==?
Create or replace function fa.fn_as_acc_info_for_gl_post(pvoucher_id varchar(50))
RETURNS TABLE  
(	index int4 , 
	company_id bigint,
	branch_id bigint,
	dc char(1),
	account_id bigint,
	debit_amt_fc numeric(18,4),
	credit_amt_fc numeric(18,4),
	debit_amt numeric(18,4),
	credit_amt numeric(18,4)
)
AS
$BODY$ 
	Declare vAssetBook_ID bigint =0; vDepAccount_ID bigint=-1; vAccDepAccount_ID bigint = -1; vAssetAccount_ID bigint = -1; vProfitLossAccount_ID bigint = -1;
Begin	
	-- This function is used by the Posting Trigger to get information on the AS

	-- Asset Sale can be affected in only one class of asset in a document.
	-- Therfore, Dep, AccDep, Sale account ID etc would be the same.
	DROP TABLE IF EXISTS as_vch_detail;	
	create temp TABLE  as_vch_detail
	(	
		index serial, 
		company_id bigint,
		branch_id bigint,
		dc char(1),
		account_id bigint,
		debit_amt_fc numeric(18,4),
		credit_amt_fc numeric(18,4),
		debit_amt numeric(18,4),
		credit_amt numeric(18,4)
	);

	-- Fetch Accounting Asset Book	
	Select asset_book_id  into vAssetBook_ID from fa.asset_book where is_accounting_asset_book= true;

	-- Fetch Accounts associated for the class from the master
	Select a.asset_account_id, a.dep_account_id, a.acc_dep_account_id, a.profit_loss_account_id 
		into vAssetAccount_ID, vDepAccount_ID, vAccDepAccount_ID, vProfitLossAccount_ID
	From fa.asset_class a 
	Inner Join fa.as_control b on a.asset_class_id=b.asset_class_id
	where b.as_id = pvoucher_id;

	-- Fetch Net Consideration (Sale Amt) from control
	Insert into as_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 'D', a.customer_id, a.debit_amt_fc, 0, a.debit_amt, 0
	from fa.as_control a
	where a.as_id = pvoucher_id;
	    
	-- Fill Acc Depreciation Data
	Insert into as_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 'D', vAccDepAccount_ID, 0, 0, sum(b.acc_dep_amt), 0
	from fa.as_control a
	Inner join fa.as_book_tran b on a.as_id=b.as_id
	where a.as_id = pvoucher_id 
		and b.asset_book_id = vAssetBook_ID
	group by a.company_id, a.branch_id;

	-- Fill Depreciation Data
	Insert into as_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 'D', vDepAccount_ID, 0, 0, sum(b.dep_amt), 0
	from fa.as_control a
	Inner join fa.as_book_tran b on a.as_id=b.as_id
	where a.as_id = pvoucher_id 
		and b.asset_book_id = vAssetBook_ID
	group by a.company_id, a.branch_id;

	-- Fill Profit/Loss
	Insert into as_vch_detail(company_id, branch_id, 
			dc, 
			account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 
		case when sum(b.profit_loss_amt) <=0 then 'D' Else 'C' End, 
		vProfitLossAccount_ID, 0, 0, 
		case when sum(b.profit_loss_amt) <=0 Then -sum(b.profit_loss_amt) Else 0 End,
		case when sum(b.profit_loss_amt) >0 Then sum(b.profit_loss_amt) Else 0 End
	from fa.as_control a
	Inner join fa.as_book_tran b on a.as_id=b.as_id
	where a.as_id = pvoucher_id 
		and b.asset_book_id = vAssetBook_ID
	group by a.company_id, a.branch_id
	having sum(b.profit_loss_amt)<>0;

	-- Fetch Net Consideration (Sale Amt) from control
	Insert into as_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 'C', vAssetAccount_ID, 0, 0, 0, sum(b.purchase_amt)
	from fa.as_control a
	Inner join fa.as_tran b on a.as_id=b.as_id
	where a.as_id = pvoucher_id
	group by a.company_id, a.branch_id;
		
    -- Fetch GST Tax Tran 
	Insert into as_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
            debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 'C', b.account_id, 0, 0 as tax_amt_fc, 
            0, coalesce(sum(b.tax_amt), 0) as tax_amt
	From fa.as_control a
	Inner Join tx.fn_gtt_info(pvoucher_ID, 'fa.as_tran') b on a.as_id =b.voucher_id
	Where a.as_id=pvoucher_ID
	group by a.company_id, a.branch_id, b.account_id;
    
	return query 
	select a.index, a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
	from as_vch_detail a;
END;
$BODY$
LANGUAGE plpgsql; 

?==?

-- Function for asset sale document print report
CREATE OR REPLACE FUNCTION fa.fn_as_report( In pas_id varchar(50))    
RETURNS TABLE
(	
	as_id varchar(50), 
	doc_date date, 
	narration varchar(500), 
	status smallint,
	amt_in_words varchar(250),
	debit_amt numeric,
	advance_amt numeric,
	v_id bigint,
	customer_id bigint,
	customer varchar(250),
	cheque_number varchar(20),
	cheque_date date,
	entered_by varchar(100), 
	posted_by varchar(100)
)
As	 
$BODY$
Begin 
	DROP TABLE IF EXISTS as_report_temp;	
	create temp TABLE  as_report_temp
	(	
		as_id varchar(50), 
		doc_date date, 
		narration varchar(500), 
		status smallint,
		amt_in_words varchar(250),
		debit_amt numeric(18,4),
		advance_amt numeric(18,4),
		v_id bigint,
		customer_id bigint,
		customer varchar(250),
		cheque_number varchar(20),
		cheque_date date,
		entered_by varchar(100), 
		posted_by varchar(100)
	)
	on commit drop;

	insert into as_report_temp (as_id, doc_date, narration, status, amt_in_words, debit_amt, advance_amt,
		v_id, customer_id, customer, cheque_number, cheque_date, entered_by, posted_by)
	select a.as_id,	a.doc_date, a.narration, a.status, a.amt_in_words, a.debit_amt, a.advance_amt, a.v_id,  
               a.customer_id, b.account_head as customer, a.cheque_number, a.cheque_date, c.entered_by, c.posted_by
        from fa.as_control a     
		inner join ac.account_head b on a.customer_id = b.account_id
		inner join sys.doc_es c on a.as_id = c.voucher_id
		where a.as_id = pas_id;

	return query 
         select a.as_id, a.doc_date, a.narration, a.status, a.amt_in_words, a.debit_amt, a.advance_amt,
		a.v_id, a.customer_id, a.customer, a.cheque_number, a.cheque_date, a.entered_by, a.posted_by 
                from as_report_temp a;
END;
$BODY$
  LANGUAGE plpgsql; 
?==?

-- Function for book transaction in asset sales document print report
CREATE FUNCTION fa.fn_as_book_tran_report(IN pas_id varchar(50))
RETURNS TABLE
(	
	asset_item_id bigint,
	asset_name varchar(250),
	asset_book_id bigint,
	asset_book_desc varchar(50),
	dep_amt numeric,
	acc_dep_amt numeric,
	profit_loss_amt numeric
)
As	 
$BODY$
Begin 
	return query 
		SELECT a.asset_item_id, b.asset_name, a.asset_book_id, c.asset_book_desc, 
		       a.dep_amt, a.acc_dep_amt, a.profit_loss_amt
		FROM fa.as_book_tran a
		 inner join fa.asset_item b on a.asset_item_id = b.asset_item_id
		 inner join fa.asset_book c on a.asset_book_id = c.asset_book_id	
		where a.as_id= pas_id; 
END;
$BODY$
  LANGUAGE plpgsql; 
?==?

-- Function for asset sale document tran print report
CREATE OR REPLACE FUNCTION fa.fn_as_tran_report(IN pas_id varchar(50))
RETURNS TABLE
(	
        as_id varchar(50),
        as_tran_id varchar(50),
	sl_no smallint,
	asset_item_id bigint,
	asset_name varchar(250),
	dep_amt numeric,
	credit_amt numeric,
	purchase_amt numeric
)
As	 
$BODY$
Begin 
	DROP TABLE IF EXISTS as_tran_report_temp;	
	create temp TABLE  as_tran_report_temp
	(	
		as_id varchar(50),
		as_tran_id varchar(50),
		sl_no smallint,
		asset_item_id bigint,
		asset_name varchar(250),
		dep_amt numeric(10,4),
		credit_amt numeric(10,4),
		purchase_amt numeric(10,4)
	)
	on commit drop;

	insert into as_tran_report_temp (as_id, as_tran_id, sl_no, asset_item_id, asset_name, dep_amt, credit_amt, purchase_amt)
		select a.as_id, a.as_tran_id, a.sl_no, a.asset_item_id, b.asset_name, 0 as dep_amt, a.credit_amt, a.purchase_amt
		from fa.as_tran a     
		inner join fa.asset_item b on a.asset_item_id = b.asset_item_id
		WHERE a.as_id=pas_id;	

	---updating the result set with dep amount  
  	update as_tran_report_temp     
		set  dep_amt=b.dep_amt
	from (select a.as_tran_id, a.as_id, coalesce(sum(d.dep_amt), 0) as dep_amt
                                from fa.as_tran a
                                inner join fa.as_control b on a.as_id=b.as_id
                                Inner join fa.asset_item c on a.asset_item_id=c.asset_item_id
                                inner join fa.asset_dep_ledger d on a.asset_item_id=d.asset_item_id and b.asset_class_id=d.asset_class_id
                                where b.as_id=pas_id
                                group by a.as_tran_id, a.as_id, a.asset_item_id, c.asset_code, c.asset_name) b
	where as_tran_report_temp.as_id=b.as_id;  

	return query 
	    select a.as_id, a.as_tran_id, a.sl_no, a.asset_item_id, a.asset_name, a.dep_amt, a.credit_amt, a.purchase_amt
	    from as_tran_report_temp a; 
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION fa.fn_gst_ap_report(IN pvoucher_id character varying)
RETURNS TABLE
(
	ap_id varchar(50),
	en_purchase_type smallint,
	doc_date date,
	bill_date date,
	bill_no varchar(50),
	net_credit_amt numeric(18,4), 
	narration varchar(500),
	credit_amt numeric(18,4),
	gross_credit_amt numeric(18,4),
	disc_pcnt numeric(18,4),
	disc_is_value boolean,
	disc_amt numeric(18,4),
	round_off_amt numeric(18,4),
	advance_amt numeric(18,4),
	amt_in_words varchar(250),
	remarks varchar(500),
	status smallint,
	fc_type_id bigint,
	exch_rate numeric(18,6),
	tax_amt numeric(18,4),
	branch_gstin varchar(15),
	branch_state varchar(50),
	supplier_gstin varchar(15),
	supplier_address text,
	supplier_state varchar(50),
	account_head varchar(250),
	entered_by varchar(100),
	posted_by varchar(100),
	cheque_number varchar(20),
	cheque_date date
) AS
$BODY$
Begin 
RETURN query
SELECT a.ap_id, a.en_purchase_type, a.doc_date, a.bill_date, a.bill_no, a.net_credit_amt, a.narration, a.credit_amt, a.gross_credit_amt,
		a.disc_pcnt, a.disc_is_value, a.disc_amt, a.round_off_amt, a.advance_amt, a.amt_in_words, a.remarks, a.status, a.fc_type_id, a.exch_rate, 
		(a.annex_info->>'tax_amt')::numeric as tax_amt,
		b.gstin as branch_gstin, (c.gst_state_code || '-' || c.state_name)::varchar as branch_state,
		(a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar as supplier_gstin,
		(a.annex_info->'gst_input_info'->>'supplier_address')::text as supplier_address,
		(g.gst_state_code || '-' || g.state_name)::varchar as supplier_state,
		f.account_head, 
		 e.entered_by, e.posted_by, a.cheque_number, a.cheque_date
	FROM fa.ap_control a	
	INNER JOIN sys.branch b ON a.branch_id = b.branch_id
	INNER JOIN tx.gst_state c ON b.gst_state_id = c.gst_state_id
	INNER JOIN sys.doc_es e ON a.ap_id = e.voucher_id
	INNER JOIN ac.account_head f ON a.account_id = f.account_id
	INNER JOIN tx.gst_state g ON (a.annex_info->'gst_input_info'->>'supplier_state_id')::bigint = g.gst_state_id
	WHERE a.ap_id = pvoucher_id;
END;
$BODY$
  LANGUAGE plpgsql;
?==?
CREATE OR REPLACE FUNCTION fa.fn_gst_ap_tran_report(IN pvoucher_id character varying)
RETURNS TABLE
(
	ap_id varchar(50),
	ap_tran_id varchar(50),
	sl_no smallint,
	asset_class_id bigint,
	asset_code varchar(50),
	asset_name varchar(250),
	use_start_date date,
	purchase_amt_fc numeric(18,4),
	purchase_amt numeric(18,4),
	asset_location_id bigint,
	asset_qty bigint,
	asset_class varchar(250),
	asset_location varchar(150),
	hsn_sc_code varchar(8),
	apply_itc boolean,
	bt_amt numeric(18,4),
	sgst_pcnt numeric(5,2),
	sgst_amt numeric(18,2),
	cgst_pcnt numeric(5,2),
	cgst_amt numeric(18,2),
	igst_pcnt numeric(5,2),
	igst_amt numeric(18,2),
	cess_pcnt numeric(5,2),
	cess_amt numeric(18,2),
	tax_amt numeric(18,2)
) AS
$BODY$
Begin 
RETURN query
SELECT b.ap_id, b.ap_tran_id, b.sl_no, b.asset_class_id, b.asset_code, b.asset_name, b.use_start_date, b.purchase_amt_fc, b.purchase_amt, 
		b.asset_location_id, b.asset_qty, c.asset_class, d.asset_location,
		e.hsn_sc_code, e.apply_itc, e.bt_amt, 
		e.sgst_pcnt, e.sgst_amt, e.cgst_pcnt, e.cgst_amt, 
		e.igst_pcnt, e.igst_amt, e.cess_pcnt, e.cess_amt,
		(e.sgst_amt+e.cgst_amt+e.igst_amt+e.cess_amt) as tax_amt
		FROM fa.ap_tran b
	INNER JOIN fa.asset_class c ON b.asset_class_id = c.asset_class_id
	INNER JOIN fa.asset_location d ON b.asset_location_id = d.asset_location_id
	INNER JOIN tx.gst_tax_tran e ON b.ap_tran_id = e.gst_tax_tran_id
	WHERE b.ap_id = pvoucher_id;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION fa.fn_gst_as_print(IN pas_id character varying, IN pcp_option smallint)
RETURNS TABLE
(	cp_id bigint, cp_desc character varying, as_id character varying, company_id bigint, finyear character varying, branch_id bigint, 
 	doc_type character varying, doc_date date, item_amt_tot numeric, tax_amt_tot numeric, nt_amt numeric, rof_amt numeric, sale_amt numeric, 
 	status smallint, narration character varying, amt_in_words character varying, 
 	cust_name character varying, cust_state character varying, cust_gstin character varying, cust_addr character varying
) 
AS
$BODY$
BEGIN	
	Drop Table if Exists inv_temp;
	Create Temp Table inv_temp
	(	cp_id BigInt,
		cp_desc Character Varying,
		as_id character varying, 
		company_id bigint, 
		finyear character varying, 
		branch_id bigint, 
		doc_type character varying, 
		doc_date date, 
		item_amt_tot numeric, 
		tax_amt_tot numeric, 
		nt_amt numeric, 
		rof_amt numeric, 
		sale_amt numeric, 
		status smallint, 
		narration character varying, 
		amt_in_words character varying, 
		cust_name character varying,
		cust_state character varying,
		cust_gstin character varying,
		cust_addr character varying
	);

	If pcp_option = 1 Then
		Insert Into inv_temp(cp_id, cp_desc, as_id, company_id, finyear, branch_id, doc_type, doc_date, 
			item_amt_tot, tax_amt_tot, nt_amt, rof_amt, sale_amt, 
			status, narration, amt_in_words, 
			cust_name, cust_state, cust_gstin, 
			cust_addr)
		Select 1, 'Original For Recipient', a.as_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.gross_debit_amt, COALESCE((a.annex_info->>'tax_amt')::numeric, 0), 0, COALESCE((a.annex_info->>'round_off_amt')::numeric, 0), a.debit_amt, 
			a.status, a.narration, a.amt_in_words, 
			b.account_head, d.gst_state_code || ' - ' || d.state_name as gst_state, a.annex_info->'gst_output_info'->>'customer_gstin',
			a.annex_info->'gst_output_info'->>'customer_address'
		From fa.as_control a
		Inner Join ac.account_head b On a.customer_id = b.account_id
        Inner Join tx.gst_state d On (a.annex_info->'gst_output_info'->>'customer_state_id')::BigInt = d.gst_state_id
		Where a.as_id=pas_id
		Union All
		Select 2, 'Triplicate For Supplier', a.as_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.gross_debit_amt, COALESCE((a.annex_info->>'tax_amt')::numeric, 0), 0, COALESCE((a.annex_info->>'round_off_amt')::numeric, 0), a.debit_amt, 
			a.status, a.narration, a.amt_in_words, 
			b.account_head, d.gst_state_code || ' - ' || d.state_name as gst_state, a.annex_info->'gst_output_info'->>'customer_gstin',
			a.annex_info->'gst_output_info'->>'customer_address'
		From fa.as_control a
		Inner Join ac.account_head b On a.customer_id = b.account_id
        Inner Join tx.gst_state d On (a.annex_info->'gst_output_info'->>'customer_state_id')::BigInt = d.gst_state_id
		Where a.as_id=pas_id;
	ElseIf pcp_option = 2 Then
		Insert Into inv_temp(cp_id, cp_desc, as_id, company_id, finyear, branch_id, doc_type, doc_date, 
			item_amt_tot, tax_amt_tot, nt_amt, rof_amt, sale_amt, 
			status, narration, amt_in_words, 
			cust_name, cust_state, cust_gstin, 
			cust_addr)
		Select 1, 'Duplicate For Transporter', a.as_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.gross_debit_amt, COALESCE((a.annex_info->>'tax_amt')::numeric, 0), 0, COALESCE((a.annex_info->>'round_off_amt')::numeric, 0), a.debit_amt, 
			a.status, a.narration, a.amt_in_words, 
			b.account_head, d.gst_state_code || ' - ' || d.state_name as gst_state, a.annex_info->'gst_output_info'->>'customer_gstin',
			a.annex_info->'gst_output_info'->>'customer_address'
		From fa.as_control a
        Inner Join tx.gst_state d On (a.annex_info->'gst_output_info'->>'customer_state_id')::BigInt = d.gst_state_id
		Where a.as_id=pas_id;
	End If;
	
	Return Query
	Select a.cp_id, a.cp_desc, a.as_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
		a.item_amt_tot, a.tax_amt_tot, a.nt_amt, a.rof_amt, a.sale_amt, 
		a.status, a.narration, a.amt_in_words, 
		a.cust_name, a.cust_state, a.cust_gstin, a.cust_addr
	From inv_temp a;
	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
create or replace function fa.sp_pocg_close_open_collection(pcompany_id bigint, pbranch_id bigint, psupplier_id bigint, ppo_status smallint, pto_date date)
RETURNS TABLE  
(	
	po_id varchar(50),	
	po_date date,
	supplier varchar(250),
	is_closed boolean,
	closed_by varchar(50),
	closed_reason varchar(250),
	closed_on date,
	po_amt numeric(18,4)
)
AS
$BODY$ 
Declare
	vYearBegin date;
Begin	
    -- Parameter ppo_status values
    -- 0 - Opened
    -- 1 - Closed
    -- 2 - All
    
    -- Fetch Year Begins based on AsOn Date
    Select a.year_begin into vYearBegin From sys.finyear a
    Where pto_date between a.year_begin and a.year_end;
    
	-- Generate Data
    Return Query
    With po_temp (po_id, po_date, supplier, is_closed, closed_by, closed_reason, 
		closed_on, po_amt)
    As
    (	Select a.ap_id, a.doc_date, b.supplier, a.is_closed, a.closed_by, a.closed_reason, 
		Case When a.is_closed Then a.closed_on Else '1970-01-01' End As closed_on, a.credit_amt
		From fa.ap_control a
		inner join ap.supplier b on a.account_id = b.supplier_id
		Where Case 
			When ppo_status = 0 Then 
			    (a.is_closed = false)
				And (a.ap_id not in (select distinct b.po_id from fa.ap_tran b))
			When ppo_status = 1 Then
			    (a.is_closed = true)
			When ppo_status = 2 Then
			    (a.doc_date between vYearBegin and pto_date)
				And (a.ap_id not in (select distinct b.po_id from fa.ap_tran b))
			End
                    And a.status = 5
                    And (a.branch_id = pbranch_id or pbranch_id = 0)
                    And a.company_id = pcompany_id
                    And a.doc_date <=pto_date
                    And (a.account_id = psupplier_id or psupplier_id = 0)
                    And a.doc_type = 'POCG'
	)
	Select a.po_id, a.po_date, a.supplier, a.is_closed, a.closed_by, a.closed_reason, a.closed_on, a.po_amt
	From po_temp a
    Order By a.po_date, a.po_id;
END
$BODY$
Language plpgsql;

?==?
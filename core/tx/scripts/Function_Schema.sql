CREATE OR REPLACE FUNCTION tx.fn_tax_tran_report(In pvoucher_id varchar(50))  
RETURNS TABLE
(
        voucher_id varchar(50), 
	tax_tran_id varchar(50),
	tax_schedule_id bigint,
	description varchar(250),
	tax_detail_id bigint,
	step_id bigint,
	account_id bigint,
	account_head varchar(250),
	tax_amt_fc numeric(18,4),
	tax_amt numeric(18,4),
	tax_perc numeric, 
	custom_rate numeric(18,4),
	include_in_lc boolean,
	account_affected_id bigint,
	account_head_affected varchar(250),
	supplier_paid boolean
) 
AS
$BODY$
BEGIN	
	return query
	select a.voucher_id, a.tax_tran_id, a.tax_schedule_id, a.description, a.tax_detail_id, 
	    d.step_id, a.account_id, b.account_head, a.tax_amt_fc, a.tax_amt, d.tax_perc, a.custom_rate, 
	    a.include_in_lc, a.account_affected_id, c.account_head As account_head_affected, a.supplier_paid 
	from tx.tax_tran a
        INNER JOIN tx.tax_detail d ON a.tax_detail_id = d.tax_detail_id
	LEFT JOIN ac.account_head b ON a.account_id = b.account_id
	LEFT JOIN ac.account_head c ON a.account_affected_id = c.account_id
	where a.voucher_id = pvoucher_id;      
END;
$BODY$
  LANGUAGE plpgsql;

?==?
Create or Replace function tx.fn_gtt_info(pvoucher_id varchar(50), ptran_group Varchar(50))
RETURNS TABLE  
(	
	voucher_id varchar(50),
	account_id bigint,
	tax_amt numeric(18,4)
)
AS
$BODY$ 
Begin	

	Drop table if exists gtt_temp;
	create temp table gtt_temp
	(
		voucher_id varchar(50),
		account_id bigint,
		tax_amt numeric(18,4)
	);
	-- Fetch Gtt tax tran
	with gtt 
	as 
	(	select a.voucher_id, sum(a.sgst_amt) as tax_amt, a.sgst_account_id as account_id
		from tx.gst_tax_tran a
		where a.voucher_id = pvoucher_ID And (a.tran_group = ptran_group or ptran_group = '')
		group by a.voucher_id, a.sgst_account_id
		union all
		select a.voucher_id, sum(a.cgst_amt), a.cgst_account_id
		from tx.gst_tax_tran a
		where a.voucher_id = pvoucher_ID And (a.tran_group = ptran_group or ptran_group = '')
		group by a.voucher_id, a.cgst_account_id
		union all
		select a.voucher_id, sum(a.igst_amt), a.igst_account_id
		from tx.gst_tax_tran a
		where a.voucher_id = pvoucher_ID And (a.tran_group = ptran_group or ptran_group = '')
		group by a.voucher_id, a.igst_account_id
		union all
		select a.voucher_id, sum(a.cess_amt), a.cess_account_id
		from tx.gst_tax_tran a
		where a.voucher_id = pvoucher_ID And (a.tran_group = ptran_group or ptran_group = '')
		group by a.voucher_id, a.cess_account_id
		
	)
	Insert into gtt_temp(voucher_id, account_id, tax_amt)
	Select a.voucher_id, a.account_id, sum(a.tax_amt)
	From gtt a
	group by a.voucher_id, a.account_id
	having sum(a.tax_amt) > 0; 
	
	return query 
	select a.voucher_id, a.account_id, a.tax_amt
	from gtt_temp a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
Drop function if exists tx.fn_gtt_itc_info(pvoucher_id varchar(50), ptran_group Varchar(50), prc_sec_id BigInt[]);

?==?
Create or Replace function tx.fn_gtt_itc_info(pvoucher_id varchar(50), ptran_group Varchar(50), prc_sec_id BigInt[], pfor_rs boolean = false)
RETURNS TABLE  
(	voucher_id varchar(50),
	account_id bigint,
	tax_amt numeric(18,4)
)
AS
$BODY$ 
Begin

	-- Fetch Gtt tax tran
    Return Query
	With gtt 
	As 
	(	Select a.voucher_id, Sum(a.sgst_amt) as tax_amt, a.sgst_itc_account_id as account_id
		From tx.gst_tax_tran a
		Where a.voucher_id = pvoucher_ID 
			And (a.tran_group = ptran_group or ptran_group = '') 
			And a.apply_itc And a.rc_sec_id = Any(prc_sec_id)
        	And case when pfor_rs then length(a.supplier_gstin) = 15 else 1=1 End
		Group by a.voucher_id, a.sgst_itc_account_id
		Union All
		select a.voucher_id, sum(a.cgst_amt), a.cgst_itc_account_id
		from tx.gst_tax_tran a
		where a.voucher_id = pvoucher_ID
            And (a.tran_group = ptran_group or ptran_group = '') 
            And a.apply_itc And a.rc_sec_id = Any(prc_sec_id)
        	And case when pfor_rs then length(a.supplier_gstin) = 15 else 1=1 End
		group by a.voucher_id, a.cgst_itc_account_id
		union all
		select a.voucher_id, sum(a.igst_amt), a.igst_itc_account_id
		from tx.gst_tax_tran a
		where a.voucher_id = pvoucher_ID
            And (a.tran_group = ptran_group or ptran_group = '') 
            And a.apply_itc And a.rc_sec_id = Any(prc_sec_id)
        	And case when pfor_rs then length(a.supplier_gstin) = 15 else 1=1 End
		group by a.voucher_id, a.igst_itc_account_id
		union all
		select a.voucher_id, sum(a.cess_amt), a.cess_itc_account_id
		from tx.gst_tax_tran a
		where a.voucher_id = pvoucher_ID
            And (a.tran_group = ptran_group or ptran_group = '') 
            And a.apply_itc And a.rc_sec_id = Any(prc_sec_id)
        	And case when pfor_rs then length(a.supplier_gstin) = 15 else 1=1 End
		group by a.voucher_id, a.cess_itc_account_id
		
	)
	Select a.voucher_id, a.account_id, sum(a.tax_amt)
	From gtt a
	group by a.voucher_id, a.account_id
	having sum(a.tax_amt) > 0;
    
END;
$BODY$
LANGUAGE plpgsql;

?==?
Drop function if exists tx.fn_gtt_rc_info(pvoucher_id varchar(50), ptran_group Varchar(50), prc_sec_id BigInt[]);

?==?
Create or Replace function tx.fn_gtt_rc_info(pvoucher_id varchar(50), ptran_group Varchar(50), prc_sec_id BigInt[], pfor_rs boolean = false)
RETURNS TABLE  
(	voucher_id varchar(50),
	account_id bigint,
	tax_amt numeric(18,4)
)
AS
$BODY$ 
Begin	

        -- Fetch Gtt tax tran
    Return Query
    with gtt 
    as 
    (       
        select a.voucher_id, sum(a.sgst_amt) as tax_amt, b.sgst_rc_account_id as account_id
		from tx.gst_tax_tran a
        Inner Join tx.gst_rate b On a.gst_rate_id = b.gst_rate_id
		where a.voucher_id = pvoucher_ID 
			And (a.tran_group = ptran_group or ptran_group = '') 
			And a.rc_sec_id = Any(prc_sec_id)
        	And case when pfor_rs then length(a.supplier_gstin) = 15 else 1=1 End
		group by a.voucher_id, b.sgst_rc_account_id
		union all
		select a.voucher_id, sum(a.cgst_amt), b.cgst_rc_account_id
		from tx.gst_tax_tran a
        Inner Join tx.gst_rate b On a.gst_rate_id = b.gst_rate_id
		where a.voucher_id = pvoucher_ID
			And (a.tran_group = ptran_group or ptran_group = '') 
			And a.rc_sec_id = Any(prc_sec_id)
        	And case when pfor_rs then length(a.supplier_gstin) = 15 else 1=1 End
		group by a.voucher_id, b.cgst_rc_account_id
		union all
		select a.voucher_id, sum(a.igst_amt), b.igst_rc_account_id
		from tx.gst_tax_tran a
        Inner Join tx.gst_rate b On a.gst_rate_id = b.gst_rate_id
		where a.voucher_id = pvoucher_ID
			And (a.tran_group = ptran_group or ptran_group = '') 
			And a.rc_sec_id = Any(prc_sec_id)
        	And case when pfor_rs then length(a.supplier_gstin) = 15 else 1=1 End
		group by a.voucher_id, b.igst_rc_account_id
		union all
		select a.voucher_id, sum(a.cess_amt), b.cess_rc_account_id
		from tx.gst_tax_tran a
        Inner Join tx.gst_rate b On a.gst_rate_id = b.gst_rate_id
		where a.voucher_id = pvoucher_ID
			And (a.tran_group = ptran_group or ptran_group = '') 
			And a.rc_sec_id = Any(prc_sec_id)
        	And case when pfor_rs then length(a.supplier_gstin) = 15 else 1=1 End
		group by a.voucher_id, b.cess_rc_account_id
	)
	Select a.voucher_id, a.account_id, sum(a.tax_amt)
	From gtt a
	group by a.voucher_id, a.account_id
	having sum(a.tax_amt) > 0; 
    
END;
$BODY$
LANGUAGE plpgsql;

?==?
Create Or Replace Function tx.fn_gst_exp_reco(pcompany_id BigInt, pbranch_id BigInt, paccount_id BigInt, 
                                              pfrom_date Date, pto_date Date, pgroup_path text)
Returns Table
(       txn_id uuid,
        doc_date Date,
        voucher_id Varchar(50),
        account_id BigInt,
        account_head Character Varying,
        txn_amt Numeric(18,4),
        non_gst_amt Numeric(18,4),
        exempt_amt Numeric(18,4),
        gst_paid_amt Numeric(18,4),
        gst_lt_amt Numeric(18,4),
        rc93_amt Numeric(18,4),
        rc93_lt_amt Numeric(18,4), 
        rc94_amt Numeric(18,4),
        itc_amt Numeric(18,4)
)
As
$BODY$
Declare
	vgst_nil_rate_ids BigInt[];
Begin

    Select array_agg(gst_rate_id) into vgst_nil_rate_ids
    From tx.gst_rate
    Where sgst_pcnt = 0 And cgst_pcnt = 0;

    Drop Table if Exists gl_txn_temp;
    Create Temp Table gl_txn_temp
    (	txn_id uuid Primary Key,
        doc_date Date,
        voucher_id Varchar(50),
        account_id BigInt,
     	account_head Character Varying,
        txn_amt Numeric(18,4),
     	non_gst_amt Numeric(18,4) Default(0),
     	exempt_amt Numeric(18,4) Default(0),
     	gst_paid_amt Numeric(18,4) Default(0),
     	gst_lt_amt Numeric(18,4) Default(0),
     	rc93_amt Numeric(18,4) Default(0),
     	rc93_lt_amt Numeric(18,4) Default(0),
     	rc94_amt Numeric(18,4) Default(0),
     	itc_amt Numeric(18,4) Default(0)
    );
	-- Step 1: Get txn summary for expenses from General Ledger
    With ac_list
    As
    (	Select a.account_id, a.account_head, a.account_type_id
        From ac.account_head a 
        Inner Join ac.account_group b On a.group_id = b.group_id
        Where b.group_path like Any(pgroup_path::Varchar[])
     		And (a.account_id = paccount_id Or paccount_id = 0)
    )
    Insert Into gl_txn_temp(txn_id, doc_date, voucher_id, account_id, account_head, txn_amt)
    Select md5(a.voucher_id || a.account_id)::uuid, a.doc_date, a.voucher_id, a.account_id, b.account_head, Sum(debit_amt - credit_amt) as txn_amt
    From ac.general_ledger a
    Inner Join ac_list b On a.account_id = b.account_id
    Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        And a.doc_date Between pfrom_date And pto_date
    Group by a.doc_date, a.voucher_id, a.account_id, b.account_head;

	-- Step 2: Get GST Expense Bills/Vouchers for exempt and gst items
    With gst_tax_txn
    As
    (	Select a.voucher_id, b.account_id, 
     		Sum(Case When c.gst_rate_id = Any(vgst_nil_rate_ids) Then c.bt_amt Else 0 End) as exempt_amt,
     		Sum(Case When c.gst_rate_id != All(vgst_nil_rate_ids) Then c.bt_amt Else 0 End) as gst_paid_amt,
     		Sum(Case When c.apply_itc = false Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as gst_lt_amt,
     		Sum(Case When c.apply_itc Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as itc_amt
     	From ac.vch_control a
     	Inner Join ac.vch_tran b On a.voucher_id = b.voucher_id
     	Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{PAYV, PAYC, PAYB}')
     		And c.is_rc = false
     	Group by a.voucher_id, b.account_id
     	Union All
     	Select a.bill_id, b.account_id, 
     		Sum(Case When c.gst_rate_id = Any(vgst_nil_rate_ids) Then c.bt_amt Else 0 End) as exempt_amt,
     		Sum(Case When c.gst_rate_id != All(vgst_nil_rate_ids) Then c.bt_amt Else 0 End) as gst_paid_amt,
     		Sum(Case When c.apply_itc = false Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as gst_lt_amt,
     		Sum(Case When c.apply_itc Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as itc_amt
     	From ap.bill_control a
     	Inner Join ap.bill_tran b On a.bill_id = b.bill_id
     	Inner Join tx.gst_tax_tran c On b.bill_tran_id = c.gst_tax_tran_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{BL2}')
     		And c.is_rc = false
     	Group by a.bill_id, b.account_id
        Union All
        Select a.voucher_id, b.account_id, 
     		Sum(Case When c.gst_rate_id = Any(vgst_nil_rate_ids) Then -c.bt_amt Else 0 End) as exempt_amt,
     		Sum(Case When c.gst_rate_id != All(vgst_nil_rate_ids) Then -c.bt_amt Else 0 End) as gst_paid_amt,
     		Sum(Case When c.apply_itc = false Then (c.sgst_amt+c.cgst_amt+c.igst_amt) * -1 Else 0 End) as gst_lt_amt,
     		Sum(Case When c.apply_itc Then (c.sgst_amt+c.cgst_amt+c.igst_amt) * -1 Else 0 End) as itc_amt
     	From ap.pymt_control a
     	Inner Join ap.pymt_tran b On a.voucher_id = b.voucher_id
     	Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{DN2}')
     		And c.is_rc = false
     	Group by a.voucher_id, b.account_id
    )
    Update gl_txn_temp a
    Set exempt_amt = b.exempt_amt,
    	gst_paid_amt = b.gst_paid_amt,
        gst_lt_amt = b.gst_lt_amt,
        itc_amt = a.itc_amt + b.itc_amt
	From gst_tax_txn b
    Where a.voucher_id = b.voucher_id And a.account_id = b.account_id;
    
    -- SPG in Stock Purchase
    With gst_tax_tran
    As
    (   Select a.stock_id, a.supplier_gstin, a.gst_rate_id, a.apply_itc, Sum(a.bt_amt) as bt_amt, Sum(a.sgst_amt) as sgst_amt,
            Sum(a.cgst_amt) as cgst_amt, Sum(a.igst_amt) as igst_amt
        From (  Select stock_id, (gtt->>'apply_itc')::Boolean apply_itc, (gtt->>'gst_rate_id')::BigInt gst_rate_id, 
                    (annex_info->'gst_input_info'->>'supplier_gstin')::Varchar supplier_gstin, 
                    (gtt->>'bt_amt')::Numeric bt_amt, (gtt->>'sgst_amt')::Numeric sgst_amt, (gtt->>'cgst_amt')::Numeric cgst_amt, 
                    (gtt->>'igst_amt')::Numeric igst_amt
                From st.stock_control, jsonb_array_elements(annex_info->'gst_tax_tran') gtt
                Where vat_type_id Between 400 And 499
                    And doc_type = Any ('{SPG}')
            ) a
        Group by a.stock_id, a.supplier_gstin, a.gst_rate_id, a.apply_itc
    ),
    gst_tax_txn
    As
    (   Select a.stock_id, a.sale_account_id, 
     		Sum(Case When c.gst_rate_id = Any(vgst_nil_rate_ids) Then c.bt_amt Else 0 End) as exempt_amt,
     		Sum(Case When length(c.supplier_gstin) = 15 And c.gst_rate_id != All(vgst_nil_rate_ids) Then c.bt_amt Else 0 End) as gst_paid_amt,
     		Sum(Case When length(c.supplier_gstin) = 2 And c.gst_rate_id != All(vgst_nil_rate_ids) Then c.bt_amt Else 0 End) as rc94_amt,
                Sum(Case When c.apply_itc = false Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as gst_lt_amt,
     		Sum(Case When c.apply_itc Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as itc_amt
     	From st.stock_control a
     	Inner Join gst_tax_tran c On a.stock_id = c.stock_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{SPG}')
     		--And c.is_rc = false
     	Group by a.stock_id, a.sale_account_id
    )
    Update gl_txn_temp a
    Set exempt_amt = b.exempt_amt,
    	gst_paid_amt = b.gst_paid_amt,
        gst_lt_amt = b.gst_lt_amt,
        rc94_amt = b.rc94_amt,
        itc_amt = a.itc_amt + b.itc_amt
	From gst_tax_txn b
    Where a.voucher_id = b.stock_id And a.account_id = b.sale_account_id;

    -- PRV in Stock Purchase
    With gst_tax_txn
    As
    (   Select a.stock_id, a.sale_account_id, 
     		Sum(Case When c.gst_rate_id = Any(vgst_nil_rate_ids) Then -c.bt_amt Else 0 End) as exempt_amt,
     		Sum(Case When c.gst_rate_id != All(vgst_nil_rate_ids) Then -c.bt_amt Else 0 End) as gst_paid_amt,
     		Sum(Case When c.apply_itc = false Then (c.sgst_amt+c.cgst_amt+c.igst_amt) * -1 Else 0 End) as gst_lt_amt,
     		Sum(Case When c.apply_itc Then (c.sgst_amt+c.cgst_amt+c.igst_amt) * -1 Else 0 End) as itc_amt
     	From st.stock_control a
     	Inner Join tx.gst_tax_tran c On a.stock_id = c.voucher_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{PRV}')
     		--And c.is_rc = false
     	Group by a.stock_id, a.sale_account_id
    )
    Update gl_txn_temp a
    Set exempt_amt = b.exempt_amt,
    	gst_paid_amt = b.gst_paid_amt,
        gst_lt_amt = b.gst_lt_amt,
        itc_amt = a.itc_amt + b.itc_amt
	From gst_tax_txn b
    Where a.voucher_id = b.stock_id And a.account_id = b.sale_account_id;
    
    -- Step 2: Get GST Expense Bills/Vouchers for reverse Charge Items
    With gst_rc_txn
    As
    (	Select a.voucher_id, b.account_id, 
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' in ('93', '53') Then c.bt_amt Else 0 End) as rc93_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' in ('93', '53') 
                    And length(coalesce(a.annex_info->'gst_input_info'->>'supplier_gstin', '')) = 15 
                    And c.apply_itc = false Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as rc93_lt_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' in ('94', '54') Then c.bt_amt Else 0 End) as rc94_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' in ('93', '53') 
                    And length(coalesce(a.annex_info->'gst_input_info'->>'supplier_gstin', '')) = 15
                    And c.apply_itc Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as itc_amt
     	From ac.vch_control a
     	Inner Join ac.vch_tran b On a.voucher_id = b.voucher_id
     	Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{PAYV, PAYC, PAYB}')
     		And c.is_rc
            And (a.annex_info->>'line_item_gst' is Null Or (a.annex_info->>'line_item_gst')::Boolean = false)
     	Group by a.voucher_id, b.account_id
     	Union All
        Select a.voucher_id, b.account_id, 
     		Sum(Case When c.rc_sec_id in ('93', '53') Then c.bt_amt Else 0 End) as rc93_amt,
     		Sum(Case When c.rc_sec_id in ('93', '53') 
                    And length(c.supplier_gstin) = 15 
                    And c.apply_itc = false Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as rc93_lt_amt,
     		Sum(Case When c.rc_sec_id in ('94', '54') Then c.bt_amt Else 0 End) as rc94_amt,
     		Sum(Case When c.rc_sec_id in ('93', '53') 
                    And length(c.supplier_gstin) = 15 
                    And c.apply_itc Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as itc_amt
     	From ac.vch_control a
     	Inner Join ac.vch_tran b On a.voucher_id = b.voucher_id
     	Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{PAYV, PAYC, PAYB}')
     		And c.is_rc
            And (a.annex_info->>'line_item_gst')::Boolean
     	Group by a.voucher_id, b.account_id
        Union All
     	Select a.bill_id, b.account_id, 
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' in ('93', '53') Then c.bt_amt Else 0 End) as rc93_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' in ('93', '53') 
                And length(coalesce(a.annex_info->'gst_input_info'->>'supplier_gstin', '')) = 15
                And c.apply_itc = false Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as rc93_lt_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id'in ('94', '54') Then c.bt_amt Else 0 End) as rc94_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' in ('93', '53') 
                And length(coalesce(a.annex_info->'gst_input_info'->>'supplier_gstin', '')) = 15
                And c.apply_itc Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as itc_amt
     	From ap.bill_control a
     	Inner Join ap.bill_tran b On a.bill_id = b.bill_id
     	Inner Join tx.gst_tax_tran c On b.bill_tran_id = c.gst_tax_tran_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{BL2}')
     		And c.is_rc
     	Group by a.bill_id, b.account_id
    )
    Update gl_txn_temp a
    Set rc93_amt = b.rc93_amt,
    	rc93_lt_amt = b.rc93_lt_amt,
    	rc94_amt = b.rc94_amt,
        itc_amt = a.itc_amt + b.itc_amt
	From gst_rc_txn b
    Where a.voucher_id = b.voucher_id And a.account_id = b.account_id;
      
    -- calculate non-gst
    Update gl_txn_temp a
    Set non_gst_amt = a.txn_amt - (a.exempt_amt + a.gst_paid_amt + a.gst_lt_amt + a.rc93_amt + a.rc93_lt_amt + a.rc94_amt);
    
    Return Query
    Select a.txn_id, a.doc_date, a.voucher_id, a.account_id, a.account_head, a.txn_amt, a.non_gst_amt, a.exempt_amt, a.gst_paid_amt, a.gst_lt_amt,
    		a.rc93_amt, a.rc93_lt_amt, a.rc94_amt, a.itc_amt
	From gl_txn_temp a;
    
End;
$BODY$
Language plpgsql;

?==?
Create Or Replace Function tx.fn_gst_exp_reco_v2(pcompany_id BigInt, pbranch_id BigInt, paccount_id BigInt, 
                                              pfrom_date Date, pto_date Date, pgroup_path text)
Returns Table
(       txn_id uuid,
        doc_date Date,
        voucher_id Varchar(50),
        account_id BigInt,
        account_head Character Varying,
        txn_amt Numeric(18,4),
        non_gst_amt Numeric(18,4),
        exempt_local_amt Numeric(18,4),
        exempt_inter_amt Numeric(18,4),
        gst_paid_amt Numeric(18,4),
        gst_lt_amt Numeric(18,4),
        rc93_amt Numeric(18,4),
        rc93_lt_amt Numeric(18,4), 
        rc94_amt Numeric(18,4),
        itc_amt Numeric(18,4)
)
As
$BODY$
Declare
	vgst_nil_rate_ids BigInt[]; vCompanyGroupBase bigint  = 0; vgst_state_id BigInt:= 0;
Begin
    
    Select  array_agg(gst_rate_id) into vgst_nil_rate_ids
    From tx.gst_rate
    Where sgst_pcnt = 0 And cgst_pcnt = 0;
    
    vCompanyGroupBase := (1000000 * pcompany_id) + 500000;
    if pbranch_id = 0 Then
        Select a.gst_state_id into vgst_state_id
        From sys.branch a Limit 1;
    elseif pbranch_id < vCompanyGroupBase Then
		Select a.gst_state_id into vgst_state_id
        From sys.branch a
        Where a.branch_id = pbranch_id;
	Elseif pbranch_id > vCompanyGroupBase then
		Select a.gst_state_id into vgst_state_id
        From sys.branch a
        Where a.branch_id = (pbranch_id - vCompanyGroupBase);
    Else
	End if; 

    Drop Table if Exists gl_txn_temp;
    Create Temp Table gl_txn_temp
    (	txn_id uuid Primary Key,
        doc_date Date,
        voucher_id Varchar(50),
        account_id BigInt,
     	account_head Character Varying,
        txn_amt Numeric(18,4),
     	non_gst_amt Numeric(18,4) Default(0),
     	exempt_local_amt Numeric(18,4) Default(0),
        exempt_inter_amt Numeric(18,4) Default(0),
     	gst_paid_amt Numeric(18,4) Default(0),
     	gst_lt_amt Numeric(18,4) Default(0),
     	rc93_amt Numeric(18,4) Default(0),
     	rc93_lt_amt Numeric(18,4) Default(0),
     	rc94_amt Numeric(18,4) Default(0),
     	itc_amt Numeric(18,4) Default(0)
    );
	-- Step 1: Get txn summary for expenses from General Ledger
    With ac_list
    As
    (	Select a.account_id, a.account_head, a.account_type_id
        From ac.account_head a 
        Inner Join ac.account_group b On a.group_id = b.group_id
        Where b.group_path like Any(pgroup_path::Varchar[])
     		And (a.account_id = paccount_id Or paccount_id = 0)
    )
    Insert Into gl_txn_temp(txn_id, doc_date, voucher_id, account_id, account_head, txn_amt)
    Select md5(a.voucher_id || a.account_id)::uuid, a.doc_date, a.voucher_id, a.account_id, b.account_head, Sum(debit_amt - credit_amt) as txn_amt
    From ac.general_ledger a
    Inner Join ac_list b On a.account_id = b.account_id
    Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        And a.doc_date Between pfrom_date And pto_date
    Group by a.doc_date, a.voucher_id, a.account_id, b.account_head;

	-- Step 2: Get GST Expense Bills/Vouchers for exempt and gst items
    With gst_tax_txn
    As
    (	Select a.voucher_id, b.account_id,
            Sum(Case When (a.annex_info->'gst_input_info'->>'supplier_state_id')::BigInt = vgst_state_id
                        And c.gst_rate_id = Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' 
                        And ((a.annex_info->'gst_input_info'->>'is_ctp')::Boolean = false Or a.annex_info->'gst_input_info'->'is_ctp' Is Null) Then c.bt_amt Else 0 End) as exempt_local_amt,
            Sum(Case When (a.annex_info->'gst_input_info'->>'supplier_state_id')::BigInt != vgst_state_id
                        And c.gst_rate_id = Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then c.bt_amt Else 0 End) as exempt_inter_amt,
            Sum(Case When c.gst_rate_id != All(vgst_nil_rate_ids) or a.annex_info->'gst_input_info'->>'is_ctp' = 'true' Then c.bt_amt Else 0 End) as gst_paid_amt,
            Sum(Case When c.apply_itc = false Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as gst_lt_amt,
            Sum(Case When c.apply_itc Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as itc_amt
     	From ac.vch_control a
     	Inner Join ac.vch_tran b On a.voucher_id = b.voucher_id
     	Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{PAYV, PAYC, PAYB}')
     		And c.is_rc = false
     	Group by a.voucher_id, b.account_id
     	Union All
     	Select a.bill_id, b.account_id, 
     		Sum(Case When (a.annex_info->'gst_input_info'->>'supplier_state_id')::BigInt = vgst_state_id
                        And c.gst_rate_id = Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then c.bt_amt Else 0 End) as exempt_local_amt,
            Sum(Case When (a.annex_info->'gst_input_info'->>'supplier_state_id')::BigInt != vgst_state_id
                        And c.gst_rate_id = Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then c.bt_amt Else 0 End) as exempt_inter_amt,
     		Sum(Case When c.gst_rate_id != All(vgst_nil_rate_ids) Then c.bt_amt Else 0 End) as gst_paid_amt,
     		Sum(Case When c.apply_itc = false Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as gst_lt_amt,
     		Sum(Case When c.apply_itc Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as itc_amt
     	From ap.bill_control a
     	Inner Join ap.bill_tran b On a.bill_id = b.bill_id
     	Inner Join tx.gst_tax_tran c On b.bill_tran_id = c.gst_tax_tran_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{BL2,ABM2}')
     		And c.is_rc = false
     	Group by a.bill_id, b.account_id
        Union All
        Select a.voucher_id, b.account_id, 
     		Sum(Case When (a.annex_info->'gst_input_info'->>'supplier_state_id')::BigInt = vgst_state_id
                        And c.gst_rate_id = Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then -c.bt_amt Else 0 End) as exempt_local_amt,
            Sum(Case When (a.annex_info->'gst_input_info'->>'supplier_state_id')::BigInt != vgst_state_id
                        And c.gst_rate_id = Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then -c.bt_amt Else 0 End) as exempt_inter_amt,
     		Sum(Case When c.gst_rate_id != All(vgst_nil_rate_ids) Then -c.bt_amt Else 0 End) as gst_paid_amt,
     		Sum(Case When c.apply_itc = false Then (c.sgst_amt+c.cgst_amt+c.igst_amt) * -1 Else 0 End) as gst_lt_amt,
     		Sum(Case When c.apply_itc Then (c.sgst_amt+c.cgst_amt+c.igst_amt) * -1 Else 0 End) as itc_amt
     	From ap.pymt_control a
     	Inner Join ap.pymt_tran b On a.voucher_id = b.voucher_id
     	Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{DN2}')
     		And c.is_rc = false
     	Group by a.voucher_id, b.account_id
    )
    Update gl_txn_temp a
    Set exempt_local_amt = b.exempt_local_amt,
        exempt_inter_amt = b.exempt_inter_amt,
    	gst_paid_amt = b.gst_paid_amt,
        gst_lt_amt = b.gst_lt_amt,
        itc_amt = a.itc_amt + b.itc_amt
	From gst_tax_txn b
    Where a.voucher_id = b.voucher_id And a.account_id = b.account_id;
    
    If exists (SELECT * FROM information_schema.tables where table_schema='pub' And table_name = 'abp_control') then
    	With gst_tax_txn
        As
        (
            	Select a.voucher_id, b.account_id, 
                    Sum(Case When (a.annex_info->'gst_input_info'->>'supplier_state_id')::BigInt = vgst_state_id
                                And c.gst_rate_id = Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then c.bt_amt Else 0 End) as exempt_local_amt,
                    Sum(Case When (a.annex_info->'gst_input_info'->>'supplier_state_id')::BigInt != vgst_state_id
                                And c.gst_rate_id = Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then c.bt_amt Else 0 End) as exempt_inter_amt,
                    Sum(Case When c.gst_rate_id != All(vgst_nil_rate_ids) Then c.bt_amt Else 0 End) as gst_paid_amt,
                    Sum(Case When c.apply_itc = false Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as gst_lt_amt,
                    Sum(Case When c.apply_itc Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as itc_amt
                From pub.abp_control a
                Inner Join pub.abp_tran b On a.voucher_id = b.voucher_id
                Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
                Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
                    And a.doc_date Between pfrom_date And pto_date
                    And c.is_rc = false
                Group by a.voucher_id, b.account_id
            )
        Update gl_txn_temp a
        Set exempt_local_amt = b.exempt_local_amt,
            exempt_inter_amt = b.exempt_inter_amt,
            gst_paid_amt = b.gst_paid_amt,
            gst_lt_amt = b.gst_lt_amt,
            itc_amt = a.itc_amt + b.itc_amt
        From gst_tax_txn b
        Where a.voucher_id = b.voucher_id And a.account_id = b.account_id;
    End If;
    
    -- SPG in Stock Purchase
    With gst_tax_tran
    As
    (   Select a.stock_id, a.supplier_gstin, a.gst_rate_id, a.apply_itc, a.hsn_sc_code, Sum(a.bt_amt) as bt_amt, Sum(a.sgst_amt) as sgst_amt,
            Sum(a.cgst_amt) as cgst_amt, Sum(a.igst_amt) as igst_amt
        From (  Select stock_id, (gtt->>'apply_itc')::Boolean apply_itc, (gtt->>'gst_rate_id')::BigInt gst_rate_id, 
                    (annex_info->'gst_input_info'->>'supplier_gstin')::Varchar supplier_gstin, 
                    (gtt->>'hsn_sc_code')::Varchar hsn_sc_code,
                    (gtt->>'bt_amt')::Numeric bt_amt, (gtt->>'sgst_amt')::Numeric sgst_amt, (gtt->>'cgst_amt')::Numeric cgst_amt, 
                    (gtt->>'igst_amt')::Numeric igst_amt
                From st.stock_control, jsonb_array_elements(annex_info->'gst_tax_tran') gtt
                Where vat_type_id Between 400 And 499
                    And doc_type = Any ('{SPG}')
            ) a
        Group by a.stock_id, a.supplier_gstin, a.gst_rate_id, a.apply_itc, a.hsn_sc_code
    ),
    gst_tax_txn
    As
    (   Select a.stock_id, a.sale_account_id, 
     		Sum(Case When (a.annex_info->'gst_input_info'->>'supplier_state_id')::BigInt = vgst_state_id
                        And c.gst_rate_id = Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then c.bt_amt Else 0 End) as exempt_local_amt,
            Sum(Case When (a.annex_info->'gst_input_info'->>'supplier_state_id')::BigInt != vgst_state_id
                        And c.gst_rate_id = Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then c.bt_amt Else 0 End) as exempt_inter_amt,
     		Sum(Case When c.gst_rate_id != All(vgst_nil_rate_ids)
                        And length(c.supplier_gstin) = 15 Then c.bt_amt Else 0 End) as gst_paid_amt,
            Sum(Case When c.gst_rate_id != All(vgst_nil_rate_ids)
                        And length(c.supplier_gstin) = 2 Then c.bt_amt Else 0 End) as rc94_amt,
     		Sum(Case When c.apply_itc = false
                    And length(c.supplier_gstin) > 2 Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as gst_lt_amt,
     		Sum(Case When c.apply_itc
                    And length(c.supplier_gstin) > 2 Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as itc_amt
     	From st.stock_control a
     	Inner Join gst_tax_tran c On a.stock_id = c.stock_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{SPG}')
     		--And c.is_rc = false
     	Group by a.stock_id, a.sale_account_id
    )
    Update gl_txn_temp a
    Set exempt_local_amt = b.exempt_local_amt,
        exempt_inter_amt = b.exempt_inter_amt,
    	gst_paid_amt = b.gst_paid_amt,
        gst_lt_amt = b.gst_lt_amt,
        rc94_amt = b.rc94_amt,
        itc_amt = a.itc_amt + b.itc_amt
	From gst_tax_txn b
    Where a.voucher_id = b.stock_id And a.account_id = b.sale_account_id;

    -- PRV in Stock Purchase
    With gst_tax_txn
    As
    (   Select a.stock_id, a.sale_account_id, 
     		Sum(Case When (a.annex_info->'gst_input_info'->>'supplier_state_id')::BigInt = vgst_state_id
                        And c.gst_rate_id = Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then -c.bt_amt Else 0 End) as exempt_local_amt,
            Sum(Case When (a.annex_info->'gst_input_info'->>'supplier_state_id')::BigInt != vgst_state_id
                        And c.gst_rate_id = Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then -c.bt_amt Else 0 End) as exempt_inter_amt,
     		Sum(Case When c.gst_rate_id != All(vgst_nil_rate_ids) Then -c.bt_amt Else 0 End) as gst_paid_amt,
     		Sum(Case When c.apply_itc = false Then (c.sgst_amt+c.cgst_amt+c.igst_amt) * -1 Else 0 End) as gst_lt_amt,
     		Sum(Case When c.apply_itc Then (c.sgst_amt+c.cgst_amt+c.igst_amt) * -1 Else 0 End) as itc_amt
     	From st.stock_control a
     	Inner Join tx.gst_tax_tran c On a.stock_id = c.voucher_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{PRV}')
     		--And c.is_rc = false
     	Group by a.stock_id, a.sale_account_id
    )
    Update gl_txn_temp a
    Set exempt_local_amt = b.exempt_local_amt,
        exempt_inter_amt = b.exempt_inter_amt,
    	gst_paid_amt = b.gst_paid_amt,
        gst_lt_amt = b.gst_lt_amt,
        itc_amt = a.itc_amt + b.itc_amt
	From gst_tax_txn b
    Where a.voucher_id = b.stock_id And a.account_id = b.sale_account_id;
    
    -- Step 2: Get GST Expense Bills/Vouchers for reverse Charge Items
    With gst_rc_txn
    As
    (	Select a.voucher_id, b.account_id, 
            Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' in ('93', '53', '94', '54') 
                    And c.gst_rate_id = Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then c.bt_amt Else 0 End) as exempt_local_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' in ('93', '53')
                    And c.gst_rate_id != Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then c.bt_amt Else 0 End) as rc93_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' in ('93', '53') 
                    And c.gst_rate_id != Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST'
                    And length(coalesce(a.annex_info->'gst_input_info'->>'supplier_gstin', '')) = 15 
                    And c.apply_itc = false Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as rc93_lt_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' in ('94', '54')
                    And c.gst_rate_id != Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then c.bt_amt Else 0 End) as rc94_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' in ('93', '53') 
                    And c.gst_rate_id != Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST'
                    And length(coalesce(a.annex_info->'gst_input_info'->>'supplier_gstin', '')) = 15
                    And c.apply_itc Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as itc_amt
     	From ac.vch_control a
     	Inner Join ac.vch_tran b On a.voucher_id = b.voucher_id
     	Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{PAYV, PAYC, PAYB}')
     		And c.is_rc
            And (a.annex_info->>'line_item_gst' is Null Or (a.annex_info->>'line_item_gst')::Boolean = false)
     	Group by a.voucher_id, b.account_id
     	Union All
        Select a.voucher_id, b.account_id, 
            Sum(Case When c.rc_sec_id in (93, 53, 94, 54) 
                    And c.gst_rate_id = Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then c.bt_amt Else 0 End) as exempt_local_amt,
     		Sum(Case When c.rc_sec_id in (93, 53) 
                    And c.gst_rate_id != Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then c.bt_amt Else 0 End) as rc93_amt,
     		Sum(Case When c.rc_sec_id in (93, 53)  
                    And c.gst_rate_id != Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST'
                    And length(c.supplier_gstin) = 15 
                    And c.apply_itc = false Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as rc93_lt_amt,
     		Sum(Case When c.rc_sec_id in (94, 54)
                    And c.gst_rate_id != Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then c.bt_amt Else 0 End) as rc94_amt,
     		Sum(Case When c.rc_sec_id in (93, 53) 
                    And c.gst_rate_id != Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' 
                    And length(c.supplier_gstin) = 15 
                    And c.apply_itc Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as itc_amt
     	From ac.vch_control a
     	Inner Join ac.vch_tran b On a.voucher_id = b.voucher_id
     	Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{PAYV, PAYC, PAYB}')
     		And c.is_rc
            And (a.annex_info->>'line_item_gst')::Boolean
     	Group by a.voucher_id, b.account_id
        Union All
     	Select a.bill_id, b.account_id, 
            Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' in ('93', '53', '94', '54') 
                    And c.gst_rate_id = Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then c.bt_amt Else 0 End) as exempt_local_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' in ('93', '53')
                    And c.gst_rate_id != Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then c.bt_amt Else 0 End) as rc93_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' in ('93', '53') 
                    And c.gst_rate_id != Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST'
                    And length(coalesce(a.annex_info->'gst_input_info'->>'supplier_gstin', '')) = 15
                    And c.apply_itc = false Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as rc93_lt_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id'in ('94', '54')
                    And c.gst_rate_id != Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST' Then c.bt_amt Else 0 End) as rc94_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' in ('93', '53') 
                    And c.gst_rate_id != Any(vgst_nil_rate_ids) And c.hsn_sc_code != 'NONGST'
                    And length(coalesce(a.annex_info->'gst_input_info'->>'supplier_gstin', '')) = 15
                    And c.apply_itc Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as itc_amt
     	From ap.bill_control a
     	Inner Join ap.bill_tran b On a.bill_id = b.bill_id
     	Inner Join tx.gst_tax_tran c On b.bill_tran_id = c.gst_tax_tran_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{BL2}')
     		And c.is_rc
     	Group by a.bill_id, b.account_id
    )
    Update gl_txn_temp a
    Set exempt_local_amt = b.exempt_local_amt,
        rc93_amt = b.rc93_amt,
    	rc93_lt_amt = b.rc93_lt_amt,
    	rc94_amt = b.rc94_amt,
        itc_amt = a.itc_amt + b.itc_amt
	From gst_rc_txn b
    Where a.voucher_id = b.voucher_id And a.account_id = b.account_id;
      
    -- calculate non-gst
    Update gl_txn_temp a
    Set non_gst_amt = a.txn_amt - (a.exempt_local_amt + a.exempt_inter_amt + a.gst_paid_amt + a.gst_lt_amt + a.rc93_amt + a.rc93_lt_amt + a.rc94_amt);
    
    Return Query
    Select a.txn_id, a.doc_date, a.voucher_id, a.account_id, a.account_head, a.txn_amt, a.non_gst_amt, 
            a.exempt_local_amt, a.exempt_inter_amt, a.gst_paid_amt, a.gst_lt_amt,
    		a.rc93_amt, a.rc93_lt_amt, a.rc94_amt, a.itc_amt
	From gl_txn_temp a;
    
End;
$BODY$
Language plpgsql;

?==?
Create Or Replace Function tx.fn_gst_rc_nonexp_reco(pcompany_id BigInt, pbranch_id BigInt, paccount_id BigInt, pfrom_date Date, pto_date Date)
Returns Table
(       doc_date Date,
        voucher_id Varchar(50),
        account_id BigInt,
        account_head Character Varying,
        exempt_amt Numeric(18,4),
        gst_paid_amt Numeric(18,4),
        gst_lt_amt Numeric(18,4),
        rc93_amt Numeric(18,4),
        rc93_lt_amt Numeric(18,4),
        rc94_amt Numeric(18,4),
        itc_amt Numeric(18,4)
)
As
$BODY$
Declare
	vgst_nil_rate_ids BigInt[];
Begin

    Select  array_agg(gst_rate_id) into vgst_nil_rate_ids
    From tx.gst_rate
    Where sgst_pcnt = 0 And cgst_pcnt = 0;

    Drop Table if Exists gl_rc_temp;
    Create Temp Table gl_rc_temp
    (	doc_date Date,
        voucher_id Varchar(50),
        account_id BigInt,
     	account_head Character Varying,
     	exempt_amt Numeric(18,4) Default(0),
     	gst_paid_amt Numeric(18,4) Default(0),
     	gst_lt_amt Numeric(18,4) Default(0),
     	rc93_amt Numeric(18,4) Default(0),
     	rc93_lt_amt Numeric(18,4) Default(0),
     	rc94_amt Numeric(18,4) Default(0),
     	itc_amt Numeric(18,4) Default(0)
    );
	-- Step 1: Get txn summary for expenses from General Ledger
    With ac_list
    As
    (	Select a.account_id, a.account_head, a.account_type_id
        From ac.account_head a 
        Inner Join ac.account_group b On a.group_id = b.group_id
        Where b.group_path not like All('{A005%,A006%}')
     		And (a.account_id = paccount_id Or paccount_id = 0)
    )
    Insert Into gl_rc_temp(doc_date, voucher_id, account_id, account_head, exempt_amt, gst_paid_amt, gst_lt_amt, itc_amt)
    Select a.doc_date, a.voucher_id, b.account_id, d.account_head,
     		Sum(Case When c.gst_rate_id = Any(vgst_nil_rate_ids) Then c.bt_amt Else 0 End) as exempt_amt,
     		Sum(Case When c.gst_rate_id != All(vgst_nil_rate_ids) Then c.bt_amt Else 0 End) as gst_paid_amt,
     		Sum(Case When c.apply_itc = false Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as gst_lt_amt,
     		Sum(Case When c.apply_itc Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as itc_amt
     	From ac.vch_control a
     	Inner Join ac.vch_tran b On a.voucher_id = b.voucher_id
     	Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
     	Inner Join ac_list d On b.account_id = d.account_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{PAYV, PAYC, PAYB}')
     		And c.is_rc = false
     	Group by a.doc_date, a.voucher_id, b.account_id, d.account_head
     	Union All
     	Select a.doc_date, a.bill_id, b.account_id, d.account_head,
     		Sum(Case When c.gst_rate_id = Any(vgst_nil_rate_ids) Then c.bt_amt Else 0 End) as exempt_amt,
     		Sum(Case When c.gst_rate_id != All(vgst_nil_rate_ids) Then c.bt_amt Else 0 End) as gst_paid_amt,
     		Sum(Case When c.apply_itc = false Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as gst_lt_amt,
     		Sum(Case When c.apply_itc Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as itc_amt
     	From ap.bill_control a
     	Inner Join ap.bill_tran b On a.bill_id = b.bill_id
     	Inner Join tx.gst_tax_tran c On b.bill_tran_id = c.gst_tax_tran_id
     	Inner Join ac_list d On b.account_id = d.account_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{BL2}')
     		And c.is_rc = false
     	Group by a.doc_date, a.bill_id, b.account_id, d.account_head;
        
    -- Step 2: Get GST Expense Bills/Vouchers for reverse Charge Items
    With ac_list
    As
    (	Select a.account_id, a.account_head, a.account_type_id
        From ac.account_head a 
        Inner Join ac.account_group b On a.group_id = b.group_id
        Where b.group_path not like All('{A005%, A006%}')
     		And (a.account_id = paccount_id Or paccount_id = 0)
    )
    Insert Into gl_rc_temp(doc_date, voucher_id, account_id, account_head, exempt_amt, rc93_amt, rc93_lt_amt, rc94_amt, itc_amt)
    Select a.doc_date, a.voucher_id, b.account_id, d.account_head,
                Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' In ('93', '53')
                        And c.gst_rate_id = Any(vgst_nil_rate_ids) Then c.bt_amt Else 0 End) as exempt_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' In ('93', '53')
                        And c.gst_rate_id != Any(vgst_nil_rate_ids) Then c.bt_amt Else 0 End) as rc93_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' In ('93', '53') And c.apply_itc = false Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as rc93_lt_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' In ('94', '54') Then c.bt_amt Else 0 End) as rc94_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' In ('93', '53') And c.apply_itc Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as itc_amt
     	From ac.vch_control a
     	Inner Join ac.vch_tran b On a.voucher_id = b.voucher_id
     	Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
        Inner Join ac_list d On b.account_id = d.account_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{PAYV, PAYC, PAYB}')
     		And c.is_rc
            And (a.annex_info->>'line_item_gst' is Null Or (a.annex_info->>'line_item_gst')::Boolean = false)
     	Group by a.doc_date, a.voucher_id, b.account_id, d.account_head
        Union All
        Select a.doc_date, a.voucher_id, b.account_id, d.account_head,
                Sum(Case When c.rc_sec_id In ('93', '53')
                        And c.gst_rate_id = Any(vgst_nil_rate_ids) Then c.bt_amt Else 0 End) as exempt_amt,
     		Sum(Case When c.rc_sec_id In ('93', '53')
                        And c.gst_rate_id != Any(vgst_nil_rate_ids) Then c.bt_amt Else 0 End) as rc93_amt,
     		Sum(Case When c.rc_sec_id In ('93', '53') And c.apply_itc = false Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as rc93_lt_amt,
     		Sum(Case When c.rc_sec_id In ('94', '54') Then c.bt_amt Else 0 End) as rc94_amt,
     		Sum(Case When c.rc_sec_id In ('93', '53') And c.apply_itc Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as itc_amt
     	From ac.vch_control a
     	Inner Join ac.vch_tran b On a.voucher_id = b.voucher_id
     	Inner Join tx.gst_tax_tran c On b.vch_tran_id = c.gst_tax_tran_id
        Inner Join ac_list d On b.account_id = d.account_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{PAYV, PAYC, PAYB}')
     		And c.is_rc
            And (a.annex_info->>'line_item_gst')::Boolean
     	Group by a.doc_date, a.voucher_id, b.account_id, d.account_head
     	Union All
     	Select a.doc_date, a.bill_id, b.account_id, d.account_head,
                Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' In ('93', '53')
                        And c.gst_rate_id = Any(vgst_nil_rate_ids) Then c.bt_amt Else 0 End) as exempt_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' In ('93', '53')
                        And c.gst_rate_id != Any(vgst_nil_rate_ids) Then c.bt_amt Else 0 End) as rc93_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' In ('93', '53') And c.apply_itc = false Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as rc93_lt_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' In ('94', '54') Then c.bt_amt Else 0 End) as rc94_amt,
     		Sum(Case When a.annex_info->'gst_rc_info'->>'rc_sec_id' In ('93', '53') And c.apply_itc Then c.sgst_amt+c.cgst_amt+c.igst_amt Else 0 End) as itc_amt
     	From ap.bill_control a
     	Inner Join ap.bill_tran b On a.bill_id = b.bill_id
     	Inner Join tx.gst_tax_tran c On b.bill_tran_id = c.gst_tax_tran_id
        Inner Join ac_list d On b.account_id = d.account_id
     	Where (a.branch_id In (select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) Or pbranch_id = 0)
        	And a.doc_date Between pfrom_date And pto_date
     		And a.doc_type = Any('{BL2}')
     		And c.is_rc
     	Group by a.doc_date, a.bill_id, b.account_id, d.account_head;
    
    Return Query
    Select a.doc_date, a.voucher_id, a.account_id, a.account_head, Sum(a.exempt_amt), Sum(a.gst_paid_amt), Sum(a.gst_lt_amt),
    		Sum(a.rc93_amt), Sum(a.rc93_lt_amt), Sum(a.rc94_amt), Sum(a.itc_amt)
	From gl_rc_temp a
    Group by a.doc_date, a.voucher_id, a.account_id, a.account_head
    Order By a.doc_date, a.voucher_id, a.account_id, a.account_head;
    
End;
$BODY$
Language plpgsql;

?==?

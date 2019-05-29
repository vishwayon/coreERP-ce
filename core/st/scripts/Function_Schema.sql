CREATE OR REPLACE FUNCTION st.fn_stock_info_for_gl_post(IN pvoucher_id character varying)
  RETURNS TABLE(index integer, company_id bigint, branch_id bigint, dc character, account_id bigint, debit_amt_fc numeric, credit_amt_fc numeric, debit_amt numeric, credit_amt numeric, remarks character varying) AS
$BODY$ 
	Declare vDiscountFC numeric(18,4)=0; vDiscount numeric(18,4)= 0; vTotalDebitFC numeric(18,4) = 0; vTotalDebit numeric(18,4) =0; vReference_ID varchar(50);
		vTotalCreditFC numeric(18,4) = 0; vTotalCredit numeric(18,4) =0;
		vMatTotalFC numeric(18,4) = 0; vMatTotal numeric(18,4) = 0;
		vCompany_ID bigint =-1; vBranch_ID bigint = -1; vAccount_ID bigint =-1; vSaleAccount_ID bigint =-1; vTotalLCFC numeric(18,4) =0; vTotalLC numeric(18,4) = 0;
		vDiffFC numeric(18,4) =0; vDiff numeric(18,4) =0; vDocType varchar(4); vTempDebitAmt numeric(18,4);
		vTaxFC numeric(18,4) = 0; vTax numeric(18,4) = 0; vdcn_type Int:= 0;
		vLCAmtFC numeric(18,4) = 0; vLCAmt numeric(18,4) = 0; vRoundOffAcc_ID bigint = -1;
		vIs_purchase_Tax boolean = false; 
                vBB_adj_account_id BigInt:=-1;	vBB_purchase_account_id BigInt:= -1; vBB_tax_account_id BigInt:=-1;
Begin	
	-- This function is used by the Posting Trigger to get information on the Supplier Payment (PYMT)
	DROP TABLE IF EXISTS stock_vch_detail;	
	create temp TABLE  stock_vch_detail
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
	-- *****	Step 1: Fetch summary of transaction for every Account in a temp table
	Select a.company_id, a.account_id, a.doc_type, a.branch_id, a.sale_account_id, (a.annex_info->>'is_purchase_tax')::boolean
		into vCompany_ID, vAccount_ID,  vDocType, vBranch_ID, vSaleAccount_ID, vIs_purchase_Tax
	From st.stock_control a
	where stock_id=replace(pvoucher_id, ':BB', '');

	If vDocType in ('SP', 'SPG') then
		-- *****	Group A: Credits
		-- *****	Step 1: Get Supplier Credit
		If Exists (Select * From st.stock_control Where stock_id=pvoucher_id And vat_type_id = 205) Then
			-- This is a URD purchase. Therefore, supplier is net of tax
			Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
			Select vCompany_ID, vBranch_ID, 'C', a.account_id, 0, a.total_amt_fc, 0, a.total_amt - a.tax_amt, 'Purchase Amt'
			from st.stock_control a
			where a.stock_id=pvoucher_id;
		Else
			Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
			Select vCompany_ID, vBranch_ID, 'C', a.account_id, 0, a.total_amt_fc, 0, a.total_amt, 'Purchase Amt'
			from st.stock_control a
			where a.stock_id=pvoucher_id;
		End If;

		-- *****	Step 2: Get round off with -ve case
		Select cast(value as bigint) into vRoundOffAcc_ID from sys.settings where key='st_round_off_account';
		Insert into stock_vch_detail(company_id, branch_id, dc, account_id, 
			debit_amt_fc, credit_amt_fc, 
			debit_amt, credit_amt, 
			remarks)
		Select vCompany_ID, vBranch_ID, case when a.round_off_amt < 0 Then 'C' Else 'D' End, vRoundOffAcc_ID, 
			case when a.round_off_amt_fc < 0 Then 0 Else -a.round_off_amt_fc End, case when a.round_off_amt_fc > 0 Then a.round_off_amt_fc Else 0 End, 
			case when a.round_off_amt > 0 Then a.round_off_amt Else 0 End, case when a.round_off_amt < 0 Then -a.round_off_amt Else 0 End, 
			'Round Off Amt'
		from st.stock_control a
		where a.stock_id=pvoucher_id And (a.round_off_amt_fc != 0 Or a.round_off_amt != 0);

		-- *****	Group B: Debits
		if vIs_purchase_Tax Then
			-- *****	Step 1: Basic value of items
			Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
				debit_amt, credit_amt, remarks)
			Select vCompany_ID, vBranch_ID, 'D', vSaleAccount_ID, sum(b.item_amt_fc), 0, 
				sum(b.bt_amt) - min(a.disc_amt), 0, 'Line Items'
			from st.stock_control a
			Inner Join st.stock_tran b On a.stock_id = b.stock_id
			where a.stock_id=pvoucher_id;

			-- *****	Step 2: Purchase Tax with ITC
			With purchase_tax
			As
			(	Select a.stock_id, 
					(p_tax->>'apply_itc')::boolean apply_itc,  
					(p_tax->>'tax_schedule_id')::BigInt tax_schedule_id,
					(p_tax->>'tax_amt')::Numeric tax_amt
				from st.stock_control a, jsonb_array_elements(annex_info->'purchase_tax') p_tax
				Where a.stock_id=pvoucher_id
			)
			Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
			Select vCompany_ID, vBranch_ID, 'D', b.account_id, 0, 0, sum(a.tax_amt), 0, 'Line Items Taxes ITC'
			from purchase_tax a
			Inner Join tx.tax_detail b On a.tax_schedule_id=b.tax_schedule_id And b.step_id=1
			Where a.stock_id=pvoucher_id And a.apply_itc = true
			Group by b.account_id;

			-- *****	Step 2: Purchase Tax without ITC
			With purchase_tax
			As
			(	Select a.stock_id, 
					(p_tax->>'apply_itc')::boolean apply_itc,  
					(p_tax->>'tax_schedule_id')::BigInt tax_schedule_id,
					(p_tax->>'tax_amt')::Numeric tax_amt
				from st.stock_control a, jsonb_array_elements(annex_info->'purchase_tax') p_tax
				Where a.stock_id=pvoucher_id
			)
			Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
			Select vCompany_ID, vBranch_ID, 'D', vSaleAccount_ID, 0, 0, sum(a.tax_amt), 0, 'Line Items Taxes Loaded (without ITC)'
			from purchase_tax a
			Inner Join tx.tax_detail b On a.tax_schedule_id=b.tax_schedule_id And b.step_id=1
			Where a.stock_id=pvoucher_id And a.apply_itc = false
			Group by b.account_id;

			-- if URD, then an additional debit is accommodated for purchase tax asset
			If Exists (Select * From st.stock_control Where stock_id=pvoucher_id And vat_type_id = 205) Then
				Select cast(value as bigint) into vBB_tax_account_id from sys.settings where key='bb_tax_account';
				Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
				Select vCompany_ID, vBranch_ID, 'C', vBB_tax_account_id, 0, 0, 0, a.tax_amt, 'URD Tax Asset'
				from st.stock_control a
				where a.stock_id = pvoucher_id;
			End If;

		Else 
			-- *****	Step 1: Basic value of items
			Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
				debit_amt, credit_amt, remarks)
			Select vCompany_ID, vBranch_ID, 'D', vSaleAccount_ID, sum(a.item_amt_fc), 0, 
				sum(case When a.apply_itc Then a.bt_amt Else a.item_amt End), 0, 'Line Items without ITC'
			from st.stock_tran a
			where a.stock_id=pvoucher_id;

			-- *****	Step 2: Line Item Taxes ITC
			Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
			Select vCompany_ID, vBranch_ID, 'D', b.account_id, 0, 0, sum(a.tax_amt), 0, 'Line Items Taxes ITC'
			from st.stock_tran a
			Inner Join tx.tax_detail b On a.tax_schedule_id=b.tax_schedule_id And b.step_id=1
			Where a.stock_id=pvoucher_id And a.apply_itc = true
			Group by b.account_id;

                        -- *****	Step 3: GST Taxes
                        If Exists(Select * From st.stock_control Where stock_id=pvoucher_id And (annex_info->>'bill_level_tax')::Boolean) Then
                                Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                                Select vCompany_ID, vBranch_ID, 'D', a.account_id, 0, 0, sum(a.tax_amt), 0, 'Tax Amt'
                                From st.fn_spg_gtt_info(pvoucher_id) a
                                group by a.account_id
                                having sum(a.tax_amt) > 0;
                    
                                -- *****	Step 2: Tax without ITC                   
                                With gst_tax_tran
                                As
                                (	Select a.stock_id, x.*
                                    From st.stock_control a, 
                                            jsonb_to_recordset(a.annex_info->'gst_tax_tran') as x (
                                            sl_no BigInt, apply_itc Boolean, gst_rate_id BigInt, bt_amt Numeric, tax_amt_ov Boolean,
                                            sgst_pcnt Numeric, sgst_amt Numeric, cgst_pcnt Numeric, cgst_amt Numeric,
                                            igst_pcnt Numeric, igst_amt Numeric, cess_pcnt Numeric, cess_amt Numeric)
                                    Where a.stock_id = pvoucher_id
                                )
                                Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                                Select vCompany_ID, vBranch_ID, 'D', vSaleAccount_ID, 0, 0, coalesce(sum(a.sgst_amt + a.cgst_amt + a.igst_amt), 0.00), 0, 'Bill Level Tax Amt (without ITC)'
                                from gst_tax_tran a
                                Where a.apply_itc = false;
                        Else
                                with gtt 
                                as 
                                (	select sum(sgst_amt) as tax_amt, sgst_itc_account_id as account_id
                                        from tx.gst_tax_tran
                                        where voucher_id = pvoucher_ID And tran_group='st.stock_tran' And apply_itc
                                        group by sgst_itc_account_id
                                        union all
                                        select sum(cgst_amt), cgst_itc_account_id
                                        from tx.gst_tax_tran
                                        where voucher_id = pvoucher_ID And tran_group='st.stock_tran' And apply_itc
                                        group by cgst_itc_account_id
                                        union all
                                        select sum(igst_amt), igst_itc_account_id
                                        from tx.gst_tax_tran
                                        where voucher_id = pvoucher_ID And tran_group='st.stock_tran' And apply_itc
                                        group by igst_itc_account_id
                                        union all
                                        select sum(cess_amt), cess_itc_account_id
                                        from tx.gst_tax_tran
                                        where voucher_id = pvoucher_ID And tran_group='st.stock_tran' And apply_itc
                                        group by cess_itc_account_id
                                )
                                Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                                Select vCompany_ID, vBranch_ID, 'D', a.account_id, 0, 0, sum(a.tax_amt), 0, 'Tax Amt'
                                From gtt a
                                group by a.account_id
                                having sum(a.tax_amt) > 0;
                        End If;
		End If;

                -- *****	Step 3: LC not paid by Supplier
		Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                Select vCompany_ID, vBranch_ID, 'D', a.account_id, sum(a.debit_amt_fc), 0, sum(a.debit_amt), 0, 'LC not paid by Supplier'
		from st.stock_lc_tran a
		where a.stock_id=pvoucher_id And a.post_gl
		group by a.account_id
                Union All
		Select vCompany_ID, vBranch_ID, 'C', a.account_affected_id, 0,  sum(a.debit_amt_fc), 0,  sum(a.debit_amt), 'LC not paid by Supplier'
		from st.stock_lc_tran a
		where a.stock_id=pvoucher_id And a.post_gl
		group by a.account_affected_id;

	End If;

	If vDocType in ('PR', 'PRV') then    
		Select reference_id, coalesce((annex_info->>'dcn_type')::Int, 0) into vReference_ID, vdcn_type 
                from st.stock_control
		where stock_id =pvoucher_id;
        
		Select a.sale_account_id into vSaleAccount_ID
		From st.stock_control a
		where stock_id = vReference_ID;
        
		If vdcn_type = 1 Then -- Rate Adjustment (Decrese)
                -- *****	Group A: Credits
                -- *****	Step 1: Get Supplier Credit
                Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                Select vCompany_ID, vBranch_ID, 'C', a.account_id, 0, a.total_amt_fc, 0, a.total_amt, 'Supplier Credit'
                from st.stock_control a
                where a.stock_id=pvoucher_id;

                -- *****	Step 2: Get round off with -ve case
                Select cast(value as bigint) into vRoundOffAcc_ID from sys.settings where key='st_round_off_account';
                Insert into stock_vch_detail(company_id, branch_id, dc, account_id, 
                        debit_amt_fc, credit_amt_fc, 
                        debit_amt, credit_amt, 
                        remarks)
                Select vCompany_ID, vBranch_ID, case when a.round_off_amt > 0 Then 'D' Else 'C' End, vRoundOffAcc_ID, 
                        case when a.round_off_amt_fc < 0 Then 0 Else -a.round_off_amt_fc End, case when a.round_off_amt_fc > 0 Then a.round_off_amt_fc Else 0 End, 
                        case when a.round_off_amt > 0 Then a.round_off_amt Else 0 End, case when a.round_off_amt < 0 Then -a.round_off_amt Else 0 End, 
                        'Round Off Amt'
                from st.stock_control a
                where a.stock_id=pvoucher_id And (a.round_off_amt_fc != 0 Or a.round_off_amt != 0);

                -- *****	Group B: Debits
                -- *****	Step 1: Basic value of items
                Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
                    debit_amt, credit_amt, remarks)
                Select vCompany_ID, vBranch_ID, 'D', vSaleAccount_ID, sum(a.bt_amt_fc), 0, 
                    sum(a.bt_amt), 0, 'Line Items without Taxes'
                from st.stock_tran a
                where a.stock_id=pvoucher_id;


                -- Fetch GST Tax Tran (ITC)		
                Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                Select a.company_id, a.branch_id, 'D', b.account_id, 0, 0, coalesce(sum(b.tax_amt), 0), 0, 'GST Tax Tran (ITC)'
                From st.stock_control a
                Inner Join tx.fn_gtt_itc_info(pvoucher_ID, 'st.stock_tran', '{-1}'::BigInt[]) b on a.stock_id =b.voucher_id
                Where a.stock_id=pvoucher_ID
                group by a.company_id, a.branch_id, b.account_id;

                -- Fetch GST Tax Tran (Non-ITC)
                -- Fetch Tran			
                Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                Select a.company_id, a.branch_id, 'D', a.account_id, 0, 0, c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt, 0, 'GST Tax Tran (Non-ITC)'
                From st.stock_control a
                Inner Join st.stock_tran b On a.stock_id = b.stock_id
                Inner Join tx.gst_tax_tran c On b.stock_tran_id = c.gst_tax_tran_id
                Where a.stock_id=pvoucher_ID And c.apply_itc = False 
                    And c.rc_sec_id = -1
                    And c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt > 0;
            Else
                -- *****	Group A: Debits
                -- *****	Step 1: Get Supplier Debit
                Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                Select vCompany_ID, vBranch_ID, 'D', a.account_id, a.total_amt_fc, 0, a.total_amt, 0, 'Supplier Debit'
                from st.stock_control a
                where a.stock_id=pvoucher_id;

                -- *****	Step 2: Get round off with -ve case
                Select cast(value as bigint) into vRoundOffAcc_ID from sys.settings where key='st_round_off_account';
                Insert into stock_vch_detail(company_id, branch_id, dc, account_id, 
                        debit_amt_fc, credit_amt_fc, 
                        debit_amt, credit_amt, 
                        remarks)
                Select vCompany_ID, vBranch_ID, case when a.round_off_amt > 0 Then 'C' Else 'D' End, vRoundOffAcc_ID, 
                        case when a.round_off_amt_fc > 0 Then a.round_off_amt_fc Else 0 End, case when a.round_off_amt_fc < 0 Then 0 Else -a.round_off_amt_fc End, 
                        case when a.round_off_amt < 0 Then -a.round_off_amt Else 0 End, case when a.round_off_amt > 0 Then a.round_off_amt Else 0 End, 
                        'Round Off Amt'
                from st.stock_control a
                where a.stock_id=pvoucher_id And (a.round_off_amt_fc != 0 Or a.round_off_amt != 0);

                -- *****	Group B: Credits
                -- *****	Step 1: Basic value of items
                Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                Select vCompany_ID, vBranch_ID, 'C', vSaleAccount_ID, 0, Sum(a.item_amt_fc), 0, Sum(a.bt_amt), 'Line Items without taxes (net sales purchase)'
                from st.stock_tran a
                where a.stock_id=pvoucher_id;

                -- *****	Step 2: Line Item Taxes
                Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                Select vCompany_ID, vBranch_ID, 'C', b.account_id, 0, 0, 0, sum(a.tax_amt), 'Line Items Taxes'
                from st.stock_tran a
                Inner Join tx.tax_detail b On a.tax_schedule_id=b.tax_schedule_id And b.step_id=1
                Where a.stock_id=pvoucher_id
                Group by b.account_id;

                -- *****	Step 3: Line Item GST Taxes
                -- Fetch GST Tax Tran (ITC)		
                Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                Select a.company_id, a.branch_id, 'C', b.account_id, 0, 0, 0, coalesce(sum(b.tax_amt), 0), 'GST Tax Tran (ITC)'
                From st.stock_control a
                Inner Join tx.fn_gtt_itc_info(pvoucher_ID, 'st.stock_tran', '{-1}'::BigInt[]) b on a.stock_id =b.voucher_id
                Where a.stock_id=pvoucher_ID
                group by a.company_id, a.branch_id, b.account_id;

                -- Fetch GST Tax Tran (Non-ITC)
                -- Fetch Tran			
                Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                Select a.company_id, a.branch_id, 'C', a.account_id, 0, 0, 0, c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt, 'GST Tax Tran (Non-ITC)'
                From st.stock_control a
                Inner Join st.stock_tran b On a.stock_id = b.stock_id
                Inner Join tx.gst_tax_tran c On b.stock_tran_id = c.gst_tax_tran_id
                Where a.stock_id=pvoucher_ID And c.apply_itc = False 
                    And c.rc_sec_id = -1
                    And c.sgst_amt + c.cgst_amt + c.igst_amt + c.cess_amt > 0;
            End If;
	End If;

        If vDocType = 'PRN' then
		-- *****	Group A: Debits
		-- *****	Step 1: Get Supplier Debit
		Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
		Select vCompany_ID, vBranch_ID, 'D', a.account_id, a.total_amt_fc, 0, a.total_amt, 0, 'Supplier Debit'
		from st.stock_control a
		where a.stock_id=pvoucher_id;

		-- *****	Group B: Credits
		-- *****	Step 1: Basic value of items
		Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
			debit_amt, credit_amt, remarks)
		Select vCompany_ID, vBranch_ID, 'C', vSaleAccount_ID, 0, sum(a.item_amt_fc), 
			0, sum(case When a.apply_itc Then a.bt_amt Else a.item_amt End), 'Line Items without ITC'
		from st.stock_tran a
		where a.stock_id=pvoucher_id;

		-- *****	Step 2: Line Item Taxes ITC
		Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
		Select vCompany_ID, vBranch_ID, 'C', b.account_id, 0, 0, 0, sum(a.tax_amt), 'Line Items Taxes ITC'
		from st.stock_tran a
		Inner Join tx.tax_detail b On a.tax_schedule_id=b.tax_schedule_id And b.step_id=1
		Where a.stock_id=pvoucher_id And a.apply_itc = true
		Group by b.account_id;
	End If;

	If vDocType = 'SI' or vDocType = 'SIV' Then
		-- *****	Group A: Debits
		-- *****	Step 1: Get Customer Debit
		Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
		Select vCompany_ID, vBranch_ID, 'D', a.account_id, a.total_amt_fc, 0, a.total_amt, 0, 'Sale Amt'
		from st.stock_control a
		where a.stock_id=pvoucher_id;

		-- *****	Step 2: Get round off with -ve case
		Select cast(value as bigint) into vRoundOffAcc_ID from sys.settings where key='st_round_off_account';
		Insert into stock_vch_detail(company_id, branch_id, dc, account_id, 
			debit_amt_fc, credit_amt_fc, 
			debit_amt, credit_amt, 
			remarks)
		Select vCompany_ID, vBranch_ID, case when a.round_off_amt < 0 Then 'C' Else 'D' End, vRoundOffAcc_ID, 
			case when a.round_off_amt_fc < 0 Then 0 Else -a.round_off_amt_fc End, case when a.round_off_amt_fc > 0 Then a.round_off_amt_fc Else 0 End, 
			case when a.round_off_amt < 0 Then -a.round_off_amt Else 0 End, case when a.round_off_amt > 0 Then a.round_off_amt Else 0 End, 
			'Round Off Amt'
		from st.stock_control a
		where a.stock_id=pvoucher_id And (a.round_off_amt_fc != 0 Or a.round_off_amt != 0);

                -- *****	Step 3: Reverse Buy Back effects (posting of Buy Back is handled in next case)
		Select cast(value as bigint) into vBB_adj_account_id from sys.settings where key='bb_adj_account';
		Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, 
                    debit_amt, credit_amt, remarks)
		Select vCompany_id, vBranch_id, 'D', vBB_adj_account_id, 0, 0, 
                    sum(b.bt_amt), 0, 'Buy Back Adjustment'
		from st.stock_control a
		Inner Join st.inv_bb b On a.stock_id = b.inv_id
		where a.stock_id = pvoucher_id
		having sum(b.bt_amt)!=0;
		
		-- *****	Group B: Credits
		-- *****	Step 1: Basic value of items
		Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
		Select vCompany_ID, vBranch_ID, 'C', vSaleAccount_ID, 0, Sum(a.item_amt_fc), 0, Sum(a.bt_amt+a.other_amt), 'Line Items without taxes (net sales)'
		from st.stock_tran a
		where a.stock_id=pvoucher_id
		Having Sum(a.bt_amt)!=0;

		-- *****	Step 2: Line Item Taxes
		Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
		Select vCompany_ID, vBranch_ID, 'C', b.account_id, 0, 0, 0, sum(a.tax_amt), 'Line Items Taxes'
		from st.stock_tran a
		Inner Join tx.tax_detail b On a.tax_schedule_id=b.tax_schedule_id And b.step_id=1
		Where a.stock_id=pvoucher_id
		Group by b.account_id;

                -- *****	Step 3: GST Taxes
                with gtt 
                as 
                (	select sum(sgst_amt) as tax_amt, sgst_account_id as account_id
                        from tx.gst_tax_tran
                        where voucher_id = pvoucher_ID And tran_group='st.stock_tran'
                        group by sgst_account_id
                        union all
                        select sum(cgst_amt), cgst_account_id
                        from tx.gst_tax_tran
                        where voucher_id = pvoucher_ID And tran_group='st.stock_tran'
                        group by cgst_account_id
                        union all
                        select sum(igst_amt), igst_account_id
                        from tx.gst_tax_tran
                        where voucher_id = pvoucher_ID And tran_group='st.stock_tran'
                        group by igst_account_id
                        union all
                        select sum(cess_amt), cess_account_id
                        from tx.gst_tax_tran
                        where voucher_id = pvoucher_ID And tran_group='st.stock_tran'
                        group by cess_account_id
                )
                Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                Select vCompany_ID, vBranch_ID, 'C', a.account_id, 0, 0, 0, sum(a.tax_amt), 'Tax Amt'
                From gtt a
                group by a.account_id
                having sum(a.tax_amt) > 0;

                -- **** Posting Buy Back(s)
                If pvoucher_id = replace(pvoucher_id, ':BB', '') || ':BB' Then
                    -- *****	Group A: Debits
                    -- *****	Step 1: Basic value of items
                    Select cast(value as bigint) into vBB_purchase_account_id from sys.settings where key='bb_purchase_account';
                    Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                    Select vCompany_id, vBranch_id, 'D', vBB_purchase_account_id, 0, 0, Sum(a.bt_amt), 0, 'Buy Back Line Items without Taxes'
                    from st.inv_bb a
                    where a.inv_id = replace(pvoucher_id, ':BB', '');

                    -- *****	Step 2: Line Item Taxes
                    Select cast(value as bigint) into vBB_tax_account_id from sys.settings where key='bb_tax_account';
                    Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                    Select vCompany_ID, vBranch_ID, 'D', vBB_tax_account_id, 0, 0, sum(b.tax_amt), 0, 'Line Items Taxes'
                    from st.stock_control a
                    Inner Join st.inv_bb b On a.stock_id=b.inv_id
                    Inner Join tx.tax_detail c On b.tax_schedule_id=c.tax_schedule_id And c.step_id=1
                    where a.stock_id = replace(pvoucher_id, ':BB', '');

                    -- *****	Group B: Credits
                    -- *****	Step 1: BB Adjustment
                    Select cast(value as bigint) into vBB_adj_account_id from sys.settings where key='bb_adj_account';
                    Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                    Select vCompany_id, vBranch_id, 'C', vBB_adj_account_id, 0, 0, 0, sum(b.bt_amt), 'Buy Back Adjustment'
                    from st.stock_control a
                    Inner Join st.inv_bb b On a.stock_id = b.inv_id
                    where a.stock_id = replace(pvoucher_id, ':BB', '');

                    -- *****	Step 2: Tax payable
                    Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                    Select vCompany_ID, vBranch_ID, 'C', c.account_id, 0, 0, 0, sum(b.tax_amt), 'Line Items Taxes'
                    from st.stock_control a
                    Inner Join st.inv_bb b On a.stock_id=b.inv_id
                    Inner Join tx.tax_detail c On b.tax_schedule_id=c.tax_schedule_id And c.step_id=1
                    where a.stock_id = replace(pvoucher_id, ':BB', '')
                    Group by c.account_id;
                End If;
	End If;


	If vDocType In ('SR', 'SRV') then 
		Select reference_id, coalesce((annex_info->>'dcn_type')::Int, 0) into vReference_ID, vdcn_type from st.stock_control
		where stock_id =pvoucher_id;

		Select sale_account_id into vSaleAccount_ID from st.stock_control
		Where stock_id = vReference_ID;
	
                If vdcn_type = 1 Then -- Rate Adjustment (Increase)
                    -- *****	Group A: Debits
                    -- *****	Step 1: Get Customer Debit
                    Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                    Select vCompany_ID, vBranch_ID, 'D', a.account_id, a.total_amt_fc, 0, a.total_amt, 0, 'Sale Ret. Amt'
                    from st.stock_control a
                    where a.stock_id=pvoucher_id;

                    -- *****	Step 2: Get round off with -ve case
                    Select cast(value as bigint) into vRoundOffAcc_ID from sys.settings where key='st_round_off_account';
                    Insert into stock_vch_detail(company_id, branch_id, dc, account_id, 
                            debit_amt_fc, credit_amt_fc, 
                            debit_amt, credit_amt, 
                            remarks)
                    Select vCompany_ID, vBranch_ID, case when a.round_off_amt > 0 Then 'C' Else 'D' End, vRoundOffAcc_ID, 
                            case when a.round_off_amt_fc > 0 Then a.round_off_amt_fc Else 0 End, case when a.round_off_amt_fc < 0 Then 0 Else -a.round_off_amt_fc End, 
                            case when a.round_off_amt < 0 Then -a.round_off_amt Else 0 End, case when a.round_off_amt > 0 Then a.round_off_amt Else 0 End, 
                            'Round Off Amt'
                    from st.stock_control a
                    where a.stock_id=pvoucher_id And (a.round_off_amt_fc != 0 Or a.round_off_amt != 0);

                    -- *****	Group B: Credits
                    -- *****	Step 1: Basic value of items
                    Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                    Select vCompany_ID, vBranch_ID, 'C', vSaleAccount_ID, 0, Sum(a.item_amt_fc), 0, Sum(a.bt_amt), 'Line Items without taxes (net sales return)'
                    from st.stock_tran a
                    where a.stock_id=pvoucher_id;

                    -- *****	Step 2: Line Item Taxes
                    Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                    Select vCompany_ID, vBranch_ID, 'C', b.account_id, 0, 0, 0, sum(a.tax_amt), 'Line Items Taxes'
                    from st.stock_tran a
                    Inner Join tx.tax_detail b On a.tax_schedule_id=b.tax_schedule_id And b.step_id=1
                    Where a.stock_id=pvoucher_id
                    Group by b.account_id;

                    -- *****	Step 3: Line Item GST Taxes
                    Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                    Select vCompany_ID, vBranch_ID, 'C', a.account_id, 0, 0, 0, sum(a.tax_amt), 'Tax Amt'
                    From tx.fn_gtt_info(pvoucher_id, 'st.stock_tran') a
                    group by a.account_id
                    having sum(a.tax_amt) > 0;

                Else
                    -- *****	Group A: Credits
                    -- *****	Step 1: Get Customer Credit
                    Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                    Select vCompany_ID, vBranch_ID, 'C', a.account_id, 0, a.total_amt_fc, 0, a.total_amt, 'Sale Ret. Amt'
                    from st.stock_control a
                    where a.stock_id=pvoucher_id;

                    -- *****	Step 2: Get round off with -ve case
                    Select cast(value as bigint) into vRoundOffAcc_ID from sys.settings where key='st_round_off_account';
                    Insert into stock_vch_detail(company_id, branch_id, dc, account_id, 
                            debit_amt_fc, credit_amt_fc, 
                            debit_amt, credit_amt, 
                            remarks)
                    Select vCompany_ID, vBranch_ID, case when a.round_off_amt > 0 Then 'D' Else 'C' End, vRoundOffAcc_ID, 
                            case when a.round_off_amt_fc < 0 Then 0 Else -a.round_off_amt_fc End, case when a.round_off_amt_fc > 0 Then a.round_off_amt_fc Else 0 End, 
                            case when a.round_off_amt > 0 Then a.round_off_amt Else 0 End, case when a.round_off_amt < 0 Then -a.round_off_amt Else 0 End, 
                            'Round Off Amt'
                    from st.stock_control a
                    where a.stock_id=pvoucher_id And (a.round_off_amt_fc != 0 Or a.round_off_amt != 0);

                    -- *****	Group B: Debits
                    -- *****	Step 1: Basic value of items
                    Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                    Select vCompany_ID, vBranch_ID, 'D', vSaleAccount_ID, Sum(a.item_amt_fc), 0, Sum(a.bt_amt), 0, 'Line Items without taxes (net sales return)'
                    from st.stock_tran a
                    where a.stock_id=pvoucher_id;

                    -- *****	Step 2: Line Item Taxes
                    Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                    Select vCompany_ID, vBranch_ID, 'D', b.account_id, 0, 0, sum(a.tax_amt), 0, 'Line Items Taxes'
                    from st.stock_tran a
                    Inner Join tx.tax_detail b On a.tax_schedule_id=b.tax_schedule_id And b.step_id=1
                    Where a.stock_id=pvoucher_id
                    Group by b.account_id;

                    -- *****	Step 3: Line Item GST Taxes
                    Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
                    Select vCompany_ID, vBranch_ID, 'D', a.account_id, 0, 0, sum(a.tax_amt), 0, 'Tax Amt'
                    From tx.fn_gtt_info(pvoucher_id, 'st.stock_tran') a
                    group by a.account_id
                    having sum(a.tax_amt) > 0;
                End If;
		
	End If;

	If vDocType = 'SRN' then 
		-- *****	Group A: Credits
		-- *****	Step 1: Get Customer Credit
		Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
		Select vCompany_ID, vBranch_ID, 'C', a.account_id, 0, a.total_amt_fc, 0, a.total_amt, 'Sale Ret. Amt'
		from st.stock_control a
		where a.stock_id=pvoucher_id;

		-- *****	Step 2: Get round off with -ve case
		Select cast(value as bigint) into vRoundOffAcc_ID from sys.settings where key='st_round_off_account';
		Insert into stock_vch_detail(company_id, branch_id, dc, account_id, 
			debit_amt_fc, credit_amt_fc, 
			debit_amt, credit_amt, 
			remarks)
		Select vCompany_ID, vBranch_ID, case when a.round_off_amt > 0 Then 'D' Else 'C' End, vRoundOffAcc_ID, 
			case when a.round_off_amt_fc < 0 Then 0 Else -a.round_off_amt_fc End, case when a.round_off_amt_fc > 0 Then a.round_off_amt_fc Else 0 End, 
			case when a.round_off_amt > 0 Then a.round_off_amt Else 0 End, case when a.round_off_amt < 0 Then -a.round_off_amt Else 0 End, 
			'Round Off Amt'
		from st.stock_control a
		where a.stock_id=pvoucher_id And (a.round_off_amt_fc != 0 Or a.round_off_amt != 0);
		
		-- *****	Group B: Debits
		-- *****	Step 1: Basic value of items
		Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
		Select vCompany_ID, vBranch_ID, 'D', vSaleAccount_ID, Sum(a.item_amt_fc), 0, Sum(a.bt_amt), 0, 'Line Items without taxes (net sales return)'
		from st.stock_tran a
		where a.stock_id=pvoucher_id;

		-- *****	Step 2: Line Item Taxes
		Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
		Select vCompany_ID, vBranch_ID, 'D', b.account_id, 0, 0, sum(a.tax_amt), 0, 'Line Items Taxes'
		from st.stock_tran a
		Inner Join tx.tax_detail b On a.tax_schedule_id=b.tax_schedule_id And b.step_id=1
		Where a.stock_id=pvoucher_id
		Group by b.account_id;
	End If;

	If vDocType = 'ST' then     	
		-- *****	Step 2: Line Item Taxes
		Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
		Select vCompany_ID, vBranch_ID, 'D', b.account_id, 0 debit_amt_fc, 0 credit_amt_fc, a.tax_amt debit_amt, 0 credit_amt, '' remarks
		from st.stock_control a
        inner join ac.ib_account b on a.target_branch_id = b.branch_id
		Where a.stock_id=pvoucher_id;        

        -- *****	Step 3: GST Taxes
        with gtt 
        as 
        (	select sum(sgst_amt) as tax_amt, sgst_account_id as account_id
                from tx.gst_tax_tran
                where voucher_id = pvoucher_ID And tran_group='st.stock_tran'
                group by sgst_account_id
                union all
                select sum(cgst_amt), cgst_account_id
                from tx.gst_tax_tran
                where voucher_id = pvoucher_ID And tran_group='st.stock_tran'
                group by cgst_account_id
                union all
                select sum(igst_amt), igst_account_id
                from tx.gst_tax_tran
                where voucher_id = pvoucher_ID And tran_group='st.stock_tran'
                group by igst_account_id
                union all
                select sum(cess_amt), cess_account_id
                from tx.gst_tax_tran
                where voucher_id = pvoucher_ID And tran_group='st.stock_tran'
                group by cess_account_id
        )
        Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
        Select vCompany_ID, vBranch_ID, 'C', a.account_id, 0, 0, 0, sum(a.tax_amt), 'Tax Amt'
        From gtt a
        group by a.account_id
        having sum(a.tax_amt) > 0;
    End If;

	return query 
	select a.index, a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt, a.remarks
	from stock_vch_detail a;
END;
$BODY$
LANGUAGE plpgsql;


?==?
create or replace function st.fn_material_balance_wac(pcompany_id bigint, pbranch_id bigint, pmaterial_id bigint, pfinyear varchar(4), pto_date date)
RETURNS TABLE  
(
	material_id bigint,
	balance_qty_base numeric(18,4),
	rate numeric(18,4),
	branch_id bigint
)
AS
$BODY$
Begin	
	-- This function is used by the Posting Trigger to get information on the Supplier Payment (PYMT)
	DROP TABLE IF EXISTS mat_balance;	
	create temp TABLE  mat_balance
	(	
		material_id bigint,
		balance_qty_base numeric(18,4),
		rate numeric(18,4),
		branch_id bigint
	);

	Insert into mat_balance (material_id, balance_qty_base, rate, branch_id)
	Select a.material_id, a.received_qty-a.issued_qty, sys.fn_handle_zero_divide (a.amt, a.received_qty_for_wac), a.branch_id
	From (	-- Fetch Stock Ledger entries for qty
		Select a.material_id, sum(a.received_qty) as received_qty, sum(a.issued_qty) as issued_qty, sum(a.received_qty - a.issued_qty) as received_qty_for_wac, sum((a.received_qty - a.issued_qty) * a.unit_rate_lc) as amt, a.branch_id
		from st.stock_ledger a
		Where a.finyear = pfinyear
			And a.doc_date <= pto_date
			And a.company_id = pcompany_id
			And (a.branch_id in (select b.branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id) b) or pbranch_id=0)
			And (a.material_id = pmaterial_id or pmaterial_id = 0)
			And a.stock_movement_type_id not in (0)
		group by a.material_id, a.branch_id
	) a
	Order by a.material_id, a.branch_id;
	
	return query 
	select a.material_id, a.balance_qty_base, a.rate, a.branch_id
	from mat_balance a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
create or replace function st.fn_sp_balance_for_prtn (pcompany_id bigint, pbranch_id bigint, paccount_id bigint, pfrom_date date, pto_date date, pvoucher_id varchar(50))
RETURNS TABLE  
(
	sl_no smallint,
	branch_id bigint,
	stock_id varchar(50),
	stock_tran_id varchar(50),
	finyear varchar(4),
	doc_date date,
	stock_location_id bigint,
	stock_location_name varchar(250),
	material_id bigint,
	material_desc varchar(2000),
	material_name varchar(250),
	bill_no varchar(50),
	fc_type_id bigint,
	fc_type varchar(20),
	account_id bigint,
	uom_id bigint,
	uom_desc varchar(20),
	received_qty numeric(18,4),
	balance_qty numeric(18,4),
	exch_rate numeric(18,6),
	rate numeric(18,4),
	rate_fc numeric(18,4),
	tax_schedule_id bigint,
	tax_pcnt numeric(18,4), 
	en_tax_type smallint
)
AS
$BODY$ 
	Declare vDiscountFC numeric(18,4)=0; vBillDisFC numeric(18,4)=0; vBillDisc numeric(18,4)=0; vBillDiscPraportionate numeric(18,4)=0; 
		vItemsTotalAmt numeric(18, 4) = 0; vItemsTotalAmtFC numeric(18, 4) = 0;
Begin	
	-- This function is used by the Posting Trigger to get information on the Supplier Payment (PYMT)
	DROP TABLE IF EXISTS sp_balance_tran;	
	create temp TABLE  sp_balance_tran
	(	
		stock_tran_id varchar(50),
		balance_qty_base numeric(18,4)
	);
	
	Insert into sp_balance_tran (stock_tran_id, balance_qty_base)
	Select a.stock_tran_id, sum(a.received_qty_base)
	From (	Select b.stock_tran_id, b.received_qty as received_qty_base
		From st.stock_control a
		Inner Join st.stock_tran b on a.stock_id=b.stock_id
		Where a.status=5
			And a.doc_type = 'SP'
			And (a.account_id=paccount_id or paccount_id =0)
			And a.branch_id=pbranch_id
			And a.doc_date between pfrom_date and pto_date
		Group by b.stock_tran_id
		Union All
		Select b.reference_tran_id, -1* b.issued_qty
		From st.stock_control a
		Inner Join st.stock_tran b on a.stock_id=b.stock_id
		Where a.stock_id <> pvoucher_id
			And b.reference_tran_id <> ''
			And a.doc_type = 'PR'
			And (a.account_id=paccount_id or paccount_id =0)
			And a.branch_id=pbranch_id
		Group by b.stock_tran_id
	) a
	Group by a.stock_tran_id
	having sum(a.received_qty_base) >0;

-- 	raise exception 'vItemsTotalAmt-%, vBillDisc-%, vBillDiscPraportionate-%', vItemsTotalAmt, vBillDisc, vBillDiscPraportionate;
	DROP TABLE IF EXISTS sp_balance;	
	create temp TABLE  sp_balance
	(	
		sl_no smallint,
		branch_id bigint,
		stock_id varchar(50),
		stock_tran_id varchar(50) primary key,
		finyear varchar(4),
		doc_date date,
		stock_location_id bigint,
		stock_location_name varchar(250),
		material_id bigint,
		material_desc varchar(2000),
		material_name varchar(250),
		bill_no varchar(50),
		fc_type_id bigint,
		fc_type varchar(20),
		account_id bigint,
		uom_id bigint,
		uom_desc varchar(20),
		received_qty numeric(18,4),
		balance_qty numeric(18,4),
		exch_rate numeric(18,6),
		rate numeric(18,4),
		rate_fc numeric(18,4),
		tax_schedule_id bigint,
		tax_pcnt numeric(18,4), 
		en_tax_type smallint,
		item_total_amt numeric(18,4),
		bill_disc numeric(18,4),
		bill_disc_praportionate numeric(18, 4),
		bt_amt numeric(18, 4),
		bt_amt_fc numeric(18, 4)
	);

	Insert into sp_balance(sl_no, branch_id, stock_id, stock_tran_id, finyear, doc_date, stock_location_id, stock_location_name, material_id, material_desc, material_name,
				bill_no, fc_type_id, fc_type, account_id, uom_id, uom_desc, 
				received_qty, balance_qty, exch_rate, 
				rate, rate_fc, tax_schedule_id, tax_pcnt, en_tax_type,
				item_total_amt, bill_disc, bt_amt, bt_amt_fc)
	Select b.sl_no, a.branch_id, a.stock_id, b.stock_tran_id, a.finyear, a.doc_date, b.stock_location_id, c.stock_location_name, b.material_id, d.material_desc, d.material_name,
				a.bill_no, a.fc_type_id, g.fc_type, a.account_id, b.uom_id, f.uom_desc,
				b.received_qty, e.balance_qty_base, a.exch_rate, 
				0, 0, -- For Purchase Return calculate rate on the amount after line item disc
				b.tax_schedule_id, b.tax_pcnt, b.en_tax_type,
				(a.annex_info->>'items_total_amt')::numeric, a.disc_amt, b.bt_amt, b.bt_amt_fc													-- Do not use actual rate of Stock Purchase
	From st.stock_control a
	Inner Join st.stock_tran b on a.stock_id=b.stock_id
	Inner Join st.stock_location c on b.stock_location_id=c.stock_location_id
	Inner Join st.material d on b.material_id = d.material_id
	Inner Join sp_balance_tran e on b.stock_tran_id=e.stock_tran_id
	Inner Join st.uom f on b.uom_id=f.uom_id
	Inner Join ac.fc_type g on a.fc_type_id=g.fc_type_id
	Where a.doc_type='SP'
		And a.branch_id = pbranch_id;

	update sp_balance
	set bill_disc_praportionate = sys.fn_handle_zero_divide(bill_disc, item_total_amt);

	update sp_balance
	set rate = sys.fn_handle_round('rate', sys.fn_handle_zero_divide(bt_amt - (bt_amt * bill_disc_praportionate), sp_balance.received_qty));
	
	return query 
	select a.sl_no, a.branch_id, a.stock_id, a.stock_tran_id, a.finyear, a.doc_date, a.stock_location_id, a.stock_location_name, a.material_id, a.material_desc, a.material_name,
		a.bill_no, a.fc_type_id, a.fc_type, a.account_id, a.uom_id, a.uom_desc, a.received_qty, a.balance_qty, a.exch_rate, a.rate, a.rate_fc,
		a.tax_schedule_id, a.tax_pcnt, a.en_tax_type
	from sp_balance a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_si_balance_for_sr(IN pcompany_id bigint, IN pbranch_id bigint, IN paccount_id bigint, IN pfrom_date date, IN pto_date date, IN pvoucher_id character varying)
RETURNS TABLE
(	sl_no smallint, 
	branch_id bigint, 
	stock_id character varying, 
	stock_tran_id character varying, 
	finyear character varying, 
	doc_date date, 
	stock_location_id bigint, 
	stock_location_name character varying, 
	material_id bigint, 
	material_desc character varying, 
	material_name character varying, 
	bill_no character varying, 
	fc_type_id bigint, 
	fc_type character varying, 
	account_id bigint, 
	uom_id bigint, 
	uom_desc character varying, 
	received_qty numeric, 
	balance_qty numeric, 
	exch_rate numeric, 
	rate numeric, 
	rate_fc numeric,
	tax_schedule_id bigint,
	tax_pcnt numeric(18,4), 
	en_tax_type smallint
) AS
$BODY$ 
	Declare vDiscountFC numeric(18,4)=0;
Begin	
	
	DROP TABLE IF EXISTS si_balance_tran;	
	create temp TABLE  si_balance_tran
	(	
		stock_tran_id varchar(50),
		balance_qty_base numeric(18,4)
	);
	
	Insert into si_balance_tran (stock_tran_id, balance_qty_base)
	Select a.stock_tran_id, sum(a.issued_qty_base)
	From (	Select b.stock_tran_id, b.issued_qty as issued_qty_base
		From st.stock_control a
		Inner Join st.stock_tran b on a.stock_id=b.stock_id
		Where a.status=5
			And a.doc_type In ('SI', 'SIV')
			And (a.account_id=paccount_id or paccount_id =0)
			And a.branch_id=pbranch_id
			And a.doc_date between pfrom_date and pto_date
		Group by b.stock_tran_id
		Union All
		Select b.reference_tran_id, -1 * b.received_qty
		From st.stock_control a
		Inner Join st.stock_tran b on a.stock_id=b.stock_id
		Where a.stock_id <> pvoucher_id
			And b.reference_tran_id <> ''
			And a.doc_type = 'SR'
			And (a.account_id=paccount_id or paccount_id =0)
			And a.branch_id=pbranch_id
		Group by b.stock_tran_id
	) a
	Group by a.stock_tran_id
	having sum(a.issued_qty_base) >0;

	DROP TABLE IF EXISTS sr_balance;	
	create temp TABLE  sr_balance
	(	
		sl_no smallint,
		branch_id bigint,
		stock_id varchar(50),
		stock_tran_id varchar(50) primary key,
		finyear varchar(4),
		doc_date date,
		stock_location_id bigint,
		stock_location_name varchar(250),
		material_id bigint,
		material_desc varchar(2000),
		material_name varchar(250),
		bill_no varchar(50),
		fc_type_id bigint,
		fc_type varchar(20),
		account_id bigint,
		uom_id bigint,
		uom_desc varchar(20),
		received_qty numeric(18,4),
		balance_qty numeric(18,4),
		exch_rate numeric(18,6),
		rate numeric(18,4),
		rate_fc numeric(18,4),
		tax_schedule_id bigint,
		tax_pcnt numeric(18,4), 
		en_tax_type smallint
	);

	Insert into sr_balance(sl_no, branch_id, stock_id, stock_tran_id, finyear, doc_date, stock_location_id, stock_location_name, material_id, material_desc, material_name,
				bill_no, fc_type_id, fc_type, account_id, uom_id, uom_desc, 
				received_qty, balance_qty, exch_rate, 
				rate, rate_fc, 
				tax_schedule_id, tax_pcnt, en_tax_type)
	Select b.sl_no, a.branch_id, a.stock_id, b.stock_tran_id, a.finyear, a.doc_date, b.stock_location_id, c.stock_location_name, b.material_id, d.material_desc, d.material_name,
				a.bill_no, a.fc_type_id, g.fc_type, a.account_id, b.uom_id, f.uom_desc,
				b.issued_qty, e.balance_qty_base, a.exch_rate,
				sys.fn_handle_zero_divide(b.bt_amt, b.issued_qty), sys.fn_handle_zero_divide(b.bt_amt_fc, b.issued_qty),-- For Sales Return calculate rate on the amount after line item disc
				b.tax_schedule_id, b.tax_pcnt, b.en_tax_type														-- Do not use actual rate of Stock Invoice
	From st.stock_control a
	Inner Join st.stock_tran b on a.stock_id=b.stock_id
	Inner Join st.stock_location c on b.stock_location_id=c.stock_location_id
	Inner Join st.material d on b.material_id = d.material_id
	Inner Join si_balance_tran e on b.stock_tran_id=e.stock_tran_id
	Inner Join st.uom f on b.uom_id=f.uom_id
	Inner Join ac.fc_type g on a.fc_type_id=g.fc_type_id
	Where a.doc_type='SI'
		And a.branch_id = pbranch_id;
		
	return query 
	select a.sl_no, a.branch_id, a.stock_id, a.stock_tran_id, a.finyear, a.doc_date, a.stock_location_id, a.stock_location_name, a.material_id, a.material_desc, a.material_name,
				a.bill_no, a.fc_type_id, a.fc_type, a.account_id, a.uom_id, a.uom_desc, a.received_qty, a.balance_qty, a.exch_rate, a.rate, a.rate_fc,
				a.tax_schedule_id, a.tax_pcnt, a.en_tax_type
	from sr_balance a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
create or replace function st.fn_material_uom_base()
RETURNS TABLE(
	material_id bigint,
	uom_id bigint,
	uom_desc varchar(20)
)
AS
$BODY$
BEGIN
	DROP TABLE IF EXISTS mat_uom_base;	
	create temp TABLE  mat_uom_base
	(	
		material_id bigint,
		uom_id bigint,
		uom_desc varchar(20)
	);

	Insert into mat_uom_base(material_id, uom_id, uom_desc)
	Select a.material_id, b.uom_id, b.uom_desc
	From st.material a 
	Inner Join st.uom b on a.material_id = b.material_id And b.is_base;

	return query
	Select a.material_id, a.uom_id, a.uom_desc
	From mat_uom_base a;

END;
$BODY$
language  plpgsql;

?==?
Create OR REPLACE Function st.fn_get_uom_to_base_qty(puom_id bigint, out punits_in_base numeric(18,4))
Returns numeric(18,4) as
$BODY$
Declare vUnitsInBase numeric(18, 4) =0;
Begin
	-- Fetch the conversion unit
	select uom_qty into vUnitsInBase from st.uom where uom_id=puom_id;
	
	-- Generate the output
	punits_in_base:=vUnitsInBase;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
create or replace function st.fn_material_balance_wac_detail(pcompany_id bigint, pbranch_id bigint, pmaterial_id bigint, pstock_location_id bigint, pfinyear varchar(4), pto_date date)
RETURNS TABLE  
(
	branch_id bigint,
	stock_location_id bigint,
	material_id bigint,
	balance_qty_base numeric(18,4),
	rate numeric(18,4)
)
AS
$BODY$ 
Begin	
	DROP TABLE IF EXISTS stock_loc;	
	create temp TABLE  stock_loc
	(	
		stock_location_id bigint,
		branch_id bigint
	);

	Insert into stock_loc(stock_location_id, branch_id)
	Select a.stock_location_id, a.branch_id 
	from st.stock_location a
	where (a.branch_id = pbranch_id or pbranch_id = 0)
		and (a.stock_location_id = pstock_location_id or pstock_location_id =0);


	-- Fetch Material Stock Location 
	DROP TABLE IF EXISTS mat_bal;
	CREATE TEMP TABLE mat_bal(
		branch_material_id varchar(50),
		branch_id bigint,
		stock_location_id bigint,
		material_id bigint,
		balance_qty_base numeric(18,4)
	);
	
	Insert into mat_bal(branch_material_id, branch_id, stock_location_id, material_id, balance_qty_base)
	Select a.branch_id || ':' || a.material_id, a.branch_id, a.stock_location_id, a.material_id, COALESCE(sum(a.received_qty-a.issued_qty), 0)
	from st.stock_ledger a
	Where  a.finyear = pfinyear 
		And a.doc_date <= pto_date
		And a.company_id = pcompany_id
		And (a.branch_id in (select b.branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id) b) or pbranch_id=0)
		And (a.material_id = pmaterial_id or pmaterial_id = 0)
		And (a.stock_location_id = pstock_location_id or pstock_location_id = 0)
	Group By a.branch_id, a.stock_location_id, a.material_id;

	
	-- Fetch Branch WAC rate 
	DROP TABLE IF EXISTS mat_wac_rate;
	CREATE TEMP TABLE mat_wac_rate(
		branch_material_id varchar(50),
		branch_id bigint,
		material_id bigint,
		rate numeric(18,4)
	);

	Insert into mat_wac_rate(branch_material_id, branch_id, material_id, rate)
	Select a.branch_id || ':' || a.material_id, a.branch_id,  a.material_id, sys.fn_handle_zero_divide(COALESCE(sum(a.amt), 0), COALESCE(sum(a.received_qty_for_wac), 0))
	From (	Select a.branch_id, a.material_id, (a.received_qty - a.issued_qty) * a.unit_rate_lc as amt,  (a.received_qty - a.issued_qty) as received_qty_for_wac
		from st.stock_ledger a
		Where a.finyear = pfinyear 
			And a.doc_date <= pto_date
			And a.company_id = pcompany_id
			And (a.branch_id in (select b.branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id) b) or pbranch_id=0)
			And (a.material_id = pmaterial_id or pmaterial_id = 0)
		)a 
	group by a.material_id, a.branch_id;

	DROP TABLE IF EXISTS mat_bal_wac;
	CREATE TABLE mat_bal_wac(
		branch_id bigint,
		stock_location_id bigint,
		material_id bigint,
		balance_qty_base numeric(18,4),
		rate numeric(18,4)
	);
	
	Insert into mat_bal_wac (branch_id, stock_location_id, material_id, balance_qty_base, rate)
	Select a.branch_id, a.stock_location_id, coalesce(b.material_id, 0), coalesce(b.balance_qty_base, 0), coalesce(c.rate, 0)
	From stock_loc a
	Left Join mat_bal b on a.branch_id = b.branch_id and a.stock_location_id = b.stock_location_id
	Left Join mat_wac_rate c on b.branch_material_id = c.branch_material_id
	Order by b.material_id, a.branch_id, a.stock_location_id;

        /* Following is a simpler logic that can be implemeted with same cost
        Return Query
        With sl_sum
        As
        (   Select a.material_id, a.branch_id, a.stock_location_id,
                Sum(a.received_qty - a.issued_qty) bal_qty,
                Sum((a.received_qty - a.issued_qty) * a.unit_rate_lc)  bal_val
            From st.stock_ledger a
            Where a.company_id = pcompany_id And a.finyear = pfinyear
                And a.doc_date <= pto_date
                And (a.branch_id = pbranch_id Or pbranch_id = 0)
                And (a.material_id = pmaterial_id Or pmaterial_id = 0)
            Group by a.material_id, a.branch_id, a.stock_location_id
        ),
        bal_rate
        As
        (   Select a.branch_id, a.stock_location_id, a.material_id, 
                a.bal_qty,
                sys.fn_handle_zero_divide(Sum(a.bal_val) Over (Partition By a.material_id, a.branch_id), Sum(a.bal_qty) Over (Partition By a.material_id, a.branch_id))::Numeric(18,4) rate
            From sl_sum a
        )
        Select a.branch_id, a.stock_location_id, a.material_id, 
                a.bal_qty, a.rate
        From bal_rate a
        Where (a.stock_location_id = pstock_location_id Or pstock_location_id = 0); */
	
	return query 
	select a.branch_id, a.stock_location_id, a.material_id, a.balance_qty_base, a.rate
	from mat_bal_wac a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
create or replace function st.fn_sl_report(pcompany_id bigint, pbranch_id bigint, pfinyear varchar(4), pmaterial_id bigint, pfrom_date date, pto_date date, pstock_location_id bigint)
RETURNS TABLE  
(
	material_id bigint,
	material_name varchar(250),
	material_code varchar(20),
	doc_date date,
	vch_tran_id varchar(50),
	reference_id varchar(50),
	reference_tran_id varchar(50),
	narration varchar(500),
	received_qty numeric(18,4),
	issued_qty numeric(18,4),
	unit_rate_lc numeric(18,4),
	uom_desc varchar(20),
	uom_id bigint,
	uom_qty numeric(18,4),
	uom_qty_desc varchar(50),
	inserted_on timestamp,
	stock_location varchar(250)
)
AS
$BODY$ 
	declare vDate date; vUnitsInBase numeric(18,6); vUoMDesc varchar(20); vStockLocation varchar(250);
Begin	
	DROP TABLE IF EXISTS sl_temp;
	CREATE TEMP TABLE sl_temp(
		material_id bigint,
		material_name varchar(250),
		material_code varchar(20),
		doc_date date,
		vch_tran_id varchar(50),
		reference_id varchar(50),
		reference_tran_id varchar(50),
		narration varchar(500),
		received_qty numeric(18,4),
		issued_qty numeric(18,4),
		unit_rate_lc numeric(18,4),
		uom_desc varchar(20),
		uom_id bigint,
		uom_qty numeric(18,4),
		uom_qty_desc varchar(50),
		inserted_on timestamp,
		stock_location varchar(250),
                kg_fat numeric(18,4),
                kg_snf numeric(18,4)
	);

	vDate := pfrom_date - '1 day'::interval;
	
	-- Fetch opening Rate
	DROP TABLE IF EXISTS op_bal_rate;
	CREATE TEMP TABLE op_bal_rate(
		material_id bigint,
		balance_qty numeric(18,4),
		rate numeric(18,4)
	);
	
	Insert into op_bal_rate(material_id, balance_qty, rate)
	Select a.material_id, coalesce(sum(a.balance_qty_base), 0) as balance_qty, sys.fn_handle_zero_divide(coalesce(sum(a.balance_qty_base * a.rate), 0), coalesce(sum(a.balance_qty_base), 0)) as rate
	From st.fn_material_balance_wac_detail(pcompany_id, pbranch_id, pmaterial_id, pstock_location_id, pfinyear, vDate) a
	group by a.material_id;

	
	-- Insert opening balance as the first record with balance qty as received qty
	Insert into sl_temp(material_id, material_name, material_code,
				doc_date, vch_tran_id, reference_id, reference_tran_id, narration, 
				received_qty, issued_qty, 
				unit_rate_lc, uom_desc, uom_id, uom_qty, uom_qty_desc, inserted_on)
	Select a.material_id, a.material_name, a.material_code, 
		pfrom_date, 'Opening Balance' as vch_tran_id, '' as reference_id, '' as reference_tran_id, '' as narration, 
		coalesce(c.balance_qty, 0), 0,
		c.rate as unit_rate_lc, b.uom_desc, -1 as uom_id, 0 as uom_qty, '' as uom_qty_desc, '1970-01-01 00:00:00'
	From st.material a
	Inner Join st.fn_material_uom_base() b on a.material_id = b.material_id
	Inner Join op_bal_rate c on  a.material_id = c.material_id
	where (a.material_id = pmaterial_id or pmaterial_id = 0)
		And c.balance_qty != 0
	Union All -- Insert Stock Ledger Balance 
	Select a.material_id, a.material_name, a.material_code,  
		d.doc_date, d.vch_tran_id, d.reference_id, d.reference_tran_id, d.narration, 
		d.received_qty, d.issued_qty,
		d.unit_rate_lc, b.uom_desc, d.uom_id, d.uom_qty, '', d.inserted_on
	From st.material a
	Inner Join st.fn_material_uom_base() b on a.material_id = b.material_id
	Left Join st.stock_ledger d on a.material_id = d.material_id
	Where d.finyear = pfinyear
		And d.doc_date between pfrom_date and pto_date
		And d.company_id = pcompany_id
		And (d.branch_id = pbranch_id or pbranch_id=0)
		And (d.material_id = pmaterial_id or pmaterial_id = 0)
		And (d.stock_location_id = pstock_location_id or pstock_location_id = 0);

	Select a.stock_location_name into vStockLocation
	From st.stock_location  a
	where a.stock_location_id = pstock_location_id;
    
	return query 
	select a.material_id, a.material_name, a.material_code, a.doc_date, a.vch_tran_id, a.reference_id, a.reference_tran_id, a.narration, 
		a.received_qty, a.issued_qty, a.unit_rate_lc, a.uom_desc, a.uom_id, a.uom_qty, a.uom_qty_desc, a.inserted_on, vStockLocation
	from sl_temp a
	order by a.material_name, a.doc_date, a.inserted_on;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_sales_purchase_report(IN pcompany_id bigint, IN pbranch_id bigint, IN pfrom_date date, IN pto_date date, IN panalysis varchar, pis_base_value boolean)
RETURNS TABLE(
	company_id bigint,
	doc_date date,
	branch_id bigint,
	branch_name varchar(100),
	stock_id varchar(50),
	fc_type_id bigint,
	currency varchar(20),
	account_id bigint,
	exch_rate numeric(18,6),
	stock_tran_id varchar(50),
	sl_no smallint,
    material_type_id bigint,
    material_type varchar(50),
	material_id bigint,
	material_name varchar(250),
	uom_id bigint,
	uom_desc varchar(20),
	base_qty numeric(18,4),
	base_rate numeric(18,4),
	base_rate_fc numeric(18,4),
	invoice_qty numeric(18,4),
	invoice_rate numeric(18,4),
	invoice_rate_fc numeric(18,4),
	invoice_amt numeric(18,4),
	invoice_amt_fc numeric(18,4),
	discount numeric(18,4),
	discount_fc numeric(18,4),
	account_head varchar(250),
	reference_tran_id varchar(50),
	bt_amt numeric(18,4),
	tax_amt numeric(18,4),
    sale_purchase_account_id bigint,
    sale_purchase_account character varying
)As
$BODY$
BEGIN	

    DROP TABLE IF EXISTS sales_purchase_report_temp;	
    Create temp table sales_purchase_report_temp
    (   company_id bigint,
        doc_date date,
        branch_id bigint,
        branch_name varchar(100),
        stock_id varchar(50),
        fc_type_id bigint,
        currency varchar(20),
        account_id bigint,
        exch_rate numeric(18,6),
        stock_tran_id varchar(50),
        sl_no smallint,
        material_type_id bigint,
        material_type varchar(50),
        material_id bigint,
        material_name varchar(250),
        uom_id bigint,
        uom_desc varchar(20),
        base_qty numeric(18,4),
        base_rate numeric(18,4),
        base_rate_fc numeric(18,4),
        invoice_qty numeric(18,4),
        invoice_rate numeric(18,4),
        invoice_rate_fc numeric(18,4),
        invoice_amt numeric(18,4),
        invoice_amt_fc numeric(18,4),
        discount numeric(18,4),
        discount_fc numeric(18,4),
        account_head varchar(250),
        reference_tran_id varchar(50),
        bt_amt numeric(18,4) default (0),
        tax_amt numeric(18,4) default (0),
        sale_purchase_account_id bigint,
        sale_purchase_account character varying
    );

	IF panalysis = 'SI' THEN 
			INSERT INTO sales_purchase_report_temp(company_id, doc_date, branch_id, branch_name, stock_id, fc_type_id, currency, account_id, exch_rate, 
				stock_tran_id, sl_no, material_id, material_name, uom_id, uom_desc, 
				base_qty, 
				base_rate, 
				base_rate_fc, 
				invoice_qty, 
				invoice_rate, invoice_rate_fc, invoice_amt, invoice_amt_fc, discount, discount_fc, account_head, reference_tran_id, sale_purchase_account_id, sale_purchase_account)
			SELECT a.company_id, a.doc_date, a.branch_id, d.branch_name, a.stock_id, a.fc_type_id, g.currency, a.account_id, 
				a.exch_rate, b.stock_tran_id, b.sl_no, b.material_id, e.material_name, b.uom_id, f.uom_desc, 
				b.issued_qty-(Case When a.doc_type = 'SRV' And (a.annex_info->>'dcn_type')::Int = Any('{0,3}') Then b.received_qty Else 0.0 End) AS base_qty, 
				sys.fn_handle_zero_divide(b.item_amt, b.issued_qty-(Case When a.doc_type = 'SRV' And (a.annex_info->>'dcn_type')::Int = Any('{0,2,3}') Then b.received_qty Else 0.0 End)) AS base_rate, 
				sys.fn_handle_zero_divide(b.item_amt_fc, b.issued_qty-b.received_qty) AS base_rate_fc, 
				b.issued_qty-(Case When a.doc_type = 'SRV' And (a.annex_info->>'dcn_type')::Int = Any('{0,3}') Then b.received_qty Else 0.0 End) AS invoice_qty, 
				b.rate AS invoice_rate, b.rate_fc AS invoice_rate_fc, 
				Case When b.issued_qty > 0 Or (a.doc_type = 'SRV' And (a.annex_info->>'dcn_type')::Int = Any('{1}')) Then b.item_amt Else -b.item_amt End nvoice_amt, 
				Case When b.issued_qty > 0 Then b.item_amt_fc Else -b.item_amt_fc End invoice_amt_fc, b.disc_amt, b.disc_amt_fc, 
					c.account_head, b.reference_tran_id, -1, ''
			FROM st.stock_control a 
			INNER JOIN st.stock_tran b ON a.stock_id = b.stock_id
			INNER JOIN ac.account_head c ON a.account_id = c.account_id
			INNER JOIN sys.branch d ON a.branch_id = d.branch_id
			INNER JOIN st.material e ON b.material_id = e.material_id
			INNER JOIN st.uom f ON b.uom_id = f.uom_id
			INNER JOIN ac.fc_type g ON a.fc_type_id = g.fc_type_id
			WHERE a.company_id = pcompany_id 
				AND (a.branch_id in (select g.branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id) g) or pbranch_id = 0)
				AND a.doc_type = Any('{SIV,SRV}'::Varchar[])
				AND a.doc_date between pfrom_date and pto_date
				AND a.status = 5
			Union All
			SELECT a.company_id, a.doc_date, a.branch_id, d.branch_name, a.inv_id, 0 fc_type_id, 'INR' currency, 0 account_id, 
				1.00 exch_rate, b.inv_tran_id, b.sl_no, b.material_id, e.material_name, b.uom_id, f.uom_desc, 
				b.issued_qty-b.received_qty AS base_qty, 
				sys.fn_handle_zero_divide(b.item_amt, b.issued_qty-b.received_qty) AS base_rate, 
				0.00 AS base_rate_fc, b.issued_qty-b.received_qty AS invoice_qty, 
				b.rate AS invoice_rate, 0.00 invoice_rate_fc, 
				Case When b.issued_qty > 0 Then b.item_amt Else -b.item_amt End invoice_amt, 
				Case When b.issued_qty > 0 Then 0.00 Else 0.00 End invoice_amt_fc, b.disc_amt, 0.00, 
					'Walk-in Customer' account_head, '' reference_tran_id, -1, ''
			FROM pos.inv_control a 
			INNER JOIN pos.inv_tran b ON a.inv_id = b.inv_id
			INNER JOIN sys.branch d ON a.branch_id = d.branch_id
			INNER JOIN st.material e ON b.material_id = e.material_id
			INNER JOIN st.uom f ON b.uom_id = f.uom_id
			WHERE a.company_id = pcompany_id 
				AND (a.branch_id in (select g.branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id) g) or pbranch_id = 0)
				AND a.doc_type = Any('{PIV,PIR}'::Varchar[])
				AND a.doc_date between pfrom_date and pto_date
				AND a.status = 5;
	
            update sales_purchase_report_temp a
            set bt_amt = Case When a.invoice_amt > 0 Then b.bt_amt Else -b.bt_amt End,
                    tax_amt = Case When a.invoice_amt > 0 
                                    Then (b.sgst_amt + b.cgst_amt + b.igst_amt + b.cess_amt) 
                                    Else -(b.sgst_amt + b.cgst_amt + b.igst_amt + b.cess_amt)
                              End
            From tx.gst_tax_tran b 
            where a.stock_tran_id = b.gst_tax_tran_id; 
    
    		update sales_purchase_report_temp a
    		set sale_purchase_account_id = b.sale_account_id,
    			sale_purchase_account = c.account_head
    		From st.stock_control b
    		inner join ac.account_head c on b.sale_account_id = c.account_id
    		Where a.stock_id = b.stock_id
            		And b.doc_type = 'SIV';
    
    		update sales_purchase_report_temp a
    		set sale_purchase_account_id = b.sale_account_id,
    			sale_purchase_account = c.account_head
    		From st.stock_control d
                Inner join st.stock_control b on (d.annex_info->>'origin_inv_id')::varchar = b.stock_id
    		inner join ac.account_head c on b.sale_account_id = c.account_id
    		Where a.stock_id = d.stock_id
            		And d.doc_type = 'SRV'
                    And a.sale_purchase_account_id = -1;
    
        
	ELSIF panalysis = 'SP' then
            If pis_base_value = True then 
                With gtt 
                As 
                (	select a.stock_id, (c->>'bt_amt')::numeric as bt_amt, (c->>'sgst_amt')::numeric as sgst_amt, (c->>'cgst_amt')::numeric as cgst_amt, 
                        (c->>'igst_amt')::numeric as igst_amt, ((c->>'sgst_pcnt')::numeric + (c->>'cgst_pcnt')::numeric + (c->>'igst_pcnt')::numeric) as gst_rate
                    from st.stock_control a, jsonb_array_elements(a.annex_info->'gst_tax_tran') c
                )
                INSERT INTO sales_purchase_report_temp(company_id, doc_date, branch_id, branch_name, stock_id, fc_type_id, currency, account_id, exch_rate, 
                    stock_tran_id, sl_no, material_id, material_name, uom_id, uom_desc, 
                    base_qty, base_rate, 
                    base_rate_fc, 
                    invoice_qty, invoice_rate, invoice_rate_fc, invoice_amt, invoice_amt_fc, 
                    discount, discount_fc, account_head, reference_tran_id,
                    bt_amt, tax_amt, sale_purchase_account_id, sale_purchase_account)
                SELECT a.company_id, a.doc_date, a.branch_id, d.branch_name, a.stock_id, a.fc_type_id, g.currency, a.account_id, 
                    a.exch_rate, b.stock_tran_id, b.sl_no, b.material_id, e.material_name, b.uom_id, f.uom_desc, 
                    st.sp_get_base_qty(b.uom_id, b.received_qty-b.issued_qty) AS base_qty, sys.fn_handle_zero_divide(b.item_amt, st.sp_get_base_qty(b.uom_id, b.received_qty-b.issued_qty)) AS base_rate, 
                    sys.fn_handle_zero_divide(b.item_amt_fc, st.sp_get_base_qty(b.uom_id, b.received_qty-b.issued_qty)) AS base_rate_fc, 
                    b.received_qty AS invoice_qty, b.rate AS invoice_rate, b.rate_fc AS invoice_rate_fc, 
                    Case When b.received_qty > 0 Then b.item_amt Else -b.item_amt End invoice_amt, 
                    Case When b.received_qty > 0 Then b.item_amt_fc Else -b.item_amt_fc End invoice_amt_fc, 
                    b.disc_amt, b.disc_amt_fc, c.account_head, b.reference_tran_id,
                    b.bt_amt, 0, -1, ''
                FROM st.stock_control a 
                INNER JOIN st.stock_tran b ON a.stock_id = b.stock_id
                INNER JOIN ac.account_head c ON a.account_id = c.account_id
                INNER JOIN sys.branch d ON a.branch_id = d.branch_id
                INNER JOIN st.material e ON b.material_id = e.material_id
                INNER JOIN st.uom f ON e.material_id = f.material_id and is_base = true
                INNER JOIN ac.fc_type g ON a.fc_type_id = g.fc_type_id
                Inner join gtt h on a.stock_id = h.stock_id
                WHERE a.company_id = pcompany_id 
                    AND (a.branch_id in (select g.branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id) g) or pbranch_id = 0)
                                    AND a.doc_type = Any('{SPG,PRV}'::Varchar[]) 
                    AND a.doc_date between pfrom_date and pto_date AND a.status = 5;
            Else        	
                INSERT INTO sales_purchase_report_temp(company_id, doc_date, branch_id, branch_name, stock_id, fc_type_id, currency, account_id, exch_rate, 
                    stock_tran_id, sl_no, material_id, material_name, uom_id, uom_desc, 
                    base_qty, base_rate, 
                    base_rate_fc, 
                    invoice_qty, invoice_rate, invoice_rate_fc, invoice_amt, invoice_amt_fc, 
                    discount, discount_fc, account_head, reference_tran_id,
                    bt_amt, tax_amt, sale_purchase_account_id, sale_purchase_account)
                SELECT a.company_id, a.doc_date, a.branch_id, d.branch_name, a.stock_id, a.fc_type_id, g.currency, a.account_id, 
                    a.exch_rate, b.stock_tran_id, b.sl_no, b.material_id, e.material_name, b.uom_id, f.uom_desc, 
                    b.received_qty-b.issued_qty AS base_qty, b.rate AS base_rate, 
                    sys.fn_handle_zero_divide(b.item_amt_fc, b.received_qty-b.issued_qty) AS base_rate_fc, 
                    b.received_qty-b.issued_qty AS invoice_qty, b.rate AS invoice_rate, b.rate_fc AS invoice_rate_fc, 
                    Case When b.received_qty > 0 Then b.item_amt Else -b.item_amt End invoice_amt, 
                    Case When b.received_qty > 0 Then b.item_amt_fc Else -b.item_amt_fc End invoice_amt_fc,
                        b.disc_amt, b.disc_amt_fc, c.account_head, b.reference_tran_id,
                    Case When b.received_qty > 0 Then b.bt_amt Else -b.bt_amt End bt_amt, 0, -1, ''
                FROM st.stock_control a 
                    INNER JOIN st.stock_tran b ON a.stock_id = b.stock_id
                    INNER JOIN ac.account_head c ON a.account_id = c.account_id
                    INNER JOIN sys.branch d ON a.branch_id = d.branch_id
                    INNER JOIN st.material e ON b.material_id = e.material_id
                    INNER JOIN st.uom f ON b.uom_id = f.uom_id
                    INNER JOIN ac.fc_type g ON a.fc_type_id = g.fc_type_id
                WHERE a.company_id = pcompany_id 
                AND (a.branch_id in (select g.branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id) g) or pbranch_id = 0)
                                AND a.doc_type = Any('{SPG,PRV}'::Varchar[]) 
                AND a.doc_date between pfrom_date and pto_date 
                AND a.status = 5;
            End If;

            -- Update PRV tax info
            Update sales_purchase_report_temp a
            set tax_amt = Case When a.base_qty > 0 Then (b.sgst_amt + b.cgst_amt + b.igst_amt) Else -(b.sgst_amt + b.cgst_amt + b.igst_amt) End
            from tx.gst_tax_tran b
            where a.stock_tran_id = b.gst_tax_tran_id;

            with st_tran
            As 
            (   Select a.stock_id, a.stock_tran_id, b.gst_rate_id, b.hsn_sc_code, a.bt_amt
                From sales_purchase_report_temp a
                inner join tx.gst_tax_tran b on a.stock_tran_id = b.gst_tax_tran_id
            ),
            gst_tax_tran
            As
            (   Select a.stock_id, x.*
                From st.stock_control a, 
                    jsonb_to_recordset(a.annex_info->'gst_tax_tran') as x (
                        sl_no BigInt, hsn_sc_code character varying, apply_itc Boolean, gst_rate_id BigInt, bt_amt Numeric, tax_amt_ov Boolean,
                        sgst_pcnt Numeric, sgst_amt Numeric, cgst_pcnt Numeric, cgst_amt Numeric,
                        igst_pcnt Numeric, igst_amt Numeric, cess_pcnt Numeric, cess_amt Numeric)
                Where coalesce((a.annex_info->'gst_rc_info'->>'apply_rc')::Boolean, false) != true
                    And a.company_id = pcompany_id 
                    AND (a.branch_id in (select g.branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id) g) or pbranch_id = 0)
                    AND a.doc_type = Any('{SPG,PRV}'::Varchar[]) 
                    AND a.doc_date between pfrom_date and pto_date 
                    AND a.status = 5
            ),
            perc_calc
            As
            (   select round((sys.fn_handle_zero_divide(a.bt_amt,b.bt_amt)* 100), 2) as perc, a.stock_tran_id, a.bt_amt, (b.sgst_amt + b.cgst_amt + b.igst_amt + b.cess_amt) as tax_tot_amt
                From st_tran a
                inner join gst_tax_tran b on (b.gst_rate_id ||'-'|| b.hsn_sc_code) = (a.gst_rate_id ||'-'|| a.hsn_sc_code) and a.stock_id = b.stock_id
            ),
            tax_calc
            As
            (   select a.stock_tran_id, a.bt_amt, round(((a.tax_tot_amt * perc)/100), 2) as tax_amt
                From perc_calc a
            )
            update sales_purchase_report_temp a
            set tax_amt = Case When a.base_qty > 0 Then b.tax_amt Else -b.tax_amt End
            from tax_calc b
            where a.stock_tran_id = b.stock_tran_id;
	END IF;	

	update sales_purchase_report_temp a
	set currency = b.currency
	from sys.branch b
	Where a.branch_id = b.branch_id
            And a.fc_type_id = 0;   
        
        update sales_purchase_report_temp a
        set material_type_id=b.material_type_id,
            material_type = c.material_type
        from st.material b
        inner join st.material_type c on b.material_type_id=c.material_type_id
        where a.material_id=b.material_id; 
    
	RETURN query
        SELECT a.company_id, a.doc_date, a.branch_id, a.branch_name, a.stock_id, a.fc_type_id, a.currency, a.account_id, a.exch_rate, 
            a.stock_tran_id, a.sl_no, a.material_type_id, a.material_type, a.material_id, a.material_name, a.uom_id, a.uom_desc, a.base_qty, a.base_rate, a.base_rate_fc, 
            a.invoice_qty, a.invoice_rate, a.invoice_rate_fc, a.invoice_amt, a.invoice_amt_fc, a.discount, a.discount_fc, 
            a.account_head, a.reference_tran_id, a.bt_amt, a.tax_amt, a.sale_purchase_account_id, a.sale_purchase_account
        FROM sales_purchase_report_temp a;

END;
$BODY$
LANGUAGE plpgsql;

?==? 
CREATE OR REPLACE Function st.fn_purchase_register_material(IN pcompany_id bigint, IN pbranch_id bigint)
RETURNS TABLE
(   
        stock_id varchar(50),
        stock_tran_id varchar(50),
        doc_date date,
	account_id bigint,
	creditor varchar(250),
	creditor_amt numeric(18,4), 
	sl_no bigint,
	bill_no varchar(50),
	bill_date date,
	material_id bigint,
	material_name varchar(250), 
	quantity numeric(18,4),
	uom_id bigint, 
	uom_desc varchar(20),  
	rate numeric(18,4),		
	stock_location_id bigint, 
	stock_location_name varchar(250),
	status smallint
)
As
$BODY$ 
Begin
	DROP TABLE IF EXISTS purchase_register_material_temp;	
	create temp table purchase_register_material_temp
	(
		stock_id varchar(50),
		stock_tran_id varchar(50),
		doc_date date,
		account_id bigint,
		creditor varchar(250),
		creditor_amt numeric(18,4), 
		sl_no bigint,
		bill_no varchar(50),
		bill_date date,
		material_id bigint,
		material_name varchar(250), 
		quantity numeric(18,4),
		uom_id bigint, 
		uom_desc varchar(20),  
		rate numeric(18,4),		
		stock_location_id bigint, 
		stock_location_name varchar(250),
		status smallint
	);
	INSERT INTO purchase_register_material_temp( stock_id, stock_tran_id, doc_date, account_id, creditor, creditor_amt, sl_no, bill_no, bill_date, material_id,
		material_name, quantity, uom_id, uom_desc, rate, stock_location_id, stock_location_name, status)
	-- fetch Stock purchase
	SELECT a.stock_id, b.stock_tran_id, a.doc_date, a.account_id, f.account_head As creditor, a.total_amt As creditor_amt, b.sl_no, a.bill_no, a.bill_date, b.material_id, 
	       c.material_name, b.received_qty As quantity, coalesce(b.uom_id, -1) As uom_id, coalesce(e.uom_desc, '') As uom_desc, b.rate, 
	       b.stock_location_id, d.stock_location_name, a.status
	FROM st.stock_control a
	     INNER JOIN st.stock_tran b ON a.stock_id = b.stock_id
	     LEFT JOIN st.material c ON b.material_id = c.material_id
	     LEFT JOIN st.stock_location d ON b.stock_location_id = d.stock_location_id
	     LEFT JOIN st.fn_material_uom_base() e ON b.material_id = e.material_id
	     LEFT JOIN ac.account_head f ON a.account_id = f.account_id
	WHERE a.doc_type IN ('SP') 
	     and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
	     and a.company_id=pcompany_id 
	     and a.status = 5
	-- fetch Stock purchase return
	UNION ALL
	SELECT a.stock_id, b.stock_tran_id, a.doc_date, a.account_id, f.account_head As creditor, a.total_amt As creditor_amt, b.sl_no, '' As bill_no, a.bill_date, b.material_id, c.material_name, 
	       b.issued_qty As quantity, coalesce(b.uom_id, -1) As uom_id, coalesce(e.uom_desc, '') As uom_desc, b.rate, b.stock_location_id,
	       d.stock_location_name, a.status
	FROM st.stock_control a
	     INNER JOIN st.stock_tran b ON a.stock_id = b.stock_id
	     LEFT JOIN st.material c ON b.material_id = c.material_id
	     LEFT JOIN st.stock_location d ON b.stock_location_id = d.stock_location_id
	     LEFT JOIN st.fn_material_uom_base() e ON b.material_id = e.material_id
	     LEFT JOIN ac.account_head f ON a.account_id = f.account_id
	WHERE a.doc_type IN ('PR') 
	     and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
	     and a.company_id=pcompany_id 
	     and a.status = 5;
	          
	RETURN query
	SELECT a.stock_id, a.stock_tran_id, a.doc_date, a.account_id, a.creditor, a.creditor_amt, a.sl_no, a.bill_no, a.bill_date, a.material_id, a.material_name, 
	       a.quantity, a.uom_id, a.uom_desc, a.rate, a.stock_location_id, a.stock_location_name, a.status 
	FROM purchase_register_material_temp a;
	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE Function st.fn_purchase_register_tran()
RETURNS TABLE
(   
	stock_id varchar(50),
	tran_id varchar(50),
	account_id bigint,
	account_head varchar(250), 
	debit_amt numeric(18,4)
)
As
$BODY$ 
Begin	
        DROP TABLE IF EXISTS purchase_register_tran_temp;	
	create temp table purchase_register_tran_temp 
	(
		stock_id varchar(50),
		tran_id varchar(50), 
		account_id bigint,
		account_head varchar(250), 
		debit_amt numeric(18,4)
	);
	INSERT INTO purchase_register_tran_temp( stock_id, tran_id, account_id, account_head, debit_amt)
	-- fetch Stock purchase Account
	SELECT a.stock_id, a.stock_tran_id As tran_id, d.sale_account_id, c.account_head, a.item_amt As debit_amt
	FROM st.stock_tran a
	INNER JOIN st.stock_control d ON a.stock_id = d.stock_id
	LEFT JOIN ac.account_head c ON d.sale_account_id = c.account_id
	WHERE d.doc_type In ('SP')
	UNION ALL
	-- fetch Stock purchase return Account
	SELECT a.stock_id, a.stock_tran_id As tran_id, b.sale_account_id, c.account_head, -a.item_amt As debit_amt
	FROM st.stock_tran a
	INNER JOIN st.stock_control d ON a.stock_id = d.stock_id
	INNER JOIN st.stock_control b ON d.reference_id = b.stock_id
	LEFT JOIN ac.account_head c ON b.sale_account_id = c.account_id	
	WHERE d.doc_type In ('PR')
        UNION ALL
	-- fetch lc Account
	SELECT a.stock_id, a.stock_lc_tran_id As tran_id, a.account_affected_id, 
		case when supplier_paid then 'By Supplier'
			else b.account_head end as account_head, a.debit_amt As debit_amt
	FROM st.stock_lc_tran a
	LEFT JOIN ac.account_head b ON a.account_affected_id = b.account_id
	INNER JOIN st.stock_control c ON a.stock_id = c.stock_id
	WHERE c.doc_type In ('SP')
	 UNION ALL
	-- fetch tax Account
	SELECT a.voucher_id, a.tax_tran_id As tran_id, a.account_id, b.account_head, a.tax_amt As debit_amt
	FROM tx.tax_tran a
	LEFT JOIN ac.account_head b ON a.account_id = b.account_id
	INNER JOIN st.stock_control c ON a.voucher_id = c.stock_id
	WHERE c.doc_type In ('SP');

	RETURN query
	SELECT a.stock_id, a.tran_id, a.account_id, a.account_head, a.debit_amt
	FROM purchase_register_tran_temp a;
	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE Function st.fn_purchase_register(IN pcompany_id bigint, IN pbranch_id bigint, IN pfrom_date date, IN pto_date date)
RETURNS TABLE
(   
	sales_group varchar(50),
	doc_date date,
	creditor varchar(250),
	stock_id varchar(50),
	bill_no varchar(50),
	bill_date date, 
	sl_no bigint,
	material_id bigint,
	material_name varchar(250), 
	quantity numeric(18,4),
	uom_id bigint, 
	uom_desc varchar(20),  
	rate numeric(18,4), 
	creditor_amt numeric(18,4),
	account_id bigint,
	account_head varchar(250),
	debit_amt numeric(18,4)
)
As
$BODY$ 
Begin
	DROP TABLE IF EXISTS purchase_register_temp;	
	create temp table purchase_register_temp
	(
                sales_group varchar(50),
		doc_date date,
		creditor varchar(250),
		stock_id varchar(50),
		bill_no varchar(50),
		bill_date date, 
		sl_no bigint,
		material_id bigint,
		material_name varchar(250), 
		quantity numeric(18,4),
		uom_id bigint, 
		uom_desc varchar(20),  
		rate numeric(18,4), 
		creditor_amt numeric(18,4),
		account_id bigint,
		account_head varchar(250),
		debit_amt numeric(18,4)
	);
        INSERT INTO purchase_register_temp( sales_group, doc_date, creditor, stock_id, bill_no, bill_date, sl_no, material_id,
		material_name, quantity, uom_id, uom_desc, rate, creditor_amt, account_id, account_head, debit_amt)
	SELECT 'Purchase Group' As sales_group, a.doc_date, a.creditor, a.stock_id, a.bill_no, a.bill_date, a.sl_no, a.material_id,
		a.material_name, a.quantity, a.uom_id, a.uom_desc, a.rate, a.creditor_amt, b.account_id, b.account_head, b.debit_amt
        FROM st.fn_purchase_register_material(pcompany_id, pbranch_id) a
	     INNER JOIN st.fn_purchase_register_tran() b ON a.stock_id = b.stock_id
	WHERE a.sl_no = 1 and a.doc_date Between pfrom_date And pto_date 
	UNION ALL 
        SELECT 'Purchase Group' As sales_group, a.doc_date, a.creditor, a.stock_id, a.bill_no, a.bill_date, a.sl_no, a.material_id,
		a.material_name, a.quantity, a.uom_id, a.uom_desc, a.rate, 0 As creditor_amt, b.account_id, b.account_head,
		0 As debit_amt
        FROM st.fn_purchase_register_material(pcompany_id, pbranch_id) a
	     INNER JOIN st.fn_purchase_register_tran() b ON a.stock_id = b.stock_id
	WHERE a.sl_no > 1 and a.doc_date Between pfrom_date And pto_date;

	RETURN query
	SELECT a.sales_group, a.doc_date, a.creditor, a.stock_id, a.bill_no, a.bill_date, a.sl_no, a.material_id, a.material_name, a.quantity, 
	       a.uom_id, a.uom_desc, a.rate, a.creditor_amt, a.account_id, a.account_head, a.debit_amt
	FROM purchase_register_temp a;
	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_purchase_register_v2(
    IN pcompany_id bigint,
    IN pbranch_id bigint,
    IN pfrom_date date,
    IN pto_date date)
RETURNS TABLE
(	doc_date date,
 	doc_type character varying,
	supplier_id bigint, 
	supplier character varying, 
	voucher_id character varying, 
	bill_no character varying, 
	bill_date date, 
	supplier_tin character varying,
	purchase_amt numeric, 
	misc_amt numeric,
 	misc_ns_amt Numeric,
	vat_type_id bigint, 
	vat_type_desc character varying, 
	tax_type_id bigint, 
	tax_type character varying, 
	tax_detail_id bigint, 
	tax_detail character varying, 
	bt_amt numeric, 
	tax_amt numeric
) 
AS
$BODY$
Begin
	Return Query
	With item_tran 
	As
	(	Select a.stock_id, b.tax_type_id, d.tax_type, c.tax_detail_id, c.description as tax_detail,
			sum(a.bt_amt) as bt_amt, sum(a.tax_amt) as tax_amt
		From st.stock_tran a
		Left Join tx.tax_schedule b On a.tax_schedule_id = b.tax_schedule_id
		Left Join tx.tax_detail c On b.tax_schedule_id = c.tax_schedule_id And c.step_id = 1
		Left Join tx.tax_type d On b.tax_type_id = d.tax_type_id
		Inner Join st.stock_control e On a.stock_id = e.stock_id
		Where e.company_id = pcompany_id And (e.branch_id = pbranch_id Or pbranch_id = 0)
			And e.doc_date Between pfrom_date And pto_date
			And e.status = 5 And (e.annex_info->>'is_purchase_tax')::boolean = false
     		And e.doc_type = Any ('{SP}')
		Group by a.stock_id, b.tax_type_id, d.tax_type, c.tax_detail_id, c.description
		Union All
		Select a.stock_id, b.tax_type_id, d.tax_type, c.tax_detail_id, c.description as tax_detail,
			sum((e->>'bt_amt')::Numeric) as bt_amt, sum((e->>'tax_amt')::Numeric) as tax_amt
		From st.stock_control a, jsonb_array_elements(a.annex_info->'purchase_tax') e 
		Left Join tx.tax_schedule b On (e->>'tax_schedule_id')::BigInt = b.tax_schedule_id
		Left Join tx.tax_detail c On b.tax_schedule_id = c.tax_schedule_id And c.step_id = 1
		Left Join tx.tax_type d On b.tax_type_id = d.tax_type_id
		Where a.company_id = pcompany_id And (a.branch_id = pbranch_id Or pbranch_id = 0)
			And a.doc_date Between pfrom_date And pto_date
			And a.status = 5 And (a.annex_info->>'is_purchase_tax')::boolean = true
     		And a.doc_type = Any ('{SP}')
		Group by a.stock_id, b.tax_type_id, d.tax_type, c.tax_detail_id, c.description
     	Union All -- SI Buy Backs would be considered as purchases
     	Select a.inv_id, b.tax_type_id, d.tax_type, c.tax_detail_id, c.description as tax_detail,
			sum(a.bt_amt) as bt_amt, sum(a.tax_amt) as tax_amt
		From st.inv_bb a
		Left Join tx.tax_schedule b On a.tax_schedule_id = b.tax_schedule_id
		Left Join tx.tax_detail c On b.tax_schedule_id = c.tax_schedule_id And c.step_id = 1
		Left Join tx.tax_type d On b.tax_type_id = d.tax_type_id
		Inner Join st.stock_control e On a.inv_id = e.stock_id
		Where e.company_id = pcompany_id And (e.branch_id = pbranch_id Or pbranch_id = 0)
			And e.doc_date Between pfrom_date And pto_date
			And e.status = 5
     		And e.doc_type = Any ('{SI}')
		Group by a.inv_id, b.tax_type_id, d.tax_type, c.tax_detail_id, c.description
     	Union All -- PI (pos) Buy Backs would be considered as purchases
     	Select a.inv_id, b.tax_type_id, d.tax_type, c.tax_detail_id, c.description as tax_detail,
			sum(a.bt_amt) as bt_amt, sum(a.tax_amt) as tax_amt
		From pos.inv_bb a
		Left Join tx.tax_schedule b On a.tax_schedule_id = b.tax_schedule_id
		Left Join tx.tax_detail c On b.tax_schedule_id = c.tax_schedule_id And c.step_id = 1
		Left Join tx.tax_type d On b.tax_type_id = d.tax_type_id
		Inner Join pos.inv_control e On a.inv_id = e.inv_id
		Where e.company_id = pcompany_id And (e.branch_id = pbranch_id Or pbranch_id = 0)
			And e.doc_date Between pfrom_date And pto_date
			And e.status = 5
     		And e.doc_type = Any ('{PI}')
		Group by a.inv_id, b.tax_type_id, d.tax_type, c.tax_detail_id, c.description
	),
	lc_tran
	As 
	(	Select a.stock_id, b.tax_type_id, d.tax_type, c.tax_detail_id, c.description as tax_detail,
			sum(a.debit_amt) as bt_amt, sum(a.tax_amt) as tax_amt
		From st.stock_lc_tran a
		Inner Join tx.tax_schedule b On a.tax_schedule_id = b.tax_schedule_id
		Inner Join tx.tax_detail c On b.tax_schedule_id = c.tax_schedule_id And c.step_id = 1
		Inner Join tx.tax_type d On b.tax_type_id = d.tax_type_id
		Inner Join st.stock_control e On a.stock_id = e.stock_id
		Where e.company_id = pcompany_id And (e.branch_id = pbranch_id Or pbranch_id = 0)
			And e.doc_date Between pfrom_date And pto_date
			And e.status = 5 And (e.annex_info->>'is_purchase_tax')::boolean = false
     		And e.doc_type = Any ('{SP}')
     		And a.supplier_paid
		Group by a.stock_id, b.tax_type_id, d.tax_type, c.tax_detail_id, c.description

	),
	lc_tran_non_tax
	As
	(	Select a.stock_id, sum(a.debit_amt) as misc_non_tax_amt
		From st.stock_lc_tran a
		Inner Join st.stock_control b On a.stock_id = b.stock_id
		Where b.company_id = pcompany_id And (b.branch_id = pbranch_id Or pbranch_id = 0)
			And b.doc_date Between pfrom_date And pto_date
			And b.status = 5
			And a.tax_schedule_id = -1
     		And a.supplier_paid
     		And b.doc_type = Any ('{SP}')
		Group by a.stock_id
	),
	lc_tran_ns
	As
	(	-- This is Landed Cost loaded but not paid by Supplier
                Select a.stock_id, sum(a.debit_amt) as misc_ns_amt
		From st.stock_lc_tran a
		Inner Join st.stock_control b On a.stock_id = b.stock_id
		Where b.company_id = pcompany_id And (b.branch_id = pbranch_id Or pbranch_id = 0)
			And b.doc_date Between pfrom_date And pto_date
			And b.status = 5
			And a.supplier_paid = False
     		And b.doc_type = Any ('{SP}')
		Group by a.stock_id
	),
	union_tran
	As
	(	Select a.doc_date, a.doc_type, a.account_id, c.supplier, a.stock_id, a.bill_no, a.bill_date, (c.annex_info->'satutory_details'->>'vat_no')::varchar as supplier_tin,
			a.total_amt, coalesce(f.misc_non_tax_amt, 0.00) + a.round_off_amt as misc_amt, coalesce(e.misc_ns_amt, 0.00) as misc_ns_amt, d.vat_type_id, d.vat_type_desc, b.tax_type_id, b.tax_type, 
				b.tax_detail_id, b.tax_detail, b.bt_amt, b.tax_amt
		From st.stock_control a
		Left Join lc_tran_non_tax f On a.stock_id=f.stock_id
		Inner Join item_tran b On a.stock_id = b.stock_id
		Inner Join ap.supplier c On a.account_id = c.supplier_id
		Inner Join tx.vat_type d On a.vat_type_id = d.vat_type_id
                Left Join lc_tran_ns e On a.stock_id=e.stock_id
		Where a.company_id = pcompany_id And (a.branch_id = pbranch_id Or pbranch_id = 0)
			And a.doc_date Between pfrom_date And pto_date
			And a.status = 5
     		And a.doc_type = Any ('{SP}')
		Union All -- Buy Back Items SI
		Select a.doc_date, a.doc_type, a.account_id, 'Walk-in Customer', a.stock_id, '', null, '29000000000' as supplier_tin,
			Sum(b.bt_amt + b.tax_amt) Over (Partition By b.stock_id), 0.00, 0.00, d.vat_type_id, d.vat_type_desc, b.tax_type_id, b.tax_type, 
				b.tax_detail_id, b.tax_detail, b.bt_amt, b.tax_amt
		From st.stock_control a
		Inner Join item_tran b On a.stock_id = b.stock_id
		Inner Join tx.vat_type d On 205 = d.vat_type_id -- URD vat type
		Where a.company_id = pcompany_id And (a.branch_id = pbranch_id Or pbranch_id = 0)
			And a.doc_date Between pfrom_date And pto_date
			And a.status = 5
     		And a.doc_type = Any ('{SI}')
		Union All -- Buy Back Items PI (pos)
		Select a.doc_date, a.doc_type, -1, 'Walk-in Customer', a.inv_id, '', null, '29000000000' as supplier_tin,
			Sum(b.bt_amt+b.tax_amt) Over (Partition By b.stock_id), 0.00, 0.00, d.vat_type_id, d.vat_type_desc, b.tax_type_id, b.tax_type, 
				b.tax_detail_id, b.tax_detail, b.bt_amt, b.tax_amt
		From pos.inv_control a
		Inner Join item_tran b On a.inv_id = b.stock_id
		Inner Join tx.vat_type d On 205 = d.vat_type_id -- URD vat type
		Where a.company_id = pcompany_id And (a.branch_id = pbranch_id Or pbranch_id = 0)
			And a.doc_date Between pfrom_date And pto_date
			And a.status = 5
     		And a.doc_type = Any ('{PI}')
		Union All
		Select a.doc_date, a.doc_type, a.account_id, c.supplier, a.stock_id, a.bill_no, a.bill_date, (c.annex_info->'satutory_details'->>'vat_no')::varchar as supplier_tin,
			a.total_amt, coalesce(f.misc_non_tax_amt, 0.00) + a.round_off_amt as misc_amt, 0.00, d.vat_type_id, d.vat_type_desc, b.tax_type_id, b.tax_type,
			b.tax_detail_id, b.tax_detail, b.bt_amt, b.tax_amt
		From st.stock_control a
		Left Join lc_tran_non_tax f On a.stock_id=f.stock_id
		Inner Join lc_tran b On a.stock_id = b.stock_id
		Inner Join ap.supplier c On a.account_id = c.supplier_id
		Inner Join tx.vat_type d On a.vat_type_id = d.vat_type_id
		Where a.company_id = pcompany_id And (a.branch_id = pbranch_id Or pbranch_id = 0)
			And a.doc_date Between pfrom_date And pto_date
			And a.status = 5
     		And a.doc_type = Any ('{SP}')
	)
	Select a.doc_date, a.doc_type, a.account_id, a.supplier, a.stock_id, a.bill_no, a.bill_date, a.supplier_tin,
		a.total_amt, a.misc_amt, a.misc_ns_amt, a.vat_type_id, a.vat_type_desc, a.tax_type_id, a.tax_type, 
		a.tax_detail_id, a.tax_detail, a.bt_amt, a.tax_amt
	From union_tran a
	Order By a.doc_date, a.stock_id;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE Function st.fn_sales_register_material(IN pcompany_id bigint, IN pbranch_id bigint)
RETURNS TABLE
(   
        stock_id varchar(50),
        stock_tran_id varchar(50),
        doc_date date,
	sl_no bigint,
	material_id bigint,
	material_name varchar(250), 
	quantity numeric(18,4),
	uom_id bigint, 
	uom_desc varchar(20),  
	rate numeric(18,4),
	account_id bigint,		
	debtor varchar(250),
	debtor_amt numeric(18,4), 
	status smallint
)
As
$BODY$ 
Begin
	DROP TABLE IF EXISTS sales_register_material_temp;	
	create temp table sales_register_material_temp
	(
		stock_id varchar(50),
		stock_tran_id varchar(50),
		doc_date date,
		sl_no bigint,
		material_id bigint,
		material_name varchar(250), 
		quantity numeric(18,4),
		uom_id bigint, 
		uom_desc varchar(20),  
		rate numeric(18,4),
		account_id bigint,		
		debtor varchar(250),
		debtor_amt numeric(18,4), 
		status smallint
	);
	INSERT INTO sales_register_material_temp( stock_id, stock_tran_id, doc_date, sl_no, material_id, material_name, quantity,
		uom_id, uom_desc, rate, account_id, debtor, debtor_amt, status)
	-- fetch sales invoice
	SELECT a.stock_id, b.stock_tran_id, a.doc_date, b.sl_no, b.material_id, c.material_name, b.issued_qty As quantity, coalesce(b.uom_id, -1) As uom_id, coalesce(e.uom_desc, '') As uom_desc,
	      b.rate, a.account_id, f.account_head As debtor, a.net_amt As debtor_amt, a.status
	FROM st.stock_control a
	     INNER JOIN st.stock_tran b ON a.stock_id = b.stock_id
	     LEFT JOIN st.material c ON b.material_id = c.material_id
	     LEFT JOIN st.stock_location d ON b.stock_location_id = d.stock_location_id
	     LEFT JOIN st.fn_material_uom_base() e ON b.material_id = e.material_id
	     LEFT JOIN ac.account_head f ON a.account_id = f.account_id
	WHERE a.doc_type IN ('SI') 
	     and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
	     and a.company_id=pcompany_id 
	     and a.status = 5
	-- fetch sales return
	UNION ALL
	SELECT a.stock_id, b.stock_tran_id, a.doc_date, b.sl_no, b.material_id, c.material_name, b.received_qty As quantity, coalesce(b.uom_id, -1) As uom_id, coalesce(e.uom_desc, '') As uom_desc,
	      b.rate, a.account_id, f.account_head As debtor, a.net_amt As debtor_amt, a.status
	FROM st.stock_control a
	     INNER JOIN st.stock_tran b ON a.stock_id = b.stock_id
	     LEFT JOIN st.material c ON b.material_id = c.material_id
	     LEFT JOIN st.stock_location d ON b.stock_location_id = d.stock_location_id
	     LEFT JOIN st.fn_material_uom_base() e ON b.material_id = e.material_id
	     LEFT JOIN ac.account_head f ON a.account_id = f.account_id
	WHERE a.doc_type IN ('SR') 
	     and (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
	     and a.company_id=pcompany_id 
	     and a.status = 5;
	          
	RETURN query
	SELECT a.stock_id, a.stock_tran_id, a.doc_date, a.sl_no, a.material_id, a.material_name, a.quantity,
	       a.uom_id, a.uom_desc, a.rate, a.account_id, a.debtor, a.debtor_amt, a.status
	FROM sales_register_material_temp a;
	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE Function st.fn_sales_register_tran()
RETURNS TABLE
(   
	stock_id varchar(50),
	tran_id varchar(50),
	account_id bigint,
	account_head varchar(250), 
	tax_amt numeric(18,4)
)
As
$BODY$ 
Begin	
        DROP TABLE IF EXISTS sales_register_tran_temp;	
	create temp table sales_register_tran_temp 
	(
		stock_id varchar(50),
		tran_id varchar(50), 
		account_id bigint,
		account_head varchar(250), 
		tax_amt numeric(18,4)
	);
	INSERT INTO sales_register_tran_temp( stock_id, tran_id, account_id, account_head, tax_amt)
	-- fetch sales invoice Account
	SELECT a.stock_id, a.stock_tran_id As tran_id, d.sale_account_id, c.account_head, a.item_amt As tax_amt
	FROM st.stock_tran a
	INNER JOIN st.stock_control d ON a.stock_id = d.stock_id
	LEFT JOIN ac.account_head c ON d.sale_account_id = c.account_id
	WHERE d.doc_type In ('SI')
	UNION ALL
	-- fetch sales return Account
	SELECT a.stock_id, a.stock_tran_id As tran_id, b.sale_account_id, c.account_head, -a.item_amt As tax_amt
	FROM st.stock_tran a
	INNER JOIN st.stock_control d ON a.stock_id = d.stock_id
	Inner JOIN st.stock_control b ON d.reference_id = b.stock_id
	LEFT JOIN ac.account_head c ON b.sale_account_id = c.account_id	
	WHERE d.doc_type In ('SR')
        UNION ALL
	-- fetch lc Account
	SELECT a.stock_id, a.stock_lc_tran_id As tran_id, a.account_id, b.account_head, a.debit_amt As tax_amt
	FROM st.stock_lc_tran a
        LEFT JOIN ac.account_head b ON a.account_id = b.account_id
        INNER JOIN st.stock_control c ON a.stock_id = c.stock_id
	WHERE c.doc_type In ('SI')
	 UNION ALL
	-- fetch tax Account
	SELECT a.voucher_id, a.tax_tran_id As tran_id, a.account_id, b.account_head, a.tax_amt As tax_amt
	FROM tx.tax_tran a
        LEFT JOIN ac.account_head b ON a.account_id = b.account_id
        INNER JOIN st.stock_control c ON a.voucher_id = c.stock_id
	WHERE c.doc_type In ('SI');

	RETURN query
	SELECT a.stock_id, a.tran_id, a.account_id, a.account_head, a.tax_amt
	FROM sales_register_tran_temp a;
	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE Function st.fn_sales_register(IN pcompany_id bigint, IN pbranch_id bigint, IN pfrom_date date, IN pto_date date)
RETURNS TABLE
(   
	sales_group varchar(50),
	doc_date date,
	debtor varchar(250),
	stock_id varchar(50),
	sl_no bigint,
	material_id bigint,
	material_name varchar(250), 
	quantity numeric(18,4),
	uom_id bigint, 
	uom_desc varchar(20),  
	rate numeric(18,4), 
	debtor_amt numeric(18,4),
	account_id bigint,
	account_head varchar(250),
	tax_amt numeric(18,4)
)
As
$BODY$ 
Begin
	DROP TABLE IF EXISTS sales_register_temp;	
	create temp table sales_register_temp
	(
		sales_group varchar(50),
		doc_date date,
		debtor varchar(250),
		stock_id varchar(50),
		sl_no bigint,
		material_id bigint,
		material_name varchar(250), 
		quantity numeric(18,4),
		uom_id bigint, 
		uom_desc varchar(20),  
		rate numeric(18,4), 
		debtor_amt numeric(18,4),
		account_id bigint,
		account_head varchar(250),
		tax_amt numeric(18,4)
	);
        INSERT INTO sales_register_temp( sales_group, doc_date, debtor, stock_id, sl_no, material_id, material_name, quantity,
		uom_id, uom_desc, rate, debtor_amt, account_id, account_head, tax_amt)
	SELECT 'Sales Group' As sales_group, a.doc_date, a.debtor, a.stock_id, a.sl_no, a.material_id, a.material_name, 
	       a.quantity, a.uom_id, a.uom_desc, a.rate, a.debtor_amt, b.account_id, b.account_head, b.tax_amt
        FROM st.fn_sales_register_material(pcompany_id, pbranch_id) a
	     INNER JOIN st.fn_sales_register_tran() b ON a.stock_id = b.stock_id
	WHERE a.sl_no = 1 and a.doc_date Between pfrom_date And pto_date 
	UNION ALL 
        SELECT 'Sales Group' As sales_group, a.doc_date, a.debtor, a.stock_id, a.sl_no, a.material_id, a.material_name, 
	       a.quantity, a.uom_id, a.uom_desc, a.rate, 0 As  debtor_amt, b.account_id, b.account_head, 0 As tax_amt
        FROM st.fn_sales_register_material(pcompany_id, pbranch_id) a
	     INNER JOIN st.fn_sales_register_tran() b ON a.stock_id = b.stock_id
	WHERE a.sl_no > 1 and a.doc_date Between pfrom_date And pto_date;

	RETURN query
	SELECT a.sales_group, a.doc_date, a.debtor, a.stock_id, a.sl_no, a.material_id, a.material_name, a.quantity,
	       a.uom_id, a.uom_desc, a.rate, a.debtor_amt, a.account_id, a.account_head, a.tax_amt
	FROM sales_register_temp a;
	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_sale_register_v2(
    IN pcompany_id bigint,
    IN pbranch_id bigint,
    IN pfrom_date date,
    IN pto_date date)
RETURNS TABLE
(	doc_type character varying,
 	doc_date date,
	customer_id bigint, 
	customer character varying, 
	voucher_id character varying, 
	customer_tin character varying,
 	origin_inv_id character varying,
 	origin_inv_date date, 
	sale_amt numeric, 
	misc_amt numeric, 
	vat_type_id bigint, 
	vat_type_desc character varying, 
	tax_type_id bigint, 
	tax_type character varying, 
	tax_detail_id bigint, 
	tax_detail character varying, 
	bt_amt numeric, 
	tax_amt numeric
) 
AS
$BODY$
Begin
	Return Query
	With st_stock_tran 
	As
	(	Select a.stock_id, b.tax_type_id, d.tax_type, c.tax_detail_id, c.description as tax_detail,
			sum(a.bt_amt) as bt_amt, sum(a.tax_amt) as tax_amt
		From st.stock_tran a
		Left Join tx.tax_schedule b On a.tax_schedule_id = b.tax_schedule_id
		Left Join tx.tax_detail c On b.tax_schedule_id = c.tax_schedule_id And c.step_id = 1
		Left Join tx.tax_type d On b.tax_type_id = d.tax_type_id
		Inner Join st.stock_control e On a.stock_id = e.stock_id
		Where e.company_id = pcompany_id And (e.branch_id = pbranch_id Or pbranch_id = 0)
			And e.doc_date Between pfrom_date And pto_date
			And e.doc_type = Any ('{SI, SR, SRN}')
			And e.status = 5
		Group by a.stock_id, b.tax_type_id, d.tax_type, c.tax_detail_id, c.description
	),
	st_stock
	As
	(	Select a.doc_date, a.doc_type, a.account_id, c.customer_name, a.stock_id, 
     		(c.annex_info->'tax_info'->>'vtin')::varchar as customer_tin,
     		(a.annex_info->>'origin_inv_id')::varchar as origin_inv_id, (a.annex_info->>'origin_inv_date')::date as origin_inv_date,
			a.total_amt, a.round_off_amt as misc_amt, d.vat_type_id, d.vat_type_desc, b.tax_type_id, b.tax_type, 
				b.tax_detail_id, b.tax_detail, b.bt_amt, b.tax_amt
		From st.stock_control a
		Inner Join st_stock_tran b On a.stock_id = b.stock_id
		Inner Join ar.customer c On a.account_id = c.customer_id
		Inner Join tx.vat_type d On a.vat_type_id = d.vat_type_id
		Where a.company_id = pcompany_id And (a.branch_id = pbranch_id Or pbranch_id = 0)
			And a.doc_date Between pfrom_date And pto_date
			And a.doc_type = Any ('{SI, SR, SRN}')
			And a.status = 5
	),
	pos_inv_tran
	As
	(	Select a.inv_id, b.tax_type_id, d.tax_type, c.tax_detail_id, c.description as tax_detail,
			sum(a.bt_amt) as bt_amt, sum(a.tax_amt) as tax_amt
		From pos.inv_tran a
		Left Join tx.tax_schedule b On a.tax_schedule_id = b.tax_schedule_id
		Left Join tx.tax_detail c On b.tax_schedule_id = c.tax_schedule_id And c.step_id = 1
		Left Join tx.tax_type d On b.tax_type_id = d.tax_type_id
		Inner Join pos.inv_control e On a.inv_id = e.inv_id
		Where e.company_id = pcompany_id And (e.branch_id = pbranch_id Or pbranch_id = 0)
			And e.doc_date Between pfrom_date And pto_date
			And e.doc_type = Any ('{PI, PSR}')
			And e.status = 5
		Group by a.inv_id, b.tax_type_id, d.tax_type, c.tax_detail_id, c.description
	),
	pos_inv
	As
	(	Select a.doc_date, a.doc_type, a.sale_account_id, a.cust_name, a.inv_id, a.cust_tin, 
     		(a.annex_info->>'origin_inv_id')::varchar as origin_inv_id, (a.annex_info->>'origin_inv_date')::date as origin_inv_date,
			a.inv_amt, a.rof_amt as misc_amt, d.vat_type_id, d.vat_type_desc, b.tax_type_id, b.tax_type, 
				b.tax_detail_id, b.tax_detail, b.bt_amt, b.tax_amt
		From pos.inv_control a
		Inner Join pos_inv_tran b On a.inv_id = b.inv_id
		Inner Join pos.inv_settle c On a.inv_id = c.inv_id
		Inner Join tx.vat_type d On a.vat_type_id = d.vat_type_id
		Where a.company_id = pcompany_id And (a.branch_id = pbranch_id Or pbranch_id = 0)
			And a.doc_date Between pfrom_date And pto_date
			And a.doc_type = Any ('{PI, PSR}')
			And a.status = 5
	),
	union_tran
	As
	(	Select a.doc_date, a.doc_type, a.account_id, a.customer_name, a.stock_id, 
     		a.customer_tin, 
     		a.origin_inv_id, a.origin_inv_date,
			a.total_amt, a.misc_amt, a.vat_type_id, a.vat_type_desc, a.tax_type_id, a.tax_type, 
			a.tax_detail_id, a.tax_detail, a.bt_amt, a.tax_amt
		From st_stock a
		Union All
		Select a.doc_date, a.doc_type, a.sale_account_id, a.cust_name, a.inv_id, 
     		case When (a.cust_tin = 'N.A.' Or a.cust_tin = '') Then '29000000000' Else a.cust_tin End,
     		a.origin_inv_id, a.origin_inv_date,
			a.inv_amt, a.misc_amt, a.vat_type_id, a.vat_type_desc, a.tax_type_id, a.tax_type, 
			a.tax_detail_id, a.tax_detail, a.bt_amt, a.tax_amt
		From pos_inv a

	)
	Select a.doc_type, a.doc_date, a.account_id, a.customer_name, a.stock_id, a.customer_tin, a.origin_inv_id, a.origin_inv_date,
		a.total_amt, a.misc_amt, a.vat_type_id, a.vat_type_desc, a.tax_type_id, a.tax_type, 
		a.tax_detail_id, a.tax_detail, a.bt_amt, a.tax_amt
	From union_tran a
	Order By a.doc_type, a.doc_date, stock_id;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE function st.fn_sl_unpost_val_nst(pvoucher_id Varchar(50))
RETURNS Table 
(	
	branch_id bigint,
	doc_date date,
	inserted_on timestamp,
	stock_location_id bigint,
	stock_location_name varchar(250),
	material_id bigint,		
	material_name varchar(250),
	material_code varchar(20),
	net_qty numeric(18,4),
	balance numeric(18,4)
)
as 
$BODY$
Declare vErrorMsg varchar(250); vCnt bigint = 0; vdoc_date Date;
Begin
	DROP TABLE IF EXISTS deleted_temp;	
	create temp TABLE  deleted_temp
	(	
		company_id bigint,
		branch_id bigint,
		finyear varchar(4),
		doc_date date,
		stock_location_id bigint,
		material_id bigint,		
		received_qty numeric(18,4),
		issued_qty numeric(18,4)
	);

	Insert into deleted_temp (company_id, branch_id, finyear, doc_date, stock_location_id, material_id, received_qty, issued_qty)
	Select a.company_id, a.branch_id, a.finyear, a.doc_date, a.stock_location_id, a.material_id, a.received_qty, a.issued_qty 
	from st.stock_ledger a 
	where a.voucher_id=pvoucher_id;

        Select a.doc_date Into vdoc_date
        From deleted_temp a Limit 1;
	
	-- Step 1: Declare Temp Table required to display error
	DROP TABLE IF EXISTS SLDailyTranTemp;	
	create temp TABLE  SLDailyTranTemp
	(	
		branch_id bigint,
		doc_date date,
		inserted_on timestamp,
		stock_location_id bigint,
		material_id bigint,		
		net_qty numeric(18,4),
		balance numeric(18,4)
	);

	-- Step 2: Extract Summary of Materials that have been affected by the document (Issues and negative receipts)
	DROP TABLE IF EXISTS VchSLTran;	
	create temp TABLE  VchSLTran
	(	
		company_id bigint,
		branch_id bigint,
		finyear varchar(4),
		doc_date date, 
		stock_location_id bigint,
		material_id bigint
	);

	-- ****		Step 2: Create cursor for delete and validate delete after each line of delete
	If Exists (Select * from deleted_temp where (received_qty > 0 or issued_qty < 0)) Then 
		

		Insert into VchSLTran(company_id, branch_id, finyear, doc_date, stock_location_id, material_id)
		Select a.company_id, a.branch_id, a.finyear, a.doc_date, a.stock_location_id, a.material_id
		From deleted_temp a
		Inner Join st.material  b on a.material_id = b.material_id
		Where a.received_qty > 0 or a.issued_qty < 0
                    And COALESCE((b.annex_info->>'is_service')::boolean, false) = false
		Group By a.company_id, a.branch_id, a.finyear, a.doc_date, a.stock_location_id, a.material_id;	

		-- Step 3:
		With Recursive SLDailyTran(branch_id, doc_date, inserted_on, stock_location_id, material_id, net_qty) 
		AS
		(	--	Consider Opening balance + entries till date
			Select a.branch_id, max(a.doc_date) as doc_date, max(a.inserted_on) as inserted_on, a.stock_location_id, a.material_id, sum(a.received_qty - a.issued_qty) as net_qty
			From st.stock_ledger a
			Inner Join VchSLTran b on a.company_id=b.company_id
							And a.branch_id = b.branch_id
							And a.stock_location_id = b.stock_location_id
							And a.finyear = b.finyear
							And a.material_id = b.material_id
							And a.doc_date < b.doc_date
			Group By a.branch_id, a.stock_location_id, a.material_id
			union all	--  Consider all future records irrespective of the fin year excluding opening balance entries
			Select a.branch_id, a.doc_date, a.inserted_on, a.stock_location_id, a.material_id, sum(a.received_qty - a.issued_qty) as net_qty
			From st.stock_ledger a
			Inner Join VchSLTran b on a.company_id=b.company_id
							And a.branch_id = b.branch_id
							And a.stock_location_id = b.stock_location_id
							And a.stock_movement_type_id != -1
							And a.material_id = b.material_id
							And a.doc_date >= b.doc_date
			Where a.voucher_id != pvoucher_id
			Group By a.branch_id, a.doc_date, a.inserted_on, a.stock_location_id, a.material_id
		),
		SLRunningSum(branch_id, doc_date, inserted_on, stock_location_id, material_id, net_qty, balance)
		AS
		(
			Select a.branch_id, a.doc_date, a.inserted_on, a.stock_location_id, a.material_id, a.net_qty,
                            sum(a.net_qty) over (partition by a.branch_id, a.stock_location_id, a.material_id order by a.doc_date, a.inserted_on asc)
                        From SLDailyTran a
		)		
		Insert Into SLDailyTranTemp (branch_id, doc_date, inserted_on, stock_location_id, material_id, net_qty, balance)
		Select a.branch_id, a.doc_date, a.inserted_on, a.stock_location_id, a.material_id, a.net_qty, a.balance
		From SLRunningSum a
		Where a.balance < 0 And a.doc_date >= vdoc_date
		Order by doc_date, inserted_on;	
	End If;	

	return query		
	Select a.branch_id, a.doc_date, a.inserted_on, a.stock_location_id, c.stock_location_name, a.material_id, b.material_name, b.material_code, a.net_qty, a.balance
	From SLDailyTranTemp a 
	Inner Join st.material  b on a.material_id = b.material_id
	Inner Join st.stock_location c on a.stock_location_id = c.stock_location_id;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
Create Or Replace function st.fn_sl_post_val_nst(pcompany_id bigint, pbranch_id bigint, pfinyear varchar(4), pvoucher_id varchar(50), pdoc_date date, pjson_tran json)
Returns Table 
(   branch_id bigint,
    doc_date date,
    inserted_on timestamp,
    stock_location_id bigint,
    stock_location_name varchar(250),
    material_id bigint,		
    material_name varchar(250),
    material_code varchar(20),
    net_qty numeric(18,4),
    balance numeric(18,4)
)
As 
$BODY$
Begin

    Return Query
    With StockTran --Extract Summary of Materials that have been affected by the document (Issues and negative receipts)
    As
    (   Select a.stock_location_id, a.material_id, 
            sum(Coalesce(st.sp_get_base_qty(a.uom_id, a.received_qty), 0) - Coalesce(st.sp_get_base_qty(a.uom_id, a.issued_qty), 0)) as net_qty
        From json_to_recordset(pjson_tran) as a(stock_location_id BigInt, material_id BigInt, uom_id BigInt, received_qty Numeric(18,3), issued_qty Numeric(18,3))
        Inner Join st.material b on a.material_id = b.material_id
        Where COALESCE((b.annex_info->>'is_service')::boolean, false) = false
        Group By a.stock_location_id, a.material_id
    ),
    SLDailyTran(branch_id, doc_date, inserted_on, stock_location_id, material_id, net_qty) 
    As
    (	--	Consider Opening balance + entries till date
        Select a.branch_id, max(a.doc_date) as doc_date, max(a.inserted_on) as inserted_on, a.stock_location_id, a.material_id, sum(a.received_qty - a.issued_qty) as net_qty
        From st.stock_ledger a
        Inner Join StockTran b on a.stock_location_id = b.stock_location_id
            And a.material_id = b.material_id
        Where a.finyear = pfinyear 
            And a.doc_date < pdoc_date
        Group By a.branch_id, a.stock_location_id, a.material_id
        Union All 	--  Consider all future records irrespective of the fin year excluding opening balance entries
        Select a.branch_id, a.doc_date, a.inserted_on, a.stock_location_id, a.material_id, sum(a.received_qty - a.issued_qty) as net_qty
        From st.stock_ledger a
            Inner Join StockTran b on a.stock_location_id = b.stock_location_id And a.material_id = b.material_id
        Where a.stock_movement_type_id != -1
            And a.doc_date >= pdoc_date
        Group By a.branch_id, a.doc_date, a.inserted_on, a.stock_location_id, a.material_id
        Union All
        Select pbranch_id, pdoc_date, current_timestamp(0), a.stock_location_id, a.material_id, a.net_qty
        From StockTran a
    ),
    SLRunningSum(branch_id, doc_date, inserted_on, stock_location_id, material_id, net_qty, balance)
    As
    (   Select a.branch_id, a.doc_date, a.inserted_on, a.stock_location_id, a.material_id, a.net_qty,
            sum(a.net_qty) over (partition by a.branch_id, a.stock_location_id, a.material_id order by a.doc_date, a.inserted_on asc)
        From SLDailyTran a
    )
    Select a.branch_id, a.doc_date, a.inserted_on::timestamp without time zone, a.stock_location_id, c.stock_location_name, a.material_id, b.material_name, b.material_code, a.net_qty, a.balance
    From SLRunningSum a
    Inner Join st.material  b on a.material_id = b.material_id
    Inner Join st.stock_location c on a.stock_location_id = c.stock_location_id
    Where a.balance < 0 And a.doc_date >= pdoc_date
    Order by doc_date, inserted_on;
    
End;
$BODY$
LANGUAGE plpgsql;

?==?
create or replace function st.fn_material_balance_wac_report(pcompany_id bigint, pbranch_id bigint, pmaterial_id bigint, pfinyear varchar(4), pto_date date, psl_id bigint = 0)
RETURNS TABLE  
(
    material_id bigint,
    material_name varchar(250),
    material_code varchar(20),
    uom_desc varchar(20), 
    balance_qty_base numeric(18,4),
    rate numeric(18,4),
    amount numeric(18,4),
    material_type_id bigint, 
    inventory_account_id bigint, 
    material_type varchar(50), 
    account_head varchar(250)
)
AS
$BODY$
Begin	
	-- This function is used by the Posting Trigger to get information on the Supplier Payment (PYMT)
	DROP TABLE IF EXISTS mat_balance;	
	create temp TABLE  mat_balance
	(	
		material_id bigint,
		material_name varchar(250),
		material_code varchar(20),
		uom_desc varchar(20), 
		balance_qty_base numeric(18,4),
		rate numeric(18,4),
		amount numeric(18,4)
	);

	Insert into mat_balance (material_id, material_name, material_code, 
		uom_desc, balance_qty_base, rate, 
		amount)
	Select d.material_id, d.material_name, d.material_code, 
		d.uom_desc, d.balance_qty, d.rate, 
		(d.balance_qty * d.rate)
	From (
		Select a.material_id, b.material_name, b.material_code, 
			c.uom_desc, coalesce(sum(a.balance_qty_base), 0) as balance_qty, 
			sys.fn_handle_zero_divide(coalesce(sum(a.balance_qty_base * a.rate), 0), coalesce(sum(a.balance_qty_base), 0)) as rate
		From st.fn_material_balance_wac_detail(pcompany_id, pbranch_id, pmaterial_id, psl_id, pfinyear, pto_date) a	
		Inner Join st.material b on a.material_id = b.material_id
		Inner Join st.fn_material_uom_base() c on a.material_id = c.material_id
		group by a.material_id, b.material_name, b.material_code, c.uom_desc
	) d;

	
	return query 
	select a.material_id, a.material_name, a.material_code, a.uom_desc, a.balance_qty_base, a.rate, a.amount,
               b.material_type_id, b.inventory_account_id, c.material_type, d.account_head
	from mat_balance a
    inner join st.material b on a.material_id = b.material_id
	left join st.material_type c on b.material_type_id = c.material_type_id
	left join ac.account_head d on b.inventory_account_id = d.account_id;
END;
$BODY$
LANGUAGE plpgsql;

?==?
create or replace function st.fn_material_opcl_wac_value_report(pcompany_id bigint, pbranch_id bigint, pfinyear varchar(4), pfrom_date date, pto_date date, psl_id bigint = 0)
RETURNS TABLE  
(
	material_id bigint,
	material_name varchar(250),
	material_code varchar(20),
	op_value numeric(18,4),
	receipts_value numeric(18,4),
	issues_at_cost numeric(18,4),
	cl_value numeric(18,4),
    material_type_id bigint, 
    inventory_account_id bigint, 
    material_type varchar(50), 
    account_head varchar(250)
)
AS
$BODY$
Declare vBeforeFromDate date;
Begin	
	-- Temp table to hold OpClValue
	DROP TABLE IF EXISTS mat_opcl_wac;	
	create temp TABLE  mat_opcl_wac
	(	
		material_id bigint,
		material_name varchar(250),
		material_code varchar(20),
		op_value numeric(18,4),
		receipts_value numeric(18,4),
		issues_at_cost numeric(18,4),
		cl_value numeric(18,4)
	);
	
	-- Temp table to hold OpClValue
	DROP TABLE IF EXISTS mat_opcl_value;	
	create temp TABLE  mat_opcl_value
	(	
		material_id bigint,
		op_value numeric(18,4),
		receipts_value numeric(18,4),
		cl_value numeric(18,4)
	);

	vBeforeFromDate := pfrom_date - '1 day'::interval; -- ****	Resolve Date one day before from date

	Insert into mat_opcl_value (material_id, op_value, receipts_value, cl_value)
	Select d.material_id, sum(d.op_value), sum(d.receipts_value), sum(d.cl_value)
	From (
		-- Op Value
		Select a.material_id, coalesce(sum(a.balance_qty_base * a.rate), 0) as op_value, 0 as receipts_value, 0 as cl_value
		From st.fn_material_balance_wac_detail(pcompany_id, pbranch_id, 0, psl_id, pfinyear, vBeforeFromDate) a	
		group by a.material_id
		union all-- receipt value
		Select a.material_id, 0, sum(a.received_qty*a.unit_rate_lc), 0
		From st.stock_ledger a
		where a.finyear = pfinyear
			And a.doc_date between pfrom_date and pto_date 
			And a.company_id = pcompany_id
			And (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
			And (a.stock_location_id = psl_id or psl_id=0)
		Group by a.material_id
		Union All -- cl value
		Select a.material_id, 0 as op_value, 0 as receipt_value, coalesce(sum(a.balance_qty_base * a.rate), 0)  as cl_value
		From st.fn_material_balance_wac_detail(pcompany_id, pbranch_id, 0, psl_id, pfinyear, pto_date) a	
		group by a.material_id

	) d
	group by d.material_id;


	Insert into mat_opcl_wac(material_id, material_name, material_code, 
		op_value, receipts_value, 
		issues_at_cost, cl_value)
	Select a.material_id, a.material_name, a.material_code, 
		b.op_value, b.receipts_value,
		sys.fn_handle_round('amt', ((b.op_value + b.receipts_value) - b.cl_value)) as issue_at_cost, b.cl_value
	From st.material a
	Inner Join mat_opcl_value b on a.material_id = b.material_id;		
	
	return query 
	select a.material_id, a.material_name, a.material_code, a.op_value, a.receipts_value, a.issues_at_cost, a.cl_value,
                b.material_type_id, b.inventory_account_id, c.material_type, d.account_head
	from mat_opcl_wac a
    inner join st.material b on a.material_id = b.material_id
	left join st.material_type c on b.material_type_id = c.material_type_id
	left join ac.account_head d on b.inventory_account_id = d.account_id;
END;
$BODY$
LANGUAGE plpgsql;

?==?
create or replace function st.fn_material_opcl_wac_qty_value_report(pcompany_id bigint, pbranch_id bigint, pfinyear varchar(4), pfrom_date date, pto_date date, psuppress_blank boolean, psl_id bigint = 0)
RETURNS TABLE  
(
	material_id bigint,
	material_name varchar(250),
	material_code varchar(20),
	uom_desc varchar(20), 
	op_bal_qty numeric(18,4),
	op_rate numeric(18,4),
	op_amount numeric(18,4),
	received_qty numeric(18,4),
	issued_qty numeric(18,4),
	cl_bal_qty numeric(18,4),
	cl_rate numeric(18,4),
	cl_amount numeric(18,4),
    material_type_id bigint, 
    inventory_account_id bigint, 
    material_type varchar(50), 
    account_head varchar(250)
)
AS
$BODY$
Declare vBeforeFromDate date;
Begin	
	-- Temp table to hold OpClValue
	DROP TABLE IF EXISTS mat_opcl_wac_qty;	
	create temp TABLE  mat_opcl_wac_qty
	(	
		material_id bigint,
		material_name varchar(250),
		material_code varchar(20),
		uom_desc varchar(20), 
		op_bal_qty numeric(18,4),
		op_rate numeric(18,4),
		op_amount numeric(18,4),
		received_qty numeric(18,4),
		issued_qty numeric(18,4),
		cl_bal_qty numeric(18,4),
		cl_rate numeric(18,4),
		cl_amount numeric(18,4)
	);
	
	-- Temp table to hold OpClValue
	DROP TABLE IF EXISTS mat_balance;	
	create temp TABLE  mat_balance
	(	
		material_id bigint,
		op_bal_qty numeric(18,4),
		op_rate numeric(18,4),
		received_qty numeric(18,4),
		issued_qty numeric(18,4),
		cl_bal_qty numeric(18,4),
		cl_rate numeric(18,4)
	);

	vBeforeFromDate := pfrom_date - '1 day'::interval; -- ****	Resolve Date one day before from date

	Insert into mat_balance (material_id, op_bal_qty, op_rate, received_qty, issued_qty, cl_bal_qty, cl_rate)
	Select d.material_id, sum(d.op_bal_qty), sum(d.op_rate), sum(d.received_qty), sum(d.issued_qty), sum(d.cl_bal_qty), sum(d.cl_rate)
	From (
		Select a.material_id, sum(a.balance_qty_base) as op_bal_qty, 
			sys.fn_handle_zero_divide(coalesce(sum(a.balance_qty_base * a.rate), 0), coalesce(sum(a.balance_qty_base), 0)) as op_rate,
			0 as received_qty, 0 as issued_qty, 0 as cl_bal_qty, 0 as cl_rate
		From st.fn_material_balance_wac_detail(pcompany_id, pbranch_id, 0, psl_id, pfinyear, vBeforeFromDate) a	
		group by a.material_id
		union all
		Select a.material_id, 0, 
			0, 
			sum(a.received_qty), sum(a.issued_qty), 0, 0
		From st.stock_ledger a
		where a.finyear = pfinyear
			And a.doc_date between pfrom_date and pto_date 
			And a.company_id = pcompany_id
			And (a.branch_id in (select branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id)) or pbranch_id=0)
			And (a.stock_location_id = psl_id or psl_id=0)
		Group by a.material_id
		Union All 
		Select a.material_id, 0, 
			0, 
			0, 0, sum(a.balance_qty_base), 
			sys.fn_handle_zero_divide(coalesce(sum(a.balance_qty_base * a.rate), 0), coalesce(sum(a.balance_qty_base), 0))
		From st.fn_material_balance_wac_detail(pcompany_id, pbranch_id, 0, psl_id, pfinyear, pto_date) a	
		group by a.material_id
	) d
	group by d.material_id;

	Insert into mat_opcl_wac_qty(material_id, material_name, material_code, uom_desc, 
		op_bal_qty, op_rate, op_amount,
		received_qty, issued_qty,
		cl_bal_qty, cl_rate, cl_amount)
	Select a.material_id, a.material_name, a.material_code, c.uom_desc,
		b.op_bal_qty, b.op_rate, sys.fn_handle_round('amt', (b.op_bal_qty * b.op_rate)) as op_amount,
		b.received_qty, b.issued_qty,
		b.cl_bal_qty, b.cl_rate, sys.fn_handle_round('amt', (b.cl_bal_qty * b.cl_rate)) as cl_amount
	From st.material a
	Inner Join mat_balance b on a.material_id = b.material_id
	Inner Join st.fn_material_uom_base() c on a.material_id = c.material_id;		

	--	****	Remove blank entries
	if psuppress_blank then
		Delete From mat_opcl_wac_qty a
		Where a.material_id Is Not Null
				And a.op_bal_qty = 0 And a.received_qty=0 And a.issued_qty=0 And a.cl_bal_qty=0;
	End if;
	
	return query 
	select a.material_id, a.material_name, a.material_code, a.uom_desc, a.op_bal_qty, a.op_rate, a.op_amount, a.received_qty, a.issued_qty,
		a.cl_bal_qty, a.cl_rate, a.cl_amount, b.material_type_id, b.inventory_account_id, c.material_type, d.account_head
	from mat_opcl_wac_qty a
    inner join st.material b on a.material_id = b.material_id
	left join st.material_type c on b.material_type_id = c.material_type_id
	left join ac.account_head d on b.inventory_account_id = d.account_id;
END;
$BODY$
LANGUAGE plpgsql;

?==? 
CREATE OR REPLACE FUNCTION st.fn_stock_report(IN pstock_id varchar)
RETURNS TABLE
(
	stock_id varchar(50),
	company_id bigint,
	finyear varchar(4),
	branch_id bigint,
	doc_type varchar(20),
	doc_date date,
	account_id bigint,
	account_head varchar(250),
	bill_no varchar(50),
	bill_date date,
	bill_amt numeric(18,4),
	bill_amt_fc numeric(18,4),
	bill_receipt_date date,
	fc_type_id bigint,
	fc_type varchar(20),
	exch_rate numeric(18,6),
	amt numeric(18,4),
	amt_fc numeric(18,4),
	gross_amt numeric(18,4),
	gross_amt_fc numeric(18,4),
	disc_is_value boolean,
	disc_percent numeric(18,4),
	disc_amt numeric(18,4),
	disc_amt_fc numeric(18,4),
	misc_taxable_amt numeric(18,4),
	misc_taxable_amt_fc numeric(18,4),
	before_tax_amt numeric(18,4),
	before_tax_amt_fc numeric(18,4),
	tax_amt numeric(18,4),
	tax_amt_fc numeric(18,4),
	round_off_amt numeric(18,4),
	round_off_amt_fc numeric(18,4),
	misc_non_taxable_amt numeric(18,4),
	misc_non_taxable_amt_fc numeric(18,4),
	total_amt numeric(18,4),
	total_amt_fc numeric(18,4),
	advance_amt numeric(18,4),
	advance_amt_fc numeric(18,4),
	net_amt numeric(18,4),
	net_amt_fc numeric(18,4),
	status smallint,
	pay_term_id bigint,
	pay_term varchar(500),
	en_tax_type smallint,
	narration varchar(500),
	remarks varchar(500),
	amt_in_words varchar(250),
	amt_in_words_fc varchar(250),
	customer_address varchar(500),
	customer_consignee_id bigint,
	customer_consignee_address varchar(500),
	reference_id varchar(50),
	reference_parent_id varchar(50),
	target_branch_id bigint,
	target_branch_name varchar(100),
	sale_account_id bigint,
	terms_and_conditions varchar(500),
	fax varchar(50),
	mobile varchar(50),
	phone varchar(50),
	email varchar(50),
	contact_person varchar(50),
	entered_by varchar(100), 
	posted_by varchar(100)
) AS
$BODY$
BEGIN	

	DROP TABLE IF EXISTS stock_report_temp;	
	create temp table stock_report_temp
	(
		stock_id varchar(50),
		company_id bigint,
		finyear varchar(4),
		branch_id bigint,
		doc_type varchar(20),
		doc_date date,
		account_id bigint,
		account_head varchar(250),
		bill_no varchar(50),
		bill_date date,
		bill_amt numeric(18,4),
		bill_amt_fc numeric(18,4),
		bill_receipt_date date,
		fc_type_id bigint,
		fc_type varchar(20),
		exch_rate numeric(18,6),
		amt numeric(18,4),
		amt_fc numeric(18,4),
		gross_amt numeric(18,4),
		gross_amt_fc numeric(18,4),
		disc_is_value boolean,
		disc_percent numeric(18,4),
		disc_amt numeric(18,4),
		disc_amt_fc numeric(18,4),
		misc_taxable_amt numeric(18,4),
		misc_taxable_amt_fc numeric(18,4),
		before_tax_amt numeric(18,4),
		before_tax_amt_fc numeric(18,4),
		tax_amt numeric(18,4),
		tax_amt_fc numeric(18,4),
		round_off_amt numeric(18,4),
		round_off_amt_fc numeric(18,4),
		misc_non_taxable_amt numeric(18,4),
		misc_non_taxable_amt_fc numeric(18,4),
		total_amt numeric(18,4),
		total_amt_fc numeric(18,4),
		advance_amt numeric(18,4),
		advance_amt_fc numeric(18,4),
		net_amt numeric(18,4),
		net_amt_fc numeric(18,4),
		status smallint,
		pay_term_id bigint,
		pay_term varchar(500),
		en_tax_type smallint,
		narration varchar(500),
		remarks varchar(500),
		amt_in_words varchar(250),
		amt_in_words_fc varchar(250),
		customer_address varchar(500),
		customer_consignee_id bigint,
		customer_consignee_address varchar(500),
		reference_id varchar(50),
		reference_parent_id varchar(50),
		target_branch_id bigint,
		target_branch_name varchar(100),
		sale_account_id bigint,
		terms_and_conditions varchar(500),
		fax varchar(50),
		mobile varchar(50),
		phone varchar(50),
		email varchar(50),
		contact_person varchar(50),
		entered_by varchar(100), 
		posted_by varchar(100)
	);

	INSERT INTO stock_report_temp(stock_id, company_id, finyear, branch_id, doc_type, doc_date, account_id, account_head, bill_no, bill_date,
		bill_amt, bill_amt_fc, bill_receipt_date, fc_type_id, fc_type, exch_rate, amt, amt_fc, gross_amt, gross_amt_fc, 
		disc_is_value, disc_percent, disc_amt, disc_amt_fc, misc_taxable_amt, misc_taxable_amt_fc, before_tax_amt, before_tax_amt_fc, tax_amt, 
		tax_amt_fc, round_off_amt, round_off_amt_fc, misc_non_taxable_amt, misc_non_taxable_amt_fc, total_amt, total_amt_fc, 
		advance_amt, advance_amt_fc, net_amt, net_amt_fc, status, pay_term_id, pay_term, en_tax_type, narration, remarks, 
		amt_in_words, amt_in_words_fc, customer_address, customer_consignee_id,	customer_consignee_address, reference_id, 
		reference_parent_id, target_branch_id, target_branch_name, sale_account_id, terms_and_conditions, fax, mobile, phone, email, 
		contact_person, entered_by, posted_by)
	SELECT  a.stock_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, a.account_id, b.account_head, a.bill_no, a.bill_date,
		a.bill_amt, a.bill_amt_fc, a.bill_receipt_date, a.fc_type_id, h.fc_type, a.exch_rate, a.amt, a.amt_fc, a.gross_amt, a.gross_amt_fc, 
		a.disc_is_value, a.disc_percent, a.disc_amt, a.disc_amt_fc, a.misc_taxable_amt, a.misc_taxable_amt_fc, a.before_tax_amt, a.before_tax_amt_fc, a.tax_amt, 
		a.tax_amt_fc, a.round_off_amt, a.round_off_amt_fc, a.misc_non_taxable_amt, a.misc_non_taxable_amt_fc, a.total_amt, a.total_amt_fc, 
		a.advance_amt, a.advance_amt_fc, a.net_amt, a.net_amt_fc, a.status, f.pay_term_id, c.pay_term, a.en_tax_type, a.narration, a.remarks, 
		a.amt_in_words, a.amt_in_words_fc, g.address As customer_address, a.customer_consignee_id, a.customer_consignee_address, a.reference_id, 
		a.reference_parent_id, a.target_branch_id, e.branch_name AS target_branch_name, a.sale_account_id, a.terms_and_conditions, g.fax, g.mobile, g.phone, g.email, 
		g.contact_person, d.entered_by, d.posted_by
	FROM st.stock_control a 
	LEFT JOIN ac.account_head b ON a.account_id = b.account_id
	INNER JOIN sys.doc_es d ON a.stock_id = d.voucher_id
	LEFT JOIN sys.branch e ON a.target_branch_id = e.branch_id
	Left Join(select x.customer_id as account_id, x.address_id, x.pay_term_id from ar.customer x
		union all 
		select y.supplier_id, y.address_id, y.pay_term_id from ap.supplier y) f on a.account_id= f.account_id
	left join ac.pay_term c on f.pay_term_id = c.pay_term_id
	left Join sys.address g on f.address_id= g.address_id
	left join ac.fc_type h on a.fc_type_id = h.fc_type_id
	WHERE a.stock_id = pstock_id;
	RETURN query
	SELECT  a.stock_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, a.account_id, a.account_head, a.bill_no, a.bill_date,
		a.bill_amt, a.bill_amt_fc, a.bill_receipt_date, a.fc_type_id, a.fc_type, a.exch_rate, a.amt, a.amt_fc, a.gross_amt, a.gross_amt_fc, 
		a.disc_is_value, a.disc_percent, a.disc_amt, a.disc_amt_fc, a.misc_taxable_amt, a.misc_taxable_amt_fc, a.before_tax_amt, a.before_tax_amt_fc, a.tax_amt,
		a.tax_amt_fc, a.round_off_amt, a.round_off_amt_fc, a.misc_non_taxable_amt, a.misc_non_taxable_amt_fc, a.total_amt, a.total_amt_fc, a.advance_amt, 
		a.advance_amt_fc, a.net_amt, a.net_amt_fc, a.status, a.pay_term_id, a.pay_term, a.en_tax_type, a.narration, a.remarks, a.amt_in_words, a.amt_in_words_fc,  
		a.customer_address, a.customer_consignee_id, a.customer_consignee_address, a.reference_id, a.reference_parent_id, a.target_branch_id, 
		a.target_branch_name, a.sale_account_id, a.terms_and_conditions, a.fax, a.mobile, a.phone, a.email, a.contact_person, a.entered_by, a.posted_by
	FROM stock_report_temp a;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_stock_tran_report(IN pstock_id varchar)
  RETURNS TABLE(stock_id character varying, stock_tran_id character varying, sl_no bigint, material_id bigint, material_name character varying, 
  stock_location_id bigint, uom_id bigint, issued_qty numeric, received_qty numeric, rate numeric, rate_fc numeric, disc_percent numeric, 
  disc_amt numeric, disc_amt_fc numeric, item_amt numeric, item_amt_fc numeric, reference_id character varying, reference_tran_id character varying,
  reference_parent_id character varying, reference_parent_tran_id character varying, target_stock_location_id bigint, stock_location_name character varying, 
  target_stock_location_name character varying, uom_desc character varying, material_type Character Varying) AS
$BODY$
BEGIN	

	DROP TABLE IF EXISTS stock_tran_report_temp;	
	create temp table stock_tran_report_temp
	(
		stock_id varchar(50),
		stock_tran_id varchar(50),
		sl_no bigint,
		material_id bigint,
		material_name varchar(250),
		stock_location_id bigint,
		uom_id bigint,
		issued_qty numeric(18,4),
		received_qty numeric(18,4),
		rate numeric(18,4),
		rate_fc numeric(18,4),
		disc_percent numeric(18,4),
		disc_amt numeric(18,4),
		disc_amt_fc numeric(18,4),
		item_amt numeric(18,4),
		item_amt_fc numeric(18,4),
		reference_id varchar(50),
		reference_tran_id varchar(50),
		reference_parent_id varchar(50),
		reference_parent_tran_id varchar(50),
		target_stock_location_id bigint,
		stock_location_name varchar(250),
		target_stock_location_name varchar(250),
		uom_desc varchar(20),
		material_type Character Varying
	)
	on commit drop;

	INSERT INTO stock_tran_report_temp(stock_id, stock_tran_id, sl_no, material_id, material_name, stock_location_id, uom_id, 
			issued_qty, received_qty, rate, rate_fc, disc_percent, disc_amt, disc_amt_fc, item_amt, item_amt_fc, reference_id, 
			reference_tran_id, reference_parent_id, reference_parent_tran_id, target_stock_location_id, stock_location_name, 
			target_stock_location_name, uom_desc, material_type)
		SELECT a.stock_id, a.stock_tran_id, a.sl_no, a.material_id, e.material_name, a.stock_location_id, a.uom_id,
		       a.issued_qty, a.received_qty, a.rate, a.rate_fc, a.disc_percent, a.disc_amt, a.disc_amt_fc, a.item_amt, a.item_amt_fc, a.reference_id, 
		       a.reference_tran_id, a.reference_parent_id, a.reference_parent_tran_id, a.target_stock_location_id, b.stock_location_name, 
		       c.stock_location_name AS target_stock_location_name, d.uom_desc, f.material_type
		FROM st.stock_tran a 
			INNER JOIN st.stock_location b ON a.stock_location_id = b.stock_location_id
			LEFT JOIN st.stock_location c ON a.target_stock_location_id = c.stock_location_id
			INNER JOIN st.uom d ON a.uom_id = d.uom_id
			INNER JOIN st.material e ON a.material_id = e.material_id
			Inner Join st.material_type f On e.material_type_id = f.material_type_id
		WHERE a.stock_id = pstock_id;
	
	RETURN query
		SELECT a.stock_id, a.stock_tran_id, a.sl_no, a.material_id, a.material_name, a.stock_location_id, a.uom_id,
		       a.issued_qty, a.received_qty, a.rate, a.rate_fc, a.disc_percent, a.disc_amt, a.disc_amt_fc, a.item_amt, a.item_amt_fc, a.reference_id, 
		       a.reference_tran_id, a.reference_parent_id, a.reference_parent_tran_id, a.target_stock_location_id, a.stock_location_name, 
		       a.target_stock_location_name, a.uom_desc, a.material_type
		FROM stock_tran_report_temp a;

END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_stock_lc_tran_report(IN pstock_id character varying)
  RETURNS TABLE(stock_id character varying, stock_lc_tran_id character varying, en_apportion_type smallint, account_id bigint, 
  account_affected_id bigint, debit_amt numeric, debit_amt_fc numeric, bill_no character varying, bill_date date, is_taxable boolean, description character varying, 
  supplier_paid boolean, account_head character varying, account_head_affected character varying) AS
$BODY$
BEGIN	

	DROP TABLE IF EXISTS stock_lc_tran_report_temp;	
	create temp table stock_lc_tran_report_temp
	(
		stock_id varchar(50),
		stock_lc_tran_id varchar(50),
		en_apportion_type smallint,
		account_id bigint,
		account_affected_id bigint,
		debit_amt numeric(18,4),
		debit_amt_fc numeric(18,4),
		bill_no character varying(50),
		bill_date date,
		is_taxable boolean,
		description varchar(250),
		supplier_paid boolean,
		account_head varchar(250),
		account_head_affected varchar(250)
	)
	on commit drop;

	INSERT INTO stock_lc_tran_report_temp(stock_id, stock_lc_tran_id, en_apportion_type, account_id, account_affected_id,
		debit_amt, debit_amt_fc, bill_no, bill_date, is_taxable, description, supplier_paid, account_head, account_head_affected)
		SELECT a.stock_id, a.stock_lc_tran_id, a.en_apportion_type, a.account_id, a.account_affected_id,
			a.debit_amt, a.debit_amt_fc, a.bill_no, a.bill_date, a.is_taxable, a.description, a.supplier_paid, b.account_head, c.account_head AS account_head_affected
		FROM st.stock_lc_tran a 
			LEFT JOIN ac.account_head b ON a.account_id = b.account_id
			LEFT JOIN ac.account_head c ON a.account_affected_id = c.account_id
		WHERE a.stock_id = pstock_id;

	RETURN query
		SELECT a.stock_id, a.stock_lc_tran_id, a.en_apportion_type, a.account_id, a.account_affected_id, a.debit_amt, a.debit_amt_fc, 
		       a.bill_no, a.bill_date, a.is_taxable, a.description, a.supplier_paid, a.account_head, a.account_head_affected
		FROM stock_lc_tran_report_temp a;

END;
$BODY$
  LANGUAGE plpgsql;

?==?
Create or Replace Function st.fn_material_balance_wac_by_inv_ac(pcompany_id BigInt, pbranch_id BigInt, pfinyear Varchar(4),  pto_date date)
Returns Table
(	account_id BigInt, 
	account_code Varchar(20),
	account_head Varchar(250),
	material_id BigInt,
	material_code Varchar(20),
	material_name Varchar(250),
	balance_qty_base Numeric(18,3),
	rate Numeric(18,8),
	uom_desc Varchar(50),
	mat_value Numeric(18,4)
)
AS
$BODY$
BEGIN
	-- Output table
	Drop Table If Exists mat_bal_by_ac;	
	Create Temp Table mat_bal_by_ac
	(	account_id BigInt, 
		account_code Varchar(20),
		account_head Varchar(250),
		material_id BigInt,
		material_code Varchar(20),
		material_name Varchar(250),
		balance_qty_base Numeric(18,3),
		rate Numeric(18,8),
		uom_desc Varchar(50),
		mat_value Numeric(18,4)
	);


	-- Generate Inventory value for materials in stock
	With mat_bal(material_id, balance_qty_base, mat_value)
	As
	(	Select x.material_id, Sum(x.balance_qty_base) as balance_qty_base, cast(Sum(x.balance_qty_base * x.rate) as Numeric(18,4)) as mat_value
		From st.fn_material_balance_wac_detail(pcompany_id, pbranch_id, 0, 0, pfinyear, pto_date) x
		Group By x.material_id
	)
	Insert Into mat_bal_by_ac(account_id, account_code, account_head,
		material_id, material_code, material_name,
		balance_qty_base, rate, uom_desc, mat_value)
	Select	c.account_id, c.account_code, c.account_head, 
		a.material_id, b.material_code, b.material_name, 
		a.balance_qty_base, sys.fn_handle_zero_divide(a.mat_value, a.balance_qty_base), d.uom_desc, a.mat_value
	From mat_bal a
	Inner Join st.material b ON a.material_id = b.material_id
	Inner Join ac.account_head c  ON b.inventory_account_id = c.account_id
	Inner Join st.fn_material_uom_base() d On b.material_id = d.material_id
	Where (a.balance_qty_base != 0 Or a.mat_value != 0);

	-- Generate Inventory data for materials not in stock
	Insert Into mat_bal_by_ac(account_id, account_code, account_head,
		material_id, material_code, material_name,
		balance_qty_base, rate, uom_desc, mat_value)
	Select a.inventory_account_id, b.account_code, b.account_head,
		a.material_id, a.material_code, a.material_name,
		0, 0, c.uom_desc, 0
	From st.material a
	Inner Join ac.account_head b On a.inventory_account_id = b.account_id
	Inner Join st.fn_material_uom_base() c On a.material_id = c.material_id
	Where ((a.annex_info->>'is_service')::Boolean Is Null Or (a.annex_info->>'is_service')::Boolean = false)
            And Not Exists (Select * from mat_bal_by_ac d Where a.material_id = d.material_id);

	Return Query
	Select a.account_id, a.account_code, a.account_head, a.material_id, a.material_code, a.material_name,
		a.balance_qty_base, a.rate, a.uom_desc, a.mat_value
	From mat_bal_by_ac a;
	
End;
$BODY$
  LANGUAGE plpgsql;

?==?

CREATE OR REPLACE FUNCTION st.fn_stock_move_by_type_value(IN pcompany_id bigint, IN pbranch_id bigint, IN pmaterial_id bigint, IN pfinyear character varying, IN pfrom_date date, IN pto_date date)
  RETURNS TABLE(branch_id bigint, stock_movement_type_id bigint, stock_movement_type character varying, material_id bigint, mat_qty numeric, mat_value numeric) AS
$BODY$
Begin
	Return Query
	Select a.branch_id, a.stock_movement_type_id, b.stock_movement_type, a.material_id, 
		Sum(received_qty-issued_qty),
		Sum((received_qty-issued_qty)*unit_rate_lc)
	From st.stock_ledger a
	Inner Join st.stock_movement_type b On a.stock_movement_type_id=b.stock_movement_type_id
	Where a.finYear=pfinyear 
		And a.doc_date Between pfrom_date And pto_date
		And a.company_id=pcompany_id 
		And (a.branch_id in (select b.branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id) b) or pbranch_id=0)
		And (a.material_id=pmaterial_id or pmaterial_id=0)
	Group By a.branch_id, a.stock_movement_type_id, b.stock_movement_type, a.material_id
	Order by a.branch_id, a.stock_movement_type_id, b.stock_movement_type, a.material_id;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
Create or Replace function st.fn_business_turnover(pcompany_id bigint, pbranch_id bigint, psalesman_id bigint, pcustomer_id bigint, pfrom_date date, pto_date date)
RETURNS TABLE  
(	
	voucher_id varchar(50),
	doc_date date,
	customer_id bigint,
	customer varchar(250),
	salesman_id bigint,
	salesman_name varchar(50),
	invoice_amt numeric(18,4),
	invoice_amt_fc numeric(18,4),
	bt_amt numeric(18,4),
	tax_amt numeric(18,4),
    branch_id bigint
)
AS
$BODY$
	declare vwalkincust bigint := 0;
Begin	
	Select a.customer_id into vwalkincust from ar.customer a where a.customer ilike 'Walk-in%';
	
	return query 
	Select 'DAY-TXN', a.doc_date, vwalkincust, 'Walk-in Customer', a.salesman_id, d.salesman_name, 
		sum(a.item_amt_tot), 0 , 
		sum(case when a.doc_type in ('PI','PIV') then
		(a.item_amt_tot - a.tax_amt_tot)
		else (-1 * (a.item_amt_tot - a.tax_amt_tot)) end) as bt_amt, sum(a.tax_amt_tot), a.branch_id
	From  pos.inv_control a
	left Join ar.salesman d on a.salesman_id = d.salesman_id
	where a.company_id = pcompany_id
		And (a.branch_id = pbranch_id or pbranch_id = 0)
		And a.doc_date between pfrom_date and pto_date
		And a.status = 5
        And (vwalkincust = pcustomer_id Or pcustomer_id = 0)
		And (a.salesman_id = psalesman_id or psalesman_id = 0)
	group by  a.doc_date, a.salesman_id, d.salesman_name, a.branch_id

	Union All
	
	Select a.stock_id, a.doc_date, a.account_id, b.customer, Coalesce(a.salesman_id, 0), Coalesce(d.salesman_name, ''), 
		case when a.doc_type in ('SI','SIV') Then a.total_amt
                    else (Case When a.doc_type = 'SRV' And (a.annex_info->>'dcn_type')::Int = 1 Then a.total_amt Else -1 * a.total_amt End) End total_amt, a.total_amt_fc, 
		case when a.doc_type in ('SI','SIV') then
		(a.gross_amt - a.tax_amt)
		else (Case When a.doc_type = 'SRV' And (a.annex_info->>'dcn_type')::Int = 1 Then (a.gross_amt - a.tax_amt) Else (-1 * (a.gross_amt - a.tax_amt)) End) end as bt_amt, 
                case when a.doc_type in ('SI','SIV') then a.tax_amt Else -a.tax_amt End as tax_amt, a.branch_id
	From  st.stock_control a
	inner join ar.customer b on a.account_id = b.customer_id
	left Join ar.salesman d on a.salesman_id = d.salesman_id
	where a.company_id = pcompany_id
		And (a.branch_id = pbranch_id or pbranch_id = 0)
		And (a.account_id = pcustomer_id or pcustomer_id = 0)
		And a.doc_date between pfrom_date and pto_date
		And a.status = 5
		And (a.salesman_id = psalesman_id or psalesman_id = 0)
		And a.doc_type= Any('{SI,SIV,SR,SRV,SRN}');
END;
$BODY$
LANGUAGE plpgsql;

?==?
create or replace function st.fn_sip_sales_inv_report(pcompany_id bigint, pbranch_id bigint, pfinyear varchar(4), pfrom_date date, pto_date date, pcustomer_id bigint, psalesman_id bigint)
RETURNS TABLE  
(
	voucher_id varchar(50),
	company_id bigint,
	branch_id bigint,
	finyear varchar(4),
	doc_date date,
	group_voucher_id varchar(50),
	sale_amt numeric(18,4),
	mat_cost numeric(18,4),
	selling_expenses numeric(18,4),
	customer_id bigint,
	customer varchar(250),
	salesman_id bigint,
	salesman_name varchar(250),
	profit numeric(18,4),
	gp numeric(18,4)
)
AS
$BODY$
Begin	
	DROP TABLE IF EXISTS gl_sales_amt;	
	create temp TABLE  gl_sales_amt
	(	
		company_id bigint,
		branch_id bigint,
		finyear varchar(4),
		voucher_id varchar(50),
		sale_amt numeric(18,4)
	);

	Insert into gl_sales_amt (company_id, branch_id, finyear, voucher_id, sale_amt)
	Select a.company_id, a.branch_id, a.finyear, a.voucher_id, sum(a.credit_amt - a.debit_amt) as sale_amt
	From ac.general_ledger a 
	Inner join ac.account_head b on a.account_id = b.account_id 
	Where b.account_type_id in (18)
		and a.company_id = pcompany_id
		and (a.branch_id in (select a.branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_id = 0)
		and a.finyear=pfinyear
	group by a.company_id, a.branch_id, a.finyear, a.doc_date, a.voucher_id;


	DROP TABLE IF EXISTS sales_control_temp;	
	create temp TABLE  sales_control_temp
	(	
		stock_id varchar(50),
		doc_date date,
		account_id bigint,
		salesman_id bigint,
		group_voucher_id varchar(50)
	);
	insert into sales_control_temp(stock_id , doc_date, account_id, salesman_id, group_voucher_id)
	Select a.stock_id, a.doc_date, a.account_id, a.salesman_id, a.stock_id
	from st.stock_control a
	where a.doc_type = 'SI'
		And a.status = 5
		And a.company_id = pcompany_id
		and (a.branch_id in (select a.branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_id = 0)
		and a.finyear=pfinyear
		And a.doc_date between pfrom_date and pto_date;

		
	DROP TABLE IF EXISTS sr_temp;	
	create temp TABLE  sr_temp
	(	
		stock_id varchar(50),
		doc_date date,
		account_id bigint,
		salesman_id bigint,
		group_voucher_id varchar(50)
	);
	insert into sr_temp(stock_id , doc_date, account_id, salesman_id, group_voucher_id)
	Select a.stock_id, a.doc_date, a.account_id, a.salesman_id, a.stock_id
	from st.stock_control a
	Inner join sales_control_temp b on a.reference_id = b.stock_id
	where a.doc_type = 'SR'
		And a.status = 5
		And a.company_id = pcompany_id
		and (a.branch_id in (select a.branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_id = 0)
		and a.finyear=pfinyear
		And a.doc_date between pfrom_date and pto_date
	Union All	
	Select a.stock_id, a.doc_date, a.account_id, a.salesman_id, a.stock_id
	from st.stock_control a
	Inner join sales_control_temp b on a.reference_id = b.stock_id
	where a.doc_type = 'SR'
		And a.status = 5
		And a.company_id = pcompany_id
		and (a.branch_id in (select a.branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_id = 0)
		and a.finyear=pfinyear
		And a.doc_date > pto_date;

		
	DROP TABLE IF EXISTS sip_temp;	
	create temp TABLE  sip_temp
	(	
		stock_id varchar(50),
		doc_date date,
		account_id bigint,
		salesman_id bigint,
		group_voucher_id varchar(50)
	);
	insert into sip_temp(stock_id , doc_date, account_id, salesman_id, group_voucher_id)
	Select a.stock_id, a.doc_date, a.account_id, a.salesman_id, a.stock_id
	from sales_control_temp a
	union all 
	Select a.stock_id, a.doc_date, a.account_id, a.salesman_id, a.stock_id
	from sr_temp a;
		
	DROP TABLE IF EXISTS sisl_tran_temp;	
	create temp TABLE  sisl_tran_temp
	(	
		sl_no serial,
		stock_id varchar(50),				
		group_voucher_id varchar(50),
		material_cost numeric(18,4)
	);
	
	Insert into sisl_tran_temp (stock_id, group_voucher_id, material_cost)
	Select b.stock_id, b.group_voucher_id, sum(a.issued_qty * a.unit_rate_lc) 
	From st.stock_ledger a
	inner join sip_temp b on a.voucher_id = b.stock_id
	group by b.stock_id, b.group_voucher_id;

	DROP TABLE IF EXISTS final_temp;
	Create temp table final_temp
	(
		voucher_id varchar(50),
		company_id bigint,
		branch_id bigint,
		finyear varchar(4),
		doc_date date,
		group_voucher_id varchar(50),
		sale_amt numeric(18,4),
		mat_cost numeric(18,4),
		selling_expenses numeric(18,4),
		customer_id bigint,
		customer varchar(250),
		salesman_id bigint,
		salesman_name varchar(250),
		profit numeric(18,4),
		gp numeric(18,4)
	);
	Insert into final_temp (voucher_id , company_id, branch_id, finyear, doc_date , group_voucher_id, sale_amt, mat_cost, selling_expenses,
			customer_id, customer, salesman_id, salesman_name, profit, 
			gp)
	Select a.voucher_id, a.company_id, a.branch_id, a.finyear, b.doc_date , c.group_voucher_id, COALESCE(a.sale_amt, 0), COALESCE(c.material_cost, 0), 0,
		b.account_id, d.customer, b.salesman_id, e.salesman_name, (COALESCE(a.sale_amt, 0) - COALESCE(c.material_cost, 0)), 
		sys.fn_handle_zero_divide(((COALESCE(a.sale_amt, 0) - COALESCE(c.material_cost, 0)) * 100), a.sale_amt)
	From gl_sales_amt a 
	inner join sip_temp b on a.voucher_id = b.stock_id 
	left join sisl_tran_temp c on b.stock_id = c.stock_id
	inner join ar.customer d on b.account_id = d.customer_id
	inner join ar.salesman e on b.salesman_id = e.salesman_id 
	Where (b.salesman_id = psalesman_id or psalesman_id = 0)
		And (b.account_id = pcustomer_id or pcustomer_id = 0);

	
	return query 
	select a.voucher_id , a.company_id, a.branch_id, a.finyear, a.doc_date , a.group_voucher_id, a.sale_amt, a.mat_cost, a.selling_expenses,
			a.customer_id, a.customer, a.salesman_id, a.salesman_name, a.profit, a.gp
	from final_temp a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
create or replace function st.fn_sales_product_profitability_report(pcompany_id bigint, pbranch_id bigint, pfrom_date date, pto_date date)
RETURNS TABLE  
(
	material_id bigint,
	material_name varchar(250),
	issued_qty numeric(18,4),
	unit_rate_lc numeric(18,4),
	issue_value numeric(18,4),
	invoice_amt numeric(18,4),
	disc_amt numeric(18,4),
	other_amt numeric(18,4),
	gross_margin_amt numeric(18,4),	
	voucher_id varchar(50),
	vch_tran_id varchar(50)
)
AS
$BODY$
Begin	
	-- Step 1: Get the stock ledger data for Stock Invoices during the period
	DROP TABLE IF EXISTS sl_tran;	
	create temp TABLE  sl_tran
	(	
		voucher_id varchar(50),
		vch_tran_id varchar(50),
		material_id bigint,
		issued_qty numeric(18,4),
		unit_rate_lc numeric(18,4),
		invoice_id varchar(50),
		invoice_amt numeric(18,4),
		disc_amt numeric(18,4),
		other_amt numeric(18,4)
	);

	Insert into sl_tran (voucher_id, vch_tran_id, material_id, issued_qty, unit_rate_lc, invoice_id, invoice_amt, disc_amt, other_amt)
	Select a.voucher_id, a.vch_tran_id, a.material_id, a.issued_qty, a.unit_rate_lc, b.stock_id, c.item_amt, 0, 0
	From st.stock_ledger a 
	Inner join st.stock_control b on a.voucher_id = b.stock_id
	inner join st.stock_tran c on a.voucher_id = c.stock_id and a.vch_tran_id = c.stock_tran_id
	Where b.doc_type='SI'
		And b.status=5 
		And a.company_id = pcompany_id		
		And b.doc_date between pfrom_date and pto_date;

	-- Step 2: Adjust invoice level discounts
	update sl_tran
	set invoice_amt = a.invoice_amt - (a.invoice_amt * sys.fn_handle_zero_divide(b.disc_amt, b.gross_amt)) + (a.invoice_amt * sys.fn_handle_zero_divide((b.misc_taxable_amt + b.misc_non_taxable_amt), b.gross_amt)) ,
		disc_amt = (a.invoice_amt * sys.fn_handle_zero_divide(b.disc_amt, b.gross_amt)),
		other_amt = (a.invoice_amt * sys.fn_handle_zero_divide((b.misc_taxable_amt + b.misc_non_taxable_amt), b.gross_amt)) 
	from sl_tran a 
	inner join st.stock_control b on a.voucher_id = b.stock_id;

	-- Step 3: Adjust Sales Return Discounts
	Insert into sl_tran (voucher_id, vch_tran_id, material_id, issued_qty, unit_rate_lc, invoice_id, invoice_amt, disc_amt, other_amt)
	Select a.voucher_id, a.vch_tran_id, a.material_id, a.issued_qty, a.unit_rate_lc, b.stock_id, (-1 * c.item_amt), 0, 0
	From st.stock_ledger a 
	Inner join st.stock_control b on a.voucher_id = b.stock_id
	inner join st.stock_tran c on a.voucher_id = c.stock_id and a.vch_tran_id = c.stock_tran_id
	Where b.doc_type='SR'
		And b.status=5 
		And a.company_id = pcompany_id		
		And b.doc_date between pfrom_date and pto_date;

	-- Step Final: Calculate and populate results
	return query 
	select a.material_id, b.material_name, a.issued_qty, a.unit_rate_lc, (a.issued_qty * a.unit_rate_lc) as issue_value, 
		a.invoice_amt, a.disc_amt, a.other_amt, (a.invoice_amt - (a.issued_qty * a.unit_rate_lc)) as gross_margin_amt, a.voucher_id, a.vch_tran_id
	from sl_tran a
	inner join st.material b on a.material_id = b.material_id;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_get_uom_qty(IN puom_id bigint, IN pqty numeric,  IN ptarget_uom_id bigint, OUT presult_qty numeric(18,4))
  RETURNS numeric AS
$BODY$
Declare vResultQty numeric(18, 4) = 0; vBaseQty numeric(18, 4) = 0; vUnitsInBase numeric(18, 4) = 0;
Begin
	-- Fetch the conversion unit
	select uom_qty into vUnitsInBase from st.uom where uom_id=puom_id;
	
	-- Covert Qty to Base Unit
	Select (pqty * vUnitsInBase) into vBaseQty;

	-- Fetch rhe coversion unit for target UoM
	select uom_qty into vUnitsInBase from st.uom where uom_id=ptarget_uom_id;
	
	-- Convert to result
	Select sys.fn_handle_zero_divide(vBaseQty, vUnitsInBase) into vResultQty;
	
	-- Generate the output
	presult_qty:=vResultQty;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_inv_print(
    IN pinv_id character varying,
    IN pcp_option smallint)
  RETURNS TABLE(cp_id bigint, cp_desc character varying, inv_id character varying, company_id bigint, finyear character varying, branch_id bigint, doc_type character varying, doc_date date, item_amt_tot numeric, tax_amt_tot numeric, nt_amt numeric, rof_amt numeric, inv_amt numeric, status smallint, narration character varying, amt_in_words character varying, cust_name character varying, cust_vtin character varying, cust_gstin character varying, cust_pan character varying, cust_addr character varying, is_ship_addr boolean, cust_ship_addr character varying, order_ref character varying, order_date date, pay_term character varying) AS
$BODY$
BEGIN	
	Drop Table if Exists inv_temp;
	Create Temp Table inv_temp
	(	cp_id BigInt,
		cp_desc Character Varying,
		inv_id character varying, 
		company_id bigint, 
		finyear character varying, 
		branch_id bigint, 
		doc_type character varying, 
		doc_date date, 
		item_amt_tot numeric, 
		tax_amt_tot numeric, 
		nt_amt numeric, 
		rof_amt numeric, 
		inv_amt numeric, 
		status smallint, 
		narration character varying, 
		amt_in_words character varying, 
		cust_name character varying,
		cust_vtin character varying,
		cust_gstin character varying,
		cust_pan character varying,
		cust_addr character varying,
		is_ship_addr boolean,
		cust_ship_addr character varying,
		order_ref Character Varying, 
		order_date Date,
		pay_term Character Varying
	);

	If pcp_option = 1 Then
		Insert Into inv_temp(cp_id, cp_desc, inv_id, company_id, finyear, branch_id, doc_type, doc_date, 
			item_amt_tot, tax_amt_tot, nt_amt, rof_amt, inv_amt, 
			status, narration, amt_in_words, 
			cust_name, cust_vtin, cust_gstin, cust_pan, 
			cust_addr, is_ship_addr, cust_ship_addr, order_ref, order_date, pay_term)
		Select 1, 'Original - Buyer''s Copy', a.stock_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.gross_amt, a.tax_amt, a.misc_non_taxable_amt, a.round_off_amt, a.total_amt, 
			a.status, a.narration, a.amt_in_words, 
			b.customer_name, b.annex_info->'tax_info'->>'vtin', b.annex_info->'tax_info'->>'gstin', b.annex_info->'tax_info'->>'pan',
			a.customer_address, (a.annex_info->'ship_info'->>'is_ship_addr')::boolean, a.annex_info->'ship_info'->>'ship_addr',
			a.bill_no, a.bill_date, c.pay_term
		From st.stock_control a
		Inner Join ar.customer b On a.account_id = b.customer_id
		Left Join ac.pay_term c On b.pay_term_id = c.pay_term_id
		Where a.stock_id=pinv_id
		Union All
		Select 2, 'Duplicate - Seller''s Copy', a.stock_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.gross_amt, a.tax_amt, a.misc_non_taxable_amt, a.round_off_amt, a.total_amt, 
			a.status, a.narration, a.amt_in_words, 
			b.customer_name, b.annex_info->'tax_info'->>'vtin', b.annex_info->'tax_info'->>'gstin', b.annex_info->'tax_info'->>'pan',
			a.customer_address, (a.annex_info->'ship_info'->>'is_ship_addr')::boolean, a.annex_info->'ship_info'->>'ship_addr',
			a.bill_no, a.bill_date, c.pay_term
		From st.stock_control a
		Inner Join ar.customer b On a.account_id = b.customer_id
		Left Join ac.pay_term c On b.pay_term_id = c.pay_term_id
		Where a.stock_id=pinv_id;
	ElseIf pcp_option = 2 Then
		Insert Into inv_temp(cp_id, cp_desc, inv_id, company_id, finyear, branch_id, doc_type, doc_date, 
			item_amt_tot, tax_amt_tot, nt_amt, rof_amt, inv_amt, 
			status, narration, amt_in_words, 
			cust_name, cust_vtin, cust_gstin, cust_pan, 
			cust_addr, is_ship_addr, cust_ship_addr, order_ref, order_date, pay_term)
		Select 1, 'Triplicate - Transporter''s Copy', a.stock_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.gross_amt, a.tax_amt, a.misc_non_taxable_amt, a.round_off_amt, a.total_amt, 
			a.status, a.narration, a.amt_in_words, 
			b.customer_name, b.annex_info->'tax_info'->>'vtin', b.annex_info->'tax_info'->>'gstin', b.annex_info->'tax_info'->>'pan',
			a.customer_address, (a.annex_info->'ship_info'->>'is_ship_addr')::boolean, a.annex_info->'ship_info'->>'ship_addr',
			a.bill_no, a.bill_date, c.pay_term
		From st.stock_control a
		Inner Join ar.customer b On a.account_id = b.customer_id
		Left Join ac.pay_term c On b.pay_term_id = c.pay_term_id
		Where a.stock_id=pinv_id;
	ElseIf pcp_option = 3 Then
		Insert Into inv_temp(cp_id, cp_desc, inv_id, company_id, finyear, branch_id, doc_type, doc_date, 
			item_amt_tot, tax_amt_tot, nt_amt, rof_amt, inv_amt, 
			status, narration, amt_in_words, 
			cust_name, cust_vtin, cust_gstin, cust_pan, 
			cust_addr, is_ship_addr, cust_ship_addr, order_ref, order_date, pay_term)
		Select 1, 'Quadruplicate - Extra Copy', a.stock_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.gross_amt, a.tax_amt, a.misc_non_taxable_amt, a.round_off_amt, a.total_amt, 
			a.status, a.narration, a.amt_in_words, 
			b.customer_name, b.annex_info->'tax_info'->>'vtin', b.annex_info->'tax_info'->>'gstin', b.annex_info->'tax_info'->>'pan',
			a.customer_address, (a.annex_info->'ship_info'->>'is_ship_addr')::boolean, a.annex_info->'ship_info'->>'ship_addr',
			a.bill_no, a.bill_date, c.pay_term
		From st.stock_control a
		Inner Join ar.customer b On a.account_id = b.customer_id
		Left Join ac.pay_term c On b.pay_term_id = c.pay_term_id
		Where a.stock_id=pinv_id;
	ElseIf pcp_option = 4 Then -- SRN
		Insert Into inv_temp(cp_id, cp_desc, inv_id, company_id, finyear, branch_id, doc_type, doc_date, 
			item_amt_tot, tax_amt_tot, nt_amt, rof_amt, inv_amt, 
			status, narration, amt_in_words, 
			cust_name, cust_vtin, cust_gstin, cust_pan, 
			cust_addr, is_ship_addr, cust_ship_addr, order_ref, order_date, pay_term)
		Select 1, 'Original - Buyer''s Copy', a.stock_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.gross_amt, a.tax_amt, a.misc_non_taxable_amt, a.round_off_amt, a.total_amt, 
			a.status, a.narration, a.amt_in_words, 
			b.customer_name, b.annex_info->'tax_info'->>'vtin', b.annex_info->'tax_info'->>'gstin', b.annex_info->'tax_info'->>'pan',
			a.customer_address, (a.annex_info->'ship_info'->>'is_ship_addr')::boolean, a.annex_info->'ship_info'->>'ship_addr',
			a.annex_info->>'origin_inv_id', (a.annex_info->>'origin_inv_date')::date, c.pay_term
		From st.stock_control a
		Inner Join ar.customer b On a.account_id = b.customer_id
		Left Join ac.pay_term c On b.pay_term_id = c.pay_term_id
		Where a.stock_id=pinv_id
		Union All
		Select 2, 'Duplicate - Seller''s Copy', a.stock_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.gross_amt, a.tax_amt, a.misc_non_taxable_amt, a.round_off_amt, a.total_amt, 
			a.status, a.narration, a.amt_in_words, 
			b.customer_name, b.annex_info->'tax_info'->>'vtin', b.annex_info->'tax_info'->>'gstin', b.annex_info->'tax_info'->>'pan',
			a.customer_address, (a.annex_info->'ship_info'->>'is_ship_addr')::boolean, a.annex_info->'ship_info'->>'ship_addr',
			a.annex_info->>'origin_inv_id', (a.annex_info->>'origin_inv_date')::date, c.pay_term
		From st.stock_control a
		Inner Join ar.customer b On a.account_id = b.customer_id
		Left Join ac.pay_term c On b.pay_term_id = c.pay_term_id
		Where a.stock_id=pinv_id;
	End If;
	
	Return Query
	Select a.cp_id, a.cp_desc, a.inv_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
		a.item_amt_tot, a.tax_amt_tot, a.nt_amt, a.rof_amt, a.inv_amt, 
		a.status, a.narration, a.amt_in_words, 
		a.cust_name, a.cust_vtin, a.cust_gstin, a.cust_pan, a.cust_addr, a.is_ship_addr, a.cust_ship_addr,
		a.order_ref, a.order_date, a.pay_term
	From inv_temp a;
	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_gst_inv_print(IN pinv_id character varying, IN pcp_option smallint)
RETURNS TABLE
(	cp_id bigint, cp_desc character varying, inv_id character varying, company_id bigint, finyear character varying, branch_id bigint, 
 	doc_type character varying, doc_date date, item_amt_tot numeric, tax_amt_tot numeric, nt_amt numeric, rof_amt numeric, inv_amt numeric, 
 	status smallint, narration character varying, amt_in_words character varying, 
 	cust_name character varying, cust_state character varying, cust_gstin character varying, cust_pan character varying, cust_addr character varying, 
 	is_ship_consign boolean, cust_ship_addr character varying, 
 	order_ref character varying, order_date date, pay_term character varying, vat_type_id BigInt
) 
AS
$BODY$
BEGIN	
	Drop Table if Exists inv_temp;
	Create Temp Table inv_temp
	(	cp_id BigInt,
		cp_desc Character Varying,
		inv_id character varying, 
		company_id bigint, 
		finyear character varying, 
		branch_id bigint, 
		doc_type character varying, 
		doc_date date, 
		item_amt_tot numeric, 
		tax_amt_tot numeric, 
		nt_amt numeric, 
		rof_amt numeric, 
		inv_amt numeric, 
		status smallint, 
		narration character varying, 
		amt_in_words character varying, 
		cust_name character varying,
		cust_state character varying,
		cust_gstin character varying,
		cust_pan character varying,
		cust_addr character varying,
		is_ship_consign boolean,
		cust_ship_addr character varying,
		order_ref Character Varying, 
		order_date Date,
		pay_term Character Varying,
                vat_type_id BigInt
	);

	If pcp_option = 1 Then
		Insert Into inv_temp(cp_id, cp_desc, inv_id, company_id, finyear, branch_id, doc_type, doc_date, 
			item_amt_tot, tax_amt_tot, nt_amt, rof_amt, inv_amt, 
			status, narration, amt_in_words, 
			cust_name, cust_state, cust_gstin, cust_pan, 
			cust_addr, is_ship_consign, cust_ship_addr, order_ref, order_date, pay_term, vat_type_id)
		Select 1, 'Original For Recipient', a.stock_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.gross_amt, a.tax_amt, a.misc_non_taxable_amt, a.round_off_amt, a.total_amt, 
			a.status, a.narration, a.amt_in_words, 
			b.customer_name, d.gst_state_code || ' - ' || d.state_name as gst_state, a.annex_info->'gst_output_info'->>'customer_gstin', b.annex_info->'tax_info'->>'pan',
			a.customer_address, (a.annex_info->'gst_output_info'->>'is_ship_consign')::boolean, a.annex_info->'gst_output_info'->>'ship_consign_addr',
			a.bill_no, a.bill_date, c.pay_term, a.vat_type_id
		From st.stock_control a
		Inner Join ar.customer b On a.account_id = b.customer_id
		Left Join ac.pay_term c On b.pay_term_id = c.pay_term_id
                Inner Join tx.gst_state d On (a.annex_info->'gst_output_info'->>'customer_state_id')::BigInt = d.gst_state_id
		Where a.stock_id=pinv_id
		Union All
		Select 2, 'Triplicate For Supplier', a.stock_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.gross_amt, a.tax_amt, a.misc_non_taxable_amt, a.round_off_amt, a.total_amt, 
			a.status, a.narration, a.amt_in_words, 
			b.customer_name, d.gst_state_code || ' - ' || d.state_name as gst_state, a.annex_info->'gst_output_info'->>'customer_gstin', b.annex_info->'tax_info'->>'pan',
			a.customer_address, (a.annex_info->'gst_output_info'->>'is_ship_consign')::boolean, a.annex_info->'gst_output_info'->>'ship_consign_addr',
			a.bill_no, a.bill_date, c.pay_term, a.vat_type_id
		From st.stock_control a
		Inner Join ar.customer b On a.account_id = b.customer_id
		Left Join ac.pay_term c On b.pay_term_id = c.pay_term_id
                Inner Join tx.gst_state d On (a.annex_info->'gst_output_info'->>'customer_state_id')::BigInt = d.gst_state_id
		Where a.stock_id=pinv_id;
	ElseIf pcp_option = 2 Then
		Insert Into inv_temp(cp_id, cp_desc, inv_id, company_id, finyear, branch_id, doc_type, doc_date, 
			item_amt_tot, tax_amt_tot, nt_amt, rof_amt, inv_amt, 
			status, narration, amt_in_words, 
			cust_name, cust_state, cust_gstin, cust_pan, 
			cust_addr, is_ship_consign, cust_ship_addr, order_ref, order_date, pay_term, vat_type_id)
		Select 1, 'Duplicate For Transporter', a.stock_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.gross_amt, a.tax_amt, a.misc_non_taxable_amt, a.round_off_amt, a.total_amt, 
			a.status, a.narration, a.amt_in_words, 
			b.customer_name, d.gst_state_code || ' - ' || d.state_name as gst_state, a.annex_info->'gst_output_info'->>'customer_gstin', b.annex_info->'tax_info'->>'pan',
			a.customer_address, (a.annex_info->'gst_output_info'->>'is_ship_consign')::boolean, a.annex_info->'gst_output_info'->>'ship_consign_addr',
			a.bill_no, a.bill_date, c.pay_term, a.vat_type_id
		From st.stock_control a
		Inner Join ar.customer b On a.account_id = b.customer_id
		Left Join ac.pay_term c On b.pay_term_id = c.pay_term_id
                Inner Join tx.gst_state d On (a.annex_info->'gst_output_info'->>'customer_state_id')::BigInt = d.gst_state_id
		Where a.stock_id=pinv_id;
	ElseIf pcp_option = 3 Then
		Insert Into inv_temp(cp_id, cp_desc, inv_id, company_id, finyear, branch_id, doc_type, doc_date, 
			item_amt_tot, tax_amt_tot, nt_amt, rof_amt, inv_amt, 
			status, narration, amt_in_words, 
			cust_name, cust_state, cust_gstin, cust_pan, 
			cust_addr, is_ship_consign, cust_ship_addr, order_ref, order_date, pay_term, vat_type_id)
		Select 1, 'Triplicate For Supplier', a.stock_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.gross_amt, a.tax_amt, a.misc_non_taxable_amt, a.round_off_amt, a.total_amt, 
			a.status, a.narration, a.amt_in_words, 
			b.customer_name, d.gst_state_code || ' - ' || d.state_name as gst_state, a.annex_info->'gst_output_info'->>'customer_gstin', b.annex_info->'tax_info'->>'pan',
			a.customer_address, (a.annex_info->'gst_output_info'->>'is_ship_consign')::boolean, a.annex_info->'gst_output_info'->>'ship_consign_addr',
			a.bill_no, a.bill_date, c.pay_term, a.vat_type_id
		From st.stock_control a
		Inner Join ar.customer b On a.account_id = b.customer_id
		Left Join ac.pay_term c On b.pay_term_id = c.pay_term_id
                Inner Join tx.gst_state d On (a.annex_info->'gst_output_info'->>'customer_state_id')::BigInt = d.gst_state_id
		Where a.stock_id=pinv_id;
	ElseIf pcp_option = 4 Then -- SRN
		Insert Into inv_temp(cp_id, cp_desc, inv_id, company_id, finyear, branch_id, doc_type, doc_date, 
			item_amt_tot, tax_amt_tot, nt_amt, rof_amt, inv_amt, 
			status, narration, amt_in_words, 
			cust_name, cust_state, cust_gstin, cust_pan, 
			cust_addr, is_ship_consign, cust_ship_addr, order_ref, order_date, pay_term, vat_type_id)
		Select 1, 'Original For Recipient', a.stock_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.gross_amt, a.tax_amt, a.misc_non_taxable_amt, a.round_off_amt, a.total_amt, 
			a.status, a.narration, a.amt_in_words, 
			b.customer_name, d.gst_state_code || ' - ' || d.state_name as gst_state, a.annex_info->'gst_output_info'->>'customer_gstin', b.annex_info->'tax_info'->>'pan',
			a.customer_address, (a.annex_info->'ship_info'->>'is_ship_addr')::boolean, a.annex_info->'ship_info'->>'ship_addr',
			a.annex_info->>'origin_inv_id', (a.annex_info->>'origin_inv_date')::date, c.pay_term, a.vat_type_id
		From st.stock_control a
		Inner Join ar.customer b On a.account_id = b.customer_id
		Left Join ac.pay_term c On b.pay_term_id = c.pay_term_id
                Inner Join tx.gst_state d On (a.annex_info->'gst_output_info'->>'customer_state_id')::BigInt = d.gst_state_id
		Where a.stock_id=pinv_id
		Union All
		Select 2, 'Duplicate For Supplier', a.stock_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.gross_amt, a.tax_amt, a.misc_non_taxable_amt, a.round_off_amt, a.total_amt, 
			a.status, a.narration, a.amt_in_words, 
			b.customer_name, d.gst_state_code || ' - ' || d.state_name as gst_state, a.annex_info->'gst_output_info'->>'customer_gstin', b.annex_info->'tax_info'->>'pan',
			a.customer_address, (a.annex_info->'ship_info'->>'is_ship_addr')::boolean, a.annex_info->'ship_info'->>'ship_addr',
			a.annex_info->>'origin_inv_id', (a.annex_info->>'origin_inv_date')::date, c.pay_term, a.vat_type_id
		From st.stock_control a
		Inner Join ar.customer b On a.account_id = b.customer_id
		Left Join ac.pay_term c On b.pay_term_id = c.pay_term_id
                Inner Join tx.gst_state d On (a.annex_info->'gst_output_info'->>'customer_state_id')::BigInt = d.gst_state_id
		Where a.stock_id=pinv_id;
	End If;
	
	Return Query
	Select a.cp_id, a.cp_desc, a.inv_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
		a.item_amt_tot, a.tax_amt_tot, a.nt_amt, a.rof_amt, a.inv_amt, 
		a.status, a.narration, a.amt_in_words, 
		a.cust_name, a.cust_state, a.cust_gstin, a.cust_pan, a.cust_addr, a.is_ship_consign, a.cust_ship_addr,
		a.order_ref, a.order_date, a.pay_term, a.vat_type_id
	From inv_temp a;
	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_inv_tran_print(IN pinv_id character varying)
  RETURNS TABLE(inv_tran_id character varying, inv_id character varying, 
	sl_no bigint, material_type_id bigint, material_type character varying, bar_code character varying, material_id bigint, material_name character varying, 
	stock_location_id bigint, stock_location_name character varying, uom_id bigint, uom_desc character varying, issued_qty numeric, rate numeric, disc_amt numeric, 
	bt_amt numeric, tax_schedule_id bigint, tax_pcnt numeric, tax_amt numeric, item_amt numeric,
	war_info Text
) AS
$BODY$
Begin
	Return Query
	With mfg_war
	As
	(	Select stock_tran_id, string_agg(x.mfg_serial, ',') as mfg_serial
		From st.stock_tran_war x
		Where x.stock_id = pinv_id
		Group by x.stock_tran_id
	)
	Select a.stock_tran_id, a.stock_id, a.sl_no, a.material_type_id, b.material_type, a.bar_code, 
		a.material_id, c.material_name, a.stock_location_id, d.stock_location_name,
		a.uom_id, e.uom_desc, Case When a.issued_qty != 0 Then a.issued_qty Else a.received_qty End, a.rate, a.disc_amt, a.bt_amt, 
		a.tax_schedule_id, a.tax_pcnt, a.tax_amt, a.item_amt, f.mfg_serial
	From st.stock_tran a
	Inner Join st.material_type b On a.material_type_id=b.material_type_id
	Inner Join st.material c On a.material_id=c.material_id
	Left Join st.stock_location d On a.stock_location_id=d.stock_location_id
	Inner Join st.uom e On a.uom_id=e.uom_id
	Left Join mfg_war f On a.stock_tran_id= f.stock_tran_id
	Where a.stock_id=pinv_id
	Order by a.sl_no;

End
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_gst_inv_tran_print(IN pinv_id character varying)
RETURNS TABLE(inv_tran_id character varying, inv_id character varying, 
	sl_no bigint, material_type_id bigint, material_type character varying, bar_code character varying, material_id bigint, material_name character varying, 
	stock_location_id bigint, stock_location_name character varying, uom_id bigint, uom_desc character varying, issued_qty numeric, rate numeric, disc_amt numeric, 
	bt_amt numeric, hsn_sc_code character varying, hsn_sc_type character varying, gst_rate_id bigint, sgst_pcnt numeric, sgst_amt numeric, 
	cgst_pcnt numeric, cgst_amt numeric, igst_pcnt numeric, igst_amt numeric,cess_pcnt numeric, cess_amt numeric, tax_amt numeric, item_amt numeric,
	war_info Text, other_amt numeric
) AS
$BODY$
Begin
	Return Query
	With mfg_war
	As
	(	Select stock_tran_id, string_agg(x.mfg_serial, ',') as mfg_serial
		From st.stock_tran_war x
		Where x.stock_id = pinv_id
		Group by x.stock_tran_id
	)
	Select a.stock_tran_id, a.stock_id, a.sl_no, c.material_type_id, c.material_type, a.bar_code, 
		a.material_id, b.material_name, a.stock_location_id, d.stock_location_name,
		a.uom_id, e.uom_desc, Case When a.issued_qty != 0 Then a.issued_qty Else a.received_qty End, a.rate, a.disc_amt, a.bt_amt, 
		g.hsn_sc_code, g.hsn_sc_type, g.gst_rate_id, g.sgst_pcnt, g.sgst_amt, 
		g.cgst_pcnt, g.cgst_amt, g.igst_pcnt, g.igst_amt, g.cess_pcnt, g.cess_amt, a.tax_amt, a.item_amt, 
                f.mfg_serial, Case When Left(pinv_id, 3) = 'SRV' Then 0.00 Else a.other_amt End
	From st.stock_tran a
	Inner Join st.material b On a.material_id=b.material_id
	Inner Join st.material_type c On b.material_type_id=c.material_type_id
	Left Join st.stock_location d On a.stock_location_id=d.stock_location_id
	Inner Join st.uom e On a.uom_id=e.uom_id
	Left Join mfg_war f On a.stock_tran_id= f.stock_tran_id
	Inner Join tx.gst_tax_tran g On a.stock_tran_id = g.gst_tax_tran_id
	Where a.stock_id=pinv_id
	Order by a.sl_no;

End
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_inv_tax_print(IN pinv_id character varying)
  RETURNS TABLE(inv_id character varying, tax_schedule_id bigint, tax_detail_id bigint, tax_desc character varying, item_assess_amt numeric, tax_pcnt numeric, tax_amt numeric, item_amt numeric) AS
$BODY$
Begin
	Return Query
	Select a.stock_id, a.tax_schedule_id, c.tax_detail_id, c.description, 
		Sum(a.bt_amt), a.tax_pcnt, Sum(a.tax_amt), Sum(a.item_amt)
	From st.stock_tran a
	Inner Join tx.tax_schedule b On a.tax_schedule_id=b.tax_schedule_id
	Inner Join tx.tax_detail c On b.tax_schedule_id=c.tax_schedule_id
	Where a.stock_id=pinv_id
	Group by a.stock_id, a.tax_schedule_id, c.tax_detail_id, c.description, a.tax_pcnt;

End
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_gst_inv_tax_print(IN pinv_id character varying)
RETURNS TABLE
(	inv_id character varying,
	item_taxable_amt numeric,
        hsn_sc_code Character Varying,
 	gst_rate_id BigInt,
	sgst_pcnt numeric, 
 	sgst_amt numeric,
 	cgst_pcnt numeric, 
 	cgst_amt numeric,
 	igst_pcnt numeric, 
 	igst_amt numeric,
 	cess_pcnt numeric, 
 	cess_amt numeric,
	tax_amt numeric,
	item_amt numeric) 
AS
$BODY$
Begin
	Return Query
	Select a.voucher_id, Sum(a.bt_amt), a.hsn_sc_code, a.gst_rate_id, 
    	min(a.sgst_pcnt), Sum(a.sgst_amt),
        min(a.cgst_pcnt), Sum(a.cgst_amt),
        min(a.igst_pcnt), Sum(a.igst_amt),
        min(a.cess_pcnt), Sum(a.cess_amt),
        Sum(a.sgst_amt+a.cgst_amt+a.igst_amt+a.cess_amt),
        Sum(a.bt_amt+a.sgst_amt+a.cgst_amt+a.igst_amt+a.cess_amt)
	From tx.gst_tax_tran a
	Where a.voucher_id=pinv_id And a.tran_group = 'st.stock_tran'
	Group by a.voucher_id, a.hsn_sc_code, a.gst_rate_id;

End
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_mat_coll_for_sale_rate(IN pmaterial_type_id bigint, IN pmaterial_id bigint, pprice_type varchar(4))
RETURNS TABLE
(   
	material_type_id bigint,
	material_type varchar(50),
	material_id bigint,
	material_name varchar(250),
	material_code varchar(20), 
	material_desc varchar(2000),
	sr_pu numeric(18,4),
	disc_pcnt numeric(18,4),
	price_type varchar(4)
) 
AS
$BODY$
Begin	 
	return query
	Select a.material_type_id, b.material_type, a.material_id, a.material_name, a.material_code, a.material_desc,
		case when (a.annex_info->'sale_price'->>'price_type')::varchar = 'WAC' then COALESCE((a.annex_info->'sale_price'->'wac_calc'->>'markup_pu')::numeric, 0)
		     when (a.annex_info->'sale_price'->>'price_type')::varchar = 'LP' then COALESCE((a.annex_info->'sale_price'->'lp_calc'->>'markup_pu')::numeric, 0)
		     when (a.annex_info->'sale_price'->>'price_type')::varchar = 'FP' then COALESCE((a.annex_info->'sale_price'->'sp_calc'->>'fixed_pu')::numeric, 0)
		End,
		case when (a.annex_info->'sale_price'->>'price_type')::varchar = 'WAC' then COALESCE((a.annex_info->'sale_price'->'wac_calc'->>'markup_pcnt')::numeric, 0)
		     when (a.annex_info->'sale_price'->>'price_type')::varchar = 'LP' then COALESCE((a.annex_info->'sale_price'->'lp_calc'->>'markup_pcnt')::numeric, 0)
		     when (a.annex_info->'sale_price'->>'price_type')::varchar = 'FP' then COALESCE((a.annex_info->'sale_price'->'sp_calc'->>'disc_pcnt')::numeric, 0)
		End, (a.annex_info->'sale_price'->>'price_type')::varchar
	from st.material a
	inner join st.material_type b on a.material_type_id = b.material_type_id
	where (a.material_type_id = pmaterial_type_id or pmaterial_type_id = 0)
		And (a.material_id=pmaterial_id or pmaterial_id=0)
		And (a.annex_info->'sale_price'->>'price_type')::varchar = pprice_type;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_mat_info_sale(pbar_code character varying, pmat_id bigint,
	pstock_loc_id bigint, pdoc_date date, pfinyear character varying, pcustomer_id bigint = -1)
RETURNS TABLE
(	bar_code character varying, 
        mat_id bigint, 
        mat_name character varying,
        is_service boolean,
        material_type_id bigint, 
        mt_name character varying, 
        uom_id bigint, 
        uom character varying, 
        sale_rate numeric, 
        disc_pcnt numeric,
        bal_qty numeric, 
        gst_hsn_info json,
        has_qc boolean
) 
AS
$BODY$
Declare
	vbar_code Varchar(20):=''; vmat_id BigInt:=-1; vmat_name character varying:=''; vmt_id BigInt:=-1; vmt_name character varying:=''; 
	vuom_id BigInt:=-1; vuom character varying:=''; vis_service boolean:=false;
	vsale_rate Numeric(18,4):=0; vdisc_pcnt Numeric(5,2):=0; vbal_qty Numeric(18,4):=0; vhas_qc boolean:=false;
        -- GST Variables
        vhsn_sc_id BigInt:=-1; vgst_hsn_info Json:='{}';
	-- Price List Variables
        vpl_id BigInt:=-1; vpl_type text:=''; vpl_disc_pcnt Numeric(5,2):=0; vpl_has_mat_ovrd Boolean:=False;
    -- Overridden rate for Sale and Purchase
    	vSaleGstRate_ID bigint = -1;
Begin
	-- By Girish
	-- This Procedure is used in POS for fetching a single material information along with gst_hsn_info
	If pmat_id = -1 Then 
            -- mat id unknown. Therefore query using bar code
            Select a.material_id, a.material_name, a.material_code, a.material_type_id,
                    (a.annex_info->'sale_price'->'sp_calc'->>'fixed_pu')::numeric,
                    (a.annex_info->'sale_price'->'sp_calc'->>'disc_pcnt')::numeric,
                    (a.annex_info->'gst_info'->>'hsn_sc_id')::bigint,
                    (a.annex_info->>'is_service')::boolean,
                    COALESCE((a.annex_info->'gst_info'->>'sale_gst_rate_id')::bigint, -1),
                    (a.annex_info->'qc_info'->>'has_qc')::boolean
                    Into vmat_id, vmat_name, vbar_code, vmt_id, vsale_rate, vdisc_pcnt, vhsn_sc_id, vis_service, vSaleGstRate_ID, vhas_qc
            From st.material a
            Where a.material_code = pbar_code;
	Else 
            -- query using mat id
            Select a.material_id, a.material_name, a.material_code, a.material_type_id,
                    (a.annex_info->'sale_price'->'sp_calc'->>'fixed_pu')::numeric,
                    (a.annex_info->'sale_price'->'sp_calc'->>'disc_pcnt')::numeric,
                    (a.annex_info->'gst_info'->>'hsn_sc_id')::bigint,
                    (a.annex_info->>'is_service')::boolean,
                    COALESCE((a.annex_info->'gst_info'->>'sale_gst_rate_id')::bigint, -1),
                    (a.annex_info->'qc_info'->>'has_qc')::boolean
                    Into vmat_id, vmat_name, vbar_code, vmt_id, vsale_rate, vdisc_pcnt, vhsn_sc_id, vis_service, vSaleGstRate_ID, vhas_qc
            From st.material a
            Where a.material_id = pmat_id;
	End If;

	-- Proceed only if vmat_id was found
	If vmat_id != -1 Then
            -- Get material_type Info
            Select a.material_type Into vmt_name
            From st.material_type a
            Where a.material_type_id = vmt_id;

            -- Get Uom Info
            Select a.uom_id, a.uom_desc || ' (SU)' Into vuom_id, vuom
            From st.uom a
            Where a.material_id = vmat_id And a.is_su = true;

            -- Get Tax Info
            -- If GST Sale rate is override in Material
            -- Fetch overriden rate 
            If vSaleGstRate_ID != -1 Then
                with hsn_sc
                As ( 
                    select a.hsn_sc_code, a.hsn_sc_type
                    From tx.hsn_sc a 
                    Where a.hsn_sc_id = vhsn_sc_id
                ),
                row_data
                As
                (   Select a.hsn_sc_code, a.hsn_sc_type, b.*
                    From hsn_sc a, tx.gst_rate b
                    Where b.gst_rate_id = vSaleGstRate_ID
                )
                Select row_to_json(r) Into vgst_hsn_info
                From row_data r;
            Else
                With row_data
                As
                (   Select a.hsn_sc_code, a.hsn_sc_type, c.*
                    From tx.hsn_sc a 
                    Inner Join tx.hsn_sc_rate b On a.hsn_sc_id = b.hsn_sc_id
                    Inner Join tx.gst_rate c On b.gst_rate_id = c.gst_rate_id
                    Where a.hsn_sc_id = vhsn_sc_id
                )
                Select row_to_json(r) Into vgst_hsn_info
                From row_data r;
            End If;

            -- Get Balance Qty
            if pstock_loc_id != -1 Then
                Select Coalesce(Sum(a.received_qty-a.issued_qty), 0.00)
                        Into vbal_qty
                From st.stock_ledger a
                Where a.material_id=vmat_id
                        And a.doc_date <= pdoc_date
                        And a.stock_location_id = pstock_loc_id
                        And a.finyear = pfinyear;
            End if;        

            -- Get Price Level
            If pcustomer_id != -1 And Exists(SELECT * FROM information_schema.tables where table_schema='crm' And table_name = 'price_list') Then
                /*Select price_list_id into vpl_id
                From crm.customer 
                Where customer_id = pcustomer_id; */
				
                -- Custom code for SKM (PL based on MatType)
                Select Coalesce(min((pl->>'pl_id')::BigInt), -1) Into vpl_id
                From ar.customer cust, jsonb_array_elements(annex_info->'pl_info') pl
                Where cust.customer_id = pcustomer_id
                        And (pl->>'mt_id')::BigInt = vmt_id;

                If vpl_id != -1 Then
                    -- When Price list exists, basic discount is not applied
                    vdisc_pcnt := 0;

                    Select a.psale_rate Into vsale_rate
                    From crm.fn_mat_info_pl(vmat_id, vpl_id) a;
                End If;
            End If;
        End If;

    -- generate output
    Return Query
    Select vbar_code, vmat_id, vmat_name, vis_service, vmt_id, vmt_name, vuom_id, vuom, vsale_rate, vdisc_pcnt, vbal_qty, vgst_hsn_info, vhas_qc;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_mat_info_purch(pbar_code character varying, pmat_id bigint,
	pstock_loc_id bigint, pdoc_date date, pfinyear character varying, pcustomer_id bigint = -1)
RETURNS TABLE
(	bar_code character varying, 
        mat_id bigint, 
        mat_name character varying,
        is_service boolean,
        material_type_id bigint, 
        mt_name character varying, 
        uom_id bigint, 
        uom character varying, 
        gst_hsn_info json
) 
AS
$BODY$
Declare
	vbar_code Varchar(20):=''; vmat_id BigInt:=-1; vmat_name character varying:=''; vmt_id BigInt:=-1; vmt_name character varying:=''; 
	vuom_id BigInt:=-1; vuom character varying:=''; vis_service boolean:=false;	
        -- GST Variables
        vhsn_sc_id BigInt:=-1; vgst_hsn_info Json:='{}';
	-- Price List Variables
        vpl_id BigInt:=-1; vpl_disc_pcnt Numeric(5,2):=0; vpl_has_mat_ovrd Boolean:=False;
    -- Overridden rate for Purchase
    	vPurchGstRate_ID bigint = -1;
Begin
	-- By Girish
	-- This Procedure is used in Purchase for fetching a single material information along with gst_hsn_info
	If pmat_id = -1 Then 
            -- mat id unknown. Therefore query using bar code
            Select a.material_id, a.material_name, a.material_code, a.material_type_id,
                    (a.annex_info->'gst_info'->>'hsn_sc_id')::bigint,
                    (a.annex_info->>'is_service')::boolean,
                    COALESCE((a.annex_info->'gst_info'->>'purch_gst_rate_id')::bigint, -1)
                    Into vmat_id, vmat_name, vbar_code, vmt_id, vhsn_sc_id, vis_service, vPurchGstRate_ID
            From st.material a
            Where a.material_code = pbar_code;
    Else 
            -- query using mat id
            Select a.material_id, a.material_name, a.material_code, a.material_type_id,
                    (a.annex_info->'gst_info'->>'hsn_sc_id')::bigint,
                    (a.annex_info->>'is_service')::boolean,
                    COALESCE((a.annex_info->'gst_info'->>'purch_gst_rate_id')::bigint, -1)
                    Into vmat_id, vmat_name, vbar_code, vmt_id, vhsn_sc_id, vis_service, vPurchGstRate_ID
            From st.material a
            Where a.material_id = pmat_id;
    End If;
    -- Proceed only if vmat_id was found
    If vhsn_sc_id != -1 Then
        -- Get material_type Info
        Select a.material_type Into vmt_name
        From st.material_type a
        Where a.material_type_id = vmt_id;

        -- Get Uom Info
        Select a.uom_id, a.uom_desc || ' (PU)' Into vuom_id, vuom
        From st.uom a
        Where a.material_id = vmat_id And a.uom_type_id = 103;

        -- Get Tax Info
        -- If GST Purchase rate is override in Material
        -- Fetch overriden rate 
        If vPurchGstRate_ID != -1 Then
            with hsn_sc
            As ( 
                select a.hsn_sc_code, a.hsn_sc_type
                From tx.hsn_sc a 
                Where a.hsn_sc_id = vhsn_sc_id
            ),
            row_data
            As
            (	Select a.hsn_sc_code, a.hsn_sc_type, b.*
                From hsn_sc a, tx.gst_rate b
                Where b.gst_rate_id = vPurchGstRate_ID
            )
            Select row_to_json(r) Into vgst_hsn_info
            From row_data r;
        Else
            With row_data
            As
            (	Select a.hsn_sc_code, a.hsn_sc_type, c.*
                From tx.hsn_sc a 
                Inner Join tx.hsn_sc_rate b On a.hsn_sc_id = b.hsn_sc_id
                Inner Join tx.gst_rate c On b.gst_rate_id = c.gst_rate_id
                Where a.hsn_sc_id = vhsn_sc_id
            )
            Select row_to_json(r) Into vgst_hsn_info
            From row_data r;
        End If;
    End If;

	-- generate output
    Return Query
	Select vbar_code, vmat_id, vmat_name, vis_service, vmt_id, vmt_name, vuom_id, vuom, vgst_hsn_info;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_mat_info_cc(pbar_code character varying, pmat_id bigint,
	pstock_loc_id bigint, pdoc_date date, pfinyear character varying)
RETURNS TABLE
(	bar_code character varying, 
        mat_id bigint, 
        mat_name character varying,
        is_service boolean,
        material_type_id bigint, 
        mt_name character varying, 
        uom_id bigint, 
        uom character varying,
        wac_rate Numeric(18,4),
        bal_qty Numeric(18,3),
        gst_hsn_info json,
        has_ts boolean,
        has_qc boolean
) 
AS
$BODY$
Declare
	vbar_code Varchar(20):=''; vmat_id BigInt:=-1; vmat_name character varying:=''; vmt_id BigInt:=-1; vmt_name character varying:=''; 
	vuom_id BigInt:=-1; vuom character varying:=''; vis_service boolean:=false; vhas_ts boolean:=false; vhas_qc boolean:=false;
        vsale_rate Numeric(18,4):=0;
        -- GST Variables
        vhsn_sc_id BigInt:=-1; vgst_hsn_info Json:='{}';
	-- wac/qty Variables
        vbranch_id BigInt=-1; vcomp_id BigInt=-1; vwac Numeric(18,4):=0; vbal_qty Numeric(18,3):=0;
        -- Overridden rate for Sale (as it is captive cons)
    	vSaleGstRate_ID bigint = -1;
Begin
	-- By Girish
	-- This Procedure is used in Stock Transfer/Captive Consumption for fetching a single material information along with gst_hsn_info
	If pmat_id = -1 Then 
            -- mat id unknown. Therefore query using bar code
            Select a.material_id, a.material_name, a.material_code, a.material_type_id,
                    (a.annex_info->'sale_price'->'sp_calc'->>'fixed_pu')::numeric,
                    (a.annex_info->'gst_info'->>'hsn_sc_id')::bigint,
                    (a.annex_info->>'is_service')::boolean,
                    COALESCE((a.annex_info->'gst_info'->>'sale_gst_rate_id')::bigint, -1),
                    (a.annex_info->'qc_info'->>'has_ts')::boolean,
                    (a.annex_info->'qc_info'->>'has_qc')::boolean
                    Into vmat_id, vmat_name, vbar_code, vmt_id, vsale_rate, vhsn_sc_id, vis_service, vSaleGstRate_ID, vhas_ts, vhas_qc
            From st.material a
            Where a.material_code = pbar_code;
    Else 
            -- query using mat id
            Select a.material_id, a.material_name, a.material_code, a.material_type_id,
                    (a.annex_info->'sale_price'->'sp_calc'->>'fixed_pu')::numeric,
                    (a.annex_info->'gst_info'->>'hsn_sc_id')::bigint,
                    (a.annex_info->>'is_service')::boolean,
                    COALESCE((a.annex_info->'gst_info'->>'sale_gst_rate_id')::bigint, -1),
                    (a.annex_info->'qc_info'->>'has_ts')::boolean,
                    (a.annex_info->'qc_info'->>'has_qc')::boolean
                    Into vmat_id, vmat_name, vbar_code, vmt_id, vsale_rate, vhsn_sc_id, vis_service, vSaleGstRate_ID, vhas_ts, vhas_qc
            From st.material a
            Where a.material_id = pmat_id;
    End If;
    -- Proceed only if vmat_id was found
    If vhsn_sc_id != -1 Then
        -- Get material_type Info
        Select a.material_type Into vmt_name
        From st.material_type a
        Where a.material_type_id = vmt_id;

        -- Get Uom Info
        Select a.uom_id, a.uom_desc Into vuom_id, vuom
        From st.uom a
        Where a.material_id = vmat_id And a.uom_type_id = 101;

        -- Get wac and bal_qty
        If pstock_loc_id != -1 Then
            Select a.branch_id, b.company_id Into vbranch_id, vcomp_id
            From st.stock_location a
            Inner Join sys.branch b On a.branch_id = b.branch_id
            Where a.stock_location_id = pstock_loc_id;

            Select balance_qty_base, rate Into vbal_qty, vwac
            From st.fn_material_balance_wac_detail(vcomp_id, vbranch_id, vmat_id, pstock_loc_id, pfinyear, pdoc_date);
            vbal_qty = coalesce(vbal_qty, 0.00);
            vwac = coalesce(vwac, 0.00);
        End If;
        -- Get Tax Info
        -- If GST Sale rate is override in Material
        -- Fetch overriden rate 
        If vSaleGstRate_ID != -1 Then
            with hsn_sc
            As ( 
                select a.hsn_sc_code, a.hsn_sc_type
                From tx.hsn_sc a 
                Where a.hsn_sc_id = vhsn_sc_id
            ),
            row_data
            As
            (	Select a.hsn_sc_code, a.hsn_sc_type, b.*
                From hsn_sc a, tx.gst_rate b
                Where b.gst_rate_id = vSaleGstRate_ID
            )
            Select row_to_json(r) Into vgst_hsn_info
            From row_data r;
        Else
            With row_data
            As
            (	Select a.hsn_sc_code, a.hsn_sc_type, c.*
                From tx.hsn_sc a 
                Inner Join tx.hsn_sc_rate b On a.hsn_sc_id = b.hsn_sc_id
                Inner Join tx.gst_rate c On b.gst_rate_id = c.gst_rate_id
                Where a.hsn_sc_id = vhsn_sc_id
            )
            Select row_to_json(r) Into vgst_hsn_info
            From row_data r;
        End If;
    End If;

    -- For all internal comsumption and stock transfer, Sale Rate would be 60% of MRP
    vwac := vsale_rate * .6;

	-- generate output
    Return Query
	Select vbar_code, vmat_id, vmat_name, vis_service, vmt_id, vmt_name, vuom_id, vuom, vwac, vbal_qty, vgst_hsn_info, vhas_ts, vhas_qc;
End;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_gst_stock_trnsf_print(IN pinv_id character varying, IN pcp_option smallint)
RETURNS TABLE
(	cp_id bigint, cp_desc character varying, inv_id character varying, company_id bigint, finyear character varying, branch_id bigint, 
 	doc_type character varying, doc_date date, item_amt_tot numeric, tax_amt_tot numeric, nt_amt numeric, rof_amt numeric, inv_amt numeric, 
 	status smallint, narration character varying, amt_in_words character varying, 
 	cust_name character varying, cust_state character varying, cust_gstin character varying, cust_pan character varying, cust_addr character varying, 
 	is_ship_consign boolean, cust_ship_addr character varying, 
 	eway_ref character varying
) 
AS
$BODY$
BEGIN	
	Drop Table if Exists inv_temp;
	Create Temp Table inv_temp
	(	cp_id BigInt,
		cp_desc Character Varying,
		inv_id character varying, 
		company_id bigint, 
		finyear character varying, 
		branch_id bigint, 
		doc_type character varying, 
		doc_date date, 
		item_amt_tot numeric, 
		tax_amt_tot numeric, 
		nt_amt numeric, 
		rof_amt numeric, 
		inv_amt numeric, 
		status smallint, 
		narration character varying, 
		amt_in_words character varying, 
		cust_name character varying,
		cust_state character varying,
		cust_gstin character varying,
		cust_pan character varying,
		cust_addr character varying,
		is_ship_consign boolean,
		cust_ship_addr character varying,
		eway_ref Character Varying
	);

	If pcp_option = 1 Then
		Insert Into inv_temp(cp_id, cp_desc, inv_id, company_id, finyear, branch_id, doc_type, doc_date, 
			item_amt_tot, tax_amt_tot, nt_amt, rof_amt, inv_amt, 
			status, narration, amt_in_words, 
			cust_name, cust_state, cust_gstin, cust_pan, 
			cust_addr, is_ship_consign, cust_ship_addr, eway_ref)
		Select 1, 'Original For Consignee', a.stock_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.gross_amt, a.tax_amt, a.misc_non_taxable_amt, a.round_off_amt, a.total_amt, 
			a.status, a.narration, a.amt_in_words, 
			c.company_name || E'\nUnit: ' || b.branch_name, d.gst_state_code || ' - ' || d.state_name as gst_state, a.annex_info->'gst_output_info'->>'target_branch_gstin', '',
			a.annex_info->'gst_output_info'->>'target_branch_addr', false, '',
			a.annex_info->'gst_output_info'->>'eway_ref'
		From st.stock_control a
		Inner Join sys.branch b On a.target_branch_id = b.branch_id
        Inner Join sys.company c On b.company_id = c.company_id
        Inner Join tx.gst_state d On (a.annex_info->'gst_output_info'->>'target_branch_state_id')::BigInt = d.gst_state_id
		Where a.stock_id=pinv_id
		Union All
		Select 2, 'Triplicate For Consignor', a.stock_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.gross_amt, a.tax_amt, a.misc_non_taxable_amt, a.round_off_amt, a.total_amt, 
			a.status, a.narration, a.amt_in_words, 
			c.company_name || E'\nUnit: ' || b.branch_name, d.gst_state_code || ' - ' || d.state_name as gst_state, a.annex_info->'gst_output_info'->>'target_branch_gstin', '',
			a.annex_info->'gst_output_info'->>'target_branch_addr', false, '',
			a.annex_info->'gst_output_info'->>'eway_ref'
		From st.stock_control a
		Inner Join sys.branch b On a.target_branch_id = b.branch_id
        Inner Join sys.company c On b.company_id = c.company_id
        Inner Join tx.gst_state d On (a.annex_info->'gst_output_info'->>'target_branch_state_id')::BigInt = d.gst_state_id
		Where a.stock_id=pinv_id;
	ElseIf pcp_option = 2 Then
		Insert Into inv_temp(cp_id, cp_desc, inv_id, company_id, finyear, branch_id, doc_type, doc_date, 
			item_amt_tot, tax_amt_tot, nt_amt, rof_amt, inv_amt, 
			status, narration, amt_in_words, 
			cust_name, cust_state, cust_gstin, cust_pan, 
			cust_addr, is_ship_consign, cust_ship_addr, eway_ref)
		Select 1, 'Duplicate for Transporter', a.stock_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.gross_amt, a.tax_amt, a.misc_non_taxable_amt, a.round_off_amt, a.total_amt, 
			a.status, a.narration, a.amt_in_words, 
			c.company_name || E'\nUnit: ' || b.branch_name, d.gst_state_code || ' - ' || d.state_name as gst_state, a.annex_info->'gst_output_info'->>'target_branch_gstin', '',
			a.annex_info->'gst_output_info'->>'target_branch_addr', false, '',
			a.annex_info->'gst_output_info'->>'eway_ref'
		From st.stock_control a
		Inner Join sys.branch b On a.target_branch_id = b.branch_id
        Inner Join sys.company c On b.company_id = c.company_id
        Inner Join tx.gst_state d On (a.annex_info->'gst_output_info'->>'target_branch_state_id')::BigInt = d.gst_state_id
		Where a.stock_id=pinv_id;
	End If;
	
	Return Query
	Select a.cp_id, a.cp_desc, a.inv_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
		a.item_amt_tot, a.tax_amt_tot, a.nt_amt, a.rof_amt, a.inv_amt, 
		a.status, a.narration, a.amt_in_words, 
		a.cust_name, a.cust_state, a.cust_gstin, a.cust_pan, a.cust_addr, a.is_ship_consign, a.cust_ship_addr,
		a.eway_ref
	From inv_temp a;
	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
Create Function st.fn_spg_gtt_info(pvoucher_id varchar(50))
Returns Table  
(	voucher_id varchar(50),
	account_id bigint,
	tax_amt numeric(18,4)
)
As
$BODY$ 
Begin
	return query 
	With gst_tax_tran
    As
    (	Select a.stock_id, x.*
        From st.stock_control a, 
            jsonb_to_recordset(a.annex_info->'gst_tax_tran') as x (
                sl_no BigInt, apply_itc Boolean, gst_rate_id BigInt, bt_amt Numeric, tax_amt_ov Boolean,
                sgst_pcnt Numeric, sgst_amt Numeric, cgst_pcnt Numeric, cgst_amt Numeric,
                igst_pcnt Numeric, igst_amt Numeric, cess_pcnt Numeric, cess_amt Numeric)
     	Where a.stock_id = pvoucher_id
            And coalesce((a.annex_info->'gst_rc_info'->>'apply_rc')::Boolean, false) != true
    ),
    gtt_tran
    As
    (	Select a.*, b.*
        From gst_tax_tran a
        Inner Join tx.gst_rate b On a.gst_rate_id = b.gst_rate_id
        Where a.apply_itc = true
    ),
    gtt
    As
    (	select a.stock_id, a.sgst_amt as tax_amt, a.sgst_itc_account_id as account_id
		from gtt_tran a
		union all
		select a.stock_id, a.cgst_amt as tax_amt, a.cgst_itc_account_id as account_id
		from gtt_tran a
		union all
		select a.stock_id, a.igst_amt as tax_amt, a.igst_itc_account_id as account_id
		from gtt_tran a
		union all
		select a.stock_id, a.cess_amt as tax_amt, a.cess_itc_account_id as account_id
		from gtt_tran a
	)
	Select a.stock_id, a.account_id, sum(a.tax_amt)
	From gtt a
	group by a.stock_id, a.account_id
	having sum(a.tax_amt) > 0; 
END;
$BODY$
LANGUAGE plpgsql;

?==?
create or replace function st.fn_purchase_sales_report(pcompany_id bigint, pbranch_id bigint, pfrom_date date, pto_date date, pmaterial_type_id bigint, pmaterial_id bigint)
RETURNS TABLE  
(
	company_id bigint,
	item_type character varying,
	material_type_id bigint,
	material_type character varying,
	material_id bigint,
	material_name character varying,
	month_name character varying,
	month_no bigint,
	order_qty numeric(18,4),
	bt_amt numeric(18,4)
)
AS
$BODY$
Begin	
	return query
	Select a.company_id, a.item_type, a.material_type_id, b.material_type, a.material_id, c.material_name, a.month_name, a.month_no, sum(a.issued_qty) as order_qty, sum(a.bt_amt) as bt_amt
	From (
		Select a.company_id, 'Sales'::varchar as item_type, b.material_type_id, b.material_id, (date_part('Month', a.doc_date))::bigint as month_no, trim(to_char(to_timestamp(date_part('Month', a.doc_date)::varchar, 'MM'), 'Month'))::varchar as month_name, 
			b.issued_qty, 
			case when a.doc_type in ('SI','SIV') then b.item_amt - b.tax_amt
			else (-1 * b.item_amt - b.tax_amt) end as bt_amt
		From  st.stock_control a
		inner join st.stock_tran b on a.stock_id = b.stock_id
		where a.company_id = pcompany_id
			And (a.branch_id = pbranch_id or pbranch_id = 0)
			And a.doc_date between pfrom_date and pto_date
			and (b.material_type_id = pmaterial_type_id or pmaterial_type_id = 0)
			and (b.material_id = pmaterial_id or pmaterial_id = 0)
			And a.status = 5
			And a.doc_type= Any('{SI,SIV,SR,SRV,SRN}')
		Union All
		Select a.company_id, 'Sales'::varchar as item_type, b.material_type_id, b.material_id, (date_part('Month', a.doc_date))::bigint as month_no, trim(to_char(to_timestamp(date_part('Month', a.doc_date)::varchar, 'MM'), 'Month'))::varchar as month_name, 
			b.issued_qty, 
			case when a.doc_type in ('PI','PIV') then b.item_amt - b.tax_amt
			else (-1 * b.item_amt - b.tax_amt) end as bt_amt
		From  pos.inv_control a
		inner join pos.inv_tran b on a.inv_id = b.inv_id
		where a.company_id = pcompany_id
			And (a.branch_id = pbranch_id or pbranch_id = 0)
			And a.doc_date between pfrom_date and pto_date
			and (b.material_type_id = pmaterial_type_id or pmaterial_type_id = 0)
			and (b.material_id = pmaterial_id or pmaterial_id = 0)
			And a.status = 5
		Union All	
		Select a.company_id, 'Purchase'::varchar as item_type, b.material_type_id, b.material_id, (date_part('Month', a.doc_date))::bigint as month_no, trim(to_char(to_timestamp(date_part('Month', a.doc_date)::varchar, 'MM'), 'Month'))::varchar as month_name, 
			b.received_qty, 
			case when a.doc_type in ('SP','SPG') then b.item_amt - b.tax_amt
			else (-1 * b.item_amt - b.tax_amt) end as bt_amt
		From  st.stock_control a
		inner join st.stock_tran b on a.stock_id = b.stock_id
		where a.company_id = pcompany_id
			And (a.branch_id = pbranch_id or pbranch_id = 0)
			And a.doc_date between pfrom_date and pto_date
			and (b.material_type_id = pmaterial_type_id or pmaterial_type_id = 0)
			and (b.material_id = pmaterial_id or pmaterial_id = 0)
			And a.status = 5
			And a.doc_type= Any('{SP,SPG,PR}')
		) a	
	inner join st.material_type b on a.material_type_id = b.material_type_id
	inner join st.material c on a.material_id = c.material_id
	group by a.company_id, a.item_type, a.material_type_id, b.material_type, a.material_id, c.material_name, a.month_no, a.month_name;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_gst_purchase_tax_print(IN pstock_id character varying)
RETURNS TABLE
(	stock_id character varying,
	item_taxable_amt numeric,
        hsn_sc_code Character Varying,
 	gst_rate_id BigInt,
	sgst_pcnt numeric, 
 	sgst_amt numeric,
 	cgst_pcnt numeric, 
 	cgst_amt numeric,
 	igst_pcnt numeric, 
 	igst_amt numeric,
 	cess_pcnt numeric, 
 	cess_amt numeric,
	tax_amt numeric,
	item_amt numeric) 
AS
$BODY$
Begin
	Return Query
	With gst_tax_tran
	As
	(	Select a.stock_id, x.*
		From st.stock_control a, 
		    jsonb_to_recordset(a.annex_info->'gst_tax_tran') as x (
			sl_no BigInt, hsn_sc_code Varchar(8), apply_itc Boolean, gst_rate_id BigInt, bt_amt Numeric, tax_amt_ov Boolean,
			sgst_pcnt Numeric, sgst_amt Numeric, cgst_pcnt Numeric, cgst_amt Numeric,
			igst_pcnt Numeric, igst_amt Numeric, cess_pcnt Numeric, cess_amt Numeric)
		Where a.stock_id = pstock_id
	)
	Select a.stock_id, Sum(a.bt_amt), 
		a.hsn_sc_code, a.gst_rate_id, 
		min(a.sgst_pcnt), Sum(a.sgst_amt),
		min(a.cgst_pcnt), Sum(a.cgst_amt),
		min(a.igst_pcnt), Sum(a.igst_amt),
		min(a.cess_pcnt), Sum(a.cess_amt),
		Sum(a.sgst_amt+ a.cgst_amt + a.igst_amt + a.cess_amt),
		Sum(a.bt_amt + a.sgst_amt+ a.cgst_amt + a.igst_amt + a.cess_amt)
	From gst_tax_tran a
	Group by a.stock_id, a.hsn_sc_code, a.gst_rate_id;
End
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_gst_purchase_lc_tran_print(IN pstock_id character varying)
  RETURNS TABLE(stock_id character varying, stock_lc_tran_id character varying, 
	account_affected_id bigint, account_head_affected character varying, debit_amt numeric) AS
$BODY$
BEGIN	
	RETURN query
	SELECT a.stock_id, a.stock_lc_tran_id, a.account_affected_id, c.account_head AS account_head_affected,
		a.debit_amt
	FROM st.stock_lc_tran a 
	LEFT JOIN ac.account_head c ON a.account_affected_id = c.account_id
	WHERE a.stock_id = pstock_id;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_gst_purchase_tran_print(IN pstock_id varchar)
RETURNS TABLE(stock_id character varying, stock_tran_id character varying, 
	  sl_no bigint, material_type_id bigint, material_type Character Varying, material_id bigint, material_name character varying, 
	  stock_location_id bigint, stock_location_name character varying, uom_id bigint, uom_desc character varying, received_qty numeric, 
	  rate numeric, disc_amt numeric, bt_amt numeric, in_lc boolean
) AS
$BODY$
BEGIN	
	RETURN query
	SELECT a.stock_id, a.stock_tran_id, a.sl_no, a.material_type_id, b.material_type, a.material_id, c.material_name, 
	       a.stock_location_id, d.stock_location_name, a.uom_id, e.uom_desc,
	       a.received_qty, a.rate, a.disc_amt, a.bt_amt, a.in_lc
	FROM st.stock_tran a 
	Inner Join st.material_type b On a.material_type_id = b.material_type_id
	INNER JOIN st.material c ON a.material_id = c.material_id
	left JOIN st.stock_location d ON a.stock_location_id = d.stock_location_id
	INNER JOIN st.uom e ON a.uom_id = e.uom_id
	WHERE a.stock_id = pstock_id;
	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_gst_purchase_print(IN pstock_id varchar)
RETURNS TABLE
(
	stock_id character varying,
	company_id bigint,
	finyear varchar(4),
	branch_id bigint,
	doc_type varchar(20),
	doc_date date,
	bill_no character varying,
	bill_date date,
	bill_amt numeric(18,4),
	bill_receipt_date date,
	fc_type_id bigint,
	fc_type character varying,
	exch_rate numeric(18,6),
	items_amt_tot numeric(18,4),
	gross_amt numeric(18,4),
	disc_is_value boolean,
	disc_percent numeric(18,4),
	disc_amt numeric(18,4),
	misc_taxable_amt numeric(18,4),
	bt_amt numeric(18,4),
	tax_amt_tot numeric(18,4),
	round_off_amt numeric(18,4),
	misc_non_taxable_amt numeric(18,4),
	purchase_amt numeric(18,4),
	advance_amt numeric(18,4),
	net_amt numeric(18,4),
	status smallint,
	pay_term_id bigint,
	pay_term character varying,
	en_tax_type smallint,
	narration character varying,
	remarks character varying,
	amt_in_words character varying,
	purchase_account_id bigint,
	purchase_acc character varying,
	entered_by character varying, posted_by character varying,	
	supplier_id bigint, supp_name character varying, supp_state character varying, supp_gstin character varying, supp_addr character varying,
	vat_type character varying
) AS
$BODY$
BEGIN	
	RETURN query
	SELECT  a.stock_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, a.bill_no, a.bill_date,
		a.bill_amt, a.bill_receipt_date, a.fc_type_id, h.fc_type, a.exch_rate, 
		(a.annex_info->>'items_total_amt')::numeric, a.gross_amt,
		a.disc_is_value, a.disc_percent, a.disc_amt, a.misc_taxable_amt, a.before_tax_amt, 
		a.tax_amt, a.round_off_amt, a.misc_non_taxable_amt, a.total_amt, a.advance_amt, a.net_amt, 
		a.status, b.pay_term_id, c.pay_term, a.en_tax_type, a.narration, a.remarks, 
		a.amt_in_words, a.sale_account_id, e.account_head as purchase_acc,
		d.entered_by, d.posted_by, a.account_id, 
		b.supplier_name, (i.gst_state_code || ' - ' || i.state_name)::varchar as gst_state, (a.annex_info->'gst_input_info'->>'supplier_gstin')::varchar,
		(a.annex_info->'gst_input_info'->>'supplier_addr')::varchar, f.vat_type_desc
	FROM st.stock_control a 
	inner JOIN ap.supplier b ON a.account_id = b.supplier_id
	inner JOIN ac.account_head e ON a.sale_account_id = e.account_id
	left join ac.pay_term c on b.pay_term_id = c.pay_term_id
	INNER JOIN sys.doc_es d ON a.stock_id = d.voucher_id
	left join ac.fc_type h on a.fc_type_id = h.fc_type_id
	Inner Join tx.gst_state i On (a.annex_info->'gst_input_info'->>'supplier_state_id')::BigInt = i.gst_state_id
	inner join tx.vat_type f on a.vat_type_id = f.vat_type_id
	WHERE a.stock_id = pstock_id;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
Create or replace Function st.mat_wac_cv(pbranch_id BigInt, pfinyear Varchar(4), pmaterial_type_id BigInt, pmaterial_id BigInt)
Returns Table
(   stock_ledger_id uuid,
    doc_date Date,
    material_id BigInt,
    received_qty Numeric(18,3),
    issued_qty Numeric(18,3),
    unit_rate_lc Numeric(18,3),
    unit_rate_sl Numeric(18,3),
    wac_stddev Numeric(18,3),
    wac_cv Numeric(18,3)
)
As
$BODY$
Begin

    Drop Table If Exists sl_tran_final;
    Create Temp Table sl_tran_final
    (	stock_ledger_id uuid,
        doc_date Date,
        material_id BigInt,
        received_qty Numeric(18,3),
        issued_qty Numeric(18,3),
        unit_rate_lc Numeric(18,3),
        stock_movement_type_id bigint,
        inserted_on timestamp without time zone,
        is_opbl Boolean
    );

    Drop Table If Exists sl_tran_temp;
    Create Temp Table sl_tran_temp
    (	stock_ledger_id uuid,
        doc_date Date,
        material_id BigInt,
        received_qty Numeric(18,3),
        issued_qty Numeric(18,3),
        unit_rate_lc Numeric(18,3),
        stock_movement_type_id bigint,
        inserted_on timestamp without time zone,
        is_opbl Boolean
    );

	-- Create Cusrsor for distinct materials in selected mat type
    Declare cur_mat Cursor
    For Select b.material_id 
        From st.stock_ledger a
        Inner Join st.material b On a.material_id = b.material_id
        Where a.branch_id = pbranch_id
        	And a.finyear = pfinyear
            And b.material_type_id = pmaterial_type_id
            And (b.material_id = pmaterial_id Or pmaterial_id = 0)
        Group By b.material_id;
    Begin
        For mat in cur_mat Loop
			-- Process each distinct material
            Insert Into sl_tran_temp
            Select a.stock_ledger_id, a.doc_date, a.material_id, a.received_qty, a.issued_qty, a.unit_rate_lc, a.stock_movement_type_id, a.inserted_on, a.is_opbl
            From st.stock_ledger a
            Where a.branch_id = pbranch_id
                And a.material_id = mat.material_id
        	And a.finyear = pfinyear
            Order by a.doc_date, a.inserted_on;

            Declare cur_issue Cursor
            For Select * From sl_tran_temp a
                Where a.stock_movement_type_id != 1 And a.is_opbl = false
                    And a.issued_qty > 0
                Order By a.material_id;
            Declare v_wac Numeric(18,4) := 0;
            Begin
                For rec In cur_issue Loop
                    Select sys.fn_handle_zero_divide(Sum((a.received_qty-a.issued_qty)*a.unit_rate_lc), Sum((a.received_qty-a.issued_qty))) Into v_wac
                    From sl_tran_temp a
                    Where a.material_id = rec.material_id
                        And a.doc_date <= rec.doc_date
                        And Case When a.doc_date = rec.doc_date Then a.inserted_on < rec.inserted_on Else 1=1 End;

                    Update sl_tran_temp a
                    Set unit_rate_lc = v_wac
                    Where a.stock_ledger_id = rec.stock_ledger_id;
                End Loop;
            End;
			-- fill for final data
            Insert Into sl_tran_final
            Select * From sl_tran_temp;
            -- remove temp data
            Delete From sl_tran_temp;
            --raise notice 'material: %', mat.material_id;
        End Loop;
    End;

	Return Query
    Select a.stock_ledger_id, a.doc_date, a.material_id, a.received_qty, a.issued_qty, a.unit_rate_lc, b.unit_rate_lc,
    	stddev((a.issued_qty*a.unit_rate_lc) - (a.issued_qty*b.unit_rate_lc)) Over (Partition by a.material_id),
        sys.fn_handle_zero_divide((stddev((a.issued_qty*a.unit_rate_lc) - (a.issued_qty*b.unit_rate_lc)) Over (Partition by a.material_id)), a.unit_rate_lc)
    From sl_tran_final a
    Inner Join st.stock_ledger b On a.stock_ledger_id = b.stock_ledger_id;

End
$BODY$
language plpgsql;

?==?
Create OR REPLACE Function st.fn_sl_lot_bal(pbranch_id BigInt, pmat_id BigInt, psloc_id BigInt, pto_date Date, pvch_id Varchar(50))
Returns Table
(   sl_lot_id uuid, 
    test_insp_id character varying, 
    test_insp_date Date, 
    lot_no character varying, 
    bal_qty Numeric(18,3), 
    mfg_date Date, 
    exp_date Date,
    lot_state_id integer
)
As
$BODY$
Begin
    Return Query
    With sl_lot --Fetch txns for the material
    As
    (   Select a.sl_lot_id, a.test_insp_id, a.test_insp_date, (a.lot_no || case when (a.lot_no != '' And COALESCE((a.ref_info->>'desc')::varchar, '')::varchar != '') then '; ' else '' end || COALESCE((a.ref_info->>'desc')::varchar, ''))::varchar lot_no, a.lot_qty, 
            a.mfg_date, a.exp_date, a.best_before, a.lot_state_id
        From st.sl_lot a
        Inner Join st.stock_ledger b On a.sl_id = b.stock_ledger_id
        Where b.material_id = pmat_id
            And b.doc_date <= pto_date
            And b.branch_id = pbranch_id
            And b.stock_location_id = psloc_id
    ),
    sl_union -- Union with alloc as -ve without the selected vch
    As
    (   Select a.sl_lot_id, a.lot_qty
        From sl_lot a
        Union All
        Select a.sl_lot_id, -a.lot_issue_qty
        From st.sl_lot_alloc a
        Inner Join sl_lot b On a.sl_lot_id = b.sl_lot_id
        Where a.voucher_id != pvch_id
    ),
    sl_sum -- extract net qty
    As
    (	Select a.sl_lot_id, sum(a.lot_qty) as bal_qty
        From sl_union a
        Group By a.sl_lot_id
        Having sum(a.lot_qty) > 0
    )
    Select a.sl_lot_id, a.test_insp_id, a.test_insp_date, a.lot_no, b.bal_qty, 
            a.mfg_date, a.exp_date, a.lot_state_id
    From sl_lot a
    Inner Join sl_sum b On a.sl_lot_id = b.sl_lot_id;
End;
$BODY$
Language plpgsql;

?==?
Create OR REPLACE Function st.fn_sl_lot_bal_many(pbranch_id BigInt, pmat_tran json, pto_date Date, pvch_id Varchar(50))
Returns Table
(   sl_lot_id uuid, 
    test_insp_id character varying, 
    test_insp_date Date, 
    lot_no character varying, 
    bal_qty Numeric(18,3), 
    mfg_date Date, 
    exp_date Date,
    lot_state_id integer,
    material_id BigInt,
    stock_location_id BigInt
)
As
$BODY$
Begin
    Return Query
    With mat_tran
    As
    (   Select x.material_id, x.stock_location_id
        From json_to_recordset(pmat_tran) as x(material_id bigint, stock_location_id bigint)
    ),
    sl_lot --Fetch txns for the material
    As
    (   Select a.sl_lot_id, a.test_insp_id, a.test_insp_date, (a.lot_no || case when (a.lot_no != '' And COALESCE((a.ref_info->>'desc')::varchar, '')::varchar != '') then '; ' else '' end || COALESCE((a.ref_info->>'desc')::varchar, ''))::varchar lot_no, a.lot_qty, 
            a.mfg_date, a.exp_date, a.best_before, a.lot_state_id, c.material_id, c.stock_location_id
        From st.sl_lot a
        Inner Join st.stock_ledger b On a.sl_id = b.stock_ledger_id
        Inner Join mat_tran c On b.material_id = c.material_id And b.stock_location_id = c.stock_location_id
        Where b.doc_date <= pto_date
    ),
    sl_union -- Union with alloc as -ve without the selected vch
    As
    (   Select a.sl_lot_id, a.lot_qty
        From sl_lot a
        Union All
        Select a.sl_lot_id, -a.lot_issue_qty
        From st.sl_lot_alloc a
        Inner Join sl_lot b On a.sl_lot_id = b.sl_lot_id
        Where a.voucher_id != pvch_id
    ),
    sl_sum -- extract net qty
    As
    (	Select a.sl_lot_id, sum(a.lot_qty) as bal_qty
        From sl_union a
        Group By a.sl_lot_id
        Having sum(a.lot_qty) > 0
    )
    Select a.sl_lot_id, a.test_insp_id, a.test_insp_date, a.lot_no, b.bal_qty, 
            a.mfg_date, a.exp_date, a.lot_state_id, a.material_id, a.stock_location_id
    From sl_lot a
    Inner Join sl_sum b On a.sl_lot_id = b.sl_lot_id;
End;
$BODY$
Language plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_sl_lot_stmt_report(IN pcompany_id bigint, IN pbranch_id bigint, IN pmaterial_id bigint, IN pmat_type_id bigint, IN pstock_location_id bigint, 
                                                       IN pto_date date, IN pin_wf boolean)
RETURNS TABLE
(   sl_lot_id uuid,
    doc_date date, 
    vch_tran_id character varying, 
    test_insp_date date,
    test_insp_id character varying, 
    lot_no character varying, 
    material_id bigint, 
    material_name character varying,
    balance numeric(18,4),
    lot_qty numeric(18,4),
    lot_issue_qty numeric(18,4),
    mfg_date date,
    exp_date date,
    lot_state_id integer,
    uom_id bigint,
    uom_desc character varying,
    branch_id bigint,
    branch_name character varying,
    branch_code character varying,
    stock_location_id BigInt,
    stock_location_name Character Varying
) AS
$BODY$
Begin 
    DROP TABLE IF EXISTS sl_lot_bal_temp;
    CREATE temp TABLE  sl_lot_bal_temp
    (
            sl_lot_id uuid,
            doc_date date, 
            vch_tran_id character varying, 
            test_insp_date date,
            test_insp_id character varying, 
            lot_no character varying, 
            material_id bigint, 
            material_name character varying,
            balance numeric(18,4),
            lot_qty numeric(18,4),
            lot_issue_qty numeric(18,4),
            mfg_date date,
            exp_date date,
            lot_state_id integer,
            uom_id bigint,
            uom_desc character varying,
            branch_id bigint,
            branch_name character varying,
            branch_code character varying,
            stock_location_id BigInt,
            stock_location_name Character Varying,
            CONSTRAINT pk_sl_lot_bal_temp PRIMARY KEY (sl_lot_id)
    );
    with sl_lot
    As (
            Select a.sl_lot_id, sum(a.lot_qty) as balance, sum(a.lot_qty) as received_qty
            From st.sl_lot a 
            Inner Join st.stock_ledger b On a.sl_id = b.stock_ledger_id
            Inner join st.material c on b.material_id = c.material_id
            Where b.doc_date <= pto_date
                And (b.material_id = pmaterial_id or  pmaterial_id = 0)
                And (c.material_type_id = pmat_type_id or  pmat_type_id = 0)
                And (b.stock_location_id = pstock_location_id or  pstock_location_id = 0)  
                And (b.branch_id In (Select x.branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id) x) or pbranch_id = 0)	
            Group By a.sl_lot_id  
    ),
    sl_lot_alloc
    As ( 
            Select a.sl_lot_id, -sum(a.lot_issue_qty) as settled, sum(a.lot_issue_qty) as issued_qty
            From st.sl_lot_alloc a 
            inner join sl_lot b on a.sl_lot_id = b.sl_lot_id
            Where a.vch_date <= pto_date 
                And (case when pin_wf = false then a.status=5 Else 1=1 End)
            Group By a.sl_lot_id
    ),
    sl_union
    As (
        Select a.sl_lot_id, sum(a.balance) as balance, sum(a.received_qty) as received_qty, sum(a.issued_qty) as issued_qty
        From ( 
            Select a.sl_lot_id, a.balance, a.received_qty, 0 as issued_qty
            From sl_lot a 
            Union All -- Alloc entries
            Select a.sl_lot_id, a.settled, 0 as received_qty, a.issued_qty
            From sl_lot_alloc a 
        ) a
        Group BY a.sl_lot_id
    )    
    Insert into sl_lot_bal_temp(sl_lot_id, doc_date, vch_tran_id, test_insp_date, test_insp_id, lot_no, material_id, material_name, balance, 
                                lot_qty, lot_issue_qty, mfg_date, exp_date, lot_state_id, uom_id, uom_desc, branch_id, branch_name, branch_code,
							   	stock_location_id, stock_location_name)    
    Select a.sl_lot_id, c.doc_date, c.vch_tran_id, a.test_insp_date, a.test_insp_id, a.lot_no, c.material_id, d.material_name, b.balance, 
    		b.received_qty, b.issued_qty, a.mfg_date, a.exp_date, a.lot_state_id, c.uom_id, e.uom_desc, c.branch_id, f.branch_name, f.branch_code,
			sl.stock_location_id, sl.stock_location_name
    From st.sl_lot a
    Inner join sl_union b On a.sl_lot_id = b.sl_lot_id  
    Inner Join st.stock_ledger c On a.sl_id = c.stock_ledger_id
    inner join st.material d on c.material_id = d.material_id
    inner join st.uom e on d.material_id = e.material_id And is_base
    Inner join sys.branch f on c.branch_id = f.branch_id
	Inner Join st.stock_location sl On c.stock_location_id = sl.stock_location_id
    Where b.balance <> 0;
        
    return query
    Select a.sl_lot_id, a.doc_date, a.vch_tran_id, a.test_insp_date, case when left(a.test_insp_id, 4) = 'OPBL' Then 'OPBL' Else a.test_insp_id End, 
    		case when left(a.lot_no, 4) = 'OPBL' Then 'OPBL' Else a.lot_no End, a.material_id, a.material_name, a.balance, a.lot_qty, a.lot_issue_qty,
    		a.mfg_date, a.exp_date, a.lot_state_id, a.uom_id, a.uom_desc, a.branch_id, a.branch_name, a.branch_code, a.stock_location_id, a.stock_location_name
    From sl_lot_bal_temp a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_sl_lot_stmt_report_detailed(IN pcompany_id bigint, IN pbranch_id bigint, IN pmaterial_id bigint, IN pmat_type_id bigint, IN pstock_location_id bigint, IN pto_date date, pin_wf boolean = false)
RETURNS TABLE
(
    sl_lot_id uuid, 
    doc_date date, 
    vch_tran_id character varying, 
    test_insp_date date,
    test_insp_id character varying, 
    material_id bigint,
    material_name character varying,
    lot_qty numeric, 
    lot_no character varying,
    mfg_date date,
    exp_date date,
    lot_state_id integer,
    uom_id bigint,
    uom_desc character varying,
    settled_id character varying,
    settled_date date, 
    lot_issue_qty numeric,
    status int,
    branch_id bigint,
    branch_name character varying,
    branch_code Character Varying,
    stock_location_id BigInt,
    stock_location_name Character Varying
) AS
$BODY$
Begin 

    return query 
    With sl_lot
    As
    (	SELECT a.sl_lot_id, b.doc_date, a.test_insp_date, a.test_insp_id, b.material_id, c.material_name, a.lot_qty, a.lot_no, 
                a.mfg_date, a.exp_date, a.lot_state_id, b.uom_id, d.uom_desc, b.vch_tran_id, b.branch_id, e.branch_name, e.branch_code,
                sl.stock_location_id, sl.stock_location_name
        FROM st.sl_lot a 
        Inner Join st.stock_ledger b On a.sl_id = b.stock_ledger_id
        inner join st.material c on b.material_id = c.material_id
        inner join st.uom d on c.material_id = d.material_id And d.is_base
    	Inner join sys.branch e on b.branch_id = e.branch_id
	 	Inner Join st.stock_location sl On b.stock_location_id = sl.stock_location_id
        Where b.doc_date <= pto_date
            And (b.material_id = pmaterial_id or  pmaterial_id = 0) 
            And (c.material_type_id = pmat_type_id or pmat_type_id = 0) 
            And (b.stock_location_id = pstock_location_id or  pstock_location_id = 0)
            And (b.branch_id In (Select x.branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id) x) or pbranch_id = 0)
    ),
    lot_alloc
    As
    (   Select a.*
        From st.sl_lot_alloc a
        Inner Join sl_lot b On a.sl_lot_id = b.sl_lot_id
        Where Case When pin_wf Then 1=1 Else a.status = 5 End
    )
    SELECT a.sl_lot_id, a.doc_date, a.vch_tran_id, a.test_insp_date, case when left(a.test_insp_id, 4) = 'OPBL' Then 'OPBL' Else a.test_insp_id End, a.material_id, a.material_name, 
    		a.lot_qty, case when left(a.lot_no, 4) = 'OPBL' Then 'OPBL' Else a.lot_no End, 
    		a.mfg_date, a.exp_date, a.lot_state_id, a.uom_id, a.uom_desc, 
            b.voucher_id, b.vch_date, b.lot_issue_qty, b.status, a.branch_id, a.branch_name, a.branch_code,
            a.stock_location_id, a.stock_location_name
    FROM sl_lot a
    Left join lot_alloc b on a.sl_lot_id = b.sl_lot_id And b.vch_date <= pto_date;
END;
$BODY$
LANGUAGE plpgsql;

?==?         
CREATE OR REPLACE FUNCTION st.fn_sl_lot_stmt_report_txn(IN pcompany_id bigint, IN pbranch_id bigint, IN pmaterial_id bigint, IN pmat_type_id bigint, IN pstock_location_id bigint, IN pfrom_date date, IN pto_date date, pin_wf boolean)
RETURNS TABLE
(
    sl_lot_id uuid, 
    doc_date date, 
    vch_tran_id character varying, 
    test_insp_date date,
    test_insp_id character varying, 
    lot_no character varying,
    material_id bigint,
    material_name character varying,
    balance numeric, 
    lot_qty numeric, 
    lot_issue_qty numeric,
    mfg_date date,
    exp_date date,
    lot_state_id integer,
    uom_id bigint,
    uom_desc character varying,
    settled_id character varying,
    settled_date date, 
    branch_id bigint,
    branch_name character varying
) AS
$BODY$
Begin 

    return query 
    With sl_lot
    As
    (	SELECT a.sl_lot_id, b.doc_date, a.test_insp_date, a.test_insp_id, b.material_id, c.material_name, a.lot_qty, a.lot_no, 
                a.mfg_date, a.exp_date, a.lot_state_id, b.uom_id, d.uom_desc, b.vch_tran_id, b.branch_id, e.branch_name
        FROM st.sl_lot a 
        Inner Join st.stock_ledger b On a.sl_id = b.stock_ledger_id
        inner join st.material c on b.material_id = c.material_id
        inner join st.uom d on c.material_id = d.material_id And d.is_base
        Inner join sys.branch e on b.branch_id = e.branch_id
        Where b.doc_date between pfrom_date And pto_date
            And (b.material_id = pmaterial_id or  pmaterial_id = 0) 
            And (c.material_type_id = pmat_type_id or pmat_type_id = 0) 
            And (b.stock_location_id = pstock_location_id or  pstock_location_id = 0)
            And (b.branch_id In (Select x.branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id) x) or pbranch_id = 0)
    )
    SELECT md5(a.material_id::varchar)::uuid sl_lot_id, (pfrom_date::date - INTERVAL '1 DAY')::date doc_date, '' vch_tran_id, 
            (pfrom_date::date - INTERVAL '1 DAY')::date, 'OPBL', 'OPBL', a.material_id, a.material_name, 
            sum(a.balance), sum(a.lot_qty), sum(a.lot_issue_qty), 
            min(a.mfg_date), max(a.exp_date), a.lot_state_id, a.uom_id, a.uom_desc, 
            '' settled_id, null settled_date, a.branch_id, a.branch_name
    FROM st.fn_sl_lot_stmt_report
    (
        pcompany_id, 
        pbranch_id, 
        pmaterial_id, 
        pmat_type_id,
        pstock_location_id,
        (pfrom_date::date - INTERVAL '1 DAY')::date,
        pin_wf::boolean
    ) a
    Group by a.material_id, a.material_name, a.branch_id, a.branch_name, a.lot_state_id, a.uom_id, a.uom_desc
    Union All
    (SELECT a.sl_lot_id, a.doc_date, a.vch_tran_id, a.test_insp_date, case when left(a.test_insp_id, 4) = 'OPBL' Then 'OPBL' Else a.test_insp_id End, 
            case when left(a.lot_no, 4) = 'OPBL' Then 'OPBL' Else a.lot_no End, a.material_id, a.material_name, 
            0, a.lot_qty, b.lot_issue_qty, a.mfg_date, a.exp_date, a.lot_state_id, a.uom_id, a.uom_desc, 
            b.voucher_id, b.vch_date, a.branch_id, a.branch_name
    FROM sl_lot a
    Left join st.sl_lot_alloc b on a.sl_lot_id = b.sl_lot_id And b.vch_date between pfrom_date And pto_date
    Where (case when pin_wf then 1=1 Else (b.status = 5 or b.status is null) End)
    ORDER BY a.material_name, a.branch_name, a.doc_date, a.sl_lot_id, b.vch_date, b.voucher_id);
END;
$BODY$
LANGUAGE plpgsql;   

?==?
CREATE OR REPLACE FUNCTION st.fn_sales_return_list(
	pcompany_id bigint,
	pbranch_id bigint,
	pmat_type_id bigint,
	pmat_id bigint,
        pcustomer_id bigint,
	psales_return_type bigint,
	pfrom_date date,
	pto_date date,
        psrr_id bigint)
    RETURNS TABLE(sales_return_id character varying, doc_date date, branch_id bigint, branch_name character varying,
                  customer_id bigint, customer character varying, sales_return_tran_id character varying,
                  invoice_tran_id character varying, sales_return_type character varying, mat_type_id bigint, 
                  mat_type character varying, material_id bigint, material_name character varying, 
                  uom_id bigint, uom_desc character varying, received_qty numeric, rate numeric, 
                  item_amt numeric, gross_amt numeric, srr_id bigint, srr_desc character varying) 
AS 
$BODY$
Begin
    Return Query
    Select x.* from ( 
    Select a.stock_id as sales_return_id, a.doc_date, a.branch_id, g.branch_name,
           a.account_id, f.customer, b.stock_tran_id, b.reference_tran_id, 
           Case when (a.annex_info->>'dcn_type')::bigint = 0 then 'Sales Return'
                when (a.annex_info->>'dcn_type')::bigint = 1 then 'Rate Adjustment'
        		when (a.annex_info->>'dcn_type')::bigint = 2  then 'Post Sale Discount'
        		when (a.annex_info->>'dcn_type')::bigint = 3 then 'Damaged Delivery'
           End :: varchar as sales_return_type,
           d.material_type_id, d.material_type, c.material_id, c.material_name, 
           e.uom_id, e.uom_desc, b.received_qty, b.rate, b.item_amt, a.gross_amt,
           coalesce((a.annex_info->>'srr_id')::bigint,-1) as srr_id, coalesce(h.srr_desc,'') as srr_desc 
    From st.stock_control a
    Inner Join st.stock_tran b On a.stock_id = b.stock_id      
    Inner Join st.material c On b.material_id = c.material_id        
    Inner Join st.material_type d On c.material_type_id = d.material_type_id  
    Inner Join st.uom e On c.material_id = e.material_id And e.is_base
    left join ar.customer f on a.account_id=f.customer_id
    inner join sys.branch g on a.branch_id=g.branch_id
    left join st.srr h on coalesce((a.annex_info->>'srr_id')::int,-1) = h.srr_id    
    Where a.doc_type in ('SRV') And a.status = 5
    	And (a.company_id) = pcompany_id
        And (a.branch_id = pbranch_id Or pbranch_id = 0)
        And (d.material_type_id = pmat_type_id Or pmat_type_id = 0)
        And (b.material_id = pmat_id Or pmat_id = -2)
        And (a.account_id = pcustomer_id or pcustomer_id=0)
        And ((a.annex_info->>'dcn_type')::bigint= psales_return_type or psales_return_type = -1)
        And a.doc_date Between pfrom_date And pto_date
        And (coalesce((a.annex_info->>'srr_id')::int,-1) =psrr_id or psrr_id = 0)
     ) x;
End;
$BODY$
 LANGUAGE 'plpgsql';

?==?
Create or replace Function st.mat_wac_rerun(pbranch_id BigInt, pfinyear Varchar(4), pmaterial_id BigInt, pfrom_date Date, pto_date Date)
Returns Table
(   stock_ledger_id uuid,
    unit_rate_lc Numeric(18,3)
)
As
$BODY$
Begin

    Drop Table If Exists sl_tran_temp;
    Create Temp Table sl_tran_temp
    (	stock_ledger_id uuid,
        voucher_id Varchar(50),
        vch_tran_id Varchar(50),
        branch_id BigInt,
        doc_date Date,
        material_id BigInt,
        received_qty Numeric(18,3),
        issued_qty Numeric(18,3),
        unit_rate_lc Numeric(18,3),
        stock_movement_type_id bigint,
        inserted_on timestamp without time zone,
        is_opbl Boolean
    );

	-- Process each distinct material
    Insert Into sl_tran_temp
    Select a.stock_ledger_id, a.voucher_id, a.vch_tran_id, a.branch_id, a.doc_date, a.material_id, a.received_qty, a.issued_qty, 
        a.unit_rate_lc, a.stock_movement_type_id, a.inserted_on, a.is_opbl
    From st.stock_ledger a
    Where (a.branch_id = pbranch_id Or pbranch_id = 0)
        And a.material_id = pmaterial_id
        And a.finyear = pfinyear
    Order by a.doc_date, a.inserted_on;

    Declare cur_sl_tran Cursor
    For Select * From sl_tran_temp a
        Where a.doc_date Between pfrom_date And pto_date;
    
        Declare v_wac Numeric(18,4) := 0; v_bal Numeric(18,3) := 0;
        Begin
            For rec In cur_sl_tran Loop
                If rec.stock_movement_type_id = 1 Then -- Stock Purchase
                    Update sl_tran_temp a
                    Set unit_rate_lc = b.unit_rate_lc
                    From st.sp_sl_post_mat_lc_data(rec.voucher_id) b
                    Where a.stock_ledger_id = b.stock_ledger_id;
                ElseIf rec.stock_movement_type_id = 9 Then -- Stock Transfer Receipt
                    Update sl_tran_temp a
                    Set unit_rate_lc = b.unit_rate_lc
                    From sl_tran_temp b
                    Where a.vch_tran_id = b.vch_tran_id || ':AJ'
                        And a.stock_ledger_id = rec.stock_ledger_id;
                ElseIf rec.stock_movement_type_id in (10, 11) Then -- 10) Sales Return  11) Purchase Return
                    With ref_tran
                    As
                    (   Select a.reference_tran_id
                        From st.stock_tran a
                        Where a.stock_tran_id = rec.vch_tran_id limit 1
                    ),
                    sl_tran
                    As
                    (   Select a.vch_tran_id, a.unit_rate_lc
                        From sl_tran_temp a
                        Inner Join ref_tran b On a.vch_tran_id = b.reference_tran_id limit 1
                    )
                    Update sl_tran_temp a
                    Set unit_rate_lc = b.unit_rate_lc
                    From sl_tran b 
                    Where a.stock_ledger_id = rec.stock_ledger_id;
                ElseIf rec.stock_movement_type_id = 104 Then -- Production Output
                    Update sl_tran_temp a
                    Set unit_rate_lc = Case When b.unit_rate_lc > 0 Then b.unit_rate_lc Else 0 End
                    From prod.sp_batch_mat_lc(rec.voucher_id) b
                    Where a.stock_ledger_id = b.stock_ledger_id
                        And a.material_id != 1001539; -- Exclude cream as rate has changed on (01 Jul, 2018)
                ElseIf rec.stock_movement_type_id = 105 Then -- Production Recovery (always at std. cost)
                    Update sl_tran_temp a
                    Set unit_rate_lc = b.std_rate
                    From prod.std_rate b
                    Where a.material_id = b.material_id
                        And a.stock_ledger_id = rec.stock_ledger_id;
                ElseIf rec.stock_movement_type_id = 4 Then -- SAN
                    Update sl_tran_temp a
                    Set unit_rate_lc = b.unit_rate_lc
                    From st.sp_san_mat_lc(rec.voucher_id, rec.vch_tran_id) b
                    Where a.stock_ledger_id = rec.stock_ledger_id;
                ElseIf rec.stock_movement_type_id Not In (-1) Then
                    Select sys.fn_handle_zero_divide(Sum((a.received_qty-a.issued_qty)*a.unit_rate_lc), Sum((a.received_qty-a.issued_qty))),
                        Sum(a.received_qty-a.issued_qty)
                            Into v_wac, v_bal
                    From sl_tran_temp a
                    Where a.material_id = rec.material_id
                        And a.branch_id = rec.branch_id
                        And a.doc_date <= rec.doc_date
                        And Case When a.doc_date = rec.doc_date Then a.inserted_on < rec.inserted_on Else 1=1 End;
                        
                    If v_bal <= 0 Or v_wac < 0  Then -- If balance is negative, fetch avg rate of inwards till date
                        Select sys.fn_handle_zero_divide(Sum(a.received_qty*a.unit_rate_lc), Sum(a.received_qty))
                                Into v_wac
                        From sl_tran_temp a
                        Where a.material_id = rec.material_id
                            And a.branch_id = rec.branch_id
                            And a.doc_date <= rec.doc_date
                            And Case When a.doc_date = rec.doc_date And a.stock_movement_type_id Not In (1,9) Then a.inserted_on < rec.inserted_on Else 1=1 End
                            And a.stock_movement_type_id IN (1, -1, 9, 104); -- Include opstock + purchases to determine rate;
                    End If;
                    
                    -- update wac
                    Update sl_tran_temp a
                    Set unit_rate_lc = v_wac
                    Where a.stock_ledger_id = rec.stock_ledger_id;
                    
                    v_wac:= 0.00;
                End If;
            End Loop;
        End;

    Return Query
    Select a.stock_ledger_id, a.unit_rate_lc
    From sl_tran_temp a;
End
$BODY$
language plpgsql;

-- Do language plpgsql
-- $BODY$
-- Begin
--     Declare cur_branch cursor
--     For Select branch_id from sys.branch;

--     Begin
--         For rec in cur_branch loop
--             Declare cur_mt cursor
--             For Select material_type_id from st.material_type;
--             Begin
--                 For mt in cur_mt loop
--                     Update st.stock_ledger a
--                         Set unit_rate_lc = coalesce(b.unit_rate_lc, 0.00)
--                     From st.mat_wac_cv(rec.branch_id, '1819', mt.material_type_id, 0) b
--                     Where a.stock_ledger_id = b.stock_ledger_id;    
--                 End loop;
--             End;
--         End Loop;
--     End;
-- End
-- $BODY$;

/*﻿Select a.*, b.unit_rate_lc as unit_rate_sl, b.voucher_id, b.vch_tran_id, b.doc_date, b.received_qty, b.issued_qty
From st.mat_wac_rerun(1000002, '1819', 1001530, '2018-04-01', '2018-04-30') a
Inner Join st.stock_ledger b On a.stock_ledger_id = b.stock_ledger_id
Order by b.branch_id, b.doc_date, b.inserted_on */


?==?
CREATE OR REPLACE FUNCTION st.fn_stock_transfer_list(
	pcompany_id bigint,
	psourcebranch_id bigint,
	ptarget_branch_id bigint,
	pfrom_date date,
	pto_date date,
	pmaterial_type_id bigint,
	pmaterial_id bigint)
    RETURNS TABLE(stock_id character varying, branch_id bigint, branch_name character varying,
                  target_branch_id bigint, target_branch_name character varying, doc_date date, 
                  transfered_date date, transfer_amt numeric, status smallint, entered_by character varying, 
                  posted_by character varying, transfered_by character varying, material_type_id bigint,
                  material_type character varying, material_id bigint, material_name character varying,
                  uom_id bigint, uom_desc character varying, issued_qty numeric, rate numeric, item_amt numeric) 
AS $BODY$
BEGIN
	DROP TABLE IF EXISTS stock_transfer_temp;	
	create temp table stock_transfer_temp
	(
            stock_id varchar(50),
            branch_id bigint,
            branch_name varchar(250),
            doc_date date,
            transfered_date date,
            transfer_amt numeric(18,4),
            status smallint,
            target_branch_id bigint,
            target_branch_name varchar(100),
            entered_by varchar(100), 
            posted_by varchar(100),
            transfered_by varchar(100),
            material_type_id bigint,
            material_type varchar(100),
            material_id bigint,
            material_name varchar(250),
            uom_id bigint,
            uom_desc varchar(100),
            issued_qty numeric(18,3),
            rate numeric(18,3),
            item_amt numeric(18,3)
	);

	Insert Into stock_transfer_temp(stock_id, branch_id, branch_name, target_branch_id, target_branch_name, 
        doc_date, transfered_date, transfer_amt, status,  entered_by, posted_by, transfered_by,
        material_type_id, material_type, material_id, material_name, uom_id, uom_desc, issued_qty, rate, item_amt)
	Select a.stock_id, a.branch_id, h.branch_name, a.target_branch_id, i.branch_name AS target_branch_name,  
        a.doc_date, c.doc_date as transfered_date, a.total_amt as transfer_amt, a.status, g.entered_by, g.posted_by,
        c.authorised_by as transfered_by, b.material_type_id, d.material_type, b.material_id, e.material_name, 
        b.uom_id, f.uom_desc, b.issued_qty, b.rate, b.item_amt        
	From st.stock_control a 
        Inner Join st.stock_tran b on a.stock_id=b.stock_id
        Inner Join st.stock_transfer_park_post c on a.stock_id=c.stock_id
        Inner Join st.material_type d on b.material_type_id=d.material_type_id
        Inner Join st.material e on b.material_id=e.material_id
        Inner Join st.uom f on b.uom_id=f.uom_id    
	Inner Join sys.doc_es g ON a.stock_id = g.voucher_id
        Inner Join sys.branch h ON a.branch_id = h.branch_id
	Inner Join sys.branch i ON a.target_branch_id = i.branch_id
	Where a.company_id = pcompany_id 
          And (a.branch_id in (select j.branch_id from sys.fn_get_cbr_group(pcompany_id,psourcebranch_id) j) or psourcebranch_id = 0)
          And (a.target_branch_id = ptarget_branch_id or ptarget_branch_id = 0)
          And a.doc_date between pfrom_date and pto_date
          And (b.material_type_id = pmaterial_type_id or pmaterial_type_id = 0)
          And (b.material_id = pmaterial_id or pmaterial_id = -2)
          And a.status = 5 ;
    
	Return query
	Select  a.stock_id, a.branch_id, a.branch_name, a.target_branch_id, a.target_branch_name, 
     	a.doc_date, a.transfered_date, a.transfer_amt, a.status,  a.entered_by, 
        a.posted_by, a.transfered_by, a.material_type_id, a.material_type, a.material_id, 
        a.material_name, a.uom_id, a.uom_desc, a.issued_qty, a.rate, a.item_amt 
    FROM stock_transfer_temp a;
    
END; 
$BODY$
    LANGUAGE 'plpgsql';
        
?==?
CREATE OR REPLACE FUNCTION st.fn_stock_in_transit(
	pcompany_id bigint,
	pbranch_id bigint,
	ptarget_branch_id bigint,
	ptransit_date date,
	pmaterial_type_id bigint,
	pmaterial_id bigint)
    RETURNS TABLE(stock_id character varying, branch_id bigint, branch_name character varying,
                  target_branch_id bigint, target_branch_name character varying, doc_date date, 
                  transfered_date date, transfer_amt numeric, status smallint, entered_by character varying, 
                  posted_by character varying, transfered_by character varying, material_type_id bigint, 
                  material_type character varying, material_id bigint, material_name character varying,
                  uom_id bigint, uom_desc character varying, issued_qty numeric, rate numeric, item_amt numeric) 
AS $BODY$

BEGIN
	DROP TABLE IF EXISTS stock_in_transit_temp;	
	create temp table stock_in_transit_temp
	(
            stock_id varchar(50),
            branch_id bigint,
            branch_name varchar(250),
            doc_date date,
            transfered_date date,
            transfer_amt numeric(18,4),
            status smallint,
            target_branch_id bigint,
            target_branch_name varchar(100),
            entered_by varchar(100), 
            posted_by varchar(100),
            transfered_by varchar(100),
            material_type_id bigint,
            material_type varchar(100),
            material_id bigint,
            material_name varchar(250),
            uom_id bigint,
            uom_desc varchar(100),
            issued_qty numeric(18,3),
            rate numeric(18,4),
            item_amt numeric(18,4)
	);

	Insert Into stock_in_transit_temp(stock_id, branch_id, branch_name, target_branch_id, target_branch_name, 
        doc_date, transfered_date,	transfer_amt, status,  entered_by, posted_by, transfered_by,
        material_type_id, material_type, material_id, material_name, uom_id, uom_desc, issued_qty, rate, item_amt)
	Select a.stock_id, a.branch_id, h.branch_name, a.target_branch_id, i.branch_name AS target_branch_name,  
        a.doc_date, c.doc_date as transfered_date, a.total_amt as transfer_amt, a.status, g.entered_by, g.posted_by,
        c.authorised_by as transfered_by, b.material_type_id, d.material_type, b.material_id, e.material_name, 
        b.uom_id, f.uom_desc, b.issued_qty, b.rate, b.item_amt        
	From st.stock_control a 
        Inner Join st.stock_tran b on a.stock_id=b.stock_id
        Inner Join st.stock_transfer_park_post c on a.stock_id=c.stock_id
        Inner Join st.material_type d on b.material_type_id=d.material_type_id
        Inner Join st.material e on b.material_id=e.material_id
        Inner Join st.uom f on b.uom_id=f.uom_id    
	Inner Join sys.doc_es g ON a.stock_id = g.voucher_id
        Inner Join  sys.branch h ON a.branch_id = h.branch_id
	Inner Join  sys.branch i ON a.target_branch_id = i.branch_id
	Where a.company_id = pcompany_id 
          And (a.branch_id in (select j.branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id) j) or pbranch_id = 0)
          And (a.target_branch_id = ptarget_branch_id or ptarget_branch_id = 0)
          And ((a.doc_date <= ptransit_date and c.doc_date > ptransit_date) or c.doc_date is null)
          And (b.material_type_id = pmaterial_type_id or pmaterial_type_id=0)
          And (b.material_id = pmaterial_id or pmaterial_id = -2)
          And a.status = 5 ;
    
	Return query
	Select  a.stock_id, a.branch_id, a.branch_name, a.target_branch_id, a.target_branch_name, 
     	a.doc_date, a.transfered_date, a.transfer_amt,a.status,  a.entered_by, 
        a.posted_by, a.transfered_by, a.material_type_id, a.material_type, a.material_id, 
        a.material_name, a.uom_id, a.uom_desc, a.issued_qty, a.rate, a.item_amt 
        FROM stock_in_transit_temp a;
END; 
$BODY$
  LANGUAGE 'plpgsql';

?==?
Drop FUNCTION If Exists st.fn_stock_consump_by_type(IN pcompany_id bigint, IN pbranch_id bigint, IN pmaterial_id bigint, IN pmaterial_type_id bigint,
                                                          IN pfinyear character varying, IN pfrom_date date, IN pto_date date, IN pstock_movement_type_id bigint);
?==?
CREATE OR REPLACE FUNCTION st.fn_stock_consump_by_type(IN pcompany_id bigint, IN pbranch_ids bigint[], IN pmaterial_id bigint, IN pmaterial_type_id bigint,
                                                          IN pfinyear character varying, IN pfrom_date date, IN pto_date date, IN pstock_movement_type_ids bigint[])
RETURNS TABLE(voucher_id character varying, doc_date date, branch_id bigint, stock_movement_type_id bigint, stock_movement_type character varying, 
                material_id bigint, material_name character varying, 
                material_type_id bigint, material_type character varying, mat_qty numeric, mat_value numeric, uom_desc character varying,
                cons_type_id bigint, cons_type_desc character varying) AS
$BODY$
Begin

	DROP TABLE IF EXISTS sm_detail;	
	create temp TABLE  sm_detail
	(	
		voucher_id character varying, doc_date date, branch_id bigint, stock_movement_type_id bigint, stock_movement_type character varying, 
        material_id bigint, material_name character varying, 
        material_type_id bigint, material_type character varying, mat_qty numeric, mat_value numeric, uom_desc character varying,
        cons_type_id bigint, cons_type_desc character varying
	);
	Insert into sm_detail(voucher_id, doc_date, branch_id, stock_movement_type_id, stock_movement_type, material_id, material_name, material_type_id, material_type, 
                          mat_qty, mat_value, uom_desc, cons_type_id, cons_type_desc)
	Select a.voucher_id, a.doc_date, a.branch_id, a.stock_movement_type_id, b.stock_movement_type, a.material_id, c.material_name, d.material_type_id, d.material_type,
		case when a.stock_movement_type_id = 7 then -1 * a.received_qty Else a.issued_qty End, 
        (Case when a.stock_movement_type_id = 7 then -1 * a.received_qty Else a.issued_qty End) * a.unit_rate_lc, e.uom_desc, -1, ''
	From st.stock_ledger a
	Inner Join st.stock_movement_type b On a.stock_movement_type_id=b.stock_movement_type_id
        Inner Join st.material c on a.material_id = c.material_id
        Inner Join st.material_type d on c.material_type_id = d.material_type_id
        inner join st.uom e on a.material_id = e.material_id and e.is_base = true
	Where a.finYear = pfinyear 
		And a.doc_date Between pfrom_date And pto_date
		And a.company_id = pcompany_id 
		And (a.branch_id = Any(pbranch_ids) or 0 = Any(pbranch_ids))
		And (a.material_id = pmaterial_id or pmaterial_id = -2)
		And (c.material_type_id = pmaterial_type_id or pmaterial_type_id = 0)
		And (a.stock_movement_type_id = Any(pstock_movement_type_ids) or 0 = Any(pstock_movement_type_ids))
        And (b.stock_movement_type_group = 'I' Or a.stock_movement_type_id = 7)
        And Left(a.voucher_id, 3) != 'MIS'
        And (case when a.stock_movement_type_id = 7 then a.received_qty != 0 Else a.issued_qty != 0 End);
    
    Update sm_detail a
    set cons_type_id = COALESCE((b.annex_info->>'cons_type_id')::bigint, -1),
    	cons_type_desc = c.cons_type_desc
    From st.stock_control b
    inner join st.cons_type c on COALESCE((b.annex_info->>'cons_type_id')::bigint, -1) = c.cons_type_id
    where a.voucher_id = b.stock_id;
    
    Update sm_detail a
    set cons_type_id = COALESCE((b.annex_info->>'cons_type_id')::bigint, -1),
    	cons_type_desc = c.cons_type_desc
    From prod.doc_control b
    inner join st.cons_type c on COALESCE((b.annex_info->>'cons_type_id')::bigint, -1) = c.cons_type_id
    where a.voucher_id = b.voucher_id;
    
    Return Query
    Select a.voucher_id, a.doc_date, a.branch_id, a.stock_movement_type_id, a.stock_movement_type, a.material_id, a.material_name, a.material_type_id, a.material_type, 
            a.mat_qty, a.mat_value, a.uom_desc, a.cons_type_id, a.cons_type_desc
    From sm_detail a;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_stock_move_type_report(
	pcompany_id bigint,
	pbranch_id bigint,
	pfinyear character varying,
	pfrom_date date,
	pto_date date,
	pmaterial_type_id bigint,
	pmaterial_id bigint,
	pstock_movement_type_id bigint,
	psl_id bigint)
    RETURNS TABLE(branch_id bigint, branch_name character varying, voucher_id character varying, 
                  doc_date date, material_id bigint, material_name character varying,
                  material_type_id bigint, material_type character varying, 
                  stock_movement_type_id bigint, stock_movement_type character varying,
                  uom_desc character varying, issued_qty numeric, received_qty numeric, 
                  unit_rate_lc numeric, amount numeric)

AS $BODY$
Declare vBeforeFromDate date; vyear_begin_date date;
Begin	

   vBeforeFromDate = pfrom_date;
   
   --Commented as opening balance not required
   --
   --Select year_begin into vyear_begin_date from sys.finyear where finyear_code=pfinyear;
   --
   ---- Check if fromdate is finyear starting date then get the opening balances by resolving one day before
   --If  (vyear_begin_date = pfrom_date)  Then
   --   vBeforeFromDate := pfrom_date - '1 day'::interval ; -- ****	Resolve Date one day before from date
   --End If;
   
	-- Temp table 
	DROP TABLE IF EXISTS stock_movement_data;	
	create temp TABLE  stock_movement_data
	(	
        branch_id bigint, 
        branch_name varchar(250),
        voucher_id varchar(50), 
        doc_date date, 
        material_id bigint, 
        material_name varchar(250), 
        material_type_id bigint, 
        material_type varchar(50),
        stock_movement_type_id bigint,
        stock_movement_type varchar(50),
        uom_desc varchar(50),
        issued_qty  numeric(18,4),
        received_qty numeric(18,4),
        unit_rate_lc  numeric(18,4),
        amount  numeric(18,4)
	);
    
    Insert into stock_movement_data
    (branch_id, branch_name, voucher_id, doc_date, 
     material_id, material_name, material_type_id, 
     material_type, stock_movement_type_id, stock_movement_type,
     uom_desc, issued_qty, received_qty,
     unit_rate_lc, amount)
    Select a.branch_id, b.branch_name, a.voucher_id, a.doc_date,
    a.material_id, c.material_name, c.material_type_id, d.material_type,
    a.stock_movement_type_id, f.stock_movement_type,
    e.uom_desc, a.issued_qty, a.received_qty, a.unit_rate_lc,
    case when a.issued_qty > 0 then ((a.issued_qty * a.unit_rate_lc) * -1) else (a.received_qty * a.unit_rate_lc) end as amount
	From st.stock_ledger a
    Inner Join sys.branch b on a.branch_id=b.branch_id
    Inner Join st.material c on a.material_id=c.material_id
    Inner Join st.material_type d on c.material_type_id=d.material_type_id
    Left Join st.uom e on a.uom_id=e.uom_id
    Left Join st.stock_movement_type f on a.stock_movement_type_id=f.stock_movement_type_id
 	Where  a.company_id = pcompany_id
	And (a.branch_id in (select x.branch_id from sys.fn_get_cbr_group(pcompany_id,pbranch_id) x) or pbranch_id=0)
 	And a.finyear = pfinyear
	And a.doc_date between vBeforeFromDate and pto_date 
	And (a.material_id = pmaterial_id or pmaterial_id=-2)
        And (c.material_type_id = pmaterial_type_id or pmaterial_type_id=0)
        And (a.stock_movement_type_id = pstock_movement_type_id or pstock_movement_type_id = 0)
	And (a.stock_location_id = psl_id or psl_id=0);
	
    Return query 
    Select a.branch_id, a.branch_name, a.voucher_id, a.doc_date,
    a.material_id, a.material_name, a.material_type_id, a.material_type,
    a.stock_movement_type_id, a.stock_movement_type, a.uom_desc, 
    a.issued_qty, a.received_qty, a.unit_rate_lc, a.amount
    From stock_movement_data a;
    
END;
$BODY$
  LANGUAGE 'plpgsql';  

?==?
CREATE OR REPLACE FUNCTION st.fn_stock_avail
(
	pcompany_id bigint,
	pbranch_id bigint,
	pfinyear character varying,
	pfrom_date date,
        pto_date date,
	pmat_type_id bigint,
	pmat_id bigint,
        preport_type int,
        pqty int)
    
    RETURNS TABLE(material_type_id bigint, material_type character varying, 
                  sub_type character varying, material_id bigint, material_name character varying, 
                  uom_id bigint, uom_desc character varying, no_of_days int, sales_qty numeric,
                  avg_sales_qty numeric, curr_stock numeric, st_avail integer) 

AS $BODY$
Declare vno_of_days int;

BEGIN	 

    -- Get no of days
    vno_of_days := (pto_Date - pfrom_date) + 1;

    DROP TABLE IF EXISTS st_temp;	
    Create temp table st_temp
    (       
        material_type_id bigint,
        material_type varchar(50),
        sub_type varchar(50),
        material_id bigint,
        material_name varchar(250),
        uom_id bigint,
        uom_desc varchar(20),
        no_of_days int,
        sales_qty numeric(18,3), 
        avg_sales_qty numeric(18,3),
        curr_stock numeric(18,3),
        st_avail int
    );

    INSERT INTO st_temp(material_type_id, material_type, sub_type, 
                            material_id, material_name, uom_id, uom_desc,
                            no_of_days,sales_qty,avg_sales_qty, st_avail)
    Select c.material_type_id, d.material_type, c.annex_info->>'sub_type',    
          b.material_id, c.material_name, b.uom_id, 
          e.uom_desc, vno_of_days, sum(b.issued_qty - b.received_qty),
          (sum(b.issued_qty - b.received_qty)/ vno_of_days),0
    From st.stock_control a
    Inner Join st.stock_tran b On a.stock_id = b.stock_id    
    Inner Join st.material c On b.material_id = c.material_id
    Inner Join st.material_type d On c.material_type_id = d.material_type_id
    Inner Join st.uom e On c.material_id = e.material_id And e.is_base
    Where a.doc_type= Any('{SI,SIV,SR,SRV,SRN}') And a.status = 5
    	And (a.company_id) = pcompany_id
        And (a.branch_id = pbranch_id Or pbranch_id = 0)
        And (c.material_type_id = pmat_type_id Or pmat_type_id = 0)
        And (c.material_id=pmat_id or pmat_id=-2)
        And a.doc_date Between pfrom_date And pto_date
    group by  c.material_type_id, d.material_type, c.annex_info->>'sub_type',
    b.material_id, c.material_name, b.uom_id, e.uom_desc;
    
    Update st_temp a set curr_stock = b.balance_qty_base
    from (select x.material_id, sum(x.balance_qty_base) as balance_qty_base 
    	 from st.fn_material_balance_wac(pcompany_id,pbranch_id,0,pfinyear,pto_date) x
         group  by x.material_id) b
    where a.material_id=b.material_id;                        
                         
    update st_temp a 
    set  st_avail = case when a.curr_stock > a.avg_sales_qty then round(a.curr_stock / a.avg_sales_qty) else 0 end
    where a.curr_stock != 0 and a.avg_sales_qty !=0;
  
    If preport_type=1 then  --**Fast Moving Items
       delete from st_temp x where x.avg_sales_qty < pqty;       
    End if;
    
    If preport_type=2 then  --**Slow Moving Items
       delete from st_temp x where x.avg_sales_qty > pqty;
    End if;
    
    RETURN query
    SELECT a.material_type_id, a.material_type, 
    a.sub_type, a.material_id, a.material_name, a.uom_id, a.uom_desc, a.no_of_days,
    a.sales_qty, a.avg_sales_qty, a.curr_stock, a.st_avail
    FROM st_temp a 
    order by a.material_type, a.sub_type, a.material_name; 

END;
$BODY$
    LANGUAGE 'plpgsql';

?==?
DROP FUNCTION If Exists st.fn_stock_reorder(bigint,bigint,character varying,date,bigint,bigint)

?==?
CREATE OR REPLACE FUNCTION st.fn_stock_reorder
(
	pcompany_id bigint,
	pbranch_id bigint,
	pfinyear character varying,
	pas_on_date date,
	pmat_type_id bigint,
	pmat_id bigint)
    
    RETURNS TABLE(material_type_id bigint, material_type character varying, 
                  sub_type character varying, material_id bigint, material_name character varying, 
                  uom_id bigint, uom_desc character varying, reorder_level numeric, curr_stock numeric, 
                  avg_cons numeric, pr_qty numeric,inpro_po_qty numeric, po_qty numeric,
                  inpro_grn_qty numeric, projected_qty numeric) 

AS $BODY$
BEGIN	

    DROP TABLE IF EXISTS st_temp;	
    Create temp table st_temp
    (       
        material_type_id bigint,
        material_type varchar(50),
        sub_type varchar(50),
        material_id bigint,
        material_name varchar(250),
        uom_id bigint,
        uom_desc varchar(20),
        reorder_level numeric(18,3),
        curr_stock numeric(18,3),
        avg_cons numeric(18,3),
        pr_qty numeric(18,3), 
        inpro_po_qty numeric(18,3),
        po_qty numeric(18,3),
        inpro_grn_qty numeric(18,3),
        projected_qty numeric(18,3)
    );	
    INSERT INTO st_temp(material_type_id, material_type, sub_type, 
                            material_id, material_name, uom_id, uom_desc,
                            reorder_level, curr_stock, avg_cons, pr_qty, 
                            inpro_po_qty, po_qty, inpro_grn_qty,projected_qty
                       )
	With bal_qty
    As
    (
    	Select x.material_id, x.branch_id, x.balance_qty_base, 0000 as reorder_level, 0.000 as avg_cons, 0.000 as pr_qty, 0.000 as ip_po_qty, 0.000 as po_qty, 0.000 as grn_qty
        From st.fn_material_balance_wac(pcompany_id, pbranch_id, 0, pfinyear, pas_on_date) x
        Union All
        Select y.material_id, y.branch_id, 0.000, 0.000, 0.000, y.open_pr_qty_base,  y.ip_po_qty, y.open_po_qty_base, y.open_grn_qty_base 
        From sm.fn_open_order_qty(pcompany_id, pbranch_id, 0) y
        Union All
        Select a.material_id, 0, 0.000, 0.000, Round((Sum(a.issued_qty)/365) * 1, 3), 0.00, 0.00, 0.00, 0.00
        From st.stock_ledger a
        Where a.company_id = pcompany_id
        And (a.branch_id = pbranch_id Or pbranch_id = 0)
        And a.doc_date Between (pas_on_date::Date - interval '1 Year') And pas_on_date
        And a.stock_movement_type_id = Any('{6, 12}'::Bigint[])  
        Group by a.material_id 
        Union All
        Select a.material_id, a.branch_id, 0.000, a.reorder_level, 0.000, 0.00, 0.00, 0.00, 0.00
        from st.mat_level a
        where (a.branch_id = pbranch_id Or pbranch_id = 0)
    )
    Select a.material_type_id, c.material_type,  a.annex_info->>'sub_type' as sub_type, 
           a.material_id, a.material_name, d.uom_id, d.uom_desc,
           coalesce(Sum(b.reorder_level), 0) as reorder_level,
     	   coalesce(Sum(b.balance_qty_base), 0) as balance_qty, 
           coalesce(Sum(b.avg_cons), 0) as avg_cons,
           coalesce(Sum(b.pr_qty), 0) as pr_qty,          
           coalesce(Sum(b.ip_po_qty), 0) as inpro_po_qty, 
           coalesce(Sum(b.po_qty), 0) as po_qty, 
           coalesce(Sum(b.grn_qty),0) as inpro_grn_qty,
           coalesce(coalesce(Sum(b.balance_qty_base), 0) + coalesce(Sum(b.po_qty), 0) + coalesce(Sum(b.grn_qty),0) + coalesce(Sum(b.ip_po_qty),0)) as projected_qty
    From st.material a
    Left Join bal_qty b On a.material_id = b.material_id
    Inner Join st.material_type c On a.material_type_id = c.material_type_id
    Inner Join st.uom d On a.material_id = d.material_id And d.is_base = true
    Where (a.material_type_id = pmat_type_id Or pmat_type_id = 0)
    And (a.material_id = pmat_id Or pmat_id = -2)
    Group By a.material_type_id, c.material_type,  a.annex_info->>'sub_type', 
             a.material_id, a.material_name, d.uom_id, d.uom_desc;
--    having (Sum(balance_qty_base) < (a.annex_info->'qty_info'->>'min_qty')::numeric);
 
    RETURN query
    SELECT a.material_type_id, a.material_type, 
    a.sub_type, a.material_id, a.material_name, a.uom_id, a.uom_desc,
    a.reorder_level, a.curr_stock, a.avg_cons, a.pr_qty, a.inpro_po_qty,
    a.po_qty, a.inpro_grn_qty, a.projected_qty
    FROM st_temp a;

END;
$BODY$
    LANGUAGE 'plpgsql';


?==?
CREATE OR REPLACE FUNCTION st.fn_mat_info_sale_many(pmat_tran jsonb, pdoc_date date, pfinyear character varying, pcustomer_id bigint = -1)
RETURNS TABLE
(   bar_code character varying, 
    mat_id bigint, 
    mat_name character varying,
    is_service boolean,
    material_type_id bigint, 
    mt_name character varying, 
    uom_id bigint, 
    uom character varying, 
    sale_rate numeric, 
    disc_pcnt numeric,
    bal_qty numeric, 
    gst_hsn_info json
) 
AS
$BODY$
Begin
    DROP TABLE IF EXISTS mat_info;
    CREATE temp TABLE mat_info
    (	bar_code character varying, 
        mat_id bigint, 
        mat_name character varying,
        is_service boolean,
        material_type_id bigint, 
        mt_name character varying, 
        uom_id bigint, 
        uom character varying, 
        sale_rate numeric, 
        disc_pcnt numeric,
        bal_qty numeric, 
        sl_id BigInt,
        gst_hsn_info json
    );

    Declare 
            mat_info_cursor Cursor For Select x.bar_code, x.material_id, x.stock_location_id
            From jsonb_to_recordset(pmat_tran) as x(material_id bigint, bar_code varchar(20), stock_location_id bigint);
    Begin
        For rec in mat_info_cursor Loop   
            -- We ignore the Stock Location id as the balance calc would take time
            Insert into mat_info(bar_code, mat_id, mat_name, is_service, material_type_id, mt_name, uom_id, uom, 
                                 sale_rate, disc_pcnt, bal_qty, sl_id, gst_hsn_info)
            Select a.bar_code, a.mat_id, a.mat_name, a.is_service, a.material_type_id, a.mt_name, a.uom_id, a.uom, 
                                 a.sale_rate, a.disc_pcnt, a.bal_qty, rec.stock_location_id, a.gst_hsn_info
            From st.fn_mat_info_sale(rec.bar_code, rec.material_id, -1, pdoc_date, pfinyear, pcustomer_id) a;
        End Loop;
    End;
    
    -- We calculate the stock balance seperately and then update the results
    With mat_sl
    As
    (   Select x.bar_code, x.material_id, x.stock_location_id
        From jsonb_to_recordset(pmat_tran) as x(material_id bigint, bar_code varchar(20), stock_location_id bigint)
    ),
    mat_bal
    As
    (   Select a.material_id, a.stock_location_id, Coalesce(Sum(a.received_qty-a.issued_qty), 0.00) as bal_qty
        From st.stock_ledger a
        Inner Join mat_sl b On a.material_id = b.material_id And a.stock_location_id = b.stock_location_id
        Where a.doc_date <= pdoc_date
            And a.finyear = pfinyear
        Group By a.material_id, a.stock_location_id
    )
    Update mat_info a
    Set bal_qty = b.bal_qty
    From mat_bal b
    Where a.mat_id = b.material_id And a.sl_id = b.stock_location_id;
    
    -- generate output
    Return Query
    Select a.bar_code, a.mat_id, a.mat_name, a.is_service, a.material_type_id, a.mt_name, a.uom_id, a.uom, a.sale_rate, a.disc_pcnt, a.bal_qty, a.gst_hsn_info
    From mat_info a;
End;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_stock_reorder_report
(
	pcompany_id bigint,
	pbranch_id bigint,
	pfinyear character varying,
	pas_on_date date,
	pmat_type_id bigint,
	pmat_id bigint,
        pin_pur_units boolean)
    
    RETURNS TABLE(material_type_id bigint, material_type character varying, 
                  sub_type character varying, material_id bigint, material_name character varying, 
                  uom_id bigint, uom_desc character varying, reorder_level numeric, curr_stock numeric, 
                  avg_cons numeric, pr_qty numeric,inpro_po_qty numeric, po_qty numeric,
                  inpro_grn_qty numeric, projected_qty numeric) 

AS $BODY$
BEGIN	

    If pin_pur_units = false Then   --In Base Units
  
        RETURN query
        SELECT a.material_type_id, a.material_type, 
        a.sub_type, a.material_id, a.material_name, a.uom_id, a.uom_desc,
        a.reorder_level, a.curr_stock, a.avg_cons, a.pr_qty, a.inpro_po_qty,
        a.po_qty, a.inpro_grn_qty, a.projected_qty
        FROM st.fn_stock_reorder(pcompany_id, pbranch_id, pfinyear, pas_on_date,
								 pmat_type_id, pmat_id) a;
     Else  --In Purchase Units
     
        RETURN query
        SELECT a.material_type_id, a.material_type, 
        a.sub_type, a.material_id, a.material_name, b.uom_id, b.uom_desc,
        sys.fn_handle_zero_divide(a.reorder_level,b.uom_qty)::Numeric(18,3) as reorder_level, 
        sys.fn_handle_zero_divide(a.curr_stock,b.uom_qty)::Numeric(18,3) as curr_stock,
        sys.fn_handle_zero_divide(a.avg_cons,b.uom_qty)::Numeric(18,3) as avg_cons, 
        sys.fn_handle_zero_divide(a.pr_qty,b.uom_qty)::Numeric(18,3) as pr_qty, 
        sys.fn_handle_zero_divide(a.inpro_po_qty,b.uom_qty)::Numeric(18,3) as inpro_po_qty,
        sys.fn_handle_zero_divide(a.po_qty,b.uom_qty)::Numeric(18,3) as po_qty,
        sys.fn_handle_zero_divide(a.inpro_grn_qty,b.uom_qty)::Numeric(18,3) as inpro_grn_qty,
        sys.fn_handle_zero_divide(a.projected_qty,b.uom_qty)::Numeric(18,3) as projected_qty 
        FROM st.fn_stock_reorder(pcompany_id, pbranch_id, pfinyear, pas_on_date,
								 pmat_type_id, pmat_id) a
        Inner join  st.uom b on a.material_id=b.material_id and uom_type_id=103 ;
     End If;
                                 
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_view_mat_cur_status
(
	pcompany_id bigint,
	pbranch_id bigint,
	pfinyear character varying,
	pas_on_date date,
	pmat_ids bigint[],
        pvoucher_id character varying)    
    RETURNS TABLE(material_type_id bigint, material_type character varying, 
                  sub_type character varying, material_id bigint, material_name character varying, 
                  uom_id bigint, uom_desc character varying, 
                  reorder_level numeric, reorder_qty numeric, max_qty numeric, curr_stock numeric, 
                  pr_qty numeric,inpro_po_qty numeric, po_qty numeric,
                  inpro_grn_qty numeric, projected_qty numeric, excess_qty numeric) 

AS $BODY$
BEGIN	

    DROP TABLE IF EXISTS st_temp;	
    Create temp table st_temp
    (       
        material_type_id bigint,
        material_type varchar(50),
        sub_type varchar(50),
        material_id bigint,
        material_name varchar(250),
        uom_id bigint,
        uom_desc varchar(20),
        reorder_level numeric(18,3),
        reorder_qty numeric(18,3),
        max_qty numeric(18,3),        
        curr_stock numeric(18,3),
        avg_cons numeric(18,3),
        pr_qty numeric(18,3), 
        inpro_po_qty numeric(18,3),
        po_qty numeric(18,3),
        inpro_grn_qty numeric(18,3),
        projected_qty numeric(18,3)
    );	
    INSERT INTO st_temp(material_type_id, material_type, sub_type, 
                            material_id, material_name, uom_id, uom_desc,
                            reorder_level,reorder_qty, max_qty, curr_stock, pr_qty, 
                            inpro_po_qty, po_qty, inpro_grn_qty,projected_qty
                       )
    With bal_qty
    As
    (
    	Select x.material_id, x.branch_id, x.balance_qty_base, 0000 as reorder_level, 0.000 as reorder_qty, 
        0000 as max_qty, 0.000 as pr_qty, 0.000 as ip_po_qty, 0.000 as po_qty, 0.000 as grn_qty
        From st.fn_material_balance_wac(pcompany_id, pbranch_id, 0, pfinyear, pas_on_date) x
        Union All
        Select y.material_id, y.branch_id, 0.000, 0.000, 0.000, 0.000, 
        y.open_pr_qty_base,  y.ip_po_qty, y.open_po_qty_base, y.open_grn_qty_base 
        From sm.fn_open_order_qty(pcompany_id, pbranch_id, 0) y       
        Union All
        Select a.material_id, a.branch_id, 0.000, a.reorder_level, a.reorder_qty, a.max_qty, 0.00, 0.00, 0.00, 0.00
        from st.mat_level a
        where (a.branch_id = pbranch_id Or pbranch_id = 0) 
        Union All
        Select b.material_id, a.target_branch_id as branch_id, 0, 0, 0, 0, 0, sum(b.received_qty) * -1 as ip_po_qty, 0, 0
        From st.stock_control a 
        Inner Join st.stock_tran b On a.stock_id = b.stock_id
        Where a.company_id = pcompany_id
              And (a.target_branch_id = pbranch_id Or pbranch_id = 0)
              And a.doc_type = 'PO'
              And a.status != 5
              And a.stock_id=pvoucher_id
              Group by b.material_id, a.target_branch_id	
    )
    
    Select a.material_type_id, c.material_type,  a.annex_info->>'sub_type' as sub_type, 
           a.material_id, a.material_name, d.uom_id, d.uom_desc,
           coalesce(Sum(b.reorder_level), 0) as reorder_level,
           coalesce(Sum(b.reorder_qty), 0) as reorder_qty,
           coalesce(Sum(b.max_qty), 0) as max_qty,
     	   coalesce(Sum(b.balance_qty_base), 0) as balance_qty, 
           coalesce(Sum(b.pr_qty), 0) as pr_qty,          
           coalesce(Sum(b.ip_po_qty), 0) as inpro_po_qty, 
           coalesce(Sum(b.po_qty), 0) as po_qty, 
           coalesce(Sum(b.grn_qty),0) as inpro_grn_qty,
           coalesce(coalesce(Sum(b.balance_qty_base), 0) + coalesce(Sum(b.po_qty), 0) + coalesce(Sum(b.grn_qty),0) + coalesce(Sum(b.ip_po_qty),0)) as projected_qty
    From st.material a
    Left Join bal_qty b On a.material_id = b.material_id
    Inner Join st.material_type c On a.material_type_id = c.material_type_id
    Inner Join st.uom d On a.material_id = d.material_id And d.is_base = true
    Where (a.material_id  = Any(pmat_ids)  Or pmat_ids = '{-2}' )
    Group By a.material_type_id, c.material_type,  a.annex_info->>'sub_type', 
             a.material_id, a.material_name, d.uom_id, d.uom_desc;
 
    RETURN query
    SELECT a.material_type_id, a.material_type, 
    a.sub_type, a.material_id, a.material_name, a.uom_id, a.uom_desc,
    a.reorder_level, a.reorder_qty, a.max_qty, a.curr_stock,
    a.pr_qty, a.inpro_po_qty, a.po_qty, a.inpro_grn_qty, a.projected_qty,
    Case when (a.projected_qty > a.max_qty and a.max_qty > 0 ) then  a.projected_qty - a.max_qty else 0 End
    FROM st_temp a;

END;
$BODY$
    LANGUAGE 'plpgsql';

?==?
CREATE OR REPLACE FUNCTION st.fn_lat_pur_price(
	pcompany_id bigint,
	pbranch_id bigint,
	pmat_type_id bigint,
	pmat_id bigint,
	psupplier_id bigint)
    RETURNS TABLE(doc_date date, voucher_id character varying, branch_id bigint,
                  account_id bigint, supplier character varying, material_type_id bigint,
                  material_type character varying, material_id bigint,
                  material_name character varying, sub_type character varying, 
                  qty numeric, rate numeric, unit_rate_lc numeric, loading_cost numeric,
                  landed_cost numeric)
AS $BODY$

BEGIN	

    Return query
    With latest_date
    as
    (select  x.branch_id, x.material_id, max(x.doc_date) as max_doc_date 
     from st.stock_ledger x 
     inner join st.stock_control y on x.voucher_id=y.stock_id
     inner join st.material z on x.material_id=z.material_id
     where x.company_id=pcompany_id
     and (x.branch_id = pbranch_id or pbranch_id = 0)
     and (z.material_type_id = pmat_type_id or pmat_type_id =0)
     and (x.material_id = pmat_id or pmat_id = -2) 
     and (y.account_id = psupplier_id or psupplier_id = 0)
     and y.doc_type='SPG' 
     group by x.branch_id, x.material_id
    )
    select a.doc_date, a.voucher_id, a.branch_id, b.account_id, g.supplier, 
    e.material_type_id, f.material_type, a.material_id, e.material_name, 
    (e.annex_info->>'sub_type')::varchar as sub_type,
    c.received_qty as qty, c.rate,  a.unit_rate_lc, 
    a.unit_rate_lc - c.rate as loading_cost, c.received_qty * a.unit_rate_lc landed_cost
    from st.stock_ledger a
    inner join st.stock_control b on a.voucher_id=b.stock_id
    inner join st.stock_tran c on a.vch_tran_id=c.stock_tran_id 
    inner join latest_date d on a.branch_id=d.branch_id and a.material_id=d.material_id and a.doc_date=d.max_doc_date
    inner join st.material e on a.material_id=e.material_id
    inner join st.material_type f on e.material_type_id=f.material_type_id
    inner join ap.supplier g on b.account_id=g.supplier_id
    where a.company_id=pcompany_id
    and (a.branch_id = pbranch_id or pbranch_id =0) 
    and (e.material_type_id = pmat_type_id or pmat_type_id =0)
    and (e.material_id=pmat_id or pmat_id=-2)
    and (b.account_id=psupplier_id or psupplier_id = 0)
    and b.doc_type='SPG' 
    ORDER BY supplier, material_name;
                                 
End;
$BODY$
    LANGUAGE 'plpgsql';

?==?

CREATE OR REPLACE FUNCTION st.fn_lat_pur_price_supplier(
	pcompany_id bigint,
	pbranch_id bigint,
	pmat_type_id bigint,
	pmat_id bigint,
	psupplier_id bigint)
    RETURNS TABLE(doc_date date, voucher_id character varying, branch_id bigint,
                  account_id bigint, supplier character varying,
                  material_type_id bigint, material_type character varying,
                  sub_type character varying, material_id bigint, 
                  material_name character varying, qty numeric,
                  rate numeric, unit_rate_lc numeric, loading_cost numeric,
                  landed_cost numeric) 

AS $BODY$
BEGIN	

    Return query
    With latest_date
    as
    (select  a.branch_id, b.account_id, a.material_id, max(a.doc_date) as max_doc_date 
     from st.stock_ledger a
     inner join st.stock_control b on a.voucher_id=b.stock_id
     inner join st.material c on a.material_id=c.material_id
     where a.company_id=pcompany_id
     and (a.branch_id = pbranch_id or pbranch_id = 0)
     and (c.material_type_id = pmat_type_id or pmat_type_id =0)
     and (a.material_id = pmat_id or pmat_id = -2) 
     and (b.account_id = psupplier_id or psupplier_id = 0)
     and b.doc_type='SPG' 
     group by a.branch_id, b.account_id, a.material_id
    )
    select a.doc_date, a.voucher_id, a.branch_id, b.account_id, g.supplier,
    e.material_type_id, f.material_type,(e.annex_info->>'sub_type')::varchar as sub_type,
    a.material_id, e.material_name, a.received_qty, c.rate, a.unit_rate_lc, 
    a.unit_rate_lc - c.rate as loading_cost, (a.received_qty * a.unit_rate_lc) landed_cost
    from st.stock_ledger a
    inner join st.stock_control b on a.voucher_id=b.stock_id
    inner join st.stock_tran c on a.vch_tran_id=c.stock_tran_id
    inner join latest_date d on a.branch_id=d.branch_id and b.account_id=d.account_id and a.material_id=d.material_id and a.doc_date=d.max_doc_date
    inner join st.material e on a.material_id=e.material_id
    inner join st.material_type f on e.material_type_id=f.material_type_id
    inner join ap.supplier g on b.account_id=g.supplier_id
    where b.company_id=pcompany_id
     and (a.branch_id = pbranch_id or pbranch_id = 0)
     and (f.material_type_id = pmat_type_id or pmat_type_id =0)
     and (e.material_id = pmat_id or pmat_id = -2) 
     and (b.account_id = psupplier_id or psupplier_id = 0)
     and b.doc_type='SPG' 
    ORDER BY material_name,supplier;
                                 
End;
$BODY$
    LANGUAGE 'plpgsql';

?==?
Create or replace Function st.fn_mat_bal_sl_wac_report(pcompany_id bigint, pbranch_id bigint, pmaterial_id bigint, pfinyear varchar(4), pto_date date, 
													   psl_id bigint)
RETURNS TABLE  
(	material_type_id bigint,
    material_type character Varying,
    material_id bigint,
    material_name varchar(250),
    material_code varchar(20),
    uom_desc varchar(20), 
    stock_location_id BigInt,
    stock_location_name Character Varying,
    sl_type_id BigInt,
    sl_type Character Varying,
    balance_qty_base numeric(18,4),
    rate numeric(18,4),
    amount numeric(18,4)
)
AS
$BODY$
Begin	
	-- This function returns Stock Balance WAC by Stock Location
	Return query 
	With sl_sum
	As
	(   -- Compute txn balance and balance value
            Select a.material_id, a.branch_id, a.stock_location_id,
                    Sum(a.received_qty - a.issued_qty) bal_qty,
                    Sum((a.received_qty - a.issued_qty) * a.unit_rate_lc)  bal_val
            From st.stock_ledger a
            Where a.company_id = pcompany_id And a.finyear = pfinyear
                    And a.doc_date <= pto_date
                    And (a.branch_id = pbranch_id Or pbranch_id = 0)
                    And (a.material_id = pmaterial_id Or pmaterial_id = 0)
            Group by a.material_id, a.branch_id, a.stock_location_id
	),
	bal_rate
	As
	(   -- Compute SL balance and Branch rate (WAC rate is always at branch level)
            -- Do not apply SL filters here. This would result in incorrect branch rate
            Select a.branch_id, a.stock_location_id, a.material_id, a.bal_qty,
                    sys.fn_handle_zero_divide(Sum(a.bal_val) Over (Partition By a.material_id, a.branch_id), Sum(a.bal_qty) Over (Partition By a.material_id, a.branch_id))::Numeric(18,4) rate
            From sl_sum a
	)
	Select c.material_type_id, c.material_type, b.material_id, b.material_name, b.material_code, d.uom_desc,
            e.stock_location_id, e.stock_location_name, f.sl_type_id, (br.branch_code || '-' || f.sl_type)::Varchar,
            a.bal_qty, a.rate, (a.bal_qty * a.rate)::Numeric(18,2)
	from bal_rate a
	Inner Join st.material b On a.material_id = b.material_id
	Inner Join st.material_type c On b.material_type_id = c.material_type_id
	Inner Join st.uom d On b.material_id = d.material_id And d.is_base
	Inner Join st.stock_location e On a.stock_location_id = e.stock_location_id
	Inner Join st.sl_type f On e.sl_type_id = f.sl_type_id
	Inner Join sys.branch br On e.branch_id = br.branch_id
	Where (a.stock_location_id = psl_id Or psl_id = 0);

END;
$BODY$
LANGUAGE plpgsql;

?==?
Create or replace function st.fn_sl_report_ts(pcompany_id bigint, pbranch_id bigint, pfinyear varchar(4), pmaterial_id bigint, pfrom_date date, pto_date date, pstock_location_id bigint)
RETURNS TABLE  
(
	material_id bigint,
	material_name varchar(250),
	material_code varchar(20),
	doc_date date,
	vch_tran_id varchar(50),
	reference_id varchar(50),
	reference_tran_id varchar(50),
	narration varchar(500),
	received_qty numeric(18,4),
	issued_qty numeric(18,4),
	unit_rate_lc numeric(18,4),
	uom_desc varchar(20),
	uom_id bigint,
	uom_qty numeric(18,4),
	uom_qty_desc varchar(50),
	inserted_on timestamp,
	stock_location varchar(250),
        kg_fat numeric(18,4),
        kg_snf numeric(18,4)
)
AS
$BODY$
Begin	
	DROP TABLE IF EXISTS sl_rpt;
	CREATE TEMP TABLE sl_rpt
	(	material_id bigint,
		material_name varchar(250),
		material_code varchar(20),
		doc_date date,
		vch_tran_id varchar(50),
		reference_id varchar(50),
		reference_tran_id varchar(50),
		narration varchar(500),
		received_qty numeric(18,4),
		issued_qty numeric(18,4),
		unit_rate_lc numeric(18,4),
		uom_desc varchar(20),
		uom_id bigint,
		uom_qty numeric(18,4),
		uom_qty_desc varchar(50),
		inserted_on timestamp,
		stock_location varchar(250),
        kg_fat numeric(18,4),
        kg_snf numeric(18,4)
	);
							 
	-- Insert opening balance as the first record with balance qty as received qty
	Insert into sl_rpt(material_id, material_name, material_code,
				doc_date, vch_tran_id, reference_id, reference_tran_id, narration, 
				received_qty, issued_qty, 
				unit_rate_lc, uom_desc, uom_id, uom_qty, uom_qty_desc, inserted_on)
	Select a.material_id, a.material_name, a.material_code,
				a.doc_date, a.vch_tran_id, a.reference_id, a.reference_tran_id, a.narration, 
				a.received_qty, a.issued_qty, 
				a.unit_rate_lc, a.uom_desc, a.uom_id, a.uom_qty, a.uom_qty_desc, a.inserted_on
	From st.fn_sl_report(pcompany_id, pbranch_id, pfinyear, pmaterial_id, pfrom_date, pto_date, pstock_location_id) a;
	
	Update sl_rpt a
	set kg_fat = case when a.received_qty != 0 then b.output_kg_fat else b.kg_fat end,
		kg_snf = case when a.received_qty != 0 then b.output_kg_snf else b.kg_snf end
	From md.ts_tran b 
	Where a.vch_tran_id = b.ts_tran_id;

    
	return query 
	select a.material_id, a.material_name, a.material_code, a.doc_date, a.vch_tran_id, a.reference_id, a.reference_tran_id, a.narration, 
		a.received_qty, a.issued_qty, a.unit_rate_lc, a.uom_desc, a.uom_id, a.uom_qty, a.uom_qty_desc, a.inserted_on, a.stock_location, 
                COALESCE(a.kg_fat, 0), COALESCE(a.kg_snf, 0)
	from sl_rpt a
	order by a.material_name, a.doc_date, a.inserted_on;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE Or Replace FUNCTION st.fn_sl_lot_cons(IN pcompany_id bigint, IN pbranch_id bigint, IN pmaterial_id bigint, IN pmat_type_id bigint, IN pfrom_date date, IN pto_date date, pin_wf boolean = false)
RETURNS TABLE
(   voucher_id Character Varying,
    vch_tran_id Character Varying,
    vch_date date,
    material_id BigInt,
    material_name Character Varying,
    lot_issue_qty Numeric(18,3),
    sl_lot_id uuid,
    lot_doc_id Character Varying,
    lot_doc_date Date,
    test_insp_id character varying, 
    test_insp_date date,
    mfg_date date,
    exp_date date,
    lot_state_id integer,
    uom_id bigint,
    uom_desc character varying,
    branch_id bigint,
    branch_name character varying,
    stock_location_id BigInt,
    stock_location_name Character Varying
) 
AS
$BODY$
Begin 

    return query 
    Select a.voucher_id, a.vch_tran_id, a.vch_date, c.material_id, d.material_name, a.lot_issue_qty, 
		b.sl_lot_id, c.vch_tran_id, c.doc_date, b.test_insp_id, b.test_insp_date, b.mfg_date, b.exp_date,
		b.lot_state_id, e.uom_id, e.uom_desc, c.branch_id, f.branch_name, sl.stock_location_id, sl.stock_location_name
	From st.sl_lot_alloc a
	Inner Join st.sl_lot b On a.sl_lot_id = b.sl_lot_id
	Inner Join st.stock_ledger c On b.sl_id = c.stock_ledger_id
	Inner Join st.material d On c.material_id = d.material_id
	Inner Join st.uom e On d.material_id = e.material_id And e.is_base
	Inner Join sys.branch f On c.branch_id = f.branch_id
	Inner Join st.stock_location sl On c.stock_location_id = sl.stock_location_id
	Where (c.branch_id = pbranch_id Or pbranch_id = 0)
		And (c.material_id = pmaterial_id Or pmaterial_id = 0)
		And a.vch_date Between pfrom_date And pto_date
		And Case When pin_wf Then 1=1 Else a.status = 5 End;

END
$BODY$
LANGUAGE plpgsql;

?==?
CREATE Or Replace FUNCTION st.fn_sl_lot_cons_ts(IN pcompany_id bigint, IN pbranch_id bigint, IN pmaterial_id bigint, IN pmat_type_id bigint, IN pfrom_date date, IN pto_date date, pin_wf boolean = false)
RETURNS TABLE
(   voucher_id Character Varying,
    vch_tran_id Character Varying,
    vch_date date,
    material_id BigInt,
    material_name Character Varying,
    lot_issue_qty Numeric(18,3),
    lot_issue_kg Numeric(18,3),
    lot_issue_fat_kg Numeric(18,3),
    lot_issue_snf_kg Numeric(18,3),
    sl_lot_id uuid,
    lot_doc_id Character Varying,
    lot_doc_date Date,
    test_insp_id character varying, 
    test_insp_date date,
    mfg_date date,
    exp_date date,
    lot_state_id integer,
    lot_qty Numeric(18,3),
    fat_pcnt Numeric(5,2),
    snf_pcnt Numeric(5,2),
    fat_kg Numeric(18,3),
    snf_kg Numeric(18,3),
    uom_id bigint,
    uom_desc character varying,
    branch_id bigint,
    branch_name character varying,
    stock_location_id BigInt,
    stock_location_name Character Varying
) 
AS
$BODY$
Begin 
    return query 
    Select a.voucher_id, a.vch_tran_id, a.vch_date, c.material_id, d.material_name, a.lot_issue_qty, 
		(a.lot_issue_qty * e.in_kg)::Numeric(18,3) lot_issue_kg,
		(a.lot_issue_qty * e.in_kg * Coalesce((b.ref_info->'tia_info'->>'tia_102')::Numeric(5,2), 0) / 100)::Numeric(18,3) lot_issue_fat_kg,
		(a.lot_issue_qty * e.in_kg * Coalesce((b.ref_info->'tia_info'->>'tia_101')::Numeric(5,2), 0) / 100)::Numeric(18,3) lot_issue_snf_kg,
		b.sl_lot_id, c.vch_tran_id, c.doc_date, b.test_insp_id, b.test_insp_date, b.mfg_date, b.exp_date,
		b.lot_state_id, b.lot_qty, 
		Coalesce((b.ref_info->'tia_info'->>'tia_102')::Numeric(5,2), 0) fat_pcnt,
		Coalesce((b.ref_info->'tia_info'->>'tia_101')::Numeric(5,2), 0) snf_pcnt,
		(b.lot_qty * e.in_kg * Coalesce((b.ref_info->'tia_info'->>'tia_102')::Numeric(5,2), 0) / 100)::Numeric(18,3) fat_kg,
		(b.lot_qty * e.in_kg * Coalesce((b.ref_info->'tia_info'->>'tia_101')::Numeric(5,2), 0) / 100)::Numeric(18,3) snf_kg,
		e.uom_id, e.uom_desc, c.branch_id, f.branch_name, sl.stock_location_id, sl.stock_location_name
	From st.sl_lot_alloc a
	Inner Join st.sl_lot b On a.sl_lot_id = b.sl_lot_id
	Inner Join st.stock_ledger c On b.sl_id = c.stock_ledger_id
	Inner Join st.material d On c.material_id = d.material_id
	Inner Join st.uom e On d.material_id = e.material_id And e.is_base
	Inner Join sys.branch f On c.branch_id = f.branch_id
	Inner Join st.stock_location sl On c.stock_location_id = sl.stock_location_id
	Where (c.branch_id = pbranch_id Or pbranch_id = 0)
		And (c.material_id = pmaterial_id Or pmaterial_id = 0)
		And a.vch_date Between pfrom_date And pto_date
		And Case When pin_wf Then 1=1 Else a.status = 5 End;
END
$BODY$
LANGUAGE plpgsql;

?==?
Create or replace Function st.fn_mat_sl_bal(pbranch_id BigInt, pmat_id BigInt, pfinyear Varchar(4), pas_on Date) 
Returns Table
(	stock_location_id BigInt,
 	stock_location_name Character Varying,
 	mat_bal Numeric(18,3)
)
As
$BODY$
Declare
	vhas_qc Boolean := false;
Begin
	-- Fetch if qcinfo is applicable
	Select Coalesce((a.annex_info->'qc_info'->>'has_qc')::Boolean, false) Into vhas_qc
	From st.material a
	Where a.material_id = pmat_id;
	
	-- Fetch balance if lot applicable
	If vhas_qc Then
		Return Query
		With sl_union
		As
		(	Select c.stock_location_id, Sum(a.lot_qty) lot_txn
			From st.sl_lot a
			Inner Join st.stock_ledger c ON a.sl_id = c.stock_ledger_id
			Where c.branch_id = pbranch_id And c.material_id = pmat_id
				And c.doc_date <= pas_on
			Group By c.stock_location_id
			Union All
			Select c.stock_location_id, -Sum(b.lot_issue_qty)
			From st.sl_lot a
			Inner Join st.sl_lot_alloc b On a.sl_lot_id = b.sl_lot_id
			Inner Join st.stock_ledger c ON a.sl_id = c.stock_ledger_id
			Where c.branch_id = pbranch_id And c.material_id = pmat_id
				And c.doc_date <= pas_on
			Group By c.stock_location_id, a.sl_lot_id 
		)
		Select a.stock_location_id, b.stock_location_name, Sum(a.lot_txn)
		From sl_union a
		Inner Join st.stock_location b ON a.stock_location_id = b.stock_location_id
		Group by a.stock_location_id, b.stock_location_name
		Having Sum(a.lot_txn) > 0;
	Else
		Return Query
		Select a.stock_location_id, b.stock_location_name, Sum(a.received_qty-a.issued_qty)
		From st.stock_ledger a
		Inner Join st.stock_location b ON a.stock_location_id = b.stock_location_id
		Where a.branch_id = pbranch_id And a.material_id = pmat_id
			And a.finyear = pfinyear
			And a.doc_date <= pas_on
		Group by a.stock_location_id, b.stock_location_name;
	End If;

End
$BODY$
Language plpgsql;

?==?
create or replace function st.fn_sl_group_by_stock_loc_report(pcompany_id bigint, pbranch_id bigint, pfinyear varchar(4), pmaterial_id bigint, pfrom_date date, pto_date date, pstock_location_id bigint)
RETURNS TABLE  
(
	material_id bigint,
	material_name varchar(250),
	material_code varchar(20),
	doc_date date,
	vch_tran_id varchar(50),
	reference_id varchar(50),
	reference_tran_id varchar(50),
	narration varchar(500),
	received_qty numeric(18,4),
	issued_qty numeric(18,4),
	unit_rate_lc numeric(18,4),
	uom_desc varchar(20),
	uom_id bigint,
	uom_qty numeric(18,4),
	uom_qty_desc varchar(50),
	inserted_on timestamp,
    stock_location_id bigint,
	stock_location_name varchar(250),
    branch_id bigint,
    branch_name character varying
)
AS
$BODY$ 
	declare vDate date; vUnitsInBase numeric(18,6); vUoMDesc varchar(20);
Begin	
	DROP TABLE IF EXISTS sl_temp;
	CREATE TEMP TABLE sl_temp(
		material_id bigint,
		material_name varchar(250),
		material_code varchar(20),
		doc_date date,
		vch_tran_id varchar(50),
		reference_id varchar(50),
		reference_tran_id varchar(50),
		narration varchar(500),
		received_qty numeric(18,4),
		issued_qty numeric(18,4),
		unit_rate_lc numeric(18,4),
		uom_desc varchar(20),
		uom_id bigint,
		uom_qty numeric(18,4),
		uom_qty_desc varchar(50),
		inserted_on timestamp,
        stock_location_id bigint,
		stock_location_name varchar(250),
        kg_fat numeric(18,4),
        kg_snf numeric(18,4),
        branch_id bigint,
        branch_name character varying
	);

	vDate := pfrom_date - '1 day'::interval;
	
	-- Fetch opening Rate
	DROP TABLE IF EXISTS op_bal_rate;
	CREATE TEMP TABLE op_bal_rate(
        branch_id bigint,
		material_id bigint,
        stock_location_id bigint,
		balance_qty numeric(18,4),
		rate numeric(18,4)
	);
	
	Insert into op_bal_rate(branch_id, material_id, stock_location_id, balance_qty, rate)
	Select a.branch_id, a.material_id, a.stock_location_id, coalesce(sum(a.balance_qty_base), 0) as balance_qty, sys.fn_handle_zero_divide(coalesce(sum(a.balance_qty_base * a.rate), 0), coalesce(sum(a.balance_qty_base), 0)) as rate
	From st.fn_material_balance_wac_detail(pcompany_id, pbranch_id, pmaterial_id, pstock_location_id, pfinyear, vDate) a
	group by a.branch_id, a.material_id, a.stock_location_id;

	
	-- Insert opening balance as the first record with balance qty as received qty
	Insert into sl_temp(branch_id, material_id, material_name, material_code,
				doc_date, vch_tran_id, reference_id, reference_tran_id, narration, 
				received_qty, issued_qty, 
				unit_rate_lc, uom_desc, uom_id, uom_qty, uom_qty_desc, inserted_on, stock_location_id)
	Select c.branch_id, a.material_id, a.material_name, a.material_code, 
		pfrom_date, 'Opening Balance' as vch_tran_id, '' as reference_id, '' as reference_tran_id, '' as narration, 
		coalesce(c.balance_qty, 0), 0,
		c.rate as unit_rate_lc, b.uom_desc, -1 as uom_id, 0 as uom_qty, '' as uom_qty_desc, '1970-01-01 00:00:00', c.stock_location_id
	From st.material a
	Inner Join st.fn_material_uom_base() b on a.material_id = b.material_id
	Inner Join op_bal_rate c on  a.material_id = c.material_id
	where (a.material_id = pmaterial_id or pmaterial_id = 0)
		And c.balance_qty != 0
	Union All -- Insert Stock Ledger Balance 
	Select d.branch_id, a.material_id, a.material_name, a.material_code,  
		d.doc_date, d.vch_tran_id, d.reference_id, d.reference_tran_id, d.narration, 
		d.received_qty, d.issued_qty,
		d.unit_rate_lc, b.uom_desc, d.uom_id, d.uom_qty, '', d.inserted_on, COALESCE(d.stock_location_id, -1)
	From st.material a
	Inner Join st.fn_material_uom_base() b on a.material_id = b.material_id
	Left Join st.stock_ledger d on a.material_id = d.material_id
	Where d.finyear = pfinyear
		And d.doc_date between pfrom_date and pto_date
		And d.company_id = pcompany_id
		And (d.branch_id = pbranch_id or pbranch_id=0)
		And (d.material_id = pmaterial_id or pmaterial_id = 0)
		And (d.stock_location_id = pstock_location_id or pstock_location_id = 0);

	Update sl_temp a
        set stock_location_name = b.stock_location_name
        From st.stock_location b
	where a.stock_location_id = b.stock_location_id;
    
	Update sl_temp a
        set branch_name = b.branch_name
        From sys.branch b
	where a.branch_id = b.branch_id;
    
	return query 
	select a.material_id, a.material_name, a.material_code, a.doc_date, a.vch_tran_id, a.reference_id, a.reference_tran_id, a.narration, 
		a.received_qty, a.issued_qty, a.unit_rate_lc, a.uom_desc, a.uom_id, a.uom_qty, a.uom_qty_desc, a.inserted_on, 
        a.stock_location_id, a.stock_location_name, a.branch_id, a.branch_name
	from sl_temp a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
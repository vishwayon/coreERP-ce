Create OR REPLACE Function st.sp_get_base_qty(puom_id bigint, pqty numeric(18,4), out pqty_base numeric(18,4))
Returns numeric(18,4) as
$BODY$
Declare vBaseQty numeric(18,4) = 0; vUnitsInBase numeric(18, 4) =0;
Begin
	-- Fetch the conversion unit
	select uom_qty into vUnitsInBase from st.uom where uom_id=puom_id;

	-- Apply conversion formula

	select sys.fn_handle_round('qty', (pqty * vUnitsInBase)) into vBaseQty;
	
	-- Generate the output
	pqty_base:=vBaseQty;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
Create or Replace Function st.sp_sl_lot_post(
    ptable_name character varying,
    pvoucher_id Character Varying,
    ptest_insp_id Character Varying)
Returns Void 
As
$BODY$
Declare
    vdoc_type varchar(4); vbranch_id bigint; vhas_str_qc boolean; 
Begin
    If ptable_name = 'st.stock_control' Then
        Select doc_type into vdoc_type
        From st.stock_control
        where stock_id=pvoucher_id;

        If vdoc_type in ('SPG', 'JWR') Then    
            Insert Into st.sl_lot(sl_lot_id, sl_id, test_insp_id, test_insp_date, lot_no, lot_qty, mfg_date, exp_date, best_before, 
                lot_state_id, ref_info)
            Select md5(a.voucher_id || c.test_insp_id)::uuid, a.stock_ledger_id, c.test_insp_id, c.test_insp_date,
                    c.lot_no, c.accept_qty + c.reject_qty, c.mfg_date, c.exp_date, c.best_before, 
                    Case When c.accept_qty > 0 Then 101 Else 102 End as lot_state_id, c.ref_info::jsonb
            From st.stock_ledger a
            Inner Join st.stock_tran b On a.vch_tran_id = b.stock_tran_id
            Inner Join st.stock_tran_qc c On b.stock_tran_id = c.stock_tran_id 
            Where a.voucher_id = pvoucher_id;
        End If;        
    End If;
    
    If ptable_name = 'prod.doc_control' Then
        Select doc_type into vdoc_type
        From prod.doc_control
        where voucher_id=pvoucher_id;

        If vdoc_type = 'BSH' Then -- This is a Batch Sheet
            
            -- Batch Sheet output items
            Insert Into st.sl_lot(sl_lot_id, sl_id, test_insp_id, test_insp_date, lot_no, lot_qty, mfg_date, exp_date, best_before, 
                lot_state_id, ref_info)
            Select md5(a.voucher_id || c.test_insp_id)::uuid, a.stock_ledger_id, c.test_insp_id, c.test_insp_date,
                    c.lot_no, c.accept_qty + c.reject_qty, c.mfg_date, c.exp_date, c.best_before, 
                Case When c.accept_qty > 0 Then 101 Else 102 End as lot_state_id, c.ref_info::jsonb
            From st.stock_ledger a
            Inner Join prod.bp_tran b On a.vch_tran_id = b.bp_tran_id
            Inner Join st.stock_tran_qc c On b.bp_tran_id = c.stock_tran_id 
            Where a.voucher_id = pvoucher_id;
            
            -- Batch Sheet recovered items
            Insert Into st.sl_lot(sl_lot_id, sl_id, test_insp_id, test_insp_date, lot_no, lot_qty, mfg_date, exp_date, best_before, 
                lot_state_id, ref_info)
            Select md5(a.voucher_id || c.test_insp_id)::uuid, a.stock_ledger_id, c.test_insp_id, c.test_insp_date,
                    c.lot_no, c.accept_qty + c.reject_qty, c.mfg_date, c.exp_date, c.best_before, 
                Case When c.accept_qty > 0 Then 101 Else 102 End as lot_state_id, c.ref_info::jsonb
            From st.stock_ledger a
            Inner Join prod.recovery_tran b On a.vch_tran_id = b.vch_tran_id
            Inner Join st.stock_tran_qc c On b.vch_tran_id = c.stock_tran_id 
            Where a.voucher_id = pvoucher_id;

        End If;

        If vdoc_type = 'LM' Then -- This is a Lot Mix     
            Insert Into st.sl_lot(sl_lot_id, sl_id, test_insp_id, test_insp_date, lot_no, lot_qty, mfg_date, exp_date, best_before, 
                lot_state_id, ref_info)
            Select md5(a.voucher_id || c.test_insp_id)::uuid, a.stock_ledger_id, c.test_insp_id, c.test_insp_date,
                    c.lot_no, c.accept_qty + c.reject_qty, c.mfg_date, c.exp_date, c.best_before, 
                Case When c.accept_qty > 0 Then 101 Else 102 End as lot_state_id, c.ref_info::jsonb
            From st.stock_ledger a
            Inner Join prod.doc_control b On a.voucher_id = b.voucher_id
            Inner Join st.stock_tran_qc c On b.voucher_id = c.stock_tran_id 
            Where a.vch_tran_id = pvoucher_id || ':R';
        End If;

        If vdoc_type = 'MIS' Then -- This is a Material Issue Stores    
            Insert Into st.sl_lot(sl_lot_id, sl_id, test_insp_id, test_insp_date, lot_no, lot_qty, mfg_date, exp_date, best_before, 
                lot_state_id, ref_info)
            Select md5(a.voucher_id || c.test_insp_id)::uuid, a.stock_ledger_id, c.test_insp_id, c.test_insp_date,
                    c.lot_no, c.accept_qty + c.reject_qty, c.mfg_date, c.exp_date, c.best_before, 
                Case When c.accept_qty > 0 Then 101 Else 102 End as lot_state_id, c.ref_info::jsonb
            From st.stock_ledger a
            Inner Join prod.doc_control b On a.voucher_id = b.voucher_id
            Inner Join st.stock_tran_qc c On b.voucher_id = c.stock_tran_id 
            Where a.vch_tran_id = pvoucher_id || ':R';
        End If;
    
    End If;

    If ptable_name = 'st.stock_transfer_park_post' Then
        Select doc_type into vdoc_type
        From st.stock_control
        where stock_id = replace(pvoucher_id, ':AJ', '');
    	
    	--
        If vdoc_type = 'PTN' Then
        	With sl_lot
            As
            (   Select a.test_insp_id, a.test_insp_date, a.lot_no, b.lot_issue_qty, a.mfg_date, a.exp_date, a.best_before, 
                    a.lot_state_id, b.vch_tran_id, a.ref_info
                From st.sl_lot a
                Inner Join st.sl_lot_alloc b ON a.sl_lot_id = b.sl_lot_id
                Where b.voucher_id = replace(pvoucher_id, ':AJ', '')
            )
            Insert Into st.sl_lot(sl_lot_id, sl_id, test_insp_id, test_insp_date, lot_no, lot_qty, mfg_date, exp_date, best_before, 
                lot_state_id, ref_info)
            Select md5(a.vch_tran_id || b.test_insp_id)::uuid, a.stock_ledger_id, b.test_insp_id, b.test_insp_date,
                    b.lot_no, b.lot_issue_qty, b.mfg_date, b.exp_date, b.best_before, b.lot_state_id, b.ref_info
            From st.stock_ledger a
            Inner Join sl_lot b On a.vch_tran_id = b.vch_tran_id || ':AJ'
            Where a.voucher_id = pvoucher_id;
        Else
            Select (annex_info->>'has_str_qc')::boolean into vhas_str_qc
            From sys.branch where branch_id = (select target_branch_id From st.stock_control where stock_id=replace(pvoucher_id, ':AJ', ''));

            If vhas_str_qc Then
                Insert Into st.sl_lot(sl_lot_id, sl_id, test_insp_id, test_insp_date, lot_no, lot_qty, mfg_date, exp_date, best_before, 
                    lot_state_id, ref_info)
                Select md5(a.vch_tran_id || c.test_insp_id)::uuid, a.stock_ledger_id, c.test_insp_id, c.test_insp_date,
                        c.lot_no, c.accept_qty + c.reject_qty, c.mfg_date, c.exp_date, c.best_before, 
                        Case When c.accept_qty > 0 Then 101 Else 102 End as lot_state_id, c.ref_info::jsonb
                From st.stock_ledger a
                Inner Join st.stock_tran b On a.vch_tran_id = b.stock_tran_id || ':AJ'
                Inner Join st.stock_tran_qc c On b.stock_tran_id = c.stock_tran_id 
                Where a.voucher_id =  pvoucher_id;
            Else
                With sl_lot
                As
                (   Select a.test_insp_id, a.test_insp_date, a.lot_no, b.lot_issue_qty, a.mfg_date, a.exp_date, a.best_before, 
                        a.lot_state_id, b.vch_tran_id, a.ref_info
                    From st.sl_lot a
                    Inner Join st.sl_lot_alloc b ON a.sl_lot_id = b.sl_lot_id
                    Where b.voucher_id = replace(pvoucher_id, ':AJ', '')
                )
                Insert Into st.sl_lot(sl_lot_id, sl_id, test_insp_id, test_insp_date, lot_no, lot_qty, mfg_date, exp_date, best_before, 
                    lot_state_id, ref_info)
                Select md5(a.vch_tran_id || b.test_insp_id)::uuid, a.stock_ledger_id, b.test_insp_id, b.test_insp_date,
                        b.lot_no, b.lot_issue_qty, b.mfg_date, b.exp_date, b.best_before, b.lot_state_id, b.ref_info
                From st.stock_ledger a
                Inner Join sl_lot b On a.vch_tran_id = b.vch_tran_id || ':AJ'
                Where a.voucher_id = pvoucher_id;
            End If;        
        End If;        
    End If;
    
    If ptable_name = 'md.doc_control' Then
        Select doc_type into vdoc_type
        From md.doc_control
        where voucher_id=pvoucher_id;

        If vdoc_type = 'DMP' Then -- This is a Daily Milk Procurement           
           Declare 
                vref_info jsonb = '{"tia_info":{}}'; vdesc character varying = '';
                dmp_cursor Cursor For Select a.material_id, a.stock_location_id, round(sys.fn_handle_zero_divide(sum(a.fat_pcnt * a.qty_in_ltr), sum(a.qty_in_ltr)), 2) avg_fat, 
                                                round(sys.fn_handle_zero_divide(sum(a.snf_pcnt * a.qty_in_ltr), sum(a.qty_in_ltr)), 2) avg_snf
                                        from md.doc_tran a
                                        where a.voucher_id = pvoucher_id
                                        Group By a.material_id, a.stock_location_id; 
            Begin
                For rec in dmp_cursor Loop

                   Select jsonb_set(vref_info, '{tia_info, tia_101}', (rec.avg_snf::varchar)::jsonb, true) into vref_info;

                    Select jsonb_set(vref_info, '{tia_info, tia_102}', (rec.avg_fat::varchar)::jsonb, true) into vref_info;

                    Select jsonb_set(vref_info, '{tia_info, tia_103}', ('0')::jsonb, true) into vref_info;                

                    with qc_info
                    As (
                        select rec.material_id, a.test_insp_attr_id,  a.test_insp_attr, 'FAT %' as test_desc, rec.avg_fat as result
                        From prod.test_insp_attr a
                        where a.test_insp_attr_id = 102
                        Union All
                        select rec.material_id, a.test_insp_attr_id,  a.test_insp_attr, 'SNF %' as test_desc, rec.avg_snf as result
                        From  prod.test_insp_attr a
                        where a.test_insp_attr_id = 101
                        Union All 
                        select rec.material_id, a.test_insp_attr_id,  a.test_insp_attr, 'CLR' as test_desc, 0 as result
                        From  prod.test_insp_attr a
                        where a.test_insp_attr_id = 103
                    )
                    Select jsonb_set(vref_info, '{data}', (json_agg(row_to_json(d)))::jsonb, true) into vref_info
                    From qc_info d;

                    with test_result
                    As (
                         select case test_insp_attr_id when 101 then 'SNF % : ' || rec.avg_snf
                                            When 102 then 'FAT % : ' || rec.avg_fat
                                            When 103 then 'CLR : 0' 
                                            Else ''
                                End info
                        From prod.test_insp_attr a
                        where a.test_insp_attr_id != 100
                    )    
                    Select ('"' || array_to_string(array(select x.info from test_result x), '; ') || '"') into vdesc;

                    Select jsonb_set(vref_info, '{desc}', vdesc::jsonb, true) into vref_info;

                    Insert Into st.sl_lot(sl_lot_id, sl_id, test_insp_id, test_insp_date, lot_no, lot_qty, 
                          mfg_date, exp_date, best_before, lot_state_id, ref_info) 
                    Select md5(a.voucher_id || rec.material_id || rec.stock_location_id)::uuid, c.stock_ledger_id, a.voucher_id, a.doc_date, '', sum(b.qty_in_ltr), 
                        a.doc_date, a.doc_date + interval '5' day, a.doc_date + interval '5' day, 101 lot_state_id, vref_info
                    From md.doc_control a 
                    Inner Join md.doc_tran b On a.voucher_id = b.voucher_id 
                    Inner Join st.stock_ledger c On a.voucher_id = c.voucher_id And b.material_id = c.material_id And b.stock_location_id = c.stock_location_id
                    Where c.voucher_id = pvoucher_id And c.material_id = rec.material_id And c.stock_location_id = rec.stock_location_id
                    group By a.voucher_id, a.doc_date, c.material_id, c.stock_location_id, c.stock_ledger_id;
                End Loop;
            End;
        End If;    
    End If;
End
$BODY$
Language plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_sl_post_generic_data(pfn_name character varying, ptable_name character varying, pvoucher_id varchar(50))
RETURNS TABLE(company_id bigint,
            branch_id bigint,
            vch_tran_id varchar(50),
            stock_location_id bigint,
            material_id bigint,
            reference_id varchar(50),
            reference_tran_id varchar(50),
            uom_id bigint,
            uom_qty numeric(18,4),
            received_qty numeric(18,4),
            issued_qty numeric(18,4),
            unit_rate_lc numeric(18,4),
            stock_movement_type_id bigint,
            account_id bigint,
            bp_id varchar(50)
) AS
$BODY$
BEGIN

    RETURN QUERY 
    EXECUTE 'SELECT a.company_id, a.branch_id, a.vch_tran_id, a.stock_location_id, a.material_id, a.reference_id, a.reference_tran_id, 
            		a.uom_id, a.uom_qty, a.received_qty, a.issued_qty, a.unit_rate_lc, a.stock_movement_type_id, a.account_id, a.bp_id
            FROM ' || pfn_name || '($1, $2) a'
    USING ptable_name, pvoucher_id;

END;
$BODY$
LANGUAGE plpgsql;

?==?
Drop FUNCTION if exists st.sp_sl_post(ptable_name character varying, 
    pfinyear character varying, 
    pvoucher_id character varying,
    pdoc_date date,
    pnarration character varying,
    pinclude_in_cost boolean,
    pstock_movement_type_id bigint);

?==?
CREATE OR REPLACE FUNCTION st.sp_sl_post(
    ptable_name character varying,
    pfinyear character varying,
    pvoucher_id character varying,
    pdoc_date date,
    pnarration character varying,
    pinclude_in_cost boolean,
    pstock_movement_type_id bigint DEFAULT 0,
    pfn_name character varying DEFAULT '')
RETURNS void AS
$BODY$
Declare vDocType varchar(4); vStockMovementTypeGroup varchar(1); vIBVoucher_ID varchar(50); vPurchaseAccount_ID bigint; vcnt smallint;
Begin
    DROP TABLE IF EXISTS stock_detail;	
    create temp TABLE  stock_detail
    (	
        index serial, 
        company_id bigint,
        branch_id bigint,
        stock_location_id bigint,
        material_id bigint,
        vch_tran_id varchar(50),
        reference_id varchar(50),
        reference_tran_id varchar(50),
        uom_id bigint,
        uom_qty numeric(18,4),
        received_qty numeric(18,4),
        issued_qty numeric(18,4),
        unit_rate_lc numeric(18,4),
        stock_movement_type_id bigint,
        bp_id varchar(50),
            purchase_account_id bigint
    );
    If ptable_name ='st.stock_control' then 
        Select doc_type, sale_account_id into vDocType, vPurchaseAccount_ID
        From st.stock_control
        where stock_id in  (pvoucher_id, replace(pvoucher_id, ':I', ''));        
		
        --raise exception 'vDocType-%', vDocType;
        if vDocType in ('PR', 'PRV')  Then 
            Select a.sale_account_id into vPurchaseAccount_ID
            From st.stock_control a
            where stock_id = (Select b.reference_id from st.stock_control b where b.stock_id = pvoucher_id);
        End If;		

        If vDocType = 'MCN' then 		
            Insert into stock_detail(company_id, branch_id, stock_location_id, material_id, vch_tran_id, reference_id, reference_tran_id, 
                    uom_id, uom_qty, 
                    received_qty, issued_qty, unit_rate_lc, stock_movement_type_id, bp_id, purchase_account_id)
            Select  a.company_id, a.branch_id, b.stock_location_id, b.material_id, b.stock_tran_id, a.reference_id, b.reference_tran_id, 
                b.uom_id, b.issued_qty, 
                0, st.sp_get_base_qty(b.uom_id, b.issued_qty), 0, 14, '', vPurchaseAccount_ID
            from st.stock_control a
            Inner Join st.stock_tran b on a.stock_id=b.stock_id
            Where a.stock_id=pVoucher_ID;

            Insert into stock_detail(company_id, branch_id, stock_location_id, material_id, vch_tran_id, reference_id, reference_tran_id, 
                    uom_id, uom_qty, 
                    received_qty, issued_qty, unit_rate_lc, stock_movement_type_id, bp_id, purchase_account_id)
            Select  a.company_id, a.branch_id, (a.annex_info->>'output_sl_id')::bigint, (a.annex_info->>'output_mat_id')::bigint, a.stock_id, a.reference_id, '', 
                (a.annex_info->>'output_uom_id')::bigint, (a.annex_info->>'output_qty')::numeric, 
                st.sp_get_base_qty((a.annex_info->>'output_uom_id')::bigint, (a.annex_info->>'output_qty')::numeric), 0, 0, 15, '', vPurchaseAccount_ID
            from st.stock_control a
            Where a.stock_id=pVoucher_ID;
        ElseIf vDocType = 'MRTN' then 
        	--
            If right(pVoucher_ID , 2) = ':I' Then
            	pVoucher_ID := replace(pvoucher_id, ':I', '');
            	--raise exception 'pVoucher_ID-%', pVoucher_ID;
                Insert into stock_detail(company_id, branch_id, stock_location_id, material_id, vch_tran_id, reference_id, reference_tran_id, 
                        uom_id, uom_qty, 
                        received_qty, issued_qty, unit_rate_lc, stock_movement_type_id, bp_id, purchase_account_id)
                Select  a.company_id, a.branch_id, b.stock_location_id, b.material_id, b.stock_tran_id, a.reference_id, b.reference_tran_id, 
                    b.uom_id, b.issued_qty, 
                    0, st.sp_get_base_qty(b.uom_id, b.issued_qty), 0, pstock_movement_type_id, '', vPurchaseAccount_ID
                from st.stock_control a
                Inner Join st.stock_tran b on a.stock_id=b.stock_id
                Where a.stock_id = pVoucher_ID;
            Else
                Insert into stock_detail(company_id, branch_id, stock_location_id, material_id, vch_tran_id, reference_id, reference_tran_id, 
                        uom_id, uom_qty, 
                        received_qty, issued_qty, unit_rate_lc, stock_movement_type_id, bp_id, purchase_account_id)
                Select  a.company_id, a.branch_id, b.target_stock_location_id, b.material_id, b.stock_tran_id, a.reference_id, b.reference_tran_id, 
                    b.uom_id, b.issued_qty, 
                    st.sp_get_base_qty(b.uom_id, b.issued_qty), 0, 0, pstock_movement_type_id, '', vPurchaseAccount_ID
                from st.stock_control a
                Inner Join st.stock_tran b on a.stock_id=b.stock_id
                Where a.stock_id=pVoucher_ID;
            End If;
        ElseIf vDocType = 'PTN' then         	--
            If right(pVoucher_ID , 2) = ':I' Then
            	pVoucher_ID := replace(pvoucher_id, ':I', '');
            	--raise exception 'pVoucher_ID-%', pVoucher_ID;
                Insert into stock_detail(company_id, branch_id, stock_location_id, material_id, vch_tran_id, reference_id, reference_tran_id, 
                        uom_id, uom_qty, 
                        received_qty, issued_qty, unit_rate_lc, stock_movement_type_id, bp_id, purchase_account_id)
                Select  a.company_id, a.branch_id, b.stock_location_id, b.material_id, b.stock_tran_id, a.reference_id, b.reference_tran_id, 
                    b.uom_id, b.issued_qty, 
                    0, st.sp_get_base_qty(b.uom_id, b.issued_qty), 0, pstock_movement_type_id, '', vPurchaseAccount_ID
                from st.stock_control a
                Inner Join st.stock_tran b on a.stock_id=b.stock_id
                Where a.stock_id = pVoucher_ID;
            Else
                Insert into stock_detail(company_id, branch_id, stock_location_id, material_id, vch_tran_id, reference_id, reference_tran_id, 
                        uom_id, uom_qty, 
                        received_qty, issued_qty, unit_rate_lc, stock_movement_type_id, bp_id, purchase_account_id)
                Select  a.company_id, a.branch_id, b.target_stock_location_id, b.material_id, b.stock_tran_id, a.reference_id, b.reference_tran_id, 
                    b.uom_id, b.issued_qty, 
                    st.sp_get_base_qty(b.uom_id, b.issued_qty), 0, 0, pstock_movement_type_id, '', vPurchaseAccount_ID
                from st.stock_control a
                Inner Join st.stock_tran b on a.stock_id=b.stock_id
                Where a.stock_id=pVoucher_ID;
            End If;
        Else
            -- Fetch Stock Movement Type ID
            if pstock_movement_type_id = 0 then		
                Select stock_movement_type_id, stock_movement_type_group into pstock_movement_type_id, vStockMovementTypeGroup
                from st.stock_movement_type 
                where seq_type= vDocType;
            else		
                Select stock_movement_type_group into vStockMovementTypeGroup
                from st.stock_movement_type 
                where stock_movement_type_id = pstock_movement_type_id;		
            End if;

            If vStockMovementTypeGroup = 'C'  Then		
                Insert into stock_detail(company_id, branch_id, stock_location_id, material_id, vch_tran_id, reference_id, reference_tran_id, 
                        uom_id, uom_qty, 
                        received_qty, issued_qty, unit_rate_lc, stock_movement_type_id, bp_id, purchase_account_id)
                Select  a.company_id, a.branch_id, b.stock_location_id, b.material_id, b.stock_tran_id, a.reference_id, b.reference_tran_id, 
                    b.uom_id, b.issued_qty, 
                    0, st.sp_get_base_qty(b.uom_id, b.issued_qty), 0, pstock_movement_type_id, '', vPurchaseAccount_ID
                from st.stock_control a
                Inner Join st.stock_tran b on a.stock_id=b.stock_id
                Where a.stock_id=pVoucher_ID;

                Insert into stock_detail(company_id, branch_id, stock_location_id, material_id, vch_tran_id, reference_id, reference_tran_id, 
                        uom_id, uom_qty, 
                        received_qty, issued_qty, unit_rate_lc, stock_movement_type_id, bp_id, purchase_account_id)
                Select  a.company_id, a.branch_id, b.target_stock_location_id, b.material_id, b.stock_tran_id, a.reference_id, b.reference_tran_id, 
                    b.uom_id, b.issued_qty, 
                    st.sp_get_base_qty(b.uom_id, b.issued_qty), 0, 0, pstock_movement_type_id, '', vPurchaseAccount_ID
                from st.stock_control a
                Inner Join st.stock_tran b on a.stock_id=b.stock_id
                Where a.stock_id=pVoucher_ID;
            Else

                Insert into stock_detail(company_id, branch_id, stock_location_id, material_id, vch_tran_id, reference_id, reference_tran_id, 
                        uom_id, uom_qty, 
                        received_qty, issued_qty, unit_rate_lc, stock_movement_type_id, bp_id, purchase_account_id)
                Select  a.company_id, a.branch_id, b.stock_location_id, b.material_id, b.stock_tran_id, a.reference_id, b.reference_tran_id, 
                    b.uom_id, case when b.received_qty > 0 Then b.received_qty Else b.issued_qty End, 
                    st.sp_get_base_qty(b.uom_id, b.received_qty), st.sp_get_base_qty(b.uom_id, b.issued_qty), 0, pstock_movement_type_id, '', vPurchaseAccount_ID
                from st.stock_control a
                Inner Join st.stock_tran b on a.stock_id=b.stock_id
                Where a.stock_id=pVoucher_ID;
            End If;
        End If;
    ElseIf ptable_name ='st.stock_transfer_park_post' then 	
        if pstock_movement_type_id = 0 then		
            Select stock_movement_type_id, stock_movement_type_group into pstock_movement_type_id, vStockMovementTypeGroup
            from st.stock_movement_type 
            where stock_movement_type = 'Stock Transfer IN';
        else		
            Select stock_movement_type_group into vStockMovementTypeGroup
            from st.stock_movement_type 
            where stock_movement_type_id = pstock_movement_type_id;		
        End if;

        vIBVoucher_ID := substring(pVoucher_ID, 0, position(':' in pVoucher_ID));
        -- Post received qty
        Insert into stock_detail(company_id, branch_id, stock_location_id, material_id, vch_tran_id, reference_id, reference_tran_id, 
                uom_id, uom_qty, 
                received_qty, issued_qty, unit_rate_lc, stock_movement_type_id, bp_id, purchase_account_id)
        Select  a.company_id, a.target_branch_id, d.receipt_sl_id, b.material_id, b.stock_tran_id || ':AJ', a.stock_id as reference_id, b.stock_tran_id as reference_tran_id, 
            b.uom_id, case when b.received_qty > 0 Then b.received_qty Else b.issued_qty End, 
            c.issued_qty, 0, c.unit_rate_lc, pstock_movement_type_id, '', vPurchaseAccount_ID
        from st.stock_control a
        Inner Join st.stock_tran b on a.stock_id=b.stock_id
        Inner Join st.stock_tran_extn d on d.stock_id=b.stock_id and d.stock_tran_id = b.stock_tran_id
        Inner Join st.stock_ledger c on b.stock_tran_id = c.vch_tran_id
        Where a.stock_id=vIBVoucher_ID;

        -- Post short/gain qty
        Insert into stock_detail(company_id, branch_id, stock_location_id, material_id, vch_tran_id, reference_id, reference_tran_id, 
                uom_id, uom_qty, 
                received_qty, issued_qty, unit_rate_lc, stock_movement_type_id, bp_id, purchase_account_id)
        Select  a.company_id, a.target_branch_id, d.receipt_sl_id, b.material_id, b.stock_tran_id || ':I:AJ', a.stock_id as reference_id, b.stock_tran_id as reference_tran_id, 
            b.uom_id, d.short_qty, 
            case when d.short_qty < 0 Then 0 else d.short_qty End, case when d.short_qty < 0 Then -1* d.short_qty else 0 End, c.unit_rate_lc, 16, '', vPurchaseAccount_ID
        from st.stock_control a
        Inner Join st.stock_tran b on a.stock_id=b.stock_id
        Inner Join st.stock_tran_extn d on d.stock_id=b.stock_id and d.stock_tran_id = b.stock_tran_id
        Inner Join st.stock_ledger c on b.stock_tran_id = c.vch_tran_id
        Where a.stock_id=vIBVoucher_ID
                And d.short_qty <> 0;
    ElseIf ptable_name = 'pos.inv_control' Then
        Select doc_type, sale_account_id into vDocType, vPurchaseAccount_ID
        From pos.inv_control
        where inv_id=pvoucher_id;

        If vDocType in ('PI', 'PIV')  Then
            -- Stock Movement Type is sale
            pstock_movement_type_id := 6;
                ElseIf vDocType = 'PSR' Then
                        pstock_movement_type_id := 7; -- Sales Return
        End If;

        -- Stocks Issues
        Insert into stock_detail(company_id, branch_id, stock_location_id, material_id, vch_tran_id, reference_id, reference_tran_id, 
                uom_id, uom_qty, 
                received_qty, issued_qty, unit_rate_lc, stock_movement_type_id, bp_id, purchase_account_id)
        Select  a.company_id, a.branch_id, b.stock_location_id, b.material_id, b.inv_tran_id, '', '', 
            b.uom_id, case when b.received_qty > 0 Then b.received_qty Else b.issued_qty End, 
            st.sp_get_base_qty(b.uom_id, b.received_qty), st.sp_get_base_qty(b.uom_id, b.issued_qty), 0, pstock_movement_type_id, '', vPurchaseAccount_ID
        from pos.inv_control a
        Inner Join pos.inv_tran b on a.inv_id=b.inv_id
        Where a.inv_id=pVoucher_ID;
    ElseIf ptable_name = 'pos.inv_bb' Then
        Select doc_type, sale_account_id into vDocType, vPurchaseAccount_ID
        From pos.inv_control
        where inv_id = replace(pVoucher_ID, ':BB', '') ;
        -- Stock Movement Type is purchase (Buy Backs)
        pstock_movement_type_id := 1;
        -- Stocks Purchased
        Insert into stock_detail(company_id, branch_id, stock_location_id, material_id, vch_tran_id, reference_id, reference_tran_id, 
                uom_id, uom_qty, 
                received_qty, issued_qty, unit_rate_lc, stock_movement_type_id, bp_id, purchase_account_id)
        Select  a.company_id, a.branch_id, b.stock_location_id, b.material_id, b.inv_bb_id, '', '', 
            b.uom_id, b.received_qty, 
            st.sp_get_base_qty(b.uom_id, b.received_qty), 0.00, b.bt_amt / st.sp_get_base_qty(b.uom_id, b.received_qty), pstock_movement_type_id, '', vPurchaseAccount_ID
        from pos.inv_control a
        Inner Join pos.inv_bb b on a.inv_id=b.inv_id
        Where a.inv_id = replace(pVoucher_ID, ':BB', '') 
            And b.received_qty > 0;
    ElseIf ptable_name = 'st.inv_bb' Then
        Select a.doc_type, a.sale_account_id into vDocType, vPurchaseAccount_ID
        From st.stock_control a
        where a.stock_id = replace(pVoucher_ID, ':BB', '') ;
        -- Stock Movement Type is purchase (Buy Backs)
        pstock_movement_type_id := 1;
        -- Stocks Purchased
        Insert into stock_detail(company_id, branch_id, stock_location_id, material_id, vch_tran_id, reference_id, reference_tran_id, 
                uom_id, uom_qty, 
                received_qty, issued_qty, unit_rate_lc, stock_movement_type_id, bp_id, purchase_account_id)
        Select  a.company_id, a.branch_id, b.stock_location_id, b.material_id, b.inv_bb_id, '', '', 
            b.uom_id, b.received_qty, 
            st.sp_get_base_qty(b.uom_id, b.received_qty), 0.00, b.bt_amt / st.sp_get_base_qty(b.uom_id, b.received_qty), pstock_movement_type_id, '', vPurchaseAccount_ID
        from st.stock_control a
        Inner Join st.inv_bb b on a.stock_id=b.inv_id
        Where a.stock_id = replace(pVoucher_ID, ':BB', '') 
            And b.received_qty > 0;
    Else    
    	If pfn_name != '' Then
            Insert into stock_detail(company_id, branch_id, stock_location_id, material_id, vch_tran_id, reference_id, reference_tran_id, 
                    uom_id, uom_qty, 
                    received_qty, issued_qty, unit_rate_lc, stock_movement_type_id, bp_id, purchase_account_id)
            select a.company_id, a.branch_id, a.stock_location_id, a.material_id, a.vch_tran_id, a.reference_id, a.reference_tran_id, 
                    a.uom_id, a.uom_qty, a.received_qty, a.issued_qty, a.unit_rate_lc, a.stock_movement_type_id, a.bp_id, a.account_id
            from st.fn_sl_post_generic_data(pfn_name, ptable_name, pVoucher_ID) a;
        Else
        	Raise Exception 'Invalid case statement for %, hence provide data via function for st.sp_sl_post', ptable_name ;
    	End If;
    End If;
	
--     select a.voucher_id into vcnt from stock_detail a limit 1;
--     raise exception 'pvoucher_id-%', pvoucher_id;
    Insert into st.stock_ledger(stock_ledger_id, company_id, branch_id, finyear, voucher_id, vch_tran_id, doc_date,
                            stock_location_id, material_id, reference_id, reference_tran_id, reference_date, narration, 
                            uom_id, uom_qty, received_qty, issued_qty, 
                            unit_rate_lc, stock_movement_type_id, inserted_on, last_updated, 
            account_id, bp_id)
    Select  sys.sp_gl_create_id(a.vch_tran_id, a.stock_location_id, a.material_id, 0), a.company_id, a.branch_id, pfinyear, pvoucher_id, a.vch_tran_id, pdoc_date,
            a.stock_location_id, a.material_id, a.reference_id, a.reference_tran_id, current_timestamp(0), pnarration, 
            a.uom_id, a.uom_qty, a.received_qty, a.issued_qty, 
            a.unit_rate_lc, a.stock_movement_type_id, current_timestamp(0), current_timestamp(0), 
    case when a.stock_movement_type_id in (1, 3) then a.purchase_account_id else b.consumed_account_id end, a.bp_id
    from stock_detail a
    inner join st.material b on a.material_id = b.material_id
    Where COALESCE((b.annex_info->>'is_service')::boolean, false) = false;
End;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.sp_sl_post_mat_lc(pvoucher_id character varying)
RETURNS TABLE  
(
	stock_ledger_id uuid,
    voucher_id character varying,
    vch_tran_id character varying,
	unit_rate_lc numeric(18,4)
) AS
$BODY$
Declare vTotalLandedCost numeric(18,4) = 0; vGrossAmt numeric(18,4) = 0; vDocDate date; vIs_purchase_tax boolean = false;
vItemTotal numeric(18,4) = 0; vVchID varchar(50) = ''; vVchTranID varchar(50) = ''; vlc_tax_amt numeric(18,4) = 0; vdisc_amt  numeric(18,4) = 0; vreceived_qty numeric(18,4) = 0;
Begin

	Select a.doc_date, a.disc_amt, (a.annex_info->>'is_purchase_tax')::boolean into vDocDate, vdisc_amt, vIs_purchase_tax	 
	from st.stock_control a
	where a.stock_id = pvoucher_id;

	--	Fetch material transaction into temp table
	DROP TABLE IF EXISTS stock_mat_tran;	
	create temp TABLE  stock_mat_tran
	(	        
		voucher_id varchar(50),
		vch_tran_id varchar(50),
        item_amt numeric(18,4),
		lc_tax_amt numeric(18,4)
	);

	Insert into stock_mat_tran(voucher_id, vch_tran_id, item_amt, lc_tax_amt)
	Select a.stock_id, a.stock_tran_id, case when a.apply_itc Then a.bt_amt Else a.item_amt End, 0.00
	From st.stock_tran a
	where a.stock_id = pvoucher_id;

	-- 	Fetch Gross amt to calculate Proportionate Tax and LC
	Select Coalesce(sum(a.item_amt), 0) into vGrossAmt
	From stock_mat_tran a;
	
	-- Fetch Voucher Level Landed Cost
	Select coalesce(sum(a.debit_amt + case When a.apply_itc Then 0 Else a.tax_amt End), 0) into vTotalLandedCost
	From st.stock_lc_tran a
	where stock_id = pvoucher_id;

	if vIs_purchase_tax Then
		-- Purchae tax items where Input Tax Credit (ITC) is not available
		With purchase_tax
		As
		(	Select a.stock_id, 
				(p_tax->>'apply_itc')::boolean apply_itc,  
				(p_tax->>'tax_schedule_id')::BigInt tax_schedule_id,
				(p_tax->>'tax_amt')::Numeric tax_amt
			from st.stock_control a, jsonb_array_elements(annex_info->'purchase_tax') p_tax
			Where a.stock_id = pvoucher_id
		)
		Select coalesce(Sum(a.tax_amt), 0) Into vlc_tax_amt 
		From purchase_tax a
		Where a.apply_itc = false;
	End if;
	

	--	Calculate proportinate LC and Disc
	If vGrossAmt > 0 Then 
		Update stock_mat_tran a
		Set lc_tax_amt = ((vTotalLandedCost -  vdisc_amt + vlc_tax_amt) * (a.item_amt/vGrossAmt));
	End If;

	--Raise Notice 'Item Amt - %, vVchID - %, vVchTranID - %, vlc_tax_amt - %, vdisc_amt - %, vreceived_qty - %', vItemTotal, vVchID, vVchTranID, vlc_tax_amt, vdisc_amt, vreceived_qty;
    
    Return query
    Select a.stock_ledger_id, b.voucher_id, b.vch_tran_id, sys.fn_handle_zero_divide ((b.item_amt + b.lc_tax_amt), a.received_qty) unit_rate_lc
    From st.stock_ledger a
    inner join stock_mat_tran b on a.voucher_id = b.voucher_id And a.vch_tran_id=b.vch_tran_id;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE function st.sp_sl_unpost(pvoucher_id Varchar(50))
RETURNS void as 
$BODY$
Begin

        -- Step 1: Toggle status of st.sl_lot_alloc 
        --          These entries are made by documents and only status, sl_id is updated by sl_post. 
        --          Therefore, reverse status and sl_id
        Update st.sl_lot_alloc
        Set status = 0,
            sl_id = Null
        Where voucher_id = pvoucher_id;

        -- Step 2: Remove entries from st.sl_lot
        Delete From st.sl_lot a
        Using st.stock_ledger b 
        Where a.sl_id = b.stock_ledger_id And b.voucher_id = pvoucher_id;
	
        -- Step 3: Remove entries from st.stock_ledger
        Delete from st.stock_ledger
        where voucher_id=pvoucher_id;
End;
$BODY$
  LANGUAGE plpgsql;

?==?

create or replace function st.sp_sl_post_mat_line_item_lc (pvoucher_id Varchar(50), pvch_tran_id varchar(50), pmaterial_id bigint, pdoc_date date, out punit_rate_lc numeric(18,4))
Returns numeric(18,4) as
$BODY$
Declare vUnitRateLC numeric(18,4); vInsertedOn date;
vCompany_ID bigint; vBranch_ID bigint; vFinYear varchar(4);
Begin
	Select company_id, branch_id, finyear, inserted_on into vCompany_ID, vBranch_ID, vFinYear, vInsertedOn
	from st.stock_ledger 
	where voucher_id=pvoucher_id
		And vch_tran_id = pvch_tran_id;

	Select sys.fn_handle_zero_divide(a.amount, a.received_qty_for_wac) into vUnitRateLC
	From (Select coalesce(sum(received_qty-issued_qty), 0) as received_qty_for_wac,
			coalesce(sum((received_qty-issued_qty) * unit_rate_lc), 0) as amount
		From st.stock_ledger
		where finyear = vFinYear
			And company_id = vCompany_ID
			And branch_id = vBranch_ID
			And material_id = pmaterial_id
			And doc_date <= pdoc_date
			And doc_date <= vInsertedOn 
			And voucher_id <> pvoucher_id
			And vch_tran_id <> pvch_tran_id
		) a;

	If vUnitRateLC < 0 Then -- Fetched rate is negative
		Select sys.fn_handle_zero_divide(a.amount, a.received_qty_for_wac) into vUnitRateLC
		From (Select coalesce(sum(received_qty-issued_qty), 0) as received_qty_for_wac,
				coalesce(sum((received_qty-issued_qty) * unit_rate_lc), 0) as amount
			From st.stock_ledger
			where finyear = vFinYear
				And company_id = vCompany_ID
				And branch_id = vBranch_ID
				And material_id = pmaterial_id
				And voucher_id <> pvoucher_id
				And vch_tran_id <> pvch_tran_id
			) a;
	End If;

	If vUnitRateLC < 0 Then
		vUnitRateLC := 0;
	End If;
	
	punit_rate_lc := vUnitRateLC;
	
End;
$BODY$
  LANGUAGE plpgsql;

?==?
create or replace function st.sp_sl_post_mat_lc_issue (pvoucher_id Varchar(50))
RETURNS void as
$BODY$
Declare vIssueUnitRateLC numeric(18,4); vVchTranID varchar(50); vMaterial_ID bigint; vqty numeric(18,4); vDocDate date; vDocType varchar(4); vStockMovementTypeGroup varchar(1);
Begin

	Select doc_date, doc_type into vDocDate, vDocType
	from st.stock_control 
	where stock_id = pvoucher_id;

	Select stock_movement_type_group into vStockMovementTypeGroup
	from st.stock_movement_type 
	where seq_type= vDocType;
	
	If vDocType in ('PR', 'SR', 'PRV', 'SRV') Then
		-- Get the unit rate for related Stock Purchase and update rate in stock ledger for PR	Ans SR	
		DECLARE	
			cur_stock_tran Cursor For (Select a.material_id, a.stock_tran_id, a.reference_tran_id from st.stock_tran a where a.stock_id = pvoucher_id);
		Begin
			For tran In cur_stock_tran Loop 
				--Raise exception 'cur_stock_tran.stock_tran_id - %', tran.stock_tran_id; --;
				Select unit_rate_lc into vIssueUnitRateLC 
				from st.stock_ledger 
				Where vch_tran_id = tran.reference_tran_id;
				
				Update st.stock_ledger a
				Set unit_rate_lc = vIssueUnitRateLC
				Where a.voucher_id=pvoucher_id
					And a.vch_tran_id = tran.stock_tran_id;
				
			End loop;
		End;
	Else
	    With sl_tran
            As
            (   Select a.stock_ledger_id, a.material_id, a.finyear, a.branch_id, a.doc_date, a.inserted_on
                From st.stock_ledger a
                Where a.voucher_id = pvoucher_id
                    And a.issued_qty > 0
            ) ,
            sl_mat_wac
            As
            (   
                Select a.material_id, sys.fn_handle_zero_divide(Sum((a.received_qty-a.issued_qty)*a.unit_rate_lc), Sum((a.received_qty-a.issued_qty))) unit_rate_lc
                From st.stock_ledger a
                Inner Join sl_tran b ON a.material_id = b.material_id 
                    And a.finyear = b.finyear
                    And a.branch_id = b.branch_id
                    And a.doc_date <= b.doc_date
                    And Case When a.doc_date = b.doc_date Then a.inserted_on < b.inserted_on Else 1=1 End
                Group by a.material_id
            ),
            sl_wac
            As
            (   Select a.stock_ledger_id, b.material_id, b.unit_rate_lc
                From sl_tran a
                Inner Join sl_mat_wac b On a.material_id = b.material_id
            )
            Update st.stock_ledger a
            Set unit_rate_lc = b.unit_rate_lc
            From sl_wac b
            Where a.stock_ledger_id = b.stock_ledger_id;
        
	End If;
End;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.sp_san_mat_lc(pvoucher_id Varchar(50), pvch_tran_id Varchar(50) = '')
RETURNS TABLE
(   stock_ledger_id uuid, 
    vch_tran_id Varchar(50), 
    unit_rate_lc numeric(18,4)
)
AS
$BODY$
Begin
    --	Fetch sl txn into temp table
	DROP TABLE IF EXISTS san_mat_tran;	
	create temp TABLE san_mat_tran
	(   stock_ledger_id uuid,
        branch_id BigInt,
        finyear Varchar(4),
        doc_date Date,
        vch_tran_id varchar(50),
        material_id BigInt,
        received_qty numeric(18,4),
        issued_qty numeric(18,4),
        unit_rate_lc numeric(18,4),
        inserted_on timestamp
	);
    Insert Into san_mat_tran(stock_ledger_id, branch_id, finyear, doc_date, vch_tran_id, material_id, received_qty, issued_qty, unit_rate_lc, inserted_on)
    Select a.stock_ledger_id, a.branch_id, a.finyear, a.doc_date, a.vch_tran_id, a.material_id, a.received_qty, a.issued_qty, a.unit_rate_lc, a.inserted_on
    From st.stock_ledger a
    Where a.voucher_id = pvoucher_id
        And (a.vch_tran_id = pvch_tran_id Or pvch_tran_id = '');
    
    -- Determine cost of issued Qty (based on master rate)
    Declare cur_san_tran Cursor
    For Select * From san_mat_tran;
    Declare v_wac Numeric(18,4) := 0; v_bal Numeric(18,3) := 0;
    Begin
            For rec In cur_san_tran Loop
                If rec.issued_qty > 0 Then
                    -- Issue based on avg. costing
                    Select sys.fn_handle_zero_divide(Sum((a.received_qty-a.issued_qty)*a.unit_rate_lc), Sum((a.received_qty-a.issued_qty))),
                        Sum(a.received_qty-a.issued_qty)
                            Into v_wac, v_bal
                    From st.stock_ledger a
                    Where a.material_id = rec.material_id
                        And a.vch_tran_id != rec.vch_tran_id
                        And a.branch_id = rec.branch_id
                        And a.finyear = rec.finyear
                        And a.doc_date <= rec.doc_date
                        And Case When a.doc_date = rec.doc_date Then a.inserted_on < rec.inserted_on Else 1=1 End;
                    If v_bal <= 0 Or v_wac < 0  Then -- If balance is negative, fetch avg rate of inwards till date
                        Select sys.fn_handle_zero_divide(Sum(a.received_qty*a.unit_rate_lc), Sum(a.received_qty))
                                Into v_wac
                        From st.stock_ledger a
                        Where a.material_id = rec.material_id
                            And a.branch_id = rec.branch_id
                            And a.finyear = rec.finyear
                            And a.doc_date <= rec.doc_date
                            And Case When a.doc_date = rec.doc_date And a.stock_movement_type_id Not In (1,9) Then a.inserted_on < rec.inserted_on Else 1=1 End
                            And a.stock_movement_type_id IN (1, -1, 9, 104); -- Include opstock + purchases to determine rate;
                    End If;
                Else
                    -- Receipts based on priority
                    If Exists(Select * From st.stock_tran Where stock_id = pvoucher_id And stock_tran_id = rec.vch_tran_id And rate > 0) Then
                        Select a.rate Into v_wac
                        From st.stock_tran a
                        Where a.stock_id = pvoucher_id And a.stock_tran_id = rec.vch_tran_id;
                    Else
                        Select sys.fn_handle_zero_divide(Sum((a.received_qty-a.issued_qty)*a.unit_rate_lc), Sum((a.received_qty-a.issued_qty))) Into v_wac
                        From st.stock_ledger a
                        Where a.material_id = rec.material_id
                            And a.branch_id = rec.branch_id
                            And a.doc_date <= rec.doc_date
                            And Case When a.doc_date = rec.doc_date Then a.inserted_on < rec.inserted_on Else 1=1 End
                            And a.stock_movement_type_id IN (1, -1, 9, 104); -- Include opstock + purchases to determine san rate
                    End If;
                    -- If production module avaialable, try std rate
                    If Exists(SELECT * FROM information_schema.tables where table_schema='prod' And table_name = 'std_rate') Then
                        If Exists(Select * From prod.std_rate Where material_id = rec.material_id) Then
                            Select a.std_rate Into v_wac
                            From prod.std_rate a
                            Where a.material_id = rec.material_id;
                        End If;
                    End If;
                End If;
                
                Update san_mat_tran a
                Set unit_rate_lc = v_wac
                Where a.stock_ledger_id = rec.stock_ledger_id;
                
                v_wac := 0;
            End Loop;
    End;
    
    Return Query
    Select a.stock_ledger_id, a.vch_tran_id, a.unit_rate_lc 
    From san_mat_tran a;
    
End;
$BODY$
LANGUAGE 'plpgsql';

?==?
-- Procedure to call from trigger
CREATE OR REPLACE FUNCTION st.trgporc_stock_post()
  RETURNS trigger AS
$BODY$
Declare vFinYear varchar(4); vVoucher_ID varchar(50)=''; vFCType_ID BigInt=0; vExchRate Numeric(18,6)=1; vDocDate Date; vCompany_ID bigint;
	vStatus smallint=0; vOldStatus smallint; vChequeDetails varchar(250)=''; vNarration Varchar(500)='';
	vType varchar(40)=''; vBillNo varchar(50)  =''; vBillDate date; vEnBillType smallint=0; vTargetBranch_ID bigint=-1; vBranch_ID bigint = -1;
	vTargetYear varchar(4) =''; vDocStageID varchar(50) = ''; vOldDocStageID varchar(50) = ''; vStageStatus smallint=0; vOldStageStatus smallint;
        vrl_narrate varchar(250) = ''; vIssueUnitRateLC numeric(18,4) = 0;
BEGIN
    -- **** Get the Existing and new values in the table    
    Select NEW.company_id, NEW.status, OLD.status, NEW.finyear, NEW.doc_date, NEW.stock_id, NEW.fc_type_id, NEW.exch_rate, LEFT(NEW.narration, 500), NEW.doc_type, NEW.branch_id, NEW.target_branch_id, 
        NEW.doc_stage_id, OLD.doc_stage_id, NEW.doc_stage_status, OLD.doc_stage_status, NEW.bill_no, NEW.bill_date
    into vCompany_ID, vStatus, vOldStatus, vFinYear, vDocDate, vVoucher_ID, vFCType_ID, vExchRate, vNarration, vType, vBranch_ID, vTargetBranch_ID, 
        vDocStageID, vOldDocStageID, vStageStatus, vOldStageStatus, vBillNo, vBillDate;

    If vType in ('SP', 'SPG') Then
    	If vOldDocStageID != 'book-purchase' and vDocStageID = 'book-purchase' and vStatus = 3 And vStageStatus > vOldStageStatus then
            perform st.sp_sl_post('st.stock_control' , vFinYear, vVoucher_ID, vDocDate, vNarration, false, 1);
            perform st.sp_sl_lot_post('st.stock_control', vVoucher_ID, '');
            --perform st.sp_sl_post_mat_lc(vVoucher_ID);	
            -- Place rates based on PO rates
            Update st.stock_ledger a
            Set unit_rate_lc = b.unit_rate_lc,
                last_updated = current_timestamp(0)
            From st.sp_sl_post_mat_lc(vVoucher_ID) b
            Where a.stock_ledger_id = b.stock_ledger_id;
        End If;
        If vOldDocStageID = 'book-purchase' and vDocStageID != 'book-purchase' and vStatus = 3 and vOldStatus = 3  And vStageStatus < vOldStageStatus then				
            perform st.sp_sl_unpost(vVoucher_ID);
        End If;
        
        -- ***** Unpost the voucher  
        If vStatus<=4 and vOldStatus=5 then 
            perform ac.sp_gl_unpost(vVoucher_ID);
            perform ap.sp_pl_status_update(vVoucher_ID, vStatus);
            perform ap.sp_pl_unpost(vVoucher_ID);
        End If;

        -- ***** Post the voucher 
        If vStatus=5 and vOldStatus<=4 then
            perform ac.sp_gl_post('st.stock_control' , vFinYear, vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vChequeDetails, vNarration, vType); 
            perform ap.sp_pl_status_update(vVoucher_ID, vStatus);
            perform ap.sp_pl_post('st.stock_control', vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vBillNo, vBillDate, vNarration, vEnBillType);
            --perform st.sp_sl_post_mat_lc(vVoucher_ID);	
            -- Replace rates based on Purchase
            Update st.stock_ledger a
            Set unit_rate_lc = b.unit_rate_lc,
                last_updated = current_timestamp(0)
            From st.sp_sl_post_mat_lc(vVoucher_ID) b
            Where a.stock_ledger_id = b.stock_ledger_id;
        End If;

        -- ***** Change status in Ref Ledger Alloc
        update ac.ref_ledger_alloc
        set status = vStatus
        Where affect_voucher_id = vVoucher_id;	
    End If;

    If vType = 'PR' Or vType = 'PRN' Then
        -- ***** Unpost the voucher 
        If vStatus<=4 and vOldStatus=5 then
            perform ac.sp_gl_unpost(vVoucher_ID);
            perform ap.sp_pl_status_update(vVoucher_ID, vStatus);
            If vType = 'PRN' Then 
                perform ap.sp_pl_unpost(vVoucher_ID);
            End If;
            perform st.sp_sl_unpost (vVoucher_ID);
        End If;

        -- ***** Post the voucher 
        If vStatus=5 and vOldStatus<=4 then
            perform st.sp_sl_post('st.stock_control' , vFinYear, vVoucher_ID, vDocDate, vNarration, false);
            perform ac.sp_gl_post('st.stock_control' , vFinYear, vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vChequeDetails, vNarration, vType); 
            If vType = 'PRN' Then 
                -- Not sure if PR also needs to be included here (Girish)
                vBillNo := NEW.annex_info->>'origin_inv_id';
                vBillDate:= (NEW.annex_info->>'origin_inv_date')::Date;
                perform ap.sp_pl_post('st.stock_control', vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vBillNo, vBillDate, vNarration, vEnBillType);
            End If;
            perform ap.sp_pl_status_update(vVoucher_ID, vStatus);
            perform st.sp_sl_post_mat_lc_issue(vVoucher_ID);	
        End If;
    End If;


    If vType = 'PRV' Then
        -- ***** Unpost the voucher
        If vStatus<=4 and vOldStatus=5 then
            perform ac.sp_gl_unpost(vVoucher_ID);
            perform ap.sp_pl_status_update(vVoucher_ID, vStatus);
            perform st.sp_sl_unpost (vVoucher_ID);
            perform ap.sp_pl_unpost(vVoucher_ID);
        End If;

        -- ***** Post the voucher 
        If vStatus=5 and vOldStatus<=4 then
            -- Post Only if it is actual Sale Return.
            If (NEW.annex_info->>'dcn_type')::Int = 0 Then
                -- All Purchase Returns are of stock_movement_type 3
                perform st.sp_sl_post('st.stock_control' , vFinYear, vVoucher_ID, vDocDate, vNarration, false, 3);
                perform st.sp_sl_post_mat_lc_issue(vVoucher_ID);
            End If;
            perform ac.sp_gl_post('st.stock_control' , vFinYear, vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vChequeDetails, vNarration, vType);
            If NEW.annex_info->'origin_inv_id' Is Not Null And NEW.annex_info->>'origin_inv_id' != '' Then
                vrl_narrate := NEW.annex_info->>'origin_inv_id' || ' dt. ' || to_char((NEW.annex_info->>'origin_inv_date')::timestamp, 'DD-MM-YYYY');
            End if;
            vBillNo := NEW.annex_info->>'origin_inv_id';
            vBillDate:= (NEW.annex_info->>'origin_inv_date')::Date;
            perform ap.sp_pl_post('st.stock_control', vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vBillNo, vBillDate, vrl_narrate, vEnBillType);
            perform ap.sp_pl_status_update(vVoucher_ID, vStatus);
        End If;
    End If;

    If vType = 'SI' or vType = 'SIV' Then
        -- ***** Unpost the voucher 
        If vStatus<=4 and vOldStatus=5 then				
            perform ac.sp_gl_unpost(vVoucher_ID);
            perform ac.sp_gl_unpost(vVoucher_ID || ':BB');				
            perform st.sp_sl_unpost (vVoucher_ID);
            perform st.sp_sl_unpost (vVoucher_ID || ':BB');
            perform ar.sp_rl_status_update(vVoucher_ID, vStatus);
            perform ar.sp_rl_unpost(vVoucher_ID);
        	-- ***** update status in sl_lot_alloc
        	perform st.sp_sl_lot_alloc_status_update(vVoucher_id, vStatus);
        End If;

        -- ***** Post the voucher 
        If vStatus=5 and vOldStatus<=4 then
            perform ac.sp_gl_post('st.stock_control' , vFinYear, vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vChequeDetails, vNarration, vType);
            If Exists(Select * From st.inv_bb Where inv_id = vVoucher_ID) Then
                -- Post Buy Backs in GL and SL
                perform ac.sp_gl_post('st.stock_control' , vFinYear, vVoucher_ID || ':BB', vDocDate, vFCType_ID, vExchRate, vChequeDetails, vNarration, vType);
                perform st.sp_sl_post('st.inv_bb' , vFinYear, vVoucher_ID || ':BB', vDocDate, 'Buy Back(s)', false);
            End If;
            perform ar.sp_rl_status_update(vVoucher_ID, vStatus);
            If vBillNo != '' Then
                vrl_narrate := vBillNo || ' dt. ' || to_char(vBillDate::timestamp, 'DD-MM-YYYY');
            End if;
            perform ar.sp_rl_post('st.stock_control', vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vrl_narrate, vEnBillType);
            perform st.sp_sl_post('st.stock_control' , vFinYear, vVoucher_ID, vDocDate, vNarration, false);
            perform st.sp_sl_post_mat_lc_issue(vVoucher_ID);
        	-- ***** update status in sl_lot_alloc
        	perform st.sp_sl_lot_alloc_status_update(vVoucher_id, vStatus);
        End If;
    End If;

    If vType In ('SR', 'SRN') Then
        -- ***** Unpost the voucher
        If vStatus<=4 and vOldStatus=5 then
            perform ac.sp_gl_unpost(vVoucher_ID);
            perform ar.sp_rl_status_update(vVoucher_ID, vStatus);
            perform st.sp_sl_unpost (vVoucher_ID);
            perform ar.sp_rl_unpost(vVoucher_ID);
        End If;

        -- ***** Post the voucher 
        If vStatus=5 and vOldStatus<=4 then
            -- All Sales Returns are of stock_movement_type 7
            perform st.sp_sl_post('st.stock_control' , vFinYear, vVoucher_ID, vDocDate, vNarration, false, 7);
            perform ac.sp_gl_post('st.stock_control' , vFinYear, vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vChequeDetails, vNarration, vType);
            If NEW.annex_info->'origin_inv_id' Is Not Null And NEW.annex_info->>'origin_inv_id' != '' Then
                vrl_narrate := NEW.annex_info->>'origin_inv_id' || ' dt. ' || to_char((NEW.annex_info->>'origin_inv_date')::timestamp, 'DD-MM-YYYY');
            End if;
            perform ar.sp_rl_post('st.stock_control', vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vrl_narrate, vEnBillType);
            perform ar.sp_rl_status_update(vVoucher_ID, vStatus);
            perform st.sp_sl_post_mat_lc_issue(vVoucher_ID);
        End If;
    End If;

    If vType = 'SRV' Then
        -- ***** Unpost the voucher
        If vStatus<=4 and vOldStatus=5 then
            perform ac.sp_gl_unpost(vVoucher_ID);
            perform ar.sp_rl_status_update(vVoucher_ID, vStatus);
            perform st.sp_sl_unpost (vVoucher_ID);
            perform ar.sp_rl_unpost(vVoucher_ID);
        End If;

        -- ***** Post the voucher 
        If vStatus=5 and vOldStatus<=4 then
            -- Post Only if it is actual Sale Return.
            If (NEW.annex_info->>'dcn_type')::Int = 0 Then
                -- All Sales Returns are of stock_movement_type 7
                perform st.sp_sl_post('st.stock_control' , vFinYear, vVoucher_ID, vDocDate, vNarration, false, 7);
                perform st.sp_sl_post_mat_lc_issue(vVoucher_ID);
            End If;
            perform ac.sp_gl_post('st.stock_control' , vFinYear, vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vChequeDetails, vNarration, vType);
            If NEW.annex_info->'origin_inv_id' Is Not Null And NEW.annex_info->>'origin_inv_id' != '' Then
                vrl_narrate := NEW.annex_info->>'origin_inv_id' || ' dt. ' || to_char((NEW.annex_info->>'origin_inv_date')::timestamp, 'DD-MM-YYYY');
            End if;
            perform ar.sp_rl_post('st.stock_control', vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vrl_narrate, vEnBillType);
            perform ar.sp_rl_status_update(vVoucher_ID, vStatus);
        End If;
    End If;

    If vType = 'ST' Then
        -- ***** Unpost the voucher 
        If vStatus<=4 and vOldStatus=5 then 
        	-- Interbranch Stock transfer need GL posting
            perform ac.sp_gl_unpost(vVoucher_ID);
            delete from st.stock_transfer_park_post 
            where stock_id = vVoucher_ID;
            -- ***** update status in sl_lot_alloc
            perform st.sp_sl_lot_alloc_status_update(vVoucher_id, vStatus);

            -- Delete sl_lot_allocations on unpost
            Delete from st.sl_lot_alloc
            where voucher_id = vVoucher_id;

            -- ***** Unpost from Stock Ledger
            perform st.sp_sl_unpost (vVoucher_ID);
        End If;

        -- ***** Post the voucher 
        If vStatus=5 and vOldStatus<=4 then
        	-- Interbranch Stock transfer need GL posting
            if NEW.vat_type_id = 302 And NEW.tax_amt != 0 Then
            	perform ac.sp_gl_post('st.stock_control' , vFinYear, vVoucher_ID, vDocDate, vFCType_ID, vExchRate, vChequeDetails, vNarration, vType); 
            End If;
            perform st.sp_sl_post('st.stock_control', vFinYear, vVoucher_ID, vDocDate, vNarration, false);
            perform st.sp_sl_post_mat_lc_issue(vVoucher_ID);
            -- ***** update status in sl_lot_alloc
            perform st.sp_sl_lot_alloc_status_update(vVoucher_id, vStatus);
            -- Make available got park post
            Insert into st.stock_transfer_park_post (stock_id, stock_transfer_id, source_branch_id, target_branch_id, status, doc_date, finyear, reference, authorised_by, last_updated)
            Select vVoucher_ID, vVoucher_ID || ':001', vBranch_ID, vTargetBranch_ID, 0, null, '', '', '', current_timestamp(0);
        End If;
    End If;

    If vType = 'SC' Then
        -- ***** Unpost the voucher 
        If vStatus<=4 and vOldStatus=5 then
            perform st.sp_sl_unpost (vVoucher_ID);
            -- ***** update status in sl_lot_alloc
            perform st.sp_sl_lot_alloc_status_update(vVoucher_id, vStatus);
        End If;	

            -- ***** Post the voucher 
        If vStatus=5 and vOldStatus<=4 then
            perform st.sp_sl_post('st.stock_control' , vFinYear, vVoucher_ID, vDocDate, vNarration, false);
        	-- ***** update status in sl_lot_alloc
            perform st.sp_sl_post_mat_lc_issue(vVoucher_ID);
            perform st.sp_sl_lot_alloc_status_update(vVoucher_id, vStatus);
        End If;
    End If;
    
    If vType = 'SAN' Then
        -- ***** Unpost the voucher 
        If vStatus<=4 and vOldStatus=5 then
            perform st.sp_sl_unpost (vVoucher_ID);
            -- ***** update status in sl_lot_alloc
            perform st.sp_sl_lot_alloc_status_update(vVoucher_id, vStatus);
        End If;	

            -- ***** Post the voucher 
        If vStatus=5 and vOldStatus<=4 then
            if (NEW.annex_info->>'adj_opbl') Then
                vDocDate := vDocDate - integer '1';
            End If;
            perform st.sp_sl_post('st.stock_control' , vFinYear, vVoucher_ID, vDocDate, vNarration, false);
            Update st.stock_ledger a
            Set unit_rate_lc = coalesce(b.unit_rate_lc, 0)
            From st.sp_san_mat_lc(vVoucher_id) b
            Where a.stock_ledger_id = b.stock_ledger_id;
            -- ***** Post receipt entry in sl_lot
            perform st.sp_sl_lot_post('st.stock_control', vVoucher_ID, '');
            -- ***** update status in sl_lot_alloc
            perform st.sp_sl_lot_alloc_status_update(vVoucher_id, vStatus);
        End If;
    End If;

    If vType In ('LTN')  Then
        -- ***** Unpost the voucher 
        If vStatus<=4 and vOldStatus=5 then
            perform st.sp_sl_unpost (vVoucher_ID);
        	-- ***** update status in sl_lot_alloc
        	perform st.sp_sl_lot_alloc_status_update(vVoucher_id, vStatus);
        End If;	

            -- ***** Post the voucher 
        If vStatus=5 and vOldStatus<=4 then
            perform st.sp_sl_post('st.stock_control' , vFinYear, vVoucher_ID, vDocDate, vNarration, false, 5);
            -- This will update unit_rate_lc for issued qty
            perform st.sp_sl_post_mat_lc_issue(vVoucher_ID);

            -- Update unit_rate_lc for received items
            DECLARE	
                    cur_stock_tran Cursor For (Select a.stock_ledger_id, a.material_id, a.vch_tran_id from st.stock_ledger a where a.voucher_id = vVoucher_id and a.received_qty > 0);
                Begin
                    For tran In cur_stock_tran Loop 
                        --Raise exception 'cur_stock_tran.stock_tran_id - %', tran.stock_tran_id; --;
                        Select unit_rate_lc into vIssueUnitRateLC 
                        from st.stock_ledger 
                        Where vch_tran_id = tran.vch_tran_id
                                    and issued_qty > 0;

                        Update st.stock_ledger a
                        Set unit_rate_lc = vIssueUnitRateLC
                        Where a.stock_ledger_id = tran.stock_ledger_id;

                    End loop;
                End;
            -- ***** update status in sl_lot_alloc
            perform st.sp_sl_lot_alloc_status_update(vVoucher_id, vStatus);

            -- ***** Post records in sl_lot
            perform st.sp_sl_lot_shift(vVoucher_ID);
        End If;
    End If;

    If vType In ('PTN')  Then     
    	If vOldDocStageID != 'confirm-issue' and vDocStageID = 'confirm-issue' And vStageStatus > vOldStageStatus then
        	-- ***** update status in sl_lot_alloc
            perform st.sp_sl_lot_alloc_status_update(vVoucher_id, vStatus);
            perform st.sp_sl_post('st.stock_control', vFinYear, vVoucher_ID || ':I', vDocDate, vNarration, false, 5); 
            
            -- Update voucher_id:I with voucher_id
            Update st.stock_ledger
            set voucher_id = vVoucher_ID
            where voucher_id = (vVoucher_ID || ':I');
            
            -- This will update unit_rate_lc for issued qty
            perform st.sp_sl_post_mat_lc_issue(vVoucher_ID);
            
            -- ***** Post records in sl_lot
            perform st.sp_sl_lot_shift(vVoucher_ID);
            
        End If;
        
        If vOldDocStageID = 'confirm-issue' and vDocStageID != 'confirm-issue' and vStatus = 3 and vOldStatus = 3  And vStageStatus < vOldStageStatus then				
            perform st.sp_sl_unpost(vVoucher_ID);
        End If;
        
    	-- ***** Unpost the voucher  
    	If vStatus<=4 and vOldStatus=5 then	        
			If (NEW.annex_info->>'is_ib')::boolean Then  
                delete from st.stock_transfer_park_post 
                where stock_id = vVoucher_ID;            	
         	Else
                -- ***** update status in sl_lot_alloc
                perform st.sp_sl_lot_alloc_status_update(vVoucher_id, vStatus);

                -- Step 2: Remove entries from st.sl_lot
                Delete From st.sl_lot a
                Using st.stock_ledger b 
                Where a.sl_id = b.stock_ledger_id And b.voucher_id = vVoucher_ID
                        And b.received_qty > 0;

                Delete from st.stock_ledger
                where voucher_id = vVoucher_id
                        And received_qty > 0;
            End If;
    	End if;
        -- Post the voucher
    	If vStatus=5 and vOldStatus<=4 then 
            -- ***** update status in sl_lot_alloc
            perform st.sp_sl_lot_alloc_status_update(vVoucher_id, vStatus);
            If (NEW.annex_info->>'is_ib')::boolean Then             	
                -- Make available got park post
                Insert into st.stock_transfer_park_post (stock_id, stock_transfer_id, source_branch_id, target_branch_id, status, doc_date, finyear, reference, authorised_by, last_updated)
                Select vVoucher_ID, vVoucher_ID || ':001', vBranch_ID, vTargetBranch_ID, 0, null, '', '', '', current_timestamp(0);
            Else
                perform st.sp_sl_post('st.stock_control', vFinYear, vVoucher_ID, vDocDate, vNarration, false, 5); 
                -- Update unit_rate_lc for received qty
                DECLARE	
                    cur_stock_tran Cursor For (Select a.stock_ledger_id, a.material_id, a.vch_tran_id from st.stock_ledger a where a.voucher_id = vVoucher_id and a.received_qty > 0);
                Begin
                    For tran In cur_stock_tran Loop 
                        --Raise exception 'cur_stock_tran.stock_tran_id - %', tran.stock_tran_id; --;
                        Select unit_rate_lc into vIssueUnitRateLC 
                        from st.stock_ledger 
                        Where vch_tran_id = tran.vch_tran_id
                                and issued_qty > 0;

                        Update st.stock_ledger a
                        Set unit_rate_lc = vIssueUnitRateLC
                        Where a.stock_ledger_id = tran.stock_ledger_id;

                    End loop;
                End;            
                -- ***** Post records in sl_lot
                perform st.sp_sl_lot_shift(vVoucher_ID);
            End If;
        End If;
    End If;
    If vType = 'MRTN'  Then
    	If vOldDocStageID != 'issued' and vDocStageID = 'issued' And vStageStatus > vOldStageStatus then
        	--raise exception 'vVoucher_ID - %', vVoucher_ID;
            perform st.sp_sl_post('st.stock_control' , vFinYear, vVoucher_ID || ':I', vDocDate, vNarration, false, 5);
            perform st.sp_sl_lot_post('st.stock_control', vVoucher_ID, '');
        End If;
        
        If vOldDocStageID = 'issued' and vDocStageID != 'issued' and vStatus = 3 and vOldStatus = 3  And vStageStatus < vOldStageStatus then				
            perform st.sp_sl_unpost(vVoucher_ID);
        End If;
        
        -- ***** Unpost the voucher 
        If vStatus<=4 and vOldStatus=5 then
            Delete from st.stock_ledger
            where voucher_id = vVoucher_id
            		And received_qty > 0;
        	-- ***** update status in sl_lot_alloc
        	perform st.sp_sl_lot_alloc_status_update(vVoucher_id, vStatus);
        End If;	

            -- ***** Post the voucher 
        If vStatus=5 and vOldStatus<=4 then
            perform st.sp_sl_post('st.stock_control' , vFinYear, vVoucher_ID, vDocDate, vNarration, false, 5);
            -- This will update unit_rate_lc for issued qty
            perform st.sp_sl_post_mat_lc_issue(vVoucher_ID);

            -- Update unit_rate_lc for received items
            DECLARE	
                    cur_stock_tran Cursor For (Select a.stock_ledger_id, a.material_id, a.vch_tran_id from st.stock_ledger a where a.voucher_id = vVoucher_id and a.received_qty > 0);
                Begin
                    For tran In cur_stock_tran Loop 
                        --Raise exception 'cur_stock_tran.stock_tran_id - %', tran.stock_tran_id; --;
                        Select unit_rate_lc into vIssueUnitRateLC 
                        from st.stock_ledger 
                        Where vch_tran_id = tran.vch_tran_id
                                    and issued_qty > 0;

                        Update st.stock_ledger a
                        Set unit_rate_lc = vIssueUnitRateLC
                        Where a.stock_ledger_id = tran.stock_ledger_id;

                    End loop;
                End;
            -- ***** update status in sl_lot_alloc
            perform st.sp_sl_lot_alloc_status_update(vVoucher_id, vStatus);
        End If;
    End If;

    If vType = 'JWR' Then
        -- ***** Unpost the voucher 
        If vStatus<=4 and vOldStatus=5 then
            perform st.sp_sl_unpost (vVoucher_ID);
        End If;	

            -- ***** Post the voucher 
        If vStatus=5 and vOldStatus<=4 then
            perform st.sp_sl_post('st.stock_control' , vFinYear, vVoucher_ID, vDocDate, vNarration, false);
            
            -- ***** Post entry in sl_lot
            perform st.sp_sl_lot_post('st.stock_control', vVoucher_ID, '');
        End If;
    End If;

    If vType = 'MCN' Then
        -- ***** Unpost the voucher 
        If vStatus<=4 and vOldStatus=5 then
            perform st.sp_sl_unpost (vVoucher_ID);
        End If;	

            -- ***** Post the voucher 
        If vStatus=5 and vOldStatus<=4 then
            perform st.sp_sl_post('st.stock_control' , vFinYear, vVoucher_ID, vDocDate, vNarration, false);
            -- This will update unit_rate_lc for issued qty
            perform st.sp_sl_post_mat_lc_issue(vVoucher_ID);
            -- This will update Received Value
            Update st.stock_ledger a
            Set unit_rate_lc = coalesce(b.unit_rate_lc, 0)
            From st.mat_mcn_lc(vVoucher_id) b
            Where a.stock_ledger_id = b.stock_ledger_id;
        End If;
    End If;


    --	Import opening Balance for next fin year if exists
    Select COALESCE(a.finyear_code, '') into vTargetYear
    From sys.finyear a
    Where a.year_begin = (Select (b.year_end + integer '1') from sys.finyear b where b.finyear_code =vFinYear);

    If vTargetYear != '' Then
        perform st.sp_import_stock_opbal(vCompany_ID, vTargetYear, vVoucher_ID);
    End If;

    RETURN NEW;
END
$BODY$
LANGUAGE plpgsql;

?==?
-- Trigger on vch control table
CREATE TRIGGER trg_stock_post
  AFTER UPDATE
  ON st.stock_control
  FOR EACH ROW
  EXECUTE PROCEDURE st.trgporc_stock_post();

?==?
create or replace function st.sp_st_part_post_update(pstock_id varchar(50), psource_branch_id bigint, ptarget_branch_id bigint, pstatus smallint, pdoc_date date, 
			pfinyear varchar(4), preference varchar(50), pauthorised_by varchar(50))
RETURNS void as 
$BODY$ 
Begin	
		update st.stock_transfer_park_post
		set source_branch_id = psource_branch_id, 
			target_branch_id = ptarget_branch_id, 
			status = pstatus, 
			doc_date = pdoc_date, 
			finyear = pfinyear, 
			reference = preference, 
			authorised_by = pauthorised_by
		Where stock_id = pstock_id;
END;
$BODY$
LANGUAGE plpgsql;

?==?
create or replace function st.sp_get_st_for_part_post(pstatus smallint, pyear_begin date, pyear_end date, ptarget_branch_id bigint)
RETURNS TABLE  
(
	stock_id varchar(50),
	st_date date,
	source_branch_id bigint,
	target_branch_id bigint,
	posted smallint,
	doc_date date,
	finyear varchar(4),
	reference varchar(50),
	authorised_by varchar(50)
)
AS
$BODY$ 
Begin	
	DROP TABLE IF EXISTS st_temp;
	CREATE TEMP TABLE st_temp(
		stock_id varchar(50),
		st_date date,
		source_branch_id bigint,
		target_branch_id bigint,
		posted smallint,
		doc_date date,
		finyear varchar(4),
		reference varchar(50),
		authorised_by varchar(50)
	);

	If pstatus = 1 Then
		Insert into st_temp(stock_id, st_date, source_branch_id, target_branch_id, 
				posted, doc_date, finyear, reference, authorised_by)
		Select a.stock_id, b.doc_date, a.source_branch_id, a.target_branch_id, 
			case a.status when 0 then 0 else 1 End as status, case when a.doc_date is null then '1970-01-01' else a.doc_date end, a.finyear, a.reference, a.authorised_by
		From st.stock_transfer_park_post a
		inner Join st.stock_control b on a.stock_id = b.stock_id
		Where a.status = 5 
			and a.doc_date between pyear_begin and pyear_end
			and a.target_branch_id = ptarget_branch_id;
	Else
		Insert into st_temp(stock_id, st_date, source_branch_id, target_branch_id, 
				posted, doc_date, finyear, reference, authorised_by)
		Select a.stock_id, b.doc_date, a.source_branch_id, a.target_branch_id, 
			case a.status when 0 then 0 else 1 End as status, case when a.doc_date is null then '1970-01-01' else a.doc_date end, a.finyear, a.reference, a.authorised_by
		From st.stock_transfer_park_post a
		Inner Join st.stock_control b on a.stock_id = b.stock_id 
		Where a.status = 0
			and b.doc_date <= pyear_end
			and a.target_branch_id = ptarget_branch_id;
	End If;
	
	return query 
	select a.stock_id, a.st_date, a.source_branch_id, a.target_branch_id, a.posted, a.doc_date, a.finyear, a.reference, a.authorised_by
	from st_temp a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
-- Procedure to call from trigger
CREATE or REPLACE FUNCTION st.trgporc_stock_transfer_park_post() 
RETURNS trigger 
AS $BODY$
Declare vFinYear varchar(4); vVoucher_ID varchar(50)=''; vFCType_ID BigInt=0; vExchRate Numeric(18,6)=1; vDocDate Date; vIBVoucher_ID varchar(50);
	vStatus smallint=0; vOldStatus smallint; vChequeDetails varchar(250)=''; vNarration Varchar(500)=''; vTaxAmt numeric(18,0) = 0;
	vType varchar(40)=''; vBillNo varchar(50)  =''; vBillDate date; vEnBillType smallint=0; vTargetBranch_ID bigint=-1; vBranch_ID bigint = -1; vVatType_ID bigint = -1;
BEGIN
            -- **** Get the Existing and new values in the table    
            Select NEW.status, OLD.status, NEW.finyear, NEW.doc_date, NEW.stock_id
            into vStatus, vOldStatus, vFinYear, vDocDate, vVoucher_ID;
            vIBVoucher_ID :=  vVoucher_ID || ':AJ';

            Select a.narration, a.fc_type_id, a.exch_rate, a.doc_type, a.vat_type_id, a.tax_amt
				into vNarration, vFCType_ID, vExchRate, vType, vVatType_ID, vTaxAmt
            From st.stock_control a
            where a.stock_id = vVoucher_ID;

            -- ***** Unpost the voucher  
            If vStatus<=4 and vOldStatus=5 then
            perform ac.sp_gl_unpost(vIBVoucher_ID);
            perform st.sp_sl_unpost (vIBVoucher_ID);
            End if;

            If vStatus=5 and vOldStatus<=4 then
                -- Interbranch Stock transfer need GL posting
                if vVatType_ID = 302  And vTaxAmt != 0 And vType = 'ST' Then
                    perform ac.sp_gl_post('st.stock_transfer_park_post', vFinYear, vIBVoucher_ID, vDocDate, vFCType_ID, vExchRate, vChequeDetails, vNarration, vType); 
                End If;
                --raise exception 'IBVoucher %', vIBVoucher_ID;
                perform st.sp_sl_post('st.stock_transfer_park_post' , vFinYear, vIBVoucher_ID, vDocDate, vNarration, false);
                perform st.sp_sl_lot_post('st.stock_transfer_park_post', vIBVoucher_ID, '');

                Update st.stock_ledger a
                set reference_date = a.doc_date
                Where a.voucher_id = vIBVoucher_ID;
            
            End IF;
	RETURN NEW;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
-- Trigger on vch control table
CREATE TRIGGER trg_stock_transfer_park_post
  AFTER UPDATE
  ON st.stock_transfer_park_post
  FOR EACH ROW
  EXECUTE PROCEDURE st.trgporc_stock_transfer_park_post();

?==?
CREATE OR REPLACE FUNCTION st.sp_material_opbl_ref_add_update(inout pstock_ledger_id uuid, pcompany_id bigint, pbranch_id bigint, pfinyear varchar(4),
				pdoc_date date, pmaterial_id bigint, 
				pstock_location_id bigint, puom_id bigint, puom_qty numeric(18,4), preceived_qty numeric(18,4), punit_rate_lc numeric(18,4), pnarration varchar(500), paccount_id bigint)
  RETURNS uuid AS
$BODY$
Begin
	if exists(Select * from st.stock_ledger where stock_ledger_id=pstock_ledger_id) Then
		Update st.stock_ledger
		Set voucher_id = 'OPBL/'||pfinyear||'/'||pstock_location_id||'/'|| pmaterial_id, 
			branch_id = pbranch_id,
			doc_date = pdoc_date,
			finyear = pfinyear,
			material_id = pmaterial_id,	
			stock_location_id = pstock_location_id, 
			uom_id = puom_id,
			uom_qty = puom_qty,
			received_qty = preceived_qty, 
			unit_rate_lc = punit_rate_lc, 
			narration = pnarration,
			account_id = paccount_id
		Where stock_ledger_id=pstock_ledger_id;
			
	Else
		pstock_ledger_id:=md5('OPBL/'||pfinyear||'/'||pstock_location_id||'/'|| pmaterial_id)::uuid;
		Insert into st.stock_ledger(stock_ledger_id, company_id, branch_id, finyear, voucher_id, vch_tran_id, doc_date, material_id, stock_location_id, 
						reference_id, reference_tran_id, reference_date, narration, uom_id, uom_qty, received_qty, issued_qty, unit_rate_lc, 
                                                stock_movement_type_id, inserted_on, last_updated, is_opbl, account_id)
		select pstock_ledger_id, pcompany_id, pbranch_id, pfinyear, 'OPBL/'||pfinyear||'/'||pstock_location_id||'/'|| pmaterial_id, '', pdoc_date, pmaterial_id, pstock_location_id, 
						'', '', null, pnarration, puom_id, puom_qty, preceived_qty, 0, punit_rate_lc, 
                                                -1, current_timestamp(0), current_timestamp(0), true, paccount_id;
	End If;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
Create or Replace Function st.sp_import_stock_opbal(pcompany_id BigInt, ptarget_year Varchar(4), pvoucher_id Varchar(20) = null)
Returns Void
AS
$BODY$
Declare vfrom_date Date; vto_date Date; vprevious_year Varchar(4);
Begin
	-- Fetch Previous FinYear details
	Select (a.year_begin - Interval '1 day')::date year_begin, a.year_end, a.finyear_code
	From sys.finyear a Into vfrom_date, vto_date, vprevious_year
	Where a.year_end = (Select b.year_begin - Interval '1 day' 
			  From sys.finyear b
			  Where b.finyear_code=ptarget_year)
		And a.company_id=pcompany_id;
	
	--Exit if previous year is not found
	if vprevious_year Is Null Then
		Return;
	End If;

	-- Step 1: Extract Net Balance Qty from Stock Ledger
	Drop Table If Exists sl_bal_temp;
	Create temp table sl_bal_temp
	(	sl_no BigSerial,
		branch_id BigInt,
		stock_location_id BigInt,
		material_id BigInt,
		balance_qty Numeric(18,3),
		unit_rate_lc Numeric(18,4)
	);

	If pvoucher_id Is Null Then
		-- We pick up all records for the finyear
		Insert Into sl_bal_temp(branch_id, stock_location_id, material_id, balance_qty, unit_rate_lc)
		Select branch_id, stock_location_id, material_id,
			Sum(received_qty-issued_qty) as balance_qty, 0
		From st.stock_ledger
		Where finyear=vprevious_year And doc_date Between vfrom_date And vto_date
			And company_id=pcompany_id
		Group By branch_id, stock_location_id, material_id
                Having Sum(received_qty-issued_qty) >= 0
		Order by material_id, branch_id;
	Else 
		-- We pick up only materials affected by voucher_id
		Insert Into sl_bal_temp(branch_id, stock_location_id, material_id, balance_qty, unit_rate_lc)
		Select branch_id, stock_location_id, material_id,
			Sum(received_qty-issued_qty) as balance_qty, 0
		From st.stock_ledger
		Where finyear=vprevious_year And doc_date Between vfrom_date And vto_date
			And company_id=pcompany_id
			And material_id in (Select b.material_id from st.stock_ledger b
					    Where b.voucher_id=pvoucher_id And b.finyear=vprevious_year
					    Group By b.material_id)
		Group By branch_id, stock_location_id, material_id
                Having Sum(received_qty-issued_qty) >= 0
		Order by material_id, branch_id;
	End If;

	--	Step 2: Extract the WAC rate for all materials in each branch and Update the Rate
	Declare 
		cursor_branch No Scroll Cursor 
		For Select branch_id From sl_bal_temp group by branch_id;
	Begin
		For branch In cursor_branch Loop

			-- Update material valuation
			Update sl_bal_temp a
			Set unit_rate_lc=b.rate
			From st.fn_material_balance_wac(pcompany_id, branch.branch_id, 0, vprevious_year, vto_date) b
			Where a.material_id=b.material_id And a.branch_id=b.branch_id;

		End Loop;
	End;

	
	-- Prepare records for insert
	Drop Table If Exists sl_temp;
	Create Temp Table sl_temp 
	(	  stock_ledger_id uuid NOT NULL,
		  company_id bigint NOT NULL,
		  branch_id bigint NOT NULL,
		  finyear character varying(4) NOT NULL,
		  voucher_id character varying(50) NOT NULL,
		  vch_tran_id character varying(50) NOT NULL,
		  doc_date date NOT NULL,
		  material_id bigint NOT NULL,
		  stock_location_id bigint NOT NULL,
		  reference_id character varying(50) NOT NULL,
		  reference_tran_id character varying(50) NOT NULL,
		  reference_date date,
		  narration character varying(500) NOT NULL,
		  uom_id bigint NOT NULL,
		  uom_qty numeric(18,4) NOT NULL,
		  received_qty numeric(18,4) NOT NULL,
		  issued_qty numeric(18,4) NOT NULL,
		  unit_rate_lc numeric(18,4) NOT NULL,
		  stock_movement_type_id bigint NOT NULL,
		  inserted_on timestamp without time zone NOT NULL,
		  last_updated timestamp without time zone NOT NULL,
		  is_opbl boolean DEFAULT false,
		  account_id bigint NOT NULL DEFAULT (-1)
	);

	INSERT INTO sl_temp(stock_ledger_id, company_id, branch_id, finyear, voucher_id, vch_tran_id, doc_date, material_id, stock_location_id, 
		reference_id, reference_tran_id, reference_date, narration, uom_id, uom_qty, received_qty, issued_qty, unit_rate_lc,
		stock_movement_type_id, inserted_on, last_updated, is_opbl, account_id)
	Select md5('OPBL/'||ptarget_year||a.stock_location_id||a.material_id)::uuid, 
		pcompany_id, a.branch_id, ptarget_year, 'OPBL/'||ptarget_year||'/'||a.stock_location_id||'/'||a.material_id, '', vto_date, a.material_id, a.stock_location_id,
		'', '', Null, 'Opening Balance', -1, 0, a.balance_qty, 0, a.unit_rate_lc, 
		-1, vto_date, current_timestamp(0), true, b.inventory_account_id
	From sl_bal_temp a
	Inner Join st.material b On a.material_id=b.material_id;
	
	-- Delete entries where Target Year and Date match.
	If pvoucher_id Is Null Then
		Delete From st.stock_ledger a
		Where finyear=ptarget_year And doc_date=vto_date And Left(voucher_id, 5)='OPBL/';
	Else
		Delete From st.stock_ledger a
		Where finyear=ptarget_year And doc_date=vto_date And Left(voucher_id, 5)='OPBL/'
			And material_id in (Select b.material_id from st.stock_ledger b
					    Where b.voucher_id=pvoucher_id And b.finyear=vprevious_year
					    Group By b.material_id);
	End If;
	
	--	Insert Into Stock Ledger the new entries
	INSERT INTO st.stock_ledger(stock_ledger_id, company_id, branch_id, finyear, voucher_id, vch_tran_id, doc_date, material_id, stock_location_id, 
		reference_id, reference_tran_id, reference_date, narration, uom_id, uom_qty, received_qty, issued_qty, unit_rate_lc,
		stock_movement_type_id, inserted_on, last_updated, is_opbl, account_id)
	Select stock_ledger_id, company_id, branch_id, finyear, voucher_id, vch_tran_id, doc_date, material_id, stock_location_id, 
		reference_id, reference_tran_id, reference_date, narration, uom_id, uom_qty, received_qty, issued_qty, unit_rate_lc,
		stock_movement_type_id, inserted_on, last_updated, is_opbl, account_id
	From sl_temp;

End;
$BODY$
Language plpgsql;

?==?

CREATE OR REPLACE FUNCTION st.sp_get_matinfo(
    IN pbar_code character varying,
    IN pmat_id bigint,
    IN pvat_type_id bigint,
    IN pstock_loc_id bigint,
    IN pdoc_date date,
    IN pfinyear character varying)
  RETURNS TABLE(bar_code character varying, mat_id bigint, mat_name character varying, material_type_id bigint, mt_name character varying, uom_id bigint, 
                uom character varying, sale_rate numeric, disc_pcnt numeric, tax_schedule_id bigint, tax_schedule_desc character varying, en_tax_type smallint, 
                tax_pcnt numeric, bal_qty numeric, has_qc boolean) AS
$BODY$
Declare
	vbar_code Varchar(20):=''; vmat_id BigInt:=-1; vmat_name character varying:=''; vmt_id BigInt:=-1; vmt_name character varying:=''; 
	vuom_id BigInt:=-1; vuom character varying:=''; vhas_qc boolean:=false;
	vsale_rate Numeric(18,4):=0; vdisc_pcnt Numeric(5,2):=0;
	vtax_schedule_id BigInt:=-1; vtax_schedule_desc character varying:=''; ven_tax_type SmallInt:=-1; vtax_pcnt Numeric(18,4):=0; 
        vapply_tax_schedule_id BigInt:=-1; vbal_qty Numeric(18,4):=0;
Begin
	-- By Girish
	-- This Procedure is used in POS for fetching a single material information
	If pmat_id = -1 Then 
		-- mat id unknown. Therefore query using bar code
		Select a.material_id, a.material_name, a.material_code, a.material_type_id,
			(a.annex_info->'sale_price'->'sp_calc'->>'fixed_pu')::numeric,
			(a.annex_info->'sale_price'->'sp_calc'->>'disc_pcnt')::numeric,
			(a.annex_info->'sale_price'->>'tax_schedule_id')::bigint,
            (a.annex_info->'qc_info'->>'has_qc')::boolean
			Into vmat_id, vmat_name, vbar_code, vmt_id, vsale_rate, vdisc_pcnt, vtax_schedule_id, vhas_qc
		From st.material a
		Where a.material_code = pbar_code;
	Else 
		-- query using mat id
		Select a.material_id, a.material_name, a.material_code, a.material_type_id,
			(a.annex_info->'sale_price'->'sp_calc'->>'fixed_pu')::numeric,
			(a.annex_info->'sale_price'->'sp_calc'->>'disc_pcnt')::numeric,
			(a.annex_info->'sale_price'->>'tax_schedule_id')::bigint,
            (a.annex_info->'qc_info'->>'has_qc')::boolean
			Into vmat_id, vmat_name, vbar_code, vmt_id, vsale_rate, vdisc_pcnt, vtax_schedule_id, vhas_qc
		From st.material a
		Where a.material_id = pmat_id;
	End If;
	-- Proceed only if vmat_id was found
	If vmat_id != -1 Then
                -- Get tax schedule based on vat type
		Select a.apply_tax_schedule_id Into vapply_tax_schedule_id
		From tx.vat_type a
		Where a.vat_type_id = pvat_type_id And a.apply_item_tax = false;
		if vapply_tax_schedule_id != -1 Then
			vtax_schedule_id := vapply_tax_schedule_id;
		End if;

		-- Get material_type Info
		Select a.material_type Into vmt_name
		From st.material_type a
		Where a.material_type_id = vmt_id;

		-- Get Uom Info
		Select a.uom_id, a.uom_desc Into vuom_id, vuom
		From st.uom a
		Where a.material_id = vmat_id And a.uom_type_id = 101;

		-- Get Tax Info
		Select b.description, a.en_tax_type, a.tax_perc Into vtax_schedule_desc, ven_tax_type, vtax_pcnt
		From tx.tax_detail a
		Inner Join tx.tax_schedule b On a.tax_schedule_id = b.tax_schedule_id
		Where b.tax_schedule_id = vtax_schedule_id And step_id = 1;

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
	End If;

	-- generate output
        Return Query
	Select vbar_code, vmat_id, vmat_name, vmt_id, vmt_name, vuom_id, vuom, vsale_rate, vdisc_pcnt, vtax_schedule_id, vtax_schedule_desc, ven_tax_type, vtax_pcnt, vbal_qty, vhas_qc;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.sp_get_matinfo_purchase(
    IN pbar_code character varying,
    IN pmat_id bigint,
    IN pvat_type_id bigint)
RETURNS TABLE
(	bar_code character varying, mat_id bigint, mat_name character varying, material_type_id bigint, mt_name character varying, uom_id bigint, uom character varying, 
	tax_schedule_id bigint, tax_schedule_desc Character Varying, en_tax_type smallint, tax_pcnt numeric)
AS
$BODY$
Declare
	vbar_code Varchar(20):=''; vmat_id BigInt:=-1; vmat_name character varying:=''; vmt_id BigInt:=-1; vmt_name character varying:=''; 
	vuom_id BigInt:=-1; vuom character varying:='';
	vtax_schedule_id BigInt:=-1; vtax_schedule_desc character varying:=''; ven_tax_type SmallInt:=-1; vtax_pcnt Numeric(18,4):=0;
	vapply_tax_schedule_id BigInt:=-1;
Begin
	-- By Girish
	-- This Procedure is used in POS for fetching a single material information
	If pmat_id = -1 Then 
		-- mat id unknown. Therefore query using bar code
		Select a.material_id, a.material_name, a.material_code, a.material_type_id,
			(a.annex_info->'supp_info'->>'tax_schedule_id')::bigint
			Into vmat_id, vmat_name, vbar_code, vmt_id, vtax_schedule_id
		From st.material a
		Where a.material_code = pbar_code;
	Else 
		-- query using mat id
		Select a.material_id, a.material_name, a.material_code, a.material_type_id,
			(a.annex_info->'supp_info'->>'tax_schedule_id')::bigint
			Into vmat_id, vmat_name, vbar_code, vmt_id, vtax_schedule_id
		From st.material a
		Where a.material_id = pmat_id;
	End If;
	-- Proceed only if vmat_id was found
	If vmat_id != -1 Then
		-- Get tax schedule based on vat type
		Select a.apply_tax_schedule_id Into vapply_tax_schedule_id
		From tx.vat_type a
		Where a.vat_type_id = pvat_type_id And a.apply_item_tax = false;
		if vapply_tax_schedule_id != -1 Then
			vtax_schedule_id := vapply_tax_schedule_id;
		End if;
	
		-- Get Mfg Info
		Select a.material_type Into vmt_name
		From st.material_type a
		Where a.material_type_id = vmt_id;

		-- Get Uom Info
		Select a.uom_id, a.uom_desc Into vuom_id, vuom
		From st.uom a
		Where a.material_id = vmat_id And a.is_base = true;

		-- Get Tax Info
		Select b.description, a.en_tax_type, a.tax_perc Into vtax_schedule_desc, ven_tax_type, vtax_pcnt
		From tx.tax_detail a
		Inner Join tx.tax_schedule b On a.tax_schedule_id = b.tax_schedule_id
		Where b.tax_schedule_id = vtax_schedule_id And step_id = 1;
		
	End If;

	-- generate output 
	Return Query
	Select vbar_code, vmat_id, vmat_name, vmt_id, vmt_name, vuom_id, vuom, vtax_schedule_id, vtax_schedule_desc, ven_tax_type, vtax_pcnt;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.sp_sale_rate_update(pprice_type varchar(4), pmaterial_id bigint, psr_pu Numeric(18,4), pdisc_pcnt Numeric(18,4))
  RETURNS void AS
$BODY$
Begin	
	if pprice_type = 'FP' then
    	-- Update fixed_pu
		update st.material
		set annex_info = jsonb_set(annex_info, '{sale_price, sp_calc, fixed_pu}', (psr_pu::Varchar)::jsonb, true)
		where material_id = pmaterial_id;
		-- update disc_pcnt
		update st.material
		set annex_info = jsonb_set(annex_info, '{sale_price, sp_calc, disc_pcnt}', (pdisc_pcnt::Varchar)::jsonb, true)
		where material_id = pmaterial_id;
	End if;
	if pprice_type = 'WAC' then
		-- Update markup_pu
		update st.material
		set annex_info = jsonb_set(annex_info, '{sale_price, wac_calc, markup_pu}', (psr_pu::Varchar)::jsonb, true)
		where material_id = pmaterial_id;
		-- Update markup_pcnt
        update st.material
		set annex_info = jsonb_set(annex_info, '{sale_price, wac_calc, markup_pcnt}', (pdisc_pcnt::Varchar)::jsonb, true)
		where material_id = pmaterial_id;
	End if;
	if pprice_type = 'LP' then
		-- Update markup_pu
		update st.material
		set annex_info = jsonb_set(annex_info, '{sale_price, lp_calc, markup_pu}', (psr_pu::Varchar)::jsonb, true)
		where material_id = pmaterial_id;
		-- Update markup_pcnt
		update st.material
		set annex_info = jsonb_set(annex_info, '{sale_price, lp_calc, markup_pcnt}', (pdisc_pcnt::Varchar)::jsonb, true)
		where material_id = pmaterial_id;
	End if;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.sp_mat_bal_update_util(pcompany_id bigint, pbranch_id bigint, pfinyear varchar(4), pdoc_date date, pmaterial_id bigint, 
	pstock_location_id bigint, pop_bal_qty numeric(18,4), punit_rate_lc numeric(18,4))
RETURNS Void 
AS
$BODY$
Declare
	vsl_id Varchar(50) := 'OPBL/'||pfinyear||'/'||pstock_location_id||'/'||pmaterial_id;
        vsl_uuid uuid := md5(vsl_id)::uuid; vuom_id BigInt := -1; viac_id BigInt := -1;
Begin
        If exists(Select * from st.stock_ledger where stock_ledger_id=vsl_uuid) Then
		Update st.stock_ledger
		Set uom_qty = pop_bal_qty,
                    received_qty = pop_bal_qty,
                    -- unit_rate_lc = punit_rate_lc﻿
                    last_updated = current_timestamp(0)
		Where stock_ledger_id=vsl_uuid;
	Else
                Select uom_id Into vuom_id From st.uom Where material_id = pmaterial_id And is_base;
                Select inventory_account_id Into viac_id From st.material Where material_id = pmaterial_id;
		Insert into st.stock_ledger(stock_ledger_id, company_id, branch_id, finyear, voucher_id, vch_tran_id, doc_date, material_id, stock_location_id, 
                        reference_id, reference_tran_id, reference_date, narration, uom_id, uom_qty, received_qty, issued_qty, unit_rate_lc, 
                        stock_movement_type_id, inserted_on, last_updated, is_opbl, account_id)
		select vsl_uuid, pcompany_id, pbranch_id, pfinyear, vsl_id, '', pdoc_date, pmaterial_id, pstock_location_id, 
                        '', '', null, 'Opening Balance', vuom_id, pop_bal_qty, pop_bal_qty, 0, punit_rate_lc, 
                        -1, current_timestamp(0), current_timestamp(0), true, viac_id;
	End If;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE function st.sp_sl_lot_alloc_status_update(pvoucher_id Varchar(50), pstatus smallint)
Returns void as
$Body$
Begin	 

    /*
    -- Commented code due to large overhead
    If pstatus = 5 Then
        Update st.sl_lot_alloc a
        set sl_id = b.stock_ledger_id,
            status = pstatus
        from st.stock_ledger b 
        where a.vch_tran_id = b.vch_tran_id
                    and b.issued_qty > 0
            And a.voucher_id = pvoucher_id;
    End If;
    */
    
    Update st.sl_lot_alloc
    set status = pstatus 
    where voucher_id = pvoucher_id;
End;
$Body$
LANGUAGE plpgsql;

?==?
Create or Replace Function st.sp_import_stock_opbal_branch_mt(pcompany_id BigInt, ptarget_year Varchar(4), sl_id BigInt, pmt_id bigInt)
Returns Void
AS
$BODY$
Declare vfrom_date Date; vto_date Date; vprevious_year Varchar(4);
Begin
	-- Fetch Previous FinYear details
	Select (a.year_begin - Interval '1 day')::date year_begin, a.year_end, a.finyear_code
	From sys.finyear a Into vfrom_date, vto_date, vprevious_year
	Where a.year_end = (Select b.year_begin - Interval '1 day' 
			  From sys.finyear b
			  Where b.finyear_code=ptarget_year)
		And a.company_id=pcompany_id;
	
	--Exit if previous year is not found
	if vprevious_year Is Null Then
		Return;
	End If;

	-- Step 1: Extract Net Balance Qty from Stock Ledger
	Drop Table If Exists sl_bal_temp;
	Create temp table sl_bal_temp
	(	sl_no BigSerial,
		branch_id BigInt,
		stock_location_id BigInt,
		material_id BigInt,
		balance_qty Numeric(18,3),
		unit_rate_lc Numeric(18,4)
	);

	-- We pick up all records for the finyear
    Insert Into sl_bal_temp(branch_id, stock_location_id, material_id, balance_qty, unit_rate_lc)
    Select a.branch_id, a.stock_location_id, a.material_id,
        Sum(a.received_qty-a.issued_qty) as balance_qty, 0
    From st.stock_ledger a
    Inner Join st.material b On a.material_Id = b.material_id
    Where a.finyear=vprevious_year And a.doc_date Between vfrom_date And vto_date
        And a.company_id=pcompany_id
        And a.stock_location_id = sl_id 
        And (b.material_type_id = pmt_id Or pmt_id = 0)
    Group By a.branch_id, a.stock_location_id, a.material_id
            Having Sum(a.received_qty-a.issued_qty) >= 0
    Order by a.material_id, a.branch_id;

	--	Step 2: Extract the WAC rate for all materials in each branch and Update the Rate
	Declare 
		cursor_branch No Scroll Cursor 
		For Select branch_id From sl_bal_temp group by branch_id;
	Begin
		For branch In cursor_branch Loop

			-- Update material valuation
			Update sl_bal_temp a
			Set unit_rate_lc=b.rate
			From st.fn_material_balance_wac(pcompany_id, branch.branch_id, 0, vprevious_year, vto_date) b
			Where a.material_id=b.material_id And a.branch_id=b.branch_id;

		End Loop;
	End;

	
	-- Prepare records for insert
	Drop Table If Exists sl_temp;
	Create Temp Table sl_temp 
	(	  stock_ledger_id uuid NOT NULL,
		  company_id bigint NOT NULL,
		  branch_id bigint NOT NULL,
		  finyear character varying(4) NOT NULL,
		  voucher_id character varying(50) NOT NULL,
		  vch_tran_id character varying(50) NOT NULL,
		  doc_date date NOT NULL,
		  material_id bigint NOT NULL,
		  stock_location_id bigint NOT NULL,
		  reference_id character varying(50) NOT NULL,
		  reference_tran_id character varying(50) NOT NULL,
		  reference_date date,
		  narration character varying(500) NOT NULL,
		  uom_id bigint NOT NULL,
		  uom_qty numeric(18,4) NOT NULL,
		  received_qty numeric(18,4) NOT NULL,
		  issued_qty numeric(18,4) NOT NULL,
		  unit_rate_lc numeric(18,4) NOT NULL,
		  stock_movement_type_id bigint NOT NULL,
		  inserted_on timestamp without time zone NOT NULL,
		  last_updated timestamp without time zone NOT NULL,
		  is_opbl boolean DEFAULT false,
		  account_id bigint NOT NULL DEFAULT (-1)
	);

	INSERT INTO sl_temp(stock_ledger_id, company_id, branch_id, finyear, voucher_id, vch_tran_id, doc_date, material_id, stock_location_id, 
		reference_id, reference_tran_id, reference_date, narration, uom_id, uom_qty, received_qty, issued_qty, unit_rate_lc,
		stock_movement_type_id, inserted_on, last_updated, is_opbl, account_id)
	Select md5('OPBL/'||ptarget_year||a.stock_location_id||a.material_id)::uuid, 
		pcompany_id, a.branch_id, ptarget_year, 'OPBL/'||ptarget_year||'/'||a.stock_location_id||'/'||a.material_id, '', vto_date, a.material_id, a.stock_location_id,
		'', '', Null, 'Opening Balance', -1, 0, a.balance_qty, 0, a.unit_rate_lc, 
		-1, vto_date, current_timestamp(0), true, b.inventory_account_id
	From sl_bal_temp a
	Inner Join st.material b On a.material_id=b.material_id;
	
	-- Delete entries where Target Year and Date match.
	Delete From st.stock_ledger a
	Where finyear=ptarget_year And doc_date=vto_date And Left(voucher_id, 5)='OPBL/'
        And a.stock_location_id = sl_id
        And a.material_id In (Select b.material_id from st.material b Where b.material_type_id = pmt_id);
	
	--	Insert Into Stock Ledger the new entries
	INSERT INTO st.stock_ledger(stock_ledger_id, company_id, branch_id, finyear, voucher_id, vch_tran_id, doc_date, material_id, stock_location_id, 
		reference_id, reference_tran_id, reference_date, narration, uom_id, uom_qty, received_qty, issued_qty, unit_rate_lc,
		stock_movement_type_id, inserted_on, last_updated, is_opbl, account_id)
	Select stock_ledger_id, company_id, branch_id, finyear, voucher_id, vch_tran_id, doc_date, material_id, stock_location_id, 
		reference_id, reference_tran_id, reference_date, narration, uom_id, uom_qty, received_qty, issued_qty, unit_rate_lc,
		stock_movement_type_id, inserted_on, last_updated, is_opbl, account_id
	From sl_temp;

End;
$BODY$
Language plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.mat_mcn_lc(pvoucher_id Varchar(50))
RETURNS TABLE
(   stock_ledger_id uuid,
    unit_rate_lc numeric(18,4)
)
AS
$BODY$
Declare
    vissue_value Numeric(18,4) := 0;
Begin
    -- Fetch Issue Value
    Select Coalesce(Sum(a.issued_qty * a.unit_rate_lc), 0) Into vissue_value
    From st.stock_ledger a
    Where a.voucher_id = pvoucher_id
        And a.issued_qty > 0;

    Return Query
    Select a.stock_ledger_id, sys.fn_handle_zero_divide(vissue_value, a.received_qty)
    From st.stock_ledger a
    Where a.voucher_id = pvoucher_id
        And a.received_qty > 0;
    
End;
$BODY$
LANGUAGE 'plpgsql';

?==?
CREATE OR REPLACE FUNCTION st.st_extn_add_update(pstock_id varchar(50), pstock_tran_id varchar(50), preceipt_qty numeric(18,4), pshort_qty numeric(18,4), preceipt_sl_id bigint)
RETURNS void AS
$BODY$
Begin	
	If NOT exists (select * from st.stock_tran_extn where stock_tran_id = pstock_tran_id) then 
		Insert into st.stock_tran_extn(stock_id, stock_tran_id, receipt_qty, short_qty, receipt_sl_id)
		Values (pstock_id, pstock_tran_id, preceipt_qty, pshort_qty, preceipt_sl_id);
    Else
    	update st.stock_tran_extn 
        Set receipt_sl_id = preceipt_sl_id
        Where stock_tran_id = pstock_tran_id;
    End If;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION st.fn_st_park_post_info_for_gl_post(IN pvoucher_id character varying)
  RETURNS TABLE(index integer, company_id bigint, branch_id bigint, dc character, account_id bigint, debit_amt_fc numeric, credit_amt_fc numeric, debit_amt numeric, credit_amt numeric, remarks character varying) AS
$BODY$ 
	Declare vCompany_ID bigint =-1; vBranch_ID bigint = -1; vTargetBr_id bigint = -1; vAccount_ID bigint =-1; vSaleAccount_ID bigint =-1; vDocType varchar(4);  vVoucher_ID varchar(50);
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
	Select a.company_id, a.account_id, a.doc_type, a.branch_id, a.target_branch_id, a.sale_account_id
		into vCompany_ID, vAccount_ID,  vDocType, vBranch_ID, vTargetBr_id, vSaleAccount_ID
	From st.stock_control a
	where stock_id=replace(pvoucher_id, ':AJ', '');
	
    vVoucher_ID := replace(pvoucher_id, ':AJ', '');
    -- *****	Step 2: Line Item Taxes
    Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
    Select vCompany_ID, a.target_branch_id, 'C', b.account_id, 0, 0, 0, a.tax_amt, ''
    from st.stock_control a
    inner join ac.ib_account b on a.branch_id = b.branch_id
    Where a.stock_id = vVoucher_ID;        

    -- *****	Step 3: GST Taxes
    with gtt 
    as 
    (	select sum(sgst_amt) as tax_amt, sgst_itc_account_id as account_id
            from tx.gst_tax_tran
            where voucher_id = vVoucher_ID And tran_group='st.stock_tran'
            group by sgst_itc_account_id
            union all
            select sum(cgst_amt), cgst_itc_account_id
            from tx.gst_tax_tran
            where voucher_id = vVoucher_ID And tran_group='st.stock_tran'
            group by cgst_itc_account_id
            union all
            select sum(igst_amt), igst_itc_account_id
            from tx.gst_tax_tran
            where voucher_id = vVoucher_ID And tran_group='st.stock_tran'
            group by igst_itc_account_id
            union all
            select sum(cess_amt), cess_itc_account_id
            from tx.gst_tax_tran
            where voucher_id = vVoucher_ID And tran_group='st.stock_tran'
            group by cess_itc_account_id
    )
    Insert into stock_vch_detail(company_id, branch_id, dc, account_id, debit_amt_fc, credit_amt_fc, debit_amt, credit_amt, remarks)
    Select vCompany_ID, vTargetBr_ID, 'D', a.account_id, 0, 0, sum(a.tax_amt), 0, 'Tax Amt'
    From gtt a
    group by a.account_id
    having sum(a.tax_amt) > 0;
        
	return query 
	select a.index, a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt, a.remarks
	from stock_vch_detail a;
END;
$BODY$
LANGUAGE plpgsql;

?==?
Create or Replace Function st.sp_sl_lot_shift(pvoucher_id Character Varying)
Returns Void 
As
$BODY$
Begin
        with sl_lot_temp
        As(
            select a.sl_lot_id, a.test_insp_id, a.test_insp_date, a.lot_no, a.mfg_date, a.exp_date, a.best_before, a.lot_state_id, a.ref_info,
            		b.lot_issue_qty, b.vch_tran_id, b.voucher_id
            from st.sl_lot a
            inner join st.sl_lot_alloc b on a.sl_lot_id = b.sl_lot_id
            Where b.voucher_id = pvoucher_id
        ),
        sl_info
        As
        (
            select a.* 
            from st.stock_ledger a
            Where a.voucher_id = pvoucher_id
                And a.received_qty > 0
        )
        INSERT INTO st.sl_lot (sl_lot_id, sl_id, test_insp_id, test_insp_date, lot_no, lot_qty, 
                          mfg_date, exp_date, best_before, lot_state_id, ref_info)
        Select md5(sl_item.vch_tran_id || a.sl_lot_id)::uuid, sl_item.stock_ledger_id, a.test_insp_id, a.test_insp_date, a.lot_no, a.lot_issue_qty,
           a.mfg_date, a.exp_date, a.best_before, a.lot_state_id, a.ref_info
        From sl_info sl_item, sl_lot_temp a
        Where a.vch_tran_id = sl_item.vch_tran_id;
End
$BODY$
Language plpgsql;

?==?

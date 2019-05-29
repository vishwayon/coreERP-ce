update st.sl_lot_alloc a
set vch_date = b.doc_date
from st.stock_control b 
where a.voucher_id = b.stock_id;

?==?
update st.stock_control
set annex_info = jsonb_set(annex_info, '{rc_tax_amt}', ('"0"')::jsonb, true)
where annex_info->'rc_tax_amt' is null
	And annex_info is not null
    And doc_type = 'PRV';

?==?
-- Insert duplicate rows of existing uom as purchase unit
select * into de.uom
from st.uom;

Alter table de.uom 
add column for_import boolean not null default true;

Do language plpgsql
$$
Declare 
    vmax_id BigInt := 0; new_uom_id BigInt:=0;
Begin
    Select max_id + 1 into vmax_id
    From sys.mast_seq_tran
    Where mast_seq_type = 'st.uom';
    
    Drop Sequence If Exists uom_ty;
    execute format('Create Sequence uom_ty Start with %s', vmax_id);
    new_uom_id := vmax_id;
    
    Declare cur_supp Cursor 
    For Select *
        From de.uom
        Where for_import = true;
        
    Begin	
        For rec in cur_supp Loop
        select nextval('uom_ty') into new_uom_id;

        Insert into st.uom(uom_id, material_id, uom_desc, uom_qty, is_base, is_su, is_discontinued,
                in_kg, in_ltr, uom_type_id)
        Select new_uom_id, rec.material_id, rec.uom_desc, rec.uom_qty, false, false, rec.is_discontinued,
                rec.in_kg, rec.in_ltr, 103; -- Purchase unit

        -- Last Step: Update Source
        Update de.uom
        Set for_import = false
        Where material_id = rec.material_id;

        Raise Notice 'uom_id: %;', new_uom_id;

        End Loop;
    End;
    
    Update sys.mast_seq_tran
    Set max_id = new_uom_id
    Where mast_seq_type = 'st.uom';
    
End;
$$
?==?

Update de.uom
set for_import = true;

?==?
Do language plpgsql
$$
Declare 
    vmax_id BigInt := 0; new_uom_id BigInt:=0;
Begin
    Select max_id + 1 into vmax_id
    From sys.mast_seq_tran
    Where mast_seq_type = 'st.uom';
    
    Drop Sequence If Exists uom_ty;
    execute format('Create Sequence uom_ty Start with %s', vmax_id);
    new_uom_id := vmax_id;
    
    Declare cur_supp Cursor 
    For Select *
        From de.uom
        Where for_import = true;
        
    Begin	
        For rec in cur_supp Loop
        select nextval('uom_ty') into new_uom_id;

        Insert into st.uom(uom_id, material_id, uom_desc, uom_qty, is_base, is_su, is_discontinued,
                in_kg, in_ltr, uom_type_id)
        Select new_uom_id, rec.material_id, rec.uom_desc, rec.uom_qty, false, true, rec.is_discontinued,
                rec.in_kg, rec.in_ltr, 104; -- Purchase unit

        -- Last Step: Update Source
        Update de.uom
        Set for_import = false
        Where material_id = rec.material_id;

        Raise Notice 'uom_id: %;', new_uom_id;

        End Loop;
    End;
    
    Update sys.mast_seq_tran
    Set max_id = new_uom_id
    Where mast_seq_type = 'st.uom';
    
End;
$$
?==?

Update st.uom
set is_su = false
where uom_type_id = 101;

?==?
update st.stock_control
set annex_info = jsonb_set(annex_info, '{st_excess_pcnt}', ('"0"')::jsonb, true)
where annex_info->'st_excess_pcnt' is null;

?==?
Update st.sl_lot_alloc
set tran_group = 'sl_lot_alloc'
where tran_group = '';

?==?

/*
* To update vch_type in menu table for the purpose of systemwide document search - 16 Aug, 2018
Update sys.menu
set vch_type = '{SP}'
where menu_name = 'mnuStockPurchase';

Update sys.menu
set vch_type = '{SPG}'
where menu_name = 'mnuStockGstPurchase';

Update sys.menu
set vch_type = '{SC}'
where menu_name = 'mnuStockConsumption';

Update sys.menu
set vch_type = '{JWR}'
where menu_name = 'mnuJobWorkReceipt';

Update sys.menu
set vch_type = '{ST}'
where menu_name = 'mnuStockTransfer';

Update sys.menu
set vch_type = '{SAN}'
where menu_name = 'mnuStockAdjustmentNote';

Update sys.menu
set vch_type = '{PR}'
where menu_name = 'mnuPurchaseReturn';

Update sys.menu
set vch_type = '{SI}'
where menu_name = 'mnuStockInvoice';

Update sys.menu
set vch_type = '{SIV}'
where menu_name = 'mnuStockGstInvoice';

Update sys.menu
set vch_type = '{SR}'
where menu_name = 'mnuSalesReturn';

Update sys.menu
set vch_type = '{SRV}'
where menu_name = 'mnuSaleReturnGst';

Update sys.menu
set vch_type = '{LTN}'
where menu_name = 'mnuLocationTransferNote';

Update sys.menu
set vch_type = '{PRN}'
where menu_name = 'mnuPurchaseReturnNote';

Update sys.menu
set vch_type = '{SRN}'
where menu_name = 'mnuSalesReturnNote';

Update sys.menu
set vch_type = '{PRV}'
where menu_name = 'mnuPurchaseReturnGst';

Update sys.menu
set vch_type = '{MCN}'
where menu_name = 'mnuMatConversionNote';
*/

with pymt_payable
As (
    select a.voucher_id, sum(b.net_debit_amt) net_debit_amt
    from ap.pymt_control a
    inner join ac.rl_pl_alloc b on a.voucher_id = b.voucher_id
    Where a.doc_type = 'PYMT'
            And b.tran_group = 'pl_alloc_tran'
    Group By a.voucher_id
)
Update ap.pymt_control a
set annex_info = jsonb_set(annex_info, '{payable_amt}', (b.net_debit_amt::varchar)::jsonb, true)
From pymt_payable b
Where annex_info->>'payable_amt' is Null
	And a.voucher_id = b.voucher_id;

?==?
Update ap.pymt_control a
set annex_info = jsonb_set(annex_info, '{payable_amt}', '0'::jsonb, true)
Where annex_info->>'payable_amt' is Null
	And doc_type = 'PYMT';

?==?
with pymt_payable
As (
    select a.voucher_id, sum(b.net_debit_amt_fc) net_debit_amt_fc
    from ap.pymt_control a
    inner join ac.rl_pl_alloc b on a.voucher_id = b.voucher_id
    Where a.doc_type = 'PYMT'
            And b.tran_group = 'pl_alloc_tran'
    Group By a.voucher_id
)
Update ap.pymt_control a
set annex_info = jsonb_set(annex_info, '{payable_amt_fc}', (b.net_debit_amt_fc::varchar)::jsonb, true)
From pymt_payable b
Where annex_info->>'payable_amt_fc' is Null
	And a.voucher_id = b.voucher_id;

?==?
Update ap.pymt_control a
set annex_info = jsonb_set(annex_info, '{payable_amt_fc}', '0'::jsonb, true)
Where annex_info->>'payable_amt_fc' is Null
	And doc_type = 'PYMT';

?==?
with pymt_payable
As (
    select a.voucher_id, sum(b.net_credit_amt) net_credit_amt
    from ap.pymt_control a
    inner join ac.rl_pl_alloc b on a.voucher_id = b.voucher_id
    Where a.doc_type = 'PYMT'
            And b.tran_group = 'receivable_ledger_alloc_tran'
    Group By a.voucher_id
)
Update ap.pymt_control a
set annex_info = jsonb_set(annex_info, '{receivable_amt}', (b.net_credit_amt::varchar)::jsonb, true)
From pymt_payable b
Where annex_info->>'receivable_amt' is Null
	And a.voucher_id = b.voucher_id;

?==?
Update ap.pymt_control a
set annex_info = jsonb_set(annex_info, '{receivable_amt}', '0'::jsonb, true)
Where annex_info->>'receivable_amt' is Null
	And doc_type = 'PYMT';
    
?==?
with pymt_payable
As (
    select a.voucher_id, sum(b.net_credit_amt_fc) net_credit_amt_fc
    from ap.pymt_control a
    inner join ac.rl_pl_alloc b on a.voucher_id = b.voucher_id
    Where a.doc_type = 'PYMT'
            And b.tran_group = 'receivable_ledger_alloc_tran'
    Group By a.voucher_id
)
Update ap.pymt_control a
set annex_info = jsonb_set(annex_info, '{receivable_amt_fc}', (b.net_credit_amt_fc::varchar)::jsonb, true)
From pymt_payable b
Where annex_info->>'receivable_amt_fc' is Null
	And a.voucher_id = b.voucher_id;

?==?
Update ap.pymt_control a
set annex_info = jsonb_set(annex_info, '{receivable_amt_fc}', '0'::jsonb, true)
Where annex_info->>'receivable_amt_fc' is Null
	And doc_type = 'PYMT';

?==?
with pymt_payable
As (
    select a.voucher_id, sum(b.net_credit_amt_fc) net_credit_amt_fc
    from ap.pymt_control a
    inner join ac.rl_pl_alloc b on a.voucher_id = b.voucher_id
    Where a.doc_type = 'PYMT'
            And b.tran_group = 'payable_ledger_alloc_tran'
    Group By a.voucher_id
)
Update ap.pymt_control a
set annex_info = jsonb_set(annex_info, '{supp_adv_amt_fc}', (b.net_credit_amt_fc::varchar)::jsonb, true)
From pymt_payable b
Where annex_info->>'supp_adv_amt_fc' is Null
	And a.voucher_id = b.voucher_id;

?==?
Update ap.pymt_control a
set annex_info = jsonb_set(annex_info, '{supp_adv_amt_fc}', '0'::jsonb, true)
Where annex_info->>'supp_adv_amt_fc' is Null
	And doc_type = 'PYMT';

?==?
with pymt_payable
As (
    select a.voucher_id, sum(b.net_credit_amt) net_credit_amt
    from ap.pymt_control a
    inner join ac.rl_pl_alloc b on a.voucher_id = b.voucher_id
    Where a.doc_type = 'PYMT'
            And b.tran_group = 'payable_ledger_alloc_tran'
    Group By a.voucher_id
)
Update ap.pymt_control a
set annex_info = jsonb_set(annex_info, '{supp_adv_amt}', (b.net_credit_amt::varchar)::jsonb, true)
From pymt_payable b
Where annex_info->>'supp_adv_amt' is Null
	And a.voucher_id = b.voucher_id;

?==?
Update ap.pymt_control a
set annex_info = jsonb_set(annex_info, '{supp_adv_amt}', '0'::jsonb, true)
Where annex_info->>'supp_adv_amt' is Null
	And doc_type = 'PYMT';

?==?
Insert into  md.ts_tran
Select b.stock_tran_id, a.stock_id, 'st.stock_tran', b.fat_pcnt, b.snf_pcnt, 0, 0, b.clr_val, 0, 0, 0, 0, 0, 0, 0
From st.stock_control a
inner join st.stock_tran b on a.stock_id = b.stock_id
Where a.doc_type = 'ST';

?==?
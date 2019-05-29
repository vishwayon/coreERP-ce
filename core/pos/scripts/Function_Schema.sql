CREATE OR REPLACE FUNCTION pos.sp_get_pos_doc_id(
    IN pdoc_type character varying,
    IN ptday_session_id uuid,
    INOUT pnew_doc_id character varying)
  RETURNS character varying AS
$BODY$
Declare
	vMax_id BigInt; vErrMsg Varchar(500); 
	vCompany_code Varchar(2); vBranch_code Varchar(2); vFinYear Varchar(4); vTerminal_code Varchar(2); vTerminal_id BigInt;
Begin
	-- By Girish
	-- This Procedure is used in POS for document id generation
	-- using terminal session

	Select c.company_code, c.branch_code, a.finYear, b.terminal_code, a.terminal_id Into vCompany_code, vBranch_code, vFinYear, vTerminal_code, vTerminal_id
	From pos.tday a
	Inner Join pos.terminal b On a.terminal_id=b.terminal_id
	Inner Join sys.branch c On b.branch_id=c.branch_id
	Where a.tday_session_id = ptday_session_id;

	If Not Exists(Select * from pos.doc_seq where doc_type=pdoc_type And finyear=vFinYear And terminal_id=vTerminal_id) Then
		-- Sequence does not exist. Therefore create a new sequence
		insert into pos.doc_seq(terminal_id, doc_type, finyear, max_voucher_no, lock_bit)
		values(vTerminal_id, pdoc_type, vFinYear, 0, false);
	End If;

	-- lock table
	update pos.doc_seq a
	Set lock_bit=true
	where a.doc_type=pdoc_type And a.terminal_id=vTerminal_id And a.finyear=vFinYear;

	-- Generate the next id
	Select a.max_voucher_no+1 into vMax_id
	From pos.doc_seq a
	Where a.doc_type=pdoc_type And a.terminal_id=vTerminal_id And a.finyear=vFinYear;

	-- Update and unlock
	update pos.doc_seq a
	Set 	max_voucher_no=vMax_id,
		lock_bit=false
	where a.doc_type=pdoc_type And a.terminal_id=vTerminal_id And a.finyear=vFinYear;

	-- generate output 
	Select pdoc_type || left(vFinYear, 2) || vCompany_code || vBranch_code || vTerminal_code || lpad(vMax_id::text, 5, '0') into pnew_doc_id;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION pos.fn_inv_print(
    IN pinv_id character varying,
    IN pcp_option smallint)
  RETURNS TABLE(cp_id bigint, cp_desc character varying, inv_id character varying, company_id bigint, finyear character varying, branch_id bigint, 
	doc_type character varying, doc_date date, item_amt_tot numeric, tax_amt_tot numeric, nt_amt numeric, rof_amt numeric, inv_amt numeric, 
	status smallint, narration character varying, amt_in_words character varying, cust_tin character varying, cust_name character varying, 
	customer_address character varying, order_ref Character Varying, order_date Date
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
		cust_tin character varying, 
		cust_name character varying, 
		customer_address character varying,
		order_ref Character Varying, 
		order_date Date
	);

	If pcp_option = 1 Then
		Insert Into inv_temp(cp_id, cp_desc, inv_id, company_id, finyear, branch_id, doc_type, doc_date, item_amt_tot, tax_amt_tot,
			nt_amt, rof_amt, inv_amt, status, narration, amt_in_words, cust_tin, cust_name, customer_address, order_ref, order_date)
		Select 1, 'Original - Buyer''s Copy', a.inv_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.item_amt_tot, a.tax_amt_tot, a.nt_amt, a.rof_amt, a.inv_amt, 
			a.status, a.narration, a.amt_in_words, 
			a.cust_tin, a.cust_name, a.cust_address, a.annex_info->>'order_ref', (a.annex_info->>'order_date')::Date
		From pos.inv_control a
		Where a.inv_id=pinv_id
		Union All
		Select 2, 'Duplicate - Seller''s Copy', a.inv_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.item_amt_tot, a.tax_amt_tot, a.nt_amt, a.rof_amt, a.inv_amt, 
			a.status, a.narration, a.amt_in_words, 
			a.cust_tin, a.cust_name, a.cust_address, a.annex_info->>'order_ref', (a.annex_info->>'order_date')::Date
		From pos.inv_control a
		Where a.inv_id=pinv_id;
	ElseIf pcp_option = 2 Then
		Insert Into inv_temp(cp_id, cp_desc, inv_id, company_id, finyear, branch_id, doc_type, doc_date, item_amt_tot, tax_amt_tot,
			nt_amt, rof_amt, inv_amt, status, narration, amt_in_words, cust_tin, cust_name, customer_address, order_ref, order_date)
		Select 1, 'Triplicate - Transporter''s Copy', a.inv_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.item_amt_tot, a.tax_amt_tot, a.nt_amt, a.rof_amt, a.inv_amt, 
			a.status, a.narration, a.amt_in_words, 
			a.cust_tin, a.cust_name, a.cust_address, a.annex_info->>'order_ref', (a.annex_info->>'order_date')::Date
		From pos.inv_control a
		Where a.inv_id=pinv_id;
	ElseIf pcp_option = 3 Then
		Insert Into inv_temp(cp_id, cp_desc, inv_id, company_id, finyear, branch_id, doc_type, doc_date, item_amt_tot, tax_amt_tot,
			nt_amt, rof_amt, inv_amt, status, narration, amt_in_words, cust_tin, cust_name, customer_address, order_ref, order_date)
		Select 1, 'Quadruplicate - Extra Copy', a.inv_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.item_amt_tot, a.tax_amt_tot, a.nt_amt, a.rof_amt, a.inv_amt, 
			a.status, a.narration, a.amt_in_words, 
			a.cust_tin, a.cust_name, a.cust_address, a.annex_info->>'order_ref', (a.annex_info->>'order_date')::Date
		From pos.inv_control a
		Where a.inv_id=pinv_id;
	ElseIf pcp_option = 4 Then -- Sales Return
		Insert Into inv_temp(cp_id, cp_desc, inv_id, company_id, finyear, branch_id, doc_type, doc_date, item_amt_tot, tax_amt_tot,
			nt_amt, rof_amt, inv_amt, status, narration, amt_in_words, cust_tin, cust_name, customer_address, order_ref, order_date)
		Select 1, 'Original - Buyer''s Copy', a.inv_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.item_amt_tot, a.tax_amt_tot, a.nt_amt, a.rof_amt, a.inv_amt, 
			a.status, a.narration, a.amt_in_words, 
			a.cust_tin, a.cust_name, a.cust_address, a.annex_info->>'origin_inv_id', (a.annex_info->>'origin_inv_date')::Date
		From pos.inv_control a
		Where a.inv_id=pinv_id
		Union All
		Select 2, 'Duplicate - Seller''s Copy', a.inv_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.item_amt_tot, a.tax_amt_tot, a.nt_amt, a.rof_amt, a.inv_amt, 
			a.status, a.narration, a.amt_in_words, 
			a.cust_tin, a.cust_name, a.cust_address, a.annex_info->>'origin_inv_id', (a.annex_info->>'origin_inv_date')::Date
		From pos.inv_control a
		Where a.inv_id=pinv_id;
	End If;
	
	Return Query
	Select a.cp_id, a.cp_desc, a.inv_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
		a.item_amt_tot, a.tax_amt_tot, a.nt_amt, a.rof_amt, a.inv_amt, 
		a.status, a.narration, a.amt_in_words, 
		a.cust_tin, a.cust_name, a.customer_address, a.order_ref, a.order_date
	From inv_temp a;
	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION pos.fn_gst_inv_print(
    IN pinv_id character varying,
    IN pcp_option smallint)
  RETURNS TABLE(cp_id bigint, cp_desc character varying, inv_id character varying, company_id bigint, finyear character varying, branch_id bigint, 
	doc_type character varying, doc_date date, item_amt_tot numeric, tax_amt_tot numeric, nt_amt numeric, rof_amt numeric, inv_amt numeric, 
	status smallint, narration character varying, amt_in_words character varying, cust_tin character varying, cust_name character varying, 
	customer_address character varying, cust_gst_state character varying, order_ref Character Varying, order_date Date
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
		cust_tin character varying, 
		cust_name character varying, 
		customer_address character varying,
                cust_gst_state character varying,
		order_ref Character Varying, 
		order_date Date
	);

	If pcp_option = 1 Then
		Insert Into inv_temp(cp_id, cp_desc, inv_id, company_id, finyear, branch_id, doc_type, doc_date, item_amt_tot, tax_amt_tot,
			nt_amt, rof_amt, inv_amt, status, narration, amt_in_words, cust_tin, cust_name, customer_address, cust_gst_state, order_ref, order_date)
		Select 1, 'Original For Recipient', a.inv_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.item_amt_tot, a.tax_amt_tot, a.nt_amt, a.rof_amt, a.inv_amt, 
			a.status, a.narration, a.amt_in_words, 
			a.cust_tin, a.cust_name, a.cust_address, 
                        b.gst_state_code || ' - ' || b.state_name,
                        a.annex_info->>'order_ref', (a.annex_info->>'order_date')::Date
		From pos.inv_control a
        Inner Join tx.gst_state b on (a.annex_info->'gst_output_info'->>'cust_state_id')::BigInt = b.gst_state_id
		Where a.inv_id=pinv_id
		Union All
		Select 2, 'Triplicate For Supplier', a.inv_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.item_amt_tot, a.tax_amt_tot, a.nt_amt, a.rof_amt, a.inv_amt, 
			a.status, a.narration, a.amt_in_words, 
			a.cust_tin, a.cust_name, a.cust_address, 
                        b.gst_state_code || ' - ' || b.state_name, 
                        a.annex_info->>'order_ref', (a.annex_info->>'order_date')::Date
		From pos.inv_control a
                Inner Join tx.gst_state b on (a.annex_info->'gst_output_info'->>'cust_state_id')::BigInt = b.gst_state_id
		Where a.inv_id=pinv_id;
	ElseIf pcp_option = 2 Then
		Insert Into inv_temp(cp_id, cp_desc, inv_id, company_id, finyear, branch_id, doc_type, doc_date, item_amt_tot, tax_amt_tot,
			nt_amt, rof_amt, inv_amt, status, narration, amt_in_words, cust_tin, cust_name, customer_address, cust_gst_state, order_ref, order_date)
		Select 1, 'Duplicate For Transporter', a.inv_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.item_amt_tot, a.tax_amt_tot, a.nt_amt, a.rof_amt, a.inv_amt, 
			a.status, a.narration, a.amt_in_words, 
			a.cust_tin, a.cust_name, a.cust_address, 
                        b.gst_state_code || ' - ' || b.state_name,
                        a.annex_info->>'order_ref', (a.annex_info->>'order_date')::Date
		From pos.inv_control a
                Inner Join tx.gst_state b on (a.annex_info->'gst_output_info'->>'cust_state_id')::BigInt = b.gst_state_id
		Where a.inv_id=pinv_id;
	ElseIf pcp_option = 3 Then
		Insert Into inv_temp(cp_id, cp_desc, inv_id, company_id, finyear, branch_id, doc_type, doc_date, item_amt_tot, tax_amt_tot,
			nt_amt, rof_amt, inv_amt, status, narration, amt_in_words, cust_tin, cust_name, customer_address, cust_gst_state, order_ref, order_date)
		Select 1, 'Triplicate For Supplier', a.inv_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.item_amt_tot, a.tax_amt_tot, a.nt_amt, a.rof_amt, a.inv_amt, 
			a.status, a.narration, a.amt_in_words, 
			a.cust_tin, a.cust_name, a.cust_address, 
                        b.gst_state_code || ' - ' || b.state_name, 
                        a.annex_info->>'order_ref', (a.annex_info->>'order_date')::Date
		From pos.inv_control a
                Inner Join tx.gst_state b on (a.annex_info->'gst_output_info'->>'cust_state_id')::BigInt = b.gst_state_id
		Where a.inv_id=pinv_id;
	ElseIf pcp_option = 4 Then -- Sales Return
		Insert Into inv_temp(cp_id, cp_desc, inv_id, company_id, finyear, branch_id, doc_type, doc_date, item_amt_tot, tax_amt_tot,
			nt_amt, rof_amt, inv_amt, status, narration, amt_in_words, cust_tin, cust_name, customer_address, cust_gst_state, order_ref, order_date)
		Select 1, 'Original - Buyer''s Copy', a.inv_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.item_amt_tot, a.tax_amt_tot, a.nt_amt, a.rof_amt, a.inv_amt, 
			a.status, a.narration, a.amt_in_words, 
			a.cust_tin, a.cust_name, a.cust_address, 
                        b.gst_state_code || ' - ' || b.state_name, 
                        a.annex_info->>'origin_inv_id', (a.annex_info->>'origin_inv_date')::Date
		From pos.inv_control a
                Inner Join tx.gst_state b on (a.annex_info->'gst_output_info'->>'cust_state_id')::BigInt = b.gst_state_id
		Where a.inv_id=pinv_id
		Union All
		Select 2, 'Duplicate - Seller''s Copy', a.inv_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
			a.item_amt_tot, a.tax_amt_tot, a.nt_amt, a.rof_amt, a.inv_amt, 
			a.status, a.narration, a.amt_in_words, 
			a.cust_tin, a.cust_name, a.cust_address, 
                        b.gst_state_code || ' - ' || b.state_name, 
                        a.annex_info->>'origin_inv_id', (a.annex_info->>'origin_inv_date')::Date
		From pos.inv_control a
                Inner Join tx.gst_state b on (a.annex_info->'gst_output_info'->>'cust_state_id')::BigInt = b.gst_state_id
		Where a.inv_id=pinv_id;
	End If;
	
	Return Query
	Select a.cp_id, a.cp_desc, a.inv_id, a.company_id, a.finyear, a.branch_id, a.doc_type, a.doc_date, 
		a.item_amt_tot, a.tax_amt_tot, a.nt_amt, a.rof_amt, a.inv_amt, 
		a.status, a.narration, a.amt_in_words, 
		a.cust_tin, a.cust_name, a.customer_address, a.cust_gst_state, a.order_ref, a.order_date
	From inv_temp a;
	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION pos.fn_inv_tran_print(IN pinv_id character varying)
  RETURNS TABLE(inv_tran_id character varying, inv_id character varying, sl_no bigint, material_type_id bigint, material_type character varying, 
	bar_code character varying, material_id bigint, material_name character varying, stock_location_id bigint, stock_location_name character varying, 
	uom_id bigint, uom_desc character varying, issued_qty numeric, rate numeric, disc_amt numeric, bt_amt numeric, 
	tax_schedule_id bigint, tax_pcnt numeric, tax_amt numeric, item_amt numeric,
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
	Select a.inv_tran_id, a.inv_id, a.sl_no, a.material_type_id, b.material_type, a.bar_code, 
		a.material_id, c.material_name, a.stock_location_id, d.stock_location_name,
		a.uom_id, e.uom_desc, Case When a.issued_qty > 0 Then a.issued_qty Else a.received_qty End, a.rate, a.disc_amt, a.bt_amt, 
		a.tax_schedule_id, a.tax_pcnt, a.tax_amt, a.item_amt, f.mfg_serial
	From pos.inv_tran a
	Inner Join st.material_type b On a.material_type_id=b.material_type_id
	Inner Join st.material c On a.material_id=c.material_id
	Left Join st.stock_location d On a.stock_location_id=d.stock_location_id
	Inner Join st.uom e On a.uom_id=e.uom_id
	Left Join mfg_war f On a.inv_tran_id= f.stock_tran_id
	Where a.inv_id=pinv_id;

End
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION pos.fn_gst_inv_tran_print(IN pinv_id character varying)
  RETURNS TABLE(inv_tran_id character varying, inv_id character varying, sl_no bigint, material_type_id bigint, material_type character varying, 
	bar_code character varying, material_id bigint, material_name character varying, stock_location_id bigint, stock_location_name character varying, 
	uom_id bigint, uom_desc character varying, issued_qty numeric, 
        rate numeric, disc_is_value boolean, disc_pcnt numeric, disc_amt numeric, bt_amt numeric, 
	hsn_sc_code character varying, hsn_sc_type character varying, gst_rate_id bigint, sgst_pcnt numeric, sgst_amt numeric, 
	cgst_pcnt numeric, cgst_amt numeric, igst_pcnt numeric, igst_amt numeric,cess_pcnt numeric, cess_amt numeric, tax_amt numeric, item_amt numeric,
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
	Select a.inv_tran_id, a.inv_id, a.sl_no, a.material_type_id, b.material_type, a.bar_code, 
		a.material_id, c.material_name, a.stock_location_id, d.stock_location_name,
		a.uom_id, e.uom_desc, Case When a.issued_qty > 0 Then a.issued_qty Else a.received_qty End, 
                a.rate, a.disc_is_value, a.disc_pcnt, a.disc_amt, a.bt_amt, 
		g.hsn_sc_code, g.hsn_sc_type, g.gst_rate_id, g.sgst_pcnt, g.sgst_amt, 
		g.cgst_pcnt, g.cgst_amt, g.igst_pcnt, g.igst_amt, g.cess_pcnt, g.cess_amt, a.tax_amt, a.item_amt, f.mfg_serial
	From pos.inv_tran a
	Inner Join st.material_type b On a.material_type_id=b.material_type_id
	Inner Join st.material c On a.material_id=c.material_id
	Left Join st.stock_location d On a.stock_location_id=d.stock_location_id
	Inner Join st.uom e On a.uom_id=e.uom_id
	Left Join mfg_war f On a.inv_tran_id= f.stock_tran_id
        Inner Join tx.gst_tax_tran g On a.inv_tran_id = g.gst_tax_tran_id
	Where a.inv_id=pinv_id;

End
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION pos.fn_inv_tax_print(IN pinv_id character varying)
  RETURNS TABLE(inv_id character varying, tax_schedule_id bigint, tax_detail_id bigint, tax_desc character varying, item_assess_amt numeric, tax_pcnt numeric, tax_amt numeric, item_amt numeric) AS
$BODY$
Begin
	Return Query
	Select a.inv_id, a.tax_schedule_id, c.tax_detail_id, c.description, 
		Sum(a.bt_amt), a.tax_pcnt, Sum(a.tax_amt), Sum(a.item_amt)
	From pos.inv_tran a
	Inner Join tx.tax_schedule b On a.tax_schedule_id=b.tax_schedule_id
	Inner Join tx.tax_detail c On b.tax_schedule_id=c.tax_schedule_id
	Where a.inv_id=pinv_id
	Group by a.inv_id, a.tax_schedule_id, c.tax_detail_id, c.description, a.tax_pcnt;

End
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION pos.fn_gst_inv_tax_print(IN pinv_id character varying)
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
	Where a.voucher_id=pinv_id And a.tran_group = 'pos.inv_tran'
	Group by a.voucher_id, a.hsn_sc_code, a.gst_rate_id;

End
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION pos.fn_inv_settle_print(IN pinv_id character varying)
  RETURNS TABLE
  (	inv_id character varying, 
	is_cash boolean, cash_amt numeric, 
	is_cheque boolean, cheque_no Character Varying, cheque_amt numeric,
	is_card boolean, card_no character varying, card_amt numeric, 
	is_customer boolean, customer character varying, customer_amt numeric
) AS
$BODY$
Begin
	Return Query
	Select a.inv_id, 
		b.is_cash, b.cash_amt, 
		b.is_cheque, b.cheque_no, b.cheque_amt,
		b.is_card, Substr(b.card_no, Length(b.card_no) - 3, 4)::Varchar(4), b.card_amt,
		b.is_customer, c.customer, b.customer_amt
	From pos.inv_control a
	Inner Join pos.inv_settle b On a.inv_id=b.inv_id
	Left Join ar.customer c On b.customer_id=c.customer_id
	Where a.inv_id=pinv_id;

End
$BODY$
  LANGUAGE plpgsql;

?==?
Create or Replace function pos.fn_tday_validate_stock(ptsessionid uuid)
Returns Table 
(	material_id BigInt,
	material_code Character Varying,
	material_name Character Varying,
	mfg_id BigInt,
	mfg Character Varying,
	bal_qty Numeric(18,3),
	issued_qty Numeric(18,3),
	short_qty Numeric(18,3)
)
As
$BODY$
Declare
	vcomp_id BigInt; vbranch_id BigInt; vsl_id BigInt; vfinyear Varchar(4); vto_date Date;
Begin

	Select a.company_id, a.branch_id, b.stock_location_id, a.finyear, a.tday_date
	Into vcomp_id, vbranch_id, vsl_id, vfinyear, vto_date	 
	From pos.tday a
	Inner Join pos.terminal b On a.terminal_id=b.terminal_id
	Where tday_session_id = ptsessionid;

	Create Temp Table tbl_mat_bal
	(	material_id BigInt,
		bal_qty Numeric(18,3) Not Null,
		issued_qty Numeric(18,3) Not Null
	);

	Insert Into tbl_mat_bal(material_id, bal_qty, issued_qty)
	Select x.material_id, Sum(x.bal_qty), Sum(x.issued_qty)
	From (	Select a.material_id, a.balance_qty_base as bal_qty, 0.000 as issued_qty 
		From st.fn_material_balance_wac_detail(vcomp_id, vbranch_id, 0, vsl_id, vfinyear, vto_date) a
		Union All
		Select a.material_id, 0.000, st.sp_get_base_qty(a.uom_id, a.issued_qty) as issued_qty 
		From pos.inv_tran a 
		Inner Join pos.inv_control b On a.inv_id=b.inv_id
		Where b.tday_session_id = ptsessionid And b.status = 3
	) x
	Group by x.material_id;

	Return Query
	Select a.material_id, b.material_code, b.material_name, (b.annex_info->'supp_info'->>'mfg_id')::bigint, e.mfg,
		a.bal_qty, a.issued_qty, a.bal_qty - a.issued_qty
	From tbl_mat_bal a 
	Inner Join st.material b On a.material_id = b.material_id
	Left Join st.mfg e On (b.annex_info->'supp_info'->>'mfg_id')::bigint = e.mfg_id
	Where a.bal_qty - a.issued_qty < 0;

End;
$BODY$
Language plpgsql;

?==?
Create Or Replace Function pos.fn_tday_settle_summary(ptsessionid uuid)
Returns Table 
(	settle_type Character Varying,
	settle_desc Character Varying,
	settle_amt Numeric(18,4)
)
As
$BODY$
Begin
	Return Query
	Select x.settle_type::Varchar, x.settle_desc, Sum(x.settle_amt)
	From (	Select 'cash' as settle_type, c.account_head as settle_desc, a.cash_amt as settle_amt
		From pos.inv_settle a
		Inner Join pos.inv_control b On a.inv_id = b.inv_id
		Inner Join ac.account_head c On a.cash_account_id = c.account_id
		Where b.tday_session_id = ptsessionid 
			And a.is_cash = true And b.doc_type Not In ('PSR', 'PIR')
			And b.status = 5
		Union All
		Select 'cash' as settle_type, c.account_head as settle_desc, -a.cash_amt as settle_amt
		From pos.inv_settle a
		Inner Join pos.inv_control b On a.inv_id = b.inv_id
		Inner Join ac.account_head c On a.cash_account_id = c.account_id
		Where b.tday_session_id = ptsessionid 
			And a.is_cash = true And b.doc_type In ('PSR', 'PIR')
			And b.status = 5
		Union All
		Select 'cheque' as settle_type, c.account_head as settle_desc, a.cheque_amt as settle_amt
		From pos.inv_settle a
		Inner Join pos.inv_control b On a.inv_id = b.inv_id
		Inner Join ac.account_head c On a.cheque_account_id = c.account_id
		Where b.tday_session_id = ptsessionid 
			And a.is_cheque = true
			And b.status = 5
		Union All
		Select 'card', c.cc_mac_code, a.card_amt
		From pos.inv_settle a
		Inner Join pos.inv_control b On a.inv_id = b.inv_id
		Inner Join pos.cc_mac c On a.cc_mac_id = c.cc_mac_id
		Where b.tday_session_id = ptsessionid 
			And a.is_card = true
			And b.status = 5
		Union All
		Select 'customer', c.customer, a.customer_amt
		From pos.inv_settle a
		Inner Join pos.inv_control b On a.inv_id = b.inv_id
		Inner Join ar.customer c On a.customer_id = c.customer_id
		Where b.tday_session_id = ptsessionid 
			And a.is_customer = true And b.doc_type Not In ('PSR', 'PIR')
			And b.status = 5
		Union All
		Select 'customer', c.customer, -a.customer_amt
		From pos.inv_settle a
		Inner Join pos.inv_control b On a.inv_id = b.inv_id
		Inner Join ar.customer c On a.customer_id = c.customer_id
		Where b.tday_session_id = ptsessionid 
			And a.is_customer = true And b.doc_type In ('PSR', 'PIR')
			And b.status = 5
		Union All -- Labour Invoices that were settled via pos
		Select 'cash', c.account_head, (a.annex_info->'pos'->'inv_settle'->>'cash_amt')::Numeric 
		From ar.invoice_control a
		Inner Join ac.account_head c On (a.annex_info->'pos'->'inv_settle'->>'cash_account_id')::BigInt = c.account_id
		Where (a.annex_info->'pos'->>'tday_session_id')::uuid = ptsessionid 
			And (a.annex_info->'pos'->'inv_settle'->>'is_cash')::Boolean
			And a.status=5
		Union All
		Select 'cheque', c.account_head, (a.annex_info->'pos'->'inv_settle'->>'cheque_amt')::Numeric 
		From ar.invoice_control a
		Inner Join ac.account_head c On (a.annex_info->'pos'->'inv_settle'->>'cheque_account_id')::BigInt = c.account_id
		Where (a.annex_info->'pos'->>'tday_session_id')::uuid = ptsessionid 
			And (a.annex_info->'pos'->'inv_settle'->>'is_cheque')::Boolean
			And a.status=5
		Union All
		Select 'card', c.cc_mac_code, (a.annex_info->'pos'->'inv_settle'->>'card_amt')::Numeric 
		From ar.invoice_control a
		Inner Join pos.cc_mac c On (a.annex_info->'pos'->'inv_settle'->>'cc_mac_id')::BigInt = c.cc_mac_id
		Where (a.annex_info->'pos'->>'tday_session_id')::uuid = ptsessionid 
			And (a.annex_info->'pos'->'inv_settle'->>'is_card')::Boolean
			And a.status=5
		Union All
		Select 'customer', c.customer, (a.annex_info->'pos'->'inv_settle'->>'customer_amt')::Numeric 
		From ar.invoice_control a
		Inner Join ar.customer c On (a.annex_info->'pos'->'inv_settle'->>'customer_id')::BigInt = c.customer_id
		Where (a.annex_info->'pos'->>'tday_session_id')::uuid = ptsessionid 
			And (a.annex_info->'pos'->'inv_settle'->>'is_customer')::Boolean
			And a.status=5
	) x
	Group by x.settle_type, x.settle_desc;
End
$BODY$
language plpgsql;

?==?
Create or Replace Function pos.fn_daily_sale_sum(pcompany_id BigInt, pbranch_id BigInt, pterminal_id BigInt, pfrom_date Date, pto_date Date)
Returns Table 
(	tday_date Date,
	terminal Character Varying,
	terminal_loc Character Varying,
	user_name Character Varying,
	inv_amt Numeric(18,4),
	cash_amt Numeric(18,4),
	card_amt Numeric(18,4),
	cheque_amt Numeric(18,4),
	customer_amt Numeric(18,4)
)
As
$BODY$
Begin
	Return Query
	With inv_sum
	As
	(	Select b.tday_date, a.terminal, a.terminal_loc, e.user_name,
			Sum(c.inv_amt) as inv_amt, sum(d.cash_amt) cash_amt, sum(d.card_amt) card_amt, sum(d.cheque_amt) cheque_amt, Sum(d.customer_amt) customer_amt
		From pos.terminal a
		Inner Join pos.tday b On a.terminal_id = b.terminal_id
		Inner Join pos.inv_control c On b.tday_session_id = c.tday_session_id
		Inner Join pos.inv_settle d On c.inv_id = d.inv_id
		Left Join sys.user e On b.user_id = e.user_id
		Where c.company_id = pcompany_id And c.status=5
		    And (c.branch_id = pbranch_id Or pbranch_id = 0)
		    And (a.terminal_id = pterminal_id Or pterminal_id = 0)
		    And c.doc_date Between pfrom_date And pto_date
		    And c.doc_type Not In ('PSR', 'PIR')
		Group by a.terminal, a.terminal_loc, b.tday_date, e.user_name
		Union All
		Select b.tday_date, a.terminal, a.terminal_loc, e.user_name,
			-Sum(c.inv_amt) as inv_amt, -Sum(d.cash_amt) cash_amt, -Sum(d.card_amt) card_amt, -Sum(d.cheque_amt) cheque_amt, -Sum(d.customer_amt) customer_amt
		From pos.terminal a
		Inner Join pos.tday b On a.terminal_id = b.terminal_id
		Inner Join pos.inv_control c On b.tday_session_id = c.tday_session_id
		Inner Join pos.inv_settle d On c.inv_id = d.inv_id
		Left Join sys.user e On b.user_id = e.user_id
		Where c.company_id = pcompany_id And c.status=5
		    And (c.branch_id = pbranch_id Or pbranch_id = 0)
		    And (a.terminal_id = pterminal_id Or pterminal_id = 0)
		    And c.doc_date Between pfrom_date And pto_date
		    And c.doc_type In ('PSR', 'PIR')
		Group by a.terminal, a.terminal_loc, b.tday_date, e.user_name
		Union All
		Select b.tday_date, a.terminal, a.terminal_loc, e.user_name,
			Sum(c.invoice_amt) as inv_amt, 
			Sum((c.annex_info->'pos'->'inv_settle'->>'cash_amt')::Numeric) cash_amt, 
			Sum((c.annex_info->'pos'->'inv_settle'->>'card_amt')::Numeric) card_amt, 
			Sum((c.annex_info->'pos'->'inv_settle'->>'cheque_amt')::Numeric) cheque_amt, 
			Sum((c.annex_info->'pos'->'inv_settle'->>'customer_amt')::Numeric) customer_amt
		From pos.terminal a
		Inner Join pos.tday b On a.terminal_id = b.terminal_id
		Inner Join ar.invoice_control c On b.tday_session_id = (c.annex_info->'pos'->>'tday_session_id')::uuid
		Left Join sys.user e On b.user_id = e.user_id
		Where c.company_id = pcompany_id And c.status=5
		    And (c.branch_id = pbranch_id Or pbranch_id = 0)
		    And (c.annex_info->'pos'->>'is_pos')::boolean
		    And (a.terminal_id = pterminal_id Or pterminal_id = 0)
		    And c.doc_date Between pfrom_date And pto_date
		Group by a.terminal, a.terminal_loc, b.tday_date, e.user_name
	)
	Select a.tday_date, a.terminal, a.terminal_loc, a.user_name,
			Sum(a.inv_amt), sum(a.cash_amt), sum(a.card_amt), sum(a.cheque_amt), Sum(a.customer_amt)
	From inv_sum a
	Group by a.terminal, a.terminal_loc, a.tday_date, a.user_name
	Order by a.tday_date, a.terminal;

End
$BODY$
language plpgsql;

?==?
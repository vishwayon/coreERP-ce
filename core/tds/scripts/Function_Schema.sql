CREATE OR REPLACE FUNCTION tds.fn_tds_info_for_gl_post(IN pvoucher_id character varying)
  RETURNS TABLE
  (
	index int4, 
	company_id bigint,
	branch_id bigint,
	dc char(1),
	account_id bigint,
	debit_amt_fc numeric(18,4),
	credit_amt_fc numeric(18,4),
	debit_amt numeric(18,4),
	credit_amt numeric(18,4)
) AS
$BODY$ 
	Declare vVoucher_id Varchar(50); 
	
Begin	
	-- This function is used by the Posting Trigger to get information on the TDS 
	DROP TABLE IF EXISTS tds_vch_detail;	
	create temp TABLE  tds_vch_detail
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

	-- Resolve Voucher_ID
	vVoucher_id := Trim(trailing ':TDS' from pVoucher_id);	

	-- Fetch Control. This would be from Payable ledger alloc as inserted by tds.sp_tds_post
	Insert into tds_vch_detail(company_id, branch_id, dc, account_id, 
		debit_amt_fc, credit_amt_fc, 
		debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 'D', a.supplier_id, 
		tds_base_rate_amt_fc + tds_ecess_amt_fc + tds_surcharge_amt_fc, 0,
		tds_base_rate_amt + tds_ecess_amt + tds_surcharge_amt, 0
	From tds.bill_tds_tran a
	Where a.voucher_id=vVoucher_id;

	-- Fetch Tran			
	Insert into tds_vch_detail(company_id, branch_id, dc, account_id, 
		debit_amt_fc, credit_amt_fc, 
		debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 'C', b.tds_account_id, 
		0, tds_base_rate_amt_fc + tds_ecess_amt_fc + tds_surcharge_amt_fc, 
		0, tds_base_rate_amt + tds_ecess_amt + tds_surcharge_amt
	From tds.bill_tds_tran a
	Inner Join tds.section_acc b On a.section_id=b.section_id
	Where a.voucher_id=vVoucher_id;

	return query 
	select a.index, a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
	from tds_vch_detail a;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
create OR REPLACE function tds.fn_get_pending_bills_for_tds_payment(pcompany_id bigint, pbranch_id bigint, pperson_type_id bigint, ppayment_id varchar(50))
RETURNS TABLE  
(	bill_tds_tran_id varchar(50), 
	voucher_id varchar(50), 
	doc_date date,
	supplier_id bigint,
	supplier varchar(250),
	tds_base_rate_amt numeric(18,4),
	tds_ecess_amt numeric(18,4),
	tds_surcharge_amt numeric(18,4),
	tds_amt numeric(18,4),
	bill_amt numeric(18,4),
	branch_id bigint
)
AS
$BODY$ 
Begin	
	DROP TABLE IF EXISTS tds_payment_balance;	
	create temp TABLE  tds_payment_balance
	(	
		bill_tds_tran_id varchar(50), 
		voucher_id varchar(50), 
		doc_date date,
		supplier_id bigint,
		supplier varchar(250),
		tds_amt numeric(18,4),
		tds_base_rate_amt numeric(18,4),
		tds_ecess_amt numeric(18,4),
		tds_surcharge_amt numeric(18,4),
		bill_amt numeric(18,4),
		branch_id bigint
	);

	Insert into tds_payment_balance(bill_tds_tran_id, voucher_id, doc_date, supplier_id, supplier, 
			tds_base_rate_amt, tds_ecess_amt, tds_surcharge_amt, 
			tds_amt, bill_amt, branch_id)
	Select a.bill_tds_tran_id, a.voucher_id, a.doc_date, a.supplier_id, b.supplier, 
		sum(a.tds_base_rate_amt), sum(a.tds_ecess_amt), sum(a.tds_surcharge_amt),
		sum(a.tds_base_rate_amt + a.tds_ecess_amt + a.tds_surcharge_amt) as tds_amt, sum(a.bill_amt) as bill_amt, a.branch_id
	From tds.bill_tds_tran a
	Inner Join ap.supplier b on a.supplier_id=b.supplier_id
	Inner Join ap.supplier_tax_info c on b.supplier_id = c.supplier_id
	where a.doc_date <= current_timestamp(0) 
		And (a.company_id=pcompany_id)
		And (a.branch_id=pbranch_id or pbranch_id=0)
		And (payment_id = '' or payment_id = ppayment_id)
		And c.tds_person_type_id = pperson_type_id
                And a.status = 5
	Group By a.bill_tds_tran_id, a.voucher_id, a.doc_date, a.supplier_id, b.supplier;

	return query 
	select a.bill_tds_tran_id, a.voucher_id, a.doc_date, a.supplier_id, a.supplier,  
		a.tds_base_rate_amt, a.tds_ecess_amt, a.tds_surcharge_amt, 
		a.tds_amt, a.bill_amt, a.branch_id
	from tds_payment_balance a;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION tds.fn_tdpy_info_for_gl_post(IN pvoucher_id character varying)
  RETURNS TABLE
  (
	index int4, 
	company_id bigint,
	branch_id bigint,
	dc char(1),
	account_id bigint,
	debit_amt_fc numeric(18,4),
	credit_amt_fc numeric(18,4),
	debit_amt numeric(18,4),
	credit_amt numeric(18,4)
) AS
$BODY$ 	
	Declare vInterestPenaltyAccount_ID bigint;
Begin	
	-- This function is used by the Posting Trigger to get information on the TDS 
	DROP TABLE IF EXISTS tds_vch_detail;	
	create temp TABLE  tds_vch_detail
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

	-- Fetch Control. 
	Insert into tds_vch_detail(company_id, branch_id, dc, account_id, 
		debit_amt_fc, credit_amt_fc, 
		debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 'C', a.account_id, 
		0, 0, 0, a.amt
	From tds.tds_payment_control a
	Where a.voucher_id=pvoucher_id;

	-- Fetch Tran
	Insert into tds_vch_detail(company_id, branch_id, dc, account_id, 
		debit_amt_fc, credit_amt_fc, 
		debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 'D', c.tds_account_id, 
		0, 0,
		tds_base_rate_amt + tds_ecess_amt + tds_surcharge_amt, 0
	From tds.bill_tds_tran a
	Inner Join ap.supplier_tax_info b on a.supplier_id = b.supplier_id
	Inner Join tds.section_acc c on b.tds_section_id = c.section_id
	Where a.payment_id=pvoucher_id;

	-- ****		Step 4: Fetch TDS Information (Credit)
	Select cast(value as bigint) into vInterestPenaltyAccount_ID from sys.settings where key='tds_interest_penalty_account';

	Insert into tds_vch_detail(company_id, branch_id, dc, account_id, 
		debit_amt_fc, credit_amt_fc, 
		debit_amt, credit_amt)
	Select a.company_id, a.branch_id, 'D', vInterestPenaltyAccount_ID, 
		0, 0,
		sum(interest_amt+penalty_amt), 0
	From tds.tds_payment_control a
	Where a.voucher_id=pvoucher_id
	group by a.company_id, a.branch_id
	having sum(interest_amt+penalty_amt) <>0;

	return query 
	select a.index, a.company_id, a.branch_id, a.dc, a.account_id, a.debit_amt_fc, a.credit_amt_fc, a.debit_amt, a.credit_amt
	from tds_vch_detail a;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
create OR REPLACE function tds.fn_get_tds_payment_for_returns(pcompany_id bigint, pbranch_id bigint, preturn_quarter varchar(2), pfrom_date date, pto_date date)
RETURNS TABLE  
(	
	payment_id varchar(50), 
	payment_date date,
	account_id bigint,
	account_head varchar(250),
	tds_total_amt numeric(18,4),
	interest_amt  numeric(18,4),
	penalty_amt numeric(18,4),
	tds_payment_amt numeric(18,4),
	bill_id varchar(50),
	bill_amt numeric(18,4),
	bill_date date,
	supplier_id bigint,
	supplier varchar(250),
	tds_base_rate_amt numeric(18,4),
	tds_ecess_amt numeric(18,4),
	tds_surcharge_amt numeric(18,4)
)
AS
$BODY$ 
Begin	
	DROP TABLE IF EXISTS tds_payment;	
	create temp TABLE  tds_payment
	(	
		payment_id varchar(50), 
		payment_date date,
		account_id bigint,
		account_head varchar(250),
		tds_total_amt numeric(18,4),
		interest_amt  numeric(18,4),
		penalty_amt numeric(18,4),
		tds_payment_amt numeric(18,4),
		bill_id varchar(50),
		bill_amt numeric(18,4),
		bill_date date,
		supplier_id bigint,
		supplier varchar(250),
		tds_base_rate_amt numeric(18,4),
		tds_ecess_amt numeric(18,4),
		tds_surcharge_amt numeric(18,4)
	);

	Insert into tds_payment(payment_id, payment_date, account_id, account_head, tds_total_amt, interest_amt, penalty_amt, 
			tds_payment_amt, bill_id, bill_amt, bill_date, supplier_id, supplier, 
			tds_base_rate_amt, tds_ecess_amt, tds_surcharge_amt)	
	select a.payment_id, a.payment_date, b.account_id, d.account_head, b.tds_total_amt, b.interest_amt, b.penalty_amt, 
			b.amt, a.voucher_id, a.bill_amt, a.doc_date, a.supplier_id, c.supplier, 
			a.tds_base_rate_amt, a.tds_ecess_amt, a.tds_surcharge_amt		
	from tds.bill_tds_tran a
	Inner Join tds.tds_payment_control b on a.payment_id= b.voucher_id
	Inner Join ap.supplier c on a.supplier_id = c.supplier_id
	Inner Join ac.account_head d on b.account_id = d.account_id
	where (b.challan_bsr !='' or b.challan_serial !='')
		And b.status=5
		And a.payment_id not in (Select e.payment_id from tds.tds_return_challan_tran e)
		And a.payment_date between pfrom_date and pto_date;

	return query 
	select a.payment_id, a.payment_date, a.account_id, a.account_head, a.tds_total_amt, a.interest_amt, a.penalty_amt, 
			a.tds_payment_amt, a.bill_id, a.bill_amt, a.bill_date, a.supplier_id, a.supplier, 
			a.tds_base_rate_amt, a.tds_ecess_amt, a.tds_surcharge_amt
	from tds_payment a;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION tds.fn_tds_deducted_report(IN pcompany_id bigint, pbranch_id bigint, pfrom_date date, pto_date date, psection_id bigint, pperson_type_id bigint)
RETURNS TABLE
(
    voucher_id varchar(50),
    doc_date date,
    section_id bigint,
    section varchar(50),
    section_desc varchar(250),
    person_type_id bigint,
    person_type_desc varchar(50),
    bill_amt  numeric(18,4),
    tds_base_rate_perc numeric(18,4),
    tds_base_rate_amt numeric(18,4),
    tds_ecess_amt numeric(18,4),
    tds_surcharge_amt numeric(18,4),
    total_amt numeric(18,4),
    amt_for_tds numeric(18,4),
    supplier character varying,
    supplier_pan character varying
)
AS
 $BODY$
 Begin 
        Return query
	select a.voucher_id, a.doc_date, a.section_id, b.section, b.section_desc, a.person_type_id, c.person_type_desc, 
		a.bill_amt, a.tds_base_rate_perc, a.tds_base_rate_amt, a.tds_ecess_amt, a.tds_surcharge_amt, (a.tds_base_rate_amt + a.tds_ecess_amt + a.tds_surcharge_amt),
		a.amt_for_tds, d.supplier, (d.annex_info->'satutory_details'->>'pan')::varchar as supplier_pan
	from tds.bill_tds_tran a
	inner join tds.section b on a.section_id = b.section_id
	inner join tds.person_type c on a.person_type_id = c.person_type_id
        inner join ap.supplier d on a.supplier_id = d.supplier_id
	Where (a.branch_id In (Select branch_id from sys.fn_get_cbr_group(pcompany_id, pbranch_id)) or pbranch_id = 0)
		AND a.company_id = pcompany_id
		And a.doc_date between pfrom_date and pto_date 		
		AND a.status=5
		And (a.section_id = psection_id or psection_id = 0)
		And (a.person_type_id = pperson_type_id or pperson_type_id = 0);
END;
$BODY$
  LANGUAGE plpgsql;
      
?==?
CREATE OR REPLACE FUNCTION ap.fn_tds_pymt_report(In pvoucher_id varchar(50))  
RETURNS TABLE
(
	voucher_id varchar(50), 
	doc_date date,        
	account_id bigint,
	account_head varchar(250), 
	status smallint,
	tds_total_amt numeric(18,4), 
	interest_amt numeric(18,4),
	penalty_amt numeric(18,4),
	cheque_number varchar(20),
	cheque_date date, 
	challan_bsr varchar(5), 
	challan_serial varchar(7),	
	narration varchar(500),
	amt_in_words varchar(250), 
	remarks varchar(500),
	amt numeric(18,4),
	entered_by varchar(100), 
	posted_by varchar(100)
) 
AS
$BODY$
BEGIN	
	return query
	select a.voucher_id, a.doc_date, 
		a.account_id, c.account_head, a.status, coalesce(a.tds_total_amt,0) as tds_total_amt, a.interest_amt, a.penalty_amt,
		a.cheque_number, a.cheque_date, a.challan_bsr, a.challan_serial, a.narration, a.amt_in_words, a.remarks, a.amt,
		d.entered_by, d.posted_by
	from tds.tds_payment_control a
		inner join ac.account_head c on a.account_id = c.account_id
		inner join sys.doc_es d on a.voucher_id = d.voucher_id
		where a.voucher_id = pvoucher_id;	
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION ap.fn_tds_pymt_tran_report(In pvoucher_id varchar(50))  
RETURNS TABLE
(
	branch_id bigint,
	voucher_id varchar(50),
	bill_tds_tran_id varchar(50),
	doc_date date,
	supplier_id bigint,
	bill_amt numeric(18,4),
	tds_base_rate_perc numeric(18,4),
	tds_base_rate_amt numeric(18,4),
	tds_ecess_perc numeric(18,4),
	tds_ecess_amt numeric(18,4),
	tds_surcharge_perc numeric(18,4),
	tds_surcharge_amt numeric(18,4),
	amt_for_tds numeric(18,4),
	payment_id varchar(50),
	payment_date date,
	supplier varchar(250)
) 
AS
$BODY$
BEGIN	
	return query
	select a.branch_id, a.voucher_id, a.bill_tds_tran_id, a.doc_date, a.supplier_id, a.bill_amt, 
		a.tds_base_rate_perc, a.tds_base_rate_amt, a.tds_ecess_perc, a.tds_ecess_amt, 
		a.tds_surcharge_perc, a.tds_surcharge_amt, a.amt_for_tds,
		a.payment_id, a.payment_date, b.supplier
	from tds.bill_tds_tran a
	inner join ap.supplier b on a.supplier_id = b.supplier_id
	where a.payment_id = pvoucher_id;
	       
END;
$BODY$
  LANGUAGE plpgsql;

?==?
Create OR Replace function tds.fn_tds_payments(pcompany_id bigint, pbranch_id bigint, pperson_type_id bigint, pfrom_date date, pto_date date, psupplier_id bigint)
Returns Table
(	bill_tds_tran_id varchar(50), 
	voucher_id varchar(50), 
	doc_date date,
	supplier_id bigint,
	supplier varchar(250),
	tds_base_rate_amt numeric(18,4),
	tds_ecess_amt numeric(18,4),
	tds_surcharge_amt numeric(18,4),
	tds_amt numeric(18,4),
	bill_amt numeric(18,4),
	branch_id bigint,
 	tdpy_id varchar(50),
 	tdpy_date date,
 	cheque_number character varying,
 	cheque_date date,
 	collected bool,
        collection_date date,
 	section_id bigint,
 	section  character varying
)
AS
$BODY$ 
Begin	
	DROP TABLE IF EXISTS tds_payment_balance;	
	Create temp TABLE  tds_payment_balance
	(	
		bill_tds_tran_id varchar(50), 
		voucher_id varchar(50), 
		doc_date date,
		supplier_id bigint,
		supplier varchar(250),
		tds_amt numeric(18,4),
		tds_base_rate_amt numeric(18,4),
		tds_ecess_amt numeric(18,4),
		tds_surcharge_amt numeric(18,4),
		bill_amt numeric(18,4),
		branch_id bigint,
                tdpy_id varchar(50),
                tdpy_date date,
                cheque_number character varying,
                cheque_date date,
                collected bool,
                collection_date date,
                section_id bigint,
 		section  character varying
	);

	Insert into tds_payment_balance(bill_tds_tran_id, voucher_id, doc_date, supplier_id, supplier, 
                    tds_base_rate_amt, tds_ecess_amt, tds_surcharge_amt, 
                    tds_amt, 
                    bill_amt, branch_id, tdpy_id, tdpy_date, section_id)
	Select a.bill_tds_tran_id, a.voucher_id, a.doc_date, a.supplier_id, b.supplier, 
		sum(a.tds_base_rate_amt), sum(a.tds_ecess_amt), sum(a.tds_surcharge_amt),
		sum(a.tds_base_rate_amt + a.tds_ecess_amt + a.tds_surcharge_amt) as tds_amt, 
            sum(a.bill_amt) as bill_amt, a.branch_id, a.payment_id, case when a.payment_id != '' then a.payment_date else '1970-01-01' end, a.section_id      
	From tds.bill_tds_tran a
	Inner Join ap.supplier b on a.supplier_id=b.supplier_id
	Inner Join ap.supplier_tax_info c on b.supplier_id = c.supplier_id    
	where a.doc_date between pfrom_date and pto_date
            And (a.company_id=pcompany_id)
            And (a.branch_id=pbranch_id or pbranch_id=0)
            And (c.tds_person_type_id = pperson_type_id or pperson_type_id =0)
            And a.status = 5
            And (a.supplier_id=psupplier_id or psupplier_id=0)
	Group By a.bill_tds_tran_id, a.voucher_id, a.doc_date, a.supplier_id, b.supplier;

	update tds_payment_balance a
        set cheque_number = b.cheque_number,
            cheque_date = b.cheque_date,
            collection_date = b.collection_date,
            collected = b.collected
        From tds.tds_payment_control b 
        where a.tdpy_id = b.voucher_id;
    
        update tds_payment_balance a
        set section = b.section
        from tds.section b
        where a.section_id = b.section_id;

	return query 
	select a.bill_tds_tran_id, a.voucher_id, a.doc_date, a.supplier_id, a.supplier,  
		a.tds_base_rate_amt, a.tds_ecess_amt, a.tds_surcharge_amt, 
		a.tds_amt, a.bill_amt, a.branch_id, a.tdpy_id, a.tdpy_date, a.cheque_number, a.cheque_date, a.collected, case when a.collected then a.collection_date else '1970-01-01' end, 
        a.section_id, a.section
	from tds_payment_balance a;
END
$BODY$ 
LANGUAGE plpgsql;

?==?
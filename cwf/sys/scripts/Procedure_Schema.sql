CREATE OR REPLACE FUNCTION sys.fn_report_defaults(IN pbranch_id bigint, IN pcompany_id bigint)
RETURNS TABLE
(
	company_name character varying, 
	branch_name character varying, 
	branch_address character varying, 
	currency_displayed character varying, 
	date_format character varying, 
	company_logo character varying, 
	header_template character varying
) AS
$BODY$
Declare vDateFormat varchar(50) = ''; vCurrencyDisplayed varchar(50) = ''; vGSTState_ID bigint; vGstState character varying = '';
Begin
	Drop table if exists tempResult;
	Create Temp Table tempResult (
		company_name varchar(500),
		branch_name varchar(100),
		branch_address varchar(250),
		currency_displayed varchar(50),
		date_format varchar(50),
		company_logo varchar(500),
		header_template varchar(500)
	);
	
	If pbranch_id=0 Or pbranch_id > ((pcompany_id * 1000000) + 500000) Then
		vGSTState_ID := pbranch_id - ((pcompany_id * 1000000) + 500000);

		Select gst_state_code || ' - ' || state_name into vGstState 
		from tx.gst_state 
		where gst_state_id = vGSTState_ID;
		
       	        select a.currency_displayed, a.date_format into vCurrencyDisplayed, vDateFormat 
	        from sys.branch a
	        where a.company_id = pcompany_id
	        order by a.branch_id asc limit 1;

		Insert into tempResult(company_name, branch_name, branch_address, currency_displayed, date_format, company_logo, 
				header_template)
		SELECT a.company_name, case when pbranch_id = 0 then 'Consolidated' else 'Consolidated For ' || vGstState end, a.company_address, vCurrencyDisplayed, vDateFormat, a.company_logo, 
			'cwf/report-templates/header-template.jrxml'
		FROM sys.company a
		Where a.company_id=pcompany_id;	
        else
		Insert into tempResult(company_name, branch_name, branch_address, currency_displayed, date_format, company_logo, 
			header_template)
		SELECT a.company_name, b.branch_name, b.branch_address, b.currency_displayed, b.date_format, a.company_logo, 
			'cwf/report-templates/header-template.jrxml'
		FROM sys.company a
		Inner Join sys.branch b on a.company_id=b.company_id
		Where b.branch_id=pbranch_id;
	End If;

	Return Query
	SELECT a.company_name, a.branch_name, a.branch_address, a.currency_displayed, a.date_format, a.company_logo, 
		a.header_template
	FROM tempResult a;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.fn_rpt_company_defaults()
RETURNS TABLE(
	key character varying, 
	value character varying
)
AS
$BODY$
Begin
	Drop table if exists tempResult;
	Create Temp Table tempResult (
		key character varying, 
		value character varying	
	);
	
	Insert into tempResult(key, value)
	SELECT a.key, a.value
	FROM sys.rpt_company_info a;	

	Return Query
	SELECT a.key, a.value
	FROM tempResult a;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE Function sys.build_doc_id(IN pdoc_type Varchar(4), IN pcompany_code Varchar(2), IN pbranch_code Varchar(2), 
	IN pfinyear Varchar(4), IN pv_id BigInt, OUT doc_id varchar(20))
Returns Varchar(20) 
As
$BODY$
Begin
	doc_id := pdoc_type || left(pfinyear, 2) || pbranch_code || lpad(pv_id::text, 5, '0');	
End
$BODY$
    Language plpgsql;

?==?
CREATE FUNCTION sys.sp_get_doc_id(IN pdoc_type varchar(50), IN pbranch_id bigint, IN pfinyear varchar(4), IN pdoc_table varchar(100), INOUT pnew_doc_id varchar(50), INOUT pnew_v_id bigint)
  RETURNS record AS
$BODY$
Declare
	vMax_id BigInt; vErrMsg Varchar(500); 
	vCompany_code Varchar(2); vBranch_code Varchar(2);
Begin

	If Not Exists(Select * from sys.doc_seq where doc_type=pdoc_type And finyear=pfinyear And branch_id=pbranch_id) Then
		-- Sequence does not exist. Therefore create a new sequence
		insert into sys.doc_seq(branch_id, doc_type, doc_table, finyear, max_voucher_no, lock_bit)
		values(pbranch_id, pdoc_type, pdoc_table, pfinyear, 0, false);
	End If;

	-- lock table
	update sys.doc_seq a
	Set lock_bit=true
	where a.doc_type=pdoc_type And a.branch_id=pbranch_id And a.finyear=pfinyear;

	-- Generate the next id
	Select a.max_voucher_no+1 into vMax_id
	From sys.doc_seq a
	Where a.doc_type=pdoc_type And a.branch_id=pbranch_id And a.finyear=pfinyear;

	-- Update and unlock
	update sys.doc_seq a
	Set 	max_voucher_no=vMax_id,
		lock_bit=false
	where a.doc_type=pdoc_type And a.branch_id=pbranch_id And a.finyear=pfinyear;

	-- generate output 
	Select company_code, branch_code into vCompany_code, vBranch_code
	From sys.branch where branch_id=pbranch_id;
	
	pnew_v_id := vMax_id;
	
	Select sys.build_doc_id(pdoc_type, vCompany_code, vBranch_code,	pfinyear, pnew_v_id) Into pnew_doc_id;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE FUNCTION sys.sp_get_mast_id(IN pcompany_id bigint, IN pmast_seq_type varchar(50), INOUT pnew_mast_id bigint)
  RETURNS bigint AS
$BODY$
Declare
	vMax_id BigInt; vErrMsg Varchar(500); 
Begin
	if pcompany_id is Null Then
		pcompany_id:=0;
	End if;

	If Not Exists(Select * from sys.mast_seq_tran where company_id=pcompany_id And mast_seq_type=pmast_seq_type) Then
	-- Sequence does not exist in tran. Therefore copy from parent
		insert into sys.mast_seq_tran(company_id, mast_seq_type, max_id, lock_bit)
		Select pcompany_id, pmast_seq_type, (pcompany_id * 1000000) + seed, false
		from sys.mast_seq a
		where a.mast_seq_type=pmast_seq_type;
	End If;

	-- lock table
	update sys.mast_seq_tran a
	Set lock_bit=true
	where a.mast_seq_type=pmast_seq_type And a.company_id=pcompany_id;

	-- Generate the next id
	Select a.max_id+1 into vMax_id
	From sys.mast_seq_tran a
	Where a.mast_seq_type=pmast_seq_type And a.company_id=pcompany_id;

	-- Update and unlock
	update sys.mast_seq_tran a
	Set 	max_id=vmax_id,
		lock_bit=false
	where a.mast_seq_type=pmast_seq_type And a.company_id=pcompany_id;

	-- generate output 
	pnew_mast_id := vmax_id;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE FUNCTION sys.fntablefieldcollection(
    IN pschema character varying,
    IN ptable character varying)
  RETURNS TABLE(column_name character varying, udt_name character varying, character_maximum_length integer, numeric_precision integer, numeric_scale integer, is_primary boolean) AS
$BODY$
Declare 
	vPrimaryKeyField varchar(50);
Begin
	Create Temp Table tempResult (
		column_name varchar(50),
		udt_name    varchar(50),
		character_maximum_length int,
		numeric_precision int,
		numeric_scale int,
		is_primary boolean Default (false)) 
	On commit drop;

	Insert into tempResult(column_name, udt_name, character_maximum_length, numeric_precision, numeric_scale)
	SELECT a.column_name::varchar(50), case When e.data_type != '' Then (a.data_type || '_' || e.udt_name)::varchar(50) Else a.udt_name::varchar(50) End, 
		a.character_maximum_length::int, a.numeric_precision::int, a.numeric_scale::int
	FROM information_schema.columns a 
	LEFT JOIN information_schema.element_types e
	     ON ((a.table_catalog, a.table_schema, a.table_name, 'TABLE', a.dtd_identifier)
	       = (e.object_catalog, e.object_schema, e.object_name, e.object_type, e.collection_type_identifier))
	where a.table_name=pTable and a.table_schema=pSchema
		and a.is_updatable='YES';

	Select b.column_name into vPrimaryKeyField
	From Information_schema.table_constraints a
	Inner Join Information_schema.key_column_usage b 
		On a.constraint_name=b.constraint_name and a.constraint_schema=b.constraint_schema 
	Where a.constraint_type='PRIMARY KEY' and a.table_schema=pSchema and a.table_name=pTable;

	update tempResult a
	Set	is_primary=true
	Where a.column_name=vPrimaryKeyField;

	Return Query
	SELECT a.column_name, a.udt_name, a.character_maximum_length, a.numeric_precision, 
		a.numeric_scale, a.is_primary
	FROM tempResult a;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE or replace FUNCTION sys.fn_table_def(pschema character varying, ptable character varying)
RETURNS TABLE
(   column_name character varying, 
    udt_name character varying, 
    character_maximum_length integer, 
    numeric_precision integer, 
    numeric_scale integer, 
    is_primary boolean
) 
AS
$BODY$
Declare 
	vPrimaryKeyField varchar(50); vtable Varchar(100):=pschema || '.' || ptable;
Begin
    -- Make entry in cache if table does not exist
    If Not Exists(Select table_name From sys.table_def Where table_name = vtable) Then
        Create Temp Table tempResult (
            column_name varchar(50),
            udt_name varchar(50),
            character_maximum_length int,
            numeric_precision int,
            numeric_scale int,
            is_primary boolean Default (false)) 
        On commit drop;

        Insert into tempResult(column_name, udt_name, character_maximum_length, numeric_precision, numeric_scale)
        SELECT a.column_name::varchar(50), case When e.data_type != '' Then (a.data_type || '_' || e.udt_name)::varchar(50) Else a.udt_name::varchar(50) End, 
            a.character_maximum_length::int, a.numeric_precision::int, a.numeric_scale::int
        FROM information_schema.columns a 
        LEFT JOIN information_schema.element_types e
             ON ((a.table_catalog, a.table_schema, a.table_name, 'TABLE', a.dtd_identifier)
               = (e.object_catalog, e.object_schema, e.object_name, e.object_type, e.collection_type_identifier))
        where a.table_name=pTable and a.table_schema=pSchema
            and a.is_updatable='YES';

        Select b.column_name into vPrimaryKeyField
        From Information_schema.table_constraints a
        Inner Join Information_schema.key_column_usage b 
            On a.constraint_name=b.constraint_name and a.constraint_schema=b.constraint_schema 
        Where a.constraint_type='PRIMARY KEY' and a.table_schema=pSchema and a.table_name=pTable;

        update tempResult a
        Set	is_primary=true
        Where a.column_name=vPrimaryKeyField;
    
        Insert Into sys.table_def(table_def_id, table_name, column_def)
        Select md5(vtable)::uuid, vtable, jsonb_agg(row_to_json(a))
        From tempResult a;
    
        Drop Table If Exists tempResult;
    End If;

    Return Query
    SELECT (col->>'column_name')::Varchar, (col->>'udt_name')::Varchar, (col->>'character_maximum_length')::Integer, 
        (col->>'numeric_precision')::Integer, (col->>'numeric_scale')::Integer, (col->>'is_primary')::Boolean
    FROM sys.table_def a, jsonb_array_elements(column_def) col
    Where a.table_name = vtable;
End;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE FUNCTION sys.fn_get_company_id (pbranch_id BigInt, out pcompany_id bigint)
RETURNS BigInt
AS
$BODY$
declare vCompany_ID bigint=0;
BEGIN
	SELECT company_id into vCompany_ID
	From sys.branch
	Where branch_ID=pbranch_id;
	
	-- generate output
	pcompany_ID := vCompany_ID;	
	
END
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.fn_get_cbr_group(IN pcompany_id bigint, IN pbranch_id bigint)
RETURNS TABLE  
(   branch_id bigint )
AS
$BODY$ 
Declare vCompanyGroupBase bigint  = 0;

BEGIN  
	DROP TABLE IF EXISTS get_cbr_group_temp;
	CREATE temp TABLE  get_cbr_group_temp
	(	
		branch_id bigint
	);	
		
	vCompanyGroupBase := (1000000 * pcompany_id) + 500000;
	if pbranch_id < vCompanyGroupBase Then
		Insert Into get_cbr_group_temp values(pbranch_id);
	Elseif pbranch_id > vCompanyGroupBase then
		Insert Into get_cbr_group_temp(branch_id)
		Select a.branch_id 
        From sys.branch a 
        Where a.gst_state_ID=(pbranch_id - vCompanyGroupBase)
        	and a.company_id=pcompany_id;
	End if;
	
	return query 
	select a.branch_id from get_cbr_group_temp a;

END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.sp_status_update_es(
    pvoucher_id character varying,
    pdoc_date date,
    pcurrent_status smallint,
    pnew_status smallint,
    pfull_user_name character varying,
    puser_name character varying)
  RETURNS void AS
$BODY$
Begin
	if not exists (Select * from sys.doc_es where voucher_id=pvoucher_id) Then
		Insert into sys.doc_es(voucher_id, doc_date, entered_by, entered_user, entered_on, posted_by, posted_user, posted_on)
		Select pvoucher_id, pdoc_date, pfull_user_name, puser_name, current_timestamp(0), '', '', NULL;
	End If;

	IF pnew_status = 5 Then 
		Update sys.doc_es 
		Set posted_by=pfull_user_name,
		    posted_user=puser_name,
		    posted_on=current_timestamp(0),
                    doc_date=pdoc_date
		where voucher_id=pvoucher_id;
	Else
		Update sys.doc_es 
		Set posted_by='',
		    posted_user='',
		    posted_on=NULL,
                    doc_date=pdoc_date
		where voucher_id=pvoucher_id;
	End If;
END;
$BODY$
  LANGUAGE plpgsql;
 
?==?
create Or REPLACE function sys.get_menu_for_user(puser_id bigint, pbranch_id bigint)
RETURNS TABLE
(
	menu_id bigint,
	en_access_level integer,
	parent_menu_id BigInt,
	menu_name varchar(100),
	menu_text varchar(250),
	menu_type smallint,
	bo_id uuid,
	link_path varchar(250),
	menu_code varchar(4)
) AS
$BODY$
Begin

	Drop table if exists  menu_temp;
	create temp table menu_temp
	(
		menu_id bigint,
		en_access_level integer,
		parent_menu_id BigInt,
		menu_name varchar(100),
		menu_text varchar(250),
		menu_type smallint,
		bo_id uuid,
		link_path varchar(250),
		menu_code varchar(4)
	) ;

	Drop table if exists access_temp;
	create temp table access_temp
	(
		menu_id bigint,
		menu_type integer,
		en_access_level integer,
		branch_id bigint
	);

	Insert into access_temp(menu_id, en_access_level, branch_id, menu_type)
	Select a.menu_id, a.en_access_level, a.branch_id, a.menu_type
	From (
		Select a.menu_id, a.role_id, cast(a.en_access_level as integer)en_access_level, a.branch_id, 1 as menu_type
		from sys.role_access_level_doc a
                where a.en_access_level <>0 and a.branch_id=pbranch_id
		union all
		Select a.menu_id, a.role_id, cast(a.en_access_level_master as integer), 0, 2 as menu_type
		from sys.role_access_level_master a
                where a.en_access_level_master <>0
		union all
		Select a.menu_id, a.role_id, cast(a.en_access_level_report as integer), a.branch_id, 3 as menu_type
		from sys.role_access_level_report a
                where a.en_access_level_report <>0 and a.branch_id=pbranch_id
                union all
		Select a.menu_id, a.role_id, cast(a.en_access_level_dataset as integer), a.branch_id, 3 as menu_type
		from sys.role_access_level_dataset a
                where a.en_access_level_dataset <>0 and a.branch_id=pbranch_id
		union all
		Select a.menu_id, a.role_id, cast(a.en_access_level_ui_form as integer), a.branch_id, 4 as menu_type
		from sys.role_access_level_ui_form a
                where a.en_access_level_ui_form <>0 and a.branch_id=pbranch_id
	) a
	Inner Join sys.role b on a.role_id=b.role_id
	Inner Join sys.role_to_user c on b.role_id=c.role_id
	where c.user_id=puser_id
	group by a.menu_id, a.en_access_level, a.branch_id, a.menu_type;
	
	with recursive menu_parts(menu_id, en_access_level, branch_id, menu_type)
	as
	(
		Select a.menu_id, a.en_access_level, a.branch_id, a.menu_type
		From access_temp a
		union all 
		Select b.parent_menu_id, 0, 0, 0
		from menu_parts a, sys.menu b 
		where b.menu_id=a.menu_id
	)
	Insert into menu_temp(menu_id, en_access_level, parent_menu_id, menu_name, menu_text, menu_type, bo_id, link_path, menu_code)
	select a.menu_id, max(b.en_access_level), a.parent_menu_id, a.menu_name, a.menu_text, a.menu_type, a.bo_id, a.link_path, a.menu_code
	from sys.menu a
	inner join menu_parts b on a.menu_id=b.menu_id
	where is_hidden=false
	group by a.menu_id, a.parent_menu_id, a.menu_key, a.menu_name, a.menu_text, a.menu_type, a.bo_id, a.link_path, a.menu_code
	Order By a.menu_key;

	Return Query
	SELECT a.menu_id, a.en_access_level, a.parent_menu_id, a.menu_name, a.menu_text, a.menu_type, a.bo_id, a.link_path, a.menu_code
	FROM menu_temp a;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.get_menu_for_userv2(
    IN puser_id bigint,
    IN pbranch_id bigint)
  RETURNS TABLE(
      menu_id bigint, 
      en_access_level integer, 
      parent_menu_id bigint, 
      menu_name character varying, 
      menu_text character varying, 
      menu_type smallint, 
      bo_id uuid, 
      link_path character varying, 
      menu_code character varying,
      count_class character varying
  ) AS
$BODY$
Begin

	Drop table if exists  menu_temp;
	create temp table menu_temp
	(
		menu_id bigint,
		en_access_level integer,
		parent_menu_id BigInt,
		menu_name varchar(100),
		menu_text varchar(250),
		menu_type smallint,
		bo_id uuid,
		link_path varchar(250),
		menu_code varchar(4),
                count_class character varying
	) ;

	Drop table if exists access_temp;
	create temp table access_temp
	(
		menu_id bigint,
		menu_type integer,
		en_access_level integer,
		branch_id bigint
	);

	Insert into access_temp(menu_id, en_access_level, branch_id, menu_type)
	Select b.menu_id, max(b.en_access_level), c.branch_id, d.menu_type
	From sys.role a
	Inner Join sys.role_access_level b on a.role_id = b.role_id
	Inner Join sys.user_branch_role c on a.role_id = c.role_id
	Inner Join sys.menu d on b.menu_id = d.menu_id
	where b.en_access_level <> 0 and c.branch_id = pbranch_id and c.user_id = puser_id
	Group By b.menu_id, c.branch_id, d.menu_type;
	
	with recursive menu_parts(menu_id, en_access_level, branch_id, menu_type)
	as
	(
		Select a.menu_id, a.en_access_level, a.branch_id, a.menu_type
		From access_temp a
		union all 
		Select b.parent_menu_id, 0, 0, 0
		from menu_parts a, sys.menu b 
		where b.menu_id=a.menu_id
	)
	Insert into menu_temp(menu_id, en_access_level, parent_menu_id, menu_name, menu_text, menu_type, bo_id, link_path, menu_code, count_class)
	select a.menu_id, max(b.en_access_level), a.parent_menu_id, a.menu_name, a.menu_text, a.menu_type, a.bo_id, a.link_path, a.menu_code, a.count_class
	from sys.menu a
	inner join menu_parts b on a.menu_id=b.menu_id
	where is_hidden=false
	group by a.menu_id, a.parent_menu_id, a.menu_key, a.menu_name, a.menu_text, a.menu_type, a.bo_id, a.link_path, a.menu_code, a.count_class
	Order By a.menu_key;

	Return Query
	SELECT a.menu_id, a.en_access_level, a.parent_menu_id, a.menu_name, a.menu_text, a.menu_type, a.bo_id, a.link_path, a.menu_code, a.count_class
	FROM menu_temp a;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
create Or REPLACE function sys.sp_get_branch_for_user(puser_id bigint)
 RETURNS TABLE(branch_id bigint,
		  branch_name varchar(100),
		  branch_address varchar(250),
		  date_format varchar(50)
		) AS
$BODY$
Begin

	Drop table if exists  branch_temp;
	create temp table branch_temp
	(
		branch_id bigint,
		branch_name varchar(100),
		branch_address varchar(250),
		date_format varchar(50)
	) ;

	Insert into branch_temp(branch_id, branch_name, branch_address, date_format)
	Select a.branch_id, d.branch_name, d.branch_address, d.date_format
	From (
		Select a.branch_id, a.role_id
		from sys.role_access_level_doc a
                where a.en_access_level <>0
                GROUP BY a.branch_id, a.role_id
		union all
		Select a.branch_id, a.role_id
		from sys.role_access_level_report a
                where a.en_access_level_report <>0
                GROUP BY a.branch_id, a.role_id
                union all
		Select a.branch_id, a.role_id
		from sys.role_access_level_dataset a
                where a.en_access_level_dataset <>0
                GROUP BY a.branch_id, a.role_id
		union all
		Select a.branch_id, a.role_id
		from sys.role_access_level_ui_form a
                where a.en_access_level_ui_form <>0
                GROUP BY a.branch_id, a.role_id
	) a
	Inner Join sys.role b on a.role_id=b.role_id
	Inner Join sys.role_to_user c on b.role_id=c.role_id
	inner join sys.branch d on a.branch_id=d.branch_id
	where c.user_id=puser_id
	group by a.branch_id, d.branch_name, d.branch_address, d.date_format;

	
	Return Query
	SELECT a.branch_id, a.branch_name, a.branch_address, a.date_format
	FROM branch_temp a;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
-- Procedure to sort voucher IDs (Immutable function)
CREATE OR REPLACE FUNCTION sys.fn_sort_vch(pvoucher_id varchar(50), out presult varchar(50))
RETURNS VARCHAR(50)
AS
$BODY$
Declare
	vch varchar(50); vch_split varchar[]; suffix Varchar(50); num_id varchar(20); vresult Varchar(50):='';
Begin
	If length(pvoucher_id)>0 Then
		-- Remove the Voucher Suffixes like :AJ, :TDS, etc.
		vch := split_part(pvoucher_id, ':', 1);
		suffix := split_part(pvoucher_id, ':', 2);
		-- Split vch based on '/'
		vch_split := string_to_array(vch, '/');
		-- Get Number part and pad for 8 chars with 0
		num_id := lpad(vch_split[array_length(vch_split, 1)], 8, '0');
		-- Update array
		vch_split[array_length(vch_split, 1)] := num_id;
		-- get result
		vresult := array_to_string(vch_split, '/') || Case When suffix!='' Then ':' || suffix Else '' End;		
	End If;

	-- Return Result
	presult:= vResult;
END
$BODY$
  LANGUAGE plpgsql IMMUTABLE;

?==?
CREATE OR REPLACE FUNCTION sys.fn_sort_vch_tran
(   pvoucher_id character varying,
	OUT presult character varying
)
RETURNS character varying
AS 
$BODY$
Declare
	vch varchar(50); vch_split varchar[]; suffix Varchar(50); num_id varchar(20); vresult Varchar(50):='';
Begin
	If length(pvoucher_id)>0 Then
		-- Split vch based on ':'
		vch_split := string_to_array(pvoucher_id, ':');
		-- Get Number part and pad for 8 chars with 0
		num_id := lpad(vch_split[array_length(vch_split, 1)], 4, '0');
		-- Update array
		vch_split[array_length(vch_split, 1)] := num_id;
		-- get result
		vresult := array_to_string(vch_split, ':');		
	End If;

	-- Return Result
	presult:= vResult;
END
$BODY$
LANGUAGE plpgsql IMMUTABLE;

?==?
Create or Replace Function sys.sp_dm_file_add(pcompany_id BigInt, pfile_name Varchar(256), pchecksum varchar(32), pfile_path Varchar(256), 
	InOut pdm_file_id BigInt, InOut pfile_store Varchar(10)) 
As
$$
Begin
	
	Select sys.sp_get_mast_id(pcompany_id, 'sys.dm_file', -1) Into pdm_file_id;
	pfile_store := 'F' || pdm_file_id::varchar;
	Insert Into sys.dm_file(dm_file_id, company_id, file_name, checksum, file_path, file_store, last_updated)
	Values(pdm_file_id, pcompany_id, pfile_name, pchecksum, pfile_path, pfile_store, current_timestamp(0));
 
End $$
language plpgsql;

?==?
Create or Replace Function sys.sp_dm_file_link(pcompany_id BigInt, pbusiness_object Varchar(128), pref_id Varchar(50), pdm_file_id BigInt) 
Returns void
As
$$
Declare
	vdm_filelink_id uuid;
Begin
	vdm_filelink_id := md5(pbusiness_object || pref_id || pdm_file_id::varchar);

	Insert Into sys.dm_filelink(dm_filelink_id, company_id, business_object, ref_id, dm_file_id, last_updated)
	Values(vdm_filelink_id, pcompany_id, pbusiness_object, pref_id, pdm_file_id, current_timestamp(0));
	
End $$
language plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.sp_dm_file_delete(pcompany_id BIGINT, pbusiness_object VARCHAR(128), pref_id VARCHAR(50), pdm_file_id BIGINT)
RETURNS void
AS
$BODY$
BEGIN
	DELETE FROM sys.dm_filelink
	WHERE dm_file_id = pdm_file_id 
		AND company_id = pcompany_id 
		AND business_object = pbusiness_object
		AND ref_id = pref_id;

	IF NOT EXISTS (SELECT * FROM sys.dm_filelink WHERE dm_file_id = pdm_file_id) THEN
		DELETE FROM sys.dm_file
		WHERE dm_file_id = pdm_file_id;
	END IF;
	
	RETURN;
END
$BODY$
LANGUAGE plpgsql;

?==?
Create or Replace Function sys.sp_doc_created(pdoc_id Varchar(50), pbranch_id BigInt, pbo_id Varchar(250), puser_id_created BigInt, pdoc_status BigInt)
RETURNS Void AS
$BODY$
Begin

	If Exists(Select doc_id from sys.doc_created where doc_id=pdoc_id) Then
		if pdoc_status > 1 Then
			Delete From sys.doc_created
			Where doc_id=pdoc_id;
		End if;
	Else
		Insert Into sys.doc_created(doc_id, branch_id, bo_id, user_id_created, doc_status, last_updated)
		Values(pdoc_id, pbranch_id, pbo_id, puser_id_created, pdoc_status, current_timestamp(0));
	End if;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.sp_doc_wf_move(
    pdoc_id character varying,
    pbranch_id BigInt,
    pfinyear character varying,
    pdoc_date date,
    pbo_id character varying,
    pedit_view character varying,
    pdoc_name character varying,
    pdoc_sender_comment character varying,
    puser_id_from bigint,
    pdoc_sent_on timestamp without time zone,
    pdoc_action character varying,
    puser_id_to bigint,
    pdoc_stage_id_from character varying,
    pdoc_stage_id character varying)
  RETURNS character varying AS
$BODY$
Declare
	vResult Varchar(500):='Error';
Begin

	If Exists(Select doc_id from sys.doc_wf where doc_id=pdoc_id) Then
		-- Move existing record to history (current action sent_on time becomes acted_on for previous record)
		Insert Into sys.doc_wf_history (doc_wf_history_id, doc_id, branch_id, finyear, doc_date, bo_id, edit_view, doc_name, 
			doc_sender_comment, user_id_from, doc_sent_on, doc_action, user_id_to, doc_acted_on, doc_stage_id_from, doc_stage_id, last_updated)
		Select md5(a.doc_id || a.doc_sent_on)::uuid, a.doc_id, a.branch_id, a.finyear, pdoc_date, a.bo_id, a.edit_view, a.doc_name, 
			a.doc_sender_comment, a.user_id_from, a.doc_sent_on, a.doc_action, a.user_id_to, pdoc_sent_on, a.doc_stage_id_from, a.doc_stage_id, a.last_updated
		From sys.doc_wf a 
		Where a.doc_id=pdoc_id;
		-- Update new record
		Update sys.doc_wf a
		Set 	branch_id = pbranch_id,
                        finyear = pfinyear,
                        doc_date = pdoc_date,
                        bo_id = pbo_id,
			edit_view = pedit_view,
			doc_name = pdoc_name,
			doc_sender_comment = pdoc_sender_comment,
			user_id_from = puser_id_from,
			doc_sent_on = pdoc_sent_on,
			doc_action = pdoc_action,
			user_id_to = puser_id_to,
			doc_stage_id_from = pdoc_stage_id_from,
			doc_stage_id = pdoc_stage_id,
			last_updated = current_timestamp(0)
		Where a.doc_id=pdoc_id;
	Else
		-- Is a first time insert
		Insert Into sys.doc_wf (doc_id, branch_id, bo_id, finyear, doc_date, edit_view, doc_name, 
			doc_sender_comment, user_id_from, doc_sent_on, doc_action, user_id_to, doc_stage_id_from, doc_stage_id, last_updated)
		Values (pdoc_id, pbranch_id, pbo_id, pfinyear, pdoc_date, pedit_view, pdoc_name, 
			pdoc_sender_comment, puser_id_from, pdoc_sent_on, pdoc_action, puser_id_to, pdoc_stage_id_from, pdoc_stage_id, current_timestamp(0));
	End If;

                
        -- Move the record if Action is Post
        If pdoc_action = 'P' Then
                Insert Into sys.doc_wf_history (doc_wf_history_id, doc_id, branch_id, finyear, doc_date, bo_id, edit_view, doc_name, 
                        doc_sender_comment, user_id_from, doc_sent_on, doc_action, user_id_to, doc_acted_on, doc_stage_id_from, doc_stage_id, last_updated)
                Select md5(a.doc_id || a.doc_sent_on)::uuid, a.doc_id, a.branch_id, a.finyear, pdoc_date, a.bo_id, a.edit_view, a.doc_name, 
                        a.doc_sender_comment, a.user_id_from, a.doc_sent_on, a.doc_action, a.user_id_to, pdoc_sent_on, a.doc_stage_id_from, a.doc_stage_id, a.last_updated
                From sys.doc_wf a 
                Where a.doc_id=pdoc_id;
                -- Remove the record
                Delete From sys.doc_wf Where doc_id=pdoc_id;
        End If;

        vResult:='OK';

Return vResult;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.fn_get_allow_print(pdoc_id character varying, pdoc_status bigint, pbo_id character varying)
  RETURNS boolean AS
$BODY$
Declare v_post_print_set bigint =-1;v_unpost_print_set bigint =-1;v_print_cnt bigint =-1;v_allow_print boolean = false;
	v_post_print bigint =-11;v_unpost_print bigint =-11;
BEGIN
Select count(*) into v_print_cnt from sys.doc_print_log where doc_id = pdoc_id and doc_status = pdoc_status;
Select cast(value as bigint) into v_unpost_print_set from sys.settings where key='print_allow_unpost';
Select cast(value as bigint) into v_post_print_set from sys.settings where key='print_allow_post';
Select print_allow_post into v_post_print From sys.doc_print_control Where bo_id = pbo_id;
Select print_allow_unpost into v_unpost_print From sys.doc_print_control Where bo_id = pbo_id;

IF v_post_print is NULL or v_post_print = -11 THEN
	v_post_print := v_post_print_set;
END IF;
IF v_unpost_print is NULL or v_unpost_print = -11 THEN
	v_unpost_print := v_unpost_print_set;
END IF;
IF pdoc_status = 5 THEN
	IF v_post_print = -1 THEN
		v_allow_print := true;
	ELSIF v_post_print = 0 THEN
		v_allow_print := false;
	ELSIF v_print_cnt < v_post_print THEN
		v_allow_print := true;
	ELSE
		v_allow_print := false;
	END IF;
ELSE
	IF v_unpost_print = -1 THEN
		v_allow_print := true;
	ELSIF v_unpost_print = 0 THEN
		v_allow_print := false;
	ELSIF v_print_cnt < v_unpost_print THEN
		v_allow_print := true;
	ELSE
		v_allow_print := false;
	END IF;
END IF;
return v_allow_print;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.fn_get_allow_export(pbo_id character varying)
  RETURNS boolean AS
$BODY$
Declare v_allow_export_set boolean = false;v_allow_export boolean = false;
	v_set_temp varchar(6);v_allow_export_res boolean = false;
BEGIN
Select cast(value as varchar(6)) into v_set_temp from sys.settings where key='export_allow';
Select export_allow into v_allow_export From sys.doc_print_control Where bo_id = pbo_id;

IF v_set_temp = 'true' THEN
	v_allow_export_set := true;
ELSE
	v_allow_export_set := false;
END IF;

IF v_allow_export is not NULL THEN
	v_allow_export_res := v_allow_export;
ELSE
	v_allow_export_res := v_allow_export_set;
END IF;

return v_allow_export_res;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.fn_get_allow_report_mail(pbo_id character varying)
  RETURNS boolean AS
$BODY$
Declare v_allow_report_mail_set boolean = false;v_allow_report_mail boolean = false;
	v_set_temp varchar(6);v_allow_report_mail_res boolean = false;
BEGIN
Select cast(value as varchar(6)) into v_set_temp from sys.settings where key='report_mail_allow';
Select report_mail_allow into v_allow_report_mail From sys.doc_print_control Where bo_id = pbo_id;

IF v_set_temp = 'true' THEN
	v_allow_report_mail_set := true;
ELSE
	v_allow_report_mail_set := false;
END IF;

IF v_allow_report_mail is not NULL THEN
	v_allow_report_mail_res := v_allow_report_mail;
ELSE
	v_allow_report_mail_res := v_allow_report_mail_set;
END IF;

return v_allow_report_mail_res;
END;
$BODY$
LANGUAGE plpgsql;

?==?
create or replace Function sys.fn_update_user_widget(pwidget_id bigint,puser_id bigint) 
	returns boolean as
$body$
declare v_uw_count bigint = 0;v_result boolean = false;
begin
	
	select count(*) into v_uw_count From sys.user_widget_access
		Where user_id = puser_id and widget_id = pwidget_id;
	if v_uw_count = 0 then
		insert into sys.user_widget_access (user_widget_access_id, user_id, widget_id)
		values ((select coalesce(max(user_widget_access_id), 0) +1 from sys.user_widget_access), puser_id, pwidget_id);
		v_result := true;
	end if;
	return v_result;
end;
$body$
language plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.fn_get_allow_audit_trail
	(pbo_id character varying, puser_id bigint, pbranch_id bigint)
  RETURNS boolean AS
$BODY$
Declare v_allow_audit_trail boolean = false; v_set_temp varchar(6);
	v_allow_audit_trail_res boolean = false; v_menu_id int;
BEGIN
Select cast(value as varchar(6)) into v_set_temp from sys.settings where key='audit_trail_allow';
select menu_id into v_menu_id from sys.menu where bo_id = md5(pbo_id)::uuid;
Select allow_audit_trail into v_allow_audit_trail From sys.user_access_level 
	Where menu_id = v_menu_id And user_id = puser_id And branch_id = pbranch_id;

IF v_set_temp = 'true' THEN
	v_allow_audit_trail_res := true;
	IF v_allow_audit_trail is not NULL THEN
		v_allow_audit_trail_res := v_allow_audit_trail;
	END IF;
ELSE
	v_allow_audit_trail_res := false;
END IF;

return v_allow_audit_trail_res;
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.get_doc_for_user(
    IN puser_id bigint,
    IN pbranch_id bigint)
  RETURNS TABLE(
      menu_id bigint, 
      en_access_level integer, 
      parent_menu_id bigint, 
      menu_name character varying, 
      menu_text character varying, 
      menu_type smallint, 
      bo_id uuid, 
      link_path character varying, 
      menu_code character varying,
      count_class character varying
  ) AS
$BODY$
Begin

	Drop table if exists  menu_temp;
	create temp table menu_temp
	(
		menu_id bigint,
		en_access_level integer,
		parent_menu_id BigInt,
		menu_name varchar(100),
		menu_text varchar(250),
		menu_type smallint,
		bo_id uuid,
		link_path varchar(250),
		menu_code varchar(4),
                count_class character varying
	) ;

	Drop table if exists access_temp;
	create temp table access_temp
	(
		menu_id bigint,
		menu_type integer,
		en_access_level integer,
		branch_id bigint
	);

	Insert into access_temp(menu_id, en_access_level, branch_id, menu_type)
	Select b.menu_id, max(b.en_access_level), c.branch_id, d.menu_type
	From sys.role a
	Inner Join sys.role_access_level b on a.role_id = b.role_id
	Inner Join sys.user_branch_role c on a.role_id = c.role_id
	Inner Join sys.menu d on b.menu_id = d.menu_id
	where b.en_access_level <> 0 and c.branch_id = pbranch_id and c.user_id = puser_id and d.menu_type in (1,2,4)
	Group By b.menu_id, c.branch_id, d.menu_type;
	
	with recursive menu_parts(menu_id, en_access_level, branch_id, menu_type)
	as
	(
		Select a.menu_id, a.en_access_level, a.branch_id, a.menu_type
		From access_temp a
		union all 
		Select b.parent_menu_id, 0, 0, 0
		from menu_parts a, sys.menu b 
		where b.menu_id=a.menu_id
	)
	Insert into menu_temp(menu_id, en_access_level, parent_menu_id, menu_name, menu_text, menu_type, bo_id, link_path, menu_code, count_class)
	select a.menu_id, max(b.en_access_level), a.parent_menu_id, a.menu_name, a.menu_text, a.menu_type, a.bo_id, a.link_path, a.menu_code, a.count_class
	from sys.menu a
	inner join menu_parts b on a.menu_id=b.menu_id
	where a.is_hidden=false
	group by a.menu_id, a.parent_menu_id, a.menu_key, a.menu_name, a.menu_text, a.menu_type, a.bo_id, a.link_path, a.menu_code, a.count_class
	Order By a.menu_key;

	Return Query
	SELECT a.menu_id, a.en_access_level, a.parent_menu_id, a.menu_name, a.menu_text, a.menu_type, a.bo_id, a.link_path, a.menu_code, a.count_class
	FROM menu_temp a;
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.sp_doc_wf_archive(
    pdoc_id character varying,
    pbranch_id BigInt,
    pfinyear character varying,
    pdoc_date date,
    pbo_id character varying,
    pedit_view character varying,
    pdoc_name character varying,
    pdoc_sender_comment character varying,
    puser_id_from bigint,
    pdoc_action character varying,
    puser_id_to bigint,
    pdoc_stage_id_from character varying,
    pdoc_stage_id character varying)
  RETURNS void AS
$BODY$
Declare
Begin

    if exists (select * from  sys.doc_wf a Where a.doc_id=pdoc_id) Then 
        Insert Into sys.doc_wf_history (doc_wf_history_id, doc_id, branch_id, finyear, doc_date, bo_id, edit_view, doc_name, 
                doc_sender_comment, user_id_from, doc_sent_on, doc_action, user_id_to, doc_acted_on, doc_stage_id_from, doc_stage_id, last_updated)
        Select md5(a.doc_id || a.doc_sent_on)::uuid, a.doc_id, a.branch_id, a.finyear, pdoc_date, a.bo_id, a.edit_view, a.doc_name, 
                pdoc_sender_comment, puser_id_from, a.doc_sent_on, pdoc_action, puser_id_to, a.doc_sent_on, a.doc_stage_id_from, a.doc_stage_id, current_timestamp(0)
        From sys.doc_wf a 
        Where a.doc_id=pdoc_id;
        -- Remove the record
        Delete From sys.doc_wf Where doc_id=pdoc_id;
    Else
        Insert Into sys.doc_wf_history (doc_wf_history_id, doc_id, branch_id, finyear, doc_date, bo_id, edit_view, doc_name, 
                doc_sender_comment, user_id_from, doc_sent_on, doc_action, user_id_to, doc_acted_on, doc_stage_id_from, doc_stage_id, last_updated)
        values(md5(pdoc_id || ((current_timestamp(0))::varchar))::uuid, pdoc_id, pbranch_id, pfinyear, pdoc_date, pbo_id, pedit_view, pdoc_name, 
                pdoc_sender_comment, puser_id_from, current_timestamp(0), pdoc_action, puser_id_to, current_timestamp(0), pdoc_stage_id_from, pdoc_stage_id, current_timestamp(0));
    End If;
End;
$BODY$
  LANGUAGE plpgsql;

?==?

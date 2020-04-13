CREATE OR REPLACE FUNCTION sys.fn_sort_key(IN pkey character varying, OUT presult character varying)
  RETURNS character varying AS
$BODY$
Begin
	if pkey Is Not Null And length(pkey)>1 Then
		presult := left(pkey, 1) || lpad(substring(pkey from 2), 4, '0');
	Else 
		presult := pkey; 
	End if;

End;
$BODY$
  LANGUAGE plpgsql IMMUTABLE;

?==?
Create function sys.to_time_zone(vtime_stamp timestamp, Out vwith_time_zone timestamp)
Returns timestamp
As
$BODY$
Begin
        -- Any change in logic of this function should also be updated in vsla/utils/TsqlModifier
	vwith_time_zone := vtime_stamp + time '05:30';
End;
$BODY$
 Language plpgsql IMMUTABLE;

?==?
Create function sys.time_display(vtime_stamp timestamp, Out vformatted_time Varchar(50))
Returns Varchar(50)
As
$BODY$
Begin
        -- Any change in logic of this function should also be updated in vsla/utils/TsqlModifier
	vformatted_time := to_char(sys.to_time_zone(vtime_stamp), 'YYYY-MM-DD HH24:MI:SS');
End;
$BODY$
 Language plpgsql IMMUTABLE;

?==?
Create Function sys.fn_get_menu_id_by_name(pmenu_name varchar(250)) 
Returns BigInt
As $BODY$
Declare
	vMenu_id BigInt;
Begin
	Select menu_id into vMenu_id
	From sys.menu
	where menu_name=pmenu_name;

	If vMenu_id is null Then
		vMenu_id:=-1;
	End If;
	Return vMenu_id;

End;
$BODY$
  Language 'plpgsql';

?==?
Create Function sys.sp_get_menu_key(pparent_menu_name Varchar(100)) 
Returns Varchar(10) 
As
$BODY$
Declare
	vMenu_key Varchar(10) = ''; vMenu_keycode Varchar(1) = ''; vSeq BigInt = 0;
	vParent_menu_id BigInt = 0; vParent_menu_key Varchar(10) = ''; 
Begin
	-- Find parent menu key
	Select menu_id, menu_key Into vParent_menu_id, vParent_menu_key
	From sys.menu
	Where menu_name=pparent_menu_name;

	-- Find key code of new menu code
	If (vParent_menu_key != '') Then
		vMenu_keycode := chr(ascii(left(vParent_menu_key, 1))+1);
	Else
		vMenu_keycode := 'A';
	End If;

	-- Insert the sequence if required
	If Not Exists(Select * From sys.menu_seq Where menu_level=vMenu_keycode) Then
		Insert Into sys.menu_seq (menu_level, max_id)
		Values(vMenu_keycode, 0);
	End if;

	-- Generate new key
	SELECT max_id + 1 INTO vSeq
	FROM sys.menu_seq 
	WHERE menu_level = vMenu_keycode;
	
	UPDATE sys.menu_seq
	SET max_id = vSeq
	WHERE menu_level = vMenu_keycode;

	-- create the menu_key
	vMenu_key := vMenu_keycode || lpad(cast(vSeq as text), 4, '0');

Return vMenu_key;

End
$BODY$
 Language plpgsql;

?==?
-- Function for handle divide by zero error
CREATE FUNCTION sys.fn_handle_zero_divide(IN pnumerator numeric, IN pdenominator numeric, OUT presult numeric)
RETURNS numeric AS 
$BODY$ 
	Declare vResult numeric(18, 5) = 0;
BEGIN 
	If pdenominator = 0 then
		vResult = 0;
	else
		vResult = pnumerator / pdenominator;
	End If;	
        -- generate result
        presult := vResult;
END
$BODY$
  LANGUAGE plpgsql;

?==?
-- Function for handle divide by zero error
CREATE FUNCTION sys.fn_handle_round(IN ptype varchar(4), IN pvalue numeric, OUT presult numeric)
RETURNS numeric AS 
$BODY$ 
	Declare vResult numeric(18, 5) = 0; vRoundDecimal smallint = 2;
BEGIN 
	If ptype = 'qty' Then
		vRoundDecimal := 3;
	Elseif ptype = 'rate' Then
		vRoundDecimal := 3;
	Elseif ptype = 'amt' Then
		vRoundDecimal := 2;
	Else
		vRoundDecimal := 2;		
	End If;

	vResult = round(pvalue, vRoundDecimal);
        -- generate result
        presult := vResult;
END
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.get_menu_access_for_user(IN puser_id bigint, IN pbranch_id bigint, IN pbo_id uuid) 
RETURNS bigint
AS
$BODY$
declare vResult bigint; vmenu_id bigint;
Begin
        Select menu_id into vmenu_id from sys.menu where bo_id=pbo_id;	
	Select max(x.en_access_level) into vResult
	From (
		Select a.menu_id, a.role_id, cast(a.en_access_level as integer)en_access_level, a.branch_id, 1 as menu_type
		from sys.role_access_level_doc a
                where a.en_access_level <>0 and a.branch_id=pbranch_id and a.menu_id=vmenu_id
		union all
		Select a.menu_id, a.role_id, cast(a.en_access_level_master as integer), 0, 2 as menu_type
		from sys.role_access_level_master a
                where a.en_access_level_master <>0 and a.menu_id=vmenu_id
		union all
		Select a.menu_id, a.role_id, cast(a.en_access_level_report as integer), a.branch_id, 3 as menu_type
		from sys.role_access_level_report a
                where a.en_access_level_report <>0 and a.branch_id=pbranch_id and a.menu_id=vmenu_id
                union all
		Select a.menu_id, a.role_id, cast(a.en_access_level_dataset as integer), a.branch_id, 3 as menu_type
		from sys.role_access_level_dataset a
                where a.en_access_level_dataset <>0 and a.branch_id=pbranch_id and a.menu_id=vmenu_id
		union all
		Select a.menu_id, a.role_id, cast(a.en_access_level_ui_form as integer), a.branch_id, 4 as menu_type
		from sys.role_access_level_ui_form a
                where a.en_access_level_ui_form <>0 and a.branch_id=pbranch_id and a.menu_id=vmenu_id
	) x
	Inner Join sys.role b on x.role_id=b.role_id
	Inner Join sys.role_to_user c on b.role_id=c.role_id
	where c.user_id=puser_id;
	return vResult;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
Create function sys.fn_branch_with_ibaccount(pcompany_id bigint)
RETURNS TABLE
(       branch_id bigint,
	branch_name varchar(100),
	branch_code varchar(2),
        gst_state_id BigInt
) 
As
$BODY$
	declare vBranchCount smallint;
Begin

	Select count(*) into vBranchCount from sys.branch;

	If vBranchCount = 1 then
		Return Query
                SELECT a.branch_id, a.branch_name, a.branch_code, a.gst_state_id
		From sys.branch a;
	Else
		Return Query
                SELECT a.branch_id, a.branch_name, a.branch_code, a.gst_state_id
		From sys.branch a
		Where a.company_id = pcompany_id
			And a.branch_id in (Select b.branch_id from ac.ib_account b)
		order by a.branch_name;
	End If;
	
END;
$BODY$
 LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.get_menu_access_for_userv2(
    puser_id bigint,
    pbranch_id bigint,
    pbo_id uuid)
  RETURNS bigint AS
$BODY$
declare vResult bigint; vmenu_id bigint;
Begin
        Select menu_id into vmenu_id from sys.menu where bo_id=pbo_id;
	Select max(b.en_access_level) into vResult
	From sys.role a
	Inner Join sys.role_access_level b on a.role_id = b.role_id
	Inner Join sys.user_branch_role c on a.role_id = c.role_id
	where b.en_access_level <> 0 and c.branch_id = pbranch_id and c.user_id = puser_id and b.menu_id=vmenu_id;
	return vResult;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE FUNCTION sys.fn_handle_zero_divide_big(IN pnumerator numeric, IN pdenominator numeric, OUT presult numeric)
RETURNS numeric AS 
$BODY$ 
	Declare vResult numeric = 0;
BEGIN 
	If pdenominator = 0 then
		vResult = 0;
	else
		vResult = pnumerator / pdenominator;
	End If;	
        -- generate result
        presult := vResult;
END
$BODY$
  LANGUAGE plpgsql;

?==?
Create OR REPLACE function sys.fn_branch_in_gst_state(pbranch_id bigint)
RETURNS TABLE
(   branch_id bigint,
	branch_name varchar(100),
	branch_code varchar(2)
) 
As
$BODY$
	declare vGstState_ID int; vGstStateName character varying;
Begin
	Select a.gst_state_id into vGstState_ID 
	from sys.branch a
	Where a.branch_id = pbranch_id;
	
	Select a.gst_state_code || ' - ' ||a.state_name into vGstStateName
	from tx.gst_state a
	Where a.gst_state_id = vGstState_ID;
	
	return query
	select a.branch_id, a.branch_name, a.branch_code 
	from (        
		Select 0 as branch_id, vGstStateName as branch_name, vGstState_ID::varchar as branch_code
		union All
		(
			select a.branch_id, a.branch_name, a.branch_code 
			from sys.branch a
			where gst_state_id = vGstState_ID
			order by a.branch_name asc
		)
	) a;
	
END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.pending_docs(
	pcompany_id bigint,
	pbranch_id bigint,
	pdoc_bo_id character varying,
	pdoc_action_id character varying, 
	pfrom_user_id bigint,
	pto_user_id bigint,
        pfrom_date date,
	pto_date date)
    RETURNS TABLE(doc_id character varying, doc_date date, doc_sender_comment character varying, 
                  user_id_from bigint, doc_sent_on date, doc_action character varying, 
                  user_id_to bigint, doc_stage_id character varying, doc_stage_id_from character varying,
                  branch_id bigint, branch_name character varying, bo_id character varying,
                  menu_text character varying, from_user character varying, to_user character varying) 
    
AS
$BODY$
Begin  
    DROP TABLE IF EXISTS pending_docs_temp;	
    Create temp TABLE pending_docs_temp
    (doc_id character varying, doc_date date, doc_sender_comment character varying, 
    user_id_from bigint, doc_sent_on date, doc_action character varying, 
    user_id_to bigint, doc_stage_id character varying, doc_stage_id_from character varying,
    branch_id bigint, branch_name character varying, bo_id character varying,
    menu_text character varying, from_user character varying, to_user character varying) ;
                  
    If pdoc_action_id = 'O' Then            
   	Insert into pending_docs_temp (doc_id, doc_date, doc_sender_comment, user_id_from, doc_sent_on, 
        doc_action, user_id_to, doc_stage_id, doc_stage_id_from,
        branch_id, branch_name, bo_id, menu_text, from_user, to_user)
        select a.doc_id, a1.doc_date, 'Saved' as doc_sender_comment, a.user_id_created as user_id_from, 
        a.last_updated::date as doc_sent_on, 'Saved' as doc_action,-1 as user_id_to, 
        'saved' as doc_stage_id, 'new' as doc_stage_id_from, a.branch_id,
        b.branch_name, a.bo_id, c.menu_text, '' as from_user,'' as to_user
        from sys.doc_created a
        inner join sys.doc_es a1 on a.doc_id = a1.voucher_id
        inner join sys.branch b on a.branch_id = b.branch_id 
        inner join sys.menu c on md5(a.bo_id)::uuid = c.bo_id
        where (pdoc_action_id = 'O') 
        and (a.branch_id = pbranch_id or pbranch_id=0) 
        and a1.doc_date between pfrom_date and pto_date
        and (a.bo_id = pdoc_bo_id or pdoc_bo_id = 'All') 
        and (a.user_id_created = pfrom_user_id or pfrom_user_id=-99);
     Else
        Insert into pending_docs_temp (doc_id, doc_date, doc_sender_comment, user_id_from, doc_sent_on, 
        doc_action, user_id_to, doc_stage_id, doc_stage_id_from,
        branch_id, branch_name, bo_id, menu_text, from_user, to_user)
        select a.doc_id, a.doc_date, a.doc_sender_comment, a.user_id_from, a.doc_sent_on::date, 
        case when a.doc_action = 'S' then 'Sent' 
             when a.doc_action = 'A' then 'Approved'
             when a.doc_action = 'R' then 'Rejected'
             when a.doc_action = 'U' then 'Unposted'
             when a.doc_action = 'I' then 'Assigned'
             when a.doc_action = 'O' then 'Saved'
             else 'UnKnown'
        end as doc_action, 
        a.user_id_to, a.doc_stage_id, a.doc_stage_id_from, a.branch_id, b.branch_name, a.bo_id, c.menu_text,
        '' as from_user,'' as to_user from sys.doc_wf a                
        inner join sys.branch b on a.branch_id = b.branch_id 
        inner join sys.menu c on md5(a.bo_id)::uuid = c.bo_id
        where a.doc_action != 'P' 
        and (case when pdoc_action_id <> 'W' then a.doc_action = pdoc_action_id else pdoc_action_id = 'W' end) 
        and (a.branch_id = pbranch_id or pbranch_id=0) 
        and (a.doc_date between pfrom_date and pto_date)       
        and (a.bo_id = pdoc_bo_id or pdoc_bo_id = 'All')
        and (a.user_id_from = pfrom_user_id or pfrom_user_id=-99) 
        and (a.user_id_to = pto_user_id or pto_user_id=-99) ;
     End If;
       
	Update pending_docs_temp a
        set from_user = b.full_user_name
        From sys.user b
        Where a.user_id_from = b.user_id;
            
    Update pending_docs_temp a
    	set to_user = b.full_user_name
    	From sys.user b
    	Where a.user_id_to = b.user_id;         
        
    RETURN query 
    Select a.doc_id, a.doc_date, a.doc_sender_comment, a.user_id_from, a.doc_sent_on, 
        a.doc_action, a.user_id_to, a.doc_stage_id, a.doc_stage_id_from,
        a.branch_id, a.branch_name, a.bo_id, a.menu_text, a.from_user, a.to_user
    From pending_docs_temp a;
                    
End
$BODY$
Language plpgsql;

?==?
CREATE or replace Function sys.get_months(pfrom_date Date, pto_date date) 
Returns table
(
   month_name varchar(20),
   start_date date,
   end_date date	
) 
AS
$BODY$
    Declare vstart_date Date; vend_date Date;
Begin

    Drop Table If Exists mth_temp;
    Create Temp Table mth_temp
    (
       month_name varchar(20),
       start_date date,
       end_date date	
    );

   If (pto_date>=pfrom_date) Then
		
        vstart_date = pfrom_date;
        SELECT (date_trunc('month', vstart_date) + interval '1 month' - interval '1 day')::date into vend_date;

        WHILE  1 = 1 LOOP
        
          Insert into mth_temp(month_name, start_date, end_date)     
          Values (left(to_char(to_timestamp (date_part('month', vstart_date)::text, 'MM'), 'Month'),3), vstart_date, vend_date);
          
          Select (date_trunc('month', vstart_date) + interval '1 month')::date into vstart_date;
          Select (date_trunc('month', vstart_date) + interval '1 month' - interval '1 day')::date into vend_date;

          if  (vend_date >= pto_date) Then
              vend_date = pto_date;
          End if;

	  Exit When vstart_date > pto_date;
          
   	End loop;
     
    End If;
        
    return Query
    select a.month_name, a.start_date, a.end_date from mth_temp a;

End;
$BODY$
language plpgsql;

?==?

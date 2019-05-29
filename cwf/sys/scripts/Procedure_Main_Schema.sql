CREATE FUNCTION sys.sp_get_mast_id(IN pcompany_id bigint, IN pmast_seq_type varchar(50), INOUT pnew_mast_id bigint)
  RETURNS bigint AS
$BODY$
Declare
	vMax_id BigInt; vErrMsg Varchar(500); 
Begin
	If Not Exists(Select * from sys.mast_seq_tran where mast_seq_type=pmast_seq_type) Then
	-- Sequence does not exist in tran. Therefore copy from parent
		insert into sys.mast_seq_tran(company_id, mast_seq_type, max_id, lock_bit)
		Select pcompany_id, pmast_seq_type, seed, false
		from sys.mast_seq a
		where a.mast_seq_type=pmast_seq_type;
	End If;

	-- lock table
	update sys.mast_seq_tran a
	Set lock_bit=true
	where a.mast_seq_type=pmast_seq_type;

	-- Generate the next id
	Select a.max_id+1 into vMax_id
	From sys.mast_seq_tran a
	Where a.mast_seq_type=pmast_seq_type;

	-- Update and unlock
	update sys.mast_seq_tran a
	Set 	max_id=vmax_id,
		lock_bit=false
	where a.mast_seq_type=pmast_seq_type;

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
CREATE OR REPLACE FUNCTION sys.sp_user_session_get(IN puser_session_id character varying)
  RETURNS TABLE(user_id bigint, full_user_name character varying, user_name character varying, session_variables character varying) AS
$BODY$
Begin
	-- Update last refresh time
	Update sys.user_session
	Set last_refresh_time = current_timestamp(0)
	Where user_session_id = puser_session_id;

	-- retreive data
	Return query
	Select a.user_id, a.full_user_name, a.user_name, b.session_variables
        From sys.user a
        Inner Join sys.user_session b On a.user_id=b.user_id
        Where b.user_session_id=puser_session_id;
        
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE FUNCTION sys.sp_user_logout_set(puser_session_id character varying)
  RETURNS void AS
$BODY$
Begin
	-- Move all user sessions with same auth id to logout
	Insert into sys.user_logout(user_session_id, user_id, login_time, last_refresh_time, session_variables)
	Select user_session_id, user_id, login_time, last_refresh_time, session_variables
	From sys.user_session
	where auth_id=( Select auth_id 
			from sys.user_session 
			Where user_session_id=puser_session_id Limit 1);

	--  delete from user session
	Delete from sys.user_session
	where auth_id=( Select auth_id 
			from sys.user_session 
			Where user_session_id=puser_session_id Limit 1);

End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.sp_user_logout_get()
  RETURNS Table (user_session_id character varying) AS
$BODY$
Begin
	DROP TABLE IF EXISTS session_temp;
	CREATE temp TABLE session_temp
	(	user_session_id character varying 
	);
	
	-- Remove invalid sessions (without activity in the last 20 minutes)
	Insert Into session_temp(user_session_id)
	Select a.user_session_id
	From sys.user_logout a;

	-- Do cleanup of invalid sessions
	Insert Into sys.user_session_history(user_session_id, user_id, login_time, last_refresh_time, session_variables)
	Select a.user_session_id, b.user_id, b.login_time, b.last_refresh_time, b.session_variables
	From session_temp a
	Inner Join sys.user_logout b On a.user_session_id=b.user_session_id;

	Delete From sys.user_logout a
	Where a.user_session_id in (Select b.user_session_id From session_temp b); 
	
	-- return data
	Return query
	Select a.user_session_id
        From session_temp a;
        
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.sp_invalid_session_get(IN ptimeinterval character varying)
  RETURNS Table (user_session_id character varying) AS
$BODY$
Begin
	DROP TABLE IF EXISTS session_temp;
	CREATE temp TABLE session_temp
	(	user_session_id character varying 
	);
	
	-- Remove invalid sessions (without activity in the last timeInterval)
	Insert Into session_temp(user_session_id)
	Select a.user_session_id
	From sys.user_session a
	Where a.last_refresh_time < (current_timestamp(0) - ptimeInterval::Interval);

	-- Do cleanup of invalid sessions
	Insert Into sys.user_session_history(user_session_id, user_id, login_time, last_refresh_time, session_variables)
	Select a.user_session_id, b.user_id, b.login_time, b.last_refresh_time, b.session_variables
	From session_temp a
	Inner Join sys.user_session b On a.user_session_id=b.user_session_id;

	Delete From sys.user_session a
	Where a.user_session_id in (Select b.user_session_id From session_temp b); 
	
	-- return data
	Return query
	Select a.user_session_id
        From session_temp a;
        
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.sp_notification_mail_add(pmail_to varchar(250), pmail_from varchar(250), pbody text, psubject varchar(250),
			pcc varchar(2000), pbcc varchar(2000), preply_to varchar(100))
  RETURNS void AS
$BODY$
Begin
	Insert into sys.notification_mail (notification_mail_id, mail_to, mail_from, body, subject, cc, bcc, is_send, reply_to)
	Select (Select COALESCE(max(notification_mail_id), 0) + 1 from sys.notification_mail), pmail_to, pmail_from, pbody, psubject, pcc, pbcc, 0, preply_to;	
End;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.sp_user_pass_reset(IN puser_name character varying, IN pweb character varying)
  RETURNS TABLE(reset_id bigint, reset_uuid uuid) AS
$BODY$
Declare vReset_ID bigint=-1; vResetuuid uuid=null; vUser_ID bigint=-1; vUserName varchar(100)=''; vMailTo varchar(100)=''; VHeaderPart varchar(8000)=''; VFooterPart varchar(8000)=''; vBody varchar(8000) = '';
vCount smallint;
BEGIN  

	if exists (Select * from sys.user  where user_name=puser_name) then 
		Select COALESCE(max(a.reset_id), 0) + 1 into vReset_ID from sys.user_pass_reset a;
		Select uuid_generate_v4() into vResetuuid;
		Select user_id, full_user_name, email into vUser_ID, vUsername, vMailTo from sys.user where user_name=puser_name;
		If vUser_ID is Not  NULL Then
			Insert into sys.user_pass_reset(reset_id, user_id, reset_uuid, reset_status, used_time)
			Select vReset_ID, vUser_ID, vResetuuid, 0, null;
			
			If pweb != '' Then -- Send e-mail only when host is not blank
				Select '<html><head>Dear '|| vUsername ||',</head><body>' into VHeaderPart;		
				select '<BR/><BR/>Regards,<BR/> Core ERP Team <BR/><BR/></body></html>' into VFooterPart;
				Select VHeaderPArt || '<p>Your password reset link is '|| pweb || vResetuuid || VFooterPart into vBody;

				Insert into sys.notification_mail (notification_mail_id, mail_to, mail_from, body, subject, cc, bcc, is_send, reply_to)
				Select (Select COALESCE(max(notification_mail_id), 0) + 1 from sys.notification_mail), vMailTo, 'noreply@coreerp.com', vBody, 'CoreERP Password Reset', '', '', 0, '';	
			End If;
		End If;
	End If;
	
	return query 
	Select vReset_ID, vResetuuid;

END;
$BODY$
LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.sp_update_reset_status(preset_uuid uuid)
  RETURNS bigint AS
$BODY$
Declare vResetStatus bigint=-1;
BEGIN  
	Select reset_status into vResetStatus
	From sys.user_pass_reset
	Where reset_uuid=preset_uuid;

	if vResetStatus=0 Then
		update sys.user_pass_reset
		Set reset_status=1,
			used_time=current_timestamp(0)
		Where reset_uuid=preset_uuid;
	Else 	
		select -1 into vResetStatus;
	End If;

	return vResetStatus;
END;
$BODY$
  LANGUAGE plpgsql;

?==?

CREATE OR REPLACE FUNCTION sys.sp_rollback_reset_status(preset_uuid uuid)
  RETURNS bigint AS
$BODY$
Declare vResetStatus bigint=-1;
BEGIN  
	Select reset_status into vResetStatus
	From sys.user_pass_reset
	Where reset_uuid=preset_uuid;

	if vResetStatus=1 Then
		update sys.user_pass_reset
		Set reset_status=0,
			used_time=current_timestamp(0)
		Where reset_uuid=preset_uuid;
	Else 	
		select -1 into vResetStatus;
	End If;

	return vResetStatus;
END;
$BODY$
  LANGUAGE plpgsql;
?==?

CREATE OR REPLACE FUNCTION sys.sp_update_password(IN preset_uuid uuid, In pnew_password varchar(20))
RETURNS bigint 
AS
$BODY$
Declare vResetStatus bigint=-1; vUser_ID bigint=-1;
BEGIN  
	Select reset_status, user_id into vResetStatus, vUser_ID
	From sys.user_pass_reset
	Where reset_uuid=preset_uuid;

	if vResetStatus=1 then 
		update sys.user
		set user_pass=pnew_password
		where user_id=vUser_ID;
	
		update sys.user_pass_reset
		Set reset_status=2,
			used_time=current_timestamp(0)
		Where reset_uuid=preset_uuid;

		select 2 into vResetStatus;
	Else
		select -1 into vResetStatus;
	End IF;

	return vResetStatus;
END;
$BODY$
  LANGUAGE plpgsql;


?==?

Create or Replace Function sys.fn_track_report(preport_path Varchar(768)) 
Returns BigInt
As 
$BODY$
Declare 
	vtrack_report_id BigInt:=-1; vreport_uuid uuid;
Begin
	vreport_uuid = md5(preport_path);
	If Not Exists (Select * from sys.track_report Where report_uuid = vreport_uuid) Then
		Insert Into sys.track_report(track_report_id, report_uuid, report_path)
		Values(default, vreport_uuid, preport_path)
		Returning track_report_id into vtrack_report_id;
	Else
		Select track_report_id Into vtrack_report_id
		From sys.track_report
		Where report_uuid = vreport_uuid;
	End If;

Return vtrack_report_id;
End;
$BODY$
    Language plpgsql;

?==?
Create Function sys.sp_get_admin_menu_key(pparent_menu_name Varchar(100)) 
Returns Varchar(10) 
As
$BODY$
Declare
	vMenu_key Varchar(10) = ''; vMenu_keycode Varchar(1) = ''; vSeq BigInt = 0;
	vParent_menu_id BigInt = 0; vParent_menu_key Varchar(10) = ''; 
Begin
	-- Find parent menu key
	Select menu_id, menu_key Into vParent_menu_id, vParent_menu_key
	From sys.menu_admin
	Where menu_name=pparent_menu_name;

	-- Find key code of new menu code
	If (vParent_menu_key != '') Then
		vMenu_keycode := chr(ascii(left(vParent_menu_key, 1))+1);
	Else
		vMenu_keycode := 'A';
	End If;

	-- Insert the sequence if required
	If Not Exists(Select * From sys.menu_admin_seq Where menu_level=vMenu_keycode) Then
		Insert Into sys.menu_admin_seq (menu_level, max_id)
		Values(vMenu_keycode, 0);
	End if;

	-- Generate new key
	SELECT max_id + 1 INTO vSeq
	FROM sys.menu_admin_seq 
	WHERE menu_level = vMenu_keycode;
	
	UPDATE sys.menu_admin_seq
	SET max_id = vSeq
	WHERE menu_level = vMenu_keycode;

	-- create the menu_key
	vMenu_key := vMenu_keycode || lpad(cast(vSeq as text), 4, '0');

Return vMenu_key;

End
$BODY$
 Language plpgsql;

?==?
Create Function sys.fn_get_admin_menu_id_by_name(pmenu_name varchar(250)) 
Returns BigInt
As $BODY$
Declare
	vMenu_id BigInt;
Begin
	Select menu_id into vMenu_id
	From sys.menu_admin
	where menu_name=pmenu_name;

	If vMenu_id is null Then
		vMenu_id:=-1;
	End If;
	Return vMenu_id;

End;
$BODY$
  Language 'plpgsql';

?==?
Create Function sys.sp_user_token_create(puser_id BigInt, ptoken Varchar(32))
Returns Void 
As
$BODY$
Begin
	If Exists(Select * From sys.user_token where user_id=puser_id) Then
		Delete From sys.user_token
		Where user_id=puser_id;
	End If;

	Insert Into sys.user_token(user_id, token, created_on) 
	Values(puser_id, ptoken, current_timestamp(0));
End
$BODY$
language plpgsql;

?==?
CREATE OR REPLACE Function sys.sp_user_token_valid(ptoken varchar(32), OUT pusername varchar(50)) 
Returns varchar(50)
As 
$BODY$
	Declare vuser_id BigInt:=-1;
Begin
	Select user_id Into vuser_id
	From sys.user_token
	where token = ptoken And extract(epoch from(current_timestamp(0) - created_on)) < 60 ; 

	If vuser_id != -1 Then
		Select user_name Into pusername
		From sys.user
		Where user_id=vuser_id;
	End If;

	Delete From sys.user_token
	Where token = ptoken;

	Return;
End
$BODY$
language plpgsql;

?==?
CREATE FUNCTION sys.sp_notification_mail_atch_add(
    pmail_to character varying,
    pmail_from character varying,
    pbody text,
    psubject character varying,
    pcc character varying,
    pbcc character varying,
    preply_to character varying,
    pattach_path character varying)
  RETURNS void AS
$BODY$
Begin
	Insert into sys.notification_mail (notification_mail_id, mail_to, mail_from, body, subject, cc, bcc, is_send, reply_to, attachment_path)
	Select (Select COALESCE(max(notification_mail_id), 0) + 1 from sys.notification_mail), pmail_to, pmail_from, pbody, psubject, pcc, pbcc, 0, preply_to, pattach_path;	
End;
$BODY$
  LANGUAGE plpgsql;

?==?

create schema sys;
?==?

create schema aud;
?==?
Create Table sys.db_ver
(   db_ver_id Bigserial Primary Key,
    db_name varchar(50) Not Null,
    coreerp_ver varchar(50) Not Null,
    modules varchar(8000) Not Null,
    last_updated timestamp without time zone Not Null
);

?==?
CREATE TABLE sys.aud_seq
(
  aud_seq_type varchar(50) NOT NULL primary key,
  seed bigint NOT NULL
);

?==?
Create Function sys.sp_get_aud_id(pkey varchar(100), out pid bigint)
Returns bigint as
$BODY$
Declare vID bigint =-1;
Begin
	if Exists(select seed from sys.aud_seq where aud_seq_type=pkey) then
		Select seed+1 into vID from  sys.aud_seq where aud_seq_type=pkey;

		update  sys.aud_seq 
		Set seed=vID
		where aud_seq_type=pkey;
	Else
		Raise exception 'Sequence Error. Sequence not found for the Master Table - %' , pkey;		
	End If;

	-- Generate the output
	pid:=vID;
End;
$BODY$
  LANGUAGE plpgsql;
?==?

CREATE FUNCTION sys.sp_create_aud_table(IN pis_master boolean, IN pmaster_type varchar(50), OUT pis_new boolean)
  RETURNS boolean AS
$BODY$
Declare vIsNew boolean=false; vTblName varchar(50)=''; vSql varchar(2000)=''; 
Begin
	-- 	This procedure checks if the Audit Trail Table Exists. If not, it creates the table.
	select 'aud.'|| pmaster_type into vTblName;

	if(SELECT EXISTS (SELECT 1 FROM   information_schema.tables  WHERE  table_schema || '.' || table_name = vTblName ))= false then
		If pis_master = true then
			vSql:= 'Create table ' || vTblName || ' ( log_entry_id bigint primary key, master_type varchar(50) not null, master_id bigint not null, 
				en_log_action smallint  not null, user_name varchar(50) not null, machine_name varchar(50) not null, json_log text not null, 
				custom_action_desc varchar(250), last_updated timestamp not null default current_timestamp(0))' ;
		ElseIf pis_master = false then
			vSql:= 'Create table ' || vTblName || ' ( log_entry_id bigint primary key, document_type varchar(50) not null, voucher_id varchar(50) not null, 
				en_log_action smallint  not null, user_name varchar(50) not null, machine_name varchar(50) not null, json_log text not null, 
				custom_action_desc varchar(250), last_updated timestamp not null default current_timestamp(0))' ;
		End If;
		select true into vIsNew;
	End If ;
	
	execute vSql;
	-- generate output
	pis_new:=vIsNew;

End
$BODY$
  LANGUAGE plpgsql;
?==?

CREATE FUNCTION sys.sp_aud_log_add(pmaster_type varchar(50), pmaster_id bigint, pvoucher_id varchar(50), pen_log_action smallint, puser_name varchar(50), pjson_log text, pmachine_name varchar(50), pcustom_action_desc varchar(250))
  RETURNS void AS
$BODY$
Declare vtblName varchar(250)=''; vSql text=''; vIsNewDocObject boolean = false; vLogEntry_ID bigint=-1;
Begin

	-- -- Step 1: Generate fully qualified Table Name
-- 	select current_database()||'_aud.aud.'||pmaster_type into vtblName;

	-- Step 1: Generate fully qualified Table Name
	select 'aud.'||pmaster_type into vtblName;
	
	
	-- Step 2: Create Table if not already exists
	If pvoucher_id='' then 
		vSql:= 'select pis_new from sys.sp_create_aud_table(true, ''' || pmaster_type || ''')';
		execute vSql into vIsNewDocObject;
	Else
		vSql:= 'select pis_new from sys.sp_create_aud_table(false, ''' || pmaster_type || ''')' ;
		execute vSql into vIsNewDocObject;
	End If;
	

	-- Step 3: Create a new sequence if this is the first time (i.e audit trail table is created
	if vIsNewDocObject = true Then
		if not exists (select * from sys.aud_seq where aud_seq_type=pmaster_type) then
			Insert into sys.aud_seq(aud_seq_type, seed)
			Select pmaster_type, 0;
		End If;
	End If;

	-- Step 4: Make entry into Audit Trail
	select pid into vLogEntry_ID from sys.sp_get_aud_id(pmaster_type);

	If pvoucher_id='' then 
		vSql:= 'Insert into ' || vtblName || '(log_entry_id, master_type, master_id, en_log_action, user_name, machine_name, json_log, custom_action_desc)
			Select ' ||vLogEntry_ID || ',  ''' || pmaster_type || ''', ' ||pmaster_id || ', ' || pen_log_action || ', ''' || puser_name || ''', ''' || pmachine_name || ''', ''' || pjson_log || ''', ''' || pcustom_action_desc || '''';

-- 			raise exception '%, log ID = %', vSql, pmaster_type;
	Else
		vSql:= 'Insert into ' || vtblName || '(log_entry_id, document_type, voucher_id, en_log_action, user_name, machine_name, json_log, custom_action_desc)
			Select ' ||vLogEntry_ID || ',  ''' || pmaster_type || ''', ''' ||pvoucher_id || ''', ' || pen_log_action || ', ''' || puser_name || ''', ''' || pmachine_name || ''', ''' || pjson_log || ''', ''' || pcustom_action_desc || '''';
	End If;
	execute vSql;
END;
$BODY$
  LANGUAGE plpgsql;
?==?
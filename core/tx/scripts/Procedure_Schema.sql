CREATE OR REPLACE FUNCTION tx.sp_hsn_sc_rate_add_update(pcompany_id bigint, phsn_sc_id bigint, 
    pgst_rate_id bigint, phsn_sc_uom_id BigInt, pis_exempt Boolean)
  RETURNS void AS
$BODY$
Declare 
	vhsn_sc_rate_id uuid := md5(phsn_sc_id ||':' || pcompany_id)::uuid;
Begin
	if exists (select * from tx.hsn_sc_rate where hsn_sc_rate_id = vhsn_sc_rate_id) then
		update tx.hsn_sc_rate
		set gst_rate_id = pgst_rate_id,
                    hsn_sc_uom_id = phsn_sc_uom_id,
                    is_exempt = pis_exempt,
			last_updated = current_timestamp(0)
		where hsn_sc_rate_id = vhsn_sc_rate_id;
	else		
		Insert into tx.hsn_sc_rate(hsn_sc_rate_id, hsn_sc_id, company_id, gst_rate_id, hsn_sc_uom_id, is_exempt, last_updated)
		Values(vhsn_sc_rate_id, phsn_sc_id, pcompany_id, pgst_rate_id, phsn_sc_uom_id, pis_exempt, current_timestamp(0));
	End If;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
Create Or Replace function tx.sp_gstr2a_reco_add_update(pgst_ret_id BigInt, pgstr_resp_id BigInt, pjdata jsonb) 
Returns BigInt
As
$BODY$
Declare vgstr2a_reco_id BigInt := -1;
Begin
	If Exists(Select gst_ret_id From tx.gstr2a_reco Where gst_ret_id = pgst_ret_id) Then
		Update tx.gstr2a_reco
        Set gstr_resp_id = pgstr_resp_id,
        	jdata = pjdata,
            last_updated = current_timestamp(0)
        Where gst_ret_id = pgst_ret_id;
        Select gstr2a_reco_id From tx.gstr2a_reco Where gst_ret_id = pgst_ret_id Into vgstr2a_reco_id ;
	Else 
    	Select coalesce(max(gstr2a_reco_id), 0)+1 From tx.gstr2a_reco Into vgstr2a_reco_id;
		Insert Into tx.gstr2a_reco(gstr2a_reco_id, gst_ret_id, gstr_resp_id, jdata, last_updated)
        Values(vgstr2a_reco_id, pgst_ret_id, pgstr_resp_id, pjdata, current_timestamp(0));
	End If;

        Update tx.gst_ret
        Set annex_info = jsonb_set(annex_info, '{gstr2a_reco_info, gstr_resp_id}', (pgstr_resp_id::Varchar)::jsonb, true)
        Where gst_ret_id = pgst_ret_id;

	return vgstr2a_reco_id;
End;
$BODY$
language plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.sp_gstn_session_add_update(pcore_session character varying, pgstn_response jsonb, 
    pbranch_id bigint)
  RETURNS void AS
$BODY$
Begin
	if exists (select core_session_id from sys.gstn_session where core_session_id = pcore_session) then
		update sys.gstn_session
		set session_info = pgstn_response,
                    branch_id = pbranch_id,
			last_updated = current_timestamp(0)
		where core_session_id = pcore_session;
	else		
		Insert into sys.gstn_session(core_session_id,session_info,branch_id, last_updated, auth_time)
		Values(pcore_session, pgstn_response, pbranch_id, current_timestamp(0), current_timestamp(0));
	End If;
END;
$BODY$
  LANGUAGE plpgsql;

?==?
CREATE OR REPLACE FUNCTION sys.sp_gstn_end_session(pcore_session character varying)
	RETURNS void AS
$BODY$
Begin
	If exists (select core_session_id from sys.gstn_session where core_session_id = pcore_session) then
        Insert into sys.gstn_auth_history(core_session_id, session_info, branch_id, auth_time)		
        select core_session_id, session_info, branch_id, auth_time 
        from sys.gstn_session
        where core_session_id = pcore_session;
        
        Delete from sys.gstn_session 
        where core_session_id = pcore_session;
    End If;
END;
$BODY$
Language plpgsql;

?==?
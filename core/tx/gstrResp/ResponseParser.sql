/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
/**
 * Author:  girishshenoy
 * Created: Sep 4, 2017
 */

/*
﻿Create Table tx.gstr2a
(	gstr2a_id BigSerial Not Null Primary Key,
 	jdata JsonB Not Null Default '{}',
 	last_updated TimeStamp Not Null Default(current_timestamp(0))
);

Insert Into tx.gstr2a(jdata)
Values('JSON data goes here'::JsonB);

-- b2b
With inv_info
As
(	Select a.gstr2a_id, b2b->>'ctin' supp_ctin, to_date(inv->>'idt', 'DD-MM-YYYY') inv_dt, inv->>'inum' inv_num, inv->>'pos' pos, 
 		inv->>'rchrg' rchrg, inv->>'inv_typ' inv_typ,
 		(inv_itms->>'num')::BigInt sl_no, (inv_itms->'itm_det'->>'rt')::Numeric gst_pcnt, 
 		(inv_itms->'itm_det'->>'txval')::Numeric taxable_val, 
 		(inv_itms->'itm_det'->>'camt')::Numeric cgst_amt, (inv_itms->'itm_det'->>'samt')::Numeric sgst_amt,
 		(inv_itms->'itm_det'->>'iamt')::Numeric igst_amt, (inv_itms->'itm_det'->>'csamt')::Numeric cess_amt
	From tx.gstr2a a, jsonb_array_elements(jdata->'b2b') b2b, jsonb_array_elements(b2b->'inv') inv, 
 		jsonb_array_elements(inv->'itms') inv_itms
)
Select *
From inv_info;


-- b2cs
With inv_info
As
(	Select a.gstr2a_id, b2cs->>'pos' pos, 
 		(b2cs->>'rt')::Numeric gst_pcnt, 
 		(b2cs->>'txval')::Numeric taxable_val, 
 		(b2cs->>'camt')::Numeric cgst_amt, (b2cs->>'samt')::Numeric sgst_amt,
 		(b2cs->>'iamt')::Numeric igst_amt, (b2cs->>'csamt')::Numeric cess_amt
	From tx.gstr2a a, jsonb_array_elements(jdata->'b2cs') b2cs
)
Select sum(taxable_val)
From inv_info
Where gstr2a_id = 5;

-- hsn
With inv_info
As
(	Select a.gstr2a_id, hsn->>'num' num, hsn->>'hsn_sc' hsn_sc,
 		(hsn->>'txval')::Numeric taxable_val, 
 		(hsn->>'camt')::Numeric cgst_amt, (hsn->>'samt')::Numeric sgst_amt,
 		(hsn->>'iamt')::Numeric igst_amt, (hsn->>'csamt')::Numeric cess_amt
	From tx.gstr2a a, jsonb_array_elements(jdata->'hsn'->'data') hsn
)
Select sum(taxable_val)
From inv_info
Where gstr2a_id = 5;

*/
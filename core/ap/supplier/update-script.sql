/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
/**
 * Author:  girishshenoy
 * Created: Sep 6, 2017
 */

/* For new json field is_ctp introduced, following sql update is required
Update ap.supplier
Set annex_info = jsonb_set(annex_info, '{satutory_details,is_ctp}', 'false'::jsonb, true)
Where annex_info->'satutory_details'->>'is_ctp' is Null

*/
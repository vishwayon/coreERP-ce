CREATE SEQUENCE sys.docview_id_seq;
ALTER TABLE sys.doc_view_log ALTER COLUMN doc_view_log_id SET NOT NULL;
ALTER TABLE sys.doc_view_log ALTER COLUMN doc_view_log_id SET DEFAULT nextval('sys.docview_id_seq');
ALTER SEQUENCE sys.docview_id_seq OWNED BY sys.doc_view_log.doc_view_log_id;
BEGIN;
LOCK TABLE sys.doc_view_log IN EXCLUSIVE MODE;
SELECT setval('sys.docview_id_seq', COALESCE((SELECT MAX(doc_view_log_id)+1 FROM sys.doc_view_log), 1), false);
COMMIT;

?==?
CREATE SEQUENCE sys.rptview_id_seq;
ALTER TABLE sys.doc_print_log ALTER COLUMN doc_print_log_id SET NOT NULL;
ALTER TABLE sys.doc_print_log ALTER COLUMN doc_print_log_id SET DEFAULT nextval('sys.rptview_id_seq');
ALTER SEQUENCE sys.rptview_id_seq OWNED BY sys.doc_print_log.doc_print_log_id;
BEGIN;
LOCK TABLE sys.doc_print_log IN EXCLUSIVE MODE;
SELECT setval('sys.rptview_id_seq', COALESCE((SELECT MAX(doc_print_log_id)+1 FROM sys.doc_print_log), 1), false);
COMMIT;

?==?

-- 03 Sep, 2018 query to update annex_info for branch table
update sys.branch
set annex_info = jsonb_set(annex_info, '{has_str_qc}', ('false')::jsonb, true)
Where annex_info->>'has_str_qc' is null;

update sys.branch
set annex_info = jsonb_set(annex_info, '{has_lot_alloc}', ('false')::jsonb, true)
Where annex_info->>'has_lot_alloc' is null;

?==?
Update sys.doc_wf
set finyear = '1920'
where doc_id ~ '^[A-Z0-9]{2,4}19';

?==?
Update sys.doc_wf
set finyear = '1819'
where doc_id ~ '^[A-Z0-9]{2,4}18';

?==?
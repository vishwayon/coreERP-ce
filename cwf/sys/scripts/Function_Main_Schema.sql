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
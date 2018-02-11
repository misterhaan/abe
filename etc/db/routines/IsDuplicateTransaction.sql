-- Look for a duplicate transaction.  The account ID must match.
-- Returns 'extid' if the account ID and external ID match an existing transaction.
-- Returns 'amt,date' if the account ID, amount, and date match an existing transaction.
-- Returns null if no match is found.
create function IsDuplicateTransaction(acctid tinyint unsigned, extid varchar(32), amount decimal(8,2), posted date)
returns varchar(8)
begin
	if not isnull(extid) and not extid='' and exists(select id from transactions as t where t.account=acctid and t.extid=extid) then
		return 'extid';
	elseif exists(select id from transactions as t where t.account=acctid and t.amount=amount and t.posted=posted) then
		return 'amt,date';
	end if;
	return null;
end

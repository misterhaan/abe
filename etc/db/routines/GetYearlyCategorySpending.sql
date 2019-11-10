create procedure GetYearlyCategorySpending (in oldest date) begin
	if oldest is null then
		set oldest = curdate();
		set oldest = concat(year(oldest) - 10, '-01-01');
	end if;

select year as displaydate, concat(year, '-01-01') as datestart, concat(year, '-12-31') as dateend, amount, catid, coalesce(catname, '(uncategorized)') as catname, groupid, coalesce(groupname, '(ungrouped)') as groupname
	from spending_yearly
	where year>=year(oldest)
	order by year, groupname, catname;
end

create procedure GetMonthlyCategorySpending (in oldest date) begin
	if oldest is null then
		set oldest = curdate();
		set oldest = concat(year(oldest) - 1, '-', month(oldest), '-01');
	end if;

select date_format(month, '%b %Y') as displaydate, date_format(month, '%Y-%m-01') as datestart, last_day(month) as dateend, amount, catid, coalesce(catname, '(uncategorized)') as catname, groupid, coalesce(groupname, '(ungrouped)') as groupname
	from spending_monthly
	where month>=oldest
	order by month, groupname, catname;
end

create procedure GetMonthlyCategoryAverage () begin
	declare newest, oldest date;
	set newest = GetLastFullMonth();
	set oldest = date_sub(date_format(newest, '%Y-%m-01'), interval 1 year);
	select round(sum(amount) / 12, 2) as amount, catid, catname, groupid, groupname
	from spending_monthly
	where month between oldest and newest
	group by catid
	order by groupname, catname;
end

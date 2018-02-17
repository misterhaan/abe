create procedure GetMonthlyCategorySpending (in oldest date) begin
	if oldest is null then
		set oldest = curdate();
		set oldest = concat(year(oldest) - 1, '-', month(oldest), '-00');
	end if;

select min(a.displaydate) as displaydate, concat(a.sortdate, '-01') as datestart, last_day(concat(a.sortdate, '-01')) as dateend, sum(a.amount) as amount, c.id as catid, coalesce(c.name, '(uncategorized)') as catname, g.id as groupid, g.name as groupname
	from (select min(date_format(posted, '%b %Y')) as displaydate, min(date_format(posted, '%Y-%m')) as sortdate, sum(amount) as amount, category
		from transactions where splitcat=0 and posted>oldest group by year(posted), month(posted), category
	union select min(date_format(t.posted, '%b %Y')) as displaydate, min(date_format(t.posted, '%Y-%m')) as sortdate, sum(s.amount) as amount, s.category
		from splitcats as s left join transactions as t on t.id=s.transaction where t.posted>oldest group by year(t.posted), month(t.posted), category) as a
	left join categories as c on c.id=a.category
	left join category_groups as g on g.id=c.grp
	where amount!=0 group by a.sortdate, c.id;
end

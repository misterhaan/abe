create procedure GetYearlyCategorySpending (in oldest date) begin
	if oldest is null then
		set oldest = curdate();
		set oldest = concat(year(oldest) - 10, '-00-00');
	end if;

select min(a.displaydate) as displaydate, concat(a.sortdate, '-01-01') as datestart, concat(a.sortdate, '-12-31') as dateend, sum(a.amount) as amount, c.id as catid, coalesce(c.name, '(uncategorized)') as catname, p.id as parentid, p.name as parentname
	from (select min(year(posted)) as displaydate, min(year(posted)) as sortdate, sum(amount) as amount, category
		from transactions where splitcat=0 and posted>oldest group by year(posted), category
	union select min(year(t.posted)) as displaydate, min(year(t.posted)) as sortdate, sum(s.amount) as amount, s.category
		from splitcats as s left join transactions as t on t.id=s.transaction where t.posted>oldest group by year(t.posted), category) as a
	left join categories as c on c.id=a.category
	left join categories as p on p.id=c.parent
	where amount!=0 group by a.sortdate, c.id;
end

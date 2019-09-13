create view spending_yearly as
select year, sum(a.amount) as amount, c.id as catid, c.name as catname, g.id as groupid, g.name as groupname
	from (select year(posted) as year, sum(amount) as amount, category
		from transactions where splitcat=0 group by year, category
	union all select year(t.posted) as year, sum(s.amount) as amount, s.category
		from splitcats as s left join transactions as t on t.id=s.transaction group by year, category) as a
	left join categories as c on c.id=a.category
	left join category_groups as g on g.id=c.grp
	where amount!=0 group by a.year, c.id;
	
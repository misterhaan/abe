create view spending_monthly as
select month, sum(a.amount) as amount, c.id as catid, c.name as catname, g.id as groupid, g.name as groupname
	from (select str_to_date(concat(year(posted), '-', month(posted), '-00'), '%Y-%m-%d') as month, sum(amount) as amount, category
		from transactions where splitcat=0 group by month, category
	union all select str_to_date(concat(year(t.posted), '-', month(t.posted), '-00'), '%Y-%m-%d') as month, sum(s.amount) as amount, s.category
		from splitcats as s left join transactions as t on t.id=s.transaction group by month, category) as a
	left join categories as c on c.id=a.category
	left join category_groups as g on g.id=c.grp
	where amount!=0 group by a.month, c.id;

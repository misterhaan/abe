create procedure GetBudget(in month date) begin
	select s.groupname, s.catname, s.catid, ifnull(b.planned, 0) as planned, ifnull(b.actual, 0) as actual, -s.amount as amount
	from spending_monthly as s
		left join budget_categories as b on s.month=b.month and s.catid=b.category
	where s.month=month

	union all

	select g.name as groupname, c.name as catname, b.category as catid, b.planned, b.actual, 0 as amount
	from budget_categories as b
		left join categories as c on c.id=b.category
		left join category_groups as g on g.id=c.grp
		left join spending_monthly as s on s.month=b.month and s.catid=b.category
	where b.month=month and s.catid is null

	order by groupname, catname;
end

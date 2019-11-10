create procedure GetTransactions (in maxcount smallint unsigned, in oldest date, in oldid int unsigned, in accountids varchar(64), in categoryids varchar(255), in daterangestart date, in daterangeend date, in minamount decimal(8,2), in namesearch varchar(64)) begin

	if oldest is null then
		set oldest = '9999-12-31';
	end if;

	if oldid is null then
		set oldid = 0;
	end if;

	if accountids is not null then
		if left(accountids, 1) != ',' then
			set accountids = concat(',', accountids);
		end if;
		if right(accountids, 1) != ',' then
			set accountids = concat(accountids, ',');
		end if;
	end if;

	if categoryids is not null then
		if left(categoryids, 1) != ',' then
			set categoryids = concat(',', categoryids);
		end if;
		if right(categoryids, 1) != ',' then
			set categoryids = concat(categoryids, ',');
		end if;
	end if;

	if daterangestart is null then
		set daterangestart = '1000-01-01';
	end if;

	if daterangeend is null then
		set daterangeend = '9999-12-31';
	end if;

	select t.id, t.posted, t.transdate, at.class as acctclass, a.name as acctname, t.name, c.name as category, t.amount, t.splitcat, group_concat(scc.name order by abs(sc.amount) desc separator '\n') as sc_names, group_concat(sc.amount order by abs(sc.amount) desc separator '\n') as sc_amounts, t.notes, t.city, t.state, t.zip
		from transactions as t
			left join categories as c on c.id=t.category
			left join accounts as a on a.id=t.account
			left join account_types as at on at.id=a.account_type
			left join splitcats as sc on sc.transaction=t.id
			left join categories as scc on scc.id=sc.category
		where
			(accountids is null or instr(accountids, concat(',', t.account, ',')))
			and (categoryids is null
				or t.splitcat=0 and instr(categoryids, concat(',', ifnull(t.category, 0), ','))
				or t.splitcat=1 and categoryids!=',0,' and exists (select 1 from splitcats as isc where isc.transaction=t.id and instr(categoryids, concat(',', isc.category, ','))))
			and (t.posted>=daterangestart and t.posted<=daterangeend)
			and (minamount is null or t.amount>=minamount or t.amount<=-minamount)
			and (namesearch is null or instr(t.name, namesearch))
			and (t.posted<oldest or t.posted=oldest and t.id<oldid)
		group by t.id
		order by t.posted desc, t.id desc
		limit maxcount;
end

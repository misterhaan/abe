create table budget_funds (
	month date not null comment 'stored as YYYY-MM-00 because the day isn’t used',
	fund smallint unsigned not null,
	foreign key(fund) references funds(id) on update cascade on delete cascade,
	primary key(month, fund),
	planned decimal(10,2) not null default 0,
	actual decimal(10,2) not null default 0
);

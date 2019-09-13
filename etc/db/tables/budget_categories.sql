create table budget_categories (
	month date not null comment 'stored as YYYY-MM-00 because the day isn’t used',
	category tinyint unsigned not null,
	foreign key(category) references categories(id) on update cascade on delete cascade,
	primary key(month, category),
	planned decimal(10,2) not null default 0,
	actual decimal(10,2) not null default 0
);

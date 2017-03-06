create table splitcats (
	transaction int unsigned not null,
	foreign key(transaction) references transactions(id) on update cascade on delete cascade,
	category tinyint unsigned not null,
	foreign key(category) references categories(id) on update cascade on delete cascade,
	primary key(transaction,category),
	amount decimal(8,2) comment 'amount of the transaction to apply to the category (all amounts for a transaction must total to the transaction amount)'
);

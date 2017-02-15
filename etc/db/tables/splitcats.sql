create table splitcats (
	id int unsigned primary key auto_increment,
	transaction int unsigned not null,
	foreign key(transaction) references transactions(id) on update cascade on delete cascade,
	amount decimal(8,2) comment 'amount of the transaction to apply to the category (all amounts for a transaction must total to the transaction amount)',
	category tinyint unsigned not null,
	foreign key(category) references categories(id) on update cascade on delete cascade
);

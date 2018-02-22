create table funds (
	id smallint unsigned not null auto_increment primary key,
	sort tinyint unsigned not null,
	name varchar(32) not null,
	target decimal(10,2) not null,
	balance decimal(10,2) not null default 0
);

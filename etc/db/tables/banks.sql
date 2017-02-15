create table banks (
	id smallint unsigned primary key auto_increment,
	class varchar(32) comment 'name of the class that handles transaction import from this bank',
	unique(class),
	name varchar(64),
	unique(name),
	url varchar(128) comment 'url for downloading transactions from this bank, or their login page'
);

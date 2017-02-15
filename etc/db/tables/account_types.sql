create table account_types (
	id tinyint unsigned primary key auto_increment,
	name varchar(32),
	unique(name),
	class varchar(32) comment 'css class for this type of account, which determines the icon used'
);

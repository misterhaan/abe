create table category_groups (
	id tinyint unsigned primary key auto_increment,
	name varchar(24),
	unique(name)
);
